<?php
if (is_post()) {
    try {
        $img = handle_upload($_FILES['qr_image'] ?? null, 'settings');
        if ($img || post('remove_qr')) { delete_upload(setting('qr_image') ?: null); setting_set('qr_image', $img ?? ''); }
    } catch (UserError $e) {
        flash('error', $e->getMessage());
        redirect(admin_url('payment'));
    }
    $code = post('bank_code') === '_other' ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('bank_code_other'))) : post('bank_code');
    $cod = post('pay_cod_enabled') ? '1' : '0';
    $bank = post('pay_bank_enabled') ? '1' : '0';
    if ($cod === '0' && $bank === '0') { $cod = '1'; flash('error', 'Phải bật ít nhất 1 hình thức. Đã tự bật lại COD.'); }
    foreach ([
        'pay_cod_enabled' => $cod, 'pay_bank_enabled' => $bank,
        'bank_code' => mb_substr($code, 0, 20), 'bank_name' => mb_substr(post('bank_name'), 0, 100),
        'bank_account' => preg_replace('/[^\d ]/', '', post('bank_account')),
        'bank_holder' => mb_strtoupper(mb_substr(post('bank_holder'), 0, 100)),
        'qr_mode' => in_array(post('qr_mode'), ['auto', 'image', 'none'], true) ? post('qr_mode') : 'auto',
        'transfer_note' => mb_substr(post('transfer_note'), 0, 500),
        'thanks_title' => mb_substr(post('thanks_title'), 0, 150),
        'thanks_message' => mb_substr(post('thanks_message'), 0, 600),
    ] as $k => $v) setting_set($k, (string)$v);
    flash('success', 'Đã lưu cài đặt thanh toán.');
    redirect(admin_url('payment'));
}

$s = settings(true);
$isOther = ($s['bank_code'] ?? '') !== '' && !isset(VIETQR_BANKS[$s['bank_code']]);
$preview = transfer_qr_url(100000, 'ZZTEST');
admin_header('Thanh toán & QR', 'payment');
?>
<h1>Thanh toán & mã QR</h1>
<form method="post" enctype="multipart/form-data" class="two">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <h2>Hình thức thanh toán hiện cho khách</h2>
      <label class="inline"><input type="checkbox" name="pay_cod_enabled" value="1" <?= checked(($s['pay_cod_enabled'] ?? '1') === '1') ?>> Thanh toán khi nhận hàng (COD)</label>
      <label class="inline"><input type="checkbox" name="pay_bank_enabled" value="1" <?= checked(($s['pay_bank_enabled'] ?? '1') === '1') ?>> Chuyển khoản ngân hàng</label>
      <p class="muted small">Chuyển khoản chỉ hiện khi đã điền số tài khoản.</p>
    </div>
    <div class="card">
      <h2>Tài khoản nhận tiền</h2>
      <label>Ngân hàng
        <select name="bank_code" id="bankSel">
          <option value="">- Chọn ngân hàng -</option>
          <?php foreach (VIETQR_BANKS as $c => $n): ?><option value="<?= e($c) ?>" <?= ($s['bank_code'] ?? '') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
          <option value="_other" <?= $isOther ? 'selected' : '' ?>>Ngân hàng khác (nhập mã)</option>
        </select>
      </label>
      <label id="otherWrap" <?= $isOther ? '' : 'hidden' ?>>Mã ngân hàng theo VietQR<input name="bank_code_other" value="<?= $isOther ? e($s['bank_code']) : '' ?>" placeholder="VD: CAKE, TIMO"></label>
      <label>Tên ngân hàng hiển thị cho khách (để trống = tên theo danh sách)<input name="bank_name" value="<?= e($s['bank_name'] ?? '') ?>" placeholder="VD: Vietcombank - CN Quảng Ngãi"></label>
      <label>Số tài khoản<input name="bank_account" value="<?= e($s['bank_account'] ?? '') ?>" inputmode="numeric"></label>
      <label>Tên chủ tài khoản (viết hoa không dấu như trên app ngân hàng)<input name="bank_holder" value="<?= e($s['bank_holder'] ?? '') ?>" placeholder="NGUYEN VAN A"></label>
    </div>
    <div class="card">
      <h2>Chữ hiển thị cho khách</h2>
      <label>Hướng dẫn ở trang chuyển khoản<textarea name="transfer_note" rows="3"><?= e($s['transfer_note'] ?? '') ?></textarea></label>
      <label>Tiêu đề trang cảm ơn<input name="thanks_title" value="<?= e($s['thanks_title'] ?? '') ?>"></label>
      <label>Lời nhắn trang cảm ơn<textarea name="thanks_message" rows="3"><?= e($s['thanks_message'] ?? '') ?></textarea></label>
      <p class="muted small">Nội dung chuyển khoản của khách luôn là <b>mã đơn</b> (VD: <?= e(setting('order_prefix', 'ZZ')) ?><?= date('ymd') ?>1234) để bạn đối chiếu.</p>
    </div>
  </div>
  <div>
    <div class="card sticky">
      <h2>Mã QR ở trang chuyển khoản</h2>
      <label class="inline"><input type="radio" name="qr_mode" value="auto" <?= checked(($s['qr_mode'] ?? 'auto') === 'auto') ?>> Tự tạo QR (VietQR): điền sẵn số tiền + nội dung <b>(khuyên dùng)</b></label>
      <label class="inline"><input type="radio" name="qr_mode" value="image" <?= checked(($s['qr_mode'] ?? '') === 'image') ?>> Dùng ảnh QR tải lên (khách tự nhập số tiền)</label>
      <label class="inline"><input type="radio" name="qr_mode" value="none" <?= checked(($s['qr_mode'] ?? '') === 'none') ?>> Không hiện QR</label>
      <?php if (!empty($s['qr_image'])): ?><img class="preview" src="<?= e(media($s['qr_image'])) ?>" alt=""><label class="inline"><input type="checkbox" name="remove_qr" value="1"> Xóa ảnh QR</label><?php endif; ?>
      <label>Ảnh QR từ app ngân hàng<input type="file" name="qr_image" accept="image/*"></label>
      <h2 class="mt">Xem thử</h2>
      <?php if ($preview): ?>
        <img class="qr-preview" src="<?= e($preview) ?>" alt="QR xem thử" width="240">
        <p class="muted small">QR thử: 100.000đ, nội dung ZZTEST. <b>Quét thử bằng app ngân hàng</b> để chắc chắn đúng tài khoản trước khi chạy thật (không cần chuyển).</p>
      <?php else: ?>
        <p class="muted small">Chưa đủ thông tin để tạo QR. Chọn ngân hàng và điền số tài khoản rồi bấm Lưu.</p>
      <?php endif; ?>
      <button class="btn primary block mt" type="submit">Lưu cài đặt thanh toán</button>
    </div>
  </div>
</form>
<script>
document.getElementById('bankSel').addEventListener('change', function () { document.getElementById('otherWrap').hidden = this.value !== '_other'; });
</script>
<?php admin_footer();
