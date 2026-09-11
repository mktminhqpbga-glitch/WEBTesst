<?php
$id = (int)get('id', '0');
$isNew = get('new') === '1';
$cats = ['' => '- Chưa phân loại -'] + array_column(rows('SELECT id, name FROM categories ORDER BY sort, id'), 'name', 'id');

/* ================= XÓA ================= */
if (is_post() && post('act') === 'delete') {
    $pid = (int)post('id', '0');
    $imgs = array_column(rows('SELECT path FROM product_images WHERE product_id = ?', [$pid]), 'path');
    $vimgs = array_column(rows('SELECT image FROM product_variants WHERE product_id = ? AND image IS NOT NULL', [$pid]), 'image');
    q('DELETE FROM products WHERE id = ?', [$pid]);
    foreach (array_unique(array_merge($imgs, $vimgs)) as $pth) delete_upload($pth);
    flash('success', 'Đã xóa sản phẩm. Đơn hàng cũ vẫn giữ tên sản phẩm.');
    redirect(admin_url('products'));
}

/* ================= THÊM / SỬA ================= */
if ($id || $isNew) {
    $p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
    if ($id && !$p) { flash('error', 'Không tìm thấy sản phẩm.'); redirect(admin_url('products')); }

    $formUrl = $id ? admin_url('products', ['id' => $id]) : admin_url('products', ['new' => 1]);
    $fail = function (string $msg) use ($formUrl): void {
        $keep = $_POST;
        unset($keep['_csrf']);
        $_SESSION['_old_product'] = $keep;
        flash('error', $msg . ' Thông tin bạn nhập vẫn được giữ lại.');
        redirect($formUrl);
    };

    if (is_post()) {
        $name = mb_substr(post('name'), 0, 200);
        if ($name === '') $fail('Nhập tên sản phẩm.');
        // Kiểm tra SKU trùng trước khi lưu
        $skus = [];
        foreach ((array)($_POST['variants'] ?? []) as $v) {
            $sku = strtoupper(trim((string)($v['sku'] ?? '')));
            if ($sku === '' || !empty($v['delete']) || trim((string)($v['name'] ?? '')) === '') continue;
            if (isset($skus[$sku])) $fail("Mã SKU $sku bị nhập 2 lần.");
            $skus[$sku] = (int)($v['id'] ?? 0);
        }
        foreach ($skus as $sku => $vid) {
            $owner = row('SELECT v.id, p.name FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.sku = ?', [$sku]);
            if ($owner && (int)$owner['id'] !== $vid) $fail("Mã SKU $sku đã dùng cho sản phẩm \"{$owner['name']}\".");
        }
        $slug = unique_slug('products', slugify(post('slug') ?: $name), $id);
        $data = [
            'category_id' => (int)post('category_id', '0') ?: null,
            'name' => $name,
            'slug' => $slug,
            'short_desc' => mb_substr(post('short_desc'), 0, 500) ?: null,
            'description' => clean_html(post('description')) ?: null,
            'price' => nullable_int(post('price')) ?? 0,
            'compare_price' => nullable_int(post('compare_price')),
            'status' => post('status') === 'active' ? 'active' : 'draft',
            'is_featured' => post('is_featured') ? 1 : 0,
            'combo_eligible' => post('combo_eligible') ? 1 : 0,
            'list_variants' => post('list_variants') ? 1 : 0,
            'scent_notes' => post('scent_notes') ?: null,
            'space_tags' => mb_substr(post('space_tags'), 0, 255) ?: null,
            'specs' => post('specs') ?: null,
            'video_url' => mb_substr(post('video_url'), 0, 255) ?: null,
            'sold_label' => mb_substr(post('sold_label'), 0, 200) ?: null,
            'seo_title' => mb_substr(post('seo_title'), 0, 200) ?: null,
            'seo_desc' => mb_substr(post('seo_desc'), 0, 300) ?: null,
            'sort' => (int)post('sort', '0'),
            'updated_at' => now(),
        ];
        // Tải ảnh trước (nếu lỗi thì chưa đổi gì trong DB)
        $newImages = [];
        try {
            foreach (files_list($_FILES['images'] ?? null) as $f) {
                if ($pth = handle_upload($f, 'products')) $newImages[] = $pth;
            }
        } catch (UserError $e) {
            foreach ($newImages as $pth) delete_upload($pth);
            $fail($e->getMessage());
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $cols = array_keys($data);
            if ($id) {
                q('UPDATE products SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($data), $id]);
            } else {
                q('INSERT INTO products (' . implode(', ', $cols) . ', created_at) VALUES (' . in_list($cols) . ', ?)', [...array_values($data), now()]);
                $id = last_id();
            }

            // Ảnh cũ: cập nhật alt/thứ tự, xóa ảnh được đánh dấu
            $toDelete = [];
            foreach ((array)($_POST['img'] ?? []) as $imgId => $im) {
                $imgId = (int)$imgId;
                $cur = row('SELECT path FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
                if (!$cur) continue;
                if (!empty($im['delete'])) {
                    q('DELETE FROM product_images WHERE id = ?', [$imgId]);
                    q('UPDATE product_variants SET image = NULL WHERE product_id = ? AND image = ?', [$id, $cur['path']]);
                    $toDelete[] = $cur['path'];
                } else {
                    q('UPDATE product_images SET alt = ?, sort = ? WHERE id = ?', [mb_substr(trim((string)($im['alt'] ?? '')), 0, 200) ?: null, (int)($im['sort'] ?? 0), $imgId]);
                }
            }
            $maxSort = (int)val('SELECT COALESCE(MAX(sort), 0) FROM product_images WHERE product_id = ?', [$id]);
            foreach ($newImages as $i => $pth) {
                q('INSERT INTO product_images (product_id, path, alt, sort) VALUES (?, ?, ?, ?)', [$id, $pth, $name, $maxSort + $i + 1]);
            }

            // Biến thể
            $keep = 0;
            foreach ((array)($_POST['variants'] ?? []) as $v) {
                $vid = (int)($v['id'] ?? 0);
                $vname = mb_substr(trim((string)($v['name'] ?? '')), 0, 120);
                if (!empty($v['delete'])) {
                    if ($vid) q('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $id]);
                    continue;
                }
                if ($vname === '') continue;
                $swatch = trim((string)($v['swatch'] ?? ''));
                $vd = [
                    'name' => $vname,
                    'sku' => mb_substr(strtoupper(trim((string)($v['sku'] ?? ''))), 0, 64) ?: null,
                    'price' => nullable_int($v['price'] ?? ''),
                    'swatch' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $swatch) ? $swatch : null,
                    'image' => ($v['image'] ?? '') !== '' && val('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND path = ?', [$id, $v['image']]) ? $v['image'] : null,
                    'description' => mb_substr(trim((string)($v['description'] ?? '')), 0, 255) ?: null,
                    'tags' => mb_substr(trim((string)($v['tags'] ?? '')), 0, 255) ?: null,
                    'sort' => (int)($v['sort'] ?? 0),
                    'active' => !empty($v['active']) ? 1 : 0,
                ];
                $vc = array_keys($vd);
                if ($vid && val('SELECT COUNT(*) FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $id])) {
                    q('UPDATE product_variants SET ' . implode(', ', array_map(fn($c) => "$c = ?", $vc)) . ' WHERE id = ?', [...array_values($vd), $vid]);
                } else {
                    q('INSERT INTO product_variants (product_id, ' . implode(', ', $vc) . ') VALUES (?, ' . in_list($vc) . ')', [$id, ...array_values($vd)]);
                }
                $keep++;
            }
            if (!(int)val('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [$id])) {
                q("INSERT INTO product_variants (product_id, name, sort, active) VALUES (?, 'Mặc định', 0, 1)", [$id]);
            }

            // Mua kèm
            q('DELETE FROM product_related WHERE product_id = ?', [$id]);
            foreach (array_unique(array_map('intval', (array)($_POST['related'] ?? []))) as $rid) {
                if ($rid && $rid !== $id) q('INSERT OR IGNORE INTO product_related (product_id, related_id) VALUES (?, ?)', [$id, $rid]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($newImages as $pth) delete_upload($pth);
            if (is_unique_violation($e) && str_contains($e->getMessage(), 'sku')) {
                if (!$p) $id = 0;
                $fail('Mã SKU bị trùng với biến thể khác.');
            }
            throw $e;
        }
        foreach ($toDelete as $pth) delete_upload($pth);
        flash('success', 'Đã lưu sản phẩm.');
        redirect(admin_url('products', ['id' => $id]));
    }

    $p = $p ?: ['id' => 0, 'category_id' => null, 'name' => '', 'slug' => '', 'short_desc' => '', 'description' => '', 'price' => 0, 'compare_price' => null,
        'status' => 'draft', 'is_featured' => 0, 'combo_eligible' => 0, 'list_variants' => 0, 'scent_notes' => '', 'space_tags' => '', 'specs' => '',
        'video_url' => '', 'sold_label' => '', 'seo_title' => '', 'seo_desc' => '', 'sort' => 0];
    $images = $id ? rows('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]) : [];
    $variants = $id ? rows('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort, id', [$id]) : [];
    if (!$variants) $variants = [['id' => 0, 'name' => 'Mặc định', 'sku' => '', 'price' => null, 'swatch' => '', 'image' => '', 'description' => '', 'tags' => '', 'sort' => 0, 'active' => 1]];
    $relatedIds = $id ? array_column(rows('SELECT related_id FROM product_related WHERE product_id = ?', [$id]), 'related_id') : [];
    if ($old = $_SESSION['_old_product'] ?? null) {
        unset($_SESSION['_old_product']);
        foreach ($p as $k => $v) {
            if (in_array($k, ['is_featured', 'combo_eligible', 'list_variants'], true)) $p[$k] = !empty($old[$k]) ? 1 : 0;
            elseif (array_key_exists($k, $old) && !in_array($k, ['id'], true)) $p[$k] = $old[$k];
        }
        $p['price'] = nullable_int($p['price']) ?? 0;
        $p['compare_price'] = nullable_int($p['compare_price']);
        $blank = ['id' => 0, 'name' => '', 'sku' => '', 'price' => null, 'swatch' => '', 'image' => '', 'description' => '', 'tags' => '', 'sort' => 0, 'active' => 0];
        $variants = array_values(array_map(function ($v) use ($blank) {
            $v = array_merge($blank, array_intersect_key((array)$v, $blank));
            $v['price'] = nullable_int($v['price']);
            return $v;
        }, (array)($old['variants'] ?? []))) ?: $variants;
        $relatedIds = array_map('intval', (array)($old['related'] ?? []));
    }
    $allProducts = rows('SELECT id, name FROM products WHERE id <> ? ORDER BY name', [$id]);
    $imgOpts = ['' => '- Ảnh chung -'];
    foreach ($images as $i => $im) $imgOpts[$im['path']] = 'Ảnh ' . ($i + 1);

    $vrow = function (int $i, array $v) use ($imgOpts): string {
        ob_start(); ?>
        <tr>
          <td><input type="hidden" name="variants[<?= $i ?>][id]" value="<?= (int)$v['id'] ?>"><input name="variants[<?= $i ?>][name]" value="<?= e($v['name']) ?>" placeholder="VD: Quế" required></td>
          <td><input name="variants[<?= $i ?>][sku]" value="<?= e($v['sku']) ?>" placeholder="N001-QUE"></td>
          <td><input name="variants[<?= $i ?>][price]" value="<?= $v['price'] === null ? '' : (int)$v['price'] ?>" placeholder="Giá chung" inputmode="numeric"></td>
          <td class="sw"><input type="color" value="<?= e($v['swatch'] ?: '#EBD3AE') ?>" data-swatch><input name="variants[<?= $i ?>][swatch]" value="<?= e($v['swatch']) ?>" placeholder="#EBD3AE"></td>
          <td><?= sel("variants[$i][image]", $imgOpts, $v['image']) ?></td>
          <td><input name="variants[<?= $i ?>][description]" value="<?= e($v['description']) ?>" placeholder="Ấm nồng. Hợp phòng khách"></td>
          <td><input name="variants[<?= $i ?>][tags]" value="<?= e($v['tags']) ?>" placeholder="Phòng khách"></td>
          <td><input name="variants[<?= $i ?>][sort]" value="<?= (int)$v['sort'] ?>" class="xs" inputmode="numeric"></td>
          <td class="c"><input type="checkbox" name="variants[<?= $i ?>][active]" value="1" <?= checked($v['active']) ?> title="Đang bán"></td>
          <td class="c"><input type="checkbox" name="variants[<?= $i ?>][delete]" value="1" title="Xóa"></td>
        </tr>
        <?php return ob_get_clean();
    };

    admin_header($id ? 'Sửa: ' . $p['name'] : 'Thêm sản phẩm', 'products', true);
    ?>
    <div class="page-head">
      <h1><?= $id ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></h1>
      <div class="actions">
        <?php if ($id && $p['status'] === 'active'): ?><a class="btn" href="<?= e(url('san-pham/' . $p['slug'])) ?>" target="_blank">Xem trên web</a><?php endif; ?>
        <a class="btn" href="<?= admin_url('products') ?>">Danh sách</a>
      </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="two wide-left" data-editor-form>
      <?= csrf_field() ?>
      <div>
        <div class="card">
          <h2>Thông tin chính</h2>
          <label>Tên sản phẩm *<input name="name" value="<?= e($p['name']) ?>" required maxlength="200"></label>
          <label>Đường dẫn (để trống sẽ tự tạo)<input name="slug" value="<?= e($p['slug']) ?>" placeholder="nen-thom-vo-que"></label>
          <label>Mô tả ngắn (hiện ở thẻ sản phẩm)<input name="short_desc" value="<?= e($p['short_desc']) ?>" maxlength="500"></label>
          <label>Mô tả chi tiết<textarea name="description" data-editor rows="10"><?= e($p['description']) ?></textarea></label>
        </div>

        <div class="card">
          <h2>Ảnh sản phẩm</h2>
          <?php if ($images): ?>
          <div class="img-grid">
            <?php foreach ($images as $i => $im): ?>
              <div class="img-item">
                <img src="<?= e(media($im['path'])) ?>" alt="">
                <span class="muted small">Ảnh <?= $i + 1 ?></span>
                <input name="img[<?= (int)$im['id'] ?>][alt]" value="<?= e($im['alt']) ?>" placeholder="Mô tả ảnh (alt)">
                <div class="row-in"><label class="inline small">Thứ tự <input class="xs" name="img[<?= (int)$im['id'] ?>][sort]" value="<?= (int)$im['sort'] ?>"></label>
                <label class="inline small danger-t"><input type="checkbox" name="img[<?= (int)$im['id'] ?>][delete]" value="1"> Xóa</label></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <label>Thêm ảnh (chọn được nhiều ảnh, JPG/PNG/WEBP, tối đa <?= (int)cfg('upload_max_mb', 5) ?>MB/ảnh)<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label>
          <p class="muted small">Ảnh có thứ tự nhỏ nhất là ảnh đại diện. Ảnh vuông 1:1, tối thiểu 1000px để hiển thị đẹp.</p>
        </div>

        <div class="card">
          <h2>Loại / biến thể (mùi hương, kích cỡ...)</h2>
          <p class="muted small">Để trống giá = dùng giá chung của sản phẩm. Ảnh riêng: lưu ảnh sản phẩm trước rồi chọn.</p>
          <div class="tbl-wrap"><table class="tbl variants">
            <thead><tr><th>Tên *</th><th>SKU</th><th>Giá riêng</th><th>Màu chấm</th><th>Ảnh</th><th>Mô tả ngắn</th><th>Tag không gian</th><th>TT</th><th>Bán</th><th>Xóa</th></tr></thead>
            <tbody id="vrows">
              <?php foreach ($variants as $i => $v) echo $vrow($i, $v); ?>
            </tbody>
          </table></div>
          <template id="vtpl"><?= $vrow(999, ['id' => 0, 'name' => '', 'sku' => '', 'price' => null, 'swatch' => '', 'image' => '', 'description' => '', 'tags' => '', 'sort' => 0, 'active' => 1]) ?></template>
          <button type="button" class="btn sm" data-add-variant>+ Thêm loại</button>
        </div>

        <div class="card">
          <h2>Mùi hương, thông số, video</h2>
          <label>Mô tả mùi hương<textarea name="scent_notes" rows="3"><?= e($p['scent_notes']) ?></textarea></label>
          <label>Thông số (mỗi dòng dạng <code>Nhãn: Giá trị</code>)<textarea name="specs" rows="6" placeholder="Thời gian thắp: Gần 40 giờ"><?= e($p['specs']) ?></textarea></label>
          <div class="grid2">
            <label>Link video (YouTube sẽ nhúng thẳng)<input name="video_url" value="<?= e($p['video_url']) ?>" placeholder="https://youtube.com/..."></label>
            <label>Tag không gian (khi không tách loại)<input name="space_tags" value="<?= e($p['space_tags']) ?>" placeholder="Phòng khách, Phòng ngủ"></label>
          </div>
          <label>Dòng social proof dưới tên (chỉ ghi số liệu thật, ghi rõ nguồn)<input name="sold_label" value="<?= e($p['sold_label']) ?>" placeholder="VD: 4.8/5 | 58,2k đánh giá | 162k đã bán trên [tên sàn]"></label>
        </div>

        <div class="card">
          <h2>SEO</h2>
          <label>Tiêu đề SEO<input name="seo_title" value="<?= e($p['seo_title']) ?>" maxlength="200"></label>
          <label>Mô tả SEO<textarea name="seo_desc" rows="2" maxlength="300"><?= e($p['seo_desc']) ?></textarea></label>
        </div>
      </div>

      <div>
        <div class="card sticky">
          <h2>Trạng thái & giá</h2>
          <label>Trạng thái<?= sel('status', ['active' => 'Đang bán (hiện trên web)', 'draft' => 'Nháp (ẩn)'], $p['status']) ?></label>
          <label>Danh mục<?= sel('category_id', $cats, $p['category_id']) ?></label>
          <label>Giá bán (đ) - để 0 sẽ hiện "Liên hệ"<input name="price" value="<?= (int)$p['price'] ?>" inputmode="numeric" required></label>
          <label>Giá gạch (đ)<input name="compare_price" value="<?= $p['compare_price'] === null ? '' : (int)$p['compare_price'] ?>" inputmode="numeric"></label>
          <label class="inline"><input type="checkbox" name="is_featured" value="1" <?= checked($p['is_featured']) ?>> Hiện ở trang chủ (nổi bật)</label>
          <label class="inline"><input type="checkbox" name="combo_eligible" value="1" <?= checked($p['combo_eligible']) ?>> Tính vào combo số lượng</label>
          <label class="inline"><input type="checkbox" name="list_variants" value="1" <?= checked($p['list_variants']) ?>> Tách mỗi loại thành 1 thẻ ở danh mục</label>
          <label>Thứ tự hiển thị<input name="sort" value="<?= (int)$p['sort'] ?>" inputmode="numeric"></label>
          <label>Sản phẩm mua kèm (giữ Ctrl/Cmd để chọn nhiều)
            <select name="related[]" multiple size="6"><?php foreach ($allProducts as $ap): ?><option value="<?= (int)$ap['id'] ?>" <?= in_array($ap['id'], $relatedIds) ? 'selected' : '' ?>><?= e($ap['name']) ?></option><?php endforeach; ?></select>
          </label>
          <button class="btn primary block" type="submit">Lưu sản phẩm</button>
        </div>
      </div>
    </form>
    <?php if ($id): ?>
    <form method="post" action="<?= admin_url('products') ?>" class="danger-zone" onsubmit="return confirm('Xóa hẳn sản phẩm này và toàn bộ ảnh? Đơn hàng cũ vẫn giữ tên sản phẩm.')">
      <?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn danger sm" type="submit">Xóa sản phẩm</button><span class="muted small">Muốn ẩn tạm thời thì chuyển trạng thái sang Nháp.</span>
    </form>
    <?php endif; ?>
    <?php
    admin_footer(true);
    return;
}

/* ================= DANH SÁCH ================= */
$qStr = get('q');
$catF = (int)get('cat', '0');
$where = ['1=1'];
$params = [];
if ($qStr !== '') { $where[] = '(p.name LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND (v.sku LIKE ? OR v.name LIKE ?)))'; array_push($params, "%$qStr%", "%$qStr%", "%$qStr%"); }
if ($catF) { $where[] = 'p.category_id = ?'; $params[] = $catF; }
$list = rows('SELECT p.*, c.name AS cat_name,
                (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY sort, id LIMIT 1) AS img,
                (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS vcount
              FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort, p.id', $params);

admin_header('Sản phẩm', 'products');
?>
<div class="page-head"><h1>Sản phẩm</h1><a class="btn primary" href="<?= admin_url('products', ['new' => 1]) ?>">+ Thêm sản phẩm</a></div>
<form class="filters card" method="get" action="<?= e(base_url('admin/index.php')) ?>">
  <input type="hidden" name="r" value="products">
  <input name="q" value="<?= e($qStr) ?>" placeholder="Tên sản phẩm, loại hoặc SKU">
  <?= sel('cat', ['0' => 'Mọi danh mục'] + array_filter($cats, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY), $catF) ?>
  <button class="btn primary" type="submit">Lọc</button>
</form>
<div class="card">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>Sản phẩm</th><th>Danh mục</th><th class="r">Giá</th><th class="r">Loại</th><th>Trạng thái</th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(media($p['img'])) ?>" alt=""></td>
        <td><a class="strong" href="<?= admin_url('products', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a>
          <?= $p['is_featured'] ? '<span class="tag">Nổi bật</span>' : '' ?><?= $p['combo_eligible'] ? '<span class="tag">Combo</span>' : '' ?></td>
        <td><?= e($p['cat_name'] ?? '-') ?></td>
        <td class="r"><?= $p['price'] > 0 ? money($p['price']) : 'Liên hệ' ?></td>
        <td class="r"><?= (int)$p['vcount'] ?></td>
        <td><span class="badge <?= $p['status'] === 'active' ? 'st-completed' : '' ?>"><?= $p['status'] === 'active' ? 'Đang bán' : 'Nháp' ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="muted">Chưa có sản phẩm.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php admin_footer();
