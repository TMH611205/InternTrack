<?php
// Quản trị · Thống kê & báo cáo: tiến độ đợt đồ án, phân bố điểm, mức thỏa mãn nguyện vọng, tải giảng viên, doanh nghiệp, so sánh các đợt; xuất Excel/PDF.
require __DIR__ . '/_common.php';
$batch = $screenData['batch'] ?? null;
$batchQuery = $batch ? '&amp;batch=' . (int) $batch['id'] : '';
?>
<div class="x-export-bar">
    <strong>Xuất dữ liệu:</strong>
    <a class="table-edit-button" href="?export=statistics">So sánh các đợt (Excel)</a>
    <a class="table-edit-button" href="?export=statistics_major">Theo ngành (Excel)</a>
    <?php if ($batch): ?>
        <a class="table-edit-button" href="?export=thesis_scores<?= $batchQuery ?>">Bảng điểm đồ án (Excel)</a>
        <a class="table-edit-button" href="?export=assignments<?= $batchQuery ?>" target="_blank">Quyết định phân công (PDF)</a>
        <a class="table-edit-button" href="?export=councils<?= $batchQuery ?>" target="_blank">Danh sách hội đồng (PDF)</a>
    <?php endif; ?>
    <a class="table-edit-button" href="?export=internship_scores">Bảng điểm thực tập (Excel)</a>
    <a class="table-edit-button" href="?export=users">Danh sách tài khoản (Excel)</a>
</div>
<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Có việc làm: doanh nghiệp xác nhận khi đánh giá, kèm số sinh viên tự khai</p><h2>Thống kê theo ngành</h2></div></div>
    <div class="table-scroll"><table class="x-compact">
        <thead><tr><th>Ngành</th><th>SV</th><th>Thực tập</th><th>Điểm TB TT</th><th>Đạt TT</th><th>Có việc làm</th><th>Đồ án</th><th>Điểm TB ĐA</th><th>Đạt ĐA</th></tr></thead>
        <tbody><?php foreach ($screenData['by_major'] as $row): $percent = static fn($part, $whole) => (int) $whole ? round($part * 100 / $whole) . '%' : '—'; ?>
            <tr><td><?= screen_escape($row['major']) ?></td><td><?= (int) $row['students'] ?></td><td><?= (int) $row['internships'] ?></td><td><?= $row['internship_average'] === null ? '—' : round((float) $row['internship_average'], 1) ?></td><td><?= $percent($row['internship_passed'], $row['internship_scored']) ?></td>
                <td><?= (int) $row['offered_confirmed'] ?><?= (int) $row['employed'] > (int) $row['offered_confirmed'] ? ' <small class="x-meta">(+' . ((int) $row['employed'] - (int) $row['offered_confirmed']) . ' tự khai)</small>' : '' ?></td>
                <td><?= (int) $row['theses'] ?></td><td><?= $row['thesis_average'] === null ? '—' : round((float) $row['thesis_average'], 2) ?></td><td><?= $percent($row['thesis_passed'], $row['thesis_scored']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$screenData['by_major']): ?><tr><td colspan="9"><?= x_empty('Chưa có dữ liệu.') ?></td></tr><?php endif; ?></tbody>
    </table></div>
</section>
<?= x_batch_switcher($screenData['batches'], $batch, 'admin/statistics') ?>

<?php if ($batch): ?>
<div class="x-split">
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow"><?= screen_escape($batch['name']) ?></p><h2>Tiến độ nộp báo cáo</h2></div></div>
        <?php foreach ($screenData['stages'] as $stage): $rate = $stage['total'] ? round($stage['submitted'] * 100 / $stage['total']) : 0; ?>
            <div class="x-progress"><div class="x-row-line"><strong><?= screen_escape($stage['label']) ?></strong><span class="x-meta"><?= (int) $stage['submitted'] ?>/<?= (int) $stage['total'] ?> đã nộp · <?= (int) $stage['late'] ?> trễ hạn · <?= screen_escape($stage['window']) ?></span></div><div class="progress-track"><span style="width: <?= $rate ?>%"></span></div></div>
        <?php endforeach; ?>
        <p class="x-meta">Sinh viên đủ điều kiện chưa được ghép: <strong><?= (int) $screenData['unmatched'] ?></strong></p>
    </section>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Theo thang 10</p><h2>Phân bố điểm đồ án</h2></div></div>
        <?= x_bars($screenData['score_bins'], ' SV') ?>
        <h4 class="x-subtitle">Mức thỏa mãn nguyện vọng</h4>
        <?= x_bars($screenData['rank_bins'], ' SV') ?>
    </section>
</div>

<div class="x-split">
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">Đề tài nhiều / ít người chọn</p><h2>Mức quan tâm đề tài</h2></div></div>
        <div class="table-scroll"><table class="x-compact"><thead><tr><th>Đề tài</th><th>GV</th><th>Chỗ</th><th>Lượt NV</th><th>NV1</th></tr></thead><tbody>
            <?php foreach ($screenData['topic_popularity'] as $topic): ?><tr><td><?= screen_escape($topic['title']) ?></td><td><?= screen_escape($topic['lecturer_name'] ?? '') ?></td><td><?= (int) $topic['max_students'] ?></td><td><?= (int) $topic['total'] ?><?= (int) $topic['total'] === 0 ? ' ' . x_badge('Không ai chọn', 'badge--attention') : '' ?></td><td><?= (int) $topic['first'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">Hướng dẫn · phản biện · hội đồng</p><h2>Tải giảng viên</h2></div></div>
        <div class="table-scroll"><table class="x-compact"><thead><tr><th>Giảng viên</th><th>Hướng dẫn</th><th>Phản biện</th><th>Hội đồng</th><th>Điểm TB SV</th></tr></thead><tbody>
            <?php foreach ($screenData['lecturer_load'] as $lecturer): ?><tr><td><?= screen_escape($lecturer['full_name']) ?></td><td><?= (int) $lecturer['supervising'] ?>/<?= (int) $lecturer['max_students'] ?></td><td><?= (int) $lecturer['reviewing'] ?></td><td><?= (int) $lecturer['councils'] ?></td><td><?= $lecturer['average'] !== null ? round((float) $lecturer['average'], 2) : '—' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<?php endif; ?>

<div class="x-split">
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">Số sinh viên, điểm, mức hài lòng, tỷ lệ được giữ lại</p><h2>Thực tập theo doanh nghiệp</h2></div></div>
        <div class="table-scroll"><table class="x-compact"><thead><tr><th>Doanh nghiệp</th><th>MoU</th><th>SV</th><th>Điểm TB</th><th>Hài lòng</th><th>Được giữ lại</th></tr></thead><tbody>
            <?php foreach ($screenData['company_stats'] as $company): ?><tr><td><?= screen_escape($company['company_name']) ?></td><td><?= screen_escape(['none' => '—', 'negotiating' => 'Đàm phán', 'signed' => 'Đã ký', 'expired' => 'Hết hạn'][$company['mou_status']] ?? '') ?></td><td><?= (int) $company['interns'] ?></td><td><?= $company['average'] !== null ? round((float) $company['average'], 1) : '—' ?></td><td><?= $company['rating'] !== null ? round((float) $company['rating'], 1) . '/5' : '—' ?></td><td><?= (int) $company['reviews'] ? (int) $company['offers'] . '/' . (int) $company['reviews'] : '—' ?></td></tr><?php endforeach; ?>
            <?php if (!$screenData['company_stats']): ?><tr><td colspan="6"><?= x_empty('Chưa có dữ liệu thực tập.') ?></td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="panel table-panel">
        <div class="table-toolbar"><div><p class="eyebrow">So sánh giữa các năm</p><h2>Thực tập theo năm</h2></div></div>
        <div class="table-scroll"><table class="x-compact"><thead><tr><th>Năm</th><th>Kỳ thực tập</th><th>Điểm TB (100)</th><th>Tỷ lệ đạt</th></tr></thead><tbody>
            <?php foreach ($screenData['internship_years'] as $year): ?><tr><td><?= (int) $year['year'] ?></td><td><?= (int) $year['total'] ?></td><td><?= $year['average'] !== null ? round((float) $year['average'], 1) : '—' ?></td><td><?= (int) $year['scored'] ? round((int) $year['passed'] * 100 / (int) $year['scored']) . '%' : '—' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">So sánh giữa các đợt / năm học</p><h2>Tổng hợp các đợt</h2></div></div>
    <div class="table-scroll"><table><thead><tr><th>Đợt</th><th>Loại</th><th>Năm học</th><th>Đề tài</th><th>Sinh viên</th><th>Tỷ lệ NV1</th><th>Điểm TB</th><th>Tỷ lệ đạt</th><th>Bài nộp trễ</th></tr></thead><tbody>
        <?php foreach ($screenData['comparison'] as $row): ?><tr><td><?= screen_escape($row['name']) ?></td><td><?= $row['batch_type'] === 'thesis' ? 'Đồ án' : 'Thực tập' ?></td><td><?= screen_escape($row['school_year'] ?? '—') ?></td><td><?= (int) $row['topics'] ?></td><td><?= (int) $row['students'] ?></td><td><?= $row['batch_type'] === 'thesis' ? $row['first_rate'] . '%' : '—' ?></td><td><?= $row['average'] ?: '—' ?></td><td><?= $row['pass_rate'] ?>%</td><td><?= (int) $row['late'] ?></td></tr><?php endforeach; ?>
        <?php if (!$screenData['comparison']): ?><tr><td colspan="9"><?= x_empty('Chưa có đợt nào.') ?></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
