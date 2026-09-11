<?php
// Tải file sao lưu toàn bộ dữ liệu (SQLite). Ảnh nằm trong thư mục uploads/, sao lưu riêng.
db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$path = db_path();
if (!is_file($path)) { flash('error', 'Không tìm thấy file dữ liệu.'); redirect(admin_url()); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="zungza-saoluu-' . date('Ymd-His') . '.sqlite"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
