<?php
require_once '../config/config.php';
require_once '../config/database.php';

ob_start();
header('Content-Type: application/json');

// pastikan helper seperti isLoggedIn(), isAdmin(), uploadImage(), deleteImage(), getConnection() tersedia
if (!isLoggedIn() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    ob_end_flush();
    exit();
}

// ambil input
$id_transaksi = intval($_POST['id_transaksi'] ?? 0);
$metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Transfer');
$jumlah_bayar = isset($_POST['jumlah_bayar']) ? floatval($_POST['jumlah_bayar']) : 0.0;

// validasi dasar
if ($id_transaksi <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
    ob_end_flush();
    exit();
}

// cek apakah transaksi milik user
$q = "SELECT id, id_user, status FROM transaksi WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($q);
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx || intval($trx['id_user']) !== intval($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan atau bukan milik Anda']);
    ob_end_flush();
    exit();
}

// jika jumlah_bayar kosong/0 -> hitung subtotal dari detail_transaksi
if (empty($jumlah_bayar) || $jumlah_bayar <= 0) {
    $q = $conn->prepare("SELECT COALESCE(SUM(subtotal),0) AS ssum FROM detail_transaksi WHERE id_transaksi = ?");
    $q->bind_param("i", $id_transaksi);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    $jumlah_bayar = floatval($res['ssum'] ?? 0);
}

// cek file upload (nama field: bukti_pembayaran)
if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File bukti pembayaran tidak valid atau belum dipilih']);
    ob_end_flush();
    exit();
}

// cek apakah sudah ada record pembayaran untuk transaksi ini oleh user
$query = "SELECT * FROM pembayaran WHERE id_transaksi = ? AND id_user = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id_transaksi, $user_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// upload file (menggunakan helper uploadImage yang ada di project)
// uploadImage($_FILES['bukti_pembayaran'], PAYMENT_IMG_PATH, 'payment_') -> mengembalikan array ['success'=>bool, 'filename'=>string, 'message'=>string]
$upload_result = uploadImage($_FILES['bukti_pembayaran'], PAYMENT_IMG_PATH, 'payment_');
if (!$upload_result['success']) {
    echo json_encode(['success' => false, 'message' => $upload_result['message']]);
    ob_end_flush();
    exit();
}
$filename = $upload_result['filename'];

// jika sudah ada record pembayaran: update, kalau tidak insert baru
$success = false;
if ($payment) {
    // hapus file lama jika ada
    if (!empty($payment['bukti_pembayaran'])) {
        deleteImage($payment['bukti_pembayaran'], PAYMENT_IMG_PATH);
    }

    $query = "UPDATE pembayaran 
              SET metode_pembayaran = ?, jumlah_bayar = ?, bukti_pembayaran = ?, status_pembayaran = 'menunggu', tanggal_bayar = NOW()
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    // parameter order: metode_pembayaran (s), jumlah_bayar (d), filename (s), payment id (i)
    $stmt->bind_param("sdsi", $metode_pembayaran, $jumlah_bayar, $filename, $payment['id']);
    $success = $stmt->execute();
    $stmt->close();
} else {
    // insert baru
    $query = "INSERT INTO pembayaran (id_user, id_transaksi, metode_pembayaran, jumlah_bayar, bukti_pembayaran, status_pembayaran, tanggal_bayar)
              VALUES (?, ?, ?, ?, ?, 'menunggu', NOW())";
    $stmt = $conn->prepare($query);
    // parameter order: id_user (i), id_transaksi (i), metode_pembayaran (s), jumlah_bayar (d), filename (s)
    $stmt->bind_param("iisds", $user_id, $id_transaksi, $metode_pembayaran, $jumlah_bayar, $filename);
    $success = $stmt->execute();
    $stmt->close();
}

if ($success) {
    // coba update status transaksi menjadi 'proses'
    $upd_ok = false;
    $upd_msg = '';
    $upd = $conn->prepare("UPDATE transaksi SET status = 'paid' WHERE id = ?");
    if ($upd) {
        $upd->bind_param("i", $id_transaksi);
        $upd_ok = $upd->execute();
        if ($upd_ok === false) {
            $upd_msg = 'Gagal mengupdate status transaksi ke "proses".';
        }
        $upd->close();
    } else {
        $upd_msg = 'Gagal menyiapkan query update status transaksi.';
    }

    if ($upd_ok) {
        echo json_encode(['success' => true, 'message' => 'Bukti pembayaran berhasil diupload. Status pembayaran: menunggu konfirmasi. Status transaksi diubah menjadi "proses".']);
    } else {
        // pembayaran berhasil tapi update status transaksi gagal
        echo json_encode(['success' => true, 'message' => 'Bukti pembayaran berhasil diupload. Status pembayaran: menunggu konfirmasi. Namun: ' . $upd_msg]);
    }
} else {
    // hapus file jika gagal menyimpan ke DB
    deleteImage($filename, PAYMENT_IMG_PATH);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data pembayaran ke database']);
}

ob_end_flush();
