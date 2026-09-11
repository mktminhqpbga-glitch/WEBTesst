<?php
$unitWord = setting('combo_unit', 'sản phẩm');
$sellable = $unit > 0;
$chatLink = setting('messenger_link') ?: setting('zalo_link');
$multi = count($variants) > 1 && !($variants[0]['name'] === 'Mặc định' && count($variants) === 1);
$combo = $sellable && $tiers;
$jsVariants = array_map(fn($v) => [
    'id' => (int)$v['id'],
    'name' => $v['name'],
    'price' => $v['price'] !== null ? (int)$v['price'] : (int)$p['price'],
    'desc' => (string)$v['description'],
    'image' => $v['image'] ? media($v['image']) : null,
], $variants);
$jsTiers = array_map(fn($t) => ['q' => (int)$t['min_qty'], 'd' => (int)$t['discount_amount'], 'f' => (int)$t['free_shipping'], 'g' => tier_gifts($t)], $tiers);
$yt = youtube_id($p['video_url']);
$firstTier = $tiers ? (int)$tiers[0]['min_qty'] : 1;
?>
<section class="wrap">
  <nav class="crumb"><a href="<?= url() ?>">Trang chủ</a><?php if ($p['cat_slug']): ?> / <a href="<?= url('danh-muc/' . $p['cat_slug']) ?>"><?= e($p['cat_name']) ?></a><?php endif; ?> / <?= e($p['name']) ?></nav>
  <div class="pdp">
    <div class="gallery">
      <div class="main-img" id="mainImg">
        <?php if ($selected['image']): ?>
          <img src="<?= e(media($selected['image'])) ?>" alt="<?= e($p['name'] . ' ' . $selected['name']) ?>">
        <?php elseif ($images): ?>
          <img src="<?= e(media($images[0]['path'])) ?>" alt="<?= e($images[0]['alt'] ?: $p['name']) ?>">
        <?php else: ?>
          <img src="<?= asset('img/placeholder.svg') ?>" alt="<?= e($p['name']) ?>">
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1 || $yt || $p['video_url']): ?>
      <div class="thumbs">
        <?php foreach ($images as $i => $img): ?>
          <button type="button" class="thumb" data-img="<?= e(media($img['path'])) ?>" data-alt="<?= e($img['alt'] ?: $p['name']) ?>" aria-label="Ảnh <?= $i + 1 ?>"><img src="<?= e(media($img['path'])) ?>" alt="" loading="lazy"></button>
        <?php endforeach; ?>
        <?php if ($yt): ?>
          <button type="button" class="thumb vid-thumb" data-yt="<?= e($yt) ?>" aria-label="Xem video">Video</button>
        <?php elseif ($p['video_url']): ?>
          <a class="thumb vid-thumb" href="<?= e($p['video_url']) ?>" target="_blank" rel="noopener">Video</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <h1><?= e($p['name']) ?></h1>
      <?php if ($p['sold_label']): ?><div class="rating"><span class="stars" aria-hidden="true">★★★★★</span> <?= e($p['sold_label']) ?></div><?php endif; ?>

      <div class="pricebox">
        <?php if ($sellable): ?>
          <span class="price" id="unitPrice"><?= money($unit) ?></span>
          <?php if ($selected['price'] === null && $p['compare_price'] > $unit): ?><span class="old"><?= money($p['compare_price']) ?></span><?php endif; ?>
          <?php if ($combo): ?><span class="note">Giá 1 <?= e($unitWord) ?>. Chọn combo bên dưới để nhận ưu đãi.</span><?php endif; ?>
        <?php else: ?>
          <span class="price">Liên hệ</span><span class="note">Nhắn <?= e(setting('site_name')) ?> để nhận báo giá sản phẩm này.</span>
        <?php endif; ?>
      </div>

      <?php if ($sellable): ?>
      <form id="addform" method="post" action="<?= url('gio-hang/them') ?>">
        <?= csrf_field() ?>
        <?php if ($multi): ?>
          <div class="opt-label">Chọn loại <span id="vdesc"><?= e($selected['description']) ?></span></div>
          <div class="chips" role="radiogroup">
            <?php foreach ($variants as $v): ?>
              <a class="chip<?= $v['id'] === $selected['id'] ? ' on' : '' ?>" href="<?= url('san-pham/' . $p['slug'], ['v' => $v['id']]) ?>" data-vid="<?= (int)$v['id'] ?>" role="radio" aria-checked="<?= $v['id'] === $selected['id'] ? 'true' : 'false' ?>">
                <?php if ($v['swatch']): ?><span class="dot" style="background:<?= e($v['swatch']) ?>"></span><?php endif; ?><?= e($v['name']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($combo): ?>
          <div class="opt-label">Chọn combo</div>
          <div class="combo" role="radiogroup" aria-label="Chọn combo">
            <?php foreach ($tiers as $t): $q = (int)$t['min_qty']; $gifts = tier_gifts($t); ?>
              <label class="combo-opt">
                <input type="radio" name="tier" value="<?= $q ?>" <?= $q === $firstTier ? 'checked' : '' ?>>
                <span class="radio"></span>
                <span><span class="t"><?= $q ?> <?= e($unitWord) ?></span>
                  <span class="d"><?= $t['free_shipping'] ? 'Freeship' : '+' . money((int)setting('shipping_fee', '0')) . ' phí ship' ?><?= $t['discount_amount'] > 0 ? ', tiết kiệm ' . short_money((int)$t['discount_amount']) : '' ?></span>
                  <?php if ($gifts): ?><span class="g">Tặng <?= e(mb_strtolower(implode(', ', $gifts))) ?></span><?php endif; ?>
                </span>
                <span class="p" data-tier-price="<?= $q ?>"><?= money(max(0, $q * $unit - (int)$t['discount_amount'])) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div id="slotsWrap" <?= $multi ? '' : 'hidden' ?>>
            <div class="opt-label" id="slotsLabel" <?= $firstTier > 1 ? '' : 'hidden' ?>>Chọn loại cho từng <?= e($unitWord) ?></div>
            <div class="slots" id="slots">
              <?php for ($i = 0; $i < $firstTier; $i++): ?>
                <label <?= $firstTier > 1 ? '' : 'class="sr"' ?>><?= e(mb_convert_case($unitWord, MB_CASE_TITLE)) ?> <?= $i + 1 ?>
                  <select name="variant_ids[]"><?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $v['id'] === $selected['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?></select>
                </label>
              <?php endfor; ?>
            </div>
          </div>
        <?php else: ?>
          <input type="hidden" name="variant_id" id="variantId" value="<?= (int)$selected['id'] ?>">
          <div class="opt-label">Số lượng</div>
          <div class="qty big"><button type="button" data-step="-1" aria-label="Giảm">−</button><input type="number" name="qty" value="1" min="1" max="99" aria-label="Số lượng"><button type="button" data-step="1" aria-label="Tăng">+</button></div>
        <?php endif; ?>
      </form>
      <?php endif; ?>

      <?php if ($as = setting_lines('assurance', true)): ?>
        <div class="assure"><?php foreach ($as as [$b, $s]): ?><div><b><?= e($b) ?></b><?= e($s) ?></div><?php endforeach; ?></div>
      <?php endif; ?>

      <?php if ($p['scent_notes'] || $multi): ?>
      <div class="block">
        <h2>Mùi hương</h2>
        <div class="scent-note">
          <?php if ($selected['swatch']): ?><span class="sw" id="sw" style="background:<?= e($selected['swatch']) ?>"></span><?php endif; ?>
          <div><b id="snName"><?= e($selected['name'] === 'Mặc định' ? $p['name'] : $selected['name']) ?></b><?php if ($selected['description']): ?>: <span id="snDesc"><?= e($selected['description']) ?></span><?php endif; ?>
            <?php if ($p['scent_notes']): ?><p class="muted"><?= nl2br(e($p['scent_notes'])) ?></p><?php endif; ?></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($p['description'] || $specs): ?>
      <div class="block">
        <h2>Mô tả sản phẩm</h2>
        <?php if ($specs): ?>
          <table class="spec"><?php foreach ($specs as [$k, $v]): ?><tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?></table>
        <?php endif; ?>
        <?php if ($p['description']): ?><div class="content"><?= $p['description'] ?></div><?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($reviews || setting('social_proof_text')): ?>
        <div class="block"><h2>Đánh giá</h2><?php partial('reviews', ['reviews' => $reviews]); ?></div>
      <?php endif; ?>

      <?php if ($related): ?>
        <div class="block"><h2>Mua kèm</h2><div class="addon"><?php foreach (array_slice($related, 0, 4) as $c) partial('card', ['c' => $c]); ?></div></div>
      <?php endif; ?>

      <?php if ($posts): ?>
        <div class="block"><h2>Bài viết liên quan</h2><div class="posts"><?php foreach ($posts as $pp) partial('postcard', ['p' => $pp]); ?></div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="buybar">
  <div class="in">
    <?php if ($chatLink): ?>
      <a class="chatb" href="<?= e($chatLink) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 1 1 3.4 6.5L4 20l1.3-3.6A8 8 0 0 1 4 12z"/></svg>Chat</a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($sellable): ?>
      <button class="btn ghost" type="submit" form="addform">Thêm vào giỏ</button>
      <button class="btn sale two" type="submit" form="addform" name="buy_now" value="1">Mua ngay<small id="buyTotal"></small></button>
    <?php else: ?>
      <span></span>
      <a class="btn sale" href="<?= e($chatLink ?: url('trang/ve-zungza')) ?>" <?= $chatLink ? 'target="_blank" rel="noopener"' : '' ?>>Nhắn tư vấn</a>
    <?php endif; ?>
  </div>
</div>

<script>
window.PDP = <?= json_encode([
    'variants' => $jsVariants,
    'tiers' => $jsTiers,
    'selected' => (int)$selected['id'],
    'ship' => (int)setting('shipping_fee', '0'),
    'freeMin' => (int)setting('free_ship_min', '0'),
    'unitWord' => $unitWord,
    'combo' => (bool)$combo,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
