<?php
// Xác định trạng thái màn hình auth để render đúng form: quên mật khẩu, xác minh OTP hay đăng nhập.
// Khai báo biến global và giá trị mặc định để file luôn an toàn khi được render từ nhiều route khác nhau.
global $screen, $flash;
$screen = $screen ?? '';
$flash = isset($flash) && is_array($flash) ? $flash : null;
$isForgot = $screen === 'auth/forgot-password';
$isReset = $screen === 'auth/reset-password';
$isRegister = $screen === 'auth/register';
$resetAuthorization = $_SESSION['password_reset_authorized'] ?? null;
$isPasswordStep = $isReset && is_array($resetAuthorization) && (int) ($resetAuthorization['expires_at'] ?? 0) >= time();
$resetEmail = is_string($_SESSION['password_reset_email'] ?? null) ? $_SESSION['password_reset_email'] : '';
$otpResendAt = (int) ($_SESSION['password_reset_otp_sent_at'] ?? 0);
$otpResendRemaining = max(0, $otpResendAt + 60 - time());
$authFlash = isset($flash) && is_array($flash) ? $flash : null;
$authEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$authPageTitle = $isRegister ? 'Doanh nghiệp đăng ký' : ($isReset ? 'Đặt lại mật khẩu' : ($isForgot ? 'Khôi phục mật khẩu' : 'Đăng nhập'));
$authPageDescription = 'Hệ thống quản lý thực tập và đồ án của Trường Đại học Vinh dành cho sinh viên, giảng viên, doanh nghiệp và phòng đào tạo.';
?>

<?php // Trang xác thực: đăng nhập, quên mật khẩu, nhập OTP và tạo mật khẩu mới (không dùng chung header/sidebar). ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0073a5">
    <script>try { var savedTheme = localStorage.getItem("theme"); if (savedTheme === "dark" || savedTheme === "light") { document.documentElement.dataset.theme = savedTheme; } } catch (error) {}</script>
    <meta name="description" content="<?= $authEscape($authPageDescription) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= $authEscape($authPageTitle) ?> · Đại học Vinh">
    <meta property="og:description" content="<?= $authEscape($authPageDescription) ?>">
    <meta property="og:type" content="website">
    <title><?= $authEscape($authPageTitle) ?> · Đại học Vinh</title>
    <link rel="icon" type="image/png" href="assets/images/vinhuni-logo.png">
    <link rel="canonical" href="https://interntrack.local/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
</head>

<body class="auth-body">

    <?php // Bố cục hai cột: cột trang trí bên trái, cột biểu mẫu bên phải. ?>
    <main class="auth-layout">

        <?php // Cột trái: logo và thông điệp (ẩn trên màn hình hẹp). ?>
        <section class="auth-aside">
            <a class="brand brand--light" href="?page=auth/login"><img class="brand-mark brand-mark--seal" src="assets/images/vinhuni-logo.png" alt="Logo Trường Đại học Vinh" width="44" height="44"><span class="brand-text"><strong>ĐẠI HỌC VINH</strong><small>Thực tập &amp; Đồ án</small></span></a>
            <div class="auth-aside-copy">
                <p class="eyebrow">KẾT NỐI HỌC TẬP VỚI THỰC TIỄN</p>
                <p class="auth-issue">Từ giảng đường<br>đến <em>nghề nghiệp.</em></p>
                <div class="auth-rule"><span>HỒ SƠ</span><span class="auth-rule-line"></span><span>PHÁT TRIỂN</span></div>
                <p class="auth-caption">Một hành trình thực tập, cùng một mục tiêu rõ ràng.</p>
            </div>
            <div class="auth-aside-footer"><span>TRƯỜNG ĐẠI HỌC VINH</span><span>182 LÊ DUẨN · TP. VINH · NGHỆ AN</span></div>
        </section>

        <?php // Cột phải: biểu mẫu theo bước (đăng nhập / quên mật khẩu / xác minh OTP / mật khẩu mới). ?>
        <section class="auth-main">
            <button type="button" class="auth-theme-toggle" data-theme-toggle><?= ui_icon("moon") ?><span data-theme-label>Giao diện tối</span></button>
            <div class="auth-form-wrap<?= $isRegister ? ' auth-form-wrap--wide' : '' ?>">
                <a class="brand auth-main-brand" href="?page=auth/login" aria-label="Trường Đại học Vinh, về đăng nhập"><img class="brand-mark brand-mark--seal" src="assets/images/vinhuni-logo.png" alt="Logo Trường Đại học Vinh" width="44" height="44"><span class="brand-text"><strong>ĐẠI HỌC VINH</strong><small>Thực tập &amp; Đồ án</small></span></a>
                <a class="auth-back" href="?page=auth/login"><span class="auth-back-arrow" aria-hidden="true">←</span><span><?= $isReset || $isForgot || $isRegister ? 'Quay lại đăng nhập' : 'Cổng thực tập &amp; đồ án' ?></span></a>
                <p class="eyebrow"><?= $isRegister ? 'DOANH NGHIỆP ĐỐI TÁC' : ($isReset ? ($isPasswordStep ? 'MẬT KHẨU MỚI' : 'XÁC MINH EMAIL') : ($isForgot ? 'KHÔI PHỤC TÀI KHOẢN' : 'CHÀO MỪNG TRỞ LẠI')) ?></p>
                <h1><?= $isRegister ? 'Đăng ký hợp tác thực tập.' : ($isReset ? ($isPasswordStep ? 'Tạo mật khẩu mới.' : 'Xác minh mã OTP.') : ($isForgot ? 'Lấy lại quyền truy cập.' : 'Đăng nhập để tiếp tục.')) ?></h1>
                <p class="auth-description"><?= $isRegister ? 'Doanh nghiệp đăng ký tài khoản để đăng tin tuyển thực tập sinh. Khoa sẽ xác minh thông tin trước khi tài khoản được kích hoạt.' : ($isReset ? ($isPasswordStep ? 'Tạo mật khẩu mới có ít nhất 10 ký tự.' : 'Nhập mã 6 chữ số đã gửi tới ' . $authEscape($resetEmail) . ' để tiếp tục.') : ($isForgot ? 'Nhập email của bạn. Mã OTP sẽ được gửi tới hộp thư Gmail đã đăng ký.' : 'Hệ thống quản lý thực tập và đồ án của Trường Đại học Vinh.')) ?></p>

                <?php // Thông báo thành công/lỗi của lần gửi form trước. ?>
                <?php if ($authFlash !== null): ?>
                    <div class="flash-message flash--<?= $authEscape($authFlash['type']) ?>" role="status">
                        <?= $authEscape($authFlash['message']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($isRegister): ?>
                    <?php // Doanh nghiệp tự đăng ký: tài khoản tạo ở trạng thái chờ khoa xác minh (action=company_register). ?>
                    <form class="auth-form auth-form--register" method="post" action="?page=auth/register">
                        <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="company_register">
                        <fieldset class="auth-fieldset"><legend>1. Thông tin doanh nghiệp</legend>
                            <div class="auth-field"><label for="company_name">Tên doanh nghiệp</label><input id="company_name" name="company_name" maxlength="200" autocomplete="organization" required></div>
                            <div class="auth-field"><label for="company_code">Mã doanh nghiệp</label><input id="company_code" name="company_code" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" placeholder="10 chữ số" required><small>Mã số đăng ký doanh nghiệp gồm đúng 10 chữ số, không sửa được sau khi tạo.</small></div>
                            <div class="auth-field"><label for="tax_code">Mã số thuế <span class="auth-optional">(không bắt buộc)</span></label><input id="tax_code" name="tax_code" maxlength="50" inputmode="numeric"></div>
                            <div class="auth-field"><label for="address">Địa chỉ</label><input id="address" name="address" maxlength="255" autocomplete="street-address" placeholder="Số nhà, đường, TP. Vinh, Nghệ An" required></div>
                            <div class="auth-field"><label for="field">Lĩnh vực hoạt động</label><input id="field" name="field" maxlength="150" placeholder="Phần mềm, kế toán, du lịch..."></div>
                            <div class="auth-field"><label for="website">Website <span class="auth-optional">(không bắt buộc)</span></label><input id="website" name="website" maxlength="255" type="url" placeholder="https://"></div>
                        </fieldset>
                        <fieldset class="auth-fieldset"><legend>2. Người liên hệ</legend>
                            <div class="auth-field"><label for="full_name">Họ tên người liên hệ</label><input id="full_name" name="full_name" maxlength="150" autocomplete="name" required></div>
                            <div class="auth-field"><label for="phone">Điện thoại</label><input id="phone" name="phone" maxlength="20" type="tel" autocomplete="tel"></div>
                            <div class="auth-field"><label for="reg_email">Email</label><input id="reg_email" name="email" type="email" maxlength="255" autocomplete="email" required><small>Thông báo xác minh và mã khôi phục mật khẩu sẽ gửi về địa chỉ này.</small></div>
                        </fieldset>
                        <fieldset class="auth-fieldset"><legend>3. Tài khoản đăng nhập</legend>
                            <div class="auth-field"><label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="50" autocomplete="username" required></div>
                            <div class="auth-field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required><small>Ít nhất 10 ký tự.</small></div>
                            <div class="auth-field"><label for="password_confirmation">Nhập lại mật khẩu</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required></div>
                        </fieldset>
                        <button class="button button--primary auth-submit" type="submit">Gửi đăng ký <span aria-hidden="true">↗</span></button>
                    </form>
                <?php elseif ($isForgot): ?>
                    <?php // Bước 1 - Quên mật khẩu: nhập email để nhận mã OTP. ?>
                    <form class="auth-form" method="post" action="?page=auth/forgot-password">
                        <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_reset_request">
                        <label for="email">Gmail đã đăng ký</label><input id="email" name="email" type="email" autocomplete="email" placeholder="ten@gmail.com" required>
                        <button class="button button--primary auth-submit" type="submit">Gửi mã OTP <span aria-hidden="true">↗</span></button>
                    </form>
                <?php elseif ($isReset): ?>
                    <?php // Bước 2 - Đặt lại mật khẩu: nhập mã OTP, sau khi máy chủ xác minh mới hiện form tạo mật khẩu mới. ?>
                    <?php if (!$isPasswordStep): ?>
                        <form class="auth-form" method="post" action="?page=auth/reset-password">
                            <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_reset_verify">
                            <label for="otp-digit-1">Mã OTP gồm 6 chữ số</label>
                            <div class="otp-digit-group" data-otp-inputs role="group" aria-label="Mã OTP gồm 6 chữ số">
                                <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                                    <input id="otp-digit-<?= $digit ?>" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Chữ số <?= $digit ?>" <?= $digit === 1 ? 'autocomplete="one-time-code"' : 'autocomplete="off"' ?> data-otp-digit required>
                                <?php endfor; ?>
                            </div>
                            <input id="otp" name="otp" type="hidden" data-otp-value>
                            <button class="button button--primary auth-submit" type="submit">Xác minh OTP <span aria-hidden="true">↗</span></button>
                        </form>
                        <form class="auth-resend-form" method="post" action="?page=auth/reset-password" data-otp-resend data-remaining="<?= $otpResendRemaining ?>">
                            <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="password_reset_resend">
                            <button class="auth-resend-button" type="submit" data-otp-resend-button>Gửi lại mã</button>
                            <span class="auth-resend-countdown" data-otp-resend-countdown aria-live="polite"></span>
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
                    <?php // Mặc định - Đăng nhập bằng email hoặc tên đăng nhập. ?>
                    <form class="auth-form" method="post" action="?page=auth/login">
                        <input type="hidden" name="_csrf" value="<?= $authEscape(app_csrf_token()) ?>"><input type="hidden" name="action" value="login">
                        <label for="email">Email hoặc tên đăng nhập</label><input id="email" name="login" type="text" autocomplete="username" placeholder="email hoặc tên đăng nhập" required>
                        <div class="password-field">
                        <div class="password-label"><label for="password">Mật khẩu</label></div>
                        <div class="password-input-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required><button type="button" class="password-toggle" data-password-target="password" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false"><span class="password-toggle-icon" aria-hidden="true">👁</span></button></div>
                        <a class="password-forgot" href="?page=auth/forgot-password">Quên mật khẩu?</a>
                        </div>
                        <button class="button button--primary auth-submit" type="submit">Đăng nhập <span aria-hidden="true">↗</span></button>
                    </form>
                    <?php require_once __DIR__ . '/../../controllers/SsoController.php'; ?>
                    <?php if (sso_enabled()): ?>
                        <div class="auth-divider"><span>hoặc</span></div>
                        <a class="button auth-sso" href="index.php?sso=google">Đăng nhập bằng email @<?= $authEscape(sso_domain()) ?></a>
                    <?php endif; ?>
                    <p class="auth-register-link">Doanh nghiệp muốn nhận sinh viên thực tập? <a href="?page=auth/register">Đăng ký hợp tác</a></p>
                <?php endif; ?>
                <p class="auth-footnote">Bảo vệ tài khoản: không chia sẻ mật khẩu hoặc mã OTP với bất kỳ ai.</p>
            </div>
        </section>
    </main>
    <div class="toast-message" role="status" aria-live="polite" data-toast-region></div>
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>" defer></script>
</body>

</html>