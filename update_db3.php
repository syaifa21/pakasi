<?php
require_once 'config.php';
if ($conn->connect_error) die("Koneksi gagal.");

$queries = [
    "ALTER TABLE tasks ADD COLUMN kode_tugas VARCHAR(50) NULL AFTER id",
    "ALTER TABLE tasks ADD COLUMN kategori ENUM('P1', 'P2', 'P3') DEFAULT 'P3' AFTER receiver_id",
    "ALTER TABLE tasks ADD COLUMN lokasi VARCHAR(255) NULL AFTER kategori",
    "ALTER TABLE tasks ADD COLUMN sla_konfirmasi VARCHAR(50) NULL AFTER lokasi"
];

foreach ($queries as $q) {
    $conn->query($q); // abaikan error kalau sudah ada
}

echo "<h1>Struktur Database Diperbarui!</h1>";
echo "<p>Kolom kode_tugas, kategori, lokasi, dan sla_konfirmasi telah ditambahkan sesuai SOP Ciremai Hiji.</p>";
?>
