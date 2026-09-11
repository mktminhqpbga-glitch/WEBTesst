<?php $F = function ($name, $label, $type = 'text', $ph = '', $auto = '') use ($errors, $old) { partial('field', compact('name', 'label', 'type', 'ph', 'auto', 'errors', 'old')); }; ?>
<section class="wrap">
  <div class="b2b-hero">
    <div>
      <h1>Quà tặng doanh nghiệp in dấu thương hiệu của bạn</h1>
      <div class="hero-cta mt"><a class="btn primary" href="#bao-gia">Nhận báo giá</a>
        <?php if ($chat = setting('zalo_link') ?: setting('messenger_link')): ?><a class="btn ghost" href="<?= e($chat) ?>" target="_blank" rel="noopener">Chat với <?= e(setting('site_name')) ?></a><?php endif; ?></div>
    </div>
    <div class="b2b-art"><img src="<?= asset('img/gift.svg') ?>" alt=""></div>
  </div>
  <?php if ($page): ?><div class="content prose"><?= $page['content'] ?></div><?php endif; ?>
  <div class="sec">
    <h2 class="mb">Quy trình đặt hàng</h2>
    <ol class="steps"><li>Gửi yêu cầu: số lượng, ngân sách, ngày cần hàng</li><li>Nhận báo giá và mẫu thiết kế</li><li>Duyệt mẫu, đặt cọc</li><li>Sản xuất và giao hàng</li></ol>
  </div>
  <div class="sec" id="bao-gia">
    <?php if ($sent): ?>
      <div class="form-card narrow-card"><h2>Đã gửi yêu cầu báo giá</h2><p class="muted"><?= e(setting('site_name')) ?> sẽ liên hệ lại qua số điện thoại bạn để lại.</p></div>
    <?php else: ?>
    <form class="form-card narrow-card" method="post" action="<?= url('qua-doanh-nghiep') ?>#bao-gia" novalidate>
      <?= csrf_field() ?><div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <h2>Nhận báo giá</h2>
      <div class="two-col"><?php $F('company', 'Tên công ty'); $F('contact', 'Người liên hệ'); ?></div>
      <div class="two-col"><?php $F('phone', 'Số điện thoại / Zalo', 'tel', '09xxxxxxxx'); $F('email', 'Email (không bắt buộc)', 'email'); ?></div>
      <div class="two-col"><?php $F('qty', 'Số lượng dự kiến', 'number', 'Ví dụ: 200'); $F('budget', 'Ngân sách mỗi phần quà', 'text', 'Ví dụ: 300.000đ'); ?></div>
      <?php $F('date', 'Ngày cần hàng', 'date'); ?>
      <div class="field"><label for="f-note">Yêu cầu thêm</label><textarea id="f-note" name="note" placeholder="Khắc logo, in hộp, mùi hương mong muốn..."><?= e($old['note']) ?></textarea></div>
      <button class="btn primary" type="submit">Gửi yêu cầu báo giá</button>
    </form>
    <?php endif; ?>
  </div>
</section>
