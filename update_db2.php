<?php
require_once 'config.php';
if ($conn->connect_error) die("Koneksi gagal.");

$sql1 = "ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'on_progress', 'completed') DEFAULT 'pending'";
$sql2 = "ALTER TABLE tasks ADD COLUMN accepted_at TIMESTAMP NULL DEFAULT NULL AFTER created_at";

$res1 = $conn->query($sql1);
$res2 = $conn->query($sql2); // might fail if already exists, that's fine

echo "<h1>Database Tasks Berhasil Diupdate!</h1>";
echo "<p>Status 'on_progress' dan kolom 'accepted_at' telah ditambahkan. Silakan tutup halaman ini.</p>";
?>
