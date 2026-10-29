<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/zip.php';

// Kiểm tra trùng lặp nội dung bài nộp đồ án (trong phạm vi hệ thống, không so với Internet):
// lấy chữ từ tệp (Word .docx, PDF, .txt), băm các cụm 5 từ liên tiếp, so với các bài nộp khác (độ trùng = tỷ lệ cụm trùng).

const SIMILARITY_SHINGLE = 5;
const SIMILARITY_WARN = 30.0;

// Chữ trong tệp; không đọc được thì trả chuỗi rỗng.
function similarity_extract_text(string $absolutePath): string
{
    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    try {
        return match ($extension) {
            'docx' => docx_text($absolutePath),
            'txt' => (string) file_get_contents($absolutePath),
            'pdf' => pdf_text((string) file_get_contents($absolutePath)),
            default => '',
        };
    } catch (Throwable) {
        return '';
    }
}

// Đọc chữ từ PDF ở mức cơ bản: giải nén luồng nội dung, ghép các chuỗi trong lệnh Tj/TJ,
// giải mã font dạng Identity-H bằng bảng ToUnicode (đủ cho tệp xuất từ Word / LibreOffice).
function pdf_text(string $pdf): string
{
    $streams = [];
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches)) {
        foreach ($matches[1] as $raw) {
            $data = @gzuncompress($raw);
            $streams[] = $data !== false ? $data : $raw;
        }
    }
    // Bảng mã glyph → Unicode gộp từ mọi CMap (beginbfchar / beginbfrange).
    $map = [];
    foreach ($streams as $stream) {
        if (!str_contains($stream, 'beginbf')) {
            continue;
        }
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $stream, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
                foreach ($pairs as $pair) {
                    $map[strtoupper($pair[1])] = pdf_utf16_hex($pair[2]);
                }
            }
        }
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $stream, $blocks)) {
            foreach ($blocks[1] as $block) {
                // Dạng mảng: <đầu> <cuối> [<mã1> <mã2> ...]
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([^\]]*)\]/', $block, $arrays, PREG_SET_ORDER);
                foreach ($arrays as [, $from, , $list]) {
                    preg_match_all('/<([0-9A-Fa-f]+)>/', $list, $targets);
                    foreach ($targets[1] as $offset => $target) {
                        $map[strtoupper(str_pad(dechex(hexdec($from) + $offset), strlen($from), '0', STR_PAD_LEFT))] = pdf_utf16_hex($target);
                    }
                }
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER);
                foreach ($ranges as [, $from, $to, $start]) {
                    $width = strlen($from);
                    for ($code = hexdec($from), $unicode = hexdec($start); $code <= hexdec($to) && $code - hexdec($from) < 5000; $code++, $unicode++) {
                        $map[strtoupper(str_pad(dechex($code), $width, '0', STR_PAD_LEFT))] = mb_chr($unicode, 'UTF-8') ?: '';
                    }
                }
            }
        }
    }
    $text = '';
    foreach ($streams as $stream) {
        if (!preg_match_all('/BT(.*?)ET/s', $stream, $blocks)) {
            continue;
        }
        foreach ($blocks[1] as $block) {
            preg_match_all('/\((?:\\\\.|[^\\\\)])*\)|<[0-9A-Fa-f\s]+>|T[Jj*]|\'|"/', $block, $tokens);
            foreach ($tokens[0] as $token) {
                if ($token[0] === '(' || $token[0] === '<') {
                    // Chuỗi (...) hoặc <hex>: có bảng ToUnicode thì giải mã theo mã glyph, không thì lấy nguyên văn.
                    $literal = $token[0] === '(' ? pdf_unescape(substr($token, 1, -1)) : null;
                    if ($literal !== null && !$map) {
                        $text .= $literal;
                        continue;
                    }
                    $hex = $literal !== null ? strtoupper(bin2hex($literal)) : strtoupper(preg_replace('/\s+/', '', substr($token, 1, -1)));
                    $step = $map && strlen(array_key_first($map)) === 2 ? 2 : 4;
                    for ($i = 0; $i < strlen($hex); $i += $step) {
                        $text .= $map[substr($hex, $i, $step)] ?? '';
                    }
                } else {
                    $text .= ' ';
                }
            }
            $text .= "\n";
        }
    }
    return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
}

// Bỏ ký tự thoát trong chuỗi PDF: \n, \(, \), \\ và mã bát phân \ddd.
function pdf_unescape(string $value): string
{
    return (string) preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static fn($m) => ctype_digit($m[1]) ? chr(octdec($m[1]) & 255) : match ($m[1]) {
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'b' => "\x08",
        'f' => "\f",
        default => $m[1],
    }, $value);
}

function pdf_utf16_hex(string $hex): string
{
    $converted = @mb_convert_encoding((string) hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex), 'UTF-8', 'UTF-16BE');
    return is_string($converted) ? $converted : '';
}

// Tập dấu vân tay (crc32 của từng cụm 5 từ) của một đoạn chữ.
function similarity_fingerprints(string $text): array
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $hashes = [];
    for ($i = 0; $i + SIMILARITY_SHINGLE <= count($words); $i++) {
        $hashes[crc32(implode(' ', array_slice($words, $i, SIMILARITY_SHINGLE)))] = true;
    }
    return array_keys($hashes);
}

// Tính dấu vân tay cho bài nộp vừa tải lên, so với mọi bài nộp của đồ án KHÁC; lưu độ trùng cao nhất.
function similarity_check_submission(int $submissionId): ?array
{
    $submission = page_one('SELECT s.*, th.group_id FROM thesis_submissions s JOIN theses th ON th.id = s.thesis_id WHERE s.id = ?', [$submissionId]);
    if (!$submission) {
        return null;
    }
    $absolute = realpath(dirname(__DIR__) . '/' . $submission['file_path']);
    $hashes = $absolute ? similarity_fingerprints(similarity_extract_text($absolute)) : [];
    database()->prepare('REPLACE INTO submission_fingerprints (submission_id, word_count, hashes) VALUES (?, ?, ?)')->execute([$submissionId, count($hashes), implode(',', $hashes)]);
    if (count($hashes) < 20) {
        database()->prepare('UPDATE thesis_submissions SET similarity = NULL, similar_submission_id = NULL WHERE id = ?')->execute([$submissionId]);
        return null;
    }
    $mine = array_flip($hashes);
    $best = ['score' => 0.0, 'id' => null];
    // Không so với bài của chính đồ án / nhóm mình (các phiên bản trước giống nhau là bình thường).
    $others = page_all(
        'SELECT f.submission_id, f.hashes FROM submission_fingerprints f JOIN thesis_submissions s ON s.id = f.submission_id JOIN theses th ON th.id = s.thesis_id
         WHERE f.submission_id <> ? AND th.id <> ? AND (? IS NULL OR th.group_id IS NULL OR th.group_id <> ?) AND f.word_count >= 20',
        [$submissionId, (int) $submission['thesis_id'], $submission['group_id'], $submission['group_id']]
    );
    foreach ($others as $other) {
        $shared = 0;
        foreach (explode(',', (string) $other['hashes']) as $hash) {
            if (isset($mine[$hash])) {
                $shared++;
            }
        }
        $score = $shared * 100 / count($hashes);
        if ($score > $best['score']) {
            $best = ['score' => $score, 'id' => (int) $other['submission_id']];
        }
    }
    database()->prepare('UPDATE thesis_submissions SET similarity = ?, similar_submission_id = ? WHERE id = ?')->execute([round($best['score'], 2), $best['id'], $submissionId]);
    return $best;
}
