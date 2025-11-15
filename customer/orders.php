<?php
$page_title = "Pesanan Saya";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

requireLogin();

if (isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Filter status
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where = ["id_user = ?"];
$params = [$user_id];
$types = "i";

if (!empty($status_filter)) {
    // tambahkan 'ditolak' sebagai status valid baru
    $valid_statuses = ['pending','paid', 'proses', 'selesai', 'batal', 'ditolak'];
    if (in_array($status_filter, $valid_statuses)) {
        $where[] = "status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

$where_clause = implode(" AND ", $where);

$query = "SELECT * FROM transaksi WHERE $where_clause ORDER BY tanggal_pemesanan DESC";
$stmt = $conn->prepare($query);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $orders = $stmt->get_result();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM transaksi WHERE id_user = ? ORDER BY tanggal_pemesanan DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders = $stmt->get_result();
    $stmt->close();
}

// Status mapping (tambahkan 'ditolak')
$status_labels = [
    'pending' => ['label'=>'Menunggu Pembayaran', 'color'=>'bg-yellow-500', 'icon'=>'fa-clock'],
    'paid' => ['label'=>'Dibayar', 'color'=>'bg-green-600', 'icon'=>'fa-check-circle'],
    'proses' => ['label'=>'Diproses', 'color'=>'bg-indigo-500', 'icon'=>'fa-cog'],
    'selesai' => ['label'=>'Selesai', 'color'=>'bg-green-600', 'icon'=>'fa-check-circle'],
    'batal' => ['label'=>'Dibatalkan', 'color'=>'bg-red-500', 'icon'=>'fa-times-circle'],
    'ditolak' => ['label'=>'Pembayaran Ditolak', 'color'=>'bg-red-500', 'icon'=>'fa-ban']
];
?>

<!-- Success Alert -->
<?php if (isset($_SESSION['checkout_success'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Pesanan Berhasil Dibuat!',
    text: 'Silakan upload bukti pembayaran untuk melanjutkan pesanan Anda.',
    confirmButtonColor: '#7A6A54'
});
</script>
<?php unset($_SESSION['checkout_success']); endif; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-accent">
            <i class="fas fa-box mr-2"></i>Pesanan Saya
        </h1>
        <p class="text-gray-600 mt-2">Kelola dan lacak pesanan Anda</p>
    </div>

    <!-- Filter Status -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="orders.php" class="px-4 py-2 rounded-lg transition <?php echo empty($status_filter) ? 'bg-primary text-white' : 'bg-light-bg text-accent hover:bg-beige-dark'; ?>">
                <i class="fas fa-list mr-2"></i>Semua
            </a>
            <?php foreach($status_labels as $key => $status): ?>
            <a href="?status=<?php echo $key; ?>" 
               class="px-4 py-2 rounded-lg transition <?php echo $status_filter == $key ? $status['color'].' text-white' : 'bg-light-bg text-accent hover:bg-beige-dark'; ?>">
               <i class="fas <?php echo $status['icon']; ?> mr-2"></i><?php echo $status['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Orders List -->
    <?php if ($orders->num_rows > 0): ?>
        <div class="space-y-6">
            <?php while ($order = $orders->fetch_assoc()):
                // Ambil detail item (ambil 1 item untuk ringkasan / foto pertama)
                $items_query = "SELECT dt.*, k.nama_kostum, kv.id as id_variasi, kv.ukuran AS nama_variasi, lm.nama_layanan, k.foto 
                                FROM detail_transaksi dt
                                LEFT JOIN kostum k ON dt.id_kostum = k.id
                                LEFT JOIN kostum_variasi kv ON dt.id_kostum_variasi = kv.id
                                LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
                                WHERE dt.id_transaksi = ? LIMIT 1";
                $stmt = $conn->prepare($items_query);
                $stmt->bind_param("i", $order['id']);
                $stmt->execute();
                $items = $stmt->get_result();
                $stmt->close();

                // Ambil alamat dan catatan dari detail_transaksi pertama (termasuk catatan_admin jika ada)
                $address_query = "SELECT alamat, catatan, catatan_admin FROM detail_transaksi WHERE id_transaksi = ? LIMIT 1";
                $stmt = $conn->prepare($address_query);
                $stmt->bind_param("i", $order['id']);
                $stmt->execute();
                $res_address = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $alamat = $res_address['alamat'] ?? '';
                $catatan_item = $res_address['catatan'] ?? '';
                $catatan_admin = $res_address['catatan_admin'] ?? '';

                // Ambil tanggal sewa & tanggal selesai jika ada item kostum pada transaksi
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

                // Ambil pembayaran terbaru untuk order (untuk menampilkan alasan penolakan jika ada)
                $last_payment = null;
                $pstmt = $conn->prepare("SELECT * FROM pembayaran WHERE id_transaksi = ? ORDER BY tanggal_bayar DESC LIMIT 1");
                if ($pstmt) {
                    $pstmt->bind_param("i", $order['id']);
                    $pstmt->execute();
                    $last_payment = $pstmt->get_result()->fetch_assoc();
                    $pstmt->close();
                }

                $status = $status_labels[$order['status']] ?? ['label'=>$order['status'], 'color'=>'bg-gray-300', 'icon'=>'fa-info-circle'];
            ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Order Header -->
                <div class="bg-light-bg px-6 py-4 border-b border-beige-dark flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-accent">Order #<?php echo $order['id']; ?></h3>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-calendar mr-1"></i>
                            <?php echo formatDate($order['tanggal_pemesanan']); ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-1 rounded-full text-sm font-medium <?php echo $status['color'].' text-white'; ?>">
                            <i class="fas <?php echo $status['icon']; ?> mr-1"></i><?php echo $status['label']; ?>
                        </span>
                        <span class="text-xl font-bold text-primary"><?php echo formatRupiah($order['total_harga']); ?></span>
                    </div>
                </div>

                <!-- Order Body -->
                <div class="p-6">
                    <!-- Order Items (ringkasan, hanya 1 item ditampilkan) -->
                    <div class="mb-4">
                        <h4 class="font-bold text-accent mb-3">Pesanan:</h4>
                        <div class="space-y-2">
                            <?php if ($items->num_rows > 0): ?>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <p class="font-medium text-accent">
                                            <?php 
                                            if(!empty($item['id_kostum'])) echo htmlspecialchars($item['nama_kostum'] . (!empty($item['nama_variasi']) ? ' - '.$item['nama_variasi'] : ''));
                                            elseif(!empty($item['id_layanan_makeup'])) echo htmlspecialchars($item['nama_layanan']);
                                            else echo 'Item';
                                            ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo intval($item['jumlah']); ?> x <?php echo ($item['jumlah']>0) ? formatRupiah($item['subtotal'] / $item['jumlah']) : formatRupiah($item['subtotal']); ?>
                                                <?php if(!empty($item['tanggal_sewa'])) echo ' | Tanggal Sewa: '.formatDate($item['tanggal_sewa']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="font-bold text-primary"><?php echo formatRupiah($item['subtotal']); ?></span>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-600">Tidak ada item pada pesanan ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Jika status 'ditolak', tampilkan alasan penolakan (jika ada) -->
                    <?php if ($order['status'] === 'ditolak'): ?>
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded">
                            <p class="font-medium text-red-700 mb-2">Pembayaran Anda ditolak.</p>

                            <?php
                            // Prioritas tampilan catatan admin:
                            // 1. catatan dari tabel pembayaran terbaru (last_payment['catatan_admin']) jika status_pembayaran == 'ditolak'
                            // 2. catatan_admin dari detail_transaksi pertama ($catatan_admin)
                            // 3. catatan_admin pada transaksi ($order['catatan_admin'])
                            ?>

                            <?php if (!empty($last_payment) && !empty($last_payment['status_pembayaran']) && strtolower($last_payment['status_pembayaran']) === 'ditolak' && !empty($last_payment['catatan_admin'])): ?>
                                <p class="text-sm text-red-600"><strong>Alasan/Note:</strong> <?php echo nl2br(htmlspecialchars($last_payment['catatan_admin'])); ?></p>
                            <?php elseif (!empty($catatan_admin)): ?>
                                <p class="text-sm text-red-600"><strong>Alasan/Note (verifikasi item):</strong> <?php echo nl2br(htmlspecialchars($catatan_admin)); ?></p>
                            <?php elseif (!empty($order['catatan_admin'])): ?>
                                <p class="text-sm text-red-600"><strong>Alasan/Note:</strong> <?php echo nl2br(htmlspecialchars($order['catatan_admin'])); ?></p>
                            <?php else: ?>
                                <p class="text-sm text-red-600">Pembayaran ditolak. Silakan upload ulang bukti pembayaran dengan bukti yang jelas.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Shipping Info & Catatan Item -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-light-bg rounded-lg p-4">
                            <h5 class="font-bold text-accent mb-2">
                                <i class="fas fa-map-marker-alt mr-2"></i>Alamat Pengiriman / Periode Sewa
                            </h5>
                            <p class="text-sm text-gray-700">
                                <?php echo nl2br(htmlspecialchars($alamat)); ?>
                            </p>

                            <?php if (!empty($tanggal_sewa) || !empty($tanggal_selesai)): ?>
                                <div class="mt-3 text-sm text-gray-700">
                                    <p>
                                        <?php 
                                        $t1 = !empty($tanggal_sewa) ? formatDate($tanggal_sewa) : '-';
                                        $t2 = !empty($tanggal_selesai) ? formatDate($tanggal_selesai) : '-';
                                        echo $t1 . ' sampai ' . $t2;
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($catatan_item)): ?>
                        <div class="bg-light-bg rounded-lg p-4">
                            <h5 class="font-bold text-accent mb-2">
                                <i class="fas fa-sticky-note mr-2"></i>Catatan
                            </h5>
                            <p class="text-sm text-gray-700">
                                <?php echo nl2br(htmlspecialchars($catatan_item)); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Catatan User/Admin (transaksi) -->
                    <?php if(!empty($order['catatan'])): ?>
                        <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded">
                            <p class="text-sm text-yellow-700"><strong>Catatan Anda:</strong> <?php echo htmlspecialchars($order['catatan']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($order['catatan_admin'])): ?>
                        <div class="mb-4 bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                            <p class="text-sm text-blue-700"><strong>Catatan Admin (Transaksi):</strong> <?php echo htmlspecialchars($order['catatan_admin']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3 mt-6 pt-6 border-t border-gray-200">
                        <?php if ($order['status'] == 'pending'): ?>
                            <button onclick="showUploadPayment(<?php echo (int)$order['id']; ?>, <?php echo json_encode((float)$order['total_harga']); ?>)" 
                                    class="btn-primary-custom px-6 py-2 rounded-lg">
                                <i class="fas fa-upload mr-2"></i>Upload Bukti Pembayaran
                            </button>
                        <?php elseif ($order['status'] == 'ditolak'): ?>
                            <!-- Jika pembayaran ditolak beri opsi upload ulang -->
                            <button onclick="showUploadPayment(<?php echo (int)$order['id']; ?>, <?php echo json_encode((float)$order['total_harga']); ?>)" 
                                    class="btn-primary-custom px-6 py-2 rounded-lg">
                                <i class="fas fa-upload mr-2"></i>Upload Ulang Bukti Pembayaran
                            </button>
                        <?php elseif ($order['status'] == 'proses'): ?>
                            <button type="button" 
                                    class="btn-primary-custom px-6 py-2 rounded-lg"
                                    onclick="confirmOrderReceived(<?php echo (int)$order['id']; ?>)">
                                <i class="fas fa-check-circle mr-2"></i>Pesanan Diterima
                            </button>
                        <?php elseif ($order['status'] == 'selesai'): ?>
                            <a href="invoice.php?order_id=<?php echo $order['id']; ?>" 
                               target="_blank"
                               class="btn-primary-custom px-6 py-2 rounded-lg inline-block">
                                <i class="fas fa-file-pdf mr-2"></i>Cetak Invoice
                            </a>
                        <?php endif; ?>

                        <button onclick="viewOrderDetail(<?php echo $order['id']; ?>)" 
                                class="btn-secondary-custom px-6 py-2 rounded-lg">
                            <i class="fas fa-eye mr-2"></i>Detail Pesanan
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-2xl font-bold text-accent mb-2">
                <?php echo empty($status_filter) ? 'Belum Ada Pesanan' : 'Tidak Ada Pesanan dengan Status Ini'; ?>
            </h3>
            <p class="text-gray-600 mb-6">
                <?php
                if(empty($status_filter)){
                    echo 'Anda belum memiliki pesanan';
                } else {
                    $status_text = $status_labels[$status_filter]['label'] ?? $status_filter;
                    echo 'Tidak ada pesanan dengan status "' . $status_text . '"';
                }
                ?>
            </p>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn-primary-custom px-8 py-3 rounded-lg inline-block">
                <i class="fas fa-shopping-bag mr-2"></i>Mulai Belanja
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Payment Modal (tidak menampilkan metode/jumlah, dikirim default) -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-accent">Upload Bukti Pembayaran</h3>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Form: hanya file terlihat. Metode & jumlah disertakan tapi sebagai hidden -->
        <form id="uploadPaymentForm" enctype="multipart/form-data">
            <!-- sesuai upload-payment.php -->
            <input type="hidden" id="upload_order_id" name="id_transaksi">
            <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" value="Transfer">
            <input type="hidden" id="jumlah_bayar" name="jumlah_bayar" value="0">

            <div class="bg-light-bg rounded-lg p-4 mb-4">
                <h4 class="font-bold text-accent mb-2">Informasi Rekening</h4>
                <div class="space-y-1 text-sm">
                    <p><strong>Bank:</strong> BCA</p>
                    <p><strong>No. Rekening:</strong> 1234567890</p>
                    <p><strong>Atas Nama:</strong> Charitize</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-accent font-medium mb-2">
                    Upload Bukti Transfer <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="flex items-center gap-3">
                        <label for="bukti_pembayaran" class="btn-secondary-custom px-6 py-3 rounded-lg cursor-pointer inline-block">
                            <i class="fas fa-image mr-2"></i>Pilih File
                        </label>
                        <span id="payment_file_name" class="text-gray-600 text-sm">Tidak ada file dipilih</span>
                        <input type="file" 
                               name="bukti_pembayaran" 
                               id="bukti_pembayaran"
                               accept="image/*" 
                               required
                               onchange="previewImage(this, 'preview_payment'); updateFileName(this, 'payment_file_name')"
                               class="absolute opacity-0 -z-10">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG (Max 5MB)</p>
                </div>
            </div>

            <div class="mb-4">
                <img id="preview_payment" src="" alt="" class="hidden w-full rounded-lg border-2 border-beige-dark">
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeUploadModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 py-2 rounded-lg font-medium transition">
                    Batal
                </button>
                <button type="submit" 
                        class="flex-1 btn-primary-custom py-2 rounded-lg font-medium">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewOrderDetail(orderId) {
        window.location.href = 'order-detail.php?order_id=' + orderId;
    }

    function showUploadPayment(transaksiId, amount) {
        // set hidden fields
        document.getElementById('upload_order_id').value = transaksiId;
        document.getElementById('metode_pembayaran').value = 'Transfer';
        // pastikan amount numeric, jika null gunakan 0
        const jumlahField = document.getElementById('jumlah_bayar');
        jumlahField.value = (typeof amount !== 'undefined' && amount !== null && amount !== '') ? Number(amount) : 0;

        // Reset file preview & label
        document.getElementById('payment_file_name').textContent = 'Tidak ada file dipilih';
        const preview = document.getElementById('preview_payment');
        preview.src = '';
        preview.classList.add('hidden');
        document.getElementById('bukti_pembayaran').value = '';

        // show modal
        document.getElementById('uploadModal').classList.remove('hidden');
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.add('hidden');
        const form = document.getElementById('uploadPaymentForm');
        if (form) form.reset();
        document.getElementById('preview_payment').classList.add('hidden');
        document.getElementById('payment_file_name').textContent = 'Tidak ada file dipilih';
    }

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        } else {
            preview.src = '';
            preview.classList.add('hidden');
        }
    }

    function updateFileName(input, labelId) {
        const label = document.getElementById(labelId);
        label.textContent = input.files && input.files[0] ? input.files[0].name : 'Tidak ada file dipilih';
    }

    document.getElementById('uploadPaymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;

        // safety: pastikan hidden nilai sudah terisi (fallback)
        const idTrans = document.getElementById('upload_order_id').value;
        if (!idTrans) {
            showError('ID transaksi tidak ditemukan.');
            return;
        }

        // FormData sudah akan berisi: id_transaksi, metode_pembayaran, jumlah_bayar, bukti_pembayaran
        const formData = new FormData(form);
        showLoading();

        fetch('<?php echo BASE_URL; ?>api/upload-payment.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data && data.success) {
                closeUploadModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message,
                    confirmButtonColor: '#7A6A54'
                }).then(()=> location.reload());
            } else {
                showError(data && data.message ? data.message : 'Terjadi kesalahan saat mengupload');
            }
        })
        .catch(err => {
            hideLoading();
            showError('Terjadi kesalahan jaringan');
            console.error(err);
        });
    });

    function showLoading(){
        Swal.fire({
            title: 'Memproses...',
            text: 'Sedang mengupload bukti pembayaran',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }
    function hideLoading(){ Swal.close(); }
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message,
            confirmButtonColor: '#7A6A54'
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>
