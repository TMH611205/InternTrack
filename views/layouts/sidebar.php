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
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'student/dashboard', 'mark' => '🏠'],
        ['key' => 'internships', 'label' => 'Cơ hội thực tập', 'page' => 'student/internships', 'mark' => '🧭'],
        ['key' => 'applications', 'label' => 'Đơn ứng tuyển', 'page' => 'student/applications', 'mark' => '📄'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'student/tasks', 'mark' => '✅'],
        ['key' => 'diary', 'label' => 'Nhật ký', 'page' => 'student/diary', 'mark' => '📝'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'student/reports', 'mark' => '📊'],
        ['key' => 'evaluation', 'label' => 'Đánh giá', 'page' => 'student/evaluation', 'mark' => '⭐'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'student/profile', 'mark' => '👤'],
    ],
    'company' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'company/dashboard', 'mark' => '🏠'],
        ['key' => 'applications', 'label' => 'Hồ sơ ứng tuyển', 'page' => 'company/applications', 'mark' => '📨'],
        ['key' => 'interns', 'label' => 'Thực tập sinh', 'page' => 'company/inters', 'mark' => '👥'],
        ['key' => 'positions', 'label' => 'Vị trí tuyển', 'page' => 'company/positions', 'mark' => '📍'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'company/tasks', 'mark' => '✅'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'company/evaluations', 'mark' => '⭐'],
        ['key' => 'profile', 'label' => 'Hồ sơ doanh nghiệp', 'page' => 'company/profile', 'mark' => '🏢'],
    ],
    'lecturer' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'lecturer/dashboard', 'mark' => '🏠'],
        ['key' => 'students', 'label' => 'Sinh viên', 'page' => 'lecturer/students', 'mark' => '👨‍🎓'],
        ['key' => 'progress', 'label' => 'Tiến độ', 'page' => 'lecturer/progress', 'mark' => '📈'],
        ['key' => 'diaries', 'label' => 'Nhật ký cần duyệt', 'page' => 'lecturer/diaries', 'mark' => '📝'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'lecturer/reports', 'mark' => '📊'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'lecturer/evaluations', 'mark' => '⭐'],
    ],
    'admin' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'admin/dashboard', 'mark' => '🏠'],
        ['key' => 'students', 'label' => 'Sinh viên', 'page' => 'admin/students', 'mark' => '👨‍🎓'],
        ['key' => 'companies', 'label' => 'Doanh nghiệp', 'page' => 'admin/companies', 'mark' => '🏢'],
        ['key' => 'positions', 'label' => 'Vị trí thực tập', 'page' => 'admin/positions', 'mark' => '📍'],
        ['key' => 'internships', 'label' => 'Kỳ thực tập', 'page' => 'admin/internship', 'mark' => '🗓️'],
        ['key' => 'users', 'label' => 'Tài khoản', 'page' => 'admin/users', 'mark' => '👤'],
    ],
];
$roleNames = ['student' => 'Sinh viên', 'company' => 'Doanh nghiệp', 'lecturer' => 'Giảng viên', 'admin' => 'Quản trị'];
$role = $screenData['role'];
$accountPage = ['student' => 'student/profile', 'company' => 'company/profile', 'lecturer' => 'lecturer/students', 'admin' => 'admin/users'][$role];
?>
<!-- Sidebar là trung tâm điều hướng của ứng dụng, hiển thị menu theo vai trò và nút đăng xuất -->
<aside class="sidebar" id="app-sidebar">
    <a class="brand" href="?page=<?= screen_escape($role) ?>/dashboard" aria-label="InternTrack, về tổng quan">
        <span class="brand-symbol">it<span>.</span></span><span class="brand-name">intern<span>track</span></span>
    </a>
    <div class="workspace-switcher"><span class="workspace-glyph"><?= screen_escape(screen_initial($roleNames[$role])) ?></span><span><small>KHÔNG GIAN</small><strong><?= screen_escape($roleNames[$role]) ?></strong></span><span class="switcher-arrow">⌄</span></div>
    <p class="nav-label">MENU CHÍNH</p>
    <nav class="primary-nav" aria-label="Điều hướng chính">
        <?php foreach ($navigation[$role] as $item): ?>
            <a class="nav-link <?= $screenData['active'] === $item['key'] ? 'is-active' : '' ?>" href="?page=<?= screen_escape($item['page']) ?>" <?= $screenData['active'] === $item['key'] ? 'aria-current="page"' : '' ?>>
                <span class="nav-mark"><?= screen_escape($item['mark']) ?></span><span><?= screen_escape($item['label']) ?></span>
                <?php if ($item['key'] === 'diaries' && $role === 'lecturer'): ?><span class="nav-count">11</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="term-card"><span class="term-dot"></span>
            <div><small>CHƯƠNG TRÌNH</small><strong>Thực tập 2026–27</strong></div><span class="term-arrow">↗</span>
        </div>
        <div class="sidebar-account"><a class="sidebar-user" href="?page=<?= screen_escape($accountPage) ?>"><span class="user-avatar"><?php if (!empty($user['avatar'])): ?><img src="?download=avatar&amp;id=<?= (int) $user['id'] ?>" alt=""><?php else: ?><?= screen_escape(screen_initial($user['full_name'])) ?><?php endif; ?></span><span class="user-meta"><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($roleNames[$role]) ?></small></span></a>
            <form method="post" action="index.php"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit"><span class="logout-icon" aria-hidden="true">⇥</span><span>Đăng xuất</span></button></form>
        </div>
    </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close></div>