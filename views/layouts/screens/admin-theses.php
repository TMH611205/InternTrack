<?php
// Quản trị · Phân công phản biện, hội đồng, lịch bảo vệ, hạn mức giảng viên, phúc khảo và công bố điểm.
require __DIR__ . '/_common.php';
$batch = $screenData['batch'] ?? null;
$lecturerOptions = static function ($selected, string $empty = 'Chưa phân công') use ($screenData): string {
    $html = '<option value="">' . screen_escape($empty) . '</option>';
    foreach ($screenData['lecturers'] ?? [] as $lecturer) {
        $html .= '<option value="' . (int) $lecturer['id'] . '"' . ((string) $selected === (string) $lecturer['id'] ? ' selected' : '') . '>' . screen_escape($lecturer['full_name'] . ($lecturer['department'] ? ' · ' . $lecturer['department'] : '')) . '</option>';
    }
    return $html;
};
$councilForm = static function (?array $council, array $members) use ($batch, $xAction, $csrfField, $lecturerOptions, $screenData): string {
    $roleOf = array_column($members, 'member_role', 'lecturer_id');
    $chair = array_search('chair', $roleOf, true);
    $secretary = array_search('secretary', $roleOf, true);
    ob_start(); ?>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="council_save"><input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>"><input type="hidden" name="council_id" value="<?= (int) ($council['id'] ?? 0) ?>">
        <header class="modal-head"><div><p class="eyebrow">Hội đồng bảo vệ</p><h2><?= $council ? screen_escape($council['name']) : 'Tạo hội đồng' ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
        <div class="form-grid">
            <label>Tên hội đồng<input name="name" maxlength="150" value="<?= screen_escape($council['name'] ?? '') ?>" placeholder="Hội đồng 1 · CNTT" required></label>
            <label>Phòng<input name="room" maxlength="100" value="<?= screen_escape($council['room'] ?? '') ?>" placeholder="A0.301"></label>
            <label>Ngày bảo vệ<input type="date" name="defense_date" value="<?= screen_escape($council['defense_date'] ?? '') ?>"></label>
            <label>Giờ bắt đầu<input type="time" name="start_time" value="<?= screen_escape(substr((string) ($council['start_time'] ?? '07:30'), 0, 5)) ?>"></label>
            <label>Chủ tịch<select name="chair_id" required><?= $lecturerOptions($chair, 'Chọn giảng viên') ?></select></label>
            <label>Thư ký<select name="secretary_id" required><?= $lecturerOptions($secretary, 'Chọn giảng viên') ?></select></label>
        </div>
        <fieldset class="x-checklist"><legend>Ủy viên</legend>
            <?php foreach ($screenData['lecturers'] as $lecturer): ?><label class="x-check"><input type="checkbox" name="member_ids[]" value="<?= (int) $lecturer['id'] ?>" <?= ($roleOf[$lecturer['id']] ?? '') === 'member' ? 'checked' : '' ?>> <?= screen_escape($lecturer['full_name']) ?></label><?php endforeach; ?>
        </fieldset>
        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu hội đồng</button></div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<?= x_batch_switcher($screenData['batches'], $batch, 'admin/theses') ?>
<?php if (!$batch): ?>
    <?= x_empty('Chưa có đợt đồ án.') ?>
<?php else: $batchId = (int) $batch['id']; ?>
<div class="x-actions-row">
    <?= $xPost('reviewer_auto', ['batch_id' => $batchId], 'Tự phân công phản biện', 'inline-action', null, 'x-strong') ?>
    <?= $xPost('council_auto', ['batch_id' => $batchId], 'Tự xếp hội đồng', 'inline-action', null, 'x-strong') ?>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="defense_schedule_auto"><input type="hidden" name="batch_id" value="<?= $batchId ?>"><input class="x-small-input" type="number" name="slot_minutes" value="45" min="15" max="120" aria-label="Phút mỗi lượt"><button type="submit" class="x-strong">Xếp lịch bảo vệ</button></form>
    <?= $xPost('batch_publish_results', ['batch_id' => $batchId], $batch['results_published_at'] ? 'Công bố lại điểm' : 'Công bố điểm', 'inline-action', 'Tính điểm tổng kết và công bố cho sinh viên?', 'x-strong') ?>
    <a class="table-edit-button" href="?export=assignments&amp;batch=<?= $batchId ?>" target="_blank">Quyết định phân công (in/PDF)</a>
    <a class="table-edit-button" href="?export=councils&amp;batch=<?= $batchId ?>" target="_blank">Danh sách hội đồng (in/PDF)</a>
    <a class="table-edit-button" href="?export=thesis_scores&amp;batch=<?= $batchId ?>">Bảng điểm (Excel)</a>
</div>

<?php if ($screenData['warnings']): ?>
    <div class="x-warning"><strong>Cảnh báo phân công:</strong><ul><?php foreach ($screenData['warnings'] as $warning): ?><li><?= screen_escape($warning) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Hội đồng bảo vệ · trọng số HD <?= screen_escape($batch['supervisor_weight']) ?> · PB <?= screen_escape($batch['reviewer_weight']) ?> · HĐ <?= screen_escape($batch['council_weight']) ?></p><h2>Hội đồng</h2></div>
        <button type="button" class="table-edit-button" data-dialog-open="council-new">+ Tạo hội đồng</button></div>
    <dialog class="modal modal--wide" id="council-new" aria-label="Tạo hội đồng"><?= $councilForm(null, []) ?></dialog>
    <div class="x-card-list x-card-list--grid">
        <?php foreach ($screenData['councils'] as $council): $members = $screenData['council_members'][(int) $council['id']] ?? []; ?>
            <article class="x-card">
                <div class="x-card-head"><div><h3><?= screen_escape($council['name']) ?></h3><p class="x-meta"><?= screen_escape(($council['defense_date'] ? date('d/m/Y', (int) strtotime($council['defense_date'])) : 'Chưa xếp ngày') . ' · ' . substr((string) $council['start_time'], 0, 5) . ' · phòng ' . ($council['room'] ?: '—')) ?></p></div></div>
                <ul class="x-plain-list"><?php foreach ($members as $member): ?><li><strong><?= screen_escape(COUNCIL_ROLE_LABELS[$member['member_role']]) ?>:</strong> <?= screen_escape($member['full_name']) ?></li><?php endforeach; ?></ul>
                <div class="x-card-tools"><button type="button" class="table-edit-button" data-dialog-open="council-<?= (int) $council['id'] ?>">Sửa</button><?= $xPost('council_delete', ['council_id' => (int) $council['id']], 'Xóa', 'inline-action', 'Xóa hội đồng này?') ?></div>
                <dialog class="modal modal--wide" id="council-<?= (int) $council['id'] ?>" aria-label="Sửa hội đồng"><?= $councilForm($council, $members) ?></dialog>
            </article>
        <?php endforeach; ?>
        <?php if (!$screenData['councils']): ?><?= x_empty('Chưa có hội đồng nào.') ?><?php endif; ?>
    </div>
</section>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Ghép GV hướng dẫn (đề tài SV đề xuất), phản biện, hội đồng · cảnh báo xung đột lợi ích</p><h2>Đồ án trong đợt</h2></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Sinh viên</th><th>Đề tài</th><th>Hướng dẫn</th><th>Phản biện</th><th>Hội đồng · giờ</th><th>Phiếu chấm</th><th>Điểm</th><th></th></tr></thead>
        <tbody><?php foreach ($screenData['theses'] as $thesis): $thesisId = (int) $thesis['id']; ?>
            <tr><td><?= screen_escape($thesis['full_name'] . ' · ' . $thesis['student_code']) ?></td><td><?= screen_escape($thesis['topic_title']) ?><?= (int) $thesis['final_submissions'] ? '' : ' ' . x_badge('Chưa nộp quyển', 'badge--waiting') ?></td>
                <td><?= screen_escape($thesis['supervisor_name'] ?? '—') ?></td><td><?= screen_escape($thesis['reviewer_name'] ?? '—') ?></td>
                <td><?= screen_escape(($thesis['council_name'] ?? '—') . ($thesis['defense_at'] ? ' · ' . thesis_datetime_label($thesis['defense_at']) : '')) ?></td>
                <td><?= (int) $thesis['score_count'] ?></td><td><?= $thesis['final_score'] !== null ? screen_escape($thesis['final_score']) : '—' ?></td>
                <td class="row-actions x-row-actions"><button type="button" class="table-edit-button" data-dialog-open="staff-<?= $thesisId ?>">Phân công</button><a class="table-edit-button" href="?export=minutes&amp;id=<?= $thesisId ?>" target="_blank">Biên bản</a>
                    <dialog class="modal" id="staff-<?= $thesisId ?>" aria-label="Phân công">
                        <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="thesis_staff_update"><input type="hidden" name="thesis_id" value="<?= $thesisId ?>">
                            <header class="modal-head"><div><p class="eyebrow">Phân công giảng viên & lịch bảo vệ</p><h2><?= screen_escape($thesis['full_name']) ?></h2><p class="modal-sub"><?= screen_escape($thesis['topic_title']) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                            <label>Giảng viên hướng dẫn<select name="supervisor_id"><?= $lecturerOptions($thesis['supervisor_id']) ?></select></label>
                            <label>Giảng viên phản biện<select name="reviewer_id"><?= $lecturerOptions($thesis['reviewer_id']) ?></select></label>
                            <label>Hội đồng<select name="council_id"><option value="">Chưa xếp</option><?php foreach ($screenData['councils'] as $council): ?><option value="<?= (int) $council['id'] ?>" <?= (int) $thesis['council_id'] === (int) $council['id'] ? 'selected' : '' ?>><?= screen_escape($council['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Thời gian bảo vệ<input type="datetime-local" name="defense_at" value="<?= screen_escape(x_input_datetime($thesis['defense_at'])) ?>"></label>
                            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu</button></div>
                        </form>
                    </dialog>
                </td></tr>
        <?php endforeach; ?>
        <?php if (!$screenData['theses']): ?><tr><td colspan="8"><?= x_empty('Chưa có đồ án nào trong đợt.') ?></td></tr><?php endif; ?></tbody>
    </table></div>
</section>

<div class="x-split">
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">Hạn mức hướng dẫn mỗi đợt · trưởng bộ môn duyệt đề tài của bộ môn</p><h2>Tải giảng viên</h2></div></div>
        <div class="table-scroll"><table class="x-compact">
            <thead><tr><th style="min-width: 200px">Giảng viên</th><th>Hướng dẫn</th><th>Phản biện</th><th>Hạn mức HD (đợt) · PB · vai trò</th></tr></thead>
            <tbody><?php foreach ($screenData['lecturers'] as $lecturer): $lecturerId = (int) $lecturer['id']; $load = (int) ($screenData['load'][$lecturerId] ?? 0); ?>
                <tr><td><?= screen_escape($lecturer['full_name']) ?><br><small class="x-meta"><?= screen_escape(mb_strimwidth((string) $lecturer['research_areas'], 0, 70, '…', 'UTF-8')) ?></small></td>
                    <?php $quota = (int) ($screenData['quotas'][$lecturerId] ?? $lecturer['max_students']); $reviews = (int) ($screenData['review_load'][$lecturerId] ?? 0); ?><td><?= $load ?>/<?= $quota ?><?= $load > $quota ? ' ' . x_badge('Vượt hạn mức', 'badge--attention') : '' ?></td><td><?= $reviews ?>/<?= (int) $lecturer['max_reviews'] ?><?= $reviews > (int) $lecturer['max_reviews'] ? ' ' . x_badge('Quá tải', 'badge--attention') : '' ?></td>
                    <td><form method="post" action="<?= screen_escape($xAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="lecturer_quota_save"><input type="hidden" name="lecturer_id" value="<?= $lecturerId ?>"><input type="hidden" name="batch_id" value="<?= $batchId ?>"><input class="x-small-input" type="number" name="max_students" min="0" max="50" value="<?= $quota ?>" aria-label="Hạn mức hướng dẫn của đợt" title="Hướng dẫn (đợt này)"><input class="x-small-input" type="number" name="max_reviews" min="0" max="50" value="<?= (int) $lecturer['max_reviews'] ?>" aria-label="Hạn mức phản biện" title="Phản biện"><label class="x-check"><input type="checkbox" name="is_head" value="1" <?= (int) $lecturer['is_head'] ? 'checked' : '' ?>> Trưởng BM</label><button type="submit">Lưu</button></form></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </section>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Đánh giá lại điểm</p><h2>Phúc khảo</h2></div></div>
        <?php foreach ($screenData['regrades'] as $request): ?>
            <article class="x-card">
                <div class="x-card-head"><div><h3><?= screen_escape($request['full_name']) ?></h3><p class="x-meta"><?= screen_escape($request['topic_title'] . ' · điểm hiện tại ' . ($request['final_score'] ?? '—') . ' · ' . thesis_datetime_label($request['created_at'])) ?></p></div><?= x_badge(['pending' => 'Chờ xử lý', 'accepted' => 'Đã chấp nhận', 'rejected' => 'Đã từ chối'][$request['status']]) ?></div>
                <p class="x-text"><?= screen_escape($request['reason']) ?></p>
                <?php if ($request['response']): ?><p class="x-meta">Phản hồi: <?= screen_escape($request['response']) ?></p><?php endif; ?>
                <?php if ($request['status'] === 'pending'): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="regrade_handle"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                        <select name="decision"><option value="accept">Chấp nhận · mở lại phiếu chấm</option><option value="reject">Từ chối</option></select>
                        <select name="reopen_role"><option value="council">Phiếu hội đồng</option><option value="reviewer">Phiếu phản biện</option><option value="supervisor">Phiếu hướng dẫn</option><option value="all">Tất cả phiếu</option></select>
                        <input name="response" maxlength="3000" placeholder="Phản hồi cho sinh viên"><button class="button button--primary" type="submit">Lưu</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$screenData['regrades']): ?><?= x_empty('Không có yêu cầu phúc khảo.') ?><?php endif; ?>
    </section>
</div>
<?php endif; ?>
