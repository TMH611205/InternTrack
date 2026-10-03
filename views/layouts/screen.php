<?php
$screenCatalog = require __DIR__ . '/page-data.php';
if (str_starts_with($screen, 'auth/')) {
    require __DIR__ . '/auth-screen.php';
    return;
}

$screenData = $screenCatalog[$screen] ?? null;
if ($screenData === null) {
    http_response_code(404);
    require __DIR__ . '/auth-screen.php';
    return;
}

if (!function_exists('screen_escape')) {
    function screen_escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function screen_initial(string $value): string
    {
        preg_match('/^./us', $value, $match);
        return $match[0] ?? '?';
    }

    function screen_badge_class(string $value): string
    {
        if (str_contains($value, 'Chờ') || str_contains($value, 'Cần') || str_contains($value, 'Mới') || str_contains($value, 'Sắp')) {
            return 'badge--waiting';
        }
        if (str_contains($value, 'Không') || str_contains($value, 'Chưa phù hợp') || str_contains($value, 'Cần hỗ trợ')) {
            return 'badge--attention';
        }
        if (str_contains($value, 'Đã') || str_contains($value, 'Hoàn tất') || str_contains($value, 'Hoạt động') || str_contains($value, 'Được nhận') || str_contains($value, 'Đúng tiến độ')) {
            return 'badge--positive';
        }
        if (str_contains($value, 'Đang')) {
            return 'badge--active';
        }
        return 'badge--neutral';
    }
}

require_once __DIR__ . '/../../controllers/PageController.php';
$user ??= authenticated_user();
$screenData = load_screen_data($screen, $screenData, $user);

// Thông báo: số chưa đọc theo từng mục (menu bên), tổng (chuông) và các thông báo của mục đang mở.
require_once __DIR__ . '/../../controllers/NotificationController.php';
$notificationCounts = notification_counts((int) $user['id']);
$notificationTotal = array_sum($notificationCounts);
$notificationRecent = $notificationTotal > 0 ? notification_unread((int) $user['id'], null, 8) : [];
$sectionKey = (string) ($screenData['active'] ?? '');
$sectionNotices = ($sectionKey !== '' && ($notificationCounts[$sectionKey] ?? 0) > 0) ? notification_unread((int) $user['id'], $sectionKey, 10) : [];

require __DIR__ . '/header.php';
require __DIR__ . '/content.php';
require __DIR__ . '/footer.php';
