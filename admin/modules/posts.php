<?php
$id = (int)get('id', '0');
$isNew = get('new') === '1';
$bcats = ['' => '- Không chuyên mục -'] + array_column(rows('SELECT id, name FROM blog_categories ORDER BY sort, id'), 'name', 'id');

if (is_post() && post('act') === 'delete') {
    $pid = (int)post('id', '0');
    $cover = val('SELECT cover FROM posts WHERE id = ?', [$pid]);
    q('DELETE FROM posts WHERE id = ?', [$pid]);
    delete_upload($cover ?: null);
    flash('success', 'Đã xóa bài viết.');
    redirect(admin_url('posts'));
}

if ($id || $isNew) {
    $p = $id ? row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
    if ($id && !$p) { flash('error', 'Không tìm thấy bài viết.'); redirect(admin_url('posts')); }
    if (is_post()) {
        $title = mb_substr(post('title'), 0, 220);
        $back = $id ? admin_url('posts', ['id' => $id]) : admin_url('posts', ['new' => 1]);
        if ($title === '') { flash('error', 'Nhập tiêu đề bài viết.'); redirect($back); }
        try { $cover = handle_upload($_FILES['cover'] ?? null, 'posts'); } catch (UserError $e) { flash('error', $e->getMessage()); redirect($back); }
        $status = post('status') === 'published' ? 'published' : 'draft';
        $pub = dt_from_local(post('published_at'));
        if ($status === 'published' && !$pub) $pub = date('Y-m-d H:i:s');
        $d = [
            'category_id' => (int)post('category_id', '0') ?: null,
            'title' => $title,
            'slug' => unique_slug('posts', slugify(post('slug') ?: $title), $id),
            'excerpt' => mb_substr(post('excerpt'), 0, 500) ?: null,
            'content' => clean_html(post('content')) ?: null,
            'author' => mb_substr(post('author'), 0, 120) ?: null,
            'status' => $status,
            'published_at' => $pub,
            'related_product_id' => (int)post('related_product_id', '0') ?: null,
            'seo_title' => mb_substr(post('seo_title'), 0, 200) ?: null,
            'seo_desc' => mb_substr(post('seo_desc'), 0, 300) ?: null,
            'updated_at' => now(),
        ];
        if ($cover || post('remove_cover')) { $d['cover'] = $cover; if ($p) delete_upload($p['cover']); }
        if ($id) {
            q('UPDATE posts SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', [...array_values($d), $id]);
        } else {
            $d['created_at'] = now();
            q('INSERT INTO posts (' . implode(', ', array_keys($d)) . ') VALUES (' . in_list($d) . ')', array_values($d));
            $id = last_id();
        }
        flash('success', $status === 'published' && $pub > date('Y-m-d H:i:s') ? 'Đã lưu. Bài sẽ tự hiện lúc ' . fmt_date($pub) . '.' : 'Đã lưu bài viết.');
        redirect(admin_url('posts', ['id' => $id]));
    }
    $p = $p ?: ['id' => 0, 'category_id' => null, 'title' => '', 'slug' => '', 'cover' => null, 'excerpt' => '', 'content' => '', 'author' => setting('site_name'), 'status' => 'draft', 'published_at' => null, 'related_product_id' => null, 'seo_title' => '', 'seo_desc' => ''];
    $products = ['' => '- Không gắn -'] + array_column(rows('SELECT id, name FROM products ORDER BY name'), 'name', 'id');
    admin_header($id ? 'Sửa bài viết' : 'Viết bài mới', 'posts', true);
    ?>
    <div class="page-head"><h1><?= $id ? 'Sửa bài viết' : 'Viết bài mới' ?></h1>
      <div class="actions"><?php if ($id && $p['status'] === 'published'): ?><a class="btn" target="_blank" href="<?= e(url('blog/' . $p['slug'])) ?>">Xem trên web</a><?php endif; ?><a class="btn" href="<?= admin_url('posts') ?>">Danh sách</a></div></div>
    <form method="post" enctype="multipart/form-data" class="two wide-left" data-editor-form>
      <?= csrf_field() ?>
      <div>
        <div class="card">
          <label>Tiêu đề *<input name="title" value="<?= e($p['title']) ?>" required></label>
          <label>Đường dẫn<input name="slug" value="<?= e($p['slug']) ?>" placeholder="tự tạo nếu để trống"></label>
          <label>Tóm tắt<textarea name="excerpt" rows="2" maxlength="500"><?= e($p['excerpt']) ?></textarea></label>
          <label>Nội dung<textarea name="content" data-editor rows="16"><?= e($p['content']) ?></textarea></label>
        </div>
        <div class="card"><h2>SEO</h2>
          <label>Tiêu đề SEO<input name="seo_title" value="<?= e($p['seo_title']) ?>"></label>
          <label>Mô tả SEO<textarea name="seo_desc" rows="2" maxlength="300"><?= e($p['seo_desc']) ?></textarea></label>
        </div>
      </div>
      <div><div class="card sticky">
        <label>Trạng thái<?= sel('status', ['draft' => 'Nháp', 'published' => 'Đăng'], $p['status']) ?></label>
        <label>Thời gian đăng (đặt tương lai = hẹn giờ)<input type="datetime-local" name="published_at" value="<?= dt_local($p['published_at']) ?>"></label>
        <label>Chuyên mục<?= sel('category_id', $bcats, $p['category_id']) ?></label>
        <label>Tác giả<input name="author" value="<?= e($p['author']) ?>"></label>
        <label>Sản phẩm gắn trong bài<?= sel('related_product_id', $products, $p['related_product_id']) ?></label>
        <?php if ($p['cover']): ?><img class="preview" src="<?= e(media($p['cover'])) ?>" alt=""><label class="inline"><input type="checkbox" name="remove_cover" value="1"> Xóa ảnh bìa</label><?php endif; ?>
        <label>Ảnh bìa (16:10)<input type="file" name="cover" accept="image/*"></label>
        <button class="btn primary block" type="submit">Lưu bài viết</button>
      </div></div>
    </form>
    <?php if ($id): ?>
    <form method="post" action="<?= admin_url('posts') ?>" class="danger-zone" onsubmit="return confirm('Xóa bài viết này?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn danger sm">Xóa bài viết</button></form>
    <?php endif; ?>
    <?php admin_footer(true);
    return;
}

$list = rows('SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id ORDER BY COALESCE(p.published_at, p.created_at) DESC');
admin_header('Bài viết blog', 'posts');
?>
<div class="page-head"><h1>Bài viết blog</h1><a class="btn primary" href="<?= admin_url('posts', ['new' => 1]) ?>">+ Viết bài mới</a></div>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Tiêu đề</th><th>Chuyên mục</th><th>Trạng thái</th><th>Ngày đăng</th></tr></thead>
  <tbody><?php foreach ($list as $r):
    $sched = $r['status'] === 'published' && $r['published_at'] > date('Y-m-d H:i:s'); ?>
    <tr><td><a class="strong" href="<?= admin_url('posts', ['id' => $r['id']]) ?>"><?= e($r['title']) ?></a></td><td><?= e($r['cat_name'] ?? '-') ?></td>
      <td><span class="badge <?= $r['status'] === 'published' && !$sched ? 'st-completed' : ($sched ? 'st-confirmed' : '') ?>"><?= $r['status'] === 'draft' ? 'Nháp' : ($sched ? 'Hẹn giờ' : 'Đã đăng') ?></span></td>
      <td class="small"><?= fmt_date($r['published_at']) ?></td></tr>
  <?php endforeach; ?><?php if (!$list): ?><tr><td colspan="4" class="muted">Chưa có bài viết.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?php admin_footer();
