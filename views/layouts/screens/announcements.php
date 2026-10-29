<?php
// Thông báo chung (theo đợt, lớp, vai trò hoặc theo nhóm) và lịch chung dạng lưới tháng / danh sách. Dùng cho mọi vai trò.
require __DIR__ . '/_common.php';
$isAdmin = $user['role'] === 'admin';
$audienceLabels = ['all' => 'Tất cả', 'student' => 'Sinh viên', 'lecturer' => 'Giảng viên', 'company' => 'Doanh nghiệp'];
$kindLabels = ['deadline' => ['Hạn chót', 'badge--attention'], 'meeting' => ['Lịch gặp', 'badge--active'], 'defense' => ['Bảo vệ', 'badge--positive'], 'task' => ['Nhiệm vụ', 'badge--waiting']];
$groups = $screenData['groups'] ?? [];
?>
<?php if ($isAdmin || $groups): ?>
<details class="panel x-section x-compose"<?= $isAdmin ? ' open' : '' ?>>
    <summary><span class="eyebrow">Gửi thông báo trong hệ thống, có thể kèm email</span><strong><?= $isAdmin ? 'Đăng thông báo' : 'Gửi thông báo cho nhóm của tôi' ?></strong></summary>
    <form method="post" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain"><?= $csrfField ?><input type="hidden" name="action" value="announcement_save">
        <label>Tiêu đề<input name="title" maxlength="200" required></label>
        <label>Nội dung<textarea name="body" rows="4" maxlength="10000" required></textarea></label>
        <div class="form-grid form-grid--3">
            <?php if ($isAdmin): ?>
                <label>Gửi tới<select name="audience"><?php foreach ($audienceLabels as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select></label>
                <label>Theo đợt<select name="batch_id"><option value="">Mọi đợt</option><?php foreach ($screenData['batches'] as $batch): ?><option value="<?= (int) $batch['id'] ?>"><?= screen_escape($batch['name']) ?></option><?php endforeach; ?></select></label>
                <label>Theo lớp (sinh viên)<select name="class_name"><option value="">Mọi lớp</option><?php foreach ($screenData['classes'] as $className): ?><option><?= screen_escape($className) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <?php if ($groups): ?>
                <label><?= $isAdmin ? 'Hoặc gửi cho một nhóm' : 'Nhóm nhận' ?><select name="group" <?= $isAdmin ? '' : 'required' ?>><?php if ($isAdmin): ?><option value="">— Không, gửi theo lựa chọn bên trái —</option><?php endif; ?><?php foreach ($groups as $key => $label): ?><option value="<?= screen_escape($key) ?>"><?= screen_escape($label) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
        </div>
        <label class="x-check"><input type="checkbox" name="send_email" value="1"> Gửi kèm email (cần MAIL_NOTIFY=1 trong .env)</label>
        <button class="button button--primary" type="submit">Đăng thông báo</button>
    </form>
</details>
<?php endif; ?>

<div class="x-stack">
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Mới nhất trước</p><h2>Thông báo</h2></div></div>
        <?php foreach ($screenData['announcements'] as $announcement): ?>
            <article class="x-announcement">
                <div class="x-row-line"><strong><?= screen_escape($announcement['title']) ?></strong><span class="x-meta"><?= screen_escape(thesis_datetime_label($announcement['created_at'])) ?></span></div>
                <p class="x-meta"><?= screen_escape(implode(' · ', array_filter([$announcement['group_label'] ?: ($audienceLabels[$announcement['audience']] ?? ''), $announcement['batch_name'], $announcement['class_name'] ? 'Lớp ' . $announcement['class_name'] : null, $announcement['author'] ? 'Người đăng: ' . $announcement['author'] : null]))) ?></p>
                <p class="x-text"><?= nl2br(screen_escape($announcement['body'])) ?></p>
                <?php if ($isAdmin || (int) $announcement['created_by'] === (int) $user['id']): ?><?= $xPost('announcement_delete', ['announcement_id' => (int) $announcement['id']], 'Xóa', 'inline-action', 'Xóa thông báo này?') ?><?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$screenData['announcements']): ?><?= x_empty('Chưa có thông báo nào.') ?><?php endif; ?>
    </section>

    <?php if (!$isAdmin): ?>
    <?php
    $month = $screenData['month'];
    $first = strtotime($month . '-01');
    $base = '?page=' . rawurlencode($screen);
    ?>
    <section class="panel x-section">
        <div class="panel-heading">
            <div><p class="eyebrow">Hạn chót, lịch gặp, bảo vệ, phỏng vấn, nhiệm vụ</p><h2>Lịch chung</h2></div>
            <div class="x-links">
                <a class="table-edit-button" href="<?= $base ?>&amp;view=<?= $screenData['view'] === 'grid' ? 'list' : 'grid' ?>&amp;month=<?= $month ?>"><?= $screenData['view'] === 'grid' ? 'Dạng danh sách' : 'Dạng lưới' ?></a>
                <a class="table-edit-button" href="?export=ical" title="Thêm vào Google Calendar / Outlook">Tải lịch .ics</a>
            </div>
        </div>
        <?php if ($screenData['view'] === 'grid'): ?>
            <?php // Lưới tháng: mỗi ô là một ngày, sự kiện hiện thành nhãn ngắn (rê chuột xem chi tiết). ?>
            <div class="x-month-nav"><a href="<?= $base ?>&amp;month=<?= date('Y-m', strtotime('-1 month', $first)) ?>" aria-label="Tháng trước">‹</a><strong>Tháng <?= date('m/Y', $first) ?></strong><a href="<?= $base ?>&amp;month=<?= date('Y-m', strtotime('+1 month', $first)) ?>" aria-label="Tháng sau">›</a></div>
            <?php
            $byDay = [];
            foreach ($screenData['calendar'] as $item) {
                if (date('Y-m', (int) strtotime((string) $item['at'])) === $month) {
                    $byDay[(int) date('j', (int) strtotime((string) $item['at']))][] = $item;
                }
            }
            $offset = ((int) date('N', $first)) - 1;
            $days = (int) date('t', $first);
            ?>
            <div class="x-cal-grid">
                <?php foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $weekday): ?><span class="x-cal-head"><?= $weekday ?></span><?php endforeach; ?>
                <?php for ($cell = 0; $cell < $offset; $cell++): ?><span class="x-cal-day is-empty"></span><?php endfor; ?>
                <?php for ($day = 1; $day <= $days; $day++): $isToday = date('Y-m-d') === $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
                    <span class="x-cal-day<?= $isToday ? ' is-today' : '' ?>"><b><?= $day ?></b>
                        <?php foreach ($byDay[$day] ?? [] as $item): ?><i class="x-cal-event x-cal-event--<?= screen_escape($item['kind']) ?>" title="<?= screen_escape(date('H:i', (int) strtotime((string) $item['at'])) . ' · ' . $item['title'] . ' · ' . $item['meta']) ?>"><?= screen_escape(mb_strimwidth($item['title'], 0, 22, '…', 'UTF-8')) ?></i><?php endforeach; ?>
                    </span>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <?php $listMonth = ''; foreach ($screenData['calendar'] as $item): $itemMonth = date('m/Y', (int) strtotime((string) $item['at'])); $past = strtotime((string) $item['at']) < time(); ?>
                <?php if ($itemMonth !== $listMonth): $listMonth = $itemMonth; ?><h3 class="x-month">Tháng <?= screen_escape($listMonth) ?></h3><?php endif; ?>
                <div class="x-calendar-item<?= $past ? ' is-past' : '' ?>">
                    <span class="x-calendar-date"><strong><?= date('d', (int) strtotime((string) $item['at'])) ?></strong><small><?= date('H:i', (int) strtotime((string) $item['at'])) ?></small></span>
                    <span><strong><?= screen_escape($item['title']) ?></strong><br><span class="x-meta"><?= screen_escape($item['meta']) ?></span></span>
                    <?= x_badge(...$kindLabels[$item['kind']]) ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$screenData['calendar']): ?><?= x_empty('Không có hạn chót hay lịch hẹn nào sắp tới.') ?><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
