<?php
declare(strict_types=1);

function nav_categories(): array
{
    static $c = null;
    if ($c === null) $c = rows('SELECT * FROM categories WHERE active = 1 ORDER BY sort, id');
    return $c;
}

function published_posts_sql(): string
{
    return "p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= '" . now() . "')";
}

/* ---------- Trang chủ ---------- */
function page_home(): void
{
    $featured = rows("SELECT * FROM products WHERE status = 'active' AND is_featured = 1 ORDER BY sort, id LIMIT 12");
    $cards = array_slice(product_cards($featured), 0, 8);
    $tiers = array_values(array_filter(combo_tiers(), fn($t) => (int)$t['min_qty'] >= 2));
    $reviews = rows('SELECT * FROM reviews WHERE approved = 1 ORDER BY created_at DESC LIMIT 6');
    $posts = rows('SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id
                   WHERE ' . published_posts_sql() . ' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT 3');
    $homeCats = array_values(array_filter(nav_categories(), fn($c) => $c['show_home']));
    view('home', [
        'title' => setting('site_name', 'Zungza') . ' | ' . setting('site_tagline'),
        'nav' => 'home',
        'cards' => $cards,
        'tiers' => $tiers,
        'reviews' => $reviews,
        'posts' => $posts,
        'homeCats' => $homeCats,
    ]);
}

/* ---------- Danh mục ---------- */
function page_category(string $slug): void
{
    $cat = row('SELECT * FROM categories WHERE slug = ? AND active = 1', [$slug]);
    if (!$cat) not_found();
    $products = rows("SELECT * FROM products WHERE status = 'active' AND category_id = ? ORDER BY sort, id", [$cat['id']]);
    $cards = product_cards($products);
    $allTags = [];
    foreach ($cards as $c) foreach ($c['tags'] as $t) $allTags[$t] = true;
    $tag = get('tag');
    if ($tag !== '') $cards = array_values(array_filter($cards, fn($c) => in_array($tag, $c['tags'], true)));
    view('category', [
        'title' => $cat['name'] . ' | ' . setting('site_name'),
        'meta_desc' => $cat['description'] ?: null,
        'nav' => 'cat-' . $cat['id'],
        'cat' => $cat,
        'cards' => $cards,
        'tags' => array_keys($allTags),
        'tag' => $tag,
    ]);
}

/* ---------- Tìm kiếm ---------- */
function page_search(): void
{
    $qStr = mb_substr(get('q'), 0, 80);
    $cards = [];
    if ($qStr !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qStr) . '%';
        $products = rows("SELECT DISTINCT p.* FROM products p LEFT JOIN product_variants v ON v.product_id = p.id AND v.active = 1
                          WHERE p.status = 'active' AND (p.name LIKE ? OR p.short_desc LIKE ? OR v.name LIKE ? OR v.description LIKE ?)
                          ORDER BY p.sort, p.id LIMIT 40", [$like, $like, $like, $like]);
        $cards = product_cards($products);
        // Với sản phẩm tách biến thể: chỉ giữ biến thể khớp từ khóa nếu có
        $n = mb_strtolower($qStr);
        $matched = array_values(array_filter($cards, fn($c) => str_contains(mb_strtolower($c['title'] . ' ' . $c['subtitle']), $n)));
        if ($matched) $cards = $matched;
    }
    view('search', ['title' => 'Tìm kiếm: ' . $qStr, 'q' => $qStr, 'cards' => $cards, 'noindex' => true]);
}

/* ---------- Trang sản phẩm ---------- */
function page_product(string $slug): void
{
    $p = row("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.slug = ? AND p.status = 'active'", [$slug]);
    if (!$p) not_found();
    $images = rows('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$p['id']]);
    $variants = rows('SELECT * FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort, id', [$p['id']]);
    if (!$variants) not_found();
    $selected = $variants[0];
    $vParam = (int)get('v');
    foreach ($variants as $v) if ((int)$v['id'] === $vParam) $selected = $v;

    $tiers = $p['combo_eligible'] ? combo_tiers() : [];
    $related = rows("SELECT p.* FROM product_related r JOIN products p ON p.id = r.related_id
                     WHERE r.product_id = ? AND p.status = 'active' ORDER BY p.sort", [$p['id']]);
    $reviews = rows('SELECT * FROM reviews WHERE approved = 1 AND (product_id = ? OR product_id IS NULL) ORDER BY product_id IS NULL, created_at DESC LIMIT 6', [$p['id']]);
    $posts = rows('SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id
                   WHERE ' . published_posts_sql() . ' AND p.related_product_id = ? ORDER BY p.published_at DESC LIMIT 2', [$p['id']]);
    $specs = [];
    foreach (lines($p['specs']) as $line) {
        [$k, $vv] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
        $specs[] = [$k, $vv];
    }
    $unit = $selected['price'] !== null ? (int)$selected['price'] : (int)$p['price'];

    view('product', [
        'title' => ($p['seo_title'] ?: $p['name']) . ' | ' . setting('site_name'),
        'meta_desc' => $p['seo_desc'] ?: ($p['short_desc'] ?: excerpt($p['description'])),
        'og_image' => $images ? media($images[0]['path']) : null,
        'nav' => 'cat-' . $p['category_id'],
        'body_class' => 'has-buybar',
        'p' => $p,
        'images' => $images,
        'variants' => $variants,
        'selected' => $selected,
        'unit' => $unit,
        'tiers' => $tiers,
        'related' => product_cards($related),
        'reviews' => $reviews,
        'posts' => $posts,
        'specs' => $specs,
        'pixel' => ['ViewContent', ['content_name' => $p['name'], 'value' => $unit, 'currency' => 'VND']],
    ]);
}

/* ---------- Giỏ hàng ---------- */
function cart_action(string $action): void
{
    csrf_check();
    switch ($action) {
        case 'them':
            $added = 0;
            $ids = $_POST['variant_ids'] ?? null;
            if (is_array($ids)) {
                foreach ($ids as $vid) {
                    if (sellable_variant((int)$vid)) { cart_add((int)$vid, 1); $added++; }
                }
            } else {
                $vid = (int)post('variant_id', '0');
                $qty = max(1, min(99, (int)post('qty', '1')));
                if (sellable_variant($vid)) { cart_add($vid, $qty); $added = $qty; }
            }
            if (!$added) {
                flash('error', 'Sản phẩm này hiện không bán online. Nhắn Zungza để được tư vấn.');
                redirect(back_url());
            }
            if (post('buy_now') !== '') redirect(url('thanh-toan'));
            flash('success', "Đã thêm $added sản phẩm vào giỏ.");
            redirect(url('gio-hang'));
            // no break
        case 'cap-nhat':
            $c = cart();
            $vid = (int)post('variant_id', '0');
            if (isset($c[$vid])) {
                $c[$vid] = (int)post('qty', '0');
                cart_save($c);
            }
            redirect(url('gio-hang'));
            // no break
        case 'xoa':
            $c = cart();
            unset($c[(int)post('variant_id', '0')]);
            cart_save($c);
            redirect(url('gio-hang'));
            // no break
        case 'ma-giam-gia':
            $code = strtoupper(post('coupon'));
            if ($code === '') {
                unset($_SESSION['coupon']);
            } else {
                $p = price_cart(cart_lines(), $code);
                if ($p['coupon']) {
                    $_SESSION['coupon'] = $code;
                    flash('success', 'Đã áp dụng mã ' . $code . '.');
                } else {
                    unset($_SESSION['coupon']);
                    flash('error', $p['coupon_error']);
                }
            }
            redirect(post('back') === 'checkout' ? url('thanh-toan') : url('gio-hang'));
            // no break
        default:
            not_found();
    }
}

function page_cart(): void
{
    $lines = cart_lines();
    $pricing = price_cart($lines, $_SESSION['coupon'] ?? '');
    if (($_SESSION['coupon'] ?? '') !== '' && !$pricing['coupon']) unset($_SESSION['coupon']);
    $nudge = combo_nudge($pricing['combo_qty']);
    $nudgeVariant = null;
    if ($nudge) foreach ($lines as $l) if ($l['combo_eligible']) { $nudgeVariant = $l['vid']; break; }
    view('cart', [
        'title' => 'Giỏ hàng | ' . setting('site_name'),
        'noindex' => true,
        'lines' => $lines,
        'pr' => $pricing,
        'nudge' => $nudge,
        'nudgeVariant' => $nudgeVariant,
    ]);
}

/* ---------- Thanh toán ---------- */
function page_checkout(): void
{
    $errors = [];
    $methods = payment_methods_enabled();
    $old = ['name' => '', 'phone' => '', 'province' => '', 'ward' => '', 'address' => '', 'note' => '', 'pay' => $methods[0] ?? 'cod', 'invoice' => false, 'company' => '', 'tax' => '', 'email' => ''];
    if (is_post()) {
        csrf_check();
        foreach ($old as $k => $_) $old[$k] = $k === 'invoice' ? post('invoice') !== '' : mb_substr((string)post($k), 0, $k === 'note' ? 1000 : 250);
        $old['phone'] = normalize_phone($old['phone']);
        if (!in_array($old['pay'], payment_methods_enabled(), true)) $errors['pay'] = 'Chọn hình thức thanh toán.';
        if ($old['name'] === '') $errors['name'] = 'Nhập họ tên người nhận.';
        if (!valid_phone($old['phone'])) $errors['phone'] = 'Nhập số điện thoại 10 số, bắt đầu bằng 0.';
        if ($old['province'] === '') $errors['province'] = 'Nhập tỉnh / thành phố.';
        if ($old['ward'] === '') $errors['ward'] = 'Nhập phường / xã.';
        if ($old['address'] === '') $errors['address'] = 'Nhập số nhà, tên đường.';
        if ($old['invoice']) {
            if ($old['company'] === '') $errors['company'] = 'Nhập tên công ty.';
            if (!preg_match('/^\d{10}(-\d{3})?$/', $old['tax'])) $errors['tax'] = 'Mã số thuế gồm 10 số (hoặc 10 số-3 số).';
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Nhập email nhận hóa đơn hợp lệ.';
        }
        if (post('website') !== '') $errors['form'] = 'Không gửi được đơn.'; // bẫy bot
        if (!$errors) {
            [$code, , $err] = place_order($old, $_SESSION['coupon'] ?? '');
            if ($code) {
                $_SESSION['my_orders'][] = $code;
                $_SESSION['my_orders'] = array_slice($_SESSION['my_orders'], -10);
                redirect($old['pay'] === 'bank' ? url('chuyen-khoan/' . $code) : url('dat-hang-thanh-cong/' . $code));
            }
            $errors['form'] = $err;
        }
    }
    $lines = cart_lines();
    if (!$lines) {
        view('cart', ['title' => 'Giỏ hàng', 'noindex' => true, 'lines' => [], 'pr' => price_cart([]), 'nudge' => null, 'nudgeVariant' => null]);
        return;
    }
    $pricing = price_cart($lines, $_SESSION['coupon'] ?? '');
    view('checkout', [
        'title' => 'Thanh toán | ' . setting('site_name'),
        'noindex' => true,
        'lines' => $lines,
        'pr' => $pricing,
        'errors' => $errors,
        'old' => $old,
        'methods' => $methods,
        'pixel' => ['InitiateCheckout', ['value' => $pricing['total'], 'currency' => 'VND']],
    ]);
}

function my_order(string $code): ?array
{
    if (!in_array($code, $_SESSION['my_orders'] ?? [], true)) return null;
    return row('SELECT * FROM orders WHERE code = ?', [$code]);
}

/* ---------- Bước 3: chuyển khoản ---------- */
function page_transfer(string $code): void
{
    $o = my_order($code);
    if (!$o || $o['payment_method'] !== 'bank') redirect(url());
    if (is_post()) {
        csrf_check();
        if (post('act') === 'paid' && !$o['transfer_reported_at']) {
            q('UPDATE orders SET transfer_reported_at = ? WHERE id = ?', [now(), $o['id']]);
            send_order_email((int)$o['id'], 'reported');
        }
        redirect(url('dat-hang-thanh-cong/' . $code));
    }
    view('transfer', [
        'title' => 'Chuyển khoản đơn ' . $code,
        'noindex' => true,
        'o' => $o,
        'qr' => transfer_qr_url((int)$o['total'], $o['code']),
    ]);
}

function page_thanks(string $code): void
{
    $o = my_order($code);
    if (!$o) redirect(url());
    $items = rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$o['id']]);
    $firePixel = empty($_SESSION['pixel_fired'][$code]);
    $_SESSION['pixel_fired'][$code] = true;
    view('thanks', [
        'title' => 'Đặt hàng thành công',
        'noindex' => true,
        'o' => $o,
        'items' => $items,
        'pixel' => $firePixel ? ['Purchase', ['value' => (int)$o['total'], 'currency' => 'VND']] : null,
    ]);
}

/* ---------- Blog ---------- */
function page_blog(): void
{
    $cats = rows('SELECT * FROM blog_categories ORDER BY sort, id');
    $catSlug = get('cat');
    $where = published_posts_sql();
    $params = [];
    $curCat = null;
    if ($catSlug !== '') {
        foreach ($cats as $c) if ($c['slug'] === $catSlug) $curCat = $c;
        if ($curCat) { $where .= ' AND p.category_id = ?'; $params[] = $curCat['id']; }
    }
    $total = (int)val("SELECT COUNT(*) FROM posts p WHERE $where", $params);
    $pg = paginate($total, 9, (int)get('page', '1'));
    $posts = rows("SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id
                   WHERE $where ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    view('blog', ['title' => 'Blog | ' . setting('site_name'), 'nav' => 'blog', 'cats' => $cats, 'curCat' => $curCat, 'posts' => $posts, 'pg' => $pg]);
}

function page_post(string $slug): void
{
    $post = row('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id
                 WHERE p.slug = ? AND ' . published_posts_sql(), [$slug]);
    if (!$post) not_found();
    $product = null;
    if ($post['related_product_id']) {
        $pp = rows("SELECT * FROM products WHERE id = ? AND status = 'active'", [$post['related_product_id']]);
        $cards = product_cards($pp);
        if ($pp && $cards) {
            $product = $cards[0];
            $product['title'] = $pp[0]['name'];
            $product['url'] = url('san-pham/' . $pp[0]['slug']);
        }
    }
    $more = rows('SELECT p.*, c.name AS cat_name FROM posts p LEFT JOIN blog_categories c ON c.id = p.category_id
                  WHERE ' . published_posts_sql() . ' AND p.id <> ? ORDER BY p.published_at DESC LIMIT 3', [$post['id']]);
    view('post', [
        'title' => ($post['seo_title'] ?: $post['title']) . ' | ' . setting('site_name'),
        'meta_desc' => $post['seo_desc'] ?: ($post['excerpt'] ?: excerpt($post['content'])),
        'og_image' => $post['cover'] ? media($post['cover']) : null,
        'nav' => 'blog',
        'post' => $post,
        'product' => $product,
        'more' => $more,
    ]);
}

/* ---------- Trang tĩnh ---------- */
function page_static(string $slug): void
{
    $page = row("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$slug]);
    if (!$page) not_found();
    view('page', [
        'title' => ($page['seo_title'] ?: $page['title']) . ' | ' . setting('site_name'),
        'meta_desc' => $page['seo_desc'] ?: excerpt($page['content']),
        'page' => $page,
    ]);
}

/* ---------- Quà tặng doanh nghiệp ---------- */
function page_b2b(): void
{
    $page = row("SELECT * FROM pages WHERE slug = 'qua-doanh-nghiep' AND status = 'published'");
    $errors = [];
    $old = ['company' => '', 'contact' => '', 'phone' => '', 'email' => '', 'qty' => '', 'budget' => '', 'date' => '', 'note' => ''];
    $sent = !empty($_SESSION['quote_sent']);
    unset($_SESSION['quote_sent']);
    if (is_post()) {
        csrf_check();
        foreach ($old as $k => $_) $old[$k] = mb_substr((string)post($k), 0, $k === 'note' ? 2000 : 200);
        $old['phone'] = normalize_phone($old['phone']);
        if ($old['company'] === '') $errors['company'] = 'Nhập tên công ty.';
        if ($old['contact'] === '') $errors['contact'] = 'Nhập tên người liên hệ.';
        if (!valid_phone($old['phone'])) $errors['phone'] = 'Nhập số điện thoại 10 số, bắt đầu bằng 0.';
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email không hợp lệ.';
        if ((int)$old['qty'] <= 0) $errors['qty'] = 'Nhập số lượng dự kiến.';
        $date = $old['date'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['date']) ? $old['date'] : null;
        if (post('website') !== '') $errors['form'] = 'Không gửi được.';
        if (!$errors) {
            q('INSERT INTO quote_requests (company, contact_name, phone, email, quantity, budget, need_date, note, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
              [$old['company'], $old['contact'], $old['phone'], $old['email'] ?: null, (int)$old['qty'], $old['budget'] ?: null, $date, $old['note'] ?: null, now()]);
            send_quote_email($old + ['need_date' => $date]);
            $_SESSION['quote_sent'] = true;
            redirect(url('qua-doanh-nghiep') . '#bao-gia');
        }
    }
    view('b2b', ['title' => 'Quà tặng doanh nghiệp | ' . setting('site_name'), 'nav' => 'b2b', 'page' => $page, 'errors' => $errors, 'old' => $old, 'sent' => $sent]);
}

/* ---------- Sitemap ---------- */
function page_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [[url(), date('Y-m-d')]];
    foreach (nav_categories() as $c) $urls[] = [url('danh-muc/' . $c['slug']), null];
    foreach (rows("SELECT slug, updated_at FROM products WHERE status = 'active'") as $p) $urls[] = [url('san-pham/' . $p['slug']), (substr((string)$p['updated_at'], 0, 10) ?: null)];
    $urls[] = [url('blog'), null];
    foreach (rows('SELECT slug, updated_at FROM posts p WHERE ' . published_posts_sql()) as $p) $urls[] = [url('blog/' . $p['slug']), (substr((string)$p['updated_at'], 0, 10) ?: null)];
    foreach (rows("SELECT slug, updated_at FROM pages WHERE status = 'published' AND slug <> 'qua-doanh-nghiep'") as $p) $urls[] = [url('trang/' . $p['slug']), (substr((string)$p['updated_at'], 0, 10) ?: null)];
    $urls[] = [url('qua-doanh-nghiep'), null];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$u, $d]) {
        echo '  <url><loc>' . e($u) . '</loc>' . ($d ? "<lastmod>$d</lastmod>" : '') . "</url>\n";
    }
    echo '</urlset>';
}
