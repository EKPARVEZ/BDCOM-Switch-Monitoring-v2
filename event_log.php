<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$switch_id = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$switch_filter = "";
if($switch_id > 0) {
    $switch_filter = "AND e.switch_id = $switch_id";
}

$event_filter = "";
if($filter == 'down') {
    $event_filter = "AND e.event_type IN ('DOWN', 'FIBER_CUT')";
} elseif($filter == 'fiber') {
    $event_filter = "AND e.event_type = 'FIBER_CUT'";
} elseif($filter == 'recovered') {
    $event_filter = "AND e.event_type = 'RECOVERED'";
}

$events = $conn->query("SELECT e.*, s.name as switch_name 
    FROM port_events e 
    JOIN switches s ON e.switch_id = s.id 
    WHERE 1=1 $switch_filter $event_filter 
    ORDER BY e.event_time DESC LIMIT 500");

$switches = $conn->query("SELECT id, name FROM switches ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Timeline | BDCOM NMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .timeline-item { border-left: 3px solid; margin-bottom: 15px; padding: 12px 15px; background: white; border-radius: 8px; }
        .timeline-FIBER_CUT { border-left-color: #dc2626; background: #fef2f2; }
        .timeline-DOWN { border-left-color: #f59e0b; }
        .timeline-RECOVERED { border-left-color: #10b981; }
        .timeline-UP { border-left-color: #3b82f6; }
        .event-time { font-size: 12px; color: #6b7280; }
        .event-icon { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-4 py-2">
    <a href="dashboard.php" class="navbar-brand"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-history text-primary me-2"></i>Event Timeline</h2>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <?php if($filter == 'all'): ?>All Events<?php elseif($filter == 'down'): ?>Down Events<?php elseif($filter == 'fiber'): ?>Fiber Cuts<?php else: ?>Recovered<?php endif; ?>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="?filter=all">All Events</a></li>
                <li><a class="dropdown-item" href="?filter=down">Only Down / Fiber Cut</a></li>
                <li><a class="dropdown-item" href="?filter=fiber">Fiber Cut Only</a></li>
                <li><a class="dropdown-item" href="?filter=recovered">Recovered Only</a></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card p-3">
                <h6 class="fw-bold mb-3">Filter by Switch</h6>
                <select class="form-select" onchange="location.href='?switch_id='+this.value+'&filter=<?= $filter ?>'">
                    <option value="0">All Switches</option>
                    <?php while($sw = $switches->fetch_assoc()): ?>
                    <option value="<?= $sw['id'] ?>" <?= $switch_id == $sw['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sw['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="col-md-9">
            <?php if($events->num_rows == 0): ?>
                <div class="alert alert-info">No events found</div>
            <?php else: ?>
                <?php while($ev = $events->fetch_assoc()): ?>
                <div class="timeline-item timeline-<?= $ev['event_type'] ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="event-time"><?= date('d-M-Y h:i:s A', strtotime($ev['event_time'])) ?></div>
                            <h6 class="mb-1 mt-1">
                                <?php if($ev['event_type'] == 'FIBER_CUT'): ?>
                                    <span class="badge bg-danger me-2">⚠️ FIBER CUT</span>
                                <?php elseif($ev['event_type'] == 'DOWN'): ?>
                                    <span class="badge bg-warning me-2">🔴 DOWN</span>
                                <?php elseif($ev['event_type'] == 'RECOVERED'): ?>
                                    <span class="badge bg-success me-2">🟢 RECOVERED</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary me-2"><?= $ev['event_type'] ?></span>
                                <?php endif; ?>
                                <span class="fw-bold"><?= htmlspecialchars($ev['switch_name']) ?></span> - 
                                Port <?= $ev['port_index'] ?> (<?= htmlspecialchars($ev['port_name']) ?>)
                            </h6>
                            <div class="text-muted small"><?= nl2br(htmlspecialchars($ev['details'])) ?></div>
                            <div class="mt-2 small">
                                <?php if($ev['rx_power'] && $ev['rx_power'] != 'N/A'): ?>
                                    <span class="me-3"><i class="fas fa-arrow-down"></i> RX: <?= $ev['rx_power'] ?></span>
                                <?php endif; ?>
                                <?php if($ev['tx_power'] && $ev['tx_power'] != 'N/A'): ?>
                                    <span><i class="fas fa-arrow-up"></i> TX: <?= $ev['tx_power'] ?></span>
                                <?php endif; ?>
                                <?php if($ev['fiber_distance'] && $ev['fiber_distance'] != 'Active'): ?>
                                    <span class="ms-3"><i class="fas fa-route"></i> Distance: <?= $ev['fiber_distance'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>