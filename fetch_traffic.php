<?php
require_once 'config.php';
header('Content-Type: application/json');
error_reporting(0);

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$switch_id) {
    echo json_encode(['error' => 'No switch ID provided']);
    exit;
}

$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

if(!$switch) {
    echo json_encode(['error' => 'Switch not found']);
    exit;
}

$ip_input = $switch['ip_address'];
$com = $switch['community'];

if (strpos($ip_input, ':') !== false) {
    list($ip, $snmp_port) = explode(':', $ip_input);
} else {
    $ip = $ip_input;
    $snmp_port = 161;
}
$target = $ip . ":" . $snmp_port;

if(function_exists('snmp_set_quick_print')) {
    snmp_set_quick_print(1);
}

// Test SNMP Connection
$sysDescr = @snmp2_get($target, $com, ".1.3.6.1.2.1.1.1.0", 2000000, 1);
if (!$sysDescr) {
    echo json_encode(['error' => 'SNMP connection failed to ' . $target]);
    exit;
}

// Get Port Names and Status
$portNames = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2", 3000000, 1);
$portStatus = @snmp2_walk($target, $com, ".1.3.6.1.2.1.2.2.1.8", 3000000, 1);
$portInOctets = @snmp2_walk($target, $com, ".1.3.6.1.2.1.2.2.1.10", 3000000, 1);

// Get VLAN (Try multiple OIDs)
$vlanData = null;
$vlanOIDs = [
    ".1.3.6.1.2.1.17.7.1.4.5.1.1",
    ".1.3.6.1.4.1.3320.101.12.1.1.3",
];
foreach($vlanOIDs as $vlanOID) {
    $vlanData = @snmp2_real_walk($target, $com, $vlanOID, 2000000, 1);
    if($vlanData && count($vlanData) > 0) break;
}

// Get Optical Power
$rxRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.99.1.1.1.4", 3000000, 1);
if(!$rxRaw || count($rxRaw) == 0) {
    $rxRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.9.63.1.7.1.3", 3000000, 1);
}
$txRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.99.1.1.1.5", 3000000, 1);
if(!$txRaw || count($txRaw) == 0) {
    $txRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.9.63.1.7.1.2", 3000000, 1);
}

// Get Port Descriptions from database
$portDescs = [];
$descRes = $conn->query("SELECT port_index, customer_name, location, customer_phone, description FROM port_descriptions WHERE switch_id = $switch_id");
while($desc = $descRes->fetch_assoc()) {
    $portDescs[$desc['port_index']] = $desc;
}

// Get last events
$lastEvents = [];
$eventRes = $conn->query("SELECT port_index, event_type, event_time FROM port_events 
    WHERE switch_id = $switch_id AND event_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY event_time DESC");
while($ev = $eventRes->fetch_assoc()) {
    if(!isset($lastEvents[$ev['port_index']])) {
        $lastEvents[$ev['port_index']] = $ev;
    }
}

// Get previous status for change detection
$prevStatus = [];
$prevRes = $conn->query("SELECT port_index, last_status FROM port_alerts WHERE switch_id = $switch_id");
while($prev = $prevRes->fetch_assoc()) {
    $prevStatus[$prev['port_index']] = $prev['last_status'];
}

$response = [];
$i = 0;

foreach($portNames as $oid => $val) {
    $parts = explode('.', $oid);
    $index = end($parts);
    $port_name = trim(str_replace('"', '', $val));
    
    // Skip virtual ports
    if(stripos($port_name, 'vlan') !== false || 
       stripos($port_name, 'bridge') !== false || 
       stripos($port_name, 'cpu') !== false ||
       stripos($port_name, 'lo') !== false ||
       $port_name == '' ||
       $index == '0') {
        $i++;
        continue;
    }
    
    // Status
    $status = "UNKNOWN";
    $statusCode = 0;
    if(isset($portStatus[$i])) {
        $statusCode = (int)$portStatus[$i];
        $status = ($statusCode == 1) ? "UP" : "DOWN";
    }
    
   // Traffic Calculation
   

// ============================================================
// FIXED TRAFFIC CALCULATION - ACCURATE Mbps & Gbps
// ============================================================

$traffic_mbps = 0.00;
$traffic_gbps = 0.00;
$traffic_kbps = 0.00;

// Get current IN octets (bytes)
if (isset($portInOctets[$i])) {
    $current_in = (float) preg_replace('/[^0-9]/', '', $portInOctets[$i]);
    
    if ($current_in > 0) {
        // Prepare statement to get last record
        $stmt = $conn->prepare("SELECT in_octets, recorded_at FROM port_traffic 
            WHERE port_index = ? AND switch_id = ? 
            ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("si", $index, $switch_id);
        $stmt->execute();
        $last_res = $stmt->get_result();
        
        if ($last_res && $last_res->num_rows > 0) {
            $last = $last_res->fetch_assoc();
            $last_octets = (float) $last['in_octets'];
            $last_time = strtotime($last['recorded_at']);
            $current_time = time();
            $time_diff = $current_time - $last_time;
            
            // Calculate only if time difference is positive and reasonable
            if ($time_diff > 0 && $time_diff < 300) { // Max 5 minutes gap
                
                // Handle counter reset (device reboot or overflow)
                if ($current_in >= $last_octets) {
                    $bytes_diff = $current_in - $last_octets;
                } else {
                    // Counter reset - use current value as difference
                    $bytes_diff = $current_in;
                }
                
                // Calculate bits per second
                $bits_per_sec = ($bytes_diff * 8) / $time_diff;
                
                // Convert to Mbps (Megabits per second)
                $traffic_mbps = round($bits_per_sec / 1000000, 2);
                $traffic_kbps = round($bits_per_sec / 1000, 2);
                $traffic_gbps = round($bits_per_sec / 1000000000, 2);
                
                // Sanity check - don't show unrealistic values
                if ($traffic_mbps < 0 || $traffic_mbps > 10000) {
                    $traffic_mbps = 0.00;
                    $traffic_kbps = 0.00;
                    $traffic_gbps = 0.00;
                }
            }
        }
        
        // Store current reading for next poll
        $insert = $conn->prepare("INSERT INTO port_traffic 
            (switch_id, port_index, in_octets, recorded_at) 
            VALUES (?, ?, ?, NOW())");
        $insert->bind_param("isd", $switch_id, $index, $current_in);
        $insert->execute();
        
        // Keep only last 2 records per port to save space
        $cleanup = $conn->prepare("DELETE FROM port_traffic 
            WHERE switch_id = ? AND port_index = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM port_traffic 
                    WHERE switch_id = ? AND port_index = ? 
                    ORDER BY id DESC LIMIT 2
                ) as tmp
            )");
        $cleanup->bind_param("isis", $switch_id, $index, $switch_id, $index);
        $cleanup->execute();
    }
}

// Format traffic for display
$traffic_display = "0 Mbps";
if ($traffic_gbps >= 1) {
    $traffic_display = $traffic_gbps . " Gbps";
} elseif ($traffic_mbps >= 1) {
    $traffic_display = $traffic_mbps . " Mbps";
} elseif ($traffic_kbps >= 1) {
    $traffic_display = $traffic_kbps . " Kbps";
} else {
    $traffic_display = "0 Mbps";
}

// Optional: Add color coding based on utilization
$traffic_class = "text-success"; // Low
if ($traffic_mbps > 100) {
    $traffic_class = "text-warning"; // Medium
}
if ($traffic_mbps > 500) {
    $traffic_class = "text-danger"; // High
}
if ($traffic_gbps > 1) {
    $traffic_class = "text-danger font-weight-bold"; // Very High
}




// Add this function to your fetch_traffic.php
function calculateDistanceFor1270nm($rx_power_dbm, $tx_power_dbm = null) {
    // 1270nm specific constants
    $attenuation_per_km = 0.42;  // dB/km for 1270nm (higher than 1310nm)
    $connector_loss = 1.0;       // 0.5dB per connector (OLT + ONU)
    $splice_loss = 0.2;          // 0.1dB per splice (assume 2)
    $total_fixed_loss = $connector_loss + $splice_loss;
    
    // Typical TX power for 1270nm BiDi SFP: +2 to +5 dBm
    $tx_default = 3.0;
    
    if($tx_power_dbm && $tx_power_dbm != 'N/A' && is_numeric($tx_power_dbm)) {
        $tx_default = (float)$tx_power_dbm;
    }
    
    if($rx_power_dbm == 'N/A' || $rx_power_dbm === null) {
        return ['distance' => 'No RX Power', 'km' => null, 'loss' => null];
    }
    
    $rx_val = (float)$rx_power_dbm;
    
    // Check for fiber cut (no light)
    if($rx_val < -35) {
        return ['distance' => 'FIBER_CUT (No Light)', 'km' => null, 'loss' => null];
    }
    
    $total_loss = $tx_default - $rx_val;
    $fiber_loss = $total_loss - $total_fixed_loss;
    
    if($fiber_loss <= 0) {
        return ['distance' => 'Active', 'km' => 0, 'loss' => round($total_loss, 2)];
    }
    
    $distance_km = round($fiber_loss / $attenuation_per_km, 2);
    
    // Sanity check - 10G 1270nm max distance is ~40km
    if($distance_km > 50) $distance_km = 50;
    
    if($distance_km > 0.1) {
        return ['distance' => "~{$distance_km} km", 'km' => $distance_km, 'loss' => round($total_loss, 2)];
    } else {
        return ['distance' => "Very Short ({$distance_km} km)", 'km' => $distance_km, 'loss' => round($total_loss, 2)];
    }
}

// Use in your port loop:
if($sfp_type == '1270nm' || $sfp_type == 'BIDI' || $wavelength == 1270) {
    $fiberInfo = calculateDistanceFor1270nm($rx_dbm, $tx_dbm);
    $fiber_distance = $fiberInfo['distance'];
} else {
    // Use standard calculation
    $fiberInfo = calculateStandardDistance($rx_dbm, $tx_dbm);
    $fiber_distance = $fiberInfo['distance'];
}


    
  // ============================================================
    // VLAN ID MAPPING - ACCURATE OID MATCHING
    // ============================================================
    $vlan_id = "N/A";

if ($vlanData && is_array($vlanData)) {
    foreach ($vlanData as $v_oid => $v_val) {
        
        $oid_clean = trim($v_oid);
        
        // Regex: OID শেষ হতে হবে .[digits].[index] দিয়ে
        // যেখানে [index] হলো port index
        $pattern = '/\.' . preg_quote($index, '/') . '$/';
        
        if (preg_match($pattern, $oid_clean)) {
            
            // নিশ্চিত করতে হবে যে index-এর আগে আরেকটি ডট আছে
            // (যাতে "10.1"-এর মতো partial match না হয়)
            $pos = strrpos($oid_clean, '.' . $index);
            if ($pos !== false && $pos > 0 && $oid_clean[$pos - 1] === '.') {
                
                $raw_vlan = preg_replace('/[^0-9]/', '', $v_val);
                
                if (!empty($raw_vlan) && $raw_vlan !== "0") {
                    $vlan_id = $raw_vlan;
                }
                break;
            }
        }
    }
}
	
	
	//RX CODE
	
	
  $rx_dbm = "N/A";
$rx_raw_value = 0;

if ($rxRaw && is_array($rxRaw)) {
    foreach ($rxRaw as $r_oid => $r_val) {
        
        $oid_parts = explode('.', trim($r_oid));
        $last_index = $oid_parts[count($oid_parts) - 1] ?? '';
        
        // Strict type match
        if ($last_index !== (string)$index) {
            continue;
        }
        
        $raw = trim($r_val);
        $raw = str_replace('"', '', $raw);
        $raw = preg_replace('/[^0-9.eE+-]/', '', $raw); // scientific notation support
        
        // -0 handling
        $raw = preg_replace('/^-0+(\.0+)?$/', '0', $raw);
        
        if ($raw === '' || !is_numeric($raw)) {
            continue;
        }
        
        $rx_raw_value = (float)$raw;
        
        // ============================================================
        // DYNAMIC SCALING (Priority: ÷10 → direct → ÷100)
        // ============================================================
        
        // Option 1: ÷10 (most common for BDCOM/OLT SFP)
        $scaled_10 = $rx_raw_value / 10;
        if ($scaled_10 >= -50 && $scaled_10 <= 10) {
            $rx_raw_value = $scaled_10;
        }
        // Option 2: Direct value (already in dBm)
        elseif ($rx_raw_value >= -50 && $rx_raw_value <= 10) {
            // keep as-is
        }
        // Option 3: ÷100 (rare, old modules)
        else {
            $scaled_100 = $rx_raw_value / 100;
            if ($scaled_100 >= -50 && $scaled_100 <= 10) {
                $rx_raw_value = $scaled_100;
            } else {
                // All scaling failed — skip this OID
                continue;
            }
        }
        
        // Final validation
        if ($rx_raw_value >= -50 && $rx_raw_value <= 10) {
            $rx_dbm = round($rx_raw_value, 2) . " dBm";
            break;
        }
    }
}
    
	
	//TX CODE
	
	
  $tx_dbm = "N/A";
$tx_raw_value = 0;

if ($txRaw && is_array($txRaw)) {
    foreach ($txRaw as $t_oid => $t_val) {
        
        $oid_parts = explode('.', trim($t_oid));
        $last_index = $oid_parts[count($oid_parts) - 1] ?? '';
        
        if ($last_index !== (string)$index) {
            continue;
        }
        
        $raw = trim($t_val);
        $raw = str_replace('"', '', $raw);
        $raw = preg_replace('/[^0-9.eE+-]/', '', $raw);
        $raw = preg_replace('/^-0+(\.0+)?$/', '0', $raw); // -0 fix
        
        if ($raw === '' || !is_numeric($raw)) {
            continue;
        }
        
        $tx_raw_value = (float)$raw;
        
        // ============================================================
        // DYNAMIC SCALING (Priority: ÷10 → direct → ÷100)
        // BDCOM standard: value × 10 (e.g., 45 = 4.5 dBm)
        // ============================================================
        
        $scaled_10 = $tx_raw_value / 10;
        if ($scaled_10 >= -50 && $scaled_10 <= 10) {
            $tx_raw_value = $scaled_10;
        } elseif ($tx_raw_value >= -50 && $tx_raw_value <= 10) {
            // direct value valid
        } else {
            $scaled_100 = $tx_raw_value / 100;
            if ($scaled_100 >= -50 && $scaled_100 <= 10) {
                $tx_raw_value = $scaled_100;
            } else {
                continue; // all scaling failed
            }
        }
        
        if ($tx_raw_value >= -50 && $tx_raw_value <= 10) {
            $tx_dbm = round($tx_raw_value, 2) . " dBm";
            break;
        }
    }
}
	

    
   // ============================================================
// FIBER DISTANCE & STATUS CALCULATION - FIXED & OPTIMIZED
// ============================================================
$fiber_distance = "Link Active";
$distance_km = 0;

// TX Power actual value (fallback to 3.0)
$tx_actual = ($tx_dbm != "N/A") 
    ? (float) preg_replace('/[^0-9.-]/', '', $tx_dbm) 
    : 3.0;

if ($statusCode == 2) { // Port DOWN
    
    // Last known good RX from DB
    $last_known_rx = -28.0;
    $stmt = $conn->prepare("
        SELECT rx_raw_value FROM port_optical 
        WHERE port_index = ? AND switch_id = ? 
        AND rx_raw_value < 0 AND rx_raw_value > -50
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param("si", $index, $switch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $last_known_rx = (float) $res->fetch_assoc()['rx_raw_value'];
    }
    
    $reference_rx = $last_known_rx;
    
    // RX completely dead or very weak
    if ($rx_raw_value == 0 || $rx_raw_value <= -30) {
        
        $total_loss = $tx_actual - $reference_rx;
        $splitter_loss = 4.0; // configure per your network
        $fiber_loss = $total_loss - $splitter_loss;
        
        if ($fiber_loss > 0) {
            $attenuation = 0.25; // 1490nm downstream
            $distance_km = round($fiber_loss / $attenuation, 2);
            
            if ($distance_km > 0.1 && $distance_km <= 20) {
                $fiber_distance = "Fiber Cut / LOS (Est. Break: ~" . $distance_km . " KM)";
            } else {
                $fiber_distance = "Fiber Cut / No Light (LOS)";
            }
        } else {
            $fiber_distance = "Fiber Cut / No Light (LOS)";
        }
    }
    // Degraded signal but port somehow down
    elseif ($rx_raw_value < -15 && $rx_raw_value > -30) {
        $total_loss = $tx_actual - $rx_raw_value;
        $distance_km = round(($total_loss - 2.0) / 0.25, 2);
        $fiber_distance = "Critical Signal Loss (~" . $distance_km . " KM) - RX: " . $rx_raw_value . " dBm";
    } else {
        $fiber_distance = "Link Down - Check Patch Cord / Admin Down";
    }

} else {
    // Port UP
    if ($rx_dbm != "N/A" && $rx_raw_value != 0) {
        if ($rx_raw_value >= -19.0) {
            $fiber_distance = "Perfect Signal (" . $rx_dbm . ")";
        } elseif ($rx_raw_value >= -24.5) {
            $fiber_distance = "Good Signal (" . $rx_dbm . ")";
        } elseif ($rx_raw_value >= -27.0) {
            $fiber_distance = "Weak Signal (" . $rx_dbm . ")";
        } elseif ($rx_raw_value >= -30.0) {
            $fiber_distance = "Critical Low (" . $rx_dbm . ")";
        } else {
            $fiber_distance = "Below Sensitivity (" . $rx_dbm . ")";
        }
    } else {
        $fiber_distance = "Link Active (No DDM Data)";
    }
}
    
	
	
	
   // ============================================================
// DETECT STATUS CHANGE & LOG EVENT - FIXED & ROBUST
// ============================================================
$current_event = null;
$event_time = null;

$previous = isset($prevStatus[$index]) ? (int)$prevStatus[$index] : null;
$statusCode = (int)$statusCode;

// Initial sync detection
$is_initial_sync = ($previous === null);

// Status change detection
$status_changed = ($previous !== null && $previous !== $statusCode);

// Event logging decision
$should_log = false;

if ($status_changed) {
    $should_log = true;
} elseif ($is_initial_sync && $statusCode == 2) {
    // Optional: first run-এ DOWN port log করতে চাইলে
    // $should_log = true; // comment out to disable
}

if ($should_log) {
    
    // Determine event type
    if ($statusCode == 2) {
        
        // Use last known RX for accurate fiber cut detection
        $check_rx = (isset($last_known_rx) && $last_known_rx != 0) 
            ? $last_known_rx 
            : $rx_raw_value;
        
        if ($check_rx == 0 || $check_rx <= -30.0) {
            $event_type = 'FIBER_CUT';
            $details = "Fiber cut detected - Loss of Optical Signal (LOS)";
        } else {
            $event_type = 'DOWN';
            $details = "Link protocol down - Port disconnected / Admin Down";
        }
        
        if (isset($distance_km) && $distance_km > 0) {
            $details .= " | Est. break: {$distance_km} km";
        }
        
    } elseif ($statusCode == 1 && $previous == 2) {
        $event_type = 'RECOVERED';
        $details = "Port recovered - Link restored successfully";
    }
    
    // Debounce check
    $can_insert = true;
    $debounce_stmt = $conn->prepare("
        SELECT event_type, event_time FROM port_events 
        WHERE switch_id = ? AND port_index = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $debounce_stmt->bind_param("is", $switch_id, $index);
    $debounce_stmt->execute();
    $debounce_res = $debounce_stmt->get_result();
    
    if ($debounce_res && $debounce_res->num_rows > 0) {
        $last_row = $debounce_res->fetch_assoc();
        $time_diff = time() - strtotime($last_row['event_time']);
        
        if ($last_row['event_type'] == $event_type && $time_diff < 300) {
            $can_insert = false; // Skip duplicate within 5 min
        }
    }
    
    if ($can_insert && isset($event_type)) {
        $stmt = $conn->prepare("INSERT INTO port_events 
            (switch_id, port_index, port_name, event_type, details, 
             rx_power, tx_power, fiber_distance, event_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->bind_param("isssssss", 
            $switch_id, $index, $port_name, $event_type, 
            $details, $rx_dbm, $tx_dbm, $fiber_distance
        );
        $stmt->execute();
        
        $current_event = $event_type;
        $event_time = date('Y-m-d H:i:s');
    }
}

// Always sync current status (UPSERT)
$sync_stmt = $conn->prepare("INSERT INTO port_alerts 
    (switch_id, port_index, last_status, updated_at) 
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
    last_status = VALUES(last_status), 
    updated_at = VALUES(updated_at)");

$sync_stmt->bind_param("isi", $switch_id, $index, $statusCode);
$sync_stmt->execute();




   // ============================================================
    // PORT DESCRIPTION (From Database) - SECURED & OPTIMIZED
    // ============================================================
    $desc_html = "";
    if(isset($portDescs[$index])) {
        $d = $portDescs[$index];
        if(!empty($d['customer_name'])) {
            $desc_html .= "<strong>" . htmlspecialchars($d['customer_name']) . "</strong>";
        }
        if(!empty($d['location'])) {
            $desc_html .= "<br><small>" . htmlspecialchars($d['location']) . "</small>";
        }
        if(!empty($d['customer_phone'])) {
            $desc_html .= "<br><small class='text-muted'>📞 " . htmlspecialchars($d['customer_phone']) . "</small>";
        }
        if(!empty($d['description'])) {
            $desc_html .= "<br><small class='text-primary'>" . htmlspecialchars($d['description']) . "</small>";
        }
    }
    
    // বাগ ফিক্স ১: জাভাস্ক্রিপ্ট স্ট্রিং এস্কেপ করা যাতে কোনো স্পেশাল ইনডেক্স ক্যারেক্টারে HTML/JS না ভাঙে
    if(empty($desc_html)) {
        $safe_index = htmlspecialchars($index, ENT_QUOTES, 'UTF-8');
        $desc_html = "<span class='text-muted' style='cursor:pointer' onclick='showEditModal(\"{$safe_index}\")'><i class='fas fa-plus-circle'></i> Add customer info</span>";
    }
    
    // Last event info
    $last_event_text = "<span class='text-muted'>No recent events</span>";
    if(isset($lastEvents[$index])) {
        $ev = $lastEvents[$index];
        $ev_icon = ($ev['event_type'] == 'DOWN') ? '🔴' : (($ev['event_type'] == 'FIBER_CUT') ? '⚠️' : '🟢');
        $last_event_text = "{$ev_icon} " . htmlspecialchars($ev['event_type']) . "<br><small class='text-muted'>" . date('d-M H:i:s', strtotime($ev['event_time'])) . "</small>";
    }
    
    // বাগ ফিক্স ২: শুধুমাত্র ইনস্ট্যান্ট ইভেন্ট নয়, পোর্টের বর্তমান স্ট্যাটাসের ওপর ভিত্তি করে স্থায়ী রো কালার নির্ধারণ
    $row_class = "";
    
    // প্রথমে বর্তমান লাইভ স্ট্যাটাস চেক (ধরি ২ = Down, ১ = Up)
    $current_status_code = isset($statusCode) ? (int)$statusCode : (isset($status) ? (int)$status : 1);
    
    if ($current_status_code == 2) {
        // পোর্ট যদি ডাউন থাকে, তবে ফাইবার কাট নাকি নরমাল ডাউন তার ওপর ভিত্তি করে কালার হবে
        if ($current_event == 'FIBER_CUT' || (isset($fiber_distance) && strpos($fiber_distance, 'Fiber Cut') !== false)) {
            $row_class = "table-danger"; // লাল (ফাইবার কাট বা বড় অ্যালার্ট)
        } else {
            $row_class = "table-warning"; // হলুদ (সাধারণ ডাউন বা ডিসকানেক্টেড)
        }
    } elseif ($current_event == 'RECOVERED') {
        $row_class = "table-success"; // জাস্ট রিকভার হলে সাময়িক সবুজ হাইলাইট
    }
    
    // বাগ ফিক্স ৩: আনডিফাইনড ভেরিয়েবল নোটিশ প্রতিরোধ (Fallback Mechanism)
    $safe_vlan = isset($vlan_id) ? $vlan_id : 'N/A';
    $safe_status = isset($status) ? $status : $current_status_code;
    $safe_mbps = isset($mbps) ? (float)$mbps : 0.0;
    
    $response[] = [
        'index' => $index,
        'name' => isset($port_name) ? $port_name : "Port-".$index,
        'description' => $desc_html,
        'customer' => isset($portDescs[$index]['customer_name']) ? $portDescs[$index]['customer_name'] : '',
        'location' => isset($portDescs[$index]['location']) ? $portDescs[$index]['location'] : '',
        'phone' => isset($portDescs[$index]['customer_phone']) ? $portDescs[$index]['customer_phone'] : '',
        'mbps' => number_format($safe_mbps, 2),
        'rx' => isset($rx_dbm) ? $rx_dbm : 'N/A',
        'tx' => isset($tx_dbm) ? $tx_dbm : 'N/A',
        'vlan' => $safe_vlan,
        'status' => $safe_status,
        'fiber_distance' => isset($fiber_distance) ? $fiber_distance : 'Link Active',
        'last_event' => $last_event_text,
        'row_class' => $row_class
    ];
    
    $i++;
}

// JSON Output জেনারেট করার আগে বাফারিং ক্লিয়ার রাখা নিশ্চিত করা
if(empty($response)) {
    echo json_encode(['error' => 'No valid ports found']);
    exit;
}

echo json_encode($response);
?>