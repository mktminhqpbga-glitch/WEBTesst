<?php
// [key, nhãn, kiểu (text|textarea|number|image|code), gợi ý]
$groups = [
    'Thông tin cửa hàng' => [
        ['site_name', 'Tên cửa hàng', 'text'],
        ['site_tagline', 'Khẩu hiệu (hiện trên tiêu đề trang chủ)', 'text'],
        ['hotline', 'Hotline / Zalo', 'text', '09xxxxxxxx'],
        ['email', 'Email', 'text'],
        ['address', 'Địa chỉ', 'text'],
        ['messenger_link', 'Link Messenger (nút Chat)', 'text', 'https://m.me/tenpage'],
        ['zalo_link', 'Link Zalo', 'text', 'https://zalo.me/09xxxxxxxx'],
        ['facebook_link', 'Link Fanpage', 'text'],
        ['footer_about', 'Giới thiệu ngắn ở chân trang', 'textarea'],
    ],
    'Bán hàng' => [
        ['announcement', 'Thanh thông báo trên cùng', 'text'],
        ['shipping_fee', 'Phí vận chuyển mặc định (đ)', 'number'],
        ['free_ship_min', 'Freeship cho đơn từ (đ), 0 = tắt', 'number'],
        ['order_prefix', 'Tiền tố mã đơn', 'text', 'ZZ'],
        ['assurance', 'Cam kết ở trang sản phẩm (mỗi dòng: Tiêu đề|Mô tả)', 'textarea'],
        ['social_proof_text', 'Dòng social proof chung (số liệu thật, ghi nguồn)', 'text'],
    ],
    'Trang chủ' => [
        ['hero_title', 'Tiêu đề lớn', 'text'],
        ['hero_subtitle', 'Mô tả dưới tiêu đề', 'textarea'],
        ['hero_points', 'Các điểm cam kết (mỗi dòng 1 ý)', 'textarea'],
        ['hero_cta_text', 'Chữ trên nút chính', 'text'],
        ['hero_cta_link', 'Link nút chính (VD: san-pham/ten-san-pham)', 'text'],
        ['hero_image', 'Ảnh lớn đầu trang', 'image'],
        ['featured_title', 'Tiêu đề khối sản phẩm nổi bật', 'text'],
        ['featured_subtitle', 'Mô tả khối sản phẩm nổi bật', 'text'],
        ['combo_title', 'Tiêu đề khối combo', 'text'],
        ['combo_subtitle', 'Mô tả khối combo', 'text'],
        ['story_title', 'Tiêu đề câu chuyện (để trống = ẩn khối)', 'text'],
        ['story_text', 'Nội dung câu chuyện', 'textarea'],
        ['story_facts', 'Số liệu (mỗi dòng: Số|Chú thích)', 'textarea'],
        ['story_image', 'Ảnh câu chuyện', 'image'],
        ['banner_title', 'Banner: tiêu đề (để trống = ẩn)', 'text'],
        ['banner_text', 'Banner: mô tả', 'text'],
        ['banner_button', 'Banner: chữ trên nút', 'text'],
        ['banner_link', 'Banner: link', 'text'],
    ],
    'SEO & quảng cáo' => [
        ['meta_desc', 'Mô tả SEO mặc định', 'textarea'],
        ['og_image', 'Ảnh chia sẻ Facebook/Zalo mặc định (1200x630)', 'image'],
        ['fb_pixel_id', 'Facebook Pixel ID', 'text', 'Chỉ nhập dãy số ID'],
        ['tiktok_pixel_id', 'TikTok Pixel ID', 'text'],
        ['head_code', 'Mã chèn vào <head> (Google Analytics...)', 'code'],
        ['body_end_code', 'Mã chèn cuối trang', 'code'],
    ],
    'Thông báo đơn mới qua Telegram' => [
        ['telegram_bot_token', 'Bot token', 'text'],
        ['telegram_chat_id', 'Chat ID', 'text'],
    ],
];

if (is_post()) {
    try {
        foreach ($groups as $fields) {
            foreach ($fields as $f) {
                [$k, , $type] = $f;
                if ($type === 'image') {
                    $new = handle_upload($_FILES[$k] ?? null, 'settings');
                    if ($new || post("remove_$k")) {
                        delete_upload(setting($k) ?: null);
                        setting_set($k, $new ?? '');
                    }
                    continue;
                }
                $v = (string)post($k);
                if ($type === 'number') $v = (string)(nullable_int($v) ?? 0);
                setting_set($k, $v);
            }
        }
        flash('success', 'Đã lưu cài đặt.');
    } catch (UserError $e) {
        flash('error', $e->getMessage());
    }
    if (post('test_telegram')) {
        settings(true);
        telegram_send('✅ Kết nối Telegram thành công với ' . setting('site_name'));
        flash('info', 'Đã gửi tin thử. Nếu không nhận được, kiểm tra lại token và chat ID.');
    }
    redirect(admin_url('settings'));
}

admin_header('Cài đặt', 'settings');
?>
<h1>Cài đặt website</h1>
<p class="muted">Email nhận đơn và tài khoản chuyển khoản nằm ở 2 mục riêng: <a href="<?= admin_url('email') ?>">Email nhận đơn</a>, <a href="<?= admin_url('payment') ?>">Thanh toán & QR</a>.</p>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php foreach ($groups as $title => $fields): ?>
  <div class="card">
    <h2><?= e($title) ?></h2>
    <?php foreach ($fields as $f): [$k, $label, $type] = $f; $ph = $f[3] ?? ''; $v = settings()[$k] ?? ''; ?>
      <?php if ($type === 'image'): ?>
        <div class="field-img">
          <span class="lbl"><?= e($label) ?></span>
          <?php if ($v): ?><img class="preview" src="<?= e(media($v)) ?>" alt=""><label class="inline"><input type="checkbox" name="remove_<?= e($k) ?>" value="1"> Xóa ảnh</label><?php endif; ?>
          <input type="file" name="<?= e($k) ?>" accept="image/*">
        </div>
      <?php elseif ($type === 'textarea' || $type === 'code'): ?>
        <label><?= e($label) ?><textarea name="<?= e($k) ?>" rows="<?= $type === 'code' ? 4 : 3 ?>" <?= $type === 'code' ? 'class="mono" spellcheck="false"' : '' ?> placeholder="<?= e($ph) ?>"><?= e($v) ?></textarea></label>
      <?php else: ?>
        <label><?= e($label) ?><input name="<?= e($k) ?>" value="<?= e($v) ?>" placeholder="<?= e($ph) ?>" <?= $type === 'number' ? 'inputmode="numeric"' : '' ?>></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (str_starts_with($title, 'Thông báo')): ?>
      <p class="muted small">Tạo bot qua @BotFather trên Telegram để lấy token, nhắn cho bot 1 tin rồi lấy chat ID. Mỗi đơn mới và yêu cầu báo giá sẽ báo về Telegram.</p>
      <label class="inline"><input type="checkbox" name="test_telegram" value="1"> Gửi tin thử khi lưu</label>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="savebar"><button class="btn primary" type="submit">Lưu cài đặt</button></div>
</form>
<?php admin_footer();
