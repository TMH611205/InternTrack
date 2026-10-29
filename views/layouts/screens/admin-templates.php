<?php
// Quản trị · Cấu hình & mẫu biểu: đơn vị / địa danh trên văn bản, hàng chữ ký từng biểu mẫu, trọng số điểm thực tập, quy trình duyệt đề tài.
require __DIR__ . '/_common.php';
$settings = $screenData['settings'];
?>
<form method="post" action="<?= screen_escape($xAction) ?>" class="x-split"><?= $csrfField ?><input type="hidden" name="action" value="settings_save">
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Áp dụng cho mọi văn bản in / PDF</p><h2>Thể thức văn bản</h2></div></div>
        <div class="workspace-form workspace-form--plain">
            <label>Đơn vị ban hành (dưới tên trường)<input name="doc_unit" maxlength="150" value="<?= screen_escape($settings['doc_unit']) ?>"></label>
            <label>Địa danh ghi ngày tháng<input name="doc_place" maxlength="80" value="<?= screen_escape($settings['doc_place']) ?>"></label>
        </div>
        <div class="panel-heading"><div><p class="eyebrow">Điểm tổng kết = DN × trọng số + GV × (1 − trọng số)</p><h2>Điểm thực tập</h2></div></div>
        <div class="workspace-form workspace-form--plain form-grid">
            <label>Trọng số doanh nghiệp (%)<input type="number" name="internship_company_weight" min="0" max="100" value="<?= (int) round($settings['internship_company_weight'] * 100) ?>"></label>
            <label>Điểm đạt (thang 100)<input type="number" name="internship_pass_score" min="0" max="100" step="0.5" value="<?= screen_escape($settings['internship_pass_score']) ?>"></label>
        </div>
        <div class="panel-heading"><div><p class="eyebrow">Đề tài giảng viên đăng ký</p><h2>Quy trình duyệt đề tài</h2></div></div>
        <div class="workspace-form workspace-form--plain">
            <label class="x-check"><input type="checkbox" name="topic_two_level" value="1" <?= $settings['topic_two_level'] ? 'checked' : '' ?>> Duyệt 2 cấp: trưởng bộ môn duyệt trước, khoa / phòng đào tạo duyệt sau</label>
            <label class="x-check"><input type="checkbox" name="archive_students" value="1" <?= $settings['archive_students'] ? 'checked' : '' ?>> Cho sinh viên tải quyển đồ án đã bảo vệ trong kho đồ án</label>
            <label class="x-check"><input type="checkbox" name="auto_fill_unmatched" value="1" <?= $settings['auto_fill_unmatched'] ? 'checked' : '' ?>> Khi ghép cặp, tự xếp sinh viên không có nguyện vọng hợp lệ vào đề tài còn chỗ (theo hướng / lĩnh vực quan tâm)</label>
        </div>
    </section>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Mỗi dòng là một ô chữ ký · dùng "|" để xuống dòng trong ô · để trống = mặc định</p><h2>Hàng chữ ký của biểu mẫu</h2></div></div>
        <div class="workspace-form workspace-form--plain">
            <?php foreach ($screenData['templates'] as $template): ?>
                <label><?= screen_escape($template['title']) ?><textarea name="sign[<?= screen_escape($template['key']) ?>]" rows="2" maxlength="500" placeholder="<?= screen_escape($template['default']) ?>"><?= screen_escape($template['value']) ?></textarea></label>
            <?php endforeach; ?>
            <div class="modal-actions"><button class="button button--primary" type="submit">Lưu cấu hình</button></div>
        </div>
    </section>
</form>
