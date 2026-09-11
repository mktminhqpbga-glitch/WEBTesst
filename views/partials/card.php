<?php /** @var array $c */ ?>
<a class="card" href="<?= e($c['url']) ?>">
  <div class="img"><img src="<?= e(media($c['image'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy"></div>
  <div class="body">
    <span class="name"><?= e($c['title']) ?></span>
    <?php if (!empty($c['subtitle'])): ?><span class="mood"><?= e($c['subtitle']) ?></span><?php endif; ?>
    <span class="foot">
      <?php if ($c['price'] > 0): ?>
        <span class="price"><?= money($c['price']) ?></span>
        <?php if (!empty($c['compare']) && $c['compare'] > $c['price']): ?><span class="old"><?= money($c['compare']) ?></span><?php endif; ?>
      <?php else: ?>
        <span class="price">Liên hệ</span>
      <?php endif; ?>
    </span>
  </div>
</a>
