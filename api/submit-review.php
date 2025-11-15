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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

    if ($product_id == 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
        exit();
    }
    
    if ($order_id == 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Order tidak valid']);
        exit();
    }
    
    if ($rating < 1 || $rating > 5) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Rating harus antara 1-5 bintang']);
        exit();
    }
    
    if (empty($review_text)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Review tidak boleh kosong']);
        exit();
    }
    
    if (strlen($review_text) < 10) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Review minimal 10 karakter']);
        exit();
    }
    
    if (strlen($review_text) > 1000) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Review maksimal 1000 karakter']);
        exit();
    }
    
    $query = "SELECT o.order_id, oi.product_id 
              FROM orders o
              JOIN order_items oi ON o.order_id = oi.order_id
              WHERE o.order_id = ? 
              AND o.user_id = ? 
              AND o.status = 'completed'
              AND oi.product_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $order_id, $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Anda hanya bisa mereview produk dari pesanan yang sudah selesai'
        ]);
        exit();
    }
    $stmt->close();
    
    $check_query = "SELECT review_id FROM product_reviews 
                    WHERE product_id = ? AND user_id = ? AND order_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("iii", $product_id, $user_id, $order_id);
    $stmt->execute();
    $existing_review = $stmt->get_result();
    
    if ($existing_review->num_rows > 0) {
        $stmt->close();
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Anda sudah memberikan review untuk produk ini'
        ]);
        exit();
    }
    $stmt->close();

    $review_text_sanitized = sanitize($review_text);
    
    $insert_query = "INSERT INTO product_reviews 
                     (product_id, user_id, order_id, rating, review_text, status) 
                     VALUES (?, ?, ?, ?, ?, 'approved')";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iiiis", $product_id, $user_id, $order_id, $rating, $review_text_sanitized);
    
    if ($stmt->execute()) {
        $review_id = $conn->insert_id;
        $stmt->close();
        
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Terima kasih! Review Anda berhasil ditambahkan',
            'review_id' => $review_id
        ]);
    } else {
        $stmt->close();
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal menambahkan review. Silakan coba lagi.'
        ]);
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

ob_end_flush();
exit();
?>