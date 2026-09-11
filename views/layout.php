<?php
$siteName = setting('site_name', 'Zungza');
$metaDesc = $meta_desc ?? setting('meta_desc');
$ogImage = $og_image ?? (setting('og_image') ? media(setting('og_image')) : null);
$nav = $nav ?? '';
$fbPixel = preg_replace('/\D/', '', setting('fb_pixel_id'));
$ttPixel = preg_replace('/[^\w]/', '', setting('tiktok_pixel_id'));
$chatLink = setting('messenger_link') ?: setting('zalo_link');
$cartN = cart_count();
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title ?? $siteName) ?></title>
<?php if ($metaDesc): ?><meta name="description" content="<?= e($metaDesc) ?>"><?php endif; ?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<?php if ($metaDesc): ?><meta property="og:description" content="<?= e($metaDesc) ?>"><?php endif; ?>
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<?php if ($fbPixel): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= $fbPixel ?>');fbq('track','PageView');</script>
<?php endif; ?>
<?php if ($ttPixel): ?>
<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('<?= $ttPixel ?>');ttq.page();}(window,document,'ttq');</script>
<?php endif; ?>
<?= setting('head_code') ?>
</head>
<body class="<?= e($body_class ?? '') ?>">
<?php if (setting('announcement')): ?><div class="ann"><?= e(setting('announcement')) ?></div><?php endif; ?>
<header class="top">
  <div class="wrap">
    <div class="bar">
      <a class="logo" href="<?= url() ?>" aria-label="<?= e($siteName) ?> trang chủ">
        <img src="<?= asset('img/favicon.svg') ?>" alt="" width="26" height="26"><?= e($siteName) ?>
      </a>
      <form class="search" role="search" action="<?= url('tim-kiem') ?>" method="get">
        <?php if (!cfg('pretty_urls', true)): ?><input type="hidden" name="_path" value="tim-kiem"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Tìm mùi hương, set quà..." aria-label="Tìm sản phẩm">
      </form>
      <a class="cart-btn" href="<?= url('gio-hang') ?>" aria-label="Giỏ hàng (<?= $cartN ?>)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 7z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/></svg>
        <?php if ($cartN): ?><span class="count"><?= $cartN ?></span><?php endif; ?>
      </a>
    </div>
    <nav class="cats" aria-label="Danh mục">
      <a href="<?= url() ?>" class="<?= $nav === 'home' ? 'on' : '' ?>">Trang chủ</a>
      <?php foreach (nav_categories() as $c): ?>
        <a href="<?= url('danh-muc/' . $c['slug']) ?>" class="<?= $nav === 'cat-' . $c['id'] ? 'on' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= url('qua-doanh-nghiep') ?>" class="<?= $nav === 'b2b' ? 'on' : '' ?>">Quà doanh nghiệp</a>
      <a href="<?= url('blog') ?>" class="<?= $nav === 'blog' ? 'on' : '' ?>">Blog</a>
    </nav>
  </div>
</header>

<main>
  <?php $fl = flashes(); if ($fl): ?>
    <div class="wrap flashes"><?php foreach ($fl as [$t, $m]): ?><div class="flash <?= e($t) ?>" role="status"><?= e($m) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <?= $content ?>
</main>

<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <h3><?= e($siteName) ?></h3>
        <p><?= e(setting('footer_about')) ?></p>
        <p class="contact">
          <?php if (setting('hotline')): ?>Hotline/Zalo: <a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('hotline'))) ?>"><?= e(setting('hotline')) ?></a><br><?php endif; ?>
          <?php if (setting('email')): ?>Email: <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><br><?php endif; ?>
          <?php if (setting('address')): ?>Địa chỉ: <?= e(setting('address')) ?><?php endif; ?>
        </p>
      </div>
      <div>
        <h3>Hỗ trợ</h3>
        <ul>
          <li><a href="<?= url('trang/chinh-sach') ?>">Chính sách giao hàng, đổi trả</a></li>
          <?php if ($chatLink): ?><li><a href="<?= e($chatLink) ?>" target="_blank" rel="noopener">Chat với <?= e($siteName) ?></a></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h3>Khám phá</h3>
        <ul>
          <li><a href="<?= url('trang/ve-zungza') ?>">Về <?= e($siteName) ?></a></li>
          <li><a href="<?= url('qua-doanh-nghiep') ?>">Quà tặng doanh nghiệp, OEM</a></li>
          <li><a href="<?= url('blog') ?>">Blog</a></li>
          <?php if (setting('facebook_link')): ?><li><a href="<?= e(setting('facebook_link')) ?>" target="_blank" rel="noopener">Fanpage Facebook</a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <p class="copy">© <?= date('Y') ?> <?= e($siteName) ?></p>
  </div>
</footer>

<?php if ($chatLink): ?>
<a class="fab" href="<?= e($chatLink) ?>" target="_blank" rel="noopener" aria-label="Chat với <?= e($siteName) ?>">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 1 1 3.4 6.5L4 20l1.3-3.6A8 8 0 0 1 4 12z"/></svg>Chat
</a>
<?php endif; ?>

<?php if (!empty($pixel)): [$evt, $params] = $pixel; ?>
<script>
<?php if ($fbPixel): ?>fbq('track', <?= json_encode($evt) ?>, <?= json_encode($params, JSON_UNESCAPED_UNICODE) ?>);<?php endif; ?>
<?php if ($ttPixel): $ttMap = ['ViewContent' => 'ViewContent', 'InitiateCheckout' => 'InitiateCheckout', 'Purchase' => 'PlaceAnOrder']; ?>ttq.track(<?= json_encode($ttMap[$evt] ?? $evt) ?>, <?= json_encode($params, JSON_UNESCAPED_UNICODE) ?>);<?php endif; ?>
</script>
<?php endif; ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?= setting('body_end_code') ?>
</body>
</html>
