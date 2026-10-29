<?php
// Quản trị · Thư viện rubric: tiêu chí, trọng số, điểm tối đa và mô tả từng mức; dùng lại giữa các đợt.
require __DIR__ . '/_common.php';
$appliesLabels = ['any' => 'Mọi vai trò', 'supervisor' => 'Đồ án · hướng dẫn', 'reviewer' => 'Đồ án · phản biện', 'council' => 'Đồ án · hội đồng', 'internship_company' => 'Thực tập · doanh nghiệp đánh giá', 'internship_lecturer' => 'Thực tập · giảng viên đánh giá'];
$rubricForm = static function (?array $rubric) use ($xAction, $csrfField, $appliesLabels): string {
    $lines = $rubric ? implode("\n", array_map(static fn($criterion) => $criterion['name'] . ' | ' . (float) $criterion['weight'] . ' | ' . (float) $criterion['max_score'] . ($criterion['levels'] ? ' | ' . $criterion['levels'] : ''), $rubric['criteria'])) : "Nội dung và kết quả | 4 | 10 | Xuất sắc 9-10; Tốt 7-8; Đạt 5-6; Chưa đạt <5\nPhương pháp, công nghệ | 2 | 10\nTrình bày quyển | 2 | 10\nThái độ, chuyên cần | 2 | 10";
    ob_start(); ?>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="rubric_save"><input type="hidden" name="rubric_id" value="<?= (int) ($rubric['id'] ?? 0) ?>">
        <header class="modal-head"><div><p class="eyebrow"><?= $rubric ? 'Chỉnh sửa rubric' : 'Tạo rubric mới' ?></p><h2><?= $rubric ? screen_escape($rubric['name']) : 'Rubric chấm điểm' ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
        <div class="form-grid"><label>Tên rubric<input name="name" maxlength="200" value="<?= screen_escape($rubric['name'] ?? '') ?>" required></label>
            <label>Dùng cho<select name="applies_to"><?php foreach ($appliesLabels as $key => $label): ?><option value="<?= $key ?>" <?= ($rubric['applies_to'] ?? 'any') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label></div>
        <label>Mô tả<textarea name="description" rows="2" maxlength="2000"><?= screen_escape($rubric['description'] ?? '') ?></textarea></label>
        <label>Tiêu chí (mỗi dòng: Tên | trọng số | điểm tối đa | mô tả các mức)<textarea name="criteria" rows="8" maxlength="20000" required><?= screen_escape($lines) ?></textarea></label>
        <p class="form-hint">Điểm phiếu chấm (thang 10) = trung bình có trọng số của điểm từng tiêu chí / điểm tối đa. Rubric đã dùng để chấm thì không sửa tiêu chí được, hãy nhân bản.</p>
        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu rubric</button></div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<button type="button" class="action-trigger" data-dialog-open="rubric-new"><span>+ Tạo rubric</span><small>Tiêu chí, trọng số, mức điểm</small></button>
<dialog class="modal modal--wide" id="rubric-new" aria-label="Tạo rubric"><?= $rubricForm(null) ?></dialog>
<section class="x-card-list">
    <?php foreach ($screenData['rubrics'] as $rubric): $rubricId = (int) $rubric['id']; $totalWeight = array_sum(array_map(static fn($criterion) => (float) $criterion['weight'], $rubric['criteria'])) ?: 1; ?>
        <article class="x-card">
            <div class="x-card-head"><div><h3><?= screen_escape($rubric['name']) ?></h3><p class="x-meta"><?= screen_escape($appliesLabels[$rubric['applies_to']] ?? $rubric['applies_to']) ?> · <?= count($rubric['criteria']) ?> tiêu chí · đang dùng ở <?= (int) $rubric['used_by'] ?> đợt</p></div></div>
            <?php if ($rubric['description']): ?><p class="x-text"><?= screen_escape($rubric['description']) ?></p><?php endif; ?>
            <div class="table-scroll"><table class="x-compact"><thead><tr><th>Tiêu chí</th><th>Trọng số</th><th>Tỷ lệ</th><th>Điểm tối đa</th><th>Mô tả các mức</th></tr></thead><tbody>
                <?php foreach ($rubric['criteria'] as $criterion): ?><tr><td><?= screen_escape($criterion['name']) ?></td><td><?= (float) $criterion['weight'] ?></td><td><?= round((float) $criterion['weight'] * 100 / $totalWeight) ?>%</td><td><?= (float) $criterion['max_score'] ?></td><td><?= screen_escape($criterion['levels'] ?? '') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="x-card-tools"><button type="button" class="table-edit-button" data-dialog-open="rubric-<?= $rubricId ?>">Sửa</button><?= $xPost('rubric_copy', ['rubric_id' => $rubricId], 'Nhân bản') ?><?= $xPost('rubric_delete', ['rubric_id' => $rubricId], 'Xóa', 'inline-action', 'Xóa rubric này?') ?></div>
            <dialog class="modal modal--wide" id="rubric-<?= $rubricId ?>" aria-label="Sửa rubric"><?= $rubricForm($rubric) ?></dialog>
        </article>
    <?php endforeach; ?>
    <?php if (!$screenData['rubrics']): ?><?= x_empty('Thư viện chưa có rubric.') ?><?php endif; ?>
</section>
