<?php
if (get('preview') === '1') {
    // Xem thử mẫu email với dữ liệu giả
    $o = ['code' => 'ZZ' . date('ymd') . '1234', 'customer_name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'province' => 'Hà Nội', 'ward' => 'Phường Hoàn Kiếm',
          'address' => '12 Hàng Bài', 'customer_note' => 'Giao giờ hành chính', 'source' => 'facebook', 'utm_campaign' => 'trungthu', 'ref' => null,
          'subtotal' => 1047000, 'combo_label' => 'Combo 3 ' . setting('combo_unit', 'hũ'), 'combo_discount' => 300000, 'coupon_code' => null, 'coupon_discount' => 0,
          'shipping_fee' => 0, 'total' => 747000, 'gifts' => "2 nến mini", 'payment_method' => get('pay') === 'bank' ? 'bank' : 'cod', 'transfer_reported_at' => null,
          'invoice_required' => 0, 'invoice_company' => null, 'invoice_tax_code' => null, 'invoice_email' => null, 'created_at' => now()];
    $items = [
        ['product_name' => 'Nến thơm vỏ quế Zungza', 'variant_name' => 'Quế', 'sku' => 'N001-QUE', 'qty' => 1, 'line_total' => 349000],
        ['product_name' => 'Nến thơm vỏ quế Zungza', 'variant_name' => 'Oải Hương', 'sku' => 'N001-OAI', 'qty' => 2, 'line_total' => 698000],
    ];
    [$subject, $html] = build_order_email($o, $items, get('type') === 'reported' ? 'reported' : 'new');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Mẫu email</title><div style="font:13px Arial;background:#eee;padding:10px 16px"><b>Tiêu đề:</b> ' . e($subject) . '</div><div style="padding:16px">' . $html . '</div>';
    exit;
}

if (is_post()) {
    $to = [];
    foreach (preg_split('/[\s,;]+/', post('mail_to')) as $m) if ($m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL)) $to[] = $m;
    setting_set('mail_to', implode(', ', array_unique($to)));
    setting_set('mail_from_name', mb_substr(post('mail_from_name'), 0, 100));
    setting_set('mail_from_email', filter_var(post('mail_from_email'), FILTER_VALIDATE_EMAIL) ? post('mail_from_email') : '');
    setting_set('smtp_host', mb_substr(post('smtp_host'), 0, 120));
    setting_set('smtp_port', (string)((int)post('smtp_port') ?: 587));
    setting_set('smtp_encryption', in_array(post('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? post('smtp_encryption') : 'tls');
    setting_set('smtp_user', mb_substr(post('smtp_user'), 0, 190));
    if (post('smtp_pass') !== '') setting_set('smtp_pass', (string)$_POST['smtp_pass']);
    if (post('clear_pass')) setting_set('smtp_pass', '');
    settings(true);
    if (post('test') === '1') {
        try {
            send_mail(mail_recipients(), 'Thử gửi email từ website ' . setting('site_name'),
                '<p>Email thử thành công. Đơn hàng mới sẽ được gửi về địa chỉ này.</p>', 'Email thử thành công. Đơn hàng mới sẽ được gửi về địa chỉ này.');
            flash('success', 'Đã gửi email thử tới ' . implode(', ', mail_recipients()) . '. Kiểm tra hộp thư (cả mục Spam).');
        } catch (Throwable $e) {
            flash('error', 'Gửi thử lỗi: ' . $e->getMessage());
        }
    } else {
        flash('success', 'Đã lưu cài đặt email.' . ($to ? '' : ' Lưu ý: chưa có email nhận đơn hợp lệ.'));
    }
    redirect(admin_url('email'));
}

$s = settings();
admin_header('Email nhận đơn', 'email');
?>
<h1>Email nhận đơn</h1>
<p class="muted">Mỗi đơn mới, mỗi lần khách báo đã chuyển khoản và mỗi yêu cầu báo giá sẽ gửi về các email bên dưới.
  <a href="<?= admin_url('email', ['preview' => 1]) ?>" target="_blank">Xem mẫu email đơn COD</a> |
  <a href="<?= admin_url('email', ['preview' => 1, 'pay' => 'bank', 'type' => 'reported']) ?>" target="_blank">Mẫu email khách báo đã CK</a></p>
<form method="post" class="two">
  <?= csrf_field() ?>
  <div class="card">
    <h2>Người nhận</h2>
    <label>Email nhận đơn (nhiều email cách nhau dấu phẩy)<input name="mail_to" value="<?= e($s['mail_to'] ?? '') ?>" placeholder="donhang@zungza.vn, chu@gmail.com"></label>
    <label>Tên người gửi hiển thị<input name="mail_from_name" value="<?= e($s['mail_from_name'] ?? '') ?>" placeholder="Zungza Website"></label>
    <label>Email người gửi (để trống = dùng tài khoản SMTP)<input name="mail_from_email" value="<?= e($s['mail_from_email'] ?? '') ?>"></label>
    <div class="actions-inline">
      <button class="btn primary" type="submit">Lưu</button>
      <button class="btn" type="submit" name="test" value="1">Lưu và gửi email thử</button>
    </div>
  </div>
  <div class="card">
    <h2>Máy chủ gửi mail (SMTP)</h2>
    <p class="muted small">Chọn mẫu có sẵn rồi điền tài khoản. Để trống Host thì web dùng hàm mail() của hosting (dễ vào spam, không khuyến khích).</p>
    <div class="actions-inline mb">
      <button type="button" class="btn sm" data-smtp="smtp.gmail.com|587|tls">Gmail</button>
      <button type="button" class="btn sm" data-smtp="smtp.zoho.com|465|ssl">Zoho Mail</button>
      <button type="button" class="btn sm" data-smtp="smtp.office365.com|587|tls">Outlook / Microsoft 365</button>
    </div>
    <div class="grid2">
      <label>Host<input name="smtp_host" id="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com"></label>
      <label>Cổng<input name="smtp_port" id="smtp_port" value="<?= e($s['smtp_port'] ?? '587') ?>" inputmode="numeric"></label>
    </div>
    <label>Bảo mật<?= sel('smtp_encryption', ['tls' => 'TLS (cổng 587)', 'ssl' => 'SSL (cổng 465)', 'none' => 'Không mã hóa'], $s['smtp_encryption'] ?? 'tls', 'id="smtp_enc"') ?></label>
    <label>Tài khoản (email đăng nhập)<input name="smtp_user" value="<?= e($s['smtp_user'] ?? '') ?>" autocomplete="off"></label>
    <label>Mật khẩu <?= !empty($s['smtp_pass']) ? '(đã lưu, để trống nếu không đổi)' : '' ?><input type="password" name="smtp_pass" autocomplete="new-password"></label>
    <?php if (!empty($s['smtp_pass'])): ?><label class="inline"><input type="checkbox" name="clear_pass" value="1"> Xóa mật khẩu đã lưu</label><?php endif; ?>
    <details class="help"><summary>Dùng Gmail thì lấy mật khẩu ở đâu?</summary>
      <ol>
        <li>Bật Xác minh 2 bước cho tài khoản Google.</li>
        <li>Vào Tài khoản Google → Bảo mật → <b>Mật khẩu ứng dụng</b>, tạo mật khẩu mới (16 ký tự).</li>
        <li>Dán mật khẩu 16 ký tự đó vào ô Mật khẩu ở trên. Không dùng mật khẩu Gmail thường.</li>
      </ol>
    </details>
  </div>
</form>
<script>
document.querySelectorAll('[data-smtp]').forEach(function (b) {
  b.addEventListener('click', function () {
    var p = b.dataset.smtp.split('|');
    document.getElementById('smtp_host').value = p[0];
    document.getElementById('smtp_port').value = p[1];
    document.getElementById('smtp_enc').value = p[2];
  });
});
</script>
<?php admin_footer();
