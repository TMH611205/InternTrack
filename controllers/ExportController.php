<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Xuất báo cáo dạng CSV (UTF-8 có BOM) để mở trực tiếp bằng Excel.
// Quyền xuất theo vai trò: quản trị viên xuất toàn bộ, giảng viên chỉ xuất sinh viên mình phụ trách.

// Chặn "CSV injection": ô bắt đầu bằng = + - @ sẽ bị Excel hiểu là công thức nên được thêm dấu nháy đơn phía trước.
function export_cell(mixed $value): string
{
    $text = (string) ($value ?? '');
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $text = "'" . $text;
    }
    return $text;
}

function export_status_label(string $status, array $labels): string
{
    return $labels[$status] ?? $status;
}

// Trả về [tên tệp, tiêu đề cột, các dòng] cho từng loại báo cáo.
function export_dataset(string $type, array $user): array
{
    $connection = database();
    $internshipStatus = ['planned' => 'Sắp bắt đầu', 'active' => 'Đang diễn ra', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];

    if ($type === 'internships' && $user['role'] === 'admin') {
        $rows = $connection->query(
            'SELECT s.student_code, su.full_name AS student_name, s.major, s.class_name, c.company_name, p.title AS position_title,
                    lu.full_name AS lecturer_name, i.start_date, i.end_date, i.status, i.final_score,
                    (SELECT e.overall_score FROM evaluations e WHERE e.internship_id = i.id AND e.evaluator_type = \'company\' AND e.status = \'submitted\' LIMIT 1) AS company_score,
                    (SELECT e.overall_score FROM evaluations e WHERE e.internship_id = i.id AND e.evaluator_type = \'lecturer\' AND e.status = \'submitted\' LIMIT 1) AS lecturer_score
             FROM internships i
             JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id
             JOIN companies c ON c.id = i.company_id JOIN positions p ON p.id = i.position_id
             LEFT JOIN lecturers l ON l.id = i.lecturer_id LEFT JOIN users lu ON lu.id = l.user_id
             ORDER BY i.start_date DESC, su.full_name'
        )->fetchAll();
        return [
            'danh-sach-ky-thuc-tap',
            ['Mã sinh viên', 'Họ tên', 'Ngành', 'Lớp', 'Doanh nghiệp', 'Vị trí', 'Giảng viên', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Điểm doanh nghiệp', 'Điểm giảng viên', 'Điểm cuối kỳ'],
            array_map(static fn($r) => [$r['student_code'], $r['student_name'], $r['major'], $r['class_name'], $r['company_name'], $r['position_title'], $r['lecturer_name'] ?: 'Chưa phân công', $r['start_date'], $r['end_date'], export_status_label((string) $r['status'], $internshipStatus), $r['company_score'], $r['lecturer_score'], $r['final_score']], $rows),
        ];
    }

    if ($type === 'evaluations' && $user['role'] === 'admin') {
        $rows = $connection->query(
            'SELECT s.student_code, su.full_name AS student_name, c.company_name, e.evaluator_type, eu.full_name AS evaluator_name,
                    e.technical_score, e.attitude_score, e.communication_score, e.discipline_score, e.overall_score, e.status, e.submitted_at, e.comments
             FROM evaluations e
             JOIN internships i ON i.id = e.internship_id
             JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id
             JOIN companies c ON c.id = i.company_id JOIN users eu ON eu.id = e.evaluator_user_id
             ORDER BY su.full_name, e.evaluator_type'
        )->fetchAll();
        return [
            'bang-diem-danh-gia',
            ['Mã sinh viên', 'Họ tên', 'Doanh nghiệp', 'Người đánh giá', 'Vai trò', 'Chuyên môn', 'Thái độ', 'Giao tiếp', 'Kỷ luật', 'Điểm tổng', 'Trạng thái', 'Ngày gửi', 'Nhận xét'],
            array_map(static fn($r) => [$r['student_code'], $r['student_name'], $r['company_name'], $r['evaluator_name'], $r['evaluator_type'] === 'company' ? 'Doanh nghiệp' : 'Giảng viên', $r['technical_score'], $r['attitude_score'], $r['communication_score'], $r['discipline_score'], $r['overall_score'], $r['status'] === 'submitted' ? 'Đã gửi' : 'Bản nháp', $r['submitted_at'], $r['comments']], $rows),
        ];
    }

    if ($type === 'my_students' && $user['role'] === 'lecturer') {
        $statement = $connection->prepare(
            'SELECT s.student_code, su.full_name AS student_name, s.major, s.class_name, c.company_name, p.title AS position_title, i.start_date, i.end_date, i.status,
                    (SELECT COUNT(*) FROM diaries d WHERE d.internship_id = i.id AND d.status IN (\'submitted\', \'approved\')) AS diary_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.internship_id = i.id AND t.status = \'completed\') AS task_done,
                    (SELECT COUNT(*) FROM tasks t WHERE t.internship_id = i.id AND t.status <> \'cancelled\') AS task_total,
                    (SELECT COUNT(*) FROM reports r WHERE r.internship_id = i.id AND r.status IN (\'submitted\', \'reviewing\', \'approved\')) AS report_count
             FROM internships i
             JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id
             JOIN companies c ON c.id = i.company_id JOIN positions p ON p.id = i.position_id
             WHERE i.lecturer_id = ? ORDER BY su.full_name'
        );
        $statement->execute([$user['lecturer_id'] ?? 0]);
        return [
            'sinh-vien-phu-trach',
            ['Mã sinh viên', 'Họ tên', 'Ngành', 'Lớp', 'Doanh nghiệp', 'Vị trí', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Số nhật ký đã nộp', 'Nhiệm vụ hoàn thành', 'Tổng nhiệm vụ', 'Số báo cáo đã nộp'],
            array_map(static fn($r) => [$r['student_code'], $r['student_name'], $r['major'], $r['class_name'], $r['company_name'], $r['position_title'], $r['start_date'], $r['end_date'], export_status_label((string) $r['status'], $internshipStatus), $r['diary_count'], $r['task_done'], $r['task_total'], $r['report_count']], $statement->fetchAll()),
        ];
    }

    http_response_code(403);
    exit('Bạn không có quyền xuất báo cáo này.');
}

function export_serve(string $type, array $user): never
{
    [$name, $header, $rows] = export_dataset($type, $user);
    header('Content-Type: text/csv; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name . '-' . date('Ymd') . '.csv'));
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $header, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, array_map('export_cell', $row), ',', '"', '\\');
    }
    fclose($output);
    exit;
}
