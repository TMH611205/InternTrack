<?php
// Giảng viên · Sinh viên đồ án (hướng dẫn) và Phản biện & hội đồng (lecturer/defense dùng chung tệp này).
require __DIR__ . '/_thesis-parts.php';
$isDefense = $screen === 'lecturer/defense';
?>
<?php if ($isDefense && $screenData['councils']): ?>
<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">Hội đồng bạn tham gia</p><h2>Lịch hội đồng</h2></div></div>
    <div class="table-scroll"><table class="x-compact"><thead><tr><th>Hội đồng</th><th>Đợt</th><th>Vai trò</th><th>Ngày · giờ · phòng</th><th></th></tr></thead><tbody>
        <?php foreach ($screenData['councils'] as $council): ?>
            <tr><td><?= screen_escape($council['name']) ?></td><td><?= screen_escape($council['batch_name']) ?></td><td><?= screen_escape(COUNCIL_ROLE_LABELS[$council['member_role']]) ?></td><td><?= screen_escape(($council['defense_date'] ? date('d/m/Y', (int) strtotime($council['defense_date'])) : 'Chưa xếp') . ' · ' . substr((string) $council['start_time'], 0, 5) . ' · ' . ($council['room'] ?: '—')) ?></td><td><a class="table-edit-button" href="?export=councils&amp;batch=<?= (int) $council['batch_id'] ?>" target="_blank">Lịch bảo vệ</a></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>

<section class="x-card-list">
    <?php foreach ($screenData['theses'] as $thesis): ?>
        <article class="x-card x-thesis">
            <div class="x-card-head">
                <div><h3><?= screen_escape($thesis['student_name'] . ' · ' . $thesis['student_code']) ?></h3>
                    <p class="x-meta"><?= screen_escape($thesis['topic_title'] . ' · ' . $thesis['batch_name']) ?><?= $isDefense ? ' · GVHD: ' . screen_escape($thesis['supervisor_name'] ?? '—') : '' ?></p>
                    <?php if ($thesis['council_name'] || $thesis['defense_at']): ?><p class="x-meta">Bảo vệ: <?= screen_escape(($thesis['council_name'] ?? 'Chưa xếp hội đồng') . ($thesis['room'] ? ' · phòng ' . $thesis['room'] : '') . ' · ' . thesis_datetime_label($thesis['defense_at'])) ?></p><?php endif; ?></div>
                <div class="x-badges"><?= x_badge(THESIS_STATUS_LABELS[$thesis['status']] ?? $thesis['status']) ?><?php foreach ($thesis['sheets'] as $sheet): ?><?= x_badge(THESIS_ROLE_LABELS[$sheet['role']], 'badge--neutral') ?><?php endforeach; ?><?php if ($thesis['final_score'] !== null): ?><?= x_badge('Tổng kết ' . $thesis['final_score'], 'badge--positive') ?><?php endif; ?></div>
            </div>
            <div class="x-card-tools">
                <a class="table-edit-button" href="?page=<?= $user['role'] ?>/messages&amp;with=<?= (int) $thesis['student_user_id'] ?>">Nhắn tin</a>
                <?php if ($isDefense && in_array('council', array_column($thesis['sheets'], 'role'), true)): ?><a class="table-edit-button" href="?export=minutes&amp;id=<?= (int) $thesis['id'] ?>" target="_blank">Biên bản bảo vệ</a><?php endif; ?>
            </div>
            <details class="x-details" <?= count($screenData['theses']) === 1 ? 'open' : '' ?>><summary>Chi tiết đồ án</summary>
                <?php if (!$isDefense): ?><?= $renderMilestones($thesis, empty($thesis['locked_at'])) ?><?= $renderMeetings($thesis, empty($thesis['locked_at']), empty($thesis['locked_at'])) ?><?php endif; ?>
                <?= $renderSubmissions($thesis, !$isDefense && empty($thesis['locked_at']), $isDefense ? 'defense' : '') ?>
                <?php foreach ($thesis['sheets'] as $sheet): ?><?= $renderScoreSheet($thesis, $sheet) ?><?php endforeach; ?>
            </details>
        </article>
    <?php endforeach; ?>
    <?php if (!$screenData['theses']): ?><?= x_empty($isDefense ? 'Bạn chưa được phân công phản biện hoặc tham gia hội đồng nào.' : 'Bạn chưa hướng dẫn sinh viên đồ án nào.') ?><?php endif; ?>
</section>
