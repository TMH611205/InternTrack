<?php

declare(strict_types=1);

// Trả về singleton PDO cho toàn bộ ứng dụng.
// Mỗi request chỉ mở một kết nối database duy nhất để tiết kiệm tài nguyên và dễ quản lý.
function database(): PDO
{
    static $connection = null;

    // Nếu kết nối đã tồn tại thì dùng lại thay vì mở mới.
    if ($connection instanceof PDO) {
        return $connection;
    }

    // Đọc cấu hình từ .env hoặc biến môi trường; fallback về mặc định XAMPP.
    $host = function_exists('app_env') ? app_env('DB_HOST', '127.0.0.1') : (getenv('DB_HOST') ?: '127.0.0.1');
    $port = function_exists('app_env') ? app_env('DB_PORT', '3306') : (getenv('DB_PORT') ?: '3306');
    $name = function_exists('app_env') ? app_env('DB_NAME', 'interntrack') : (getenv('DB_NAME') ?: 'interntrack');
    $username = function_exists('app_env') ? app_env('DB_USER', 'root') : (getenv('DB_USER') ?: 'root');
    $password = function_exists('app_env') ? app_env('DB_PASSWORD', '') : (getenv('DB_PASSWORD') ?: '');
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
    } catch (PDOException $error) {
        error_log('InternTrack database connection failed: ' . $error->getMessage());
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra cấu hình MySQL.', 0, $error);
    }

    return $connection;
}
