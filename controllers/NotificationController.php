<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Thông báo được gắn với một "mục" (section) trùng khóa menu của vai trò, ví dụ applications, tasks, diaries.
// Số chưa đọc theo mục hiện ở menu bên; tổng số chưa đọc hiện ở chuông phía trên.

function notify_user(int $userId, string $section, string $title, string $message = '', ?string $route = null): void
{
    if ($userId <= 0) {
        return;
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

function notify_by_profile(string $table, int $profileId, string $section, string $title, string $message, ?string $route): void
{
    if (!in_array($table, ['students', 'companies', 'lecturers'], true) || $profileId <= 0) {
        return;
    }
    $statement = database()->prepare('SELECT user_id FROM ' . $table . ' WHERE id = ?');
    $statement->execute([$profileId]);
    $userId = $statement->fetchColumn();
    if ($userId) {
        notify_user((int) $userId, $section, $title, $message, $route);
    }
}

function notify_student(int $studentId, string $section, string $title, string $message = '', ?string $route = null): void
{
    notify_by_profile('students', $studentId, $section, $title, $message, $route);
}

function notify_company(int $companyId, string $section, string $title, string $message = '', ?string $route = null): void
{
    notify_by_profile('companies', $companyId, $section, $title, $message, $route);
}

function notify_lecturer(int $lecturerId, string $section, string $title, string $message = '', ?string $route = null): void
{
    notify_by_profile('lecturers', $lecturerId, $section, $title, $message, $route);
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
