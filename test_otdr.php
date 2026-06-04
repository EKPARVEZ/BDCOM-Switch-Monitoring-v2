<?php
require_once 'config.php';

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 4; // Your switch ID

echo "<h2>OTDR Debug Test</h2>";

$sw = $conn->query("SELECT * FROM switches WHERE id = $switch_id")->fetch_assoc();
if(!$sw) {
    die("Switch not found");
}

echo "<p>Switch: " . $sw['name'] . "</p>";
echo "<p>IP: " . $sw['ip_address'] . "</p>";
echo "<p>Community: " . $sw['community'] . "</p>";

$ip = $sw['ip_address'];
$com = $sw['community'];

if(strpos($ip, ':') !== false) {
    list($ip_addr, $port) = explode(':', $ip);
} else {
    $ip_addr = $ip;
    $port = 161;
}
$target = $ip_addr . ":" . $port;

echo "<p>Target: $target</p>";
echo "<hr>";

// Test 1: Basic SNMP
echo "<h4>1. Testing SNMP Connection...</h4>";
$sysDescr = @snmp2_get($target, $com, ".1.3.6.1.2.1.1.1.0", 2000000, 1);
if($sysDescr) {
    echo "<pre style='color:green'>✅ SNMP WORKING: " . htmlspecialchars($sysDescr) . "</pre>";
} else {
    echo "<pre style='color:red'>❌ SNMP FAILED - Check IP/Community/Firewall</pre>";
    die();
}

// Test 2: Get Ports
echo "<h4>2. Getting Ports...</h4>";
$portNames = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2", 3000000, 1);
if($portNames && is_array($portNames)) {
    echo "<pre style='color:green'>✅ Found " . count($portNames) . " ports</pre>";
    
    $count = 0;
    foreach($portNames as $oid => $name) {
        if($count++ > 10) break;
        $idx = substr(strrchr($oid, "."), 1);
        $port_name = str_replace('"', '', $name);
        echo "<pre>Port $idx: $port_name</pre>";
    }
} else {
    echo "<pre style='color:red'>❌ No ports found</pre>";
}

// Test 3: Get RX Power for first few ports
echo "<h4>3. Testing RX Power for ports...</h4>";
foreach($portNames as $oid => $name) {
    $idx = substr(strrchr($oid, "."), 1);
    $rxOIDs = [
        ".1.3.6.1.2.1.99.1.1.1.4." . $idx,
        ".1.3.6.1.4.1.3320.9.63.1.7.1.3." . $idx,
    ];
    
    foreach($rxOIDs as $rxOID) {
        $rx = @snmp2_get($target, $com, $rxOID, 2000000, 1);
        if($rx && strpos($rx, 'No Such') === false) {
            echo "<pre>Port $idx - RX: " . htmlspecialchars($rx) . " (OID: $rxOID)</pre>";
            break;
        }
    }
    break;
}

// Test 4: Direct API call
echo "<h4>4. Testing API endpoint...</h4>";
$api_url = "http://" . $_SERVER['HTTP_HOST'] . "/switch/otdr_distance.php?action=analyze&id=$switch_id";
echo "<p>Calling: <a href='$api_url' target='_blank'>$api_url</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$api_result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if($api_result) {
    echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto'>" . htmlspecialchars($api_result) . "</pre>";
    
    $json = json_decode($api_result, true);
    if($json && !isset($json['error'])) {
        echo "<pre style='color:green'>✅ API returned " . count($json) . " ports</pre>";
    } else {
        echo "<pre style='color:red'>❌ API error: " . ($json['error'] ?? 'Unknown') . "</pre>";
    }
} else {
    echo "<pre style='color:red'>❌ No response from API</pre>";
}
?>