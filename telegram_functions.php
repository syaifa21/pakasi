<?php
// Fungsi untuk mengirim Teks Biasa
function sendMessage($chat_id, $text, $bot_token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if($reply_markup) $data['reply_markup'] = $reply_markup;
    return sendCurl($url, $data);
}

// Fungsi untuk mengirim Foto + Caption
function sendPhoto($chat_id, $photo, $caption, $bot_token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot$bot_token/sendPhoto";
    $data = [
        'chat_id' => $chat_id,
        'photo' => $photo, // Bisa berupa file_id (forwarding) atau URL (dari web)
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    if($reply_markup) $data['reply_markup'] = $reply_markup;
    return sendCurl($url, $data);
}

// Fungsi untuk mengirim Dokumen + Caption (Via URL atau file_id)
function sendDocument($chat_id, $document, $caption, $bot_token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot$bot_token/sendDocument";
    $data = [
        'chat_id' => $chat_id,
        'document' => $document, // Bisa berupa file_id atau URL file
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    if($reply_markup) $data['reply_markup'] = $reply_markup;
    return sendCurl($url, $data);
}

// Fungsi untuk mengirim Dokumen Lokal (Langsung dari server/upload)
function sendDocumentLocal($chat_id, $file_path, $caption, $bot_token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot$bot_token/sendDocument";
    $data = [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($file_path)),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    if($reply_markup) $data['reply_markup'] = $reply_markup;
    return sendCurl($url, $data);
}

// Fungsi inti (Worker cURL)
function sendCurl($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Jika Anda memakai localhost/XAMPP yang tidak memiliki sertifikat SSL yang valid, buka komen di bawah:
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $result = curl_exec($ch);
    if(curl_errno($ch)){
        error_log('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

// Fungsi untuk download file dari Telegram ke server lokal
function downloadTelegramFile($file_id, $bot_token) {
    // 1. Dapatkan file_path dari Telegram
    $url = "https://api.telegram.org/bot$bot_token/getFile?file_id=" . urlencode($file_id);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (!$data || !$data['ok'] || !isset($data['result']['file_path'])) {
        return null;
    }
    
    $tg_file_path = $data['result']['file_path'];
    $ext = pathinfo($tg_file_path, PATHINFO_EXTENSION);
    if (empty($ext)) $ext = 'bin';
    
    // 2. Download file
    $download_url = "https://api.telegram.org/file/bot$bot_token/$tg_file_path";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $file_content = curl_exec($ch);
    curl_close($ch);
    
    if (empty($file_content)) return null;
    
    // 3. Simpan ke folder uploads/
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $filename = time() . '_' . uniqid() . '.' . $ext;
    $local_path = $upload_dir . $filename;
    file_put_contents($local_path, $file_content);
    
    return 'uploads/' . $filename;
}
?>

