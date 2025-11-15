<?php
ob_start();

$page_title = "Checkout";
require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../config/config.php';

requireLogin();

if (isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'] ?? null;

/**
 * DEBUG helper - tulis ke error_log agar mudah cek penyebab redirect 302
 * Cari baris yang diawali CHECKOUT_DEBUG: di log XAMPP / PHP
 */
function checkout_debug($msg, $var = null) {
    $line = "CHECKOUT_DEBUG: " . $msg;
    if (!is_null($var)) {
        $line .= " | " . print_r($var, true);
    }
    error_log($line);
}

// safety: pastikan user_id ada
if (empty($user_id)) {
    checkout_debug('No user_id in session; redirecting to cart', $_SESSION);
    $_SESSION['error_message'] = 'Sesi Anda berakhir. Silakan login kembali.';
    redirect('customer/cart.php');
}

// Ambil item yang dipilih dari cart
$selected_items_param = isset($_GET['items']) ? trim($_GET['items']) : '';
checkout_debug('Raw $_GET[items]', $_GET['items'] ?? null);

if (empty($selected_items_param)) {
    checkout_debug('Selected items param is empty; redirecting to cart', $selected_items_param);
    $_SESSION['error_message'] = 'Tidak ada produk yang dipilih untuk checkout';
    redirect('customer/cart.php');
}

// Normalize: hapus spasi dan trailing/comma anomali
$selected_items_param = preg_replace('/\s+/', '', $selected_items_param);
$selected_items_param = trim($selected_items_param, ','); // remove leading/trailing commas
checkout_debug('Normalized selected_items_param', $selected_items_param);

// explode -> intval -> filter
$selected_cart_ids = array_filter(array_map('intval', explode(',', $selected_items_param)), function($v){ return $v > 0; });
checkout_debug('Parsed selected_cart_ids', $selected_cart_ids);

if (empty($selected_cart_ids)) {
    checkout_debug('selected_cart_ids empty after parsing; redirecting', $selected_cart_ids);
    $_SESSION['error_message'] = 'Tidak ada produk yang dipilih untuk checkout';
    redirect('customer/cart.php');
}

/**
 * Helper: bind dynamic params to mysqli_stmt using references
 */
function mysqli_stmt_bind_params_by_ref(mysqli_stmt $stmt, $types, array &$params) {
    $refs = [];
    $refs[] = $types;
    foreach ($params as $key => $val) {
        $refs[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Ambil data cart (tambahkan c.jadwal_id)
// build placeholders
$placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
$query = "SELECT c.*, 
                 k.nama_kostum, k.harga_sewa, 
                 kv.stok AS stok_variasi, kv.id AS id_variasi, kv.id,
                 l.nama_layanan, l.id AS id_layanan, l.id_kategori_makeup
          FROM cart c
          LEFT JOIN kostum k ON c.product_id = k.id
          LEFT JOIN kostum_variasi kv ON c.variasi_id = kv.id
          LEFT JOIN layanan_makeup l ON c.product_id = l.id
          WHERE c.user_id = ? AND c.cart_id IN ($placeholders)";

checkout_debug('Prepared SELECT cart query', $query);

$stmt = $conn->prepare($query);
if (!$stmt) {
    // log error detail sebelum redirect
    checkout_debug('Failed to prepare cart SELECT', $conn->error);
    $_SESSION['error_message'] = 'Gagal menyiapkan query cart: ' . $conn->error;
    redirect('customer/cart.php');
}

$types = str_repeat('i', count($selected_cart_ids) + 1);
$params = array_merge([$user_id], array_values($selected_cart_ids));

$bind_ok = false;
try {
    $bind_ok = mysqli_stmt_bind_params_by_ref($stmt, $types, $params);
} catch (Throwable $e) {
    checkout_debug('Exception while binding params', $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan internal saat memproses permintaan (bind params).';
    redirect('customer/cart.php');
}

$stmt->execute();
$cart_items = $stmt->get_result();
$stmt->close();

if ($cart_items === false) {
    checkout_debug('cart_items is false after execute', $conn->error);
    $_SESSION['error_message'] = 'Terjadi kesalahan ketika mengambil data keranjang.';
    redirect('customer/cart.php');
}

checkout_debug('cart_items num_rows', $cart_items->num_rows);

if ($cart_items->num_rows == 0) {
    // kemungkinan cart sudah kosong / item tidak ditemukan untuk user ini
    checkout_debug('No cart rows found for selected ids', $selected_cart_ids);
    $_SESSION['error_message'] = 'Produk yang dipilih tidak ditemukan';
    redirect('customer/cart.php');
}

// Ambil data user
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    checkout_debug('Failed to prepare user SELECT', $conn->error);
    $_SESSION['error_message'] = 'Gagal mengambil data user';
    redirect('customer/cart.php');
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    checkout_debug('User record not found', $user_id);
    $_SESSION['error_message'] = 'Data user tidak ditemukan';
    redirect('customer/cart.php');
}

// Build items & compute initial per-item subtotal (without duration)
// -----------------------------
// Perubahan penting: tidak lagi memeriksa stok di kostum_variasi.
// Kita hanya menggunakan quantity yang tersimpan di tabel cart.
// -----------------------------
$subtotal = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    // ensure quantity read from cart
    $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $item['quantity'] = $quantity;

    if ($item['type'] === 'kostum') {
        // gunakan harga_sewa jika ada
        $harga = isset($item['harga_sewa']) ? (float)$item['harga_sewa'] : 0;
        $item['harga_sewa'] = $harga;
        $item_subtotal = $harga * $quantity; // initial (will be multiplied by days later if dates provided)
    } else {
        // makeup -> ambil harga dari kategori_makeup jika tersedia
        $harga_cat = 0;
        if (!empty($item['id_kategori_makeup'])) {
            $cat_query = "SELECT harga FROM kategori_makeup WHERE id = ?";
            $stmt2 = $conn->prepare($cat_query);
            if ($stmt2) {
                $stmt2->bind_param("i", $item['id_kategori_makeup']);
                $stmt2->execute();
                $res = $stmt2->get_result()->fetch_assoc();
                $harga_cat = isset($res['harga']) ? (float)$res['harga'] : 0;
                $stmt2->close();
            } else {
                checkout_debug('Failed to prepare kategori_makeup query', $conn->error);
            }
        }
        $item['harga_layanan'] = $harga_cat;
        $item_subtotal = $harga_cat * $quantity;
    }

    $item['item_subtotal'] = $item_subtotal;
    $subtotal += $item_subtotal;
    $items[] = $item;
}

// Cek apakah ada item kostum (digunakan untuk menampilkan input tanggal sewa & metode ambil)
$hasKostum = false;
foreach ($items as $it) {
    if ($it['type'] === 'kostum') {
        $hasKostum = true;
        break;
    }
}

// Cek apakah ada item makeup (untuk field alamat jika diperlukan)
$hasMakeup = false;
foreach ($items as $it2) {
    if ($it2['type'] === 'makeup') {
        $hasMakeup = true;
        break;
    }
}

$errors = [];

// Ambil nilai tanggal sewa dari POST jika submit gagal sebelumnya (untuk menampilkan kembali)
$old_tanggal_sewa = isset($_POST['tanggal_sewa']) ? sanitize($_POST['tanggal_sewa']) : '';
$old_tanggal_selesai = isset($_POST['tanggal_selesai']) ? sanitize($_POST['tanggal_selesai']) : '';

/**
 * Helper server-side untuk hitung durasi inklusif hari
 */
function compute_days_inclusive($start, $end) {
    try {
        $d1 = new DateTime($start);
        $d2 = new DateTime($end);
        $diff = $d1->diff($d2);
        return $diff->days + 1;
    } catch (Exception $e) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $postal_code = sanitize($_POST['postal_code'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $pickup_method = sanitize($_POST['pickup_method'] ?? 'ambil');

    // jika ada kostum, ambil tanggal sewa & tanggal selesai dari form
    $tanggal_sewa_input = $hasKostum ? sanitize($_POST['tanggal_sewa'] ?? '') : '';
    $tanggal_selesai_input = $hasKostum ? sanitize($_POST['tanggal_selesai'] ?? '') : '';

    if (empty($phone)) $errors[] = "Nomor telepon harus diisi";
    if ($hasKostum && empty($tanggal_sewa_input)) $errors[] = "Tanggal sewa harus diisi untuk pesanan kostum";
    if ($hasKostum && empty($tanggal_selesai_input)) $errors[] = "Tanggal selesai sewa harus diisi untuk pesanan kostum";
    if ($hasKostum && !empty($tanggal_sewa_input) && !empty($tanggal_selesai_input)) {
        // Validasi tambahan server-side:
        // 1) tanggal_sewa tidak boleh sebelum hari ini
        // 2) tanggal_selesai tidak boleh sebelum tanggal_sewa
        // 3) tanggal_selesai tidak boleh lebih dari 5 hari setelah tanggal_sewa
        $today = date('Y-m-d');
        $ts_start = strtotime($tanggal_sewa_input);
        $ts_end = strtotime($tanggal_selesai_input);

        if ($ts_start === false || $ts_end === false) {
            $errors[] = "Format tanggal tidak valid.";
        } else {
            if ($ts_start < strtotime($today)) {
                $errors[] = "Tanggal sewa tidak boleh sebelum hari ini.";
            }
            if ($ts_end < $ts_start) {
                $errors[] = "Periode sewa tidak valid (tanggal selesai harus sama atau setelah tanggal mulai).";
            } else {
                // raw diff in days (non-inclusive)
                $diffDays = ($ts_end - $ts_start) / (60 * 60 * 24);
                if ($diffDays > 5) {
                    $errors[] = "Tanggal selesai tidak boleh lebih dari 5 hari setelah tanggal sewa.";
                }
            }
        }

        // existing check using compute_days_inclusive (keamanan tambahan)
        $days_check = compute_days_inclusive($tanggal_sewa_input, $tanggal_selesai_input);
        if ($days_check === null || $days_check <= 0) {
            $errors[] = "Periode sewa tidak valid (pastikan tanggal mulai tidak setelah tanggal selesai)";
        }
    }
    if ($hasMakeup && empty($address) && (isset($_POST['alamat_choice']) && $_POST['alamat_choice'] == 'lainnya')) $errors[] = "Alamat lengkap harus diisi";

    // Jika tidak ada error, recompute subtotal server-side termasuk durasi bila ada
    if (empty($errors)) {
        // jika ada kostum dan tanggal sewa diisi, hitung days
        $durationDays = null;
        if ($hasKostum && !empty($tanggal_sewa_input) && !empty($tanggal_selesai_input)) {
            $durationDays = compute_days_inclusive($tanggal_sewa_input, $tanggal_selesai_input);
            if ($durationDays === null) $durationDays = 1;
        }

        // recompute subtotal_server sesuai durasi bila ada
        $subtotal_server = 0;
        foreach ($items as &$it) {
            // ensure quantity taken from cart
            $qty = (int)$it['quantity'];
            if ($it['type'] === 'kostum') {
                $price = $it['harga_sewa'];
                $it['item_subtotal'] = ($durationDays ? $price * $qty * $durationDays : $price * $qty);
            } else {
                $price = $it['harga_layanan'];
                $it['item_subtotal'] = $price * $qty;
            }
            $subtotal_server += $it['item_subtotal'];
        }
        unset($it);

        // gunakan subtotal_server ketika menyimpan transaksi
        $conn->begin_transaction();
        try {
            // Insert ke tabel transaksi
            // PERUBAHAN: mengisi kolom updated_at dengan NOW() saat membuat pesanan
            // Pastikan tabel transaksi memiliki kolom updated_at (timestamp/datetime)
            $query = "INSERT INTO transaksi (id_user, tanggal_pemesanan, total_harga, status, updated_at)
                      VALUES (?, NOW(), ?, 'pending', NOW())";
            $stmt = $conn->prepare($query);
            if (!$stmt) throw new Exception('Gagal menyiapkan query transaksi: ' . $conn->error);
            $stmt->bind_param("id", $user_id, $subtotal_server);
            $stmt->execute();
            $transaksi_id = $conn->insert_id;
            $stmt->close();

            // Insert ke detail_transaksi
            foreach ($items as $item) {
                // subtotal per item sudah diitem_subtotal (termasuk durasi jika kostum)
                $subtotal_item_val = (float)$item['item_subtotal'];

                // Ambil data jadwal jika ada jadwal_id di cart (untuk makeup)
                $tanggal_layanan = null;
                $jam_mulai = null;
                $jam_selesai = null;
                $jadwal_id = $item['jadwal_id'] ?? null;
                if (!empty($jadwal_id)) {
                    $jadwal_q = "SELECT tanggal, jam_mulai, jam_selesai FROM jadwal_makeup WHERE id = ? LIMIT 1";
                    $stmtJ = $conn->prepare($jadwal_q);
                    if ($stmtJ) {
                        $stmtJ->bind_param("i", $jadwal_id);
                        $stmtJ->execute();
                        $jadwal_row = $stmtJ->get_result()->fetch_assoc();
                        $stmtJ->close();
                        if ($jadwal_row) {
                            $tanggal_layanan = $jadwal_row['tanggal'] ?? null;
                            $jam_mulai = $jadwal_row['jam_mulai'] ?? null;
                            $jam_selesai = $jadwal_row['jam_selesai'] ?? null;
                        }
                    }
                }

                // Jika item adalah kostum, gunakan tanggal sewa dari form, jika bukan maka null
                $tanggal_sewa_val = null;
                $tanggal_selesai_val = null;
                if ($item['type'] === 'kostum') {
                    $tanggal_sewa_val = !empty($tanggal_sewa_input) ? $tanggal_sewa_input : null;
                    $tanggal_selesai_val = !empty($tanggal_selesai_input) ? $tanggal_selesai_input : null;
                }

                // PERUBAHAN: menambahkan updated_at pada detail_transaksi diisi NOW()
                $query = "INSERT INTO detail_transaksi 
                (id_transaksi, id_kostum, id_kostum_variasi, id_layanan_makeup, jumlah, subtotal, metode_ambil, catatan, alamat, tanggal_layanan, jam_mulai, jam_selesai, tanggal_sewa, tanggal_selesai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Gagal menyiapkan query insert detail_transaksi: " . $conn->error);
                }

                // ambil jumlah dari cart (pastikan kita pakai quantity dari cart)
                $qty = (int)($item['quantity'] ?? 1);

                $id_kostum = ($item['type'] === 'kostum') ? ($item['product_id'] ?? null) : null;
                $id_variasi = ($item['type'] === 'kostum') ? ($item['variasi_id'] ?? null) : null;
                $id_makeup = ($item['type'] === 'makeup') ? ($item['product_id'] ?? null) : null;
                $trans = $transaksi_id;
                $metode = $pickup_method;
                $catatan_val = $notes;
                $alamat_val = $address;

                // Prepare variables to avoid bind problems with NULLs
                $tanggal_layanan_val = !empty($tanggal_layanan) ? $tanggal_layanan : null;
                $jam_mulai_val = !empty($jam_mulai) ? $jam_mulai : null;
                $jam_selesai_val = !empty($jam_selesai) ? $jam_selesai : null;
                $tanggal_sewa_val = !empty($tanggal_sewa_val) ? $tanggal_sewa_val : $tanggal_sewa_val;
                $tanggal_selesai_val = !empty($tanggal_selesai_val) ? $tanggal_selesai_val : $tanggal_selesai_val;

                $stmt->bind_param(
                    "iiiiidssssssss",
                    $trans,
                    $id_kostum,
                    $id_variasi,
                    $id_makeup,
                    $qty,
                    $subtotal_item_val,
                    $metode,
                    $catatan_val,
                    $alamat_val,
                    $tanggal_layanan_val,
                    $jam_mulai_val,
                    $jam_selesai_val,
                    $tanggal_sewa_val,
                    $tanggal_selesai_val
                );
                $stmt->execute();
                $stmt->close();
            }

            // Hapus item dari cart (menggunakan cart_id)
            $placeholders = implode(',', array_fill(0, count($selected_cart_ids), '?'));
            $query = "DELETE FROM cart WHERE user_id = ? AND cart_id IN ($placeholders)";
            $stmt = $conn->prepare($query);
            if (!$stmt) throw new Exception('Gagal menyiapkan query hapus cart: ' . $conn->error);
            $types = str_repeat('i', count($selected_cart_ids) + 1);
            $params = array_merge([$user_id], array_values($selected_cart_ids));
            mysqli_stmt_bind_params_by_ref($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['checkout_success'] = true;
            redirect('customer/orders.php');
        } catch (Exception $e) {
            $conn->rollback();
            checkout_debug('Exception during checkout transaction', $e->getMessage());
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        // simpan nilai lama agar tetap tampil bila validasi gagal
        $old_tanggal_sewa = $tanggal_sewa_input ?? '';
        $old_tanggal_selesai = $tanggal_selesai_input ?? '';
    }
}

// Jika halaman di-render setelah POST gagal atau belum submit, kita bisa
// menghitung initial_durasi_server (jika ada old tanggal) supaya tampilan awal benar
$initial_durasi = null;
if ($hasKostum && !empty($old_tanggal_sewa) && !empty($old_tanggal_selesai)) {
    $initial_durasi = compute_days_inclusive($old_tanggal_sewa, $old_tanggal_selesai);
    if ($initial_durasi === null) $initial_durasi = null;
    // jika ada durasi, update items' item_subtotal untuk tampilan
    if ($initial_durasi) {
        $display_subtotal = 0;
        foreach ($items as &$it) {
            if ($it['type'] === 'kostum') {
                $it['item_subtotal'] = $it['harga_sewa'] * $it['quantity'] * $initial_durasi;
            }
            $display_subtotal += $it['item_subtotal'];
        }
        unset($it);
    } else {
        $display_subtotal = $subtotal;
    }
} else {
    $display_subtotal = $subtotal;
}

// fungsi formatRupiah & formatDate diasumsikan sudah ada di includes
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
</head>
<body>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-accent"><i class="fas fa-credit-card mr-2"></i>Checkout</h1>
        <p class="text-gray-600 mt-2">Lengkapi informasi Anda untuk menyelesaikan pesanan</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <?php foreach ($errors as $error): ?>
                <p class="text-sm"><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Informasi Penerima -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-accent mb-4"><i class="fas fa-user mr-2"></i>Informasi Penerima</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-accent font-medium mb-2">Nama Lengkap</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" class="form-control-custom w-full bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-accent font-medium mb-2">Nomor Telepon <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-control-custom w-full" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
            </div>

            <?php if ($hasMakeup): ?>
            <!-- Alamat (tampilkan jika ada makeup atau user memilih lainnya) -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-accent mb-4"><i class="fas fa-map-marker-alt mr-2"></i>Alamat</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-accent font-medium mb-2">Pilih Alamat <span class="text-red-500">*</span></label>
                        <select name="alamat_choice" id="alamat_choice" class="form-control-custom w-full">
                            <option value="ditempat" <?php echo (isset($_POST['alamat_choice']) && $_POST['alamat_choice'] == 'ditempat') ? 'selected' : ''; ?>>Ditempat</option>
                            <option value="lainnya" <?php echo (isset($_POST['alamat_choice']) && $_POST['alamat_choice'] == 'lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div id="alamat_lainnya_container" style="display: <?php echo (isset($_POST['alamat_choice']) && $_POST['alamat_choice'] == 'lainnya') ? 'block' : 'none'; ?>;">
                        <label class="block text-accent font-medium mb-2">Alamat Lengkap <span class="text-red-500">*</span></label>
                        <textarea name="address" rows="3" class="form-control-custom w-full"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($hasKostum): ?>
            <!-- Periode Sewa (hanya jika ada kostum) -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-accent mb-4"><i class="fas fa-calendar-alt mr-2"></i>Periode Sewa</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-accent font-medium mb-2">Tanggal Mulai Sewa <span class="text-red-500">*</span></label>
                        <input type="date" id="tanggal_sewa" name="tanggal_sewa" value="<?php echo htmlspecialchars($old_tanggal_sewa); ?>" class="form-control-custom w-full">
                    </div>
                    <div>
                        <label class="block text-accent font-medium mb-2">Tanggal Selesai Sewa <span class="text-red-500">*</span></label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars($old_tanggal_selesai); ?>" class="form-control-custom w-full">
                    </div>
                </div>
                <p id="rental_date_warning" class="text-sm text-red-600 mt-2" style="display:none;">Periode sewa tidak valid (tanggal akhir harus sama atau setelah tanggal mulai, dan maksimal 5 hari setelah tanggal mulai).</p>
            </div>
            <?php endif; ?>

            <!-- Catatan -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-accent mb-4"><i class="fas fa-sticky-note mr-2"></i>Catatan (Opsional)</h3>
                <textarea name="notes" rows="3" class="form-control-custom w-full"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>

            <?php if ($hasKostum): ?>
                <!-- Metode Ambil -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-accent mb-4"><i class="fas fa-box mr-2"></i>Metode Ambil</h3>
                    <select name="pickup_method" class="form-control-custom w-full">
                        <option value="ambil" <?php echo (isset($_POST['pickup_method']) && $_POST['pickup_method'] == 'ambil') ? 'selected' : ''; ?>>Ambil di Toko</option>
                        <option value="gosend" <?php echo (isset($_POST['pickup_method']) && $_POST['pickup_method'] == 'gosend') ? 'selected' : ''; ?>>Gosend / Kurir</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ringkasan Pesanan -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                <h3 class="text-xl font-bold text-accent mb-6">Ringkasan Pesanan</h3>
                <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                        // format initial per-item subtotal
                        $display_item_subtotal = $item['item_subtotal'];
                        ?>
                        <div class="flex justify-between text-sm order-item" data-item-type="<?php echo htmlspecialchars($item['type']); ?>"
                             data-price="<?php echo ($item['type'] === 'kostum') ? $item['harga_sewa'] : $item['harga_layanan']; ?>"
                             data-qty="<?php echo $item['quantity']; ?>"
                             data-index="<?php echo $index; ?>">
                            <div class="flex-1">
                                <p class="font-medium text-accent"><?php echo htmlspecialchars($item['type'] === 'kostum' ? $item['nama_kostum'] : $item['nama_layanan']); ?></p>
                                <p class="text-gray-500"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['type'] === 'kostum' ? $item['harga_sewa'] : $item['harga_layanan']); ?></p>

                                <?php
                                if ($item['type'] === 'makeup' && !empty($item['jadwal_id'])) {
                                    $jadwal_preview = null;
                                    $jadwal_q = $conn->prepare("SELECT tanggal, jam_mulai, jam_selesai FROM jadwal_makeup WHERE id = ? LIMIT 1");
                                    if ($jadwal_q) {
                                        $jadwal_q->bind_param("i", $item['jadwal_id']);
                                        $jadwal_q->execute();
                                        $jadwal_preview = $jadwal_q->get_result()->fetch_assoc();
                                        $jadwal_q->close();
                                    }
                                    if ($jadwal_preview) {
                                        echo '<p class="text-gray-400 text-xs">Jadwal: ' . (function_exists('formatDate') ? formatDate($jadwal_preview['tanggal']) : $jadwal_preview['tanggal']) . ' ' . ($jadwal_preview['jam_mulai'] ?? '') . '-' . ($jadwal_preview['jam_selesai'] ?? '') . '</p>';
                                    }
                                }
                                if ($item['type'] === 'kostum' && !empty($item['nama_variasi'])) {
                                    echo '<p class="text-gray-400 text-xs">Ukuran: ' . htmlspecialchars($item['nama_variasi']) . '</p>';
                                }
                                ?>

                                <?php if ($item['type'] === 'kostum'): ?>
                                    <p class="text-gray-400 text-xs rental-duration" style="<?php echo ($initial_durasi ? '' : 'display:none;'); ?>">
                                        Durasi sewa: <span class="duration-value"><?php echo $initial_durasi ? intval($initial_durasi) : ''; ?></span> hari
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium item-subtotal" data-index="<?php echo $index; ?>"><?php echo formatRupiah($display_item_subtotal); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-gray-200 pt-4 space-y-2 mb-6">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal (<?php echo count($items); ?> item)</span>
                        <span id="summary-subtotal" class="font-medium"><?php echo formatRupiah($display_subtotal); ?></span>
                    </div>

                    <!-- Durasi ringkasan ditempatkan persis di bawah subtotal -->
                    <div id="rental-duration-summary" class="flex justify-between text-gray-600" style="<?php echo ($initial_durasi ? '' : 'display:none;'); ?>">
                        <span>Durasi Sewa</span>
                        <span id="rental-duration-value" class="font-medium"><?php echo $initial_durasi ? intval($initial_durasi) . ' hari' : ''; ?></span>
                    </div>

                    <div class="border-t border-gray-200 pt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-accent">Total</span>
                            <span id="summary-total" class="text-2xl font-bold text-primary"><?php echo formatRupiah($display_subtotal); ?></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full btn-primary-custom py-3 rounded-lg font-medium"><i class="fas fa-check-circle mr-2"></i>Buat Pesanan</button>
                <a href="cart.php" class="block text-center mt-4 text-gray-600 hover:text-primary transition"><i class="fas fa-arrow-left mr-2"></i>Kembali ke Keranjang</a>
            </div>
        </div>
    </form>
</div>

<script>
    // Fungsi menghitung durasi inklusif (hari)
    function computeInclusiveDays(startDateStr, endDateStr) {
        if (!startDateStr || !endDateStr) return null;
        const d1 = new Date(startDateStr);
        const d2 = new Date(endDateStr);
        if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return null;
        if (d2 < d1) return null;
        const diffMs = d2.getTime() - d1.getTime();
        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1; // inklusif
        return days;
    }

    function formatDateToYMD(d) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function updateDurasiAndTotals() {
        const start = document.getElementById('tanggal_sewa') ? document.getElementById('tanggal_sewa').value : '';
        const end = document.getElementById('tanggal_selesai') ? document.getElementById('tanggal_selesai').value : '';
        const days = computeInclusiveDays(start, end);
        const warning = document.getElementById('rental_date_warning');
        const durationSummary = document.getElementById('rental-duration-summary');
        const durationValueEl = document.getElementById('rental-duration-value');
        const itemEls = document.querySelectorAll('.order-item');
        let total = 0;

        // additional check: ensure end is not more than 5 days after start
        let rawDiffTooLarge = false;
        if (start && end) {
            const rawDiff = (new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24);
            if (isNaN(rawDiff) || rawDiff < 0) {
                rawDiffTooLarge = false; // handled by days === null
            } else {
                if (rawDiff > 5) rawDiffTooLarge = true;
            }
        }

        if (days === null || rawDiffTooLarge) {
            if ((start && end && (days === null || rawDiffTooLarge)) && warning) {
                warning.style.display = 'block';
            } else if (warning) {
                warning.style.display = 'none';
            }
            if (durationSummary) durationSummary.style.display = 'none';

            itemEls.forEach(function(el){
                const type = el.getAttribute('data-item-type');
                const price = parseFloat(el.getAttribute('data-price')) || 0;
                const qty = parseInt(el.getAttribute('data-qty')) || 1;
                let itemSubtotal = price * qty;
                const idx = el.getAttribute('data-index');
                const subtotalEl = document.querySelector('.item-subtotal[data-index="'+idx+'"]');
                if (subtotalEl) subtotalEl.textContent = new Intl.NumberFormat('id-ID').format(itemSubtotal).replace(/,/g, '.');
                total += itemSubtotal;
            });
        } else {
            if (warning) warning.style.display = 'none';
            if (durationSummary) {
                durationSummary.style.display = 'flex';
                durationValueEl.textContent = days + ' hari';
            }
            itemEls.forEach(function(el){
                const type = el.getAttribute('data-item-type');
                const price = parseFloat(el.getAttribute('data-price')) || 0;
                const qty = parseInt(el.getAttribute('data-qty')) || 1;
                let itemSubtotal = price * qty;
                if (type === 'kostum') {
                    itemSubtotal = price * qty * days;
                    const durEls = el.querySelectorAll('.rental-duration');
                    durEls.forEach(function(d){
                        d.style.display = 'block';
                        const span = d.querySelector('.duration-value');
                        if (span) span.textContent = days;
                    });
                }
                const idx = el.getAttribute('data-index');
                const subtotalEl = document.querySelector('.item-subtotal[data-index="'+idx+'"]');
                if (subtotalEl) subtotalEl.textContent = new Intl.NumberFormat('id-ID').format(itemSubtotal).replace(/,/g, '.');
                total += itemSubtotal;
            });
        }

        const summarySubtotalEl = document.getElementById('summary-subtotal');
        const summaryTotalEl = document.getElementById('summary-total');
        if (summarySubtotalEl) summarySubtotalEl.textContent = new Intl.NumberFormat('id-ID').format(total).replace(/,/g, '.');
        if (summaryTotalEl) summaryTotalEl.textContent = new Intl.NumberFormat('id-ID').format(total).replace(/,/g, '.');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const startInput = document.getElementById('tanggal_sewa');
        const endInput = document.getElementById('tanggal_selesai');

        // set min for start & end to today's date
        const today = new Date();
        const todayStr = formatDateToYMD(today);
        if (startInput) {
            startInput.setAttribute('min', todayStr);
        }
        if (endInput) {
            // default min is today (if start not selected yet)
            endInput.setAttribute('min', todayStr);
        }

        if (startInput) {
            startInput.addEventListener('change', function() {
                const startVal = this.value;
                if (!startVal) {
                    if (endInput) {
                        endInput.setAttribute('min', todayStr);
                        endInput.removeAttribute('max');
                    }
                    updateDurasiAndTotals();
                    return;
                }
                // set end min = start
                if (endInput) {
                    endInput.setAttribute('min', startVal);
                    // set end max = start + 5 days
                    const maxDate = new Date(startVal);
                    maxDate.setDate(maxDate.getDate() + 4);
                    const maxDateStr = formatDateToYMD(maxDate);
                    endInput.setAttribute('max', maxDateStr);

                    // if end empty, set it to start by default (UX)
                    if (!endInput.value) {
                        endInput.value = startVal;
                    } else {
                        // jika value end melebihi max, set ke max
                        if (new Date(endInput.value) > new Date(maxDateStr)) {
                            endInput.value = maxDateStr;
                        }
                        // jika value end lebih kecil dari start, set ke start
                        if (new Date(endInput.value) < new Date(startVal)) {
                            endInput.value = startVal;
                        }
                    }
                }
                updateDurasiAndTotals();
            });
        }

        if (endInput) endInput.addEventListener('change', updateDurasiAndTotals);

        // initial run (in case old values present)
        // Also ensure if old values violate constraints, adjust them
        if (startInput && startInput.value) {
            // apply same logic as change
            const ev = new Event('change');
            startInput.dispatchEvent(ev);
        } else {
            updateDurasiAndTotals();
        }
    });

    const alamatChoice = document.getElementById('alamat_choice');
    const alamatLainnyaContainer = document.getElementById('alamat_lainnya_container');

    if (alamatChoice) {
        alamatChoice.addEventListener('change', function() {
            if (this.value === 'lainnya') {
                alamatLainnyaContainer.style.display = 'block';
            } else {
                alamatLainnyaContainer.style.display = 'none';
            }
        });
    }
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>
