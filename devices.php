<?php
require_once 'config.php';
if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

// ১. ডিভাইস ডিলিট করার লজিক
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM switches WHERE id = $id");
    // এই সুইচের ট্রাফিক ডাটাও ডিলিট করে দেওয়া ভালো
    $conn->query("DELETE FROM port_traffic WHERE switch_id = $id");
    header("Location: devices.php?msg=Deleted");
}

// ২. ডিভাইস অ্যাড করার লজিক
if(isset($_POST['add'])){
    $n = $_POST['n']; $i = $_POST['i']; $c = $_POST['c'];
    $conn->query("INSERT INTO switches (name, ip_address, community) VALUES ('$n', '$i', '$c')");
    header("Location: devices.php?msg=Added");
}

// ৩. ডিভাইস এডিট করার লজিক
if(isset($_POST['update'])){
    $id = (int)$_POST['id'];
    $n = $_POST['n']; $i = $_POST['i']; $c = $_POST['c'];
    $conn->query("UPDATE switches SET name='$n', ip_address='$i', community='$c' WHERE id=$id");
    header("Location: devices.php?msg=Updated");
}

// এডিট করার জন্য ডাটা আনা
$edit_data = null;
if(isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM switches WHERE id = $id");
    $edit_data = $res->fetch_assoc();
}

$all_switches = $conn->query("SELECT * FROM switches");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Device Management</title>
	<link rel="icon" type="image/png" sizes="32x32" href="bd.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-4 py-2 mb-4">
    <a href="dashboard.php" class="navbar-brand">← Back to Dashboard</a>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4">
                <h4><?= $edit_data ? 'Edit Switch' : 'Add New Switch' ?></h4>
                <hr>
                <form method="POST">
                    <?php if($edit_data): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label>Switch Name</label>
                        <input name="n" class="form-control" value="<?= $edit_data ? $edit_data['name'] : '' ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>IP Address</label>
                        <input name="i" class="form-control" value="<?= $edit_data ? $edit_data['ip_address'] : '' ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>SNMP Community</label>
                        <input name="c" class="form-control" value="<?= $edit_data ? $edit_data['community'] : 'public' ?>" required>
                    </div>
                    
                    <?php if($edit_data): ?>
                        <button name="update" class="btn btn-warning w-100">Update Device</button>
                        <a href="devices.php" class="btn btn-link w-100 mt-2 text-decoration-none">Cancel Edit</a>
                    <?php else: ?>
                        <button name="add" class="btn btn-primary w-100">Save Device</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm p-4">
                <h4>Switch List</h4>
                <hr>
                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-success py-2"><?= $_GET['msg'] ?> Successfully!</div>
                <?php endif; ?>
                
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>IP Address</th>
                            <th>Community</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($s = $all_switches->fetch_assoc()): ?>
                        <tr>
                            <td><?= $s['name'] ?></td>
                            <td><?= $s['ip_address'] ?></td>
                            <td><code><?= $s['community'] ?></code></td>
                            <td class="text-center">
                                <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>