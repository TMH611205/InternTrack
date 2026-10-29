<?php
// Dữ liệu này giúp hiển thị tên vai trò và thông tin trang hiện tại đúng với quyền người dùng.
// Chúng ta khai báo biến global để IDE hiểu rằng các giá trị này được truyền từ controller.
global $screenData, $user;
$screenData = is_array($screenData ?? null) ? $screenData : [];
$user = is_array($user ?? null) ? $user : [];
$roleNames = ['student' => 'Sinh viên', 'company' => 'Doanh nghiệp', 'lecturer' => 'Giảng viên', 'admin' => 'Quản trị'];
$screenData += [
    'role' => 'student',
    'title' => 'InternTrack',
    'description' => 'Hệ thống quản lý kỳ thực tập chuyên nghiệp cho sinh viên, doanh nghiệp và giảng viên.',
];
$user += [
    'full_name' => 'Người dùng',
    'avatar' => null,
    'id' => 0,
];
$role = $screenData['role'];
$pageTitle = (string) ($screenData['title'] ?? 'InternTrack');
$pageDescription = (string) ($screenData['description'] ?? 'Hệ thống quản lý kỳ thực tập chuyên nghiệp cho sinh viên, doanh nghiệp và giảng viên.');
?>

<?php // Phần đầu tài liệu HTML: thẻ meta (SEO), tiêu đề, favicon, font và CSS. ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0073a5">
    <?php // Áp giao diện sáng/tối đã chọn trước khi vẽ trang để không nhấp nháy (lưu trong trình duyệt; mặc định theo hệ điều hành). ?>
    <script>try { var savedTheme = localStorage.getItem('theme'); if (savedTheme === 'dark' || savedTheme === 'light') { document.documentElement.dataset.theme = savedTheme; } } catch (error) {}</script>
    <meta name="description" content="<?= screen_escape($pageDescription) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= screen_escape($pageTitle) ?> · InternTrack">
    <meta property="og:description" content="<?= screen_escape($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="InternTrack">
    <meta name="twitter:card" content="summary_large_image">
    <title><?= screen_escape($pageTitle) ?> · Đại học Vinh</title>
    <link rel="icon" type="image/png" href="assets/images/vinhuni-logo.png">
    <link rel="canonical" href="https://interntrack.local/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
</head>

<body>

    <?php // Khung ứng dụng: sidebar bên trái + vùng nội dung bên phải (đóng ở footer.php). ?>
    <div class="app-shell">

        <?php // Menu điều hướng bên trái (sidebar.php). ?>
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="main-area">

            <?php // Thanh trên cùng: nút mở menu (mobile), đường dẫn, học kỳ, đồng hồ, chuông thông báo và tài khoản. ?>
            <header class="topbar">
                <button class="mobile-menu-button" type="button" aria-label="Mở menu" aria-controls="app-sidebar" data-sidebar-toggle><span></span><span></span></button>
                <div class="breadcrumb"><span>ĐH Vinh</span><span class="breadcrumb-slash">/</span><strong><?= screen_escape($screenData['title']) ?></strong></div>
                <div class="topbar-actions"><span class="term-chip">HỌC KỲ 1 <span>·</span> 2026</span><span class="clock-chip" role="timer" aria-label="Ngày giờ hiện tại"><?= ui_icon('calendar') ?><span data-clock-date><?= screen_escape(["Chủ nhật", "Thứ hai", "Thứ ba", "Thứ tư", "Thứ năm", "Thứ sáu", "Thứ bảy"][(int) date('w')] . ', ' . date('d/m/Y')) ?></span><span class="clock-sep">·</span><?= ui_icon('clock') ?><time data-clock-time><?= screen_escape(date('H:i:s')) ?></time></span><?php
                $bellTotal = (int) ($notificationTotal ?? 0);
                $bellItems = $notificationRecent ?? [];
                $bellLabels = [];
                foreach (($navigation[$role] ?? []) as $navItem) {
                    $bellLabels[$navItem['key']] = $navItem['label'];
                }
                ?><div class="notification-wrap" data-notification-root>
                    <button class="notification-button" type="button" aria-label="Thông báo<?= $bellTotal > 0 ? ': ' . $bellTotal . ' chưa đọc' : '' ?>" aria-haspopup="true" aria-expanded="false" data-notification-toggle><span class="notification-glyph"><?= ui_icon('bell') ?></span><?php if ($bellTotal > 0): ?><i><?= $bellTotal > 99 ? '99+' : $bellTotal ?></i><?php endif; ?></button>
                    <div class="notification-panel" role="menu" data-notification-panel hidden>
                        <div class="notification-panel-head"><strong>Thông báo<?= $bellTotal > 0 ? ' (' . $bellTotal . ')' : '' ?></strong>
                            <?php if ($bellTotal > 0): ?><form method="post" action="?page=<?= screen_escape(rawurlencode((string) ($screen ?? ''))) ?>"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="notification_read"><button type="submit" class="link-button">Đã đọc tất cả</button></form><?php endif; ?>
                        </div>
                        <?php if (!$bellItems): ?><p class="notification-empty">Không có thông báo mới.</p><?php endif; ?>
                        <?php foreach ($bellItems as $item): ?>
                            <a class="notification-item" role="menuitem" href="?page=<?= screen_escape((string) ($item['link'] ?: $role . '/dashboard')) ?>">
                                <small><?= screen_escape($bellLabels[$item['section']] ?? 'Hệ thống') ?> · <?= screen_escape(notification_time((string) $item['created_at'])) ?></small>
                                <strong><?= screen_escape($item['title']) ?></strong>
                                <?php if (!empty($item['message'])): ?><span><?= screen_escape($item['message']) ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($bellTotal > count($bellItems)): ?><p class="notification-empty">Và <?= $bellTotal - count($bellItems) ?> thông báo khác trong từng mục.</p><?php endif; ?>
                    </div>
                </div><span class="topbar-divider"></span>
                    <?php // Menu tài khoản: rê chuột (hoặc bấm trên điện thoại) vào ảnh / tên để mở hồ sơ, đổi giao diện sáng/tối, đăng xuất. ?>
                    <div class="user-menu" data-user-menu>
                        <button class="topbar-user" type="button" aria-haspopup="true" aria-expanded="false" data-user-menu-toggle><span class="user-avatar user-avatar--small"><?php if (!empty($user['avatar'])): ?><img src="<?= screen_escape(app_avatar_url($user)) ?>" alt=""><?php else: ?><?= screen_escape(screen_initial($user['full_name'])) ?><?php endif; ?></span><span><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($roleNames[$role]) ?></small></span><span class="user-menu-caret"><?= ui_icon('chevron-down') ?></span></button>
                        <div class="user-menu-panel" role="menu">
                            <div class="user-menu-head"><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($user['email'] ?? '') ?></small></div>
                            <a class="user-menu-item" role="menuitem" href="?page=<?= screen_escape($accountPage) ?>"><?= ui_icon('user') ?><span>Hồ sơ cá nhân</span></a>
                            <button class="user-menu-item" type="button" role="menuitem" data-dialog-open="password-change"><?= ui_icon('shield') ?><span>Đổi mật khẩu</span></button>
                            <button class="user-menu-item" type="button" role="menuitem" data-theme-toggle><?= ui_icon('moon') ?><span data-theme-label>Giao diện tối</span></button>
                            <form method="post" action="index.php"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="user-menu-item user-menu-item--danger" type="submit" role="menuitem"><?= ui_icon('logout') ?><span>Đăng xuất</span></button></form>
                        </div>
                    </div>
                </div>
            </header>
            <?php // Hộp thoại đổi mật khẩu (mở từ menu tài khoản trên mọi trang). ?>
            <dialog class="modal" id="password-change" aria-label="Đổi mật khẩu">
                <form method="post" action="?page=<?= screen_escape(rawurlencode((string) ($screen ?? ''))) ?>" class="workspace-form modal-form"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_change">
                    <header class="modal-head"><div><p class="eyebrow">Tài khoản</p><h2>Đổi mật khẩu</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                    <label>Mật khẩu hiện tại<input type="password" name="current_password" autocomplete="current-password" required></label>
                    <label>Mật khẩu mới<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label>
                    <label>Nhập lại mật khẩu mới<input type="password" name="new_password_confirmation" minlength="10" autocomplete="new-password" required></label>
                    <p class="form-hint">Ít nhất 10 ký tự.</p>
                    <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Đổi mật khẩu</button></div>
                </form>
            </dialog>