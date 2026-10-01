<?php
session_start();
// Konfigurasi Database Utama
$db_host = 'localhost';
$db_user = 'seedlocm_ciremai';
$db_pass = 'ciremai210103';
$db_name = 'seedlocm_ciremai';
$bot_token = '8554161366:AAEXYZI1xL2a3A2X-PpSxiqEtb7_aWOGJKM';

date_default_timezone_set('Asia/Jakarta');
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$conn->connect_error) {
    $conn->query("SET time_zone = '+07:00'");
}
// Fungsi Proteksi Halaman
function checkLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}
?>