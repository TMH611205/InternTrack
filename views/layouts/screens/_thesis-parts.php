<?php
// Khối giao diện dùng chung cho đồ án (giảng viên và sinh viên): mốc công việc, lịch gặp, bài nộp theo phiên bản + góp ý, phiếu chấm rubric.
require_once __DIR__ . '/_common.php';

// Mốc công việc (kế hoạch làm việc giữa sinh viên và giảng viên hướng dẫn).
$renderMilestones = static function (array $thesis, bool $canEdit) use ($xAction, $csrfField, $xPost): string {
    ob_start(); ?>
    <div class="x-block">
        <h4>Kế hoạch & mốc công việc</h4>
        <ul class="x-plain-list">
            <?php foreach ($thesis['milestones'] as $milestone): $overdue = $milestone['status'] !== 'done' && $milestone['due_date'] && $milestone['due_date'] < date('Y-m-d'); ?>
                <li class="x-row-line"><span><?= $milestone['status'] === 'done' ? '✅' : ($overdue ? '⚠️' : '⬜') ?> <strong><?= screen_escape($milestone['title']) ?></strong><?= $milestone['due_date'] ? ' · hạn ' . page_date($milestone['due_date']) : '' ?><?= $milestone['description'] ? ' · ' . screen_escape($milestone['description']) : '' ?></span>
                    <?php if ($canEdit): ?><?= $xPost('milestone_toggle', ['milestone_id' => (int) $milestone['id']], $milestone['status'] === 'done' ? 'Mở lại' : 'Hoàn thành') ?><?php endif; ?></li>
            <?php endforeach; ?>
            <?php if (!$thesis['milestones']): ?><li class="x-meta">Chưa có mốc công việc.</li><?php endif; ?>
        </ul>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="milestone_save"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>">
                <input name="title" maxlength="200" placeholder="Mốc mới, ví dụ: Hoàn thành chương 2" required><input type="date" name="due_date" aria-label="Hạn"><input name="description" maxlength="2000" placeholder="Ghi chú"><button class="button" type="submit">Thêm mốc</button>
            </form>
        <?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};

// Lịch gặp / nhật ký hướng dẫn; giảng viên xác nhận buổi đã diễn ra và ghi biên bản.
$renderMeetings = static function (array $thesis, bool $canCreate, bool $canConfirm) use ($xAction, $csrfField): string {
    ob_start(); ?>
    <div class="x-block">
        <h4>Lịch gặp & nhật ký hướng dẫn</h4>
        <ul class="x-plain-list">
            <?php foreach ($thesis['meetings'] as $meeting): ?>
                <li class="x-row-line"><span><strong><?= screen_escape(thesis_datetime_label($meeting['meeting_at'])) ?></strong><?= $meeting['location'] ? ' · ' . screen_escape($meeting['location']) : '' ?> <?= x_badge(['scheduled' => 'Đã hẹn', 'held' => 'Đã gặp · GV xác nhận', 'cancelled' => 'Đã hủy'][$meeting['status']]) ?><?= $meeting['agenda'] ? '<br><span class="x-meta">Nội dung: ' . screen_escape($meeting['agenda']) . '</span>' : '' ?><?= $meeting['notes'] ? '<br><span class="x-meta">Biên bản: ' . screen_escape($meeting['notes']) . '</span>' : '' ?></span>
                    <?php if ($canConfirm && $meeting['status'] === 'scheduled'): ?>
                        <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="meeting_confirm"><input type="hidden" name="meeting_id" value="<?= (int) $meeting['id'] ?>"><select name="status"><option value="held">Đã gặp</option><option value="cancelled">Hủy</option></select><input name="notes" maxlength="5000" placeholder="Biên bản / nhận xét buổi gặp"><button class="button" type="submit">Xác nhận</button></form>
                    <?php endif; ?></li>
            <?php endforeach; ?>
            <?php if (!$thesis['meetings']): ?><li class="x-meta">Chưa có buổi gặp nào.</li><?php endif; ?>
        </ul>
        <?php if ($canCreate): ?>
            <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="meeting_save"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>">
                <input type="datetime-local" name="meeting_at" required aria-label="Thời gian"><input name="location" maxlength="200" placeholder="Phòng / link họp"><input name="agenda" maxlength="2000" placeholder="Nội dung trao đổi"><button class="button" type="submit">Đặt lịch</button>
            </form>
        <?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};

// Bài nộp theo giai đoạn và phiên bản, kèm góp ý trên từng phiên bản.
$renderSubmissions = static function (array $thesis, bool $canReview, string $return = '') use ($xAction, $csrfField): string {
    ob_start(); ?>
    <?php // Điểm theo giai đoạn: giảng viên hướng dẫn chấm từng giai đoạn đã có bài nộp; sinh viên xem điểm và nhận xét. ?>
    <?php $stagesWithWork = array_unique(array_column($thesis['submissions'], 'stage')); $stageLabels = thesis_stages((int) $thesis['batch_id']); ?>
    <?php if (array_diff($stagesWithWork, ['other']) || !empty($thesis['stage_scores'])): ?>
        <div class="x-block">
            <h4>Điểm theo giai đoạn</h4>
            <?php foreach ($stageLabels as $stage => $label): if ($stage === 'other' || (!in_array($stage, $stagesWithWork, true) && !isset($thesis['stage_scores'][$stage]))) { continue; } $stageScore = $thesis['stage_scores'][$stage] ?? null; ?>
                <?php if ($canReview && $return !== 'defense'): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="stage_score_save"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>"><input type="hidden" name="stage" value="<?= screen_escape($stage) ?>">
                        <strong><?= screen_escape($label) ?></strong><input class="x-small-input" type="number" name="score" min="0" max="10" step="0.25" value="<?= screen_escape($stageScore['score'] ?? '') ?>" placeholder="/10" aria-label="Điểm"><input name="comments" maxlength="2000" value="<?= screen_escape($stageScore['comments'] ?? '') ?>" placeholder="Nhận xét giai đoạn"><button class="button" type="submit">Lưu</button>
                    </form>
                <?php else: ?>
                    <p class="x-row-line"><span><strong><?= screen_escape($label) ?></strong>: <?= $stageScore ? screen_escape((string) (float) $stageScore['score']) . '/10' . ($stageScore['comments'] ? ' · ' . screen_escape($stageScore['comments']) : '') : 'chưa chấm' ?></span></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="x-block">
        <h4>Bài nộp theo phiên bản</h4>
        <?php foreach ($thesis['submissions'] as $submission): ?>
            <div class="x-submission">
                <div class="x-row-line"><span><strong><?= screen_escape(thesis_stages((int) $thesis['batch_id'])[$submission['stage']] ?? $submission['stage']) ?> · bản <?= (int) $submission['version'] ?></strong> · <a class="table-download" href="?download=submission&amp;id=<?= (int) $submission['id'] ?>"><?= screen_escape($submission['file_name']) ?> ↓</a> · <?= round((int) $submission['file_size'] / 1048576, 2) ?> MB · <?= screen_escape(thesis_datetime_label($submission['submitted_at'])) ?>
                    <?= (int) $submission['is_late'] ? x_badge('Nộp trễ', 'badge--attention') : '' ?><?= $submission['similarity'] !== null ? x_badge('Trùng ' . round((float) $submission['similarity']) . '%', (float) $submission['similarity'] >= SIMILARITY_WARN ? 'badge--attention' : 'badge--neutral') : '' ?> <?= x_badge(['submitted' => 'Chờ GV xem', 'reviewed' => 'GV đã xem', 'revision' => 'Cần chỉnh sửa'][$submission['status']]) ?></span></div>
                <?php if ($submission['note']): ?><p class="x-meta">Ghi chú: <?= screen_escape($submission['note']) ?></p><?php endif; ?>
                <?php foreach ($submission['comments'] as $comment): ?><p class="x-comment"><strong><?= screen_escape($comment['full_name']) ?></strong> <span class="x-meta"><?= screen_escape(thesis_datetime_label($comment['created_at'])) ?></span><br><?= nl2br(screen_escape($comment['body'])) ?></p><?php endforeach; ?>
                <?php if ($canReview && $submission['status'] === 'submitted'): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="submission_review"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><select name="status"><option value="reviewed">Đã xem, đạt</option><option value="revision">Yêu cầu chỉnh sửa</option></select><input name="comment" maxlength="5000" placeholder="Nhận xét cho phiên bản này"><button class="button button--primary" type="submit">Lưu nhận xét</button></form>
                <?php else: ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="submission_comment"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><input type="hidden" name="return" value="<?= screen_escape($return) ?>"><input name="body" maxlength="5000" placeholder="Góp ý / trao đổi trên phiên bản này" required><button class="button" type="submit">Gửi</button></form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$thesis['submissions']): ?><p class="x-meta">Chưa có bài nộp.</p><?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};

// Phiếu chấm theo rubric của đợt (hoặc ô điểm tổng nếu đợt chưa gán rubric).
$renderScoreSheet = static function (array $thesis, array $sheet) use ($xAction, $csrfField): string {
    $score = $sheet['score'];
    $locked = !empty($thesis['locked_at']) || (!empty($thesis['results_published_at']) && ($score['status'] ?? '') === 'submitted');
    ob_start(); ?>
    <div class="x-block x-score">
        <h4>Phiếu chấm <?= screen_escape(mb_strtolower(THESIS_ROLE_LABELS[$sheet['role']], 'UTF-8')) ?> <?= $score ? x_badge(($score['status'] === 'submitted' ? 'Đã gửi · ' : 'Nháp · ') . $score['total'] . '/10') : x_badge('Chưa chấm', 'badge--waiting') ?> <a class="table-download" href="?export=scoresheet&amp;id=<?= (int) $thesis['id'] ?>&amp;role=<?= $sheet['role'] ?>" target="_blank">In phiếu</a></h4>
        <?php if ($locked): ?><p class="x-meta">Phiếu đã khóa (điểm đã công bố hoặc đợt đã chốt). Chỉ chấm lại khi có phúc khảo được chấp nhận.</p><?php endif; ?>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="thesis_score_save"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>"><input type="hidden" name="evaluator_role" value="<?= $sheet['role'] ?>">
            <?php if (!empty($sheet['rubric']['criteria'])): ?>
                <p class="x-meta">Rubric: <?= screen_escape($sheet['rubric']['name']) ?></p>
                <div class="table-scroll"><table class="x-compact"><thead><tr><th>Tiêu chí</th><th>Mô tả mức</th><th>Trọng số</th><th>Điểm (tối đa)</th></tr></thead><tbody>
                    <?php foreach ($sheet['rubric']['criteria'] as $criterion): ?>
                        <tr><td><?= screen_escape($criterion['name']) ?></td><td><?= screen_escape($criterion['levels'] ?? '') ?></td><td><?= (float) $criterion['weight'] ?></td><td><input class="x-small-input" type="number" step="0.25" min="0" max="<?= (float) $criterion['max_score'] ?>" name="scores[<?= (int) $criterion['id'] ?>]" value="<?= screen_escape($sheet['items'][(int) $criterion['id']] ?? '') ?>" required> / <?= (float) $criterion['max_score'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?>
                <label>Điểm (thang 10)<input type="number" step="0.1" min="0" max="10" name="total" value="<?= screen_escape($score['total'] ?? '') ?>" required></label>
            <?php endif; ?>
            <label>Nhận xét, góp ý<textarea name="comments" rows="2" maxlength="5000"><?= screen_escape($score['comments'] ?? '') ?></textarea></label>
            <div class="x-actions-row"><select name="status" aria-label="Trạng thái phiếu"><option value="submitted">Gửi phiếu chấm</option><option value="draft" <?= ($score['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Lưu nháp</option></select><button class="button button--primary" type="submit" <?= $locked ? 'disabled' : '' ?>>Lưu phiếu chấm</button></div>
        </form>
    </div>
    <?php return (string) ob_get_clean();
};
