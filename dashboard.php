<?php
require_once 'config.php';

if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($switch_id == 0) {
    $first = $conn->query("SELECT id FROM switches LIMIT 1");
    $sw_row = $first->fetch_assoc();
    $switch_id = $sw_row ? $sw_row['id'] : 0;
}

$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | BDCOM Monitor</title>
	<link rel="icon" type="image/png" sizes="32x32" href="bd.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', system-ui; }
        .sidebar { min-height: calc(100vh - 56px); background: #1a1a2e; color: #a0a0b0; }
        .sidebar .nav-link { color: #a0a0b0; border-radius: 8px; margin: 4px 12px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #16213e; color: #fff; }
        .card-stats { border: none; border-radius: 16px; transition: 0.2s; }
        .table-responsive { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .badge-up { background: #d1fae5; color: #065f46; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .badge-down { background: #fee2e2; color: #991b1b; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .badge-fiber { background: #fef3c7; color: #92400e; font-weight: 600; }
        .event-timeline { font-size: 11px; line-height: 1.3; max-width: 180px; }
        .port-desc { font-size: 11px; line-height: 1.3; max-width: 180px; }
        .fiber-cut { background: #fef3c7; color: #d97706; }
        .fiber-cut-badge { background: #dc2626; color: white; font-size: 10px; padding: 2px 8px; border-radius: 12px; }
        .blink-red { animation: blink 1s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.5} }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">BDCOM <span class="text-primary">NMS</span></a>
        <div class="ms-auto">
            <span class="text-light small me-3"><i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 sidebar p-0 pt-3">
            <div class="px-3 mb-2 text-uppercase small fw-bold">Devices</div>
            <ul class="nav flex-column">
                <?php
                $sw_menu = $conn->query("SELECT id, name FROM switches ORDER BY name ASC");
                while($m_row = $sw_menu->fetch_assoc()) {
                    $active = ($m_row['id'] == $switch_id) ? "active" : "";
                    echo "<li class='nav-item'><a class='nav-link {$active}' href='dashboard.php?id={$m_row['id']}'><i class='fas fa-hdd me-2'></i> " . htmlspecialchars($m_row['name']) . "</a></li>";
                }
                ?>
                <hr class="text-secondary opacity-25 my-3 mx-2">
                <li class="nav-item"><a class="nav-link" href="devices.php"><i class="fas fa-edit me-2"></i> Device Manager</a></li>
                <li class="nav-item"><a class="nav-link" href="port_config.php"><i class="fas fa-tag me-2"></i> Port Description</a></li>
                <li class="nav-item"><a class="nav-link" href="event_log.php"><i class="fas fa-history me-2"></i> Event Timeline</a></li>
			
         <li class="nav-item">   <a href="settings.php" class="nav-link"><i class="fab fa-telegram me-2"></i> Bot Settings</a></li>
         <li class="nav-item">   <a href="users.php" class="nav-link"><i class="fas fa-users-cog me-2"></i> User Access</a></li></li>
          <li class="nav-item">  <a href="support.php" class="nav-link"><i class="fas fa-headset me-2"></i> Support</a></li>
            </ul>
        </div>

        <div class="col-lg-10 ms-sm-auto p-4">
            <?php if(!$switch): ?>
                <div class="alert alert-warning">No active switches found.</div>
            <?php else: ?>
                
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1"><?= htmlspecialchars($switch['name']) ?></h3>
                        <p class="text-muted mb-0 small">
                            IP: <?= htmlspecialchars($switch['ip_address']) ?> | Model: BDCOM
                            <span id="lastUpdate" class="ms-3 text-primary"><i class="fas fa-sync-alt fa-spin"></i> Loading...</span>
                        </p>
                    </div>
                    <div>
                        <span id="connectionStatus" class="badge bg-secondary px-3 py-2">Connecting...</span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card card-stats p-3">
                            <div class="text-muted small">Active Ports</div>
                            <h3 id="upPorts" class="fw-bold mb-0 text-success">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-stats p-3">
                            <div class="text-muted small">Down / Fault</div>
                            <h3 id="downPorts" class="fw-bold mb-0 text-danger">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-stats p-3">
                            <div class="text-muted small">Fiber Cut Today</div>
                            <h3 id="fiberCutCount" class="fw-bold mb-0 text-warning">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-stats p-3">
                            <div class="text-muted small">Last Alert</div>
                            <h6 id="lastAlertTime" class="fw-bold mb-0">-</h6>
                        </div>
                    </div>
                </div>

                <!-- Ports Table -->
                <div class="card shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-white border-bottom">
                        <h5 class="fw-bold mb-0"><i class="fas fa-table me-2 text-primary"></i> Ports Status Matrix</h5>
                    </div>
                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table class="table table-hover mb-0" id="portsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">Port</th>
                                    <th>Interface</th>
                                    <th>Description / Customer</th>
                                    <th>VLAN</th>
                                    <th>TX Power</th>
                                    <th>RX Power</th>
                                    <th>VCT / Fiber Dist</th>
                                    <th>Traffic</th>
                                    <th>Status</th>
                                    <th class="pe-3">Last Event / Time</th>
                                </tr>
                            </thead>
                            <tbody id="portsContainer">
                                <tr><td colspan="10" class="text-center py-5"><div class="spinner-border text-primary"></div> Loading ports...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Description Modal -->
<div class="modal fade" id="editDescModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Port Description</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDescForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_port_index">
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" id="edit_customer" class="form-control" placeholder="e.g., ABC Company">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location / Address</label>
                        <input type="text" id="edit_location" class="form-control" placeholder="e.g., Pulerhat, Jashore">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="edit_phone" class="form-control" placeholder="e.g., 017XXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea id="edit_desc" class="form-control" rows="2" placeholder="Additional information..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Description</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const switchId = <?= $switch_id ?>;
let refreshTimer = null;

function startRefresh() {
    if(refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(updateData, 5000);
}

function showEditModal(portIndex) {
    // Redirect to port config page
    window.location.href = `port_config.php?switch_id=${switchId}`;
}

function updateData() {
    if (switchId === 0) return;

    fetch(`fetch_traffic.php?id=${switchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('connectionStatus').className = 'badge bg-danger';
                document.getElementById('connectionStatus').textContent = 'Error: ' + data.error;
                return;
            }

            document.getElementById('connectionStatus').className = 'badge bg-success';
            document.getElementById('connectionStatus').textContent = 'Live';
            
            const container = document.getElementById('portsContainer');
            const loadingRow = container.querySelector('tr td[colspan="10"]');
            if (loadingRow && loadingRow.parentElement) {
                loadingRow.parentElement.remove();
            }

            let upCount = 0, downCount = 0;

            data.forEach(port => {
                if (port.status === 'UP') upCount++;
                else if (port.status === 'DOWN') downCount++;

                let statusBadge = port.status === 'UP' ? 
                    `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>UP</span>` : 
                    `<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>DOWN</span>`;

                let vlanBadge = (!port.vlan || port.vlan === 'N/A') ? 
                    `<span class="badge bg-secondary">N/A</span>` : 
                    `<span class="badge bg-info">${port.vlan}</span>`;

                let rxClass = "text-secondary";
                if(port.rx && port.rx !== "N/A") {
                    let rxNum = parseFloat(port.rx);
                    if(rxNum < -28) rxClass = "text-danger fw-bold";
                    else if(rxNum < -22) rxClass = "text-warning fw-bold";
                    else if(rxNum > -15) rxClass = "text-success fw-bold";
                }

                let distanceHtml = `<span class="badge bg-secondary">${port.fiber_distance || 'Active'}</span>`;
                if(port.fiber_distance && (port.fiber_distance.includes('Cut') || port.fiber_distance.includes('loss'))) {
                    distanceHtml = `<span class="badge bg-warning text-dark">⚠️ ${port.fiber_distance}</span>`;
                } else if(port.fiber_distance && port.fiber_distance.includes('Weak')) {
                    distanceHtml = `<span class="badge bg-warning text-dark">⚠️ ${port.fiber_distance}</span>`;
                }

                let existingRow = document.getElementById(`port-row-${port.index}`);
                if (existingRow) {
                    existingRow.querySelector('.port-status').innerHTML = statusBadge;
                    existingRow.querySelector('.port-vlan').innerHTML = vlanBadge;
                    existingRow.querySelector('.port-rx').innerHTML = port.rx;
                    existingRow.querySelector('.port-tx').innerHTML = port.tx;
                    existingRow.querySelector('.port-traffic').innerHTML = `<b>${port.mbps}</b> <small>Mbps</small>`;
                    existingRow.querySelector('.port-distance').innerHTML = distanceHtml;
                    existingRow.querySelector('.port-rx').className = `port-rx ${rxClass}`;
                    existingRow.querySelector('.port-desc').innerHTML = port.description;
                    existingRow.querySelector('.port-last-event').innerHTML = port.last_event;
                    if(port.row_class) {
                        existingRow.className = port.row_class;
                    }
                } else {
                    let tr = document.createElement('tr');
                    tr.id = `port-row-${port.index}`;
                    if(port.row_class) tr.className = port.row_class;
                    tr.innerHTML = `
                        <td class="ps-3 fw-bold text-primary" style="cursor:pointer" onclick="showEditModal('${port.index}')">${port.index} <i class="fas fa-pen text-muted fa-xs"></i></td>
                        <td class="fw-bold">${escapeHtml(port.name)}</td>
                        <td class="port-desc"><div class="small">${port.description}</div></td>
                        <td class="port-vlan">${vlanBadge}</td>
                        <td class="port-tx">${port.tx}</td>
                        <td class="port-rx ${rxClass}">${port.rx}</td>
                        <td class="port-distance">${distanceHtml}</td>
                        <td class="port-traffic"><b>${port.mbps}</b> <small>Mbps</small></td>
                        <td class="port-status">${statusBadge}</td>
                        <td class="port-last-event pe-3"><div class="small">${port.last_event}</div></td>
                    `;
                    container.appendChild(tr);
                }
            });

            document.getElementById('upPorts').textContent = upCount;
            document.getElementById('downPorts').textContent = downCount;
            document.getElementById('lastUpdate').innerHTML = `<i class="fas fa-clock me-1"></i> ${new Date().toLocaleTimeString()}`;
        })
        .catch(err => {
            console.error("Error:", err);
            document.getElementById('connectionStatus').className = 'badge bg-warning';
            document.getElementById('connectionStatus').textContent = 'Retrying...';
        });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

if(switchId > 0) {
    updateData();
    startRefresh();
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>