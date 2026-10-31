<?php
// Form động theo từng màn hình để tránh lặp code và dễ mở rộng chức năng.
// Mỗi block form tương ứng với một feature riêng của role đang truy cập.
global $screen, $user, $screenData;
$screen = $screen ?? '';
$user = is_array($user ?? null) ? $user : [];
$screenData = is_array($screenData ?? null) ? $screenData : [];
$user += ['full_name' => 'Người dùng', 'phone' => '', 'email' => ''];
$screenData += ['internship_id' => null, 'internship_options' => [], 'profile_record' => []];

// Trường CSRF ẩn: BẮT BUỘC có trong mọi form POST (index.php sẽ từ chối nếu thiếu).
$csrfField = '<input type="hidden" name="_csrf" value="' . screen_escape(app_csrf_token()) . '">';

// Địa chỉ gửi form: luôn quay lại đúng màn hình hiện tại (giữ nhóm tài khoản ở admin/users).
$formAction = '?page=' . rawurlencode($screen) . ($screen === 'admin/users' && !empty($screenData['group']) ? '&group=' . rawurlencode($screenData['group']) : '');
?>
<?php if ($screen === 'company/inters' && empty($user['is_mentor'])): ?>
    <?php // [Doanh nghiệp · Thực tập sinh] Tài khoản người hướng dẫn tại đơn vị: mỗi người chỉ thấy thực tập sinh được giao. ?>
    <button type="button" class="action-trigger" data-dialog-open="mentors"><span>Người hướng dẫn tại đơn vị (<?= count($screenData['mentors'] ?? []) ?>)</span><small>Tạo tài khoản, giao thực tập sinh</small></button>
    <dialog class="modal modal--wide" id="mentors" aria-label="Người hướng dẫn tại đơn vị">
        <div class="workspace-form modal-form">
            <header class="modal-head"><div><p class="eyebrow">Người hướng dẫn tại đơn vị</p><h2>Tài khoản người hướng dẫn</h2><p class="modal-sub">Người hướng dẫn đăng nhập riêng, chỉ thấy và đánh giá thực tập sinh được giao.</p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?php if (!empty($screenData['mentors'])): ?>
                <ul class="x-plain-list">
                    <?php foreach ($screenData['mentors'] as $mentor): ?>
                        <li class="x-row-line"><span><strong><?= screen_escape($mentor['full_name']) ?></strong> · <?= screen_escape(trim(($mentor['job_title'] ?: '') . ' · ' . $mentor['email'], ' ·')) ?> · <?= (int) $mentor['load_count'] ?> thực tập sinh<?= $mentor['status'] !== 'active' ? ' · đã khóa' : '' ?></span>
                            <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="mentor_toggle"><input type="hidden" name="mentor_user_id" value="<?= (int) $mentor['id'] ?>"><button type="submit"><?= $mentor['status'] === 'active' ? 'Khóa' : 'Mở khóa' ?></button></form></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="mentor_create">
                <p class="form-hint">Thêm người hướng dẫn</p>
                <div class="form-grid">
                    <label>Họ tên<input name="full_name" maxlength="150" required></label>
                    <label>Chức vụ<input name="job_title" maxlength="150" placeholder="Trưởng nhóm phát triển"></label>
                    <label>Email<input type="email" name="email" maxlength="255" required></label>
                    <label>Điện thoại<input name="phone" maxlength="20"></label>
                    <label>Tên đăng nhập<input name="username" maxlength="50" required></label>
                    <label>Mật khẩu (≥ 10 ký tự)<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
                </div>
                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit">Tạo tài khoản</button></div>
            </form>
        </div>
    </dialog>
<?php endif; ?>

<?php if ($screen === 'company/positions'): ?>
    <?php // [Doanh nghiệp · Vị trí tuyển] Nút và hộp thoại đăng vị trí tuyển dụng mới (action=position_save). ?>
    <button type="button" class="action-trigger" data-dialog-open="new-position"><span>+ Đăng vị trí mới</span><small>Tạo tin tuyển dụng cho doanh nghiệp</small></button>
    <dialog class="modal" id="new-position" aria-label="Đăng vị trí mới">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Tạo tin tuyển dụng cho doanh nghiệp</p><h2>Đăng vị trí mới</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="position_save">
            <p class="form-hint">Tin được khoa duyệt trước khi hiển thị cho sinh viên.</p>
            <label>Tên vị trí<input name="title" maxlength="200" required></label>
            <label>Mô tả công việc<textarea name="description" rows="3" required></textarea></label>
            <label>Yêu cầu<textarea name="requirements" rows="2"></textarea></label>
            <label>Quyền lợi<textarea name="benefits" rows="2"></textarea></label>
            <div class="form-grid"><label>Nơi làm việc<input name="location" maxlength="255" placeholder="TP. Vinh, Nghệ An hoặc Từ xa"></label><label>Số lượng<input name="quantity" type="number" min="1" value="1" required></label><label>Hạn nhận hồ sơ<input name="deadline" type="date" min="<?= date('Y-m-d') ?>"></label><label>Trạng thái<select name="status">
                        <option value="open">Gửi khoa duyệt</option>
                        <option value="draft">Lưu bản nháp</option>
                    </select></label></div>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu vị trí</button></div>

        </form>
    </dialog>
<?php elseif ($screen === 'company/tasks' && !empty($screenData['internship_options'])): ?>
    <?php // [Doanh nghiệp · Nhiệm vụ] Giao nhiệm vụ mới cho thực tập sinh (action=task_create). ?>
    <button type="button" class="action-trigger" data-dialog-open="new-task"><span>+ Giao nhiệm vụ</span><small>Gắn nhiệm vụ với một kỳ thực tập</small></button>
    <dialog class="modal" id="new-task" aria-label="Giao nhiệm vụ">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Gắn nhiệm vụ với một kỳ thực tập</p><h2>Giao nhiệm vụ</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                <p class="form-hint">Dùng để giao thêm nhiệm vụ ngoài kế hoạch thực tập do nhà trường phân công. Xem kế hoạch của từng sinh viên ở mục Thực tập sinh.</p>
            <?= $csrfField ?><input type="hidden" name="action" value="task_create">
            <label>Kỳ thực tập<select name="internship_id" required><?php foreach ($screenData['internship_options'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= screen_escape($option['full_name'] . ' · ' . $option['title']) ?></option><?php endforeach; ?></select></label>
            <label>Tên nhiệm vụ<input name="title" maxlength="200" required></label>
            <label>Mô tả<textarea name="description" rows="2"></textarea></label>
            <div class="form-grid"><label>Mức ưu tiên<select name="priority">
                        <option value="low">Thấp</option>
                        <option value="medium" selected>Vừa</option>
                        <option value="high">Cao</option>
                        <option value="urgent">Khẩn</option>
                    </select></label><label>Ngày bắt đầu<input name="start_date" type="date"></label><label>Hạn hoàn thành<input name="due_date" type="date"></label></div>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Giao nhiệm vụ</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'student/diary' && !empty($screenData['internship_id'])): ?>
    <?php // [Sinh viên · Nhật ký] Ghi nhật ký (lưu nháp hoặc gửi giảng viên duyệt). ?>
    <button type="button" class="action-trigger" data-dialog-open="new-diary"><span>+ Ghi nhật ký</span><small>Lưu nháp hoặc gửi giảng viên duyệt</small></button>
    <dialog class="modal" id="new-diary" aria-label="Ghi nhật ký">
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Lưu nháp hoặc gửi giảng viên duyệt</p><h2>Ghi nhật ký</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="diary_save"><input type="hidden" name="internship_id" value="<?= (int) $screenData['internship_id'] ?>">
            <div class="form-grid"><label>Ngày thực tập<input name="diary_date" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></label><label>Số giờ<input name="hours_worked" type="number" min="0" max="24" step="0.5" value="8" required></label></div>
            <label>Tiêu đề<input name="title" maxlength="200" required></label>
            <label>Nội dung<textarea name="content" rows="4" maxlength="10000" required></textarea></label>
            <label>Trạng thái<select name="status">
                    <option value="submitted">Gửi giảng viên duyệt</option>
                    <option value="draft">Lưu bản nháp</option>
                </select></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu nhật ký</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'student/diary'): ?>
    <?php // [Sinh viên · Nhật ký] Chưa có kỳ thực tập nên chưa viết được nhật ký: chỉ hiện lời nhắc. ?>
    <p class="flash-message">Bạn chưa có kỳ thực tập đang diễn ra nên chưa thể viết nhật ký. Hãy ứng tuyển và chờ doanh nghiệp nhận hồ sơ; nhật ký sẽ mở khi kỳ thực tập được tạo. <a href="?page=student/internships">Xem cơ hội thực tập</a></p>
<?php elseif ($screen === 'student/reports' && !empty($screenData['internship_id'])): ?>
    <?php // [Sinh viên · Báo cáo] Tạo báo cáo (đề cương, giữa kỳ, cuối kỳ). ?>
    <button type="button" class="action-trigger" data-dialog-open="form-modal-4"><span>+ Tạo báo cáo</span><small>Đề cương, giữa kỳ hoặc cuối kỳ</small></button>
    <dialog class="modal" id="form-modal-4" aria-label="Tạo báo cáo">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Đề cương, giữa kỳ hoặc cuối kỳ</p><h2>Tạo báo cáo</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="report_save"><input type="hidden" name="internship_id" value="<?= (int) $screenData['internship_id'] ?>">
            <div class="form-grid"><label>Loại báo cáo<select name="report_type">
                        <option value="proposal">Đề cương</option>
                        <option value="midterm">Giữa kỳ</option>
                        <option value="final">Cuối kỳ</option>
                        <option value="other">Khác</option>
                    </select></label><label>Trạng thái<select name="status">
                        <option value="submitted">Nộp cho giảng viên</option>
                        <option value="draft">Lưu bản nháp</option>
                    </select></label></div>
            <label>Tiêu đề<input name="title" maxlength="255" required></label>
            <label>Nội dung<textarea name="content" rows="5" maxlength="20000" required></textarea></label>
            <label>Tệp báo cáo (PDF, DOC, DOCX · tối đa 3 MB)<input name="report_file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu báo cáo</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'student/profile'): ?>
    <?php // [Sinh viên · Hồ sơ] Chỉnh sửa hồ sơ, tải CV và ảnh đại diện. ?>
    <button type="button" class="action-trigger" data-dialog-open="profile-edit-1"><span>Chỉnh sửa hồ sơ</span><small>Cập nhật thông tin liên hệ và giới thiệu</small></button>
    <dialog class="modal" id="profile-edit-1" aria-label="Chỉnh sửa hồ sơ">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Cập nhật thông tin liên hệ và giới thiệu</p><h2>Chỉnh sửa hồ sơ</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="profile_save">
            <div class="form-grid"><label>Họ và tên<input name="full_name" maxlength="150" value="<?= screen_escape($user['full_name']) ?>" required></label><label>Số điện thoại<input name="phone" maxlength="20" value="<?= screen_escape($user['phone'] ?? '') ?>"></label><label>Địa chỉ<input name="address" maxlength="255" value="<?= screen_escape($screenData['profile_record']['address'] ?? '') ?>"></label></div>
            <label>Giới thiệu<textarea name="bio" rows="3" maxlength="5000"><?= screen_escape($screenData['profile_record']['bio'] ?? '') ?></textarea></label>
            <div class="form-grid"><label>Kỹ năng (cách nhau bởi dấu phẩy)<input name="skills" maxlength="2000" value="<?= screen_escape($screenData['profile_record']['skills'] ?? '') ?>" placeholder="PHP, MySQL, Figma, tiếng Anh B1"></label><label>Lĩnh vực quan tâm<input name="interests" maxlength="2000" value="<?= screen_escape($screenData['profile_record']['interests'] ?? '') ?>" placeholder="Phát triển web, AI, IoT"></label></div>
            <p class="form-hint">GPA và số tín chỉ được đồng bộ từ phòng đào tạo, sinh viên không tự sửa.</p>
            <label>Ảnh đại diện (JPG, PNG, WebP · tối đa 3 MB)<input name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <label>CV (PDF, DOC, DOCX · tối đa 5 MB)<input name="cv_file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu hồ sơ</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'company/profile'): ?>
    <?php // [Doanh nghiệp · Hồ sơ] Chỉnh sửa thông tin liên hệ và giới thiệu doanh nghiệp. ?>
    <button type="button" class="action-trigger" data-dialog-open="profile-edit-2"><span>Chỉnh sửa thông tin doanh nghiệp</span><small>Cập nhật liên hệ và phần giới thiệu</small></button>
    <dialog class="modal" id="profile-edit-2" aria-label="Chỉnh sửa thông tin doanh nghiệp">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Cập nhật liên hệ và phần giới thiệu</p><h2>Chỉnh sửa thông tin doanh nghiệp</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="profile_save">
            <div class="form-grid"><label>Người liên hệ<input name="full_name" maxlength="150" value="<?= screen_escape($user['full_name']) ?>" required></label><label>Điện thoại<input name="phone" maxlength="20" value="<?= screen_escape($user['phone'] ?? '') ?>"></label><label>Website<input name="website" maxlength="255" value="<?= screen_escape($screenData['profile_record']['website'] ?? '') ?>"></label><label>Email liên hệ<input name="company_email" type="email" maxlength="255" value="<?= screen_escape($screenData['profile_record']['email'] ?? $user['email']) ?>"></label><label>Địa chỉ công ty<input name="address" maxlength="255" placeholder="Số nhà, đường, phường, TP. Vinh, Nghệ An" value="<?= screen_escape($screenData['profile_record']['address'] ?? '') ?>" required></label></div>
            <label>Lĩnh vực hoạt động<input name="field" maxlength="150" value="<?= screen_escape($screenData['profile_record']['field'] ?? '') ?>" placeholder="Phần mềm, tài chính, du lịch..."></label>
            <label>Giới thiệu<textarea name="description" rows="4" maxlength="10000"><?= screen_escape($screenData['profile_record']['description'] ?? '') ?></textarea></label>
            <label>Ảnh đại diện (JPG, PNG, WebP · tối đa 3 MB)<input name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu hồ sơ</button></div>
        </form>
    </dialog>
<?php elseif (in_array($screen, ['lecturer/profile', 'admin/profile'], true)): ?>
    <?php // [Giảng viên / Quản trị · Hồ sơ] Chỉnh sửa họ tên, số điện thoại, bộ môn và ảnh đại diện. ?>
    <button type="button" class="action-trigger" data-dialog-open="profile-edit-3"><span>Chỉnh sửa hồ sơ</span><small>Cập nhật thông tin liên hệ và ảnh đại diện</small></button>
    <dialog class="modal" id="profile-edit-3" aria-label="Chỉnh sửa hồ sơ">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Cập nhật thông tin liên hệ và ảnh đại diện</p><h2>Chỉnh sửa hồ sơ</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="profile_save">
            <div class="form-grid"><label>Họ và tên<input name="full_name" maxlength="150" value="<?= screen_escape($user['full_name']) ?>" required></label><label>Số điện thoại<input name="phone" maxlength="20" value="<?= screen_escape($user['phone'] ?? '') ?>"></label></div>
            <?php if ($screen === 'lecturer/profile'): ?><div class="form-grid"><label>Bộ môn / Khoa<input name="department" maxlength="150" value="<?= screen_escape($user['department'] ?? '') ?>"></label><label>Chuyên môn<input name="specialization" maxlength="200" value="<?= screen_escape($screenData['profile_record']['specialization'] ?? '') ?>"></label></div>
            <label>Hướng nghiên cứu (dùng để ghép phản biện, hội đồng đúng chuyên môn)<textarea name="research_areas" rows="2" maxlength="2000"><?= screen_escape($screenData['profile_record']['research_areas'] ?? '') ?></textarea></label><?php endif; ?>
            <label>Ảnh đại diện (JPG, PNG, WebP · tối đa 3 MB)<input name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu hồ sơ</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'admin/documents'): ?>
    <?php // [Quản trị · Tài liệu khoa] Thêm tài liệu cho trợ lý hỏi đáp (nhập văn bản hoặc tải .txt/.md). ?>
    <button type="button" class="action-trigger" data-dialog-open="form-modal-kb"><span>+ Thêm tài liệu</span><small>Quy định, mốc thời gian, biểu mẫu thực tập</small></button>
    <dialog class="modal" id="form-modal-kb" aria-label="Thêm tài liệu">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
            <header class="modal-head"><div><p class="eyebrow">Nguồn cho trợ lý hỏi đáp</p><h2>Thêm tài liệu</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="kb_save">
            <label>Tiêu đề<input name="title" maxlength="200" placeholder="Ví dụ: Quy định thực tập 2026"></label>
            <label>Nội dung<textarea name="content" rows="10" maxlength="60000" placeholder="Dán nội dung quy định, mốc thời gian, hướng dẫn biểu mẫu..."></textarea></label>
            <label>Hoặc tải tệp văn bản (.txt, .md · UTF-8 · tối đa 300 KB)<input name="kb_file" type="file" accept=".txt,.md,text/plain,text/markdown"></label>
            <p class="form-hint">Hãy dán nguyên văn nội dung quan trọng (mốc nộp báo cáo, điều kiện, số tín chỉ...). AI chỉ trả lời dựa trên các tài liệu này.</p>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu tài liệu</button></div>
        </form>
    </dialog>
<?php elseif ($screen === 'admin/users' && ($screenData['group'] ?? 'student') !== 'admin'): ?>
    <?php // [Quản trị · Tài khoản] Nhập hàng loạt từ Excel (CSV/XLSX) và xuất danh sách (action=users_import, ?export=users). ?>
    <button type="button" class="action-trigger" data-dialog-open="users-import"><span>⇪ Nhập từ Excel</span><small>Tạo mới hoặc cập nhật hàng loạt, đồng bộ GPA/tín chỉ</small></button>
    <dialog class="modal" id="users-import" aria-label="Nhập tài khoản từ Excel">
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
            <header class="modal-head"><div><p class="eyebrow">Nhập dữ liệu hàng loạt</p><h2>Nhập tài khoản từ Excel</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="users_import">
            <p class="form-hint">Dòng đầu là tên cột: role, email, full_name, code, major, class_name, gpa, credits, department, company_name… Email hoặc tên đăng nhập đã có thì cập nhật hồ sơ (dùng để đồng bộ GPA, tín chỉ từ hệ thống đào tạo). Tài khoản mới không có mật khẩu sẽ tự đặt qua chức năng Quên mật khẩu.</p>
            <p><a class="table-download" href="?export=import_template">Tải tệp mẫu (.csv) ↓</a> · <a class="table-download" href="?export=users">Xuất toàn bộ tài khoản ↓</a></p>
            <label>Tệp Excel (.xlsx) hoặc CSV UTF-8 · tối đa 5 MB<input name="import_file" type="file" accept=".xlsx,.csv" required></label>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Nhập dữ liệu</button></div>
        </form>
    </dialog>
    <?php // [Quản trị · Tài khoản] Tạo tài khoản sinh viên / giảng viên / doanh nghiệp (action=user_create). ?>
    <?php
    $createRole = in_array($screenData['group'] ?? '', ['student', 'lecturer', 'company'], true) ? $screenData['group'] : 'student';
    $roleFieldAttr = static fn(string $role): string => 'data-role-field="' . $role . '"' . ($role === $createRole ? '' : ' hidden');
    ?>
    <button type="button" class="action-trigger" data-dialog-open="form-modal-9"><span>+ Tạo tài khoản <?= screen_escape(['student' => 'sinh viên', 'lecturer' => 'giảng viên', 'company' => 'doanh nghiệp'][$createRole]) ?></span><small>Tạo tài khoản và hồ sơ theo vai trò</small></button>
    <dialog class="modal" id="form-modal-9" aria-label="Tạo tài khoản ">
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form" data-role-form>
                <header class="modal-head"><div><p class="eyebrow">Tạo tài khoản và hồ sơ theo vai trò</p><h2>Tạo tài khoản <?= screen_escape(['student' => 'sinh viên', 'lecturer' => 'giảng viên', 'company' => 'doanh nghiệp'][$createRole]) ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
            <?= $csrfField ?><input type="hidden" name="action" value="user_create">
            <div class="form-grid"><label>Họ tên<input name="full_name" maxlength="150" required></label><label>Tên đăng nhập<input name="username" maxlength="50" required></label><label>Email<input name="email" type="email" maxlength="255" required></label><label>Mật khẩu tạm (tối thiểu 10 ký tự)<input name="password" type="password" minlength="10" required></label><input type="hidden" name="role" value="<?= screen_escape($createRole) ?>">
                <label <?= $roleFieldAttr('student') ?>>Mã sinh viên (15 chữ số, ngành và khóa tự suy ra từ mã)<input name="student_code" maxlength="15" minlength="15" pattern="[0-9]{15}" inputmode="numeric" placeholder="235748020110017" title="Gồm đúng 15 chữ số" data-required-for="student" <?= $createRole === 'student' ? 'required' : '' ?>></label>
                <label <?= $roleFieldAttr('student') ?>>Lớp<input name="class_name" maxlength="100" placeholder="64K-CNTT1"></label>
                <label <?= $roleFieldAttr('company') ?>>Mã doanh nghiệp (10 chữ số)<input name="company_code" maxlength="10" minlength="10" pattern="[0-9]{10}" inputmode="numeric" placeholder="0123456789" title="Gồm đúng 10 chữ số" data-required-for="company" <?= $createRole === 'company' ? 'required' : '' ?>></label>
                <label <?= $roleFieldAttr('company') ?>>Tên doanh nghiệp<input name="company_name" maxlength="200" data-required-for="company" <?= $createRole === 'company' ? 'required' : '' ?>></label>
                <label <?= $roleFieldAttr('lecturer') ?>>Mã giảng viên (GV + 6 chữ số)<input name="lecturer_code" maxlength="8" pattern="GV[0-9]{6}" placeholder="GV000101" title="GV và 6 chữ số" data-required-for="lecturer" <?= $createRole === 'lecturer' ? 'required' : '' ?>></label>
                <label <?= $roleFieldAttr('lecturer') ?>>Khoa<select name="department"><option value="">Chọn khoa</option><?php foreach (SCHOOL_DEPARTMENTS as $department): ?><option><?= screen_escape($department) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Tạo tài khoản</button></div>
        </form>
    </dialog>
<?php endif; ?>