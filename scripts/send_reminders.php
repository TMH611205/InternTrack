<?php

declare(strict_types=1);

// Chạy trên dòng lệnh, ví dụ mỗi sáng bằng cron hoặc Windows Task Scheduler:
//   php scripts/send_reminders.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy trên dòng lệnh.');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../controllers/ReminderController.php';

$result = reminder_run();
echo 'Nhắc nhật ký: ' . $result['diary'] . ', nhắc nhiệm vụ: ' . $result['tasks'] . ', nhắc xác minh doanh nghiệp: ' . $result['companies'] . ', email đã gửi: ' . $result['emails'] . PHP_EOL;
