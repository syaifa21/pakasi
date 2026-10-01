<?php
require_once 'header.php';

if ($_SESSION['role'] === 'pending') {
    echo '<div style="text-align:center; padding: 100px 20px;">
            <i class="ri-time-line" style="font-size: 5rem; color: #f59e0b;"></i>
            <h1 style="margin-top: 20px;">Menunggu Persetujuan</h1>
            <p style="color: #64748b; font-size: 1.1rem; line-height: 1.6;">Akun Anda telah terdaftar namun belum memiliki peran <i>(Role Pending)</i>.<br>Silakan hubungi <b>Admin</b> atau <b>Pimpinan</b> Anda untuk mengaktifkan akun ini.</p>
          </div>';
    require_once 'footer.php';
    exit;
}

// Handle form submit untuk task baru (Simulasi sebagai Pimpinan)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_tugas']) && !$conn->connect_error) {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'];
    $pesan_teks = $_POST['pesan_teks'];
    $kategori = $_POST['kategori'];
    $lokasi = $_POST['lokasi'];
    
    // SLA and Kode prefix mapping
    $sla = '1 Jam';
    $kode_prefix = 'RTN';
    if ($kategori == 'P1') { $sla = '15 Menit'; $kode_prefix = 'DRT'; }
    elseif ($kategori == 'P2') { $sla = '30 Menit'; $kode_prefix = 'ADN'; }
    
    // Cek urutan hari ini untuk no
    $date_str = date('Ymd');
    $count_res = $conn->query("SELECT count(*) as c FROM tasks WHERE DATE(created_at) = CURDATE()");
    $no_urut = "01";
    if ($count_res) {
        $row = $count_res->fetch_assoc();
        $no_urut = str_pad($row['c'] + 1, 2, "0", STR_PAD_LEFT);
    }
    $kode_tugas = "CRM-$kode_prefix-$date_str-$no_urut";
    
    $file_url = null;
    $local_path = null;
    
    // Proses Upload File Lampiran
    if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['file_lampiran']['name']));
        if (move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $upload_dir . $filename)) {
            $base_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir . "/" . $upload_dir . $filename;
            $local_path = $upload_dir . $filename;
        }
    }
    
    // Insert ke database
    $stmt = $conn->prepare("INSERT INTO tasks (kode_tugas, sender_id, receiver_id, kategori, lokasi, sla_konfirmasi, pesan_teks, file_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("siisssss", $kode_tugas, $sender_id, $receiver_id, $kategori, $lokasi, $sla, $pesan_teks, $file_url);
    $stmt->execute();
    $task_id = $stmt->insert_id;
    
    // Mengirim notifikasi ke Telegram Staf
    require_once 'telegram_functions.php';
    
    $receiver_id_safe = (int)$receiver_id;
    $res = $conn->query("SELECT username, telegram_chat_id FROM users WHERE id = $receiver_id_safe");
    
    if ($res->num_rows > 0) {
        $staf = $res->fetch_assoc();
        $chat_id = $staf['telegram_chat_id'];
        
        if (!empty($chat_id)) {
            $base_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $dashboard_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir;
            
            $msg = "■ <b>[INSTRUKSI CIREMAI HIJI]</b>\n";
            $msg .= "Kode: <b>$kode_tugas</b>\n";
            $msg .= "Petugas: " . htmlspecialchars($staf['username']) . "\n";
            $msg .= "Lokasi: " . htmlspecialchars($lokasi) . "\n";
            $msg .= "Giat: " . htmlspecialchars($pesan_teks) . "\n";
            $msg .= "SLA Konfirmasi: $sla\n";
            $msg .= "Dashboard: $dashboard_link\n\n";
            $msg .= "<i>Tekan tombol <b>Terima Tugas</b> di bawah untuk mulai mengerjakannya.</i>";
            
            // Inline Keyboard Button
            $reply_markup = json_encode([
                "inline_keyboard" => [[["text" => "🎯 Terima Tugas", "callback_data" => "accept_" . $task_id]]]
            ]);
            
            if ($local_path) {
                // Gunakan sendDocumentLocal agar lebih stabil via CURLFile
                sendDocumentLocal($chat_id, $local_path, $msg, $bot_token, $reply_markup);
            } else {
                sendMessage($chat_id, $msg, $bot_token, $reply_markup);
            }
        }
    }
    
    echo "<script>window.location='index.php?success=1';</script>";
    exit;
}

// Ambil data users (Untuk dropdown staf)
$staf_query = "SELECT id, username FROM users WHERE role = 'staf'";
$staf_result = !$conn->connect_error ? $conn->query($staf_query) : null;

// Statistik Dasar (Beranda menampilkan highlight saja)
$tasks_query = "
    SELECT 
        t.id, t.pesan_teks, t.file_url, t.status, t.created_at, t.completed_at,
        p.username as pimpinan_name, s.username as staf_name,
        TIMEDIFF(t.completed_at, t.accepted_at) as durasi
    FROM tasks t
    LEFT JOIN users p ON t.sender_id = p.id
    LEFT JOIN users s ON t.receiver_id = s.id
    ORDER BY t.created_at DESC LIMIT 5
";
$tasks_result = !$conn->connect_error ? $conn->query($tasks_query) : null;

// Hitung total untuk dashboard
$cnt_res = !$conn->connect_error ? $conn->query("SELECT status, count(*) as c FROM tasks GROUP BY status") : null;
$total_tugas = 0; $pending = 0; $on_progress = 0; $completed = 0;
if($cnt_res) {
    while($r = $cnt_res->fetch_assoc()) {
        $total_tugas += $r['c'];
        if($r['status']=='pending') $pending = $r['c'];
        if($r['status']=='on_progress') $on_progress = $r['c'];
        if($r['status']=='completed') $completed = $r['c'];
    }
}
?>

<div class="page-header">
    <div>
        <h1>Beranda Dashboard</h1>
        <p>Ikhtisar pelacakan tugas harian dan respons staf.</p>
    </div>
    <button class="btn-primary" onclick="document.getElementById('taskModal').style.display='flex'">
        <i class="ri-add-line"></i> Buat Tugas Baru
    </button>
</div>

<!-- Stats Widget -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-blue"><i class="ri-folders-line"></i></div>
        <div class="stat-info">
            <h3>Total Semua Tugas</h3>
            <h2><?= $total_tugas ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-orange"><i class="ri-time-line"></i></div>
        <div class="stat-info">
            <h3>Belum Diterima</h3>
            <h2><?= $pending ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#8b5cf6;"><i class="ri-loader-4-line ri-spin"></i></div>
        <div class="stat-info">
            <h3>Sedang Dikerjakan</h3>
            <h2><?= $on_progress ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-green"><i class="ri-check-double-line"></i></div>
        <div class="stat-info">
            <h3>Tugas Selesai</h3>
            <h2><?= $completed ?></h2>
        </div>
    </div>
</div>

<!-- Modern Table (Highlight) -->
<div class="table-container">
    <div class="table-header">
        <h2>5 Tugas Terakhir</h2>
        <a href="tugas.php" class="btn-outline"><i class="ri-eye-line"></i> Lihat Semua</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Tugas</th>
                    <th>Pimpinan</th>
                    <th>Ditugaskan Ke</th>
                    <th>Detail Pesan</th>
                    <th>Durasi Respons</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tasks_result && $tasks_result->num_rows > 0): ?>
                    <?php while($row = $tasks_result->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td><?= $row['pimpinan_name'] ?? 'Pimpinan' ?></td>
                        <td>
                            <div class="staff-badge">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['staf_name'] ?? 'S') ?>&background=random" alt="S"> 
                                <?= $row['staf_name'] ?? 'Staf ID: '.$row['receiver_id'] ?>
                            </div>
                        </td>
                        <td class="message-cell" title="<?= htmlspecialchars($row['pesan_teks']) ?>">
                            <?= htmlspecialchars($row['pesan_teks']) ?>
                            <?php if(!empty($row['file_url'])): ?>
                                <br><a href="<?= htmlspecialchars($row['file_url']) ?>" target="_blank" style="font-size:0.8rem; color:#3b82f6; text-decoration:none;"><i class="ri-attachment-line"></i> Lampiran</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 'completed'): ?>
                                <span class="duration-badge">
                                    <i class="ri-timer-flash-line"></i> <?= $row['durasi'] ?>
                                </span>
                            <?php elseif($row['status'] == 'on_progress'): ?>
                                <span class="text-muted" style="color:#8b5cf6;"><i class="ri-timer-line"></i> Timer Aktif...</span>
                            <?php else: ?>
                                <span class="text-muted"><i class="ri-time-line"></i> Belum Diterima</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 'completed'): ?>
                                <span class="status-badge status-completed">Selesai</span>
                            <?php elseif($row['status'] == 'on_progress'): ?>
                                <span class="status-badge" style="background:#ede9fe; color:#8b5cf6;"><i class="ri-loader-4-line ri-spin"></i> In Progress</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <!-- Fallback -->
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: #718096;">
                            <i class="ri-inbox-line" style="font-size: 3rem; display:block; margin-bottom: 1rem;"></i>
                            Belum ada tugas atau database belum terkoneksi.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tambah Tugas -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('taskModal').style.display='none'">&times;</span>
        <div class="modal-header">
            <h2>Kirim Tugas Baru</h2>
            <p>Tugas akan langsung di-push ke Telegram bot staf bersangkutan.</p>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih Staf (Penerima)</label>
                <select name="receiver_id" required>
                    <option value="" disabled selected>-- Pilih Staf dari Database --</option>
                    <?php 
                    if ($staf_result && $staf_result->num_rows > 0) {
                        while($s = $staf_result->fetch_assoc()) {
                            echo "<option value='{$s['id']}'>{$s['username']}</option>";
                        }
                    } else {
                        echo "<option value='2'>Staf Dummy 1 (ID: 2)</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Kategori Penugasan</label>
                <select name="kategori" required>
                    <option value="P1">DARURAT (P1) - SLA: 15 Menit</option>
                    <option value="P2">ADUAN PUBLIK (P2) - SLA: 30 Menit</option>
                    <option value="P3" selected>RUTIN (P3) - SLA: 1 Jam</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lokasi / Koordinat GPS</label>
                <input type="text" name="lokasi" placeholder="Contoh: Jalur Pendakian Palutungan Km 3" class="form-control" required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
            </div>
            <div class="form-group">
                <label>Giat / Rincian Perintah Taktis</label>
                <textarea name="pesan_teks" rows="4" placeholder="Contoh: Evakuasi pendaki cedera kaki..." required></textarea>
            </div>
            <div class="form-group">
                <label>Lampirkan File / Dokumen (Opsional)</label>
                <input type="file" name="file_lampiran" class="form-control" style="padding: 0.8rem; background: #fff;">
            </div>
            <button type="submit" name="tambah_tugas" class="btn-primary w-100">
                <i class="ri-telegram-fill"></i> Kirim & Lacak Sekarang
            </button>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
