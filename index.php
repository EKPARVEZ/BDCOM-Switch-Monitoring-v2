<?php
require_once 'config.php';

// যদি অলরেডি লগইন থাকে তবে ড্যাশবোর্ডে পাঠিয়ে দাও
if(isset($_SESSION['loggedin'])) { header("Location: dashboard.php"); exit; }

$error = "";

if(isset($_POST['login'])) {
    // HTML ফর্মের name="username" এবং name="password" এর সাথে মিল রেখে রিসিভ করা হয়েছে
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE username = '$username'");
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // পাসওয়ার্ড ভেরিফিকেশন
        if(password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "ভুল পাসওয়ার্ড!";
        }
    } else {
        $error = "ইউজার পাওয়া যায়নি!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | BDCOM NMS</title>
	<link rel="icon" type="image/png" sizes="32x32" href="bd.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:#f4f7f6;">

    <div class="card shadow-lg" style="width: 100%; max-width: 400px; border-radius: 15px; border: none;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="fas fa-network-wired fa-3x text-primary mb-3"></i>
                <h4 class="fw-bold">BDCOM SWITCH</h4>
                <p class="text-muted">Monitoring System Login</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger text-center p-2 small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">Username</label>
                    <input name="username" placeholder="Enter username" class="form-control" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="small fw-bold">Password</label>
                    <input type="password" name="password" placeholder="Enter password" class="form-control" required>
                </div>
                <button name="login" class="btn btn-primary w-100 py-2 fw-bold" style="border-radius: 8px;">
                    Login <i class="fas fa-sign-in-alt ms-1"></i>
                </button>
            </form>
        </div>
        <div class="card-footer bg-white text-center border-0 pb-4">
            <small class="text-muted">BD Technology v3.0 &copy; 2026</small>
        </div>
    </div>

</body>
</html>