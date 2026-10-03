<?php
// Danh mục điều hướng chính được dựng theo role hiện tại.
// Mỗi role có menu riêng phù hợp với chức năng và quyền truy cập của người dùng.
global $screenData, $user;
$screenData = is_array($screenData ?? null) ? $screenData : [];
$user = is_array($user ?? null) ? $user : [];
$screenData += ['role' => 'student', 'active' => '',];
$user += ['full_name' => 'Người dùng', 'avatar' => null, 'id' => 0];
$navigation = [
    'student' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'student/dashboard', 'icon' => 'home'],
        ['key' => 'internships', 'label' => 'Cơ hội thực tập', 'page' => 'student/internships', 'icon' => 'compass'],
        ['key' => 'applications', 'label' => 'Đơn ứng tuyển', 'page' => 'student/applications', 'icon' => 'file'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'student/tasks', 'icon' => 'check'],
        ['key' => 'diary', 'label' => 'Nhật ký', 'page' => 'student/diary', 'icon' => 'book'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'student/reports', 'icon' => 'chart'],
        ['key' => 'evaluation', 'label' => 'Đánh giá', 'page' => 'student/evaluation', 'icon' => 'star'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'student/profile', 'icon' => 'user'],
    ],
    'company' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'company/dashboard', 'icon' => 'home'],
        ['key' => 'applications', 'label' => 'Hồ sơ ứng tuyển', 'page' => 'company/applications', 'icon' => 'inbox'],
        ['key' => 'interns', 'label' => 'Thực tập sinh', 'page' => 'company/inters', 'icon' => 'users'],
        ['key' => 'positions', 'label' => 'Vị trí tuyển', 'page' => 'company/positions', 'icon' => 'pin'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'company/tasks', 'icon' => 'check'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'company/evaluations', 'icon' => 'star'],
        ['key' => 'profile', 'label' => 'Hồ sơ doanh nghiệp', 'page' => 'company/profile', 'icon' => 'building'],
    ],
    'lecturer' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'lecturer/dashboard', 'icon' => 'home'],
        ['key' => 'students', 'label' => 'Sinh viên', 'page' => 'lecturer/students', 'icon' => 'cap'],
        ['key' => 'progress', 'label' => 'Tiến độ', 'page' => 'lecturer/progress', 'icon' => 'trend'],
        ['key' => 'diaries', 'label' => 'Nhật ký cần duyệt', 'page' => 'lecturer/diaries', 'icon' => 'book'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'lecturer/reports', 'icon' => 'chart'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'lecturer/evaluations', 'icon' => 'star'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'lecturer/profile', 'icon' => 'user'],
    ],
    'admin' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'admin/dashboard', 'icon' => 'home'],
        ['key' => 'students', 'label' => 'Sinh viên', 'page' => 'admin/students', 'icon' => 'cap'],
        ['key' => 'companies', 'label' => 'Doanh nghiệp', 'page' => 'admin/companies', 'icon' => 'building'],
        ['key' => 'positions', 'label' => 'Vị trí thực tập', 'page' => 'admin/positions', 'icon' => 'pin'],
        ['key' => 'internships', 'label' => 'Kỳ thực tập', 'page' => 'admin/internship', 'icon' => 'calendar'],
        ['key' => 'users', 'label' => 'Tài khoản', 'page' => 'admin/users', 'icon' => 'users'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'admin/profile', 'icon' => 'user'],
    ],
];
$roleNames = ['student' => 'Sinh viên', 'company' => 'Doanh nghiệp', 'lecturer' => 'Giảng viên', 'admin' => 'Quản trị'];
$role = $screenData['role'];
$accountPage = ['student' => 'student/profile', 'company' => 'company/profile', 'lecturer' => 'lecturer/profile', 'admin' => 'admin/profile'][$role];
?>
<!-- Sidebar là trung tâm điều hướng của ứng dụng, hiển thị menu theo vai trò và nút đăng xuất -->
<aside class="sidebar" id="app-sidebar">
    <a class="brand" href="?page=<?= screen_escape($role) ?>/dashboard" aria-label="InternTrack, về tổng quan">
        <span class="brand-symbol">it<span>.</span></span><span class="brand-name">intern<span>track</span></span>
    </a>
    <div class="workspace-switcher"><span class="workspace-glyph"><?= screen_escape(screen_initial($roleNames[$role])) ?></span><span><small>KHÔNG GIAN</small><strong><?= screen_escape($roleNames[$role]) ?></strong></span><span class="switcher-arrow"><?= ui_icon('chevron-down') ?></span></div>
    <p class="nav-label">MENU CHÍNH</p>
    <nav class="primary-nav" aria-label="Điều hướng chính">
        <?php foreach ($navigation[$role] as $item): ?>
            <a class="nav-link <?= $screenData['active'] === $item['key'] ? 'is-active' : '' ?>" href="?page=<?= screen_escape($item['page']) ?>" <?= $screenData['active'] === $item['key'] ? 'aria-current="page"' : '' ?>>
                <span class="nav-mark"><?= ui_icon($item['icon']) ?></span><span><?= screen_escape($item['label']) ?></span>
                <?php $unread = (int) (($notificationCounts ?? [])[$item['key']] ?? 0); ?><?php if ($unread > 0): ?><span class="nav-count" title="<?= $unread ?> thông báo chưa đọc"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="term-card"><span class="term-dot"></span>
            <div><small>CHƯƠNG TRÌNH</small><strong>Thực tập 2026–27</strong></div><span class="term-arrow"><?= ui_icon('arrow-up-right') ?></span>
        </div>
        <div class="sidebar-account"><a class="sidebar-user" href="?page=<?= screen_escape($accountPage) ?>"><span class="user-avatar"><?php if (!empty($user['avatar'])): ?><img src="<?= screen_escape(app_avatar_url($user)) ?>" alt=""><?php else: ?><?= screen_escape(screen_initial($user['full_name'])) ?><?php endif; ?></span><span class="user-meta"><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($roleNames[$role]) ?></small></span></a>
            <form method="post" action="index.php"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit"><span class="logout-icon" aria-hidden="true"><?= ui_icon('logout') ?></span><span>Đăng xuất</span></button></form>
        </div>
    </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close></div>