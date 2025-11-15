<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', 'http://localhost/charitize/');

define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'assets/images/');
define('PRODUCT_IMG_PATH', UPLOAD_PATH . 'products/');
define('PAYMENT_IMG_PATH', UPLOAD_PATH . 'payments/');

define('SITE_NAME', 'Charitize');
define('SITE_TAGLINE', 'Jasa Makeup dan Kostum terbaik di Indonesia');

define('PRIMARY_COLOR', '#7A6A54');
define('SECONDARY_COLOR', '#687F5A'); 
define('ACCENT_COLOR', '#3E352E'); 

define('ITEMS_PER_PAGE', 6);

define('ORDER_STATUS', [
    'pending' => 'Menunggu Pembayaran',
    'proses' => 'Diproses',
    'selesai' => 'Selesai',
    'batal' => 'Dibatalkan',
    'ditolak' => 'Pembayaran Ditolak'
]);

define('PAID_STATUS', ['paid', 'processing', 'shipped', 'completed']);
define('PENDING_STATUS', ['pending']);

function redirect($url) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header("Location: " . BASE_URL . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('auth/login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect('index.php');
    }
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatDate($date) {
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $month . ' ' . $year;
}

function sanitize($data) {
    if ($data === null) {
        return '';
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function uploadImage($file, $path, $prefix = '') {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; 
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '.' . $extension;
    $destination = $path . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Gagal mengupload file'];
}

function deleteImage($filename, $path) {
    $file = $path . $filename;
    if (file_exists($file)) {
        unlink($file);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Jakarta');
?>