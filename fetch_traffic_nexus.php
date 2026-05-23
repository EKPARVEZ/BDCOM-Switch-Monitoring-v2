<?php
require_once 'config.php';
header('Content-Type: application/json');
snmp_set_quick_print(1);

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

$response = [];

if($switch) {
    $ip = $switch['ip_address'];
    $com = $switch['community'];

    // ১. ইন্টারফেস এবং ট্রাফিক ডাটা সংগ্রহ (Nexus-এ 64-bit HC counters জরুরি)
    $portNames = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.2");
    $portInRaw = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.31.1.1.1.6");
    
    // ২. Nexus Sensor Value OID (পাওয়ার রিডিং এর জন্য)
    // .1.3.6.1.4.1.9.9.91.1.1.1.1.4 (entSensorValue)
    $sensorValues = @snmp2_real_walk($ip, $com, ".1.3.6.1.4.1.9.9.91.1.1.1.1.4");

    if($portNames) {
        foreach($portNames as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $port_name = str_replace('"', '', $val);

            // শুধু ফিজিক্যাল পোর্ট (Eth) ফিল্টার
            if (strpos($port_name, 'Eth') === false) continue;

            // Mbps ক্যালকুলেশন
            $mbps = 0;
            $raw_in_oid = ".1.3.6.1.2.1.31.1.1.1.6." . $index;
            if(isset($portInRaw[$raw_in_oid])) {
                $current_in = (float)preg_replace('/[^0-9.]/', '', $portInRaw[$raw_in_oid]);
                $last_res = $conn->query("SELECT in_octets, recorded_at FROM port_traffic WHERE port_index='$index' AND switch_id=$switch_id ORDER BY id DESC LIMIT 1");
                if($last = $last_res->fetch_assoc()) {
                    $diff = $current_in - (float)$last['in_octets'];
                    $time_diff = time() - strtotime($last['recorded_at']);
                    if($time_diff > 0 && $diff > 0) $mbps = round(($diff * 8) / ($time_diff * 1000000), 2);
                }
                $conn->query("INSERT INTO port_traffic (switch_id, port_index, in_octets) VALUES ($switch_id, '$index', '$current_in')");
            }

            // Power (DOM) ম্যাপিং
            // Nexus-এ সেন্সর ইনডেক্স ডাইনামিক। সাধারণত পোর্টের সেন্সর আইডিগুলো ভিন্ন হয়।
            // এই পার্টটি N/A দেখাবে যদি DOM ডাটা ওআইডিতে না পাওয়া যায়।
            $rx_dbm = "N/A"; $tx_dbm = "N/A";

            $response[] = [
                'index' => $index,
                'mbps' => number_format($mbps, 2),
                'rx' => $rx_dbm,
                'tx' => $tx_dbm
            ];
        }
    }
}
echo json_encode($response);