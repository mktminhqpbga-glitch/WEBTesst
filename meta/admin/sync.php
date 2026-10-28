<?php
// Dong bo tu Meta Graph API v21.0. Chi goi khi user bam nut, khong goi tu dong moi lan mo trang.
// Token lay tu user_ad_accounts cua chinh user do (admin dong bo tai khoan cua minh; nhan vien dong bo tai khoan cua minh).
// Token KHONG bao gio in ra log / man hinh, chi bao loi dang: het han, thieu quyen, sai tai khoan.
require __DIR__ . '/lib.php';
require_login();
$u = current_user();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: index.php?page=sync'); exit; }
check_csrf();
$start = $_POST['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end = $_POST['end'] ?? date('Y-m-d');
if ($start > $end) { [$start, $end] = [$end, $start]; }

$pdo = db();
$s = $pdo->prepare("SELECT * FROM user_ad_accounts WHERE user_id=?");
$s->execute([$u['id']]);
$accounts = $s->fetchAll(PDO::FETCH_ASSOC);
$ok = 0; $rows = 0; $errors = [];
foreach ($accounts as $acc) {
    $token = dec_token($acc['token_enc']);
    if ($token === '') { $errors[] = $acc['ad_account_name'] . ': token khong giai ma duoc.'; continue; }
    $aid = preg_replace('/[^0-9]/', '', $acc['ad_account_id']);
    $url = 'https://graph.facebook.com/v21.0/act_' . $aid . '/insights'
        . '?fields=campaign_id,campaign_name,spend,impressions,clicks,reach'
        . '&time_increment=1&time_range=' . urlencode(json_encode(['since'=>$start,'until'=>$end]))
        . '&limit=500&access_token=' . urlencode($token);
    $page_url = $url;
    $got = false;
    for ($p = 0; $p < 10 && $page_url; $p++) {
        $ch = curl_init($page_url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
        $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $j = json_decode($body ?: '{}', true);
        if ($code !== 200 || isset($j['error'])) {
            $msg = $j['error']['message'] ?? ('HTTP ' . $code);
            // Khong in token, chi phan loai loi thuong gap.
            if (stripos($msg, 'expired') !== false || ($j['error']['code'] ?? 0) == 190) $msg = 'Token het han hoac bi thu hoi, can nhap lai token.';
            elseif (stripos($msg, 'permission') !== false) $msg = 'Thieu quyen ads_read tren tai khoan nay.';
            $errors[] = $acc['ad_account_name'] . ': ' . $msg;
            break;
        }
        foreach (($j['data'] ?? []) as $d) {
            $pdo->prepare("INSERT INTO campaigns (ad_account_id, campaign_id, campaign_name) VALUES (?,?,?)
                ON CONFLICT(ad_account_id, campaign_id) DO UPDATE SET campaign_name=excluded.campaign_name")
                ->execute([$acc['ad_account_id'], $d['campaign_id'], $d['campaign_name']]);
            $pdo->prepare("INSERT INTO insights (date, ad_account_id, campaign_id, campaign_name, spend, impressions, clicks, reach)
                VALUES (?,?,?,?,?,?,?,?) ON CONFLICT(date, ad_account_id, campaign_id) DO UPDATE SET
                campaign_name=excluded.campaign_name, spend=excluded.spend, impressions=excluded.impressions, clicks=excluded.clicks, reach=excluded.reach")
                ->execute([$d['date_start'], $acc['ad_account_id'], $d['campaign_id'], $d['campaign_name'],
                    (float)$d['spend'], (int)$d['impressions'], (int)$d['clicks'], (int)($d['reach'] ?? 0)]);
            $rows++;
        }
        $page_url = $j['paging']['next'] ?? null;
        $got = true;
    }
    if ($got) $ok++;
}
$_SESSION['sync_msg'] = "Dong bo xong: $ok/" . count($accounts) . " tai khoan, $rows dong insights tu $start den $end."
    . ($errors ? ' Loi: ' . implode(' | ', $errors) : '');
header('Location: index.php?page=sync');
exit;
