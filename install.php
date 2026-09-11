<?php
/**
 * Trình cài đặt 1 lần. Sau khi tạo xong tài khoản quản trị, XÓA FILE NÀY khỏi hosting.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$err = '';

function page(string $title, string $body): void
{
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . htmlspecialchars($title) . '</title>
<style>body{font:15px/1.6 system-ui,sans-serif;background:#F4F2EC;color:#23261A;margin:0;padding:30px 16px}.box{max-width:560px;margin:0 auto;background:#fff;border:1px solid #E2DDD0;border-radius:12px;padding:24px}
h1{font-size:20px;margin-top:0}label{display:grid;gap:4px;margin-bottom:12px;font-size:13px;font-weight:600}input{font:inherit;padding:9px;border:1px solid #ccc;border-radius:8px}
button{background:#3F4722;color:#fff;border:0;border-radius:8px;padding:10px 18px;font:600 14px sans-serif;cursor:pointer}.ok{color:#2F6B12}.bad{color:#A3361E}code{background:#EEEBE2;padding:1px 5px;border-radius:4px}li{margin-bottom:4px}</style></head><body><div class="box">'
        . $body . '</div></body></html>';
    exit;
}

$CONFIG = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbPath = (string)($CONFIG['db_path'] ?? __DIR__ . '/data/zungza.sqlite');
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) @mkdir($dbDir, 0755, true);

$checks = [
    'PHP 8.0 trở lên (đang dùng ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'Extension pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'Extension mbstring' => extension_loaded('mbstring'),
    'Có file config.php' => is_file(__DIR__ . '/config.php'),
    'Thư mục dữ liệu ghi được (' . $dbDir . ')' => is_writable($dbDir),
    'Thư mục uploads ghi được' => is_writable(__DIR__ . '/uploads'),
];
if (in_array(false, $checks, true)) {
    $h = '<h1>Kiểm tra máy chủ</h1><ul>';
    foreach ($checks as $k => $ok) $h .= '<li class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? '✓' : '✗') . ' ' . htmlspecialchars($k) . '</li>';
    $h .= '</ul><p>Sửa các mục ✗ rồi tải lại trang. Thư mục chưa ghi được: chỉnh quyền 755 (hoặc 775) trong File Manager.</p>';
    page('Cài đặt', $h);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    page('Cài đặt', '<h1>Không tạo được file dữ liệu</h1><p class="bad">' . htmlspecialchars($e->getMessage()) . '</p>');
}

$hasTables = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'")->fetchColumn();
if ($hasTables && (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) {
    page('Đã cài đặt', '<h1>Website đã được cài đặt</h1><p>Đã có tài khoản quản trị. <b class="bad">Hãy xóa file install.php khỏi hosting ngay.</b></p><p><a href="admin/">Vào trang quản trị</a></p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Nhập tên và email hợp lệ.';
    elseif (mb_strlen($pass) < 8) $err = 'Mật khẩu cần ít nhất 8 ký tự.';
    elseif ($pass !== ($_POST['password2'] ?? '')) $err = 'Hai lần nhập mật khẩu không khớp.';
    else {
        try {
            if (!$hasTables) $pdo->exec((string)file_get_contents(__DIR__ . '/database.sql'));
            date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Ho_Chi_Minh');
            $st = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
            $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            $st = $pdo->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES ('mail_to', ?)");
            $st->execute([$email]);
            @chmod($dbPath, 0640);
            page('Hoàn tất', '<h1 class="ok">Cài đặt thành công</h1><ol><li><b class="bad">Xóa file install.php khỏi hosting.</b></li><li>Vào <a href="admin/">trang quản trị</a>, đăng nhập bằng email vừa tạo.</li><li>Mở <b>Email nhận đơn</b> để cài SMTP và gửi email thử.</li><li>Mở <b>Thanh toán & QR</b> để điền số tài khoản.</li></ol>');
        } catch (Throwable $e) {
            $err = 'Lỗi: ' . $e->getMessage();
        }
    }
}

$h = '<h1>Cài đặt website</h1>';
$h .= $hasTables ? '<p>Dữ liệu đã có. Chỉ cần tạo tài khoản quản trị.</p>' : '<p>Bước này tạo file dữ liệu, dữ liệu mẫu và tài khoản quản trị. Email của bạn cũng được đặt làm email nhận đơn (đổi được sau).</p>';
if ($err) $h .= '<p class="bad">' . htmlspecialchars($err) . '</p>';
$h .= '<form method="post"><label>Tên của bạn<input name="name" required value="' . htmlspecialchars((string)($_POST['name'] ?? '')) . '"></label>
<label>Email đăng nhập<input type="email" name="email" required value="' . htmlspecialchars((string)($_POST['email'] ?? '')) . '"></label>
<label>Mật khẩu (ít nhất 8 ký tự)<input type="password" name="password" required minlength="8"></label>
<label>Nhập lại mật khẩu<input type="password" name="password2" required minlength="8"></label>
<button type="submit">Cài đặt</button></form>';
page('Cài đặt', $h);
