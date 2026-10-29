<?php
// Doanh nghiệp · Điểm danh thực tập sinh theo ngày và giấy tờ (phiếu tiếp nhận, xác nhận của đơn vị).
require __DIR__ . '/_common.php';
$date = $screenData['date'];
?>
<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Xác nhận chuyên cần · vắng không phép 3 buổi sẽ cảnh báo giảng viên theo dõi</p><h2>Điểm danh ngày <?= date('d/m/Y', (int) strtotime($date)) ?></h2></div>
        <form method="get" class="inline-action"><input type="hidden" name="page" value="company/attendance"><input type="date" name="date" value="<?= screen_escape($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" aria-label="Chọn ngày"></form></div>
    <form method="post" action="<?= screen_escape($xAction) ?>&amp;date=<?= screen_escape($date) ?>"><?= $csrfField ?><input type="hidden" name="action" value="attendance_save"><input type="hidden" name="att_date" value="<?= screen_escape($date) ?>">
        <div class="table-scroll"><table><thead><tr><th>Thực tập sinh</th><th>Vị trí</th><th>Trạng thái</th><th>Ghi chú</th><th>Đã ghi / vắng</th></tr></thead><tbody>
            <?php foreach ($screenData['interns'] as $intern): $internshipId = (int) $intern['internship_id']; ?>
                <tr><td><?= screen_escape($intern['student_name'] . ' · ' . $intern['student_code']) ?></td><td><?= screen_escape($intern['position_title']) ?></td>
                    <td><select name="status[<?= $internshipId ?>]" class="x-select"><option value="">— Chưa điểm danh —</option><?php foreach (ATTENDANCE_LABELS as $key => $label): ?><option value="<?= $key ?>" <?= $intern['att_status'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></td>
                    <td><input class="x-select" name="note[<?= $internshipId ?>]" maxlength="255" value="<?= screen_escape($intern['att_note'] ?? '') ?>"></td>
                    <td><?= (int) $intern['recorded'] ?> buổi · <?= (int) $intern['absences'] ?> vắng<?= (int) $intern['absences'] >= 3 ? ' ' . x_badge('Cần chú ý', 'badge--attention') : '' ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$screenData['interns']): ?><tr><td colspan="5"><?= x_empty('Không có thực tập sinh đang thực tập.') ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($screenData['interns']): ?><div class="table-foot"><button class="button button--primary" type="submit">Lưu điểm danh</button></div><?php endif; ?>
    </form>
</section>

<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Phiếu tiếp nhận, cam kết, xác nhận hoàn thành của đơn vị</p><h2>Giấy tờ thực tập</h2></div></div>
    <?php if ($screenData['interns']): ?>
        <details class="x-details"><summary>In mẫu giấy tờ của từng thực tập sinh</summary>
            <ul class="x-plain-list"><?php foreach ($screenData['interns'] as $intern): $internId = (int) $intern['internship_id']; ?><li class="x-row-line"><span><?= screen_escape($intern['student_name']) ?></span><span class="x-links"><a class="table-download" href="?export=letter&amp;id=<?= $internId ?>" target="_blank">Giấy giới thiệu</a><a class="table-download" href="?export=receipt&amp;id=<?= $internId ?>" target="_blank">Phiếu tiếp nhận</a><a class="table-download" href="?export=company_form&amp;id=<?= $internId ?>" target="_blank">Phiếu nhận xét</a></span></li><?php endforeach; ?></ul>
        </details>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="internship_doc_upload">
            <select name="internship_id"><?php foreach ($screenData['interns'] as $intern): ?><option value="<?= (int) $intern['internship_id'] ?>"><?= screen_escape($intern['student_name']) ?></option><?php endforeach; ?></select>
            <select name="doc_type"><?php foreach (INTERNSHIP_DOC_TYPES as $key => $label): ?><option value="<?= $key ?>" <?= $key === 'tiep_nhan' ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
            <input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"><button class="button" type="submit">Tải lên</button>
        </form>
    <?php endif; ?>
    <ul class="x-plain-list"><?php foreach ($screenData['documents'] as $document): ?><li><a class="table-download" href="?download=internship_doc&amp;id=<?= (int) $document['id'] ?>"><?= screen_escape($document['student_name'] . ' · ' . INTERNSHIP_DOC_TYPES[$document['doc_type']] . ' · ' . $document['file_name']) ?> ↓</a> <span class="x-meta"><?= screen_escape(thesis_datetime_label($document['created_at'])) ?></span></li><?php endforeach; ?>
        <?php if (!$screenData['documents']): ?><li class="x-meta">Chưa có giấy tờ nào.</li><?php endif; ?></ul>
</section>
