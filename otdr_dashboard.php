<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

// সব সুইচের তথ্য নিন
$switches = $conn->query("SELECT id, name, ip_address, community, model FROM switches WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi-Switch OTDR | Fiber Distance Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; font-family: 'Segoe UI', monospace; }
        .switch-card { background: #1e293b; border-radius: 16px; border: 1px solid #334155; margin-bottom: 24px; overflow: hidden; }
        .switch-header { background: #0f172a; padding: 12px 20px; border-bottom: 1px solid #334155; cursor: pointer; }
        .switch-header:hover { background: #1a2744; }
        .port-table { font-size: 12px; }
        .port-table th { background: #0f172a; color: #94a3b8; font-weight: 500; }
        .rx-good { color: #10b981; font-weight: bold; }
        .rx-warning { color: #f59e0b; font-weight: bold; }
        .rx-critical { color: #ef4444; font-weight: bold; }
        .distance-badge { padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .distance-short { background: #065f46; color: #d1fae5; }
        .distance-medium { background: #92400e; color: #fef3c7; }
        .distance-long { background: #991b1b; color: #fee2e2; }
        .fiber-cut { background: #450a0a; color: #fca5a5; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.7} }
        .badge-up { background: #10b981; }
        .badge-down { background: #ef4444; }
        .badge-unknown { background: #6b7280; }
        .loading { display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .summary-card { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 12px; padding: 15px; text-align: center; }
        .error-message { color: #ef4444; background: #450a0a; padding: 8px; border-radius: 8px; }
        .retry-btn { background: #3b82f6; color: white; border: none; padding: 4px 12px; border-radius: 6px; font-size: 12px; }
        .retry-btn:hover { background: #2563eb; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-4 py-3 mb-4">
    <div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i> Back</a>
        <span class="navbar-brand mb-0 h5"><i class="fas fa-microscope text-primary me-2"></i>Multi-Switch OTDR Analyzer</span>
    </div>
    <div>
        <span class="text-secondary small"><i class="fas fa-chart-line me-1"></i> 1270nm | 0.42 dB/km</span>
        <button class="btn btn-sm btn-outline-primary ms-3" onclick="refreshAll()"><i class="fas fa-sync-alt"></i> Refresh All</button>
    </div>
</nav>

<div class="container-fluid px-4">
    <!-- Summary Cards -->
    <div class="row mb-4" id="summaryCards">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-secondary small">Total Switches</div>
                <h3 class="text-white mb-0" id="totalSwitches">0</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-secondary small">Active 10G Links</div>
                <h3 class="text-success mb-0" id="activeLinks">0</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-secondary small">Fiber Cuts</div>
                <h3 class="text-danger mb-0" id="fiberCuts">0</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card">
                <div class="text-secondary small">Avg Distance</div>
                <h3 class="text-warning mb-0" id="avgDistance">0<span class="small"> km</span></h3>
            </div>
        </div>
    </div>

    <!-- Switches Container -->
    <div id="switchesContainer"></div>
</div>

<script>
const switches = <?php 
    $sw_list = [];
    while($sw = $switches->fetch_assoc()) {
        $sw_list[] = $sw;
    }
    echo json_encode($sw_list);
?>;

let allData = {};
let loadingCount = 0;

function refreshAll() {
    document.getElementById('switchesContainer').innerHTML = '<div class="text-center py-5"><div class="loading me-2"></div> Loading all switches...</div>';
    
    let promises = [];
    loadingCount = switches.length;
    
    switches.forEach(sw => {
        promises.push(
            fetch(`otdr_distance.php?action=analyze&id=${sw.id}&_=${Date.now()}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    allData[sw.id] = { switch: sw, ports: data, error: null, lastUpdate: new Date() };
                    return { id: sw.id, data: data };
                })
                .catch(err => {
                    console.error(`Error loading switch ${sw.id}:`, err);
                    allData[sw.id] = { switch: sw, ports: [], error: err.message, lastUpdate: new Date() };
                    return { id: sw.id, error: err.message };
                })
        );
    });
    
    Promise.all(promises).then(() => {
        renderAllSwitches();
        updateSummary();
    });
}

function renderAllSwitches() {
    const container = document.getElementById('switchesContainer');
    container.innerHTML = '';
    
    for (const [id, data] of Object.entries(allData)) {
        const sw = data.switch;
        let ports = data.ports;
        const hasError = data.error;
        
        // Handle error case
        if (hasError) {
            const errorCard = document.createElement('div');
            errorCard.className = 'switch-card';
            errorCard.innerHTML = `
                <div class="switch-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-server me-2 text-danger"></i>
                        <strong class="text-white">${sw.name}</strong>
                        <span class="text-secondary ms-2 small">${sw.ip_address}</span>
                    </div>
                    <div>
                        <span class="badge bg-danger me-2"><i class="fas fa-exclamation-triangle"></i> Error</span>
                        <button class="retry-btn" onclick="refreshSingleSwitch(${sw.id})"><i class="fas fa-sync-alt"></i> Retry</button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="error-message text-center">
                        <i class="fas fa-exclamation-circle me-2"></i> ${hasError}
                    </div>
                </div>
            `;
            container.appendChild(errorCard);
            continue;
        }
        
        // Check if ports is array and has error property
        if (ports && ports.error) {
            const errorCard = document.createElement('div');
            errorCard.className = 'switch-card';
            errorCard.innerHTML = `
                <div class="switch-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-server me-2 text-warning"></i>
                        <strong class="text-white">${sw.name}</strong>
                        <span class="text-secondary ms-2 small">${sw.ip_address}</span>
                    </div>
                    <div>
                        <span class="badge bg-warning me-2">Warning</span>
                        <button class="retry-btn" onclick="refreshSingleSwitch(${sw.id})"><i class="fas fa-sync-alt"></i> Retry</button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="error-message text-center">${ports.error}</div>
                </div>
            `;
            container.appendChild(errorCard);
            continue;
        }
        
        // Filter only optical ports with data
        const opticalPorts = Array.isArray(ports) ? ports.filter(p => 
            p.rx_power !== null && 
            (p.port_name.includes('TGiga') || p.port_name.includes('Giga'))
        ) : [];
        
        const upPorts = opticalPorts.filter(p => p.status === 'UP').length;
        const downPorts = opticalPorts.filter(p => p.status === 'DOWN').length;
        const fiberCuts = opticalPorts.filter(p => p.distance_km === 'FIBER_CUT').length;
        const unknownPorts = opticalPorts.filter(p => p.status === 'UNKNOWN').length;
        
        const switchCard = document.createElement('div');
        switchCard.className = 'switch-card';
        switchCard.innerHTML = `
            <div class="switch-header d-flex justify-content-between align-items-center" onclick="toggleSwitch('${id}')">
                <div>
                    <i class="fas fa-server me-2 text-primary"></i>
                    <strong class="text-white">${escapeHtml(sw.name)}</strong>
                    <span class="text-secondary ms-2 small">${escapeHtml(sw.ip_address)}</span>
                    ${data.lastUpdate ? `<span class="text-secondary ms-2 small"><i class="far fa-clock"></i> ${data.lastUpdate.toLocaleTimeString()}</span>` : ''}
                </div>
                <div>
                    <span class="badge bg-info me-2">${escapeHtml(sw.model || 'BDCOM')}</span>
                    <span class="badge bg-success me-2"><i class="fas fa-arrow-up"></i> ${upPorts}</span>
                    <span class="badge bg-danger me-2"><i class="fas fa-arrow-down"></i> ${downPorts}</span>
                    ${unknownPorts > 0 ? `<span class="badge bg-secondary me-2"><i class="fas fa-question"></i> ${unknownPorts}</span>` : ''}
                    ${fiberCuts > 0 ? `<span class="badge bg-danger"><i class="fas fa-cut"></i> ${fiberCuts} Cuts</span>` : ''}
                    <i class="fas fa-chevron-down ms-2 text-secondary" id="icon-${id}"></i>
                </div>
            </div>
            <div class="switch-body" id="body-${id}" style="display: block;">
                <div class="table-responsive">
                    <table class="table table-dark table-hover port-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Port</th>
                                <th>Interface</th>
                                <th>Status</th>
                                <th>Admin</th>
                                <th>RX Power</th>
                                <th>TX Power</th>
                                <th>Loss</th>
                                <th>Distance</th>
                                <th class="pe-3">Quality</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${opticalPorts.length === 0 ? 
                                `<tr><td colspan="9" class="text-center text-secondary">No optical ports with data</td></tr>` : 
                                opticalPorts.map(port => {
                                    let rxClass = '';
                                    let rxDisplay = 'N/A';
                                    if(port.rx_power !== null) {
                                        rxDisplay = port.rx_power + ' dBm';
                                        if(port.rx_power >= -16) rxClass = 'rx-good';
                                        else if(port.rx_power >= -24) rxClass = 'rx-warning';
                                        else rxClass = 'rx-critical';
                                    }
                                    
                                    let distanceHtml = '';
                                    if(port.distance_km === 'FIBER_CUT') {
                                        distanceHtml = '<span class="fiber-cut px-2 py-1 rounded"><i class="fas fa-cut me-1"></i>FIBER CUT</span>';
                                    } else if(port.distance_km !== null && port.distance_km > 0) {
                                        let distClass = port.distance_km <= 5 ? 'distance-short' : (port.distance_km <= 20 ? 'distance-medium' : 'distance-long');
                                        distanceHtml = `<span class="distance-badge ${distClass}">${port.distance_km} km</span><br><small class="text-secondary">~${port.distance_m} m</small>`;
                                    } else {
                                        distanceHtml = '<span class="text-secondary">—</span>';
                                    }
                                    
                                    let statusBadge = '';
                                    if(port.status === 'UP') {
                                        statusBadge = '<span class="badge badge-up">UP</span>';
                                    } else if(port.status === 'DOWN') {
                                        statusBadge = '<span class="badge badge-down">DOWN</span>';
                                    } else {
                                        statusBadge = '<span class="badge badge-unknown">UNKNOWN</span>';
                                    }
                                    
                                    let adminBadge = '';
                                    if(port.admin_status === 'ENABLED') {
                                        adminBadge = '<span class="badge bg-success">ENABLED</span>';
                                    } else if(port.admin_status === 'DISABLED') {
                                        adminBadge = '<span class="badge bg-danger">DISABLED</span>';
                                    } else {
                                        adminBadge = '<span class="badge bg-secondary">—</span>';
                                    }
                                    
                                    let qualityHtml = port.quality ? 
                                        `<span class="fs-6">${port.quality.icon}</span> ${port.quality.level}` : '—';
                                    
                                    let lossDisplay = port.total_loss_db !== null ? port.total_loss_db + ' dB' : '—';
                                    let txDisplay = port.tx_power !== null ? port.tx_power + ' dBm' : '—';
                                    
                                    return `
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary">${port.port_index}</td>
                                            <td class="font-monospace">${escapeHtml(port.port_name)}</td>
                                            <td>${statusBadge}</td>
                                            <td>${adminBadge}</td>
                                            <td class="${rxClass}">${rxDisplay}</td>
                                            <td>${txDisplay}</td>
                                            <td>${lossDisplay}</td>
                                            <td>${distanceHtml}</td>
                                            <td class="pe-3">${qualityHtml}</td>
                                        </tr>
                                    `;
                                }).join('')
                            }
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        container.appendChild(switchCard);
    }
}

function refreshSingleSwitch(switchId) {
    const switchData = switches.find(s => s.id == switchId);
    if (!switchData) return;
    
    // Show loading on that specific switch
    const container = document.getElementById('switchesContainer');
    const switchCard = container.querySelector(`.switch-card:has([onclick*="toggleSwitch('${switchId}')"])`);
    if (switchCard) {
        switchCard.style.opacity = '0.5';
    }
    
    fetch(`otdr_distance.php?action=analyze&id=${switchId}&_=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            allData[switchId] = { switch: switchData, ports: data, error: null, lastUpdate: new Date() };
            renderAllSwitches();
            updateSummary();
        })
        .catch(err => {
            console.error(`Error loading switch ${switchId}:`, err);
            allData[switchId] = { switch: switchData, ports: [], error: err.message, lastUpdate: new Date() };
            renderAllSwitches();
            updateSummary();
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

function toggleSwitch(id) {
    const body = document.getElementById(`body-${id}`);
    const icon = document.getElementById(`icon-${id}`);
    if (!body || !icon) return;
    
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.className = 'fas fa-chevron-down ms-2 text-secondary';
    } else {
        body.style.display = 'none';
        icon.className = 'fas fa-chevron-right ms-2 text-secondary';
    }
}

function updateSummary() {
    let totalSwitches = Object.keys(allData).length;
    let activeLinks = 0;
    let fiberCuts = 0;
    let totalDistance = 0;
    let distanceCount = 0;
    
    for (const [id, data] of Object.entries(allData)) {
        const ports = data.ports;
        if (Array.isArray(ports) && !ports.error) {
            const opticalPorts = ports.filter(p => p.rx_power !== null && (p.port_name.includes('TGiga') || p.port_name.includes('Giga')));
            activeLinks += opticalPorts.filter(p => p.status === 'UP').length;
            fiberCuts += opticalPorts.filter(p => p.distance_km === 'FIBER_CUT').length;
            opticalPorts.forEach(p => {
                if (p.distance_km !== null && p.distance_km !== 'FIBER_CUT' && typeof p.distance_km === 'number') {
                    totalDistance += p.distance_km;
                    distanceCount++;
                }
            });
        }
    }
    
    document.getElementById('totalSwitches').textContent = totalSwitches;
    document.getElementById('activeLinks').textContent = activeLinks;
    document.getElementById('fiberCuts').textContent = fiberCuts;
    document.getElementById('avgDistance').textContent = distanceCount > 0 ? (totalDistance / distanceCount).toFixed(1) : '0';
}

// Auto refresh every 60 seconds
refreshAll();
setInterval(refreshAll, 60000);
</script>
</body>
</html>