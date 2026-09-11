-- =========================================================
-- ZUNGZA SHOP - Cơ sở dữ liệu SQLite
-- Không cần import thủ công: install.php tự chạy file này.
-- =========================================================
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS settings (
  k TEXT PRIMARY KEY,
  v TEXT
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  email TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip, created_at);

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  description TEXT,
  image TEXT,
  show_home INTEGER NOT NULL DEFAULT 1,
  sort INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  short_desc TEXT,
  description TEXT,
  price INTEGER NOT NULL DEFAULT 0,          -- 0 = hiển thị "Liên hệ"
  compare_price INTEGER,                     -- giá gạch
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('active','draft')),
  is_featured INTEGER NOT NULL DEFAULT 0,
  combo_eligible INTEGER NOT NULL DEFAULT 0, -- tính vào combo số lượng
  list_variants INTEGER NOT NULL DEFAULT 0,  -- tách mỗi loại thành 1 thẻ ở danh mục
  scent_notes TEXT,
  space_tags TEXT,
  specs TEXT,                                -- mỗi dòng "Nhãn: Giá trị"
  video_url TEXT,
  sold_label TEXT,
  seo_title TEXT,
  seo_desc TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_product_status ON products(status, sort);
CREATE INDEX IF NOT EXISTS idx_product_cat ON products(category_id);

CREATE TABLE IF NOT EXISTS product_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  path TEXT NOT NULL,
  alt TEXT,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_img_product ON product_images(product_id, sort);

CREATE TABLE IF NOT EXISTS product_variants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  sku TEXT UNIQUE,
  price INTEGER,                             -- NULL = dùng giá sản phẩm
  swatch TEXT,
  image TEXT,
  description TEXT,
  tags TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_variant_product ON product_variants(product_id, sort);

CREATE TABLE IF NOT EXISTS product_related (
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  related_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  PRIMARY KEY (product_id, related_id)
);

CREATE TABLE IF NOT EXISTS combo_tiers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  min_qty INTEGER NOT NULL UNIQUE,
  discount_amount INTEGER NOT NULL DEFAULT 0,
  free_shipping INTEGER NOT NULL DEFAULT 0,
  gifts TEXT,                                -- mỗi quà 1 dòng
  active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS coupons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL DEFAULT 'fixed' CHECK (type IN ('percent','fixed')),
  value INTEGER NOT NULL DEFAULT 0,
  min_order INTEGER NOT NULL DEFAULT 0,
  max_uses INTEGER,
  used_count INTEGER NOT NULL DEFAULT 0,
  starts_at TEXT,
  ends_at TEXT,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT
);

-- Đơn hàng: chỉ lưu làm BẢN DỰ PHÒNG. Đơn chính gửi về email.
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  customer_name TEXT NOT NULL,
  phone TEXT NOT NULL,
  province TEXT NOT NULL,
  ward TEXT NOT NULL,
  address TEXT NOT NULL,
  customer_note TEXT,
  source TEXT,
  utm_campaign TEXT,
  ref TEXT,
  subtotal INTEGER NOT NULL DEFAULT 0,
  combo_label TEXT,
  combo_discount INTEGER NOT NULL DEFAULT 0,
  coupon_code TEXT,
  coupon_discount INTEGER NOT NULL DEFAULT 0,
  shipping_fee INTEGER NOT NULL DEFAULT 0,
  total INTEGER NOT NULL DEFAULT 0,
  gifts TEXT,
  payment_method TEXT NOT NULL DEFAULT 'cod' CHECK (payment_method IN ('cod','bank')),
  transfer_reported_at TEXT,                 -- lúc khách bấm "Tôi đã chuyển khoản"
  invoice_required INTEGER NOT NULL DEFAULT 0,
  invoice_company TEXT,
  invoice_tax_code TEXT,
  invoice_email TEXT,
  email_status TEXT NOT NULL DEFAULT 'pending', -- pending | sent | failed
  email_error TEXT,
  ip TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_order_created ON orders(created_at);

CREATE TABLE IF NOT EXISTS order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
  variant_id INTEGER REFERENCES product_variants(id) ON DELETE SET NULL,
  product_name TEXT NOT NULL,
  variant_name TEXT,
  sku TEXT,
  unit_price INTEGER NOT NULL,
  qty INTEGER NOT NULL,
  line_total INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_item_order ON order_items(order_id);

CREATE TABLE IF NOT EXISTS blog_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id INTEGER REFERENCES blog_categories(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  cover TEXT,
  excerpt TEXT,
  content TEXT,
  author TEXT,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
  published_at TEXT,                         -- đặt giờ tương lai = hẹn giờ đăng
  related_product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
  seo_title TEXT,
  seo_desc TEXT,
  created_at TEXT,
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_post_pub ON posts(status, published_at);

CREATE TABLE IF NOT EXISTS pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  content TEXT,
  status TEXT NOT NULL DEFAULT 'published' CHECK (status IN ('draft','published')),
  seo_title TEXT,
  seo_desc TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
  customer_name TEXT NOT NULL,
  rating INTEGER NOT NULL DEFAULT 5,
  content TEXT,
  image TEXT,
  approved INTEGER NOT NULL DEFAULT 1,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS quote_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company TEXT NOT NULL,
  contact_name TEXT NOT NULL,
  phone TEXT NOT NULL,
  email TEXT,
  quantity INTEGER,
  budget TEXT,
  need_date TEXT,
  note TEXT,
  status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new','contacted','done')),
  created_at TEXT NOT NULL
);

-- =========================================================
-- DỮ LIỆU MẪU (sửa trong trang quản trị)
-- Chỗ [trong ngoặc vuông] là thông tin cần Zungza điền.
-- =========================================================
INSERT INTO settings (k, v) VALUES
('site_name', 'Zungza'),
('site_tagline', 'Nến thơm vỏ quế Trà Bồng'),
('meta_desc', 'Nến thơm đựng trong hũ vỏ quế Trà Bồng, sáp đậu nành và tinh dầu thiên nhiên. Làm thủ công, mỗi hũ một vân quế riêng.'),
('announcement', 'Freeship từ 2 hũ | Thanh toán khi nhận hàng (COD) | Đổi trả nếu lỗi nhà sản xuất'),
('hotline', ''), ('zalo_link', ''), ('messenger_link', ''), ('facebook_link', ''), ('email', ''), ('address', ''),
('footer_about', 'Xưởng nến thơm thủ công. Nến trong hũ vỏ quế Trà Bồng, Quảng Ngãi.'),
('shipping_fee', '30000'), ('free_ship_min', '0'), ('combo_unit', 'hũ'), ('order_prefix', 'ZZ'),
('hero_title', 'Nến thơm đựng trong hũ vỏ quế thật'),
('hero_subtitle', 'Mỗi hũ khoét từ nguyên khúc vỏ quế Trà Bồng 15 năm tuổi, làm tay trong 24 giờ. Vân quế tự nhiên nên không có hai hũ giống nhau.'),
('hero_points', 'Không khói đen
Không hóa chất
Không nhức đầu'),
('hero_cta_text', 'Chọn mùi hương'), ('hero_cta_link', 'san-pham/nen-thom-vo-que-zungza'), ('hero_image', ''),
('featured_title', '7 mùi hương cho 7 góc nhà'), ('featured_subtitle', 'Cùng một giá. Chọn theo không gian bạn muốn thắp.'),
('combo_title', 'Mua càng nhiều, quà càng lớn'), ('combo_subtitle', 'Được chọn mùi khác nhau cho từng hũ.'),
('assurance', 'Freeship|từ 2 hũ
COD|trả tiền khi nhận
Đổi trả|nếu lỗi nhà sản xuất'),
('story_title', 'Làm tay bởi người dân Trà Bồng, Quảng Ngãi'),
('story_text', 'Vỏ quế được khoét nguyên khúc thành hũ, sau đó rót sáp đậu nành thuần chay và tinh dầu thiên nhiên. Không dùng lọ thủy tinh.'),
('story_facts', '15 năm|tuổi vỏ quế
24 giờ|hoàn thiện 1 hũ
~40 giờ|thời gian thắp'),
('story_image', ''),
('banner_title', 'Nến cho ban thờ và không gian tâm linh'),
('banner_text', 'Nến sạch, không khói, hương dịu. Dùng cho ban thờ gia tiên, ban Thần Tài, đền chùa.'),
('banner_button', 'Tìm hiểu thêm'), ('banner_link', 'blog'),
('social_proof_text', ''), ('og_image', ''), ('fb_pixel_id', ''), ('tiktok_pixel_id', ''), ('head_code', ''), ('body_end_code', ''),
('telegram_bot_token', ''), ('telegram_chat_id', ''),
('mail_to', ''), ('mail_from_name', 'Zungza Website'), ('mail_from_email', ''),
('smtp_host', ''), ('smtp_port', '587'), ('smtp_encryption', 'tls'), ('smtp_user', ''), ('smtp_pass', ''),
('pay_cod_enabled', '1'), ('pay_bank_enabled', '1'),
('bank_code', ''), ('bank_name', ''), ('bank_account', ''), ('bank_holder', ''),
('qr_mode', 'auto'), ('qr_image', ''),
('transfer_note', 'Chuyển đúng số tiền và ghi đúng nội dung để Zungza đối chiếu nhanh. Chuyển xong, bấm "Tôi đã chuyển khoản".'),
('thanks_title', 'Cảm ơn bạn đã đặt hàng!'),
('thanks_message', 'Zungza sẽ gọi điện xác nhận đơn cho bạn trong thời gian sớm nhất. Bạn để ý điện thoại giúp Zungza nhé.');

INSERT INTO categories (id, name, slug, description, show_home, sort, active) VALUES
(1, 'Nến vỏ quế', 'nen-vo-que', 'Nến thơm trong hũ vỏ quế Trà Bồng. Freeship từ 2 hũ.', 1, 1, 1),
(2, 'Tinh dầu', 'tinh-dau', 'Tinh dầu thiên nhiên, tinh dầu khuếch tán, thơm ô tô.', 1, 2, 1),
(3, 'Máy xông', 'may-xong', 'Máy xông dùng cùng tinh dầu Zungza.', 1, 3, 1),
(4, 'Set quà', 'set-qua', 'Set quà theo mùa lễ.', 1, 4, 1);

INSERT INTO products (id, category_id, name, slug, short_desc, description, price, compare_price, status, is_featured, combo_eligible, list_variants, scent_notes, specs, seo_title, seo_desc, sort) VALUES
(1, 1, 'Nến thơm vỏ quế Zungza', 'nen-thom-vo-que-zungza',
 'Hũ nến khoét từ nguyên khúc vỏ quế Trà Bồng 15 năm tuổi. 7 mùi hương.',
 '<p>Hũ nến làm từ 100% vỏ quế già tự nhiên, không dùng lọ thủy tinh. Ruột nến là sáp đậu nành thuần chay kết hợp tinh dầu thiên nhiên.</p><p>Mỗi hũ được làm thủ công bởi người dân Trà Bồng, Quảng Ngãi, mất 24 giờ để hoàn thiện. Vân quế tự nhiên nên mỗi hũ là một độc bản.</p><p>Cam kết 3 không: không khói đen, không hóa chất, không nhức đầu.</p>',
 349000, 399000, 'active', 1, 1, 1,
 'Chọn mùi theo không gian: phòng khách, phòng ngủ, góc làm việc, góc thiền.',
 'Hũ nến: Nguyên khúc vỏ quế Trà Bồng 15 năm tuổi
Ruột nến: 125g sáp đậu nành thuần chay, tinh dầu đa hương nguyên chất đậm đặc
Thời gian thắp: Gần 40 giờ
Sản xuất: Làm tay tại Trà Bồng, Quảng Ngãi. 24 giờ hoàn thiện 1 hũ
Đặc điểm: Mỗi hũ là độc bản vì vân quế tự nhiên khác nhau',
 'Nến thơm vỏ quế Zungza - Hũ vỏ quế Trà Bồng, sáp đậu nành',
 'Nến thơm đựng trong hũ vỏ quế Trà Bồng 15 năm tuổi, 125g sáp đậu nành, thắp gần 40 giờ. 7 mùi hương. Freeship từ 2 hũ, COD.',
 1),
(2, 3, 'Máy xông tinh dầu', 'may-xong-tinh-dau', '[Mô tả ngắn]', '<p>[Mô tả sản phẩm]</p>', 0, NULL, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 2),
(3, 2, 'Tinh dầu thiên nhiên', 'tinh-dau-thien-nhien', '[Mô tả ngắn]', '<p>[Mô tả sản phẩm]</p>', 0, NULL, 'active', 0, 0, 0, NULL, NULL, NULL, NULL, 3);

INSERT INTO product_variants (product_id, name, sku, price, swatch, description, tags, sort, active) VALUES
(1, 'Quế',       'N001-QUE',   NULL, '#EBD3AE', 'Ấm nồng, sum vầy. Hợp phòng khách',      'Phòng khách',     1, 1),
(1, 'Hoa Hồng',  'N001-HONG',  NULL, '#F0D5D0', 'Yêu thương. Hợp buổi tối lãng mạn',       'Phòng ngủ',       2, 1),
(1, 'Nhài',      'N001-NHAI',  NULL, '#F3EEDB', 'Thanh khiết. Thư giãn, cân bằng',         'Phòng khách',     3, 1),
(1, 'Oải Hương', 'N001-OAI',   NULL, '#DDD4E6', 'An yên. Hợp phòng ngủ, ngủ sâu',          'Phòng ngủ',       4, 1),
(1, 'Núi Non',   'N001-NUI',   NULL, '#D5DFD8', 'Tự do, thoáng đãng. Hợp góc làm việc',    'Góc làm việc',    5, 1),
(1, 'Rừng Già',  'N001-RUNG',  NULL, '#D8D4BC', 'Trầm lắng. Hợp thiền, đọc sách',          'Thiền, đọc sách', 6, 1),
(1, 'Thông Reo', 'N001-THONG', NULL, '#D3E0C6', 'Tươi mới, tích cực. Năng lượng ngày mới', 'Góc làm việc',    7, 1),
(2, 'Mặc định', NULL, NULL, NULL, NULL, NULL, 1, 1),
(3, 'Mặc định', NULL, NULL, NULL, NULL, NULL, 1, 1);

INSERT INTO product_related (product_id, related_id) VALUES (1, 2), (1, 3);

-- Combo: giá = số hũ x giá 1 hũ - tiền tiết kiệm. XÁC NHẬN LẠI trong admin.
INSERT INTO combo_tiers (min_qty, discount_amount, free_shipping, gifts, active) VALUES
(1, 0,      0, '', 1),
(2, 110000, 1, '', 1),
(3, 300000, 1, '2 nến mini', 1),
(4, 400000, 1, '2 nến mini
Tinh dầu quế 15ml', 1),
(5, 625000, 1, '2 nến mini
Tinh dầu quế 15ml
Máy xông tinh dầu', 1);

INSERT INTO blog_categories (id, name, slug, sort) VALUES
(1, 'Kiến thức nến & tinh dầu', 'kien-thuc', 1),
(2, 'Nến trong không gian tâm linh', 'tam-linh', 2),
(3, 'Gợi ý quà tặng', 'goi-y-qua-tang', 3),
(4, 'Câu chuyện Trà Bồng', 'cau-chuyen-tra-bong', 4);

-- Bài viết mẫu ở trạng thái NHÁP: viết nội dung rồi bấm Đăng trong admin.
INSERT INTO posts (category_id, title, slug, excerpt, content, author, status, related_product_id) VALUES
(1, 'Thắp nến vỏ quế có bị cháy vỏ không?', 'thap-nen-vo-que-co-bi-chay-khong', 'Câu hỏi khách hỏi nhiều nhất trước khi mua.', '<p>[Nội dung bài viết]</p>', 'Zungza', 'draft', 1),
(2, 'Nến sạch cho ban thờ gia tiên và ban Thần Tài', 'nen-sach-cho-ban-tho', 'Vì sao nhiều gia đình chọn nến không khói, hương dịu cho nơi thờ cúng.', '<p>[Nội dung bài viết]</p>', 'Zungza', 'draft', 1),
(3, 'Gợi ý quà Trung thu cho đối tác và nhân viên', 'goi-y-qua-trung-thu', 'Chọn quà theo ngân sách và theo người nhận.', '<p>[Nội dung bài viết]</p>', 'Zungza', 'draft', 1),
(4, 'Từ rừng quế Trà Bồng đến một hũ nến làm tay', 'tu-rung-que-tra-bong', 'Một hũ nến mất 24 giờ để hoàn thiện.', '<p>[Nội dung bài viết]</p>', 'Zungza', 'draft', 1);

INSERT INTO pages (title, slug, content, status) VALUES
('Về Zungza', 've-zungza', '<p>Zungza là xưởng sản xuất nến thơm thủ công. Sản phẩm chủ lực là nến trong hũ vỏ quế, làm tay bởi người dân Trà Bồng, Quảng Ngãi.</p><p>Mỗi hũ dùng nguyên khúc vỏ quế 15 năm tuổi và mất 24 giờ để hoàn thiện. Vân quế tự nhiên nên không có hai hũ giống nhau.</p><p>Ngoài nến quế, xưởng sản xuất nến cốc, tinh dầu, tinh dầu khuếch tán, thơm ô tô, sáp thơm và nước hoa.</p><p>[Câu chuyện người sáng lập, hình ảnh xưởng]</p>', 'published'),
('Chính sách', 'chinh-sach', '<h2>Giao hàng</h2><p>Freeship toàn quốc cho đơn từ 2 hũ. Đơn 1 hũ phí vận chuyển 30.000đ. Thời gian giao: [số ngày theo khu vực].</p><h2>Đổi trả</h2><p>Đổi trả nếu sản phẩm lỗi do nhà sản xuất. [Thời hạn và điều kiện đổi trả]</p><h2>Thanh toán</h2><p>Thanh toán khi nhận hàng (COD) hoặc chuyển khoản.</p><h2>Bảo mật</h2><p>[Chính sách bảo mật thông tin khách hàng]</p>', 'published'),
('Quà tặng doanh nghiệp', 'qua-doanh-nghiep', '<p>Xưởng thủ công Zungza nhận đặt số lượng lớn: khắc logo lên nắp, vỏ nến quế; in bao bì, in tem; hộp nến, hộp sản phẩm, túi sản phẩm theo nhận diện của doanh nghiệp.</p><h2>Chất liệu tùy chọn</h2><ul><li>Bấc: bấc vân gỗ hoặc bấc cotton</li><li>Sáp: dừa, cọ, đậu nành, parafin</li><li>Hương: tinh dầu thiên nhiên hoặc hương nhân tạo</li></ul><h2>Dòng sản phẩm</h2><p>Nến quế, nến cốc, sáp thơm, tinh dầu, tinh dầu khuếch tán, thơm ô tô, nước hoa.</p><p>Thời gian gia công tùy số lượng và mẫu.</p>', 'published');
