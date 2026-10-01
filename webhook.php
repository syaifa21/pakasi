<?php
// Konfigurasi Database disatukan di config.php
require_once 'config.php';

// Jika gagal konek, hentikan eksekusi webhook
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

// Sertakan file fungsi telegram di bawah
require_once 'telegram_functions.php';

// Menerima input JSON dari API Telegram
$update = json_decode(file_get_contents("php://input"), TRUE);

// ==========================================
// 0. Handle Inline Keyboard (Tombol Callback)
// ==========================================
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    
    if (strpos($data, 'accept_') === 0) {
        $task_id = (int)str_replace('accept_', '', $data);
        
        $stmt_update = $conn->prepare("UPDATE tasks SET status = 'on_progress' WHERE id = ? AND status = 'pending'");
        $stmt_update->bind_param("i", $task_id);
        $stmt_update->execute();
        
        if ($stmt_update->affected_rows > 0) {
            $reply_markup = json_encode([
                "inline_keyboard" => [[["text" => "Kirim Laporan / Bukti 📝", "callback_data" => "complete_" . $task_id]]]
            ]);
            sendMessage($chat_id, "⏳ Tugas <b>#$task_id</b> DITERIMA!\nTimer mulai berjalan. Jika selesai, balas pesan ini dengan <b>Lampiran Bukti (Foto/File)</b> atau tekan tombol di bawah.", $bot_token, $reply_markup);
            
            // Format SOP: [SAYA TERIMA & PROSES]
            $res = $conn->query("SELECT t.kode_tugas, u.telegram_chat_id as sender_chat_id, r.username as staf_name FROM tasks t JOIN users u ON t.sender_id = u.id JOIN users r ON t.receiver_id = r.id WHERE t.id = $task_id");
            if ($res && $res->num_rows > 0) {
                $task_info = $res->fetch_assoc();
                if(!empty($task_info['sender_chat_id'])) {
                    $jam = date('H:i') . " WIB";
                    $kode = $task_info['kode_tugas'] ? $task_info['kode_tugas'] : "#$task_id";
                    $msg = "■ <b>[SAYA TERIMA & PROSES]</b>\n";
                    $msg .= "Kode: $kode\n";
                    $msg .= "Tim Bergerak: " . $task_info['staf_name'] . " ($jam)";
                    sendMessage($task_info['sender_chat_id'], $msg, $bot_token);
                }
            }
        } else {
            sendMessage($chat_id, "⚠️ Tugas #$task_id sudah diterima sebelumnya atau tidak ditemukan.", $bot_token);
        }
    }
    else if (strpos($data, 'complete_') === 0) {
        $task_id = (int)str_replace('complete_', '', $data);
        
        // Selesaikan tugas secara langsung
        $stmt_update = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update->bind_param("i", $task_id);
        $stmt_update->execute();
        
        $reply_markup = json_encode([
            "force_reply" => true,
            "input_field_placeholder" => "Kirim foto/laporan..."
        ]);
        
        sendMessage($chat_id, "✅ Tugas #$task_id telah diselesaikan!\n\n(Opsional) Jika Anda ingin melampirkan foto/dokumen bukti, silakan balas (reply) pesan ini.", $bot_token, $reply_markup);
        
        // Beritahu pimpinan bahwa tugas selesai tanpa lampiran
        $res = $conn->query("SELECT t.kode_tugas, u.telegram_chat_id as sender_chat_id, r.username as staf_name FROM tasks t JOIN users u ON t.sender_id = u.id JOIN users r ON t.receiver_id = r.id WHERE t.id = $task_id");
        if ($res && $res->num_rows > 0) {
            $task_info = $res->fetch_assoc();
            if(!empty($task_info['sender_chat_id'])) {
                $jam = date('H:i') . " WIB";
                $kode = $task_info['kode_tugas'] ? $task_info['kode_tugas'] : "#$task_id";
                $msg = "■ <b>[TUGAS SELESAI]</b>\n";
                $msg .= "Kode: $kode\n";
                $msg .= "Oleh: " . $task_info['staf_name'] . " ($jam)\n";
                $msg .= "<i>(Diselesaikan via Tombol)</i>";
                sendMessage($task_info['sender_chat_id'], $msg, $bot_token);
            }
        }
        
        $url = "https://api.telegram.org/bot$bot_token/answerCallbackQuery";
        $cb_data = ['callback_query_id' => $callback['id'], 'text' => 'Tugas Diselesaikan!'];
        sendCurl($url, $cb_data);
        exit;
    }
    else if (strpos($data, 'view_') === 0) {
        $task_id = (int)str_replace('view_', '', $data);
        
        $res = $conn->query("SELECT t.*, u.username as staf_name, p.username as pimpinan_name, u.telegram_chat_id as receiver_chat_id FROM tasks t JOIN users u ON t.receiver_id = u.id JOIN users p ON t.sender_id = p.id WHERE t.id = $task_id");
        if ($res && $res->num_rows > 0) {
            $t = $res->fetch_assoc();
            $status_text = $t['status'] == 'pending' ? '🕒 Belum Diterima' : ($t['status'] == 'on_progress' ? '⏳ Sedang Dikerjakan' : '✅ Selesai');
            $kode = $t['kode_tugas'] ? $t['kode_tugas'] : "#" . $t['id'];
            
            $msg = "📝 <b>DETAIL TUGAS $kode</b>\n";
            $msg .= "Dari: " . $t['pimpinan_name'] . "\n";
            $msg .= "Kepada: " . $t['staf_name'] . "\n";
            $msg .= "Status: <b>$status_text</b>\n";
            $msg .= "Dibuat: " . date('d/m/Y H:i', strtotime($t['created_at'])) . "\n\n";
            $msg .= "<b>Instruksi:</b>\n" . htmlspecialchars($t['pesan_teks']) . "\n";
            
            $buttons = [];
            
            // Dapatkan user_id dari yang menekan tombol
            $chat_id_safe = $conn->real_escape_string($chat_id);
            $res_clicker = $conn->query("SELECT id FROM users WHERE telegram_chat_id = '$chat_id_safe'");
            $clicker_user_id = ($res_clicker && $res_clicker->num_rows > 0) ? $res_clicker->fetch_assoc()['id'] : 0;
            
            // Show action buttons ONLY if the user clicking is the Receiver (Staf)
            if ($t['receiver_id'] == $clicker_user_id) {
                if ($t['status'] == 'pending') {
                    $buttons[] = [["text" => "Terima Tugas 🎯", "callback_data" => "accept_" . $t['id']]];
                } else if ($t['status'] == 'on_progress') {
                    $buttons[] = [["text" => "Kirim Laporan / Bukti 📝", "callback_data" => "complete_" . $t['id']]];
                }
            } else {
                $msg .= "\n<i>(Akun Telegram ini terkait dengan User ID $clicker_user_id. Karena Anda bukan penerima tugas ini, maka tombol tindakan disembunyikan. Hanya " . $t['staf_name'] . " yang dapat mengambil tindakan.)</i>\n";
            }
            
            $reply_markup = empty($buttons) ? "" : json_encode(["inline_keyboard" => $buttons]);
            sendMessage($chat_id, $msg, $bot_token, $reply_markup);
        } else {
            sendMessage($chat_id, "⚠️ Tugas #$task_id tidak ditemukan.", $bot_token);
        }
        
        $url = "https://api.telegram.org/bot$bot_token/answerCallbackQuery";
        $cb_data = ['callback_query_id' => $callback['id']];
        sendCurl($url, $cb_data);
        exit;
    }
    
    // Hentikan loading di tombol Telegram
    $url = "https://api.telegram.org/bot$bot_token/answerCallbackQuery";
    $cb_data = ['callback_query_id' => $callback['id']];
    sendCurl($url, $cb_data);
    exit;
}

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = isset($message['text']) ? $message['text'] : '';

    // ==========================================
    // 1. Fitur Link Akun via /start [username] & Keyboard Tetap
    // ==========================================
    if (strpos($text, '/start') === 0) {
        $username = trim(substr($text, 7)); // Ambil teks setelah "/start "
        
        $reply_markup = json_encode([
            "keyboard" => [
                [["text" => "📋 Dashboard Tugas"]]
            ],
            "resize_keyboard" => true,
            "is_persistent" => true
        ]);

        if (!empty($username)) {
            $stmt = $conn->prepare("UPDATE users SET telegram_chat_id = ? WHERE username = ?");
            $stmt->bind_param("ss", $chat_id, $username);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                sendMessage($chat_id, "✅ Berhasil! Chat ID Anda telah ditautkan dengan akun <b>$username</b>.\n\nGunakan tombol menu di bawah untuk mulai.", $bot_token, $reply_markup);
            } else {
                sendMessage($chat_id, "❌ Gagal. Username tidak ditemukan di sistem.", $bot_token);
            }
            $stmt->close();
        } else {
            sendMessage($chat_id, "🤖 <b>Selamat Datang di Bot Ciremai Hiji</b>\n\nGunakan tombol di bawah ini untuk melihat daftar tugas.", $bot_token, $reply_markup);
        }
        exit;
    }

    // ==========================================
    // 2. Fitur Telegram Dashboard (/dashboard atau /tugas)
    // ==========================================
    if ($text === '/dashboard' || $text === '/tugas' || $text === '/list_tugas' || $text === '📋 Dashboard Tugas') {
        $chat_id_safe = $conn->real_escape_string($chat_id);
        $res = $conn->query("SELECT id, username, role FROM users WHERE telegram_chat_id = '$chat_id_safe'");
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $user_id = $user['id'];
            
            $base_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $dashboard_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir;
            
            // 1. Tampilkan Tugas yang DITERIMA (Sebagai Staf / Pekerja)
            $res_tasks = $conn->query("SELECT id, pesan_teks, status, created_at FROM tasks WHERE receiver_id = $user_id AND status IN ('pending', 'on_progress') ORDER BY created_at ASC LIMIT 10");
            if ($res_tasks && $res_tasks->num_rows > 0) {
                $msg = "📋 <b>DASHBOARD TUGAS ANDA</b>\n\nSilakan klik salah satu tugas di bawah ini untuk melihat detail dan mengambil tindakan:\n\n";
                $buttons = [];
                while($t = $res_tasks->fetch_assoc()) {
                    $status_icon = $t['status'] == 'pending' ? '🕒' : '⏳';
                    $snippet = mb_substr($t['pesan_teks'], 0, 30) . (mb_strlen($t['pesan_teks']) > 30 ? "..." : "");
                    $buttons[] = [["text" => "$status_icon #" . $t['id'] . " - " . htmlspecialchars($snippet), "callback_data" => "view_" . $t['id']]];
                }
                $buttons[] = [["text" => "🌐 Buka Web Dashboard", "url" => $dashboard_link]];
                $reply_markup = json_encode(["inline_keyboard" => $buttons]);
                sendMessage($chat_id, $msg, $bot_token, $reply_markup);
            } elseif ($user['role'] == 'staf') {
                $reply_markup = json_encode([
                    "inline_keyboard" => [[["text" => "🌐 Buka Web Dashboard", "url" => $dashboard_link]]]
                ]);
                sendMessage($chat_id, "🎉 <b>Luar Biasa!</b>\nTidak ada tugas yang tertunda. Anda sudah menyelesaikan semuanya.", $bot_token, $reply_markup);
            }
            
            // 2. Tampilkan Ringkasan Tugas yang DIKIRIM (Sebagai Pimpinan/Admin)
            if ($user['role'] == 'pimpinan' || $user['role'] == 'admin') {
                $res_tasks_sent = $conn->query("SELECT status, count(*) as total FROM tasks WHERE sender_id = $user_id GROUP BY status");
                $pending = 0; $on_progress = 0; $completed = 0;
                if ($res_tasks_sent) {
                    while($t = $res_tasks_sent->fetch_assoc()) {
                        if ($t['status'] == 'pending') $pending = $t['total'];
                        if ($t['status'] == 'on_progress') $on_progress = $t['total'];
                        if ($t['status'] == 'completed') $completed = $t['total'];
                    }
                }
                $msg2 = "📊 <b>RINGKASAN TUGAS (PIMPINAN)</b>\n\n";
                $msg2 .= "📝 <b>Belum Diterima (Pending)</b> : $pending\n";
                $msg2 .= "⏳ <b>Sedang Dikerjakan</b> : $on_progress\n";
                $msg2 .= "✅ <b>Selesai (Completed)</b>: $completed\n\n";
                
                $res_active = $conn->query("
                    SELECT t.id, t.pesan_teks, t.status, u.username as staf_name 
                    FROM tasks t JOIN users u ON t.receiver_id = u.id 
                    WHERE t.sender_id = $user_id AND t.status IN ('pending', 'on_progress') 
                    ORDER BY t.created_at ASC LIMIT 5
                ");
                
                $buttons2 = [];
                if ($res_active && $res_active->num_rows > 0) {
                    $msg2 .= "📋 <b>Klik tombol tugas di bawah untuk melihat detail:</b>\n";
                    while($t = $res_active->fetch_assoc()) {
                        $icon = $t['status'] == 'pending' ? '🕒' : '⏳';
                        $snippet = mb_substr($t['pesan_teks'], 0, 20) . (mb_strlen($t['pesan_teks']) > 20 ? "..." : "");
                        $buttons2[] = [["text" => "$icon #" . $t['id'] . " (" . $t['staf_name'] . ") - " . htmlspecialchars($snippet), "callback_data" => "view_" . $t['id']]];
                    }
                } else {
                    $msg2 .= "<i>Tidak ada tugas aktif saat ini.</i>\n\n";
                }
                
                $buttons2[] = [["text" => "🌐 Buka Web Dashboard", "url" => $dashboard_link]];
                $reply_markup2 = json_encode(["inline_keyboard" => $buttons2]);
                sendMessage($chat_id, $msg2, $bot_token, $reply_markup2);
            }
        } else {
            sendMessage($chat_id, "⚠️ Akun Telegram Anda belum ditautkan. Ketik /start [username] untuk menautkan akun.", $bot_token);
        }
        exit;
    }

    // ==========================================
    // 3. Logika 2-Way Bot (Staf Membalas Pimpinan)
    // ==========================================
    
    // Cari apakah pengirim pesan ini ada di tabel users
    $chat_id_safe = $conn->real_escape_string($chat_id);
    $res_user = $conn->query("SELECT id FROM users WHERE telegram_chat_id = '$chat_id_safe'");
    
    if ($res_user->num_rows > 0) {
        $user_row = $res_user->fetch_assoc();
        $user_id = $user_row['id'];
        $user_id_safe = (int)$user_id;
        
        // Deteksi jika user sedang membalas (reply) pesan dari bot yang mengandung ID tugas
        $forced_task_id = null;
        if (isset($message['reply_to_message']) && isset($message['reply_to_message']['text'])) {
            // Regex lebih longgar, mencari #123 di teks reply
            if (preg_match('/#(\d+)/', $message['reply_to_message']['text'], $matches)) {
                $forced_task_id = (int)$matches[1];
            }
        }
        
        $where_clause = "t.receiver_id = $user_id_safe";
        if ($forced_task_id) {
            $where_clause .= " AND t.id = $forced_task_id";
        } else {
            $where_clause .= " AND t.status IN ('on_progress', 'pending')";
        }
        
        // Cari tugas
        $res_task = $conn->query("
            SELECT t.id, t.kode_tugas, t.created_at, t.status, u.telegram_chat_id as sender_chat_id 
            FROM tasks t 
            JOIN users u ON t.sender_id = u.id 
            WHERE $where_clause 
            ORDER BY FIELD(t.status, 'on_progress', 'pending', 'completed'), t.created_at ASC LIMIT 1
        ");
        
        if ($res_task && $res_task->num_rows > 0) {
            $task = $res_task->fetch_assoc();
            $task_id = $task['id'];
            $sender_chat_id = $task['sender_chat_id'];
            $kode = $task['kode_tugas'] ? $task['kode_tugas'] : "#$task_id";
            
            // ==========================================
            // 3. Penanganan Media (Teks, Foto, Dokumen)
            // ==========================================
            $caption = isset($message['caption']) ? $message['caption'] : '';
            $user_text = !empty($text) ? $text : $caption;
            $user_text = $user_text ? htmlspecialchars($user_text) : "(Melampirkan File/Foto)";
            
            if ($task['status'] === 'completed') {
                // Jika tugas sudah selesai, jadikan ini sebagai tambahan lampiran
                $forward_text = "■ <b>[TAMBAHAN LAMPIRAN / LAPORAN]</b>\nKode: $kode\nKeterangan: " . $user_text . "\n";
                if (isset($message['photo'])) {
                    $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
                    sendPhoto($sender_chat_id, $file_id, $forward_text, $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'foto');
                    sendMessage($chat_id, "✅ Laporan/bukti tambahan untuk tugas <b>#$task_id</b> telah diteruskan ke pimpinan.", $bot_token);
                } elseif (isset($message['document'])) {
                    $file_id = $message['document']['file_id'];
                    sendDocument($sender_chat_id, $file_id, $forward_text, $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'dokumen');
                    sendMessage($chat_id, "✅ Dokumen tambahan untuk tugas <b>#$task_id</b> telah diteruskan ke pimpinan.", $bot_token);
                } elseif (isset($message['video'])) {
                    $file_id = $message['video']['file_id'];
                    sendDocument($sender_chat_id, $file_id, $forward_text, $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'video');
                    sendMessage($chat_id, "✅ Video tambahan untuk tugas <b>#$task_id</b> telah diteruskan ke pimpinan.", $bot_token);
                } elseif (!empty($text)) {
                    sendMessage($sender_chat_id, $forward_text, $bot_token);
                    saveReport($conn, $task_id, $user_id, $text, null, 'teks');
                    sendMessage($chat_id, "✅ Pesan laporan untuk tugas <b>#$task_id</b> telah diteruskan ke pimpinan.", $bot_token);
                }
            } else {
                $created_at = strtotime($task['created_at']);
                $completed_at = time();
                $diff = $completed_at - $created_at;
                $hours = floor($diff / 3600);
                $minutes = floor(($diff / 60) % 60);
                
                $durasi_str = "";
                if ($hours > 0) $durasi_str .= "$hours jam ";
                if ($minutes > 0) $durasi_str .= "$minutes menit";
                if (empty($durasi_str)) $durasi_str = "Kurang dari 1 menit";
                
                $forward_text = "■ <b>[TUGAS TUNTAS]</b>\n";
                $forward_text .= "Kode: $kode\n";
                $forward_text .= "Hasil: " . $user_text . "\n";
                $forward_text .= "Durasi: " . trim($durasi_str) . "\n";
                $forward_text .= "Bukti: ";

                if (isset($message['photo'])) {
                    $file_id = $message['photo'][count($message['photo']) - 1]['file_id'];
                    sendPhoto($sender_chat_id, $file_id, $forward_text . "Terlampir Foto", $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'foto');
                } 
                elseif (isset($message['document'])) {
                    $file_id = $message['document']['file_id'];
                    sendDocument($sender_chat_id, $file_id, $forward_text . "Terlampir Dokumen", $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'dokumen');
                } 
                elseif (isset($message['video'])) {
                    $file_id = $message['video']['file_id'];
                    sendDocument($sender_chat_id, $file_id, $forward_text . "Terlampir Video", $bot_token);
                    $local_file = downloadTelegramFile($file_id, $bot_token);
                    saveReport($conn, $task_id, $user_id, $user_text, $local_file, 'video');
                }
                elseif (!empty($text)) {
                    sendMessage($sender_chat_id, $forward_text . "Tanpa Lampiran", $bot_token);
                    saveReport($conn, $task_id, $user_id, $text, null, 'teks');
                }
                
                // ==========================================
                // 4. Update Status Tugas & Catat Waktu
                // ==========================================
                $stmt_update = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_update->bind_param("i", $task_id);
                $stmt_update->execute();
                
                // Notifikasi ke Staf bahwa pesan berhasil diteruskan
                sendMessage($chat_id, "✅ Pesan Anda telah diteruskan dan status tugas <b>#$task_id</b> menjadi Selesai.", $bot_token);
            }
        } else {
            // Cek apakah error query atau memang kosong
            if (!$res_task) {
                $err = $conn->error;
                sendMessage($chat_id, "⚠️ Terjadi kesalahan sistem (Database Error). Pastikan Pimpinan sudah klik Update Database. Error: $err", $bot_token);
            } else {
                if (isset($message['photo']) || isset($message['document']) || isset($message['video']) || !empty($text)) {
                    sendMessage($chat_id, "⚠️ Anda mengirim pesan/file, namun Anda tidak memiliki tugas aktif (Belum Diterima / Sedang Dikerjakan). Gunakan /dashboard untuk melihat tugas.", $bot_token);
                }
            }
        }
    } else {
        if (!empty($text) && strpos($text, '/') !== 0) {
            sendMessage($chat_id, "⚠️ Akun belum tertaut. Ketik /start [username]", $bot_token);
        }
    }
}

// ==========================================
// 5. Antarmuka Monitor Webhook (Jika Diakses via Browser / GET Request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Tampilkan UI Monitor (Hacker-style/Terminal/Modern look)
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Webhook Listener - TaskMonitor</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 3rem 1rem; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
            .monitor-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2.5rem; max-width: 650px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); position: relative; overflow: hidden; }
            .monitor-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #8b5cf6, #3b82f6, #10b981); }
            
            .header-flex { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;}
            .status-indicator { display: inline-flex; align-items: center; gap: 0.6rem; background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2); }
            .pulse { width: 10px; height: 10px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulse 2s infinite; }
            @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
            
            h1 { margin: 0 0 0.5rem 0; font-size: 1.8rem; color: #f8fafc; }
            p.desc { color: #94a3b8; line-height: 1.6; margin: 0; }
            
            .terminal { background: #020617; border-radius: 8px; padding: 1.5rem; margin: 2rem 0; border: 1px solid #1e293b; font-family: 'Fira Code', monospace; }
            .terminal-header { display: flex; gap: 6px; margin-bottom: 1rem; }
            .dot { width: 12px; height: 12px; border-radius: 50%; }
            .dot.r { background: #ef4444; } .dot.y { background: #f59e0b; } .dot.g { background: #10b981; }
            
            .log-line { margin-bottom: 0.75rem; font-size: 0.85rem; display: flex; gap: 1rem;}
            .log-time { color: #64748b; }
            .log-msg { color: #38bdf8; }
            .log-val { color: #a3e635; }
            
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;}
            .info-box { background: rgba(15, 23, 42, 0.5); border-radius: 12px; padding: 1.25rem; border: 1px solid #334155; }
            .info-box h3 { margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;}
            .info-box p { margin: 0; color: #f8fafc; font-size: 0.95rem; font-weight: 600; word-break: break-all;}
            
            .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #4f46e5; color: white; padding: 0.85rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; width: 100%;}
            .btn:hover { background: #4338ca; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);}
        </style>
    </head>
    <body>
        <div class="monitor-card">
            <div class="header-flex">
                <div>
                    <h1>Webhook Endpoint</h1>
                    <p class="desc">Listening for Telegram API POST requests...</p>
                </div>
                <div class="status-indicator">
                    <div class="pulse"></div>
                    ONLINE
                </div>
            </div>
            
            <div class="terminal">
                <div class="terminal-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                </div>
                <div class="log-line">
                    <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                    <span class="log-msg">SYSTEM:</span>
                    <span class="log-val">Initialization complete.</span>
                </div>
                <div class="log-line">
                    <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                    <span class="log-msg">DB_CONN:</span>
                    <span class="log-val">Connected successfully to '<?php echo htmlspecialchars($db_name); ?>'.</span>
                </div>
                <div class="log-line">
                    <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                    <span class="log-msg">BOT_TOKEN:</span>
                    <span class="log-val">Validated (***<?php echo substr($bot_token, -5); ?>).</span>
                </div>
                <div class="log-line">
                    <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                    <span class="log-msg">STATUS:</span>
                    <span class="log-val" style="color: #cbd5e1;">Awaiting incoming JSON payloads from Telegram...</span>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-box">
                    <h3>Request Method</h3>
                    <p style="color: #fbbf24;">GET (Browser View)</p>
                </div>
                <div class="info-box">
                    <h3>Payload Received</h3>
                    <p style="color: #f43f5e;">NULL</p>
                </div>
            </div>
            
            <div class="info-box" style="margin-bottom: 2rem;">
                <h3>Endpoint URL (Gunakan untuk SetWebhook)</h3>
                <p style="color: #60a5fa;">https://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></p>
            </div>
            
            <a href="index.php" class="btn"><i class="ri-arrow-left-line"></i> Kembali ke Dashboard Utama</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
