<?php
// Giảng viên · Đề tài: đăng ký/sửa đề tài, xem nguyện vọng và xếp hạng sinh viên, xét đề xuất của sinh viên, duyệt đề tài bộ môn (trưởng bộ môn).
require __DIR__ . '/admin-topics.php'; // dùng lại $topicCard và các hàm chung
$lecturer = $screenData['lecturer'] ?? [];
$filters = $screenData['filters'];

// Form đăng ký / sửa đề tài.
$topicForm = static function (?array $topic) use ($screenData, $xAction, $csrfField): string {
    $value = static fn(string $key, $default = '') => screen_escape($topic[$key] ?? $default);
    ob_start(); ?>
    <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="topic_save"><input type="hidden" name="topic_id" value="<?= (int) ($topic['id'] ?? 0) ?>">
        <header class="modal-head"><div><p class="eyebrow"><?= $topic ? 'Chỉnh sửa đề tài' : 'Đăng ký đề tài mới' ?></p><h2><?= $topic ? screen_escape($topic['title']) : 'Đề tài đồ án' ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
        <?php if (!$topic): ?>
            <label>Đợt đồ án<select name="batch_id" required><?php foreach ($screenData['open_batches'] as $batch): ?><option value="<?= (int) $batch['id'] ?>" <?= $batch['phase'] !== 'open' ? 'disabled' : '' ?>><?= screen_escape($batch['name'] . ' · ' . batch_phase_label($batch['phase']) . ' (' . $batch['window'] . ')') ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label>Tên đề tài<input name="title" maxlength="255" value="<?= $value('title') ?>" required></label>
        <label>Mô tả<textarea name="description" rows="3" maxlength="10000"><?= $value('description') ?></textarea></label>
        <label>Mục tiêu<textarea name="objectives" rows="2" maxlength="5000"><?= $value('objectives') ?></textarea></label>
        <label>Yêu cầu kiến thức<textarea name="requirements" rows="2" maxlength="5000"><?= $value('requirements') ?></textarea></label>
        <div class="form-grid form-grid--3">
            <label>Công nghệ<input name="technologies" maxlength="255" value="<?= $value('technologies') ?>" placeholder="Laravel, React, MySQL"></label>
            <label>Lĩnh vực / hướng<input name="field" maxlength="150" value="<?= $value('field') ?>" placeholder="Phát triển web"></label>
            <label>Số SV tối đa<input name="max_students" type="number" min="1" max="10" value="<?= $value('max_students', 1) ?>"></label>
        </div>
        <label>Tài liệu đính kèm (PDF, Word · tối đa 10 MB)<input name="attachment" type="file" accept=".pdf,.doc,.docx"></label>
        <label class="x-check"><input type="checkbox" name="save_draft" value="1"> Lưu bản nháp (chưa gửi duyệt)</label>
        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu đề tài</button></div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<div class="x-actions-row">
    <?php if ($screenData['open_batches']): ?><button type="button" class="action-trigger" data-dialog-open="topic-new"><span>+ Đăng ký đề tài</span><small>Hạn mức <?= (int) ($lecturer['max_students'] ?? 0) ?> sinh viên mỗi đợt</small></button><?php endif; ?>
    <a class="action-trigger" href="?page=lecturer/topics&amp;scope=archive"><span>Kho đề tài các khóa</span><small>Tham khảo đề tài đã duyệt của mọi năm</small></a>
    <?php if ($screenData['open_batches'] && ai_match_enabled()): ?><?= ai_topic_dialog($xAction, $csrfField, 'topic-new') ?><?php endif; ?>
</div>
<dialog class="modal modal--wide" id="topic-new" aria-label="Đăng ký đề tài"><?= $topicForm(null) ?></dialog>

<?php if ($filters['archive']): ?>
    <form method="get" class="x-filter"><input type="hidden" name="page" value="lecturer/topics"><input type="hidden" name="scope" value="archive">
        <label>Năm học<select name="year"><option value="">Mọi năm</option><?php foreach ($screenData['years'] as $year): ?><option <?= $filters['year'] === $year ? 'selected' : '' ?>><?= screen_escape($year) ?></option><?php endforeach; ?></select></label>
        <label>Từ khóa<input type="search" name="q" value="<?= screen_escape($filters['q']) ?>" placeholder="Tên đề tài, hướng, công nghệ, giảng viên"></label>
        <button class="button button--primary" type="submit">Tìm</button> <a class="table-edit-button" href="?page=lecturer/topics">← Đề tài của tôi</a>
    </form>
    <section class="x-card-list"><?php foreach ($screenData['topics'] as $topic): ?><?= $topicCard($topic, false) ?><?php endforeach; ?><?php if (!$screenData['topics']): ?><?= x_empty('Không tìm thấy đề tài.') ?><?php endif; ?></section>
<?php else: ?>
    <?php if ($screenData['proposals']): ?>
        <section class="panel x-section"><div class="panel-heading"><div><p class="eyebrow">Sinh viên đề xuất · bạn là GV hướng dẫn mong muốn</p><h2>Đề xuất chờ xét duyệt</h2></div></div>
            <div class="x-card-list"><?php foreach ($screenData['proposals'] as $topic): $topic += ['lecturer_name' => $user['full_name'], 'pref_total' => 0, 'pref_first' => 0, 'assigned' => 0, 'similar' => []]; ?><?= $topicCard($topic, true) ?><?php endforeach; ?></div>
            <p class="form-hint">Duyệt đề xuất sẽ ghép thẳng sinh viên với đề tài và bạn là người hướng dẫn.</p>
        </section>
    <?php endif; ?>
    <?php if ($screenData['department_queue']): ?>
        <section class="panel x-section"><div class="panel-heading"><div><p class="eyebrow">Trưởng bộ môn · <?= screen_escape($lecturer['department'] ?? '') ?></p><h2>Đề tài của bộ môn chờ duyệt</h2></div></div>
            <div class="x-card-list"><?php foreach ($screenData['department_queue'] as $topic): $topic += ['pref_total' => 0, 'pref_first' => 0, 'assigned' => 0]; ?><?= $topicCard($topic, true) ?><?php endforeach; ?></div>
        </section>
    <?php endif; ?>

    <section class="x-card-list">
        <?php foreach ($screenData['topics'] as $topic): $topicId = (int) $topic['id']; $applicants = $screenData['applicants'][$topicId] ?? []; ?>
            <div class="x-card-wrap">
                <?= $topicCard($topic, false, !$topic['assigned'] && !$topic['proposed_by_student_id']) ?>
                <div class="x-card-tools">
                    <?php if (in_array($topic['status'], ['draft', 'pending', 'revision'], true) && !$topic['proposed_by_student_id']): ?>
                        <button type="button" class="table-edit-button" data-dialog-open="topic-edit-<?= $topicId ?>">Sửa đề tài</button>
                        <dialog class="modal modal--wide" id="topic-edit-<?= $topicId ?>" aria-label="Sửa đề tài"><?= $topicForm($topic) ?></dialog>
                    <?php endif; ?>
                    <?php if ($applicants): ?>
                        <button type="button" class="table-edit-button" data-dialog-open="rank-<?= $topicId ?>">Xếp hạng <?= count($applicants) ?> sinh viên</button>
                        <dialog class="modal modal--wide" id="rank-<?= $topicId ?>" aria-label="Xếp hạng sinh viên">
                            <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="lecturer_rank_save"><input type="hidden" name="topic_id" value="<?= $topicId ?>">
                                <header class="modal-head"><div><p class="eyebrow">Ưu tiên của giảng viên (dùng cho ghép hai phía)</p><h2><?= screen_escape($topic['title']) ?></h2><p class="modal-sub">Điền thứ hạng 1, 2, 3… Để trống: xếp theo GPA, thời điểm đăng ký và mức khớp kỹ năng.</p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                <div class="table-scroll"><table><thead><tr><th>Sinh viên</th><th>GPA</th><th>Kỹ năng</th><th>NV của SV</th><th>Thứ hạng của bạn</th></tr></thead><tbody>
                                    <?php foreach ($applicants as $applicant): ?>
                                        <tr><td><?= screen_escape($applicant['full_name'] . ' · ' . $applicant['student_code']) ?></td><td><?= screen_escape($applicant['gpa'] ?? '—') ?></td><td><?= screen_escape(mb_strimwidth((string) $applicant['skills'], 0, 80, '…', 'UTF-8') ?: '—') ?></td><td>NV<?= (int) $applicant['rank_no'] ?></td>
                                            <td><input class="x-small-input" type="number" min="1" max="999" name="rank[<?= (int) $applicant['student_id'] ?>]" value="<?= screen_escape($applicant['lecturer_rank'] ?? '') ?>"></td></tr>
                                    <?php endforeach; ?>
                                </tbody></table></div>
                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu thứ hạng</button></div>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$screenData['topics']): ?><?= x_empty('Bạn chưa đăng ký đề tài nào.') ?><?php endif; ?>
    </section>
<?php endif; ?>
