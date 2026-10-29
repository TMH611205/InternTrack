<?php

declare(strict_types=1);

// API đồng bộ với hệ thống quản lý đào tạo của trường (gọi từ máy chủ đào tạo, không dùng trình duyệt).
// Bật bằng SYNC_API_KEY trong .env; mỗi yêu cầu gửi header "X-Api-Key: <khóa>".
//   POST index.php?api=sync            body JSON {"users": [{"role": "student", "email": ..., "full_name": ..., "code": ..., "major": ...,
//                                       "class_name": ..., "faculty": ..., "gpa": ..., "credits": ..., "courses": "INT3306,INT3505"}, ...]}
//                                       → tạo / cập nhật tài khoản và hồ sơ (cùng quy tắc với nhập Excel).
//   GET  index.php?api=sync&type=results → điểm tổng kết thực tập và đồ án để hệ thống đào tạo ghi nhận.
function sync_api_handle(): never
{
    header('Content-Type: application/json; charset=utf-8');
    $key = (string) app_env('SYNC_API_KEY', '');
    $sent = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($key === '' || strlen($key) < 24 || !hash_equals($key, $sent)) {
        http_response_code(401);
        exit(json_encode(['error' => 'Khóa API không hợp lệ hoặc chưa cấu hình SYNC_API_KEY.'], JSON_UNESCAPED_UNICODE));
    }
    require_once __DIR__ . '/ActionController.php';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $body = json_decode((string) file_get_contents('php://input'), true);
            $users = is_array($body['users'] ?? null) ? array_slice($body['users'], 0, 5000) : null;
            if ($users === null) {
                throw new DomainException('Thiếu danh sách "users".');
            }
            $columns = [];
            foreach ($users as $user) {
                $columns = array_unique(array_merge($columns, array_keys(is_array($user) ? $user : [])));
            }
            $rows = [$columns];
            foreach ($users as $user) {
                $rows[] = array_map(static fn($column) => is_scalar($user[$column] ?? null) ? (string) $user[$column] : '', $columns);
            }
            [$created, $updated, $errors] = import_users($rows);
            audit_record('sync_api_users', ['created' => (string) $created, 'updated' => (string) $updated], ['id' => null, 'role' => 'system']);
            exit(json_encode(['created' => $created, 'updated' => $updated, 'errors' => $errors], JSON_UNESCAPED_UNICODE));
        }
        if (($_GET['type'] ?? '') === 'results') {
            $internships = page_all('SELECT v.student_code, v.student_name, v.company_name, v.start_date, v.end_date, v.final_score FROM v_student_internships v WHERE v.status = \'completed\' AND v.final_score IS NOT NULL ORDER BY v.end_date DESC');
            $theses = page_all('SELECT s.student_code, u.full_name AS student_name, t.title, b.name AS batch_name, th.final_score, th.final_score >= b.pass_score AS passed FROM theses th JOIN students s ON s.id = th.student_id JOIN users u ON u.id = s.user_id JOIN topics t ON t.id = th.topic_id JOIN batches b ON b.id = th.batch_id WHERE th.final_score IS NOT NULL AND b.results_published_at IS NOT NULL ORDER BY b.created_at DESC');
            exit(json_encode(['internships' => $internships, 'theses' => $theses, 'internship_pass_score' => final_pass_score()], JSON_UNESCAPED_UNICODE));
        }
        http_response_code(400);
        exit(json_encode(['error' => 'Dùng POST để gửi danh sách users, hoặc GET với type=results.'], JSON_UNESCAPED_UNICODE));
    } catch (DomainException $error) {
        http_response_code(422);
        exit(json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE));
    }
}
