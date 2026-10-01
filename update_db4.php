<?php
require_once 'config.php';

// Buat tabel task_reports untuk menyimpan semua laporan/lampiran per tugas
$sql1 = "CREATE TABLE IF NOT EXISTS task_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    teks TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_type VARCHAR(20) DEFAULT NULL,
    sumber ENUM('telegram', 'web') DEFAULT 'telegram',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$ok1 = $conn->query($sql1);

// Pastikan kolom laporan_teks dan laporan_file_url ada di tabel tasks (untuk backward compat)
$conn->query("ALTER TABLE tasks ADD COLUMN laporan_teks TEXT DEFAULT NULL AFTER pesan_teks");
$conn->query("ALTER TABLE tasks ADD COLUMN laporan_file_url VARCHAR(255) DEFAULT NULL AFTER file_url");

if ($ok1) {
    echo "✅ Tabel task_reports berhasil dibuat!<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
}

echo "<br>Selesai. <a href='tugas.php'>Kembali ke Tugas</a>";
?>
