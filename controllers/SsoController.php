<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuthController.php';

// Đăng nhập một lần (SSO) bằng tài khoản Google của email trường (Google Workspace).
// Bật khi có GOOGLE_CLIENT_ID và GOOGLE_CLIENT_SECRET trong .env; SSO_EMAIL_DOMAIN mặc định vinhuni.edu.vn.
// Chỉ đăng nhập vào tài khoản ĐÃ CÓ trong hệ thống (khớp email); không tự tạo tài khoản mới.

function sso_enabled(): bool
{
    return (string) app_env('GOOGLE_CLIENT_ID', '') !== '' && (string) app_env('GOOGLE_CLIENT_SECRET', '') !== '';
}

function sso_domain(): string
{
    return strtolower(trim((string) app_env('SSO_EMAIL_DOMAIN', 'vinhuni.edu.vn')));
}

// Địa chỉ Google gọi lại sau khi đăng nhập; phải khai báo y hệt trong Google Cloud Console.
function sso_redirect_uri(): string
{
    $configured = (string) app_env('APP_URL', '');
    if ($configured !== '') {
        return rtrim($configured, '/') . '/index.php?sso=callback';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path . '/index.php?sso=callback';
}

// Bước 1: chuyển sang trang đăng nhập Google (kèm state chống giả mạo).
function sso_start(): never
{
    if (!sso_enabled()) {
        throw new DomainException('Đăng nhập bằng email trường chưa được cấu hình.');
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['sso_state'] = $state;
    $query = http_build_query([
        'client_id' => app_env('GOOGLE_CLIENT_ID'),
        'redirect_uri' => sso_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'hd' => sso_domain(),
        'state' => $state,
        'prompt' => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    exit;
}

// Bước 2: đổi mã lấy id_token (gọi thẳng máy chủ Google qua HTTPS), kiểm tra các trường rồi đăng nhập.
function sso_callback(array $query): array
{
    $state = (string) ($query['state'] ?? '');
    $expected = (string) ($_SESSION['sso_state'] ?? '');
    unset($_SESSION['sso_state']);
    if ($expected === '' || !hash_equals($expected, $state)) {
        throw new DomainException('Phiên đăng nhập bằng email trường không hợp lệ. Hãy thử lại.');
    }
    if (!empty($query['error']) || empty($query['code'])) {
        throw new DomainException('Bạn đã hủy hoặc Google từ chối đăng nhập.');
    }
    $curl = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => (string) $query['code'],
            'client_id' => app_env('GOOGLE_CLIENT_ID'),
            'client_secret' => app_env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => sso_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]),
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $token = is_string($response) ? json_decode($response, true) : null;
    if ($status !== 200 || empty($token['id_token'])) {
        throw new DomainException('Không xác thực được với Google. Hãy thử lại sau.');
    }
    $parts = explode('.', (string) $token['id_token']);
    $claims = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
    $email = strtolower((string) ($claims['email'] ?? ''));
    if (!is_array($claims)
        || ($claims['aud'] ?? '') !== app_env('GOOGLE_CLIENT_ID')
        || !in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
        || (int) ($claims['exp'] ?? 0) < time()
        || empty($claims['email_verified'])) {
        throw new DomainException('Thông tin đăng nhập từ Google không hợp lệ.');
    }
    if (!str_ends_with($email, '@' . sso_domain()) || strtolower((string) ($claims['hd'] ?? '')) !== sso_domain()) {
        throw new DomainException('Chỉ đăng nhập bằng email @' . sso_domain() . '.');
    }
    $account = page_one('SELECT id, status, google_sub FROM users WHERE google_sub = ? OR LOWER(email) = ? ORDER BY google_sub = ? DESC LIMIT 1', [(string) $claims['sub'], $email, (string) $claims['sub']]);
    if (!$account || $account['status'] !== 'active') {
        throw new DomainException('Email ' . $email . ' chưa có tài khoản đang hoạt động trong hệ thống. Hãy liên hệ phòng đào tạo.');
    }
    if ($account['google_sub'] === null) {
        database()->prepare('UPDATE users SET google_sub = ? WHERE id = ?')->execute([(string) $claims['sub'], (int) $account['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    unset($_SESSION['active_role']);
    database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $account['id']]);
    return authenticated_user() ?? throw new DomainException('Không đăng nhập được.');
}
