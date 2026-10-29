<?php
// Quản trị · Ghép cặp sinh viên – đề tài: tham số, chạy thử, so sánh thuật toán, xem trước, công bố, ghép thủ công, khóa cặp, lịch sử.
require __DIR__ . '/_common.php';
$batch = $screenData['batch'] ?? null;
$params = $screenData['params'];
$run = $screenData['current_run'] ?? null;
$metricCells = static function (array $metrics): array {
    return [
        ($metrics['rank_rate']['1'] ?? 0) . '%',
        ($metrics['rank_rate']['2'] ?? 0) . '%',
        ($metrics['rank_rate']['3'] ?? 0) . '%',
        (string) ($metrics['unmatched'] ?? 0),
        (string) ($metrics['average_rank'] ?? '—'),
        (string) ($metrics['blocking_pairs'] ?? 0),
        ($metrics['fill_rate'] ?? 0) . '%',
    ];
};
?>
<?= x_batch_switcher($screenData['batches'], $batch, 'admin/matching') ?>
<?php if (!$batch): ?>
    <?= x_empty('Chưa có đợt đồ án. Hãy tạo đợt ở mục "Đợt & mốc thời gian".') ?>
<?php else: ?>
<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Tham số ghép · mốc ghép cặp: <?= screen_escape(batch_phase_label($screenData['phase'])) ?></p><h2>Chạy ghép tự động</h2></div></div>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
        <div class="form-grid form-grid--3">
            <label>Thuật toán<select name="algorithm"><?php foreach (MATCHING_ALGORITHMS as $key => $label): ?><option value="<?= $key ?>"><?= screen_escape($label) ?></option><?php endforeach; ?></select></label>
            <label>Phá đồng hạng theo<select name="tie_break"><option value="gpa">GPA cao hơn</option><option value="time">Đăng ký sớm hơn</option><option value="random">Ngẫu nhiên (cố định)</option></select></label>
            <label>Thứ tự Serial Dictatorship<select name="order_by"><option value="gpa">GPA giảm dần</option><option value="time">Thời điểm đăng ký</option></select></label>
            <label>Trọng số GPA<input type="number" name="gpa_weight" step="0.05" min="0" max="1" value="<?= $params['gpa_weight'] ?>"></label>
            <label>Trọng số thời điểm đăng ký<input type="number" name="time_weight" step="0.05" min="0" max="1" value="<?= $params['time_weight'] ?>"></label>
            <label>Trọng số khớp kỹ năng<input type="number" name="skill_weight" step="0.05" min="0" max="1" value="<?= $params['skill_weight'] ?>"></label>
        </div>
        <label class="x-check"><input type="checkbox" name="use_lecturer_rankings" value="1" checked> Ưu tiên thứ hạng giảng viên đã xếp (ghép hai phía)</label>
        <label class="x-check"><input type="checkbox" name="auto_fill" value="1" <?= app_setting('auto_fill_unmatched', '1') === '1' ? 'checked' : '' ?>> Tự xếp sinh viên chưa được ghép vào đề tài còn chỗ (theo kỹ năng và lĩnh vực quan tâm trong hồ sơ)</label>
        <label class="x-check"><input type="checkbox" name="keep_existing" value="1"> Vòng bổ sung: giữ nguyên kết quả đã công bố, chỉ ghép sinh viên chưa có đề tài hoặc đã từ chối</label>
        <p class="form-hint">Tổng số chỗ các đề tài của một giảng viên được giới hạn theo hạn mức hướng dẫn của đợt. Cặp đã khóa và đồ án sinh viên đã xác nhận luôn được giữ nguyên. Chạy thử không thay đổi dữ liệu cho đến khi bấm "Công bố".</p>
        <div class="x-actions-row"><button class="button button--primary" type="submit" name="action" value="matching_run">Chạy thử</button><button class="button" type="submit" name="action" value="matching_compare">So sánh cả 4 thuật toán</button></div>
    </form>
</section>

<?php if ($screenData['compare']): ?>
<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">So sánh kết quả giữa các thuật toán (lần chạy gần nhất của mỗi thuật toán)</p><h2>Chỉ số đánh giá</h2></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Thuật toán</th><th>NV1</th><th>NV2</th><th>NV3</th><th>Chưa ghép</th><th>NV trung bình</th><th>Cặp bất ổn</th><th>Lấp đầy</th><th></th></tr></thead>
        <tbody><?php foreach ($screenData['compare'] as $algorithm => $compareRun): ?>
            <tr><td><?= screen_escape(MATCHING_ALGORITHMS[$algorithm] ?? $algorithm) ?></td><?php foreach ($metricCells($compareRun['metrics']) as $cell): ?><td><?= screen_escape($cell) ?></td><?php endforeach; ?><td><a class="table-edit-button" href="?page=admin/matching&amp;batch=<?= (int) $batch['id'] ?>&amp;run=<?= (int) $compareRun['id'] ?>">Xem</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <p class="form-hint">Cặp bất ổn (blocking pair): sinh viên thích đề tài khác hơn kết quả, và đề tài đó còn chỗ hoặc ưu tiên sinh viên này hơn người đang giữ chỗ. Gale-Shapley sinh viên đề xuất cho 0 cặp bất ổn khi không có nhóm.</p>
</section>
<?php endif; ?>

<?php if ($run): $metrics = $run['metrics']; ?>
<section class="panel table-panel">
    <div class="table-toolbar">
        <div><p class="eyebrow">Lần chạy #<?= (int) $run['id'] ?> · <?= screen_escape(thesis_datetime_label($run['created_at'])) ?> · <?= screen_escape($run['creator'] ?? '') ?></p><h2><?= screen_escape(MATCHING_ALGORITHMS[$run['algorithm']] ?? $run['algorithm']) ?> <?= x_badge(['preview' => 'Xem trước', 'published' => 'Đã công bố', 'discarded' => 'Đã bỏ'][$run['status']], $run['status'] === 'published' ? 'badge--positive' : 'badge--waiting') ?></h2></div>
        <div class="x-actions-row">
            <?php if ($run['status'] === 'preview'): ?>
                <?= $xPost('matching_publish', ['run_id' => (int) $run['id']], 'Công bố kết quả', 'inline-action', 'Công bố sẽ thay các cặp chưa chốt bằng kết quả này và gửi thông báo cho sinh viên. Tiếp tục?', 'x-strong') ?>
                <?= $xPost('matching_discard', ['run_id' => (int) $run['id']], 'Bỏ lần chạy') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="chip-row">
        <span class="chip">Ứng viên: <?= (int) ($metrics['applicants'] ?? 0) ?> (<?= (int) ($metrics['students'] ?? 0) ?> SV)</span>
        <span class="chip chip--active">Được ghép: <?= (int) ($metrics['matched'] ?? 0) ?></span>
        <span class="chip">NV1: <?= $metrics['rank_rate']['1'] ?? 0 ?>% · NV2: <?= $metrics['rank_rate']['2'] ?? 0 ?>% · NV3: <?= $metrics['rank_rate']['3'] ?? 0 ?>%</span>
        <span class="chip">Không có nguyện vọng: <?= (int) ($metrics['no_prefs'] ?? 0) ?></span>
        <span class="chip">Cặp bất ổn: <?= (int) ($metrics['blocking_pairs'] ?? 0) ?></span>
        <span class="chip">Giữ nguyên (đã chốt): <?= (int) ($metrics['fixed'] ?? 0) ?></span>
        <span class="chip">Thời gian chạy: <?= (int) ($metrics['elapsed_ms'] ?? 0) ?> ms</span>
    </div>
    <p class="form-hint">Tham số: GPA <?= screen_escape($run['params']['gpa_weight'] ?? '') ?> · thời điểm <?= screen_escape($run['params']['time_weight'] ?? '') ?> · kỹ năng <?= screen_escape($run['params']['skill_weight'] ?? '') ?> · đồng hạng theo <?= screen_escape($run['params']['tie_break'] ?? '') ?><?= !empty($run['params']['use_lecturer_rankings']) ? ' · có dùng thứ hạng giảng viên' : '' ?><?= !empty($run['params']['keep_existing']) ? ' · vòng bổ sung' : '' ?></p>
    <div class="table-scroll"><table>
        <thead><tr><th>Sinh viên / nhóm</th><th>Số NV</th><th>Đề tài được ghép</th><th>Đúng NV</th></tr></thead>
        <tbody><?php foreach ($run['result'] as $row): ?>
            <tr><td><?= screen_escape($row['name']) ?></td><td><?= (int) $row['prefs'] ?></td><td><?php if ($run['status'] === 'preview'): ?><form method="post" action="<?= screen_escape($xAction) ?>&amp;run=<?= (int) $run['id'] ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="matching_run_edit"><input type="hidden" name="run_id" value="<?= (int) $run['id'] ?>"><input type="hidden" name="key" value="<?= screen_escape($row['key']) ?>"><select name="topic_id" onchange="this.form.submit()" aria-label="Đề tài"><option value="">— Chưa ghép —</option><?php foreach ($screenData['topics'] as $topicOption): ?><option value="<?= (int) $topicOption['id'] ?>" <?= (int) ($row['topic_id'] ?? 0) === (int) $topicOption['id'] ? 'selected' : '' ?>><?= screen_escape($topicOption['title']) ?></option><?php endforeach; ?></select></form><?php else: ?><?= $row['topic_title'] ? screen_escape($row['topic_title']) : x_badge('Chưa được ghép', 'badge--attention') ?><?php endif; ?></td><td><?= !empty($row['auto']) ? x_badge('Tự xếp', 'badge--active') : '' ?><?= $row['rank'] ? x_badge('NV' . (int) $row['rank'], (int) $row['rank'] === 1 ? 'badge--positive' : 'badge--active') : '—' ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if ($run['status'] === 'preview'): ?>
        <?php // Bảng kéo thả: kéo thẻ sinh viên sang cột đề tài khác để sửa kết quả chạy thử. ?>
        <details class="x-details match-board-wrap">
            <summary>Chỉnh bằng kéo thả</summary>
            <form method="post" action="<?= screen_escape($xAction) ?>&amp;run=<?= (int) $run['id'] ?>" data-match-board-form><?= $csrfField ?><input type="hidden" name="action" value="matching_run_edit"><input type="hidden" name="run_id" value="<?= (int) $run['id'] ?>"><input type="hidden" name="key"><input type="hidden" name="topic_id"></form>
            <div class="match-board">
                <?php $columns = ['' => ['title' => 'Chưa ghép', 'max' => null]]; foreach ($screenData['topics'] as $topicOption) { $columns[(string) $topicOption['id']] = ['title' => $topicOption['title'], 'max' => (int) $topicOption['max_students']]; } ?>
                <?php foreach ($columns as $columnId => $column): $cards = array_filter($run['result'], static fn($row) => (string) ($row['topic_id'] ?? '') === (string) $columnId); ?>
                    <div class="match-column" data-match-column="<?= screen_escape((string) $columnId) ?>">
                        <h4><?= screen_escape($column['title']) ?><?php if ($column['max'] !== null): ?> <small><?= array_sum(array_map(static fn($row) => count($row['student_ids']), $cards)) ?>/<?= $column['max'] ?></small><?php endif; ?></h4>
                        <?php foreach ($cards as $row): ?><div class="match-card" draggable="true" data-match-key="<?= screen_escape($row['key']) ?>"><?= screen_escape($row['name']) ?><?= $row['rank'] ? ' · NV' . (int) $row['rank'] : (!empty($row['auto']) ? ' · tự xếp' : '') ?></div><?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="x-split">
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">Mức quan tâm theo đề tài</p><h2>Đề tài đã duyệt</h2></div></div>
        <div class="table-scroll"><table class="x-compact">
            <thead><tr><th>Đề tài</th><th>GV</th><th>Chỗ</th><th>NV</th><th>NV1</th><th>Đã ghép</th></tr></thead>
            <tbody><?php foreach ($screenData['topics'] as $topic): ?>
                <tr><td><?= screen_escape($topic['title']) ?></td><td><?= screen_escape($topic['lecturer_name'] ?? '') ?></td><td><?= (int) $topic['max_students'] ?></td><td><?= (int) $topic['pref_total'] ?></td><td><?= (int) $topic['pref_first'] ?></td><td><?= (int) $topic['assigned'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </section>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Ghép thủ công · <?= count($screenData['unassigned']) ?> sinh viên chưa có đề tài</p><h2>Chọn tay một cặp</h2></div></div>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="thesis_assign_manual">
            <label>Sinh viên<select name="student_id" required><?php foreach ($screenData['unassigned'] as $student): ?><option value="<?= (int) $student['id'] ?>"><?= screen_escape($student['full_name'] . ' · ' . $student['student_code'] . ' · GPA ' . ($student['gpa'] ?? '—') . ' · ' . (int) $student['pref_count'] . ' NV') ?></option><?php endforeach; ?></select></label>
            <label>Đề tài<select name="topic_id" required><?php foreach ($screenData['topics'] as $topic): ?><option value="<?= (int) $topic['id'] ?>" <?= (int) $topic['assigned'] >= (int) $topic['max_students'] ? 'disabled' : '' ?>><?= screen_escape($topic['title'] . ' (' . (int) $topic['assigned'] . '/' . (int) $topic['max_students'] . ')') ?></option><?php endforeach; ?></select></label>
            <label class="x-check"><input type="checkbox" name="locked" value="1" checked> Khóa cặp này (các lần chạy sau giữ nguyên)</label>
            <button class="button button--primary" type="submit">Ghép</button>
        </form>
    </section>
</div>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Kết quả hiện hành · chỉnh sửa, khóa cặp đã chốt rồi chạy lại phần còn lại</p><h2>Cặp sinh viên – đề tài</h2></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Sinh viên</th><th>Đề tài</th><th>GV hướng dẫn</th><th>Nguồn</th><th>Trạng thái</th><th>Khóa</th><th></th></tr></thead>
        <tbody><?php foreach ($screenData['theses'] as $thesis): ?>
            <tr><td><?= screen_escape($thesis['full_name'] . ' · ' . $thesis['student_code']) ?></td><td><?= screen_escape($thesis['topic_title']) ?></td><td><?= screen_escape($thesis['supervisor_name'] ?? '—') ?></td>
                <td><?= screen_escape(['auto' => 'Tự động' . ($thesis['matched_rank'] ? ' · NV' . $thesis['matched_rank'] : ''), 'manual' => 'Thủ công', 'proposal' => 'SV đề xuất'][$thesis['source']]) ?></td>
                <td><?= x_badge(THESIS_STATUS_LABELS[$thesis['status']] ?? $thesis['status']) ?></td><td><?= (int) $thesis['locked'] ? '🔒' : '—' ?></td>
                <td class="row-actions x-row-actions"><?= $xPost('thesis_lock', ['thesis_id' => (int) $thesis['id']], (int) $thesis['locked'] ? 'Mở khóa' : 'Khóa') ?><?= $xPost('thesis_unassign', ['thesis_id' => (int) $thesis['id']], 'Gỡ', 'inline-action', 'Gỡ cặp ghép này?') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$screenData['theses']): ?><tr><td colspan="7"><?= x_empty('Chưa có cặp nào được công bố.') ?></td></tr><?php endif; ?></tbody>
    </table></div>
</section>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Lưu lịch sử các lần chạy và tham số</p><h2>Lịch sử chạy ghép</h2></div></div>
    <div class="table-scroll"><table class="x-compact">
        <thead><tr><th>#</th><th>Thời điểm</th><th>Thuật toán</th><th>NV1</th><th>Chưa ghép</th><th>Cặp bất ổn</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody><?php foreach ($screenData['runs'] as $history): ?>
            <tr><td><?= (int) $history['id'] ?></td><td><?= screen_escape(thesis_datetime_label($history['created_at'])) ?></td><td><?= screen_escape(MATCHING_ALGORITHMS[$history['algorithm']] ?? $history['algorithm']) ?></td><td><?= ($history['metrics']['rank_rate']['1'] ?? 0) ?>%</td><td><?= (int) ($history['metrics']['unmatched'] ?? 0) ?></td><td><?= (int) ($history['metrics']['blocking_pairs'] ?? 0) ?></td>
                <td><?= x_badge(['preview' => 'Xem trước', 'published' => 'Đã công bố', 'discarded' => 'Đã bỏ'][$history['status']]) ?></td><td><a class="table-edit-button" href="?page=admin/matching&amp;batch=<?= (int) $batch['id'] ?>&amp;run=<?= (int) $history['id'] ?>">Xem</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>
