<?php
require_once 'config.php';
require_once 'telegram_functions.php';

// Pastikan skrip tidak bisa dijalankan oleh user yang belum login kecuali lewat cron/CLI
// Menggunakan parameter key rahasia agar aman
if (php_sapi_name() !== 'cli') {
    if(!isset($_GET['key']) || $_GET['key'] !== 'RAHASIA123') {
        die("Akses Ditolak.");
    }
}

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil semua tugas yang berstatus pending atau on_progress, yang stafnya punya telegram_chat_id
$query = "
    SELECT u.id, u.username, u.telegram_chat_id, t.id as task_id, t.pesan_teks, t.status, t.created_at
    FROM users u
    JOIN tasks t ON u.id = t.receiver_id
    WHERE t.status IN ('pending', 'on_progress') 
      AND u.telegram_chat_id IS NOT NULL 
      AND u.telegram_chat_id != ''
      AND t.created_at <= (NOW() - INTERVAL 1 HOUR)
    ORDER BY u.id ASC, t.created_at ASC
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    // Kelompokkan tugas berdasarkan chat_id staf
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $chat_id = $row['telegram_chat_id'];
        if (!isset($reminders[$chat_id])) {
            $reminders[$chat_id] = [
                'username' => $row['username'],
                'tasks' => []
            ];
        }
        $reminders[$chat_id]['tasks'][] = $row;
    }

    $sukses = 0;
    // Kirim pesan reminder ke masing-masing staf
    foreach ($reminders as $chat_id => $data) {
        $msg = "⏰ <b>PENGINGAT OTOMATIS (REMINDER)</b>\n\n";
        $msg .= "Halo <b>" . $data['username'] . "</b>, Anda memiliki <b>" . count($data['tasks']) . " tugas</b> yang belum diselesaikan:\n\n";
        
        $count = 1;
        $buttons = [];
        
        foreach ($data['tasks'] as $task) {
            $status_str = $task['status'] == 'pending' ? '🕒 Belum Diterima' : '⏳ Sedang Dikerjakan';
            $msg .= "<b>$count. Tugas #" . $task['task_id'] . "</b> ($status_str)\n";
            $msg .= "<i>\"" . htmlspecialchars(substr($task['pesan_teks'], 0, 50)) . (strlen($task['pesan_teks']) > 50 ? "..." : "") . "\"</i>\n\n";
            $count++;
            
            // Tambahkan tombol aksi untuk 3 tugas teratas agar layar tidak kepenuhan tombol
            if (count($buttons) < 3) {
                if ($task['status'] == 'pending') {
                    $buttons[] = [["text" => "Terima #" . $task['task_id'] . " 🎯", "callback_data" => "accept_" . $task['task_id']]];
                } else {
                    $buttons[] = [["text" => "Selesaikan #" . $task['task_id'] . " ✅", "callback_data" => "complete_" . $task['task_id']]];
                }
            }
        }
        
        $msg .= "Harap segera mengecek dan menyelesaikannya. Semangat! 💪\n*(Abaikan pesan ini jika sudah diselesaikan)*";
        
        $reply_markup = null;
        if (!empty($buttons)) {
            $reply_markup = json_encode(["inline_keyboard" => $buttons]);
        }
        
        sendMessage($chat_id, $msg, $bot_token, $reply_markup);
        
        // Throttling: Beri jeda 0.1 detik untuk menghindari Telegram API Rate Limit (429 Too Many Requests)
        usleep(100000); 
        $sukses++;
    }
    
    echo "<h1>Reminder Sukses Dieksekusi</h1>";
    echo "<p>Pesan pengingat telah dikirim ke $sukses staf yang memiliki tanggungan tugas.</p>";
} else {
    echo "<h1>Tidak Perlu Reminder</h1>";
    echo "<p>Semua tugas sudah bersih atau tidak ada staf yang terkait.</p>";
}
?>
