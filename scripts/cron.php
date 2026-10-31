<?php

declare(strict_types=1);

// Tác vụ định kỳ của InternTrack, chạy bằng dòng lệnh (không qua web). Nên chạy mỗi giờ:
//   Windows (Task Scheduler): C:\xampp\php\php.exe C:\xampp\htdocs\InternTrack\scripts\cron.php
//   Linux (crontab -e):       0 * * * * php /var/www/InternTrack/scripts/cron.php
// Mỗi lần chạy: nhắc hạn / cảnh báo (tối đa một lần mỗi ngày) và sao lưu tự động một lần mỗi ngày vào backups/ (giữ 14 bản).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ActionController.php';

reminders_run_daily();
echo date('Y-m-d H:i:s') . " · nhắc hạn: đã kiểm tra\n";

if (app_setting('backup_last_run', '') !== date('Y-m-d') && app_setting('backup_auto', '1') === '1') {
    try {
        $path = backup_store(14);
        app_setting_set('backup_last_run', date('Y-m-d'));
        echo date('Y-m-d H:i:s') . ' · sao lưu: ' . $path . ' (' . round(filesize($path) / 1048576, 1) . " MB)\n";
    } catch (Throwable $error) {
        error_log('InternTrack backup failed: ' . $error->getMessage());
        foreach (page_all('SELECT id FROM users WHERE role = \'admin\' AND status = \'active\' AND scope_faculty IS NULL') as $admin) {
            notify_user((int) $admin['id'], 'audit', 'Sao lưu tự động thất bại', 'Hãy kiểm tra dung lượng đĩa và nhật ký lỗi của máy chủ.', 'admin/audit');
        }
        fwrite(STDERR, 'Sao lưu thất bại: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
