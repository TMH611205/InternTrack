<?php

declare(strict_types=1);

// Đọc / ghi tệp ZIP bằng PHP thuần (không cần tiện ích zip của PHP).
// Dùng cho: xuất Excel .xlsx, đọc .xlsx / .docx khi nhập liệu, sao lưu kèm thư mục uploads/.

// Tạo nội dung ZIP từ [đường dẫn trong zip => nội dung]. Nén deflate khi có zlib.
function zip_build(array $files): string
{
    $data = '';
    $directory = '';
    $count = 0;
    [$time, $date] = zip_dos_time(time());
    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', (string) $name);
        $content = (string) $content;
        $crc = crc32($content);
        $compressed = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
        $method = $compressed !== false && strlen($compressed) < strlen($content) ? 8 : 0;
        $stored = $method === 8 ? $compressed : $content;
        $offset = strlen($data);
        // Cờ 0x0800: tên tệp mã hóa UTF-8.
        $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, strlen($stored), strlen($content), strlen($name), 0) . $name . $stored;
        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, strlen($stored), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $count++;
    }
    return $data . $directory . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), strlen($data), 0);
}

// Ghi ZIP thẳng ra đĩa, từng tệp một, để sao lưu lớn không cần giữ trong RAM.
// shortcut: không có ZIP64 nên báo lỗi khi vượt 4 GB; khi đó dùng mysqldump và công cụ nén ngoài.
function zip_stream_open(string $path): array
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Không tạo được tệp nén.');
    }
    return ['handle' => $handle, 'directory' => fopen('php://temp', 'w+b'), 'directory_size' => 0, 'count' => 0];
}

function zip_stream_add(array &$zip, string $name, string $sourcePath): void
{
    $source = fopen($sourcePath, 'rb');
    if ($source === false) {
        throw new RuntimeException('Không đọc được tệp "' . $name . '".');
    }
    $handle = $zip['handle'];
    $offset = (int) ftell($handle);
    $size = (int) filesize($sourcePath);
    $crc = (int) hexdec((string) hash_file('crc32b', $sourcePath));
    $deflate = function_exists('deflate_init') ? deflate_init(ZLIB_ENCODING_RAW, ['level' => 6]) : false;
    $method = $deflate !== false ? 8 : 0;
    [$time, $date] = zip_dos_time(time());
    $header = static fn(int $stored) => pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, $stored, $size, strlen($name), 0) . $name;
    fwrite($handle, $header(0));
    $stored = 0;
    while (!feof($source)) {
        $chunk = (string) fread($source, 1048576);
        $bytes = $deflate !== false ? deflate_add($deflate, $chunk, ZLIB_NO_FLUSH) : $chunk;
        $stored += (int) fwrite($handle, $bytes);
    }
    fclose($source);
    if ($deflate !== false) {
        $stored += (int) fwrite($handle, deflate_add($deflate, '', ZLIB_FINISH));
    }
    $end = (int) ftell($handle);
    if ($end >= 0xFFFFFFFF || $size >= 0xFFFFFFFF) {
        throw new RuntimeException('Bản sao lưu vượt 4 GB, không ghi được bằng định dạng ZIP hiện tại.');
    }
    fseek($handle, $offset);
    fwrite($handle, $header($stored));
    fseek($handle, $end);
    $zip['directory_size'] += (int) fwrite($zip['directory'], pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, $stored, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name);
    $zip['count']++;
}

function zip_stream_close(array $zip): void
{
    $handle = $zip['handle'];
    $offset = (int) ftell($handle);
    rewind($zip['directory']);
    stream_copy_to_stream($zip['directory'], $handle);
    fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $zip['count'], $zip['count'], $zip['directory_size'], $offset, 0));
    fclose($zip['directory']);
    fclose($handle);
}

function zip_dos_time(int $timestamp): array
{
    $parts = getdate($timestamp);
    return [
        ($parts['hours'] << 11) | ($parts['minutes'] << 5) | intdiv($parts['seconds'], 2),
        (max(0, $parts['year'] - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'],
    ];
}

// Đọc tệp ZIP thành [đường dẫn => nội dung]. $only: chỉ lấy các tệp có tên trong danh sách (null = tất cả).
// Giới hạn tổng dung lượng giải nén để tránh "zip bomb".
function zip_read(string $path, ?array $only = null, int $maximumBytes = 300 * 1024 * 1024): array
{
    $binary = (string) file_get_contents($path);
    $end = strrpos($binary, pack('V', 0x06054b50));
    if ($end === false || strlen($binary) < $end + 22) {
        throw new DomainException('Tệp nén không hợp lệ.');
    }
    $footer = unpack('Vsignature/vdisk/vstart/ventries/vtotal/Vsize/Voffset', substr($binary, $end, 20));
    $position = (int) $footer['offset'];
    $files = [];
    $total = 0;
    for ($index = 0; $index < (int) $footer['total']; $index++) {
        $entry = unpack('Vsignature/vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset', substr($binary, $position, 46));
        if ($entry['signature'] !== 0x02014b50) {
            throw new DomainException('Tệp nén bị hỏng.');
        }
        $name = substr($binary, $position + 46, $entry['name']);
        $position += 46 + $entry['name'] + $entry['extra'] + $entry['comment'];
        if (str_ends_with($name, '/') || ($only !== null && !in_array($name, $only, true))) {
            continue;
        }
        $local = unpack('Vsignature/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra', substr($binary, $entry['offset'], 30));
        $raw = substr($binary, $entry['offset'] + 30 + $local['name'] + $local['extra'], $entry['compressed']);
        $total += $entry['size'];
        if ($total > $maximumBytes) {
            throw new DomainException('Tệp nén quá lớn.');
        }
        $content = match ($entry['method']) {
            0 => $raw,
            8 => @gzinflate($raw),
            default => false,
        };
        if ($content === false) {
            throw new DomainException('Không giải nén được "' . $name . '".');
        }
        $files[$name] = $content;
    }
    return $files;
}

// ---------------------------------------------------------------------------
// EXCEL .xlsx
// ---------------------------------------------------------------------------

// Tạo tệp .xlsx một trang tính: dòng tiêu đề in đậm, cố định dòng đầu, số được ghi dạng số.
function xlsx_build(array $header, array $rows, string $sheetName = 'Dữ liệu'): string
{
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $column = static function (int $index): string {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26) . $name;
        }
        return $name;
    };
    $widths = array_map(static fn($cell) => mb_strlen((string) $cell, 'UTF-8'), $header);
    $xmlRows = '';
    foreach (array_merge([$header], $rows) as $rowIndex => $row) {
        $cells = '';
        foreach (array_values($row) as $cellIndex => $value) {
            $reference = $column($cellIndex) . ($rowIndex + 1);
            $style = $rowIndex === 0 ? ' s="1"' : '';
            $text = (string) ($value ?? '');
            $widths[$cellIndex] = max($widths[$cellIndex] ?? 0, min(60, mb_strlen($text, 'UTF-8')));
            // Số thuần (không có số 0 đứng đầu như mã SV) ghi dạng số để Excel tính toán được.
            if ($rowIndex > 0 && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d+)?$/', $text)) {
                $cells .= '<c r="' . $reference . '"' . $style . '><v>' . $text . '</v></c>';
            } elseif ($text !== '') {
                $cells .= '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $escape($text) . '</t></is></c>';
            }
        }
        $xmlRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
    }
    $cols = '';
    foreach ($widths as $index => $width) {
        $cols .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . max(8, min(60, $width + 2)) . '" customWidth="1"/>';
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $xmlRows . '</sheetData></worksheet>';
    $sheetName = $escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $sheetName), 0, 31, 'UTF-8'));
    return zip_build([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6F1F8"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

// Đọc trang tính đầu tiên của tệp .xlsx thành mảng các dòng.
function xlsx_read(string $path): array
{
    $files = zip_read($path, ['xl/sharedStrings.xml', 'xl/worksheets/sheet1.xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'], 50 * 1024 * 1024);
    $sheetXml = $files['xl/worksheets/sheet1.xml'] ?? null;
    if ($sheetXml === null) {
        // Trang tính đầu tiên có thể mang tên khác: tìm theo workbook.xml.rels.
        $all = zip_read($path, null, 50 * 1024 * 1024);
        foreach ($all as $name => $content) {
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $sheetXml = $content;
                break;
            }
        }
        $files['xl/sharedStrings.xml'] ??= $all['xl/sharedStrings.xml'] ?? null;
    }
    if ($sheetXml === null) {
        throw new DomainException('Tệp Excel không có trang tính.');
    }
    $previous = libxml_use_internal_errors(true);
    $shared = [];
    if (!empty($files['xl/sharedStrings.xml'])) {
        $xml = simplexml_load_string($files['xl/sharedStrings.xml'], 'SimpleXMLElement', LIBXML_NONET);
        foreach ($xml->si ?? [] as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $shared[] = implode('', array_map('strval', $item->xpath('.//m:t') ?: []));
        }
    }
    $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($previous);
    if ($sheet === false) {
        throw new DomainException('Không đọc được tệp Excel.');
    }
    $rows = [];
    foreach ($sheet->sheetData->row ?? [] as $row) {
        $line = [];
        $next = 0;
        foreach ($row->c as $cell) {
            $column = $next;
            if ((string) $cell['r'] !== '') {
                $column = 0;
                foreach (str_split(preg_replace('/\d/', '', (string) $cell['r'])) as $letter) {
                    $column = $column * 26 + (ord($letter) - 64);
                }
                $column--;
            }
            $next = $column + 1;
            $value = (string) ($cell->v ?? '');
            $type = (string) $cell['t'];
            $line[$column] = match ($type) {
                's' => $shared[(int) $value] ?? '',
                'inlineStr' => (string) ($cell->is->t ?? ''),
                default => $value,
            };
        }
        if ($line) {
            $rows[] = array_replace(array_fill(0, max(array_keys($line)) + 1, ''), $line);
        }
    }
    return $rows;
}

// Lấy chữ từ tệp .docx (kiểm tra trùng lặp nội dung bài nộp).
function docx_text(string $path): string
{
    $files = zip_read($path, ['word/document.xml'], 50 * 1024 * 1024);
    $xml = $files['word/document.xml'] ?? '';
    $xml = preg_replace(['/<w:p[ >]/', '/<w:tab\/>/'], ["\n<w:p ", ' '], $xml);
    return html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}
