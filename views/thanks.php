<section class="wrap"><div class="done">
  <?php partial('steps', ['step' => 4, 'bank' => $o['payment_method'] === 'bank']); ?>
  <div class="tick"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg></div>
  <h1><?= e(setting('thanks_title', 'Cảm ơn bạn đã đặt hàng!')) ?></h1>
  <div class="code">Mã đơn: <?= e($o['code']) ?></div>
  <p class="lead-t"><?= nl2br(e(setting('thanks_message'))) ?></p>
  <p class="muted">Số điện thoại nhận cuộc gọi: <b><?= e($o['phone']) ?></b></p>
  <?php if ($o['payment_method'] === 'bank'): ?>
    <p class="pay-state <?= $o['transfer_reported_at'] ? 'ok' : '' ?>"><?= $o['transfer_reported_at'] ? 'Đã ghi nhận bạn báo chuyển khoản. Zungza sẽ kiểm tra và xác nhận.' : 'Đơn đang chờ chuyển khoản.' ?>
      <?php if (!$o['transfer_reported_at']): ?><a class="link" href="<?= url('chuyen-khoan/' . $o['code']) ?>">Xem lại thông tin chuyển khoản</a><?php endif; ?></p>
  <?php endif; ?>
  <div class="summary left">
    <?php foreach ($items as $it): ?><div class="row"><span><?= e($it['product_name'] . ($it['variant_name'] ? ' - ' . $it['variant_name'] : '')) ?> x <?= (int)$it['qty'] ?></span><span><?= money($it['line_total']) ?></span></div><?php endforeach; ?>
    <hr>
    <div class="row"><span>Tạm tính</span><span><?= money($o['subtotal']) ?></span></div>
    <?php if ($o['combo_discount']): ?><div class="row save"><span><?= e($o['combo_label']) ?></span><span>-<?= money($o['combo_discount']) ?></span></div><?php endif; ?>
    <?php if ($o['coupon_discount']): ?><div class="row save"><span>Mã <?= e($o['coupon_code']) ?></span><span>-<?= money($o['coupon_discount']) ?></span></div><?php endif; ?>
    <div class="row"><span>Phí vận chuyển</span><span><?= $o['shipping_fee'] ? money($o['shipping_fee']) : 'Miễn phí' ?></span></div>
    <?php if ($o['gifts']): ?><div class="gifts"><b>Quà tặng kèm đơn</b><ul><?php foreach (lines($o['gifts']) as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row total"><span>Tổng thanh toán</span><span><?= money($o['total']) ?></span></div>
    <div class="row"><span>Thanh toán</span><span><?= e(PAYMENT_METHODS[$o['payment_method']]) ?></span></div>
    <div class="row"><span>Giao đến</span><span class="r-t"><?= e($o['customer_name']) ?>, <?= e($o['address'] . ', ' . $o['ward'] . ', ' . $o['province']) ?></span></div>
  </div>
  <div class="hero-cta center-row"><a class="btn primary" href="<?= url() ?>">Tiếp tục mua sắm</a></div>
</div></section>
