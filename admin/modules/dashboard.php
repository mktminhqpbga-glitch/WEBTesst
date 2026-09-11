<?php
if (is_post() && post('act') === 'resend') {
    $oid = (int)post('id', '0');
    if (send_order_email($oid, 'new')) flash('success', 'Đã gửi lại email đơn hàng.');
    else flash('error', 'Gửi lại vẫn lỗi: ' . (string)val('SELECT email_error FROM orders WHERE id = ?', [$oid]));
    redirect(admin_url());
}

$checks = [
    ['Email nhận đơn', (bool)mail_recipients(), 'email', 'Chưa có email nhận đơn'],
    ['Máy chủ gửi mail (SMTP)', setting('smtp_host') !== '', 'email', 'Chưa cài SMTP, mail dễ vào spam'],
    ['Tài khoản chuyển khoản', setting('bank_account') !== '' && setting('bank_holder') !== '', 'payment', 'Chưa có số tài khoản, khách sẽ chỉ thấy COD'],
    ['Hotline & link chat', setting('hotline') !== '' && (setting('messenger_link') !== '' || setting('zalo_link') !== ''), 'settings', 'Chưa có hotline hoặc link chat'],
];
$failed = (int)val("SELECT COUNT(*) FROM orders WHERE email_status = 'failed'");
$monthStart = date('Y-m-01 00:00:00');
$stats = [
    'today' => (int)val('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [date('Y-m-d 00:00:00')]),
    'month' => (int)val('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$monthStart]),
    'month_total' => (int)val('SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ?', [$monthStart]),
    'quotes' => (int)val("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
];
$recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 50');
$items = [];
if ($recent) {
    $ids = array_column($recent, 'id');
    foreach (rows('SELECT * FROM order_items WHERE order_id IN (' . in_list($ids) . ') ORDER BY id', $ids) as $it) $items[$it['order_id']][] = $it;
}

admin_header('Tổng quan', 'dashboard');
?>
<h1>Tổng quan</h1>

<div class="card">
  <h2>Tình trạng cài đặt</h2>
  <ul class="checks">
    <?php foreach ($checks as [$label, $ok, $r, $warn]): ?>
      <li class="<?= $ok ? 'ok' : 'no' ?>"><span><?= $ok ? '✓' : '!' ?></span><?= e($label) ?><?php if (!$ok): ?>: <a href="<?= admin_url($r) ?>"><?= e($warn) ?></a><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="kpis">
  <div class="kpi"><b><?= $stats['today'] ?></b><span>Đơn hôm nay</span></div>
  <div class="kpi"><b><?= $stats['month'] ?></b><span>Đơn tháng này</span></div>
  <div class="kpi"><b><?= money($stats['month_total']) ?></b><span>Tổng giá trị đặt tháng này</span></div>
  <a class="kpi" href="<?= admin_url('quotes') ?>"><b><?= $stats['quotes'] ?></b><span>Yêu cầu báo giá mới</span></a>
  <a class="kpi" href="<?= admin_url('backup') ?>"><b>⬇</b><span>Tải file sao lưu dữ liệu</span></a>
</div>

<?php if ($failed): ?>
<div class="flash error"><b><?= $failed ?> đơn chưa gửi được email.</b> Kiểm tra mục Email nhận đơn, sau đó bấm "Gửi lại email" ở từng đơn bên dưới.</div>
<?php endif; ?>

<div class="card">
  <h2>Đơn gần đây (bản sao dự phòng)</h2>
  <p class="muted small">Đơn chính gửi về email. Danh sách này chỉ để đối chiếu khi mail bị lỗi hoặc vào spam. Bấm vào mã đơn để xem chi tiết.</p>
  <?php if (!$recent): ?><p class="muted">Chưa có đơn nào.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Mã đơn</th><th>Thời gian</th><th>Khách</th><th class="r">Tổng</th><th>Thanh toán</th><th>Email</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $o): ?>
      <tr>
        <td><details><summary class="strong"><?= e($o['code']) ?></summary>
          <div class="order-detail small">
            <?= e($o['address'] . ', ' . $o['ward'] . ', ' . $o['province']) ?><br>
            <?php foreach ($items[$o['id']] ?? [] as $it): ?>• <?= e($it['product_name'] . ($it['variant_name'] ? ' - ' . $it['variant_name'] : '')) ?> x<?= (int)$it['qty'] ?><br><?php endforeach; ?>
            <?php if ($o['gifts']): ?>Quà: <?= e(str_replace("\n", ', ', $o['gifts'])) ?><br><?php endif; ?>
            <?php if ($o['customer_note']): ?>Ghi chú: <?= e($o['customer_note']) ?><br><?php endif; ?>
            <?php if ($o['invoice_required']): ?>Hóa đơn: <?= e($o['invoice_company'] . ' | MST ' . $o['invoice_tax_code'] . ' | ' . $o['invoice_email']) ?><br><?php endif; ?>
            Nguồn: <?= e(($o['source'] ?: '-') . ($o['utm_campaign'] ? ' / ' . $o['utm_campaign'] : '')) ?>
          </div></details></td>
        <td class="nowrap small"><?= fmt_date($o['created_at']) ?></td>
        <td><?= e($o['customer_name']) ?><br><a class="small" href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></td>
        <td class="r nowrap strong"><?= money($o['total']) ?></td>
        <td class="small"><?php if ($o['payment_method'] === 'cod'): ?>COD<?php elseif ($o['transfer_reported_at']): ?><span class="ok">CK: khách báo đã chuyển</span><?php else: ?>CK: chờ chuyển<?php endif; ?></td>
        <td class="small"><?php if ($o['email_status'] === 'sent'): ?><span class="ok">Đã gửi</span><?php else: ?><span class="danger-t" title="<?= e($o['email_error']) ?>"><?= $o['email_status'] === 'failed' ? 'Lỗi' : 'Chưa gửi' ?></span><?php endif; ?></td>
        <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="act" value="resend"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn sm" type="submit">Gửi lại email</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php admin_footer();
