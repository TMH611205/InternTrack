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
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f5f0">
    <meta name="description" content="<?= screen_escape($pageDescription) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= screen_escape($pageTitle) ?> · InternTrack">
    <meta property="og:description" content="<?= screen_escape($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="InternTrack">
    <meta name="twitter:card" content="summary_large_image">
    <title><?= screen_escape($pageTitle) ?> · InternTrack</title>
    <link rel="canonical" href="https://interntrack.local/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="app-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="main-area">
            <header class="topbar">
                <button class="mobile-menu-button" type="button" aria-label="Mở menu" aria-controls="app-sidebar" data-sidebar-toggle><span></span><span></span></button>
                <div class="breadcrumb"><span>InternTrack</span><span class="breadcrumb-slash">/</span><strong><?= screen_escape($screenData['title']) ?></strong></div>
                <div class="topbar-actions"><span class="term-chip">HỌC KỲ 1 <span>·</span> 2026</span><button class="notification-button" type="button" aria-label="Thông báo" data-toast="Không có thông báo mới."><span class="notification-glyph">!</span></button><span class="topbar-divider"></span>
                    <div class="topbar-user"><span class="user-avatar user-avatar--small"><?= screen_escape(screen_initial($user['full_name'])) ?></span><span><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($roleNames[$role]) ?></small></span></div>
                </div>
            </header>