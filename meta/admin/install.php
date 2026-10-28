<?php
// Cai dat lan dau cho Meta Admin. Sau khi tao xong, XOA file nay ngay.
require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $n = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($n > 0) die('Da co tai khoan, xoa install.php ngay.');
    $pdo->prepare("INSERT INTO users (username,name,password_hash,role) VALUES (?,?,?, 'admin')")
        ->execute([$_POST['username'], $_POST['name'], password_hash($_POST['password'], PASSWORD_DEFAULT)]);
    echo '<p>Tao admin xong. <a href="index.php?page=login">Dang nhap</a> roi <b>xoa install.php</b>.</p>';
    exit;
}
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>Cai dat Meta Admin</title></head><body>
<h1>Cai dat Meta Admin - lan dau</h1>
<p>Thu muc <code>data/</code> phai ghi duoc. Sau khi tao admin, xoa file install.php.</p>
<form method="post">
<input name="username" placeholder="Ten dang nhap admin" required><br>
<input name="name" placeholder="Ho ten" required><br>
<input type="password" name="password" placeholder="Mat khau" required><br>
<button>Tao admin</button></form></body></html>
