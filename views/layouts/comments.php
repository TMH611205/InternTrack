<?php
// Hộp thoại trao đổi gắn với một báo cáo / nhật ký thực tập: các phiên bản đã nộp (báo cáo) và luồng bình luận.
// Sinh viên, giảng viên theo dõi và doanh nghiệp (người hướng dẫn) của kỳ thực tập đều bình luận được.
if (!function_exists('comment_thread_dialog')) {
    function comment_thread_dialog(string $type, int $id, array $comments, array $versions, string $formAction, string $csrfField, string $title): string
    {
        $dialogId = 'thread-' . $type . '-' . $id;
        $html = '<button type="button" class="table-edit-button" data-dialog-open="' . $dialogId . '">Trao đổi' . ($comments ? ' (' . count($comments) . ')' : '') . '</button>';
        $html .= '<dialog class="modal" id="' . $dialogId . '" aria-label="Trao đổi"><div class="workspace-form modal-form">';
        $html .= '<header class="modal-head"><div><p class="eyebrow">' . ($type === 'report' ? 'Báo cáo thực tập' : 'Nhật ký thực tập') . '</p><h2>' . screen_escape($title) . '</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>';
        if ($versions) {
            $html .= '<h4 class="x-subtitle">Các phiên bản đã nộp</h4><ul class="x-plain-list">';
            foreach ($versions as $version) {
                $html .= '<li>Bản ' . (int) $version['version'] . ' · ' . screen_escape(date('d/m/Y H:i', (int) strtotime((string) $version['submitted_at'])))
                    . ($version['file_path'] ? ' · <a class="table-download" href="?download=report_version&amp;id=' . (int) $version['id'] . '">Tải tệp ↓</a>' : '') . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '<h4 class="x-subtitle">Bình luận</h4>';
        foreach ($comments as $comment) {
            $html .= '<p class="x-comment"><strong>' . screen_escape($comment['full_name']) . '</strong> <span class="x-meta">' . screen_escape(date('d/m/Y H:i', (int) strtotime((string) $comment['created_at']))) . '</span><br>' . nl2br(screen_escape($comment['body'])) . '</p>';
        }
        if (!$comments) {
            $html .= '<p class="x-meta">Chưa có bình luận.</p>';
        }
        $html .= '<form method="post" action="' . screen_escape($formAction) . '" class="workspace-form workspace-form--plain">' . $csrfField
            . '<input type="hidden" name="action" value="item_comment"><input type="hidden" name="item_type" value="' . $type . '"><input type="hidden" name="item_id" value="' . $id . '">'
            . '<textarea name="body" rows="2" maxlength="3000" required placeholder="Góp ý, câu hỏi..."></textarea>'
            . '<div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit">Gửi bình luận</button></div></form>';
        return $html . '</div></dialog>';
    }
}
