<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';
require_once __DIR__ . '/ThesisController.php';
require_once __DIR__ . '/../config/zip.php';

// =====================================================================================
// AdminToolsController: các chức năng hỗ trợ dùng chung.
//   - Nhật ký hệ thống (audit log), nhắc hạn tự động hằng ngày.
//   - Thông báo chung theo đợt/lớp/vai trò, lịch chung (hạn nộp, lịch gặp, lịch bảo vệ, hạn nhiệm vụ).
//   - Thống kê, xuất CSV (mở bằng Excel) và biểu mẫu in/PDF, sao lưu & phục hồi dữ liệu.
//   - Nhập người dùng hàng loạt từ Excel (CSV/XLSX), doanh nghiệp tự đăng ký chờ duyệt.
//   - Thực tập mở rộng: sinh viên tự tìm nơi thực tập, giấy tờ, chuyên cần, sinh viên đánh giá đơn vị.
// =====================================================================================

const INTERNSHIP_DOC_TYPES = ['gioi_thieu' => 'Giấy giới thiệu', 'tiep_nhan' => 'Phiếu tiếp nhận', 'cam_ket' => 'Bản cam kết', 'xac_nhan' => 'Xác nhận của đơn vị', 'khac' => 'Giấy tờ khác'];
const ATTENDANCE_LABELS = ['present' => 'Có mặt', 'late' => 'Đi muộn', 'absent' => 'Vắng', 'excused' => 'Vắng có phép'];

// ---------------------------------------------------------------------------
// NHẬT KÝ HỆ THỐNG
// ---------------------------------------------------------------------------

// Ghi một dòng nhật ký: ai (người đăng nhập), làm gì (action), trên đối tượng nào (các trường *_id), lúc nào.
// Thao tác sửa một bản ghi cụ thể: [bảng, trường id trong form]. Nhật ký lưu giá trị trước / sau của bản ghi đó.
const AUDIT_TRACKED = [
    'position_save' => ['positions', 'position_id'], 'position_status' => ['positions', 'position_id'], 'position_reject' => ['positions', 'position_id'],
    'company_update' => ['companies', 'company_id'], 'company_status' => ['companies', 'company_id'],
    'user_status' => ['users', 'user_id'], 'user_access_save' => ['users', 'user_id'],
    'internship_update' => ['internships', 'internship_id'], 'mentor_assign' => ['internships', 'internship_id'],
    'batch_save' => ['batches', 'batch_id'], 'batch_status' => ['batches', 'batch_id'],
    'topic_save' => ['topics', 'topic_id'], 'topic_review' => ['topics', 'topic_id'],
    'thesis_staff_update' => ['theses', 'thesis_id'], 'thesis_assign_manual' => ['theses', 'thesis_id'],
    'lecturer_quota_save' => ['lecturers', 'lecturer_id'], 'placement_review' => ['self_placements', 'placement_id'],
    'application_review' => ['applications', 'application_id'], 'interview_schedule' => ['applications', 'application_id'],
    'diary_review' => ['diaries', 'diary_id'], 'diary_confirm' => ['diaries', 'diary_id'], 'report_review' => ['reports', 'report_id'],
    'rubric_save' => ['rubrics', 'rubric_id'], 'council_save' => ['councils', 'council_id'],
];

// Ảnh chụp bản ghi trước khi sửa (null nếu thao tác không thuộc danh sách theo dõi).
function audit_snapshot(string $action, array $input): ?array
{
    if (!isset(AUDIT_TRACKED[$action])) {
        return null;
    }
    [$table, $key] = AUDIT_TRACKED[$action];
    $id = (int) (filter_var($input[$key] ?? null, FILTER_VALIDATE_INT) ?: 0);
    if ($id <= 0) {
        return null;
    }
    return ['table' => $table, 'id' => $id, 'row' => page_one('SELECT * FROM `' . $table . '` WHERE id = ?', [$id]) ?: []];
}

// So sánh với bản ghi sau khi sửa: {trường: [cũ, mới]} (bỏ mật khẩu và mốc cập nhật).
function audit_changes(?array $snapshot): ?array
{
    if ($snapshot === null || !$snapshot['row']) {
        return null;
    }
    $after = page_one('SELECT * FROM `' . $snapshot['table'] . '` WHERE id = ?', [$snapshot['id']]) ?: [];
    $changes = [];
    foreach ($after as $field => $value) {
        if (in_array($field, ['password_hash', 'updated_at', 'google_sub'], true)) {
            continue;
        }
        $old = $snapshot['row'][$field] ?? null;
        if ((string) $old !== (string) $value) {
            $short = static fn($text) => $text === null ? null : mb_strimwidth((string) $text, 0, 120, '…', 'UTF-8');
            $changes[$field] = [$short($old), $short($value)];
        }
    }
    return $changes ?: null;
}

function audit_record(string $action, array $input = [], ?array $user = null, ?array $changes = null): void
{
    try {
        $user ??= authenticated_user();
        $details = [];
        $target = null;
        foreach ($input as $key => $value) {
            if (!is_string($key) || in_array($key, ['_csrf', 'action', 'password', 'password_confirmation'], true) || is_array($value)) {
                continue;
            }
            if (str_ends_with($key, '_id') || in_array($key, ['status', 'decision', 'role', 'batch_type', 'algorithm', 'stage', 'evaluator_role', 'audience', 'username', 'email'], true)) {
                $details[$key] = mb_substr((string) $value, 0, 80, 'UTF-8');
                if ($target === null && str_ends_with($key, '_id') && (string) $value !== '') {
                    $target = $key . '=' . (int) $value;
                }
            }
        }
        database()->prepare('INSERT INTO audit_logs (user_id, role, action, target, details, changes, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            $user['id'] ?? null,
            $user['role'] ?? null,
            mb_substr($action, 0, 60, 'UTF-8'),
            $target,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $error) {
        error_log('InternTrack audit failed: ' . $error->getMessage());
    }
}

function app_setting_set(string $key, ?string $value): void
{
    database()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$key, $value]);
}

function app_setting(string $key, ?string $default = null): ?string
{
    $row = page_one('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    return $row ? $row['setting_value'] : $default;
}

// Nhắc hạn tự động, chạy tối đa một lần mỗi ngày khi có người dùng mở trang (không cần cron).
function reminders_run_daily(): void
{
    try {
        $today = date('Y-m-d');
        $connection = database();
        $connection->prepare('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (\'reminders_last_run\', \'\')')->execute();
        $claim = $connection->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = \'reminders_last_run\' AND setting_value <> ?');
        $claim->execute([$today, $today]);
        if ($claim->rowCount() !== 1) {
            return;
        }
        // 0) Cảnh báo thực tập: nhiệm vụ quá hạn, sắp hết hạn báo cáo, sắp kết thúc mà chưa đánh giá (InternshipController).
        internship_daily_warnings();
        // 1) Hạn nộp bài đồ án trong 2 ngày tới: nhắc sinh viên chưa nộp giai đoạn đó.
        foreach (page_all('SELECT m.batch_id, m.kind, m.title, m.ends_at FROM batch_milestones m JOIN batches b ON b.id = m.batch_id WHERE b.status = \'open\' AND b.locked_at IS NULL AND (m.kind IN (\'report_1\', \'report_2\', \'report_final\') OR m.kind LIKE \'stage\\_%\') AND m.ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)') as $deadline) {
            foreach (page_all('SELECT th.id, s.user_id FROM theses th JOIN students s ON s.id = th.student_id WHERE th.batch_id = ? AND th.status IN (\'confirmed\', \'in_progress\') AND NOT EXISTS (SELECT 1 FROM thesis_submissions sb WHERE sb.thesis_id = th.id AND sb.stage = ?)', [(int) $deadline['batch_id'], $deadline['kind']]) as $thesis) {
                notify_user((int) $thesis['user_id'], 'thesis', 'Sắp hết hạn: ' . $deadline['title'], 'Hạn chót ' . thesis_datetime_label($deadline['ends_at']) . '. Bạn chưa nộp bài cho giai đoạn này.', 'student/thesis&batch=' . (int) $deadline['batch_id'], true);
            }
        }
        // 2) Lịch gặp hướng dẫn ngày mai.
        foreach (page_all('SELECT m.meeting_at, m.location, s.user_id AS student_user_id, th.supervisor_id FROM thesis_meetings m JOIN theses th ON th.id = m.thesis_id JOIN students s ON s.id = th.student_id WHERE m.status = \'scheduled\' AND DATE(m.meeting_at) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)') as $meeting) {
            $text = thesis_datetime_label($meeting['meeting_at']) . ($meeting['location'] ? ' · ' . $meeting['location'] : '');
            notify_user((int) $meeting['student_user_id'], 'thesis', 'Nhắc lịch gặp hướng dẫn ngày mai', $text, 'student/thesis', true);
            if ($meeting['supervisor_id']) {
                notify_lecturer((int) $meeting['supervisor_id'], 'theses', 'Nhắc lịch gặp hướng dẫn ngày mai', $text, 'lecturer/theses');
            }
        }
        // 3) Cảnh báo thực tập: sinh viên đang thực tập nhưng 7 ngày không cập nhật nhật ký.
        foreach (page_all('SELECT i.id, i.student_id, i.lecturer_id, u.full_name FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id WHERE i.status = \'active\' AND i.start_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOT EXISTS (SELECT 1 FROM diaries d WHERE d.internship_id = i.id AND d.diary_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))') as $internship) {
            notify_student((int) $internship['student_id'], 'diary', 'Bạn chưa cập nhật nhật ký 7 ngày', 'Hãy ghi nhật ký thực tập để giảng viên theo dõi tiến độ.', 'student/diary', true);
            if ($internship['lecturer_id']) {
                notify_lecturer((int) $internship['lecturer_id'], 'progress', 'Cảnh báo: ' . $internship['full_name'] . ' không cập nhật nhật ký', '7 ngày chưa có nhật ký mới.', 'lecturer/progress');
            }
        }
    } catch (Throwable $error) {
        error_log('InternTrack reminders failed: ' . $error->getMessage());
    }
}

// ---------------------------------------------------------------------------
// THÔNG BÁO CHUNG VÀ LỊCH
// ---------------------------------------------------------------------------

// Đợt mà người dùng tham gia (để lọc thông báo theo đợt).
function user_batch_ids(array $user): array
{
    $studentId = (int) ($user['student_id'] ?? 0);
    return match ($user['role']) {
        'student' => array_map('intval', array_column(page_all('SELECT batch_id FROM batch_students WHERE student_id = ? UNION SELECT batch_id FROM theses WHERE student_id = ? UNION SELECT batch_id FROM topic_preferences WHERE student_id = ? UNION SELECT batch_id FROM internships WHERE student_id = ? AND batch_id IS NOT NULL', [$studentId, $studentId, $studentId, $studentId]), 'batch_id')),
        default => array_map('intval', array_column(page_all('SELECT id AS batch_id FROM batches'), 'batch_id')),
    };
}

function announcements_for(array $user, int $limit = 50): array
{
    $sql = 'SELECT a.*, b.name AS batch_name, u.full_name AS author FROM announcements a LEFT JOIN batches b ON b.id = a.batch_id LEFT JOIN users u ON u.id = a.created_by';
    if ($user['role'] === 'admin') {
        return page_all($sql . ' ORDER BY a.created_at DESC LIMIT ' . $limit);
    }
    $batchIds = user_batch_ids($user);
    $where = ['a.recipients IS NULL', 'a.audience IN (\'all\', ?)', '(a.batch_id IS NULL' . ($batchIds ? ' OR a.batch_id IN (' . thesis_in($batchIds) . ')' : '') . ')', '(a.class_name IS NULL OR a.class_name = ?)'];
    return page_all(
        $sql . ' WHERE (' . implode(' AND ', $where) . ') OR FIND_IN_SET(?, a.recipients) OR a.created_by = ? ORDER BY a.created_at DESC LIMIT ' . $limit,
        array_merge([$user['role']], $batchIds, [(string) ($user['class_name'] ?? ''), (string) $user['id'], (int) $user['id']])
    );
}

// Các nhóm người nhận mà người dùng được gửi thông báo: [khóa => [tên nhóm, [id người nhận...]]].
// Giảng viên: sinh viên mình hướng dẫn đồ án / theo dõi thực tập, từng nhóm đồ án; doanh nghiệp: thực tập sinh; quản trị: mọi nhóm đồ án.
function announcement_groups(array $user): array
{
    $groups = [];
    $add = static function (string $key, string $label, array $rows) use (&$groups): void {
        $ids = array_values(array_unique(array_map('intval', array_column($rows, 'user_id'))));
        if ($ids) {
            $groups[$key] = [$label . ' (' . count($ids) . ')', $ids];
        }
    };
    $lecturerId = (int) ($user['lecturer_id'] ?? 0);
    if ($user['role'] === 'lecturer' && $lecturerId) {
        $add('sup_thesis', 'Sinh viên tôi hướng dẫn đồ án', page_all('SELECT s.user_id FROM theses th JOIN students s ON s.id = th.student_id JOIN batches b ON b.id = th.batch_id WHERE th.supervisor_id = ? AND th.status <> \'declined\' AND b.status = \'open\'', [$lecturerId]));
        $add('sup_intern', 'Sinh viên thực tập tôi theo dõi', page_all('SELECT s.user_id FROM internships i JOIN students s ON s.id = i.student_id WHERE i.lecturer_id = ? AND i.status IN (\'planned\', \'active\')', [$lecturerId]));
    }
    if ($user['role'] === 'company') {
        $add('company_interns', 'Thực tập sinh của đơn vị', page_all('SELECT s.user_id FROM internships i JOIN students s ON s.id = i.student_id WHERE i.company_id = ? AND (@it_mentor IS NULL OR i.mentor_user_id = @it_mentor) AND i.status IN (\'planned\', \'active\')', [(int) $user['company_id']]));
    }
    if (in_array($user['role'], ['lecturer', 'admin'], true)) {
        $groupFilter = $user['role'] === 'lecturer' ? ' AND EXISTS (SELECT 1 FROM theses th WHERE th.group_id = g.id AND th.supervisor_id = ' . $lecturerId . ')' : '';
        foreach (page_all('SELECT g.id, g.name, b.name AS batch_name FROM thesis_groups g JOIN batches b ON b.id = g.batch_id WHERE b.status = \'open\'' . $groupFilter . ' ORDER BY b.name, g.name') as $group) {
            $add('tg_' . $group['id'], 'Nhóm đồ án ' . $group['name'] . ' · ' . $group['batch_name'], page_all('SELECT s.user_id FROM thesis_groups g JOIN students s ON s.id = g.leader_student_id WHERE g.id = ? UNION SELECT s.user_id FROM thesis_group_members m JOIN students s ON s.id = m.student_id WHERE m.group_id = ? AND m.status = \'accepted\'', [(int) $group['id'], (int) $group['id']]));
        }
    }
    return $groups;
}

// Người nhận một thông báo chung (để gửi thông báo trong hệ thống và email).
function announcement_recipients(string $audience, ?int $batchId, ?string $className): array
{
    $roles = $audience === 'all' ? ['student', 'lecturer', 'company'] : [$audience];
    $sql = 'SELECT u.id FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE u.status = \'active\' AND u.role IN (' . thesis_in($roles) . ')';
    $parameters = $roles;
    if ($className) {
        $sql .= ' AND (u.role <> \'student\' OR s.class_name = ?)';
        $parameters[] = $className;
    }
    if ($batchId) {
        $sql .= ' AND (u.role <> \'student\' OR s.id IN (SELECT student_id FROM batch_students WHERE batch_id = ? UNION SELECT student_id FROM theses WHERE batch_id = ? UNION SELECT student_id FROM internships WHERE batch_id = ?))';
        array_push($parameters, $batchId, $batchId, $batchId);
    }
    return array_map('intval', array_column(page_all($sql, $parameters), 'id'));
}

// Lịch chung: hạn nộp theo mốc đợt, lịch gặp hướng dẫn, lịch bảo vệ, hạn nhiệm vụ thực tập (14 ngày trước → 120 ngày tới).
function calendar_items(array $user): array
{
    $items = [];
    $studentId = (int) ($user['student_id'] ?? 0);
    $lecturerId = (int) ($user['lecturer_id'] ?? 0);
    $companyId = (int) ($user['company_id'] ?? 0);
    $range = 'BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_ADD(NOW(), INTERVAL 120 DAY)';
    $batchFilter = match ($user['role']) {
        'student' => ($ids = user_batch_ids($user)) ? ' AND b.id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0',
        'company' => ' AND b.batch_type = \'internship\'',
        default => '',
    };
    foreach (page_all('SELECT m.title, m.ends_at, m.starts_at, b.name AS batch_name FROM batch_milestones m JOIN batches b ON b.id = m.batch_id WHERE b.status = \'open\' AND m.ends_at ' . $range . $batchFilter) as $row) {
        $items[] = ['at' => $row['ends_at'], 'title' => 'Hạn: ' . $row['title'], 'meta' => $row['batch_name'] . ($row['starts_at'] ? ' · mở từ ' . thesis_datetime_label($row['starts_at']) : ''), 'kind' => 'deadline'];
    }
    $meetingScope = match ($user['role']) {
        'student' => ['th.student_id = ?', [$studentId]],
        'lecturer' => ['th.supervisor_id = ?', [$lecturerId]],
        default => null,
    };
    if ($meetingScope) {
        foreach (page_all('SELECT m.meeting_at, m.location, m.agenda, u.full_name, tp.title FROM thesis_meetings m JOIN theses th ON th.id = m.thesis_id JOIN topics tp ON tp.id = th.topic_id JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id WHERE m.status = \'scheduled\' AND m.meeting_at ' . $range . ' AND ' . $meetingScope[0], $meetingScope[1]) as $row) {
            $items[] = ['at' => $row['meeting_at'], 'title' => 'Gặp hướng dẫn' . ($user['role'] === 'lecturer' ? ' · ' . $row['full_name'] : ''), 'meta' => trim(($row['location'] ?: '') . ' · ' . ($row['agenda'] ?: $row['title']), ' ·'), 'kind' => 'meeting'];
        }
        $defenseScope = $user['role'] === 'student' ? ['th.student_id = ?', [$studentId]] : ['(th.supervisor_id = ? OR th.reviewer_id = ? OR th.council_id IN (SELECT council_id FROM council_members WHERE lecturer_id = ?))', [$lecturerId, $lecturerId, $lecturerId]];
        foreach (page_all('SELECT th.defense_at, c.name, c.room, u.full_name FROM theses th JOIN councils c ON c.id = th.council_id JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id WHERE th.defense_at ' . $range . ' AND ' . $defenseScope[0], $defenseScope[1]) as $row) {
            $items[] = ['at' => $row['defense_at'], 'title' => 'Bảo vệ đồ án' . ($user['role'] === 'lecturer' ? ' · ' . $row['full_name'] : ''), 'meta' => $row['name'] . ($row['room'] ? ' · phòng ' . $row['room'] : ''), 'kind' => 'defense'];
        }
    }
    $taskScope = match ($user['role']) {
        'student' => ['t.assigned_to = ?', [$studentId]],
        'company' => ['i.company_id = ? AND (@it_mentor IS NULL OR i.mentor_user_id = @it_mentor)', [$companyId]],
        default => null,
    };
    if ($taskScope) {
        foreach (page_all('SELECT t.title, t.due_date, u.full_name FROM tasks t JOIN internships i ON i.id = t.internship_id JOIN students s ON s.id = t.assigned_to JOIN users u ON u.id = s.user_id WHERE t.status IN (\'todo\', \'in_progress\') AND t.due_date ' . $range . ' AND ' . $taskScope[0], $taskScope[1]) as $row) {
            $items[] = ['at' => $row['due_date'] . ' 23:59:00', 'title' => 'Hạn nhiệm vụ: ' . $row['title'], 'meta' => $user['role'] === 'company' ? $row['full_name'] : 'Nhiệm vụ thực tập', 'kind' => 'task'];
        }
    }
    // Lịch phỏng vấn của hồ sơ còn đang chờ (sinh viên và doanh nghiệp).
    $interviewScope = match ($user['role']) {
        'student' => ['a.student_id = ?', [$studentId]],
        'company' => ['p.company_id = ?', [$companyId]],
        default => null,
    };
    if ($interviewScope) {
        foreach (page_all('SELECT a.interview_at, a.interview_place, p.title, c.company_name, u.full_name FROM applications a JOIN positions p ON p.id = a.position_id JOIN companies c ON c.id = p.company_id JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE a.status IN (\'pending\', \'reviewing\') AND a.interview_at ' . $range . ' AND ' . $interviewScope[0], $interviewScope[1]) as $row) {
            $items[] = ['at' => $row['interview_at'], 'title' => 'Phỏng vấn · ' . ($user['role'] === 'company' ? $row['full_name'] : $row['company_name']), 'meta' => $row['title'] . ' · ' . $row['interview_place'], 'kind' => 'meeting'];
        }
    }
    usort($items, static fn($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
    return $items;
}

// ---------------------------------------------------------------------------
// NHẬP DỮ LIỆU TỪ EXCEL (CSV UTF-8 hoặc XLSX)
// ---------------------------------------------------------------------------

// Đọc bảng tính thành mảng các dòng; dòng đầu là tiêu đề cột.
function import_read_table(string $path, string $originalName): array
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === 'xlsx') {
        return xlsx_read($path);
    }
    if (!in_array($extension, ['csv', 'txt'], true)) {
        throw new DomainException('Chỉ nhận tệp .xlsx hoặc .csv (Excel → Lưu thành → CSV UTF-8).');
    }
    $content = (string) file_get_contents($path);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1258');
    }
    $firstLine = strtok($content, "\n") ?: '';
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        if ($row !== [null]) {
            $rows[] = $row;
        }
    }
    fclose($handle);
    return $rows;
}

// Chuẩn hóa tên cột (chấp nhận tiếng Việt có dấu, không dấu hoặc tiếng Anh).
function import_column_key(string $header): string
{
    $key = mb_strtolower(trim($header), 'UTF-8');
    $key = strtr($key, ['à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a', 'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a', 'đ' => 'd', 'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e', 'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i', 'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o', 'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u', 'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u', 'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y']);
    $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', $key), '_');
    $aliases = [
        'vai_tro' => 'role', 'ten_dang_nhap' => 'username', 'ho_ten' => 'full_name', 'ho_va_ten' => 'full_name', 'so_dien_thoai' => 'phone', 'dien_thoai' => 'phone',
        'ma_sinh_vien' => 'code', 'mssv' => 'code', 'ma_giang_vien' => 'code', 'ma_doanh_nghiep' => 'code', 'ma' => 'code', 'student_code' => 'code', 'lecturer_code' => 'code', 'company_code' => 'code',
        'nganh' => 'major', 'nganh_hoc' => 'major', 'lop' => 'class_name', 'class' => 'class_name', 'khoa' => 'faculty', 'tin_chi' => 'credits', 'so_tin_chi' => 'credits', 'he_dao_tao' => 'training_system',
        'bo_mon' => 'department', 'bo_mon_khoa' => 'department', 'ten_doanh_nghiep' => 'company_name', 'mat_khau' => 'password', 'han_muc' => 'max_students', 'ky_nang' => 'skills', 'diem_tb' => 'gpa', 'diem_trung_binh' => 'gpa', 'chuyen_nganh' => 'specialization', 'hoc_phan' => 'courses', 'hoc_phan_da_dat' => 'courses',
    ];
    return $aliases[$key] ?? $key;
}

// Nhập/cập nhật tài khoản hàng loạt. Đã có (trùng email hoặc tên đăng nhập) thì cập nhật hồ sơ (GPA, tín chỉ, lớp... từ hệ thống đào tạo); chưa có thì tạo mới.
function import_users(array $rows): array
{
    $header = array_map(static fn($cell) => import_column_key((string) $cell), array_shift($rows) ?? []);
    if (!in_array('email', $header, true) || !in_array('full_name', $header, true)) {
        throw new DomainException('Thiếu cột bắt buộc: email và họ tên (full_name). Xem tệp mẫu.');
    }
    $created = 0;
    $updated = 0;
    $errors = [];
    $roleNames = ['sinh_vien' => 'student', 'sinh vien' => 'student', 'giang_vien' => 'lecturer', 'giang vien' => 'lecturer', 'doanh_nghiep' => 'company', 'doanh nghiep' => 'company'];
    foreach ($rows as $index => $cells) {
        $line = $index + 2;
        $row = [];
        foreach ($header as $column => $key) {
            $row[$key] = trim((string) ($cells[$column] ?? ''));
        }
        if (implode('', $row) === '') {
            continue;
        }
        try {
            $role = strtolower($row['role'] ?? 'student') ?: 'student';
            $role = $roleNames[import_column_key($role)] ?? $role;
            if (!in_array($role, ['student', 'lecturer', 'company'], true)) {
                throw new DomainException('vai trò không hợp lệ');
            }
            $email = $row['email'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || ($row['full_name'] ?? '') === '') {
                throw new DomainException('email hoặc họ tên không hợp lệ');
            }
            $code = $row['code'] ?? '';
            $username = ($row['username'] ?? '') ?: ($code ?: strstr($email, '@', true));
            $gpa = ($row['gpa'] ?? '') !== '' ? max(0.0, min(4.0, (float) str_replace(',', '.', $row['gpa']))) : null;
            $credits = ($row['credits'] ?? '') !== '' ? max(0, (int) $row['credits']) : null;
            database_transaction(static function (PDO $connection) use ($row, $role, $email, $code, $username, $gpa, $credits, &$created, &$updated): void {
                $existing = page_one('SELECT id, role FROM users WHERE email = ? OR username = ? LIMIT 1', [$email, $username]);
                if ($existing) {
                    if ($existing['role'] !== $role) {
                        throw new DomainException('tài khoản đã tồn tại với vai trò khác');
                    }
                    $userId = (int) $existing['id'];
                    $connection->prepare('UPDATE users SET full_name = ?, phone = COALESCE(NULLIF(?, \'\'), phone) WHERE id = ?')->execute([$row['full_name'], $row['phone'] ?? '', $userId]);
                    $updated++;
                } else {
                    $password = ($row['password'] ?? '') !== '' ? $row['password'] : bin2hex(random_bytes(8));
                    if (strlen($password) < 10 && ($row['password'] ?? '') !== '') {
                        throw new DomainException('mật khẩu cần ít nhất 10 ký tự');
                    }
                    $connection->prepare('INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, \'active\')')->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $row['full_name'], ($row['phone'] ?? '') ?: null, $role]);
                    $userId = (int) $connection->lastInsertId();
                    $created++;
                }
                if ($role === 'student') {
                    if ($code === '' && !page_one('SELECT id FROM students WHERE user_id = ?', [$userId])) {
                        throw new DomainException('thiếu mã sinh viên');
                    }
                    $connection->prepare('INSERT INTO students (user_id, student_code, major, class_name, faculty, university, gpa, credits, training_system, skills) VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), \'Trường Đại học Vinh\', ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'))
                         ON DUPLICATE KEY UPDATE major = COALESCE(VALUES(major), major), class_name = COALESCE(VALUES(class_name), class_name), faculty = COALESCE(VALUES(faculty), faculty), gpa = COALESCE(VALUES(gpa), gpa), credits = COALESCE(VALUES(credits), credits), training_system = COALESCE(VALUES(training_system), training_system), skills = COALESCE(VALUES(skills), skills)')
                        ->execute([$userId, $code ?: 'SV' . $userId, $row['major'] ?? '', $row['class_name'] ?? '', $row['faculty'] ?? '', $gpa, $credits, $row['training_system'] ?? '', $row['skills'] ?? '']);
                    $studentId = (int) page_one('SELECT id FROM students WHERE user_id = ?', [$userId])['id'];
                    if (($row['specialization'] ?? '') !== '') {
                        $connection->prepare('UPDATE students SET specialization = ? WHERE id = ?')->execute([mb_substr($row['specialization'], 0, 150, 'UTF-8'), $studentId]);
                    }
                    // Cột học phần: danh sách mã học phần đã đạt (ngăn cách bằng dấu phẩy / chấm phẩy) thay cho danh sách cũ.
                    if (($row['courses'] ?? '') !== '') {
                        $connection->prepare('DELETE FROM student_courses WHERE student_id = ?')->execute([$studentId]);
                        $insertCourse = $connection->prepare('INSERT IGNORE INTO student_courses (student_id, course_code) VALUES (?, ?)');
                        foreach (array_filter(array_map(static fn($code) => strtoupper(trim($code)), preg_split('/[,;]/', $row['courses']) ?: [])) as $course) {
                            $insertCourse->execute([$studentId, mb_substr($course, 0, 30)]);
                        }
                    }
                } elseif ($role === 'lecturer') {
                    $connection->prepare('INSERT INTO lecturers (user_id, lecturer_code, department, faculty, max_students) VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?) ON DUPLICATE KEY UPDATE department = COALESCE(VALUES(department), department), faculty = COALESCE(VALUES(faculty), faculty), max_students = VALUES(max_students)')
                        ->execute([$userId, $code ?: 'GV' . $userId, $row['department'] ?? '', $row['faculty'] ?? '', max(1, (int) (($row['max_students'] ?? '') ?: 8))]);
                } else {
                    if (!preg_match('/^\d{10}$/', $code) && !page_one('SELECT id FROM companies WHERE user_id = ?', [$userId])) {
                        throw new DomainException('mã doanh nghiệp phải gồm 10 chữ số');
                    }
                    $connection->prepare('INSERT INTO companies (user_id, company_code, company_name, email, status) VALUES (?, ?, ?, ?, \'active\') ON DUPLICATE KEY UPDATE company_name = VALUES(company_name)')
                        ->execute([$userId, $code, ($row['company_name'] ?? '') ?: $row['full_name'], $email]);
                }
            });
        } catch (DomainException $error) {
            $errors[] = 'Dòng ' . $line . ': ' . $error->getMessage();
        } catch (PDOException $error) {
            $errors[] = 'Dòng ' . $line . ': trùng mã hoặc dữ liệu không hợp lệ';
        }
    }
    return [$created, $updated, $errors];
}

// ---------------------------------------------------------------------------
// XUẤT DỮ LIỆU: CSV (mở bằng Excel), biểu mẫu in / lưu PDF, sao lưu SQL
// ---------------------------------------------------------------------------

// Xuất bảng dữ liệu: mặc định tệp Excel .xlsx; thêm &format=csv để lấy CSV UTF-8.
function export_csv(string $filename, array $header, array $rows): never
{
    if (($_GET['format'] ?? '') !== 'csv') {
        $content = xlsx_build($header, array_map(static fn($row) => array_map(static fn($cell) => (string) ($cell ?? ''), array_values($row)), $rows), $filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Ymd-His') . '.xlsx"');
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($output, array_map(static fn($cell) => (string) $cell, $row), ',', '"', '');
    }
    fclose($output);
    exit;
}

// Trang in theo thể thức văn bản của trường; người dùng bấm "In / Lưu PDF".
function export_print(string $title, string $bodyHtml, string $signatures = ''): never
{
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pdf = ($_GET['format'] ?? '') === 'pdf';
    // Mẫu biểu do phòng đào tạo chỉnh (tên đơn vị, địa danh, chữ ký) thay cho phần đầu / chữ ký mặc định.
    $unit = app_setting('doc_unit', 'Phòng Đào tạo');
    $place = app_setting('doc_place', 'Nghệ An');
    $signatures = document_template_signatures($title, $signatures);
    // PDF dùng bản JPEG của logo (nhúng được mà không cần tiện ích GD của PHP).
    $logoPath = dirname(__DIR__) . '/assets/images/vinhuni-logo.jpg';
    $logo = $pdf && is_file($logoPath) ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logoPath)) : 'assets/images/vinhuni-logo.png';
    $pdfUrl = '?' . http_build_query(array_merge($_GET, ['format' => 'pdf']));
    $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' . $escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>
        body{font-family:' . ($pdf ? '"DejaVu Serif"' : '"Times New Roman",serif') . ';color:#111;max-width:900px;margin:' . ($pdf ? '0' : '24px auto') . ';padding:0 ' . ($pdf ? '0' : '24px') . ';font-size:' . ($pdf ? '12px' : '14px') . ';line-height:1.45}
        .doc-head{width:100%;border:0;margin-bottom:18px}.doc-head td{border:0;width:50%;text-align:center;vertical-align:top}.doc-head img{width:56px;height:56px}
        .doc-head strong{display:block;text-transform:uppercase}.doc-title{text-align:center;font-size:' . ($pdf ? '16px' : '19px') . ';font-weight:700;text-transform:uppercase;margin:20px 0 4px}
        .doc-sub{text-align:center;font-style:italic;margin-bottom:18px}table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #333;padding:5px 7px;vertical-align:top;text-align:left}
        th{background:#eef4f9}.sign{width:100%;border:0;margin-top:36px}.sign td{border:0;text-align:center;height:110px;vertical-align:top}
        .toolbar{position:sticky;top:0;background:#0073a5;color:#fff;padding:8px 12px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:16px;font-family:sans-serif}
        .toolbar span{flex:1}.toolbar button,.toolbar a{background:#fff;color:#0073a5;border:0;border-radius:4px;padding:6px 14px;font-weight:700;cursor:pointer;text-decoration:none;font-size:13px}@media print{.toolbar{display:none}body{margin:0}}
        </style></head><body>' . ($pdf ? '' : '<div class="toolbar"><span>' . $escape($title) . '</span><a href="' . $escape($pdfUrl) . '">Tải tệp PDF</a><button onclick="window.print()">In</button></div>')
        . '<table class="doc-head"><tr><td><img src="' . $logo . '" alt=""><strong>Trường Đại học Vinh</strong><span>' . $escape($unit) . '</span></td>'
        . '<td><strong>Cộng hòa xã hội chủ nghĩa Việt Nam</strong><span>Độc lập - Tự do - Hạnh phúc</span><br><em>' . $escape($place) . ', ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y') . '</em></td></tr></table>'
        . $bodyHtml . $signatures . '</body></html>';
    if (!$pdf) {
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf(['defaultFont' => 'DejaVu Serif', 'isRemoteEnabled' => false, 'chroot' => dirname(__DIR__)]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4');
    $dompdf->render();
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title))), '-') ?: 'bieu-mau';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '-' . date('Ymd') . '.pdf"');
    echo $dompdf->output();
    exit;
}

// Hàng chữ ký: dùng cấu hình mẫu biểu (nếu phòng đào tạo đã sửa cho biểu mẫu này), không thì giữ mặc định.
// Mỗi dòng cấu hình là một ô chữ ký; nhiều dòng trong một ô ngăn bằng "|".
function document_template_signatures(string $title, string $default): string
{
    $configured = trim((string) app_setting('doc_sign_' . substr(md5($title), 0, 12), ''));
    if ($configured === '' && $default === '' && isset(DOCUMENT_TEMPLATES[$title])) {
        $configured = DOCUMENT_TEMPLATES[$title];
    }
    if ($configured === '') {
        return $default === '' ? '' : str_replace(['<div class="sign">', '</div></div>', '<div>', '</div>'], ['<table class="sign"><tr>', '</td></tr></table>', '<td>', '</td>'], $default);
    }
    $cells = array_map(static fn($line) => '<td><strong>' . implode('<br>', array_map(static fn($part) => htmlspecialchars(trim($part), ENT_QUOTES, 'UTF-8'), explode('|', $line))) . '</strong></td>', array_filter(array_map('trim', preg_split('/\R/u', $configured) ?: [])));
    return '<table class="sign"><tr>' . implode('', $cells) . '</tr></table>';
}

// Danh sách biểu mẫu in được (tiêu đề → hàng chữ ký mặc định), để phòng đào tạo sửa ở trang Mẫu biểu.
const DOCUMENT_TEMPLATES = [
    'Quyết định phân công hướng dẫn đồ án' => "NGƯỜI LẬP BIỂU\nTRƯỞNG PHÒNG ĐÀO TẠO\nHIỆU TRƯỞNG",
    'Danh sách hội đồng bảo vệ' => "TRƯỞNG PHÒNG ĐÀO TẠO\nHIỆU TRƯỞNG",
    'Phiếu chấm đồ án' => "\nNGƯỜI CHẤM|(Ký, ghi rõ họ tên)",
    'Biên bản bảo vệ đồ án' => "THƯ KÝ HỘI ĐỒNG\nCHỦ TỊCH HỘI ĐỒNG",
    'Giấy giới thiệu thực tập' => "XÁC NHẬN CỦA ĐƠN VỊ TIẾP NHẬN\nTL. HIỆU TRƯỞNG|TRƯỞNG PHÒNG ĐÀO TẠO",
    'Phiếu tiếp nhận sinh viên thực tập' => "SINH VIÊN|(Ký, ghi rõ họ tên)\nĐẠI DIỆN ĐƠN VỊ TIẾP NHẬN|(Ký, đóng dấu)",
    'Bản cam kết thực tập' => "GIẢNG VIÊN THEO DÕI\nSINH VIÊN CAM KẾT|(Ký, ghi rõ họ tên)",
    'Phiếu nhận xét thực tập' => "NGƯỜI HƯỚNG DẪN TẠI ĐƠN VỊ\nXÁC NHẬN CỦA ĐƠN VỊ|(Ký, đóng dấu)",
];

function export_html_table(array $header, array $rows): string
{
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<table><thead><tr>' . implode('', array_map(static fn($cell) => '<th>' . $escape($cell) . '</th>', $header)) . '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>' . implode('', array_map(static fn($cell) => '<td>' . nl2br($escape($cell)) . '</td>', $row)) . '</tr>';
    }
    return $html . '</tbody></table>';
}

// Điểm từng vai trò của các đồ án trong đợt (dùng cho bảng điểm và biên bản).
function export_thesis_rows(int $batchId): array
{
    return page_all(
        'SELECT th.*, s.student_code, s.class_name, u.full_name, tp.title AS topic_title, su.full_name AS supervisor_name, ru.full_name AS reviewer_name, c.name AS council_name, c.room,
                (SELECT AVG(total) FROM thesis_scores sc WHERE sc.thesis_id = th.id AND sc.evaluator_role = \'supervisor\' AND sc.status = \'submitted\') AS supervisor_score,
                (SELECT AVG(total) FROM thesis_scores sc WHERE sc.thesis_id = th.id AND sc.evaluator_role = \'reviewer\' AND sc.status = \'submitted\') AS reviewer_score,
                (SELECT AVG(total) FROM thesis_scores sc WHERE sc.thesis_id = th.id AND sc.evaluator_role = \'council\' AND sc.status = \'submitted\') AS council_score
         FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN topics tp ON tp.id = th.topic_id
         LEFT JOIN lecturers sl ON sl.id = th.supervisor_id LEFT JOIN users su ON su.id = sl.user_id LEFT JOIN lecturers rl ON rl.id = th.reviewer_id LEFT JOIN users ru ON ru.id = rl.user_id
         LEFT JOIN councils c ON c.id = th.council_id WHERE th.batch_id = ? AND th.status <> \'declined\' ORDER BY s.class_name, u.full_name',
        [$batchId]
    );
}

function export_score(mixed $value): string
{
    return $value === null ? '' : number_format((float) $value, 2, ',', '');
}

// Sao lưu toàn bộ bảng dữ liệu thành tệp SQL (DROP + CREATE + INSERT). View được tạo lại bằng database/interntrack.sql.
function backup_sql(): string
{
    $connection = database();
    $sql = "-- Sao lưu InternTrack · Trường Đại học Vinh · " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n";
    foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as [$table]) {
        $create = $connection->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
        $sql .= "\nDROP TABLE IF EXISTS `" . $table . "`;\n" . $create . ";\n";
        $statement = $connection->query('SELECT * FROM `' . str_replace('`', '', $table) . '`');
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $sql .= 'INSERT INTO `' . $table . '` VALUES (' . implode(',', array_map(static fn($value) => $value === null ? 'NULL' : $connection->quote((string) $value), $row)) . ");\n";
        }
    }
    return $sql . "\nSET FOREIGN_KEY_CHECKS = 1;\n";
}

// Bản sao lưu đầy đủ: database.sql + toàn bộ tệp trong uploads/ (CV, báo cáo, bài nộp, giấy tờ...).
function backup_zip(): string
{
    $files = ['database.sql' => backup_sql()];
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                $files['uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = (string) file_get_contents($file->getPathname());
            }
        }
    }
    return zip_build($files);
}

// Sao lưu tự động (scripts/cron.php): lưu vào backups/ và giữ lại $keep bản mới nhất.
function backup_store(int $keep = 14): string
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($directory)) {
        mkdir($directory, 0750, true);
        file_put_contents($directory . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
    }
    $path = $directory . DIRECTORY_SEPARATOR . 'interntrack-' . date('Ymd-His') . '.zip';
    file_put_contents($path, backup_zip());
    $old = glob($directory . DIRECTORY_SEPARATOR . 'interntrack-*.zip') ?: [];
    rsort($old);
    foreach (array_slice($old, $keep) as $stale) {
        unlink($stale);
    }
    return $path;
}

function export_backup(): never
{
    $content = backup_zip();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="interntrack-backup-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

// GET ?export=<loại>: kiểm tra quyền rồi xuất tệp.
function serve_export(string $type, array $user): never
{
    $batchId = thesis_query_int('batch');
    $id = thesis_query_int('id');
    $isAdmin = $user['role'] === 'admin';
    $deny = static function (): never {
        http_response_code(403);
        exit('Bạn không có quyền xuất dữ liệu này.');
    };

    switch ($type) {
        case 'backup':
            $isAdmin || $deny();
            audit_record('backup_download', [], $user);
            export_backup();

        case 'users':
            $isAdmin || $deny();
            $rows = page_all('SELECT u.username, u.email, u.full_name, u.phone, u.role, u.status, COALESCE(s.student_code, l.lecturer_code, c.company_code) AS code, s.major, s.class_name, s.gpa, s.credits, l.department, c.company_name FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN lecturers l ON l.user_id = u.id LEFT JOIN companies c ON c.user_id = u.id ORDER BY u.role, u.full_name');
            export_csv('tai-khoan', ['role', 'username', 'email', 'full_name', 'phone', 'status', 'code', 'major', 'class_name', 'gpa', 'credits', 'department', 'company_name'], array_map(static fn($row) => [$row['role'], $row['username'], $row['email'], $row['full_name'], $row['phone'], $row['status'], $row['code'], $row['major'], $row['class_name'], $row['gpa'], $row['credits'], $row['department'], $row['company_name']], $rows));

        case 'import_template':
            $isAdmin || $deny();
            export_csv('mau-nhap-tai-khoan', ['role', 'username', 'email', 'full_name', 'phone', 'code', 'major', 'specialization', 'class_name', 'faculty', 'gpa', 'credits', 'training_system', 'courses', 'department', 'company_name', 'password'], [
                ['student', '', 'sv.minhanh@vinhuni.edu.vn', 'Nguyễn Minh Anh', '0912345678', '215748020110001', 'Công nghệ thông tin', 'Kỹ thuật phần mềm', '62K-CNTT1', 'Viện Kỹ thuật và Công nghệ', '3.25', '128', 'Chính quy', 'INT2208,INT3306', '', '', ''],
                ['lecturer', '', 'gv.hoangnam@vinhuni.edu.vn', 'TS. Hoàng Nam', '', 'GV0101', '', '', '', 'Viện Kỹ thuật và Công nghệ', '', '', '', '', 'Bộ môn Kỹ thuật phần mềm', '', ''],
                ['company', '', 'hr@congty.vn', 'Trần Thị Lan', '0238123456', '2900123456', '', '', '', '', '', '', '', '', '', 'Công ty Cổ phần Phần mềm Nghệ An', ''],
            ]);

        case 'thesis_scores':
            $isAdmin || $deny();
            $batch = thesis_batch($batchId);
            $rows = export_thesis_rows($batchId);
            export_csv('bang-diem-do-an', ['Mã SV', 'Họ tên', 'Lớp', 'Đề tài', 'GV hướng dẫn', 'GV phản biện', 'Hội đồng', 'Điểm HD', 'Điểm PB', 'Điểm HĐ', 'Điểm tổng kết', 'Kết quả'], array_map(static fn($row) => [
                $row['student_code'], $row['full_name'], $row['class_name'], $row['topic_title'], $row['supervisor_name'], $row['reviewer_name'], $row['council_name'],
                export_score($row['supervisor_score']), export_score($row['reviewer_score']), export_score($row['council_score']), export_score($row['final_score']),
                $row['final_score'] === null ? 'Chưa đủ điểm' : ((float) $row['final_score'] >= (float) $batch['pass_score'] ? 'Đạt' : 'Không đạt'),
            ], $rows));

        case 'assignments':
            $isAdmin || $deny();
            $batch = thesis_batch($batchId);
            $rows = export_thesis_rows($batchId);
            export_print('Quyết định phân công hướng dẫn đồ án', '<p class="doc-title">Danh sách phân công giảng viên hướng dẫn và phản biện</p><p class="doc-sub">(Kèm theo quyết định giao đề tài ' . htmlspecialchars($batch['name'], ENT_QUOTES, 'UTF-8') . ')</p>'
                . export_html_table(['STT', 'Mã SV', 'Họ tên', 'Lớp', 'Tên đề tài', 'GV hướng dẫn', 'GV phản biện'], array_map(static fn($row, $index) => [$index + 1, $row['student_code'], $row['full_name'], $row['class_name'], $row['topic_title'], $row['supervisor_name'], $row['reviewer_name'] ?: '—'], $rows, array_keys($rows))),
                '<div class="sign"><div><strong>NGƯỜI LẬP BIỂU</strong></div><div><strong>TRƯỞNG PHÒNG ĐÀO TẠO</strong></div><div><strong>HIỆU TRƯỞNG</strong></div></div>');

        case 'councils':
            ($isAdmin || $user['role'] === 'lecturer') || $deny();
            $batch = thesis_batch($batchId);
            $body = '<p class="doc-title">Danh sách hội đồng và lịch bảo vệ đồ án</p><p class="doc-sub">' . htmlspecialchars($batch['name'], ENT_QUOTES, 'UTF-8') . '</p>';
            foreach (page_all('SELECT * FROM councils WHERE batch_id = ? ORDER BY defense_date, start_time, name', [$batchId]) as $council) {
                $members = page_all('SELECT cm.member_role, u.full_name FROM council_members cm JOIN lecturers l ON l.id = cm.lecturer_id JOIN users u ON u.id = l.user_id WHERE cm.council_id = ? ORDER BY FIELD(cm.member_role, \'chair\', \'secretary\', \'member\')', [(int) $council['id']]);
                $theses = page_all('SELECT th.defense_at, s.student_code, u.full_name, tp.title, su.full_name AS supervisor FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN topics tp ON tp.id = th.topic_id LEFT JOIN lecturers l ON l.id = th.supervisor_id LEFT JOIN users su ON su.id = l.user_id WHERE th.council_id = ? AND th.status <> \'declined\' ORDER BY th.defense_at', [(int) $council['id']]);
                $body .= '<h3>' . htmlspecialchars($council['name'], ENT_QUOTES, 'UTF-8') . ' · Phòng ' . htmlspecialchars((string) $council['room'], ENT_QUOTES, 'UTF-8') . ' · ' . ($council['defense_date'] ? date('d/m/Y', (int) strtotime($council['defense_date'])) : 'chưa xếp ngày') . '</h3>'
                    . '<p>' . implode(' · ', array_map(static fn($member) => COUNCIL_ROLE_LABELS[$member['member_role']] . ': ' . htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8'), $members)) . '</p>'
                    . export_html_table(['Giờ', 'Mã SV', 'Họ tên', 'Đề tài', 'GV hướng dẫn'], array_map(static fn($row) => [$row['defense_at'] ? date('H:i', (int) strtotime($row['defense_at'])) : '', $row['student_code'], $row['full_name'], $row['title'], $row['supervisor']], $theses));
            }
            export_print('Danh sách hội đồng bảo vệ', $body, '<div class="sign"><div><strong>TRƯỞNG PHÒNG ĐÀO TẠO</strong></div><div><strong>HIỆU TRƯỞNG</strong></div></div>');

        case 'minutes':
        case 'scoresheet':
            $thesis = thesis_access($id, $user);
            if ($type === 'minutes' && !$isAdmin && !$thesis['is_council']) {
                $deny();
            }
            $batch = thesis_batch((int) $thesis['batch_id']);
            $row = array_values(array_filter(export_thesis_rows((int) $batch['id']), static fn($item) => (int) $item['id'] === $id))[0] ?? null;
            if (!$row) {
                $deny();
            }
            $info = export_html_table(['Sinh viên', 'Mã SV', 'Lớp', 'Đề tài'], [[$row['full_name'], $row['student_code'], $row['class_name'], $row['topic_title']]])
                . '<p>Giảng viên hướng dẫn: <strong>' . htmlspecialchars((string) $row['supervisor_name'], ENT_QUOTES, 'UTF-8') . '</strong> · Phản biện: <strong>' . htmlspecialchars((string) ($row['reviewer_name'] ?: '—'), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            if ($type === 'scoresheet') {
                $role = is_string($_GET['role'] ?? null) && isset(THESIS_ROLE_LABELS[$_GET['role']]) ? $_GET['role'] : 'supervisor';
                if (!$isAdmin && empty($thesis['is_' . $role])) {
                    $deny();
                }
                $sheet = thesis_score_sheet($thesis, $batch, (int) $user['id'], $role);
                $criteria = $sheet['rubric']['criteria'] ?? [];
                $body = '<p class="doc-title">Phiếu chấm đồ án tốt nghiệp</p><p class="doc-sub">Dành cho ' . mb_strtolower(THESIS_ROLE_LABELS[$role], 'UTF-8') . ' · ' . htmlspecialchars($batch['name'], ENT_QUOTES, 'UTF-8') . '</p>' . $info
                    . export_html_table(['Tiêu chí', 'Mô tả các mức', 'Trọng số', 'Điểm tối đa', 'Điểm chấm'], $criteria ? array_map(static fn($criterion) => [$criterion['name'], (string) $criterion['levels'], $criterion['weight'], $criterion['max_score'], isset($sheet['items'][(int) $criterion['id']]) ? $sheet['items'][(int) $criterion['id']] : ''], $criteria) : [['Điểm tổng (thang 10)', '', '1', '10', $sheet['score']['total'] ?? '']])
                    . '<p>Tổng điểm (thang 10): <strong>' . htmlspecialchars((string) ($sheet['score']['total'] ?? '……'), ENT_QUOTES, 'UTF-8') . '</strong></p><p>Nhận xét: ' . nl2br(htmlspecialchars((string) ($sheet['score']['comments'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
                export_print('Phiếu chấm đồ án', $body, '<div class="sign"><div></div><div><strong>NGƯỜI CHẤM</strong><br><em>(Ký, ghi rõ họ tên)</em></div></div>');
            }
            $members = $row['council_id'] ? page_all('SELECT cm.member_role, u.full_name, sc.total, sc.comments FROM council_members cm JOIN lecturers l ON l.id = cm.lecturer_id JOIN users u ON u.id = l.user_id LEFT JOIN thesis_scores sc ON sc.thesis_id = ? AND sc.evaluator_user_id = u.id AND sc.evaluator_role = \'council\' WHERE cm.council_id = ? ORDER BY FIELD(cm.member_role, \'chair\', \'secretary\', \'member\')', [$id, (int) $row['council_id']]) : [];
            $body = '<p class="doc-title">Biên bản bảo vệ đồ án tốt nghiệp</p><p class="doc-sub">' . htmlspecialchars((string) $row['council_name'], ENT_QUOTES, 'UTF-8') . ' · Phòng ' . htmlspecialchars((string) $row['room'], ENT_QUOTES, 'UTF-8') . ' · ' . thesis_datetime_label($row['defense_at']) . '</p>' . $info
                . '<h3>Thành phần hội đồng và điểm</h3>' . export_html_table(['Vai trò', 'Họ tên', 'Điểm', 'Nhận xét'], array_map(static fn($member) => [COUNCIL_ROLE_LABELS[$member['member_role']], $member['full_name'], export_score($member['total']), (string) $member['comments']], $members))
                . export_html_table(['Điểm hướng dẫn', 'Điểm phản biện', 'Điểm hội đồng (TB)', 'Điểm tổng kết', 'Kết quả'], [[export_score($row['supervisor_score']), export_score($row['reviewer_score']), export_score($row['council_score']), export_score($row['final_score']), $row['final_score'] === null ? 'Chưa đủ điểm' : ((float) $row['final_score'] >= (float) $batch['pass_score'] ? 'Đạt' : 'Không đạt')]])
                . '<p>Ý kiến khác của hội đồng: ……………………………………………………………………………………………</p>';
            export_print('Biên bản bảo vệ đồ án', $body, '<div class="sign"><div><strong>THƯ KÝ HỘI ĐỒNG</strong></div><div><strong>CHỦ TỊCH HỘI ĐỒNG</strong></div></div>');

        case 'internship_scores':
            $isAdmin || $deny();
            $rows = page_all('SELECT v.student_code, v.student_name, v.company_name, v.position_title, v.lecturer_name, v.start_date, v.end_date, v.status, v.final_score,
                    (SELECT overall_score FROM evaluations e WHERE e.internship_id = v.internship_id AND e.evaluator_type = \'company\' AND e.status = \'submitted\' LIMIT 1) AS company_score,
                    (SELECT overall_score FROM evaluations e WHERE e.internship_id = v.internship_id AND e.evaluator_type = \'lecturer\' AND e.status = \'submitted\' LIMIT 1) AS lecturer_score
                 FROM v_student_internships v ORDER BY v.student_name');
            export_csv('bang-diem-thuc-tap', ['Mã SV', 'Họ tên', 'Doanh nghiệp', 'Vị trí', 'GV theo dõi', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Điểm DN', 'Điểm GV', 'Điểm tổng kết (100)', 'Kết quả'], array_map(static fn($row) => [
                $row['student_code'], $row['student_name'], $row['company_name'], $row['position_title'], $row['lecturer_name'], $row['start_date'], $row['end_date'], $row['status'],
                export_score($row['company_score']), export_score($row['lecturer_score']), export_score($row['final_score']), $row['final_score'] === null ? '' : ((float) $row['final_score'] >= final_pass_score() ? 'Đạt' : 'Không đạt'),
            ], $rows));

        case 'statistics_major':
            $isAdmin || $deny();
            $percent = static fn($part, $whole) => (int) $whole ? round($part * 100 / $whole, 1) : '';
            export_csv('thong-ke-theo-nganh', ['Ngành', 'Sinh viên', 'Kỳ thực tập', 'Điểm TB thực tập', 'Tỷ lệ đạt thực tập (%)', 'Có việc làm (DN xác nhận)', 'Có việc làm (gồm tự khai)', 'Đồ án', 'Điểm TB đồ án', 'Tỷ lệ đạt đồ án (%)'], array_map(static fn($row) => [$row['major'], $row['students'], $row['internships'], $row['internship_average'] === null ? '' : round((float) $row['internship_average'], 2), $percent($row['internship_passed'], $row['internship_scored']), $row['offered_confirmed'], $row['employed'], $row['theses'], $row['thesis_average'] === null ? '' : round((float) $row['thesis_average'], 2), $percent($row['thesis_passed'], $row['thesis_scored'])], statistics_by_major()));

        case 'statistics':
            $isAdmin || $deny();
            export_csv('thong-ke-cac-dot', ['Đợt', 'Loại', 'Năm học', 'Trạng thái', 'Đề tài', 'Sinh viên được ghép', 'Tỷ lệ NV1 (%)', 'Điểm TB', 'Tỷ lệ đạt (%)', 'Bài nộp trễ'], array_map(static fn($row) => [$row['name'], $row['batch_type'] === 'thesis' ? 'Đồ án' : 'Thực tập', $row['school_year'], $row['status'], $row['topics'], $row['students'], $row['first_rate'], $row['average'], $row['pass_rate'], $row['late']], statistics_batches()));

        case 'audit':
            $isAdmin || $deny();
            $rows = page_all('SELECT a.created_at, u.full_name, a.role, a.action, a.target, a.details, a.ip_address FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 20000');
            export_csv('nhat-ky-he-thong', ['Thời điểm', 'Người thực hiện', 'Vai trò', 'Thao tác', 'Đối tượng', 'Chi tiết', 'IP'], array_map('array_values', $rows));

        case 'letter':
            $internship = action_internship($id, $user);
            $row = page_one('SELECT v.*, s.class_name, s.major, s.date_of_birth, c.address AS company_address FROM v_student_internships v JOIN students s ON s.id = v.student_id JOIN companies c ON c.id = v.company_id WHERE v.internship_id = ?', [(int) $internship['id']]);
            $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = '<p class="doc-title">Giấy giới thiệu thực tập</p><p>Kính gửi: <strong>' . $escape($row['company_name']) . '</strong><br>Địa chỉ: ' . $escape($row['company_address']) . '</p>'
                . '<p>Trường Đại học Vinh trân trọng giới thiệu sinh viên <strong>' . $escape($row['student_name']) . '</strong>, mã sinh viên ' . $escape($row['student_code']) . ', lớp ' . $escape($row['class_name']) . ', ngành ' . $escape($row['major'])
                . ' đến Quý cơ quan thực tập ở vị trí <strong>' . $escape($row['position_title']) . '</strong> trong thời gian từ ' . page_date($row['start_date']) . ' đến ' . page_date($row['end_date']) . '.</p>'
                . '<p>Giảng viên theo dõi: ' . $escape($row['lecturer_name'] ?: 'Sẽ được phân công') . '.</p><p>Kính đề nghị Quý cơ quan tạo điều kiện để sinh viên hoàn thành đợt thực tập, đồng thời nhận xét và đánh giá kết quả thực tập của sinh viên theo phiếu đánh giá trực tuyến của nhà trường.</p><p>Trân trọng cảm ơn!</p>';
            export_print('Giấy giới thiệu thực tập', $body, '<div class="sign"><div><strong>XÁC NHẬN CỦA ĐƠN VỊ TIẾP NHẬN</strong></div><div><strong>TL. HIỆU TRƯỞNG<br>TRƯỞNG PHÒNG ĐÀO TẠO</strong></div></div>');
    }
    // Lịch cá nhân dạng iCalendar (.ics): hạn chót, lịch gặp, bảo vệ, phỏng vấn, hạn nhiệm vụ.
    if ($type === 'ical') {
        $escape = static fn($value) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], (string) $value);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Truong Dai hoc Vinh//InternTrack//VI', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:InternTrack · ' . $escape($user['full_name'])];
        foreach (calendar_items($user) as $index => $item) {
            $start = (int) strtotime((string) $item['at']);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . md5($item['kind'] . $item['at'] . $item['title']) . '-' . $index . '@interntrack';
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $start);
            $lines[] = 'DTEND:' . gmdate('Ymd\THis\Z', $start + 3600);
            $lines[] = 'SUMMARY:' . $escape($item['title']);
            $lines[] = 'DESCRIPTION:' . $escape($item['meta']);
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        header('Content-Type: text/calendar; charset=UTF-8');
        header('Content-Disposition: attachment; filename="interntrack-lich.ics"');
        echo implode("\r\n", $lines) . "\r\n";
        exit;
    }

    // Phiếu tiếp nhận / bản cam kết / phiếu nhận xét của đơn vị: điền sẵn thông tin kỳ thực tập.
    if (in_array($type, ['receipt', 'commitment', 'company_form'], true)) {
        $internship = action_internship($id, $user);
        $row = page_one('SELECT v.*, s.class_name, s.major, c.address AS company_address, c.phone AS company_phone, mu.full_name AS mentor_name FROM v_student_internships v JOIN students s ON s.id = v.student_id JOIN companies c ON c.id = v.company_id JOIN internships i ON i.id = v.internship_id LEFT JOIN users mu ON mu.id = i.mentor_user_id WHERE v.internship_id = ?', [(int) $internship['id']]);
        $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $info = export_html_table(['Sinh viên', 'Mã SV', 'Lớp · ngành', 'Đơn vị · vị trí', 'Thời gian'], [[$row['student_name'], $row['student_code'], $row['class_name'] . ' · ' . $row['major'], $row['company_name'] . ' · ' . $row['position_title'], page_date($row['start_date']) . ' – ' . page_date($row['end_date'])]]);
        if ($type === 'receipt') {
            export_print('Phiếu tiếp nhận sinh viên thực tập', '<p class="doc-title">Phiếu tiếp nhận sinh viên thực tập</p><p>Kính gửi: Phòng Đào tạo, Trường Đại học Vinh</p>'
                . '<p><strong>' . $escape($row['company_name']) . '</strong> (địa chỉ: ' . $escape($row['company_address']) . ') đồng ý tiếp nhận sinh viên sau đến thực tập:</p>' . $info
                . '<p>Người hướng dẫn tại đơn vị: ' . $escape($row['mentor_name'] ?: '……………………………………') . ' · Điện thoại liên hệ: ' . $escape($row['company_phone'] ?: '……………………') . '</p>'
                . '<p>Đơn vị cam kết tạo điều kiện để sinh viên thực hiện kế hoạch thực tập và đánh giá kết quả thực tập theo phiếu đánh giá của nhà trường.</p>', '');
        }
        if ($type === 'commitment') {
            export_print('Bản cam kết thực tập', '<p class="doc-title">Bản cam kết thực hiện thực tập</p>' . $info
                . '<p>Tôi tên là <strong>' . $escape($row['student_name']) . '</strong>, xin cam kết:</p><ol><li>Chấp hành nội quy, quy định về an toàn, bảo mật thông tin của đơn vị thực tập.</li><li>Thực hiện đầy đủ kế hoạch, nhiệm vụ được giao; ghi nhật ký và nộp báo cáo đúng hạn trên hệ thống.</li><li>Không tự ý bỏ hoặc thay đổi nơi thực tập khi chưa được khoa đồng ý.</li><li>Chịu trách nhiệm về tính trung thực của nhật ký, báo cáo và sản phẩm thực tập.</li></ol>', '');
        }
        $criteria = export_html_table(['Tiêu chí', 'Điểm (0–100)', 'Nhận xét'], [['Kiến thức, kỹ năng chuyên môn', '', ''], ['Ý thức, thái độ, tinh thần chủ động', '', ''], ['Giao tiếp, làm việc nhóm', '', ''], ['Chấp hành kỷ luật, nội quy', '', '']]);
        export_print('Phiếu nhận xét thực tập', '<p class="doc-title">Phiếu nhận xét của đơn vị thực tập</p>' . $info . $criteria . '<p>Nhận xét chung: ……………………………………………………………………………………………………………</p><p>Đơn vị có thể đánh giá trực tuyến tại mục "Đánh giá" trên hệ thống thay cho phiếu này.</p>', '');
    }
    http_response_code(404);
    exit('Không có biểu mẫu này.');
}

// ---------------------------------------------------------------------------
// THỐNG KÊ
// ---------------------------------------------------------------------------

// Thống kê theo ngành: số sinh viên, thực tập (điểm TB, tỷ lệ đạt, có việc làm) và đồ án (điểm TB, tỷ lệ đạt).
// Có việc làm = doanh nghiệp xác nhận tuyển dụng (evaluations.offered) hoặc sinh viên tự khai (company_reviews.got_offer).
function statistics_by_major(): array
{
    return page_all(
        'SELECT COALESCE(NULLIF(TRIM(s.major), \'\'), \'Chưa cập nhật\') AS major, COUNT(DISTINCT s.id) AS students,
                COUNT(DISTINCT i.id) AS internships,
                AVG(i.final_score) AS internship_average,
                COUNT(DISTINCT CASE WHEN i.final_score >= ? THEN i.id END) AS internship_passed,
                COUNT(DISTINCT CASE WHEN i.final_score IS NOT NULL THEN i.id END) AS internship_scored,
                COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM evaluations e WHERE e.internship_id = i.id AND e.offered = 1) THEN s.id END) AS offered_confirmed,
                COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM evaluations e WHERE e.internship_id = i.id AND e.offered = 1) OR EXISTS (SELECT 1 FROM company_reviews cr WHERE cr.internship_id = i.id AND cr.got_offer = 1) THEN s.id END) AS employed,
                COUNT(DISTINCT th.id) AS theses,
                AVG(th.final_score) AS thesis_average,
                COUNT(DISTINCT CASE WHEN th.final_score >= b.pass_score THEN th.id END) AS thesis_passed,
                COUNT(DISTINCT CASE WHEN th.final_score IS NOT NULL THEN th.id END) AS thesis_scored
         FROM students s JOIN users u ON u.id = s.user_id
         LEFT JOIN internships i ON i.student_id = s.id AND i.status <> \'cancelled\'
         LEFT JOIN theses th ON th.student_id = s.id AND th.status <> \'declined\' LEFT JOIN batches b ON b.id = th.batch_id
         WHERE @it_faculty IS NULL OR s.faculty = @it_faculty
         GROUP BY major ORDER BY students DESC',
        [final_pass_score()]
    );
}

// Bảng so sánh giữa các đợt / năm học.
function statistics_batches(): array
{
    $rows = [];
    foreach (thesis_batches() as $batch) {
        $batchId = (int) $batch['id'];
        if ($batch['batch_type'] === 'thesis') {
            $theses = page_all('SELECT final_score, matched_rank FROM theses WHERE batch_id = ? AND status <> \'declined\'', [$batchId]);
            $scores = array_filter(array_column($theses, 'final_score'), static fn($score) => $score !== null);
            $ranked = array_filter(array_column($theses, 'matched_rank'), static fn($rank) => $rank !== null);
            $rows[] = [
                'id' => $batchId, 'name' => $batch['name'], 'batch_type' => 'thesis', 'school_year' => $batch['school_year'], 'status' => $batch['status'],
                'topics' => page_count('SELECT COUNT(*) FROM topics WHERE batch_id = ? AND status = \'approved\'', [$batchId]),
                'students' => count($theses),
                'first_rate' => $ranked ? round(count(array_filter($ranked, static fn($rank) => (int) $rank === 1)) * 100 / count($ranked), 1) : 0,
                'average' => $scores ? round(array_sum($scores) / count($scores), 2) : 0,
                'pass_rate' => $scores ? round(count(array_filter($scores, static fn($score) => (float) $score >= (float) $batch['pass_score'])) * 100 / count($scores), 1) : 0,
                'late' => page_count('SELECT COUNT(*) FROM thesis_submissions sb JOIN theses th ON th.id = sb.thesis_id WHERE th.batch_id = ? AND sb.is_late = 1', [$batchId]),
            ];
        } else {
            $scores = array_column(page_all('SELECT final_score FROM internships WHERE batch_id = ? AND final_score IS NOT NULL', [$batchId]), 'final_score');
            $rows[] = [
                'id' => $batchId, 'name' => $batch['name'], 'batch_type' => 'internship', 'school_year' => $batch['school_year'], 'status' => $batch['status'],
                'topics' => 0, 'students' => page_count('SELECT COUNT(*) FROM internships WHERE batch_id = ?', [$batchId]), 'first_rate' => 0,
                'average' => $scores ? round(array_sum($scores) / count($scores), 2) : 0,
                'pass_rate' => $scores ? round(count(array_filter($scores, static fn($score) => (float) $score >= final_pass_score())) * 100 / count($scores), 1) : 0,
                'late' => 0,
            ];
        }
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// DỮ LIỆU MÀN HÌNH
// ---------------------------------------------------------------------------

function admin_tools_screen_data(string $screen, array $data, array $user): ?array
{
    $studentId = (int) ($user['student_id'] ?? 0);
    $companyId = (int) ($user['company_id'] ?? 0);

    switch ($screen) {
        // Thông báo chung và lịch chung của mọi vai trò.
        case 'admin/announcements':
        case 'student/announcements':
        case 'lecturer/announcements':
        case 'company/announcements':
            $data['announcements'] = announcements_for($user);
            $data['calendar'] = $user['role'] === 'admin' ? [] : calendar_items($user);
            $data['groups'] = array_map(static fn($group) => $group[0], announcement_groups($user));
            $month = is_string($_GET['month'] ?? null) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
            $data['month'] = $month;
            $data['view'] = ($_GET['view'] ?? '') === 'list' ? 'list' : 'grid';
            $data['batches'] = thesis_batches();
            $data['classes'] = array_column(page_all('SELECT DISTINCT class_name FROM students WHERE class_name IS NOT NULL AND class_name <> \'\' ORDER BY class_name'), 'class_name');
            $upcoming = array_filter($data['calendar'], static fn($item) => strtotime((string) $item['at']) >= time());
            $data['metrics'] = [
                ['label' => 'Thông báo', 'value' => (string) count($data['announcements']), 'note' => 'Theo đợt, lớp và vai trò'],
                ['label' => 'Sự kiện sắp tới', 'value' => (string) count($upcoming), 'note' => 'Trong 120 ngày tới'],
                ['label' => 'Gần nhất', 'value' => $upcoming ? date('d/m', (int) strtotime((string) reset($upcoming)['at'])) : '—', 'note' => $upcoming ? (string) reset($upcoming)['title'] : 'Không có hạn chót sắp tới'],
            ];
            return $data;

        // Thống kê và báo cáo cho quản lý.
        case 'admin/statistics':
            $data['batches'] = thesis_batches('thesis');
            $batch = thesis_selected_batch('thesis');
            $data['batch'] = $batch;
            $data['comparison'] = statistics_batches();
            if ($batch) {
                $batchId = (int) $batch['id'];
                $theses = page_all('SELECT final_score, matched_rank, status FROM theses WHERE batch_id = ? AND status <> \'declined\'', [$batchId]);
                $bins = ['< 4' => 0, '4 – 5' => 0, '5 – 6.5' => 0, '6.5 – 8' => 0, '8 – 9' => 0, '≥ 9' => 0];
                foreach ($theses as $thesis) {
                    if ($thesis['final_score'] === null) {
                        continue;
                    }
                    $score = (float) $thesis['final_score'];
                    $bins[$score < 4 ? '< 4' : ($score < 5 ? '4 – 5' : ($score < 6.5 ? '5 – 6.5' : ($score < 8 ? '6.5 – 8' : ($score < 9 ? '8 – 9' : '≥ 9'))))]++;
                }
                $data['score_bins'] = $bins;
                $ranks = ['1' => 0, '2' => 0, '3' => 0, '4+' => 0, 'Thủ công / đề xuất' => 0];
                foreach ($theses as $thesis) {
                    $rank = $thesis['matched_rank'];
                    $ranks[$rank === null ? 'Thủ công / đề xuất' : ((int) $rank >= 4 ? '4+' : (string) (int) $rank)]++;
                }
                $data['rank_bins'] = $ranks;
                $data['topic_popularity'] = page_all('SELECT t.title, u.full_name AS lecturer_name, t.max_students, (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id) AS total, (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id AND p.rank_no = 1) AS first FROM topics t LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users u ON u.id = l.user_id WHERE t.batch_id = ? AND t.status = \'approved\' ORDER BY total DESC', [$batchId]);
                $data['lecturer_load'] = page_all(
                    'SELECT u.full_name, l.max_students,
                            (SELECT COUNT(*) FROM theses th WHERE th.batch_id = ? AND th.supervisor_id = l.id AND th.status <> \'declined\') AS supervising,
                            (SELECT COUNT(*) FROM theses th WHERE th.batch_id = ? AND th.reviewer_id = l.id) AS reviewing,
                            (SELECT COUNT(*) FROM council_members cm JOIN councils c ON c.id = cm.council_id WHERE c.batch_id = ? AND cm.lecturer_id = l.id) AS councils,
                            (SELECT AVG(th.final_score) FROM theses th WHERE th.batch_id = ? AND th.supervisor_id = l.id AND th.final_score IS NOT NULL) AS average
                     FROM lecturers l JOIN users u ON u.id = l.user_id ORDER BY supervising DESC, u.full_name',
                    [$batchId, $batchId, $batchId, $batchId]
                );
                $data['stages'] = [];
                foreach (THESIS_STAGES as $stage => $label) {
                    if ($stage === 'other') {
                        continue;
                    }
                    $data['stages'][] = [
                        'label' => $label,
                        'window' => batch_window_text($batchId, $stage),
                        'submitted' => page_count('SELECT COUNT(DISTINCT th.id) FROM theses th JOIN thesis_submissions sb ON sb.thesis_id = th.id WHERE th.batch_id = ? AND sb.stage = ?', [$batchId, $stage]),
                        'late' => page_count('SELECT COUNT(DISTINCT th.id) FROM theses th JOIN thesis_submissions sb ON sb.thesis_id = th.id WHERE th.batch_id = ? AND sb.stage = ? AND sb.is_late = 1', [$batchId, $stage]),
                        'total' => count($theses),
                    ];
                }
                $data['unmatched'] = page_count('SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = ? AND bs.eligible = 1 AND bs.student_id NOT IN (SELECT student_id FROM theses WHERE batch_id = ? AND status <> \'declined\')', [$batchId, $batchId]);
                $data['thesis_total'] = count($theses);
            }
            $data['company_stats'] = page_all(
                'SELECT c.company_name, c.mou_status, COUNT(i.id) AS interns, AVG(i.final_score) AS average, AVG(cr.rating) AS rating, SUM(cr.got_offer) AS offers, COUNT(cr.id) AS reviews
                 FROM companies c LEFT JOIN internships i ON i.company_id = c.id LEFT JOIN company_reviews cr ON cr.internship_id = i.id GROUP BY c.id HAVING interns > 0 ORDER BY interns DESC LIMIT 20'
            );
            $data['internship_years'] = page_all('SELECT YEAR(start_date) AS year, COUNT(*) AS total, AVG(final_score) AS average, SUM(final_score >= ?) AS passed, SUM(final_score IS NOT NULL) AS scored FROM internships GROUP BY YEAR(start_date) ORDER BY year DESC', [FINAL_PASS_SCORE]);
            $reviews = page_one('SELECT COUNT(*) AS total, AVG(rating) AS rating, AVG(mentoring_rating) AS mentoring, SUM(got_offer) AS offers FROM company_reviews') ?: [];
            $data['by_major'] = statistics_by_major();
            $data['metrics'] = [
                ['label' => 'Đồ án trong đợt', 'value' => (string) ($data['thesis_total'] ?? 0), 'note' => ($data['unmatched'] ?? 0) . ' sinh viên đủ điều kiện chưa được ghép'],
                ['label' => 'Thực tập sinh', 'value' => (string) page_count('SELECT COUNT(*) FROM internships'), 'note' => page_count('SELECT COUNT(*) FROM internships WHERE status = \'active\'') . ' đang thực tập'],
                ['label' => 'Hài lòng với đơn vị', 'value' => (int) ($reviews['total'] ?? 0) ? round((float) $reviews['rating'], 1) . '/5' : '—', 'note' => (int) ($reviews['total'] ?? 0) ? (int) $reviews['offers'] . '/' . (int) $reviews['total'] . ' sinh viên được giữ lại / có việc' : 'Chưa có đánh giá của sinh viên'],
            ];
            return $data;

        // Nhật ký hệ thống, sao lưu và phục hồi.
        case 'admin/audit':
            $filters = ['action' => trim((string) ($_GET['action_filter'] ?? '')), 'q' => trim((string) ($_GET['q'] ?? '')), 'date' => trim((string) ($_GET['date'] ?? ''))];
            $where = ['1 = 1'];
            $parameters = [];
            if ($filters['action'] !== '') {
                $where[] = 'a.action = ?';
                $parameters[] = $filters['action'];
            }
            if ($filters['q'] !== '') {
                $where[] = '(u.full_name LIKE ? OR a.target LIKE ? OR a.details LIKE ?)';
                array_push($parameters, ...array_fill(0, 3, '%' . $filters['q'] . '%'));
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) {
                $where[] = 'DATE(a.created_at) = ?';
                $parameters[] = $filters['date'];
            }
            $data['filters'] = $filters;
            $data['logs'] = page_all('SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 300', $parameters);
            $data['actions'] = array_column(page_all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');
            $data['locked_batches'] = page_all('SELECT name, locked_at FROM batches WHERE locked_at IS NOT NULL ORDER BY locked_at DESC');
            $data['metrics'] = [
                ['label' => 'Thao tác hôm nay', 'value' => (string) page_count('SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()'), 'note' => 'Được ghi nhật ký'],
                ['label' => 'Tổng nhật ký', 'value' => number_format(page_count('SELECT COUNT(*) FROM audit_logs')), 'note' => 'Ai sửa / ghép / chấm gì, lúc nào'],
                ['label' => 'Đợt đã chốt', 'value' => (string) count($data['locked_batches']), 'note' => 'Dữ liệu bị khóa chỉnh sửa'],
            ];
            return $data;

        // Quản trị · Thực tập tự tìm: xét duyệt khai báo nơi thực tập của sinh viên.
        case 'admin/placements':
            $data['placements'] = page_all('SELECT p.*, u.full_name, s.student_code, s.major FROM self_placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id WHERE @it_faculty IS NULL OR s.faculty = @it_faculty ORDER BY p.status = \'pending\' DESC, p.created_at DESC');
            $data['lecturers'] = thesis_lecturers();
            $data['batches'] = thesis_batches('internship');
            $data['metrics'] = [
                ['label' => 'Chờ xét duyệt', 'value' => (string) count(array_filter($data['placements'], static fn($row) => $row['status'] === 'pending')), 'note' => 'Khai báo nơi thực tập tự tìm'],
                ['label' => 'Đã duyệt', 'value' => (string) count(array_filter($data['placements'], static fn($row) => $row['status'] === 'approved')), 'note' => 'Đã tạo kỳ thực tập'],
                ['label' => 'Từ chối', 'value' => (string) count(array_filter($data['placements'], static fn($row) => $row['status'] === 'rejected')), 'note' => 'Cần khai báo lại'],
            ];
            return internship_screen_extras($screen, $data, $user);

        // Sinh viên · Hồ sơ thực tập: tự khai báo nơi thực tập, giấy tờ, chuyên cần, đánh giá đơn vị.
        case 'student/placement':
            $data['placements'] = page_all('SELECT * FROM self_placements WHERE student_id = ? ORDER BY created_at DESC', [$studentId]);
            $data['internships'] = page_all('SELECT v.*, cr.rating, cr.mentoring_rating, cr.would_recommend, cr.got_offer, cr.comments AS review_comments FROM v_student_internships v LEFT JOIN company_reviews cr ON cr.internship_id = v.internship_id WHERE v.student_id = ? ORDER BY v.start_date DESC', [$studentId]);
            foreach ($data['internships'] as &$internship) {
                $internship['documents'] = page_all('SELECT d.*, u.full_name FROM internship_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.internship_id = ? ORDER BY d.created_at DESC', [(int) $internship['internship_id']]);
                $internship['attendance'] = page_all('SELECT status, COUNT(*) AS total FROM attendance WHERE internship_id = ? GROUP BY status', [(int) $internship['internship_id']]);
            }
            unset($internship);
            $data['has_open_internship'] = page_count('SELECT COUNT(*) FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\')', [$studentId]) > 0;
            $data['metrics'] = [
                ['label' => 'Kỳ thực tập', 'value' => (string) count($data['internships']), 'note' => $data['has_open_internship'] ? 'Đang có kỳ thực tập' : 'Chưa có nơi thực tập'],
                ['label' => 'Khai báo tự tìm', 'value' => (string) count($data['placements']), 'note' => count(array_filter($data['placements'], static fn($row) => $row['status'] === 'pending')) . ' đang chờ khoa duyệt'],
                ['label' => 'Giấy tờ', 'value' => (string) array_sum(array_map(static fn($row) => count($row['documents']), $data['internships'])), 'note' => 'Đã tải lên'],
            ];
            return internship_screen_extras($screen, $data, $user);

        // Quản trị · Cấu hình & mẫu biểu.
        case 'admin/templates':
            $data['settings'] = [
                'doc_unit' => app_setting('doc_unit', 'Phòng Đào tạo'),
                'doc_place' => app_setting('doc_place', 'Nghệ An'),
                'internship_company_weight' => final_score_weights()['company'],
                'internship_pass_score' => final_pass_score(),
                'topic_two_level' => app_setting('topic_two_level', '1') === '1',
                'auto_fill_unmatched' => app_setting('auto_fill_unmatched', '1') === '1',
                'archive_students' => app_setting('archive_students', '1') === '1',
            ];
            $data['templates'] = [];
            foreach (DOCUMENT_TEMPLATES as $title => $default) {
                $key = substr(md5($title), 0, 12);
                $data['templates'][] = ['title' => $title, 'key' => $key, 'default' => str_replace("\n", ' / ', $default), 'value' => (string) app_setting('doc_sign_' . $key, '')];
            }
            return $data;

        // Doanh nghiệp · Nhật ký thực tập sinh (đã gửi) để người hướng dẫn tại đơn vị xác nhận.
        case 'company/diaries':
            $showAll = ($_GET['view'] ?? '') === 'all';
            $base = 'FROM diaries d JOIN internships i ON i.id = d.internship_id JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id LEFT JOIN batches b ON b.id = i.batch_id
                     WHERE i.company_id = ? AND (@it_mentor IS NULL OR i.mentor_user_id = @it_mentor) AND d.status <> \'draft\'';
            $data['show_all'] = $showAll;
            $data['pending_count'] = page_count('SELECT COUNT(*) ' . $base . ' AND d.company_status IS NULL', [$companyId]);
            $data['diaries'] = page_all(
                'SELECT d.id, d.diary_date, d.title, d.content, d.hours_worked, d.company_status, d.company_note, u.full_name AS student_name,
                        (i.status NOT IN (\'planned\', \'active\') OR b.locked_at IS NOT NULL) AS locked '
                . $base . ($showAll ? '' : ' AND d.company_status IS NULL') . ' ORDER BY d.diary_date DESC LIMIT 200',
                [$companyId]
            );
            $data['comments'] = item_comments('diary', array_column($data['diaries'], 'id'));
            $data['metrics'] = [
                ['label' => 'Chờ xác nhận', 'value' => (string) $data['pending_count'], 'note' => 'Nhật ký sinh viên đã gửi'],
                ['label' => 'Đã xác nhận', 'value' => (string) page_count('SELECT COUNT(*) ' . $base . ' AND d.company_status = \'confirmed\'', [$companyId]), 'note' => 'Đúng với công việc tại đơn vị'],
                ['label' => 'Đã ghi chú', 'value' => (string) page_count('SELECT COUNT(*) ' . $base . ' AND d.company_status = \'flagged\'', [$companyId]), 'note' => 'Báo sinh viên và giảng viên'],
            ];
            return $data;

        // Doanh nghiệp · Chuyên cần và giấy tờ của thực tập sinh.
        case 'company/attendance':
            $date = is_string($_GET['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $data['date'] = $date;
            $data['interns'] = page_all(
                'SELECT v.internship_id, v.student_name, v.student_code, v.position_title, a.status AS att_status, a.note AS att_note,
                        (SELECT COUNT(*) FROM attendance x WHERE x.internship_id = v.internship_id AND x.status = \'absent\') AS absences,
                        (SELECT COUNT(*) FROM attendance x WHERE x.internship_id = v.internship_id) AS recorded
                 FROM v_student_internships v LEFT JOIN attendance a ON a.internship_id = v.internship_id AND a.att_date = ? WHERE v.company_id = ? AND (@it_mentor IS NULL OR v.internship_id IN (SELECT id FROM internships WHERE mentor_user_id = @it_mentor)) AND v.status IN (\'planned\', \'active\') ORDER BY v.student_name',
                [$date, $companyId]
            );
            $data['documents'] = page_all('SELECT d.*, v.student_name FROM internship_documents d JOIN v_student_internships v ON v.internship_id = d.internship_id WHERE v.company_id = ? AND (@it_mentor IS NULL OR v.internship_id IN (SELECT id FROM internships WHERE mentor_user_id = @it_mentor)) ORDER BY d.created_at DESC LIMIT 100', [$companyId]);
            $data['metrics'] = [
                ['label' => 'Thực tập sinh', 'value' => (string) count($data['interns']), 'note' => 'Đang hoặc sắp thực tập'],
                ['label' => 'Đã điểm danh ngày ' . date('d/m', (int) strtotime($date)), 'value' => (string) count(array_filter($data['interns'], static fn($row) => $row['att_status'] !== null)), 'note' => 'Trên ' . count($data['interns']) . ' thực tập sinh'],
                ['label' => 'Buổi vắng', 'value' => (string) array_sum(array_column($data['interns'], 'absences')), 'note' => 'Tổng số buổi vắng không phép'],
            ];
            return $data;
    }
    return null;
}

// ---------------------------------------------------------------------------
// THAO TÁC
// ---------------------------------------------------------------------------

function admin_tools_handle_action(string $action, array $input, array $files): ?array
{
    $connection = database();
    $done = static fn(string $route, string $message): array => ['route' => $route, 'message' => $message];

    switch ($action) {
        case 'announcement_save':
            $user = action_user(['admin', 'lecturer', 'company']);
            $title = action_value($input, 'title', 200);
            $body = action_value($input, 'body', 10000);
            $audience = $user['role'] === 'admin' ? action_value($input, 'audience', 20) : 'all';
            $groupKey = action_value($input, 'group', 40);
            if ($title === '' || $body === '' || !in_array($audience, ['all', 'student', 'lecturer', 'company'], true)) {
                throw new DomainException('Hãy nhập tiêu đề, nội dung và chọn đối tượng nhận.');
            }
            $groups = announcement_groups($user);
            if ($groupKey !== '' || $user['role'] !== 'admin') {
                if (!isset($groups[$groupKey])) {
                    throw new DomainException('Hãy chọn nhóm nhận thông báo.');
                }
                [$groupLabel, $recipients] = $groups[$groupKey];
                $connection->prepare('INSERT INTO announcements (title, body, audience, recipients, group_label, created_by) VALUES (?, ?, \'all\', ?, ?, ?)')->execute([$title, $body, implode(',', $recipients), $groupLabel, (int) $user['id']]);
            } else {
                $batchId = thesis_int($input, 'batch_id') ?: null;
                $className = action_value($input, 'class_name', 100) ?: null;
                $connection->prepare('INSERT INTO announcements (title, body, audience, batch_id, class_name, created_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([$title, $body, $audience, $batchId, $className, (int) $user['id']]);
                $recipients = announcement_recipients($audience, $batchId, $className);
            }
            $roles = [];
            foreach (page_all('SELECT id, role FROM users WHERE id IN (' . thesis_in($recipients) . ')', $recipients ?: [0]) as $row) {
                $roles[(int) $row['id']] = $row['role'];
            }
            foreach ($recipients as $recipientId) {
                notify_user($recipientId, 'announcements', 'Thông báo: ' . $title, mb_strimwidth($body, 0, 200, '…', 'UTF-8'), ($roles[$recipientId] ?? 'student') . '/announcements', !empty($input['send_email']));
            }
            return $done($user['role'] . '/announcements', 'Đã đăng thông báo tới ' . count($recipients) . ' người' . (!empty($input['send_email']) ? ' (kèm email nếu đã bật MAIL_NOTIFY).' : '.'));

        case 'announcement_delete':
            $user = action_user(['admin', 'lecturer', 'company']);
            $connection->prepare('DELETE FROM announcements WHERE id = ?' . ($user['role'] === 'admin' ? '' : ' AND created_by = ' . (int) $user['id']))->execute([thesis_int($input, 'announcement_id')]);
            return $done($user['role'] . '/announcements', 'Đã xóa thông báo.');

        case 'users_import':
            action_user(['admin']);
            $file = $files['import_file'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name']) || (int) $file['size'] > 5 * 1024 * 1024) {
                throw new DomainException('Hãy chọn tệp Excel (.xlsx) hoặc CSV, tối đa 5 MB.');
            }
            [$created, $updated, $errors] = import_users(import_read_table((string) $file['tmp_name'], (string) $file['name']));
            $message = 'Đã nhập: ' . $created . ' tài khoản mới, ' . $updated . ' tài khoản được cập nhật.';
            if ($created) {
                $message .= ' Tài khoản mới chưa có mật khẩu trong tệp sẽ dùng chức năng "Quên mật khẩu" để đặt mật khẩu qua email.';
            }
            if ($errors) {
                $message .= ' ' . count($errors) . ' dòng lỗi: ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '');
            }
            return $done('admin/users', $message);

        // Phục hồi dữ liệu từ tệp sao lưu do hệ thống tạo ra (ghi đè toàn bộ dữ liệu hiện tại).
        case 'backup_restore':
            $user = action_user(['admin']);
            if (action_value($input, 'confirm', 20) !== 'KHÔI PHỤC') {
                throw new DomainException('Hãy gõ đúng chữ KHÔI PHỤC để xác nhận ghi đè dữ liệu.');
            }
            $file = $files['backup_file'] ?? [];
            $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name']) || !in_array($extension, ['sql', 'zip'], true) || (int) $file['size'] > 500 * 1024 * 1024) {
                throw new DomainException('Hãy chọn tệp sao lưu .zip (hoặc .sql) do hệ thống tạo ra.');
            }
            $uploadsRestored = 0;
            if ($extension === 'zip') {
                $archive = zip_read((string) $file['tmp_name']);
                $content = (string) ($archive['database.sql'] ?? '');
                $uploadRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
                foreach ($archive as $name => $data) {
                    // Chỉ ghi lại tệp trong uploads/, tên an toàn (không có "..", không phải tệp thực thi).
                    if (!preg_match('#^uploads/([a-z_]+/)*[A-Za-z0-9._-]+$#', $name) || str_contains($name, '..') || preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $name)) {
                        continue;
                    }
                    $target = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                    if (!is_dir(dirname($target))) {
                        mkdir(dirname($target), 0750, true);
                    }
                    if (str_starts_with($target, $uploadRoot . DIRECTORY_SEPARATOR)) {
                        file_put_contents($target, $data);
                        $uploadsRestored++;
                    }
                }
            } else {
                $content = (string) file_get_contents((string) $file['tmp_name']);
            }
            if (!str_starts_with($content, '-- Sao lưu InternTrack')) {
                throw new DomainException('Tệp không phải bản sao lưu do hệ thống tạo ra.');
            }
            $statements = preg_split('/;\s*\n/', $content) ?: [];
            $executed = 0;
            foreach ($statements as $statement) {
                $statement = trim((string) preg_replace('/^--.*$/m', '', $statement));
                if ($statement !== '') {
                    $connection->exec($statement);
                    $executed++;
                }
            }
            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
            audit_record('backup_restore', ['statements' => (string) $executed], $user);
            return $done('admin/audit', 'Đã phục hồi dữ liệu (' . $executed . ' câu lệnh, ' . $uploadsRestored . ' tệp tải lên). Nếu thiếu view thống kê, hãy import lại database/interntrack.sql.');

        case 'lecturer_quota_save':
            action_user(['admin']);
            // Hạn mức nhập ở trang phân công là hạn mức RIÊNG của đợt đang xem; không chọn đợt thì sửa hạn mức chung.
            $quotaValue = max(0, min(50, thesis_int($input, 'max_students')));
            if (thesis_int($input, 'batch_id')) {
                thesis_batch(thesis_int($input, 'batch_id'));
                $connection->prepare('INSERT INTO batch_lecturer_quotas (batch_id, lecturer_id, max_students) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE max_students = VALUES(max_students)')->execute([thesis_int($input, 'batch_id'), thesis_int($input, 'lecturer_id'), $quotaValue]);
                $connection->prepare('UPDATE lecturers SET is_head = ?, max_reviews = ?, max_councils = ? WHERE id = ?')->execute([!empty($input['is_head']) ? 1 : 0, max(0, min(50, thesis_int($input, 'max_reviews') ?: 10)), max(0, min(50, thesis_int($input, 'max_councils') ?: 6)), thesis_int($input, 'lecturer_id')]);
            } else {
                $connection->prepare('UPDATE lecturers SET max_students = ?, is_head = ?, research_areas = COALESCE(NULLIF(?, \'\'), research_areas) WHERE id = ?')->execute([$quotaValue, !empty($input['is_head']) ? 1 : 0, action_value($input, 'research_areas', 2000), thesis_int($input, 'lecturer_id')]);
            }
            return $done('admin/theses' . (thesis_int($input, 'batch_id') ? '&batch=' . thesis_int($input, 'batch_id') : ''), 'Đã cập nhật hạn mức và vai trò của giảng viên.');

        case 'placement_submit':
            $user = action_user(['student']);
            if (page_count('SELECT COUNT(*) FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\')', [(int) $user['student_id']]) > 0) {
                throw new DomainException('Bạn đang có kỳ thực tập, không cần khai báo thêm.');
            }
            if (page_count('SELECT COUNT(*) FROM self_placements WHERE student_id = ? AND status = \'pending\'', [(int) $user['student_id']]) > 0) {
                throw new DomainException('Bạn đang có một khai báo chờ khoa duyệt.');
            }
            internship_require_registration((int) $user['student_id']);
            $fields = ['company_name' => 200, 'address' => 255, 'contact_name' => 150, 'position_title' => 200];
            $values = [];
            foreach ($fields as $key => $length) {
                $values[$key] = action_value($input, $key, $length);
                if ($values[$key] === '') {
                    throw new DomainException('Hãy điền đủ tên đơn vị, địa chỉ, người hướng dẫn tại đơn vị và vị trí thực tập.');
                }
            }
            $start = action_value($input, 'start_date', 10);
            $end = action_value($input, 'end_date', 10);
            if (!strtotime($start) || !strtotime($end) || $end < $start) {
                throw new DomainException('Thời gian thực tập không hợp lệ.');
            }
            $email = action_value($input, 'contact_email', 255);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Email người liên hệ không hợp lệ.');
            }
            $path = store_uploaded_file($files['confirmation_file'] ?? [], 'placements', 5 * 1024 * 1024);
            $connection->prepare('INSERT INTO self_placements (student_id, company_name, tax_code, address, contact_name, contact_phone, contact_email, position_title, start_date, end_date, description, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $user['student_id'], $values['company_name'], action_value($input, 'tax_code', 50) ?: null, $values['address'], $values['contact_name'], action_value($input, 'contact_phone', 20) ?: null, $email ?: null, $values['position_title'], $start, $end, action_value($input, 'description', 5000) ?: null, $path]);
            foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\'') as $admin) {
                notify_user((int) $admin['id'], 'placements', 'Khai báo nơi thực tập tự tìm', $user['full_name'] . ' · ' . $values['company_name'], 'admin/placements');
            }
            return $done('student/placement', 'Đã gửi khai báo. Khoa sẽ xét duyệt và tạo kỳ thực tập cho bạn.');

        // Duyệt khai báo tự tìm: dùng doanh nghiệp đã có (theo mã số thuế) hoặc tạo hồ sơ đơn vị mới, rồi tạo vị trí và kỳ thực tập.
        case 'placement_review':
            $user = action_user(['admin']);
            $placement = page_one('SELECT * FROM self_placements WHERE id = ? AND status = \'pending\'', [thesis_int($input, 'placement_id')]);
            if (!$placement) {
                throw new DomainException('Không tìm thấy khai báo đang chờ.');
            }
            $note = action_value($input, 'review_note', 2000);
            if (($input['decision'] ?? '') !== 'approve') {
                $connection->prepare('UPDATE self_placements SET status = \'rejected\', review_note = ?, reviewed_by = ? WHERE id = ?')->execute([$note ?: null, (int) $user['id'], (int) $placement['id']]);
                notify_student((int) $placement['student_id'], 'placement', 'Khai báo nơi thực tập bị từ chối', $note, 'student/placement', true);
                return $done('admin/placements', 'Đã từ chối khai báo.');
            }
            $lecturerId = thesis_int($input, 'lecturer_id') ?: null;
            // Không chọn đợt thì gắn vào đợt thực tập đang mở của sinh viên (nếu có).
            $batchId = thesis_int($input, 'batch_id') ?: (internship_batch_for_student((int) $placement['student_id'])['id'] ?? null);
            $internshipId = database_transaction(static function (PDO $connection) use ($placement, $lecturerId, $batchId, $note, $user): int {
                $company = $placement['tax_code'] ? page_one('SELECT id FROM companies WHERE tax_code = ?', [$placement['tax_code']]) : null;
                $company ??= page_one('SELECT id FROM companies WHERE company_name = ?', [$placement['company_name']]);
                if (!$company) {
                    // Đơn vị chưa có tài khoản: tạo hồ sơ ở trạng thái không đăng nhập được; đơn vị có thể được kích hoạt sau.
                    $suffix = bin2hex(random_bytes(4));
                    $connection->prepare('INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, \'company\', \'inactive\')')
                        ->execute(['dn_' . $suffix, $placement['contact_email'] ?: 'dn_' . $suffix . '@tutimthuctap.vinhuni.edu.vn', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $placement['contact_name'], $placement['contact_phone']]);
                    $companyUserId = (int) $connection->lastInsertId();
                    do {
                        $code = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (page_one('SELECT id FROM companies WHERE company_code = ?', [$code]));
                    $connection->prepare('INSERT INTO companies (user_id, company_code, company_name, tax_code, email, phone, address, contact_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\')')
                        ->execute([$companyUserId, $code, $placement['company_name'], $placement['tax_code'], $placement['contact_email'], $placement['contact_phone'], $placement['address'], $placement['contact_name']]);
                    $company = ['id' => (int) $connection->lastInsertId()];
                }
                $connection->prepare('INSERT INTO positions (company_id, title, description, location, quantity, status) VALUES (?, ?, ?, ?, 1, \'closed\')')->execute([(int) $company['id'], $placement['position_title'], $placement['description'], $placement['address']]);
                $positionId = (int) $connection->lastInsertId();
                $status = strtotime((string) $placement['start_date']) <= time() ? 'active' : 'planned';
                $connection->prepare('INSERT INTO internships (student_id, company_id, position_id, lecturer_id, start_date, end_date, status, description, batch_id, self_found) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
                    ->execute([(int) $placement['student_id'], (int) $company['id'], $positionId, $lecturerId, $placement['start_date'], $placement['end_date'], $status, 'Sinh viên tự tìm nơi thực tập. Người hướng dẫn tại đơn vị: ' . $placement['contact_name'], $batchId]);
                $internshipId = (int) $connection->lastInsertId();
                $connection->prepare('UPDATE self_placements SET status = \'approved\', review_note = ?, reviewed_by = ?, internship_id = ? WHERE id = ?')->execute([$note ?: null, (int) $user['id'], $internshipId, (int) $placement['id']]);
                if ($placement['file_path']) {
                    $connection->prepare('INSERT INTO internship_documents (internship_id, doc_type, file_path, file_name, uploaded_by) VALUES (?, \'tiep_nhan\', ?, ?, ?)')->execute([$internshipId, $placement['file_path'], 'Xác nhận tiếp nhận (khai báo)', null]);
                }
                return $internshipId;
            });
            notify_student((int) $placement['student_id'], 'placement', 'Khai báo nơi thực tập đã được duyệt', $placement['company_name'], 'student/placement', true);
            if ($lecturerId) {
                notify_lecturer($lecturerId, 'students', 'Bạn được phân công theo dõi sinh viên thực tập', $placement['company_name'], 'lecturer/students');
            }
            return $done('admin/placements', 'Đã duyệt và tạo kỳ thực tập #' . $internshipId . '.');

        case 'internship_doc_upload':
            $user = action_user(['student', 'company', 'lecturer', 'admin']);
            $internship = action_internship(thesis_int($input, 'internship_id'), $user);
            $type = action_value($input, 'doc_type', 20);
            if (!isset(INTERNSHIP_DOC_TYPES[$type])) {
                throw new DomainException('Loại giấy tờ không hợp lệ.');
            }
            $file = $files['document'] ?? [];
            $path = store_uploaded_file($file, 'documents', 10 * 1024 * 1024);
            if ($path === null) {
                throw new DomainException('Hãy chọn tệp giấy tờ.');
            }
            $connection->prepare('INSERT INTO internship_documents (internship_id, doc_type, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?, ?)')->execute([(int) $internship['id'], $type, $path, mb_substr((string) ($file['name'] ?? INTERNSHIP_DOC_TYPES[$type]), 0, 255, 'UTF-8'), (int) $user['id']]);
            if ($user['role'] === 'company') {
                notify_student((int) $internship['student_id'], 'placement', 'Doanh nghiệp tải lên ' . mb_strtolower(INTERNSHIP_DOC_TYPES[$type], 'UTF-8'), '', 'student/placement');
            }
            return $done($user['role'] === 'company' ? 'company/attendance' : ($user['role'] === 'student' ? 'student/placement' : $user['role'] . '/dashboard'), 'Đã tải lên ' . mb_strtolower(INTERNSHIP_DOC_TYPES[$type], 'UTF-8') . '.');

        case 'settings_save':
            $user = action_user(['admin']);
            if (!empty($user['scope_faculty'])) {
                throw new DomainException('Chỉ phòng đào tạo được sửa cấu hình chung.');
            }
            app_setting_set('doc_unit', action_value($input, 'doc_unit', 150) ?: 'Phòng Đào tạo');
            app_setting_set('doc_place', action_value($input, 'doc_place', 80) ?: 'Nghệ An');
            app_setting_set('internship_company_weight', (string) (max(0, min(100, thesis_int($input, 'internship_company_weight'))) / 100));
            app_setting_set('internship_pass_score', (string) max(0.0, min(100.0, (float) ($input['internship_pass_score'] ?? 50))));
            app_setting_set('topic_two_level', !empty($input['topic_two_level']) ? '1' : '0');
            app_setting_set('auto_fill_unmatched', !empty($input['auto_fill_unmatched']) ? '1' : '0');
            app_setting_set('archive_students', !empty($input['archive_students']) ? '1' : '0');
            $signs = is_array($input['sign'] ?? null) ? $input['sign'] : [];
            foreach (DOCUMENT_TEMPLATES as $title => $default) {
                $key = substr(md5($title), 0, 12);
                app_setting_set('doc_sign_' . $key, mb_substr(trim((string) ($signs[$key] ?? '')), 0, 500, 'UTF-8'));
            }
            return $done('admin/templates', 'Đã lưu cấu hình.');

        // Chủ tài khoản doanh nghiệp tạo tài khoản người hướng dẫn tại đơn vị.
        case 'mentor_create':
            $user = action_user(['company']);
            if (!empty($user['is_mentor'])) {
                throw new DomainException('Chỉ tài khoản chính của doanh nghiệp được tạo người hướng dẫn.');
            }
            $fullName = action_value($input, 'full_name', 150);
            $email = action_value($input, 'email', 255);
            $username = action_value($input, 'username', 50);
            $password = (string) ($input['password'] ?? '');
            $phone = action_value($input, 'phone', 20);
            if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
                throw new DomainException('Hãy nhập họ tên, email hợp lệ và tên đăng nhập (chữ, số, dấu chấm/gạch).');
            }
            if (strlen($password) < 10) {
                throw new DomainException('Mật khẩu cần ít nhất 10 ký tự.');
            }
            if ($phone !== '' && !preg_match('/^[0-9+() .-]{8,20}$/', $phone)) {
                throw new DomainException('Số điện thoại không hợp lệ.');
            }
            if (page_one('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
                throw new DomainException('Tên đăng nhập hoặc email đã được dùng.');
            }
            database_transaction(static function (PDO $connection) use ($username, $email, $password, $fullName, $phone, $user, $input): void {
                $connection->prepare('INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, \'company\', \'active\')')->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $phone ?: null]);
                $connection->prepare('INSERT INTO company_mentors (user_id, company_id, job_title) VALUES (?, ?, ?)')->execute([(int) $connection->lastInsertId(), (int) $user['company_id'], action_value($input, 'job_title', 150) ?: null]);
            });
            return $done('company/inters', 'Đã tạo tài khoản người hướng dẫn ' . $fullName . '.');

        case 'mentor_toggle':
            $user = action_user(['company']);
            $mentor = page_one('SELECT u.id, u.status FROM company_mentors cm JOIN users u ON u.id = cm.user_id WHERE cm.user_id = ? AND cm.company_id = ?', [thesis_int($input, 'mentor_user_id'), (int) $user['company_id']]);
            if (!$mentor || !empty($user['is_mentor'])) {
                throw new DomainException('Không tìm thấy người hướng dẫn của doanh nghiệp.');
            }
            $connection->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$mentor['status'] === 'active' ? 'inactive' : 'active', (int) $mentor['id']]);
            return $done('company/inters', $mentor['status'] === 'active' ? 'Đã khóa tài khoản người hướng dẫn.' : 'Đã mở khóa tài khoản người hướng dẫn.');

        case 'mentor_assign':
            $user = action_user(['company']);
            if (!empty($user['is_mentor'])) {
                throw new DomainException('Chỉ tài khoản chính của doanh nghiệp được giao người hướng dẫn.');
            }
            $internship = action_internship(thesis_int($input, 'internship_id'), $user);
            action_require_open_internship($internship);
            $mentorId = thesis_int($input, 'mentor_user_id') ?: null;
            if ($mentorId && !page_one('SELECT cm.user_id FROM company_mentors cm JOIN users u ON u.id = cm.user_id WHERE cm.user_id = ? AND cm.company_id = ? AND u.status = \'active\'', [$mentorId, (int) $user['company_id']])) {
                throw new DomainException('Người hướng dẫn không thuộc doanh nghiệp hoặc đã bị khóa.');
            }
            $connection->prepare('UPDATE internships SET mentor_user_id = ? WHERE id = ?')->execute([$mentorId, (int) $internship['id']]);
            if ($mentorId) {
                notify_user($mentorId, 'interns', 'Bạn được giao hướng dẫn thực tập sinh', 'Xem ở mục Thực tập sinh.', 'company/inters');
            }
            return $done('company/inters', $mentorId ? 'Đã giao người hướng dẫn.' : 'Đã bỏ giao người hướng dẫn.');

        // Người hướng dẫn tại doanh nghiệp xác nhận nhật ký, hoặc ghi chú nội dung chưa đúng (báo sinh viên và giảng viên).
        case 'diary_confirm':
            $user = action_user(['company']);
            $diary = page_one('SELECT d.id, d.diary_date, d.student_id, i.* , d.id AS diary_id FROM diaries d JOIN internships i ON i.id = d.internship_id WHERE d.id = ? AND i.company_id = ? AND (@it_mentor IS NULL OR i.mentor_user_id = @it_mentor) AND d.status <> \'draft\'', [thesis_int($input, 'diary_id'), (int) $user['company_id']]);
            if (!$diary) {
                throw new DomainException('Không tìm thấy nhật ký của thực tập sinh.');
            }
            action_require_open_internship($diary);
            $flag = ($input['decision'] ?? '') === 'flag';
            $note = action_value($input, 'company_note', 1000);
            if ($flag && $note === '') {
                throw new DomainException('Hãy ghi rõ nội dung chưa đúng.');
            }
            $connection->prepare('UPDATE diaries SET company_status = ?, company_note = ?, company_reviewed_at = NOW() WHERE id = ?')->execute([$flag ? 'flagged' : 'confirmed', $flag ? $note : null, (int) $diary['diary_id']]);
            if ($flag) {
                $day = date('d/m/Y', (int) strtotime((string) $diary['diary_date']));
                notify_student((int) $diary['student_id'], 'diary', 'Doanh nghiệp ghi chú nhật ký ngày ' . $day, $note, 'student/diary');
                if (!empty($diary['lecturer_id'])) {
                    notify_lecturer((int) $diary['lecturer_id'], 'diaries', 'Doanh nghiệp ghi chú nhật ký', ($user['company_name'] ?? 'Doanh nghiệp') . ' · ngày ' . $day . ': ' . $note, 'lecturer/diaries');
                }
            }
            return $done('company/diaries' . (($input['view'] ?? '') === 'all' ? '&view=all' : ''), $flag ? 'Đã gửi ghi chú cho sinh viên và giảng viên.' : 'Đã xác nhận nhật ký.');

        case 'attendance_save':
            $user = action_user(['company']);
            $date = action_value($input, 'att_date', 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
                throw new DomainException('Ngày điểm danh không hợp lệ (không chọn ngày trong tương lai).');
            }
            $statuses = is_array($input['status'] ?? null) ? $input['status'] : [];
            $notes = is_array($input['note'] ?? null) ? $input['note'] : [];
            $saved = 0;
            foreach ($statuses as $internshipId => $status) {
                if (!is_string($status) || !isset(ATTENDANCE_LABELS[$status])) {
                    continue;
                }
                $internship = action_internship((int) $internshipId, $user);
                action_require_open_internship($internship);
                $note = is_string($notes[$internshipId] ?? null) ? mb_substr(trim($notes[$internshipId]), 0, 255, 'UTF-8') : '';
                $connection->prepare('INSERT INTO attendance (internship_id, att_date, status, note, recorded_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), recorded_by = VALUES(recorded_by)')
                    ->execute([(int) $internship['id'], $date, $status, $note ?: null, (int) $user['id']]);
                $saved++;
                // Cảnh báo giảng viên khi sinh viên vắng không phép từ 3 buổi.
                if ($status === 'absent' && $internship['lecturer_id'] && page_count('SELECT COUNT(*) FROM attendance WHERE internship_id = ? AND status = \'absent\'', [(int) $internship['id']]) === 3) {
                    $name = (string) (page_one('SELECT u.full_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) $internship['student_id']])['full_name'] ?? '');
                    notify_lecturer((int) $internship['lecturer_id'], 'progress', 'Cảnh báo chuyên cần: ' . $name, 'Sinh viên đã vắng không phép 3 buổi tại đơn vị.', 'lecturer/progress', true);
                }
            }
            return $done('company/attendance&date=' . $date, 'Đã lưu điểm danh cho ' . $saved . ' thực tập sinh.');

        case 'company_review_save':
            $user = action_user(['student']);
            $internship = action_internship(thesis_int($input, 'internship_id'), $user);
            $rating = thesis_int($input, 'rating');
            $mentoring = thesis_int($input, 'mentoring_rating');
            if ($rating < 1 || $rating > 5 || $mentoring < 1 || $mentoring > 5) {
                throw new DomainException('Hãy chấm từ 1 đến 5 sao.');
            }
            $connection->prepare('INSERT INTO company_reviews (internship_id, student_id, rating, mentoring_rating, would_recommend, got_offer, comments) VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), mentoring_rating = VALUES(mentoring_rating), would_recommend = VALUES(would_recommend), got_offer = VALUES(got_offer), comments = VALUES(comments)')
                ->execute([(int) $internship['id'], (int) $internship['student_id'], $rating, $mentoring, !empty($input['would_recommend']) ? 1 : 0, !empty($input['got_offer']) ? 1 : 0, action_value($input, 'comments', 3000) ?: null]);
            return $done('student/placement', 'Cảm ơn bạn! Đánh giá giúp khoa chọn đơn vị thực tập tốt hơn cho khóa sau.');
    }
    return null;
}

// Doanh nghiệp tự đăng ký tài khoản (trang công khai auth/register); tài khoản chờ khoa xác minh mới đăng nhập được.
function admin_tools_register_company(array $input): void
{
    $values = [];
    foreach (['company_name' => 200, 'company_code' => 10, 'address' => 255, 'full_name' => 150, 'email' => 255, 'username' => 50, 'phone' => 20, 'field' => 150, 'tax_code' => 50, 'website' => 255] as $key => $length) {
        $values[$key] = action_value($input, $key, $length);
    }
    $password = is_string($input['password'] ?? null) ? $input['password'] : '';
    if ($values['company_name'] === '' || $values['address'] === '' || $values['full_name'] === '' || $values['username'] === '') {
        throw new DomainException('Hãy điền tên doanh nghiệp, địa chỉ, người liên hệ và tên đăng nhập.');
    }
    if (!preg_match('/^\d{10}$/', $values['company_code'])) {
        throw new DomainException('Mã doanh nghiệp phải gồm đúng 10 chữ số.');
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Email không hợp lệ.');
    }
    if (strlen($password) < 10 || $password !== ($input['password_confirmation'] ?? null)) {
        throw new DomainException('Mật khẩu phải có ít nhất 10 ký tự và hai lần nhập phải khớp.');
    }
    if ($values['phone'] !== '' && !preg_match('/^[0-9+() .-]{8,20}$/', $values['phone'])) {
        throw new DomainException('Số điện thoại không hợp lệ.');
    }
    if (page_one('SELECT id FROM users WHERE username = ? OR email = ?', [$values['username'], $values['email']]) || page_one('SELECT id FROM companies WHERE company_code = ?' . ($values['tax_code'] !== '' ? ' OR tax_code = ?' : ''), array_filter([$values['company_code'], $values['tax_code']]))) {
        throw new DomainException('Tên đăng nhập, email, mã doanh nghiệp hoặc mã số thuế đã được đăng ký.');
    }
    database_transaction(static function (PDO $connection) use ($values, $password): void {
        $connection->prepare('INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, \'company\', \'inactive\')')->execute([$values['username'], $values['email'], password_hash($password, PASSWORD_DEFAULT), $values['full_name'], $values['phone'] ?: null]);
        $userId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO companies (user_id, company_code, company_name, tax_code, website, email, phone, address, field, contact_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')')
            ->execute([$userId, $values['company_code'], $values['company_name'], $values['tax_code'] ?: null, $values['website'] ?: null, $values['email'], $values['phone'] ?: null, $values['address'], $values['field'] ?: null, $values['full_name']]);
    });
    foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\'') as $admin) {
        notify_user((int) $admin['id'], 'companies', 'Doanh nghiệp đăng ký mới chờ xác minh', $values['company_name'], 'admin/companies');
    }
    audit_record('company_register', ['username' => $values['username'], 'email' => $values['email']], ['id' => null, 'role' => 'guest']);
}

// Bản ghi tệp cho các loại tải xuống mới: [file_path, title] hoặc null nếu không có quyền / không thuộc loại này.
function extension_download_record(string $type, int $recordId, array $user): ?array
{
    switch ($type) {
        case 'topic':
            $topic = page_one('SELECT t.attachment_path AS file_path, t.title, t.status, t.lecturer_id FROM topics t WHERE t.id = ?', [$recordId]);
            if ($topic && ($topic['status'] === 'approved' || $user['role'] === 'admin' || (int) $topic['lecturer_id'] === (int) ($user['lecturer_id'] ?? 0))) {
                return $topic;
            }
            return null;
        case 'submission':
            $submission = page_one('SELECT thesis_id, file_path, file_name AS title FROM thesis_submissions WHERE id = ?', [$recordId]);
            if (!$submission) {
                return null;
            }
            try {
                thesis_access((int) $submission['thesis_id'], $user);
            } catch (DomainException) {
                return null;
            }
            $submission['title'] = pathinfo((string) $submission['title'], PATHINFO_FILENAME);
            return $submission;
        // Quyển đồ án các khóa trước (đồ án đã có điểm, đợt đã công bố / chốt): giảng viên, quản trị; sinh viên nếu phòng đào tạo cho phép.
        case 'archive':
            $archived = page_one('SELECT sb.file_path, CONCAT(\'Do an - \', t.title) AS title FROM thesis_submissions sb JOIN theses th ON th.id = sb.thesis_id JOIN topics t ON t.id = th.topic_id JOIN batches b ON b.id = th.batch_id
                                  WHERE sb.id = ? AND sb.stage = \'report_final\' AND th.final_score IS NOT NULL AND (b.locked_at IS NOT NULL OR b.results_published_at IS NOT NULL)', [$recordId]);
            if (!$archived || ($user['role'] === 'student' && app_setting('archive_students', '1') !== '1') || $user['role'] === 'company') {
                return null;
            }
            return $archived;
        case 'report_version':
            $version = page_one('SELECT v.file_path, CONCAT(v.title, \' - ban \', v.version) AS title, r.internship_id FROM report_versions v JOIN reports r ON r.id = v.report_id WHERE v.id = ?', [$recordId]);
            if (!$version) {
                return null;
            }
            try {
                action_internship((int) $version['internship_id'], $user);
            } catch (DomainException) {
                return null;
            }
            return $version;
        case 'placement':
            return page_one('SELECT p.file_path, CONCAT(\'Xac nhan tiep nhan - \', p.company_name) AS title FROM self_placements p WHERE p.id = ? AND (p.student_id = ? OR ? = \'admin\')', [$recordId, (int) ($user['student_id'] ?? 0), $user['role']]);
        case 'internship_doc':
            $document = page_one('SELECT internship_id, file_path, file_name AS title FROM internship_documents WHERE id = ?', [$recordId]);
            if (!$document) {
                return null;
            }
            try {
                action_internship((int) $document['internship_id'], $user);
            } catch (DomainException) {
                return null;
            }
            $document['title'] = pathinfo((string) $document['title'], PATHINFO_FILENAME);
            return $document;
    }
    return null;
}
