<?php
require_once 'config.php';
if ($conn->connect_error) die("Koneksi gagal.");

// Alter table to add 'pending' to ENUM
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'pimpinan', 'staf', 'pending') NOT NULL DEFAULT 'pending'";
if ($conn->query($sql)) {
    echo "<h1>Database Berhasil Diupdate!</h1>";
    echo "<p>Role 'pending' telah ditambahkan. Anda bisa menutup halaman ini.</p>";
} else {
    echo "Gagal: " . $conn->error;
}
?>
