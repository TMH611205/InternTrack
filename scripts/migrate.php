<?php

declare(strict_types=1);

// Nâng cấp CSDL đang chạy lên cấu trúc mới nhất của database/interntrack.sql (chạy bằng dòng lệnh, chạy lại nhiều lần vẫn an toàn):
//   C:\xampp\php\php.exe scripts\migrate.php
// 1) Tạo các bảng / view còn thiếu (chạy interntrack.sql: CREATE TABLE IF NOT EXISTS, CREATE OR REPLACE VIEW).
// 2) Bảng đã có: thêm cột còn thiếu và cập nhật danh sách giá trị ENUM theo định nghĩa trong interntrack.sql.
// 3) Chỉ mục duy nhất mới và dữ liệu chuyển đổi một lần (tin tuyển dụng cũ coi như đã duyệt).
// Nên sao lưu trước khi chạy (trang Nhật ký & sao lưu, hoặc scripts/cron.php).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$connection = database();
// Tùy chọn --database=<tên>: nâng cấp một CSDL khác trên cùng máy chủ (ví dụ bản sao để chạy thử).
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--database=(\w+)$/', $argument, $match)) {
        $connection->exec('USE `' . $match[1] . '`');
    }
}
$schemaFile = (string) file_get_contents(__DIR__ . '/../database/interntrack.sql');
$schema = str_replace("\r\n", "\n", $schemaFile);
// Chỉ lấy phần cấu trúc: dữ liệu mẫu sau dòng đánh dấu không bao giờ được đưa vào CSDL đang chạy.
$schema = explode('-- ==== DỮ LIỆU MẪU ====', $schema, 2)[0];
$databaseName = (string) $connection->query('SELECT DATABASE()')->fetchColumn();

// Bảng / cột hiện có trước khi chạy.
$existing = [];
foreach ($connection->query('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $row) {
    $existing[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
}

// Định nghĩa cột theo interntrack.sql: [bảng => [[cột, định nghĩa, cột đứng trước], ...]].
$definitions = [];
preg_match_all('/CREATE TABLE IF NOT EXISTS `?(\w+)`?\s*\((.*?)\n\)\s*ENGINE/s', $schema, $tables, PREG_SET_ORDER);
foreach ($tables as [, $table, $body]) {
    $previous = null;
    // Tách các mục theo dấu phẩy ở cấp ngoài cùng (bỏ qua dấu phẩy trong ngoặc và chuỗi), bỏ chú thích.
    $body = (string) preg_replace('/--[^\n]*/', '', $body);
    $items = [];
    $current = '';
    $depth = 0;
    $quoted = false;
    foreach (str_split($body) as $char) {
        if ($char === "'") {
            $quoted = !$quoted;
        } elseif (!$quoted && $char === '(') {
            $depth++;
        } elseif (!$quoted && $char === ')') {
            $depth--;
        }
        if ($char === ',' && $depth === 0 && !$quoted) {
            $items[] = $current;
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $items[] = $current;
    foreach ($items as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', $line));
        if ($line === '' || preg_match('/^(CONSTRAINT|INDEX|KEY|UNIQUE|PRIMARY KEY|FOREIGN KEY)\b/i', $line)) {
            continue;
        }
        if (preg_match('/^`?(\w+)`?\s+(.+)$/', $line, $match)) {
            $definitions[$table][] = [$match[1], $match[2], $previous];
            $previous = $match[1];
        }
    }
}

$changes = 0;
foreach ($definitions as $table => $columns) {
    if (!isset($existing[$table])) {
        continue;
    }
    foreach ($columns as [$column, $definition, $after]) {
        // Cột mới: không thêm ràng buộc khóa chính / AUTO_INCREMENT vào bảng đã có.
        $definitionForAlter = preg_replace('/\s+(PRIMARY KEY|AUTO_INCREMENT)\b/i', '', $definition);
        if (!isset($existing[$table][$column])) {
            $connection->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definitionForAlter . ($after ? ' AFTER `' . $after . '`' : ' FIRST'));
            echo "+ {$table}.{$column}\n";
            $changes++;
            continue;
        }
        // ENUM có thêm giá trị mới: cập nhật định nghĩa cột.
        if (preg_match('/^ENUM\s*\((.*?)\)/i', $definition, $enum)) {
            $wanted = strtolower('enum(' . preg_replace('/\s*,\s*/', ',', $enum[1]) . ')');
            if (strtolower(str_replace(' ', '', $existing[$table][$column])) !== str_replace(' ', '', $wanted)) {
                $connection->exec('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` ' . $definitionForAlter);
                echo "~ {$table}.{$column} (ENUM)\n";
                $changes++;
            }
        }
    }
}

// Bảng, view mới (và view được tạo lại theo cột mới).
$script = (string) preg_replace(['/^\s*CREATE DATABASE[^;]*;/mi', '/^\s*USE\s+\w+\s*;/mi'], '', $schema);
foreach (preg_split('/;\s*\n/', $script) ?: [] as $statement) {
    $statement = trim((string) preg_replace('/^--.*$/m', '', $statement));
    if ($statement !== '') {
        $connection->exec($statement);
    }
}

// Chỉ mục duy nhất thêm sau.
$uniqueIndexes = [['users', 'uq_users_google_sub', 'google_sub']];
foreach ($uniqueIndexes as [$table, $name, $column]) {
    $has = $connection->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $has->execute([$table, $name]);
    if (!(int) $has->fetchColumn()) {
        $connection->exec('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $name . '` UNIQUE (`' . $column . '`)');
        echo "+ index {$table}.{$name}\n";
        $changes++;
    }
}

// Dữ liệu chuyển đổi một lần: tin tuyển dụng đang mở / đã đóng từ trước khi có bước duyệt được coi là đã duyệt.
if (isset($existing['positions']) && !isset($existing['positions']['approved_at'])) {
    $connection->exec('UPDATE positions SET approved_at = created_at WHERE status IN (\'open\', \'closed\') AND approved_at IS NULL');
    echo "* tin tuyển dụng cũ: đánh dấu đã duyệt\n";
}

echo $changes ? "Đã nâng cấp {$databaseName}: {$changes} thay đổi cấu trúc.\n" : "{$databaseName} đã ở phiên bản mới nhất.\n";
