<?php
if (is_post()) {
    $seen = [];
    db()->beginTransaction();
    foreach ((array)($_POST['tiers'] ?? []) as $t) {
        $tid = (int)($t['id'] ?? 0);
        $q = (int)($t['min_qty'] ?? 0);
        if (!empty($t['delete']) || $q < 1) { if ($tid) q('DELETE FROM combo_tiers WHERE id = ?', [$tid]); continue; }
        if (isset($seen[$q])) continue;
        $seen[$q] = true;
        $d = [$q, nullable_int($t['discount_amount'] ?? '') ?? 0, !empty($t['free_shipping']) ? 1 : 0, trim((string)($t['gifts'] ?? '')) ?: null, !empty($t['active']) ? 1 : 0];
        if ($tid) q('UPDATE combo_tiers SET min_qty = ?, discount_amount = ?, free_shipping = ?, gifts = ?, active = ? WHERE id = ?', [...$d, $tid]);
        else q('INSERT INTO combo_tiers (min_qty, discount_amount, free_shipping, gifts, active) VALUES (?,?,?,?,?)', $d);
    }
    db()->commit();
    setting_set('combo_unit', mb_substr(post('combo_unit'), 0, 20) ?: 'sản phẩm');
    flash('success', 'Đã lưu bảng combo.');
    redirect(admin_url('combo'));
}
$tiers = rows('SELECT * FROM combo_tiers ORDER BY min_qty');
$tiers[] = ['id' => 0, 'min_qty' => '', 'discount_amount' => '', 'free_shipping' => 1, 'gifts' => '', 'active' => 1];
$price = (int)val("SELECT price FROM products WHERE combo_eligible = 1 AND status = 'active' AND price > 0 ORDER BY sort LIMIT 1");
admin_header('Combo & quà tặng', 'combo');
?>
<h1>Combo theo số lượng & quà tặng</h1>
<p class="muted">Áp dụng cho các sản phẩm bật "Tính vào combo số lượng". Khách được trộn loại. Quà tự động ghi vào đơn hàng.</p>
<form class="card" method="post">
  <?= csrf_field() ?>
  <label class="narrow">Đơn vị tính (hiện trên web)<input name="combo_unit" value="<?= e(setting('combo_unit', 'hũ')) ?>"></label>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Từ số lượng</th><th>Tiền giảm (đ)</th><th>Giá khách trả<?= $price ? ' (giá ' . money($price) . ')' : '' ?></th><th>Freeship</th><th>Quà tặng (mỗi quà 1 dòng)</th><th>Bật</th><th>Xóa</th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $i => $t): ?>
      <tr>
        <td><input type="hidden" name="tiers[<?= $i ?>][id]" value="<?= (int)$t['id'] ?>"><input class="xs" name="tiers[<?= $i ?>][min_qty]" value="<?= e($t['min_qty']) ?>" inputmode="numeric" placeholder="<?= $t['id'] ? '' : 'Thêm mới' ?>"></td>
        <td><input name="tiers[<?= $i ?>][discount_amount]" value="<?= e($t['discount_amount']) ?>" inputmode="numeric"></td>
        <td class="nowrap strong"><?= $price && $t['min_qty'] !== '' ? money(max(0, $t['min_qty'] * $price - (int)$t['discount_amount'])) : '' ?></td>
        <td class="c"><input type="checkbox" name="tiers[<?= $i ?>][free_shipping]" value="1" <?= checked($t['free_shipping']) ?>></td>
        <td><textarea name="tiers[<?= $i ?>][gifts]" rows="2"><?= e($t['gifts']) ?></textarea></td>
        <td class="c"><input type="checkbox" name="tiers[<?= $i ?>][active]" value="1" <?= checked($t['active']) ?>></td>
        <td class="c"><?php if ($t['id']): ?><input type="checkbox" name="tiers[<?= $i ?>][delete]" value="1"><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small">Nên giữ dòng "Từ 1" với tiền giảm 0 để trang sản phẩm hiện lựa chọn mua lẻ. Phí ship mặc định và ngưỡng freeship theo giá trị đơn chỉnh ở Cài đặt.</p>
  <button class="btn primary" type="submit">Lưu bảng combo</button>
</form>
<?php admin_footer();
