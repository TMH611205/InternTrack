<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Thông báo được gắn với một "mục" (section) trùng khóa menu của vai trò, ví dụ applications, tasks, diaries.
// Số chưa đọc theo mục hiện ở menu bên; tổng số chưa đọc hiện ở chuông phía trên.

// $email = true: gửi kèm email (chỉ khi bật MAIL_NOTIFY=1 và đã cấu hình SMTP), dùng cho sự kiện quan trọng như nhắc hạn, kết quả ghép, điểm mới.
function notify_user(int $userId, string $section, string $title, string $message = '', ?string $route = null, bool $email = false): void
{
    if ($userId <= 0) {
        return;
    }
    if ($email) {
        notify_email($userId, $title, $message);
    }
    try {
        database()->prepare('INSERT INTO notifications (user_id, section, title, message, link) VALUES (?, ?, ?, ?, ?)')->execute([
            $userId,
            $section,
            mb_substr($title, 0, 200, 'UTF-8'),
            $message !== '' ? mb_substr($message, 0, 500, 'UTF-8') : null,
            $route,
        ]);
    } catch (Throwable $error) {
        // Thông báo lỗi không được làm hỏng thao tác nghiệp vụ chính.
        error_log('InternTrack notification failed: ' . $error->getMessage());
    }
}

function notify_by_profile(string $table, int $profileId, string $section, string $title, string $message, ?string $route, bool $email = false): void
{
    if (!in_array($table, ['students', 'companies', 'lecturers'], true) || $profileId <= 0) {
        return;
    }
    $statement = database()->prepare('SELECT user_id FROM ' . $table . ' WHERE id = ?');
    $statement->execute([$profileId]);
    $userId = $statement->fetchColumn();
    if ($userId) {
        notify_user((int) $userId, $section, $title, $message, $route, $email);
    }
}

// Gửi email thông báo; lỗi gửi chỉ ghi log, không làm hỏng thao tác chính.
function notify_email(int $userId, string $title, string $message): void
{
    if ((string) app_env('MAIL_NOTIFY', '0') !== '1') {
        return;
    }
    $statement = database()->prepare('SELECT email, full_name FROM users WHERE id = ? AND status = \'active\'');
    $statement->execute([$userId]);
    $recipient = $statement->fetch();
    if ($recipient) {
        app_send_mail((string) $recipient['email'], $title, 'Xin chào ' . $recipient['full_name'] . ",

" . $title . ($message !== '' ? "
" . $message : '') . "

Đăng nhập hệ thống Thực tập & Đồ án - Trường Đại học Vinh để xem chi tiết.
");
    }
}

function notify_student(int $studentId, string $section, string $title, string $message = '', ?string $route = null, bool $email = false): void
{
    notify_by_profile('students', $studentId, $section, $title, $message, $route, $email);
}

function notify_company(int $companyId, string $section, string $title, string $message = '', ?string $route = null, bool $email = false): void
{
    notify_by_profile('companies', $companyId, $section, $title, $message, $route, $email);
}

function notify_lecturer(int $lecturerId, string $section, string $title, string $message = '', ?string $route = null, bool $email = false): void
{
    notify_by_profile('lecturers', $lecturerId, $section, $title, $message, $route, $email);
}

// Số thông báo chưa đọc theo từng mục: ['applications' => 2, 'tasks' => 1].
function notification_counts(int $userId): array
{
    $statement = database()->prepare('SELECT section, COUNT(*) AS total FROM notifications WHERE user_id = ? AND read_at IS NULL GROUP BY section');
    $statement->execute([$userId]);
    $counts = [];
    foreach ($statement->fetchAll() as $row) {
        $counts[(string) $row['section']] = (int) $row['total'];
    }
    return $counts;
}

// Thông báo chưa đọc gần nhất; truyền $section để chỉ lấy của một mục.
function notification_unread(int $userId, ?string $section = null, int $limit = 10): array
{
    $sql = 'SELECT id, section, title, message, link, created_at FROM notifications WHERE user_id = ? AND read_at IS NULL';
    $parameters = [$userId];
    if ($section !== null) {
        $sql .= ' AND section = ?';
        $parameters[] = $section;
    }
    $statement = database()->prepare($sql . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit));
    $statement->execute($parameters);
    return $statement->fetchAll();
}

// Đánh dấu đã đọc: một thông báo ($id), cả một mục ($section) hoặc tất cả khi cả hai đều rỗng.
function notification_mark_read(int $userId, ?int $id = null, ?string $section = null): void
{
    $sql = 'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL';
    $parameters = [$userId];
    if ($id !== null) {
        $sql .= ' AND id = ?';
        $parameters[] = $id;
    } elseif ($section !== null) {
        $sql .= ' AND section = ?';
        $parameters[] = $section;
    }
    database()->prepare($sql)->execute($parameters);
}

function notification_time(string $createdAt): string
{
    $seconds = max(0, time() - (int) strtotime($createdAt));
    if ($seconds < 60) {
        return 'Vừa xong';
    }
    if ($seconds < 3600) {
        return intdiv($seconds, 60) . ' phút trước';
    }
    if ($seconds < 86400) {
        return intdiv($seconds, 3600) . ' giờ trước';
    }
    return date('d/m/Y H:i', (int) strtotime($createdAt));
}
