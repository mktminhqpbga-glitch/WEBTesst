<?php
$id = (int)get('id', '0');
$isNew = get('new') === '1';
if (is_post() && post('act') === 'delete') {
    q("DELETE FROM pages WHERE id = ? AND slug NOT IN ('ve-zungza','chinh-sach','qua-doanh-nghiep')", [(int)post('id', '0')]);
    flash('success', 'Đã xóa trang (trang hệ thống không xóa được).');
    redirect(admin_url('pages'));
}
if ($id || $isNew) {
    $p = $id ? row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
    if ($id && !$p) redirect(admin_url('pages'));
    $system = $p && in_array($p['slug'], ['ve-zungza', 'chinh-sach', 'qua-doanh-nghiep'], true);
    if (is_post()) {
        $title = mb_substr(post('title'), 0, 200);
        if ($title === '') { flash('error', 'Nhập tiêu đề trang.'); redirect(admin_url('pages', $id ? ['id' => $id] : ['new' => 1])); }
        $slug = $system ? $p['slug'] : unique_slug('pages', slugify(post('slug') ?: $title), $id);
        $d = [$title, $slug, clean_html(post('content')) ?: null, post('status') === 'draft' ? 'draft' : 'published', mb_substr(post('seo_title'), 0, 200) ?: null, mb_substr(post('seo_desc'), 0, 300) ?: null, now()];
        if ($id) q('UPDATE pages SET title=?, slug=?, content=?, status=?, seo_title=?, seo_desc=?, updated_at=? WHERE id=?', [...$d, $id]);
        else { q('INSERT INTO pages (title, slug, content, status, seo_title, seo_desc, updated_at) VALUES (?,?,?,?,?,?,?)', $d); $id = last_id(); }
        flash('success', 'Đã lưu trang.');
        redirect(admin_url('pages', ['id' => $id]));
    }
    $p = $p ?: ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'status' => 'published', 'seo_title' => '', 'seo_desc' => ''];
    $link = $p['slug'] === 'qua-doanh-nghiep' ? url('qua-doanh-nghiep') : url('trang/' . $p['slug']);
    admin_header($id ? 'Sửa trang' : 'Thêm trang', 'pages', true);
    ?>
    <div class="page-head"><h1><?= $id ? 'Sửa trang' : 'Thêm trang' ?></h1><div class="actions"><?php if ($id): ?><a class="btn" target="_blank" href="<?= e($link) ?>">Xem trên web</a><?php endif; ?><a class="btn" href="<?= admin_url('pages') ?>">Danh sách</a></div></div>
    <form method="post" class="card" data-editor-form>
      <?= csrf_field() ?>
      <label>Tiêu đề *<input name="title" value="<?= e($p['title']) ?>" required></label>
      <label>Đường dẫn <?= $system ? '(trang hệ thống, không đổi được)' : '' ?><input name="slug" value="<?= e($p['slug']) ?>" <?= $system ? 'readonly' : '' ?>></label>
      <label>Nội dung<textarea name="content" data-editor rows="16"><?= e($p['content']) ?></textarea></label>
      <div class="grid2"><label>Tiêu đề SEO<input name="seo_title" value="<?= e($p['seo_title']) ?>"></label><label>Trạng thái<?= sel('status', ['published' => 'Hiện', 'draft' => 'Ẩn'], $p['status']) ?></label></div>
      <label>Mô tả SEO<textarea name="seo_desc" rows="2"><?= e($p['seo_desc']) ?></textarea></label>
      <button class="btn primary" type="submit">Lưu trang</button>
    </form>
    <?php admin_footer(true);
    return;
}
$list = rows('SELECT * FROM pages ORDER BY id');
admin_header('Trang nội dung', 'pages');
?>
<div class="page-head"><h1>Trang nội dung</h1><a class="btn primary" href="<?= admin_url('pages', ['new' => 1]) ?>">+ Thêm trang</a></div>
<div class="card"><table class="tbl"><thead><tr><th>Tiêu đề</th><th>Địa chỉ</th><th>Trạng thái</th><th></th></tr></thead><tbody>
<?php foreach ($list as $r): $sys = in_array($r['slug'], ['ve-zungza', 'chinh-sach', 'qua-doanh-nghiep'], true); ?>
  <tr><td><a class="strong" href="<?= admin_url('pages', ['id' => $r['id']]) ?>"><?= e($r['title']) ?></a></td>
    <td class="small"><?= e($r['slug'] === 'qua-doanh-nghiep' ? '/qua-doanh-nghiep' : '/trang/' . $r['slug']) ?></td><td><?= $r['status'] === 'published' ? 'Hiện' : 'Ẩn' ?></td>
    <td><?php if (!$sys): ?><form method="post" class="inline-form" onsubmit="return confirm('Xóa trang?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn danger-t">Xóa</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php admin_footer();
