<?php
require_once 'config.php';
header('Content-Type: application/json');
error_reporting(0);

class FiberOTDR {
    private $conn;
    private $switch_id;
    private $target;
    private $community;
    private $switch_model;
    private $switch_name;
    
    const BDCOM_SCALE_FACTOR = 100;
    const ATTN_1270 = 0.42;
    const ATTN_1310 = 0.35;
    const ATTN_1550 = 0.25;
    const TOTAL_FIXED_LOSS = 1.2;  // Connector + Splice loss
    
    public function __construct($conn, $switch_id, $target, $community, $switch_model = 'BDCOM', $switch_name = '') {
        $this->conn = $conn;
        $this->switch_id = $switch_id;
        $this->target = $target;
        $this->community = $community;
        $this->switch_model = $switch_model;
        $this->switch_name = $switch_name;
    }
    
    /**
     * Detect switch model from sysDescr if not provided
     */
    private function detectModel() {
        if($this->switch_model != 'BDCOM') return $this->switch_model;
        
        $sysDescr = @snmp2_get($this->target, $this->community, ".1.3.6.1.2.1.1.1.0", 2000000, 1);
        if($sysDescr) {
            if(strpos($sysDescr, 'S2900') !== false) return 'S2900';
            if(strpos($sysDescr, 'S2928E') !== false) return 'S2928E';
            if(strpos($sysDescr, 'S2510') !== false) return 'S2510';
            if(strpos($sysDescr, 'P3310') !== false) return 'P3310';
        }
        return 'BDCOM';
    }
    
    /**
     * Get RX Power - Supports multiple models
     */
    public function getRxPower($port_index) {
        $model = $this->detectModel();
        $rxOIDs = [];
        
        // BDCOM S2900 / S2928E / S2510 series
        if(in_array($model, ['S2900', 'S2928E', 'S2510', 'P3310', 'BDCOM'])) {
            $rxOIDs = [
                ".1.3.6.1.4.1.3320.9.63.1.7.1.3." . $port_index,  // Primary
                ".1.3.6.1.4.1.3320.101.10.5.1.5." . $port_index, // Alternative
                ".1.3.6.1.4.1.3320.2.5.1.1.1.1.10." . $port_index, // DOM
            ];
        }
        
        // Generic ENTITY-SENSOR (works on many devices)
        $rxOIDs[] = ".1.3.6.1.2.1.99.1.1.1.4." . $port_index;
        
        foreach($rxOIDs as $oid) {
            $result = @snmp2_get($this->target, $this->community, $oid, 2000000, 1);
            if($result && $result !== 'FALSE' && strpos($result, 'No Such') === false) {
                $value = $this->parseValue($result, $model);
                if($value !== null && $value > -50 && $value < 10) {
                    return ['value' => $value, 'raw' => $result, 'oid' => $oid];
                }
            }
        }
        return ['value' => null, 'raw' => null, 'oid' => null];
    }
    
    /**
     * Get TX Power
     */
    public function getTxPower($port_index) {
        $model = $this->detectModel();
        $txOIDs = [];
        
        if(in_array($model, ['S2900', 'S2928E', 'S2510', 'P3310', 'BDCOM'])) {
            $txOIDs = [
                ".1.3.6.1.4.1.3320.9.63.1.7.1.2." . $port_index,
                ".1.3.6.1.4.1.3320.101.10.5.1.6." . $port_index,
            ];
        }
        
        $txOIDs[] = ".1.3.6.1.2.1.99.1.1.1.5." . $port_index;
        
        foreach($txOIDs as $oid) {
            $result = @snmp2_get($this->target, $this->community, $oid, 2000000, 1);
            if($result && $result !== 'FALSE' && strpos($result, 'No Such') === false) {
                $value = $this->parseValue($result, $model);
                if($value !== null && $value > -50 && $value < 10) {
                    return ['value' => $value, 'raw' => $result, 'oid' => $oid];
                }
            }
        }
        return ['value' => null, 'raw' => null, 'oid' => null];
    }
    
    /**
     * Parse BDCOM value (divide by 100)
     */
    private function parseValue($raw, $model) {
        if(!$raw || $raw === 'FALSE') return null;
        $clean = trim(str_replace('"', '', $raw));
        $num = (float)preg_replace('/[^0-9.-]/', '', $clean);
        
        // BDCOM series returns values multiplied by 100
        if(in_array($model, ['S2900', 'S2928E', 'S2510', 'P3310', 'BDCOM'])) {
            if($num > 100 || $num < -100) {
                $num = $num / self::BDCOM_SCALE_FACTOR;
            }
        }
        return round($num, 2);
    }
    
    /**
     * Get port status (1=UP, 2=DOWN) - IMPROVED
     */
    public function getPortStatus($port_index) {
        // Try standard IF-MIB::ifOperStatus
        $oid = ".1.3.6.1.2.1.2.2.1.8." . $port_index;
        $result = @snmp2_get($this->target, $this->community, $oid, 2000000, 1);
        
        if($result && $result !== 'FALSE' && strpos($result, 'No Such') === false) {
            $status = (int)$result;
            if($status == 1) return 'UP';
            if($status == 2) return 'DOWN';
            if($status == 3) return 'TESTING';
            if($status == 4) return 'UNKNOWN';
            if($status == 5) return 'DORMANT';
            if($status == 6) return 'NOT_PRESENT';
            if($status == 7) return 'LOWER_LAYER_DOWN';
            return 'DOWN';
        }
        
        // Try alternative OID for BDCOM
        $altOid = ".1.3.6.1.2.1.2.2.1.7." . $port_index; // ifAdminStatus
        $altResult = @snmp2_get($this->target, $this->community, $altOid, 2000000, 1);
        if($altResult && $altResult !== 'FALSE' && strpos($altResult, 'No Such') === false) {
            $status = (int)$altResult;
            if($status == 1) return 'UP';
            if($status == 2) return 'DOWN';
        }
        
        // If no status, check if we have RX power - if RX power exists, port is likely UP
        $rx = $this->getRxPower($port_index);
        if($rx['value'] !== null && $rx['value'] > -50) {
            return 'UP'; // Assume UP if we have valid RX power
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * Get port name
     */
    public function getPortName($port_index) {
        $oid = ".1.3.6.1.2.1.2.2.1.2." . $port_index;
        $result = @snmp2_get($this->target, $this->community, $oid, 2000000, 1);
        if($result && $result !== 'FALSE' && strpos($result, 'No Such') === false) {
            return trim(str_replace('"', '', $result));
        }
        return "Port {$port_index}";
    }
    
    /**
     * Get port admin status (enabled/disabled)
     */
    public function getPortAdminStatus($port_index) {
        $oid = ".1.3.6.1.2.1.2.2.1.7." . $port_index;
        $result = @snmp2_get($this->target, $this->community, $oid, 2000000, 1);
        if($result && $result !== 'FALSE' && strpos($result, 'No Such') === false) {
            $status = (int)$result;
            if($status == 1) return 'ENABLED';
            if($status == 2) return 'DISABLED';
        }
        return 'UNKNOWN';
    }
    
    /**
     * Calculate fiber distance
     * Formula: Distance = (TX - RX - FixedLoss) / Attenuation
     */
    public function calculateDistance($rx_power, $tx_power = null, $port_index = null) {
        $attenuation = self::ATTN_1270;  // 0.42 dB/km for 1270nm
        $fixed_loss = self::TOTAL_FIXED_LOSS;  // 1.2 dB
        $tx_default = 3.0;  // Default TX for 1270nm SFP
        
        if($tx_power && $tx_power['value'] !== null && $tx_power['value'] > -20) {
            $tx_default = $tx_power['value'];
        }
        
        // Check calibration
        if($port_index) {
            $cal = $this->conn->query("SELECT * FROM fiber_calibration 
                WHERE switch_id = {$this->switch_id} AND port_index = '$port_index'");
            if($cal && $cal->num_rows > 0) {
                $c = $cal->fetch_assoc();
                if($c['tx_reference']) $tx_default = (float)$c['tx_reference'];
                if($c['attenuation_per_km']) $attenuation = (float)$c['attenuation_per_km'];
                if($c['connector_loss']) $fixed_loss = (float)$c['connector_loss'];
            }
        }
        
        if($rx_power['value'] === null) {
            return ['distance_km' => null, 'message' => 'No optical power data', 'quality' => null];
        }
        
        $rx_val = $rx_power['value'];
        
        // Fiber cut detection
        if($rx_val < -35) {
            return ['distance_km' => 'FIBER_CUT', 'message' => 'FIBER CUT - No light', 'quality' => ['level' => 'NO_SIGNAL', 'icon' => '🔴']];
        }
        
        $total_loss = $tx_default - $rx_val;
        $fiber_loss = $total_loss - $fixed_loss;
        
        // Very short link (<100m)
        if($fiber_loss <= 0.2) {
            return [
                'distance_km' => 0.1,
                'distance_m' => 100,
                'total_loss_db' => round($total_loss, 2),
                'message' => 'Short link (<100m)',
                'quality' => $this->getSignalQuality($rx_val)
            ];
        }
        
        $distance_km = round($fiber_loss / $attenuation, 2);
        $distance_m = round($distance_km * 1000);
        
        // Sanity checks
        if($distance_km > 80) $distance_km = 80;
        if($distance_km < 0) $distance_km = 0;
        
        return [
            'distance_km' => $distance_km,
            'distance_m' => $distance_m,
            'total_loss_db' => round($total_loss, 2),
            'fiber_loss_db' => round($fiber_loss, 2),
            'tx_reference' => round($tx_default, 2),
            'message' => 'Calculated from optical loss',
            'quality' => $this->getSignalQuality($rx_val)
        ];
    }
    
    private function getSignalQuality($rx_power) {
        if($rx_power >= -12) return ['level' => 'EXCELLENT', 'icon' => '🟢', 'desc' => 'Strong signal'];
        if($rx_power >= -16) return ['level' => 'GOOD', 'icon' => '🟢', 'desc' => 'Good signal'];
        if($rx_power >= -20) return ['level' => 'FAIR', 'icon' => '🟡', 'desc' => 'Acceptable'];
        if($rx_power >= -24) return ['level' => 'POOR', 'icon' => '🟠', 'desc' => 'Weak signal'];
        if($rx_power >= -28) return ['level' => 'CRITICAL', 'icon' => '🔴', 'desc' => 'Very weak'];
        return ['level' => 'NO_SIGNAL', 'icon' => '⚫', 'desc' => 'No signal'];
    }
    
    /**
     * Analyze all ports - Works for all BDCOM models
     */
    public function analyzeAllPorts() {
        $portNames = @snmp2_real_walk($this->target, $this->community, ".1.3.6.1.2.1.2.2.1.2", 3000000, 1);
        if(!$portNames || !is_array($portNames)) {
            return ['error' => 'No ports found'];
        }
        
        $results = [];
        foreach($portNames as $oid => $name) {
            $index = substr(strrchr($oid, "."), 1);
            $port_name = trim(str_replace('"', '', $name));
            
            // Skip virtual ports
            if(stripos($port_name, 'aggregator') !== false || 
               stripos($port_name, 'Null') !== false ||
               stripos($port_name, 'vlan') !== false ||
               $port_name == '') {
                continue;
            }
            
            // Check if optical port (Giga or TGiga)
            $is_optical = (stripos($port_name, 'Giga') !== false || 
                          stripos($port_name, 'TGiga') !== false);
            
            $rx = null; $tx = null; $distance = null;
            $status = $this->getPortStatus($index);
            $admin_status = $this->getPortAdminStatus($index);
            
            if($is_optical) {
                $rx = $this->getRxPower($index);
                $tx = $this->getTxPower($index);
                $distance = $this->calculateDistance($rx, $tx, $index);
                
                // Override status based on RX power if needed
                if($rx['value'] !== null && $rx['value'] > -35 && $status == 'DOWN') {
                    $status = 'UP'; // Port is actually UP if we have good RX power
                }
                if($rx['value'] !== null && $rx['value'] <= -35) {
                    $status = 'DOWN'; // No light
                }
            }
            
            $results[] = [
                'port_index' => $index,
                'port_name' => $port_name,
                'rx_power' => $rx ? $rx['value'] : null,
                'tx_power' => $tx ? $tx['value'] : null,
                'status' => $status,
                'admin_status' => $admin_status,
                'distance_km' => $distance ? $distance['distance_km'] : null,
                'distance_m' => $distance ? $distance['distance_m'] : null,
                'total_loss_db' => $distance ? $distance['total_loss_db'] : null,
                'quality' => $distance ? $distance['quality'] : null,
                'message' => $distance ? $distance['message'] : 'No optical data'
            ];
        }
        
        return $results;
    }
}

// ============================================
// API ENDPOINT
// ============================================

if(isset($_GET['action'])) {
    $switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if($switch_id == 0) {
        echo json_encode(['error' => 'No switch ID provided']);
        exit;
    }
    
    $sw = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
    if(!$sw || $sw->num_rows == 0) {
        echo json_encode(['error' => 'Switch not found']);
        exit;
    }
    
    $switch = $sw->fetch_assoc();
    $ip = $switch['ip_address'];
    $com = $switch['community'];
    $model = $switch['model'] ?? 'BDCOM';
    $name = $switch['name'];
    
    if(strpos($ip, ':') !== false) {
        list($ip_addr, $snmp_port) = explode(':', $ip);
    } else {
        $ip_addr = $ip;
        $snmp_port = 161;
    }
    $target = $ip_addr . ":" . $snmp_port;
    
    $otdr = new FiberOTDR($conn, $switch_id, $target, $com, $model, $name);
    
    if($_GET['action'] == 'analyze') {
        echo json_encode($otdr->analyzeAllPorts());
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

echo json_encode(['error' => 'No action specified']);
?>