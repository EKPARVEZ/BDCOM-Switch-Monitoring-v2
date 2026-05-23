<?php
require_once 'config.php';

// টেলিগ্রাম ফ্যালব্যাক সেটিংস (যদি ডাটাবেসে না থাকে)
$botToken = TELEGRAM_TOKEN != "" ? TELEGRAM_TOKEN : "8765180306:AAHIMDwC84NaNjOHhvTK4dJRK65Y2N_cP8M";
$chatId = TELEGRAM_CHAT_ID != "" ? TELEGRAM_CHAT_ID : "1913663623";

function sendTelegram($msg, $token, $id) {
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$id&text=" . urlencode($msg) . "&parse_mode=HTML";
    @file_get_contents($url);
}

// ফাংশন: Loss of Signal (LOS) চেক করা - Fiber Cut কিনা বোঝার জন্য
function checkLOS($ip, $community, $port_index) {
    $los_oid = ".1.3.6.1.4.1.3320.101.10.5.1.8." . $port_index;
    $los_status = @snmp2_get($ip, $community, $los_oid, 2000000, 1);
    
    if($los_status) {
        $los_val = (int)trim(str_replace('"', '', $los_status));
        if($los_val == 2) {
            return true;
        }
    }
    return false;
}

// ফাংশন: আনুমানিক দূরত্ব বের করা (Rx Power থেকে)
function estimateDistance($ip, $community, $port_index) {
    $rx_oid = ".1.3.6.1.4.1.3320.101.10.5.1.5." . $port_index;
    $rx_raw = @snmp2_get($ip, $community, $rx_oid, 2000000, 1);
    
    if($rx_raw) {
        $rx_val = (float)preg_replace('/[^0-9.-]/', '', $rx_raw);
        if($rx_val < -100) $rx_val = $rx_val / 10;
        
        if($rx_val < -20 && $rx_val > -50) {
            $estimated_km = round(abs($rx_val + 20) / 0.25, 1);
            return "~" . $estimated_km . " KM";
        }
    }
    return "N/A";
}

// telegram_alert.php আপডেট করুন
function sendDetailedAlert($sw, $port_name, $index, $current_state, $issues, $distance) {
    global $botToken, $chatId;
    
    $emoji = $current_state == 2 ? '🔴' : '🟢';
    $status = $current_state == 2 ? 'DOWN' : 'UP';
    
    $message = "$emoji <b>PORT $status ALERT</b>\n\n";
    $message .= "🏢 <b>Switch:</b> {$sw['name']} ({$sw['ip_address']})\n";
    $message .= "🔌 <b>Port:</b> $port_name [$index]\n";
    $message .= "⏰ <b>Time:</b> " . date('d-M-Y H:i:s') . "\n";
    
    // Add root cause analysis
    if (!empty($issues)) {
        $message .= "\n🔍 <b>Root Cause Analysis:</b>\n";
        foreach ($issues as $issue) {
            $confidenceEmoji = $issue['confidence'] == 'HIGH' ? '✅' : '⚠️';
            $message .= "$confidenceEmoji {$issue['message']}\n";
        }
    }
    
    // Add distance if available
    if ($distance) {
        $message .= "\n📏 <b>Fiber Distance:</b> {$distance['km']} km ({$distance['meters']}m)\n";
        $message .= "💡 <b>Estimated Cut Location:</b> ~{$distance['km']} km from OLT\n";
    }
    
    // Add troubleshooting guide
    if ($current_state == 2) {
        $message .= "\n🛠️ <b>Recommended Actions:</b>\n";
        $message .= "1. Check physical fiber connection\n";
        $message .= "2. Verify OLT port status\n";
        $message .= "3. Check customer ONU power\n";
        if ($distance) {
            $message .= "4. Inspect fiber at ~{$distance['km']} km point\n";
        }
    }
    
    sendTelegram($message, $botToken, $chatId);
    return true;
}




// ফাংশন: Rx Power ভালো/খারাপ চেক
function getRxStatus($rx_val) {
    if($rx_val >= -15) return "🟢 Excellent";
    if($rx_val >= -22) return "🟡 Good";
    if($rx_val >= -28) return "🟠 Poor";
    return "🔴 Critical";
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Smart Alert System...\n";

$switches = $conn->query("SELECT * FROM switches WHERE status = 'active'");

while($sw = $switches->fetch_assoc()) {
    $ip = $sw['ip_address'];
    $com = $sw['community'];
    $switch_id = $sw['id'];
    $switch_name = $sw['name'];

    // সুইচ অনলাইন কিনা চেক
    $ping = @fsockopen($ip, 161, $errno, $errstr, 2);
    $is_online = ($ping !== false);
    if($ping) fclose($ping);
    
    if(!$is_online) {
        $message = "💀 <b>SWITCH OFFLINE!</b>\n\n";
        $message .= "🏢 Switch: <b>{$switch_name}</b>\n";
        $message .= "🌐 IP: <b>{$ip}</b>\n";
        $message .= "⚠️ Status: <b>COMPLETELY OFFLINE</b>\n";
        $message .= "🔧 Possible Cause: Power Failure or Uplink Down\n";
        $message .= "⏰ Time: " . date('d-M-Y H:i:s');
        
        sendTelegram($message, $botToken, $chatId);
        echo "⚠️ Switch {$switch_name} is OFFLINE\n";
        continue;
    }
    
    // পোর্ট ডাটা সংগ্রহ
    $names = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.2");
    $statuses = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.8");
    $rx_powers = @snmp2_real_walk($ip, $com, ".1.3.6.1.4.1.3320.101.10.5.1.5");
    
    if($names && $statuses) {
        foreach($statuses as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $current_state = (int)$val;
            $port_name = str_replace('"', '', $names[".1.3.6.1.2.1.2.2.1.2.$index"]);
            
            // শুধু ফিজিক্যাল পোর্ট ফিল্টার
            if(preg_match('/^(Eth|Gigabit|FastEthernet|XGigabit|Port)/i', $port_name) || is_numeric($port_name)) {
                
                // Rx পাওয়ার বের করা
                $rx_oid_full = ".1.3.6.1.4.1.3320.101.10.5.1.5." . $index;
                $rx_current = isset($rx_powers[$rx_oid_full]) ? trim(str_replace('"', '', $rx_powers[$rx_oid_full])) : "0";
                $rx_current_val = (float)preg_replace('/[^0-9.-]/', '', $rx_current);
                if($rx_current_val < -100) $rx_current_val = $rx_current_val / 10;
                
                // ডাটাবেস থেকে আগের স্ট্যাটাস
                $stmt = $conn->query("SELECT last_status, last_down_cause FROM port_alerts WHERE switch_id=$switch_id AND port_index='$index'");
                $row = $stmt->fetch_assoc();
                
                $cause = null;
                $distance = null;
                
                if($current_state == 2) { // DOWN
                    $is_los = checkLOS($ip, $com, $index);
                    $rx_status = getRxStatus($rx_current_val);
                    
                    if($is_los) {
                        $cause = "🔴 FIBER CUT (LOS Detected)";
                        $distance = estimateDistance($ip, $com, $index);
                    } elseif($rx_current_val < -28 && $rx_current_val > -50) {
                        $cause = "⚠️ WEAK SIGNAL ({$rx_current_val} dBm) - {$rx_status}";
                        $distance = estimateDistance($ip, $com, $index);
                    } else {
                        $cause = "🟡 PORT DOWN (Electrical/Faulty Cable/NIC Issue)";
                        $distance = "N/A";
                    }
                    
                    if($row && $row['last_status'] == 1) {
                        $message = "🔴 <b>PORT DOWN ALERT!</b>\n\n";
                        $message .= "🏢 Switch: <b>{$switch_name}</b> ({$ip})\n";
                        $message .= "🔌 Port: <b>{$port_name}</b> [Index: {$index}]\n";
                        $message .= "⚠️ Status: <b>DOWN</b>\n";
                        $message .= "🔍 Cause: {$cause}\n";
                        if($distance && $distance != "N/A") {
                            $message .= "📏 Est. Distance: <b>{$distance}</b>\n";
                        }
                        $message .= "📡 Rx Power: <b>{$rx_current_val} dBm</b> ({$rx_status})\n";
                        $message .= "⏰ Time: " . date('d-M-Y H:i:s');
                        
                        sendTelegram($message, $botToken, $chatId);
                        echo "🔴 Port {$port_name} DOWN - Cause: {$cause}\n";
                    }
                    
                    $conn->query("UPDATE port_alerts SET last_status=$current_state, last_down_cause='$cause', last_down_distance='$distance' WHERE switch_id=$switch_id AND port_index='$index'");
                    
                } elseif($current_state == 1) { // UP
                    if($row && $row['last_status'] == 2) {
                        $previous_cause = $row['last_down_cause'] ?? "Unknown";
                        $message = "✅ <b>PORT RECOVERED</b>\n\n";
                        $message .= "🏢 Switch: <b>{$switch_name}</b> ({$ip})\n";
                        $message .= "🔌 Port: <b>{$port_name}</b> [Index: {$index}]\n";
                        $message .= "🟢 Status: <b>UP</b>\n";
                        $message .= "🔧 Previous Issue: {$previous_cause}\n";
                        $message .= "📡 Current Rx Power: <b>{$rx_current_val} dBm</b>\n";
                        $message .= "⏰ Time: " . date('d-M-Y H:i:s');
                        
                        sendTelegram($message, $botToken, $chatId);
                        echo "✅ Port {$port_name} RECOVERED\n";
                    }
                    
                    $conn->query("UPDATE port_alerts SET last_status=$current_state, last_down_cause=NULL, last_down_distance=NULL WHERE switch_id=$switch_id AND port_index='$index'");
                }
                
                if(!$row) {
                    $conn->query("INSERT INTO port_alerts (switch_id, port_index, last_status) VALUES ($switch_id, '$index', $current_state)");
                }
            }
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Smart Alert Check Completed!\n";
?>