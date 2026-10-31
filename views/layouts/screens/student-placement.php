<?php
// Sinh viên · Hồ sơ thực tập: khai báo nơi tự tìm, giấy tờ (tải lên / in giấy giới thiệu), chuyên cần, đánh giá lại đơn vị thực tập.
require __DIR__ . '/_common.php';
$placementLabels = ['pending' => 'Chờ khoa duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối'];
?>
<?php foreach ($screenData['internships'] as $internship): $internshipId = (int) $internship['internship_id']; $attendance = array_column($internship['attendance'], 'total', 'status'); ?>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow"><?= page_date($internship['start_date']) ?> – <?= page_date($internship['end_date']) ?> · GV theo dõi: <?= screen_escape($internship['lecturer_name'] ?? 'chưa phân công') ?></p><h2><?= screen_escape($internship['company_name'] . ' · ' . $internship['position_title']) ?></h2></div><?= x_badge(['planned' => 'Sắp bắt đầu', 'active' => 'Đang thực tập', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy'][$internship['status']] ?? '') ?></div>
        <div class="chip-row"><?php foreach (ATTENDANCE_LABELS as $key => $label): ?><span class="chip<?= $key === 'absent' && !empty($attendance[$key]) ? ' chip--warn' : '' ?>"><?= $label ?>: <?= (int) ($attendance[$key] ?? 0) ?></span><?php endforeach; ?></div>
        <div class="x-split">
            <div class="x-block">
                <h4>Giấy tờ thực tập</h4>
                <p class="x-links"><a class="table-edit-button" href="?export=letter&amp;id=<?= $internshipId ?>" target="_blank">Giấy giới thiệu</a><a class="table-edit-button" href="?export=receipt&amp;id=<?= $internshipId ?>" target="_blank">Phiếu tiếp nhận</a><a class="table-edit-button" href="?export=commitment&amp;id=<?= $internshipId ?>" target="_blank">Bản cam kết</a></p>
                <ul class="x-plain-list"><?php foreach ($internship['documents'] as $document): ?><li><a class="table-download" href="?download=internship_doc&amp;id=<?= (int) $document['id'] ?>"><?= screen_escape(INTERNSHIP_DOC_TYPES[$document['doc_type']] . ' · ' . $document['file_name']) ?> ↓</a> <span class="x-meta"><?= screen_escape(($document['full_name'] ?? '') . ' · ' . thesis_datetime_label($document['created_at'])) ?></span></li><?php endforeach; ?>
                    <?php if (!$internship['documents']): ?><li class="x-meta">Chưa có giấy tờ nào.</li><?php endif; ?></ul>
                <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="internship_doc_upload"><input type="hidden" name="internship_id" value="<?= $internshipId ?>">
                    <select name="doc_type"><?php foreach (INTERNSHIP_DOC_TYPES as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select><input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"><button class="button" type="submit">Tải lên</button>
                </form>
            </div>
            <div class="x-block">
                <h4>Đánh giá đơn vị thực tập <span class="x-meta">(giúp khoa chọn đơn vị cho khóa sau)</span></h4>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="company_review_save"><input type="hidden" name="internship_id" value="<?= $internshipId ?>">
                    <div class="form-grid">
                        <label>Mức hài lòng chung<select name="rating"><?php for ($star = 5; $star >= 1; $star--): ?><option value="<?= $star ?>" <?= (int) ($internship['rating'] ?? 0) === $star ? 'selected' : '' ?>><?= str_repeat('★', $star) . str_repeat('☆', 5 - $star) ?></option><?php endfor; ?></select></label>
                        <label>Người hướng dẫn tại đơn vị<select name="mentoring_rating"><?php for ($star = 5; $star >= 1; $star--): ?><option value="<?= $star ?>" <?= (int) ($internship['mentoring_rating'] ?? 0) === $star ? 'selected' : '' ?>><?= str_repeat('★', $star) . str_repeat('☆', 5 - $star) ?></option><?php endfor; ?></select></label>
                    </div>
                    <label class="x-check"><input type="checkbox" name="would_recommend" value="1" <?= !isset($internship['would_recommend']) || (int) $internship['would_recommend'] ? 'checked' : '' ?>> Giới thiệu đơn vị này cho khóa sau</label>
                    <label class="x-check"><input type="checkbox" name="got_offer" value="1" <?= (int) ($internship['got_offer'] ?? 0) ? 'checked' : '' ?>> Tôi được giữ lại / nhận lời mời làm việc</label>
                    <label>Nhận xét<textarea name="comments" rows="2" maxlength="3000"><?= screen_escape($internship['review_comments'] ?? '') ?></textarea></label>
                    <button class="button" type="submit"><?= isset($internship['rating']) ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?></button>
                </form>
            </div>
        </div>
        <?php // Đề cương theo tuần và yêu cầu đổi / rút: gọn trong hai mục mở rộng. ?>
        <?php if (in_array($internship['status'], ['planned', 'active'], true)): ?>
            <details class="x-details"><summary>Đề cương thực tập theo tuần (<?= count($internship['weekly_plans']) ?> tuần)</summary>
                <?php foreach ($internship['weekly_plans'] as $plan): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-week"><?= $csrfField ?><input type="hidden" name="action" value="weekly_plan_save"><input type="hidden" name="internship_id" value="<?= $internshipId ?>"><input type="hidden" name="week_no" value="<?= (int) $plan['week_no'] ?>">
                        <strong>Tuần <?= (int) $plan['week_no'] ?></strong><textarea name="goals" rows="2" maxlength="3000" aria-label="Mục tiêu"><?= screen_escape($plan['goals']) ?></textarea><textarea name="result" rows="2" maxlength="3000" placeholder="Kết quả đạt được" aria-label="Kết quả"><?= screen_escape($plan['result'] ?? '') ?></textarea><button class="button" type="submit">Lưu</button>
                    </form>
                <?php endforeach; ?>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="x-week x-week--add"><?= $csrfField ?><input type="hidden" name="action" value="weekly_plan_save"><input type="hidden" name="internship_id" value="<?= $internshipId ?>">
                    <label>Tuần<input class="x-small-input" type="number" name="week_no" min="1" max="52" value="<?= count($internship['weekly_plans']) + 1 ?>"></label><label>Mục tiêu<textarea name="goals" rows="2" maxlength="3000" placeholder="Mục tiêu, công việc dự kiến của tuần" required></textarea></label><button class="button button--primary" type="submit">Thêm tuần</button>
                </form>
            </details>
            <details class="x-details"><summary>Xin đổi hoặc rút khỏi đơn vị thực tập</summary>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="change_request_submit"><input type="hidden" name="internship_id" value="<?= $internshipId ?>">
                    <label>Yêu cầu<select name="request_type"><?php foreach (CHANGE_REQUEST_LABELS as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select></label>
                    <label>Lý do<textarea name="reason" rows="2" maxlength="3000" required></textarea></label>
                    <button class="button" type="submit" onclick="return confirm('Gửi yêu cầu cho khoa xét duyệt?');">Gửi yêu cầu</button>
                </form>
            </details>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php foreach ($screenData['change_requests'] ?? [] as $request): ?>
    <p class="x-row-line"><span><?= screen_escape(CHANGE_REQUEST_LABELS[$request['request_type']]) ?> · <?= screen_escape($request['company_name']) ?> · <?= screen_escape(thesis_datetime_label($request['created_at'])) ?><?= $request['response'] ? ' · ' . screen_escape($request['response']) : '' ?></span><?= x_badge(['pending' => 'Chờ khoa duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối'][$request['status']]) ?></p>
<?php endforeach; ?>

<?php if (!empty($screenData['allocation_batch']) && !$screenData['has_open_internship']): $prefs = $screenData['my_preferences']; ?>
<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow"><?= screen_escape($screenData['allocation_batch']['name']) ?> · khoa phân bổ theo nguyện vọng, GPA và kỹ năng</p><h2>Nguyện vọng thực tập</h2></div></div>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="internship_preference_save">
        <div class="form-grid">
            <?php for ($rank = 1; $rank <= INTERNSHIP_MAX_PREFERENCES; $rank++): ?>
                <label>Nguyện vọng <?= $rank ?><select name="pref[]" <?= $screenData['allocation_open'] ? '' : 'disabled' ?>><option value="">—</option><?php foreach ($screenData['allocation_positions'] as $position): ?><option value="<?= (int) $position['id'] ?>" <?= (int) ($prefs[$rank] ?? 0) === (int) $position['id'] ? 'selected' : '' ?>><?= screen_escape($position['company_name'] . ' · ' . $position['title'] . ' (còn ' . (int) $position['seats_left'] . ')') ?></option><?php endforeach; ?></select></label>
            <?php endfor; ?>
        </div>
        <?php if ($screenData['allocation_open']): ?><button class="button button--primary" type="submit">Lưu nguyện vọng</button><?php else: ?><p class="form-hint">Ngoài thời gian đăng ký nguyện vọng.</p><?php endif; ?>
    </form>
</section>
<?php endif; ?>

<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Sinh viên tự tìm nơi thực tập · khoa xét duyệt rồi tạo kỳ thực tập</p><h2>Khai báo nơi thực tập</h2></div></div>
    <?php foreach ($screenData['placements'] as $placement): ?>
        <div class="x-row-line"><span><strong><?= screen_escape($placement['company_name']) ?></strong> · <?= screen_escape($placement['position_title']) ?> · <?= page_date($placement['start_date']) ?> – <?= page_date($placement['end_date']) ?><?= $placement['review_note'] ? '<br><span class="x-meta">Nhận xét: ' . screen_escape($placement['review_note']) . '</span>' : '' ?></span><?= x_badge($placementLabels[$placement['status']]) ?></div>
    <?php endforeach; ?>
    <?php if ($screenData['has_open_internship']): ?>
        <p class="form-hint">Bạn đang có kỳ thực tập nên không cần khai báo thêm.</p>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="placement_submit">
            <div class="form-grid">
                <label>Tên đơn vị<input name="company_name" maxlength="200" required></label><label>Mã số thuế<input name="tax_code" maxlength="50"></label>
                <label>Địa chỉ<input name="address" maxlength="255" required></label><label>Vị trí thực tập<input name="position_title" maxlength="200" required></label>
                <label>Người hướng dẫn tại đơn vị<input name="contact_name" maxlength="150" required></label><label>Điện thoại<input name="contact_phone" maxlength="20"></label>
                <label>Email người hướng dẫn<input type="email" name="contact_email" maxlength="255"></label><label>Giấy xác nhận tiếp nhận (PDF/ảnh · 5 MB)<input type="file" name="confirmation_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></label>
                <label>Bắt đầu<input type="date" name="start_date" required></label><label>Kết thúc<input type="date" name="end_date" required></label>
            </div>
            <label>Mô tả công việc<textarea name="description" rows="2" maxlength="5000"></textarea></label>
            <button class="button button--primary" type="submit">Gửi khai báo</button>
        </form>
    <?php endif; ?>
</section>
