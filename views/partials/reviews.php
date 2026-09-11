<?php /** @var array $reviews */ $sp = setting('social_proof_text'); ?>
<?php if ($sp): ?><p class="stats"><span class="stars" aria-hidden="true">★★★★★</span> <?= e($sp) ?></p><?php endif; ?>
<?php if ($reviews): ?>
<div class="reviews">
  <?php foreach ($reviews as $r): ?>
  <figure class="rv">
    <div class="rv-head"><b><?= e($r['customer_name']) ?></b><span class="stars" aria-label="<?= (int)$r['rating'] ?> sao"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span></div>
    <?php if ($r['content']): ?><blockquote><?= nl2br(e($r['content'])) ?></blockquote><?php endif; ?>
    <?php if ($r['image']): ?><img src="<?= e(media($r['image'])) ?>" alt="Ảnh khách <?= e($r['customer_name']) ?>" loading="lazy"><?php endif; ?>
  </figure>
  <?php endforeach; ?>
</div>
<?php endif; ?>
