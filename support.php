<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technical Support | NMS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #0f172a; --accent-blue: #0ea5e9; --bg-light: #f8fafc; }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; }
        .sidebar { min-height: 100vh; background: var(--sidebar-bg); color: white; }
        .nav-link { color: #cbd5e1; border-radius: 10px; margin-bottom: 5px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(14, 165, 233, 0.15); color: var(--accent-blue); }
        
        .support-card { border: none; border-radius: 20px; transition: 0.3s; overflow: hidden; }
        .support-header { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; padding: 40px; text-align: center; }
        .profile-img { width: 120px; height: 120px; border-radius: 50%; border: 5px solid rgba(255,255,255,0.2); margin-bottom: 15px; object-fit: cover; }
        
        .contact-item { padding: 15px; border-radius: 12px; background: #fff; margin-bottom: 15px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .contact-item:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--accent-blue); }
        .contact-icon { width: 45px; height: 45px; border-radius: 10px; background: rgba(14, 165, 233, 0.1); color: var(--accent-blue); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        .social-btn { width: 45px; height: 45px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 8px; transition: 0.3s; color: white; text-decoration: none; }
        .fb { background: #1877F2; } .wa { background: #25D366; } .ln { background: #0077B5; } .gh { background: #333; }
        .social-btn:hover { transform: scale(1.2) rotate(10deg); color: white; opacity: 0.9; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3 d-none d-md-block">
            <div class="text-center py-4">
                <i class="fas fa-headset fa-2x text-info mb-2"></i>
                <h5 class="fw-bold">SUPPORT HUB</h5>
            </div>
            <div class="nav flex-column mt-3">
                <a href="dashboard.php" class="nav-link px-3"><i class="fas fa-th-large me-2"></i> Dashboard</a>
                <a href="users.php" class="nav-link px-3"><i class="fas fa-users-cog me-2"></i> User Access</a>
                <a href="support.php" class="nav-link px-3 active"><i class="fas fa-envelope-open-text me-2"></i> Support</a>
                <hr class="border-secondary">
                <a href="logout.php" class="nav-link px-3 text-danger"><i class="fas fa-power-off me-2"></i> Logout</a>
            </div>
        </div>

        <div class="col-md-10 p-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card support-card shadow-sm">
                        <div class="support-header">
                            <img src="https://via.placeholder.com/150" alt="Profile" class="profile-img">
                            <h3 class="fw-bold mb-1">E.K Parvez</h3>
                            <p class="mb-0 opacity-75">Network Engineer & Software Developer</p>
                            
                            <div class="mt-4">
                                <a href="https://facebook.com/ek.parvez.7" class="social-btn fb"><i class="fab fa-facebook-f"></i></a>
                                <a href="https://wa.me/8801912981072" class="social-btn wa"><i class="fab fa-whatsapp"></i></a>
                                <a href="https://linkedin.com/in/yourprofile" class="social-btn ln"><i class="fab fa-linkedin-in"></i></a>
                                <a href="https://github.com/yourprofile" class="social-btn gh"><i class="fab fa-github"></i></a>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <h5 class="fw-bold mb-4 text-center">Contact Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="contact-item d-flex align-items-center">
                                        <div class="contact-icon me-3"><i class="fas fa-phone-alt"></i></div>
                                        <div>
                                            <small class="text-muted d-block">Phone Number</small>
                                            <span class="fw-bold">+8801912981072 , +8801612981072</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="contact-item d-flex align-items-center">
                                        <div class="contact-icon me-3"><i class="fas fa-envelope"></i></div>
                                        <div>
                                            <small class="text-muted d-block">Email Address</small>
                                            <span class="fw-bold">bdtechnology2019@gmail.com</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="contact-item d-flex align-items-center">
                                        <div class="contact-icon me-3"><i class="fas fa-map-marker-alt"></i></div>
                                        <div>
                                            <small class="text-muted d-block">Office Address</small>
                                            <span class="fw-bold">Pulerhat,Jashore-Sador-7402</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4 border-0 rounded-4">
                                <div class="d-flex">
                                    <i class="fas fa-info-circle mt-1 me-3 fs-4"></i>
                                    <div>
                                        <strong>Available for Support:</strong><br>
                                        সকাল ১০টা থেকে রাত ৮টা পর্যন্ত যেকোনো কারিগরি সহায়তার জন্য যোগাযোগ করতে পারেন।
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include 'footer.php'; ?>
        </div>
    </div>
</div>

</body>
</html>