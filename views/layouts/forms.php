<?php
// Form động theo từng màn hình để tránh lặp code và dễ mở rộng chức năng.
// Mỗi block form tương ứng với một feature riêng của role đang truy cập.
global $screen, $user, $screenData;
$screen = $screen ?? '';
$user = is_array($user ?? null) ? $user : [];
$screenData = is_array($screenData ?? null) ? $screenData : [];
$user += ['full_name' => 'Người dùng', 'phone' => '', 'email' => ''];
$screenData += ['internship_id' => null, 'internship_options' => [], 'profile_record' => []];
$csrfField = '<input type="hidden" name="_csrf" value="' . screen_escape(app_csrf_token()) . '">';
$formAction = '?page=' . rawurlencode($screen);
?>
<?php if ($screen === 'company/positions'): ?>
    <details id="new-position" class="action-panel">
        <summary><span>+ Đăng vị trí mới</span><small>Tạo tin tuyển dụng cho doanh nghiệp</small></summary>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="position_save">
            <p class="form-hint">Tin công khai hiển thị tên và địa chỉ công ty từ hồ sơ doanh nghiệp. Ưu tiên cơ hội tại TP. Vinh, Nghệ An.</p>
            <label>Tên vị trí<input name="title" maxlength="200" required></label>
            <label>Mô tả công việc<textarea name="description" rows="3" required></textarea></label>
            <label>Yêu cầu<textarea name="requirements" rows="2"></textarea></label>
            <label>Quyền lợi<textarea name="benefits" rows="2"></textarea></label>
            <div class="form-grid"><label>Nơi làm việc<input name="location" maxlength="255" placeholder="TP. Vinh, Nghệ An hoặc Từ xa"></label><label>Số lượng<input name="quantity" type="number" min="1" value="1" required></label><label>Hạn nhận hồ sơ<input name="deadline" type="date" min="<?= date('Y-m-d') ?>"></label><label>Trạng thái<select name="status">
                        <option value="draft">Bản nháp</option>
                        <option value="open">Đang mở</option>
                    </select></label></div>
            <button class="button button--primary" type="submit">Lưu vị trí</button>

        </form>

    </details>
<?php elseif ($screen === 'company/tasks' && !empty($screenData['internship_options'])): ?>
    <details id="new-task" class="action-panel">
        <summary><span>+ Giao nhiệm vụ</span><small>Gắn nhiệm vụ với một kỳ thực tập</small></summary>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form">
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
            <button class="button button--primary" type="submit">Giao nhiệm vụ</button>
        </form>
    </details>
<?php elseif ($screen === 'student/diary' && !empty($screenData['internship_id'])): ?>
    <details id="new-diary" class="action-panel">
        <summary><span>+ Ghi nhật ký</span><small>Lưu nháp hoặc gửi giảng viên duyệt</small></summary>
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="diary_save"><input type="hidden" name="internship_id" value="<?= (int) $screenData['internship_id'] ?>">
            <div class="form-grid"><label>Ngày thực tập<input name="diary_date" type="date" value="<?= date('Y-m-d') ?>" required></label><label>Số giờ<input name="hours_worked" type="number" min="0" max="24" step="0.5" value="8" required></label></div>
            <label>Tiêu đề<input name="title" maxlength="200" required></label>
            <label>Nội dung<textarea name="content" rows="4" maxlength="10000" required></textarea></label>
            <label>Trạng thái<select name="status">
                    <option value="submitted">Gửi giảng viên duyệt</option>
                    <option value="draft">Lưu bản nháp</option>
                </select></label>
            <button class="button button--primary" type="submit">Lưu nhật ký</button>
        </form>
    </details>
<?php elseif ($screen === 'student/reports' && !empty($screenData['internship_id'])): ?>
    <details class="action-panel">
        <summary><span>+ Tạo báo cáo</span><small>Đề cương, giữa kỳ hoặc cuối kỳ</small></summary>
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form">
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
            <label>Tệp báo cáo (PDF, DOC, DOCX · tối đa 10 MB)<input name="report_file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
            <button class="button button--primary" type="submit">Lưu báo cáo</button>
        </form>
    </details>
<?php elseif ($screen === 'student/profile'): ?>
    <details class="action-panel">
        <summary><span>Chỉnh sửa hồ sơ</span><small>Cập nhật thông tin liên hệ và giới thiệu</small></summary>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="profile_save">
            <div class="form-grid"><label>Họ và tên<input name="full_name" maxlength="150" value="<?= screen_escape($user['full_name']) ?>" required></label><label>Số điện thoại<input name="phone" maxlength="20" value="<?= screen_escape($user['phone'] ?? '') ?>"></label><label>Địa chỉ<input name="address" maxlength="255" value="<?= screen_escape($screenData['profile_record']['address'] ?? '') ?>"></label></div>
            <label>Giới thiệu<textarea name="bio" rows="3" maxlength="5000"><?= screen_escape($screenData['profile_record']['bio'] ?? '') ?></textarea></label>
            <label>Ảnh đại diện (JPG, PNG, WebP · tối đa 10 MB)<input name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <label>CV (PDF, DOC, DOCX · tối đa 15 MB)<input name="cv_file" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
            <button class="button button--primary" type="submit">Lưu hồ sơ</button>
        </form>
    </details>
<?php elseif ($screen === 'company/profile'): ?>
    <details class="action-panel">
        <summary><span>Chỉnh sửa thông tin doanh nghiệp</span><small>Cập nhật liên hệ và phần giới thiệu</small></summary>
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="profile_save">
            <div class="form-grid"><label>Người liên hệ<input name="full_name" maxlength="150" value="<?= screen_escape($user['full_name']) ?>" required></label><label>Điện thoại<input name="phone" maxlength="20" value="<?= screen_escape($user['phone'] ?? '') ?>"></label><label>Website<input name="website" maxlength="255" value="<?= screen_escape($screenData['profile_record']['website'] ?? '') ?>"></label><label>Email liên hệ<input name="company_email" type="email" maxlength="255" value="<?= screen_escape($screenData['profile_record']['email'] ?? $user['email']) ?>"></label><label>Địa chỉ công ty<input name="address" maxlength="255" placeholder="Số nhà, đường, phường, TP. Vinh, Nghệ An" value="<?= screen_escape($screenData['profile_record']['address'] ?? '') ?>" required></label></div>
            <label>Giới thiệu<textarea name="description" rows="4" maxlength="10000"><?= screen_escape($screenData['profile_record']['description'] ?? '') ?></textarea></label>
            <label>Ảnh đại diện (JPG, PNG, WebP · tối đa 10 MB)<input name="avatar_file" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <button class="button button--primary" type="submit">Lưu hồ sơ</button>
        </form>
    </details>
<?php elseif (in_array($screen, ['company/evaluations', 'lecturer/evaluations'], true) && !empty($screenData['internship_options'])): ?>
    <details class="action-panel">
        <summary><span>+ Tạo đánh giá</span><small>Chấm điểm bốn tiêu chí và gửi nhận xét</small></summary>
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="evaluation_save">
            <label>Kỳ thực tập<select name="internship_id" required><?php foreach ($screenData['internship_options'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= screen_escape($option['full_name'] . ' · ' . $option['title']) ?></option><?php endforeach; ?></select></label>
            <div class="form-grid"><label>Chuyên môn<input name="technical_score" type="number" min="0" max="100" value="80" required></label><label>Thái độ<input name="attitude_score" type="number" min="0" max="100" value="80" required></label><label>Giao tiếp<input name="communication_score" type="number" min="0" max="100" value="80" required></label><label>Kỷ luật<input name="discipline_score" type="number" min="0" max="100" value="80" required></label></div>
            <label>Nhận xét<textarea name="comments" rows="3" maxlength="5000"></textarea></label>
            <label>Trạng thái<select name="status">
                    <option value="submitted">Gửi đánh giá</option>
                    <option value="draft">Lưu bản nháp</option>
                </select></label>
            <button class="button button--primary" type="submit">Lưu đánh giá</button>
        </form>
    </details>
<?php elseif ($screen === 'admin/users'): ?>
    <details class="action-panel">
        <summary><span>+ Tạo tài khoản</span><small>Tạo tài khoản và hồ sơ theo vai trò</small></summary>
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form">
            <?= $csrfField ?><input type="hidden" name="action" value="user_create">
            <div class="form-grid"><label>Họ tên<input name="full_name" maxlength="150" required></label><label>Tên đăng nhập<input name="username" maxlength="50" required></label><label>Email<input name="email" type="email" maxlength="255" required></label><label>Mật khẩu tạm<input name="password" type="password" minlength="10" required></label><label>Vai trò<select name="role">
                        <option value="student">Sinh viên</option>
                        <option value="company">Doanh nghiệp</option>
                        <option value="lecturer">Giảng viên</option>
                    </select></label><label>Mã sinh viên<input name="student_code" maxlength="30"></label><label>Mã doanh nghiệp<input name="company_code" maxlength="30"></label><label>Tên doanh nghiệp<input name="company_name" maxlength="200"></label><label>Mã giảng viên<input name="lecturer_code" maxlength="30"></label><label>Khoa / bộ phận<input name="department" maxlength="150"></label><label>Ngành học<input name="major" maxlength="150"></label><label>Lớp<input name="class_name" maxlength="100"></label></div>
            <button class="button button--primary" type="submit">Tạo tài khoản</button>
        </form>
    </details>
<?php endif; ?>