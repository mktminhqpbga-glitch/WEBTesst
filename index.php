<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_ROOT . '/app/front.php';

capture_source();

// Xác định đường dẫn: từ .htaccess (?_path=), hoặc từ REQUEST_URI (nginx / không rewrite)
if (isset($_GET['_path'])) {
    $path = (string)$_GET['_path'];
} else {
    $uri = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    $basePath = rtrim((string)parse_url((string)cfg('base_url'), PHP_URL_PATH), '/');
    if ($basePath !== '' && str_starts_with($uri, $basePath)) $uri = substr($uri, strlen($basePath));
    $path = preg_replace('~^/?index\.php~', '', $uri);
}
$path = trim($path, '/');
$seg = $path === '' ? [] : explode('/', $path);
$a = $seg[0] ?? '';
$b = $seg[1] ?? '';

switch (true) {
    case $a === '':
        page_home();
        break;
    case $a === 'danh-muc' && $b !== '':
        page_category($b);
        break;
    case $a === 'san-pham' && $b !== '':
        page_product($b);
        break;
    case $a === 'tim-kiem':
        page_search();
        break;
    case $a === 'gio-hang' && $b === '':
        page_cart();
        break;
    case $a === 'gio-hang' && is_post():
        cart_action($b);
        break;
    case $a === 'thanh-toan':
        page_checkout();
        break;
    case $a === 'dat-hang-thanh-cong' && $b !== '':
        page_thanks($b);
        break;
    case $a === 'chuyen-khoan' && $b !== '':
        page_transfer($b);
        break;
    case $a === 'blog':
        $b === '' ? page_blog() : page_post($b);
        break;
    case $a === 'qua-doanh-nghiep':
        page_b2b();
        break;
    case $a === 'trang' && $b !== '':
        page_static($b);
        break;
    case $a === 'sitemap.xml':
        page_sitemap();
        break;
    case $a === 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\nDisallow: /gio-hang\nDisallow: /thanh-toan\nDisallow: /chuyen-khoan\nDisallow: /dat-hang-thanh-cong\nSitemap: " . url('sitemap.xml') . "\n";
        break;
    default:
        not_found();
}
