<?php
require __DIR__ . '/lib.php';
$page = $_GET['page'] ?? 'tong-hop';
$u = current_user();

// ---------- Dang xuat ----------
if ($page === 'logout') { session_destroy(); header('Location: ?page=login'); exit; }

// ---------- Dang nhap ----------
if ($page === 'login') {
    if ($u) { header('Location: ?page=tong-hop'); exit; }
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $s = db()->prepare("SELECT * FROM users WHERE username=?");
        $s->execute([$_POST['username'] ?? '']);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            $_SESSION['uid'] = $row['id']; header('Location: ?page=tong-hop'); exit;
        }
        $err = 'Sai tai khoan hoac mat khau.';
    }
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meta Admin - Dang nhap</title><style>body{font-family:system-ui,sans-serif;max-width:420px;margin:60px auto;padding:0 16px}input,button{width:100%;padding:10px;margin:6px 0;box-sizing:border-box}button{background:#1877f2;color:#fff;border:0;border-radius:8px;cursor:pointer}.err{color:#c00}</style></head><body>';
    echo '<h1>Meta Admin</h1><p>Bao cao quang cao theo ngay, giong bang Sheet BC_MKT. Dang nhap bang tai khoan nhan vien duoc admin tao.</p>';
    if ($err) echo '<p class="err">' . h($err) . '</p>';
    echo '<form method="post"><input name="username" placeholder="Ten dang nhap" required><input type="password" name="password" placeholder="Mat khau" required><button>Dang nhap</button></form>';
    echo '<p style="color:#666">Chua co tai khoan? Admin chay install.php lan dau de tao tai khoan admin, roi vao Nguoi dung de tao tai khoan nhan vien.</p></body></html>';
    exit;
}
require_login();
$u = current_user();
$pdo = db();

// ---------- Xu ly form ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add_user' && is_admin($u)) {
        $pdo->prepare("INSERT INTO users (username,name,password_hash,role) VALUES (?,?,?,?)")
            ->execute([$_POST['username'], $_POST['name'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role'] ?? 'employee']);
    } elseif ($act === 'add_account') {
        $pdo->prepare("INSERT INTO user_ad_accounts (user_id,ad_account_id,ad_account_name,token_enc) VALUES (?,?,?,?)
            ON CONFLICT(user_id,ad_account_id) DO UPDATE SET ad_account_name=excluded.ad_account_name, token_enc=excluded.token_enc")
            ->execute([$u['id'], preg_replace('/[^0-9]/','',$_POST['ad_account_id']), $_POST['ad_account_name'], enc_token($_POST['token'])]);
    } elseif ($act === 'del_account') {
        $pdo->prepare("DELETE FROM user_ad_accounts WHERE id=? AND user_id=?")->execute([$_POST['id'], $u['id']]);
    } elseif ($act === 'map_product') {
        $pdo->prepare("UPDATE campaigns SET product_code=? WHERE ad_account_id=? AND campaign_id=?")
            ->execute([$_POST['product_code'], $_POST['ad_account_id'], $_POST['campaign_id']]);
    } elseif ($act === 'add_sale') {
        $pdo->prepare("INSERT INTO sales (date,user_id,product_code,orders,products,revenue,return_rate,cogs,shipping,other_cost) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$_POST['date'], $u['id'], $_POST['product_code'], (int)$_POST['orders'], (int)$_POST['products'], (float)$_POST['revenue'], (float)$_POST['return_rate']/100, (float)$_POST['cogs'], (float)$_POST['shipping'], (float)$_POST['other_cost']]);
    }
    header('Location: ?page=' . urlencode($_POST['back'] ?? 'tong-hop')); exit;
}

// ---------- Xuat CSV cho Sheet ----------
if ($page === 'xuat') {
    $period = $_GET['period'] ?? 'this_month';
    [$start, $end] = period_range($period);
    $accs = array_column(visible_accounts($u), 'ad_account_id');
    $m = calc_metrics($start, $end, $accs, '', is_admin($u) ? null : $u['id']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bao-cao-meta-' . $start . '_' . $end . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Chi tieu', 'Gia tri', 'Ky', period_label($period)]);
    $rows = [['Chi phi Marketing',$m['spend']],['Don',$m['orders']],['San pham',$m['products']],['Doanh so',$m['revenue']],
        ['Ty le hoan du kien', $m['return_rate'] !== null ? $m['return_rate']*100 . '%' : ''],
        ['Doanh thu du kien',$m['expected_revenue']],['Chi phi/Doanh thu du kien', $m['cost_ratio'] !== null ? $m['cost_ratio']*100 . '%' : ''],
        ['Chi phi gia von',$m['cogs']],['Chi phi van chuyen',$m['shipping']],['Chi phi khac',$m['other']],
        ['Loi nhuan',$m['profit']],['Loi nhuan/DT', $m['profit_ratio'] !== null ? $m['profit_ratio']*100 . '%' : ''],
        ['Hien thi',$m['impressions']],['Click',$m['clicks']],['CTR', $m['ctr'] !== null ? $m['ctr']*100 . '%' : ''],['CPC',$m['cpc']]];
    foreach ($rows as $r) fputcsv($out, $r);
    exit;
}

// ---------- Giao dien ----------
$period = $_GET['period'] ?? 'today';
if (!in_array($period, ['today','yesterday','day_before','this_week','last_week','this_month'])) $period = 'today';
[$start, $end] = period_range($period);
$my_accounts = visible_accounts($u);
$acc_ids = array_column($my_accounts, 'ad_account_id');
$sales_uid = is_admin($u) ? null : $u['id'];
$periods = ['today'=>'Hom nay','yesterday'=>'Hom qua','day_before'=>'Hom kia','this_week'=>'Tuan nay','last_week'=>'Tuan truoc','this_month'=>'Thang nay'];
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Meta Admin - Bao cao quang cao</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f5f7;color:#222}
header{background:#111;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}
header a{color:#fff;text-decoration:none;margin-right:14px}
main{max-width:1100px;margin:0 auto;padding:16px}
.card{background:#fff;border-radius:12px;padding:16px;margin:12px 0;box-shadow:0 1px 3px rgba(0,0,0,.1)}
table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}th{background:#f0f2f5}
.neg{color:#c00}.pos{color:#0a7a2e}
input,select,button{padding:8px;margin:4px 2px}button{background:#1877f2;color:#fff;border:0;border-radius:8px;cursor:pointer}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;background:#e7f3ff}
.warn{background:#fff4e5}
</style></head><body>
<header><div><b>Meta Admin</b> <span class="badge"><?=h($u['name'])?> (<?=h($u['role'])?>)</span></div>
<nav>
<a href="?page=tong-hop">Tong hop</a><a href="?page=camp">Camp</a><a href="?page=ngay">Theo ngay</a><a href="?page=san-pham">San pham</a><a href="?page=ai">Danh gia AI</a><a href="?page=doanh-so">Doanh so</a><a href="?page=tai-khoan">Tai khoan QC</a><a href="?page=dong-bo">Dong bo Meta</a><a href="?page=xuat&period=<?=$period?>">Xuat Sheet (CSV)</a><?php if (is_admin($u)) echo '<a href="?page=nguoi-dung">Nguoi dung</a>'; ?><a href="?page=logout">Thoat</a>
</nav></header>
<main>
<form method="get"><input type="hidden" name="page" value="<?=h($page)?>">
<label>Ky: <select name="period" onchange="this.form.submit()"><?php foreach ($periods as $k=>$v) echo '<option value="'.$k.'"'.($k===$period?' selected':'').'>'.$v.'</option>'; ?></select></label>
<span><?=h(period_label($period))?></span></form>

<?php if (!empty($_SESSION['sync_msg'])) { echo '<div class="card">'.h($_SESSION['sync_msg']).'</div>'; unset($_SESSION['sync_msg']); } ?>

<?php if ($page === 'tong-hop'): ?>
<?php $m = calc_metrics($start, $end, $acc_ids, '', $sales_uid); ?>
<div class="card"><h2>Tong hop theo ky: <?=h($periods[$period])?> (<?=h(period_label($period))?>)</h2>
<p>Tai khoan dang xem: <?= $acc_ids ? h(implode(', ', $acc_ids)) : 'Chua gan tai khoan QC nao - vao Tai khoan QC de them.' ?></p>
<table>
<tr><th>#</th><th>Chi so</th><th>Gia tri</th></tr>
<?php $rows = [['Chi phi Marketing', money($m['spend'])],['Don', $m['orders']],['San pham', $m['products']],['Doanh so', money($m['revenue'])],['Ty le hoan du kien', pct($m['return_rate'])],['Doanh thu du kien', money($m['expected_revenue'])],['Chi phi/Doanh thu du kien', pct($m['cost_ratio'])],['Chi phi gia von', money($m['cogs'])],['Chi phi van chuyen', money($m['shipping'])],['Chi phi khac', money($m['other'])],['Loi nhuan', money($m['profit'])],['Loi nhuan/DT', pct($m['profit_ratio'])],['Hien thi', money($m['impressions'])],['Click', money($m['clicks'])],['CTR', pct($m['ctr'])],['CPC', $m['cpc'] ? money($m['cpc']) : '-']];
foreach ($rows as $i=>$r) echo '<tr><td>'.($i+1).'</td><td>'.h($r[0]).'</td><td class="'.($r[0]==='Loi nhuan' && $m['profit']<0 ? 'neg' : ($r[0]==='Loi nhuan' && $m['profit']>0 ? 'pos' : '')).'">'.h($r[1]).'</td></tr>'; ?>
</table></div>
<div class="card"><h3>Tong hop nhanh cac ky (giong Sheet)</h3><table><tr><th>Ky</th><th>Chi phi</th><th>Don</th><th>Doanh so</th><th>Loi nhuan</th></tr>
<?php foreach ($periods as $k=>$v) { [$a,$b] = period_range($k); $mm = calc_metrics($a,$b,$acc_ids,'',$sales_uid); echo '<tr><td>'.h($v).'</td><td>'.money($mm['spend']).'</td><td>'.$mm['orders'].'</td><td>'.money($mm['revenue']).'</td><td class="'.($mm['profit']<0?'neg':'pos').'">'.money($mm['profit']).'</td></tr>'; } ?>
</table></div>

<?php elseif ($page === 'camp'): ?>
<div class="card"><h2>Camp trong ky</h2><table><tr><th>Camp</th><th>Tai khoan</th><th>Ma SP</th><th>Chi phi</th><th>Hien thi</th><th>Click</th><th>CTR</th><th>CPC</th><th>Gan ma SP</th></tr>
<?php
$acc_q = $acc_ids ? ' AND ad_account_id IN ('.implode(',', array_fill(0,count($acc_ids),'?')).')' : ' AND 1=0';
$s = $pdo->prepare("SELECT ad_account_id, campaign_id, campaign_name, SUM(spend) sp, SUM(impressions) im, SUM(clicks) cl FROM insights WHERE date BETWEEN ? AND ? $acc_q GROUP BY ad_account_id, campaign_id ORDER BY sp DESC");
$s->execute(array_merge([$start,$end], $acc_ids));
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $prod = $pdo->prepare("SELECT product_code FROM campaigns WHERE ad_account_id=? AND campaign_id=?"); $prod->execute([$r['ad_account_id'],$r['campaign_id']]); $pc = $prod->fetchColumn() ?: '';
    $ctr = $r['im']>0 ? $r['cl']/$r['im'] : null; $cpc = $r['cl']>0 ? $r['sp']/$r['cl'] : null;
    echo '<tr><td>'.h($r['campaign_name']).'</td><td>'.h($r['ad_account_id']).'</td><td>'.h($pc).'</td><td>'.money($r['sp']).'</td><td>'.money($r['im']).'</td><td>'.money($r['cl']).'</td><td>'.pct($ctr).'</td><td>'.($cpc?money($cpc):'-').'</td><td><form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="act" value="map_product"><input type="hidden" name="back" value="camp"><input type="hidden" name="ad_account_id" value="'.h($r['ad_account_id']).'"><input type="hidden" name="campaign_id" value="'.h($r['campaign_id']).'"><input name="product_code" value="'.h($pc).'" placeholder="VD: CAMPUCH-BC8" size="14"><button>Luu</button></form></td></tr>';
}
?>
</table><p>Gan ma san pham cho camp de bao cao theo san pham va doanh so theo nguoi / san pham duoc tinh dung.</p></div>

<?php elseif ($page === 'ngay'): ?>
<div class="card"><h2>Chi tiet tung ngay - <?=h(period_label($period))?></h2><table><tr><th>Ngay</th><th>Chi phi</th><th>Don</th><th>Doanh so</th><th>Doanh thu du kien</th><th>Loi nhuan</th></tr>
<?php for ($d=$start; $d<=$end; $d=date('Y-m-d', strtotime($d.' +1 day'))) { $mm = calc_metrics($d,$d,$acc_ids,'',$sales_uid); echo '<tr><td>'.date('d/m/Y',strtotime($d)).'</td><td>'.money($mm['spend']).'</td><td>'.$mm['orders'].'</td><td>'.money($mm['revenue']).'</td><td>'.money($mm['expected_revenue']).'</td><td class="'.($mm['profit']<0?'neg':'pos').'">'.money($mm['profit']).'</td></tr>'; } ?>
</table></div>

<?php elseif ($page === 'san-pham'): ?>
<div class="card"><h2>Theo ma san pham</h2><table><tr><th>Ma SP</th><th>Chi phi</th><th>Don</th><th>San pham</th><th>Doanh so</th><th>Doanh thu du kien</th><th>Loi nhuan</th><th>Loi nhuan/DT</th></tr>
<?php
$s = $pdo->prepare("SELECT DISTINCT product_code FROM campaigns WHERE product_code<>'' UNION SELECT DISTINCT product_code FROM sales WHERE product_code<>'' ORDER BY 1");
foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $pc) { $mm = calc_metrics($start,$end,$acc_ids,$pc,$sales_uid); echo '<tr><td>'.h($pc).'</td><td>'.money($mm['spend']).'</td><td>'.$mm['orders'].'</td><td>'.$mm['products'].'</td><td>'.money($mm['revenue']).'</td><td>'.money($mm['expected_revenue']).'</td><td class="'.($mm['profit']<0?'neg':'pos').'">'.money($mm['profit']).'</td><td>'.pct($mm['profit_ratio']).'</td></tr>'; }
?>
</table><p>Neu 1 camp chua gan ma SP, chi phi cua no se khong vao bang nay. Gan ma o trang Camp.</p></div>
<div class="card"><h3>Doanh so theo nguoi / san pham</h3><table><tr><th>Nguoi</th><th>Ma SP</th><th>Don</th><th>Doanh so</th><th>Loi nhuan</th></tr>
<?php
$s = $pdo->prepare("SELECT u.name, s.product_code FROM sales s JOIN users u ON u.id=s.user_id WHERE s.date BETWEEN ? AND ? GROUP BY u.name, s.product_code ORDER BY 1,2");
$s->execute([$start,$end]);
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $uid = $pdo->prepare("SELECT id FROM users WHERE name=?"); $uid->execute([$r['name']]); $id = $uid->fetchColumn();
    $aq = $pdo->prepare("SELECT ad_account_id FROM user_ad_accounts WHERE user_id=?"); $aq->execute([$id]); $uacc = $aq->fetchAll(PDO::FETCH_COLUMN);
    $mm = calc_metrics($start,$end,$uacc,$r['product_code'],$id);
    echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['product_code']).'</td><td>'.$mm['orders'].'</td><td>'.money($mm['revenue']).'</td><td>'.money($mm['profit']).'</td></tr>';
}
?>
</table></div>

<?php elseif ($page === 'ai'): ?>
<div class="card"><h2>Danh gia AI - Ky <?=h(period_label($period))?></h2>
<p>Danh gia theo luat: chi phi/doanh thu du kien, loi nhuan, CTR, CPC va xu huong so voi ky truoc cung do dai. Khong phai nhan xet tu LLM ben ngoai, nen khong can API key.</p>
<?php
$days = (strtotime($end)-strtotime($start))/86400 + 1;
$prev_end = date('Y-m-d', strtotime($start.' -1 day')); $prev_start = date('Y-m-d', strtotime($prev_end.' -'.($days-1).' days'));
$acc_q = $acc_ids ? ' AND ad_account_id IN ('.implode(',', array_fill(0,count($acc_ids),'?')).')' : ' AND 1=0';
$s = $pdo->prepare("SELECT ad_account_id, campaign_id, campaign_name, SUM(spend) sp FROM insights WHERE date BETWEEN ? AND ? $acc_q GROUP BY ad_account_id, campaign_id ORDER BY sp DESC");
$s->execute(array_merge([$start,$end], $acc_ids));
$rows = $s->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) echo '<p>Chua co camp nao trong ky nay. Hay Dong bo Meta truoc.</p>';
foreach ($rows as $r) {
    $prod = $pdo->prepare("SELECT product_code FROM campaigns WHERE ad_account_id=? AND campaign_id=?"); $prod->execute([$r['ad_account_id'],$r['campaign_id']]); $pc = $prod->fetchColumn() ?: '';
    // Chi so camp: chi phi + click tu insights, doanh so theo ma SP (neu co).
    $cur = calc_metrics($start,$end,[$r['ad_account_id']],$pc,$sales_uid);
    // De tranh nhan doi khi nhieu camp cung ma SP, chi hien thi chi so camp rieng o day la chi phi/CTR/CPC tu insights:
    $s2 = $pdo->prepare("SELECT COALESCE(SUM(spend),0) sp, COALESCE(SUM(impressions),0) im, COALESCE(SUM(clicks),0) cl FROM insights WHERE date BETWEEN ? AND ? AND campaign_id=?");
    $s2->execute([$start,$end,$r['campaign_id']]); $ci = $s2->fetch(PDO::FETCH_ASSOC);
    $cur['spend'] = (float)$ci['sp']; $cur['impressions'] = (int)$ci['im']; $cur['clicks'] = (int)$ci['cl'];
    $cur['ctr'] = $cur['impressions']>0 ? $cur['clicks']/$cur['impressions'] : null; $cur['cpc'] = $cur['clicks']>0 ? $cur['spend']/$cur['clicks'] : null;
    $prev = calc_metrics($prev_start,$prev_end,[$r['ad_account_id']],$pc,$sales_uid);
    $ev = ai_evaluate($cur,$prev,$r['campaign_name']);
    echo '<div class="card"><b>'.h($r['campaign_name']).'</b> - Ma SP: '.h($pc ?: '-').' - Diem: <b>'.$ev['score'].'/100</b> - '.$ev['label'].'<ul>';
    foreach ($ev['notes'] as $n) echo '<li>'.h($n).'</li>';
    echo '</ul><p>Chi phi: '.money($cur['spend']).' | CTR: '.pct($cur['ctr']).' | CPC: '.($cur['cpc']?money($cur['cpc']):'-').' | Loi nhuan theo ma SP: '.money($cur['profit']).'</p></div>';
}
?>
</div>

<?php elseif ($page === 'doanh-so'): ?>
<div class="card"><h2>Nhap doanh so (kenh ban hang)</h2>
<p>Nhap theo ngay, theo ma san pham, theo nguoi dang nhap. App se tu tinh doanh thu du kien, chi phi/doanh thu, loi nhuan giong Sheet. Sau nay co the thay bang import CSV tu he thong ban hang.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="act" value="add_sale"><input type="hidden" name="back" value="doanh-so">
<input type="date" name="date" value="<?=date('Y-m-d')?>" required><input name="product_code" placeholder="Ma SP" required>
<input type="number" name="orders" placeholder="Don" required><input type="number" name="products" placeholder="San pham" required>
<input type="number" step="0.01" name="revenue" placeholder="Doanh so" required><input type="number" step="0.01" name="return_rate" placeholder="Ty le hoan %" value="0">
<input type="number" step="0.01" name="cogs" placeholder="Gia von" value="0"><input type="number" step="0.01" name="shipping" placeholder="Van chuyen" value="0"><input type="number" step="0.01" name="other_cost" placeholder="Chi phi khac" value="0">
<button>Luu doanh so</button></form></div>

<?php elseif ($page === 'tai-khoan'): ?>
<div class="card"><h2>Tai khoan quang cao cua ban</h2>
<p>Moi nhan vien tu them tai khoan minh dang chay. Vi du: A them 1,2,3; B them 2,3,4; C them 3,4,1. Tai khoan trung nhau thi ca 2 nguoi deu thay duoc camp cua tai khoan do, nhung token thi moi nguoi giu rieng, ma hoa truoc khi luu. <b>Khong bao gio commit token vao GitHub - repo nay la public.</b></p>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="act" value="add_account"><input type="hidden" name="back" value="tai-khoan">
<input name="ad_account_id" placeholder="ID tai khoan QC (chi so, khong can act_)" required><input name="ad_account_name" placeholder="Ten goi nho, VD: TK Cam 1" required>
<input type="password" name="token" placeholder="Access token Meta (quyen ads_read)" required><button>Luu tai khoan</button></form>
<table><tr><th>ID</th><th>Ten</th><th></th></tr>
<?php $s=$pdo->prepare("SELECT * FROM user_ad_accounts WHERE user_id=?"); $s->execute([$u['id']]);
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) echo '<tr><td>'.h($r['ad_account_id']).'</td><td>'.h($r['ad_account_name']).'</td><td><form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="act" value="del_account"><input type="hidden" name="back" value="tai-khoan"><input type="hidden" name="id" value="'.$r['id'].'"><button>Xoa</button></form></td></tr>'; ?>
</table></div>

<?php elseif ($page === 'dong-bo'): ?>
<div class="card"><h2>Dong bo tu Trinh quan ly quang cao</h2>
<p>App goi Meta Graph API v21.0, lay insights theo ngay cho tung tai khoan cua ban, luu vao SQLite. Bam dong bo khi muon cap nhat, khong goi API moi lan mo trang.</p>
<form method="post" action="sync.php"><input type="hidden" name="csrf" value="<?=csrf()?>">
<input type="date" name="start" value="<?=date('Y-m-d', strtotime('-30 days'))?>"><input type="date" name="end" value="<?=date('Y-m-d')?>"><button>Dong bo ngay</button></form>
<p>Token can quyen <b>ads_read</b>. Neu bao het han hoac thieu quyen, vao Tai khoan QC nhap lai token.</p></div>

<?php elseif ($page === 'nguoi-dung' && is_admin($u)): ?>
<div class="card"><h2>Tao tai khoan nhan vien</h2>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="act" value="add_user"><input type="hidden" name="back" value="nguoi-dung">
<input name="username" placeholder="Ten dang nhap" required><input name="name" placeholder="Ho ten" required><input type="password" name="password" placeholder="Mat khau" required>
<select name="role"><option value="employee">Nhan vien</option><option value="admin">Admin (xem tat ca)</option></select><button>Tao</button></form>
<table><tr><th>Ten dang nhap</th><th>Ho ten</th><th>Quyen</th></tr>
<?php foreach ($pdo->query("SELECT username,name,role FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) echo '<tr><td>'.h($r['username']).'</td><td>'.h($r['name']).'</td><td>'.h($r['role']).'</td></tr>'; ?>
</table></div>
<?php endif; ?>
</main></body></html>
