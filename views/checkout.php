<?php $F = function ($name, $label, $type = 'text', $ph = '', $auto = '') use ($errors, $old) { partial('field', compact('name', 'label', 'type', 'ph', 'auto', 'errors', 'old')); }; ?>
<section class="wrap">
  <?php partial('steps', ['step' => 2]); ?>
  <div class="page-title"><h1>Thông tin nhận hàng</h1><p class="muted">Không cần tạo tài khoản.</p></div>
  <?php if (!empty($errors['form'])): ?><div class="flash error"><?= e($errors['form']) ?></div><?php endif; ?>
  <?php if ($errors && empty($errors['form'])): ?><div class="flash error">Kiểm tra lại các ô được đánh dấu bên dưới.</div><?php endif; ?>
  <form class="cols" method="post" action="<?= url('thanh-toan') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <div>
      <div class="form-card"><h2>Người nhận</h2>
        <div class="two-col"><?php $F('name', 'Họ và tên', 'text', 'Nguyễn Văn A', 'name'); $F('phone', 'Số điện thoại', 'tel', '09xxxxxxxx'); ?></div>
        <div class="two-col"><?php $F('province', 'Tỉnh / Thành phố', 'text', '', 'address-level1'); $F('ward', 'Phường / Xã', 'text', '', 'address-level2'); ?></div>
        <?php $F('address', 'Số nhà, tên đường', 'text', '', 'street-address'); ?>
        <div class="field"><label for="f-note">Ghi chú (không bắt buộc)</label><textarea id="f-note" name="note" placeholder="Ví dụ: giao giờ hành chính"><?= e($old['note']) ?></textarea></div>
      </div>
      <div class="form-card<?= !empty($errors['pay']) ? ' bad-card' : '' ?>"><h2>Hình thức thanh toán</h2>
        <div class="pay">
          <?php if (in_array('cod', $methods, true)): ?>
          <label><input type="radio" name="pay" value="cod" <?= $old['pay'] === 'cod' ? 'checked' : '' ?>><span><b>Thanh toán khi nhận hàng (COD)</b><small>Trả tiền mặt cho nhân viên giao hàng.</small></span></label>
          <?php endif; ?>
          <?php if (in_array('bank', $methods, true)): ?>
          <label><input type="radio" name="pay" value="bank" <?= $old['pay'] === 'bank' ? 'checked' : '' ?>><span><b>Chuyển khoản ngân hàng</b><small>Bấm Đặt hàng, trang tiếp theo sẽ hiện số tài khoản và mã QR đã điền sẵn số tiền.</small></span></label>
          <?php endif; ?>
        </div>
        <?php if (!empty($errors['pay'])): ?><p class="err"><?= e($errors['pay']) ?></p><?php endif; ?>
      </div>
      <div class="form-card"><h2>Hóa đơn</h2>
        <label class="check"><input type="checkbox" name="invoice" value="1" id="invToggle" <?= $old['invoice'] ? 'checked' : '' ?>><span>Xuất hóa đơn công ty</span></label>
        <div id="inv" <?= $old['invoice'] ? '' : 'hidden' ?>>
          <?php $F('company', 'Tên công ty', 'text', '', 'organization'); ?>
          <div class="two-col"><?php $F('tax', 'Mã số thuế'); $F('email', 'Email nhận hóa đơn', 'email', '', 'email'); ?></div>
        </div>
      </div>
    </div>
    <aside class="summary">
      <h2>Đơn hàng</h2>
      <?php foreach ($lines as $l): ?><div class="row"><span><?= e($l['label']) ?> x <?= (int)$l['qty'] ?></span><span><?= money($l['total']) ?></span></div><?php endforeach; ?>
      <hr>
      <?php partial('summary', ['pr' => $pr]); ?>
      <button class="btn sale block mt" type="submit">Đặt hàng</button>
      <p class="muted small center mt">Zungza sẽ gọi điện xác nhận đơn trước khi gửi hàng.</p>
      <p class="small center mt"><a class="link" href="<?= url('gio-hang') ?>">Sửa giỏ hàng hoặc nhập mã giảm giá</a></p>
    </aside>
  </form>
</section>
