<?php
declare(strict_types=1);

/* =========================================================
   NGUỒN ĐƠN (Ads Facebook / TikTok / Google / CTV...)
   ========================================================= */
function capture_source(): void
{
    $src = get('utm_source');
    if ($src === '' && isset($_GET['fbclid'])) $src = 'facebook';
    if ($src === '' && isset($_GET['ttclid'])) $src = 'tiktok';
    if ($src === '' && isset($_GET['gclid'])) $src = 'google';
    if ($src !== '') {
        $_SESSION['src'] = [
            'source'   => mb_substr(mb_strtolower($src), 0, 60),
            'campaign' => mb_substr(get('utm_campaign'), 0, 120),
        ];
    }
    if (($ref = preg_replace('/[^\w-]/', '', get('ref'))) !== '') {
        $_SESSION['ref'] = substr($ref, 0, 60);
    }
    if (empty($_SESSION['src'])) {
        $host = strtolower((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $own = strtolower((string)parse_url((string)cfg('base_url'), PHP_URL_HOST));
        $source = 'truc-tiep';
        if ($host && $host !== $own) {
            $source = $host;
            foreach (['facebook' => 'facebook', 'fb.' => 'facebook', 'instagram' => 'instagram', 'tiktok' => 'tiktok', 'google' => 'google', 'zalo' => 'zalo', 'youtube' => 'youtube', 'shopee' => 'shopee'] as $needle => $name) {
                if (str_contains($host, $needle)) { $source = $name; break; }
            }
        }
        $_SESSION['src'] = ['source' => substr($source, 0, 60), 'campaign' => ''];
    }
}

/* =========================================================
   DANH SÁCH SẢN PHẨM (thẻ sản phẩm)
   ========================================================= */
function product_first_images(array $ids): array
{
    if (!$ids) return [];
    $out = [];
    foreach (rows('SELECT product_id, path FROM product_images WHERE product_id IN (' . in_list($ids) . ') ORDER BY sort, id', $ids) as $r) {
        $out[$r['product_id']] ??= $r['path'];
    }
    return $out;
}

/** Trả về các thẻ hiển thị. Sản phẩm bật "list_variants" sẽ tách mỗi biến thể thành 1 thẻ. */
function product_cards(array $products): array
{
    if (!$products) return [];
    $ids = array_column($products, 'id');
    $imgs = product_first_images($ids);
    $variants = [];
    foreach (rows('SELECT * FROM product_variants WHERE active = 1 AND product_id IN (' . in_list($ids) . ') ORDER BY sort, id', $ids) as $v) {
        $variants[$v['product_id']][] = $v;
    }
    $cards = [];
    foreach ($products as $p) {
        $vs = $variants[$p['id']] ?? [];
        if (!$vs) continue;
        if ($p['list_variants'] && count($vs) > 1) {
            foreach ($vs as $v) {
                $price = $v['price'] !== null ? (int)$v['price'] : (int)$p['price'];
                $cards[] = [
                    'url' => url('san-pham/' . $p['slug'], ['v' => $v['id']]),
                    'image' => $v['image'] ?: ($imgs[$p['id']] ?? null),
                    'title' => $p['name'] . ' - ' . $v['name'],
                    'subtitle' => $v['description'] ?: $p['short_desc'],
                    'price' => $price,
                    'compare' => $v['price'] === null ? (int)$p['compare_price'] : 0,
                    'tags' => array_filter(array_map('trim', explode(',', (string)$v['tags']))),
                    'swatch' => $v['swatch'],
                ];
            }
        } else {
            $cards[] = [
                'url' => url('san-pham/' . $p['slug']),
                'image' => $imgs[$p['id']] ?? null,
                'title' => $p['name'],
                'subtitle' => $p['short_desc'],
                'price' => (int)$p['price'],
                'compare' => (int)$p['compare_price'],
                'tags' => array_filter(array_map('trim', explode(',', (string)$p['space_tags']))),
                'swatch' => null,
            ];
        }
    }
    return $cards;
}

/* =========================================================
   COMBO THEO SỐ LƯỢNG
   ========================================================= */
function combo_tiers(): array
{
    static $t = null;
    if ($t === null) $t = rows('SELECT * FROM combo_tiers WHERE active = 1 ORDER BY min_qty');
    return $t;
}

function tier_for(int $qty): ?array
{
    $found = null;
    foreach (combo_tiers() as $t) if ((int)$t['min_qty'] <= $qty) $found = $t;
    return $found;
}

function next_tier(int $qty): ?array
{
    foreach (combo_tiers() as $t) if ((int)$t['min_qty'] > $qty) return $t;
    return null;
}

function tier_gifts(?array $tier): array
{
    return $tier ? lines($tier['gifts']) : [];
}

/** Câu nhắc "Thêm 1 hũ để ..." trong giỏ */
function combo_nudge(int $qty): ?array
{
    if ($qty < 1) return null;
    $cur = tier_for($qty);
    $nx = next_tier($qty);
    if (!$nx) return null;
    $need = (int)$nx['min_qty'] - $qty;
    $parts = [];
    if ($nx['free_shipping'] && !($cur['free_shipping'] ?? 0)) $parts[] = 'được freeship';
    if ((int)$nx['discount_amount'] > (int)($cur['discount_amount'] ?? 0)) $parts[] = 'tiết kiệm ' . short_money((int)$nx['discount_amount']);
    $newGifts = array_diff(tier_gifts($nx), tier_gifts($cur));
    if ($newGifts) $parts[] = 'nhận thêm ' . mb_strtolower(implode(', ', $newGifts));
    if (!$parts) return null;
    return ['need' => $need, 'text' => 'Thêm ' . $need . ' ' . setting('combo_unit', 'sản phẩm') . ' để ' . implode(', ', $parts) . '.'];
}

/* =========================================================
   GIỎ HÀNG (lưu trong session: [variant_id => qty])
   ========================================================= */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_save(array $c): void
{
    $clean = [];
    foreach ($c as $vid => $qty) {
        $qty = min(99, (int)$qty);
        if ((int)$vid > 0 && $qty > 0) $clean[(int)$vid] = $qty;
    }
    $_SESSION['cart'] = $clean;
}

function cart_add(int $vid, int $qty = 1): void
{
    $c = cart();
    $c[$vid] = ($c[$vid] ?? 0) + max(1, $qty);
    cart_save($c);
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_clear(): void
{
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

/** Lấy biến thể hợp lệ để thêm vào giỏ (sản phẩm đang bán, có giá) */
function sellable_variant(int $vid): ?array
{
    return row("SELECT v.*, p.price AS p_price, p.name AS p_name FROM product_variants v
                JOIN products p ON p.id = v.product_id
                WHERE v.id = ? AND v.active = 1 AND p.status = 'active'
                  AND COALESCE(v.price, p.price) > 0", [$vid]);
}

function cart_lines(): array
{
    $c = cart();
    if (!$c) return [];
    $ids = array_keys($c);
    $rs = rows("SELECT v.id AS vid, v.name AS vname, v.sku, v.price AS vprice, v.image AS vimage,
                       p.id AS pid, p.name AS pname, p.slug, p.price, p.combo_eligible
                FROM product_variants v JOIN products p ON p.id = v.product_id
                WHERE v.id IN (" . in_list($ids) . ") AND v.active = 1 AND p.status = 'active'", $ids);
    $imgs = product_first_images(array_unique(array_column($rs, 'pid')));
    $lines = [];
    $valid = [];
    $byId = array_column($rs, null, 'vid');
    foreach ($c as $vid => $qty) {
        $r = $byId[$vid] ?? null;
        if (!$r) continue;
        $unit = $r['vprice'] !== null ? (int)$r['vprice'] : (int)$r['price'];
        if ($unit <= 0) continue;
        $valid[$vid] = $qty;
        $lines[] = $r + [
            'unit' => $unit,
            'qty' => $qty,
            'total' => $unit * $qty,
            'image' => $r['vimage'] ?: ($imgs[$r['pid']] ?? null),
            'label' => $r['vname'] === 'Mặc định' ? $r['pname'] : $r['pname'] . ' - ' . $r['vname'],
        ];
    }
    if (count($valid) !== count($c)) cart_save($valid);
    return $lines;
}

/* =========================================================
   MÃ GIẢM GIÁ & TÍNH TIỀN
   ========================================================= */
function coupon_check(string $code, int $amount): array
{
    $code = strtoupper(trim($code));
    $c = row('SELECT * FROM coupons WHERE code = ? AND active = 1', [$code]);
    if (!$c) return [null, 'Mã giảm giá không tồn tại hoặc đã tắt.'];
    $now = date('Y-m-d H:i:s');
    if ($c['starts_at'] && $c['starts_at'] > $now) return [null, 'Mã giảm giá chưa đến thời gian áp dụng.'];
    if ($c['ends_at'] && $c['ends_at'] < $now) return [null, 'Mã giảm giá đã hết hạn.'];
    if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses']) return [null, 'Mã giảm giá đã hết lượt dùng.'];
    if ($amount < (int)$c['min_order']) return [null, 'Đơn tối thiểu ' . money($c['min_order']) . ' để dùng mã này.'];
    return [$c, ''];
}

function price_cart(array $lines, string $couponCode = ''): array
{
    $sub = 0;
    $comboQty = 0;
    foreach ($lines as $l) {
        $sub += $l['total'];
        if ($l['combo_eligible']) $comboQty += $l['qty'];
    }
    $tier = $comboQty > 0 ? tier_for($comboQty) : null;
    $combo = $tier ? min((int)$tier['discount_amount'], $sub) : 0;
    $after = $sub - $combo;

    $coupon = null;
    $couponErr = '';
    $couponDisc = 0;
    if ($couponCode !== '' && $lines) {
        [$coupon, $couponErr] = coupon_check($couponCode, $after);
        if ($coupon) {
            $couponDisc = $coupon['type'] === 'percent'
                ? intdiv($after * min(100, (int)$coupon['value']), 100)
                : min((int)$coupon['value'], $after);
        }
    }
    $after -= $couponDisc;

    $ship = 0;
    if ($lines) {
        $ship = (int)setting('shipping_fee', '0');
        $min = (int)setting('free_ship_min', '0');
        if (($tier && $tier['free_shipping']) || ($min > 0 && $after >= $min)) $ship = 0;
    }
    return [
        'subtotal' => $sub,
        'combo_qty' => $comboQty,
        'tier' => $tier,
        'combo_label' => $tier && (int)$tier['discount_amount'] > 0 ? 'Combo ' . $tier['min_qty'] . ' ' . setting('combo_unit', 'sản phẩm') : '',
        'combo_discount' => $combo,
        'coupon' => $coupon,
        'coupon_error' => $couponErr,
        'coupon_discount' => $couponDisc,
        'shipping' => $ship,
        'gifts' => tier_gifts($tier),
        'total' => $after + $ship,
    ];
}

/* =========================================================
   ĐẶT HÀNG
   ========================================================= */
function new_order_code(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper(setting('order_prefix', 'ZZ'))) ?: 'ZZ';
    do {
        $code = $prefix . date('ymd') . random_int(1000, 9999);
    } while (val('SELECT COUNT(*) FROM orders WHERE code = ?', [$code]));
    return $code;
}

/** @return array{0:?string,1:?int,2:?string} [mã đơn, id đơn, lỗi] */
function place_order(array $f, string $couponCode): array
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE'); // khóa ghi để không trùng mã, không vượt lượt dùng mã giảm giá
    try {
        $lines = cart_lines();
        if (!$lines) throw new UserError('Giỏ hàng đang trống.');
        $p = price_cart($lines, $couponCode);
        if ($couponCode !== '' && !$p['coupon']) throw new UserError($p['coupon_error']);
        if ($p['coupon']) {
            $ok = q('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)', [$p['coupon']['id']])->rowCount();
            if (!$ok) throw new UserError('Mã giảm giá đã hết lượt dùng.');
        }
        $code = new_order_code();
        $src = $_SESSION['src'] ?? ['source' => 'truc-tiep', 'campaign' => ''];
        q('INSERT INTO orders (code, customer_name, phone, province, ward, address, customer_note, source, utm_campaign, ref,
                subtotal, combo_label, combo_discount, coupon_code, coupon_discount, shipping_fee, total, gifts,
                payment_method, invoice_required, invoice_company, invoice_tax_code, invoice_email, ip, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?)', [
            $code, $f['name'], $f['phone'], $f['province'], $f['ward'], $f['address'], $f['note'] ?: null,
            $src['source'] ?: null, $src['campaign'] ?: null, $_SESSION['ref'] ?? null,
            $p['subtotal'], $p['combo_label'] ?: null, $p['combo_discount'],
            $p['coupon'] ? $p['coupon']['code'] : null, $p['coupon_discount'], $p['shipping'], $p['total'],
            $p['gifts'] ? implode("\n", $p['gifts']) : null,
            $f['pay'], $f['invoice'] ? 1 : 0, $f['company'] ?: null, $f['tax'] ?: null, $f['email'] ?: null, client_ip(), now(),
        ]);
        $oid = last_id();
        foreach ($lines as $l) {
            q('INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, sku, unit_price, qty, line_total)
               VALUES (?,?,?,?,?,?,?,?,?)', [
                $oid, $l['pid'], $l['vid'], $l['pname'], $l['vname'] === 'Mặc định' ? null : $l['vname'],
                $l['sku'], $l['unit'], $l['qty'], $l['total'],
            ]);
        }
        $pdo->exec('COMMIT');
    } catch (UserError $e) {
        $pdo->exec('ROLLBACK');
        return [null, null, $e->getMessage()];
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
        throw $e;
    }
    cart_clear();
    send_order_email($oid, 'new');
    return [$code, $oid, null];
}

/* =========================================================
   THÔNG BÁO ĐƠN MỚI QUA TELEGRAM (tùy chọn)
   ========================================================= */
function telegram_send(string $text): void
{
    $token = setting('telegram_bot_token');
    $chat = setting('telegram_chat_id');
    if ($token === '' || $chat === '') return;
    $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $payload = http_build_query(['chat_id' => $chat, 'text' => $text]);
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec($ch);
            curl_close($ch);
        } else {
            @file_get_contents($endpoint, false, stream_context_create(['http' => [
                'method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $payload, 'timeout' => 5,
            ]]));
        }
    } catch (Throwable $e) {
        error_log('[zungza] telegram: ' . $e->getMessage());
    }
}

/* =========================================================
   THANH TOÁN: COD / CHUYỂN KHOẢN + MÃ QR (VietQR)
   ========================================================= */
// Mã ngân hàng theo chuẩn VietQR (dùng tạo QR tự điền số tiền + nội dung)
const VIETQR_BANKS = [
    'VCB' => 'Vietcombank', 'BIDV' => 'BIDV', 'ICB' => 'VietinBank', 'VBA' => 'Agribank',
    'TCB' => 'Techcombank', 'MB' => 'MB Bank', 'ACB' => 'ACB', 'VPB' => 'VPBank', 'TPB' => 'TPBank',
    'STB' => 'Sacombank', 'HDB' => 'HDBank', 'VIB' => 'VIB', 'SHB' => 'SHB', 'EIB' => 'Eximbank',
    'MSB' => 'MSB', 'OCB' => 'OCB', 'SEAB' => 'SeABank', 'LPB' => 'LPBank', 'NAB' => 'Nam A Bank',
    'ABB' => 'ABBANK', 'KLB' => 'Kienlongbank', 'PVCB' => 'PVcomBank', 'SCB' => 'SCB', 'NCB' => 'NCB',
    'BAB' => 'Bac A Bank', 'VCCB' => 'BVBank (Bản Việt)', 'VAB' => 'VietABank', 'SGICB' => 'Saigonbank',
];

function payment_methods_enabled(): array
{
    $m = [];
    if (setting('pay_cod_enabled', '1') === '1') $m[] = 'cod';
    if (setting('pay_bank_enabled', '1') === '1' && setting('bank_account') !== '') $m[] = 'bank';
    return $m ?: ['cod'];
}

function transfer_content(string $code): string
{
    return $code; // nội dung chuyển khoản = mã đơn
}

/** Link ảnh QR: VietQR tự điền số tiền + nội dung, hoặc ảnh QR tải lên, hoặc null */
function transfer_qr_url(int $amount, string $code): ?string
{
    $mode = setting('qr_mode', 'auto');
    if ($mode === 'image' && setting('qr_image') !== '') return media(setting('qr_image'));
    if ($mode === 'auto' && setting('bank_code') !== '' && setting('bank_account') !== '') {
        return 'https://img.vietqr.io/image/' . rawurlencode(setting('bank_code')) . '-' . rawurlencode(preg_replace('/\s+/', '', setting('bank_account')))
            . '-compact2.png?' . http_build_query(array_filter([
                'amount' => $amount > 0 ? $amount : null,
                'addInfo' => transfer_content($code),
                'accountName' => setting('bank_holder') ?: null,
            ]));
    }
    return null;
}

function bank_display_name(): string
{
    return setting('bank_name') ?: (VIETQR_BANKS[setting('bank_code')] ?? setting('bank_code'));
}
