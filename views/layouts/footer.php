<?php // Đóng vùng nội dung và khung ứng dụng (được mở ở header.php). ?>
</main>
</div>

<?php // Bong bóng chat AI: chỉ sinh viên thấy và chỉ khi đã cấu hình GEMINI_API_KEY. Logic JS ở assets/js/app.js (data-ai-chat), xử lý server ở index.php (action=ai_chat). ?>
<?php if (($user['role'] ?? '') === 'student' && function_exists('ai_match_enabled') && ai_match_enabled()): ?>
    <div class="ai-chat" data-ai-chat data-csrf="<?= screen_escape(app_csrf_token()) ?>" data-endpoint="index.php">
        <section class="ai-chat-panel" data-ai-chat-panel hidden aria-label="Trợ lý AI">
            <header class="ai-chat-head"><span class="ai-chat-head-avatar"><img src="assets/images/logo-mark-light.svg" alt="" width="24" height="15"></span><div class="ai-chat-head-text"><strong>Trợ lý InternTrack</strong><span>Gợi ý thực tập từ CV của bạn</span></div><button type="button" class="ai-chat-close" data-ai-chat-close aria-label="Đóng">&times;</button></header>
            <div class="ai-chat-log" data-ai-chat-log aria-live="polite"></div>
            <div class="ai-chat-suggest" data-ai-chat-suggest>
                <button type="button">Vị trí nào hợp với CV của tôi nhất?</button>
                <button type="button">CV của tôi còn thiếu gì?</button>
                <button type="button">Quy định nộp báo cáo thực tập?</button>
                <button type="button">Viết thư xin thực tập giúp tôi</button>
                <button type="button">Gợi ý dàn ý báo cáo cuối kỳ</button>
            </div>
            <form class="ai-chat-form" data-ai-chat-form><input type="text" name="message" maxlength="1000" placeholder="Nhập câu hỏi của bạn…" autocomplete="off" aria-label="Câu hỏi" required><button type="submit" aria-label="Gửi"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg></button></form>
        </section>
        <button type="button" class="ai-chat-toggle" data-ai-chat-toggle aria-label="Mở trợ lý AI" aria-expanded="false"><svg class="ai-icon-chat" viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/><path d="M9 11h.01M12 11h.01M15 11h.01"/></svg><svg class="ai-icon-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg><span class="ai-chat-dot" aria-hidden="true"></span></button>
    </div>
<?php endif; ?>

<?php // Sau khi AI chạy xong, tự mở lại hộp thoại kết quả (tên hộp thoại do ActionController đặt trong $_SESSION['_open_dialog']). ?>
<?php if (!empty($_SESSION['_open_dialog']) && is_string($_SESSION['_open_dialog'])): ?>
    <script>document.addEventListener('DOMContentLoaded', function () { var dialog = document.getElementById(<?= json_encode($_SESSION['_open_dialog']) ?>); if (dialog && dialog.showModal) { dialog.showModal(); } });</script>
<?php unset($_SESSION['_open_dialog']); endif; ?>

<?php // Vùng hiển thị thông báo nhanh (toast) do JavaScript điều khiển. ?>
<div class="toast-message" role="status" aria-live="polite" data-toast-region></div>

<?php // JavaScript chung của toàn ứng dụng (tải sau cùng, thuộc tính defer). ?>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>" defer></script>
</body>

</html>