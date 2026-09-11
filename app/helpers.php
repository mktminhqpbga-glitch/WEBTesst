<?php
declare(strict_types=1);

class UserError extends RuntimeException {}

const PAYMENT_METHODS = ['cod' => 'COD (thanh toán khi nhận hàng)', 'bank' => 'Chuyển khoản'];
const QUOTE_STATUS = ['new' => 'Mới', 'contacted' => 'Đã liên hệ', 'done' => 'Đã xong'];

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($n): string
{
    return number_format((int)$n, 0, ',', '.') . 'đ';
}

function short_money(int $n): string
{
    return $n >= 1000 && $n % 1000 === 0 ? ($n / 1000) . 'k' : money($n);
}

function base_url(string $p = ''): string
{
    return rtrim((string)cfg('base_url'), '/') . '/' . ltrim($p, '/');
}

/** Link trang cửa hàng. Tự dùng URL đẹp hoặc index.php?_path= tùy config. */
function url(string $path = '', array $qs = []): string
{
    $path = trim($path, '/');
    if (cfg('pretty_urls', true)) {
        $u = base_url($path);
    } else {
        $u = base_url('index.php');
        if ($path !== '') $qs = ['_path' => $path] + $qs;
    }
    return $qs ? $u . '?' . http_build_query($qs) : $u;
}

/** Link đã lưu trong cài đặt: chấp nhận link tuyệt đối hoặc đường dẫn nội bộ */
function link_to(string $v): string
{
    $v = trim($v);
    if ($v === '') return url();
    if (preg_match('~^(https?:)?//|^(tel|mailto):~i', $v)) return $v;
    return url($v);
}

function asset(string $p): string
{
    return base_url('assets/' . ltrim($p, '/')) . '?v=' . ASSET_V;
}

function media(?string $path, string $fallback = 'img/placeholder.svg'): string
{
    return $path ? base_url('uploads/' . ltrim($path, '/')) : asset($fallback);
}

function redirect(string $to): void
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Quay lại trang trước nếu cùng tên miền, ngược lại về trang chủ */
function back_url(): string
{
    $r = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $own = parse_url((string)cfg('base_url'), PHP_URL_HOST);
    return $r !== '' && parse_url($r, PHP_URL_HOST) === $own ? $r : url();
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $k, $default = '')
{
    $v = $_POST[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $k, $default = '')
{
    $v = $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------- Cài đặt ---------- */
function settings(bool $reload = false): array
{
    static $s = null;
    if ($s === null || $reload) {
        $s = [];
        foreach (rows('SELECT k, v FROM settings') as $r) $s[$r['k']] = (string)$r['v'];
    }
    return $s;
}

function setting(string $k, string $default = ''): string
{
    $s = settings();
    return isset($s[$k]) && $s[$k] !== '' ? $s[$k] : $default;
}

function setting_set(string $k, string $v): void
{
    q('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

/** Tách cài đặt nhiều dòng; mỗi dòng "a|b" -> [a, b] */
function setting_lines(string $k, bool $pairs = false): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', setting($k)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $out[] = $pairs ? array_map('trim', array_pad(explode('|', $line, 2), 2, '')) : $line;
    }
    return $out;
}

function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$text)), 'strlen'));
}

/* ---------- Bảo mật form ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

/* ---------- Thông báo ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- Chuỗi ---------- */
function slugify(string $s): string
{
    if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;
    $s = mb_strtolower(trim($s), 'UTF-8');
    static $map = null;
    if ($map === null) {
        $from = mb_str_split('àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ');
        $to = str_split(str_repeat('a', 17) . str_repeat('e', 11) . str_repeat('i', 5) . str_repeat('o', 17) . str_repeat('u', 11) . str_repeat('y', 5) . 'd');
        $map = array_combine($from, $to);
    }
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'muc-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

/** Tạo slug không trùng trong bảng */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $base = $slug;
    $i = 2;
    while (val("SELECT COUNT(*) FROM \"$table\" WHERE slug = ? AND id <> ?", [$slug, $ignoreId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function excerpt(?string $html, int $len = 160): string
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

function normalize_phone(string $p): string
{
    $p = preg_replace('/[^\d+]/', '', $p);
    if (str_starts_with($p, '+84')) $p = '0' . substr($p, 3);
    elseif (str_starts_with($p, '84') && strlen($p) === 11) $p = '0' . substr($p, 2);
    return $p;
}

function valid_phone(string $p): bool
{
    return (bool)preg_match('/^0\d{9}$/', $p);
}

/** Lọc HTML do admin nhập: bỏ script, sự kiện on*, javascript: */
function clean_html(?string $html): string
{
    $html = (string)$html;
    $html = preg_replace('#<(script|style|object|embed)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|object|embed)\b[^>]*/?>#is', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2#i', '$1="#"', $html);
    return trim((string)$html);
}

function youtube_id(?string $u): ?string
{
    if (!$u) return null;
    return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([\w-]{11})~', $u, $m) ? $m[1] : null;
}

function fmt_date(?string $dt, string $f = 'd/m/Y H:i'): string
{
    return $dt ? date($f, strtotime($dt)) : '';
}

function paginate(int $total, int $per, int $page): array
{
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per, 'per' => $per, 'total' => $total];
}

/* ---------- Upload ảnh ---------- */
function handle_upload(?array $f, string $dir = 'misc'): ?string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new UserError($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Ảnh vượt quá dung lượng hosting cho phép.' : 'Tải ảnh lỗi (mã ' . $f['error'] . ').');
    }
    $maxMb = (int)cfg('upload_max_mb', 5);
    if ($f['size'] > $maxMb * 1024 * 1024) throw new UserError("Ảnh vượt quá {$maxMb}MB.");
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
    if (!$ext) throw new UserError('Chỉ nhận ảnh JPG, PNG, WEBP hoặc GIF.');
    $sub = trim($dir, '/') . '/' . date('Y/m');
    $abs = APP_ROOT . '/uploads/' . $sub;
    if (!is_dir($abs) && !mkdir($abs, 0755, true)) throw new UserError('Không tạo được thư mục uploads. Kiểm tra quyền ghi (chmod 755).');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$abs/$name")) throw new UserError('Không lưu được ảnh lên máy chủ.');
    return "$sub/$name";
}

/** Chuyển $_FILES['x'] dạng mảng nhiều file thành danh sách */
function files_list(?array $f): array
{
    if (!$f || !is_array($f['name'] ?? null)) return $f ? [$f] : [];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function delete_upload(?string $path): void
{
    if (!$path) return;
    $root = realpath(APP_ROOT . '/uploads');
    $abs = realpath(APP_ROOT . '/uploads/' . $path);
    if ($root && $abs && str_starts_with($abs, $root . DIRECTORY_SEPARATOR) && is_file($abs)) @unlink($abs);
}

/* ---------- Render giao diện ---------- */
function view(string $__view, array $__data = [], ?string $__layout = 'layout'): void
{
    extract($__data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . "/views/$__view.php";
    $content = ob_get_clean();
    if ($__layout) {
        require APP_ROOT . "/views/$__layout.php";
    } else {
        echo $content;
    }
}

function partial(string $__partial, array $__data = []): void
{
    extract($__data, EXTR_SKIP);
    require APP_ROOT . "/views/partials/$__partial.php";
}

function not_found(): void
{
    http_response_code(404);
    view('404', ['title' => 'Không tìm thấy trang']);
    exit;
}
