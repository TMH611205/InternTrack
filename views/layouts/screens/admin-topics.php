<?php
// Quản trị · Duyệt đề tài và kho đề tài các khóa (cũng dùng phần hiển thị thẻ đề tài cho giảng viên).
require __DIR__ . '/_common.php';
$filters = $screenData['filters'];

// Thẻ đề tài: thông tin, mức quan tâm của sinh viên, cảnh báo trùng lặp và form duyệt.
$topicCard = static function (array $topic, bool $canReview, bool $canDelete = false) use ($xAction, $csrfField, $xPost): string {
    ob_start(); ?>
    <article class="x-card">
        <div class="x-card-head">
            <div><h3><?= screen_escape($topic['title']) ?></h3>
                <p class="x-meta"><?= screen_escape(implode(' · ', array_filter([$topic['batch_name'] ?? '', $topic['lecturer_name'] ? 'GV: ' . $topic['lecturer_name'] : 'Chưa có GV', $topic['proposer_name'] ?? null ? 'SV đề xuất: ' . $topic['proposer_name'] : null, $topic['field'] ?: null, 'Tối đa ' . (int) $topic['max_students'] . ' SV']))) ?></p></div>
            <?= x_badge(TOPIC_STATUS_LABELS[$topic['status']] ?? $topic['status'], ['approved' => 'badge--positive', 'pending' => 'badge--waiting', 'dept_approved' => 'badge--active', 'revision' => 'badge--waiting', 'rejected' => 'badge--attention', 'draft' => 'badge--neutral'][$topic['status']] ?? null) ?>
        </div>
        <?php if (!empty($topic['description'])): ?><p class="x-text"><?= screen_escape(mb_strimwidth((string) $topic['description'], 0, 320, '…', 'UTF-8')) ?></p><?php endif; ?>
        <dl class="x-kv">
            <?php if (!empty($topic['objectives'])): ?><dt>Mục tiêu</dt><dd><?= screen_escape($topic['objectives']) ?></dd><?php endif; ?>
            <?php if (!empty($topic['requirements'])): ?><dt>Yêu cầu kiến thức</dt><dd><?= screen_escape($topic['requirements']) ?></dd><?php endif; ?>
            <?php if (!empty($topic['technologies'])): ?><dt>Công nghệ</dt><dd><?= screen_escape($topic['technologies']) ?></dd><?php endif; ?>
            <dt>Quan tâm</dt><dd><?= (int) ($topic['pref_total'] ?? 0) ?> nguyện vọng (<?= (int) ($topic['pref_first'] ?? 0) ?> NV1) · đã ghép <?= (int) ($topic['assigned'] ?? 0) ?>/<?= (int) $topic['max_students'] ?></dd>
            <?php if (!empty($topic['review_note'])): ?><dt>Nhận xét duyệt</dt><dd><?= screen_escape($topic['review_note']) ?></dd><?php endif; ?>
        </dl>
        <?php if (!empty($topic['attachment_path'])): ?><a class="table-download" href="?download=topic&amp;id=<?= (int) $topic['id'] ?>">Tài liệu đính kèm ↓</a><?php endif; ?>
        <?php if (!empty($topic['archive_submission_id'])): ?><a class="table-download" href="?download=archive&amp;id=<?= (int) $topic['archive_submission_id'] ?>">Quyển đồ án đã bảo vệ ↓</a><?php endif; ?>
        <?php if (!empty($topic['similar'])): ?>
            <div class="x-warning"><strong>Có thể trùng lặp:</strong><?php foreach ($topic['similar'] as $similar): ?> "<?= screen_escape($similar['title']) ?>" (<?= screen_escape($similar['batch_name']) ?>, giống <?= (int) $similar['similarity'] ?>%);<?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($canReview && in_array($topic['status'], ['pending', 'dept_approved', 'revision'], true)): ?>
            <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="topic_review"><input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>">
                <select name="decision" aria-label="Kết quả duyệt"><option value="approved">Duyệt</option><option value="revision">Yêu cầu chỉnh sửa</option><option value="rejected">Từ chối</option></select>
                <input name="review_note" maxlength="2000" placeholder="Nhận xét gửi giảng viên / sinh viên">
                <button class="button button--primary" type="submit">Lưu kết quả</button>
            </form>
        <?php endif; ?>
        <?php if ($canDelete): ?><?= $xPost('topic_delete', ['topic_id' => (int) $topic['id']], 'Xóa đề tài', 'inline-action', 'Xóa đề tài này?') ?><?php endif; ?>
    </article>
    <?php return (string) ob_get_clean();
};

if ($screen === 'admin/topics'):
?>
<form method="get" class="x-filter">
    <input type="hidden" name="page" value="admin/topics">
    <label>Đợt<select name="batch"><?php foreach ($screenData['batches'] as $batch): ?><option value="<?= (int) $batch['id'] ?>" <?= (int) ($screenData['batch']['id'] ?? 0) === (int) $batch['id'] ? 'selected' : '' ?>><?= screen_escape($batch['name']) ?></option><?php endforeach; ?></select></label>
    <label>Trạng thái<select name="status"><option value="">Tất cả</option><?php foreach (TOPIC_STATUS_LABELS as $key => $label): ?><option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label>Năm học<select name="year"><option value="">Mọi năm</option><?php foreach ($screenData['years'] as $year): ?><option <?= $filters['year'] === $year ? 'selected' : '' ?>><?= screen_escape($year) ?></option><?php endforeach; ?></select></label>
    <label>Từ khóa<input type="search" name="q" value="<?= screen_escape($filters['q']) ?>" placeholder="Tên đề tài, hướng, công nghệ, giảng viên"></label>
    <label class="x-check"><input type="checkbox" name="scope" value="archive" <?= $filters['archive'] ? 'checked' : '' ?>> Tìm trong kho đề tài mọi khóa</label>
    <button class="button button--primary" type="submit">Lọc</button>
</form>
<section class="x-card-list">
    <?php foreach ($screenData['topics'] as $topic): ?><?= $topicCard($topic, true, true) ?><?php endforeach; ?>
    <?php if (!$screenData['topics']): ?><?= x_empty('Không có đề tài phù hợp bộ lọc.') ?><?php endif; ?>
</section>
<?php endif; ?>
