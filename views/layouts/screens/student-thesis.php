<?php
// Sinh viên · Đồ án: mốc của đợt, điều kiện, nhóm, nguyện vọng, đề xuất đề tài, kết quả ghép, theo dõi, nộp bài, điểm, phúc khảo, kho đề tài.
require __DIR__ . '/_thesis-parts.php';
$batch = $screenData['batch'] ?? null;
$thesis = $screenData['thesis'] ?? null;
$phases = $screenData['phases'] ?? [];
$prefOpen = ($phases['preference']['state'] ?? '') === 'open';
$stateClass = static fn(string $state) => ['open' => 'badge--active', 'not_started' => 'badge--neutral', 'ended' => 'badge--waiting', 'closed' => 'badge--neutral', 'locked' => 'badge--neutral'][$state] ?? 'badge--neutral';
?>
<?= x_batch_switcher($screenData['batches'], $batch, 'student/thesis') ?>
<?php if (!$batch): ?>
    <?= x_empty('Hiện chưa có đợt đồ án nào được mở.') ?>
<?php else: $batchId = (int) $batch['id']; ?>
<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Mốc thời gian · chức năng tự mở/khóa theo mốc</p><h2><?= screen_escape($batch['name']) ?></h2></div></div>
    <ol class="x-timeline">
        <?php foreach ($phases as $kind => $phase): ?><li class="x-timeline-item x-timeline-item--<?= $phase['state'] ?>"><strong><?= screen_escape($phase['title']) ?></strong><span class="x-meta"><?= screen_escape($phase['window']) ?></span><?= x_badge(batch_phase_label($phase['state']), $stateClass($phase['state'])) ?></li><?php endforeach; ?>
    </ol>
    <?php if (!$screenData['eligible']): ?><div class="x-warning"><strong>Bạn chưa đủ điều kiện tham gia đợt này.</strong> <?= screen_escape($screenData['eligibility_note']) ?> Liên hệ phòng đào tạo nếu thông tin chưa đúng.</div><?php endif; ?>
    <?php if ($batch['prerequisites']): ?><p class="form-hint">Điều kiện: <?= screen_escape($batch['prerequisites']) ?></p><?php endif; ?>
</section>

<?php if ($thesis): ?>
    <section class="panel x-section x-thesis">
        <div class="panel-heading"><div><p class="eyebrow">Đề tài của bạn · <?= screen_escape(['auto' => 'ghép tự động' . ($thesis['matched_rank'] ? ' (nguyện vọng ' . (int) $thesis['matched_rank'] . ')' : ''), 'manual' => 'khoa phân công', 'proposal' => 'đề tài bạn đề xuất'][$thesis['source']]) ?></p><h2><?= screen_escape($thesis['topic_title']) ?></h2></div><?= x_badge(THESIS_STATUS_LABELS[$thesis['status']] ?? $thesis['status']) ?></div>
        <dl class="x-kv">
            <dt>Giảng viên hướng dẫn</dt><dd><?= screen_escape($thesis['supervisor_name'] ?? 'Đang phân công') ?><?php if (!empty($thesis['supervisor_user_id'])): ?> · <a class="table-download" href="?page=student/messages&amp;with=<?= (int) $thesis['supervisor_user_id'] ?>">Nhắn tin</a><?php endif; ?></dd>
            <dt>Giảng viên phản biện</dt><dd><?= screen_escape($thesis['reviewer_name'] ?? 'Chưa phân công') ?></dd>
            <dt>Hội đồng · lịch bảo vệ</dt><dd><?= screen_escape(($thesis['council_name'] ?? 'Chưa xếp') . ($thesis['room'] ? ' · phòng ' . $thesis['room'] : '') . ' · ' . thesis_datetime_label($thesis['defense_at'])) ?></dd>
            <?php if ($thesis['topic_description']): ?><dt>Mô tả đề tài</dt><dd><?= screen_escape($thesis['topic_description']) ?></dd><?php endif; ?>
        </dl>
        <?php if ($thesis['status'] === 'assigned'): ?>
            <div class="x-actions-row"><?= $xPost('thesis_confirm', ['thesis_id' => (int) $thesis['id'], 'decision' => 'confirm'], 'Xác nhận đề tài', 'inline-action', null, 'x-strong') ?><?= $xPost('thesis_confirm', ['thesis_id' => (int) $thesis['id'], 'decision' => 'decline'], 'Từ chối, chờ vòng bổ sung', 'inline-action', 'Từ chối kết quả ghép? Bạn sẽ tham gia vòng ghép bổ sung.') ?></div>
        <?php endif; ?>
    </section>

    <?php if ($thesis['status'] !== 'declined'): $editable = empty($batch['locked_at']); ?>
        <section class="panel x-section">
            <div class="panel-heading"><div><p class="eyebrow">Nộp báo cáo: tải tệp, mỗi lần nộp lại là một phiên bản; nộp sau hạn được ghi nhận là trễ</p><h2>Nộp bài</h2></div></div>
            <?php if ($editable && in_array($thesis['status'], ['assigned', 'confirmed', 'in_progress'], true)): ?>
                <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="submission_upload"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>">
                    <div class="form-grid form-grid--3">
                        <label>Giai đoạn<select name="stage"><?php foreach ($thesis['stages'] as $stage => $label): ?><option value="<?= $stage ?>"><?= screen_escape($label . ' · ' . ($thesis['stage_windows'][$stage] ?? '')) ?></option><?php endforeach; ?></select></label>
                        <label>Tệp (PDF, Word, ZIP mã nguồn… · tối đa 20 MB)<input type="file" name="file" required accept=".pdf,.doc,.docx,.zip,.rar,.7z,.ppt,.pptx,.xls,.xlsx"></label>
                        <label>Ghi chú<input name="note" maxlength="2000" placeholder="Những thay đổi so với bản trước"></label>
                    </div>
                    <button class="button button--primary" type="submit">Nộp bài</button>
                </form>
            <?php endif; ?>
            <?= $renderSubmissions($thesis, false) ?>
        </section>
        <div class="x-split">
            <section class="panel x-section"><?= $renderMilestones($thesis, $editable) ?></section>
            <section class="panel x-section"><?= $renderMeetings($thesis, $editable, false) ?></section>
        </div>
        <?php if ($batch['results_published_at']): ?>
            <section class="panel x-section">
                <div class="panel-heading"><div><p class="eyebrow">Kết quả đã công bố <?= screen_escape(thesis_datetime_label($batch['results_published_at'])) ?></p><h2>Điểm đồ án: <?= $thesis['final_score'] !== null ? screen_escape($thesis['final_score']) . '/10' : 'đang chấm lại' ?></h2></div>
                    <?php if ($thesis['final_score'] !== null): ?><?= (float) $thesis['final_score'] >= (float) $batch['pass_score'] ? x_badge('Đạt', 'badge--positive') : x_badge('Không đạt', 'badge--attention') ?><?php endif; ?></div>
                <div class="table-scroll"><table class="x-compact"><thead><tr><th>Vai trò</th><th>Điểm</th><th>Nhận xét</th></tr></thead><tbody>
                    <?php foreach ($thesis['scores'] as $score): ?><tr><td><?= screen_escape(THESIS_ROLE_LABELS[$score['evaluator_role']] ?? '') ?></td><td><?= round((float) $score['total'], 2) ?></td><td><?= nl2br(screen_escape((string) $score['comments'])) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php foreach ($thesis['regrades'] as $request): ?><p class="x-meta">Phúc khảo <?= screen_escape(thesis_datetime_label($request['created_at'])) ?>: <?= x_badge(['pending' => 'Chờ xử lý', 'accepted' => 'Được chấp nhận', 'rejected' => 'Bị từ chối'][$request['status']]) ?> <?= screen_escape((string) $request['response']) ?></p><?php endforeach; ?>
                <?php if (empty($batch['locked_at']) && !in_array('pending', array_column($thesis['regrades'], 'status'), true)): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="regrade_request"><input type="hidden" name="thesis_id" value="<?= (int) $thesis['id'] ?>"><input name="reason" maxlength="3000" minlength="20" placeholder="Lý do đề nghị phúc khảo (trong 14 ngày kể từ khi công bố)" required><button class="button" type="submit">Gửi yêu cầu phúc khảo</button></form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ((!$thesis || $thesis['status'] === 'declined') && $screenData['eligible']): ?>
    <?php if ((int) $batch['group_max_size'] > 1): $group = $screenData['group']; ?>
        <section class="panel x-section">
            <div class="panel-heading"><div><p class="eyebrow">Làm đồ án theo nhóm · tối đa <?= (int) $batch['group_max_size'] ?> sinh viên</p><h2>Nhóm của bạn</h2></div></div>
            <?php foreach ($screenData['invitations'] as $invitation): ?>
                <div class="x-row-line"><span>Lời mời vào <strong><?= screen_escape($invitation['name']) ?></strong> từ <?= screen_escape($invitation['leader_name']) ?></span><span class="x-actions-row"><?= $xPost('group_respond', ['group_id' => (int) $invitation['id'], 'decision' => 'accept'], 'Tham gia') ?><?= $xPost('group_respond', ['group_id' => (int) $invitation['id'], 'decision' => 'decline'], 'Từ chối') ?></span></div>
            <?php endforeach; ?>
            <?php if ($group): ?>
                <p><strong><?= screen_escape($group['name']) ?></strong> · nhóm trưởng <?= screen_escape($group['leader_name']) ?></p>
                <ul class="x-plain-list"><?php foreach ($group['members'] as $member): ?><li><?= screen_escape($member['full_name'] . ' · ' . $member['student_code']) ?> <?= x_badge(['invited' => 'Chờ xác nhận', 'accepted' => 'Đã vào nhóm', 'declined' => 'Từ chối'][$member['status']]) ?></li><?php endforeach; ?></ul>
                <?php if ($group['is_leader'] && $prefOpen): ?>
                    <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="group_invite"><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>"><input name="student_code" maxlength="30" placeholder="Mã sinh viên cần mời" required><button class="button" type="submit">Mời</button></form>
                <?php endif; ?>
                <?php if ($prefOpen): ?><?= $xPost('group_leave', ['group_id' => (int) $group['id']], $group['is_leader'] ? 'Giải tán nhóm' : 'Rời nhóm', 'inline-action', 'Bạn chắc chắn?') ?><?php endif; ?>
            <?php elseif ($prefOpen): ?>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="x-inline-form"><?= $csrfField ?><input type="hidden" name="action" value="group_create"><input type="hidden" name="batch_id" value="<?= $batchId ?>"><input name="name" maxlength="150" placeholder="Tên nhóm (không bắt buộc)"><button class="button" type="submit">Tạo nhóm, tôi làm nhóm trưởng</button></form>
                <p class="form-hint">Làm cá nhân thì bỏ qua phần này. Thành viên nhóm dùng chung nguyện vọng do nhóm trưởng đăng ký.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Xếp nguyện vọng theo thứ tự ưu tiên · sửa được đến khi hết hạn · <?= screen_escape($phases['preference']['window'] ?? '') ?></p><h2>Nguyện vọng đề tài</h2></div><?= x_badge(batch_phase_label($phases['preference']['state'] ?? 'closed'), $stateClass($phases['preference']['state'] ?? 'closed')) ?></div>
        <?php if (!$screenData['can_edit_prefs']): ?><p class="form-hint">Bạn là thành viên nhóm: nhóm trưởng đăng ký nguyện vọng cho cả nhóm.</p><?php endif; ?>
        <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="preference_save"><input type="hidden" name="batch_id" value="<?= $batchId ?>">
            <div class="x-pref-list">
                <?php for ($rank = 1; $rank <= (int) $batch['max_preferences']; $rank++): $chosen = $screenData['preferences'][$rank - 1] ?? 0; ?>
                    <label>Nguyện vọng <?= $rank ?><select name="pref[]" <?= !$prefOpen || !$screenData['can_edit_prefs'] ? 'disabled' : '' ?>><option value="">— Không chọn —</option><?php foreach ($screenData['topics'] as $topic): ?><option value="<?= (int) $topic['id'] ?>" <?= $chosen === (int) $topic['id'] ? 'selected' : '' ?>><?= screen_escape($topic['title'] . ' · ' . ($topic['lecturer_name'] ?? '') . ' (' . (int) $topic['pref_total'] . ' NV / ' . (int) $topic['max_students'] . ' chỗ)') ?></option><?php endforeach; ?></select></label>
                <?php endfor; ?>
            </div>
            <?php if ($prefOpen && $screenData['can_edit_prefs']): ?><button class="button button--primary" type="submit">Lưu nguyện vọng</button><?php endif; ?>
        </form>
    </section>

    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow"><?= count($screenData['topics']) ?> đề tài đã duyệt</p><h2>Danh sách đề tài</h2></div><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-card-search="topic-catalog" placeholder="Lọc theo tên, hướng, công nghệ, giảng viên" aria-label="Lọc đề tài"></label></div>
        <div class="x-card-list x-card-list--grid" id="topic-catalog">
            <?php foreach ($screenData['topics'] as $topic): ?>
                <article class="x-card" data-card-text="<?= screen_escape(mb_strtolower($topic['title'] . ' ' . $topic['field'] . ' ' . $topic['technologies'] . ' ' . $topic['lecturer_name'], 'UTF-8')) ?>">
                    <div class="x-card-head"><div><h3><?= screen_escape($topic['title']) ?></h3><p class="x-meta"><?= screen_escape(implode(' · ', array_filter([$topic['lecturer_name'], $topic['field'], 'Tối đa ' . (int) $topic['max_students'] . ' SV', (int) $topic['pref_total'] . ' lượt đăng ký']))) ?></p></div><?= in_array((int) $topic['id'], $screenData['preferences'], true) ? x_badge('NV' . (array_search((int) $topic['id'], $screenData['preferences'], true) + 1), 'badge--active') : '' ?></div>
                    <?php if ($topic['description']): ?><p class="x-text"><?= screen_escape(mb_strimwidth((string) $topic['description'], 0, 220, '…', 'UTF-8')) ?></p><?php endif; ?>
                    <dl class="x-kv"><?php if ($topic['requirements']): ?><dt>Yêu cầu</dt><dd><?= screen_escape($topic['requirements']) ?></dd><?php endif; ?><?php if ($topic['technologies']): ?><dt>Công nghệ</dt><dd><?= screen_escape($topic['technologies']) ?></dd><?php endif; ?></dl>
                    <?php if ($topic['attachment_path']): ?><a class="table-download" href="?download=topic&amp;id=<?= (int) $topic['id'] ?>">Tài liệu đính kèm ↓</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$screenData['topics']): ?><?= x_empty('Chưa có đề tài nào được duyệt trong đợt.') ?><?php endif; ?>
        </div>
    </section>

    <?php if ((int) $batch['allow_student_proposals']): ?>
        <section class="panel x-section">
            <div class="panel-heading"><div><p class="eyebrow">Đề xuất đề tài riêng · giảng viên xét duyệt</p><h2>Đề tài tôi đề xuất</h2></div></div>
            <?php foreach ($screenData['proposals'] as $proposal): ?><div class="x-row-line"><span><strong><?= screen_escape($proposal['title']) ?></strong> · GV: <?= screen_escape($proposal['lecturer_name'] ?? '') ?><?= $proposal['review_note'] ? ' · ' . screen_escape($proposal['review_note']) : '' ?></span><?= x_badge(TOPIC_STATUS_LABELS[$proposal['status']] ?? $proposal['status']) ?></div><?php endforeach; ?>
            <?php if ($prefOpen): ?>
                <?php if (ai_match_enabled()): ?><?= ai_topic_dialog($xAction, $csrfField, 'topic-propose') ?><?php endif; ?>
                <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain" id="topic-propose"><?= $csrfField ?><input type="hidden" name="action" value="topic_propose"><input type="hidden" name="batch_id" value="<?= $batchId ?>">
                    <div class="form-grid"><label>Tên đề tài<input name="title" maxlength="255" required></label>
                        <label>Giảng viên hướng dẫn mong muốn<select name="lecturer_id" required><?php foreach ($screenData['lecturers'] as $lecturer): ?><option value="<?= (int) $lecturer['id'] ?>"><?= screen_escape($lecturer['full_name'] . ($lecturer['research_areas'] ? ' · ' . mb_strimwidth((string) $lecturer['research_areas'], 0, 60, '…', 'UTF-8') : '')) ?></option><?php endforeach; ?></select></label>
                        <label>Lĩnh vực<input name="field" maxlength="150"></label><label>Công nghệ<input name="technologies" maxlength="255"></label></div>
                    <label>Mô tả<textarea name="description" rows="3" maxlength="10000"></textarea></label>
                    <label>Mục tiêu<textarea name="objectives" rows="2" maxlength="5000"></textarea></label>
                    <button class="button" type="submit">Gửi đề xuất</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<section class="panel x-section">
    <div class="panel-heading"><div><p class="eyebrow">Tra cứu đồ án các khóa trước</p><h2>Kho đề tài</h2></div></div>
    <form method="get" class="x-inline-form"><input type="hidden" name="page" value="student/thesis"><?php if ($batch): ?><input type="hidden" name="batch" value="<?= (int) $batch['id'] ?>"><?php endif; ?><input type="search" name="q" value="<?= screen_escape($screenData['archive_query']) ?>" placeholder="Từ khóa: tên đề tài, lĩnh vực, công nghệ"><button class="button" type="submit">Tìm</button></form>
    <?php foreach ($screenData['archive'] as $item): ?><div class="x-row-line"><span><strong><?= screen_escape($item['title']) ?></strong> · <?= screen_escape($item['batch_name'] . ' · GV ' . ($item['lecturer_name'] ?? '')) ?><br><span class="x-meta"><?= screen_escape(mb_strimwidth((string) $item['description'], 0, 200, '…', 'UTF-8')) ?></span></span></div><?php endforeach; ?>
    <?php if ($screenData['archive_query'] !== '' && !$screenData['archive']): ?><?= x_empty('Không tìm thấy đề tài phù hợp ở các đợt đã đóng.') ?><?php endif; ?>
</section>

<?php // Kho đồ án các khóa trước (thu gọn, mở khi cần tham khảo). ?>
<details class="panel x-section x-details"<?= ($screenData['archive_search'] ?? '') !== '' ? ' open' : '' ?>>
    <summary><strong>Kho đồ án các khóa trước</strong> <span class="x-meta">· tham khảo tên đề tài, công nghệ<?= !empty($screenData['archive_download']) ? ', tải quyển đã bảo vệ' : '' ?></span></summary>
    <form method="get" class="x-filter"><input type="hidden" name="page" value="student/thesis"><input type="search" name="archive_q" value="<?= screen_escape($screenData['archive_search'] ?? '') ?>" placeholder="Tìm theo tên, lĩnh vực, công nghệ"><button class="button" type="submit">Tìm</button></form>
    <ul class="x-plain-list">
        <?php foreach ($screenData['archive'] ?? [] as $archived): ?>
            <li class="x-row-line"><span><strong><?= screen_escape($archived['title']) ?></strong><br><small class="x-meta"><?= screen_escape(implode(' · ', array_filter([$archived['batch_name'], $archived['field'], $archived['technologies']]))) ?></small></span>
                <?php if (!empty($screenData['archive_download']) && $archived['submission_id']): ?><a class="table-download" href="?download=archive&amp;id=<?= (int) $archived['submission_id'] ?>">Tải quyển ↓</a><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if (empty($screenData['archive'])): ?><li class="x-meta">Chưa có đồ án nào trong kho.</li><?php endif; ?>
    </ul>
</details>
