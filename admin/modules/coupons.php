<?php
if (is_post()) {
    $cid = (int)post('id', '0');
    if (post('act') === 'delete') { q('DELETE FROM coupons WHERE id = ?', [$cid]); flash('success', 'Đã xóa mã.'); redirect(admin_url('coupons')); }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', post('code')));
    if ($code === '') { flash('error', 'Nhập mã giảm giá (chữ, số, gạch).'); redirect(admin_url('coupons')); }
    $type = post('type') === 'percent' ? 'percent' : 'fixed';
    $value = nullable_int(post('value')) ?? 0;
    if ($type === 'percent' && $value > 100) $value = 100;
    $d = [$code, $type, $value, nullable_int(post('min_order')) ?? 0, nullable_int(post('max_uses')), dt_from_local(post('starts_at')), dt_from_local(post('ends_at')), post('active') ? 1 : 0];
    try {
        if ($cid) q('UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, starts_at=?, ends_at=?, active=? WHERE id=?', [...$d, $cid]);
        else q('INSERT INTO coupons (code, type, value, min_order, max_uses, starts_at, ends_at, active, created_at) VALUES (?,?,?,?,?,?,?,?,?)', [...$d, now()]);
        flash('success', 'Đã lưu mã ' . $code . '.');
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) throw $e;
        flash('error', 'Mã ' . $code . ' đã tồn tại.');
    }
    redirect(admin_url('coupons'));
}
$edit = (int)get('id', '0') ? row('SELECT * FROM coupons WHERE id = ?', [(int)get('id')]) : null;
$c = $edit ?: ['id' => 0, 'code' => '', 'type' => 'fixed', 'value' => '', 'min_order' => 0, 'max_uses' => null, 'used_count' => 0, 'starts_at' => null, 'ends_at' => null, 'active' => 1];
$list = rows('SELECT * FROM coupons ORDER BY created_at DESC');
admin_header('Mã giảm giá', 'coupons');
?>
<h1>Mã giảm giá</h1>
<div class="two">
  <div class="card"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Mã</th><th>Giảm</th><th>Đơn tối thiểu</th><th>Đã dùng</th><th>Thời hạn</th><th>Bật</th><th></th></tr></thead>
    <tbody><?php foreach ($list as $r): ?>
      <tr><td class="strong"><?= e($r['code']) ?></td><td><?= $r['type'] === 'percent' ? (int)$r['value'] . '%' : money($r['value']) ?></td><td><?= money($r['min_order']) ?></td>
        <td><?= (int)$r['used_count'] ?><?= $r['max_uses'] !== null ? ' / ' . (int)$r['max_uses'] : '' ?></td>
        <td class="small"><?= fmt_date($r['starts_at'], 'd/m/Y') ?: '...' ?> - <?= fmt_date($r['ends_at'], 'd/m/Y') ?: '...' ?></td><td><?= $r['active'] ? 'Có' : 'Tắt' ?></td>
        <td class="nowrap"><a href="<?= admin_url('coupons', ['id' => $r['id']]) ?>">Sửa</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Xóa mã?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn danger-t">Xóa</button></form></td></tr>
    <?php endforeach; ?><?php if (!$list): ?><tr><td colspan="7" class="muted">Chưa có mã giảm giá.</td></tr><?php endif; ?></tbody>
  </table></div></div>
  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <h2><?= $c['id'] ? 'Sửa mã' : 'Tạo mã' ?></h2>
    <label>Mã *<input name="code" value="<?= e($c['code']) ?>" required placeholder="TRUNGTHU50"></label>
    <div class="grid2"><label>Kiểu<?= sel('type', ['fixed' => 'Số tiền (đ)', 'percent' => 'Phần trăm (%)'], $c['type']) ?></label>
      <label>Giá trị<input name="value" value="<?= e($c['value']) ?>" inputmode="numeric" required></label></div>
    <div class="grid2"><label>Đơn tối thiểu (đ)<input name="min_order" value="<?= (int)$c['min_order'] ?>" inputmode="numeric"></label>
      <label>Tổng lượt dùng (trống = không giới hạn)<input name="max_uses" value="<?= e($c['max_uses']) ?>" inputmode="numeric"></label></div>
    <div class="grid2"><label>Bắt đầu<input type="datetime-local" name="starts_at" value="<?= dt_local($c['starts_at']) ?>"></label>
      <label>Kết thúc<input type="datetime-local" name="ends_at" value="<?= dt_local($c['ends_at']) ?>"></label></div>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= checked($c['active']) ?>> Đang bật</label>
    <p class="muted small">Mã giảm tính trên số tiền sau ưu đãi combo.</p>
    <button class="btn primary" type="submit">Lưu mã</button>
  </form>
</div>
<?php admin_footer();
