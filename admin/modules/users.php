<?php
$me = admin_user();
if (is_post()) {
    $uid = (int)post('id', '0');
    $email = mb_strtolower(post('email'));
    $pass = (string)post('password');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('name') === '') { flash('error', 'Nhập tên và email hợp lệ.'); redirect(admin_url('users')); }
    if (!$uid && mb_strlen($pass) < 8) { flash('error', 'Mật khẩu cần ít nhất 8 ký tự.'); redirect(admin_url('users')); }
    if ($pass !== '' && mb_strlen($pass) < 8) { flash('error', 'Mật khẩu cần ít nhất 8 ký tự.'); redirect(admin_url('users', ['id' => $uid])); }
    $active = post('active') ? 1 : 0;
    if ($uid === (int)$me['id']) $active = 1; // không tự khóa tài khoản đang dùng
    try {
        if ($uid) {
            q('UPDATE admin_users SET name = ?, email = ?, active = ? WHERE id = ?', [mb_substr(post('name'), 0, 120), $email, $active, $uid]);
            if ($pass !== '') q('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $uid]);
        } else {
            q('INSERT INTO admin_users (name, email, password_hash, active, created_at) VALUES (?,?,?,?,?)', [mb_substr(post('name'), 0, 120), $email, password_hash($pass, PASSWORD_DEFAULT), $active, now()]);
        }
        flash('success', 'Đã lưu tài khoản.');
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) throw $e;
        flash('error', 'Email này đã có tài khoản.');
    }
    redirect(admin_url('users'));
}
$edit = (int)get('id', '0') ? row('SELECT * FROM admin_users WHERE id = ?', [(int)get('id')]) : null;
$u = $edit ?: ['id' => 0, 'name' => '', 'email' => '', 'active' => 1];
$list = rows('SELECT * FROM admin_users ORDER BY id');
admin_header('Tài khoản quản trị', 'users');
?>
<h1>Tài khoản quản trị</h1>
<p class="muted">Mọi tài khoản đều có toàn quyền quản trị.</p>
<div class="two">
  <div class="card"><table class="tbl"><thead><tr><th>Tên</th><th>Email</th><th>Đăng nhập gần nhất</th><th>Trạng thái</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $x): ?><tr><td class="strong"><?= e($x['name']) ?></td><td><?= e($x['email']) ?></td>
    <td class="small"><?= fmt_date($x['last_login_at']) ?: '-' ?></td><td><?= $x['active'] ? 'Hoạt động' : 'Đã khóa' ?></td><td><a href="<?= admin_url('users', ['id' => $x['id']]) ?>">Sửa</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    <h2><?= $u['id'] ? 'Sửa tài khoản' : 'Thêm tài khoản' ?></h2>
    <label>Tên *<input name="name" value="<?= e($u['name']) ?>" required></label>
    <label>Email đăng nhập *<input type="email" name="email" value="<?= e($u['email']) ?>" required></label>
    <label>Mật khẩu <?= $u['id'] ? '(để trống nếu không đổi)' : '*' ?><input type="password" name="password" minlength="8" autocomplete="new-password" <?= $u['id'] ? '' : 'required' ?>></label>
    <label class="inline"><input type="checkbox" name="active" value="1" <?= checked($u['active']) ?>> Cho phép đăng nhập</label>
    <button class="btn primary" type="submit">Lưu</button>
  </form>
</div>
<?php admin_footer();
