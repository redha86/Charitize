<?php
$page_title = "Kelola Pesanan - Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

$conn = getConnection();

/**
 * Handle update status (admin)
 * Admin allowed to set: paid, proses, batal, selesai, ditolak
 */
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = sanitize($_POST['status']);

    // ambil catatan admin jika diberikan
    $catatan_admin = isset($_POST['catatan_admin']) ? trim($_POST['catatan_admin']) : '';

    // perbarui daftar valid dan admin allowed termasuk 'ditolak'
    $valid_statuses = ['pending','paid','proses','selesai','batal','ditolak'];
    $admin_allowed = ['paid', 'proses', 'batal', 'selesai', 'ditolak'];

    if (!in_array($new_status, $valid_statuses) || !in_array($new_status, $admin_allowed)) {
        $_SESSION['error_message'] = "Status tidak valid.";
        redirect('admin/orders.php');
        exit;
    }

    $status_query = "SELECT status FROM transaksi WHERE id = ?";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    if (!$current) {
        $_SESSION['error_message'] = "Pesanan tidak ditemukan.";
        redirect('admin/orders.php');
        exit;
    }

    if (in_array($current['status'], ['selesai', 'batal'])) {
        $status_message = [
            'selesai' => 'selesai',
            'batal' => 'dibatalkan'
        ];
        $_SESSION['error_message'] = "Pesanan yang sudah " . $status_message[$current['status']] . " tidak dapat diubah statusnya";
        redirect('admin/orders.php');
        exit;
    }

    // Jika admin memilih batal atau ditolak, require catatan admin
    if (in_array($new_status, ['batal', 'ditolak']) && $catatan_admin === '') {
        $_SESSION['error_message'] = "Catatan admin wajib diisi ketika memilih status 'batal' atau 'ditolak'.";
        redirect('admin/orders.php');
        exit;
    }

    // Update transaksi status
    $update_query = "UPDATE transaksi SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $order_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Jika dibatalkan dan ada catatan_admin -> simpan ke detail_transaksi
                if ($new_status === 'batal' && $catatan_admin !== '') {
                    $u = $conn->prepare("UPDATE detail_transaksi SET catatan_admin = ? WHERE id_transaksi = ?");
                    if ($u) {
                        $u->bind_param("si", $catatan_admin, $order_id);
                        $u->execute();
                        $u->close();
                    }
                }

                // Jika ditolak: update pembayaran terbaru jika ada, simpan catatan_admin di pembayaran.
                if ($new_status === 'ditolak' && $catatan_admin !== '') {
                    // cari pembayaran terakhir
                    $pstmt = $conn->prepare("SELECT id FROM pembayaran WHERE id_transaksi = ? ORDER BY id DESC LIMIT 1");
                    if ($pstmt) {
                        $pstmt->bind_param("i", $order_id);
                        $pstmt->execute();
                        $pRes = $pstmt->get_result()->fetch_assoc();
                        $pstmt->close();

                        if ($pRes && !empty($pRes['id'])) {
                            $payId = intval($pRes['id']);
                            // Update pembayaran: status_pembayaran dan catatan_admin
                            $up = $conn->prepare("UPDATE pembayaran SET status_pembayaran = 'ditolak', catatan_admin = ? WHERE id = ?");
                            if ($up) {
                                $up->bind_param("si", $catatan_admin, $payId);
                                $up->execute();
                                $up->close();
                            }
                        } else {
                            // Jika tidak ada record pembayaran, simpan catatan ke transaksi sebagai fallback
                            $ut = $conn->prepare("UPDATE transaksi SET catatan_admin = ? WHERE id = ?");
                            if ($ut) {
                                $ut->bind_param("si", $catatan_admin, $order_id);
                                $ut->execute();
                                $ut->close();
                            }
                        }
                    }
                }

                $label_map = [
                    'pending' => 'Menunggu Pembayaran',
                    'paid' => 'Dibayar',
                    'proses' => 'Diproses',
                    'selesai' => 'Selesai',
                    'batal' => 'Dibatalkan',
                    'ditolak' => 'Pembayaran Ditolak'
                ];
                $label = $label_map[$new_status] ?? $new_status;
                $_SESSION['success_message'] = "Status pesanan berhasil diupdate menjadi '" . $label . "'!";
            } else {
                $_SESSION['error_message'] = "Tidak ada perubahan yang dilakukan. Mungkin status sudah sama.";
            }
        } else {
            $_SESSION['error_message'] = "Gagal mengupdate status pesanan: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
    }

    redirect('admin/orders.php');
    exit;
}

// Filtering & fetching
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$valid_statuses = ['pending','paid','proses','selesai','batal','ditolak'];

$where = [];
$params = [];
$types = "";

if (!empty($status_filter) && in_array($status_filter, $valid_statuses)) {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/*
  Perbaikan: JOIN users hanya berdasarkan satu kolom id (t.id_user = u.id)
  Subquery mengambil bukti_pembayaran dari tabel pembayaran berdasarkan id_transaksi.
*/
$query = "SELECT 
            t.*,
            COALESCE(u.name, u.name) AS customer_name,
            u.email,
            COALESCE(u.phone, u.phone) AS phone,
            (SELECT p.bukti_pembayaran FROM pembayaran p WHERE p.id_transaksi = t.id ORDER BY p.id DESC LIMIT 1) AS bukti_pembayaran
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.user_id
          $where_clause
          ORDER BY t.tanggal_pemesanan DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $orders = $stmt->get_result();
        $stmt->close();
    } else {
        $orders = $conn->query($query);
    }
} else {
    $orders = $conn->query($query);
}

// status labels mapping (UI) - tambahkan 'ditolak'
$status_labels = [
    'pending' => ['label'=>'Menunggu Pembayaran', 'color'=>'bg-yellow-500', 'icon'=>'fa-clock'],
    'paid' => ['label'=>'Dibayar', 'color'=>'bg-green-600', 'icon'=>'fa-check-circle'],
    'proses' => ['label'=>'Diproses', 'color'=>'bg-indigo-500', 'icon'=>'fa-cog'],
    'selesai' => ['label'=>'Selesai', 'color'=>'bg-green-600', 'icon'=>'fa-check-circle'],
    'batal' => ['label'=>'Dibatalkan', 'color'=>'bg-red-500', 'icon'=>'fa-times-circle'],
    'ditolak' => ['label'=>'Pembayaran Ditolak', 'color'=>'bg-red-500', 'icon'=>'fa-ban']
];
?>

<div class="flex">
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 bg-lighter-bg lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-8 lg:pb-16">
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Kelola Pesanan</h1>
                <p class="text-gray-600 mt-2 text-sm sm:text-base">Kelola dan update status pesanan</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                    confirmButtonColor: '#7A6A54',
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            </script>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                    confirmButtonColor: '#7A6A54',
                    showConfirmButton: true
                });
            </script>
            <?php unset($_SESSION['error_message']); endif; ?>

            <!-- Filter -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-wrap gap-2">
                    <a href="orders.php" class="px-3 sm:px-4 py-2 rounded-lg transition text-center text-xs sm:text-sm <?php echo empty($status_filter) ? 'bg-primary text-white' : 'bg-light-bg text-accent hover:bg-beige-dark'; ?>">
                        <i class="fas fa-list mr-1 sm:mr-2"></i><span class="hidden sm:inline">Semua</span>
                    </a>
                    <?php foreach ($status_labels as $key => $s): ?>
                    <a href="?status=<?php echo $key; ?>" class="px-3 sm:px-4 py-2 rounded-lg transition text-center text-xs sm:text-sm <?php echo $status_filter == $key ? $s['color'].' text-white' : 'bg-light-bg text-accent hover:bg-beige-dark'; ?>">
                        <span class="hidden sm:inline"><?php echo $s['label']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($orders && $orders->num_rows > 0): ?>
                <div class="space-y-4 lg:space-y-6">
                    <?php while ($order = $orders->fetch_assoc()):
                        // ambil 1 item ringkasan
                        $items_query = "SELECT dt.*, k.nama_kostum, kv.id AS id_variasi, kv.ukuran AS nama_variasi, lm.nama_layanan, k.foto
                                        FROM detail_transaksi dt
                                        LEFT JOIN kostum k ON dt.id_kostum = k.id
                                        LEFT JOIN kostum_variasi kv ON dt.id_kostum_variasi = kv.id
                                        LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
                                        WHERE dt.id_transaksi = ? LIMIT 1";
                        $stmt = $conn->prepare($items_query);
                        if ($stmt) {
                            $stmt->bind_param("i", $order['id']);
                            $stmt->execute();
                            $items = $stmt->get_result();
                            $stmt->close();
                        } else {
                            $items = null;
                        }

                        // alamat & catatan
                        $address_query = "SELECT alamat, catatan FROM detail_transaksi WHERE id_transaksi = ? LIMIT 1";
                        $stmt = $conn->prepare($address_query);
                        if ($stmt) {
                            $stmt->bind_param("i", $order['id']);
                            $stmt->execute();
                            $res_address = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                        } else {
                            $res_address = null;
                        }

                        $alamat = $res_address['alamat'] ?? '';
                        $catatan_item = $res_address['catatan'] ?? '';

                        // tanggal sewa jika ada
                        $tanggal_sewa = null;
                        $tanggal_selesai = null;
                        $date_query = "SELECT tanggal_sewa, tanggal_selesai FROM detail_transaksi WHERE id_transaksi = ? AND id_kostum IS NOT NULL LIMIT 1";
                        $stmtD = $conn->prepare($date_query);
                        if ($stmtD) {
                            $stmtD->bind_param("i", $order['id']);
                            $stmtD->execute();
                            $res_date = $stmtD->get_result()->fetch_assoc();
                            $stmtD->close();
                            if ($res_date) {
                                $tanggal_sewa = $res_date['tanggal_sewa'] ?? null;
                                $tanggal_selesai = $res_date['tanggal_selesai'] ?? null;
                            }
                        }

                        $status = $status_labels[$order['status']] ?? ['label'=>$order['status'], 'color'=>'bg-gray-300', 'icon'=>'fa-info-circle'];
                        $proof = $order['bukti_pembayaran'] ?? null;
                    ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="bg-light-bg px-4 sm:px-6 py-3 sm:py-4 border-b border-beige-dark">
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <h3 class="text-base sm:text-lg font-bold text-accent">Order #<?php echo $order['id']; ?></h3>
                                        <p class="text-xs sm:text-sm text-gray-600 mt-1"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($order['customer_name'] ?? '—'); ?></p>
                                        <p class="text-xs sm:text-sm text-gray-600"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($order['phone'] ?? '—'); ?></p>
                                        <p class="text-xs sm:text-sm text-gray-600"><i class="fas fa-calendar mr-1"></i><?php echo formatDate($order['tanggal_pemesanan']); ?></p>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status['color'].' text-white'; ?>">
                                            <i class="fas <?php echo $status['icon']; ?> mr-1"></i><?php echo $status['label']; ?>
                                        </span>
                                        <span class="text-lg sm:text-xl font-bold text-primary"><?php echo formatRupiah($order['total_harga']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 sm:p-6">
                                <div class="space-y-4 lg:space-y-6">
                                    <div>
                                        <h4 class="font-bold text-accent mb-3 text-sm sm:text-base">Produk Pesanan:</h4>
                                        <div class="space-y-2">
                                            <?php if ($items && $items->num_rows > 0): ?>
                                                <?php while ($item = $items->fetch_assoc()): ?>
                                                    <div class="flex justify-between py-2 border-b border-gray-100 text-xs sm:text-sm">
                                                        <div>
                                                            <p class="font-medium"><?php 
                                                                if(!empty($item['id_kostum'])) echo htmlspecialchars($item['nama_kostum'] . (!empty($item['nama_variasi']) ? ' - '.$item['nama_variasi'] : ''));
                                                                elseif(!empty($item['id_layanan_makeup'])) echo htmlspecialchars($item['nama_layanan']);
                                                                else echo 'Item';
                                                            ?></p>
                                                            <p class="text-xs text-gray-600"><?php echo intval($item['jumlah']); ?> x <?php echo ($item['jumlah']>0) ? formatRupiah($item['subtotal'] / $item['jumlah']) : formatRupiah($item['subtotal']); ?> <?php if(!empty($item['tanggal_sewa'])) echo ' | Tanggal Sewa: '.formatDate($item['tanggal_sewa']); ?></p>
                                                        </div>
                                                        <span class="font-bold text-primary"><?php echo formatRupiah($item['subtotal']); ?></span>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <p class="text-gray-600">Tidak ada item pada pesanan ini.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="bg-light-bg rounded-lg p-3 sm:p-4">
                                        <h5 class="font-bold text-accent mb-2 text-sm sm:text-base"><i class="fas fa-map-marker-alt mr-2"></i>Alamat Pengiriman / Periode Sewa</h5>
                                        <p class="text-xs sm:text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($alamat)); ?></p>
                                        <?php if (!empty($tanggal_sewa) || !empty($tanggal_selesai)): ?>
                                            <div class="mt-3 text-sm text-gray-700">
                                                <p><?php $t1 = !empty($tanggal_sewa) ? formatDate($tanggal_sewa) : '-'; $t2 = !empty($tanggal_selesai) ? formatDate($tanggal_selesai) : '-'; echo $t1 . ' sampai ' . $t2; ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($catatan_item)): ?>
                                    <div class="bg-light-bg rounded-lg p-3 sm:p-4">
                                        <h5 class="font-bold text-accent mb-2 text-sm sm:text-base"><i class="fas fa-sticky-note mr-2"></i>Catatan Item</h5>
                                        <p class="text-xs sm:text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($catatan_item)); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($proof)): ?>
                                    <div>
                                        <h5 class="font-bold text-accent mb-2 text-sm sm:text-base">Bukti Pembayaran:</h5>
                                        <img src="<?php echo BASE_URL; ?>assets/images/payments/<?php echo htmlspecialchars($proof); ?>" alt="Bukti Pembayaran" class="w-full max-w-sm rounded-lg border-2 border-beige-dark cursor-pointer" onclick="window.open(this.src, '_blank')">
                                    </div>
                                    <?php endif; ?>

                                    <?php if(!empty($order['catatan'])): ?>
                                        <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded">
                                            <p class="text-sm text-yellow-700"><strong>Catatan User:</strong> <?php echo htmlspecialchars($order['catatan']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($order['catatan_admin'])): ?>
                                        <div class="mb-4 bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                                            <p class="text-sm text-blue-700"><strong>Catatan Admin (Pesanan):</strong> <?php echo htmlspecialchars($order['catatan_admin']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($order['status'], ['selesai','batal'])): 
                                        $info = ($order['status'] == 'selesai') ? ['bg'=>'green','icon'=>'check-circle','title'=>'Pesanan Selesai','message'=>'Pesanan ini sudah selesai dan tidak dapat diubah lagi.'] : ['bg'=>'red','icon'=>'times-circle','title'=>'Pesanan Dibatalkan','message'=>'Pesanan ini telah dibatalkan dan tidak dapat diubah lagi.'];
                                    ?>
                                        <div class="bg-<?php echo $info['bg']; ?>-50 rounded-lg p-3 sm:p-4 border-2 border-<?php echo $info['bg']; ?>-500">
                                            <div class="flex items-center gap-3 mb-2">
                                                <i class="fas fa-<?php echo $info['icon']; ?> text-<?php echo $info['bg']; ?>-600 text-xl sm:text-2xl"></i>
                                                <h5 class="font-bold text-<?php echo $info['bg']; ?>-700 text-sm sm:text-lg"><?php echo $info['title']; ?></h5>
                                            </div>
                                            <p class="text-xs sm:text-sm text-<?php echo $info['bg']; ?>-700"><?php echo $info['message']; ?></p>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light-bg rounded-lg p-3 sm:p-4">
                                            <h5 class="font-bold text-accent mb-4 text-sm sm:text-base">Update Status Pesanan:</h5>
                                            <form method="POST" action="" id="statusForm_<?php echo $order['id']; ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                <div class="mb-4">
                                                    <label class="block text-accent font-medium mb-2 text-sm">Status Baru:</label>
                                                    <select name="status" required class="form-control-custom w-full text-sm" id="status_<?php echo $order['id']; ?>">
                                                        <?php 
                                                        // tambahkan opsi 'ditolak'
                                                        $admin_allowed_statuses = [
                                                            'paid' => $status_labels['paid']['label'],
                                                            'proses' => $status_labels['proses']['label'],
                                                            'selesai' => $status_labels['selesai']['label'],
                                                            'batal' => $status_labels['batal']['label'],
                                                            'ditolak' => $status_labels['ditolak']['label']
                                                        ];
                                                        foreach ($admin_allowed_statuses as $key => $label):
                                                        ?>
                                                            <option value="<?php echo $key; ?>" <?php echo $order['status'] == $key ? 'selected' : ''; ?>>
                                                                <?php echo $label; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <!-- Catatan Admin (tampil saat memilih batal atau ditolak) -->
                                                <div id="catatan_wrap_<?php echo $order['id']; ?>" style="display: none;" class="mb-4">
                                                    <label class="block text-accent font-medium mb-2 text-sm">Catatan (wajib jika memilih batal/ditolak)</label>
                                                    <textarea name="catatan_admin" id="catatan_admin_<?php echo $order['id']; ?>" rows="4" class="form-control-custom w-full text-sm" placeholder="Masukkan alasan pembatalan atau alasan penolakan pembayaran..."></textarea>
                                                    <p class="text-xs text-gray-500 mt-1">Catatan ini akan terlihat pada halaman detail pesanan pelanggan.</p>
                                                </div>

                                                <button type="button" onclick="confirmUpdateStatus(<?php echo $order['id']; ?>)" class="w-full btn-primary-custom py-2 sm:py-3 rounded-lg font-medium text-sm sm:text-base">
                                                    <i class="fas fa-save mr-2"></i>Update Status
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-8 sm:p-12 text-center">
                    <i class="fas fa-box-open text-4xl sm:text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl sm:text-2xl font-bold text-accent mb-2">Tidak Ada Pesanan</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Belum ada pesanan dengan status ini</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function confirmUpdateStatus(orderId) {
    const statusSelect = document.getElementById('status_' + orderId);
    const form = document.getElementById('statusForm_' + orderId);
    const newStatus = statusSelect.value;
    const statusLabels = {
        'paid': 'Dibayar',
        'proses': 'Diproses',
        'selesai': 'Selesai',
        'batal': 'Dibatalkan',
        'ditolak': 'Pembayaran Ditolak'
    };

    // if batal or ditolak, ensure catatan_admin not empty
    let catatan = '';
    if (newStatus === 'batal' || newStatus === 'ditolak') {
        const ta = document.getElementById('catatan_admin_' + orderId);
        if (ta) catatan = ta.value.trim();
        if (!catatan) {
            Swal.fire({
                icon: 'warning',
                title: 'Catatan Diperlukan',
                text: 'Silakan isi catatan sebelum melanjutkan (wajib untuk batal/ditolak).',
                confirmButtonColor: '#7A6A54'
            });
            return;
        }
    }

    let message = `<div class="text-left">
        <p>Apakah Anda yakin ingin mengubah status pesanan menjadi:</p>
        <div class="bg-gray-50 p-3 rounded-lg mt-2 border-l-4 border-primary">
            <strong class="text-lg text-primary">${statusLabels[newStatus] || newStatus}</strong>
        </div>`;

    if (newStatus === 'batal' || newStatus === 'ditolak') {
        message += `<div class="mt-3 p-3 bg-red-50 rounded-lg border-l-4 border-red-500">
            <p class="text-sm text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Perubahan ini bersifat permanen</p>
            <p class="text-sm text-gray-700 mt-2"><strong>Catatan:</strong><br>${escapeHtml(catatan)}</p>
        </div>`;
    }

    message += `</div>`;

    Swal.fire({
        title: 'Konfirmasi Update Status',
        html: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7A6A54',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Update Status',
        cancelButtonText: 'Batal',
        buttonsStyling: true,
        width: '90%',
        maxWidth: '700px'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang mengupdate status pesanan',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            form.submit();
        }
    });
}

// helper: escape html untuk preview catatan di modal
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;")
         .replace(/\n/g, '<br>');
}

// show/hide catatan textarea based on select change (batal or ditolak)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[id^="status_"]').forEach(function(sel) {
        const id = sel.id.replace('status_', '');
        const wrap = document.getElementById('catatan_wrap_' + id);
        // initial visibility (for pages where selected may already be 'batal' or 'ditolak')
        if (wrap) {
            if (sel.value === 'batal' || sel.value === 'ditolak') wrap.style.display = 'block';
            else wrap.style.display = 'none';
        }

        sel.addEventListener('change', function() {
            if (!wrap) return;
            if (this.value === 'batal' || this.value === 'ditolak') {
                wrap.style.display = 'block';
            } else {
                // clear previous note when switching away (optional)
                const ta = document.getElementById('catatan_admin_' + id);
                if (ta) ta.value = '';
                wrap.style.display = 'none';
            }
        });
    });
});
</script>

<div class="lg:ml-64">
    <?php require_once '../includes/footer.php'; ?>
</div>
