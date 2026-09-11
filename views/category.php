<section class="wrap">
  <div class="page-title">
    <h1><?= e($cat['name']) ?></h1>
    <?php if ($cat['description']): ?><p class="muted"><?= e($cat['description']) ?></p><?php endif; ?>
  </div>
  <?php if (count($tags) > 1): ?>
  <div class="chipbar">
    <a class="chip<?= $tag === '' ? ' on' : '' ?>" href="<?= url('danh-muc/' . $cat['slug']) ?>">Tất cả</a>
    <?php foreach ($tags as $t): ?>
      <a class="chip<?= $tag === $t ? ' on' : '' ?>" href="<?= url('danh-muc/' . $cat['slug'], ['tag' => $t]) ?>"><?= e($t) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($cards): ?>
    <div class="grid pad-b"><?php foreach ($cards as $c) partial('card', ['c' => $c]); ?></div>
  <?php else: ?>
    <div class="empty"><p>Danh mục này chưa có sản phẩm.</p><a class="btn primary" href="<?= url() ?>">Về trang chủ</a></div>
  <?php endif; ?>
</section>
