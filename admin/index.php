<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib.php';

header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$r = preg_replace('/[^a-z]/', '', (string)($_GET['r'] ?? 'dashboard')) ?: 'dashboard';

if ($r === 'login') {
    require __DIR__ . '/modules/login.php';
    exit;
}

require_login();

if ($r === 'logout') {
    if (is_post()) {
        csrf_check();
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }
    redirect(admin_url('login'));
}

$allowed = array_merge(array_keys(ADMIN_MENU), ['account', 'upload', 'backup']);
if (!in_array($r, $allowed, true) || !is_file(__DIR__ . "/modules/$r.php")) {
    http_response_code(404);
    admin_header('Không tìm thấy', '');
    echo '<div class="card"><p>Không tìm thấy trang quản trị này.</p></div>';
    admin_footer();
    exit;
}
if (is_post()) csrf_check();

require __DIR__ . "/modules/$r.php";
