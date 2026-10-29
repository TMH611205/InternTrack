<?php
// Quản trị · Đợt thực tập / đồ án: danh mục năm học & ngành, tạo đợt, mốc thời gian, mở/đóng đợt, danh sách đủ điều kiện.
require __DIR__ . '/_common.php';
$typeLabels = ['thesis' => 'Đồ án', 'internship' => 'Thực tập'];
$statusLabels = ['draft' => 'Bản nháp', 'open' => 'Đang mở', 'closed' => 'Đã đóng · chốt dữ liệu'];

// Form tạo / sửa đợt (dùng chung cho hộp thoại tạo mới và chỉnh sửa).
$batchForm = static function (?array $batch) use ($screenData, $xAction, $csrfField): string {
    $value = static fn(string $key, $default = '') => screen_escape($batch[$key] ?? $default);
    $select = static function (string $name, array $options, $current, string $empty = '') {
        $html = '<select name="' . $name . '">' . ($empty !== '' ? '<option value="">' . screen_escape($empty) . '</option>' : '');
        foreach ($options as $key => $label) {
            $html .= '<option value="' . screen_escape($key) . '"' . ((string) $current === (string) $key ? ' selected' : '') . '>' . screen_escape($label) . '</option>';
        }
        return $html . '</select>';
    };
    $terms = array_column($screenData['terms'], 'name', 'id');
    $majors = array_column($screenData['majors'], 'name', 'id');
    $specializations = array_column(array_map(static fn($row) => ['id' => $row['id'], 'label' => $row['major_name'] . ' · ' . $row['name']], $screenData['specializations']), 'label', 'id');
    $rubrics = array_column($screenData['rubrics'], 'name', 'id');
    // Rubric thực tập: rubric dành cho thực tập hoặc dùng chung.
    $internshipRubrics = array_column(array_filter($screenData['rubrics'], static fn($rubric) => in_array($rubric['applies_to'], ['any', 'internship_company', 'internship_lecturer'], true)), 'name', 'id');
    ob_start(); ?>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="batch_save"><input type="hidden" name="batch_id" value="<?= (int) ($batch['id'] ?? 0) ?>">
        <header class="modal-head"><div><p class="eyebrow"><?= $batch ? 'Chỉnh sửa đợt' : 'Tạo đợt mới' ?></p><h2><?= $batch ? screen_escape($batch['name']) : 'Đợt thực tập / đồ án' ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
        <div class="form-grid">
            <label>Loại đợt<?= $select('batch_type', ['thesis' => 'Đồ án tốt nghiệp', 'internship' => 'Thực tập'], $batch['batch_type'] ?? 'thesis') ?></label>
            <label>Tên đợt<input name="name" maxlength="200" value="<?= $value('name') ?>" placeholder="Đồ án tốt nghiệp K62 · HK2 2026–2027" required></label>
            <label>Năm học · học kỳ<?= $select('term_id', $terms, $batch['term_id'] ?? '', 'Chưa chọn') ?></label>
            <label>Ngành (để trống: mọi ngành)<?= $select('major_id', $majors, $batch['major_id'] ?? '', 'Mọi ngành') ?></label>
            <?php if ($specializations): ?><label>Chuyên ngành<?= $select('specialization_id', $specializations, $batch['specialization_id'] ?? '', 'Mọi chuyên ngành') ?></label><?php endif; ?>
            <label>Hệ đào tạo<input name="training_system" maxlength="60" value="<?= $value('training_system') ?>" placeholder="Chính quy"></label>
            <label>Số tín chỉ tối thiểu<input name="min_credits" type="number" min="0" value="<?= $value('min_credits') ?>"></label>
            <label>GPA tối thiểu (thang 4)<input name="min_gpa" type="number" step="0.01" min="0" max="4" value="<?= $value('min_gpa') ?>"></label>
        </div>
        <label>Học phần bắt buộc đã đạt (mã học phần, cách nhau dấu phẩy)<input name="required_courses" maxlength="500" value="<?= $value('required_courses') ?>" placeholder="INT3306, INT3505"></label>
        <label>Điều kiện tiên quyết khác<textarea name="prerequisites" rows="2" maxlength="2000" placeholder="Hoàn thành học phần thực tập tốt nghiệp, không nợ học phí..."><?= $value('prerequisites') ?></textarea></label>
        <?php // Phần riêng của đợt thực tập: rubric đánh giá của doanh nghiệp và giảng viên. ?>
        <div data-batch-only="internship" class="x-batch-part">
            <label class="x-check"><input type="checkbox" name="allocation_mode" value="1" <?= (int) ($batch['allocation_mode'] ?? 0) ? 'checked' : '' ?>> Khoa phân bổ sinh viên vào vị trí theo nguyện vọng (ngoài ứng tuyển trực tiếp)</label>
            <p class="form-hint">Rubric đánh giá thực tập (để trống: 4 tiêu chí mặc định chuyên môn, thái độ, giao tiếp, kỷ luật):</p>
            <div class="form-grid">
                <label>Doanh nghiệp đánh giá<?= $select('company_rubric_id', $internshipRubrics, $batch['company_rubric_id'] ?? '', '4 tiêu chí mặc định') ?></label>
                <label>Giảng viên đánh giá<?= $select('lecturer_rubric_id', $internshipRubrics, $batch['lecturer_rubric_id'] ?? '', '4 tiêu chí mặc định') ?></label>
            </div>
        </div>
        <?php // Phần riêng của đợt đồ án: nguyện vọng, nhóm, trọng số và rubric chấm. ?>
        <div data-batch-only="thesis" class="x-batch-part">
        <div class="form-grid form-grid--3">
            <label>Số nguyện vọng tối đa<input name="max_preferences" type="number" min="1" max="10" value="<?= $value('max_preferences', 5) ?>"></label>
            <label>Số sinh viên tối đa mỗi nhóm<input name="group_max_size" type="number" min="1" max="5" value="<?= $value('group_max_size', 1) ?>"></label>
            <label>Điểm đạt (thang 10)<input name="pass_score" type="number" step="0.1" min="0" max="10" value="<?= $value('pass_score', 5) ?>"></label>
        </div>
        <label class="x-check"><input type="checkbox" name="allow_student_proposals" value="1" <?= !$batch || (int) $batch['allow_student_proposals'] ? 'checked' : '' ?>> Cho phép sinh viên đề xuất đề tài riêng</label>
        <p class="form-hint">Trọng số điểm đồ án (tự chuẩn hóa) và rubric chấm cho từng vai trò:</p>
        <div class="form-grid form-grid--3">
            <label>Hướng dẫn<input name="supervisor_weight" type="number" step="0.05" min="0" value="<?= $value('supervisor_weight', 0.4) ?>"></label>
            <label>Phản biện<input name="reviewer_weight" type="number" step="0.05" min="0" value="<?= $value('reviewer_weight', 0.2) ?>"></label>
            <label>Hội đồng<input name="council_weight" type="number" step="0.05" min="0" value="<?= $value('council_weight', 0.4) ?>"></label>
            <label>Rubric hướng dẫn<?= $select('supervisor_rubric_id', $rubrics, $batch['supervisor_rubric_id'] ?? '', 'Chấm điểm tổng') ?></label>
            <label>Rubric phản biện<?= $select('reviewer_rubric_id', $rubrics, $batch['reviewer_rubric_id'] ?? '', 'Chấm điểm tổng') ?></label>
            <label>Rubric hội đồng<?= $select('council_rubric_id', $rubrics, $batch['council_rubric_id'] ?? '', 'Chấm điểm tổng') ?></label>
        </div>
        </div>
        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit"><?= $batch ? 'Lưu thay đổi' : 'Tạo đợt' ?></button></div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<div class="x-actions-row">
    <button type="button" class="action-trigger" data-dialog-open="batch-new"><span>+ Tạo đợt</span><small>Đợt thực tập hoặc đồ án theo học kỳ, ngành, hệ đào tạo</small></button>
    <button type="button" class="action-trigger" data-dialog-open="catalog"><span>Danh mục</span><small>Năm học · học kỳ · ngành · chuyên ngành</small></button>
</div>
<dialog class="modal modal--wide" id="batch-new" aria-label="Tạo đợt"><?= $batchForm(null) ?></dialog>
<dialog class="modal" id="catalog" aria-label="Danh mục">
    <div class="workspace-form modal-form">
        <header class="modal-head"><div><p class="eyebrow">Cấu hình chung</p><h2>Năm học, học kỳ, ngành và chuyên ngành</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
        <div class="chip-row"><?php foreach ($screenData['terms'] as $term): ?><span class="chip<?= $term['is_current'] ? ' chip--active' : '' ?>"><?= screen_escape($term['name']) ?></span><?php endforeach; ?></div>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="form-grid"><?= $csrfField ?><input type="hidden" name="action" value="term_save">
            <label>Năm học<input name="school_year" placeholder="2026-2027" pattern="\d{4}-\d{4}" required></label>
            <label>Học kỳ<select name="semester"><option value="1">Học kỳ 1</option><option value="2">Học kỳ 2</option><option value="3">Học kỳ hè</option></select></label>
            <label>Bắt đầu<input type="date" name="start_date"></label><label>Kết thúc<input type="date" name="end_date"></label>
            <label class="x-check"><input type="checkbox" name="is_current" value="1"> Học kỳ hiện tại</label><button class="button button--primary" type="submit">Lưu học kỳ</button>
        </form>
        <div class="chip-row"><?php foreach ($screenData['majors'] as $major): ?><span class="chip"><?= screen_escape($major['code'] . ' · ' . $major['name']) ?></span><?php endforeach; ?></div>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="form-grid"><?= $csrfField ?><input type="hidden" name="action" value="major_save">
            <label>Mã ngành<input name="code" maxlength="30" placeholder="7480201" required></label><label>Tên ngành<input name="name" maxlength="150" placeholder="Công nghệ thông tin" required></label>
            <label>Khoa / Viện<input name="faculty" maxlength="150"></label><button class="button button--primary" type="submit">Lưu ngành</button>
        </form>
        <div class="chip-row"><?php foreach ($screenData['specializations'] as $specialization): ?><span class="chip"><?= screen_escape($specialization['major_name'] . ' · ' . $specialization['name']) ?></span><?php endforeach; ?></div>
        <?php if ($screenData['majors']): ?>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="form-grid"><?= $csrfField ?><input type="hidden" name="action" value="specialization_save">
            <label>Thuộc ngành<select name="major_id" required><?php foreach ($screenData['majors'] as $major): ?><option value="<?= (int) $major['id'] ?>"><?= screen_escape($major['name']) ?></option><?php endforeach; ?></select></label>
            <label>Tên chuyên ngành<input name="name" maxlength="150" placeholder="Kỹ thuật phần mềm" required></label><button class="button button--primary" type="submit">Lưu chuyên ngành</button>
        </form>
        <?php endif; ?>
    </div>
</dialog>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Danh sách đợt</p><h2>Đợt thực tập & đồ án</h2></div></div>
    <div class="table-scroll"><table>
        <thead><tr><th>Đợt</th><th>Loại</th><th>Học kỳ · ngành</th><th>Điều kiện</th><th>Số liệu</th><th>Trạng thái</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead>
        <tbody>
        <?php foreach ($screenData['batches'] as $batch): $batchId = (int) $batch['id']; ?>
            <tr<?= (int) $screenData['selected_batch'] === $batchId ? ' class="is-selected"' : '' ?>>
                <td><?= screen_escape($batch['name']) ?></td>
                <td><?= $typeLabels[$batch['batch_type']] ?></td>
                <td><?= screen_escape(($batch['term_name'] ?: 'Chưa chọn học kỳ') . ' · ' . ($batch['major_name'] ?: 'mọi ngành') . ($batch['training_system'] ? ' · ' . $batch['training_system'] : '')) ?></td>
                <td><?= screen_escape(trim(($batch['min_credits'] !== null ? '≥ ' . $batch['min_credits'] . ' TC' : '') . ($batch['min_gpa'] !== null ? ' · GPA ≥ ' . $batch['min_gpa'] : '')) ?: 'Không giới hạn') ?></td>
                <td><?= (int) $batch['eligible_count'] ?>/<?= (int) $batch['listed_count'] ?> đủ ĐK<?= $batch['batch_type'] === 'thesis' ? ' · ' . (int) $batch['topic_count'] . ' đề tài · ' . (int) $batch['thesis_count'] . ' đồ án' : ' · ' . (int) $batch['internship_count'] . ' kỳ thực tập' ?></td>
                <td><?= x_badge($statusLabels[$batch['status']], $batch['status'] === 'open' ? 'badge--active' : ($batch['status'] === 'closed' ? 'badge--neutral' : 'badge--waiting')) ?></td>
                <td class="row-actions x-row-actions">
                    <a class="table-edit-button" href="?page=admin/batches&amp;batch=<?= $batchId ?>">Danh sách SV</a>
                    <button type="button" class="table-edit-button" data-dialog-open="milestones-<?= $batchId ?>">Mốc thời gian</button>
                    <button type="button" class="table-edit-button" data-dialog-open="batch-edit-<?= $batchId ?>">Sửa</button>
                    <?php if ($batch['batch_type'] === 'internship' && !$batch['locked_at']): ?><?= $xPost('batch_attach_internships', ['batch_id' => $batchId], 'Gắn kỳ thực tập', 'inline-action', 'Gắn các kỳ thực tập đang chạy chưa thuộc đợt nào (của sinh viên trong danh sách) vào đợt này?') ?><?php endif; ?>
                    <?php if ($batch['status'] !== 'open'): ?><?= $xPost('batch_status', ['batch_id' => $batchId, 'status' => 'open'], $batch['status'] === 'closed' ? 'Mở lại' : 'Mở đợt', 'inline-action', $batch['status'] === 'closed' ? 'Mở lại đợt sẽ gỡ khóa dữ liệu. Tiếp tục?' : null) ?><?php endif; ?>
                    <?php if ($batch['status'] === 'open'): ?><?= $xPost('batch_status', ['batch_id' => $batchId, 'status' => 'closed'], 'Đóng & chốt', 'inline-action', 'Đóng đợt sẽ chốt và khóa mọi dữ liệu của đợt. Tiếp tục?') ?><?php endif; ?>
                    <dialog class="modal modal--wide" id="batch-edit-<?= $batchId ?>" aria-label="Sửa đợt"><?= $batchForm($batch) ?></dialog>
                    <dialog class="modal modal--wide" id="milestones-<?= $batchId ?>" aria-label="Mốc thời gian">
                        <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="batch_milestones_save"><input type="hidden" name="batch_id" value="<?= $batchId ?>">
                            <header class="modal-head"><div><p class="eyebrow">Mốc thời gian · tự mở/khóa chức năng</p><h2><?= screen_escape($batch['name']) ?></h2><p class="modal-sub">Để trống là không giới hạn. Ngoài khoảng thời gian, chức năng tương ứng tự khóa.</p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                            <div class="milestone-grid">
                                <?php foreach (BATCH_MILESTONES[$batch['batch_type']] as $kind => $title): $milestone = $screenData['milestones'][$batchId][$kind] ?? []; ?>
                                    <strong><?= screen_escape($title) ?></strong>
                                    <label>Bắt đầu<input type="datetime-local" name="starts[<?= $kind ?>]" value="<?= screen_escape(x_input_datetime($milestone['starts_at'] ?? null)) ?>"></label>
                                    <label>Kết thúc<input type="datetime-local" name="ends[<?= $kind ?>]" value="<?= screen_escape(x_input_datetime($milestone['ends_at'] ?? null)) ?>"></label>
                                <?php endforeach; ?>
                                <?php if ($batch['batch_type'] === 'thesis'): ?>
                                    <?php foreach ($screenData['milestones'][$batchId] ?? [] as $kind => $milestone): if (!str_starts_with($kind, 'stage_')) { continue; } ?>
                                        <input name="custom[<?= screen_escape($kind) ?>][title]" maxlength="200" value="<?= screen_escape($milestone['title']) ?>" aria-label="Tên mốc nộp bài" title="Xóa tên để bỏ mốc">
                                        <label>Bắt đầu<input type="datetime-local" name="custom[<?= screen_escape($kind) ?>][start]" value="<?= screen_escape(x_input_datetime($milestone['starts_at'] ?? null)) ?>"></label>
                                        <label>Kết thúc<input type="datetime-local" name="custom[<?= screen_escape($kind) ?>][end]" value="<?= screen_escape(x_input_datetime($milestone['ends_at'] ?? null)) ?>"></label>
                                    <?php endforeach; ?>
                                    <input name="new_title" maxlength="200" placeholder="+ Thêm mốc nộp bài (VD: Nộp bản thảo chương 2)" aria-label="Mốc nộp bài mới">
                                    <label>Bắt đầu<input type="datetime-local" name="new_start"></label>
                                    <label>Kết thúc<input type="datetime-local" name="new_end"></label>
                                <?php endif; ?>
                            </div>
                            <?php if ($batch['batch_type'] === 'thesis'): ?><p class="form-hint">Mốc nộp bài thêm vào sẽ thành một giai đoạn nộp của sinh viên. Xóa tên mốc để bỏ (khi chưa có bài nộp).</p><?php endif; ?>
                            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu mốc thời gian</button></div>
                        </form>
                    </dialog>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$screenData['batches']): ?><tr><td colspan="7"><?= x_empty('Chưa có đợt nào. Bấm "+ Tạo đợt" để bắt đầu.') ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<?php if ($screenData['selected_batch']): $selected = array_values(array_filter($screenData['batches'], static fn($row) => (int) $row['id'] === (int) $screenData['selected_batch']))[0] ?? null; ?>
    <?php if ($selected): ?>
    <section class="panel table-panel">
        <div class="table-toolbar">
            <div><p class="eyebrow">Danh sách sinh viên đủ điều kiện</p><h2><?= screen_escape($selected['name']) ?></h2><p class="modal-sub">Lọc theo ngành, hệ đào tạo, số tín chỉ và GPA của đợt. Lập lại danh sách sẽ xóa các điều chỉnh thủ công.</p></div>
            <?= $xPost('batch_eligibility_refresh', ['batch_id' => (int) $selected['id']], 'Lập / cập nhật danh sách', 'inline-action', null, 'x-strong') ?>
        </div>
        <div class="table-scroll"><table>
            <thead><tr><th>Sinh viên</th><th>Mã SV</th><th>Lớp · ngành</th><th>GPA</th><th>Tín chỉ</th><th>Ghi chú</th><th>Điều kiện</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead>
            <tbody>
            <?php foreach ($screenData['eligibility'] as $row): ?>
                <tr><td><?= screen_escape($row['full_name']) ?></td><td><?= screen_escape($row['student_code']) ?></td><td><?= screen_escape(trim($row['class_name'] . ' · ' . $row['major'], ' ·')) ?></td><td><?= screen_escape($row['gpa'] ?? '—') ?></td><td><?= screen_escape($row['credits'] ?? '—') ?></td><td><?= screen_escape($row['note']) ?></td>
                    <td><?= (int) $row['eligible'] ? x_badge('Đủ điều kiện', 'badge--positive') : x_badge('Chưa đủ', 'badge--attention') ?></td>
                    <td><?= $xPost('batch_eligibility_toggle', ['batch_id' => (int) $selected['id'], 'student_id' => (int) $row['student_id'], 'eligible' => (int) $row['eligible'] ? '' : '1'], (int) $row['eligible'] ? 'Loại khỏi đợt' : 'Cho phép tham gia') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$screenData['eligibility']): ?><tr><td colspan="8"><?= x_empty('Chưa lập danh sách. Khi chưa có danh sách, mọi sinh viên đều tham gia được đợt.') ?></td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <?php endif; ?>
<?php endif; ?>
