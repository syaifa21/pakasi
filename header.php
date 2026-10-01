<?php
require_once 'config.php';
checkLogin();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskMonitor - Dashboard Premium</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="ri-blaze-fill"></i>
                <span>TaskMonitor.</span>
            </div>
            <ul class="nav-links">
                <li class="<?= $current_page == 'index.php' ? 'active' : '' ?>"><a href="index.php"><i class="ri-dashboard-fill"></i> Beranda</a></li>
                <li class="<?= $current_page == 'tugas.php' ? 'active' : '' ?>"><a href="tugas.php"><i class="ri-file-list-3-line"></i> Manajemen Tugas</a></li>
                <li class="<?= $current_page == 'staf.php' ? 'active' : '' ?>"><a href="staf.php"><i class="ri-team-line"></i> Tim & Staf</a></li>
                <li class="<?= $current_page == 'laporan.php' ? 'active' : '' ?>"><a href="laporan.php"><i class="ri-bar-chart-box-line"></i> Laporan</a></li>
                <li class="spacer"></li>
                <li class="<?= $current_page == 'pengaturan.php' ? 'active' : '' ?>"><a href="pengaturan.php"><i class="ri-settings-4-line"></i> Pengaturan</a></li>
                <li><a href="logout.php" class="text-danger"><i class="ri-logout-box-r-line"></i> Keluar</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="search-bar">
                    <i class="ri-search-line"></i>
                    <input type="text" placeholder="Cari data...">
                </div>
                <div class="user-profile">
                    <div class="notif-btn">
                        <i class="ri-notification-3-line"></i>
                        <span class="badge">2</span>
                    </div>
                    <div class="avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=4f46e5&color=fff" alt="Avatar">
                    </div>
                    <div class="user-info">
                        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                        <span style="text-transform: capitalize;"><?= htmlspecialchars($_SESSION['role']) ?></span>
                    </div>
                </div>
            </header>

            <div class="content">
                <?php if($conn->connect_error): ?>
                <div class="alert-warning">
                    <i class="ri-error-warning-fill"></i>
                    Database belum terkoneksi (Mode Dummy). Silakan atur konfigurasi di <code>config.php</code>.
                </div>
                <?php endif; ?>
