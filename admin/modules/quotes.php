<?php
if (is_post()) {
    $qid = (int)post('id', '0');
    if (post('act') === 'delete') q('DELETE FROM quote_requests WHERE id = ?', [$qid]);
    elseif (isset(QUOTE_STATUS[post('status')])) q('UPDATE quote_requests SET status = ? WHERE id = ?', [post('status'), $qid]);
    flash('success', 'Đã cập nhật.');
    redirect(admin_url('quotes', array_filter(['status' => get('status')])));
}
$st = get('status');
$list = isset(QUOTE_STATUS[$st]) ? rows('SELECT * FROM quote_requests WHERE status = ? ORDER BY created_at DESC LIMIT 300', [$st]) : rows('SELECT * FROM quote_requests ORDER BY created_at DESC LIMIT 300');
admin_header('Yêu cầu báo giá', 'quotes');
?>
<h1>Yêu cầu báo giá doanh nghiệp</h1>
<p class="muted">Mỗi yêu cầu cũng được gửi về email nhận đơn. Trang này để đánh dấu đã liên hệ.</p>
<div class="tabs"><a href="<?= admin_url('quotes') ?>" class="<?= $st === '' ? 'on' : '' ?>">Tất cả</a><?php foreach (QUOTE_STATUS as $k => $l): ?><a href="<?= admin_url('quotes', ['status' => $k]) ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ngày</th><th>Công ty / Liên hệ</th><th class="r">SL</th><th>Ngân sách</th><th>Cần ngày</th><th>Yêu cầu</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach ($list as $x): ?>
  <tr><td class="small nowrap"><?= fmt_date($x['created_at']) ?></td>
    <td><b><?= e($x['company']) ?></b><br><?= e($x['contact_name']) ?> - <a href="tel:<?= e($x['phone']) ?>"><?= e($x['phone']) ?></a><?= $x['email'] ? '<br>' . e($x['email']) : '' ?></td>
    <td class="r"><?= (int)$x['quantity'] ?></td><td><?= e($x['budget']) ?></td><td class="small"><?= fmt_date($x['need_date'], 'd/m/Y') ?></td><td class="small"><?= nl2br(e($x['note'])) ?></td>
    <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><?= sel('status', QUOTE_STATUS, $x['status'], 'onchange="this.form.submit()"') ?></form>
      <form method="post" class="inline-form" onsubmit="return confirm('Xóa yêu cầu?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="linkbtn danger-t">Xóa</button></form></td></tr>
<?php endforeach; ?><?php if (!$list): ?><tr><td colspan="7" class="muted">Chưa có yêu cầu.</td></tr><?php endif; ?></tbody></table></div></div>
<?php admin_footer();
