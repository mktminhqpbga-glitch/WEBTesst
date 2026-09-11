<?php
// Copy file này thành config.php rồi điền thông tin.
return [
    // Địa chỉ website, KHÔNG có dấu / ở cuối. VD: https://zungza.vn  hoặc chạy thử: http://localhost/zungza-shop
    'base_url' => 'https://ten-mien-cua-ban.vn',

    // File dữ liệu SQLite (tự tạo khi cài). Mặc định nằm trong thư mục data/ (đã chặn truy cập từ web).
    // Nếu hosting cho phép, nên đặt NGOÀI public_html, VD: '/home/tenuser/zungza-data/zungza.sqlite'
    'db_path' => __DIR__ . '/data/zungza.sqlite',

    // true: link đẹp /san-pham/abc (cần .htaccess). false: /index.php?_path=san-pham/abc
    'pretty_urls' => true,
    'timezone' => 'Asia/Ho_Chi_Minh',
    // Bật true khi chạy thử để xem lỗi chi tiết. Chạy thật phải để false.
    'debug' => false,
    'upload_max_mb' => 5,
];
