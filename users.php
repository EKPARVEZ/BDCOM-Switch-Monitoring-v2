<?php
require_once 'config.php';

// লগইন চেক
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$message = "";

// ১. ইউজার অ্যাড করার লজিক
if (isset($_POST['add_user'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $conn->real_escape_string($_POST['role']);

    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $message = "<div class='alert alert-danger'>Username already exists!</div>";
    } else {
        $conn->query("INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')");
        $message = "<div class='alert alert-success'>User added successfully!</div>";
    }
}

// ২. ইউজার ডিলিট করার লজিক
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // নিজের অ্যাকাউন্ট যাতে ডিলিট না হয় তার সুরক্ষা
    if ($id == $_SESSION['user_id']) {
        $message = "<div class='alert alert-warning'>You cannot delete your own account!</div>";
    } else {
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = "<div class='alert alert-success'>User deleted!</div>";
    }
}

// ৩. ইউজার এডিট/আপডেট করার লজিক
if (isset($_POST['update_user'])) {
    $id = (int)$_POST['user_id'];
    $username = $conn->real_escape_string($_POST['username']);
    $role = $conn->real_escape_string($_POST['role']);
    
    $update_query = "UPDATE users SET username='$username', role='$role' WHERE id=$id";
    
    // যদি পাসওয়ার্ড ফিল্ডে কিছু লেখা থাকে তবেই আপডেট হবে
    if (!empty($_POST['password'])) {
        $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET username='$username', role='$role', password='$new_pass' WHERE id=$id";
    }
    
    if ($conn->query($update_query)) {
        $message = "<div class='alert alert-success'>User updated successfully!</div>";
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Access Control | NMS Pro</title>
	<link rel="icon" type="image/png" sizes="32x32" href="bd.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #0f172a; --accent-blue: #0ea5e9; }
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { min-height: 100vh; background: var(--sidebar-bg); color: white; }
        .nav-link { color: #cbd5e1; border-radius: 10px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: rgba(14, 165, 233, 0.15); color: var(--accent-blue); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .btn-primary { background-color: var(--accent-blue); border: none; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3 d-none d-md-block">
            <div class="text-center py-4">
                <i class="fas fa-user-shield fa-2x text-info mb-2"></i>
                <h5 class="fw-bold">USER ACCESS</h5>
            </div>
            <div class="nav flex-column mt-3">
                <a href="dashboard.php" class="nav-link px-3"><i class="fas fa-th-large me-2"></i> Dashboard</a>
                <a href="devices.php" class="nav-link px-3"><i class="fas fa-tools me-2"></i> Manage Devices</a>
                <a href="users.php" class="nav-link px-3 active"><i class="fas fa-users-cog me-2"></i> User Access</a>
                <hr class="border-secondary">
				<a href="support.php" class="nav-link px-3"><i class="fas fa-headset me-1"></i> Support</a>
                <a href="logout.php" class="nav-link px-3 text-danger"><i class="fas fa-power-off me-2"></i> Logout</a>
            </div>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">System Users</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i> Add New User
                </button>
            </div>

            <?= $message ?>

            <div class="card overflow-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?=$u['id']?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light p-2 me-2">
                                        <i class="fas fa-user text-muted"></i>
                                    </div>
                                    <?=$u['username']?>
                                </div>
                            </td>
                            <td><span class="badge bg-soft-info text-primary border border-info px-3"><?=$u['role']?></span></td>
                            <td class="text-muted small"><?=$u['created_at'] ?? 'N/A'?></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary me-2" 
                                        onclick="editUser(<?=htmlspecialchars(json_encode($u))?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?=$u['id']?>" class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('Are you sure?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title">Add New System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Access Role</label>
                    <select name="role" class="form-select">
                        <option value="Admin">Admin</option>
                        <option value="Operator">Operator</option>
                        <option value="Viewer">Viewer</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Edit User Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="user_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password (Keep blank to stay same)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit_role" class="form-select">
                        <option value="Admin">Admin</option>
                        <option value="Operator">Operator</option>
                        <option value="Viewer">Viewer</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_user" class="btn btn-success">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.min.js"></script>
<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    
    var myModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    myModal.show();
}
</script>

</body>
</html>