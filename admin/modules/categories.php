<?php
if (is_post()) {
    $act = post('act');
    $cid = (int)post('id', '0');
    if ($act === 'delete') {
        $img = val('SELECT image FROM categories WHERE id = ?', [$cid]);
        q('DELETE FROM categories WHERE id = ?', [$cid]);
        delete_upload($img ?: null);
        flash('success', 'Đã xóa danh mục. Sản phẩm trong danh mục chuyển thành chưa phân loại.');
        redirect(admin_url('categories'));
    }
    $name = mb_substr(post('name'), 0, 150);
    if ($name === '') { flash('error', 'Nhập tên danh mục.'); redirect(admin_url('categories', $cid ? ['id' => $cid] : [])); }
    try {
        $img = handle_upload($_FILES['image'] ?? null, 'categories');
    } catch (UserError $e) {
        flash('error', $e->getMessage()); redirect(admin_url('categories', $cid ? ['id' => $cid] : []));
    }
    $d = [
        'name' => $name,
        'slug' => unique_slug('categories', slugify(post('slug') ?: $name), $cid),
        'description' => post('description') ?: null,
        'show_home' => post('show_home') ? 1 : 0,
        'sort' => (int)post('sort', '0'),
        'active' => post('active') ? 1 : 0,
    ];
    if ($cid) {
        $old = val('SELECT image FROM categories WHERE id = ?', [$cid]);
        if ($img || post('remove_image')) { $d['image'] = $img; delete_upload($old ?: null); }
        q('UPDATE categories SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', [...array_values($d), $cid]);
    } else {
        $d['image'] = $img;
        q('INSERT INTO categories (' . implode(', ', array_keys($d)) . ') VALUES (' . in_list($d) . ')', array_values($d));
    }
    flash('success', 'Đã lưu danh mục.');
    redirect(admin_url('categories'));
}
$edit = (int)get('id', '0') ? row('SELECT * FROM categories WHERE id = ?', [(int)get('id')]) : null;
$c = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'image' => null, 'show_home' => 1, 'sort' => 0, 'active' => 1];
$list = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY sort, id');
admin_header('Danh mục', 'categories');
?>
<h1>Danh mục sản phẩm</h1>
<div class="two">
  <div class="card">
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th></th><th>Tên</th><th>Đường dẫn</th><th class="r">SP</th><th>Trang chủ</th><th>Hiện</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $r): ?>
        <tr><td><img class="thumb" src="<?= e(media($r['image'])) ?>" alt=""></td><td class="strong"><?= e($r['name']) ?></td><td class="small"><?= e($r['slug']) ?></td><td class="r"><?= (int)$r['n'] ?></td>
          <td><?= $r['show_home'] ? 'Có' : '-' ?></td><td><?= $r['active'] ? 'Có' : 'Ẩn' ?></td>
          <td class="nowrap"><a href="<?= admin_url('categories', ['id' => $r['id']]) ?>">Sửa</a>
            <form method="post" class="inline-form" onsubmit="return confirm('Xóa danh mục <?= e($r['name']) ?>?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn danger-t" type="submit">Xóa</button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <h2><?= $c['id'] ? 'Sửa danh mục' : 'Thêm danh mục' ?></h2>
    <label>Tên *<input name="name" value="<?= e($c['name']) ?>" required></label>
    <label>Đường dẫn<input name="slug" value="<?= e($c['slug']) ?>" placeholder="tự tạo nếu để trống"></label>
    <label>Mô tả<textarea name="description" rows="2"><?= e($c['description']) ?></textarea></label>
    <?php if ($c['image']): ?><img class="preview" src="<?= e(media($c['image'])) ?>" alt=""><label class="inline"><input type="checkbox" name="remove_image" value="1"> Xóa ảnh</label><?php endif; ?>
    <label>Ảnh icon (vuông)<input type="file" name="image" accept="image/*"></label>
    <label>Thứ tự<input name="sort" value="<?= (int)$c['sort'] ?>"></label>
    <label class="inline"><input type="checkbox" name="show_home" value="1" <?= checked($c['show_home']) ?>> Hiện icon ở trang chủ</label>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= checked($c['active']) ?>> Hiện trên menu</label>
    <button class="btn primary" type="submit">Lưu</button>
    <?php if ($c['id']): ?><a class="btn" href="<?= admin_url('categories') ?>">Hủy</a><?php endif; ?>
  </form>
</div>
<?php admin_footer();
