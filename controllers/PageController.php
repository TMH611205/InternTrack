<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Bộ trợ giúp truy vấn dữ liệu dùng chung cho các màn hình dashboard và layout.
// Mục tiêu: gom các câu lệnh SQL lặp lại vào các hàm nhỏ, dễ đọc và dễ tái sử dụng.
function page_count(string $sql, array $parameters = []): int
{
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
}

function page_all(string $sql, array $parameters = []): array
{
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function page_one(string $sql, array $parameters = []): ?array
{
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    $row = $statement->fetch();
    return $row ?: null;
}

function page_status_label(string $status, array $labels): string
{
    return $labels[$status] ?? $status;
}

function page_date(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : 'Chưa cập nhật';
}

function page_week_bars(string $sql, array $parameters = []): array
{
    $bars = array_fill(0, 7, 0);
    foreach (page_all($sql, $parameters) as $row) {
        $day = (int) $row['day'];
        if ($day >= 0 && $day < 7) {
            $bars[$day] = (int) $row['total'];
        }
    }
    $maximum = max($bars);
    return $maximum ? array_map(static fn($value) => (int) round($value * 100 / $maximum), $bars) : $bars;
}

function page_recommendation_tokens(string $text): array
{
    $stopWords = array_fill_keys([
        'and',
        'are',
        'for',
        'from',
        'have',
        'into',
        'the',
        'their',
        'with',
        'các',
        'cho',
        'của',
        'được',
        'để',
        'khi',
        'là',
        'một',
        'những',
        'trong',
        'và',
        'với',
    ], true);
    $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_filter($tokens, static fn($token) => mb_strlen($token, 'UTF-8') > 2 && !isset($stopWords[$token])));
}

function page_recommendation_scores(array $profile, array $positions): array
{
    $profileTerms = [];
    foreach ([['major', 2.5], ['bio', 1.0]] as [$field, $weight]) {
        foreach (page_recommendation_tokens((string) ($profile[$field] ?? '')) as $token) {
            $profileTerms[$token] = ($profileTerms[$token] ?? 0) + $weight;
        }
    }

    $documents = [];
    $documentFrequency = [];
    foreach ($positions as $index => $position) {
        $terms = [];
        foreach ([['title', 3.0], ['requirements', 2.0], ['description', 1.0]] as [$field, $weight]) {
            foreach (page_recommendation_tokens((string) ($position[$field] ?? '')) as $token) {
                $terms[$token] = ($terms[$token] ?? 0) + $weight;
            }
        }
        $documents[$index] = $terms;
        foreach (array_keys($terms) as $token) {
            $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
        }
    }

    $documentCount = count($documents);
    $inverseFrequency = static fn(string $token): float => log(1 + ($documentCount + 1) / (($documentFrequency[$token] ?? 0) + 1));
    $profileVector = [];
    foreach ($profileTerms as $token => $frequency) {
        $profileVector[$token] = $frequency * $inverseFrequency($token);
    }
    $profileLength = sqrt(array_sum(array_map(static fn($weight) => $weight ** 2, $profileVector)));

    $scores = [];
    foreach ($documents as $index => $terms) {
        $documentVector = [];
        foreach ($terms as $token => $frequency) {
            $documentVector[$token] = (1 + log($frequency)) * $inverseFrequency($token);
        }
        $documentLength = sqrt(array_sum(array_map(static fn($weight) => $weight ** 2, $documentVector)));
        $dotProduct = 0.0;
        foreach ($profileVector as $token => $weight) {
            $dotProduct += $weight * ($documentVector[$token] ?? 0);
        }
        $scores[$index] = $profileLength > 0 && $documentLength > 0
            ? (int) round(100 * $dotProduct / ($profileLength * $documentLength))
            : 0;
    }

    return $scores;
}

// Tạo dữ liệu hiển thị động cho từng màn hình theo vai trò người dùng.
// Hàm này xử lý phần lớn dữ liệu dashboard, bảng, card, profile và tiến độ của từng role.
// Minh chứng sinh viên gửi kèm khi báo hoàn thành nhiệm vụ (null nếu chưa gửi).
function page_task_submission(array $task): ?array
{
    if (empty($task['submission_link']) && empty($task['submission_file']) && empty($task['submission_note'])) {
        return null;
    }
    return [
        'link' => $task['submission_link'] ?: null,
        'has_file' => !empty($task['submission_file']),
        'file_name' => !empty($task['submission_file']) ? basename((string) $task['submission_file']) : null,
        'note' => $task['submission_note'] ?: null,
        'submitted_at' => !empty($task['submitted_at']) ? date('d/m/Y H:i', (int) strtotime((string) $task['submitted_at'])) : null,
    ];
}

// Kế hoạch thực tập do nhà trường phân công cho kỳ thực tập hiện tại của sinh viên (chỉ xem).
function page_student_plan(int $studentId): ?array
{
    $internship = page_one(
        'SELECT i.training_plan, i.start_date, i.end_date, c.company_name, p.title FROM internships i JOIN companies c ON c.id = i.company_id JOIN positions p ON p.id = i.position_id
         WHERE i.student_id = ? AND i.status IN (\'planned\', \'active\') ORDER BY i.start_date DESC LIMIT 1',
        [$studentId]
    );
    if (!$internship) {
        return null;
    }
    return [
        'heading' => $internship['company_name'] . ' · ' . $internship['title'],
        'meta' => page_date($internship['start_date']) . ' – ' . page_date($internship['end_date']),
        'text' => trim((string) $internship['training_plan']),
    ];
}

// Câu chào theo giờ trong ngày kèm họ tên đầy đủ của người đang đăng nhập.
function page_greeting(array $user, string $prefix = 'Chào'): string
{
    $hour = (int) date('G');
    $period = $hour < 11 ? 'buổi sáng' : ($hour < 13 ? 'buổi trưa' : ($hour < 18 ? 'buổi chiều' : 'buổi tối'));
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($user['full_name'] ?? ''))) ?: 'bạn';
    return $prefix . ' ' . $period . ', ' . $name;
}

function load_screen_data(string $screen, array $data, array $user): array
{
    $studentId = isset($user['student_id']) ? (int) $user['student_id'] : 0;
    $companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
    $lecturerId = isset($user['lecturer_id']) ? (int) $user['lecturer_id'] : 0;
    $role = $user['role'];
    $applicationLabels = ['pending' => 'Chờ xem', 'reviewing' => 'Đang xem', 'accepted' => 'Được nhận', 'rejected' => 'Chưa phù hợp', 'withdrawn' => 'Đã rút'];
    $internshipLabels = ['planned' => 'Sắp bắt đầu', 'active' => 'Đang diễn ra', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy'];
    $taskLabels = ['todo' => 'Cần làm', 'in_progress' => 'Đang làm', 'submitted' => 'Chờ phản hồi', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy'];
    $diaryLabels = ['draft' => 'Bản nháp', 'submitted' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Cần chỉnh sửa'];
    $reportLabels = ['draft' => 'Bản nháp', 'submitted' => 'Chờ xem', 'reviewing' => 'Đang xem', 'approved' => 'Đã duyệt', 'rejected' => 'Cần chỉnh sửa'];
    $positionLabels = ['draft' => 'Bản nháp', 'open' => 'Đang mở', 'closed' => 'Đã đóng', 'cancelled' => 'Đã hủy'];

    switch ($screen) {
        case 'student/dashboard':
            $data['plan'] = page_student_plan($studentId);
            $internship = page_one(
                'SELECT i.*, p.title, c.company_name, u.full_name AS mentor_name FROM internships i JOIN positions p ON p.id = i.position_id JOIN companies c ON c.id = i.company_id LEFT JOIN lecturers l ON l.id = i.lecturer_id LEFT JOIN users u ON u.id = l.user_id WHERE i.student_id = ? AND i.status IN (\'planned\', \'active\') ORDER BY i.start_date DESC LIMIT 1',
                [$studentId]
            );
            $taskTotal = page_count('SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> \'cancelled\'', [$studentId]);
            $taskDone = page_count('SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = \'completed\'', [$studentId]);
            $diaryTotal = page_count('SELECT COUNT(*) FROM diaries WHERE student_id = ? AND status = \'approved\'', [$studentId]);
            $diaryCount = page_count('SELECT COUNT(*) FROM diaries WHERE student_id = ?', [$studentId]);
            $progress = $taskTotal > 0 ? (int) round($taskDone * 100 / $taskTotal) : 0;
            $data['title'] = page_greeting($user);
            $data['description'] = $internship
                ? 'Kỳ thực tập tại ' . $internship['company_name'] . ' đang diễn ra. Đây là những việc đáng chú ý hôm nay.'
                : 'Theo dõi cơ hội, hồ sơ và những bước tiếp theo trong hành trình thực tập của bạn.';
            $data['metrics'] = [
                ['label' => 'Tiến độ nhiệm vụ', 'value' => $progress . '%', 'note' => $taskDone . '/' . $taskTotal . ' nhiệm vụ hoàn tất'],
                ['label' => 'Nhật ký đã duyệt', 'value' => (string) $diaryTotal, 'note' => $diaryCount . ' mục đã ghi'],
                ['label' => 'Nhiệm vụ đang mở', 'value' => (string) max(0, $taskTotal - $taskDone), 'note' => 'Cập nhật từ kỳ thực tập'],
            ];
            $data['progress'] = [
                'label' => $internship ? $internship['title'] . ' · ' . $internship['company_name'] : 'Hoàn thành nhiệm vụ được giao',
                'value' => $progress . '%',
                'note' => $internship ? 'Kỳ thực tập ' . page_date($internship['start_date']) . ' – ' . page_date($internship['end_date']) : 'Chưa có kỳ thực tập đang diễn ra.',
            ];
            $data['bars'] = page_week_bars('SELECT WEEKDAY(diary_date) AS day, COUNT(*) AS total FROM diaries WHERE student_id = ? AND YEARWEEK(diary_date, 1) = YEARWEEK(CURDATE(), 1) GROUP BY day', [$studentId]);
            $recent = page_all(
                'SELECT title, status, updated_at AS happened_at, description FROM tasks WHERE assigned_to = ? UNION ALL SELECT COALESCE(title, \'Nhật ký thực tập\') AS title, status, updated_at AS happened_at, content AS description FROM diaries WHERE student_id = ? ORDER BY happened_at DESC LIMIT 4',
                [$studentId, $studentId]
            );
            $data['activities'] = array_map(static fn($item) => [
                'time' => page_date($item['happened_at']),
                'title' => $item['title'],
                'description' => mb_strimwidth((string) ($item['description'] ?? ''), 0, 105, '…', 'UTF-8'),
                'status' => page_status_label($item['status'], $taskLabels + $diaryLabels),
            ], $recent);
            if (!$data['activities']) {
                $data['activities'] = [['time' => 'Bắt đầu', 'title' => 'Chưa có hoạt động', 'description' => 'Các cập nhật sẽ xuất hiện khi có dữ liệu.', 'status' => 'Mới']];
            }
            break;

        case 'student/internships':
            $positions = page_all(
                'SELECT p.id, p.title, p.description, p.requirements, p.location, p.quantity, p.deadline, c.company_name, c.address AS company_address, a.status AS application_status FROM positions p JOIN companies c ON c.id = p.company_id LEFT JOIN applications a ON a.position_id = p.id AND a.student_id = ? WHERE p.status = \'open\' AND (p.deadline IS NULL OR p.deadline >= CURDATE()) AND c.status = \'active\' AND TRIM(COALESCE(c.address, \'\')) <> \'\' AND TRIM(COALESCE(p.location, \'\')) <> \'\' ORDER BY p.deadline, p.created_at DESC',
                [$studentId]
            );
            $studentProfile = page_one('SELECT major, bio FROM students WHERE id = ?', [$studentId]) ?: [];
            $matchScores = page_recommendation_scores($studentProfile, $positions);
            foreach ($positions as $index => &$position) {
                $position['match_score'] = $matchScores[$index] ?? 0;
            }
            unset($position);
            usort($positions, static fn($left, $right) => ($right['match_score'] <=> $left['match_score']) ?: strcmp((string) $left['deadline'], (string) $right['deadline']));
            $data['cards'] = array_map(static fn($position) => [
                'id' => (int) $position['id'],
                'title' => $position['title'],
                'meta' => ($position['location'] ?: 'Tại văn phòng công ty') . ' · ' . $position['quantity'] . ' vị trí',
                'company_name' => $position['company_name'],
                'company_address' => $position['company_address'],
                'work_location' => $position['location'] ?: 'Tại văn phòng công ty',
                'description' => mb_strimwidth((string) $position['description'], 0, 130, '…', 'UTF-8') . ' Hạn ' . page_date($position['deadline']) . '. Độ phù hợp hồ sơ: ' . $position['match_score'] . '%.',
                'tag' => $position['application_status'] ? page_status_label($position['application_status'], $applicationLabels) : ($position['match_score'] >= 20 ? 'Gợi ý phù hợp · ' . $position['match_score'] . '%' : 'Đang tuyển'),
                'match_score' => (int) $position['match_score'],
                'deadline' => (string) ($position['deadline'] ?? ''),
            ], $positions);
            $data['metrics'] = [
                ['label' => 'Vị trí đang mở', 'value' => (string) count($positions), 'note' => 'Ưu tiên vị trí khớp chuyên ngành'],
                ['label' => 'Đơn đã nộp', 'value' => (string) page_count('SELECT COUNT(*) FROM applications WHERE student_id = ?', [$studentId]), 'note' => 'Theo dõi trong mục đơn ứng tuyển'],
                ['label' => 'Doanh nghiệp', 'value' => (string) page_count('SELECT COUNT(DISTINCT company_id) FROM positions WHERE status = \'open\' AND (deadline IS NULL OR deadline >= CURDATE())'), 'note' => 'Đang có vị trí tuyển'],
            ];
            break;

        case 'student/applications':
            $applications = page_all(
                'SELECT a.id, p.title, c.company_name, a.applied_at, a.status FROM applications a JOIN positions p ON p.id = a.position_id JOIN companies c ON c.id = p.company_id WHERE a.student_id = ? ORDER BY a.applied_at DESC',
                [$studentId]
            );
            $data['rows'] = array_map(static fn($row) => [$row['title'], $row['company_name'], page_date($row['applied_at']), page_status_label($row['status'], $applicationLabels)], $applications);
            $data['row_ids'] = array_column($applications, 'id');
            $data['metrics'] = [
                ['label' => 'Tổng đơn', 'value' => (string) count($applications), 'note' => 'Tất cả vị trí đã ứng tuyển'],
                ['label' => 'Đang chờ', 'value' => (string) count(array_filter($applications, static fn($row) => in_array($row['status'], ['pending', 'reviewing'], true))), 'note' => 'Chờ doanh nghiệp phản hồi'],
                ['label' => 'Được nhận', 'value' => (string) count(array_filter($applications, static fn($row) => $row['status'] === 'accepted')), 'note' => 'Đơn đã được chấp nhận'],
            ];
            break;

        case 'student/tasks':
            $data['plan'] = page_student_plan($studentId);
            $tasks = page_all('SELECT id, title, description, priority, status, due_date, submission_link, submission_file, submission_note, submitted_at FROM tasks WHERE assigned_to = ? AND status <> \'cancelled\' ORDER BY due_date, created_at DESC', [$studentId]);
            $data['boards'] = [
                ['title' => 'Cần làm', 'items' => []],
                ['title' => 'Đang thực hiện', 'items' => []],
                ['title' => 'Chờ phản hồi / Hoàn tất', 'items' => []],
            ];
            foreach ($tasks as $task) {
                $column = match ($task['status']) {
                    'todo' => 0,
                    'submitted', 'completed' => 2,
                    default => 1,
                };
                $data['boards'][$column]['items'][] = [
                    'id' => (int) $task['id'],
                    'title' => $task['title'],
                    'meta' => 'Ưu tiên ' . $task['priority'] . ' · Hạn ' . page_date($task['due_date']),
                    'description' => $task['description'],
                    'status' => $task['status'],
                    'submission' => page_task_submission($task),
                ];
            }
            break;

        case 'student/diary':
            $currentInternship = page_one('SELECT id FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\') ORDER BY start_date DESC LIMIT 1', [$studentId]);
            $data['internship_id'] = $currentInternship ? (int) $currentInternship['id'] : null;
            $diaries = page_all('SELECT id, diary_date, title, content, hours_worked, status FROM diaries WHERE student_id = ? ORDER BY diary_date DESC', [$studentId]);
            $data['activities'] = array_map(static fn($row) => [
                'id' => (int) $row['id'],
                'time' => page_date($row['diary_date']) . ' · ' . ($row['hours_worked'] ?? 0) . ' giờ',
                'title' => $row['title'] ?: 'Nhật ký thực tập',
                'description' => $row['content'],
                'status' => page_status_label($row['status'], $diaryLabels),
            ], $diaries);
            $data['metrics'] = [
                ['label' => 'Tổng nhật ký', 'value' => (string) count($diaries), 'note' => 'Các ngày đã ghi nhận'],
                ['label' => 'Giờ đã ghi', 'value' => (string) array_sum(array_map(static fn($row) => (float) $row['hours_worked'], $diaries)) . 'h', 'note' => 'Tổng thời gian thực tập'],
                ['label' => 'Chờ duyệt', 'value' => (string) count(array_filter($diaries, static fn($row) => $row['status'] === 'submitted')), 'note' => 'Giảng viên phụ trách sẽ phản hồi'],
            ];
            break;

        case 'student/evaluation':
            $evaluations = page_all(
                'SELECT e.*, u.full_name AS evaluator_name FROM evaluations e JOIN internships i ON i.id = e.internship_id JOIN users u ON u.id = e.evaluator_user_id WHERE i.student_id = ? AND e.status = \'submitted\' ORDER BY e.submitted_at DESC',
                [$studentId]
            );
            $criteria = [
                ['technical_score', 'Chuyên môn'],
                ['attitude_score', 'Tinh thần chủ động'],
                ['communication_score', 'Giao tiếp'],
                ['discipline_score', 'Kỷ luật'],
            ];
            $data['criteria'] = array_map(static function ($criterion) use ($evaluations): array {
                $scores = array_filter(array_column($evaluations, $criterion[0]), static fn($score) => $score !== null);
                return ['name' => $criterion[1], 'score' => $scores ? (string) round(array_sum($scores) / count($scores)) : '0'];
            }, $criteria);
            $overallScores = array_filter(array_column($evaluations, 'overall_score'), static fn($score) => $score !== null);
            $averageScore = $overallScores ? round(array_sum($overallScores) / count($overallScores)) : 0;
            $data['metrics'] = [
                ['label' => 'Điểm tổng hợp', 'value' => $averageScore . '/100', 'note' => count($evaluations) . ' đánh giá đã gửi'],
                ['label' => 'Đã đánh giá', 'value' => (string) count($evaluations), 'note' => 'Từ doanh nghiệp và giảng viên'],
                ['label' => 'Còn lại', 'value' => (string) max(0, page_count('SELECT COUNT(*) FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\')', [$studentId]) * 2 - count($evaluations)), 'note' => 'Các mốc đánh giá còn thiếu'],
            ];
            $latestEvaluation = $evaluations[0] ?? null;
            $data['quote'] = ($latestEvaluation['comments'] ?? '') ?: 'Chưa có nhận xét. Phản hồi sẽ xuất hiện sau khi người hướng dẫn gửi đánh giá.';
            $data['evaluatorName'] = $latestEvaluation['evaluator_name'] ?? 'Người hướng dẫn';
            $data['evaluatorRole'] = ($latestEvaluation['evaluator_type'] ?? '') === 'company' ? 'Doanh nghiệp' : 'Giảng viên';
            break;

        case 'student/internship-detail':
            $positionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $sql = 'SELECT p.*, c.company_name, c.address AS company_address, a.status AS application_status FROM positions p JOIN companies c ON c.id = p.company_id LEFT JOIN applications a ON a.position_id = p.id AND a.student_id = ? WHERE p.status = \'open\' AND (p.deadline IS NULL OR p.deadline >= CURDATE()) AND c.status = \'active\' AND TRIM(COALESCE(c.address, \'\')) <> \'\' AND TRIM(COALESCE(p.location, \'\')) <> \'\'';
            $parameters = [$studentId];
            if ($positionId) {
                $sql .= ' AND p.id = ?';
                $parameters[] = $positionId;
            }
            $sql .= ' ORDER BY p.deadline LIMIT 1';
            $position = page_one($sql, $parameters);
            if ($position) {
                $data['title'] = $position['title'];
                $data['description'] = $position['company_name'] . ' · ' . $position['location'];
                $data['metrics'] = [
                    ['label' => 'Số lượng', 'value' => (string) $position['quantity'], 'note' => 'Vị trí đang tuyển'],
                    ['label' => 'Hạn ứng tuyển', 'value' => page_date($position['deadline']), 'note' => 'Kiểm tra trước khi nộp hồ sơ'],
                    ['label' => 'Trạng thái', 'value' => page_status_label($position['status'], $positionLabels), 'note' => $position['company_name']],
                ];
                $data['position_id'] = (int) $position['id'];
                $data['application_status'] = $position['application_status'];
                $data['fields'] = [
                    ['label' => 'Công ty tuyển dụng', 'value' => $position['company_name']],
                    ['label' => 'Địa chỉ công ty', 'value' => $position['company_address']],
                    ['label' => 'Mô tả', 'value' => $position['description'] ?: 'Doanh nghiệp chưa bổ sung mô tả.'],
                    ['label' => 'Yêu cầu', 'value' => $position['requirements'] ?: 'Doanh nghiệp chưa bổ sung yêu cầu.'],
                    ['label' => 'Quyền lợi', 'value' => $position['benefits'] ?: 'Trao đổi trực tiếp với doanh nghiệp.'],
                    ['label' => 'Địa điểm làm việc', 'value' => $position['location'] ?: 'Tại văn phòng công ty'],
                ];
            }
            break;

        case 'student/profile':
            $profile = page_one('SELECT * FROM students WHERE user_id = ?', [(int) $user['id']]);
            $data['profile_record'] = $profile;
            $data['title'] = $user['full_name'];
            $data['description'] = trim(implode(' · ', array_filter([$profile['major'] ?? null, $profile['university'] ?? null])));
            $data['fields'] = [
                ['label' => 'Mã sinh viên', 'value' => $profile['student_code'] ?? 'Chưa cập nhật'],
                ['label' => 'Email', 'value' => $user['email']],
                ['label' => 'Lớp / Khoa', 'value' => trim(($profile['class_name'] ?? '') . ' · ' . ($profile['faculty'] ?? ''), ' ·') ?: 'Chưa cập nhật'],
                ['label' => 'Số điện thoại', 'value' => $user['phone'] ?: 'Chưa cập nhật'],
                ['label' => 'Địa chỉ', 'value' => ($profile['address'] ?? '') ?: 'Chưa cập nhật'],
                ['label' => 'Giới thiệu', 'value' => ($profile['bio'] ?? '') ?: 'Chưa có giới thiệu.'],
            ];
            break;

        case 'student/reports':
            $currentInternship = page_one('SELECT id FROM internships WHERE student_id = ? AND status IN (\'planned\', \'active\') ORDER BY start_date DESC LIMIT 1', [$studentId]);
            $data['internship_id'] = $currentInternship ? (int) $currentInternship['id'] : null;
            $reports = page_all('SELECT id, report_type, title, status, submitted_at, feedback, file_path FROM reports WHERE student_id = ? ORDER BY created_at DESC', [$studentId]);
            $reportTypeLabels = ['proposal' => 'Đề cương', 'midterm' => 'Giữa kỳ', 'final' => 'Cuối kỳ', 'other' => 'Khác'];
            $data['cards'] = array_map(static fn($row) => [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'meta' => $reportTypeLabels[$row['report_type']] . ' · ' . page_date($row['submitted_at']),
                'description' => $row['feedback'] ?: 'Trạng thái: ' . page_status_label($row['status'], $reportLabels),
                'tag' => page_status_label($row['status'], $reportLabels),
                'file_path' => $row['file_path'],
            ], $reports);
            break;

        case 'company/dashboard':
            $data['title'] = page_greeting($user);
            $data['eyebrow'] = (string) ($user['company_name'] ?? $data['eyebrow']);
            $positionCount = page_count('SELECT COUNT(*) FROM positions WHERE company_id = ? AND status = \'open\'', [$companyId]);
            $applicationCount = page_count('SELECT COUNT(*) FROM applications a JOIN positions p ON p.id = a.position_id WHERE p.company_id = ? AND a.status IN (\'pending\', \'reviewing\')', [$companyId]);
            $internCount = page_count('SELECT COUNT(*) FROM internships WHERE company_id = ? AND status IN (\'active\', \'planned\')', [$companyId]);
            $data['metrics'] = [
                ['label' => 'Vị trí đang tuyển', 'value' => (string) $positionCount, 'note' => 'Vị trí mở của doanh nghiệp'],
                ['label' => 'Hồ sơ chờ xử lý', 'value' => (string) $applicationCount, 'note' => 'Cần xem xét và phản hồi'],
                ['label' => 'Thực tập sinh', 'value' => (string) $internCount, 'note' => 'Kỳ thực tập đang hoạt động'],
            ];
            $submittedEvaluations = page_count('SELECT COUNT(*) FROM evaluations e JOIN internships i ON i.id = e.internship_id WHERE i.company_id = ? AND e.evaluator_type = \'company\' AND e.status = \'submitted\'', [$companyId]);
            $data['progress'] = ['label' => 'Đánh giá đã gửi', 'value' => ($internCount ? (int) round(min($internCount, $submittedEvaluations) * 100 / $internCount) : 0) . '%', 'note' => $submittedEvaluations . '/' . $internCount . ' kỳ thực tập có đánh giá'];
            $data['bars'] = page_week_bars('SELECT WEEKDAY(applied_at) AS day, COUNT(*) AS total FROM applications a JOIN positions p ON p.id = a.position_id WHERE p.company_id = ? AND YEARWEEK(applied_at, 1) = YEARWEEK(CURDATE(), 1) GROUP BY day', [$companyId]);
            $latestApplications = page_all('SELECT u.full_name, p.title, a.applied_at, a.status FROM applications a JOIN positions p ON p.id = a.position_id JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE p.company_id = ? ORDER BY a.applied_at DESC LIMIT 4', [$companyId]);
            $data['activities'] = array_map(static fn($row) => ['time' => page_date($row['applied_at']), 'title' => $row['full_name'] . ' · ' . $row['title'], 'description' => 'Đơn ứng tuyển mới, trạng thái ' . page_status_label($row['status'], $applicationLabels) . '.', 'status' => page_status_label($row['status'], $applicationLabels)], $latestApplications);
            if (!$data['activities']) {
                $data['activities'] = [['time' => 'Hôm nay', 'title' => 'Chưa có hồ sơ mới', 'description' => 'Hồ sơ ứng tuyển sẽ xuất hiện tại đây.', 'status' => 'Mới']];
            }
            break;

        case 'company/positions':
        case 'admin/positions':
            $parameters = $screen === 'company/positions' ? [$companyId] : [];
            $filter = $screen === 'company/positions' ? 'WHERE p.company_id = ?' : '';
            $positions = page_all(
                'SELECT p.id, p.title, p.description, p.requirements, p.benefits, p.location, c.company_name, c.address AS company_address, p.quantity, p.deadline, p.status, COUNT(a.id) AS applicant_count FROM positions p JOIN companies c ON c.id = p.company_id LEFT JOIN applications a ON a.position_id = p.id ' . $filter . ' GROUP BY p.id ORDER BY p.created_at DESC',
                $parameters
            );
            if ($screen === 'company/positions') {
                $data['cards'] = array_map(static fn($row) => [
                    'id' => (int) $row['id'],
                    'title' => $row['title'],
                    'meta' => $row['quantity'] . ' vị trí · Hạn ' . page_date($row['deadline']),
                    'description' => 'Công ty: ' . $row['company_name'] . ' · Địa chỉ: ' . ($row['company_address'] ?: 'Chưa cập nhật') . ' · ' . $row['applicant_count'] . ' hồ sơ',
                    'position_description' => (string) ($row['description'] ?? ''),
                    'requirements' => (string) ($row['requirements'] ?? ''),
                    'benefits' => (string) ($row['benefits'] ?? ''),
                    'location' => (string) ($row['location'] ?? ''),
                    'quantity' => (int) $row['quantity'],
                    'deadline' => (string) ($row['deadline'] ?? ''),
                    'tag' => page_status_label($row['status'], $positionLabels),
                    'status' => $row['status'],
                ], $positions);
                $data['metrics'] = [
                    ['label' => 'Đang mở', 'value' => (string) count(array_filter($positions, static fn($row) => $row['status'] === 'open')), 'note' => 'Vị trí đang nhận hồ sơ'],
                    ['label' => 'Bản nháp', 'value' => (string) count(array_filter($positions, static fn($row) => $row['status'] === 'draft')), 'note' => 'Chưa hiển thị cho sinh viên'],
                    ['label' => 'Hồ sơ ứng tuyển', 'value' => (string) array_sum(array_column($positions, 'applicant_count')), 'note' => 'Trên các vị trí của doanh nghiệp'],
                ];
            } else {
                $data['columns'] = ['Vị trí', 'Doanh nghiệp', 'Số lượng', 'Địa chỉ công ty', 'Trạng thái'];
                $data['rows'] = array_map(static fn($row) => [$row['title'], $row['company_name'], (string) $row['quantity'], $row['company_address'] ?: 'Chưa cập nhật', page_status_label($row['status'], $positionLabels)], $positions);
                $data['row_ids'] = array_column($positions, 'id');
                $data['metrics'] = [
                    ['label' => 'Đang mở', 'value' => (string) count(array_filter($positions, static fn($row) => $row['status'] === 'open')), 'note' => 'Đang nhận hồ sơ'],
                    ['label' => 'Bản nháp', 'value' => (string) count(array_filter($positions, static fn($row) => $row['status'] === 'draft')), 'note' => 'Chưa công khai'],
                    ['label' => 'Tổng vị trí', 'value' => (string) count($positions), 'note' => 'Tất cả doanh nghiệp'],
                ];
            }
            break;

        case 'company/applications':
            $allApplications = page_all(
                'SELECT a.id, s.id AS student_id, a.cv_file, u.full_name, p.title, a.applied_at, a.status FROM applications a JOIN positions p ON p.id = a.position_id JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id WHERE p.company_id = ? ORDER BY a.applied_at DESC',
                [$companyId]
            );
            // Hồ sơ đã nhận chuyển sang mục Thực tập sinh nên không còn nằm trong danh sách xử lý.
            $applications = array_values(array_filter($allApplications, static fn($row) => $row['status'] !== 'accepted'));
            $data['columns'] = ['Ứng viên', 'Vị trí', 'Ngày nộp', 'Trạng thái', 'CV'];
            $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['title'], page_date($row['applied_at']), page_status_label($row['status'], $applicationLabels), $row['cv_file'] ? 'Tải CV' : 'Chưa có CV'], $applications);
            $data['row_ids'] = array_column($applications, 'id');
            $data['row_student_ids'] = array_column($applications, 'student_id');
            $data['row_cv_paths'] = array_column($applications, 'cv_file');
            $data['metrics'] = [
                ['label' => 'Đang trong danh sách', 'value' => (string) count($applications), 'note' => 'Chưa được nhận'],
                ['label' => 'Chờ xử lý', 'value' => (string) count(array_filter($applications, static fn($row) => in_array($row['status'], ['pending', 'reviewing'], true))), 'note' => 'Cần xem xét'],
                ['label' => 'Đã nhận', 'value' => (string) (count($allApplications) - count($applications)), 'note' => 'Đã chuyển sang mục Thực tập sinh'],
            ];
            break;

        case 'company/inters':
            $internships = page_all('SELECT internship_id, student_id, student_name, position_title, lecturer_name, status FROM v_student_internships WHERE company_id = ? ORDER BY start_date DESC', [$companyId]);
            $data['rows'] = array_map(static fn($row) => [$row['student_name'], $row['position_title'], $row['lecturer_name'] ?: 'Chưa phân công', page_status_label($row['status'], $internshipLabels)], $internships);
            $data['row_ids'] = array_column($internships, 'internship_id');
            $planRows = page_all('SELECT id, training_plan FROM internships WHERE company_id = ?', [$companyId]);
            $plansById = array_column($planRows, 'training_plan', 'id');
            $data['row_plans'] = array_map(static fn($id) => trim((string) ($plansById[$id] ?? '')), $data['row_ids']);
            $data['metrics'] = [
                ['label' => 'Đang thực tập', 'value' => (string) count(array_filter($internships, static fn($row) => $row['status'] === 'active')), 'note' => 'Kỳ thực tập hoạt động'],
                ['label' => 'Sắp bắt đầu', 'value' => (string) count(array_filter($internships, static fn($row) => $row['status'] === 'planned')), 'note' => 'Đã được lên kế hoạch'],
                ['label' => 'Hoàn tất', 'value' => (string) count(array_filter($internships, static fn($row) => $row['status'] === 'completed')), 'note' => 'Đã kết thúc'],
            ];
            break;

        case 'company/tasks':
            $data['internship_options'] = page_all('SELECT i.id, i.student_id, u.full_name, p.title FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN positions p ON p.id = i.position_id WHERE i.company_id = ? AND i.status IN (\'planned\', \'active\') ORDER BY u.full_name', [$companyId]);
            $tasks = page_all('SELECT t.id, t.title, t.priority, t.status, t.due_date, t.submission_link, t.submission_file, t.submission_note, t.submitted_at, su.full_name AS student_name FROM tasks t JOIN internships i ON i.id = t.internship_id JOIN students s ON s.id = t.assigned_to JOIN users su ON su.id = s.user_id WHERE i.company_id = ? ORDER BY t.due_date', [$companyId]);
            $data['boards'] = [['title' => 'Chưa bắt đầu', 'items' => []], ['title' => 'Đang thực hiện', 'items' => []], ['title' => 'Chờ phản hồi', 'items' => []]];
            foreach ($tasks as $task) {
                $column = match ($task['status']) {
                    'todo' => 0,
                    'submitted', 'completed' => 2,
                    default => 1,
                };
                $data['boards'][$column]['items'][] = ['id' => (int) $task['id'], 'title' => $task['title'], 'meta' => $task['student_name'] . ' · ' . $task['priority'] . ' · Hạn ' . page_date($task['due_date']), 'status' => $task['status'], 'submission' => page_task_submission($task)];
            }
            break;

        case 'company/evaluations':
            $data['internship_options'] = page_all('SELECT i.id, u.full_name, p.title FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN positions p ON p.id = i.position_id WHERE i.company_id = ? AND i.status IN (\'planned\', \'active\') ORDER BY u.full_name', [$companyId]);
            $evaluationRows = page_all(
                'SELECT i.id, u.full_name, p.title, e.status FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN positions p ON p.id = i.position_id LEFT JOIN evaluations e ON e.internship_id = i.id AND e.evaluator_user_id = ? WHERE i.company_id = ? ORDER BY i.end_date',
                [(int) $user['id'], $companyId]
            );
            $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['title'], 'Định kỳ', $row['status'] === 'submitted' ? 'Đã gửi' : 'Cần đánh giá'], $evaluationRows);
            $data['row_ids'] = array_column($evaluationRows, 'id');
            $data['metrics'] = [
                ['label' => 'Cần đánh giá', 'value' => (string) count(array_filter($evaluationRows, static fn($row) => $row['status'] !== 'submitted')), 'note' => 'Kỳ thực tập đang hoạt động'],
                ['label' => 'Đã gửi', 'value' => (string) count(array_filter($evaluationRows, static fn($row) => $row['status'] === 'submitted')), 'note' => 'Đánh giá đã hoàn tất'],
                ['label' => 'Tổng kỳ thực tập', 'value' => (string) count($evaluationRows), 'note' => 'Thuộc doanh nghiệp'],
            ];
            break;

        case 'company/profile':
            $profile = page_one('SELECT * FROM companies WHERE id = ?', [$companyId]);
            $data['profile_record'] = $profile;
            if ($profile) {
                $data['title'] = $profile['company_name'];
                $data['description'] = $profile['description'] ?: 'Chưa có phần giới thiệu doanh nghiệp.';
                $data['fields'] = [
                    ['label' => 'Mã doanh nghiệp', 'value' => $profile['company_code']],
                    ['label' => 'Website', 'value' => $profile['website'] ?: 'Chưa cập nhật'],
                    ['label' => 'Email liên hệ', 'value' => $profile['email'] ?: $user['email']],
                    ['label' => 'Điện thoại', 'value' => $profile['phone'] ?: $user['phone']],
                    ['label' => 'Địa chỉ', 'value' => $profile['address'] ?: 'Chưa cập nhật'],
                    ['label' => 'Giới thiệu', 'value' => $profile['description'] ?: 'Chưa có giới thiệu.'],
                ];
            }
            break;

        case 'lecturer/profile':
            $profile = page_one('SELECT * FROM lecturers WHERE id = ?', [$lecturerId]);
            $data['title'] = $user['full_name'];
            $data['description'] = trim(implode(' · ', array_filter([$profile['academic_title'] ?? null, $profile['department'] ?? null]))) ?: 'Giảng viên hướng dẫn thực tập';
            $data['fields'] = [
                ['label' => 'Mã giảng viên', 'value' => $profile['lecturer_code'] ?? 'Chưa cập nhật'],
                ['label' => 'Email', 'value' => $user['email']],
                ['label' => 'Số điện thoại', 'value' => $user['phone'] ?: 'Chưa cập nhật'],
                ['label' => 'Bộ môn / Khoa', 'value' => ($profile['department'] ?? '') ?: 'Chưa cập nhật'],
            ];
            break;

        case 'admin/profile':
            $data['title'] = $user['full_name'];
            $data['description'] = 'Quản trị viên hệ thống InternTrack';
            $data['fields'] = [
                ['label' => 'Tên đăng nhập', 'value' => $user['username']],
                ['label' => 'Email', 'value' => $user['email']],
                ['label' => 'Số điện thoại', 'value' => $user['phone'] ?: 'Chưa cập nhật'],
            ];
            break;

        case 'lecturer/dashboard':
            $data['title'] = page_greeting($user, 'Xin chào');
            $studentCount = page_count('SELECT COUNT(*) FROM internships WHERE lecturer_id = ? AND status IN (\'planned\', \'active\')', [$lecturerId]);
            $diaryCount = page_count('SELECT COUNT(*) FROM diaries d JOIN internships i ON i.id = d.internship_id WHERE i.lecturer_id = ? AND d.status = \'submitted\'', [$lecturerId]);
            $reportCount = page_count('SELECT COUNT(*) FROM reports r JOIN internships i ON i.id = r.internship_id WHERE i.lecturer_id = ? AND r.status IN (\'submitted\', \'reviewing\')', [$lecturerId]);
            $data['metrics'] = [
                ['label' => 'Sinh viên phụ trách', 'value' => (string) $studentCount, 'note' => 'Các kỳ thực tập đang chạy'],
                ['label' => 'Nhật ký chờ duyệt', 'value' => (string) $diaryCount, 'note' => 'Cần phản hồi'],
                ['label' => 'Báo cáo cần đọc', 'value' => (string) $reportCount, 'note' => 'Báo cáo đã nộp'],
            ];
            $totalStudents = max(1, $studentCount);
            $activeThisWeek = page_count('SELECT COUNT(DISTINCT d.student_id) FROM diaries d JOIN internships i ON i.id = d.internship_id WHERE i.lecturer_id = ? AND d.diary_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)', [$lecturerId]);
            $data['progress'] = ['label' => 'Sinh viên cập nhật tuần này', 'value' => (int) round($activeThisWeek * 100 / $totalStudents) . '%', 'note' => $activeThisWeek . '/' . $studentCount . ' sinh viên đã ghi nhật ký'];
            $data['bars'] = page_week_bars('SELECT WEEKDAY(d.diary_date) AS day, COUNT(*) AS total FROM diaries d JOIN internships i ON i.id = d.internship_id WHERE i.lecturer_id = ? AND YEARWEEK(d.diary_date, 1) = YEARWEEK(CURDATE(), 1) GROUP BY day', [$lecturerId]);
            $pendingDiaries = page_all('SELECT u.full_name, d.title, d.diary_date FROM diaries d JOIN internships i ON i.id = d.internship_id JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id WHERE i.lecturer_id = ? AND d.status = \'submitted\' ORDER BY d.diary_date LIMIT 4', [$lecturerId]);
            $data['activities'] = array_map(static fn($row) => ['time' => page_date($row['diary_date']), 'title' => $row['full_name'] . ' · ' . ($row['title'] ?: 'Nhật ký thực tập'), 'description' => 'Nhật ký đang chờ giảng viên xem xét.', 'status' => 'Chờ duyệt'], $pendingDiaries);
            if (!$data['activities']) {
                $data['activities'] = [['time' => 'Tuần này', 'title' => 'Không có nhật ký chờ duyệt', 'description' => 'Các nhật ký mới sẽ xuất hiện ở đây.', 'status' => 'Đã cập nhật']];
            }
            break;

        case 'lecturer/students':
        case 'lecturer/progress':
            $members = page_all('SELECT v.student_id, v.student_name, v.company_name, v.position_title, v.status, u.id AS user_id, u.avatar FROM v_student_internships v JOIN students s ON s.id = v.student_id JOIN users u ON u.id = s.user_id WHERE v.lecturer_id = ? ORDER BY v.student_name', [$lecturerId]);
            if ($screen === 'lecturer/students') {
                $placements = page_all(
                    'SELECT i.id, i.status, i.start_date, i.end_date, i.training_plan, s.student_code, s.class_name, u.full_name AS student_name, u.phone AS student_phone,
                            c.company_name, c.address AS company_address, c.phone AS company_phone, p.title AS position_title, p.location,
                            (SELECT COUNT(*) FROM tasks t WHERE t.internship_id = i.id AND t.status <> \'cancelled\') AS task_total,
                            (SELECT COUNT(*) FROM tasks t WHERE t.internship_id = i.id AND t.status = \'completed\') AS task_done,
                            (SELECT COUNT(*) FROM diaries d WHERE d.internship_id = i.id AND d.status IN (\'submitted\', \'approved\')) AS diary_count
                     FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id
                     JOIN companies c ON c.id = i.company_id JOIN positions p ON p.id = i.position_id
                     WHERE i.lecturer_id = ? ORDER BY FIELD(i.status, \'active\', \'planned\', \'completed\', \'cancelled\'), u.full_name',
                    [$lecturerId]
                );
                $data['columns'] = ['Sinh viên', 'Nơi thực tập', 'Vị trí · Địa điểm', 'Thời gian', 'Tiến độ', 'Trạng thái'];
                $data['description'] = 'Sinh viên được phân công cho bạn, nơi đang thực tập, thời gian và tiến độ để theo dõi và đánh giá.';
                $data['rows'] = array_map(static function ($row) use ($internshipLabels): array {
                    $progress = (int) $row['task_total'] > 0 ? (int) round((int) $row['task_done'] * 100 / (int) $row['task_total']) : 0;
                    return [
                        $row['student_name'] . ' · ' . $row['student_code'] . ($row['class_name'] ? ' · ' . $row['class_name'] : ''),
                        $row['company_name'] . ($row['company_address'] ? ' · ' . $row['company_address'] : '') . ($row['company_phone'] ? ' · ' . $row['company_phone'] : ''),
                        $row['position_title'] . ($row['location'] ? ' · ' . $row['location'] : ''),
                        page_date($row['start_date']) . ' – ' . page_date($row['end_date']),
                        $progress . '% · ' . $row['task_done'] . '/' . $row['task_total'] . ' nhiệm vụ · ' . $row['diary_count'] . ' nhật ký',
                        page_status_label($row['status'], $internshipLabels),
                    ];
                }, $placements);
                $data['row_ids'] = array_column($placements, 'id');
                $data['row_plans'] = array_map(static fn($row) => trim((string) $row['training_plan']), $placements);
                $data['metrics'] = [
                    ['label' => 'Sinh viên có kỳ thực tập', 'value' => (string) count($members), 'note' => 'Được phân công cho bạn'],
                    ['label' => 'Đang thực tập', 'value' => (string) count(array_filter($members, static fn($row) => $row['status'] === 'active')), 'note' => 'Kỳ thực tập đang diễn ra'],
                    ['label' => 'Sắp bắt đầu', 'value' => (string) count(array_filter($members, static fn($row) => $row['status'] === 'planned')), 'note' => 'Đã được lên kế hoạch'],
                ];
            } else {
                $data['members'] = array_map(static function ($row) use ($studentId): array {
                    $taskTotal = page_count('SELECT COUNT(*) FROM tasks WHERE assigned_to = ?', [(int) $row['student_id']]);
                    $taskDone = page_count('SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = \'completed\'', [(int) $row['student_id']]);
                    return [
                        'name' => $row['student_name'],
                        'user_id' => (int) $row['user_id'],
                        'avatar' => $row['avatar'],
                        'detail' => $row['company_name'] . ' · ' . $row['position_title'],
                        'progress' => $taskTotal ? (int) round($taskDone * 100 / $taskTotal) : 0,
                        'status' => page_status_label($row['status'], ['planned' => 'Sắp bắt đầu', 'active' => 'Đang thực tập', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy']),
                    ];
                }, $members);
            }
            break;

        case 'lecturer/diaries':
            $diaries = page_all('SELECT d.id, u.full_name, d.title, d.diary_date, d.status FROM diaries d JOIN internships i ON i.id = d.internship_id JOIN students s ON s.id = d.student_id JOIN users u ON u.id = s.user_id WHERE i.lecturer_id = ? ORDER BY d.diary_date DESC', [$lecturerId]);
            $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['title'] ?: 'Nhật ký thực tập', page_date($row['diary_date']), page_status_label($row['status'], $diaryLabels)], $diaries);
            $data['row_ids'] = array_column($diaries, 'id');
            $data['metrics'] = [
                ['label' => 'Chờ duyệt', 'value' => (string) count(array_filter($diaries, static fn($row) => $row['status'] === 'submitted')), 'note' => 'Nhật ký sinh viên đã gửi'],
                ['label' => 'Đã duyệt', 'value' => (string) count(array_filter($diaries, static fn($row) => $row['status'] === 'approved')), 'note' => 'Đã có phản hồi'],
                ['label' => 'Tổng nhật ký', 'value' => (string) count($diaries), 'note' => 'Sinh viên bạn phụ trách'],
            ];
            break;

        case 'lecturer/reports':
            $reports = page_all('SELECT r.id, u.full_name, r.title, r.report_type, r.status, r.submitted_at, r.feedback, r.file_path FROM reports r JOIN internships i ON i.id = r.internship_id JOIN students s ON s.id = r.student_id JOIN users u ON u.id = s.user_id WHERE i.lecturer_id = ? ORDER BY r.updated_at DESC', [$lecturerId]);
            $data['cards'] = array_map(static fn($row) => [
                'id' => (int) $row['id'],
                'title' => $row['title'] . ' · ' . $row['full_name'],
                'meta' => strtoupper($row['report_type']) . ' · ' . page_date($row['submitted_at']),
                'description' => $row['feedback'] ?: 'Sinh viên đã nộp báo cáo để giảng viên xem xét.',
                'tag' => page_status_label($row['status'], $reportLabels),
                'file_path' => $row['file_path'],
            ], $reports);
            $data['metrics'] = [
                ['label' => 'Cần xem', 'value' => (string) count(array_filter($reports, static fn($row) => in_array($row['status'], ['submitted', 'reviewing'], true))), 'note' => 'Đã nộp báo cáo'],
                ['label' => 'Đã duyệt', 'value' => (string) count(array_filter($reports, static fn($row) => $row['status'] === 'approved')), 'note' => 'Có phản hồi hoàn tất'],
                ['label' => 'Tổng báo cáo', 'value' => (string) count($reports), 'note' => 'Trong nhóm sinh viên'],
            ];
            break;

        case 'lecturer/evaluations':
            $data['internship_options'] = page_all('SELECT i.id, u.full_name, p.title FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN positions p ON p.id = i.position_id WHERE i.lecturer_id = ? AND i.status IN (\'planned\', \'active\') ORDER BY u.full_name', [$lecturerId]);
            $evaluations = page_all('SELECT i.id, u.full_name, c.company_name, e.id AS evaluation_id, e.status FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN companies c ON c.id = i.company_id LEFT JOIN evaluations e ON e.internship_id = i.id AND e.evaluator_user_id = ? WHERE i.lecturer_id = ? ORDER BY i.end_date', [(int) $user['id'], $lecturerId]);
            $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['company_name'], 'Định kỳ', $row['status'] === 'submitted' ? 'Đã gửi' : 'Cần đánh giá'], $evaluations);
            $data['row_ids'] = array_column($evaluations, 'id');
            $data['metrics'] = [
                ['label' => 'Cần đánh giá', 'value' => (string) count(array_filter($evaluations, static fn($row) => $row['status'] !== 'submitted')), 'note' => 'Kỳ thực tập bạn phụ trách'],
                ['label' => 'Đã gửi', 'value' => (string) count(array_filter($evaluations, static fn($row) => $row['status'] === 'submitted')), 'note' => 'Đánh giá hoàn tất'],
                ['label' => 'Tổng sinh viên', 'value' => (string) count($evaluations), 'note' => 'Có kỳ thực tập được phân công'],
            ];
            break;

        case 'admin/dashboard':
            $companyCount = page_count('SELECT COUNT(*) FROM companies');
            $activeCompanyCount = page_count('SELECT COUNT(*) FROM companies WHERE status = \'active\'');
            $data['metrics'] = [
                ['label' => 'Sinh viên', 'value' => (string) page_count('SELECT COUNT(*) FROM students'), 'note' => 'Hồ sơ trong hệ thống'],
                ['label' => 'Doanh nghiệp', 'value' => (string) $companyCount, 'note' => (string) max(0, $companyCount - $activeCompanyCount) . ' hồ sơ cần xác minh'],
                ['label' => 'Kỳ thực tập đang chạy', 'value' => (string) page_count('SELECT COUNT(*) FROM internships WHERE status = \'active\''), 'note' => 'Đang được theo dõi'],
            ];
            $data['progress'] = ['label' => 'Doanh nghiệp đã xác minh', 'value' => ($companyCount ? (int) round($activeCompanyCount * 100 / $companyCount) : 0) . '%', 'note' => $activeCompanyCount . '/' . $companyCount . ' doanh nghiệp đã kích hoạt'];
            $data['bars'] = page_week_bars('SELECT WEEKDAY(applied_at) AS day, COUNT(*) AS total FROM applications WHERE YEARWEEK(applied_at, 1) = YEARWEEK(CURDATE(), 1) GROUP BY day');
            $pendingCompanies = page_all('SELECT company_name, created_at FROM companies WHERE status = \'pending\' ORDER BY created_at DESC LIMIT 4');
            $data['activities'] = array_map(static fn($row) => ['time' => page_date($row['created_at']), 'title' => $row['company_name'], 'description' => 'Hồ sơ doanh nghiệp đang chờ xác minh.', 'status' => 'Chờ xác minh'], $pendingCompanies);
            if (!$data['activities']) {
                $data['activities'] = [['time' => 'Hệ thống', 'title' => 'Không có hồ sơ chờ xác minh', 'description' => 'Các đăng ký mới sẽ xuất hiện ở đây.', 'status' => 'Đã cập nhật']];
            }
            break;

        case 'admin/students':
        case 'admin/users':
            $accounts = page_all(
                'SELECT u.id, u.full_name, u.email, u.role, u.status, s.student_code, s.major FROM users u LEFT JOIN students s ON s.user_id = u.id ORDER BY u.role, u.full_name'
            );
            $roleLabels = ['student' => 'Sinh viên', 'company' => 'Doanh nghiệp', 'lecturer' => 'Giảng viên', 'admin' => 'Quản trị'];
            if ($screen === 'admin/students') {
                $accounts = array_values(array_filter($accounts, static fn($row) => $row['role'] === 'student'));
                $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['student_code'] ?: 'Chưa cấp mã', $row['major'] ?: 'Chưa cập nhật', $row['status'] === 'active' ? 'Hoạt động' : 'Đã khóa'], $accounts);
            } else {
                // Tài khoản được chia thành các nhóm riêng: sinh viên, giảng viên, doanh nghiệp, quản trị.
                $groupLabels = ['student' => 'Sinh viên', 'lecturer' => 'Giảng viên', 'company' => 'Doanh nghiệp', 'admin' => 'Quản trị'];
                $group = isset($_GET['group']) && is_string($_GET['group']) && isset($groupLabels[$_GET['group']]) ? $_GET['group'] : 'student';
                $accounts = page_all(
                    'SELECT u.id, u.full_name, u.username, u.email, u.phone, u.role, u.status, s.student_code, s.major, s.class_name,
                            l.lecturer_code, l.department, c.company_code, c.company_name
                     FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN lecturers l ON l.user_id = u.id LEFT JOIN companies c ON c.user_id = u.id
                     ORDER BY u.full_name'
                );
                $groupCounts = array_fill_keys(array_keys($groupLabels), 0);
                foreach ($accounts as $account) {
                    $groupCounts[$account['role']]++;
                }
                $data['groups'] = array_map(static fn($key) => ['key' => $key, 'label' => $groupLabels[$key], 'count' => $groupCounts[$key]], array_keys($groupLabels));
                $data['group'] = $group;
                $accounts = array_values(array_filter($accounts, static fn($row) => $row['role'] === $group));
                $stateText = static fn($row) => $row['status'] === 'active' ? 'Hoạt động' : ($row['status'] === 'locked' ? 'Đã khóa' : 'Tạm ngưng');
                $none = 'Chưa cập nhật';
                $data['columns'] = match ($group) {
                    'student' => ['Họ tên', 'Email', 'Mã sinh viên', 'Ngành · Lớp', 'Trạng thái'],
                    'lecturer' => ['Họ tên', 'Email', 'Mã giảng viên', 'Bộ môn / Khoa', 'Trạng thái'],
                    'company' => ['Doanh nghiệp', 'Người liên hệ', 'Email đăng nhập', 'Mã doanh nghiệp', 'Trạng thái'],
                    default => ['Họ tên', 'Email', 'Tên đăng nhập', 'Điện thoại', 'Trạng thái'],
                };
                $data['rows'] = array_map(static fn($row) => match ($group) {
                    'student' => [$row['full_name'], $row['email'], $row['student_code'] ?: 'Chưa cấp mã', trim(($row['major'] ?: '') . ($row['major'] && $row['class_name'] ? ' · ' : '') . ($row['class_name'] ?: '')) ?: $none, $stateText($row)],
                    'lecturer' => [$row['full_name'], $row['email'], $row['lecturer_code'] ?: 'Chưa cấp mã', $row['department'] ?: $none, $stateText($row)],
                    'company' => [$row['company_name'] ?: $row['full_name'], $row['full_name'], $row['email'], $row['company_code'] ?: 'Chưa cấp mã', $stateText($row)],
                    default => [$row['full_name'], $row['email'], $row['username'], $row['phone'] ?: $none, $stateText($row)],
                }, $accounts);
                $data['row_active'] = array_map(static fn($row) => $row['status'] === 'active', $accounts);
                $activeAccounts = count(array_filter($accounts, static fn($row) => $row['status'] === 'active'));
                $data['title'] = 'Tài khoản ' . mb_strtolower($groupLabels[$group], 'UTF-8');
                $data['metrics'] = [
                    ['label' => 'Tổng ' . mb_strtolower($groupLabels[$group], 'UTF-8'), 'value' => (string) count($accounts), 'note' => 'Tài khoản trong nhóm này'],
                    ['label' => 'Đang hoạt động', 'value' => (string) $activeAccounts, 'note' => 'Có thể đăng nhập'],
                    ['label' => 'Không hoạt động', 'value' => (string) (count($accounts) - $activeAccounts), 'note' => 'Đã khóa hoặc tạm ngưng'],
                ];
            }
            if ($screen === 'admin/students') {
                $activeStudents = count(array_filter($accounts, static fn($row) => $row['status'] === 'active'));
                $data['metrics'] = [
                    ['label' => 'Tổng sinh viên', 'value' => (string) count($accounts), 'note' => 'Hồ sơ đã tạo'],
                    ['label' => 'Đang hoạt động', 'value' => (string) $activeStudents, 'note' => 'Tài khoản sử dụng được'],
                    ['label' => 'Đã khóa', 'value' => (string) (count($accounts) - $activeStudents), 'note' => 'Tài khoản không hoạt động'],
                ];
            }
            $data['row_ids'] = array_column($accounts, 'id');
            break;

        case 'admin/companies':
            $companies = page_all('SELECT c.id, c.company_name, c.company_code, c.status, COUNT(p.id) AS positions FROM companies c LEFT JOIN positions p ON p.company_id = c.id GROUP BY c.id ORDER BY c.company_name');
            $companyLabels = ['pending' => 'Chờ xác minh', 'active' => 'Hoạt động', 'inactive' => 'Tạm ngưng', 'rejected' => 'Từ chối'];
            $data['rows'] = array_map(static fn($row) => [$row['company_name'], $row['company_code'], (string) $row['positions'], page_status_label($row['status'], $companyLabels)], $companies);
            $data['row_ids'] = array_column($companies, 'id');
            $data['row_status'] = array_column($companies, 'status');
            $data['metrics'] = [
                ['label' => 'Hoạt động', 'value' => (string) count(array_filter($companies, static fn($row) => $row['status'] === 'active')), 'note' => 'Doanh nghiệp đã xác minh'],
                ['label' => 'Chờ xác minh', 'value' => (string) count(array_filter($companies, static fn($row) => $row['status'] === 'pending')), 'note' => 'Hồ sơ cần xử lý'],
                ['label' => 'Tổng vị trí', 'value' => (string) array_sum(array_column($companies, 'positions')), 'note' => 'Tin tuyển dụng đã đăng'],
            ];
            break;

        case 'admin/internship':
            $data['lecturer_options'] = page_all('SELECT l.id, l.lecturer_code, u.full_name FROM lecturers l JOIN users u ON u.id = l.user_id ORDER BY u.full_name');
            $internships = page_all('SELECT i.id, i.lecturer_id, u.full_name, p.title, lu.full_name AS lecturer_name, i.status, i.training_plan FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id JOIN positions p ON p.id = i.position_id LEFT JOIN lecturers l ON l.id = i.lecturer_id LEFT JOIN users lu ON lu.id = l.user_id ORDER BY i.start_date DESC');
            $data['rows'] = array_map(static fn($row) => [$row['full_name'], $row['title'], $row['lecturer_name'] ?: 'Chưa phân công', page_status_label($row['status'], $internshipLabels)], $internships);
            $data['row_ids'] = array_column($internships, 'id');
            $data['row_status'] = array_column($internships, 'status');
            $data['row_lecturer_ids'] = array_column($internships, 'lecturer_id');
            $data['row_training_plans'] = array_map(static fn($row) => $row['training_plan'] ?? '', $internships);
            $data['metrics'] = [
                ['label' => 'Đang diễn ra', 'value' => (string) count(array_filter($internships, static fn($row) => $row['status'] === 'active')), 'note' => 'Kỳ thực tập hiện tại'],
                ['label' => 'Sắp bắt đầu', 'value' => (string) count(array_filter($internships, static fn($row) => $row['status'] === 'planned')), 'note' => 'Đã được lên kế hoạch'],
                ['label' => 'Chưa phân công', 'value' => (string) count(array_filter($internships, static fn($row) => !$row['lecturer_name'])), 'note' => 'Thiếu giảng viên phụ trách'],
            ];
            break;

        default:
            break;
    }

    if (isset($data['title']) && str_starts_with($screen, 'admin/')) {
        $data['currentUserName'] = $user['full_name'];
    }
    return $data;
}
