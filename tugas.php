<?php
require_once 'header.php';

// Handle Delete Tugas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task']) && !$conn->connect_error) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan') {
        $delete_id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            echo "<script>window.location='tugas.php?success_delete=1';</script>";
            exit;
        }
    }
}

// Handle Update Tugas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_task']) && !$conn->connect_error) {
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan') {
        $edit_id = (int)$_POST['edit_id'];
        $pesan_teks = $_POST['pesan_teks'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE tasks SET pesan_teks = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssi", $pesan_teks, $status, $edit_id);
        if ($stmt->execute()) {
            echo "<script>window.location='tugas.php?success_edit=1';</script>";
            exit;
        }
    }
}

// Handle Kirim Laporan oleh Staf
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kirim_laporan']) && !$conn->connect_error) {
    if ($_SESSION['role'] == 'staf') {
        $task_id = (int)$_POST['laporan_task_id'];
        $laporan_teks = $_POST['laporan_teks'];
        $file_url = null;
        
        // Cek apakah ini tugas milik staf tersebut
        $check = $conn->query("SELECT id FROM tasks WHERE id = $task_id AND receiver_id = " . $_SESSION['user_id']);
        if ($check && $check->num_rows > 0) {
            // Upload file jika ada
            if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == 0) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $filename = time() . "_laporan_" . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['file_laporan']['name']));
                if (move_uploaded_file($_FILES['file_laporan']['tmp_name'], $upload_dir . $filename)) {
                    $base_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir . "/" . $upload_dir . $filename;
                }
            }
            
            // Karena laporan_teks dan laporan_file_url mungkin belum ada di tabel pada instalasi lama, kita perlu pastikan error tidak terjadi
            // Kita akan update status ke completed, dan mencoba update laporan jika kolom ada.
            // Gunakan prepared statement yang disesuaikan
            $stmt = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP, laporan_teks = ?, laporan_file_url = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $laporan_teks, $file_url, $task_id);
                $stmt->execute();
            } else {
                // Fallback jika kolom belum dibuat oleh admin
                $stmt_fallback = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_fallback->bind_param("i", $task_id);
                $stmt_fallback->execute();
            }
            echo "<script>window.location='tugas.php?success_laporan=1';</script>";
            exit;
        }
    }
}

// Ambil data tasks (Semua)
$tasks_query = "
    SELECT 
        t.id, t.pesan_teks, t.status, t.created_at, t.completed_at, t.file_url, t.receiver_id,
        t.laporan_teks, t.laporan_file_url,
        p.username as pimpinan_name, s.username as staf_name,
        TIMEDIFF(t.completed_at, t.created_at) as durasi
    FROM tasks t
    LEFT JOIN users p ON t.sender_id = p.id
    LEFT JOIN users s ON t.receiver_id = s.id
    ORDER BY t.created_at DESC
";
$tasks_result = !$conn->connect_error ? $conn->query($tasks_query) : null;
?>

<div class="page-header">
    <div>
        <h1>Manajemen Tugas</h1>
        <p>Lihat semua log tugas yang pernah dibuat.</p>
        <?php if(isset($_GET['success_delete'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">Tugas berhasil dihapus!</div>
        <?php endif; ?>
        <?php if(isset($_GET['success_edit'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">Tugas berhasil diperbarui!</div>
        <?php endif; ?>
        <?php if(isset($_GET['success_laporan'])): ?>
            <div style="color: green; margin-top: 10px; font-weight: bold;">Laporan berhasil dikirim dan tugas diselesaikan!</div>
        <?php endif; ?>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Semua Riwayat Tugas</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Tugas</th>
                    <th>Pimpinan</th>
                    <th>Ditugaskan Ke</th>
                    <th>Detail Pesan</th>
                    <th>Dikirim Pada</th>
                    <th>Selesai Pada</th>
                    <th>Durasi</th>
                    <th>Status</th>
                    <th>Aksi</th>
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
                                <?= $row['staf_name'] ?? 'ID: '.$row['receiver_id'] ?>
                            </div>
                        </td>
                        <td class="message-cell">
                            <strong>Instruksi:</strong><br>
                            <?= htmlspecialchars($row['pesan_teks']) ?>
                            <?php if(!empty($row['file_url'])): ?>
                                <br><a href="<?= htmlspecialchars($row['file_url']) ?>" target="_blank" style="font-size:0.8rem; color:#3b82f6; text-decoration:none;"><i class="ri-attachment-line"></i> Lampiran Pimpinan</a>
                            <?php endif; ?>
                            
                            <?php if(!empty($row['laporan_teks']) || !empty($row['laporan_file_url'])): ?>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1;">
                                    <strong>Hasil Laporan:</strong><br>
                                    <?= htmlspecialchars($row['laporan_teks'] ?? '') ?>
                                    <?php if(!empty($row['laporan_file_url'])): ?>
                                        <br><a href="<?= htmlspecialchars($row['laporan_file_url']) ?>" target="_blank" style="font-size:0.8rem; color:#10b981; text-decoration:none;"><i class="ri-attachment-line"></i> Lampiran Laporan Staf</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td><?= $row['completed_at'] ? date('d/m/Y H:i', strtotime($row['completed_at'])) : '-' ?></td>
                        <td>
                            <?php if($row['status'] == 'completed'): ?>
                                <span class="duration-badge">
                                    <i class="ri-timer-flash-line"></i> <?= $row['durasi'] ?>
                                </span>
                            <?php elseif($row['status'] == 'on_progress'): ?>
                                <span class="text-muted" style="color:#8b5cf6;"><i class="ri-timer-line"></i> Aktif...</span>
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
                        <td>
                            <div style="display:flex; gap:5px;">
                                <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'pimpinan'): ?>
                                    <button onclick="editTask(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['pesan_teks'])) ?>', '<?= $row['status'] ?>')" style="padding: 5px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Edit Tugas"><i class="ri-edit-line"></i></button>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete_task" style="padding: 5px 8px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Hapus Tugas"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if($_SESSION['role'] == 'staf' && $row['receiver_id'] == $_SESSION['user_id'] && $row['status'] != 'completed'): ?>
                                    <button onclick="laporTask(<?= $row['id'] ?>)" style="padding: 5px 8px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" title="Kirim Laporan Bukti"><i class="ri-send-plane-fill"></i> Kirim Laporan</button>
                                <?php elseif($_SESSION['role'] == 'staf' && $row['status'] == 'completed'): ?>
                                    <span style="font-size: 0.8rem; color: #64748b;"><i class="ri-check-double-line"></i> Tuntas</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: #718096;">
                            Belum ada data tugas.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Edit Tugas -->
<div id="editTaskModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('editTaskModal').style.display='none'">&times;</span>
        <div class="modal-header">
            <h2>Edit Tugas</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>Giat / Rincian Pesan</label>
                <textarea name="pesan_teks" id="edit_pesan_teks" rows="4" class="form-control" required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;"></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" required class="form-control" style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <option value="pending">Pending</option>
                    <option value="on_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <button type="submit" name="edit_task" class="btn-primary w-100">
                <i class="ri-save-line"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<!-- Modal Form Kirim Laporan (Staf) -->
<div id="laporTaskModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('laporTaskModal').style.display='none'">&times;</span>
        <div class="modal-header">
            <h2>Kirim Laporan / Bukti Selesai</h2>
            <p>Tugas akan ditandai selesai setelah Anda mengirim laporan.</p>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="laporan_task_id" id="laporan_task_id">
            <div class="form-group">
                <label>Keterangan / Hasil Laporan</label>
                <textarea name="laporan_teks" rows="4" class="form-control" placeholder="Tuliskan hasil kerja Anda..." required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;"></textarea>
            </div>
            <div class="form-group">
                <label>Lampirkan Bukti (Foto/Dokumen)</label>
                <input type="file" name="file_laporan" class="form-control" style="padding: 0.8rem; background: #fff; border: 1px solid #e2e8f0; width: 100%; border-radius: 8px;">
            </div>
            <button type="submit" name="kirim_laporan" class="btn-primary w-100" style="background: #10b981;">
                <i class="ri-check-double-line"></i> Selesaikan Tugas
            </button>
        </form>
    </div>
</div>

<script>
function editTask(id, pesan, status) {
    document.getElementById('editTaskModal').style.display = 'flex';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_pesan_teks').value = pesan;
    document.getElementById('edit_status').value = status;
}

function laporTask(id) {
    document.getElementById('laporTaskModal').style.display = 'flex';
    document.getElementById('laporan_task_id').value = id;
}
</script>

<?php require_once 'footer.php'; ?>
