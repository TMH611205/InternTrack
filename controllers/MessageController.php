<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';

// Nhắn tin trực tiếp 1-1 giữa sinh viên, giảng viên và doanh nghiệp.
// Hai người chỉ nhắn được cho nhau khi cùng nằm trong một kỳ thực tập (sinh viên - doanh nghiệp - giảng viên phụ trách);
// quản trị viên không tham gia. Danh sách "được phép nhắn" (message_contacts) là nguồn kiểm tra quyền duy nhất.

// Những người user được phép nhắn: [['user_id', 'name', 'role', 'context'], ...] theo kỳ thực tập chưa hủy.
function message_contacts(array $user): array
{
    $studentId = (int) ($user['student_id'] ?? 0);
    $companyId = (int) ($user['company_id'] ?? 0);
    $lecturerId = (int) ($user['lecturer_id'] ?? 0);

    // Mỗi truy vấn trả về: user_id, name, role của người kia, context (vị trí / vai trò hiển thị dưới tên).
    $queries = match ($user['role'] ?? '') {
        'student' => [
            [
                'SELECT cu.id AS user_id, c.company_name AS name, \'company\' AS role, GROUP_CONCAT(DISTINCT p.title ORDER BY p.title SEPARATOR \', \') AS context
                 FROM internships i JOIN companies c ON c.id = i.company_id JOIN users cu ON cu.id = c.user_id AND cu.status = \'active\' JOIN positions p ON p.id = i.position_id
                 WHERE i.student_id = ? AND i.status <> \'cancelled\' GROUP BY cu.id, c.company_name',
                [$studentId],
            ],
            [
                'SELECT lu.id AS user_id, lu.full_name AS name, \'lecturer\' AS role, \'Giảng viên hướng dẫn\' AS context
                 FROM internships i JOIN lecturers l ON l.id = i.lecturer_id JOIN users lu ON lu.id = l.user_id AND lu.status = \'active\'
                 WHERE i.student_id = ? AND i.status <> \'cancelled\' GROUP BY lu.id, lu.full_name',
                [$studentId],
            ],
        ],
        'company' => [
            [
                'SELECT su.id AS user_id, su.full_name AS name, \'student\' AS role, GROUP_CONCAT(DISTINCT p.title ORDER BY p.title SEPARATOR \', \') AS context
                 FROM internships i JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id AND su.status = \'active\' JOIN positions p ON p.id = i.position_id
                 WHERE i.company_id = ? AND i.status <> \'cancelled\' GROUP BY su.id, su.full_name',
                [$companyId],
            ],
            [
                'SELECT lu.id AS user_id, lu.full_name AS name, \'lecturer\' AS role, CONCAT(\'Phụ trách: \', GROUP_CONCAT(DISTINCT su.full_name ORDER BY su.full_name SEPARATOR \', \')) AS context
                 FROM internships i JOIN lecturers l ON l.id = i.lecturer_id JOIN users lu ON lu.id = l.user_id AND lu.status = \'active\' JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id
                 WHERE i.company_id = ? AND i.status <> \'cancelled\' GROUP BY lu.id, lu.full_name',
                [$companyId],
            ],
        ],
        'lecturer' => [
            [
                'SELECT su.id AS user_id, su.full_name AS name, \'student\' AS role, GROUP_CONCAT(DISTINCT p.title ORDER BY p.title SEPARATOR \', \') AS context
                 FROM internships i JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id AND su.status = \'active\' JOIN positions p ON p.id = i.position_id
                 WHERE i.lecturer_id = ? AND i.status <> \'cancelled\' GROUP BY su.id, su.full_name',
                [$lecturerId],
            ],
            [
                'SELECT cu.id AS user_id, c.company_name AS name, \'company\' AS role, CONCAT(\'Sinh viên: \', GROUP_CONCAT(DISTINCT su.full_name ORDER BY su.full_name SEPARATOR \', \')) AS context
                 FROM internships i JOIN companies c ON c.id = i.company_id JOIN users cu ON cu.id = c.user_id AND cu.status = \'active\' JOIN students s ON s.id = i.student_id JOIN users su ON su.id = s.user_id
                 WHERE i.lecturer_id = ? AND i.status <> \'cancelled\' GROUP BY cu.id, c.company_name',
                [$lecturerId],
            ],
        ],
        default => [],
    };

    $contacts = [];
    foreach ($queries as [$sql, $parameters]) {
        foreach (page_all($sql, $parameters) as $row) {
            $contacts[(int) $row['user_id']] = [
                'user_id' => (int) $row['user_id'],
                'name' => (string) $row['name'],
                'role' => (string) $row['role'],
                'context' => (string) ($row['context'] ?? ''),
            ];
        }
    }
    return $contacts;
}

// Dữ liệu màn hình nhắn tin: danh sách liên hệ (kèm tin cuối và số tin chưa đọc), người đang chọn và nội dung hội thoại.
// Mở một hội thoại cũng đánh dấu các tin gửi đến trong đó là đã đọc (cả thông báo tương ứng ở menu).
function message_screen_data(array $user): array
{
    $userId = (int) $user['id'];
    $contacts = message_contacts($user);

    $lastMessages = [];
    foreach (page_all(
        'SELECT m.sender_id, m.recipient_id, m.body, m.file_name, m.created_at FROM messages m
         JOIN (SELECT IF(sender_id = ?, recipient_id, sender_id) AS other_id, MAX(id) AS last_id FROM messages WHERE sender_id = ? OR recipient_id = ? GROUP BY other_id) t ON t.last_id = m.id',
        [$userId, $userId, $userId]
    ) as $row) {
        $otherId = (int) $row['sender_id'] === $userId ? (int) $row['recipient_id'] : (int) $row['sender_id'];
        $lastMessages[$otherId] = $row;
    }
    $unread = [];
    foreach (page_all('SELECT sender_id, COUNT(*) AS total FROM messages WHERE recipient_id = ? AND read_at IS NULL GROUP BY sender_id', [$userId]) as $row) {
        $unread[(int) $row['sender_id']] = (int) $row['total'];
    }

    $selectedId = filter_var($_GET['with'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $thread = [];
    if ($selectedId > 0 && isset($contacts[$selectedId])) {
        database()->prepare('UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL')->execute([$userId, $selectedId]);
        database()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND section = \'messages\' AND link = ? AND read_at IS NULL')->execute([$userId, message_notification_link((string) $user['role'], $selectedId)]);
        unset($unread[$selectedId]);
        // Lấy 200 tin mới nhất rồi đảo lại theo thứ tự thời gian tăng dần.
        $thread = array_reverse(page_all(
            'SELECT id, sender_id, body, file_name, file_path, created_at FROM messages WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) ORDER BY id DESC LIMIT 200',
            [$userId, $selectedId, $selectedId, $userId]
        ));
    } else {
        $selectedId = 0;
    }

    foreach ($contacts as $contactId => &$contact) {
        // Tin chỉ có tệp thì hiện tên tệp thay cho nội dung.
        $contact['last_body'] = isset($lastMessages[$contactId]) ? ((string) $lastMessages[$contactId]['body'] !== '' ? (string) $lastMessages[$contactId]['body'] : 'Tệp: ' . $lastMessages[$contactId]['file_name']) : '';
        $contact['last_at'] = isset($lastMessages[$contactId]) ? (string) $lastMessages[$contactId]['created_at'] : '';
        $contact['last_mine'] = isset($lastMessages[$contactId]) && (int) $lastMessages[$contactId]['sender_id'] === $userId;
        $contact['unread'] = $unread[$contactId] ?? 0;
    }
    unset($contact);
    // Hội thoại mới nhất lên đầu; người chưa nhắn lần nào xếp theo tên.
    uasort($contacts, static fn(array $a, array $b): int => [$b['last_at'], $a['name']] <=> [$a['last_at'], $b['name']]);

    return [
        'contacts' => array_values($contacts),
        'selected_id' => $selectedId,
        'selected' => $selectedId > 0 ? $contacts[$selectedId] : null,
        'thread' => $thread,
        'metrics' => [],
    ];
}

// Liên kết trong thông báo "tin nhắn mới" của người nhận (role của người nhận) tới hội thoại với người gửi.
function message_notification_link(string $recipientRole, int $senderId): string
{
    return $recipientRole . '/messages&with=' . $senderId;
}

// Gửi một tin nhắn (nội dung và/hoặc một tệp đã lưu); trả về thông tin người nhận.
// Ném DomainException nếu hai bên không cùng kỳ thực tập hoặc tin trống.
function message_send(array $sender, int $recipientId, string $body, ?string $filePath = null, ?string $fileName = null): array
{
    $body = trim($body);
    if ($recipientId <= 0 || ($body === '' && $filePath === null)) {
        throw new DomainException('Hãy chọn người nhận và nhập nội dung hoặc đính kèm tệp.');
    }
    // Tên tệp gốc chỉ để hiển thị: bỏ đường dẫn và ký tự điều khiển, giới hạn độ dài.
    $fileName = $filePath === null ? null : (mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string) $fileName))) ?? '', 0, 200, 'UTF-8') ?: basename($filePath));
    // Đuôi hiển thị luôn khớp loại tệp thật đã được kiểm tra khi lưu (ví dụ tệp đặt tên .exe nhưng là văn bản sẽ hiện .txt).
    if ($filePath !== null) {
        $fileName = (pathinfo($fileName, PATHINFO_FILENAME) ?: 'tep') . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
    }
    if (mb_strlen($body, 'UTF-8') > 2000) {
        throw new DomainException('Tin nhắn tối đa 2000 ký tự.');
    }
    $recipient = message_contacts($sender)[$recipientId] ?? null;
    if ($recipient === null) {
        throw new DomainException('Bạn chỉ nhắn tin được với người cùng kỳ thực tập.');
    }

    database()->prepare('INSERT INTO messages (sender_id, recipient_id, body, file_path, file_name) VALUES (?, ?, ?, ?, ?)')->execute([(int) $sender['id'], $recipientId, $body, $filePath, $fileName]);

    // Gộp thông báo: một người gửi nhiều tin liên tiếp chỉ tạo một thông báo chưa đọc, cập nhật nội dung mới nhất.
    $link = message_notification_link($recipient['role'], (int) $sender['id']);
    $preview = $body !== '' ? mb_substr($body, 0, 120, 'UTF-8') : 'Đã gửi tệp: ' . $fileName;
    $existing = page_one('SELECT id FROM notifications WHERE user_id = ? AND section = \'messages\' AND link = ? AND read_at IS NULL LIMIT 1', [$recipientId, $link]);
    if ($existing) {
        database()->prepare('UPDATE notifications SET message = ?, created_at = NOW() WHERE id = ?')->execute([$preview, $existing['id']]);
    } else {
        // Doanh nghiệp hiện bằng tên công ty (giống danh sách liên hệ), người khác hiện bằng họ tên.
        $senderName = ($sender['role'] ?? '') === 'company' ? ($sender['company_name'] ?? $sender['full_name']) : $sender['full_name'];
        notify_user($recipientId, 'messages', 'Tin nhắn mới từ ' . $senderName, $preview, $link);
    }
    return $recipient;
}
