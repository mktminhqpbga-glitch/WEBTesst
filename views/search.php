<section class="wrap">
  <div class="page-title"><h1><?= $q !== '' ? 'Kết quả cho “' . e($q) . '”' : 'Tìm kiếm' ?></h1><p class="muted"><?= count($cards) ?> sản phẩm</p></div>
  <?php if ($cards): ?>
    <div class="grid pad-b"><?php foreach ($cards as $c) partial('card', ['c' => $c]); ?></div>
  <?php else: ?>
    <div class="empty"><p>Không có sản phẩm khớp từ khóa này. Thử tên mùi như “quế”, “nhài” hoặc “set quà”.</p><a class="btn primary" href="<?= url() ?>">Về trang chủ</a></div>
  <?php endif; ?>
</section>
