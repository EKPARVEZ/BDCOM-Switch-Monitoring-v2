<?php
require_once 'config.php';

$switches = $conn->query("SELECT * FROM switches");

while ($switch = $switches->fetch_assoc()) {
    $ip = $switch['ip_address'];
    $com = $switch['community'];
    $switch_id = $switch['id'];

    // SNMP দিয়ে পোর্টের বর্তমান স্ট্যাটাস আনা (1 = Up, 2 = Down)
    $portStatus = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.8");

    if ($portStatus) {
        foreach ($portStatus as $oid => $status) {
            $index = substr(strrchr($oid, "."), 1);
            $status = (int)$status;

            // ডাটাবেস থেকে আগের স্ট্যাটাস চেক করা
            $check = $conn->query("SELECT last_status FROM port_alerts WHERE switch_id = $switch_id AND port_index = '$index'");
            $row = $check->fetch_assoc();

            if ($row) {
                if ($row['last_status'] != $status) {
                    $msg = ($status == 1) ? "✅ Port $index is UP" : "❌ Port $index is DOWN";
                    $full_msg = "Device: " . $switch['name'] . " ($ip)\n" . $msg;
                    
                    sendTelegramMessage($full_msg);
                    
                    // স্ট্যাটাস আপডেট করা
                    $conn->query("UPDATE port_alerts SET last_status = $status WHERE switch_id = $switch_id AND port_index = '$index'");
                }
            } else {
                // নতুন পোর্টের জন্য এন্ট্রি করা
                $conn->query("INSERT INTO port_alerts (switch_id, port_index, last_status) VALUES ($switch_id, '$index', $status)");
            }
        }
    }
}
?>