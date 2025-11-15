<?php
$page_title = "Detail Kostum";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id == 0) {
    redirect('../index.php');
}

$conn = getConnection();

// Ambil data kostum
$query = "SELECT k.*, c.nama_kategori 
          FROM kostum k
          LEFT JOIN kategori_kostum c ON k.id_kategori = c.id
          WHERE k.id = ? AND k.status = 'aktif'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('../index.php');
}

$kostum = $result->fetch_assoc();
$stmt->close();

// Ambil variasi kostum per ukuran
$variasiQuery = "SELECT id, ukuran, stok FROM kostum_variasi WHERE id_kostum = ?";
$stmt = $conn->prepare($variasiQuery);
$stmt->bind_param("i", $id);
$stmt->execute();
$variasiResult = $stmt->get_result();
$stmt->close();

$variasiList = [];
$totalStok = 0;
while ($v = $variasiResult->fetch_assoc()) {
    $variasiList[$v['ukuran']] = [
        'id' => $v['id'],
        'stok' => $v['stok']
    ];
    $totalStok += $v['stok'];
}

// Ambil kostum terkait
$relatedQuery = "SELECT * FROM kostum WHERE id_kategori = ? AND id != ? AND status = 'aktif' LIMIT 4";
$stmt = $conn->prepare($relatedQuery);
$stmt->bind_param("ii", $kostum['id_kategori'], $id);
$stmt->execute();
$related = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2 text-gray-600">
            <li><a href="<?php echo BASE_URL; ?>index.php" class="hover:text-primary">Beranda</a></li>
            <li><i class="fas fa-chevron-right text-xs"></i></li>
            <li class="text-accent font-medium"><?php echo htmlspecialchars($kostum['nama_kategori']); ?></li>
        </ol>
    </nav>

    <!-- Kostum Detail -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white rounded-lg shadow-lg p-8 mb-8">
        <!-- Gambar -->
        <div>
            <?php 
            $image_path = !empty($kostum['foto']) 
                ? '../assets/images/kostum/' . $kostum['foto'] 
                : 'https://via.placeholder.com/600x600?text=No+Image';
            ?>
            <img src="<?php echo $image_path; ?>" 
                 alt="<?php echo htmlspecialchars($kostum['nama_kostum']); ?>"
                 class="w-full rounded-lg shadow-md">
        </div>

        <!-- Info -->
        <div>
            <span class="inline-block bg-secondary text-white px-4 py-1 rounded-full text-sm font-medium mb-4">
                <?php echo htmlspecialchars($kostum['nama_kategori']); ?>
            </span>

            <h1 class="text-3xl font-bold text-accent mb-4">
                <?php echo htmlspecialchars($kostum['nama_kostum']); ?>
            </h1>

            <div class="mb-6">
                <span class="text-4xl font-bold text-primary">
                    <?php echo formatRupiah($kostum['harga_sewa']); ?>
                </span>
            </div>

            <div class="mb-6">
                <div id="stok-info" class="flex items-center <?php echo $totalStok > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <i class="fas <?php echo $totalStok > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-2"></i>
                    <span class="font-medium" id="stok-text">
                        <?php echo $totalStok > 0 ? "Stok Tersedia: $totalStok unit" : "Stok Habis"; ?>
                    </span>
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-accent mb-3">Deskripsi Kostum</h3>
                <p class="text-gray-700 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($kostum['deskripsi'])); ?>
                </p>
            </div>

            <!-- Pilihan Ukuran & Jumlah (multi-size) -->
            <?php if (isLoggedIn() && !isAdmin() && count($variasiList) > 0): ?>
            <div class="mb-6">
                <label class="block text-accent font-medium mb-2">Pilih Ukuran (boleh lebih dari satu)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <?php foreach ($variasiList as $ukuran => $v): ?>
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        class="ukuran-btn px-4 py-2 border rounded-lg select-ukuran-btn
                                               <?php echo $v['stok'] == 0 ? 'bg-gray-300 text-gray-600 cursor-not-allowed' : 'bg-white text-accent'; ?>"
                                        data-ukuran="<?php echo htmlspecialchars($ukuran); ?>"
                                        data-stok="<?php echo intval($v['stok']); ?>"
                                        data-variasi_id="<?php echo intval($v['id']); ?>"
                                        <?php echo $v['stok'] == 0 ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($ukuran); ?>
                                </button>
                                <div class="text-sm text-gray-500">
                                    <div>Stok: <?php echo intval($v['stok']); ?></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- quantity controls per variasi (hidden until selected) -->
                                <button type="button" class="qty-decrease px-2 py-1 rounded-lg bg-gray-100 hover:bg-gray-200"
                                        data-variasi_id="<?php echo intval($v['id']); ?>" disabled>
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number"
                                       class="qty-input w-16 text-center border rounded-md py-1"
                                       id="qty-<?php echo intval($v['id']); ?>"
                                       value="0"
                                       min="0"
                                       max="<?php echo intval($v['stok']); ?>"
                                       data-variasi_id="<?php echo intval($v['id']); ?>"
                                       disabled>
                                <button type="button" class="qty-increase px-2 py-1 rounded-lg bg-gray-100 hover:bg-gray-200"
                                        data-variasi_id="<?php echo intval($v['id']); ?>" disabled>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="text-sm text-gray-600">Catatan: Pilih ukuran yang diinginkan lalu atur jumlah per ukuran. Jika jumlah 0, variasi tidak akan ditambahkan ke keranjang.</p>
            </div>
            <?php endif; ?>

            <div class="flex gap-4">
                <?php if (isLoggedIn() && !isAdmin()): ?>
                    <?php if ($totalStok > 0): ?>
                        <button id="add-to-cart-btn" onclick="addToCartBatch(<?php echo $id; ?>, 'kostum', this)"
                                class="flex-1 btn-primary-custom py-3 rounded-lg text-lg font-medium">
                            <i class="fas fa-cart-plus mr-2"></i>Tambah ke Keranjang
                        </button>
                    <?php else: ?>
                        <button disabled class="flex-1 bg-gray-300 text-gray-600 py-3 rounded-lg text-lg font-medium cursor-not-allowed">
                            <i class="fas fa-times-circle mr-2"></i>Stok Habis
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" 
                       class="flex-1 btn-primary-custom text-center py-3 rounded-lg text-lg font-medium">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login untuk Membeli
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kostum Terkait -->
    <?php if ($related->num_rows > 0): ?>
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-accent mb-6">Kostum Terkait</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($r = $related->fetch_assoc()): ?>
                    <div class="card-custom product-card">
                        <img src="<?php echo !empty($r['foto']) ? '../assets/images/kostum/'.$r['foto'] : 'https://via.placeholder.com/400x300?text=No+Image'; ?>" 
                             alt="<?php echo htmlspecialchars($r['nama_kostum']); ?>"
                             class="product-image">
                        <div class="product-body">
                            <h3 class="product-title">
                                <?php echo htmlspecialchars($r['nama_kostum']); ?>
                            </h3>
                            <div class="product-price mb-4">
                                <?php echo formatRupiah($r['harga_sewa']); ?>
                            </div>
                            <a href="detail_kostum.php?id=<?php echo $r['id']; ?>" 
                               class="block btn-secondary-custom text-center py-2 rounded-lg text-white">
                                <i class="fas fa-eye mr-1"></i>Lihat Detail
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ukuranButtons = document.querySelectorAll('.select-ukuran-btn');
    const stokText = document.getElementById('stok-text');

    ukuranButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;

            const variasiId = btn.dataset.variasi_id;
            const stok = parseInt(btn.dataset.stok, 10);

            const alreadySelected = btn.classList.contains('ukuran-selected');
            if (!alreadySelected) {
                // select it
                btn.classList.add('ukuran-selected', 'border-primary', 'bg-light-bg');
                enableQtyControls(variasiId, stok);
            } else {
                // unselect it
                btn.classList.remove('ukuran-selected', 'border-primary', 'bg-light-bg');
                disableQtyControls(variasiId);
            }
            updateSelectedSummary();
        });
    });

    // Quantity control buttons (increase/decrease)
    document.querySelectorAll('.qty-increase').forEach(b => {
        b.addEventListener('click', () => {
            const vid = b.dataset.variasi_id;
            const input = document.querySelector(`.qty-input[data-variasi_id="${vid}"]`);
            if (!input) return;
            const max = parseInt(input.max, 10);
            let val = parseInt(input.value, 10) || 0;
            if (val < max) input.value = val + 1;
            updateSelectedSummary();
        });
    });

    document.querySelectorAll('.qty-decrease').forEach(b => {
        b.addEventListener('click', () => {
            const vid = b.dataset.variasi_id;
            const input = document.querySelector(`.qty-input[data-variasi_id="${vid}"]`);
            if (!input) return;
            let val = parseInt(input.value, 10) || 0;
            if (val > 0) input.value = val - 1;
            updateSelectedSummary();
        });
    });

    // Input manual change validation
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', () => {
            const max = parseInt(input.max, 10) || 0;
            let val = parseInt(input.value, 10);
            if (isNaN(val) || val < 0) val = 0;
            if (val > max) val = max;
            input.value = val;
            updateSelectedSummary();
        });
    });

    // Initialize: ensure controls disabled for unselected sizes
    document.querySelectorAll('.qty-input').forEach(input => {
        input.value = 0;
        input.disabled = true;
    });
    document.querySelectorAll('.qty-increase, .qty-decrease').forEach(b => b.disabled = true);

    function enableQtyControls(variasiId, stok) {
        const input = document.querySelector(`.qty-input[data-variasi_id="${variasiId}"]`);
        const inc = document.querySelector(`.qty-increase[data-variasi_id="${variasiId}"]`);
        const dec = document.querySelector(`.qty-decrease[data-variasi_id="${variasiId}"]`);
        if (!input) return;
        input.disabled = false;
        input.min = 1;
        if (parseInt(input.value, 10) === 0) input.value = Math.min(stok, 1);
        inc.disabled = false;
        dec.disabled = false;
    }

    function disableQtyControls(variasiId) {
        const input = document.querySelector(`.qty-input[data-variasi_id="${variasiId}"]`);
        const inc = document.querySelector(`.qty-increase[data-variasi_id="${variasiId}"]`);
        const dec = document.querySelector(`.qty-decrease[data-variasi_id="${variasiId}"]`);
        if (!input) return;
        input.disabled = true;
        input.value = 0;
        inc.disabled = true;
        dec.disabled = true;
    }

    function updateSelectedSummary() {
        let totalSelected = 0;
        document.querySelectorAll('.qty-input').forEach(input => {
            const val = parseInt(input.value, 10) || 0;
            totalSelected += val;
        });
        if (totalSelected > 0) {
            stokText.textContent = "Total dipilih: " + totalSelected + " unit";
        } else {
            stokText.textContent = "<?php echo $totalStok > 0 ? "Stok Tersedia: $totalStok unit" : "Stok Habis"; ?>";
        }
    }
});

// NEW: batch add function — sends one JSON request with items[]
function addToCartBatch(productId, type, btnEl) {
    // collect items
    const qtyInputs = document.querySelectorAll('.qty-input');
    const items = [];
    qtyInputs.forEach(input => {
        const qty = parseInt(input.value, 10) || 0;
        if (qty > 0) {
            items.push({
                type: type,
                product_id: productId,
                variasi_id: parseInt(input.dataset.variasi_id, 10),
                quantity: qty
            });
        }
    });

    if (items.length === 0) {
        showError('Silakan pilih minimal 1 ukuran dan atur jumlahnya.');
        return;
    }

    // disable button & show loading
    const originalHtml = btnEl.innerHTML;
    btnEl.disabled = true;
    btnEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';

    Swal.fire({
        title: 'Menambahkan ke keranjang...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    // send single JSON request
    fetch('<?php echo BASE_URL; ?>api/cart-handler.php?action=add', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'add',
            items: items
        })
    })
    .then(res => res.json())
    .then(data => {
        Swal.close();
        btnEl.disabled = false;
        btnEl.innerHTML = originalHtml;

        if (data && data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message || 'Semua item berhasil ditambahkan ke keranjang.',
                confirmButtonText: 'Lihat Keranjang',
                showCancelButton: true,
                cancelButtonText: 'Lanjut Belanja'
            }).then(result => {
                if (result.isConfirmed) window.location.href = '<?php echo BASE_URL; ?>customer/cart.php';
                else {
                    // optionally update cart icon/count on page if you have such UI
                    // e.g. document.getElementById('cart-count').textContent = data.cart_count || '';
                }
            });
        } else {
            const msg = data && data.message ? data.message : 'Gagal menambahkan ke keranjang';
            showError(msg);
        }
    })
    .catch(err => {
        Swal.close();
        btnEl.disabled = false;
        btnEl.innerHTML = originalHtml;
        showError('Terjadi kesalahan jaringan. Coba lagi.');
        console.error(err);
    });
}

// helper showError
function showError(msg) {
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: msg
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
