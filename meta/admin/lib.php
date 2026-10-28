<?php
// Clip: lib chung cho Meta Admin. PHP 8, SQLite, khong can MySQL.
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdo = new PDO('sqlite:' . $dir . '/meta.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL, name TEXT NOT NULL,
        password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'employee',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_ad_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL, ad_account_id TEXT NOT NULL,
        ad_account_name TEXT NOT NULL, token_enc TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        UNIQUE(user_id, ad_account_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
        ad_account_id TEXT NOT NULL, campaign_id TEXT NOT NULL,
        campaign_name TEXT NOT NULL, product_code TEXT NOT NULL DEFAULT '',
        PRIMARY KEY(ad_account_id, campaign_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS insights (
        date TEXT NOT NULL, ad_account_id TEXT NOT NULL, campaign_id TEXT NOT NULL,
        campaign_name TEXT NOT NULL, spend REAL NOT NULL DEFAULT 0,
        impressions INTEGER NOT NULL DEFAULT 0, clicks INTEGER NOT NULL DEFAULT 0,
        reach INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(date, ad_account_id, campaign_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL, user_id INTEGER NOT NULL, product_code TEXT NOT NULL,
        orders INTEGER NOT NULL DEFAULT 0, products INTEGER NOT NULL DEFAULT 0,
        revenue REAL NOT NULL DEFAULT 0, return_rate REAL NOT NULL DEFAULT 0,
        cogs REAL NOT NULL DEFAULT 0, shipping REAL NOT NULL DEFAULT 0,
        other_cost REAL NOT NULL DEFAULT 0)");
    return $pdo;
}

function enc_key() {
    $f = __DIR__ . '/data/.key';
    if (file_exists($f)) return trim(file_get_contents($f));
    $k = bin2hex(random_bytes(32));
    file_put_contents($f, $k);
    return $k;
}
function enc_token($plain) {
    $iv = random_bytes(16);
    $c = openssl_encrypt($plain, 'AES-256-CBC', enc_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $c);
}
function dec_token($enc) {
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 17) return '';
    return openssl_decrypt(substr($raw, 16), 'AES-256-CBC', enc_key(), OPENSSL_RAW_DATA, substr($raw, 0, 16)) ?: '';
}

function current_user() {
    if (empty($_SESSION['uid'])) return null;
    $s = db()->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([$_SESSION['uid']]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function require_login() {
    if (!current_user()) { header('Location: ?page=login'); exit; }
}
function is_admin($u) { return $u && $u['role'] === 'admin'; }
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('CSRF khong hop le.');
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n) { return number_format((float)$n, 0, ',', '.'); }
function pct($n) { return $n === null ? '-' : number_format((float)$n * 100, 1, ',', '.') . '%'; }

// Cac ky giong Sheet: hom nay, hom qua, hom kia, tuan nay, tuan truoc, thang nay.
function period_range($p) {
    $today = date('Y-m-d');
    switch ($p) {
        case 'yesterday': $d = date('Y-m-d', strtotime('-1 day')); return [$d, $d];
        case 'day_before': $d = date('Y-m-d', strtotime('-2 days')); return [$d, $d];
        case 'this_week': return [date('Y-m-d', strtotime('monday this week')), $today];
        case 'last_week': return [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))];
        case 'this_month': return [date('Y-m-01'), $today];
        default: return [$today, $today];
    }
}
function period_label($p) {
    [$a, $b] = period_range($p);
    return $a === $b ? date('d/m/Y', strtotime($a)) : date('d/m/Y', strtotime($a)) . ' - ' . date('d/m/Y', strtotime($b));
}

// Tai khoan QC ma user duoc xem: admin xem tat ca, nhan vien chi xem tai khoan da gan.
function visible_accounts($u) {
    $pdo = db();
    if (is_admin($u)) {
        return $pdo->query("SELECT DISTINCT ad_account_id, ad_account_name FROM user_ad_accounts ORDER BY ad_account_name")->fetchAll(PDO::FETCH_ASSOC);
    }
    $s = $pdo->prepare("SELECT ad_account_id, ad_account_name FROM user_ad_accounts WHERE user_id=? ORDER BY ad_account_name");
    $s->execute([$u['id']]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Tinh 12 chi so giong Sheet tu insights + sales trong khoang ngay.
function calc_metrics($start, $end, $account_ids = [], $product_code = '', $user_id = null) {
    $pdo = db();
    $m = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'orders'=>0,'products'=>0,
          'revenue'=>0,'return_weighted'=>0,'cogs'=>0,'shipping'=>0,'other'=>0];
    $acc = '';
    $params = [$start, $end];
    if ($account_ids) { $acc = ' AND ad_account_id IN (' . implode(',', array_fill(0, count($account_ids), '?')) . ')'; $params = array_merge($params, $account_ids); }
    if ($product_code !== '') {
        // Chi phi theo san pham: chi tinh camp da gan ma SP do.
        $acc .= ' AND campaign_id IN (SELECT campaign_id FROM campaigns WHERE product_code=?)';
        $params[] = $product_code;
    }
    $r = $pdo->prepare("SELECT COALESCE(SUM(spend),0) s, COALESCE(SUM(impressions),0) i, COALESCE(SUM(clicks),0) c, COALESCE(SUM(reach),0) r FROM insights WHERE date BETWEEN ? AND ? $acc");
    $r->execute($params);
    $row = $r->fetch(PDO::FETCH_ASSOC);
    $m['spend'] = (float)$row['s']; $m['impressions'] = (int)$row['i']; $m['clicks'] = (int)$row['c']; $m['reach'] = (int)$row['r'];

    $sp = ' WHERE date BETWEEN ? AND ?'; $spp = [$start, $end];
    if ($user_id) { $sp .= ' AND user_id=?'; $spp[] = $user_id; }
    if ($product_code !== '') { $sp .= ' AND product_code=?'; $spp[] = $product_code; }
    $r = $pdo->prepare("SELECT COALESCE(SUM(orders),0) o, COALESCE(SUM(products),0) pr, COALESCE(SUM(revenue),0) rev,
        COALESCE(SUM(revenue*return_rate),0) rw, COALESCE(SUM(cogs),0) cg, COALESCE(SUM(shipping),0) sh, COALESCE(SUM(other_cost),0) ot FROM sales $sp");
    $r->execute($spp);
    $row = $r->fetch(PDO::FETCH_ASSOC);
    $m['orders'] = (int)$row['o']; $m['products'] = (int)$row['pr']; $m['revenue'] = (float)$row['rev'];
    $m['return_rate'] = $m['revenue'] > 0 ? (float)$row['rw'] / $m['revenue'] : null;
    $m['cogs'] = (float)$row['cg']; $m['shipping'] = (float)$row['sh']; $m['other'] = (float)$row['ot'];
    $m['expected_revenue'] = $m['revenue'] * (1 - ($m['return_rate'] ?? 0));
    $m['cost_ratio'] = $m['expected_revenue'] > 0 ? $m['spend'] / $m['expected_revenue'] : null;
    $m['profit'] = $m['expected_revenue'] - $m['spend'] - $m['cogs'] - $m['shipping'] - $m['other'];
    $m['profit_ratio'] = $m['expected_revenue'] > 0 ? $m['profit'] / $m['expected_revenue'] : null;
    $m['ctr'] = $m['impressions'] > 0 ? $m['clicks'] / $m['impressions'] : null;
    $m['cpc'] = $m['clicks'] > 0 ? $m['spend'] / $m['clicks'] : null;
    return $m;
}

// Danh gia AI dang luat (khong goi LLM ben ngoai): dua tren chi phi/doanh thu, loi nhuan, CTR, CPC, xu huong.
function ai_evaluate($cur, $prev, $name) {
    $notes = []; $score = 50;
    if ($cur['spend'] <= 0 && $cur['orders'] <= 0) return ['label'=>'Chua du du lieu','score'=>0,'notes'=>['Chua co chi phi hoac don trong ky nay, chua the danh gia.']];
    if ($cur['cost_ratio'] !== null) {
        if ($cur['cost_ratio'] < 0.5) { $score += 25; $notes[] = 'Chi phi / doanh thu du kien thap (' . pct($cur['cost_ratio']) . '), hieu qua chi phi tot.'; }
        elseif ($cur['cost_ratio'] < 1) { $score += 10; $notes[] = 'Chi phi / doanh thu du kien o muc chap nhan duoc (' . pct($cur['cost_ratio']) . ').'; }
        else { $score -= 25; $notes[] = 'Chi phi vuot doanh thu du kien (' . pct($cur['cost_ratio']) . '), camp dang lo neu khong cai thien don hoac gia von.'; }
    }
    if ($cur['profit'] > 0) { $score += 20; $notes[] = 'Dang co lai ' . money($cur['profit']) . ' trong ky.'; }
    elseif ($cur['profit'] < 0) { $score -= 15; $notes[] = 'Dang lo ' . money(abs($cur['profit'])) . ' trong ky.'; }
    if ($cur['ctr'] !== null) {
        if ($cur['ctr'] >= 0.02) { $score += 5; $notes[] = 'CTR tot (' . pct($cur['ctr']) . '), noi dung / doi tuong dang hut click.'; }
        elseif ($cur['ctr'] < 0.01) { $score -= 5; $notes[] = 'CTR thap (' . pct($cur['ctr']) . '), nen xem lai creative hoac targeting.'; }
    }
    if ($prev && $prev['spend'] > 0) {
        $trend = ($cur['spend'] - $prev['spend']) / $prev['spend'];
        if ($cur['profit'] > $prev['profit']) $notes[] = 'Loi nhuan cai thien so voi ky truoc.';
        elseif ($cur['profit'] < $prev['profit']) $notes[] = 'Loi nhuan giam so voi ky truoc, can kiem tra camp thay doi gi.';
        if ($trend > 0.3) $notes[] = 'Chi phi tang manh so voi ky truoc (' . pct($trend) . ').';
    }
    $score = max(0, min(100, $score));
    $label = $score >= 75 ? 'Tot - co the mo rong' : ($score >= 50 ? 'Tam on - can theo doi' : 'Kem - can toi uu hoac tam dung');
    return ['label'=>$label,'score'=>$score,'notes'=>$notes];
}
