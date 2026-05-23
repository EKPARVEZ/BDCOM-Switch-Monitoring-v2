<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$switch_id = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
if($switch_id == 0) {
    $first = $conn->query("SELECT id FROM switches LIMIT 1");
    $sw = $first->fetch_assoc();
    $switch_id = $sw ? $sw['id'] : 0;
}

$switches = $conn->query("SELECT id, name FROM switches ORDER BY name");
$ports = [];
$portNames = [];

if($switch_id > 0) {
    $sw = $conn->query("SELECT * FROM switches WHERE id = $switch_id")->fetch_assoc();
    if($sw) {
        $ip = $sw['ip_address'];
        $com = $sw['community'];
        if(strpos($ip, ':') !== false) list($ip, $p) = explode(':', $ip);
        $target = $ip . ":" . ($p ?? 161);
        
        $names = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2", 2000000, 1);
        if($names) {
            foreach($names as $oid => $val) {
                $idx = substr(strrchr($oid, "."), 1);
                $name = str_replace('"', '', $val);
                if(stripos($name, 'vlan') === false && stripos($name, 'bridge') === false && $name != '') {
                    $portNames[$idx] = $name;
                }
            }
        }
    }
    
    $descRes = $conn->query("SELECT * FROM port_descriptions WHERE switch_id = $switch_id");
    while($d = $descRes->fetch_assoc()) {
        $ports[$d['port_index']] = $d;
    }
}

// Save port description
if(isset($_POST['save'])) {
    $port_index = $conn->real_escape_string($_POST['port_index']);
    $customer_name = $conn->real_escape_string($_POST['customer_name']);
    $location = $conn->real_escape_string($_POST['location']);
    $customer_phone = $conn->real_escape_string($_POST['customer_phone']);
    $description = $conn->real_escape_string($_POST['description']);
    
    $conn->query("INSERT INTO port_descriptions (switch_id, port_index, customer_name, location, customer_phone, description) 
        VALUES ($switch_id, '$port_index', '$customer_name', '$location', '$customer_phone', '$description')
        ON DUPLICATE KEY UPDATE 
        customer_name = VALUES(customer_name),
        location = VALUES(location),
        customer_phone = VALUES(customer_phone),
        description = VALUES(description)");
    
    header("Location: port_config.php?switch_id=$switch_id&msg=saved");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Port Configuration | BDCOM NMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { min-height: 100vh; background: #1a1a2e; }
        .sidebar .nav-link { color: #a0a0b0; }
        .sidebar .nav-link:hover { color: white; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3">
            <h5 class="text-white mb-4"><i class="fas fa-tag me-2"></i>Port Config</h5>
            <a href="dashboard.php?id=<?= $switch_id ?>" class="nav-link d-block mb-2"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            <hr class="text-secondary">
            <a href="devices.php" class="nav-link d-block"><i class="fas fa-server me-2"></i>Device Manager</a>
            <a href="event_log.php" class="nav-link d-block"><i class="fas fa-history me-2"></i>Event Log</a>
            <a href="logout.php" class="nav-link d-block text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
        
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-address-card text-primary me-2"></i>Port / Customer Information</h3>
                <select class="form-select w-auto" onchange="location.href='port_config.php?switch_id='+this.value">
                    <option value="0">Select Switch</option>
                    <?php while($s = $switches->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>" <?= $switch_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if(isset($_GET['msg'])): ?>
                <div class="alert alert-success">Information saved successfully!</div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white fw-bold">Add/Edit Port Info</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Port Index</label>
                                    <select name="port_index" class="form-select" required>
                                        <option value="">Select Port</option>
                                        <?php foreach($portNames as $idx => $name): ?>
                                        <option value="<?= $idx ?>"><?= $idx ?> - <?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control" placeholder="e.g., ABC Company">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Location / Address</label>
                                    <input type="text" name="location" class="form-control" placeholder="e.g., Pulerhat, Jashore">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="customer_phone" class="form-control" placeholder="e.g., 017XXXXXXXX">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description / Notes</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Additional information..."></textarea>
                                </div>
                                <button type="submit" name="save" class="btn btn-primary w-100">Save Information</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white fw-bold">Saved Port Information</div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>Port</th><th>Customer Name</th><th>Location</th><th>Phone</th><th>Actions</th</tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($ports)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No data saved yet</td></tr>
                                    <?php else: ?>
                                    <?php foreach($ports as $idx => $p): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $idx ?></td>
                                        <td><?= htmlspecialchars($p['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($p['location']) ?></td>
                                        <td><?= htmlspecialchars($p['customer_phone']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPort('<?= $idx ?>', '<?= addslashes($p['customer_name']) ?>', '<?= addslashes($p['location']) ?>', '<?= addslashes($p['customer_phone']) ?>', '<?= addslashes($p['description']) ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editPort(idx, customer, location, phone, desc) {
    const form = document.querySelector('form');
    form.querySelector('[name="port_index"]').value = idx;
    form.querySelector('[name="customer_name"]').value = customer;
    form.querySelector('[name="location"]').value = location;
    form.querySelector('[name="customer_phone"]').value = phone;
    form.querySelector('[name="description"]').value = desc;
    form.scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>