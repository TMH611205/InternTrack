<?php
// Doanh nghiệp · Nhật ký thực tập sinh: người hướng dẫn tại đơn vị xác nhận nội dung nhật ký hoặc ghi chú chưa đúng.
require __DIR__ . '/_common.php';
$showAll = $screenData['show_all'];
?>
<div class="x-filter">
    <a class="chip<?= $showAll ? '' : ' chip--active' ?>" href="?page=company/diaries">Chờ xác nhận (<?= (int) $screenData['pending_count'] ?>)</a>
    <a class="chip<?= $showAll ? ' chip--active' : '' ?>" href="?page=company/diaries&amp;view=all">Tất cả</a>
</div>
<section class="x-card-list">
    <?php foreach ($screenData['diaries'] as $diary): $diaryId = (int) $diary['id']; ?>
        <article class="x-card">
            <div class="x-card-head">
                <div><h3><?= screen_escape($diary['student_name']) ?> · <?= page_date($diary['diary_date']) ?></h3><p class="x-meta"><?= screen_escape(($diary['title'] ?: 'Nhật ký thực tập') . ($diary['hours_worked'] !== null ? ' · ' . (float) $diary['hours_worked'] . ' giờ' : '')) ?></p></div>
                <?= match ($diary['company_status']) {
                    'confirmed' => x_badge('Đã xác nhận', 'badge--positive'),
                    'flagged' => x_badge('Đã ghi chú', 'badge--attention'),
                    default => x_badge('Chờ xác nhận', 'badge--waiting'),
                } ?>
            </div>
            <p class="x-text"><?= screen_escape($diary['content']) ?></p>
            <?php if ($diary['company_note']): ?><p class="x-meta">Ghi chú của đơn vị: <?= screen_escape($diary['company_note']) ?></p><?php endif; ?>
            <div class="x-card-tools">
                <?= comment_thread_dialog('diary', $diaryId, $screenData['comments'][$diaryId] ?? [], [], $xAction, $csrfField, $diary['student_name'] . ' · ' . page_date($diary['diary_date'])) ?>
            <?php if (!$diary['locked']): ?>
                    <?php if ($diary['company_status'] !== 'confirmed'): ?><?= $xPost('diary_confirm', ['diary_id' => $diaryId, 'decision' => 'confirm'], 'Xác nhận', 'inline-action', null, 'x-strong') ?><?php endif; ?>
                    <button type="button" class="table-edit-button" data-dialog-open="diary-note-<?= $diaryId ?>">Ghi chú chưa đúng</button>
                <dialog class="modal" id="diary-note-<?= $diaryId ?>" aria-label="Ghi chú nhật ký">
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="diary_confirm"><input type="hidden" name="diary_id" value="<?= $diaryId ?>"><input type="hidden" name="decision" value="flag">
                        <header class="modal-head"><div><p class="eyebrow">Nhật ký ngày <?= page_date($diary['diary_date']) ?></p><h2><?= screen_escape($diary['student_name']) ?></h2><p class="modal-sub">Sinh viên và giảng viên theo dõi sẽ nhận được ghi chú này.</p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                        <label>Nội dung chưa đúng với thực tế<textarea name="company_note" rows="3" maxlength="1000" required><?= screen_escape($diary['company_note'] ?? '') ?></textarea></label>
                        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Gửi ghi chú</button></div>
                    </form>
                </dialog>
            <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$screenData['diaries']): ?><?= x_empty($showAll ? 'Thực tập sinh chưa gửi nhật ký nào.' : 'Không có nhật ký nào chờ xác nhận.') ?><?php endif; ?>
</section>
