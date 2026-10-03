<?php

declare(strict_types=1);

const APP_NAME = 'InternTrack';
const APP_CHARSET = 'UTF-8';

date_default_timezone_set('Asia/Ho_Chi_Minh');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

// Bộ biểu tượng dạng đường nét (24x24) dùng chung cho menu và thanh trên cùng; kế thừa màu chữ qua currentColor.
function ui_icon(string $name): string
{
    static $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'check' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 12 3 3 5-6"/>',
        'book' => '<path d="M2 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2z"/><path d="M22 4h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M8 17v-6M13 17V7M18 17v-9"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
        'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4.2a4 4 0 0 1 0 7.6M18 14.5a7 7 0 0 1 4 6.5"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'cap' => '<path d="M22 10 12 5 2 10l10 5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
        'trend' => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    ];

    return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['file']) . '</svg>';
}

// URL ảnh đại diện của một tài khoản; tham số v đổi khi người dùng tải ảnh mới nên mọi nơi hiển thị đều cập nhật ngay.
function app_avatar_url(array $person): string
{
    $id = (int) ($person['user_id'] ?? $person['id'] ?? 0);
    return '?download=avatar&id=' . $id . '&v=' . substr(md5((string) ($person['avatar'] ?? '')), 0, 8);
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function app_valid_csrf(): bool
{
    $submitted = $_POST['_csrf'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $submitted);
}

// Ghi lại thông báo trạng thái (thành công, lỗi, cảnh báo) để hiển thị ở trang tiếp theo.
function app_set_flash(string $type, string $message, array $details = []): void
{
    $_SESSION['_flash'] = array_merge(['type' => $type, 'message' => $message], $details);
}

// Lấy flash message đã được lưu, đồng thời xóa nó để tránh hiển thị lặp lại.
function app_take_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

// Đọc giá trị cấu hình từ biến môi trường hoặc file .env của dự án.
// Tính chất: ưu tiên biến môi trường hệ thống, sau đó fallback về file .env để phù hợp với XAMPP/Apache.
function app_env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value !== false && $value !== null && $value !== '') {
        return $value;
    }

    if (array_key_exists($key, $_ENV) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    static $loaded = false;
    static $values = [];

    if (!$loaded) {
        $dotenvPath = dirname(__DIR__) . '/.env';
        if (is_file($dotenvPath)) {
            foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                    continue;
                }

                if (!str_contains($trimmed, '=')) {
                    continue;
                }

                [$name, $rawValue] = array_map('trim', explode('=', $trimmed, 2));
                $value = $rawValue;
                if (preg_match('/^(?:"|\').*(?:"|\')$/', $value) === 1) {
                    $value = substr($value, 1, -1);
                }
                $values[$name] = $value;
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
        $loaded = true;
    }

    if (array_key_exists($key, $values) && $values[$key] !== '') {
        return $values[$key];
    }

    foreach ($values as $name => $value) {
        if (strcasecmp((string) $name, $key) === 0 && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function app_send_password_reset_otp(string $email, string $otp): bool
{
    $fallbackSender = (string) (app_env('MAIL_USERNAME', '') ?: '');
    $sender = (string) (app_env('MAIL_FROM', $fallbackSender) ?: $fallbackSender);
    $username = (string) (app_env('MAIL_USERNAME', $sender) ?: $sender);
    $password = (string) (app_env('MAIL_PASSWORD', '') ?: '');
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        error_log('InternTrack password reset OTP could not be sent: MAIL_FROM or MAIL_PASSWORD is not configured.');
        return false;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }

    try {
        require_once $autoload;
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) (app_env('MAIL_HOST', 'smtp.gmail.com') ?: 'smtp.gmail.com');
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int) (app_env('MAIL_PORT', 587) ?: 587);
        $mailer->Timeout = 15;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($sender, (string) (app_env('MAIL_FROM_NAME', APP_NAME) ?: APP_NAME));
        $mailer->addAddress($email);
        $mailer->Subject = 'InternTrack - Ma OTP dat lai mat khau';
        $mailer->Body = "Xin chào,\r\n\r\nMã OTP đặt lại mật khẩu InternTrack của bạn là: " . $otp . "\r\nMã có hiệu lực trong 60 giây và chỉ dùng một lần.\r\n\r\nNếu bạn không yêu cầu mã này, hãy bỏ qua email.\r\n";
        $mailer->send();
        return true;
    } catch (Throwable $error) {
        error_log('InternTrack Gmail SMTP delivery failed: ' . $error->getMessage());
        return false;
    }
}

// Chuyển hướng người dùng đến một route cụ thể trong dự án.
// Dùng 303 để đảm bảo đúng chuẩn redirect sau khi thực hiện POST.
function app_redirect(string $page): never
{
    $location = '?page=' . rawurlencode($page);
    // Giữ nguyên tab nhóm tài khoản ở trang quản trị sau khi thao tác.
    $group = $_GET['group'] ?? null;
    if ($page === 'admin/users' && is_string($group) && in_array($group, ['student', 'lecturer', 'company', 'admin'], true)) {
        $location .= '&group=' . $group;
    }
    header('Location: ' . $location, true, 303);
    exit;
}
