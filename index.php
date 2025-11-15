<?php
$page_title = "Beranda";
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$conn = getConnection();

// =============================================
// ================ FILTER GLOBAL ==============
// =============================================
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page_kostum = isset($_GET['page_kostum']) ? intval($_GET['page_kostum']) : 1;
$page_makeup = isset($_GET['page_makeup']) ? intval($_GET['page_makeup']) : 1;
$limit = ITEMS_PER_PAGE;

// ===========================================================
// ==================== BAGIAN KOSTUM ========================
// ===========================================================
$kategori_kostum = isset($_GET['kategori_kostum']) ? intval($_GET['kategori_kostum']) : 0;

$where_kostum = ["p.status = 'aktif'"];
$params_kostum = [];
$types_kostum = "";

if (!empty($search)) {
    $where_kostum[] = "(p.nama_kostum LIKE ? OR p.deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params_kostum[] = $search_param;
    $params_kostum[] = $search_param;
    $types_kostum .= "ss";
}

if ($kategori_kostum > 0) {
    $where_kostum[] = "p.id_kategori = ?";
    $params_kostum[] = $kategori_kostum;
    $types_kostum .= "i";
}

$where_clause_kostum = implode(" AND ", $where_kostum);

$count_query_kostum = "SELECT COUNT(*) AS total FROM kostum p WHERE $where_clause_kostum";
$stmt = $conn->prepare($count_query_kostum);
if (!empty($params_kostum)) $stmt->bind_param($types_kostum, ...$params_kostum);
$stmt->execute();
$total_kostum = $stmt->get_result()->fetch_assoc()['total'];
$total_pages_kostum = ceil($total_kostum / $limit);
$stmt->close();

$offset_kostum = ($page_kostum - 1) * $limit;

$query_kostum = "SELECT 
                    p.*, 
                    k.nama_kategori, 
                    IFNULL(SUM(v.stok), 0) AS total_stok
                 FROM kostum p
                 LEFT JOIN kategori_kostum k ON p.id_kategori = k.id
                 LEFT JOIN kostum_variasi v ON v.id_kostum = p.id
                 WHERE $where_clause_kostum
                 GROUP BY p.id
                 ORDER BY p.id DESC
                 LIMIT ? OFFSET ?";
$params_kostum[] = $limit;
$params_kostum[] = $offset_kostum;
$types_kostum .= "ii";
$stmt = $conn->prepare($query_kostum);
$stmt->bind_param($types_kostum, ...$params_kostum);
$stmt->execute();
$kostum = $stmt->get_result();
$stmt->close();

$kategori_kostum_list = $conn->query("SELECT * FROM kategori_kostum ORDER BY nama_kategori ASC");

// ===========================================================
// ==================== BAGIAN MAKEUP =========================
// ===========================================================
$kategori_makeup = isset($_GET['kategori_makeup']) ? intval($_GET['kategori_makeup']) : 0;

$where_makeup = ["1=1"];
$params_makeup = [];
$types_makeup = "";

if (!empty($search)) {
    $where_makeup[] = "(l.nama_layanan LIKE ? OR l.deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params_makeup[] = $search_param;
    $params_makeup[] = $search_param;
    $types_makeup .= "ss";
}

if ($kategori_makeup > 0) {
    $where_makeup[] = "l.id_kategori_makeup = ?";
    $params_makeup[] = $kategori_makeup;
    $types_makeup .= "i";
}

$where_clause_makeup = implode(" AND ", $where_makeup);

$count_query_makeup = "SELECT COUNT(*) AS total FROM layanan_makeup l WHERE $where_clause_makeup";
$stmt = $conn->prepare($count_query_makeup);
if (!empty($params_makeup)) $stmt->bind_param($types_makeup, ...$params_makeup);
$stmt->execute();
$total_makeup = $stmt->get_result()->fetch_assoc()['total'];
$total_pages_makeup = ceil($total_makeup / $limit);
$stmt->close();

$offset_makeup = ($page_makeup - 1) * $limit;

$query_makeup = "SELECT l.*, km.nama_kategori, km.harga 
                 FROM layanan_makeup l
                 LEFT JOIN kategori_makeup km ON l.id_kategori_makeup = km.id
                 WHERE $where_clause_makeup
                 ORDER BY l.id DESC
                 LIMIT ? OFFSET ?";
$params_makeup[] = $limit;
$params_makeup[] = $offset_makeup;
$types_makeup .= "ii";
$stmt = $conn->prepare($query_makeup);
$stmt->bind_param($types_makeup, ...$params_makeup);
$stmt->execute();
$layanan_makeup = $stmt->get_result();
$stmt->close();

$kategori_makeup_list = $conn->query("SELECT * FROM kategori_makeup ORDER BY nama_kategori ASC");
?>

<!-- ================= HERO ================= -->
<section class="hero-section">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="hero-title mb-4">Sewa Kostum & Layanan Makeup</h1>
        <p class="hero-subtitle mb-8">Temukan kostum dan makeup terbaik untuk acaramu</p>

        <div class="max-w-2xl mx-auto">
            <form method="GET" action="index.php" class="flex gap-2">
                <input type="text" name="search" class="form-control-custom flex-1"
                    placeholder="Cari kostum atau layanan makeup..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-primary-custom px-6 py-3 rounded-lg">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- ================== KOSTUM ================== -->
<section id="kostumSection" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-200">
    <h2 class="text-3xl font-bold text-accent mb-8 text-center">Koleksi Kostum</h2>
    <div class="flex flex-col lg:flex-row gap-8">
        <div class="lg:w-64">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-20">
                <h3 class="text-lg font-bold text-accent mb-4"><i class="fas fa-filter mr-2"></i>Filter Kategori Kostum</h3>
                <div class="space-y-2">
                    <a href="index.php" class="block px-4 py-2 rounded-lg <?php echo $kategori_kostum == 0 ? 'bg-primary text-white' : 'text-accent hover:bg-light-bg'; ?>">Semua Kostum</a>
                    <?php while ($cat = $kategori_kostum_list->fetch_assoc()): ?>
                        <a href="?kategori_kostum=<?php echo $cat['id']; ?>" class="block px-4 py-2 rounded-lg <?php echo $kategori_kostum == $cat['id'] ? 'bg-primary text-white' : 'text-accent hover:bg-light-bg'; ?>"><?php echo $cat['nama_kategori']; ?></a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($kostum->num_rows > 0): ?>
                    <?php while ($item = $kostum->fetch_assoc()): ?>
                        <div class="card-custom">
                            <img src="<?php echo !empty($item['foto']) ? 'assets/images/kostum/' . $item['foto'] : 'https://via.placeholder.com/400x300?text=No+Image'; ?>" class="w-full h-64 object-cover rounded-t-lg">
                            <div class="p-4">
                                <span class="text-xs text-secondary font-medium"><?php echo $item['nama_kategori'] ?: 'Umum'; ?></span>
                                <h3 class="text-lg font-bold text-accent mb-2"><?php echo htmlspecialchars($item['nama_kostum']); ?></h3>
                                <p class="text-gray-600 text-sm mb-2 line-clamp-2"><?php echo htmlspecialchars(substr($item['deskripsi'], 0, 100)); ?></p>
                                <div class="text-primary font-semibold mb-3"><?php echo formatRupiah($item['harga_sewa']); ?> / hari</div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <a href="customer/detail_kostum.php?id=<?php echo $item['id']; ?>"
                                        class="flex-1 btn-secondary-custom text-center py-2 rounded-lg text-white text-sm">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>

                                    <?php if (isLoggedIn() && !isAdmin() && $item['total_stok'] > 0): ?>
                                    <?php elseif (!isLoggedIn()): ?>
                                        <a href="auth/login.php"
                                            class="flex-1 btn-primary-custom text-center py-2 rounded-lg text-white text-sm">
                                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="col-span-full text-center py-16"><i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-accent mb-2">Tidak ada kostum ditemukan</h3>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PAGINATION KOSTUM -->
            <?php if ($total_pages_kostum > 1): ?>
                <div class="flex justify-center mt-8 space-x-2">
                    <?php for ($i = 1; $i <= $total_pages_kostum; $i++): ?>
                        <a href="?page_kostum=<?php echo $i; ?>" class="px-4 py-2 rounded <?php echo $page_kostum == $i ? 'bg-primary text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ================== LAYANAN MAKEUP ================== -->
<section id="makeupSection" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-200">
    <h2 class="text-3xl font-bold text-accent mb-8 text-center">Layanan Makeup</h2>
    <div class="flex flex-col lg:flex-row gap-8">
        <div class="lg:w-64">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-20">
                <h3 class="text-lg font-bold text-accent mb-4"><i class="fas fa-filter mr-2"></i>Filter Kategori Makeup</h3>
                <div class="space-y-2">
                    <a href="index.php" class="block px-4 py-2 rounded-lg <?php echo $kategori_makeup == 0 ? 'bg-primary text-white' : 'text-accent hover:bg-light-bg'; ?>">Semua Layanan</a>
                    <?php while ($cat = $kategori_makeup_list->fetch_assoc()): ?>
                        <a href="?kategori_makeup=<?php echo $cat['id']; ?>" class="block px-4 py-2 rounded-lg <?php echo $kategori_makeup == $cat['id'] ? 'bg-primary text-white' : 'text-accent hover:bg-light-bg'; ?>"><?php echo $cat['nama_kategori']; ?></a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($layanan_makeup->num_rows > 0): ?>
                    <?php while ($m = $layanan_makeup->fetch_assoc()): ?>
                        <div class="card-custom makeup-card">
                            <?php
                            $foto_q = $conn->query("SELECT path_foto FROM foto_layanan_makeup WHERE id_layanan_makeup = {$m['id']} LIMIT 1");
                            $foto = $foto_q->num_rows > 0 ? 'assets/images/makeup/' . $foto_q->fetch_assoc()['path_foto'] : 'https://via.placeholder.com/400x300?text=No+Image';
                            ?>
                            <img src="<?php echo $foto; ?>" class="w-full h-64 object-cover rounded-t-lg">
                            <div class="p-4">
                                <span class="text-xs text-secondary font-medium"><?php echo $m['nama_kategori'] ?: 'Umum'; ?></span>
                                <h3 class="text-lg font-bold text-accent mb-2"><?php echo htmlspecialchars($m['nama_layanan']); ?></h3>
                                <p class="text-gray-600 text-sm mb-2 line-clamp-2"><?php echo htmlspecialchars(substr($m['deskripsi'], 0, 100)); ?></p>
                                <div class="text-primary font-semibold mb-3">
                                    Harga: <?php echo formatRupiah($m['harga']); ?><br>Durasi: <?php echo $m['durasi']; ?> menit
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <a href="customer/detail_makeup.php?id=<?php echo $m['id']; ?>"
                                        class="flex-1 btn-secondary-custom text-center py-2 rounded-lg text-white text-sm">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>

                                    <?php if (isLoggedIn() && !isAdmin()): ?>
                                    <?php elseif (!isLoggedIn()): ?>
                                        <a href="auth/login.php"
                                            class="flex-1 btn-primary-custom text-center py-2 rounded-lg text-white text-sm">
                                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="col-span-full text-center py-16"><i class="fas fa-magic text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-accent mb-2">Tidak ada layanan makeup ditemukan</h3>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PAGINATION MAKEUP -->
            <?php if ($total_pages_makeup > 1): ?>
                <div class="flex justify-center mt-8 space-x-2">
                    <?php for ($i = 1; $i <= $total_pages_makeup; $i++): ?>
                        <a href="?page_makeup=<?php echo $i; ?>" class="px-4 py-2 rounded <?php echo $page_makeup == $i ? 'bg-primary text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>