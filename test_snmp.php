<?php
require_once 'config.php';

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

if(!$switch) die("No switch found");

echo "<h2>SNMP Diagnostic for: " . htmlspecialchars($switch['name']) . "</h2>";
echo "<p>IP: " . htmlspecialchars($switch['ip_address']) . " | Community: " . htmlspecialchars($switch['community']) . "</p>";
echo "<hr>";

$ip = $switch['ip_address'];
$com = $switch['community'];

if (strpos($ip, ':') !== false) {
    list($ip, $port) = explode(':', $ip);
} else {
    $port = 161;
}
$target = $ip . ":" . $port;

if (!function_exists('snmp2_get')) {
    die("<pre style='color:red'>❌ PHP SNMP extension NOT enabled! Enable php_snmp.dll in php.ini</pre>");
}

snmp_set_quick_print(1);
snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

echo "<pre>";

// Test 1: Basic Connection
echo "\n[1] Testing SNMP connection to $target with community '$com'...\n";
$sysDescr = @snmp2_get($target, $com, ".1.3.6.1.2.1.1.1.0", 3000000, 1);
if ($sysDescr) {
    echo "✅ CONNECTION SUCCESSFUL!\n";
    echo "Device: " . str_replace('"', '', $sysDescr) . "\n";
} else {
    echo "❌ CONNECTION FAILED!\n";
    echo "Check: IP, Community string, SNMP enabled on switch\n";
    die("</pre>");
}

// Test 2: Port Names
echo "\n[2] Testing Port Names (ifDescr)...\n";
$portNames = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2", 3000000, 1);
if($portNames && is_array($portNames)) {
    echo "✅ Found " . count($portNames) . " ports\n";
    $c = 0;
    foreach($portNames as $oid => $val) {
        if($c++ > 10) break;
        $idx = substr(strrchr($oid, "."), 1);
        echo "   Port $idx: " . str_replace('"', '', $val) . "\n";
    }
} else {
    echo "❌ Could not retrieve port names\n";
}

// Test 3: Port Status
echo "\n[3] Testing Port Status (ifOperStatus)...\n";
$portStatus = @snmp2_walk($target, $com, ".1.3.6.1.2.1.2.2.1.8", 3000000, 1);
if($portStatus && is_array($portStatus)) {
    echo "✅ Found " . count($portStatus) . " port status entries\n";
} else {
    echo "❌ Could not retrieve port status\n";
}

// Test 4: 64-bit Traffic Counters
echo "\n[4] Testing 64-bit Traffic (ifHCInOctets)...\n";
$traffic64 = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.31.1.1.1.6", 3000000, 1);
if($traffic64 && is_array($traffic64) && count($traffic64) > 0) {
    echo "✅ 64-bit counters available\n";
} else {
    echo "❌ 64-bit counters not available (will use 32-bit)\n";
}

// Test 5: VLAN - Standard Q-BRIDGE
echo "\n[5] Testing VLAN (Q-BRIDGE MIB)...\n";
$vlanStd = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.17.7.1.4.5.1.1", 3000000, 1);
if($vlanStd && is_array($vlanStd) && count($vlanStd) > 0) {
    echo "✅ VLAN data available (Standard)\n";
    $c = 0;
    foreach($vlanStd as $oid => $val) {
        if($c++ > 5) break;
        $idx = substr(strrchr($oid, "."), 1);
        echo "   Port $idx: VLAN " . preg_replace('/[^0-9]/', '', $val) . "\n";
    }
} else {
    echo "❌ No VLAN data from Standard MIB\n";
    
    // Test BDCOM VLAN
    echo "\n[5b] Testing VLAN (BDCOM Specific)...\n";
    $vlanBdcom = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.101.12.1.1.3", 3000000, 1);
    if($vlanBdcom && is_array($vlanBdcom) && count($vlanBdcom) > 0) {
        echo "✅ VLAN data available (BDCOM)\n";
    } else {
        echo "❌ No VLAN data from BDCOM MIB either\n";
    }
}

// Test 6: Optical Power - ENTITY-SENSOR
echo "\n[6] Testing Optical RX Power (ENTITY-SENSOR)...\n";
$rxEntity = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.99.1.1.1.4", 3000000, 1);
if($rxEntity && is_array($rxEntity) && count($rxEntity) > 0) {
    echo "✅ RX Power data available (ENTITY-SENSOR)\n";
    $c = 0;
    foreach($rxEntity as $oid => $val) {
        if($c++ > 5) break;
        $raw = preg_replace('/[^0-9.-]/', '', $val);
        echo "   Sensor: $raw (raw value)\n";
    }
} else {
    echo "❌ No ENTITY-SENSOR data\n";
    
    // Test BDCOM Optical
    echo "\n[6b] Testing Optical Power (BDCOM Specific)...\n";
    $rxBdcom = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.9.63.1.7.1.3", 3000000, 1);
    if($rxBdcom && is_array($rxBdcom) && count($rxBdcom) > 0) {
        echo "✅ RX Power data available (BDCOM)\n";
    } else {
        echo "❌ No BDCOM optical data - This switch may not support DDM or has no SFP modules\n";
    }
}

// Test 7: VCT / Fiber Distance
echo "\n[7] Testing VCT / Fiber Distance...\n";
$vct = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.9.63.1.7.1.5", 3000000, 1);
if($vct && is_array($vct) && count($vct) > 0) {
    echo "✅ VCT/Fiber distance data available\n";
} else {
    echo "❌ VCT data not available (BDCOM specific feature)\n";
}

echo "\n</pre>";
echo "<hr>";
echo "<h3>Summary</h3>";
echo "<pre>";
echo "SNMP Connection: " . ($sysDescr ? "✅ WORKING" : "❌ FAILED") . "\n";
echo "Port Names:      " . ($portNames ? "✅ WORKING" : "❌ FAILED") . "\n";
echo "Port Status:     " . ($portStatus ? "✅ WORKING" : "❌ FAILED") . "\n";
echo "VLAN Data:       " . (($vlanStd && count($vlanStd)>0) ? "✅ WORKING" : "❌ NOT AVAILABLE") . "\n";
echo "RX Power Data:   " . (($rxEntity && count($rxEntity)>0) ? "✅ WORKING" : "❌ NOT AVAILABLE (Check if SFP has DDM)") . "\n";
echo "VCT Distance:    " . (($vct && count($vct)>0) ? "✅ WORKING" : "❌ NOT AVAILABLE") . "\n";
echo "</pre>";
?>