<?php
// ১. ডাটাবেস কনফিগারেশন
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Parvez@9810#'); // আপনার ডাটাবেস পাসওয়ার্ড
define('DB_NAME', 'switch_monitor');

// ২. ডাটাবেস কানেকশন
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ৩. টেলিগ্রাম সেটিংস
$tg_token = "";
$tg_chat_id = "";
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if($check_table && $check_table->num_rows > 0) {
    $res = $conn->query("SELECT * FROM settings");
    while($row = $res->fetch_assoc()) {
        if($row['setting_key'] == 'tg_token') $tg_token = $row['setting_value'];
        if($row['setting_key'] == 'tg_chat_id') $tg_chat_id = $row['setting_value'];
    }
}
define('TELEGRAM_TOKEN', $tg_token);
define('TELEGRAM_CHAT_ID', $tg_chat_id);

// ৪. টেলিগ্রাম মেসেজ ফাংশন
function sendTelegramMessage($message, $parse_mode = 'HTML') {
    if (TELEGRAM_TOKEN == "" || TELEGRAM_CHAT_ID == "") return false;
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result ? true : false;
}

// গ্লোবাল সেটিংস
$site_title = "BDCOM Network Monitor";
$refresh_rate = 10;
$default_community = "public";
date_default_timezone_set('Asia/Dhaka');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>