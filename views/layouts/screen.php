<?php

// screen.php: bộ khung chung cho MỌI màn hình (các view views/<vai_trò>/<màn_hình>.php chỉ khai báo $screen rồi gọi tệp này).
// Các bước: lấy cấu hình mẫu → nạp dữ liệu thật → nạp thông báo → ghép header + content + footer.
$screenCatalog = require __DIR__ . '/page-data.php';

// Trang đăng nhập / quên mật khẩu dùng layout riêng (auth-screen.php), không có menu hay header.
if (str_starts_with($screen, 'auth/')) {
    require __DIR__ . '/auth-screen.php';
    return;
}

// Cấu hình mẫu của màn hình lấy từ page-data.php; không có thì trả 404.
$screenData = $screenCatalog[$screen] ?? null;
if ($screenData === null) {
    http_response_code(404);
    require __DIR__ . '/auth-screen.php';
    return;
}

// Các hàm hiển thị dùng chung cho mọi view (khai báo một lần).
if (!function_exists('screen_escape')) {

    // Escape HTML an toàn: LUÔN dùng khi in dữ liệu ra giao diện để chống XSS.
    function screen_escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Lấy chữ cái đầu của tên (dùng cho ảnh đại diện mặc định).
    function screen_initial(string $value): string
    {
        preg_match('/^./us', $value, $match);
        return $match[0] ?? '?';
    }

    // Chọn màu huy hiệu (badge) theo nội dung nhãn trạng thái tiếng Việt: chờ / cần chú ý / hoàn tất / đang làm.
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

// Nạp dữ liệu thật từ database cho màn hình (xem load_screen_data trong PageController.php).
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

// Ghép trang theo thứ tự: header (khung + sidebar) → content (thân trang) → footer.
require_once __DIR__ . '/ai-insight.php';
require __DIR__ . '/header.php';
require __DIR__ . '/content.php';
require __DIR__ . '/footer.php';
