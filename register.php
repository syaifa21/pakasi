<?php
require_once 'config.php';

if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password']; 
    $role = 'pending'; // Default role
    
    if(empty($username) || empty($password)) {
        $error = "Semua field harus diisi!";
    } else {
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if($check && $check->num_rows > 0) {
            $error = "Username sudah digunakan! Silakan pilih yang lain.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $username, $password, $role);
                if($stmt->execute()) {
                    $success = "Registrasi Berhasil! Status akun Anda saat ini <b>Pending</b>. Silakan hubungi Admin untuk persetujuan.";
                } else {
                    $error = "Terjadi kesalahan sistem: " . $conn->error;
                }
            } else {
                $error = "Database belum disetup atau error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi - TaskMonitor</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .login-page { 
            display: flex; align-items: center; justify-content: center; 
            min-height: 100vh; background: var(--bg-color); 
            background-image: radial-gradient(circle at top right, rgba(79,70,229,0.1) 0%, rgba(255,255,255,0) 40%);
        }
        .login-card { 
            background: var(--card-bg); padding: 3.5rem; 
            border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); 
            width: 100%; max-width: 450px; text-align: center; border: 1px solid var(--border);
            margin: 2rem;
        }
        .login-card i.logo-icon { font-size: 3rem; color: var(--primary); margin-bottom: 0.5rem; display: block;}
        .login-card h2 { margin-bottom: 0.5rem; color: var(--text-main); font-weight: 800;}
        .login-card p.subtitle { color: var(--text-muted); margin-bottom: 2rem; font-size: 0.95rem; }
        .login-card .form-group { text-align: left; margin-bottom: 1.25rem; }
        .error-msg { background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; text-align:left;}
        .success-msg { background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; text-align:left;}
        .form-control { width: 100%; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-md); outline: none; font-size: 0.95rem; font-family: inherit; background: #f8fafc;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); background: #fff;}
        .link-text { display: block; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.95rem; }
        .link-text a { color: var(--primary); font-weight: 600; text-decoration: none; transition: 0.3s; }
        .link-text a:hover { text-decoration: underline; color: var(--primary-hover); }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <i class="ri-user-add-fill logo-icon"></i>
        <h2>Buat Akun Baru</h2>
        <p class="subtitle">Daftarkan diri Anda untuk menggunakan sistem</p>
        
        <?php if($error): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>
        <?php if($success): ?><div class="success-msg"><?= $success ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label style="font-weight: 600; display:block; margin-bottom: 0.5rem; color: var(--text-main)">Username Unik</label>
                <input type="text" name="username" class="form-control" placeholder="Contoh: budi_staf" required>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; display:block; margin-bottom: 0.5rem; color: var(--text-main)">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Buat password yang kuat" required>
            </div>
            <!-- Jabatan di-set pending oleh sistem, admin yang approve -->
            <button type="submit" class="btn-primary w-100" style="justify-content: center; font-size: 1.05rem; padding: 1rem; margin-top: 1rem;">
                Daftar Sekarang
            </button>
        </form>
        
        <div class="link-text">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
</body>
</html>
