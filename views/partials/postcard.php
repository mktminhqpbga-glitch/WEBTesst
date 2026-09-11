<?php /** @var array $p */ ?>
<a class="post" href="<?= url('blog/' . $p['slug']) ?>">
  <div class="pimg"><img src="<?= e(media($p['cover'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy"></div>
  <?php if (!empty($p['cat_name'])): ?><span class="pcat"><?= e($p['cat_name']) ?></span><?php endif; ?>
  <h3><?= e($p['title']) ?></h3>
  <?php if ($p['excerpt']): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
</a>
