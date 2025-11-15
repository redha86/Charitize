<?php
$page_title = "Detail Layanan Makeup";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id == 0) {
    redirect('../index.php');
}

$conn = getConnection();

// Ambil data layanan makeup
$query = "SELECT lm.*, km.nama_kategori, km.harga 
          FROM layanan_makeup lm
          LEFT JOIN kategori_makeup km ON lm.id_kategori_makeup = km.id
          WHERE lm.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('../index.php');
}

$makeup = $result->fetch_assoc();
$stmt->close();

// Ambil semua foto
$fotoQuery = "SELECT path_foto FROM foto_layanan_makeup WHERE id_layanan_makeup = ?";
$stmt = $conn->prepare($fotoQuery);
$stmt->bind_param("i", $id);
$stmt->execute();
$fotos = $stmt->get_result();
$stmt->close();

$fotoList = [];
while ($f = $fotos->fetch_assoc()) {
    $fotoList[] = '../assets/images/makeup/' . $f['path_foto'];
}
if (count($fotoList) == 0) {
    $fotoList[] = 'https://via.placeholder.com/600x600?text=No+Image';
}

// Ambil jadwal tersedia (tidak terikat layanan)
$jadwalQuery = "SELECT * FROM jadwal_makeup ORDER BY tanggal, jam_mulai";
$jadwal = $conn->query($jadwalQuery);

// Ambil layanan terkait
$relatedQuery = "SELECT * FROM layanan_makeup WHERE id_kategori_makeup = ? AND id != ? LIMIT 4";
$stmt = $conn->prepare($relatedQuery);
$stmt->bind_param("ii", $makeup['id_kategori_makeup'], $id);
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
            <li class="text-accent font-medium"><?php echo htmlspecialchars($makeup['nama_kategori']); ?></li>
        </ol>
    </nav>

    <!-- Detail Layanan -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white rounded-lg shadow-lg p-8 mb-8">
        <!-- Gambar -->
        <div class="relative">
            <div class="relative overflow-hidden rounded-lg shadow-md" style="height: 500px;">
                <img id="mainImage" 
                     src="<?php echo $fotoList[0]; ?>" 
                     alt="<?php echo htmlspecialchars($makeup['nama_layanan']); ?>"
                     class="w-full h-full object-cover transition duration-300 ease-in-out rounded-lg">

                <?php if (count($fotoList) > 1): ?>
                    <button id="prevBtn" 
                            class="absolute top-1/2 left-3 transform -translate-y-1/2 bg-gray-800 bg-opacity-50 text-white p-3 rounded-full hover:bg-opacity-80 transition">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button id="nextBtn" 
                            class="absolute top-1/2 right-3 transform -translate-y-1/2 bg-gray-800 bg-opacity-50 text-white p-3 rounded-full hover:bg-opacity-80 transition">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Thumbnail -->
            <?php if (count($fotoList) > 1): ?>
                <div class="grid grid-cols-4 gap-3 mt-4">
                    <?php foreach ($fotoList as $index => $f): ?>
                        <img src="<?php echo $f; ?>" 
                             alt="Foto <?php echo $index + 1; ?>"
                             class="thumbnail w-full h-24 object-cover rounded-lg cursor-pointer border-2 border-transparent hover:border-primary transition"
                             onclick="setImage(<?php echo $index; ?>)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div>
            <span class="inline-block bg-secondary text-white px-4 py-1 rounded-full text-sm font-medium mb-4">
                <?php echo htmlspecialchars($makeup['nama_kategori']); ?>
            </span>

            <h1 class="text-3xl font-bold text-accent mb-4">
                <?php echo htmlspecialchars($makeup['nama_layanan']); ?>
            </h1>

            <div class="mb-6">
                <span class="text-4xl font-bold text-primary">
                    <?php echo formatRupiah($makeup['harga']); ?>
                </span>
                <p class="text-gray-500 mt-1">Durasi: <?php echo intval($makeup['durasi']); ?> menit</p>
            </div>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-accent mb-3">Deskripsi Layanan</h3>
                <p class="text-gray-700 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($makeup['deskripsi'])); ?>
                </p>
            </div>

            <!-- Pilih Jadwal (Horizontal Scroll) -->
            <?php if ($jadwal->num_rows > 0): ?>
                <div class="mb-6">
                    <h3 class="block text-accent font-bold mb-3">Pilih Jadwal Makeup</h3>

                    <!-- Container with horizontal scroll -->
                    <div class="overflow-x-auto -mx-3 py-2" style="-webkit-overflow-scrolling: touch;">
                        <div class="flex space-x-3 px-3">
                            <?php 
                            // rewind result pointer if needed (depends on mysqli config)
                            // but here we assume $jadwal hasn't been consumed yet
                            while ($j = $jadwal->fetch_assoc()): 
                                $isDisabled = $j['status'] === 'dipesan';
                            ?>
                                <div class="jadwal-box flex-shrink-0 w-40 sm:w-44 md:w-48 border-2 rounded-lg p-3 text-center cursor-pointer transition <?php echo $isDisabled ? 'border-gray-300 bg-gray-100 text-gray-400 disabled' : 'border-gray-300 hover:border-primary hover:bg-light-bg'; ?>" 
                                     data-id="<?php echo $j['id']; ?>">
                                    <p class="font-bold text-accent"><?php echo date('d M Y', strtotime($j['tanggal'])); ?></p>
                                    <p class="text-sm"><?php echo substr($j['jam_mulai'], 0, 5) . " - " . substr($j['jam_selesai'], 0, 5); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <input type="hidden" id="jadwal_id" value="">
                </div>
            <?php else: ?>
                <p class="text-red-600 font-medium">Tidak ada jadwal tersedia saat ini.</p>
            <?php endif; ?>

            <div class="flex gap-4 mt-6">
                <?php if (isLoggedIn() && !isAdmin()): ?>
                    <button onclick="addToCart(<?php echo $id; ?>, 'makeup')" 
                            class="flex-1 btn-primary-custom py-3 rounded-lg text-lg font-medium">
                        <i class="fas fa-cart-plus mr-2"></i>Tambah ke Keranjang
                    </button>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" 
                       class="flex-1 btn-primary-custom text-center py-3 rounded-lg text-lg font-medium">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login untuk Memesan
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Layanan Terkait -->
    <?php if ($related->num_rows > 0): ?>
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-accent mb-6">Layanan Makeup Terkait</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($r = $related->fetch_assoc()): ?>
                    <div class="card-custom product-card">
                        <?php
                        $fotoRel = $conn->query("SELECT path_foto FROM foto_layanan_makeup WHERE id_layanan_makeup = {$r['id']} LIMIT 1")->fetch_assoc();
                        $fotoPath = $fotoRel ? '../assets/images/makeup/'.$fotoRel['path_foto'] : 'https://via.placeholder.com/400x300?text=No+Image';
                        ?>
                        <img src="<?php echo $fotoPath; ?>" 
                             alt="<?php echo htmlspecialchars($r['nama_layanan']); ?>"
                             class="product-image">
                        <div class="product-body">
                            <h3 class="product-title">
                                <?php echo htmlspecialchars($r['nama_layanan']); ?>
                            </h3>
                            <div class="product-price mb-4">
                                <?php echo formatRupiah($r['harga']); ?>
                            </div>
                            <a href="detail_makeup.php?id=<?php echo $r['id']; ?>" 
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
    let currentIndex = 0;
    const images = <?php echo json_encode($fotoList); ?>;
    const mainImage = document.getElementById('mainImage');

    function setImage(index) {
        currentIndex = index;
        mainImage.src = images[index];
    }

    function nextImage() {
        currentIndex = (currentIndex + 1) % images.length;
        mainImage.src = images[currentIndex];
    }

    function prevImage() {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        mainImage.src = images[currentIndex];
    }

    if (document.getElementById('nextBtn')) {
        document.getElementById('nextBtn').addEventListener('click', nextImage);
        document.getElementById('prevBtn').addEventListener('click', prevImage);
    }

    // Pilih jadwal
    const jadwalBoxes = document.querySelectorAll('.jadwal-box');
    let selectedJadwal = null;

    jadwalBoxes.forEach(box => {
        box.addEventListener('click', () => {
            if (box.classList.contains('disabled')) return;

            jadwalBoxes.forEach(b => b.classList.remove('border-primary', 'bg-light-bg'));
            box.classList.add('border-primary', 'bg-light-bg');
            selectedJadwal = box.dataset.id;
            document.getElementById('jadwal_id').value = selectedJadwal;

            // Jika kotak berada di luar viewport horizontal, scroll supaya terlihat (opsional UX)
            // Cari parent scroll container
            const scrollParent = box.closest('.overflow-x-auto');
            if (scrollParent) {
                const boxRect = box.getBoundingClientRect();
                const parentRect = scrollParent.getBoundingClientRect();
                if (boxRect.left < parentRect.left || boxRect.right > parentRect.right) {
                    // scroll agar kotak berada di tengah container
                    const offset = boxRect.left - parentRect.left - (parentRect.width / 2) + (boxRect.width / 2);
                    scrollParent.scrollBy({ left: offset, behavior: 'smooth' });
                }
            }
        });
    });

    window.addToCart = function(id, type) {
        const jadwal_id = document.getElementById('jadwal_id').value;
        if (!jadwal_id) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Silakan pilih jadwal terlebih dahulu' });
            return;
        }

        Swal.fire({
            title: 'Menambahkan ke keranjang...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('<?php echo BASE_URL; ?>api/cart-handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=add&type=${type}&product_id=${id}&jadwal_id=${jadwal_id}`
        })
        .then(res => res.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message,
                    confirmButtonText: 'Lihat Keranjang',
                    showCancelButton: true,
                    cancelButtonText: 'Lanjut Belanja'
                }).then(result => {
                    if (result.isConfirmed) window.location.href = '<?php echo BASE_URL; ?>customer/cart.php';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menambahkan ke keranjang' }));
    };

    window.setImage = setImage;
});
</script>

<?php require_once '../includes/footer.php'; ?>
