<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || isAdmin()) {
    echo json_encode(['success' => true, 'count' => 0]);
    exit();
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

$query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_count = $result->fetch_assoc()['count'];
$stmt->close();

echo json_encode([
    'success' => true,
    'count' => $cart_count
]);
?>