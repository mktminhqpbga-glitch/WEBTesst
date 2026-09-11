<?php $unit = setting('combo_unit', 'sản phẩm'); ?>
<section class="hero wrap">
  <div>
    <h1><?= e(setting('hero_title')) ?></h1>
    <?php if (setting('hero_subtitle')): ?><p class="lead"><?= e(setting('hero_subtitle')) ?></p><?php endif; ?>
    <div class="hero-cta">
      <a class="btn primary" href="<?= e(link_to(setting('hero_cta_link'))) ?>"><?= e(setting('hero_cta_text', 'Xem sản phẩm')) ?></a>
      <a class="btn ghost" href="<?= url('qua-doanh-nghiep') ?>">Quà doanh nghiệp</a>
    </div>
    <?php if ($pts = setting_lines('hero_points')): ?>
      <ul class="promise"><?php foreach ($pts as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
  <div class="hero-art"><img src="<?= e(media(setting('hero_image'), 'img/hero.svg')) ?>" alt="<?= e(setting('hero_title')) ?>" fetchpriority="high"></div>
</section>

<?php if ($homeCats): ?>
<section class="wrap sec tight">
  <div class="tiles" style="--n:<?= count($homeCats) + 1 ?>">
    <?php foreach ($homeCats as $c): ?>
      <a class="tile" href="<?= url('danh-muc/' . $c['slug']) ?>"><img src="<?= e(media($c['image'])) ?>" alt="" loading="lazy"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a class="tile" href="<?= url('qua-doanh-nghiep') ?>"><img src="<?= asset('img/gift.svg') ?>" alt="" loading="lazy">Quà doanh nghiệp</a>
  </div>
</section>
<?php endif; ?>

<?php if ($cards): ?>
<section class="wrap sec">
  <div class="sec-head">
    <div><h2><?= e(setting('featured_title', 'Sản phẩm nổi bật')) ?></h2><?php if (setting('featured_subtitle')): ?><p><?= e(setting('featured_subtitle')) ?></p><?php endif; ?></div>
  </div>
  <div class="grid"><?php foreach ($cards as $c) partial('card', ['c' => $c]); ?></div>
</section>
<?php endif; ?>

<?php if ($tiers): ?>
<section class="wrap sec">
  <div class="sec-head"><div><h2><?= e(setting('combo_title')) ?></h2><?php if (setting('combo_subtitle')): ?><p><?= e(setting('combo_subtitle')) ?></p><?php endif; ?></div></div>
  <div class="tiers">
    <?php foreach ($tiers as $i => $t): ?>
    <div class="tier<?= $i === count($tiers) - 1 ? ' top' : '' ?>">
      <div class="q"><?= (int)$t['min_qty'] ?><span><?= e($unit) ?></span></div>
      <?php if ($t['discount_amount'] > 0): ?><div class="save">Tiết kiệm <?= short_money((int)$t['discount_amount']) ?></div><?php endif; ?>
      <ul>
        <?php if ($t['free_shipping']): ?><li>Freeship</li><?php endif; ?>
        <?php foreach (tier_gifts($t) as $g): ?><li>Tặng <?= e(mb_strtolower($g)) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (setting('story_title')): ?>
<section class="wrap sec">
  <div class="story">
    <div class="story-img"><img src="<?= e(media(setting('story_image'), 'img/bark.svg')) ?>" alt="" loading="lazy"></div>
    <div>
      <h2><?= e(setting('story_title')) ?></h2>
      <?php if (setting('story_text')): ?><p><?= nl2br(e(setting('story_text'))) ?></p><?php endif; ?>
      <?php if ($facts = setting_lines('story_facts', true)): ?>
        <div class="facts"><?php foreach ($facts as [$big, $small]): ?><div><b><?= e($big) ?></b><span><?= e($small) ?></span></div><?php endforeach; ?></div>
      <?php endif; ?>
      <a class="link" href="<?= url('trang/ve-zungza') ?>">Đọc câu chuyện <?= e(setting('site_name')) ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (setting('banner_title')): ?>
<section class="wrap sec">
  <div class="spirit">
    <div><h2><?= e(setting('banner_title')) ?></h2><?php if (setting('banner_text')): ?><p><?= e(setting('banner_text')) ?></p><?php endif; ?></div>
    <?php if (setting('banner_button')): ?><a class="btn" href="<?= e(link_to(setting('banner_link'))) ?>"><?= e(setting('banner_button')) ?></a><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($reviews || setting('social_proof_text')): ?>
<section class="wrap sec">
  <div class="sec-head"><div><h2>Khách nói gì về <?= e(setting('site_name')) ?></h2></div></div>
  <?php partial('reviews', ['reviews' => $reviews]); ?>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<section class="wrap sec">
  <div class="sec-head"><div><h2>Blog</h2></div><a class="link" href="<?= url('blog') ?>">Xem tất cả</a></div>
  <div class="posts three"><?php foreach ($posts as $p) partial('postcard', ['p' => $p]); ?></div>
</section>
<?php endif; ?>
