<?php /** @var array $pr */ $unit = setting('combo_unit', 'sản phẩm'); ?>
<div class="row"><span>Tạm tính</span><span><?= money($pr['subtotal']) ?></span></div>
<?php if ($pr['combo_discount']): ?><div class="row save"><span><?= e($pr['combo_label'] ?: 'Ưu đãi combo') ?></span><span>-<?= money($pr['combo_discount']) ?></span></div><?php endif; ?>
<?php if ($pr['coupon_discount']): ?><div class="row save"><span>Mã <?= e($pr['coupon']['code']) ?></span><span>-<?= money($pr['coupon_discount']) ?></span></div><?php endif; ?>
<div class="row"><span>Phí vận chuyển</span><span><?= $pr['shipping'] ? money($pr['shipping']) : 'Miễn phí' ?></span></div>
<?php if ($pr['gifts']): ?>
<div class="gifts"><b>Quà tặng kèm đơn</b><ul><?php foreach ($pr['gifts'] as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="row total"><span>Tổng thanh toán</span><span><?= money($pr['total']) ?></span></div>
