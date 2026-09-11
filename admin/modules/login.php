<?php
if (admin_user()) redirect(admin_url());
$error = '';
$email = '';
if (is_post()) {
    csrf_check();
    $email = mb_strtolower(post('email'));
    if (too_many_attempts()) {
        $error = 'Đăng nhập sai quá nhiều lần. Thử lại sau 15 phút.';
    } else {
        $u = row('SELECT * FROM admin_users WHERE email = ? AND active = 1', [$email]);
        if ($u && password_verify((string)post('password'), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$u['id'];
            q('UPDATE admin_users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash((string)post('password'), PASSWORD_DEFAULT), $u['id']]);
            }
            redirect(admin_url());
        }
        q('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, ?)', [client_ip(), $email, now()]);
        $error = 'Email hoặc mật khẩu không đúng.';
    }
}
admin_header('Đăng nhập', '');
?>
<div class="login">
  <form class="card" method="post" action="<?= admin_url('login') ?>">
    <h1><?= e(setting('site_name', 'Zungza')) ?> | Quản trị</h1>
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
    <label>Mật khẩu<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn primary block" type="submit">Đăng nhập</button>
  </form>
</div>
<?php admin_footer();
