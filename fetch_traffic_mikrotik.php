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
    $target = $ip;

    // ১. মাইক্রোটিক ইন্টারফেস নাম (.1.3.6.1.2.1.2.2.1.2)
    $portNames = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2");
    
    // ২. ট্রাফিক ডাটা (HC-In-Octets - 64 bit counters)
    $portInRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.31.1.1.1.6");

    if($portNames) {
        foreach($portNames as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $port_name = str_replace('"', '', $val);

            // শুধু ফিজিক্যাল পোর্ট ফিল্টার করা (sfp-sfpplus, ether, bridge বাদে)
            if (preg_match('/(ether|sfp|sfpplus)/i', $port_name)) {
                
                // Mbps ক্যালকুলেশন
                $raw_oid = ".1.3.6.1.2.1.31.1.1.1.6." . $index;
                $current_in = isset($portInRaw[$raw_oid]) ? (float)preg_replace('/[^0-9.]/', '', $portInRaw[$raw_oid]) : 0;
                
                $mbps = 0;
                $last_res = $conn->query("SELECT in_octets, recorded_at FROM port_traffic WHERE port_index='$index' AND switch_id=$switch_id ORDER BY id DESC LIMIT 1");
                $last = $last_res->fetch_assoc();
                
                if($last && $current_in > 0) {
                    $diff = $current_in - (float)$last['in_octets'];
                    $time_diff = time() - strtotime($last['recorded_at']);
                    if($time_diff > 0 && $diff > 0) {
                        $mbps = round(($diff * 8) / ($time_diff * 1000000), 2);
                    }
                }

                if($current_in > 0) {
                    $conn->query("INSERT INTO port_traffic (switch_id, port_index, in_octets) VALUES ($switch_id, '$index', '$current_in')");
                }

                $response[] = [
                    'index' => $index,
                    'mbps' => number_format($mbps, 2),
                    'rx' => "N/A", // MikroTik standard OID-তে সচরাচর পাওয়ার ডাটা থাকে না
                    'tx' => "N/A"
                ];
            }
        }
    }
}
echo json_encode($response);