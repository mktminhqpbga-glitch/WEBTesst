<?php /** @var int $step 1..4 */ $labels = !empty($bank) ? [1 => 'Giỏ hàng', 2 => 'Thông tin', 3 => 'Chuyển khoản', 4 => 'Hoàn tất'] : [1 => 'Giỏ hàng', 2 => 'Thông tin', 3 => 'Hoàn tất'];
if ($step === 4 && empty($bank)) $step = 3; ?>
<?php $final = $step === count($labels); ?>
<ol class="steps-bar" aria-label="Các bước đặt hàng">
  <?php foreach ($labels as $i => $l): ?>
    <li class="<?= $i < $step || ($final && $i === $step) ? 'done' : '' ?><?= $i === $step ? ' cur' : '' ?>" <?= $i === $step ? 'aria-current="step"' : '' ?>><span><?= $i ?></span><?= e($l) ?></li>
  <?php endforeach; ?>
</ol>
