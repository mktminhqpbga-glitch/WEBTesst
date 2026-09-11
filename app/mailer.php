<?php
declare(strict_types=1);

/* =========================================================
   GỬI EMAIL: SMTP (Gmail, email tên miền) hoặc mail() của hosting
   ========================================================= */

function mail_recipients(): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', setting('mail_to')) as $m) {
        if (filter_var($m, FILTER_VALIDATE_EMAIL)) $out[] = $m;
    }
    return array_values(array_unique($out));
}

function mime_header(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/**
 * Gửi email. Ném RuntimeException kèm lý do nếu lỗi.
 * @param string[] $to
 */
function send_mail(array $to, string $subject, string $html, string $text, ?string $replyTo = null): void
{
    if (!$to) throw new RuntimeException('Chưa cài email nhận đơn (Quản trị → Email nhận đơn).');
    $fromEmail = setting('mail_from_email') ?: setting('smtp_user');
    $host = parse_url((string)cfg('base_url'), PHP_URL_HOST) ?: 'localhost';
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $fromEmail = 'no-reply@' . $host;
    $fromName = setting('mail_from_name', setting('site_name', 'Website'));

    $boundary = 'b_' . bin2hex(random_bytes(12));
    $headers = [
        'Date: ' . date('r'),
        'From: ' . mime_header($fromName) . " <$fromEmail>",
        'To: ' . implode(', ', $to),
        'Subject: ' . mime_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $host . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = "Reply-To: $replyTo";
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . "--$boundary--\r\n";

    if (setting('smtp_host') === '') {
        // Không cài SMTP: dùng mail() của hosting (dễ vào spam)
        $h = array_filter($headers, fn($l) => !preg_match('/^(To|Subject):/', $l));
        if (!@mail(implode(', ', $to), mime_header($subject), $body, implode("\r\n", $h), '-f' . $fromEmail)) {
            throw new RuntimeException('Hàm mail() của hosting không gửi được. Hãy cài SMTP trong mục Email nhận đơn.');
        }
        return;
    }
    smtp_send($to, $fromEmail, implode("\r\n", $headers) . "\r\n\r\n" . $body);
}

function smtp_send(array $to, string $from, string $message): void
{
    $host = setting('smtp_host');
    $port = (int)setting('smtp_port', '587');
    $enc = setting('smtp_encryption', 'tls'); // ssl | tls | none
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Không kết nối được máy chủ mail $host:$port ($errstr). Kiểm tra lại host, cổng, kiểu bảo mật.");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (?string $c, array $ok, string $label = '') use ($fp, $read): string {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('Máy chủ mail từ chối' . ($label ? " ở bước $label" : '') . ': ' . trim($r ?: 'không phản hồi'));
        }
        return $r;
    };

    try {
        $cmd(null, [220], 'kết nối');
        $ehlo = 'EHLO ' . (parse_url((string)cfg('base_url'), PHP_URL_HOST) ?: 'localhost');
        $cmd($ehlo, [250], 'EHLO');
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220], 'STARTTLS');
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $method)) throw new RuntimeException('Không bật được mã hóa TLS. Thử đổi sang SSL cổng 465.');
            $cmd($ehlo, [250], 'EHLO sau TLS');
        }
        if (setting('smtp_user') !== '') {
            $cmd('AUTH LOGIN', [334], 'đăng nhập');
            $cmd(base64_encode(setting('smtp_user')), [334], 'tên đăng nhập');
            $cmd(base64_encode(setting('smtp_pass')), [235], 'mật khẩu (Gmail cần dùng Mật khẩu ứng dụng)');
        }
        $cmd("MAIL FROM:<$from>", [250], 'người gửi');
        foreach ($to as $rcpt) $cmd("RCPT TO:<$rcpt>", [250, 251], 'người nhận ' . $rcpt);
        $cmd('DATA', [354], 'DATA');
        $message = preg_replace("/(?<!\r)\n/", "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        $cmd(null, [250], 'gửi nội dung');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/* =========================================================
   MẪU EMAIL ĐƠN HÀNG
   ========================================================= */

function order_payment_line(array $o): string
{
    if ($o['payment_method'] === 'cod') return 'COD - thu hộ ' . money($o['total']);
    return $o['transfer_reported_at']
        ? 'CHUYỂN KHOẢN - khách báo đã chuyển lúc ' . fmt_date($o['transfer_reported_at'])
        : 'CHUYỂN KHOẢN - chờ khách chuyển';
}

/** @param string $type new | reported */
function build_order_email(array $o, array $items, string $type = 'new'): array
{
    $site = setting('site_name', 'Zungza');
    $addr = $o['address'] . ', ' . $o['ward'] . ', ' . $o['province'];
    $pay = order_payment_line($o);
    $subject = $type === 'reported'
        ? "[KHÁCH BÁO ĐÃ CK] {$o['code']} | " . money($o['total']) . " | {$o['customer_name']}"
        : "[ĐƠN MỚI] {$o['code']} | " . ($o['payment_method'] === 'cod' ? 'COD' : 'Chuyển khoản') . ' | ' . money($o['total']) . " | {$o['customer_name']}";

    /* ---------- Bản text ---------- */
    $t = [];
    if ($type === 'reported') {
        $t[] = "KHÁCH BÁO ĐÃ CHUYỂN KHOẢN - hãy kiểm tra app ngân hàng";
        $t[] = "Số tiền cần nhận: " . money($o['total']);
        $t[] = "Nội dung chuyển khoản: {$o['code']}";
        $t[] = '';
    }
    $t[] = "MÃ ĐƠN:     {$o['code']}";
    $t[] = "THỜI GIAN:  " . fmt_date($o['created_at']);
    $t[] = "THANH TOÁN: $pay";
    $t[] = "NGUỒN:      " . ($o['source'] ?: '-') . ($o['utm_campaign'] ? " / {$o['utm_campaign']}" : '') . ($o['ref'] ? " | CTV: {$o['ref']}" : '');
    $t[] = '';
    $t[] = '--- KHÁCH HÀNG ---';
    $t[] = "Họ tên:  {$o['customer_name']}";
    $t[] = "SĐT:     {$o['phone']}";
    $t[] = "Địa chỉ: $addr";
    if ($o['customer_note']) $t[] = "Ghi chú: {$o['customer_note']}";
    if ($o['invoice_required']) $t[] = "Xuất hóa đơn: {$o['invoice_company']} | MST {$o['invoice_tax_code']} | {$o['invoice_email']}";
    $t[] = '';
    $t[] = '--- SẢN PHẨM ---';
    foreach ($items as $i => $it) {
        $t[] = ($i + 1) . '. ' . $it['product_name'] . ($it['variant_name'] ? ' - ' . $it['variant_name'] : '') . ($it['sku'] ? " [{$it['sku']}]" : '')
            . " x {$it['qty']} = " . money($it['line_total']);
    }
    if ($o['gifts']) $t[] = 'QUÀ TẶNG KÈM (nhớ gửi): ' . str_replace("\n", ', ', $o['gifts']);
    $t[] = '';
    $t[] = '--- THANH TOÁN ---';
    $t[] = 'Tạm tính: ' . money($o['subtotal']);
    if ($o['combo_discount']) $t[] = "{$o['combo_label']}: -" . money($o['combo_discount']);
    if ($o['coupon_discount']) $t[] = "Mã {$o['coupon_code']}: -" . money($o['coupon_discount']);
    $t[] = 'Phí ship: ' . ($o['shipping_fee'] ? money($o['shipping_fee']) : 'Miễn phí');
    $t[] = 'TỔNG: ' . money($o['total']);
    $text = implode("\n", $t);

    /* ---------- Bản HTML ---------- */
    $css = 'font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222';
    $th = 'text-align:left;padding:6px 8px;background:#F1EEE4;font-size:12px;color:#555;border-bottom:1px solid #ddd';
    $td = 'padding:6px 8px;border-bottom:1px solid #eee;vertical-align:top';
    $rowKv = fn($k, $v) => "<tr><td style=\"padding:4px 12px 4px 0;color:#666;white-space:nowrap;vertical-align:top\">$k</td><td style=\"padding:4px 0\">$v</td></tr>";
    $phoneDigits = preg_replace('/\D/', '', $o['phone']);

    $h = "<div style=\"$css;max-width:680px\">";
    if ($type === 'reported') {
        $h .= '<div style="background:#FFF4D6;border:1px solid #E9C98E;border-radius:8px;padding:12px 14px;margin-bottom:14px">'
            . '<b style="font-size:16px">Khách báo đã chuyển khoản. Kiểm tra app ngân hàng.</b><br>'
            . 'Số tiền cần nhận: <b>' . money($o['total']) . '</b><br>Nội dung chuyển khoản: <b>' . e($o['code']) . '</b></div>';
    }
    $payColor = $o['payment_method'] === 'cod' ? '#3F4722' : '#1D4A7A';
    $h .= '<table style="border-collapse:collapse;margin-bottom:10px">'
        . $rowKv('Mã đơn', '<b style="font-size:16px">' . e($o['code']) . '</b>')
        . $rowKv('Thời gian', e(fmt_date($o['created_at'])))
        . $rowKv('Thanh toán', '<b style="color:' . $payColor . '">' . e($pay) . '</b>')
        . $rowKv('Nguồn đơn', e(($o['source'] ?: '-') . ($o['utm_campaign'] ? " / {$o['utm_campaign']}" : '') . ($o['ref'] ? " | CTV: {$o['ref']}" : '')))
        . '</table>';

    $h .= '<h3 style="font-size:15px;margin:16px 0 6px;border-bottom:2px solid #3F4722;padding-bottom:4px">Khách hàng</h3><table style="border-collapse:collapse">'
        . $rowKv('Họ tên', '<b>' . e($o['customer_name']) . '</b>')
        . $rowKv('SĐT', '<b>' . e($o['phone']) . '</b> &nbsp; <a href="tel:' . $phoneDigits . '">Gọi</a> | <a href="https://zalo.me/' . $phoneDigits . '">Nhắn Zalo</a>')
        . $rowKv('Địa chỉ', e($addr))
        . ($o['customer_note'] ? $rowKv('Ghi chú', '<span style="background:#FFF4D6">' . nl2br(e($o['customer_note'])) . '</span>') : '')
        . ($o['invoice_required'] ? $rowKv('Xuất hóa đơn', e("{$o['invoice_company']} | MST {$o['invoice_tax_code']} | {$o['invoice_email']}")) : '')
        . '</table>';

    $h .= '<h3 style="font-size:15px;margin:16px 0 6px;border-bottom:2px solid #3F4722;padding-bottom:4px">Sản phẩm</h3>'
        . '<table style="border-collapse:collapse;width:100%"><tr>'
        . "<th style=\"$th\">#</th><th style=\"$th\">Sản phẩm</th><th style=\"$th\">Mẫu / mùi</th><th style=\"$th\">SKU</th><th style=\"$th;text-align:right\">SL</th><th style=\"$th;text-align:right\">Thành tiền</th></tr>";
    foreach ($items as $i => $it) {
        $h .= '<tr><td style="' . $td . '">' . ($i + 1) . '</td><td style="' . $td . '">' . e($it['product_name']) . '</td><td style="' . $td . '"><b>' . e($it['variant_name'] ?: '-') . '</b></td>'
            . '<td style="' . $td . '">' . e($it['sku'] ?: '') . '</td><td style="' . $td . ';text-align:right"><b>' . (int)$it['qty'] . '</b></td><td style="' . $td . ';text-align:right">' . money($it['line_total']) . '</td></tr>';
    }
    $h .= '</table>';
    if ($o['gifts']) {
        $h .= '<div style="background:#FFF4D6;border:1px solid #E9C98E;border-radius:6px;padding:8px 10px;margin-top:8px"><b>Quà tặng kèm (nhớ gửi):</b> ' . e(str_replace("\n", ', ', $o['gifts'])) . '</div>';
    }

    $h .= '<h3 style="font-size:15px;margin:16px 0 6px;border-bottom:2px solid #3F4722;padding-bottom:4px">Thanh toán</h3><table style="border-collapse:collapse;min-width:300px">'
        . $rowKv('Tạm tính', money($o['subtotal']))
        . ($o['combo_discount'] ? $rowKv(e($o['combo_label']), '-' . money($o['combo_discount'])) : '')
        . ($o['coupon_discount'] ? $rowKv('Mã ' . e($o['coupon_code']), '-' . money($o['coupon_discount'])) : '')
        . $rowKv('Phí ship', $o['shipping_fee'] ? money($o['shipping_fee']) : 'Miễn phí')
        . $rowKv('<b>TỔNG</b>', '<b style="font-size:17px;color:#A3361E">' . money($o['total']) . '</b>')
        . '</table>';
    $h .= '<p style="color:#888;font-size:12px;margin-top:20px">Email tự động từ website ' . e($site) . '. Trả lời email này nếu cần ghi chú thêm.</p></div>';

    return [$subject, $h, $text];
}

/** Gửi email đơn hàng, lưu trạng thái gửi vào bản dự phòng. Trả về true nếu gửi được. */
function send_order_email(int $orderId, string $type = 'new'): bool
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return false;
    $items = rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    [$subject, $html, $text] = build_order_email($o, $items, $type);
    try {
        send_mail(mail_recipients(), $subject, $html, $text, $o['invoice_email'] ?: null);
        if ($type === 'new') q("UPDATE orders SET email_status = 'sent', email_error = NULL WHERE id = ?", [$orderId]);
        $ok = true;
    } catch (Throwable $e) {
        error_log('[zungza] mail: ' . $e->getMessage());
        q("UPDATE orders SET email_status = 'failed', email_error = ? WHERE id = ?", [mb_substr(($type === 'reported' ? '[Email báo CK] ' : '') . $e->getMessage(), 0, 500), $orderId]);
        $ok = false;
    }
    // Báo thêm qua Telegram nếu có cài
    telegram_send($text ? ($type === 'reported' ? '💰 ' : '🛒 ') . $subject . "\n\n" . mb_substr($text, 0, 3500) : $subject);
    return $ok;
}

function send_quote_email(array $q): void
{
    $subject = "[BÁO GIÁ DN] {$q['company']} | SL {$q['qty']} | {$q['contact']}";
    $rows = [
        'Công ty' => $q['company'], 'Người liên hệ' => $q['contact'], 'SĐT / Zalo' => $q['phone'], 'Email' => $q['email'] ?: '-',
        'Số lượng dự kiến' => $q['qty'], 'Ngân sách / phần' => $q['budget'] ?: '-', 'Ngày cần hàng' => $q['need_date'] ? fmt_date($q['need_date'], 'd/m/Y') : '-',
        'Yêu cầu thêm' => $q['note'] ?: '-',
    ];
    $text = '';
    $html = '<div style="font-family:Arial,sans-serif;font-size:14px"><h3>Yêu cầu báo giá quà tặng doanh nghiệp</h3><table style="border-collapse:collapse">';
    foreach ($rows as $k => $v) {
        $text .= "$k: $v\n";
        $html .= '<tr><td style="padding:4px 12px 4px 0;color:#666;vertical-align:top">' . e($k) . '</td><td style="padding:4px 0"><b>' . nl2br(e((string)$v)) . '</b></td></tr>';
    }
    $html .= '</table></div>';
    try {
        send_mail(mail_recipients(), $subject, $html, $text, $q['email'] ?: null);
    } catch (Throwable $e) {
        error_log('[zungza] quote mail: ' . $e->getMessage());
    }
    telegram_send('📦 ' . $subject . "\n\n" . $text);
}
