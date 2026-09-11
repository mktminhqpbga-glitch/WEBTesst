<?php
$u = admin_user();
if (is_post()) {
    $cur = row('SELECT password_hash FROM admin_users WHERE id = ?', [$u['id']]);
    $new = (string)post('new');
    if (!password_verify((string)post('current'), $cur['password_hash'])) {
        flash('error', 'Mật khẩu hiện tại không đúng.');
    } elseif (mb_strlen($new) < 8) {
        flash('error', 'Mật khẩu mới cần ít nhất 8 ký tự.');
    } elseif ($new !== (string)post('confirm')) {
        flash('error', 'Hai lần nhập mật khẩu mới không khớp.');
    } else {
        q('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('success', 'Đã đổi mật khẩu.');
    }
    redirect(admin_url('account'));
}
admin_header('Đổi mật khẩu', 'account');
?>
<h1>Đổi mật khẩu</h1>
<form class="card narrow" method="post">
  <?= csrf_field() ?>
  <label>Mật khẩu hiện tại<input type="password" name="current" required autocomplete="current-password"></label>
  <label>Mật khẩu mới (ít nhất 8 ký tự)<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
  <label>Nhập lại mật khẩu mới<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
  <button class="btn primary" type="submit">Đổi mật khẩu</button>
</form>
<?php admin_footer();
