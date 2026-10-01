<?php
require_once 'config.php';

if($conn->connect_error) {
    die("<h2>Koneksi DB Gagal!</h2><p>Pastikan username dan password database di config.php sudah benar.</p>");
}

$username = 'admin';
$password = 'admin123';
$role = 'admin';

// Cek apakah tabel users sudah ada
$check_table = $conn->query("SHOW TABLES LIKE 'users'");
if($check_table->num_rows == 0) {
    die("<h2>Tabel 'users' belum ada!</h2><p>Silakan import database.sql terlebih dahulu ke phpMyAdmin.</p>");
}

// Cek apakah admin sudah ada
$check = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if($check->num_rows > 0) {
    echo "<h2>Aman!</h2><p>User <b>admin</b> sudah ada di database. Silakan langsung <a href='login.php'>Login di sini</a>.</p>";
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);
    if($stmt->execute()) {
        echo "<h2>Sukses!</h2><p>Berhasil membuat user admin ke database!</p>";
        echo "<ul><li>Username: <b>admin</b></li><li>Password: <b>admin123</b></li></ul>";
        echo "<a href='login.php'>Klik di sini untuk Login</a>";
    } else {
        echo "<h2>Gagal!</h2><p>Error saat insert: " . $conn->error . "</p>";
    }
}
?>
