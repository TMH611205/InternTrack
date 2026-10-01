<?php

declare(strict_types=1);

const APP_NAME = 'InternTrack';
const APP_CHARSET = 'UTF-8';

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
    header('Location: ?page=' . rawurlencode($page), true, 303);
    exit;
}
