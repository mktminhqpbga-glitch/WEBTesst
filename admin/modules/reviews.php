<?php
if (is_post()) {
    $rid = (int)post('id', '0');
    if (post('act') === 'delete') {
        $img = val('SELECT image FROM reviews WHERE id = ?', [$rid]);
        q('DELETE FROM reviews WHERE id = ?', [$rid]); delete_upload($img ?: null);
        flash('success', 'Đã xóa đánh giá.'); redirect(admin_url('reviews'));
    }
    $name = mb_substr(post('customer_name'), 0, 120);
    if ($name === '') { flash('error', 'Nhập tên khách.'); redirect(admin_url('reviews')); }
    try { $img = handle_upload($_FILES['image'] ?? null, 'reviews'); } catch (UserError $e) { flash('error', $e->getMessage()); redirect(admin_url('reviews')); }
    $d = ['product_id' => (int)post('product_id', '0') ?: null, 'customer_name' => $name, 'rating' => max(1, min(5, (int)post('rating', '5'))),
          'content' => post('content') ?: null, 'approved' => post('approved') ? 1 : 0];
    if ($rid) {
        if ($img || post('remove_image')) { delete_upload(val('SELECT image FROM reviews WHERE id = ?', [$rid]) ?: null); $d['image'] = $img; }
        q('UPDATE reviews SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', [...array_values($d), $rid]);
    } else {
        $d['image'] = $img;
        $d['created_at'] = now();
        q('INSERT INTO reviews (' . implode(', ', array_keys($d)) . ') VALUES (' . in_list($d) . ')', array_values($d));
    }
    flash('success', 'Đã lưu đánh giá.');
    redirect(admin_url('reviews'));
}
$edit = (int)get('id', '0') ? row('SELECT * FROM reviews WHERE id = ?', [(int)get('id')]) : null;
$r = $edit ?: ['id' => 0, 'product_id' => null, 'customer_name' => '', 'rating' => 5, 'content' => '', 'image' => null, 'approved' => 1];
$products = ['' => 'Chung (hiện mọi sản phẩm)'] + array_column(rows('SELECT id, name FROM products ORDER BY name'), 'name', 'id');
$list = rows('SELECT r.*, p.name AS pname FROM reviews r LEFT JOIN products p ON p.id = r.product_id ORDER BY r.created_at DESC');
admin_header('Đánh giá', 'reviews');
?>
<h1>Đánh giá của khách</h1>
<p class="muted">Chỉ nhập đánh giá thật của khách (kèm ảnh khách gửi nếu có). Không tạo đánh giá giả.</p>
<div class="two">
  <div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Khách</th><th>Sao</th><th>Nội dung</th><th>Sản phẩm</th><th>Hiện</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $x): ?><tr><td class="strong"><?= e($x['customer_name']) ?></td><td><?= (int)$x['rating'] ?>★</td><td class="small"><?= e(excerpt($x['content'], 80)) ?></td>
    <td class="small"><?= e($x['pname'] ?? 'Chung') ?></td><td><?= $x['approved'] ? 'Có' : 'Ẩn' ?></td>
    <td class="nowrap"><a href="<?= admin_url('reviews', ['id' => $x['id']]) ?>">Sửa</a>
    <form method="post" class="inline-form" onsubmit="return confirm('Xóa đánh giá?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="linkbtn danger-t">Xóa</button></form></td></tr>
  <?php endforeach; ?><?php if (!$list): ?><tr><td colspan="6" class="muted">Chưa có đánh giá.</td></tr><?php endif; ?></tbody></table></div></div>
  <form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <h2><?= $r['id'] ? 'Sửa' : 'Thêm' ?> đánh giá</h2>
    <label>Tên khách *<input name="customer_name" value="<?= e($r['customer_name']) ?>" required></label>
    <div class="grid2"><label>Số sao<?= sel('rating', [5 => '5 sao', 4 => '4 sao', 3 => '3 sao', 2 => '2 sao', 1 => '1 sao'], $r['rating']) ?></label>
      <label>Sản phẩm<?= sel('product_id', $products, $r['product_id']) ?></label></div>
    <label>Nội dung<textarea name="content" rows="4"><?= e($r['content']) ?></textarea></label>
    <?php if ($r['image']): ?><img class="preview" src="<?= e(media($r['image'])) ?>" alt=""><label class="inline"><input type="checkbox" name="remove_image" value="1"> Xóa ảnh</label><?php endif; ?>
    <label>Ảnh khách gửi<input type="file" name="image" accept="image/*"></label>
    <label class="inline"><input type="checkbox" name="approved" value="1" <?= checked($r['approved']) ?>> Hiện trên web</label>
    <button class="btn primary" type="submit">Lưu</button>
  </form>
</div>
<?php admin_footer();
