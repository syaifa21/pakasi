<?php
require_once 'header.php';

// Handle form submit untuk tambah staf/pimpinan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_user']) && !$conn->connect_error) {
    $new_username = $conn->real_escape_string($_POST['username']);
    $new_password = $_POST['password']; // Jika untuk produksi, disarankan password_hash()
    $new_role = $conn->real_escape_string($_POST['role']);
    
    // Cek apakah username sudah ada
    $check = $conn->query("SELECT id FROM users WHERE username = '$new_username'");
    if ($check && $check->num_rows > 0) {
        $error_msg = "Username sudah digunakan!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $new_username, $new_password, $new_role);
        if ($stmt->execute()) {
            echo "<script>window.location='staf.php?success=1';</script>";
            exit;
        } else {
            $error_msg = "Gagal menyimpan data: " . $conn->error;
        }
    }
}

// Handle form submit assign role
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_role']) && !$conn->connect_error) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan') {
        $target_id = (int)$_POST['target_id'];
        $assign_role = $conn->real_escape_string($_POST['assign_role_value']);
        $stmt_role = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt_role->bind_param("si", $assign_role, $target_id);
        if ($stmt_role->execute()) {
            echo "<script>window.location='staf.php?success_assign=1';</script>";
            exit;
        } else {
            $error_msg = "Gagal mengubah role.";
        }
    }
}

// Handle form submit delete user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user']) && !$conn->connect_error) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan') {
        $delete_id = (int)$_POST['delete_id'];
        // Jangan biarkan user menghapus dirinya sendiri (asumsi session user_id ada)
        if (isset($_SESSION['user_id']) && $delete_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                echo "<script>window.location='staf.php?success_delete=1';</script>";
                exit;
            } else {
                $error_msg = "Gagal menghapus user.";
            }
        } else {
            $error_msg = "Anda tidak dapat menghapus akun Anda sendiri.";
        }
    }
}

// Ambil data users (semua kecuali admin)
$staf_query = "SELECT * FROM users WHERE role IN ('staf', 'pimpinan', 'pending') ORDER BY FIELD(role, 'pending', 'pimpinan', 'staf'), id DESC";
$staf_result = !$conn->connect_error ? $conn->query($staf_query) : null;
?>
<div class="page-header">
    <div>
        <h1>Tim & Staf</h1>
        <p>Kelola data anggota tim dan status bot Telegram mereka.</p>
        <?php if(isset($error_msg)): ?>
            <div style="color: red; margin-top: 10px; font-weight: bold;"><?= $error_msg ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['success'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">User berhasil ditambahkan!</div>
        <?php endif; ?>
        <?php if(isset($_GET['success_assign'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">Role berhasil diupdate!</div>
        <?php endif; ?>
        <?php if(isset($_GET['success_delete'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">User berhasil dihapus!</div>
        <?php endif; ?>
    </div>
    <button class="btn-primary" onclick="document.getElementById('stafModal').style.display='flex'">
        <i class="ri-user-add-line"></i> Tambah Staf/User
    </button>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Daftar Staf</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Telegram Chat ID</th>
                    <th>Status Bot</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($staf_result && $staf_result->num_rows > 0): ?>
                    <?php while($row = $staf_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td>
                            <div class="staff-badge" style="background: transparent;">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['username']) ?>&background=random" alt="S"> 
                                <strong><?= $row['username'] ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if($row['role'] == 'pending'): ?>
                                <span class="status-badge status-pending" style="background:#fef3c7; color:#d97706;"><i class="ri-time-line"></i> Pending</span>
                            <?php else: ?>
                                <span style="text-transform: capitalize; font-weight: 600; color: #4f46e5;"><?= $row['role'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['telegram_chat_id'] ?? '<em class="text-muted">Belum ada</em>' ?></td>
                        <td>
                            <?php if(!empty($row['telegram_chat_id'])): ?>
                                <span class="status-badge status-completed"><i class="ri-telegram-fill"></i> Terhubung</span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><i class="ri-error-warning-line"></i> Belum Start</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan'): ?>
                                <div style="display:flex; gap:5px; align-items:center;">
                                    <form method="POST" style="display:flex; gap:5px; align-items:center; margin:0;">
                                        <input type="hidden" name="target_id" value="<?= $row['id'] ?>">
                                        <select name="assign_role_value" style="padding: 4px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #cbd5e1; outline:none;">
                                            <option value="staf" <?= $row['role'] == 'staf' ? 'selected' : '' ?>>Staf</option>
                                            <option value="pimpinan" <?= $row['role'] == 'pimpinan' ? 'selected' : '' ?>>Pimpinan</option>
                                        </select>
                                        <button type="submit" name="assign_role" style="padding: 5px 8px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;"><i class="ri-check-line"></i> Update</button>
                                    </form>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete_user" style="padding: 5px 8px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Hapus User"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: #718096;">
                            Tidak ada staf atau koneksi DB gagal.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tambah Staf -->
<div id="stafModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('stafModal').style.display='none'">&times;</span>
        <div class="modal-header">
            <h2>Tambah Anggota Tim Baru</h2>
            <p>User yang ditambahkan dapat menggunakan bot Telegram setelah melakukan /start.</p>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username unik" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>
            <div class="form-group">
                <label>Role / Jabatan</label>
                <select name="role" required>
                    <option value="staf">Staf (Penerima Tugas)</option>
                    <option value="pimpinan">Pimpinan (Pemberi Tugas)</option>
                </select>
            </div>
            <button type="submit" name="tambah_user" class="btn-primary w-100">
                <i class="ri-save-line"></i> Simpan Data User
            </button>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
