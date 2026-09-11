<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('ASSET_V', '1.0.0');

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(503);
    exit('Chưa có file config.php. Copy config.sample.php thành config.php và điền thông tin database (xem README.md).');
}
$GLOBALS['CONFIG'] = require APP_ROOT . '/config.php';

date_default_timezone_set($GLOBALS['CONFIG']['timezone'] ?? 'Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['CONFIG']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('zzsid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/shop.php';
require APP_ROOT . '/app/mailer.php';

set_exception_handler(function (Throwable $e) {
    error_log('[zungza] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (cfg('debug')) {
        echo '<pre>' . e((string)$e) . '</pre>';
    } else {
        echo 'Hệ thống đang gặp lỗi. Vui lòng thử lại sau ít phút.';
    }
});
