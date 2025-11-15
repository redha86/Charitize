<?php
require_once '../config/config.php';
require_once '../config/database.php';

ob_start();

header('Content-Type: application/json');

if (!isLoggedIn() || isAdmin()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_order'])) {
    $order_id = intval($_POST['order_id']);
    
    $query = "SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND status = 'shipped'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        $query = "UPDATE orders SET status = 'completed', updated_at = NOW() WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Terima kasih telah berbelanja di Toko Gorden! Pesanan Anda telah selesai dan transaksi dinyatakan sukses.'
            ]);
        } else {
            $stmt->close();
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyelesaikan pesanan'
            ]);
        }
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Pesanan tidak valid atau tidak dapat diselesaikan'
        ]);
    }
} else {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

ob_end_flush();
exit();
?>