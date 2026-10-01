<?php
require_once 'config.php';

// Pastikan koneksi aman
if ($conn->connect_error) {
    die("<div style='font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px;'>
        <h3>❌ Koneksi Database Gagal!</h3>
        <p>Pastikan kredensial di <b>config.php</b> sudah benar. <br>Error: " . $conn->connect_error . "</p>
        </div>");
}

echo "<div style='font-family:sans-serif; max-width:600px; margin:20px auto; padding:20px; border:1px solid #ddd; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1);'>";
echo "<h2 style='border-bottom:2px solid #4f46e5; padding-bottom:10px;'>⚙️ Auto-Migrate Database</h2>";

$sql_file = 'database.sql';

if (!file_exists($sql_file)) {
    die("<p style='color:red;'>File <b>database.sql</b> tidak ditemukan di folder ini.</p></div>");
}

$sql_content = file_get_contents($sql_file);

if (empty(trim($sql_content))) {
    die("<p style='color:red;'>File <b>database.sql</b> kosong.</p></div>");
}

echo "<p>⏳ Menjalankan script SQL...</p>";

// Mengeksekusi multi query dari file sql
if ($conn->multi_query($sql_content)) {
    // Kita harus membebaskan memori dari setiap query agar bisa menjalankan query selanjutnya (seperti insert admin)
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "<p style='color:green; font-weight:bold;'>✅ Struktur tabel berhasil di-import/di-update!</p>";
} else {
    echo "<p style='color:red;'>❌ Gagal menjalankan query: " . $conn->error . "</p>";
}

// ---------------------------------------------------------
// AUTO-INJECT ADMIN JIKA BELUM ADA
// ---------------------------------------------------------
echo "<h3>🔐 Pengecekan Akun Admin</h3>";
$username = 'admin';
$password = 'admin123';
$role = 'admin';

// Cek keberadaan admin
$check = $conn->query("SELECT id FROM users WHERE username = 'admin'");

if ($check) {
    if ($check->num_rows > 0) {
        echo "<p style='color:#0ea5e9;'>ℹ️ User <b>admin</b> sudah tersedia di database.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $password, $role);
            if ($stmt->execute()) {
                echo "<p style='color:green; font-weight:bold;'>✅ User Admin berhasil dibuat otomatis!</p>";
                echo "<ul><li>Username: <b>admin</b></li><li>Password: <b>admin123</b></li></ul>";
            } else {
                echo "<p style='color:red;'>❌ Gagal inject admin: " . $conn->error . "</p>";
            }
        }
    }
} else {
    echo "<p style='color:red;'>❌ Gagal mengecek data admin, pastikan tabel users sudah terbuat.</p>";
}

echo "<div style='margin-top:30px;'>
        <a href='login.php' style='background:#4f46e5; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>
            Pergi ke Halaman Login
        </a>
      </div>";
echo "</div>";
?>
