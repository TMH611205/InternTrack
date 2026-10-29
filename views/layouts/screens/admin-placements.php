<?php
// Quản trị · Thực tập tự tìm: xét duyệt đơn vị do sinh viên khai báo; duyệt sẽ tạo kỳ thực tập.
require __DIR__ . '/_common.php';
$allocation = $screenData['allocation_batch'] ?? null;
$run = $screenData['allocation_run'] ?? null;
?>
<?php if ($allocation): ?>
<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow"><?= screen_escape($allocation['name']) ?> · <?= (int) $screenData['allocation_stats']['students'] ?> sinh viên đã đăng ký nguyện vọng</p><h2>Phân bổ thực tập theo nguyện vọng</h2></div>
        <?= $xPost('internship_allocation_run', ['batch_id' => (int) $allocation['id']], 'Chạy thử phân bổ', 'inline-action', null, 'x-strong') ?></div>
    <?php if ($run): ?>
        <div class="chip-row">
            <span class="chip chip--active">Được xếp: <?= (int) ($run['metrics']['matched'] ?? 0) ?></span>
            <span class="chip">Chưa xếp: <?= (int) ($run['metrics']['unmatched'] ?? 0) ?></span>
            <span class="chip">NV1: <?= $run['metrics']['rank_rate']['1'] ?? 0 ?>%</span>
            <span class="chip"><?= $run['status'] === 'published' ? 'Đã công bố ' . screen_escape(thesis_datetime_label($run['published_at'])) : 'Xem trước · chưa công bố' ?></span>
        </div>
        <details class="x-details"><summary>Xem kết quả (<?= count($run['result']) ?> sinh viên)</summary>
            <div class="table-scroll"><table class="x-compact"><thead><tr><th>Sinh viên</th><th>Vị trí được xếp</th><th>Nguyện vọng</th></tr></thead><tbody>
                <?php foreach ($run['result'] as $row): ?><tr><td><?= screen_escape($row['name']) ?></td><td><?= $row['topic_title'] ? screen_escape($row['topic_title']) : x_badge('Chưa xếp được', 'badge--attention') ?></td><td><?= $row['rank'] ? 'NV' . (int) $row['rank'] : '—' ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </details>
        <?php if ($run['status'] === 'preview'): ?><?= $xPost('internship_allocation_publish', ['run_id' => (int) $run['id']], 'Công bố & tạo kỳ thực tập', 'inline-action', 'Công bố sẽ tạo kỳ thực tập cho sinh viên được xếp và báo cho doanh nghiệp. Tiếp tục?', 'x-strong') ?><?php endif; ?>
    <?php else: ?>
        <p class="form-hint">Sinh viên xếp tối đa <?= INTERNSHIP_MAX_PREFERENCES ?> nguyện vọng vị trí trong mốc ứng tuyển. Thuật toán Gale-Shapley xếp theo nguyện vọng, ưu tiên GPA và độ khớp kỹ năng với yêu cầu vị trí.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($screenData['change_requests'])): ?>
<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Duyệt sẽ hủy kỳ thực tập hiện tại; sinh viên đổi đơn vị được ứng tuyển / khai báo lại</p><h2>Yêu cầu đổi / rút đơn vị</h2></div></div>
    <?php foreach ($screenData['change_requests'] as $request): ?>
        <div class="x-row-line"><span><strong><?= screen_escape($request['student_name']) ?></strong> · <?= screen_escape(CHANGE_REQUEST_LABELS[$request['request_type']]) ?> · <?= screen_escape($request['company_name'] . ' · ' . $request['position_title']) ?><br><small class="x-meta"><?= screen_escape($request['reason']) ?><?= $request['response'] ? ' · Phản hồi: ' . screen_escape($request['response']) : '' ?></small></span>
            <?php if ($request['status'] === 'pending'): ?>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="change_request_handle"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input name="response" maxlength="2000" placeholder="Phản hồi"><button class="button" name="decision" value="reject" type="submit">Từ chối</button><button class="button button--primary" name="decision" value="approve" type="submit">Duyệt</button></form>
            <?php else: ?><?= x_badge($request['status'] === 'approved' ? 'Đã duyệt' : 'Đã từ chối') ?><?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<section class="x-card-list">
    <?php foreach ($screenData['placements'] as $placement): $placementId = (int) $placement['id']; ?>
        <article class="x-card">
            <div class="x-card-head"><div><h3><?= screen_escape($placement['company_name']) ?></h3><p class="x-meta"><?= screen_escape($placement['full_name'] . ' · ' . $placement['student_code'] . ' · ' . ($placement['major'] ?: '') . ' · gửi ' . thesis_datetime_label($placement['created_at'])) ?></p></div>
                <?= x_badge(['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'][$placement['status']]) ?></div>
            <dl class="x-kv">
                <dt>Vị trí</dt><dd><?= screen_escape($placement['position_title']) ?> · <?= page_date($placement['start_date']) ?> – <?= page_date($placement['end_date']) ?></dd>
                <dt>Địa chỉ</dt><dd><?= screen_escape($placement['address']) ?><?= $placement['tax_code'] ? ' · MST ' . screen_escape($placement['tax_code']) : '' ?></dd>
                <dt>Người hướng dẫn tại đơn vị</dt><dd><?= screen_escape(implode(' · ', array_filter([$placement['contact_name'], $placement['contact_phone'], $placement['contact_email']]))) ?></dd>
                <?php if ($placement['description']): ?><dt>Mô tả công việc</dt><dd><?= screen_escape($placement['description']) ?></dd><?php endif; ?>
                <?php if ($placement['review_note']): ?><dt>Nhận xét</dt><dd><?= screen_escape($placement['review_note']) ?></dd><?php endif; ?>
            </dl>
            <?php if ($placement['file_path']): ?><a class="table-download" href="?download=placement&amp;id=<?= $placementId ?>">Giấy xác nhận tiếp nhận ↓</a><?php endif; ?>
            <?php if ($placement['status'] === 'pending'): ?>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="placement_review"><input type="hidden" name="placement_id" value="<?= $placementId ?>">
                    <select name="decision"><option value="approve">Duyệt · tạo kỳ thực tập</option><option value="reject">Từ chối</option></select>
                    <select name="lecturer_id"><option value="">GV theo dõi (chọn sau)</option><?php foreach ($screenData['lecturers'] as $lecturer): ?><option value="<?= (int) $lecturer['id'] ?>"><?= screen_escape($lecturer['full_name']) ?></option><?php endforeach; ?></select>
                    <select name="batch_id"><option value="">Đợt đang mở của sinh viên</option><?php foreach ($screenData['batches'] as $batch): ?><option value="<?= (int) $batch['id'] ?>"><?= screen_escape($batch['name']) ?></option><?php endforeach; ?></select>
                    <input name="review_note" maxlength="2000" placeholder="Nhận xét"><button class="button button--primary" type="submit">Lưu</button>
                </form>
            <?php elseif ($placement['internship_id']): ?><p class="x-meta">Đã tạo kỳ thực tập #<?= (int) $placement['internship_id'] ?> (xem ở mục Kỳ thực tập).</p><?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$screenData['placements']): ?><?= x_empty('Chưa có khai báo nào.') ?><?php endif; ?>
</section>
