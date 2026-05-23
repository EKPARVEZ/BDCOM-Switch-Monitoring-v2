<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$msg = "";
if(isset($_POST['save_tg'])) {
    $token = $conn->real_escape_string($_POST['tg_token']);
    $chat_id = $conn->real_escape_string($_POST['tg_chat_id']);
    
    $conn->query("UPDATE settings SET setting_value='$token' WHERE setting_key='tg_token'");
    $conn->query("UPDATE settings SET setting_value='$chat_id' WHERE setting_key='tg_chat_id'");
    $msg = "Settings Updated Successfully!";
}

// বর্তমান ডাটা আনা
$res = $conn->query("SELECT * FROM settings");
$set = [];
while($row = $res->fetch_assoc()) { $set[$row['setting_key']] = $row['setting_value']; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Telegram Settings | BDCOM Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <a href="dashboard.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4"><i class="fab fa-telegram text-primary"></i> Telegram Bot Settings</h4>
                    
                    <?php if($msg): ?>
                        <div class="alert alert-success"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Telegram Bot Token</label>
                            <input type="text" name="tg_token" class="form-control" value="<?= $set['tg_token'] ?? '' ?>" placeholder="123456789:ABCDefgh...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Telegram Chat ID</label>
                            <input type="text" name="tg_chat_id" class="form-control" value="<?= $set['tg_chat_id'] ?? '' ?>" placeholder="-100123456789">
                        </div>
                        <button name="save_tg" class="btn btn-primary w-100 fw-bold py-2">
                            <i class="fas fa-save me-1"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>