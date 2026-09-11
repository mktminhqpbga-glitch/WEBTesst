# Zungza Shop: website bán hàng + trang quản trị (bản SQLite)

- PHP 8, **không cần MySQL, không cần tạo database**: dữ liệu nằm trong 1 file `data/zungza.sqlite`.
- Đơn hàng **gửi về email**, không có trang quản lý đơn.
- Chạy trên hosting thông thường (cPanel, ONEPANEL, DirectAdmin, LiteSpeed, Apache).

## 1. Luồng đặt hàng

1. **Giỏ hàng**: khách chốt mẫu mã, số lượng, combo. Quà tặng tự cộng.
2. **Thông tin & thanh toán**: điền họ tên, SĐT, địa chỉ, chọn **COD** hoặc **Chuyển khoản**, bấm Đặt hàng. Web gửi email **[ĐƠN MỚI]** cho bạn.
   - COD: chuyển sang trang cảm ơn.
   - Chuyển khoản: sang bước 3.
3. **Chuyển khoản**: hiện số tiền, STK, chủ tài khoản, nội dung = mã đơn, mã QR đã điền sẵn số tiền. Khách bấm **"Tôi đã chuyển khoản"**, web gửi email **[KHÁCH BÁO ĐÃ CK]** để bạn kiểm tra app ngân hàng, rồi sang trang cảm ơn.

Trang cảm ơn luôn nhắn khách chờ Zungza gọi điện xác nhận. Yêu cầu báo giá doanh nghiệp cũng gửi về email **[BÁO GIÁ DN]**.

## 2. Yêu cầu hosting
- PHP **8.0+** với extension `pdo_sqlite` và `mbstring` (hầu hết hosting bật sẵn; `install.php` sẽ tự kiểm tra)
- SSL (https)

## 3. Cài đặt
1. Upload thư mục lên `public_html` (nhớ upload cả file ẩn `.htaccess`).
2. Copy `config.sample.php` thành `config.php`, sửa `base_url` thành tên miền (không có `/` ở cuối).
3. Thư mục `data/` và `uploads/` phải ghi được (chmod 755, lỗi thì 775).
4. Vào `https://ten-mien/install.php`, tạo tài khoản quản trị. Email của bạn tự được đặt làm email nhận đơn.
5. **Xóa `install.php`**.
6. Vào `https://ten-mien/admin/`:
   - **Email nhận đơn**: điền email nhận, cài SMTP, bấm "Lưu và gửi email thử".
   - **Thanh toán & QR**: chọn ngân hàng, STK, tên chủ tài khoản, rồi **quét thử mã QR xem trước bằng app ngân hàng**.
   - **Cài đặt website**: hotline, link Messenger/Zalo, ảnh trang chủ, Pixel.

Chạy thử trên máy (XAMPP): `base_url` = `http://localhost/zungza-shop`, bỏ `#` ở dòng `RewriteBase /zungza-shop/` trong `.htaccess`.

### Link sản phẩm báo 404?
Đặt `'pretty_urls' => false` trong `config.php`. Hosting Nginx thêm:
```nginx
location / { try_files $uri $uri/ /index.php?$args; }
location ~ ^/(app|views|data|admin/modules)/ { deny all; }
location ~ \.(sqlite|sql)$ { deny all; }
location ^~ /uploads/ { location ~ \.php$ { deny all; } }
```

## 4. Gửi email bằng Gmail
1. Bật Xác minh 2 bước cho tài khoản Google.
2. Tài khoản Google → Bảo mật → **Mật khẩu ứng dụng** → tạo mật khẩu 16 ký tự.
3. Trong admin → Email nhận đơn: bấm nút **Gmail**, điền email Gmail và mật khẩu 16 ký tự, bấm "Lưu và gửi email thử".

Không cài SMTP thì web dùng `mail()` của hosting, rất dễ vào spam.

## 5. Chống mất đơn
- Mọi đơn vẫn được lưu **bản sao dự phòng**. Xem ở **Tổng quan** trong admin (chỉ để đối chiếu, không phải trang quản lý).
- Email gửi lỗi thì menu Tổng quan hiện số đỏ. Sửa SMTP rồi bấm **"Gửi lại email"**.
- Tùy chọn: điền Telegram bot trong Cài đặt website để nhận báo đơn thêm trên điện thoại.

## 6. Sao lưu (bắt buộc)
- Admin → Tổng quan → **Tải file sao lưu dữ liệu** (1 file `.sqlite` chứa toàn bộ sản phẩm, bài viết, cài đặt, đơn dự phòng).
- Tải thư mục `uploads/` (ảnh) định kỳ.
- Khôi phục: chép file sao lưu đè lên `data/zungza.sqlite`.

## 7. Trang quản trị gồm
Tổng quan · Sản phẩm (ảnh, loại/mùi, SKU, giá riêng, mua kèm, SEO) · Danh mục · Combo & quà · Mã giảm giá · Bài viết blog (soạn thảo, chèn ảnh, hẹn giờ) · Chuyên mục blog · Trang nội dung · Đánh giá · Báo giá DN · **Email nhận đơn** · **Thanh toán & QR** · Cài đặt website · Tài khoản.

## 8. Bảo mật
Mật khẩu mã hóa bcrypt, chống CSRF, khóa đăng nhập 15 phút sau 5 lần sai, truy vấn dạng prepared statement, escape dữ liệu hiển thị, chặn truy cập `data/`, `config.php`, file `.sqlite`, chặn chạy PHP trong `uploads/`, trang chuyển khoản/cảm ơn chỉ người đặt đơn mới xem được.
Nếu hosting cho phép, đặt file dữ liệu **ngoài `public_html`** bằng cách sửa `db_path` trong `config.php`.

## 9. Chưa có
- Tự xác nhận tiền về (cần dịch vụ đọc biến động số dư như SePay, Casso).
- Tạo vận đơn GHN/GHTK tự động.
- Email xác nhận gửi cho khách.
