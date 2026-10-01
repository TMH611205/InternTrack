<?php
// Xác định trạng thái màn hình auth để render đúng form: quên mật khẩu, xác minh OTP hay đăng nhập.
// Khai báo biến global và giá trị mặc định để file luôn an toàn khi được render từ nhiều route khác nhau.
global $screen, $flash;
$screen = $screen ?? '';
$flash = isset($flash) && is_array($flash) ? $flash : null;
$isForgot = $screen === 'auth/forgot-password';
$isReset = $screen === 'auth/reset-password';
$resetAuthorization = $_SESSION['password_reset_authorized'] ?? null;
$isPasswordStep = $isReset && is_array($resetAuthorization) && (int) ($resetAuthorization['expires_at'] ?? 0) >= time();
$resetEmail = is_string($_SESSION['password_reset_email'] ?? null) ? $_SESSION['password_reset_email'] : '';
$authFlash = isset($flash) && is_array($flash) ? $flash : null;
$authEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$authPageTitle = $isReset ? 'Đặt lại mật khẩu' : ($isForgot ? 'Khôi phục mật khẩu' : 'Đăng nhập');
$authPageDescription = 'Hệ thống InternTrack giúp sinh viên, doanh nghiệp và giảng viên quản lý thực tập hiệu quả, an toàn và dễ theo dõi.';
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183c32">
    <meta name="description" content="<?= $authEscape($authPageDescription) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= $authEscape($authPageTitle) ?> · InternTrack">
    <meta property="og:description" content="<?= $authEscape($authPageDescription) ?>">
    <meta property="og:type" content="website">
    <title><?= $authEscape($authPageTitle) ?> · InternTrack</title>
    <link rel="canonical" href="https://interntrack.local/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">
    <main class="auth-layout">
        <section class="auth-aside">
            <a class="brand brand--light" href="?page=auth/login"><span class="brand-symbol">it<span>.</span></span><span class="brand-name">intern<span>track</span></span></a>
            <div class="auth-aside-copy">
                <p class="eyebrow">KẾT NỐI HỌC TẬP VỚI THỰC TIỄN</p>
                <p class="auth-issue">Từ giảng đường<br>đến <em>nghề nghiệp.</em></p>
                <div class="auth-rule"><span>HỒ SƠ</span><span class="auth-rule-line"></span><span>PHÁT TRIỂN</span></div>
                <p class="auth-caption">Một hành trình thực tập, cùng một mục tiêu rõ ràng.</p>
            </div>
            <div class="auth-aside-footer"><span>INTERNTRACK</span><span>TP. HỒ CHÍ MINH</span></div>
        </section>
        <section class="auth-main">
            <div class="auth-form-wrap">
                <a class="brand auth-main-brand" href="?page=auth/login" aria-label="InternTrack, về đăng nhập"><span class="brand-symbol">it<span>.</span></span><span class="brand-name">intern<span>track</span></span></a>
                <a class="auth-back" href="?page=auth/login"><span class="auth-back-arrow" aria-hidden="true">←</span><span><?= $isReset ? 'Quay lại đăng nhập' : ($isForgot ? 'Quay lại đăng nhập' : 'Cổng thực tập') ?></span></a>
                <p class="eyebrow"><?= $isReset ? ($isPasswordStep ? 'MẬT KHẨU MỚI' : 'XÁC MINH EMAIL') : ($isForgot ? 'KHÔI PHỤC TÀI KHOẢN' : 'CHÀO MỪNG TRỞ LẠI') ?></p>
                <h1><?= $isReset ? ($isPasswordStep ? 'Tạo mật khẩu mới.' : 'Xác minh mã OTP.') : ($isForgot ? 'Lấy lại quyền truy cập.' : 'Đăng nhập để tiếp tục.') ?></h1>
                <p class="auth-description"><?= $isReset ? ($isPasswordStep ? 'Tạo mật khẩu mới có ít nhất 10 ký tự.' : 'Nhập mã 6 chữ số đã gửi tới ' . $authEscape($resetEmail) . ' để tiếp tục.') : ($isForgot ? 'Nhập email của bạn. Mã OTP sẽ được gửi tới hộp thư Gmail đã đăng ký.' : 'Chào mừng bạn quay lại InternTrack.') ?></p>
                <?php if ($authFlash !== null): ?>
                    <div class="flash-message flash--<?= $authEscape($authFlash['type']) ?>" role="status">
                        <?= $authEscape($authFlash['message']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isForgot): ?>
                    <form class="auth-form" method="post" action="?page=auth/forgot-password">
                        <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_reset_request">
                        <label for="email">Gmail đã đăng ký</label><input id="email" name="email" type="email" autocomplete="email" placeholder="ten@gmail.com" required>
                        <button class="button button--primary auth-submit" type="submit">Gửi mã OTP <span aria-hidden="true">↗</span></button>
                    </form>
                <?php elseif ($isReset): ?>
                    <?php if (!$isPasswordStep): ?>
                        <form class="auth-form" method="post" action="?page=auth/reset-password">
                            <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_reset_verify">
                            <label for="otp">Mã OTP gồm 6 chữ số</label><input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                            <button class="button button--primary auth-submit" type="submit">Xác minh OTP <span aria-hidden="true">↗</span></button>
                            <a class="auth-inline-link" href="?page=auth/forgot-password">Yêu cầu gửi mã mới</a>
                        </form>
                    <?php else: ?>
                        <form class="auth-form" method="post" action="?page=auth/reset-password">
                            <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="reset_password">
                            <div class="password-label"><label for="password">Mật khẩu mới</label></div>
                            <div class="password-input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required><button type="button" class="password-toggle" data-password-target="password" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><span class="password-toggle-icon" aria-hidden="true">👁</span></button></div>
                            <div class="password-label"><label for="password_confirmation">Nhập lại mật khẩu</label></div>
                            <div class="password-input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required><button type="button" class="password-toggle" data-password-target="password_confirmation" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><span class="password-toggle-icon" aria-hidden="true">👁</span></button></div>
                            <button class="button button--primary auth-submit" type="submit">Cập nhật mật khẩu <span aria-hidden="true">↗</span></button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form class="auth-form" method="post" action="?page=auth/login">
                        <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="login">
                        <label for="email">Email hoặc tên đăng nhập</label><input id="email" name="login" type="text" autocomplete="username" placeholder="email hoặc tên đăng nhập" required>
                        <div class="password-label"><label for="password">Mật khẩu</label><a href="?page=auth/forgot-password">Quên mật khẩu?</a></div>
                        <div class="password-input-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required><button type="button" class="password-toggle" data-password-target="password" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><span class="password-toggle-icon" aria-hidden="true">👁</span></button></div>
                        <button class="button button--primary auth-submit" type="submit">Đăng nhập <span aria-hidden="true">↗</span></button>
                    </form>
                <?php endif; ?>
                <p class="auth-footnote">Bảo vệ tài khoản: không chia sẻ mật khẩu hoặc mã OTP với bất kỳ ai.</p>
            </div>
        </section>
    </main>
    <div class="toast-message" role="status" aria-live="polite" data-toast-region></div>
    <script src="assets/js/app.js" defer></script>
</body>

</html>