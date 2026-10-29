<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';
require_once __DIR__ . '/ThesisController.php';

// =====================================================================================
// InternshipController: phần mở rộng của THỰC TẬP
// (1) Phân bổ sinh viên vào vị trí thực tập theo nguyện vọng (Gale-Shapley sinh viên đề xuất, sức chứa = số lượng tuyển còn lại).
// (2) Sinh viên xin rút / đổi đơn vị thực tập, khoa duyệt.
// (3) Đề cương thực tập theo tuần do sinh viên lập.
// (4) Cảnh báo bổ sung chạy hằng ngày (nhiệm vụ quá hạn, sắp hết hạn báo cáo, sắp kết thúc mà chưa đánh giá).
// =====================================================================================

const INTERNSHIP_MAX_PREFERENCES = 5;
const CHANGE_REQUEST_LABELS = ['withdraw' => 'Rút khỏi đơn vị', 'change' => 'Đổi đơn vị thực tập'];

// Vị trí còn nhận (tin đã duyệt, còn hạn, doanh nghiệp hoạt động) và số chỗ còn lại.
function internship_open_positions(): array
{
    return page_all(
        'SELECT p.id, p.title, p.requirements, p.location, p.quantity, c.company_name, c.id AS company_id,
                p.quantity - (SELECT COUNT(*) FROM internships i WHERE i.position_id = p.id AND i.status IN (\'planned\', \'active\', \'completed\')) AS seats_left
         FROM positions p JOIN companies c ON c.id = p.company_id
         WHERE p.status = \'open\' AND c.status = \'active\' AND (p.deadline IS NULL OR p.deadline >= CURDATE()) ORDER BY c.company_name, p.title'
    );
}

// Chạy thử phân bổ cho một đợt thực tập: lưu vào matching_runs (algorithm = internship_gs) để xem trước rồi công bố.
function internship_allocation_run(array $batch, int $userId): int
{
    $positions = [];
    foreach (internship_open_positions() as $row) {
        if ((int) $row['seats_left'] > 0) {
            $positions[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'title' => $row['company_name'] . ' · ' . $row['title'],
                'capacity' => (int) $row['seats_left'],
                'lecturer_id' => null,
                'terms' => array_unique(page_recommendation_tokens($row['title'] . ' ' . $row['requirements'])),
                'ranking' => [],
            ];
        }
    }
    $applicants = [];
    $registered = [];
    foreach (page_all(
        'SELECT p.student_id, p.position_id, p.created_at, s.gpa, CONCAT_WS(\' \', s.skills, s.interests) AS skills, s.student_code, u.full_name
         FROM internship_preferences p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id
         WHERE p.batch_id = ? AND NOT EXISTS (SELECT 1 FROM internships i WHERE i.student_id = p.student_id AND i.status IN (\'planned\', \'active\'))
         ORDER BY p.student_id, p.rank_no',
        [(int) $batch['id']]
    ) as $row) {
        $key = 's' . $row['student_id'];
        $applicants[$key] ??= ['key' => $key, 'name' => $row['full_name'] . ' · ' . $row['student_code'], 'student_ids' => [(int) $row['student_id']], 'size' => 1, 'prefs' => [], 'gpa' => (float) $row['gpa'], 'registered' => (int) strtotime((string) $row['created_at']), 'terms' => array_unique(page_recommendation_tokens((string) $row['skills']))];
        if (isset($positions[(int) $row['position_id']])) {
            $applicants[$key]['prefs'][] = (int) $row['position_id'];
        }
        $registered[] = (int) strtotime((string) $row['created_at']);
    }
    if (!$applicants) {
        throw new DomainException('Chưa có sinh viên nào đăng ký nguyện vọng thực tập trong đợt này.');
    }
    $params = matching_default_params();
    $params['use_lecturer_rankings'] = 0;
    $priority = [];
    foreach ($applicants as $key => $applicant) {
        foreach ($applicant['prefs'] as $positionId) {
            $priority[$positionId][$key] = matching_priority($applicant, $positions[$positionId], $params, min($registered), max($registered));
        }
    }
    $in = ['topics' => $positions, 'applicants' => $applicants, 'priority' => $priority, 'fixed' => [], 'params' => $params];
    $assignment = matching_gs_student($in);
    $metrics = matching_metrics($in, $assignment);
    $result = [];
    foreach ($applicants as $key => $applicant) {
        $positionId = $assignment[$key] ?? null;
        $result[] = ['key' => $key, 'name' => $applicant['name'], 'student_ids' => $applicant['student_ids'], 'topic_id' => $positionId, 'topic_title' => $positionId ? $positions[$positionId]['title'] : null, 'rank' => $positionId ? array_search($positionId, $applicant['prefs'], true) + 1 : null, 'prefs' => count($applicant['prefs'])];
    }
    database()->prepare('INSERT INTO matching_runs (batch_id, algorithm, params, result, metrics, created_by) VALUES (?, \'internship_gs\', ?, ?, ?, ?)')
        ->execute([(int) $batch['id'], json_encode($params), json_encode($result, JSON_UNESCAPED_UNICODE), json_encode($metrics, JSON_UNESCAPED_UNICODE), $userId]);
    return (int) database()->lastInsertId();
}

// Công bố phân bổ: mỗi sinh viên được xếp có hồ sơ "được nhận" và kỳ thực tập thuộc đợt; báo sinh viên và doanh nghiệp.
function internship_allocation_publish(array $run, array $user): int
{
    $result = json_decode((string) $run['result'], true) ?: [];
    $created = 0;
    $notices = [];
    database_transaction(static function (PDO $connection) use ($run, $result, $user, &$created, &$notices): void {
        foreach ($result as $row) {
            if (empty($row['topic_id'])) {
                continue;
            }
            $position = page_one('SELECT p.*, p.quantity - (SELECT COUNT(*) FROM internships i WHERE i.position_id = p.id AND i.status IN (\'planned\', \'active\', \'completed\')) AS seats_left FROM positions p WHERE p.id = ? FOR UPDATE', [(int) $row['topic_id']]);
            $studentId = (int) $row['student_ids'][0];
            if (!$position || (int) $position['seats_left'] <= 0 || page_count('SELECT COUNT(*) FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\')', [$studentId]) > 0) {
                continue;
            }
            $connection->prepare('INSERT INTO applications (student_id, position_id, status, reviewed_at, reviewed_by, note) VALUES (?, ?, \'accepted\', NOW(), ?, \'Phân bổ theo nguyện vọng\') ON DUPLICATE KEY UPDATE status = \'accepted\', reviewed_at = NOW(), reviewed_by = VALUES(reviewed_by), note = VALUES(note)')
                ->execute([$studentId, (int) $position['id'], (int) $user['id']]);
            $start = date('Y-m-d', max(time(), (int) strtotime((string) ($position['deadline'] ?? 'today'))));
            $lecturerId = $connection->query('SELECT l.id FROM lecturers l JOIN users lu ON lu.id = l.user_id WHERE lu.status = \'active\' ORDER BY (SELECT COUNT(*) FROM internships i WHERE i.lecturer_id = l.id AND i.status IN (\'planned\', \'active\')), l.id LIMIT 1')->fetchColumn() ?: null;
            // Sinh viên từng có kỳ đã hủy ở đúng vị trí và ngày bắt đầu này (khóa duy nhất): mở lại kỳ đó thay vì tạo mới.
            $cancelled = page_one('SELECT id FROM internships WHERE student_id = ? AND position_id = ? AND start_date = ? AND status = \'cancelled\'', [$studentId, (int) $position['id'], $start]);
            if ($cancelled) {
                $connection->prepare('UPDATE internships SET status = \'planned\', lecturer_id = ?, batch_id = ?, end_date = ?, actual_end_date = NULL, final_score = NULL WHERE id = ?')
                    ->execute([$lecturerId, (int) $run['batch_id'], date('Y-m-d', strtotime($start . ' +10 weeks')), (int) $cancelled['id']]);
            } else {
                $connection->prepare('INSERT INTO internships (student_id, company_id, position_id, lecturer_id, batch_id, start_date, end_date, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, \'planned\', \'Kỳ thực tập do khoa phân bổ theo nguyện vọng.\')')
                    ->execute([$studentId, (int) $position['company_id'], (int) $position['id'], $lecturerId, (int) $run['batch_id'], $start, date('Y-m-d', strtotime($start . ' +10 weeks'))]);
            }
            $created++;
            $notices[] = [$studentId, (int) $position['company_id'], (string) $position['title'], (int) ($row['rank'] ?? 0)];
        }
        $connection->prepare('UPDATE matching_runs SET status = \'published\', published_at = NOW() WHERE id = ?')->execute([(int) $run['id']]);
    });
    foreach ($notices as [$studentId, $companyId, $title, $rank]) {
        notify_student($studentId, 'placement', 'Kết quả phân bổ thực tập', 'Bạn được xếp vào vị trí ' . $title . ($rank ? ' (nguyện vọng ' . $rank . ')' : '') . '.', 'student/placement', true);
        notify_company($companyId, 'interns', 'Khoa phân bổ thực tập sinh', 'Vị trí ' . $title . ' có thêm thực tập sinh do khoa phân bổ.', 'company/inters', true);
    }
    return $created;
}

// Cảnh báo bổ sung chạy trong reminders_run_daily().
function internship_daily_warnings(): void
{
    // Nhiệm vụ quá hạn 3 ngày chưa nộp: nhắc sinh viên và doanh nghiệp.
    foreach (page_all('SELECT t.id, t.title, t.due_date, s.user_id AS student_user, i.company_id FROM tasks t JOIN internships i ON i.id = t.internship_id JOIN students s ON s.id = t.assigned_to WHERE t.status IN (\'todo\', \'in_progress\') AND t.due_date = DATE_SUB(CURDATE(), INTERVAL 3 DAY) AND i.status = \'active\'') as $task) {
        notify_user((int) $task['student_user'], 'tasks', 'Nhiệm vụ quá hạn 3 ngày', $task['title'], 'student/tasks', true);
        notify_company((int) $task['company_id'], 'tasks', 'Nhiệm vụ quá hạn chưa hoàn thành', $task['title'], 'company/tasks');
    }
    // Hạn báo cáo giữa kỳ / cuối kỳ của đợt thực tập trong 2 ngày tới mà sinh viên chưa nộp.
    foreach (page_all('SELECT m.batch_id, m.kind, m.title, m.ends_at FROM batch_milestones m JOIN batches b ON b.id = m.batch_id WHERE b.batch_type = \'internship\' AND b.status = \'open\' AND m.kind IN (\'report_1\', \'report_final\') AND m.ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)') as $deadline) {
        $type = array_search($deadline['kind'], INTERNSHIP_REPORT_PHASES, true);
        foreach (page_all('SELECT s.user_id FROM internships i JOIN students s ON s.id = i.student_id WHERE i.batch_id = ? AND i.status IN (\'planned\', \'active\') AND NOT EXISTS (SELECT 1 FROM reports r WHERE r.internship_id = i.id AND r.report_type = ? AND r.status <> \'draft\')', [(int) $deadline['batch_id'], $type]) as $student) {
            notify_user((int) $student['user_id'], 'reports', 'Sắp hết hạn: ' . $deadline['title'], 'Hạn chót ' . thesis_datetime_label($deadline['ends_at']) . '.', 'student/reports', true);
        }
    }
    // Kỳ thực tập kết thúc trong 7 ngày mà doanh nghiệp / giảng viên chưa gửi đánh giá.
    foreach (page_all('SELECT i.id, i.company_id, i.lecturer_id, u.full_name, i.end_date,
            (SELECT COUNT(*) FROM evaluations e WHERE e.internship_id = i.id AND e.evaluator_type = \'company\' AND e.status = \'submitted\') AS company_done,
            (SELECT COUNT(*) FROM evaluations e WHERE e.internship_id = i.id AND e.evaluator_type = \'lecturer\' AND e.status = \'submitted\') AS lecturer_done
         FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id WHERE i.status = \'active\' AND i.end_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)') as $ending) {
        if (!(int) $ending['company_done']) {
            notify_company((int) $ending['company_id'], 'evaluations', 'Sắp kết thúc thực tập, chưa đánh giá', $ending['full_name'] . ' kết thúc ngày ' . date('d/m/Y', (int) strtotime($ending['end_date'])) . '.', 'company/evaluations', true);
        }
        if (!(int) $ending['lecturer_done'] && $ending['lecturer_id']) {
            notify_lecturer((int) $ending['lecturer_id'], 'evaluations', 'Sắp kết thúc thực tập, chưa đánh giá', $ending['full_name'], 'lecturer/evaluations');
        }
    }
}

// Bình luận theo báo cáo / nhật ký: [item_id => [bình luận...]].
function item_comments(string $type, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $byItem = [];
    foreach (page_all('SELECT c.*, u.full_name FROM item_comments c JOIN users u ON u.id = c.user_id WHERE c.item_type = ? AND c.item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY c.created_at', array_merge([$type], $ids)) as $comment) {
        $byItem[(int) $comment['item_id']][] = $comment;
    }
    return $byItem;
}

function report_versions(array $reportIds): array
{
    $reportIds = array_values(array_filter(array_map('intval', $reportIds)));
    if (!$reportIds) {
        return [];
    }
    $byReport = [];
    foreach (page_all('SELECT * FROM report_versions WHERE report_id IN (' . implode(',', array_fill(0, count($reportIds), '?')) . ') ORDER BY version DESC', $reportIds) as $version) {
        $byReport[(int) $version['report_id']][] = $version;
    }
    return $byReport;
}

// Dữ liệu bổ sung cho trang Hồ sơ thực tập (sinh viên) và Thực tập tự tìm (quản trị).
function internship_screen_extras(string $screen, array $data, array $user): array
{
    $studentId = (int) ($user['student_id'] ?? 0);
    if ($screen === 'student/placement') {
        $batch = internship_batch_for_student($studentId);
        $data['allocation_batch'] = $batch && (int) $batch['allocation_mode'] ? $batch : null;
        if ($data['allocation_batch']) {
            $data['allocation_open'] = batch_phase_state($batch, 'registration') === 'open';
            $data['allocation_positions'] = array_values(array_filter(internship_open_positions(), static fn($row) => (int) $row['seats_left'] > 0));
            $data['my_preferences'] = array_column(page_all('SELECT rank_no, position_id FROM internship_preferences WHERE batch_id = ? AND student_id = ? ORDER BY rank_no', [(int) $batch['id'], $studentId]), 'position_id', 'rank_no');
        }
        $data['change_requests'] = page_all('SELECT r.*, v.company_name FROM internship_change_requests r JOIN v_student_internships v ON v.internship_id = r.internship_id WHERE r.student_id = ? ORDER BY r.created_at DESC', [$studentId]);
        foreach ($data['internships'] as &$internship) {
            $internship['weekly_plans'] = page_all('SELECT * FROM internship_weekly_plans WHERE internship_id = ? ORDER BY week_no', [(int) $internship['internship_id']]);
        }
        unset($internship);
    }
    if ($screen === 'admin/placements') {
        $data['change_requests'] = page_all('SELECT r.*, v.student_name, v.student_code, v.company_name, v.position_title FROM internship_change_requests r JOIN v_student_internships v ON v.internship_id = r.internship_id WHERE (@it_faculty IS NULL OR r.student_id IN (SELECT id FROM students WHERE faculty = @it_faculty)) ORDER BY r.status = \'pending\' DESC, r.created_at DESC LIMIT 100');
        $data['allocation_batches'] = array_values(array_filter(thesis_batches('internship'), static fn($row) => (int) $row['allocation_mode'] && $row['status'] === 'open'));
        $selected = thesis_query_int('batch');
        $data['allocation_batch'] = null;
        foreach ($data['allocation_batches'] as $row) {
            if (!$data['allocation_batch'] || (int) $row['id'] === $selected) {
                $data['allocation_batch'] = $row;
            }
        }
        if ($data['allocation_batch']) {
            $batchId = (int) $data['allocation_batch']['id'];
            $data['allocation_stats'] = ['students' => page_count('SELECT COUNT(DISTINCT student_id) FROM internship_preferences WHERE batch_id = ?', [$batchId]), 'positions' => count(internship_open_positions())];
            $run = page_one('SELECT * FROM matching_runs WHERE batch_id = ? AND algorithm = \'internship_gs\' AND status <> \'discarded\' ORDER BY id DESC LIMIT 1', [$batchId]);
            if ($run) {
                $run['result'] = json_decode((string) $run['result'], true) ?: [];
                $run['metrics'] = json_decode((string) $run['metrics'], true) ?: [];
            }
            $data['allocation_run'] = $run;
        }
    }
    return $data;
}

// Thao tác của phần mở rộng thực tập; null nếu không thuộc phần này.
function internship_handle_action(string $action, array $input, array $files): ?array
{
    $connection = database();
    $done = static fn(string $route, string $message): array => ['route' => $route, 'message' => $message];
    switch ($action) {
        // Sinh viên xếp nguyện vọng vị trí thực tập (đợt phân bổ theo nguyện vọng, trong mốc ứng tuyển).
        case 'internship_preference_save':
            $user = action_user(['student']);
            $batch = internship_require_registration((int) $user['student_id']);
            if (!$batch || !(int) $batch['allocation_mode']) {
                throw new DomainException('Đợt thực tập hiện không phân bổ theo nguyện vọng.');
            }
            $chosen = array_values(array_unique(array_filter(array_map('intval', is_array($input['pref'] ?? null) ? $input['pref'] : []))));
            $open = array_map('intval', array_column(internship_open_positions(), 'id'));
            if (array_diff($chosen, $open)) {
                throw new DomainException('Có vị trí không còn nhận sinh viên.');
            }
            database_transaction(static function (PDO $connection) use ($batch, $user, $chosen): void {
                $connection->prepare('DELETE FROM internship_preferences WHERE batch_id = ? AND student_id = ?')->execute([(int) $batch['id'], (int) $user['student_id']]);
                $insert = $connection->prepare('INSERT INTO internship_preferences (batch_id, student_id, position_id, rank_no) VALUES (?, ?, ?, ?)');
                foreach (array_slice($chosen, 0, INTERNSHIP_MAX_PREFERENCES) as $index => $positionId) {
                    $insert->execute([(int) $batch['id'], (int) $user['student_id'], $positionId, $index + 1]);
                }
            });
            return $done('student/placement', $chosen ? 'Đã lưu ' . min(count($chosen), INTERNSHIP_MAX_PREFERENCES) . ' nguyện vọng thực tập.' : 'Đã xóa nguyện vọng.');

        case 'internship_allocation_run':
            $user = action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            if ($batch['batch_type'] !== 'internship' || !(int) $batch['allocation_mode']) {
                throw new DomainException('Đợt này không phân bổ theo nguyện vọng.');
            }
            internship_allocation_run($batch, (int) $user['id']);
            return $done('admin/placements&batch=' . (int) $batch['id'], 'Đã chạy thử phân bổ. Kiểm tra kết quả rồi bấm Công bố.');

        case 'internship_allocation_publish':
            $user = action_user(['admin']);
            $run = page_one('SELECT * FROM matching_runs WHERE id = ? AND algorithm = \'internship_gs\' AND status = \'preview\'', [thesis_int($input, 'run_id')]);
            if (!$run) {
                throw new DomainException('Không tìm thấy lần phân bổ chưa công bố.');
            }
            batch_require_unlocked(thesis_batch((int) $run['batch_id']));
            $created = internship_allocation_publish($run, $user);
            return $done('admin/placements&batch=' . (int) $run['batch_id'], 'Đã công bố phân bổ: tạo ' . $created . ' kỳ thực tập.');

        // Sinh viên xin rút hoặc đổi đơn vị thực tập; khoa duyệt.
        case 'change_request_submit':
            $user = action_user(['student']);
            $internship = action_internship(thesis_int($input, 'internship_id'), $user);
            action_require_open_internship($internship);
            $type = ($input['request_type'] ?? '') === 'change' ? 'change' : 'withdraw';
            $reason = action_value($input, 'reason', 3000);
            if ($reason === '') {
                throw new DomainException('Hãy nêu lý do.');
            }
            if (page_count('SELECT COUNT(*) FROM internship_change_requests WHERE internship_id = ? AND status = \'pending\'', [(int) $internship['id']]) > 0) {
                throw new DomainException('Bạn đã có một yêu cầu đang chờ khoa xử lý.');
            }
            $connection->prepare('INSERT INTO internship_change_requests (internship_id, student_id, request_type, reason) VALUES (?, ?, ?, ?)')->execute([(int) $internship['id'], (int) $user['student_id'], $type, $reason]);
            foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\'') as $admin) {
                notify_user((int) $admin['id'], 'placements', 'Yêu cầu ' . mb_strtolower(CHANGE_REQUEST_LABELS[$type], 'UTF-8'), $user['full_name'], 'admin/placements');
            }
            if ($internship['lecturer_id']) {
                notify_lecturer((int) $internship['lecturer_id'], 'students', 'Sinh viên xin ' . mb_strtolower(CHANGE_REQUEST_LABELS[$type], 'UTF-8'), $user['full_name'] . ': ' . $reason, 'lecturer/students');
            }
            return $done('student/placement', 'Đã gửi yêu cầu. Khoa sẽ xem xét và phản hồi.');

        case 'change_request_handle':
            $user = action_user(['admin']);
            $request = page_one('SELECT r.*, i.company_id, i.lecturer_id, i.status AS internship_status FROM internship_change_requests r JOIN internships i ON i.id = r.internship_id WHERE r.id = ? AND r.status = \'pending\'', [thesis_int($input, 'request_id')]);
            if (!$request) {
                throw new DomainException('Không tìm thấy yêu cầu đang chờ.');
            }
            $approve = ($input['decision'] ?? '') === 'approve';
            $response = action_value($input, 'response', 2000);
            database_transaction(static function (PDO $connection) use ($request, $approve, $response, $user): void {
                $connection->prepare('UPDATE internship_change_requests SET status = ?, response = ?, handled_by = ?, handled_at = NOW() WHERE id = ?')->execute([$approve ? 'approved' : 'rejected', $response ?: null, (int) $user['id'], (int) $request['id']]);
                if ($approve) {
                    // Duyệt: hủy kỳ thực tập hiện tại (lưu lịch sử); với "đổi đơn vị", sinh viên được ứng tuyển / khai báo lại.
                    $connection->prepare('UPDATE internships SET status = \'cancelled\', actual_end_date = CURDATE() WHERE id = ?')->execute([(int) $request['internship_id']]);
                    $connection->prepare('INSERT INTO internship_status_history (internship_id, old_status, new_status, changed_by, note) VALUES (?, ?, \'cancelled\', ?, ?)')
                        ->execute([(int) $request['internship_id'], $request['internship_status'], (int) $user['id'], CHANGE_REQUEST_LABELS[$request['request_type']] . ': ' . $request['reason']]);
                }
            });
            notify_student((int) $request['student_id'], 'placement', 'Yêu cầu ' . mb_strtolower(CHANGE_REQUEST_LABELS[$request['request_type']], 'UTF-8') . ($approve ? ' đã được duyệt' : ' bị từ chối'), $response ?: ($approve && $request['request_type'] === 'change' ? 'Bạn có thể ứng tuyển hoặc khai báo đơn vị mới.' : ''), 'student/placement', true);
            if ($approve) {
                notify_company((int) $request['company_id'], 'interns', 'Sinh viên kết thúc thực tập tại đơn vị', 'Khoa đã duyệt yêu cầu ' . mb_strtolower(CHANGE_REQUEST_LABELS[$request['request_type']], 'UTF-8') . ' của một thực tập sinh.', 'company/inters', true);
            }
            return $done('admin/placements', $approve ? 'Đã duyệt yêu cầu; kỳ thực tập cũ đã được hủy.' : 'Đã từ chối yêu cầu.');

        // Bình luận trên báo cáo / nhật ký: người có quyền với kỳ thực tập (sinh viên, giảng viên, doanh nghiệp, quản trị).
        case 'item_comment':
            $user = action_user(['student', 'company', 'lecturer', 'admin']);
            $type = ($input['item_type'] ?? '') === 'diary' ? 'diary' : 'report';
            $item = page_one('SELECT internship_id, student_id' . ($type === 'report' ? ', title' : ', diary_date AS title') . ' FROM ' . ($type === 'report' ? 'reports' : 'diaries') . ' WHERE id = ?', [thesis_int($input, 'item_id')]);
            if (!$item) {
                throw new DomainException('Không tìm thấy nội dung để bình luận.');
            }
            $internship = action_internship((int) $item['internship_id'], $user);
            $body = action_value($input, 'body', 3000);
            if ($body === '') {
                throw new DomainException('Hãy nhập nội dung bình luận.');
            }
            $connection->prepare('INSERT INTO item_comments (item_type, item_id, user_id, body) VALUES (?, ?, ?, ?)')->execute([$type, thesis_int($input, 'item_id'), (int) $user['id'], $body]);
            // Báo cho những người còn lại của kỳ thực tập.
            $label = $type === 'report' ? 'báo cáo "' . $item['title'] . '"' : 'nhật ký ngày ' . date('d/m/Y', (int) strtotime((string) $item['title']));
            $section = $type === 'report' ? 'reports' : 'diaries';
            if ($user['role'] !== 'student') {
                notify_student((int) $internship['student_id'], $type === 'report' ? 'reports' : 'diary', 'Bình luận mới trên ' . $label, $user['full_name'] . ': ' . mb_strimwidth($body, 0, 150, '…', 'UTF-8'), 'student/' . ($type === 'report' ? 'reports' : 'diary'));
            }
            if ($user['role'] !== 'lecturer' && $internship['lecturer_id']) {
                notify_lecturer((int) $internship['lecturer_id'], $section, 'Bình luận mới trên ' . $label, $user['full_name'], 'lecturer/' . $section);
            }
            if ($user['role'] !== 'company' && $type === 'diary') {
                notify_company((int) $internship['company_id'], 'diaries', 'Bình luận mới trên ' . $label, $user['full_name'], 'company/diaries');
            }
            return $done($user['role'] . '/' . ['student' => $type === 'report' ? 'reports' : 'diary', 'lecturer' => $section, 'company' => 'diaries', 'admin' => 'internship'][$user['role']], 'Đã gửi bình luận.');

        // Đề cương thực tập theo tuần: sinh viên lập mục tiêu từng tuần, sau đó ghi kết quả.
        case 'weekly_plan_save':
            $user = action_user(['student']);
            $internship = action_internship(thesis_int($input, 'internship_id'), $user);
            action_require_open_internship($internship);
            $week = max(1, min(52, thesis_int($input, 'week_no')));
            $goals = action_value($input, 'goals', 3000);
            if ($goals === '') {
                $connection->prepare('DELETE FROM internship_weekly_plans WHERE internship_id = ? AND week_no = ?')->execute([(int) $internship['id'], $week]);
                return $done('student/placement', 'Đã xóa đề cương tuần ' . $week . '.');
            }
            $connection->prepare('INSERT INTO internship_weekly_plans (internship_id, week_no, goals, result) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE goals = VALUES(goals), result = VALUES(result)')
                ->execute([(int) $internship['id'], $week, $goals, action_value($input, 'result', 3000) ?: null]);
            return $done('student/placement', 'Đã lưu đề cương tuần ' . $week . '.');
    }
    return null;
}
