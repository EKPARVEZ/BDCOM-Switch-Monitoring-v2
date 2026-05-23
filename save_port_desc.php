<?php
require_once 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$switch_id = isset($_POST['switch_id']) ? (int)$_POST['switch_id'] : 0;
$port_index = isset($_POST['port_index']) ? $conn->real_escape_string($_POST['port_index']) : '';
$customer = isset($_POST['customer']) ? $conn->real_escape_string($_POST['customer']) : '';
$location = isset($_POST['location']) ? $conn->real_escape_string($_POST['location']) : '';
$phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : '';
$description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';

if(!$switch_id || !$port_index) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$sql = "INSERT INTO port_descriptions (switch_id, port_index, customer_name, location, customer_phone, description) 
        VALUES ($switch_id, '$port_index', '$customer', '$location', '$phone', '$description')
        ON DUPLICATE KEY UPDATE 
        customer_name = VALUES(customer_name),
        location = VALUES(location),
        customer_phone = VALUES(customer_phone),
        description = VALUES(description)";

if($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>