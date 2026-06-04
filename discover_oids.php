<?php
require_once 'config.php';

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 4;

$sw = $conn->query("SELECT * FROM switches WHERE id = $switch_id")->fetch_assoc();
if(!$sw) die("Switch not found");

$ip = $sw['ip_address'];
$com = $sw['community'];

if(strpos($ip, ':') !== false) list($ip, $port) = explode(':', $ip);
$target = $ip . ":" . ($port ?? 161);

echo "<h2>BDCOM S2900 OID Discovery Tool</h2>";
echo "<p>Target: $target | Community: $com</p>";
echo "<hr>";

// Test ports (TGigaEthernet)
$test_ports = [173, 174, 175, 176];

echo "<h3>Testing Optical Power OIDs for 10G Ports:</h3>";
echo "<table border='1' cellpadding='8' cellspacing='0'>";
echo "<tr><th>Port</th><th>Name</th><th>OID Type</th><th>OID</th><th>Raw Value</th><th>Parsed (dBm)</th></tr>";

$rxOIDs = [
    "BDCOM RX (S2900)" => ".1.3.6.1.4.1.3320.9.63.1.7.1.3",
    "BDCOM RX (Alt)" => ".1.3.6.1.4.1.3320.101.10.5.1.5",
    "ENTITY-SENSOR RX" => ".1.3.6.1.2.1.99.1.1.1.4",
    "BDCOM DOM RX" => ".1.3.6.1.4.1.3320.2.5.1.1.1.1.10",
];

$txOIDs = [
    "BDCOM TX (S2900)" => ".1.3.6.1.4.1.3320.9.63.1.7.1.2",
    "BDCOM TX (Alt)" => ".1.3.6.1.4.1.3320.101.10.5.1.6",
    "ENTITY-SENSOR TX" => ".1.3.6.1.2.1.99.1.1.1.5",
];

foreach($test_ports as $port) {
    // Get port name
    $name_oid = ".1.3.6.1.2.1.2.2.1.2." . $port;
    $name_raw = @snmp2_get($target, $com, $name_oid, 2000000, 1);
    $port_name = $name_raw ? str_replace('"', '', $name_raw) : "Unknown";
    
    // Test RX OIDs
    foreach($rxOIDs as $label => $oid_base) {
        $oid = $oid_base . "." . $port;
        $val = @snmp2_get($target, $com, $oid, 2000000, 1);
        if($val && strpos($val, 'No Such') === false && $val !== 'FALSE') {
            $clean = trim(str_replace('"', '', $val));
            $num = (float)preg_replace('/[^0-9.-]/', '', $clean);
            if($num > 100 || $num < -100) $num = $num / 10;
            echo "<tr bgcolor='#ccffcc'>";
            echo "<td>$port</td><td>$port_name</td><td>$label (RX)</td><td><code>$oid</code></td><td>$clean</td><td><b>$num dBm</b></td>";
            echo "</tr>";
        }
    }
    
    // Test TX OIDs
    foreach($txOIDs as $label => $oid_base) {
        $oid = $oid_base . "." . $port;
        $val = @snmp2_get($target, $com, $oid, 2000000, 1);
        if($val && strpos($val, 'No Such') === false && $val !== 'FALSE') {
            $clean = trim(str_replace('"', '', $val));
            $num = (float)preg_replace('/[^0-9.-]/', '', $clean);
            if($num > 100 || $num < -100) $num = $num / 10;
            echo "<tr bgcolor='#ccffcc'>";
            echo "<td>$port</td><td>$port_name</td><td>$label (TX)</td><td><code>$oid</code></td><td>$clean</td><td><b>$num dBm</b></td>";
            echo "</tr>";
        }
    }
}

echo "</table>";

// Also test standard SNMP walks
echo "<h3>Testing SNMP Walk for Port 173 (TGigaEthernet0/1):</h3>";
echo "<pre>";
$test_port = 173;
echo "Testing OIDs for port $test_port:\n";
$testOIDs = [
    ".1.3.6.1.2.1.2.2.1.2" => "ifDescr (Name)",
    ".1.3.6.1.2.1.2.2.1.8" => "ifOperStatus",
    ".1.3.6.1.4.1.3320.9.63.1.7.1.3" => "BDCOM RX",
    ".1.3.6.1.4.1.3320.9.63.1.7.1.2" => "BDCOM TX",
    ".1.3.6.1.4.1.3320.101.10.5.1.5" => "BDCOM Alt RX",
    ".1.3.6.1.4.1.3320.101.10.5.1.6" => "BDCOM Alt TX",
    ".1.3.6.1.2.1.99.1.1.1.4" => "ENTITY RX",
    ".1.3.6.1.2.1.99.1.1.1.5" => "ENTITY TX",
];
foreach($testOIDs as $oid_base => $desc) {
    $oid = $oid_base . "." . $test_port;
    $val = @snmp2_get($target, $com, $oid, 2000000, 1);
    if($val && strpos($val, 'No Such') === false) {
        echo "✅ $desc: $val\n";
    } else {
        echo "❌ $desc: No data\n";
    }
}
echo "</pre>";
?>