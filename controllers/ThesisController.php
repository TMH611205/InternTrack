<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';
require_once __DIR__ . '/MatchingController.php';
require_once __DIR__ . '/SimilarityController.php';

// =====================================================================================
// ThesisController: phân hệ ĐỒ ÁN và quản lý ĐỢT (đợt thực tập / đợt đồ án).
//
// (A) Hằng số và hàm trợ giúp: đợt, mốc thời gian (mở/khóa chức năng theo mốc), quyền truy cập đồ án, rubric, điểm.
// (B) thesis_screen_data(): dữ liệu cho các màn hình admin/batches, admin/topics, admin/matching, admin/theses,
//     admin/rubrics, lecturer/topics, lecturer/theses, lecturer/defense, student/thesis.
// (C) thesis_handle_action(): các thao tác POST của phân hệ; trả về null nếu action không thuộc phân hệ này.
// =====================================================================================

// Mốc thời gian theo loại đợt. Chức năng gắn với một mốc chỉ mở trong khoảng thời gian của mốc (mốc chưa đặt ngày thì không giới hạn).
const BATCH_MILESTONES = [
    'thesis' => [
        'topic_registration' => 'Giảng viên đăng ký đề tài',
        'preference' => 'Sinh viên chọn hướng, xếp nguyện vọng, đề xuất đề tài',
        'matching' => 'Ghép cặp và công bố kết quả',
        'report_1' => 'Nộp báo cáo tiến độ 1',
        'report_2' => 'Nộp báo cáo tiến độ 2',
        'report_final' => 'Nộp quyển đồ án',
        'evaluation' => 'Chấm hướng dẫn và phản biện',
        'defense' => 'Bảo vệ trước hội đồng',
        'close' => 'Đóng đợt',
    ],
    'internship' => [
        'registration' => 'Ứng tuyển / khai báo nơi thực tập',
        'report_1' => 'Nộp báo cáo giữa kỳ',
        'report_final' => 'Nộp báo cáo cuối kỳ',
        'evaluation' => 'Đánh giá thực tập',
        'close' => 'Đóng đợt',
    ],
];
const THESIS_STAGES = ['report_1' => 'Báo cáo tiến độ 1', 'report_2' => 'Báo cáo tiến độ 2', 'report_final' => 'Quyển đồ án', 'other' => 'Tài liệu khác'];
const TOPIC_STATUS_LABELS = ['draft' => 'Bản nháp', 'pending' => 'Chờ duyệt', 'dept_approved' => 'Bộ môn đã duyệt · chờ khoa', 'revision' => 'Cần chỉnh sửa', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'];
const THESIS_STATUS_LABELS = ['assigned' => 'Chờ sinh viên xác nhận', 'confirmed' => 'Đã xác nhận', 'declined' => 'Sinh viên từ chối', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn tất'];
const THESIS_ROLE_LABELS = ['supervisor' => 'Hướng dẫn', 'reviewer' => 'Phản biện', 'council' => 'Hội đồng'];
const COUNCIL_ROLE_LABELS = ['chair' => 'Chủ tịch', 'secretary' => 'Thư ký', 'member' => 'Ủy viên'];

// ---------------------------------------------------------------------------
// (A) HÀM TRỢ GIÚP
// ---------------------------------------------------------------------------

function thesis_int(array $input, string $key): int
{
    return (int) (filter_var($input[$key] ?? null, FILTER_VALIDATE_INT) ?: 0);
}

function thesis_query_int(string $key): int
{
    return (int) (filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT) ?: 0);
}

// Đổi giá trị ô datetime-local / date ("2026-11-05T08:00", "2026-11-05") thành chuỗi DATETIME cho MySQL; rỗng hoặc sai thì null.
function thesis_datetime(string $value, bool $endOfDay = false): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d'] as $format) {
        $parsed = DateTime::createFromFormat($format, $value);
        if ($parsed !== false) {
            if ($format === 'Y-m-d') {
                $parsed->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0);
            }
            return $parsed->format('Y-m-d H:i:s');
        }
    }
    throw new DomainException('Ngày giờ không hợp lệ.');
}

function thesis_datetime_label(?string $value): string
{
    return $value ? date('d/m/Y H:i', (int) strtotime($value)) : 'Chưa đặt';
}

function thesis_batch(int $batchId): array
{
    $batch = page_one('SELECT b.*, t.name AS term_name, t.school_year, m.name AS major_name FROM batches b LEFT JOIN academic_terms t ON t.id = b.term_id LEFT JOIN majors m ON m.id = b.major_id WHERE b.id = ? AND (@it_faculty IS NULL OR b.major_id IS NULL OR m.faculty = @it_faculty)', [$batchId]);
    if (!$batch) {
        throw new DomainException('Không tìm thấy đợt.');
    }
    return $batch;
}

function thesis_batches(?string $type = null): array
{
    return page_all(
        'SELECT b.*, t.name AS term_name, t.school_year, m.name AS major_name FROM batches b LEFT JOIN academic_terms t ON t.id = b.term_id LEFT JOIN majors m ON m.id = b.major_id
         WHERE (@it_faculty IS NULL OR b.major_id IS NULL OR m.faculty = @it_faculty)'
        . ($type ? ' AND b.batch_type = ?' : '') . ' ORDER BY FIELD(b.status, \'open\', \'draft\', \'closed\'), b.created_at DESC',
        $type ? [$type] : []
    );
}

// Đợt đang xem: theo ?batch= trên URL, nếu không thì đợt đang mở mới nhất, cuối cùng là đợt mới nhất.
function thesis_selected_batch(string $type): ?array
{
    $batches = thesis_batches($type);
    $requested = thesis_query_int('batch');
    foreach ($batches as $batch) {
        if ((int) $batch['id'] === $requested) {
            return $batch;
        }
    }
    return $batches[0] ?? null;
}

function batch_window(int $batchId, string $kind): ?array
{
    return page_one('SELECT starts_at, ends_at, title FROM batch_milestones WHERE batch_id = ? AND kind = ?', [$batchId, $kind]);
}

// Trạng thái của một chức năng theo mốc: open | not_started | ended | closed (đợt chưa mở/đã đóng) | locked (đã chốt dữ liệu).
function batch_phase_state(array $batch, string $kind): string
{
    if (!empty($batch['locked_at'])) {
        return 'locked';
    }
    if ($batch['status'] !== 'open') {
        return 'closed';
    }
    $window = batch_window((int) $batch['id'], $kind);
    $now = time();
    if ($window && $window['starts_at'] && $now < strtotime((string) $window['starts_at'])) {
        return 'not_started';
    }
    if ($window && $window['ends_at'] && $now > strtotime((string) $window['ends_at'])) {
        return 'ended';
    }
    return 'open';
}

function batch_phase_label(string $state): string
{
    return ['open' => 'Đang mở', 'not_started' => 'Chưa đến hạn mở', 'ended' => 'Đã hết hạn', 'closed' => 'Đợt chưa mở hoặc đã đóng', 'locked' => 'Đợt đã chốt dữ liệu'][$state] ?? $state;
}

function batch_require_phase(array $batch, string $kind): void
{
    $state = batch_phase_state($batch, $kind);
    if ($state !== 'open') {
        $title = BATCH_MILESTONES[$batch['batch_type']][$kind] ?? $kind;
        throw new DomainException('Chức năng "' . $title . '" hiện không khả dụng: ' . mb_strtolower(batch_phase_label($state), 'UTF-8') . '.');
    }
}

function batch_require_unlocked(array $batch): void
{
    if (!empty($batch['locked_at'])) {
        throw new DomainException('Đợt đã đóng và chốt dữ liệu nên không thể thay đổi.');
    }
}

// Khoảng thời gian của mốc dưới dạng chữ để hiển thị.
function batch_window_text(int $batchId, string $kind): string
{
    $window = batch_window($batchId, $kind);
    if (!$window || (!$window['starts_at'] && !$window['ends_at'])) {
        return 'Không giới hạn thời gian';
    }
    return ($window['starts_at'] ? thesis_datetime_label($window['starts_at']) : '…') . ' → ' . ($window['ends_at'] ? thesis_datetime_label($window['ends_at']) : '…');
}

// Sinh viên có thuộc danh sách đủ điều kiện của đợt không (đợt chưa lập danh sách thì mọi sinh viên đều tham gia được).
function batch_student_eligible(int $batchId, int $studentId): bool
{
    if (page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ?', [$batchId]) === 0) {
        return true;
    }
    return page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ? AND eligible = 1', [$batchId, $studentId]) > 0;
}

// Lập lại danh sách sinh viên đủ điều kiện theo ngành, hệ đào tạo, số tín chỉ và GPA tối thiểu của đợt.
function batch_refresh_eligibility(array $batch): array
{
    $students = page_all('SELECT s.id, s.major, s.specialization, s.training_system, s.credits, s.gpa FROM students s JOIN users u ON u.id = s.user_id WHERE u.status = \'active\'');
    $specialization = !empty($batch['specialization_id']) ? mb_strtolower((string) (page_one('SELECT name FROM specializations WHERE id = ?', [(int) $batch['specialization_id']])['name'] ?? ''), 'UTF-8') : '';
    $requiredCourses = array_values(array_filter(array_map(static fn($code) => strtoupper(trim($code)), explode(',', (string) ($batch['required_courses'] ?? '')))));
    $major = mb_strtolower(trim((string) ($batch['major_name'] ?? '')), 'UTF-8');
    $system = mb_strtolower(trim((string) ($batch['training_system'] ?? '')), 'UTF-8');
    $eligible = 0;
    $total = 0;
    database_transaction(static function (PDO $connection) use ($batch, $students, $major, $system, $specialization, $requiredCourses, &$eligible, &$total): void {
        $connection->prepare('DELETE FROM batch_students WHERE batch_id = ?')->execute([(int) $batch['id']]);
        $insert = $connection->prepare('INSERT INTO batch_students (batch_id, student_id, eligible, note) VALUES (?, ?, ?, ?)');
        foreach ($students as $student) {
            if ($major !== '' && mb_strtolower(trim((string) $student['major']), 'UTF-8') !== $major) {
                continue;
            }
            if ($system !== '' && mb_strtolower(trim((string) $student['training_system']), 'UTF-8') !== $system) {
                continue;
            }
            $reasons = [];
            if ($batch['min_credits'] !== null && (int) ($student['credits'] ?? 0) < (int) $batch['min_credits']) {
                $reasons[] = 'Chưa đủ ' . (int) $batch['min_credits'] . ' tín chỉ (' . (int) ($student['credits'] ?? 0) . ')';
            }
            if ($specialization !== '' && mb_strtolower(trim((string) $student['specialization']), 'UTF-8') !== $specialization) {
                continue;
            }
            if ($requiredCourses) {
                $passed = array_map('strtoupper', array_column(page_all('SELECT course_code FROM student_courses WHERE student_id = ?', [(int) $student['id']]), 'course_code'));
                $missing = array_diff($requiredCourses, $passed);
                if ($missing) {
                    $reasons[] = 'Chưa đạt học phần ' . implode(', ', $missing);
                }
            }
            if ($batch['min_gpa'] !== null && (float) ($student['gpa'] ?? 0) < (float) $batch['min_gpa']) {
                $reasons[] = 'GPA dưới ' . $batch['min_gpa'] . ' (' . ($student['gpa'] ?? 'chưa có') . ')';
            }
            $insert->execute([(int) $batch['id'], (int) $student['id'], $reasons ? 0 : 1, $reasons ? implode('; ', $reasons) : 'Đủ điều kiện']);
            $total++;
            $eligible += $reasons ? 0 : 1;
        }
    });
    return [$eligible, $total];
}

// ----- Đợt thực tập -----
// Khi có đợt thực tập đang mở, sinh viên chỉ ứng tuyển / khai báo trong mốc "registration" của đợt mình thuộc danh sách.
// Kỳ thực tập gắn đợt (internships.batch_id) thì nộp báo cáo, đánh giá theo mốc của đợt và bị khóa khi đợt đóng.
// Chưa có đợt thực tập nào đang mở thì hệ thống chạy như trước (không giới hạn).

// Mốc của đợt ứng với loại báo cáo thực tập.
const INTERNSHIP_REPORT_PHASES = ['midterm' => 'report_1', 'final' => 'report_final'];

// Đợt thực tập đang mở (mới nhất) mà sinh viên tham gia được; null nếu không có.
function internship_batch_for_student(int $studentId): ?array
{
    foreach (thesis_batches('internship') as $batch) {
        if ($batch['status'] === 'open' && batch_student_eligible((int) $batch['id'], $studentId)) {
            return $batch;
        }
    }
    return null;
}

// Kiểm tra trước khi ứng tuyển / tự khai báo nơi thực tập. Trả về đợt sẽ gắn cho kỳ thực tập (hoặc null).
function internship_require_registration(int $studentId): ?array
{
    $openBatches = page_count('SELECT COUNT(*) FROM batches WHERE batch_type = \'internship\' AND status = \'open\'');
    if ($openBatches === 0) {
        return null;
    }
    $batch = internship_batch_for_student($studentId);
    if (!$batch) {
        throw new DomainException('Bạn không có trong danh sách sinh viên của đợt thực tập đang mở. Hãy liên hệ phòng đào tạo.');
    }
    batch_require_phase($batch, 'registration');
    return $batch;
}

// Kỳ thực tập thuộc đợt đã đóng thì không được thay đổi; $kind là mốc cần đang mở (null = chỉ kiểm tra khóa).
function internship_require_batch(array $internship, ?string $kind = null): void
{
    if (empty($internship['batch_id'])) {
        return;
    }
    $batch = thesis_batch((int) $internship['batch_id']);
    batch_require_unlocked($batch);
    if ($kind !== null) {
        batch_require_phase($batch, $kind);
    }
}

// Dòng thông tin ngắn về đợt thực tập hiện tại cho các trang thực tập của sinh viên: [tên đợt, mốc => trạng thái].
function internship_batch_notice(int $studentId): ?array
{
    $internship = page_one('SELECT batch_id FROM internships WHERE student_id = ? AND batch_id IS NOT NULL ORDER BY FIELD(status, \'active\', \'planned\', \'completed\', \'cancelled\'), start_date DESC LIMIT 1', [$studentId]);
    $batch = $internship ? thesis_batch((int) $internship['batch_id']) : internship_batch_for_student($studentId);
    if (!$batch) {
        return null;
    }
    $phases = [];
    foreach (['registration', 'report_1', 'report_final', 'evaluation'] as $kind) {
        $window = batch_window((int) $batch['id'], $kind);
        $state = batch_phase_state($batch, $kind);
        $phases[] = [
            'title' => ['registration' => 'Ứng tuyển', 'report_1' => 'Báo cáo giữa kỳ', 'report_final' => 'Báo cáo cuối kỳ', 'evaluation' => 'Đánh giá'][$kind],
            'state' => $state,
            'until' => $window && $window['ends_at'] ? date('d/m', (int) strtotime((string) $window['ends_at'])) : null,
            'from' => $window && $window['starts_at'] ? date('d/m', (int) strtotime((string) $window['starts_at'])) : null,
        ];
    }
    return ['name' => $batch['name'], 'locked' => !empty($batch['locked_at']), 'phases' => $phases];
}

// Đồ án và vai trò của người dùng với đồ án đó (sinh viên/nhóm, hướng dẫn, phản biện, hội đồng, quản trị).
function thesis_access(int $thesisId, array $user): array
{
    $thesis = page_one(
        'SELECT th.*, tp.title AS topic_title, tp.field, s.user_id AS student_user_id, u.full_name AS student_name, s.student_code, b.name AS batch_name, b.locked_at, b.results_published_at, b.status AS batch_status, b.batch_type
         FROM theses th JOIN topics tp ON tp.id = th.topic_id JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN batches b ON b.id = th.batch_id WHERE th.id = ?',
        [$thesisId]
    );
    if (!$thesis) {
        throw new DomainException('Không tìm thấy đồ án.');
    }
    $lecturerId = (int) ($user['lecturer_id'] ?? 0);
    $studentId = (int) ($user['student_id'] ?? 0);
    $thesis['is_admin'] = $user['role'] === 'admin';
    $thesis['is_student'] = $user['role'] === 'student' && ((int) $thesis['student_id'] === $studentId
        || ($thesis['group_id'] && page_count('SELECT COUNT(*) FROM theses WHERE group_id = ? AND student_id = ?', [(int) $thesis['group_id'], $studentId]) > 0));
    $thesis['is_supervisor'] = $lecturerId > 0 && (int) $thesis['supervisor_id'] === $lecturerId;
    $thesis['is_reviewer'] = $lecturerId > 0 && (int) $thesis['reviewer_id'] === $lecturerId;
    $thesis['is_council'] = $lecturerId > 0 && $thesis['council_id'] && page_count('SELECT COUNT(*) FROM council_members WHERE council_id = ? AND lecturer_id = ?', [(int) $thesis['council_id'], $lecturerId]) > 0;
    if (!$thesis['is_admin'] && !$thesis['is_student'] && !$thesis['is_supervisor'] && !$thesis['is_reviewer'] && !$thesis['is_council']) {
        throw new DomainException('Bạn không có quyền truy cập đồ án này.');
    }
    return $thesis;
}

// Các đồ án cùng nhóm (bài nộp, mốc, lịch gặp được dùng chung trong nhóm).
function thesis_related_ids(array $thesis): array
{
    if (empty($thesis['group_id'])) {
        return [(int) $thesis['id']];
    }
    return array_map('intval', array_column(page_all('SELECT id FROM theses WHERE group_id = ?', [(int) $thesis['group_id']]), 'id'));
}

function thesis_in(array $ids): string
{
    return implode(',', array_fill(0, max(1, count($ids)), '?'));
}

// Rubric kèm danh sách tiêu chí (null nếu không có).
function rubric_with_criteria(?int $rubricId): ?array
{
    if (!$rubricId) {
        return null;
    }
    $rubric = page_one('SELECT * FROM rubrics WHERE id = ?', [$rubricId]);
    if (!$rubric) {
        return null;
    }
    $rubric['criteria'] = page_all('SELECT * FROM rubric_criteria WHERE rubric_id = ? ORDER BY sort_order, id', [$rubricId]);
    return $rubric;
}

// Rubric dùng khi doanh nghiệp / giảng viên đánh giá kỳ thực tập (theo đợt của kỳ); null = 4 tiêu chí mặc định.
function internship_rubric(array $internship, string $evaluatorType): ?array
{
    if (empty($internship['batch_id'])) {
        return null;
    }
    $batch = page_one('SELECT company_rubric_id, lecturer_rubric_id FROM batches WHERE id = ?', [(int) $internship['batch_id']]);
    $rubric = rubric_with_criteria($batch ? (int) ($batch[$evaluatorType === 'company' ? 'company_rubric_id' : 'lecturer_rubric_id'] ?? 0) : 0);
    return $rubric && $rubric['criteria'] ? $rubric : null;
}

// Điểm thang 10 từ điểm từng tiêu chí: trung bình có trọng số của (điểm / điểm tối đa).
function rubric_total(array $rubric, array $scores): float
{
    $weighted = 0.0;
    $weights = 0.0;
    foreach ($rubric['criteria'] as $criterion) {
        $maximum = max(0.01, (float) $criterion['max_score']);
        $score = (float) ($scores[(int) $criterion['id']] ?? 0);
        $weighted += (float) $criterion['weight'] * min(1.0, max(0.0, $score / $maximum));
        $weights += (float) $criterion['weight'];
    }
    return $weights > 0 ? round($weighted / $weights * 10, 2) : 0.0;
}

// Điểm cuối kỳ = trung bình có trọng số của điểm hướng dẫn, phản biện và hội đồng (trung bình các thành viên). Thiếu vai trò có trọng số thì chưa tính.
function thesis_compute_final(int $thesisId): ?float
{
    $thesis = page_one('SELECT th.id, b.supervisor_weight, b.reviewer_weight, b.council_weight FROM theses th JOIN batches b ON b.id = th.batch_id WHERE th.id = ?', [$thesisId]);
    if (!$thesis) {
        return null;
    }
    $byRole = [];
    foreach (page_all('SELECT evaluator_role, total FROM thesis_scores WHERE thesis_id = ? AND status = \'submitted\' AND total IS NOT NULL', [$thesisId]) as $row) {
        $byRole[$row['evaluator_role']][] = (float) $row['total'];
    }
    $total = 0.0;
    $weights = 0.0;
    foreach (['supervisor', 'reviewer', 'council'] as $role) {
        $weight = (float) $thesis[$role . '_weight'];
        if ($weight <= 0) {
            continue;
        }
        if (empty($byRole[$role])) {
            database()->prepare('UPDATE theses SET final_score = NULL WHERE id = ?')->execute([$thesisId]);
            return null;
        }
        $total += $weight * array_sum($byRole[$role]) / count($byRole[$role]);
        $weights += $weight;
    }
    $final = $weights > 0 ? round($total / $weights, 2) : null;
    database()->prepare('UPDATE theses SET final_score = ? WHERE id = ?')->execute([$final, $thesisId]);
    return $final;
}

// Đề tài có tên gần giống (so khớp từ khóa, hệ số Jaccard) trong mọi đợt, để phát hiện trùng lặp và tham khảo khóa trước.
// Đề tài gần giống: so tên (Jaccard theo từ khóa) và cả mô tả + mục tiêu; điểm = max(tên, 0,6 × tên + 0,4 × mô tả).
function topic_similar(string $title, int $excludeId = 0, int $limit = 3, string $description = ''): array
{
    $tokens = array_unique(page_recommendation_tokens($title));
    if (!$tokens) {
        return [];
    }
    $descriptionTokens = array_unique(page_recommendation_tokens($description));
    $jaccard = static function (array $a, array $b): float {
        $union = count(array_unique(array_merge($a, $b)));
        return $union ? count(array_intersect($a, $b)) / $union : 0.0;
    };
    $matches = [];
    foreach (page_all('SELECT t.id, t.title, t.description, t.objectives, t.status, b.name AS batch_name FROM topics t JOIN batches b ON b.id = t.batch_id WHERE t.id <> ? AND t.status <> \'rejected\'', [$excludeId]) as $row) {
        $titleScore = $jaccard($tokens, array_unique(page_recommendation_tokens((string) $row['title'])));
        $score = $titleScore;
        if ($descriptionTokens) {
            $otherDescription = array_unique(page_recommendation_tokens($row['description'] . ' ' . $row['objectives']));
            $score = max($titleScore, 0.6 * $titleScore + 0.4 * $jaccard($descriptionTokens, $otherDescription));
        }
        if ($score >= 0.45) {
            $row['similarity'] = (int) round($score * 100);
            $matches[] = $row;
        }
    }
    usort($matches, static fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    return array_slice($matches, 0, $limit);
}

// Hạn mức hướng dẫn của giảng viên trong đợt: hạn mức riêng của đợt nếu có, không thì hạn mức chung.
function lecturer_quota(int $batchId, int $lecturerId): int
{
    $row = page_one('SELECT q.max_students AS batch_quota, l.max_students FROM lecturers l LEFT JOIN batch_lecturer_quotas q ON q.lecturer_id = l.id AND q.batch_id = ? WHERE l.id = ?', [$batchId, $lecturerId]);
    return (int) ($row['batch_quota'] ?? $row['max_students'] ?? 0);
}

function lecturer_quotas(int $batchId): array
{
    return array_map('intval', array_column(page_all('SELECT l.id, COALESCE(q.max_students, l.max_students) AS quota FROM lecturers l LEFT JOIN batch_lecturer_quotas q ON q.lecturer_id = l.id AND q.batch_id = ?', [$batchId]), 'quota', 'id'));
}

// Các giai đoạn nộp bài của đợt đồ án: 3 giai đoạn chuẩn + mốc nộp bài do phòng đào tạo thêm (kind = stage_*) + tài liệu khác.
function thesis_stages(int $batchId): array
{
    static $cache = [];
    if (isset($cache[$batchId])) {
        return $cache[$batchId];
    }
    $stages = array_diff_key(THESIS_STAGES, ['other' => true]);
    foreach (page_all('SELECT kind, title FROM batch_milestones WHERE batch_id = ? AND kind LIKE \'stage\\_%\' ORDER BY COALESCE(ends_at, starts_at), id', [$batchId]) as $row) {
        $stages[$row['kind']] = $row['title'];
    }
    return $cache[$batchId] = $stages + ['other' => THESIS_STAGES['other']];
}

// Số sinh viên mỗi giảng viên đang hướng dẫn trong một đợt.
function lecturer_supervision_load(int $batchId): array
{
    $load = [];
    foreach (page_all('SELECT supervisor_id, COUNT(*) AS total FROM theses WHERE batch_id = ? AND supervisor_id IS NOT NULL AND status <> \'declined\' GROUP BY supervisor_id', [$batchId]) as $row) {
        $load[(int) $row['supervisor_id']] = (int) $row['total'];
    }
    return $load;
}

function thesis_lecturers(): array
{
    return page_all('SELECT l.id, l.lecturer_code, l.department, l.research_areas, l.max_students, l.max_reviews, l.max_councils, l.is_head, l.faculty, u.full_name, u.id AS user_id FROM lecturers l JOIN users u ON u.id = l.user_id WHERE u.status = \'active\' ORDER BY u.full_name');
}

// Bài nộp, góp ý, mốc công việc và lịch gặp của một đồ án (dùng chung trong nhóm).
function thesis_workspace(array $thesis): array
{
    $ids = thesis_related_ids($thesis);
    $submissions = page_all('SELECT ts.*, u.full_name AS submitter FROM thesis_submissions ts LEFT JOIN users u ON u.id = ts.submitted_by WHERE ts.thesis_id IN (' . thesis_in($ids) . ') ORDER BY ts.stage, ts.version DESC', $ids);
    $comments = [];
    if ($submissions) {
        $submissionIds = array_map('intval', array_column($submissions, 'id'));
        foreach (page_all('SELECT c.*, u.full_name, u.role FROM submission_comments c JOIN users u ON u.id = c.user_id WHERE c.submission_id IN (' . thesis_in($submissionIds) . ') ORDER BY c.created_at', $submissionIds) as $comment) {
            $comments[(int) $comment['submission_id']][] = $comment;
        }
    }
    foreach ($submissions as &$submission) {
        $submission['comments'] = $comments[(int) $submission['id']] ?? [];
    }
    unset($submission);
    return [
        'submissions' => $submissions,
        'milestones' => page_all('SELECT * FROM thesis_milestones WHERE thesis_id IN (' . thesis_in($ids) . ') ORDER BY status, due_date IS NULL, due_date, id', $ids),
        'meetings' => page_all('SELECT * FROM thesis_meetings WHERE thesis_id IN (' . thesis_in($ids) . ') ORDER BY meeting_at DESC', $ids),
        // Điểm giảng viên hướng dẫn chấm theo từng giai đoạn nộp bài.
        'stage_scores' => array_column(page_all('SELECT stage, score, comments FROM thesis_stage_scores WHERE thesis_id = ?', [(int) $thesis['id']]), null, 'stage'),
    ];
}

// Phiếu chấm của một người cho một đồ án theo vai trò, kèm rubric của đợt và điểm từng tiêu chí.
function thesis_score_sheet(array $thesis, array $batch, int $userId, string $role): array
{
    $rubric = rubric_with_criteria($batch[$role . '_rubric_id'] !== null ? (int) $batch[$role . '_rubric_id'] : null);
    $score = page_one('SELECT * FROM thesis_scores WHERE thesis_id = ? AND evaluator_user_id = ? AND evaluator_role = ?', [(int) $thesis['id'], $userId, $role]);
    $items = [];
    if ($score) {
        foreach (page_all('SELECT criterion_id, score FROM thesis_score_items WHERE score_id = ?', [(int) $score['id']]) as $item) {
            $items[(int) $item['criterion_id']] = (float) $item['score'];
        }
    }
    return ['role' => $role, 'rubric' => $rubric, 'score' => $score, 'items' => $items];
}

// ---------------------------------------------------------------------------
// (B) DỮ LIỆU MÀN HÌNH
// ---------------------------------------------------------------------------

function thesis_screen_data(string $screen, array $data, array $user): ?array
{
    $lecturerId = (int) ($user['lecturer_id'] ?? 0);
    $studentId = (int) ($user['student_id'] ?? 0);

    switch ($screen) {
        // Quản trị · Đợt: năm học/học kỳ, ngành, đợt và mốc thời gian, danh sách đủ điều kiện.
        case 'admin/batches':
            $data['terms'] = page_all('SELECT * FROM academic_terms ORDER BY school_year DESC, semester DESC');
            $data['majors'] = page_all('SELECT * FROM majors ORDER BY name');
            $data['specializations'] = page_all('SELECT sp.*, m.name AS major_name FROM specializations sp JOIN majors m ON m.id = sp.major_id ORDER BY m.name, sp.name');
            $data['rubrics'] = page_all('SELECT id, name, applies_to FROM rubrics ORDER BY name');
            $data['batches'] = page_all(
                'SELECT b.*, t.name AS term_name, m.name AS major_name,
                        (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id AND bs.eligible = 1) AS eligible_count,
                        (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS listed_count,
                        (SELECT COUNT(*) FROM topics tp WHERE tp.batch_id = b.id) AS topic_count,
                        (SELECT COUNT(*) FROM theses th WHERE th.batch_id = b.id) AS thesis_count,
                        (SELECT COUNT(*) FROM internships i WHERE i.batch_id = b.id) AS internship_count
                 FROM batches b LEFT JOIN academic_terms t ON t.id = b.term_id LEFT JOIN majors m ON m.id = b.major_id WHERE (@it_faculty IS NULL OR b.major_id IS NULL OR m.faculty = @it_faculty) ORDER BY b.created_at DESC'
            );
            $milestones = [];
            foreach (page_all('SELECT * FROM batch_milestones ORDER BY id') as $row) {
                $milestones[(int) $row['batch_id']][$row['kind']] = $row;
            }
            $data['milestones'] = $milestones;
            $selected = thesis_query_int('batch');
            $data['selected_batch'] = $selected;
            $data['eligibility'] = $selected ? page_all('SELECT bs.*, s.student_code, s.gpa, s.credits, s.major, s.class_name, u.full_name FROM batch_students bs JOIN students s ON s.id = bs.student_id JOIN users u ON u.id = s.user_id WHERE bs.batch_id = ? ORDER BY bs.eligible DESC, u.full_name', [$selected]) : [];
            $data['metrics'] = [
                ['label' => 'Đợt đang mở', 'value' => (string) count(array_filter($data['batches'], static fn($row) => $row['status'] === 'open')), 'note' => count($data['batches']) . ' đợt trong hệ thống'],
                ['label' => 'Đợt đồ án', 'value' => (string) count(array_filter($data['batches'], static fn($row) => $row['batch_type'] === 'thesis')), 'note' => 'Đăng ký đề tài, ghép cặp, bảo vệ'],
                ['label' => 'Năm học · học kỳ', 'value' => (string) count($data['terms']), 'note' => count($data['majors']) . ' ngành trong danh mục'],
            ];
            return $data;

        // Quản trị · Đề tài: duyệt đề tài của đợt, kiểm tra trùng lặp và tra cứu kho đề tài các khóa.
        case 'admin/topics':
        case 'lecturer/topics':
            $data['batches'] = thesis_batches('thesis');
            $batch = thesis_selected_batch('thesis');
            $data['batch'] = $batch;
            $data['years'] = array_column(page_all('SELECT DISTINCT school_year FROM academic_terms ORDER BY school_year DESC'), 'school_year');
            $search = trim((string) ($_GET['q'] ?? ''));
            $status = is_string($_GET['status'] ?? null) && isset(TOPIC_STATUS_LABELS[$_GET['status']]) ? $_GET['status'] : '';
            $year = is_string($_GET['year'] ?? null) ? trim($_GET['year']) : '';
            $archive = ($_GET['scope'] ?? '') === 'archive';
            $data['filters'] = ['q' => $search, 'status' => $status, 'year' => $year, 'archive' => $archive];
            $where = ['(@it_faculty IS NULL OR l.faculty = @it_faculty OR s.faculty = @it_faculty)'];
            $parameters = [];
            if ($screen === 'lecturer/topics' && !$archive) {
                $where[] = 't.lecturer_id = ?';
                $parameters[] = $lecturerId;
            } elseif (!$archive) {
                $where[] = 't.batch_id = ?';
                $parameters[] = (int) ($batch['id'] ?? 0);
            } else {
                $where[] = 't.status = \'approved\'';
            }
            if ($status !== '') {
                $where[] = 't.status = ?';
                $parameters[] = $status;
            }
            if ($year !== '') {
                $where[] = 'ac.school_year = ?';
                $parameters[] = $year;
            }
            if ($search !== '') {
                $where[] = '(t.title LIKE ? OR t.field LIKE ? OR t.technologies LIKE ? OR lu.full_name LIKE ?)';
                array_push($parameters, ...array_fill(0, 4, '%' . $search . '%'));
            }
            $data['topics'] = page_all(
                'SELECT t.*, b.name AS batch_name, b.status AS batch_status, ac.school_year, lu.full_name AS lecturer_name, l.department, su.full_name AS proposer_name,
                        (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id) AS pref_total,
                        (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id AND p.rank_no = 1) AS pref_first,
                        (SELECT COUNT(*) FROM theses th WHERE th.topic_id = t.id AND th.status <> \'declined\') AS assigned,
                        (SELECT sb.id FROM thesis_submissions sb JOIN theses th ON th.id = sb.thesis_id WHERE th.topic_id = t.id AND sb.stage = \'report_final\' AND th.final_score IS NOT NULL ORDER BY sb.version DESC LIMIT 1) AS archive_submission_id
                 FROM topics t JOIN batches b ON b.id = t.batch_id LEFT JOIN academic_terms ac ON ac.id = b.term_id
                 LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users lu ON lu.id = l.user_id
                 LEFT JOIN students s ON s.id = t.proposed_by_student_id LEFT JOIN users su ON su.id = s.user_id
                 WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(t.status, \'pending\', \'revision\', \'approved\', \'draft\', \'rejected\'), t.created_at DESC LIMIT 300',
                $parameters
            );
            foreach ($data['topics'] as &$topic) {
                $topic['similar'] = in_array($topic['status'], ['pending', 'dept_approved', 'revision'], true) ? topic_similar((string) $topic['title'], (int) $topic['id'], 3, $topic['description'] . ' ' . $topic['objectives']) : [];
            }
            unset($topic);

            if ($screen === 'lecturer/topics') {
                $me = page_one('SELECT * FROM lecturers WHERE id = ?', [$lecturerId]) ?: [];
                $data['lecturer'] = $me;
                $data['open_batches'] = array_values(array_filter($data['batches'], static fn($row) => $row['status'] === 'open' && empty($row['locked_at'])));
                foreach ($data['open_batches'] as &$openBatch) {
                    $openBatch['phase'] = batch_phase_state($openBatch, 'topic_registration');
                    $openBatch['window'] = batch_window_text((int) $openBatch['id'], 'topic_registration');
                }
                unset($openBatch);
                // Sinh viên đăng ký nguyện vọng vào từng đề tài của tôi, kèm thứ hạng tôi đã xếp.
                $applicants = [];
                $myTopicIds = array_map('intval', array_column(array_filter($data['topics'], static fn($row) => (int) $row['lecturer_id'] === $lecturerId), 'id'));
                if ($myTopicIds) {
                    foreach (page_all(
                        'SELECT p.topic_id, p.rank_no, s.id AS student_id, s.student_code, s.gpa, s.skills, u.full_name, lr.rank_no AS lecturer_rank
                         FROM topic_preferences p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id
                         LEFT JOIN lecturer_rankings lr ON lr.topic_id = p.topic_id AND lr.student_id = p.student_id
                         WHERE p.topic_id IN (' . thesis_in($myTopicIds) . ') ORDER BY lr.rank_no IS NULL, lr.rank_no, s.gpa DESC',
                        $myTopicIds
                    ) as $row) {
                        $applicants[(int) $row['topic_id']][] = $row;
                    }
                }
                $data['applicants'] = $applicants;
                // Đề tài sinh viên đề xuất nhờ tôi hướng dẫn, và hàng đợi duyệt của bộ môn nếu tôi là trưởng bộ môn.
                $data['proposals'] = page_all('SELECT t.*, b.name AS batch_name, u.full_name AS proposer_name, s.student_code FROM topics t JOIN batches b ON b.id = t.batch_id JOIN students s ON s.id = t.proposed_by_student_id JOIN users u ON u.id = s.user_id WHERE t.lecturer_id = ? AND t.status IN (\'pending\', \'revision\') ORDER BY t.created_at', [$lecturerId]);
                $data['department_queue'] = !empty($me['is_head']) ? page_all(
                    'SELECT t.*, b.name AS batch_name, u.full_name AS lecturer_name FROM topics t JOIN batches b ON b.id = t.batch_id JOIN lecturers l ON l.id = t.lecturer_id JOIN users u ON u.id = l.user_id
                     WHERE t.status = \'pending\' AND t.proposed_by_student_id IS NULL AND t.lecturer_id <> ? AND l.department = ? ORDER BY t.created_at',
                    [$lecturerId, (string) ($me['department'] ?? '')]
                ) : [];
                foreach ($data['department_queue'] as &$queued) {
                    $queued['similar'] = topic_similar((string) $queued['title'], (int) $queued['id'], 3, $queued['description'] . ' ' . $queued['objectives']);
                }
                unset($queued);
                $mine = array_filter($data['topics'], static fn($row) => (int) $row['lecturer_id'] === $lecturerId);
                $data['metrics'] = [
                    ['label' => 'Đề tài của tôi', 'value' => (string) count($mine), 'note' => count(array_filter($mine, static fn($row) => $row['status'] === 'approved')) . ' đã được duyệt'],
                    ['label' => 'Lượt nguyện vọng', 'value' => (string) array_sum(array_column($mine, 'pref_total')), 'note' => array_sum(array_column($mine, 'pref_first')) . ' lượt nguyện vọng 1'],
                    ['label' => 'Cần xét duyệt', 'value' => (string) (count($data['proposals']) + count($data['department_queue'])), 'note' => 'Đề xuất của sinh viên' . (!empty($me['is_head']) ? ' và đề tài bộ môn' : '')],
                ];
            } else {
                $all = $batch ? page_all('SELECT status FROM topics WHERE batch_id = ?', [(int) $batch['id']]) : [];
                $data['metrics'] = [
                    ['label' => 'Chờ duyệt', 'value' => (string) count(array_filter($all, static fn($row) => $row['status'] === 'pending')), 'note' => 'Trong đợt đang xem'],
                    ['label' => 'Đã duyệt', 'value' => (string) count(array_filter($all, static fn($row) => $row['status'] === 'approved')), 'note' => 'Sẵn sàng cho sinh viên chọn'],
                    ['label' => 'Kho đề tài', 'value' => (string) page_count('SELECT COUNT(*) FROM topics WHERE status = \'approved\''), 'note' => 'Đề tài đã duyệt của mọi khóa'],
                ];
            }
            return $data;

        // Quản trị · Ghép cặp: tham số, chạy thử, so sánh thuật toán, công bố, ghép thủ công, lịch sử.
        case 'admin/matching':
            $data['batches'] = thesis_batches('thesis');
            $batch = thesis_selected_batch('thesis');
            $data['batch'] = $batch;
            $data['params'] = matching_default_params();
            if (!$batch) {
                return $data;
            }
            $batchId = (int) $batch['id'];
            $data['topics'] = page_all(
                'SELECT t.id, t.title, t.max_students, t.field, u.full_name AS lecturer_name,
                        (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id) AS pref_total,
                        (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id AND p.rank_no = 1) AS pref_first,
                        (SELECT COUNT(*) FROM theses th WHERE th.topic_id = t.id AND th.status <> \'declined\') AS assigned
                 FROM topics t LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users u ON u.id = l.user_id WHERE t.batch_id = ? AND t.status = \'approved\' ORDER BY pref_total DESC',
                [$batchId]
            );
            $topicTitles = array_column($data['topics'], 'title', 'id');
            $runs = page_all('SELECT r.*, u.full_name AS creator FROM matching_runs r LEFT JOIN users u ON u.id = r.created_by WHERE r.batch_id = ? ORDER BY r.id DESC LIMIT 40', [$batchId]);
            foreach ($runs as &$run) {
                $run['metrics'] = json_decode((string) $run['metrics'], true) ?: [];
                $run['params'] = json_decode((string) $run['params'], true) ?: [];
            }
            unset($run);
            $data['runs'] = $runs;
            $selectedRun = thesis_query_int('run');
            $current = null;
            foreach ($runs as $run) {
                if ($selectedRun ? (int) $run['id'] === $selectedRun : $run['status'] !== 'discarded') {
                    $current = $run;
                    break;
                }
            }
            if ($current) {
                $raw = page_one('SELECT result FROM matching_runs WHERE id = ?', [(int) $current['id']]);
                $current['result'] = json_decode((string) ($raw['result'] ?? '[]'), true) ?: [];
                foreach ($current['result'] as &$row) {
                    $row['topic_title'] = $row['topic_id'] ? ($topicTitles[$row['topic_id']] ?? 'Đề tài #' . $row['topic_id']) : null;
                }
                unset($row);
                usort($current['result'], static fn($a, $b) => [$a['topic_id'] === null, $a['rank'] ?? 99] <=> [$b['topic_id'] === null, $b['rank'] ?? 99]);
            }
            $data['current_run'] = $current;
            // Bảng so sánh: lần chạy gần nhất của từng thuật toán.
            $compare = [];
            foreach ($runs as $run) {
                if ($run['status'] !== 'discarded' && !isset($compare[$run['algorithm']])) {
                    $compare[$run['algorithm']] = $run;
                }
            }
            $data['compare'] = $compare;
            $data['theses'] = page_all(
                'SELECT th.*, u.full_name, s.student_code, tp.title AS topic_title, lu.full_name AS supervisor_name FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id
                 JOIN topics tp ON tp.id = th.topic_id LEFT JOIN lecturers l ON l.id = th.supervisor_id LEFT JOIN users lu ON lu.id = l.user_id WHERE th.batch_id = ? ORDER BY u.full_name',
                [$batchId]
            );
            $hasList = page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ?', [$batchId]) > 0;
            $data['unassigned'] = page_all(
                'SELECT s.id, s.student_code, s.gpa, u.full_name, (SELECT COUNT(*) FROM topic_preferences p WHERE p.student_id = s.id AND p.batch_id = ?) AS pref_count
                 FROM students s JOIN users u ON u.id = s.user_id WHERE u.status = \'active\''
                . ($hasList ? ' AND s.id IN (SELECT student_id FROM batch_students WHERE batch_id = ? AND eligible = 1)' : ' AND s.id IN (SELECT student_id FROM topic_preferences WHERE batch_id = ?)')
                . ' AND s.id NOT IN (SELECT student_id FROM theses WHERE batch_id = ? AND status <> \'declined\') ORDER BY u.full_name',
                [$batchId, $batchId, $batchId]
            );
            $data['phase'] = batch_phase_state($batch, 'matching');
            $eligible = $hasList ? page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND eligible = 1', [$batchId]) : page_count('SELECT COUNT(DISTINCT student_id) FROM topic_preferences WHERE batch_id = ?', [$batchId]);
            $data['metrics'] = [
                ['label' => 'Sinh viên cần ghép', 'value' => (string) $eligible, 'note' => page_count('SELECT COUNT(DISTINCT student_id) FROM topic_preferences WHERE batch_id = ?', [$batchId]) . ' đã đăng ký nguyện vọng'],
                ['label' => 'Đề tài · chỗ', 'value' => count($data['topics']) . ' · ' . array_sum(array_column($data['topics'], 'max_students')), 'note' => 'Đề tài đã duyệt và tổng sức chứa'],
                ['label' => 'Đã ghép', 'value' => (string) count(array_filter($data['theses'], static fn($row) => $row['status'] !== 'declined')), 'note' => count(array_filter($data['theses'], static fn($row) => (int) $row['locked'] === 1)) . ' cặp đã khóa'],
            ];
            return $data;

        // Quản trị · Phân công & bảo vệ: phản biện, hội đồng, lịch bảo vệ, phúc khảo, công bố điểm.
        case 'admin/theses':
            $data['batches'] = thesis_batches('thesis');
            $batch = thesis_selected_batch('thesis');
            $data['batch'] = $batch;
            if (!$batch) {
                return $data;
            }
            $batchId = (int) $batch['id'];
            $data['lecturers'] = thesis_lecturers();
            $data['councils'] = page_all('SELECT * FROM councils WHERE batch_id = ? ORDER BY defense_date, start_time, name', [$batchId]);
            $members = [];
            foreach (page_all('SELECT cm.*, u.full_name FROM council_members cm JOIN councils c ON c.id = cm.council_id JOIN lecturers l ON l.id = cm.lecturer_id JOIN users u ON u.id = l.user_id WHERE c.batch_id = ? ORDER BY FIELD(cm.member_role, \'chair\', \'secretary\', \'member\')', [$batchId]) as $row) {
                $members[(int) $row['council_id']][] = $row;
            }
            $data['council_members'] = $members;
            $data['theses'] = page_all(
                'SELECT th.*, u.full_name, s.student_code, tp.title AS topic_title, tp.field, su.full_name AS supervisor_name, ru.full_name AS reviewer_name, c.name AS council_name,
                        (SELECT COUNT(*) FROM thesis_scores sc WHERE sc.thesis_id = th.id AND sc.status = \'submitted\') AS score_count,
                        (SELECT COUNT(*) FROM thesis_submissions sb WHERE sb.thesis_id = th.id AND sb.stage = \'report_final\') AS final_submissions
                 FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN topics tp ON tp.id = th.topic_id
                 LEFT JOIN lecturers sl ON sl.id = th.supervisor_id LEFT JOIN users su ON su.id = sl.user_id
                 LEFT JOIN lecturers rl ON rl.id = th.reviewer_id LEFT JOIN users ru ON ru.id = rl.user_id
                 LEFT JOIN councils c ON c.id = th.council_id WHERE th.batch_id = ? AND th.status <> \'declined\' ORDER BY c.name IS NULL, c.name, th.defense_at, u.full_name',
                [$batchId]
            );
            // Cảnh báo: xung đột lợi ích (phản biện / hội đồng trùng người hướng dẫn) và vượt hạn mức hướng dẫn.
            $warnings = [];
            $load = lecturer_supervision_load($batchId);
            $lecturerNames = array_column($data['lecturers'], 'full_name', 'id');
            $data['quotas'] = lecturer_quotas($batchId);
            foreach ($data['lecturers'] as $lecturer) {
                $quota = $data['quotas'][(int) $lecturer['id']] ?? (int) $lecturer['max_students'];
                if (($load[(int) $lecturer['id']] ?? 0) > $quota) {
                    $warnings[] = $lecturer['full_name'] . ' hướng dẫn ' . $load[(int) $lecturer['id']] . ' sinh viên, vượt hạn mức ' . $quota . ' của đợt.';
                }
            }
            foreach ($data['theses'] as $thesis) {
                if ($thesis['reviewer_id'] && (int) $thesis['reviewer_id'] === (int) $thesis['supervisor_id']) {
                    $warnings[] = $thesis['full_name'] . ': người phản biện trùng người hướng dẫn.';
                }
                $councilLecturers = array_map('intval', array_column($members[(int) $thesis['council_id']] ?? [], 'lecturer_id'));
                if ($thesis['council_id'] && in_array((int) $thesis['supervisor_id'], $councilLecturers, true)) {
                    $warnings[] = $thesis['full_name'] . ': người hướng dẫn (' . ($lecturerNames[(int) $thesis['supervisor_id']] ?? '') . ') nằm trong hội đồng chấm.';
                }
            }
            $reviewCount = [];
            foreach ($data['theses'] as $thesis) {
                if ($thesis['reviewer_id']) {
                    $reviewCount[(int) $thesis['reviewer_id']] = ($reviewCount[(int) $thesis['reviewer_id']] ?? 0) + 1;
                }
            }
            $councilCount = array_map('intval', array_column(page_all('SELECT cm.lecturer_id, COUNT(DISTINCT cm.council_id) AS total FROM council_members cm JOIN councils c ON c.id = cm.council_id WHERE c.batch_id = ? GROUP BY cm.lecturer_id', [$batchId]), 'total', 'lecturer_id'));
            foreach ($data['lecturers'] as $lecturer) {
                if (($reviewCount[(int) $lecturer['id']] ?? 0) > (int) $lecturer['max_reviews']) {
                    $warnings[] = $lecturer['full_name'] . ' phản biện ' . $reviewCount[(int) $lecturer['id']] . ' đồ án, vượt hạn mức ' . (int) $lecturer['max_reviews'] . '.';
                }
                if (($councilCount[(int) $lecturer['id']] ?? 0) > (int) $lecturer['max_councils']) {
                    $warnings[] = $lecturer['full_name'] . ' tham gia ' . $councilCount[(int) $lecturer['id']] . ' hội đồng, vượt hạn mức ' . (int) $lecturer['max_councils'] . '.';
                }
            }
            $data['warnings'] = $warnings;
            $data['load'] = $load;
            $reviewLoad = [];
            foreach ($data['theses'] as $thesis) {
                if ($thesis['reviewer_id']) {
                    $reviewLoad[(int) $thesis['reviewer_id']] = ($reviewLoad[(int) $thesis['reviewer_id']] ?? 0) + 1;
                }
            }
            $data['review_load'] = $reviewLoad;
            $data['regrades'] = page_all('SELECT r.*, u.full_name, tp.title AS topic_title, th.final_score FROM regrade_requests r JOIN theses th ON th.id = r.thesis_id JOIN topics tp ON tp.id = th.topic_id JOIN students s ON s.id = r.student_id JOIN users u ON u.id = s.user_id WHERE th.batch_id = ? ORDER BY r.status = \'pending\' DESC, r.created_at DESC', [$batchId]);
            $scored = array_filter($data['theses'], static fn($row) => $row['final_score'] !== null);
            $data['metrics'] = [
                ['label' => 'Đồ án', 'value' => (string) count($data['theses']), 'note' => count(array_filter($data['theses'], static fn($row) => !$row['reviewer_id'])) . ' chưa có phản biện'],
                ['label' => 'Hội đồng', 'value' => (string) count($data['councils']), 'note' => count(array_filter($data['theses'], static fn($row) => !$row['council_id'])) . ' đồ án chưa xếp hội đồng'],
                ['label' => 'Đã có điểm', 'value' => count($scored) . '/' . count($data['theses']), 'note' => $batch['results_published_at'] ? 'Đã công bố ' . thesis_datetime_label($batch['results_published_at']) : 'Chưa công bố điểm'],
            ];
            return $data;

        // Quản trị · Rubric: thư viện tiêu chí chấm, dùng lại giữa các đợt.
        case 'admin/rubrics':
            $rubrics = page_all('SELECT r.*, (SELECT COUNT(*) FROM batches b WHERE r.id IN (b.supervisor_rubric_id, b.reviewer_rubric_id, b.council_rubric_id, b.company_rubric_id, b.lecturer_rubric_id)) AS used_by FROM rubrics r ORDER BY r.created_at DESC');
            foreach ($rubrics as &$rubric) {
                $rubric['criteria'] = page_all('SELECT * FROM rubric_criteria WHERE rubric_id = ? ORDER BY sort_order, id', [(int) $rubric['id']]);
            }
            unset($rubric);
            $data['rubrics'] = $rubrics;
            $data['metrics'] = [
                ['label' => 'Rubric', 'value' => (string) count($rubrics), 'note' => 'Trong thư viện'],
                ['label' => 'Tiêu chí', 'value' => (string) array_sum(array_map(static fn($rubric) => count($rubric['criteria']), $rubrics)), 'note' => 'Có trọng số và mô tả mức'],
                ['label' => 'Đang dùng', 'value' => (string) count(array_filter($rubrics, static fn($rubric) => (int) $rubric['used_by'] > 0)), 'note' => 'Được gán cho ít nhất một đợt'],
            ];
            return $data;

        // Giảng viên · Đồ án hướng dẫn: mốc công việc, lịch gặp, bài nộp theo phiên bản, góp ý, chấm hướng dẫn.
        case 'lecturer/theses':
        case 'lecturer/defense':
            $isDefense = $screen === 'lecturer/defense';
            $myCouncils = page_all('SELECT c.*, cm.member_role, b.name AS batch_name FROM councils c JOIN council_members cm ON cm.council_id = c.id JOIN batches b ON b.id = c.batch_id WHERE cm.lecturer_id = ? ORDER BY c.defense_date DESC', [$lecturerId]);
            $councilIds = array_map('intval', array_column($myCouncils, 'id'));
            $condition = $isDefense
                ? '(th.reviewer_id = ?' . ($councilIds ? ' OR th.council_id IN (' . thesis_in($councilIds) . ')' : '') . ')'
                : 'th.supervisor_id = ?';
            $parameters = $isDefense ? array_merge([$lecturerId], $councilIds) : [$lecturerId];
            $theses = page_all(
                'SELECT th.*, u.full_name AS student_name, u.id AS student_user_id, s.student_code, tp.title AS topic_title, tp.field, b.name AS batch_name, b.locked_at, b.results_published_at,
                        c.name AS council_name, c.room, su.full_name AS supervisor_name
                 FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN topics tp ON tp.id = th.topic_id JOIN batches b ON b.id = th.batch_id
                 LEFT JOIN councils c ON c.id = th.council_id LEFT JOIN lecturers sl ON sl.id = th.supervisor_id LEFT JOIN users su ON su.id = sl.user_id
                 WHERE th.status <> \'declined\' AND ' . $condition . ' ORDER BY b.created_at DESC, th.defense_at, u.full_name',
                $parameters
            );
            foreach ($theses as &$thesis) {
                $batch = thesis_batch((int) $thesis['batch_id']);
                $roles = [];
                if ((int) $thesis['supervisor_id'] === $lecturerId && !$isDefense) {
                    $roles[] = 'supervisor';
                }
                if ($isDefense && (int) $thesis['reviewer_id'] === $lecturerId) {
                    $roles[] = 'reviewer';
                }
                if ($isDefense && in_array((int) $thesis['council_id'], $councilIds, true)) {
                    $roles[] = 'council';
                }
                $thesis['sheets'] = array_map(static fn($role) => thesis_score_sheet($thesis, $batch, (int) $user['id'], $role), $roles);
                $thesis += thesis_workspace($thesis);
            }
            unset($thesis);
            $data['theses'] = $theses;
            $data['councils'] = $myCouncils;
            $pendingSubmissions = 0;
            foreach ($theses as $thesis) {
                $pendingSubmissions += count(array_filter($thesis['submissions'], static fn($row) => $row['status'] === 'submitted'));
            }
            $data['metrics'] = $isDefense ? [
                ['label' => 'Đồ án phản biện', 'value' => (string) count(array_filter($theses, static fn($row) => (int) $row['reviewer_id'] === $lecturerId)), 'note' => 'Được phân công cho bạn'],
                ['label' => 'Hội đồng tham gia', 'value' => (string) count($myCouncils), 'note' => count(array_filter($theses, static fn($row) => in_array((int) $row['council_id'], $councilIds, true))) . ' đồ án cần chấm'],
                ['label' => 'Phiếu đã gửi', 'value' => (string) page_count('SELECT COUNT(*) FROM thesis_scores WHERE evaluator_user_id = ? AND evaluator_role IN (\'reviewer\', \'council\') AND status = \'submitted\'', [(int) $user['id']]), 'note' => 'Phản biện và hội đồng'],
            ] : [
                ['label' => 'Sinh viên hướng dẫn', 'value' => (string) count($theses), 'note' => 'Hạn mức ' . (int) (page_one('SELECT max_students FROM lecturers WHERE id = ?', [$lecturerId])['max_students'] ?? 0) . ' sinh viên/đợt'],
                ['label' => 'Bài nộp chờ xem', 'value' => (string) $pendingSubmissions, 'note' => 'Theo phiên bản mới nhất'],
                ['label' => 'Lịch gặp sắp tới', 'value' => (string) page_count('SELECT COUNT(*) FROM thesis_meetings m JOIN theses th ON th.id = m.thesis_id WHERE th.supervisor_id = ? AND m.status = \'scheduled\' AND m.meeting_at >= NOW()', [$lecturerId]), 'note' => 'Đã lên lịch'],
            ];
            return $data;

        // Sinh viên · Đồ án: điều kiện, nhóm, chọn đề tài/nguyện vọng, đề xuất đề tài, kết quả ghép, theo dõi, nộp bài, điểm, phúc khảo.
        case 'student/thesis':
            $batches = array_values(array_filter(thesis_batches('thesis'), static fn($row) => $row['status'] !== 'draft'));
            $data['batches'] = $batches;
            $batch = null;
            $requested = thesis_query_int('batch');
            foreach ($batches as $row) {
                if ($requested ? (int) $row['id'] === $requested : true) {
                    $batch = $row;
                    break;
                }
            }
            $data['batch'] = $batch;
            $data['archive_query'] = trim((string) ($_GET['q'] ?? ''));
            $data['archive'] = $data['archive_query'] !== '' ? page_all(
                'SELECT t.title, t.field, t.technologies, t.description, b.name AS batch_name, u.full_name AS lecturer_name FROM topics t JOIN batches b ON b.id = t.batch_id LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users u ON u.id = l.user_id
                 WHERE t.status = \'approved\' AND b.status = \'closed\' AND (t.title LIKE ? OR t.field LIKE ? OR t.technologies LIKE ?) ORDER BY b.created_at DESC LIMIT 40',
                array_fill(0, 3, '%' . $data['archive_query'] . '%')
            ) : [];
            if (!$batch) {
                $data['metrics'] = [];
                return $data;
            }
            $batchId = (int) $batch['id'];
            $data['eligible'] = batch_student_eligible($batchId, $studentId);
            $data['eligibility_note'] = (string) (page_one('SELECT note FROM batch_students WHERE batch_id = ? AND student_id = ?', [$batchId, $studentId])['note'] ?? '');
            $data['phases'] = [];
            foreach (BATCH_MILESTONES['thesis'] as $kind => $title) {
                $data['phases'][$kind] = ['title' => $title, 'state' => batch_phase_state($batch, $kind), 'window' => batch_window_text($batchId, $kind)];
            }
            // Nhóm: nhóm tôi làm trưởng hoặc đã tham gia, và lời mời đang chờ.
            $group = page_one(
                'SELECT g.* FROM thesis_groups g LEFT JOIN thesis_group_members m ON m.group_id = g.id AND m.student_id = ? AND m.status = \'accepted\' WHERE g.batch_id = ? AND (g.leader_student_id = ? OR m.student_id IS NOT NULL) LIMIT 1',
                [$studentId, $batchId, $studentId]
            );
            if ($group) {
                $group['members'] = page_all('SELECT m.*, u.full_name, s.student_code FROM thesis_group_members m JOIN students s ON s.id = m.student_id JOIN users u ON u.id = s.user_id WHERE m.group_id = ?', [(int) $group['id']]);
                $group['leader_name'] = (string) (page_one('SELECT u.full_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) $group['leader_student_id']])['full_name'] ?? '');
                $group['is_leader'] = (int) $group['leader_student_id'] === $studentId;
            }
            $data['group'] = $group;
            $data['invitations'] = page_all('SELECT g.id, g.name, u.full_name AS leader_name FROM thesis_group_members m JOIN thesis_groups g ON g.id = m.group_id JOIN students s ON s.id = g.leader_student_id JOIN users u ON u.id = s.user_id WHERE m.student_id = ? AND m.status = \'invited\' AND g.batch_id = ?', [$studentId, $batchId]);
            $prefOwner = $group ? (int) $group['leader_student_id'] : $studentId;
            $data['can_edit_prefs'] = !$group || $group['is_leader'];
            $data['topics'] = page_all(
                'SELECT t.*, u.full_name AS lecturer_name, l.research_areas, (SELECT COUNT(*) FROM topic_preferences p WHERE p.topic_id = t.id) AS pref_total,
                        (SELECT COUNT(*) FROM theses th WHERE th.topic_id = t.id AND th.status <> \'declined\') AS assigned
                 FROM topics t LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users u ON u.id = l.user_id WHERE t.batch_id = ? AND t.status = \'approved\' ORDER BY t.field, t.title',
                [$batchId]
            );
            $data['preferences'] = array_map('intval', array_column(page_all('SELECT topic_id FROM topic_preferences WHERE batch_id = ? AND student_id = ? ORDER BY rank_no', [$batchId, $prefOwner]), 'topic_id'));
            $data['proposals'] = page_all('SELECT t.*, u.full_name AS lecturer_name FROM topics t LEFT JOIN lecturers l ON l.id = t.lecturer_id LEFT JOIN users u ON u.id = l.user_id WHERE t.batch_id = ? AND t.proposed_by_student_id = ? ORDER BY t.created_at DESC', [$batchId, $studentId]);
            $data['lecturers'] = thesis_lecturers();
            $thesis = page_one(
                'SELECT th.*, tp.title AS topic_title, tp.description AS topic_description, tp.field, su.full_name AS supervisor_name, su.id AS supervisor_user_id, ru.full_name AS reviewer_name, c.name AS council_name, c.room
                 FROM theses th JOIN topics tp ON tp.id = th.topic_id LEFT JOIN lecturers sl ON sl.id = th.supervisor_id LEFT JOIN users su ON su.id = sl.user_id
                 LEFT JOIN lecturers rl ON rl.id = th.reviewer_id LEFT JOIN users ru ON ru.id = rl.user_id LEFT JOIN councils c ON c.id = th.council_id WHERE th.batch_id = ? AND th.student_id = ?',
                [$batchId, $studentId]
            );
            if ($thesis) {
                $thesis += thesis_workspace($thesis);
                $thesis['scores'] = $batch['results_published_at'] ? page_all('SELECT evaluator_role, AVG(total) AS total, GROUP_CONCAT(comments SEPARATOR \'\n— \') AS comments FROM thesis_scores WHERE thesis_id = ? AND status = \'submitted\' GROUP BY evaluator_role', [(int) $thesis['id']]) : [];
                $thesis['regrades'] = page_all('SELECT * FROM regrade_requests WHERE thesis_id = ? ORDER BY created_at DESC', [(int) $thesis['id']]);
                $thesis['stage_windows'] = [];
                $thesis['stages'] = thesis_stages($batchId);
                foreach ($thesis['stages'] as $stage => $label) {
                    $thesis['stage_windows'][$stage] = $stage === 'other' ? 'Không giới hạn' : batch_window_text($batchId, $stage);
                }
            }
            $data['thesis'] = $thesis;
            // Kho đồ án các khóa trước để tham khảo: tìm theo tên / lĩnh vực / công nghệ; tải quyển nếu phòng đào tạo cho phép.
            $archiveSearch = trim((string) ($_GET['archive_q'] ?? ''));
            $data['archive_search'] = $archiveSearch;
            $data['archive_download'] = app_setting('archive_students', '1') === '1';
            $data['archive'] = page_all(
                'SELECT t.title, t.field, t.technologies, b.name AS batch_name, MAX(th.final_score) AS final_score,
                        MAX((SELECT sb.id FROM thesis_submissions sb WHERE sb.thesis_id = th.id AND sb.stage = \'report_final\' ORDER BY sb.version DESC LIMIT 1)) AS submission_id
                 FROM theses th JOIN topics t ON t.id = th.topic_id JOIN batches b ON b.id = th.batch_id
                 WHERE th.final_score IS NOT NULL AND (b.locked_at IS NOT NULL OR b.results_published_at IS NOT NULL) AND th.batch_id <> ?
                   AND (? = \'\' OR t.title LIKE ? OR t.field LIKE ? OR t.technologies LIKE ?)
                 GROUP BY th.topic_id, t.title, t.field, t.technologies, b.name, b.created_at ORDER BY b.created_at DESC, t.title LIMIT 30',
                [(int) ($batch['id'] ?? 0), $archiveSearch, '%' . $archiveSearch . '%', '%' . $archiveSearch . '%', '%' . $archiveSearch . '%']
            );
            $data['metrics'] = [
                ['label' => 'Điều kiện tham gia', 'value' => $data['eligible'] ? 'Đủ' : 'Chưa đủ', 'note' => $data['eligibility_note'] ?: $batch['name']],
                ['label' => 'Nguyện vọng', 'value' => count($data['preferences']) . '/' . (int) $batch['max_preferences'], 'note' => batch_phase_label($data['phases']['preference']['state'])],
                ['label' => 'Đề tài', 'value' => $thesis ? 'Đã ghép' : 'Chưa ghép', 'note' => $thesis ? ($thesis['final_score'] !== null && $batch['results_published_at'] ? 'Điểm ' . $thesis['final_score'] . '/10' : THESIS_STATUS_LABELS[$thesis['status']] ?? '') : count($data['topics']) . ' đề tài đã duyệt'],
            ];
            return $data;
    }
    return null;
}

// ---------------------------------------------------------------------------
// (C) THAO TÁC
// ---------------------------------------------------------------------------

// Trả về ['route' => ..., 'message' => ...] hoặc null nếu action không thuộc phân hệ đồ án / đợt.
function thesis_handle_action(string $action, array $input, array $files): ?array
{
    $connection = database();
    $done = static fn(string $route, string $message): array => ['route' => $route, 'message' => $message];

    switch ($action) {
        // ----- Danh mục và đợt (quản trị) -----
        case 'term_save':
            action_user(['admin']);
            $year = action_value($input, 'school_year', 20);
            $semester = thesis_int($input, 'semester');
            if (!preg_match('/^\d{4}-\d{4}$/', $year) || $semester < 1 || $semester > 3) {
                throw new DomainException('Năm học dạng 2026-2027, học kỳ từ 1 đến 3.');
            }
            if (!empty($input['is_current'])) {
                $connection->exec('UPDATE academic_terms SET is_current = 0');
            }
            $connection->prepare('INSERT INTO academic_terms (name, school_year, semester, start_date, end_date, is_current) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date), is_current = VALUES(is_current)')
                ->execute(['Học kỳ ' . $semester . ' · ' . $year, $year, $semester, action_value($input, 'start_date', 10) ?: null, action_value($input, 'end_date', 10) ?: null, !empty($input['is_current']) ? 1 : 0]);
            return $done('admin/batches', 'Đã lưu năm học / học kỳ.');

        case 'major_save':
            action_user(['admin']);
            $code = strtoupper(action_value($input, 'code', 30));
            $name = action_value($input, 'name', 150);
            if ($code === '' || $name === '') {
                throw new DomainException('Hãy nhập mã và tên ngành.');
            }
            $connection->prepare('INSERT INTO majors (code, name, faculty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), faculty = VALUES(faculty)')->execute([$code, $name, action_value($input, 'faculty', 150) ?: null]);
            return $done('admin/batches', 'Đã lưu ngành ' . $name . '.');

        case 'specialization_save':
            action_user(['admin']);
            $majorId = thesis_int($input, 'major_id');
            $name = action_value($input, 'name', 150);
            if (!$majorId || $name === '' || !page_one('SELECT id FROM majors WHERE id = ?', [$majorId])) {
                throw new DomainException('Hãy chọn ngành và nhập tên chuyên ngành.');
            }
            $connection->prepare('INSERT IGNORE INTO specializations (major_id, name) VALUES (?, ?)')->execute([$majorId, $name]);
            return $done('admin/batches', 'Đã lưu chuyên ngành ' . $name . '.');

        case 'batch_save':
            $user = action_user(['admin']);
            $batchId = thesis_int($input, 'batch_id');
            $type = action_value($input, 'batch_type', 20);
            $name = action_value($input, 'name', 200);
            if (!isset(BATCH_MILESTONES[$type]) || $name === '') {
                throw new DomainException('Hãy chọn loại đợt và nhập tên đợt.');
            }
            $weights = array_map(static fn($key) => max(0.0, (float) ($input[$key] ?? 0)), ['supervisor_weight' => 'supervisor_weight', 'reviewer_weight' => 'reviewer_weight', 'council_weight' => 'council_weight']);
            if ($type === 'thesis' && array_sum($weights) <= 0) {
                throw new DomainException('Tổng trọng số điểm phải lớn hơn 0.');
            }
            $nullableInt = static fn(string $key) => thesis_int($input, $key) ?: null;
            $minGpa = trim((string) ($input['min_gpa'] ?? '')) === '' ? null : max(0.0, min(4.0, (float) $input['min_gpa']));
            $values = [
                $nullableInt('term_id'), $type, $name, $nullableInt('major_id'), action_value($input, 'training_system', 60) ?: null,
                $nullableInt('min_credits'), $minGpa, action_value($input, 'prerequisites', 2000) ?: null,
                !empty($input['allow_student_proposals']) ? 1 : 0, max(1, min(10, thesis_int($input, 'max_preferences') ?: 5)), max(1, min(5, thesis_int($input, 'group_max_size') ?: 1)),
                $weights['supervisor_weight'], $weights['reviewer_weight'], $weights['council_weight'], max(0.0, min(10.0, (float) ($input['pass_score'] ?? 5))),
                $nullableInt('supervisor_rubric_id'), $nullableInt('reviewer_rubric_id'), $nullableInt('council_rubric_id'),
                $nullableInt('company_rubric_id'), $nullableInt('lecturer_rubric_id'),
                $nullableInt('specialization_id'), strtoupper(preg_replace('/\s+/', '', action_value($input, 'required_courses', 500))) ?: null,
                !empty($input['allocation_mode']) ? 1 : 0,
            ];
            if ($batchId) {
                batch_require_unlocked(thesis_batch($batchId));
                $connection->prepare('UPDATE batches SET term_id = ?, batch_type = ?, name = ?, major_id = ?, training_system = ?, min_credits = ?, min_gpa = ?, prerequisites = ?, allow_student_proposals = ?, max_preferences = ?, group_max_size = ?, supervisor_weight = ?, reviewer_weight = ?, council_weight = ?, pass_score = ?, supervisor_rubric_id = ?, reviewer_rubric_id = ?, council_rubric_id = ?, company_rubric_id = ?, lecturer_rubric_id = ?, specialization_id = ?, required_courses = ?, allocation_mode = ? WHERE id = ?')
                    ->execute(array_merge($values, [$batchId]));
                return $done('admin/batches&batch=' . $batchId, 'Đã cập nhật đợt.');
            }
            $batchId = database_transaction(static function (PDO $connection) use ($values, $type, $user): int {
                $connection->prepare('INSERT INTO batches (term_id, batch_type, name, major_id, training_system, min_credits, min_gpa, prerequisites, allow_student_proposals, max_preferences, group_max_size, supervisor_weight, reviewer_weight, council_weight, pass_score, supervisor_rubric_id, reviewer_rubric_id, council_rubric_id, company_rubric_id, lecturer_rubric_id, specialization_id, required_courses, allocation_mode, created_by) VALUES (' . implode(',', array_fill(0, 24, '?')) . ')')
                    ->execute(array_merge($values, [(int) $user['id']]));
                $id = (int) $connection->lastInsertId();
                $insert = $connection->prepare('INSERT INTO batch_milestones (batch_id, kind, title) VALUES (?, ?, ?)');
                foreach (BATCH_MILESTONES[$type] as $kind => $title) {
                    $insert->execute([$id, $kind, $title]);
                }
                return $id;
            });
            return $done('admin/batches&batch=' . $batchId, 'Đã tạo đợt mới. Hãy đặt mốc thời gian và lập danh sách sinh viên đủ điều kiện.');

        case 'batch_milestones_save':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $starts = is_array($input['starts'] ?? null) ? $input['starts'] : [];
            $ends = is_array($input['ends'] ?? null) ? $input['ends'] : [];
            $statement = $connection->prepare('INSERT INTO batch_milestones (batch_id, kind, title, starts_at, ends_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE starts_at = VALUES(starts_at), ends_at = VALUES(ends_at)');
            foreach (BATCH_MILESTONES[$batch['batch_type']] as $kind => $title) {
                $start = thesis_datetime(is_string($starts[$kind] ?? null) ? $starts[$kind] : '');
                $end = thesis_datetime(is_string($ends[$kind] ?? null) ? $ends[$kind] : '', true);
                if ($start && $end && $end < $start) {
                    throw new DomainException('Mốc "' . $title . '": thời điểm kết thúc phải sau thời điểm bắt đầu.');
                }
                $statement->execute([(int) $batch['id'], $kind, $title, $start, $end]);
            }
            // Mốc nộp bài tùy chọn (đợt đồ án): sửa / xóa (để trống tên) mốc đã có, thêm mốc mới.
            if ($batch['batch_type'] === 'thesis') {
                $custom = is_array($input['custom'] ?? null) ? $input['custom'] : [];
                foreach (page_all('SELECT kind FROM batch_milestones WHERE batch_id = ? AND kind LIKE \'stage\\_%\'', [(int) $batch['id']]) as $row) {
                    $values = is_array($custom[$row['kind']] ?? null) ? $custom[$row['kind']] : [];
                    $title = trim(mb_substr((string) ($values['title'] ?? ''), 0, 200, 'UTF-8'));
                    if ($title === '') {
                        if (page_count('SELECT COUNT(*) FROM thesis_submissions s JOIN theses th ON th.id = s.thesis_id WHERE th.batch_id = ? AND s.stage = ?', [(int) $batch['id'], $row['kind']]) > 0) {
                            throw new DomainException('Không xóa được mốc đã có bài nộp.');
                        }
                        $connection->prepare('DELETE FROM batch_milestones WHERE batch_id = ? AND kind = ?')->execute([(int) $batch['id'], $row['kind']]);
                        continue;
                    }
                    $connection->prepare('UPDATE batch_milestones SET title = ?, starts_at = ?, ends_at = ? WHERE batch_id = ? AND kind = ?')
                        ->execute([$title, thesis_datetime((string) ($values['start'] ?? '')), thesis_datetime((string) ($values['end'] ?? ''), true), (int) $batch['id'], $row['kind']]);
                }
                $newTitle = trim(mb_substr(action_value($input, 'new_title', 200), 0, 200, 'UTF-8'));
                if ($newTitle !== '') {
                    $connection->prepare('INSERT INTO batch_milestones (batch_id, kind, title, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)')
                        ->execute([(int) $batch['id'], 'stage_' . substr(bin2hex(random_bytes(4)), 0, 8), $newTitle, thesis_datetime(action_value($input, 'new_start', 20)), thesis_datetime(action_value($input, 'new_end', 20), true)]);
                }
            }
            return $done('admin/batches&batch=' . (int) $batch['id'], 'Đã lưu mốc thời gian. Các chức năng sẽ tự mở/khóa theo mốc.');

        case 'batch_status':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['draft', 'open', 'closed'], true)) {
                throw new DomainException('Trạng thái đợt không hợp lệ.');
            }
            // Đóng đợt = chốt và khóa dữ liệu; mở lại đợt sẽ gỡ khóa.
            $connection->prepare('UPDATE batches SET status = ?, locked_at = ' . ($status === 'closed' ? 'NOW()' : 'NULL') . ' WHERE id = ?')->execute([$status, (int) $batch['id']]);
            if ($status === 'closed' && $batch['batch_type'] === 'thesis') {
                $connection->prepare('UPDATE theses SET status = \'completed\' WHERE batch_id = ? AND final_score IS NOT NULL')->execute([(int) $batch['id']]);
            }
            return $done('admin/batches&batch=' . (int) $batch['id'], ['draft' => 'Đợt chuyển về bản nháp.', 'open' => 'Đợt đã mở.', 'closed' => 'Đợt đã đóng và chốt dữ liệu.'][$status]);

        case 'batch_eligibility_refresh':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            [$eligible, $total] = batch_refresh_eligibility($batch);
            return $done('admin/batches&batch=' . (int) $batch['id'], 'Đã lập danh sách: ' . $eligible . '/' . $total . ' sinh viên đủ điều kiện.');

        // Gắn các kỳ thực tập đang chạy chưa thuộc đợt nào (của sinh viên trong danh sách đợt) vào đợt thực tập này.
        case 'batch_attach_internships':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            if ($batch['batch_type'] !== 'internship') {
                throw new DomainException('Chỉ gắn kỳ thực tập vào đợt thực tập.');
            }
            $hasList = page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ?', [(int) $batch['id']]) > 0;
            $statement = $connection->prepare(
                'UPDATE internships i SET i.batch_id = ? WHERE i.batch_id IS NULL AND i.status IN (\'planned\', \'active\')'
                . ($hasList ? ' AND EXISTS (SELECT 1 FROM batch_students bs WHERE bs.batch_id = ? AND bs.student_id = i.student_id AND bs.eligible = 1)' : '')
            );
            $statement->execute($hasList ? [(int) $batch['id'], (int) $batch['id']] : [(int) $batch['id']]);
            return $done('admin/batches', 'Đã gắn ' . $statement->rowCount() . ' kỳ thực tập vào đợt.');

        case 'batch_eligibility_toggle':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $eligible = !empty($input['eligible']) ? 1 : 0;
            $connection->prepare('UPDATE batch_students SET eligible = ?, note = ? WHERE batch_id = ? AND student_id = ?')->execute([$eligible, 'Điều chỉnh thủ công: ' . ($eligible ? 'cho phép' : 'không cho phép') . ' tham gia', (int) $batch['id'], thesis_int($input, 'student_id')]);
            return $done('admin/batches&batch=' . (int) $batch['id'], 'Đã điều chỉnh điều kiện tham gia.');

        // ----- Đề tài -----
        case 'topic_save':
            $user = action_user(['lecturer']);
            $topicId = thesis_int($input, 'topic_id');
            $existing = $topicId ? page_one('SELECT * FROM topics WHERE id = ? AND lecturer_id = ?', [$topicId, (int) $user['lecturer_id']]) : null;
            if ($topicId && !$existing) {
                throw new DomainException('Không tìm thấy đề tài của bạn.');
            }
            if ($existing && !in_array($existing['status'], ['draft', 'pending', 'revision'], true)) {
                throw new DomainException('Đề tài đã được duyệt hoặc đã bị từ chối nên không sửa được.');
            }
            $batch = thesis_batch($existing ? (int) $existing['batch_id'] : thesis_int($input, 'batch_id'));
            if ($batch['batch_type'] !== 'thesis') {
                throw new DomainException('Chỉ đăng ký đề tài trong đợt đồ án.');
            }
            batch_require_phase($batch, 'topic_registration');
            $title = action_value($input, 'title', 255);
            $maxStudents = max(1, min(10, thesis_int($input, 'max_students') ?: 1));
            if ($title === '') {
                throw new DomainException('Hãy nhập tên đề tài.');
            }
            // Hạn mức hướng dẫn: tổng số chỗ của các đề tài trong đợt không vượt quá hạn mức của giảng viên.
            $quota = lecturer_quota((int) $batch['id'], (int) $user['lecturer_id']);
            $used = page_count('SELECT COALESCE(SUM(max_students), 0) FROM topics WHERE batch_id = ? AND lecturer_id = ? AND status <> \'rejected\' AND id <> ?', [(int) $batch['id'], (int) $user['lecturer_id'], $topicId]);
            if ($used + $maxStudents > $quota) {
                throw new DomainException('Vượt hạn mức hướng dẫn: bạn còn ' . max(0, $quota - $used) . ' chỗ trong đợt này (hạn mức ' . $quota . ').');
            }
            $attachment = store_uploaded_file($files['attachment'] ?? [], 'topics', 10 * 1024 * 1024);
            $status = !empty($input['save_draft']) ? 'draft' : 'pending';
            $fields = [$title, action_value($input, 'description', 10000) ?: null, action_value($input, 'objectives', 5000) ?: null, action_value($input, 'requirements', 5000) ?: null, action_value($input, 'technologies', 255) ?: null, action_value($input, 'field', 150) ?: null, $maxStudents, $status];
            if ($existing) {
                $connection->prepare('UPDATE topics SET title = ?, description = ?, objectives = ?, requirements = ?, technologies = ?, field = ?, max_students = ?, status = ?, attachment_path = COALESCE(?, attachment_path), attachment_name = COALESCE(?, attachment_name) WHERE id = ?')
                    ->execute(array_merge($fields, [$attachment, $attachment ? (string) ($files['attachment']['name'] ?? '') : null, $topicId]));
            } else {
                $connection->prepare('INSERT INTO topics (title, description, objectives, requirements, technologies, field, max_students, status, attachment_path, attachment_name, batch_id, lecturer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute(array_merge($fields, [$attachment, $attachment ? (string) ($files['attachment']['name'] ?? '') : null, (int) $batch['id'], (int) $user['lecturer_id']]));
                $topicId = (int) $connection->lastInsertId();
            }
            $similar = topic_similar($title, $topicId, 1, $fields[1] . ' ' . $fields[2]);
            return $done('lecturer/topics', ($status === 'draft' ? 'Đã lưu bản nháp đề tài.' : 'Đã gửi đề tài chờ bộ môn/khoa duyệt.') . ($similar ? ' Lưu ý: gần giống đề tài "' . $similar[0]['title'] . '" (' . $similar[0]['batch_name'] . ', ' . $similar[0]['similarity'] . '%).' : ''));

        case 'topic_delete':
            $user = action_user(['lecturer', 'admin']);
            $topic = page_one('SELECT * FROM topics WHERE id = ?', [thesis_int($input, 'topic_id')]);
            if (!$topic || ($user['role'] === 'lecturer' && (int) $topic['lecturer_id'] !== (int) $user['lecturer_id']) || ($user['role'] === 'lecturer' && $topic['proposed_by_student_id'])) {
                throw new DomainException('Không tìm thấy đề tài của bạn.');
            }
            if (page_count('SELECT COUNT(*) FROM theses WHERE topic_id = ?', [(int) $topic['id']]) > 0) {
                throw new DomainException('Đề tài đã có sinh viên được ghép nên không xóa được.');
            }
            $connection->prepare('DELETE FROM topics WHERE id = ?')->execute([(int) $topic['id']]);
            return $done($user['role'] === 'admin' ? 'admin/topics&batch=' . (int) $topic['batch_id'] : 'lecturer/topics', 'Đã xóa đề tài.');

        // Duyệt đề tài: quản trị (phòng đào tạo), trưởng bộ môn cùng bộ môn, hoặc giảng viên được sinh viên đề xuất làm hướng dẫn.
        case 'topic_review':
            $user = action_user(['admin', 'lecturer']);
            $topic = page_one('SELECT t.*, l.department FROM topics t LEFT JOIN lecturers l ON l.id = t.lecturer_id WHERE t.id = ?', [thesis_int($input, 'topic_id')]);
            if (!$topic) {
                throw new DomainException('Không tìm thấy đề tài.');
            }
            if ($user['role'] === 'lecturer') {
                $me = page_one('SELECT * FROM lecturers WHERE id = ?', [(int) $user['lecturer_id']]) ?: [];
                $isProposalOwner = $topic['proposed_by_student_id'] && (int) $topic['lecturer_id'] === (int) $user['lecturer_id'];
                $isHead = !empty($me['is_head']) && !$topic['proposed_by_student_id'] && (string) $me['department'] === (string) $topic['department'] && (int) $topic['lecturer_id'] !== (int) $user['lecturer_id'];
                if (!$isProposalOwner && !$isHead) {
                    throw new DomainException('Bạn không có quyền duyệt đề tài này.');
                }
            }
            $batch = thesis_batch((int) $topic['batch_id']);
            batch_require_unlocked($batch);
            $decision = action_value($input, 'decision', 20);
            if (!in_array($decision, ['approved', 'revision', 'rejected'], true)) {
                throw new DomainException('Kết quả duyệt không hợp lệ.');
            }
            $note = action_value($input, 'review_note', 2000);
            // Duyệt 2 cấp: trưởng bộ môn duyệt xong thì đề tài chờ khoa / phòng đào tạo duyệt tiếp.
            if ($decision === 'approved' && $user['role'] === 'lecturer' && empty($isProposalOwner) && app_setting('topic_two_level', '1') === '1') {
                $decision = 'dept_approved';
            }
            if ($user['role'] === 'lecturer' && $topic['status'] === 'dept_approved') {
                throw new DomainException('Đề tài đã qua bộ môn, đang chờ khoa duyệt.');
            }
            database_transaction(static function (PDO $connection) use ($topic, $decision, $note, $user): void {
                $connection->prepare('UPDATE topics SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')->execute([$decision, $note ?: null, (int) $user['id'], (int) $topic['id']]);
                // Đề tài sinh viên tự đề xuất được chấp nhận: ghép thẳng sinh viên với giảng viên hướng dẫn và khóa cặp.
                if ($decision === 'approved' && $topic['proposed_by_student_id']) {
                    $connection->prepare('INSERT INTO theses (batch_id, student_id, topic_id, supervisor_id, source, locked, status, student_confirmed_at) VALUES (?, ?, ?, ?, \'proposal\', 1, \'confirmed\', NOW()) ON DUPLICATE KEY UPDATE topic_id = VALUES(topic_id), supervisor_id = VALUES(supervisor_id), source = \'proposal\', locked = 1, status = \'confirmed\'')
                        ->execute([(int) $topic['batch_id'], (int) $topic['proposed_by_student_id'], (int) $topic['id'], $topic['lecturer_id']]);
                    $connection->prepare('DELETE FROM topic_preferences WHERE batch_id = ? AND student_id = ?')->execute([(int) $topic['batch_id'], (int) $topic['proposed_by_student_id']]);
                }
            });
            $label = TOPIC_STATUS_LABELS[$decision];
            if ($decision === 'dept_approved') {
                foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\'') as $admin) {
                    notify_user((int) $admin['id'], 'topics', 'Đề tài chờ khoa duyệt', $topic['title'], 'admin/topics&batch=' . (int) $topic['batch_id']);
                }
            }
            if ($topic['proposed_by_student_id']) {
                notify_student((int) $topic['proposed_by_student_id'], 'thesis', 'Đề xuất đề tài: ' . $label, $topic['title'] . ($note ? ' · ' . $note : ''), 'student/thesis', true);
            } elseif ($topic['lecturer_id']) {
                notify_lecturer((int) $topic['lecturer_id'], 'topics', 'Đề tài ' . mb_strtolower($label, 'UTF-8'), $topic['title'] . ($note ? ' · ' . $note : ''), 'lecturer/topics');
            }
            return $done($user['role'] === 'admin' ? 'admin/topics&batch=' . (int) $topic['batch_id'] : 'lecturer/topics', 'Đã cập nhật: ' . $label . '.');

        case 'ai_topic_suggest':
            $user = action_user(['lecturer', 'student']);
            $direction = action_value($input, 'direction', 500);
            if ($direction === '') {
                throw new DomainException('Hãy nhập hướng / lĩnh vực bạn quan tâm.');
            }
            ai_topic_suggest($user, $direction);
            $_SESSION['_open_dialog'] = 'ai-topics';
            return $done($user['role'] === 'lecturer' ? 'lecturer/topics' : 'student/thesis', 'AI đã gợi ý đề tài.');

        case 'topic_propose':
            $user = action_user(['student']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            if (!(int) $batch['allow_student_proposals']) {
                throw new DomainException('Đợt này không cho phép sinh viên đề xuất đề tài.');
            }
            batch_require_phase($batch, 'preference');
            if (!batch_student_eligible((int) $batch['id'], (int) $user['student_id'])) {
                throw new DomainException('Bạn chưa đủ điều kiện tham gia đợt này.');
            }
            $lecturer = page_one('SELECT id FROM lecturers WHERE id = ?', [thesis_int($input, 'lecturer_id')]);
            $title = action_value($input, 'title', 255);
            if (!$lecturer || $title === '') {
                throw new DomainException('Hãy nhập tên đề tài và chọn giảng viên hướng dẫn mong muốn.');
            }
            if (page_count('SELECT COUNT(*) FROM topics WHERE batch_id = ? AND proposed_by_student_id = ? AND status IN (\'pending\', \'revision\')', [(int) $batch['id'], (int) $user['student_id']]) > 0) {
                throw new DomainException('Bạn đang có một đề xuất chờ xét duyệt.');
            }
            $connection->prepare('INSERT INTO topics (batch_id, lecturer_id, proposed_by_student_id, title, description, objectives, technologies, field, max_students, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, \'pending\')')
                ->execute([(int) $batch['id'], (int) $lecturer['id'], (int) $user['student_id'], $title, action_value($input, 'description', 10000) ?: null, action_value($input, 'objectives', 5000) ?: null, action_value($input, 'technologies', 255) ?: null, action_value($input, 'field', 150) ?: null]);
            notify_lecturer((int) $lecturer['id'], 'topics', 'Sinh viên đề xuất đề tài', $user['full_name'] . ': ' . $title, 'lecturer/topics', true);
            return $done('student/thesis&batch=' . (int) $batch['id'], 'Đã gửi đề xuất đề tài cho giảng viên xét duyệt.');

        case 'lecturer_rank_save':
            $user = action_user(['lecturer']);
            $topic = page_one('SELECT * FROM topics WHERE id = ? AND lecturer_id = ?', [thesis_int($input, 'topic_id'), (int) $user['lecturer_id']]);
            if (!$topic) {
                throw new DomainException('Không tìm thấy đề tài của bạn.');
            }
            batch_require_unlocked(thesis_batch((int) $topic['batch_id']));
            $ranks = is_array($input['rank'] ?? null) ? $input['rank'] : [];
            $applicants = array_map('intval', array_column(page_all('SELECT student_id FROM topic_preferences WHERE topic_id = ?', [(int) $topic['id']]), 'student_id'));
            database_transaction(static function (PDO $connection) use ($topic, $ranks, $applicants): void {
                $connection->prepare('DELETE FROM lecturer_rankings WHERE topic_id = ?')->execute([(int) $topic['id']]);
                $insert = $connection->prepare('INSERT INTO lecturer_rankings (topic_id, student_id, rank_no) VALUES (?, ?, ?)');
                foreach ($ranks as $studentId => $rank) {
                    $rank = (int) (filter_var($rank, FILTER_VALIDATE_INT) ?: 0);
                    if ($rank > 0 && in_array((int) $studentId, $applicants, true)) {
                        $insert->execute([(int) $topic['id'], (int) $studentId, $rank]);
                    }
                }
            });
            return $done('lecturer/topics', 'Đã lưu thứ tự ưu tiên sinh viên cho đề tài "' . $topic['title'] . '".');

        // ----- Nguyện vọng và nhóm (sinh viên) -----
        case 'preference_save':
            $user = action_user(['student']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_phase($batch, 'preference');
            $studentId = (int) $user['student_id'];
            if (!batch_student_eligible((int) $batch['id'], $studentId)) {
                throw new DomainException('Bạn chưa đủ điều kiện tham gia đợt này.');
            }
            if (page_count('SELECT COUNT(*) FROM theses WHERE batch_id = ? AND student_id = ? AND (locked = 1 OR status IN (\'confirmed\', \'in_progress\', \'completed\'))', [(int) $batch['id'], $studentId]) > 0) {
                throw new DomainException('Bạn đã có đề tài được chốt trong đợt này.');
            }
            if (page_count('SELECT COUNT(*) FROM thesis_group_members m JOIN thesis_groups g ON g.id = m.group_id WHERE g.batch_id = ? AND m.student_id = ? AND m.status = \'accepted\'', [(int) $batch['id'], $studentId]) > 0) {
                throw new DomainException('Bạn là thành viên nhóm: nhóm trưởng đăng ký nguyện vọng cho cả nhóm.');
            }
            $chosen = array_values(array_filter(array_map('intval', is_array($input['pref'] ?? null) ? $input['pref'] : []), static fn($id) => $id > 0));
            if (count($chosen) !== count(array_unique($chosen))) {
                throw new DomainException('Mỗi đề tài chỉ chọn một lần trong danh sách nguyện vọng.');
            }
            if (count($chosen) > (int) $batch['max_preferences']) {
                throw new DomainException('Chỉ được đăng ký tối đa ' . (int) $batch['max_preferences'] . ' nguyện vọng.');
            }
            $valid = array_map('intval', array_column(page_all('SELECT id FROM topics WHERE batch_id = ? AND status = \'approved\'', [(int) $batch['id']]), 'id'));
            if (array_diff($chosen, $valid)) {
                throw new DomainException('Có đề tài không thuộc đợt hoặc chưa được duyệt.');
            }
            database_transaction(static function (PDO $connection) use ($batch, $studentId, $chosen): void {
                $connection->prepare('DELETE FROM topic_preferences WHERE batch_id = ? AND student_id = ?')->execute([(int) $batch['id'], $studentId]);
                $insert = $connection->prepare('INSERT INTO topic_preferences (batch_id, student_id, topic_id, rank_no) VALUES (?, ?, ?, ?)');
                foreach ($chosen as $index => $topicId) {
                    $insert->execute([(int) $batch['id'], $studentId, $topicId, $index + 1]);
                }
            });
            return $done('student/thesis&batch=' . (int) $batch['id'], $chosen ? 'Đã lưu ' . count($chosen) . ' nguyện vọng. Bạn có thể sửa đến khi hết hạn.' : 'Đã xóa toàn bộ nguyện vọng.');

        case 'group_create':
            $user = action_user(['student']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_phase($batch, 'preference');
            if ((int) $batch['group_max_size'] < 2) {
                throw new DomainException('Đợt này làm đồ án cá nhân, không đăng ký nhóm.');
            }
            $studentId = (int) $user['student_id'];
            if (page_count('SELECT COUNT(*) FROM thesis_groups g LEFT JOIN thesis_group_members m ON m.group_id = g.id AND m.student_id = ? AND m.status = \'accepted\' WHERE g.batch_id = ? AND (g.leader_student_id = ? OR m.student_id IS NOT NULL)', [$studentId, (int) $batch['id'], $studentId]) > 0) {
                throw new DomainException('Bạn đã thuộc một nhóm trong đợt này.');
            }
            $name = action_value($input, 'name', 150) ?: 'Nhóm của ' . $user['full_name'];
            $connection->prepare('INSERT INTO thesis_groups (batch_id, leader_student_id, name) VALUES (?, ?, ?)')->execute([(int) $batch['id'], $studentId, $name]);
            return $done('student/thesis&batch=' . (int) $batch['id'], 'Đã tạo nhóm. Hãy mời thành viên bằng mã sinh viên.');

        case 'group_invite':
            $user = action_user(['student']);
            $group = page_one('SELECT * FROM thesis_groups WHERE id = ? AND leader_student_id = ?', [thesis_int($input, 'group_id'), (int) $user['student_id']]);
            if (!$group) {
                throw new DomainException('Chỉ nhóm trưởng được mời thành viên.');
            }
            $batch = thesis_batch((int) $group['batch_id']);
            batch_require_phase($batch, 'preference');
            $target = page_one('SELECT s.id, s.user_id FROM students s WHERE s.student_code = ?', [action_value($input, 'student_code', 30)]);
            if (!$target || (int) $target['id'] === (int) $user['student_id']) {
                throw new DomainException('Không tìm thấy sinh viên với mã đã nhập.');
            }
            if (!batch_student_eligible((int) $batch['id'], (int) $target['id'])) {
                throw new DomainException('Sinh viên này chưa đủ điều kiện tham gia đợt.');
            }
            $size = 1 + page_count('SELECT COUNT(*) FROM thesis_group_members WHERE group_id = ? AND status IN (\'invited\', \'accepted\')', [(int) $group['id']]);
            if ($size >= (int) $batch['group_max_size']) {
                throw new DomainException('Nhóm đã đủ tối đa ' . (int) $batch['group_max_size'] . ' thành viên.');
            }
            $connection->prepare('INSERT INTO thesis_group_members (group_id, student_id, status) VALUES (?, ?, \'invited\') ON DUPLICATE KEY UPDATE status = \'invited\', responded_at = NULL')->execute([(int) $group['id'], (int) $target['id']]);
            notify_user((int) $target['user_id'], 'thesis', 'Lời mời tham gia nhóm đồ án', $user['full_name'] . ' mời bạn vào ' . $group['name'] . '.', 'student/thesis&batch=' . (int) $batch['id']);
            return $done('student/thesis&batch=' . (int) $batch['id'], 'Đã gửi lời mời. Thành viên cần xác nhận để vào nhóm.');

        case 'group_respond':
            $user = action_user(['student']);
            $group = page_one('SELECT g.* FROM thesis_groups g JOIN thesis_group_members m ON m.group_id = g.id WHERE g.id = ? AND m.student_id = ? AND m.status = \'invited\'', [thesis_int($input, 'group_id'), (int) $user['student_id']]);
            if (!$group) {
                throw new DomainException('Không tìm thấy lời mời.');
            }
            $batch = thesis_batch((int) $group['batch_id']);
            batch_require_phase($batch, 'preference');
            $accept = ($input['decision'] ?? '') === 'accept';
            if ($accept && page_count('SELECT COUNT(*) FROM thesis_groups g LEFT JOIN thesis_group_members m ON m.group_id = g.id AND m.student_id = ? AND m.status = \'accepted\' WHERE g.batch_id = ? AND (g.leader_student_id = ? OR m.student_id IS NOT NULL)', [(int) $user['student_id'], (int) $batch['id'], (int) $user['student_id']]) > 0) {
                throw new DomainException('Bạn đã thuộc một nhóm khác trong đợt này.');
            }
            database_transaction(static function (PDO $connection) use ($group, $user, $accept, $batch): void {
                $connection->prepare('UPDATE thesis_group_members SET status = ?, responded_at = NOW() WHERE group_id = ? AND student_id = ?')->execute([$accept ? 'accepted' : 'declined', (int) $group['id'], (int) $user['student_id']]);
                if ($accept) {
                    // Thành viên dùng nguyện vọng của nhóm trưởng nên bỏ nguyện vọng cá nhân.
                    $connection->prepare('DELETE FROM topic_preferences WHERE batch_id = ? AND student_id = ?')->execute([(int) $batch['id'], (int) $user['student_id']]);
                }
            });
            notify_student((int) $group['leader_student_id'], 'thesis', $user['full_name'] . ($accept ? ' đã vào nhóm' : ' từ chối lời mời'), $group['name'], 'student/thesis&batch=' . (int) $batch['id']);
            return $done('student/thesis&batch=' . (int) $batch['id'], $accept ? 'Bạn đã vào nhóm.' : 'Đã từ chối lời mời.');

        case 'group_leave':
            $user = action_user(['student']);
            $group = page_one('SELECT * FROM thesis_groups WHERE id = ?', [thesis_int($input, 'group_id')]);
            if (!$group) {
                throw new DomainException('Không tìm thấy nhóm.');
            }
            $batch = thesis_batch((int) $group['batch_id']);
            batch_require_phase($batch, 'preference');
            if (page_count('SELECT COUNT(*) FROM theses WHERE group_id = ?', [(int) $group['id']]) > 0) {
                throw new DomainException('Nhóm đã được ghép đề tài nên không thay đổi thành viên được.');
            }
            if ((int) $group['leader_student_id'] === (int) $user['student_id']) {
                $connection->prepare('DELETE FROM thesis_groups WHERE id = ?')->execute([(int) $group['id']]);
                return $done('student/thesis&batch=' . (int) $batch['id'], 'Đã giải tán nhóm.');
            }
            $connection->prepare('DELETE FROM thesis_group_members WHERE group_id = ? AND student_id = ?')->execute([(int) $group['id'], (int) $user['student_id']]);
            return $done('student/thesis&batch=' . (int) $batch['id'], 'Bạn đã rời nhóm.');

        case 'thesis_confirm':
            $user = action_user(['student']);
            $thesis = page_one('SELECT th.*, tp.title FROM theses th JOIN topics tp ON tp.id = th.topic_id WHERE th.id = ? AND th.student_id = ?', [thesis_int($input, 'thesis_id'), (int) $user['student_id']]);
            if (!$thesis || $thesis['status'] !== 'assigned') {
                throw new DomainException('Không có kết quả ghép nào cần xác nhận.');
            }
            batch_require_unlocked(thesis_batch((int) $thesis['batch_id']));
            $accept = ($input['decision'] ?? '') === 'confirm';
            $connection->prepare('UPDATE theses SET status = ?, student_confirmed_at = ' . ($accept ? 'NOW()' : 'NULL') . ' WHERE id = ?')->execute([$accept ? 'confirmed' : 'declined', (int) $thesis['id']]);
            if ($thesis['supervisor_id']) {
                notify_lecturer((int) $thesis['supervisor_id'], 'theses', $user['full_name'] . ($accept ? ' xác nhận đề tài' : ' từ chối đề tài'), $thesis['title'], 'lecturer/theses');
            }
            return $done('student/thesis&batch=' . (int) $thesis['batch_id'], $accept ? 'Đã xác nhận đề tài. Chúc bạn thực hiện đồ án thành công!' : 'Đã từ chối. Bạn sẽ tham gia vòng ghép bổ sung.');

        // ----- Quá trình thực hiện: mốc công việc, lịch gặp, bài nộp, góp ý -----
        case 'milestone_save':
            $user = action_user(['lecturer', 'student']);
            $thesis = thesis_access(thesis_int($input, 'thesis_id'), $user);
            if (!$thesis['is_supervisor'] && !$thesis['is_student']) {
                throw new DomainException('Chỉ sinh viên và giảng viên hướng dẫn lập kế hoạch đồ án.');
            }
            batch_require_unlocked($thesis);
            $title = action_value($input, 'title', 200);
            if ($title === '') {
                throw new DomainException('Hãy nhập tên mốc công việc.');
            }
            $connection->prepare('INSERT INTO thesis_milestones (thesis_id, title, description, due_date, created_by) VALUES (?, ?, ?, ?, ?)')->execute([(int) $thesis['id'], $title, action_value($input, 'description', 2000) ?: null, action_value($input, 'due_date', 10) ?: null, (int) $user['id']]);
            return $done($user['role'] . '/' . ($user['role'] === 'student' ? 'thesis&batch=' . (int) $thesis['batch_id'] : 'theses'), 'Đã thêm mốc công việc.');

        case 'milestone_toggle':
            $user = action_user(['lecturer', 'student']);
            $milestone = page_one('SELECT * FROM thesis_milestones WHERE id = ?', [thesis_int($input, 'milestone_id')]);
            if (!$milestone) {
                throw new DomainException('Không tìm thấy mốc công việc.');
            }
            $thesis = thesis_access((int) $milestone['thesis_id'], $user);
            batch_require_unlocked($thesis);
            $isDone = $milestone['status'] !== 'done';
            $connection->prepare('UPDATE thesis_milestones SET status = ?, completed_at = ' . ($isDone ? 'NOW()' : 'NULL') . ' WHERE id = ?')->execute([$isDone ? 'done' : 'todo', (int) $milestone['id']]);
            return $done($user['role'] === 'student' ? 'student/thesis&batch=' . (int) $thesis['batch_id'] : 'lecturer/theses', $isDone ? 'Đã đánh dấu hoàn thành.' : 'Đã mở lại mốc công việc.');

        case 'meeting_save':
            $user = action_user(['lecturer', 'student']);
            $thesis = thesis_access(thesis_int($input, 'thesis_id'), $user);
            if (!$thesis['is_supervisor'] && !$thesis['is_student']) {
                throw new DomainException('Chỉ sinh viên và giảng viên hướng dẫn đặt lịch gặp.');
            }
            batch_require_unlocked($thesis);
            $at = thesis_datetime(action_value($input, 'meeting_at', 20));
            if ($at === null) {
                throw new DomainException('Hãy chọn thời gian gặp.');
            }
            $connection->prepare('INSERT INTO thesis_meetings (thesis_id, meeting_at, location, agenda, created_by) VALUES (?, ?, ?, ?, ?)')->execute([(int) $thesis['id'], $at, action_value($input, 'location', 200) ?: null, action_value($input, 'agenda', 2000) ?: null, (int) $user['id']]);
            if ($thesis['is_student'] && $thesis['supervisor_id']) {
                notify_lecturer((int) $thesis['supervisor_id'], 'theses', 'Sinh viên đặt lịch gặp hướng dẫn', $thesis['student_name'] . ' · ' . thesis_datetime_label($at), 'lecturer/theses');
            } else {
                notify_user((int) $thesis['student_user_id'], 'thesis', 'Giảng viên đặt lịch gặp hướng dẫn', thesis_datetime_label($at) . ' · ' . action_value($input, 'location', 200), 'student/thesis&batch=' . (int) $thesis['batch_id'], true);
            }
            return $done($user['role'] === 'student' ? 'student/thesis&batch=' . (int) $thesis['batch_id'] : 'lecturer/theses', 'Đã đặt lịch gặp.');

        case 'meeting_confirm':
            $user = action_user(['lecturer']);
            $meeting = page_one('SELECT * FROM thesis_meetings WHERE id = ?', [thesis_int($input, 'meeting_id')]);
            if (!$meeting) {
                throw new DomainException('Không tìm thấy lịch gặp.');
            }
            $thesis = thesis_access((int) $meeting['thesis_id'], $user);
            if (!$thesis['is_supervisor']) {
                throw new DomainException('Chỉ giảng viên hướng dẫn xác nhận buổi gặp.');
            }
            batch_require_unlocked($thesis);
            $status = in_array($input['status'] ?? '', ['held', 'cancelled'], true) ? $input['status'] : 'held';
            $connection->prepare('UPDATE thesis_meetings SET status = ?, notes = ?, confirmed_at = ' . ($status === 'held' ? 'NOW()' : 'NULL') . ' WHERE id = ?')->execute([$status, action_value($input, 'notes', 5000) ?: null, (int) $meeting['id']]);
            return $done('lecturer/theses', $status === 'held' ? 'Đã xác nhận buổi hướng dẫn và lưu biên bản.' : 'Đã hủy buổi gặp.');

        case 'submission_upload':
            $user = action_user(['student']);
            $thesis = thesis_access(thesis_int($input, 'thesis_id'), $user);
            if (!in_array($thesis['status'], ['assigned', 'confirmed', 'in_progress'], true)) {
                throw new DomainException('Đồ án chưa được xác nhận hoặc đã kết thúc.');
            }
            $batch = thesis_batch((int) $thesis['batch_id']);
            batch_require_unlocked($batch);
            $stage = action_value($input, 'stage', 30);
            if (!isset(thesis_stages((int) $thesis['batch_id'])[$stage])) {
                throw new DomainException('Giai đoạn nộp không hợp lệ.');
            }
            $late = false;
            if ($stage !== 'other') {
                $window = batch_window((int) $batch['id'], $stage);
                if ($window && $window['starts_at'] && time() < strtotime((string) $window['starts_at'])) {
                    throw new DomainException('Chưa đến thời gian nộp ' . mb_strtolower(thesis_stages((int) $thesis['batch_id'])[$stage], 'UTF-8') . ' (mở từ ' . thesis_datetime_label($window['starts_at']) . ').');
                }
                $late = $window && $window['ends_at'] && time() > strtotime((string) $window['ends_at']);
            }
            $file = $files['file'] ?? [];
            $path = store_uploaded_file($file, 'theses', 20 * 1024 * 1024);
            if ($path === null) {
                throw new DomainException('Hãy chọn tệp cần nộp.');
            }
            $ids = thesis_related_ids($thesis);
            $version = 1 + page_count('SELECT COUNT(*) FROM thesis_submissions WHERE stage = ? AND thesis_id IN (' . thesis_in($ids) . ')', array_merge([$stage], $ids));
            $connection->prepare('INSERT INTO thesis_submissions (thesis_id, stage, version, file_path, file_name, file_size, note, is_late, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $thesis['id'], $stage, $version, $path, mb_substr((string) ($file['name'] ?? 'bai-nop'), 0, 255, 'UTF-8'), (int) ($file['size'] ?? 0), action_value($input, 'note', 2000) ?: null, $late ? 1 : 0, (int) $user['id']]);
            // Kiểm tra trùng lặp với bài nộp của các đồ án khác; vượt ngưỡng thì báo giảng viên hướng dẫn.
            $similarity = similarity_check_submission((int) $connection->lastInsertId());
            if ($similarity && $similarity['score'] >= SIMILARITY_WARN && $thesis['supervisor_id']) {
                notify_lecturer((int) $thesis['supervisor_id'], 'theses', 'Bài nộp trùng lặp ' . round($similarity['score']) . '%', $thesis['student_name'] . ' · ' . thesis_stages((int) $thesis['batch_id'])[$stage], 'lecturer/theses');
            }
            if ($thesis['status'] === 'confirmed') {
                $connection->prepare('UPDATE theses SET status = \'in_progress\' WHERE id IN (' . thesis_in($ids) . ')')->execute($ids);
            }
            if ($thesis['supervisor_id']) {
                notify_lecturer((int) $thesis['supervisor_id'], 'theses', 'Bài nộp mới: ' . thesis_stages((int) $thesis['batch_id'])[$stage] . ' (bản ' . $version . ')', $thesis['student_name'] . ($late ? ' · nộp trễ hạn' : ''), 'lecturer/theses');
            }
            return $done('student/thesis&batch=' . (int) $thesis['batch_id'], 'Đã nộp ' . mb_strtolower(thesis_stages((int) $thesis['batch_id'])[$stage], 'UTF-8') . ' · phiên bản ' . $version . ($late ? ' (nộp trễ hạn, đã được ghi nhận).' : '.'));

        case 'submission_review':
            $user = action_user(['lecturer']);
            $submission = page_one('SELECT * FROM thesis_submissions WHERE id = ?', [thesis_int($input, 'submission_id')]);
            if (!$submission) {
                throw new DomainException('Không tìm thấy bài nộp.');
            }
            $thesis = thesis_access((int) $submission['thesis_id'], $user);
            if (!$thesis['is_supervisor']) {
                throw new DomainException('Chỉ giảng viên hướng dẫn nhận xét bài nộp.');
            }
            $status = ($input['status'] ?? '') === 'revision' ? 'revision' : 'reviewed';
            $connection->prepare('UPDATE thesis_submissions SET status = ? WHERE id = ?')->execute([$status, (int) $submission['id']]);
            $comment = action_value($input, 'comment', 5000);
            if ($comment !== '') {
                $connection->prepare('INSERT INTO submission_comments (submission_id, user_id, body) VALUES (?, ?, ?)')->execute([(int) $submission['id'], (int) $user['id'], $comment]);
            }
            notify_user((int) $thesis['student_user_id'], 'thesis', $status === 'revision' ? 'Giảng viên yêu cầu chỉnh sửa bài nộp' : 'Giảng viên đã xem bài nộp', THESIS_STAGES[$submission['stage']] . ' · bản ' . $submission['version'], 'student/thesis&batch=' . (int) $thesis['batch_id']);
            return $done('lecturer/theses', 'Đã cập nhật trạng thái bài nộp.');

        case 'submission_comment':
            $user = action_user(['lecturer', 'student', 'admin']);
            $submission = page_one('SELECT * FROM thesis_submissions WHERE id = ?', [thesis_int($input, 'submission_id')]);
            if (!$submission) {
                throw new DomainException('Không tìm thấy bài nộp.');
            }
            $thesis = thesis_access((int) $submission['thesis_id'], $user);
            $body = action_value($input, 'body', 5000);
            if ($body === '') {
                throw new DomainException('Hãy nhập nội dung góp ý.');
            }
            $connection->prepare('INSERT INTO submission_comments (submission_id, user_id, body) VALUES (?, ?, ?)')->execute([(int) $submission['id'], (int) $user['id'], $body]);
            if ($user['role'] === 'student' && $thesis['supervisor_id']) {
                notify_lecturer((int) $thesis['supervisor_id'], 'theses', 'Sinh viên trao đổi trên bài nộp', $thesis['student_name'] . ': ' . mb_strimwidth($body, 0, 120, '…', 'UTF-8'), 'lecturer/theses');
            } elseif ($user['role'] !== 'student') {
                notify_user((int) $thesis['student_user_id'], 'thesis', 'Góp ý mới trên bài nộp', mb_strimwidth($body, 0, 160, '…', 'UTF-8'), 'student/thesis&batch=' . (int) $thesis['batch_id']);
            }
            return $done($user['role'] === 'student' ? 'student/thesis&batch=' . (int) $thesis['batch_id'] : ($user['role'] === 'admin' ? 'admin/theses' : ((string) ($input['return'] ?? '') === 'defense' ? 'lecturer/defense' : 'lecturer/theses')), 'Đã gửi góp ý.');

        // Giảng viên hướng dẫn chấm điểm từng giai đoạn (thang 10) kèm nhận xét; sinh viên xem được ngay.
        case 'stage_score_save':
            $user = action_user(['lecturer']);
            $thesis = thesis_access(thesis_int($input, 'thesis_id'), $user);
            if (!$thesis['is_supervisor']) {
                throw new DomainException('Chỉ giảng viên hướng dẫn chấm điểm giai đoạn.');
            }
            $batch = thesis_batch((int) $thesis['batch_id']);
            batch_require_unlocked($batch);
            $stage = action_value($input, 'stage', 30);
            if (!isset(thesis_stages((int) $batch['id'])[$stage]) || $stage === 'other') {
                throw new DomainException('Giai đoạn không hợp lệ.');
            }
            $score = filter_var(str_replace(',', '.', (string) ($input['score'] ?? '')), FILTER_VALIDATE_FLOAT);
            if ($score === false || $score < 0 || $score > 10) {
                throw new DomainException('Điểm giai đoạn từ 0 đến 10.');
            }
            foreach (thesis_related_ids($thesis) as $relatedId) {
                $connection->prepare('INSERT INTO thesis_stage_scores (thesis_id, stage, score, comments, scored_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), comments = VALUES(comments), scored_by = VALUES(scored_by)')
                    ->execute([$relatedId, $stage, round($score, 2), action_value($input, 'comments', 2000) ?: null, (int) $user['id']]);
            }
            notify_user((int) $thesis['student_user_id'], 'thesis', 'Điểm ' . mb_strtolower(thesis_stages((int) $batch['id'])[$stage], 'UTF-8') . ': ' . round($score, 2), action_value($input, 'comments', 2000), 'student/thesis');
            return $done('lecturer/theses', 'Đã lưu điểm giai đoạn.');

        // ----- Chấm điểm theo rubric -----
        case 'thesis_score_save':
            $user = action_user(['lecturer']);
            $thesis = thesis_access(thesis_int($input, 'thesis_id'), $user);
            $role = action_value($input, 'evaluator_role', 20);
            if (!isset(THESIS_ROLE_LABELS[$role]) || empty($thesis['is_' . $role])) {
                throw new DomainException('Bạn không được phân công chấm đồ án này với vai trò đã chọn.');
            }
            $batch = thesis_batch((int) $thesis['batch_id']);
            batch_require_unlocked($batch);
            if ($batch['results_published_at'] && page_count('SELECT COUNT(*) FROM regrade_requests WHERE thesis_id = ? AND status = \'accepted\'', [(int) $thesis['id']]) === 0) {
                throw new DomainException('Điểm đã công bố. Chỉ chấm lại khi có yêu cầu phúc khảo được chấp nhận.');
            }
            $rubric = rubric_with_criteria($batch[$role . '_rubric_id'] !== null ? (int) $batch[$role . '_rubric_id'] : null);
            $items = [];
            if ($rubric && $rubric['criteria']) {
                $raw = is_array($input['scores'] ?? null) ? $input['scores'] : [];
                foreach ($rubric['criteria'] as $criterion) {
                    $value = filter_var($raw[(int) $criterion['id']] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($value === false || $value < 0 || $value > (float) $criterion['max_score']) {
                        throw new DomainException('Điểm tiêu chí "' . $criterion['name'] . '" phải từ 0 đến ' . (float) $criterion['max_score'] . '.');
                    }
                    $items[(int) $criterion['id']] = (float) $value;
                }
                $total = rubric_total($rubric, $items);
            } else {
                $value = filter_var($input['total'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($value === false || $value < 0 || $value > 10) {
                    throw new DomainException('Điểm phải từ 0 đến 10.');
                }
                $total = round((float) $value, 2);
            }
            $status = ($input['status'] ?? '') === 'draft' ? 'draft' : 'submitted';
            database_transaction(static function (PDO $connection) use ($thesis, $user, $role, $rubric, $total, $status, $items, $input): void {
                $connection->prepare('INSERT INTO thesis_scores (thesis_id, evaluator_user_id, evaluator_role, rubric_id, total, comments, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ' . ($status === 'submitted' ? 'NOW()' : 'NULL') . ')
                     ON DUPLICATE KEY UPDATE rubric_id = VALUES(rubric_id), total = VALUES(total), comments = VALUES(comments), status = VALUES(status), submitted_at = VALUES(submitted_at)')
                    ->execute([(int) $thesis['id'], (int) $user['id'], $role, $rubric['id'] ?? null, $total, action_value($input, 'comments', 5000) ?: null, $status]);
                $scoreId = (int) (page_one('SELECT id FROM thesis_scores WHERE thesis_id = ? AND evaluator_user_id = ? AND evaluator_role = ?', [(int) $thesis['id'], (int) $user['id'], $role])['id'] ?? 0);
                $connection->prepare('DELETE FROM thesis_score_items WHERE score_id = ?')->execute([$scoreId]);
                $insert = $connection->prepare('INSERT INTO thesis_score_items (score_id, criterion_id, score) VALUES (?, ?, ?)');
                foreach ($items as $criterionId => $score) {
                    $insert->execute([$scoreId, $criterionId, $score]);
                }
            });
            $final = thesis_compute_final((int) $thesis['id']);
            if ($batch['results_published_at'] && $status === 'submitted' && $final !== null) {
                notify_user((int) $thesis['student_user_id'], 'thesis', 'Điểm đồ án đã được cập nhật sau phúc khảo', 'Điểm mới: ' . $final . '/10', 'student/thesis&batch=' . (int) $thesis['batch_id'], true);
            }
            return $done($role === 'supervisor' ? 'lecturer/theses' : 'lecturer/defense', ($status === 'draft' ? 'Đã lưu nháp phiếu chấm · ' : 'Đã gửi phiếu chấm ' . mb_strtolower(THESIS_ROLE_LABELS[$role], 'UTF-8') . ' · ') . $total . '/10.');

        // ----- Ghép cặp (quản trị) -----
        case 'matching_run':
        case 'matching_compare':
            $user = action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $params = matching_params($input);
            if ($action === 'matching_compare') {
                $summary = [];
                foreach (array_keys(MATCHING_ALGORITHMS) as $algorithm) {
                    $runId = matching_run((int) $batch['id'], $algorithm, $params, (int) $user['id']);
                    $metrics = json_decode((string) (page_one('SELECT metrics FROM matching_runs WHERE id = ?', [$runId])['metrics'] ?? '{}'), true) ?: [];
                    $summary[] = MATCHING_ALGORITHMS[$algorithm] . ': ' . ($metrics['rank_rate']['1'] ?? 0) . '% NV1, ' . ($metrics['blocking_pairs'] ?? 0) . ' cặp bất ổn';
                }
                return $done('admin/matching&batch=' . (int) $batch['id'], 'Đã chạy thử 4 thuật toán. ' . implode(' · ', $summary) . '.');
            }
            $runId = matching_run((int) $batch['id'], action_value($input, 'algorithm', 40), $params, (int) $user['id']);
            return $done('admin/matching&batch=' . (int) $batch['id'] . '&run=' . $runId, 'Đã chạy thử. Kết quả chưa công bố: hãy xem chỉ số và bảng kết quả trước khi công bố.');

        case 'matching_publish':
            action_user(['admin']);
            $run = page_one('SELECT * FROM matching_runs WHERE id = ?', [thesis_int($input, 'run_id')]);
            if (!$run) {
                throw new DomainException('Không tìm thấy lần chạy.');
            }
            batch_require_unlocked(thesis_batch((int) $run['batch_id']));
            $count = matching_publish((int) $run['id']);
            return $done('admin/matching&batch=' . (int) $run['batch_id'] . '&run=' . (int) $run['id'], 'Đã công bố kết quả cho ' . $count . ' sinh viên. Sinh viên sẽ xác nhận trong mục Đồ án.');

        case 'matching_discard':
            action_user(['admin']);
            $run = page_one('SELECT * FROM matching_runs WHERE id = ? AND status = \'preview\'', [thesis_int($input, 'run_id')]);
            if (!$run) {
                throw new DomainException('Chỉ bỏ được lần chạy chưa công bố.');
            }
            $connection->prepare('UPDATE matching_runs SET status = \'discarded\' WHERE id = ?')->execute([(int) $run['id']]);
            return $done('admin/matching&batch=' . (int) $run['batch_id'], 'Đã bỏ lần chạy thử.');

        // Sửa trực tiếp kết quả chạy thử (chọn đề tài khác hoặc kéo thả) trước khi công bố.
        case 'matching_run_edit':
            action_user(['admin']);
            $run = page_one('SELECT * FROM matching_runs WHERE id = ? AND status = \'preview\'', [thesis_int($input, 'run_id')]);
            if (!$run) {
                throw new DomainException('Chỉ sửa được lần chạy chưa công bố.');
            }
            $batch = thesis_batch((int) $run['batch_id']);
            batch_require_unlocked($batch);
            $result = json_decode((string) $run['result'], true) ?: [];
            $key = action_value($input, 'key', 30);
            $topicId = thesis_int($input, 'topic_id') ?: null;
            $index = array_search($key, array_column($result, 'key'), true);
            if ($index === false) {
                throw new DomainException('Không tìm thấy sinh viên / nhóm trong lần chạy.');
            }
            if ($topicId !== null) {
                $topic = page_one('SELECT id, title, max_students FROM topics WHERE id = ? AND batch_id = ? AND status = \'approved\'', [$topicId, (int) $batch['id']]);
                if (!$topic) {
                    throw new DomainException('Đề tài không thuộc đợt hoặc chưa được duyệt.');
                }
                // Sức chứa: sinh viên đã chốt của đề tài + sinh viên đang xếp vào đề tài trong lần chạy này.
                $taken = page_count('SELECT COUNT(*) FROM theses WHERE topic_id = ? AND (locked = 1 OR status IN (\'confirmed\', \'in_progress\', \'completed\'))', [$topicId]);
                foreach ($result as $position => $row) {
                    if ($position !== $index && (int) ($row['topic_id'] ?? 0) === $topicId) {
                        $taken += count($row['student_ids']);
                    }
                }
                if ($taken + count($result[$index]['student_ids']) > (int) $topic['max_students']) {
                    throw new DomainException('Đề tài "' . $topic['title'] . '" đã đủ ' . (int) $topic['max_students'] . ' chỗ.');
                }
            }
            $leader = (int) ($result[$index]['student_ids'][0] ?? 0);
            $rank = $topicId ? page_one('SELECT rank_no FROM topic_preferences WHERE batch_id = ? AND student_id = ? AND topic_id = ?', [(int) $batch['id'], $leader, $topicId]) : null;
            $result[$index]['topic_id'] = $topicId;
            $result[$index]['rank'] = $rank ? (int) $rank['rank_no'] : null;
            $result[$index]['auto'] = false;
            $result[$index]['manual'] = true;
            // Cập nhật lại các chỉ số chính của lần chạy.
            $metrics = json_decode((string) $run['metrics'], true) ?: [];
            $students = array_sum(array_map(static fn($row) => count($row['student_ids']), $result));
            $matchedRows = array_filter($result, static fn($row) => !empty($row['topic_id']));
            $metrics['matched'] = array_sum(array_map(static fn($row) => count($row['student_ids']), $matchedRows));
            $metrics['unmatched'] = $students - $metrics['matched'];
            foreach (['1', '2', '3'] as $rankKey) {
                $metrics['rank_rate'][$rankKey] = $students ? round(array_sum(array_map(static fn($row) => (string) ($row['rank'] ?? '') === $rankKey ? count($row['student_ids']) : 0, $result)) * 100 / $students, 1) : 0;
            }
            $metrics['edited'] = true;
            $connection->prepare('UPDATE matching_runs SET result = ?, metrics = ? WHERE id = ?')->execute([json_encode($result, JSON_UNESCAPED_UNICODE), json_encode($metrics, JSON_UNESCAPED_UNICODE), (int) $run['id']]);
            return $done('admin/matching&batch=' . (int) $batch['id'] . '&run=' . (int) $run['id'], 'Đã sửa kết quả chạy thử.');

        case 'thesis_assign_manual':
            action_user(['admin']);
            $topic = page_one('SELECT * FROM topics WHERE id = ? AND status = \'approved\'', [thesis_int($input, 'topic_id')]);
            $student = page_one('SELECT id FROM students WHERE id = ?', [thesis_int($input, 'student_id')]);
            if (!$topic || !$student) {
                throw new DomainException('Hãy chọn sinh viên và đề tài đã duyệt.');
            }
            batch_require_unlocked(thesis_batch((int) $topic['batch_id']));
            $assigned = page_count('SELECT COUNT(*) FROM theses WHERE topic_id = ? AND status <> \'declined\' AND student_id <> ?', [(int) $topic['id'], (int) $student['id']]);
            if ($assigned >= (int) $topic['max_students']) {
                throw new DomainException('Đề tài đã đủ ' . (int) $topic['max_students'] . ' sinh viên.');
            }
            $connection->prepare('INSERT INTO theses (batch_id, student_id, topic_id, supervisor_id, source, locked, status) VALUES (?, ?, ?, ?, \'manual\', ?, \'assigned\')
                 ON DUPLICATE KEY UPDATE topic_id = VALUES(topic_id), supervisor_id = VALUES(supervisor_id), source = \'manual\', locked = VALUES(locked), status = \'assigned\', matched_rank = NULL, student_confirmed_at = NULL')
                ->execute([(int) $topic['batch_id'], (int) $student['id'], (int) $topic['id'], $topic['lecturer_id'], !empty($input['locked']) ? 1 : 0]);
            notify_student((int) $student['id'], 'thesis', 'Bạn được phân công đề tài', $topic['title'], 'student/thesis&batch=' . (int) $topic['batch_id'], true);
            return $done('admin/matching&batch=' . (int) $topic['batch_id'], 'Đã ghép thủ công.');

        case 'thesis_lock':
            action_user(['admin']);
            $thesis = page_one('SELECT * FROM theses WHERE id = ?', [thesis_int($input, 'thesis_id')]);
            if (!$thesis) {
                throw new DomainException('Không tìm thấy cặp ghép.');
            }
            batch_require_unlocked(thesis_batch((int) $thesis['batch_id']));
            $connection->prepare('UPDATE theses SET locked = 1 - locked WHERE id = ?')->execute([(int) $thesis['id']]);
            return $done('admin/matching&batch=' . (int) $thesis['batch_id'], (int) $thesis['locked'] ? 'Đã mở khóa cặp ghép.' : 'Đã khóa cặp ghép: lần chạy sau sẽ giữ nguyên cặp này.');

        case 'thesis_unassign':
            action_user(['admin']);
            $thesis = page_one('SELECT * FROM theses WHERE id = ?', [thesis_int($input, 'thesis_id')]);
            if (!$thesis) {
                throw new DomainException('Không tìm thấy cặp ghép.');
            }
            batch_require_unlocked(thesis_batch((int) $thesis['batch_id']));
            if (page_count('SELECT COUNT(*) FROM thesis_submissions WHERE thesis_id = ?', [(int) $thesis['id']]) > 0) {
                throw new DomainException('Sinh viên đã nộp bài cho đồ án này nên không gỡ ghép được.');
            }
            $connection->prepare('DELETE FROM theses WHERE id = ?')->execute([(int) $thesis['id']]);
            return $done('admin/matching&batch=' . (int) $thesis['batch_id'], 'Đã gỡ cặp ghép.');

        // ----- Phân công phản biện, hội đồng, lịch bảo vệ -----
        case 'thesis_staff_update':
            action_user(['admin']);
            $thesis = page_one('SELECT * FROM theses WHERE id = ?', [thesis_int($input, 'thesis_id')]);
            if (!$thesis) {
                throw new DomainException('Không tìm thấy đồ án.');
            }
            batch_require_unlocked(thesis_batch((int) $thesis['batch_id']));
            $supervisorId = thesis_int($input, 'supervisor_id') ?: null;
            $reviewerId = thesis_int($input, 'reviewer_id') ?: null;
            $councilId = thesis_int($input, 'council_id') ?: null;
            if ($reviewerId && $reviewerId === $supervisorId) {
                throw new DomainException('Xung đột lợi ích: người phản biện không được trùng người hướng dẫn.');
            }
            if ($councilId && $supervisorId && page_count('SELECT COUNT(*) FROM council_members WHERE council_id = ? AND lecturer_id = ?', [$councilId, $supervisorId]) > 0) {
                throw new DomainException('Xung đột lợi ích: người hướng dẫn đang là thành viên hội đồng được chọn.');
            }
            $connection->prepare('UPDATE theses SET supervisor_id = ?, reviewer_id = ?, council_id = ?, defense_at = ? WHERE id = ?')->execute([$supervisorId, $reviewerId, $councilId, thesis_datetime(action_value($input, 'defense_at', 20)), (int) $thesis['id']]);
            return $done('admin/theses&batch=' . (int) $thesis['batch_id'], 'Đã cập nhật phân công.');

        // Tự phân công phản biện: chọn giảng viên khác người hướng dẫn, ưu tiên khớp chuyên môn và ít việc nhất.
        case 'reviewer_auto':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $lecturers = thesis_lecturers();
            $load = [];
            foreach (page_all('SELECT reviewer_id, COUNT(*) AS total FROM theses WHERE batch_id = ? AND reviewer_id IS NOT NULL GROUP BY reviewer_id', [(int) $batch['id']]) as $row) {
                $load[(int) $row['reviewer_id']] = (int) $row['total'];
            }
            $assigned = 0;
            foreach (page_all('SELECT th.id, th.supervisor_id, tp.title, tp.field FROM theses th JOIN topics tp ON tp.id = th.topic_id WHERE th.batch_id = ? AND th.reviewer_id IS NULL AND th.status <> \'declined\'', [(int) $batch['id']]) as $thesis) {
                $topicTerms = array_unique(page_recommendation_tokens($thesis['title'] . ' ' . $thesis['field']));
                $best = null;
                $bestScore = -INF;
                foreach ($lecturers as $lecturer) {
                    if ((int) $lecturer['id'] === (int) $thesis['supervisor_id'] || ($load[(int) $lecturer['id']] ?? 0) >= (int) $lecturer['max_reviews']) {
                        continue;
                    }
                    $expertise = count(array_intersect($topicTerms, array_unique(page_recommendation_tokens((string) $lecturer['research_areas'] . ' ' . $lecturer['department']))));
                    $score = $expertise * 2 - ($load[(int) $lecturer['id']] ?? 0);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = (int) $lecturer['id'];
                    }
                }
                if ($best) {
                    $connection->prepare('UPDATE theses SET reviewer_id = ? WHERE id = ?')->execute([$best, (int) $thesis['id']]);
                    $load[$best] = ($load[$best] ?? 0) + 1;
                    $assigned++;
                }
            }
            return $done('admin/theses&batch=' . (int) $batch['id'], 'Đã tự phân công phản biện cho ' . $assigned . ' đồ án (tránh trùng người hướng dẫn, không vượt hạn mức phản biện, ưu tiên đúng chuyên môn).');

        // Tự xếp hội đồng: mỗi đồ án vào hội đồng không có người hướng dẫn của nó và đang ít đồ án nhất.
        case 'council_auto':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $councils = page_all('SELECT id FROM councils WHERE batch_id = ?', [(int) $batch['id']]);
            if (!$councils) {
                throw new DomainException('Hãy tạo hội đồng trước.');
            }
            $members = [];
            $count = [];
            $expertise = [];
            foreach ($councils as $council) {
                $rows = page_all('SELECT cm.lecturer_id, CONCAT_WS(\' \', l.research_areas, l.specialization, l.department) AS areas FROM council_members cm JOIN lecturers l ON l.id = cm.lecturer_id WHERE cm.council_id = ?', [(int) $council['id']]);
                $members[(int) $council['id']] = array_map('intval', array_column($rows, 'lecturer_id'));
                $expertise[(int) $council['id']] = array_unique(page_recommendation_tokens(implode(' ', array_column($rows, 'areas'))));
                $count[(int) $council['id']] = page_count('SELECT COUNT(*) FROM theses WHERE council_id = ?', [(int) $council['id']]);
            }
            $placed = 0;
            $skipped = 0;
            foreach (page_all('SELECT th.id, th.supervisor_id, tp.title, tp.field, tp.technologies FROM theses th JOIN topics tp ON tp.id = th.topic_id WHERE th.batch_id = ? AND th.council_id IS NULL AND th.status <> \'declined\'', [(int) $batch['id']]) as $thesis) {
                $options = array_filter(array_keys($members), static fn($councilId) => !in_array((int) $thesis['supervisor_id'], $members[$councilId], true));
                if (!$options) {
                    $skipped++;
                    continue;
                }
                // Ưu tiên hội đồng có chuyên môn gần đề tài nhất, sau đó hội đồng đang ít đồ án.
                $topicTerms = array_unique(page_recommendation_tokens($thesis['title'] . ' ' . $thesis['field'] . ' ' . $thesis['technologies']));
                $score = static fn($councilId) => count(array_intersect($topicTerms, $expertise[$councilId])) * 2 - $count[$councilId];
                usort($options, static fn($a, $b) => $score($b) <=> $score($a));
                $connection->prepare('UPDATE theses SET council_id = ? WHERE id = ?')->execute([$options[0], (int) $thesis['id']]);
                $count[$options[0]]++;
                $placed++;
            }
            return $done('admin/theses&batch=' . (int) $batch['id'], 'Đã xếp ' . $placed . ' đồ án vào hội đồng theo chuyên môn' . ($skipped ? '; ' . $skipped . ' đồ án không xếp được vì người hướng dẫn có mặt ở mọi hội đồng.' : '.'));

        case 'defense_schedule_auto':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $minutes = max(15, min(120, thesis_int($input, 'slot_minutes') ?: 45));
            $scheduled = 0;
            foreach (page_all('SELECT * FROM councils WHERE batch_id = ? AND defense_date IS NOT NULL', [(int) $batch['id']]) as $council) {
                $time = strtotime($council['defense_date'] . ' ' . ($council['start_time'] ?: '07:30:00'));
                foreach (page_all('SELECT th.id FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id WHERE th.council_id = ? AND th.status <> \'declined\' ORDER BY th.group_id, u.full_name', [(int) $council['id']]) as $thesis) {
                    // Lượt nào kết thúc sau 11:30 thì dời sang 13:30 (nghỉ trưa).
                    $noon = strtotime(date('Y-m-d', $time) . ' 11:30:00');
                    if ($time < $noon && $time + $minutes * 60 > $noon) {
                        $time = strtotime(date('Y-m-d', $time) . ' 13:30:00');
                    }
                    $connection->prepare('UPDATE theses SET defense_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', $time), (int) $thesis['id']]);
                    $time += $minutes * 60;
                    $scheduled++;
                }
            }
            return $done('admin/theses&batch=' . (int) $batch['id'], 'Đã xếp lịch bảo vệ cho ' . $scheduled . ' đồ án (' . $minutes . ' phút/lượt).');

        case 'council_save':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $name = action_value($input, 'name', 150);
            $chair = thesis_int($input, 'chair_id');
            $secretary = thesis_int($input, 'secretary_id');
            $others = array_values(array_filter(array_map('intval', is_array($input['member_ids'] ?? null) ? $input['member_ids'] : []), static fn($id) => $id > 0 && $id !== $chair && $id !== $secretary));
            if ($name === '' || !$chair || !$secretary || $chair === $secretary) {
                throw new DomainException('Hội đồng cần tên, chủ tịch và thư ký là hai giảng viên khác nhau.');
            }
            $councilId = thesis_int($input, 'council_id');
            database_transaction(static function (PDO $connection) use ($batch, $name, $input, $chair, $secretary, $others, &$councilId): void {
                $values = [$name, action_value($input, 'room', 100) ?: null, action_value($input, 'defense_date', 10) ?: null, action_value($input, 'start_time', 8) ?: null];
                if ($councilId) {
                    $connection->prepare('UPDATE councils SET name = ?, room = ?, defense_date = ?, start_time = ? WHERE id = ? AND batch_id = ?')->execute(array_merge($values, [$councilId, (int) $batch['id']]));
                } else {
                    $connection->prepare('INSERT INTO councils (name, room, defense_date, start_time, batch_id) VALUES (?, ?, ?, ?, ?)')->execute(array_merge($values, [(int) $batch['id']]));
                    $councilId = (int) $connection->lastInsertId();
                }
                $connection->prepare('DELETE FROM council_members WHERE council_id = ?')->execute([$councilId]);
                $insert = $connection->prepare('INSERT INTO council_members (council_id, lecturer_id, member_role) VALUES (?, ?, ?)');
                $insert->execute([$councilId, $chair, 'chair']);
                $insert->execute([$councilId, $secretary, 'secretary']);
                foreach (array_unique($others) as $memberId) {
                    $insert->execute([$councilId, $memberId, 'member']);
                }
            });
            $conflicts = page_count('SELECT COUNT(*) FROM theses th JOIN council_members cm ON cm.council_id = th.council_id AND cm.lecturer_id = th.supervisor_id WHERE th.council_id = ?', [$councilId]);
            return $done('admin/theses&batch=' . (int) $batch['id'], 'Đã lưu hội đồng.' . ($conflicts ? ' Cảnh báo: ' . $conflicts . ' đồ án trong hội đồng có người hướng dẫn là thành viên.' : ''));

        case 'council_delete':
            action_user(['admin']);
            $council = page_one('SELECT * FROM councils WHERE id = ?', [thesis_int($input, 'council_id')]);
            if (!$council) {
                throw new DomainException('Không tìm thấy hội đồng.');
            }
            batch_require_unlocked(thesis_batch((int) $council['batch_id']));
            $connection->prepare('DELETE FROM councils WHERE id = ?')->execute([(int) $council['id']]);
            return $done('admin/theses&batch=' . (int) $council['batch_id'], 'Đã xóa hội đồng; các đồ án trong hội đồng chuyển về chưa xếp.');

        case 'batch_publish_results':
            action_user(['admin']);
            $batch = thesis_batch(thesis_int($input, 'batch_id'));
            batch_require_unlocked($batch);
            $complete = 0;
            $missing = 0;
            $students = [];
            foreach (page_all('SELECT th.id, s.user_id FROM theses th JOIN students s ON s.id = th.student_id WHERE th.batch_id = ? AND th.status <> \'declined\'', [(int) $batch['id']]) as $thesis) {
                if (thesis_compute_final((int) $thesis['id']) === null) {
                    $missing++;
                    continue;
                }
                $complete++;
                $students[] = (int) $thesis['user_id'];
            }
            $connection->prepare('UPDATE batches SET results_published_at = NOW() WHERE id = ?')->execute([(int) $batch['id']]);
            foreach ($students as $studentUserId) {
                notify_user($studentUserId, 'thesis', 'Đã có điểm đồ án', 'Kết quả ' . $batch['name'] . ' đã được công bố.', 'student/thesis&batch=' . (int) $batch['id'], true);
            }
            return $done('admin/theses&batch=' . (int) $batch['id'], 'Đã công bố điểm cho ' . $complete . ' sinh viên' . ($missing ? '; ' . $missing . ' đồ án còn thiếu phiếu chấm.' : '.'));

        // ----- Phúc khảo -----
        case 'regrade_request':
            $user = action_user(['student']);
            $thesis = page_one('SELECT th.*, b.results_published_at, b.locked_at FROM theses th JOIN batches b ON b.id = th.batch_id WHERE th.id = ? AND th.student_id = ?', [thesis_int($input, 'thesis_id'), (int) $user['student_id']]);
            if (!$thesis || !$thesis['results_published_at']) {
                throw new DomainException('Chỉ yêu cầu phúc khảo sau khi điểm được công bố.');
            }
            batch_require_unlocked($thesis);
            if (time() - strtotime((string) $thesis['results_published_at']) > 14 * 86400) {
                throw new DomainException('Đã quá 14 ngày kể từ khi công bố điểm.');
            }
            if (page_count('SELECT COUNT(*) FROM regrade_requests WHERE thesis_id = ? AND status = \'pending\'', [(int) $thesis['id']]) > 0) {
                throw new DomainException('Bạn đang có một yêu cầu phúc khảo chờ xử lý.');
            }
            $reason = action_value($input, 'reason', 3000);
            if (mb_strlen($reason, 'UTF-8') < 20) {
                throw new DomainException('Hãy trình bày lý do phúc khảo (ít nhất 20 ký tự).');
            }
            $connection->prepare('INSERT INTO regrade_requests (thesis_id, student_id, reason) VALUES (?, ?, ?)')->execute([(int) $thesis['id'], (int) $user['student_id'], $reason]);
            foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\'') as $admin) {
                notify_user((int) $admin['id'], 'theses', 'Yêu cầu phúc khảo mới', $user['full_name'], 'admin/theses&batch=' . (int) $thesis['batch_id']);
            }
            return $done('student/thesis&batch=' . (int) $thesis['batch_id'], 'Đã gửi yêu cầu phúc khảo.');

        case 'regrade_handle':
            $user = action_user(['admin']);
            $request = page_one('SELECT r.*, th.batch_id FROM regrade_requests r JOIN theses th ON th.id = r.thesis_id WHERE r.id = ? AND r.status = \'pending\'', [thesis_int($input, 'request_id')]);
            if (!$request) {
                throw new DomainException('Không tìm thấy yêu cầu đang chờ.');
            }
            batch_require_unlocked(thesis_batch((int) $request['batch_id']));
            $accept = ($input['decision'] ?? '') === 'accept';
            $role = action_value($input, 'reopen_role', 20);
            database_transaction(static function (PDO $connection) use ($request, $accept, $role, $input, $user): void {
                $connection->prepare('UPDATE regrade_requests SET status = ?, response = ?, handled_by = ?, handled_at = NOW() WHERE id = ?')->execute([$accept ? 'accepted' : 'rejected', action_value($input, 'response', 3000) ?: null, (int) $user['id'], (int) $request['id']]);
                if ($accept) {
                    // Mở lại phiếu chấm của vai trò được chọn để chấm lại; điểm cuối kỳ tạm bỏ đến khi có phiếu mới.
                    $roles = isset(THESIS_ROLE_LABELS[$role]) ? [$role] : array_keys(THESIS_ROLE_LABELS);
                    $connection->prepare('UPDATE thesis_scores SET status = \'draft\' WHERE thesis_id = ? AND evaluator_role IN (' . thesis_in($roles) . ')')->execute(array_merge([(int) $request['thesis_id']], $roles));
                    $connection->prepare('UPDATE theses SET final_score = NULL WHERE id = ?')->execute([(int) $request['thesis_id']]);
                }
            });
            if ($accept) {
                foreach (page_all('SELECT DISTINCT evaluator_user_id FROM thesis_scores WHERE thesis_id = ? AND status = \'draft\'', [(int) $request['thesis_id']]) as $evaluator) {
                    notify_user((int) $evaluator['evaluator_user_id'], 'defense', 'Chấm lại đồ án theo phúc khảo', 'Phiếu chấm đã được mở lại.', 'lecturer/defense', true);
                }
            }
            notify_student((int) $request['student_id'], 'thesis', $accept ? 'Yêu cầu phúc khảo được chấp nhận' : 'Yêu cầu phúc khảo bị từ chối', action_value($input, 'response', 3000), 'student/thesis&batch=' . (int) $request['batch_id'], true);
            return $done('admin/theses&batch=' . (int) $request['batch_id'], $accept ? 'Đã chấp nhận phúc khảo và mở lại phiếu chấm.' : 'Đã từ chối yêu cầu phúc khảo.');

        // ----- Rubric -----
        case 'rubric_save':
            $user = action_user(['admin']);
            $name = action_value($input, 'name', 200);
            $appliesTo = action_value($input, 'applies_to', 20);
            if ($name === '' || !in_array($appliesTo, ['supervisor', 'reviewer', 'council', 'any', 'internship_company', 'internship_lecturer'], true)) {
                throw new DomainException('Hãy nhập tên rubric và chọn đối tượng chấm.');
            }
            // Mỗi dòng một tiêu chí: Tên | trọng số | điểm tối đa | mô tả các mức (ngăn cách bằng ;)
            $criteria = [];
            foreach (preg_split('/\R/u', action_value($input, 'criteria', 20000)) ?: [] as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));
                $weight = (float) str_replace(',', '.', $parts[1] ?? '1');
                $maximum = (float) str_replace(',', '.', $parts[2] ?? '10');
                if ($parts[0] === '' || $weight <= 0 || $maximum <= 0) {
                    throw new DomainException('Dòng tiêu chí không hợp lệ: "' . mb_strimwidth($line, 0, 60, '…', 'UTF-8') . '". Định dạng: Tên | trọng số | điểm tối đa | mô tả mức.');
                }
                $criteria[] = [mb_substr($parts[0], 0, 200, 'UTF-8'), $weight, $maximum, $parts[3] ?? ''];
            }
            if (!$criteria) {
                throw new DomainException('Rubric cần ít nhất một tiêu chí.');
            }
            $rubricId = thesis_int($input, 'rubric_id');
            database_transaction(static function (PDO $connection) use ($rubricId, $name, $appliesTo, $input, $criteria, $user): void {
                if ($rubricId) {
                    $connection->prepare('UPDATE rubrics SET name = ?, applies_to = ?, description = ? WHERE id = ?')->execute([$name, $appliesTo, action_value($input, 'description', 2000) ?: null, $rubricId]);
                    if (page_count('SELECT COUNT(*) FROM thesis_scores WHERE rubric_id = ?', [$rubricId]) + page_count('SELECT COUNT(*) FROM evaluations WHERE rubric_id = ?', [$rubricId]) > 0) {
                        throw new DomainException('Rubric đã được dùng để chấm nên không sửa tiêu chí. Hãy nhân bản rồi sửa bản mới.');
                    }
                    $connection->prepare('DELETE FROM rubric_criteria WHERE rubric_id = ?')->execute([$rubricId]);
                } else {
                    $connection->prepare('INSERT INTO rubrics (name, applies_to, description, created_by) VALUES (?, ?, ?, ?)')->execute([$name, $appliesTo, action_value($input, 'description', 2000) ?: null, (int) $user['id']]);
                    $rubricId = (int) $connection->lastInsertId();
                }
                $insert = $connection->prepare('INSERT INTO rubric_criteria (rubric_id, name, weight, max_score, levels, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($criteria as $index => [$criterionName, $weight, $maximum, $levels]) {
                    $insert->execute([$rubricId, $criterionName, $weight, $maximum, $levels ?: null, $index]);
                }
            });
            return $done('admin/rubrics', 'Đã lưu rubric "' . $name . '".');

        case 'rubric_copy':
            action_user(['admin']);
            $rubric = rubric_with_criteria(thesis_int($input, 'rubric_id'));
            if (!$rubric) {
                throw new DomainException('Không tìm thấy rubric.');
            }
            database_transaction(static function (PDO $connection) use ($rubric): void {
                $connection->prepare('INSERT INTO rubrics (name, applies_to, description, created_by) VALUES (?, ?, ?, ?)')->execute([mb_substr($rubric['name'] . ' (bản sao)', 0, 200, 'UTF-8'), $rubric['applies_to'], $rubric['description'], $rubric['created_by']]);
                $newId = (int) $connection->lastInsertId();
                $insert = $connection->prepare('INSERT INTO rubric_criteria (rubric_id, name, description, weight, max_score, levels, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                foreach ($rubric['criteria'] as $criterion) {
                    $insert->execute([$newId, $criterion['name'], $criterion['description'], $criterion['weight'], $criterion['max_score'], $criterion['levels'], $criterion['sort_order']]);
                }
            });
            return $done('admin/rubrics', 'Đã nhân bản rubric.');

        case 'rubric_delete':
            action_user(['admin']);
            $rubricId = thesis_int($input, 'rubric_id');
            if (page_count('SELECT COUNT(*) FROM thesis_scores WHERE rubric_id = ?', [$rubricId]) > 0 || page_count('SELECT COUNT(*) FROM evaluations WHERE rubric_id = ?', [$rubricId]) > 0 || page_count('SELECT COUNT(*) FROM batches WHERE ? IN (supervisor_rubric_id, reviewer_rubric_id, council_rubric_id, company_rubric_id, lecturer_rubric_id)', [$rubricId]) > 0) {
                throw new DomainException('Rubric đang được đợt hoặc phiếu chấm sử dụng nên không xóa được.');
            }
            $connection->prepare('DELETE FROM rubrics WHERE id = ?')->execute([$rubricId]);
            return $done('admin/rubrics', 'Đã xóa rubric.');
    }
    return null;
}
