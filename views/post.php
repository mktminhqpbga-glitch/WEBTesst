<section class="wrap"><article class="article">
  <nav class="crumb"><a href="<?= url('blog') ?>">Blog</a><?php if ($post['cat_slug']): ?> / <a href="<?= url('blog', ['cat' => $post['cat_slug']]) ?>"><?= e($post['cat_name']) ?></a><?php endif; ?></nav>
  <h1><?= e($post['title']) ?></h1>
  <p class="muted small"><?= e($post['author'] ?: setting('site_name')) ?> | <?= fmt_date($post['published_at'] ?: $post['created_at'], 'd/m/Y') ?></p>
  <?php if ($post['cover']): ?><div class="cover"><img src="<?= e(media($post['cover'])) ?>" alt="<?= e($post['title']) ?>"></div><?php endif; ?>
  <div class="content"><?= $post['content'] ?></div>
  <?php if ($product): ?>
    <a class="inline-prod" href="<?= e($product['url']) ?>">
      <span class="th"><img src="<?= e(media($product['image'])) ?>" alt=""></span>
      <span><b><?= e($product['title']) ?></b><br>
        <?php if ($product['price'] > 0): ?><span class="price"><?= money($product['price']) ?></span><?php if ($product['compare'] > $product['price']): ?><span class="old"><?= money($product['compare']) ?></span><?php endif; ?><?php endif; ?><br>
        <span class="link">Xem sản phẩm</span></span>
    </a>
  <?php endif; ?>
</article>
<?php if ($more): ?>
  <div class="sec"><div class="sec-head"><h2>Bài viết khác</h2></div><div class="posts three"><?php foreach ($more as $p) partial('postcard', ['p' => $p]); ?></div></div>
<?php endif; ?>
</section>
