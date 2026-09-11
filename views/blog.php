<section class="wrap">
  <div class="page-title"><h1><?= $curCat ? e($curCat['name']) : 'Blog' ?></h1></div>
  <?php if ($cats): ?>
  <div class="chipbar">
    <a class="chip<?= !$curCat ? ' on' : '' ?>" href="<?= url('blog') ?>">Tất cả</a>
    <?php foreach ($cats as $c): ?><a class="chip<?= $curCat && $curCat['id'] === $c['id'] ? ' on' : '' ?>" href="<?= url('blog', ['cat' => $c['slug']]) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($posts): ?>
    <div class="posts three pad-b"><?php foreach ($posts as $p) partial('postcard', ['p' => $p]); ?></div>
    <?php if ($pg['pages'] > 1): ?>
      <nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><a class="<?= $i === $pg['page'] ? 'on' : '' ?>" href="<?= url('blog', array_filter(['cat' => $curCat['slug'] ?? null, 'page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a><?php endfor; ?></nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty"><p>Chưa có bài viết trong mục này.</p></div>
  <?php endif; ?>
</section>
