<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';

// Nhắc hạn tự động: tạo thông báo trong hệ thống và gửi thêm email (nếu đã cấu hình Gmail).
// Chạy định kỳ bằng scripts/send_reminders.php hoặc bấm nút "Gửi nhắc hạn" của quản trị viên.
// Mỗi người chỉ nhận một lần cho cùng một nội dung trong một ngày nên chạy lại nhiều lần không gây trùng lặp.

const REMINDER_DIARY_DAYS = 7;
const REMINDER_TASK_DAYS = 2;
const REMINDER_COMPANY_DAYS = 3;

// Gửi một lời nhắc cho một người. Trả về số email đã gửi (0 hoặc 1), hoặc null nếu lời nhắc đã gửi trong hôm nay.
function reminder_send(int $userId, string $email, string $section, string $title, string $message, string $route): ?int
{
    $check = database()->prepare('SELECT 1 FROM notifications WHERE user_id = ? AND title = ? AND message = ? AND created_at >= CURDATE() LIMIT 1');
    $check->execute([$userId, $title, $message]);
    if ($check->fetchColumn()) {
        return null;
    }
    notify_user($userId, $section, $title, $message, $route);
    return app_send_mail($email, 'InternTrack - ' . $title, "Xin chào,\r\n\r\n" . $message . "\r\n\r\nĐăng nhập InternTrack để xử lý: " . $title . "\r\n") ? 1 : 0;
}

// Trả về số lời nhắc đã tạo theo từng loại và số email gửi thành công.
function reminder_run(): array
{
    $connection = database();
    $result = ['diary' => 0, 'tasks' => 0, 'companies' => 0, 'emails' => 0];

    // 1. Sinh viên đang thực tập nhưng chưa ghi nhật ký trong những ngày gần đây.
    $students = $connection->query(
        'SELECT u.id AS user_id, u.email, u.full_name
         FROM internships i JOIN students s ON s.id = i.student_id JOIN users u ON u.id = s.user_id
         WHERE i.status = \'active\' AND u.status = \'active\' AND CURDATE() >= i.start_date
           AND NOT EXISTS (SELECT 1 FROM diaries d WHERE d.internship_id = i.id AND d.diary_date >= DATE_SUB(CURDATE(), INTERVAL ' . REMINDER_DIARY_DAYS . ' DAY))'
    )->fetchAll();
    foreach ($students as $student) {
        $sent = reminder_send((int) $student['user_id'], (string) $student['email'], 'diary', 'Nhắc ghi nhật ký thực tập', 'Bạn chưa có nhật ký nào trong ' . REMINDER_DIARY_DAYS . ' ngày qua. Hãy ghi lại công việc đã làm.', 'student/diary');
        if ($sent !== null) {
            $result['diary']++;
            $result['emails'] += $sent;
        }
    }

    // 2. Nhiệm vụ sắp đến hạn hoặc đã quá hạn mà sinh viên chưa nộp.
    $tasks = $connection->query(
        'SELECT t.title, t.due_date, u.id AS user_id, u.email
         FROM tasks t JOIN students s ON s.id = t.assigned_to JOIN users u ON u.id = s.user_id
         WHERE t.status IN (\'todo\', \'in_progress\') AND t.due_date IS NOT NULL AND u.status = \'active\'
           AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL ' . REMINDER_TASK_DAYS . ' DAY)'
    )->fetchAll();
    foreach ($tasks as $task) {
        $overdue = $task['due_date'] < date('Y-m-d');
        $title = $overdue ? 'Nhiệm vụ đã quá hạn' : 'Nhiệm vụ sắp đến hạn';
        $message = 'Nhiệm vụ "' . $task['title'] . '" ' . ($overdue ? 'đã quá hạn từ ' : 'đến hạn vào ') . date('d/m/Y', strtotime((string) $task['due_date'])) . '.';
        $sent = reminder_send((int) $task['user_id'], (string) $task['email'], 'tasks', $title, $message, 'student/tasks');
        if ($sent !== null) {
            $result['tasks']++;
            $result['emails'] += $sent;
        }
    }

    // 3. Hồ sơ doanh nghiệp chờ xác minh quá lâu: nhắc tất cả quản trị viên.
    $pending = (int) $connection->query('SELECT COUNT(*) FROM companies WHERE status = \'pending\' AND created_at <= DATE_SUB(NOW(), INTERVAL ' . REMINDER_COMPANY_DAYS . ' DAY)')->fetchColumn();
    if ($pending > 0) {
        foreach ($connection->query('SELECT id, email FROM users WHERE role = \'admin\' AND status = \'active\'')->fetchAll() as $admin) {
            $sent = reminder_send((int) $admin['id'], (string) $admin['email'], 'companies', 'Doanh nghiệp chờ xác minh', $pending . ' hồ sơ doanh nghiệp đã chờ xác minh quá ' . REMINDER_COMPANY_DAYS . ' ngày.', 'admin/companies');
            if ($sent !== null) {
                $result['companies']++;
                $result['emails'] += $sent;
            }
        }
    }

    return $result;
}
