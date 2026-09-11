<?php
declare(strict_types=1);

const ADMIN_MENU = [
    // route => nhãn
    'dashboard'  => 'Tổng quan',
    'products'   => 'Sản phẩm',
    'categories' => 'Danh mục',
    'combo'      => 'Combo & quà',
    'coupons'    => 'Mã giảm giá',
    'posts'      => 'Bài viết blog',
    'blogcats'   => 'Chuyên mục blog',
    'pages'      => 'Trang nội dung',
    'reviews'    => 'Đánh giá',
    'quotes'     => 'Báo giá DN',
    'email'      => 'Email nhận đơn',
    'payment'    => 'Thanh toán & QR',
    'settings'   => 'Cài đặt website',
    'users'      => 'Tài khoản',
];

function admin_url(string $r = 'dashboard', array $params = []): string
{
    return base_url('admin/index.php') . '?' . http_build_query(['r' => $r] + $params);
}

function admin_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = !empty($_SESSION['admin_id'])
            ? row('SELECT id, name, email, active FROM admin_users WHERE id = ? AND active = 1', [$_SESSION['admin_id']])
            : null;
    }
    return $u;
}

function require_login(): void
{
    if (!admin_user()) {
        unset($_SESSION['admin_id']);
        redirect(admin_url('login'));
    }
}

function too_many_attempts(): bool
{
    q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    return (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), date('Y-m-d H:i:s', time() - 900)]) >= 5;
}

function sel(string $name, array $opts, $cur, string $attrs = ''): string
{
    $h = '<select name="' . e($name) . '" ' . $attrs . '>';
    foreach ($opts as $v => $l) $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$cur ? ' selected' : '') . '>' . e($l) . '</option>';
    return $h . '</select>';
}

function checked($v): string
{
    return $v ? 'checked' : '';
}

function nullable_int($v): ?int
{
    $v = is_string($v) ? str_replace(['.', ',', ' '], '', trim($v)) : $v;
    return ($v === '' || $v === null) ? null : max(0, (int)$v);
}

function dt_local(?string $dt): string
{
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}

function dt_from_local(string $v): ?string
{
    $v = trim($v);
    if ($v === '') return null;
    $t = strtotime($v);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

function pager_html(array $pg, array $params): string
{
    if ($pg['pages'] <= 1) return '';
    $h = '<nav class="pager">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($pg['pages'] > 12 && abs($i - $pg['page']) > 3 && $i !== 1 && $i !== $pg['pages']) {
            if ($i === 2 || $i === $pg['pages'] - 1) $h .= '<span>…</span>';
            continue;
        }
        $h .= '<a class="' . ($i === $pg['page'] ? 'on' : '') . '" href="' . e(admin_url($params['r'], array_merge($params, ['page' => $i]))) . '">' . $i . '</a>';
    }
    return $h . '</nav>';
}

function admin_header(string $title, string $active, bool $editor = false): void
{
    $u = admin_user();
    $mailFailed = $u ? (int)val("SELECT COUNT(*) FROM orders WHERE email_status = 'failed'") : 0;
    $newQuotes = $u ? (int)val("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'") : 0;
    ?><!DOCTYPE html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | Quản trị <?= e(setting('site_name', 'Zungza')) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if ($editor): ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css"><?php endif; ?>
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="upload-url" content="<?= e(admin_url('upload')) ?>">
</head><body>
<?php if ($u): ?>
<input type="checkbox" id="navtoggle" hidden>
<aside class="side">
  <a class="brand" href="<?= admin_url() ?>"><img src="<?= asset('img/favicon.svg') ?>" alt="" width="24"><?= e(setting('site_name', 'Zungza')) ?></a>
  <nav>
    <?php foreach (ADMIN_MENU as $r => $label): ?>
      <a href="<?= admin_url($r) ?>" class="<?= $active === $r ? 'on' : '' ?>"><?= e($label) ?>
        <?php if ($r === 'dashboard' && $mailFailed): ?><span class="pill" title="Đơn gửi email lỗi"><?= $mailFailed ?></span><?php endif; ?>
        <?php if ($r === 'quotes' && $newQuotes): ?><span class="pill"><?= $newQuotes ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= e(url()) ?>" target="_blank" rel="noopener">Xem website</a>
    <a href="<?= admin_url('account') ?>">Đổi mật khẩu</a>
    <form method="post" action="<?= admin_url('logout') ?>"><?= csrf_field() ?><button type="submit">Đăng xuất (<?= e($u['name']) ?>)</button></form>
  </div>
</aside>
<header class="mob"><label for="navtoggle" aria-label="Mở menu">☰</label><b><?= e($title) ?></b></header>
<?php endif; ?>
<main class="main">
<?php if ($u && $active !== 'email' && !mail_recipients()): ?><div class="flash error">Chưa cài email nhận đơn. Đơn mới sẽ không gửi về đâu cả. <a href="<?= admin_url('email') ?>">Cài ngay</a></div><?php endif; ?>
<?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php
}

function admin_footer(bool $editor = false): void
{
    if ($editor): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<?php endif; ?>
<script src="<?= asset('js/admin.js') ?>"></script>
</main></body></html>
<?php
}
