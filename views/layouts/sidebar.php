<?php
// Danh mục điều hướng chính được dựng theo role hiện tại.
// Mỗi role có menu riêng phù hợp với chức năng và quyền truy cập của người dùng.
global $screenData, $user;
$screenData = is_array($screenData ?? null) ? $screenData : [];
$user = is_array($user ?? null) ? $user : [];
$screenData += ['role' => 'student', 'active' => '',];
$user += ['full_name' => 'Người dùng', 'avatar' => null, 'id' => 0];

// Menu điều hướng theo vai trò. Mỗi mục: key (khớp "active" trong page-data.php), nhãn, trang đích và icon (xem ui_icon trong config/app.php).
$navigation = [

    // Menu sinh viên. Mục có 'group' mở đầu một nhóm menu mới.
    'student' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'student/dashboard', 'icon' => 'home'],
        ['key' => 'announcements', 'label' => 'Thông báo & lịch', 'page' => 'student/announcements', 'icon' => 'megaphone'],
        ['key' => 'thesis', 'label' => 'Đồ án tốt nghiệp', 'page' => 'student/thesis', 'icon' => 'layers', 'group' => 'ĐỒ ÁN'],
        ['key' => 'internships', 'label' => 'Cơ hội thực tập', 'page' => 'student/internships', 'icon' => 'compass', 'group' => 'THỰC TẬP'],
        ['key' => 'applications', 'label' => 'Đơn ứng tuyển', 'page' => 'student/applications', 'icon' => 'file'],
        ['key' => 'placement', 'label' => 'Hồ sơ thực tập', 'page' => 'student/placement', 'icon' => 'clipboard'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'student/tasks', 'icon' => 'check'],
        ['key' => 'diary', 'label' => 'Nhật ký', 'page' => 'student/diary', 'icon' => 'book'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'student/reports', 'icon' => 'chart'],
        ['key' => 'evaluation', 'label' => 'Đánh giá', 'page' => 'student/evaluation', 'icon' => 'star'],
        ['key' => 'messages', 'label' => 'Tin nhắn', 'page' => 'student/messages', 'icon' => 'chat', 'group' => 'CÁ NHÂN'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'student/profile', 'icon' => 'user'],
    ],

    // Menu doanh nghiệp.
    'company' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'company/dashboard', 'icon' => 'home'],
        ['key' => 'announcements', 'label' => 'Thông báo & lịch', 'page' => 'company/announcements', 'icon' => 'megaphone'],
        ['key' => 'positions', 'label' => 'Vị trí tuyển', 'page' => 'company/positions', 'icon' => 'pin', 'group' => 'TUYỂN DỤNG'],
        ['key' => 'applications', 'label' => 'Hồ sơ ứng tuyển', 'page' => 'company/applications', 'icon' => 'inbox'],
        ['key' => 'interns', 'label' => 'Thực tập sinh', 'page' => 'company/inters', 'icon' => 'users', 'group' => 'HƯỚNG DẪN'],
        ['key' => 'diaries', 'label' => 'Nhật ký thực tập sinh', 'page' => 'company/diaries', 'icon' => 'book'],
        ['key' => 'attendance', 'label' => 'Chuyên cần & giấy tờ', 'page' => 'company/attendance', 'icon' => 'clipboard'],
        ['key' => 'tasks', 'label' => 'Nhiệm vụ', 'page' => 'company/tasks', 'icon' => 'check'],
        ['key' => 'skills', 'label' => 'Kỹ năng thực tập', 'page' => 'company/skills', 'icon' => 'chart'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'company/evaluations', 'icon' => 'star'],
        ['key' => 'messages', 'label' => 'Tin nhắn', 'page' => 'company/messages', 'icon' => 'chat', 'group' => 'TÀI KHOẢN'],
        ['key' => 'profile', 'label' => 'Hồ sơ doanh nghiệp', 'page' => 'company/profile', 'icon' => 'building'],
    ],

    // Menu giảng viên.
    'lecturer' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'lecturer/dashboard', 'icon' => 'home'],
        ['key' => 'announcements', 'label' => 'Thông báo & lịch', 'page' => 'lecturer/announcements', 'icon' => 'megaphone'],
        ['key' => 'topics', 'label' => 'Đề tài của tôi', 'page' => 'lecturer/topics', 'icon' => 'layers', 'group' => 'ĐỒ ÁN'],
        ['key' => 'theses', 'label' => 'Sinh viên đồ án', 'page' => 'lecturer/theses', 'icon' => 'cap'],
        ['key' => 'defense', 'label' => 'Phản biện & hội đồng', 'page' => 'lecturer/defense', 'icon' => 'award'],
        ['key' => 'students', 'label' => 'Sinh viên thực tập', 'page' => 'lecturer/students', 'icon' => 'users', 'group' => 'THỰC TẬP'],
        ['key' => 'progress', 'label' => 'Tiến độ', 'page' => 'lecturer/progress', 'icon' => 'trend'],
        ['key' => 'diaries', 'label' => 'Nhật ký cần duyệt', 'page' => 'lecturer/diaries', 'icon' => 'book'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'page' => 'lecturer/reports', 'icon' => 'chart'],
        ['key' => 'evaluations', 'label' => 'Đánh giá', 'page' => 'lecturer/evaluations', 'icon' => 'star'],
        ['key' => 'messages', 'label' => 'Tin nhắn', 'page' => 'lecturer/messages', 'icon' => 'chat', 'group' => 'CÁ NHÂN'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'lecturer/profile', 'icon' => 'user'],
    ],

    // Menu quản trị (phòng đào tạo / khoa).
    'admin' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'page' => 'admin/dashboard', 'icon' => 'home'],
        ['key' => 'statistics', 'label' => 'Thống kê & biểu mẫu', 'page' => 'admin/statistics', 'icon' => 'trend'],
        ['key' => 'batches', 'label' => 'Đợt & mốc thời gian', 'page' => 'admin/batches', 'icon' => 'calendar', 'group' => 'ĐÀO TẠO'],
        ['key' => 'students', 'label' => 'Sinh viên', 'page' => 'admin/students', 'icon' => 'cap'],
        ['key' => 'users', 'label' => 'Tài khoản', 'page' => 'admin/users', 'icon' => 'users'],
        ['key' => 'topics', 'label' => 'Duyệt đề tài', 'page' => 'admin/topics', 'icon' => 'layers', 'group' => 'ĐỒ ÁN'],
        ['key' => 'matching', 'label' => 'Ghép cặp', 'page' => 'admin/matching', 'icon' => 'shuffle'],
        ['key' => 'theses', 'label' => 'Phân công & bảo vệ', 'page' => 'admin/theses', 'icon' => 'award'],
        ['key' => 'rubrics', 'label' => 'Rubric chấm điểm', 'page' => 'admin/rubrics', 'icon' => 'clipboard'],
        ['key' => 'companies', 'label' => 'Doanh nghiệp', 'page' => 'admin/companies', 'icon' => 'building', 'group' => 'THỰC TẬP'],
        ['key' => 'positions', 'label' => 'Vị trí thực tập', 'page' => 'admin/positions', 'icon' => 'pin'],
        ['key' => 'internships', 'label' => 'Kỳ thực tập', 'page' => 'admin/internship', 'icon' => 'file'],
        ['key' => 'placements', 'label' => 'Xét duyệt thực tập', 'page' => 'admin/placements', 'icon' => 'compass'],
        ['key' => 'announcements', 'label' => 'Thông báo chung', 'page' => 'admin/announcements', 'icon' => 'megaphone', 'group' => 'HỆ THỐNG'],
        ['key' => 'documents', 'label' => 'Tài liệu khoa', 'page' => 'admin/documents', 'icon' => 'book'],
        ['key' => 'templates', 'label' => 'Cấu hình & mẫu biểu', 'page' => 'admin/templates', 'icon' => 'file'],
        ['key' => 'audit', 'label' => 'Nhật ký & sao lưu', 'page' => 'admin/audit', 'icon' => 'shield'],
        ['key' => 'profile', 'label' => 'Hồ sơ cá nhân', 'page' => 'admin/profile', 'icon' => 'user'],
    ],
];
$roleNames = ['student' => 'Sinh viên', 'company' => 'Doanh nghiệp', 'lecturer' => 'Giảng viên', 'admin' => 'Quản trị'];
$role = $screenData['role'];
// Menu thu gọn theo quyền: người hướng dẫn DN không quản lý tuyển dụng; quản trị khoa không sao lưu / nhật ký toàn hệ thống.
$navigation[$role] = array_values(array_filter($navigation[$role], static fn($item) => !in_array($item['page'], app_hidden_pages($user), true)));

// Trang hồ sơ cá nhân của từng vai trò (liên kết ở khung tài khoản cuối menu).
$accountPage = ['student' => 'student/profile', 'company' => 'company/profile', 'lecturer' => 'lecturer/profile', 'admin' => 'admin/profile'][$role];
?>
<!-- Sidebar là trung tâm điều hướng của ứng dụng, hiển thị menu theo vai trò và nút đăng xuất -->

<?php // Cột menu bên trái: logo, tên vai trò, danh sách mục, thẻ học kỳ, tài khoản và nút đăng xuất. ?>
<aside class="sidebar" id="app-sidebar">
    <a class="brand" href="?page=<?= screen_escape($role) ?>/dashboard" aria-label="Trường Đại học Vinh, về tổng quan">
        <img class="brand-mark brand-mark--seal" src="assets/images/vinhuni-logo.png" alt="Logo Trường Đại học Vinh" width="44" height="44"><span class="brand-text"><strong>ĐẠI HỌC VINH</strong><small>Thực tập &amp; Đồ án</small></span>
    </a>
    <?php // Không gian làm việc: tài khoản có nhiều vai trò (VD giảng viên kiêm quản trị khoa) chuyển vai trò tại đây. ?>
    <?php $spaceName = $role === 'admin' && !empty($user['scope_faculty']) ? 'Quản trị khoa' : ($role === 'company' && !empty($user['is_mentor']) ? 'Người hướng dẫn DN' : $roleNames[$role]); ?>
    <?php if (count($user['roles'] ?? []) > 1): ?>
        <details class="role-switch">
            <summary class="workspace-switcher"><span class="workspace-glyph"><?= screen_escape(screen_initial($spaceName)) ?></span><span><small>KHÔNG GIAN</small><strong><?= screen_escape($spaceName) ?></strong></span><span class="switcher-arrow"><?= ui_icon('chevron-down') ?></span></summary>
            <div class="role-switch-menu">
                <?php foreach ($user['roles'] as $otherRole): if ($otherRole === $role) { continue; } ?>
                    <form method="post" action="index.php"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="role_switch"><input type="hidden" name="role" value="<?= screen_escape($otherRole) ?>"><button type="submit">Chuyển sang <?= screen_escape(mb_strtolower($roleNames[$otherRole], 'UTF-8')) ?></button></form>
                <?php endforeach; ?>
            </div>
        </details>
    <?php else: ?>
        <div class="workspace-switcher"><span class="workspace-glyph"><?= screen_escape(screen_initial($spaceName)) ?></span><span><small>KHÔNG GIAN</small><strong><?= screen_escape($spaceName) ?><?= $role === 'admin' && !empty($user['scope_faculty']) ? ' · ' . screen_escape($user['scope_faculty']) : '' ?></strong></span></div>
    <?php endif; ?>
    <p class="nav-label">MENU CHÍNH</p>

    <?php // Danh sách mục menu của vai trò hiện tại; mục đang mở được đánh dấu và hiện chấm đỏ nếu có thông báo chưa đọc. ?>
    <nav class="primary-nav" aria-label="Điều hướng chính">
        <?php foreach ($navigation[$role] as $item): ?>
            <?php if (!empty($item['group'])): ?><p class="nav-label nav-label--group"><?= screen_escape($item['group']) ?></p><?php endif; ?>
            <a class="nav-link <?= $screenData['active'] === $item['key'] ? 'is-active' : '' ?>" href="?page=<?= screen_escape($item['page']) ?>" <?= $screenData['active'] === $item['key'] ? 'aria-current="page"' : '' ?>>
                <span class="nav-mark"><?= ui_icon($item['icon']) ?></span><span><?= screen_escape($item['label']) ?></span>
                <?php $unread = (int) (($notificationCounts ?? [])[$item['key']] ?? 0); ?><?php if ($unread > 0): ?><span class="nav-count" title="<?= $unread ?> thông báo chưa đọc"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php // Phần cuối menu: thẻ học kỳ, tài khoản người dùng và đăng xuất. ?>
    <div class="sidebar-bottom">
        <div class="term-card"><span class="term-dot"></span>
            <div><small>CHƯƠNG TRÌNH</small><strong>Năm học 2026–2027</strong></div><span class="term-arrow"><?= ui_icon('arrow-up-right') ?></span>
        </div>
        <div class="sidebar-account"><a class="sidebar-user" href="?page=<?= screen_escape($accountPage) ?>"><span class="user-avatar"><?php if (!empty($user['avatar'])): ?><img src="<?= screen_escape(app_avatar_url($user)) ?>" alt=""><?php else: ?><?= screen_escape(screen_initial($user['full_name'])) ?><?php endif; ?></span><span class="user-meta"><strong><?= screen_escape($user['full_name']) ?></strong><small><?= screen_escape($roleNames[$role]) ?></small></span></a>
            <form method="post" action="index.php"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit"><span class="logout-icon" aria-hidden="true"><?= ui_icon('logout') ?></span><span>Đăng xuất</span></button></form>
        </div>
    </div>
</aside>
<div class="sidebar-scrim" data-sidebar-close></div>