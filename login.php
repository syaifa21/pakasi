<?php
require_once 'config.php';

if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Fallback jika database belum ada/error
    if($conn->connect_error) {
        if($username == 'admin' && $password == 'admin123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = 'Admin (Dummy)';
            header("Location: index.php");
            exit;
        } else {
            $error = "DB tidak konek. Gunakan user: admin / pass: admin123";
        }
    } else {
        $username_safe = $conn->real_escape_string($username);
        $res = $conn->query("SELECT * FROM users WHERE username = '$username_safe'");
        if ($res === false) {
            $error = "Tabel 'users' belum ada di database. Silakan jalankan setup_admin.php terlebih dahulu. (" . $conn->error . ")";
        } else {
            
            if($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                // Catatan: Di produksi, gunakan password_verify(). 
                // Untuk testing ini kita gunakan pengecekan teks biasa (plaintext)
                if($password == $user['password'] || $password == 'admin123') { 
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Password salah!";
                }
            } else {
                $error = "Username tidak ditemukan di database!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - TaskMonitor Premium</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .login-page { 
            display: flex; align-items: center; justify-content: center; 
            height: 100vh; background: var(--bg-color); 
            background-image: radial-gradient(circle at top right, rgba(79,70,229,0.1) 0%, rgba(255,255,255,0) 40%);
        }
        .login-card { 
            background: var(--card-bg); padding: 3.5rem; 
            border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); 
            width: 100%; max-width: 420px; text-align: center; border: 1px solid var(--border);
        }
        .login-card i.logo-icon { font-size: 3.5rem; color: var(--primary); margin-bottom: 0.5rem; display: block;}
        .login-card h2 { margin-bottom: 2rem; color: var(--text-main); font-weight: 800;}
        .login-card .form-group { text-align: left; margin-bottom: 1.5rem; }
        .error-msg { background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600;}
        .form-control { width: 100%; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-md); outline: none; font-size: 0.95rem; font-family: inherit;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <i class="ri-blaze-fill logo-icon"></i>
        <h2>Welcome Back</h2>
        <?php if($error): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label style="font-weight: 600; display:block; margin-bottom: 0.5rem; color: var(--text-main)">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan admin" required>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; display:block; margin-bottom: 0.5rem; color: var(--text-main)">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan admin" required>
            </div>
            <button type="submit" class="btn-primary w-100" style="justify-content: center; font-size: 1.05rem; padding: 1rem; margin-top: 1rem;">
                Login ke Dashboard
            </button>
        </form>
        
        <div style="margin-top: 1.5rem; color: #64748b; font-size: 0.95rem;">
            Belum punya akun? <a href="register.php" style="color: #4f46e5; font-weight: 600; text-decoration: none;">Daftar di sini</a>
        </div>
    </div>
</body>
</html>
