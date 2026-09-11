<?php $content = transfer_content($o['code']); $acc = preg_replace('/\s+/', '', setting('bank_account')); ?>
<section class="wrap narrow-wide">
  <?php partial('steps', ['step' => 3, 'bank' => true]); ?>
  <div class="page-title center-t"><h1>Chuyển khoản để hoàn tất đơn</h1><p class="muted">Đơn <b><?= e($o['code']) ?></b> đã được ghi nhận.</p></div>
  <div class="transfer">
    <?php if ($qr): ?>
    <div class="qr-box">
      <img src="<?= e($qr) ?>" alt="Mã QR chuyển khoản <?= money($o['total']) ?>" width="300" height="300">
      <p class="muted small">Mở app ngân hàng, chọn quét QR.<?= setting('qr_mode') === 'auto' ? ' Số tiền và nội dung đã điền sẵn.' : '' ?></p>
    </div>
    <?php endif; ?>
    <div class="bank-box">
      <div class="kv"><span>Ngân hàng</span><b><?= e(bank_display_name()) ?></b></div>
      <div class="kv"><span>Số tài khoản</span><b class="big"><?= e(setting('bank_account')) ?></b><button type="button" class="copy-btn" data-copy="<?= e($acc) ?>">Sao chép</button></div>
      <div class="kv"><span>Chủ tài khoản</span><b><?= e(setting('bank_holder')) ?></b></div>
      <div class="kv hl"><span>Số tiền</span><b class="big price"><?= money($o['total']) ?></b><button type="button" class="copy-btn" data-copy="<?= (int)$o['total'] ?>">Sao chép</button></div>
      <div class="kv hl"><span>Nội dung</span><b class="big"><?= e($content) ?></b><button type="button" class="copy-btn" data-copy="<?= e($content) ?>">Sao chép</button></div>
      <?php if (setting('transfer_note')): ?><p class="note-t"><?= nl2br(e(setting('transfer_note'))) ?></p><?php endif; ?>
    </div>
  </div>
  <form method="post" class="transfer-actions">
    <?= csrf_field() ?>
    <?php if ($o['transfer_reported_at']): ?>
      <p class="flash success">Bạn đã báo chuyển khoản lúc <?= fmt_date($o['transfer_reported_at']) ?>.</p>
      <a class="btn primary block" href="<?= url('dat-hang-thanh-cong/' . $o['code']) ?>">Xem xác nhận đơn</a>
    <?php else: ?>
      <button class="btn sale block" type="submit" name="act" value="paid">Tôi đã chuyển khoản</button>
      <button class="linkbtn" type="submit" name="act" value="later">Chuyển sau, chờ Zungza gọi xác nhận</button>
    <?php endif; ?>
  </form>
</section>
