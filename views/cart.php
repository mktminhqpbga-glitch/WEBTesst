<section class="wrap">
<?php if (!$lines): ?>
  <div class="empty">
    <img src="<?= asset('img/placeholder.svg') ?>" alt="" width="120">
    <h1>Giỏ hàng đang trống</h1>
    <p class="muted">Chọn sản phẩm để bắt đầu.</p>
    <a class="btn primary" href="<?= url() ?>">Xem sản phẩm</a>
  </div>
<?php else: ?>
  <?php partial('steps', ['step' => 1]); ?>
  <div class="page-title"><h1>Giỏ hàng</h1><p class="muted">Kiểm tra mẫu mã, số lượng trước khi đặt.</p></div>
  <div class="cols">
    <div>
      <?php if ($nudge && $nudgeVariant): ?>
      <form class="nudge" method="post" action="<?= url('gio-hang/them') ?>">
        <?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int)$nudgeVariant ?>"><input type="hidden" name="qty" value="1">
        <span><?= e($nudge['text']) ?></span>
        <button class="btn sale" type="submit">Thêm 1 <?= e(setting('combo_unit', 'sản phẩm')) ?></button>
      </form>
      <?php endif; ?>
      <?php foreach ($lines as $l): ?>
      <div class="line">
        <a class="th" href="<?= url('san-pham/' . $l['slug']) ?>"><img src="<?= e(media($l['image'])) ?>" alt=""></a>
        <div>
          <a class="nm" href="<?= url('san-pham/' . $l['slug']) ?>"><?= e($l['label']) ?></a>
          <div class="sub"><?= money($l['unit']) ?> / sản phẩm</div>
          <form class="qty" method="post" action="<?= url('gio-hang/cap-nhat') ?>">
            <?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int)$l['vid'] ?>">
            <button type="submit" name="qty" value="<?= $l['qty'] - 1 ?>" aria-label="Giảm">−</button>
            <span><?= (int)$l['qty'] ?></span>
            <button type="submit" name="qty" value="<?= $l['qty'] + 1 ?>" aria-label="Tăng">+</button>
          </form>
        </div>
        <div class="right">
          <b><?= money($l['total']) ?></b>
          <form method="post" action="<?= url('gio-hang/xoa') ?>"><?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int)$l['vid'] ?>"><button class="rm" type="submit">Xóa</button></form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($pr['combo_qty']): ?><p class="muted small">Ưu đãi combo tính theo tổng số <?= e(setting('combo_unit', 'sản phẩm')) ?>, được trộn loại.</p><?php endif; ?>
    </div>
    <aside class="summary">
      <h2>Đơn hàng</h2>
      <form class="coupon" method="post" action="<?= url('gio-hang/ma-giam-gia') ?>">
        <?= csrf_field() ?>
        <input name="coupon" value="<?= e($_SESSION['coupon'] ?? '') ?>" placeholder="Mã giảm giá" aria-label="Mã giảm giá">
        <button class="btn soft" type="submit">Áp dụng</button>
      </form>
      <?php partial('summary', ['pr' => $pr]); ?>
      <a class="btn sale block mt" href="<?= url('thanh-toan') ?>">Tiếp tục: điền thông tin</a>
      <a class="link center mt" href="<?= url() ?>">Mua thêm sản phẩm khác</a>
    </aside>
  </div>
<?php endif; ?>
</section>
