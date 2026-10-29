<?php
// Quản trị · Nhật ký hệ thống (ai sửa / ghép / chấm gì, lúc nào), sao lưu và phục hồi dữ liệu.
require __DIR__ . '/_common.php';
$filters = $screenData['filters'];
?>
<div class="x-split">
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Tệp .zip gồm cơ sở dữ liệu và mọi tệp tải lên · tự động mỗi ngày qua scripts/cron.php</p><h2>Sao lưu</h2></div></div>
        <p class="x-text">Nên sao lưu trước mỗi lần công bố kết quả ghép, công bố điểm hoặc đóng đợt. Tệp đính kèm trong thư mục <code>uploads/</code> cần sao lưu riêng.</p>
        <a class="button button--primary" href="?export=backup">Tải bản sao lưu</a>
        <?php if ($screenData['locked_batches']): ?><p class="x-meta">Đợt đã chốt dữ liệu: <?= screen_escape(implode(', ', array_map(static fn($row) => $row['name'] . ' (' . thesis_datetime_label($row['locked_at']) . ')', $screenData['locked_batches']))) ?></p><?php endif; ?>
    </section>
    <section class="panel x-section">
        <div class="panel-heading"><div><p class="eyebrow">Ghi đè toàn bộ dữ liệu hiện tại</p><h2>Phục hồi</h2></div></div>
        <form method="post" enctype="multipart/form-data" action="<?= screen_escape($xAction) ?>" class="workspace-form workspace-form--plain" onsubmit="return confirm('Phục hồi sẽ GHI ĐÈ toàn bộ dữ liệu hiện tại. Tiếp tục?');"><?= $csrfField ?><input type="hidden" name="action" value="backup_restore">
            <label>Tệp sao lưu (.zip hoặc .sql do hệ thống tạo)<input type="file" name="backup_file" accept=".zip,.sql" required></label>
            <label>Gõ KHÔI PHỤC để xác nhận<input name="confirm" maxlength="20" autocomplete="off" required></label>
            <button class="button" type="submit">Phục hồi dữ liệu</button>
        </form>
    </section>
</div>

<section class="panel table-panel">
    <div class="table-toolbar"><div><p class="eyebrow">300 thao tác gần nhất theo bộ lọc</p><h2>Nhật ký hệ thống</h2></div><a class="table-edit-button" href="?export=audit">Xuất Excel</a></div>
    <form method="get" class="x-filter"><input type="hidden" name="page" value="admin/audit">
        <label>Thao tác<select name="action_filter"><option value="">Tất cả</option><?php foreach ($screenData['actions'] as $action): ?><option <?= $filters['action'] === $action ? 'selected' : '' ?>><?= screen_escape($action) ?></option><?php endforeach; ?></select></label>
        <label>Ngày<input type="date" name="date" value="<?= screen_escape($filters['date']) ?>"></label>
        <label>Người / đối tượng<input type="search" name="q" value="<?= screen_escape($filters['q']) ?>"></label>
        <button class="button button--primary" type="submit">Lọc</button>
    </form>
    <div class="table-scroll"><table class="x-compact"><thead><tr><th>Thời điểm</th><th>Người thực hiện</th><th>Thao tác</th><th>Đối tượng</th><th>Chi tiết</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($screenData['logs'] as $log): ?><tr><td><?= screen_escape(date('d/m/Y H:i:s', (int) strtotime($log['created_at']))) ?></td><td><?= screen_escape(($log['full_name'] ?? 'Khách') . ' · ' . ($log['role'] ?? '')) ?></td><td><code><?= screen_escape($log['action']) ?></code></td><td><?= screen_escape($log['target'] ?? '') ?></td><td class="x-wrap"><?= screen_escape($log['details'] ?? '') ?><?php foreach ((array) json_decode((string) ($log['changes'] ?? ''), true) as $field => [$old, $new]): ?><div class="x-change"><code><?= screen_escape($field) ?></code>: <del><?= screen_escape($old ?? '∅') ?></del> → <ins><?= screen_escape($new ?? '∅') ?></ins></div><?php endforeach; ?></td><td><?= screen_escape($log['ip_address'] ?? '') ?></td></tr><?php endforeach; ?>
        <?php if (!$screenData['logs']): ?><tr><td colspan="6"><?= x_empty('Chưa có nhật ký phù hợp.') ?></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
