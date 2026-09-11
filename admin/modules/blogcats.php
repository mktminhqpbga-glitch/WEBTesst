<?php
if (is_post()) {
    $cid = (int)post('id', '0');
    if (post('act') === 'delete') { q('DELETE FROM blog_categories WHERE id = ?', [$cid]); flash('success', 'Đã xóa chuyên mục.'); redirect(admin_url('blogcats')); }
    $name = mb_substr(post('name'), 0, 150);
    if ($name === '') { flash('error', 'Nhập tên chuyên mục.'); redirect(admin_url('blogcats')); }
    $d = [$name, unique_slug('blog_categories', slugify(post('slug') ?: $name), $cid), (int)post('sort', '0')];
    if ($cid) q('UPDATE blog_categories SET name = ?, slug = ?, sort = ? WHERE id = ?', [...$d, $cid]);
    else q('INSERT INTO blog_categories (name, slug, sort) VALUES (?, ?, ?)', $d);
    flash('success', 'Đã lưu chuyên mục.');
    redirect(admin_url('blogcats'));
}
$edit = (int)get('id', '0') ? row('SELECT * FROM blog_categories WHERE id = ?', [(int)get('id')]) : null;
$c = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'sort' => 0];
$list = rows('SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS n FROM blog_categories c ORDER BY sort, id');
admin_header('Chuyên mục blog', 'blogcats');
?>
<h1>Chuyên mục blog</h1>
<div class="two">
  <div class="card"><table class="tbl"><thead><tr><th>Tên</th><th>Đường dẫn</th><th class="r">Bài</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $r): ?><tr><td class="strong"><?= e($r['name']) ?></td><td class="small"><?= e($r['slug']) ?></td><td class="r"><?= (int)$r['n'] ?></td>
    <td class="nowrap"><a href="<?= admin_url('blogcats', ['id' => $r['id']]) ?>">Sửa</a>
    <form method="post" class="inline-form" onsubmit="return confirm('Xóa chuyên mục?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn danger-t">Xóa</button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <h2><?= $c['id'] ? 'Sửa' : 'Thêm' ?> chuyên mục</h2>
    <label>Tên *<input name="name" value="<?= e($c['name']) ?>" required></label>
    <label>Đường dẫn<input name="slug" value="<?= e($c['slug']) ?>"></label>
    <label>Thứ tự<input name="sort" value="<?= (int)$c['sort'] ?>"></label>
    <button class="btn primary" type="submit">Lưu</button>
  </form>
</div>
<?php admin_footer();
