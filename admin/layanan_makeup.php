<?php
// admin/layanan_makeup.php (final: improved foto form + edit opens add-tab)
$page_title = "Kelola Layanan Makeup - Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

$conn = getConnection();

// image dir/url
$image_dir = __DIR__ . '/../assets/images/makeup/';
$image_url = BASE_URL . 'assets/images/makeup/';

// fallback helpers (jika belum ada)
if (!function_exists('uploadImage')) {
    function uploadImage($file, $destDir, $prefix = '') {
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0775, true)) {
                return ['success' => false, 'message' => 'Gagal membuat direktori upload'];
            }
        }
        $allowed = ['image/jpeg','image/png','image/jpg','image/webp'];
        if (!in_array($file['type'], $allowed)) {
            return ['success' => false, 'message' => 'Format file tidak didukung'];
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = rtrim($destDir, '/') . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => true, 'filename' => $filename];
        }
        return ['success' => false, 'message' => 'Gagal memindahkan file upload'];
    }
}
if (!function_exists('deleteImage')) {
    function deleteImage($filename, $destDir) {
        $path = rtrim($destDir, '/') . '/' . $filename;
        if (file_exists($path) && is_file($path)) {
            @unlink($path);
            return true;
        }
        return false;
    }
}

// flash helper
function showFlash($key = 'success_message') {
    if (isset($_SESSION[$key])) {
        $msg = addslashes($_SESSION[$key]);
        echo "<script>document.addEventListener('DOMContentLoaded', function(){ Swal.fire({ icon: 'success', title: 'Berhasil', text: '{$msg}', confirmButtonColor: '#7A6A54' }); });</script>";
        unset($_SESSION[$key]);
    }
}

$errors = [];

/* ------------------ SERVER-SIDE HANDLERS ------------------ */

/* Kategori: add, edit, delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kategori_add') {
    $nama = sanitize($_POST['nama_kategori'] ?? '');
    $harga = sanitize($_POST['harga'] ?? '');
    if (empty($nama)) $errors[] = "Nama kategori harus diisi";
    if (empty($harga)) $errors[] = "Harga kategori harus diisi";
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO kategori_makeup (nama_kategori, harga) VALUES (?, ?)");
        $stmt->bind_param("ss", $nama, $harga);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Kategori makeup berhasil ditambahkan";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-kategori');
        } else {
            $errors[] = "Gagal menambahkan kategori";
            $stmt->close();
        }
    }
}

if (isset($_GET['delete_kategori'])) {
    $id = intval($_GET['delete_kategori']);
    // cek dependency
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM layanan_makeup WHERE id_kategori_makeup = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
    if ($cnt > 0) {
        $_SESSION['success_message'] = "Tidak dapat menghapus kategori yang masih memiliki layanan";
    } else {
        $stmt = $conn->prepare("DELETE FROM kategori_makeup WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $_SESSION['success_message'] = "Kategori berhasil dihapus";
        else $_SESSION['success_message'] = "Gagal menghapus kategori";
        $stmt->close();
    }
    redirect('admin/layanan_makeup.php#tab-kategori');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kategori_edit') {
    $id = intval($_POST['kategori_id'] ?? 0);
    $nama = sanitize($_POST['nama_kategori'] ?? '');
    $harga = sanitize($_POST['harga'] ?? '');
    if (empty($nama)) $errors[] = "Nama kategori harus diisi";
    if (empty($harga)) $errors[] = "Harga kategori harus diisi";
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE kategori_makeup SET nama_kategori = ?, harga = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama, $harga, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Kategori berhasil diupdate";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-kategori');
        } else {
            $errors[] = "Gagal mengupdate kategori";
            $stmt->close();
        }
    }
}

/* Layanan makeup: add, edit, delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'layanan_add') {
    $id_kategori = intval($_POST['id_kategori_makeup'] ?? 0);
    $nama = sanitize($_POST['nama_layanan'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $durasi = intval($_POST['durasi'] ?? 0);

    if ($id_kategori <= 0) $errors[] = "Pilih kategori";
    if (empty($nama)) $errors[] = "Nama layanan harus diisi";
    if ($durasi <= 0) $errors[] = "Durasi harus lebih dari 0 menit";

    if (empty($errors)) {
        // retrieve nama kategori untuk menyimpan ke kolom kategori (opsional)
        $nama_kat = '';
        $ktr = $conn->prepare("SELECT nama_kategori FROM kategori_makeup WHERE id = ? LIMIT 1");
        if ($ktr) {
            $ktr->bind_param("i", $id_kategori);
            $ktr->execute();
            $tmp = $ktr->get_result()->fetch_assoc();
            $nama_kat = $tmp['nama_kategori'] ?? '';
            $ktr->close();
        }

        $stmt = $conn->prepare("INSERT INTO layanan_makeup (id_kategori_makeup, nama_layanan, kategori, deskripsi, durasi) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $id_kategori, $nama, $nama_kat, $deskripsi, $durasi);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Layanan berhasil ditambahkan";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-list');
        } else {
            $errors[] = "Gagal menambahkan layanan";
            $stmt->close();
        }
    }
}

if (isset($_GET['delete_layanan'])) {
    $id = intval($_GET['delete_layanan']);
    // hapus foto terkait
    $stmt = $conn->prepare("SELECT path_foto FROM foto_layanan_makeup WHERE id_layanan_makeup = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['path_foto'])) deleteImage($row['path_foto'], $image_dir);
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM foto_layanan_makeup WHERE id_layanan_makeup = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM layanan_makeup WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) $_SESSION['success_message'] = "Layanan berhasil dihapus";
    else $_SESSION['success_message'] = "Gagal menghapus layanan";
    $stmt->close();
    redirect('admin/layanan_makeup.php#tab-list');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'layanan_edit') {
    $id = intval($_POST['layanan_id'] ?? 0);
    $id_kategori = intval($_POST['id_kategori_makeup'] ?? 0);
    $nama = sanitize($_POST['nama_layanan'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $durasi = intval($_POST['durasi'] ?? 0);

    if ($id_kategori <= 0) $errors[] = "Pilih kategori";
    if (empty($nama)) $errors[] = "Nama layanan harus diisi";
    if ($durasi <= 0) $errors[] = "Durasi harus lebih dari 0 menit";

    if (empty($errors)) {
        $nama_kat = '';
        $ktr = $conn->prepare("SELECT nama_kategori FROM kategori_makeup WHERE id = ? LIMIT 1");
        if ($ktr) {
            $ktr->bind_param("i", $id_kategori);
            $ktr->execute();
            $tmp = $ktr->get_result()->fetch_assoc();
            $nama_kat = $tmp['nama_kategori'] ?? '';
            $ktr->close();
        }

        $stmt = $conn->prepare("UPDATE layanan_makeup SET id_kategori_makeup = ?, nama_layanan = ?, kategori = ?, deskripsi = ?, durasi = ? WHERE id = ?");
        $stmt->bind_param("isssii", $id_kategori, $nama, $nama_kat, $deskripsi, $durasi, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Layanan berhasil diupdate";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-list');
        } else {
            $errors[] = "Gagal mengupdate layanan";
            $stmt->close();
        }
    }
}

/* Jadwal: add, edit, delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'jadwal_add') {
    $tanggal = sanitize($_POST['tanggal'] ?? '');
    $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
    $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');

    if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) $errors[] = "Tanggal dan jam harus diisi";
    if (empty($errors)) {
        $status = 'tersedia';
        $stmt = $conn->prepare("INSERT INTO jadwal_makeup (tanggal, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $tanggal, $jam_mulai, $jam_selesai, $status);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal berhasil ditambahkan";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-jadwal');
        } else {
            $errors[] = "Gagal menambahkan jadwal";
            $stmt->close();
        }
    }
}

if (isset($_GET['delete_jadwal'])) {
    $id = intval($_GET['delete_jadwal']);
    $stmt = $conn->prepare("DELETE FROM jadwal_makeup WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) $_SESSION['success_message'] = "Jadwal berhasil dihapus";
    else $_SESSION['success_message'] = "Gagal menghapus jadwal";
    $stmt->close();
    redirect('admin/layanan_makeup.php#tab-jadwal');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'jadwal_edit') {
    $id = intval($_POST['jadwal_id'] ?? 0);
    $tanggal = sanitize($_POST['tanggal'] ?? '');
    $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
    $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');
    $status = in_array($_POST['status'] ?? 'tersedia', ['tersedia','dipesan']) ? $_POST['status'] : 'tersedia';

    if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) $errors[] = "Tanggal dan jam harus diisi";
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE jadwal_makeup SET tanggal = ?, jam_mulai = ?, jam_selesai = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $tanggal, $jam_mulai, $jam_selesai, $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal berhasil diupdate";
            $stmt->close();
            redirect('admin/layanan_makeup.php#tab-jadwal');
        } else {
            $errors[] = "Gagal mengupdate jadwal";
            $stmt->close();
        }
    }
}

/* Foto layanan: upload, delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'foto_upload') {
    $id_layanan = intval($_POST['id_layanan_foto'] ?? 0);
    if ($id_layanan <= 0) $errors[] = "Pilih layanan untuk mengupload foto";
    if (isset($_FILES['foto_layanan']) && $_FILES['foto_layanan']['error'] == UPLOAD_ERR_OK) {
        $up = uploadImage($_FILES['foto_layanan'], $image_dir, 'layanan_');
        if ($up['success']) {
            $filename = $up['filename'];
            $stmt = $conn->prepare("INSERT INTO foto_layanan_makeup (id_layanan_makeup, path_foto) VALUES (?, ?)");
            $stmt->bind_param("is", $id_layanan, $filename);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Foto berhasil diupload";
                $stmt->close();
                redirect('admin/layanan_makeup.php#tab-foto');
            } else {
                $errors[] = "Gagal menyimpan data foto";
                $stmt->close();
            }
        } else {
            $errors[] = $up['message'];
        }
    } else {
        $errors[] = "Pilih file foto untuk diupload";
    }
}

if (isset($_GET['delete_foto'])) {
    $id = intval($_GET['delete_foto']);
    $stmt = $conn->prepare("SELECT path_foto FROM foto_layanan_makeup WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['path_foto'])) deleteImage($row['path_foto'], $image_dir);

    $stmt = $conn->prepare("DELETE FROM foto_layanan_makeup WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) $_SESSION['success_message'] = "Foto berhasil dihapus";
    else $_SESSION['success_message'] = "Gagal menghapus foto";
    $stmt->close();
    redirect('admin/layanan_makeup.php#tab-foto');
}

/* ------------------ FETCH DATA FOR UI (with search & pagination) ------------------ */
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

// Count total with/without search
if ($search !== '') {
    $like = '%' . $search . '%';
    $count_sql = "SELECT COUNT(*) AS total
                  FROM layanan_makeup lm
                  LEFT JOIN kategori_makeup km ON lm.id_kategori_makeup = km.id
                  WHERE lm.nama_layanan LIKE ? OR km.nama_kategori LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $total_records = intval($res['total'] ?? 0);
    $stmt->close();

    $list_sql = "SELECT lm.*, km.nama_kategori, km.harga
                 FROM layanan_makeup lm
                 LEFT JOIN kategori_makeup km ON lm.id_kategori_makeup = km.id
                 WHERE lm.nama_layanan LIKE ? OR km.nama_kategori LIKE ?
                 ORDER BY lm.id DESC
                 LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($list_sql);
    // bind types: ssii
    $stmt->bind_param("ssii", $like, $like, $limit, $offset);
    $stmt->execute();
    $layanan_list = $stmt->get_result();
    $stmt->close();
} else {
    // no search
    $res = $conn->query("SELECT COUNT(*) AS total FROM layanan_makeup");
    $total_records = intval($res->fetch_assoc()['total'] ?? 0);

    $list_sql = "SELECT lm.*, km.nama_kategori, km.harga
                 FROM layanan_makeup lm
                 LEFT JOIN kategori_makeup km ON lm.id_kategori_makeup = km.id
                 ORDER BY lm.id DESC
                 LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($list_sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $layanan_list = $stmt->get_result();
    $stmt->close();
}

$total_pages = ($total_records > 0) ? ceil($total_records / $limit) : 1;

// Other lists used in other tabs
$kategori_q = $conn->query("SELECT * FROM kategori_makeup ORDER BY nama_kategori ASC");
$jadwal_q = $conn->query("SELECT * FROM jadwal_makeup ORDER BY tanggal DESC, jam_mulai DESC LIMIT 200");
$foto_q = $conn->query("SELECT f.*, lm.nama_layanan FROM foto_layanan_makeup f LEFT JOIN layanan_makeup lm ON f.id_layanan_makeup = lm.id ORDER BY f.id DESC LIMIT 200");

showFlash();

/* helper to build pagination links preserving query */
function buildPageUrl($pageNum) {
    $qs = [];
    if (isset($_GET['q']) && $_GET['q'] !== '') $qs['q'] = $_GET['q'];
    $qs['page'] = $pageNum;
    $query = http_build_query($qs);
    // ensure we go to the list tab
    return 'layanan_makeup.php?' . $query . '#tab-list';
}
?>

<div class="flex">
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 bg-lighter-bg min-h-screen lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-20 lg:pb-16">
            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Kelola Layanan Makeup</h1>
                <p class="text-gray-600 mt-2 text-sm">CRUD untuk layanan, kategori, jadwal, dan foto layanan</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                    <?php foreach ($errors as $e): ?>
                        <p class="text-sm"><?php echo htmlspecialchars($e); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <nav class="flex gap-2">
                    <button class="tab-btn px-4 py-2 rounded-md bg-primary text-white"  data-tab="tab-list">Daftar Layanan</button>
                    <button class="tab-btn px-4 py-2 rounded-md bg-light-bg text-accent" data-tab="tab-add">Tambah Layanan</button>
                    <button class="tab-btn px-4 py-2 rounded-md bg-light-bg text-accent" data-tab="tab-kategori">Kategori</button>
                    <button class="tab-btn px-4 py-2 rounded-md bg-light-bg text-accent" data-tab="tab-jadwal">Jadwal</button>
                    <button class="tab-btn px-4 py-2 rounded-md bg-light-bg text-accent" data-tab="tab-foto">Foto Layanan</button>
                </nav>
            </div>

            <!-- TAB: LIST -->
            <div id="tab-list" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <form method="GET" action="layanan_makeup.php" class="flex items-center gap-2">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari layanan..." class="form-control-custom w-full">
                        <button class="btn-primary-custom px-4 py-2">Cari</button>
                    </form>
                    <div class="text-sm text-gray-600">Menampilkan <?php echo ($total_records==0?0:($offset+1)); ?> - <?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?></div>
                </div>

                <?php if ($layanan_list && $layanan_list->num_rows > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php while ($l = $layanan_list->fetch_assoc()): ?>
                            <div class="border p-3 rounded-lg">
                                <h4 class="font-semibold text-accent"><?php echo htmlspecialchars($l['nama_layanan']); ?></h4>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($l['nama_kategori'] ?? '-'); ?></p>
                                <p class="text-sm font-bold text-primary mt-2"><?php echo formatRupiah($l['harga'] ?? 0); ?></p>
                                <p class="text-sm text-gray-700 mt-2"><?php echo htmlspecialchars(mb_strimwidth($l['deskripsi'] ?? '', 0, 120, '...')); ?></p>
                                <div class="flex items-center gap-2 mt-3">
                                    <!-- Edit opens add-tab and loads edit param -->
                                    <a href="?edit=<?php echo $l['id']; ?>#tab-add" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-edit mr-1"></i>Edit</a>
                                    <a href="?delete_layanan=<?php echo $l['id']; ?>" onclick="return confirm('Hapus layanan ini?')" class="px-3 py-2 bg-red-600 text-white rounded text-sm"><i class="fas fa-trash mr-1"></i>Hapus</a>

                                    <!-- Foto button: use data attribute, handled by JS -->
                                    <button type="button" class="px-3 py-2 bg-gray-200 rounded text-sm text-accent btn-open-foto" data-layanan-id="<?php echo $l['id']; ?>" data-layanan-name="<?php echo htmlspecialchars(addslashes($l['nama_layanan'])); ?>">
                                        <i class="fas fa-image mr-1"></i>Foto
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex items-center justify-center">
                        <nav class="inline-flex items-center space-x-2">
                            <!-- Prev -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildPageUrl($page-1); ?>" class="px-3 py-1 bg-white border rounded">Prev</a>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400">Prev</span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($start_page > 1) {
                                echo '<a href="'.buildPageUrl(1).'" class="px-3 py-1 bg-white border rounded">1</a>';
                                if ($start_page > 2) echo '<span class="px-2">...</span>';
                            }
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1 bg-primary text-white rounded"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo buildPageUrl($i); ?>" class="px-3 py-1 bg-white border rounded"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor;
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<span class="px-2">...</span>';
                                echo '<a href="'.buildPageUrl($total_pages).'" class="px-3 py-1 bg-white border rounded">'.$total_pages.'</a>';
                            }
                            ?>

                            <!-- Next -->
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo buildPageUrl($page+1); ?>" class="px-3 py-1 bg-white border rounded">Next</a>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400">Next</span>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">Tidak ada layanan</div>
                <?php endif; ?>
            </div>

            <!-- TAB: ADD / EDIT LAYANAN -->
            <div id="tab-add" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display:none;">
                <?php
                $edit_mode = false;
                $edit_data = null;
                if (isset($_GET['edit'])) {
                    $eid = intval($_GET['edit']);
                    $stmt = $conn->prepare("SELECT * FROM layanan_makeup WHERE id = ? LIMIT 1");
                    $stmt->bind_param("i", $eid);
                    $stmt->execute();
                    $edit_data = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($edit_data) $edit_mode = true;
                }
                ?>
                <h3 class="text-lg font-bold mb-4"><?php echo $edit_mode ? 'Edit Layanan' : 'Tambah Layanan Baru'; ?></h3>

                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_mode ? 'layanan_edit' : 'layanan_add'; ?>">
                    <?php if ($edit_mode): ?><input type="hidden" name="layanan_id" value="<?php echo $edit_data['id']; ?>"><?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm">Kategori *</label>
                            <select name="id_kategori_makeup" required class="form-control-custom w-full w-full">
                                <option value="">Pilih Kategori</option>
                                <?php
                                $kategori_q->data_seek(0);
                                while ($kc = $kategori_q->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $kc['id']; ?>" <?php echo ($edit_mode && $edit_data['id_kategori_makeup'] == $kc['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kc['nama_kategori']); ?> (<?php echo htmlspecialchars($kc['harga']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block mb-1 text-sm">Nama Layanan *</label>
                            <input type="text" name="nama_layanan" required class="form-control-custom w-full w-full" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nama_layanan']) : ''; ?>">
                        </div>

                        <div>
                            <label class="block mb-1 text-sm">Durasi (menit) *</label>
                            <input type="number" name="durasi" required min="1" class="form-control-custom w-full" value="<?php echo $edit_mode ? intval($edit_data['durasi']) : 60; ?>">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 text-sm">Deskripsi</label>
                            <textarea name="deskripsi" rows="4" class="form-control-custom w-full"><?php echo $edit_mode ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn-primary-custom px-5 py-2"><?php echo $edit_mode ? 'Update Layanan' : 'Tambah Layanan'; ?></button>
                        <?php if ($edit_mode): ?><a href="layanan_makeup.php#tab-list" class="btn-secondary-custom px-5 py-2">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- TAB: KATEGORI -->
            <div id="tab-kategori" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display:none;">
                <h3 class="text-lg font-bold mb-4">Kelola Kategori Makeup</h3>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    <input type="hidden" name="action" value="kategori_add">
                    <div>
                        <input type="text" name="nama_kategori" placeholder="Nama kategori" class="form-control-custom w-full">
                    </div>
                    <div>
                        <input type="text" name="harga" placeholder="Harga (contoh: 150000)" class="form-control-custom w-full">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary-custom px-4 py-2">Tambah Kategori</button>
                    </div>
                </form>

                <?php if ($kategori_q && $kategori_q->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php
                        $kategori_q->data_seek(0);
                        while ($c = $kategori_q->fetch_assoc()):
                        ?>
                        <div class="flex items-center justify-between p-3 border rounded">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($c['nama_kategori']); ?></div>
                                <div class="text-sm text-gray-600">Harga: <?php echo htmlspecialchars($c['harga']); ?></div>
                            </div>
                            <div class="flex gap-2">
                                <button class="px-3 py-1 bg-blue-600 text-white rounded text-sm" onclick="openEditKategori(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['nama_kategori'])); ?>', '<?php echo htmlspecialchars(addslashes($c['harga'])); ?>')">Edit</button>
                                <a href="?delete_kategori=<?php echo $c['id']; ?>" onclick="return confirm('Hapus kategori ini?')" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Hapus</a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500 py-6">Belum ada kategori</div>
                <?php endif; ?>
            </div>

            <!-- TAB: JADWAL -->
            <div id="tab-jadwal" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display:none;">
                <h3 class="text-lg font-bold mb-4">Kelola Jadwal Makeup</h3>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                    <input type="hidden" name="action" value="jadwal_add">
                    <div>
                        <label class="block mb-1 text-sm">Tanggal *</label>
                        <input type="date" name="tanggal" class="form-control-custom w-full">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm">Jam Mulai *</label>
                        <input type="time" name="jam_mulai" class="form-control-custom w-full">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm">Jam Selesai *</label>
                        <input type="time" name="jam_selesai" class="form-control-custom w-full">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary-custom px-4 py-2">Tambah Jadwal</button>
                    </div>
                </form>

                <?php if ($jadwal_q && $jadwal_q->num_rows > 0): ?>
                    <div class="space-y-2">
                        <?php
                        $jadwal_q->data_seek(0);
                        while ($j = $jadwal_q->fetch_assoc()):
                        ?>
                        <div class="flex items-center justify-between p-3 border rounded">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($j['tanggal']); ?> <?php echo htmlspecialchars($j['jam_mulai'] .' - '. $j['jam_selesai']); ?></div>
                                <div class="text-sm text-gray-600">Status: <?php echo htmlspecialchars($j['status']); ?></div>
                            </div>
                            <div class="flex gap-2">
                                <button class="px-3 py-1 bg-yellow-500 text-white rounded text-sm btn-edit-jadwal" data-id="<?php echo $j['id']; ?>" data-tanggal="<?php echo $j['tanggal']; ?>" data-jam_mulai="<?php echo $j['jam_mulai']; ?>" data-jam_selesai="<?php echo $j['jam_selesai']; ?>" data-status="<?php echo $j['status']; ?>">Edit</button>
                                <a href="?delete_jadwal=<?php echo $j['id']; ?>" onclick="return confirm('Hapus jadwal ini?')" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Hapus</a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500 py-6">Belum ada jadwal</div>
                <?php endif; ?>
            </div>

            <!-- TAB: FOTO LAYANAN (improved UI, select & file sejajar) -->
<div id="tab-foto" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display:none;">
    <h3 class="text-lg font-bold mb-2">Kelola Foto Layanan</h3>

    <div class="bg-white p-2 rounded mb-4">
        <?php
        // count per layanan
        $counts = $conn->query("SELECT id_layanan_makeup, COUNT(*) as cnt FROM foto_layanan_makeup GROUP BY id_layanan_makeup");
        $map = [];
        while ($row = $counts->fetch_assoc()) $map[intval($row['id_layanan_makeup'])] = intval($row['cnt']);
        $kk = $conn->query("SELECT id, nama_layanan FROM layanan_makeup ORDER BY nama_layanan");
        if ($kk && $kk->num_rows>0):
        ?>
            <ul class="text-sm text-gray-700 space-y-2">
                <?php while ($r = $kk->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($r['nama_layanan']); ?>: <span class="font-medium"><?php echo $map[intval($r['id'])] ?? 0; ?></span></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <div class="text-gray-500">Belum ada layanan</div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 rounded border mb-6">
        <form method="POST" enctype="multipart/form-data" id="formUploadFoto">
            <input type="hidden" name="action" value="foto_upload">

            <!-- GRID: select layanan & file sejajar pada layar md+ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                <div>
                    <label class="block mb-1 text-sm">Pilih Layanan *</label>
                    <select name="id_layanan_foto" id="id_layanan_foto" required class="form-control-custom w-full">
                        <option value="">-- Pilih Layanan --</option>
                        <?php
                        $kk = $conn->query("SELECT id, nama_layanan FROM layanan_makeup ORDER BY nama_layanan");
                        while ($r = $kk->fetch_assoc()):
                        ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nama_layanan']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div id="selected_layanan_name" class="text-sm text-gray-600 mt-2">Nama layanan: <span class="font-medium">-</span></div>
                </div>

                <div>
                    <label class="block mb-1 text-sm">Pilih Foto *</label>
                    <input type="file" name="foto_layanan" id="foto_layanan" accept="image/*" class="" required>
                    <p class="text-xs text-gray-500 mt-2">Maks ukuran file sesuai konfigurasi server. Format: JPG/PNG/WEBP.</p>
                </div>
            </div>

            <!-- preview & actions -->
            <div class="mt-4 flex flex-col md:flex-row items-start md:items-center gap-4">
                <div class="w-28 h-28 bg-gray-100 rounded overflow-hidden flex items-center justify-center" id="preview_container">
                    <span class="text-xs text-gray-400">Preview</span>
                </div>

                <div class="flex-1">
                    <p class="text-xs text-gray-500 mb-2">Preview file sebelum upload. Pastikan gambar sesuai.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary-custom px-4 py-2">Upload Foto</button>
                        <button type="button" id="reset_preview" class="btn-secondary-custom px-4 py-2">Reset</button>
                        <a href="#tab-foto" class="px-4 py-2 text-sm text-gray-700">Bantuan & aturan upload</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Foto grid -->
    <?php if ($foto_q && $foto_q->num_rows > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            $foto_q->data_seek(0);
            while ($f = $foto_q->fetch_assoc()):
            ?>
            <div class="border rounded overflow-hidden">
                <div class="h-48 bg-gray-100 flex items-center justify-center overflow-hidden">
                    <?php if (!empty($f['path_foto'])): ?>
                        <img src="<?php echo $image_url . htmlspecialchars($f['path_foto']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="text-gray-300"><i class="fas fa-image text-3xl"></i></div>
                    <?php endif; ?>
                </div>
                <div class="p-3 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-sm"><?php echo htmlspecialchars($f['nama_layanan'] ?? '-'); ?></div>
                    </div>
                    <div class="flex gap-2">
                        <a href="?delete_foto=<?php echo $f['id']; ?>" onclick="return confirm('Hapus foto ini?')" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Hapus</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-8 text-gray-500">Belum ada foto layanan</div>
    <?php endif; ?>
</div>

        </div>
    </main>
</div>

<!-- Modal Edit Kategori -->
<div id="modal-edit-kategori" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="font-bold mb-4">Edit Kategori</h3>
        <form id="formEditKategori" method="POST">
            <input type="hidden" name="action" value="kategori_edit">
            <input type="hidden" name="kategori_id" id="kategori_id">
            <div class="mb-3">
                <label class="block mb-1 text-sm">Nama Kategori</label>
                <input type="text" name="nama_kategori" id="kategori_nama" class="form-control-custom w-full">
            </div>
            <div class="mb-3">
                <label class="block mb-1 text-sm">Harga</label>
                <input type="text" name="harga" id="kategori_harga" class="form-control-custom w-full">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" class="btn-secondary-custom px-4 py-2" onclick="closeKategoriModal()">Batal</button>
                <button type="submit" class="btn-primary-custom px-4 py-2">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Jadwal -->
<div id="modal-edit-jadwal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="font-bold mb-4">Edit Jadwal</h3>
        <form id="formEditJadwal" method="POST">
            <input type="hidden" name="action" value="jadwal_edit">
            <input type="hidden" name="jadwal_id" id="jadwal_id">
            <div class="mb-3">
                <label class="block mb-1 text-sm">Tanggal</label>
                <input type="date" name="tanggal" id="jadwal_tanggal" class="form-control-custom w-full">
            </div>
            <div class="mb-3">
                <label class="block mb-1 text-sm">Jam Mulai</label>
                <input type="time" name="jam_mulai" id="jadwal_jam_mulai" class="form-control-custom w-full">
            </div>
            <div class="mb-3">
                <label class="block mb-1 text-sm">Jam Selesai</label>
                <input type="time" name="jam_selesai" id="jadwal_jam_selesai" class="form-control-custom w-full">
            </div>
            <div class="mb-3">
                <label class="block mb-1 text-sm">Status</label>
                <select name="status" id="jadwal_status" class="form-control-custom w-full">
                    <option value="tersedia">tersedia</option>
                    <option value="dipesan">dipesan</option>
                </select>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" class="btn-secondary-custom px-4 py-2" onclick="closeJadwalModal()">Batal</button>
                <button type="submit" class="btn-primary-custom px-4 py-2">Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
.tab-btn { cursor: pointer; }
.tab-content { display: block; }
.btn-primary-custom { background:#7A6A54; color:white; border-radius:6px; }
.btn-secondary-custom { background:#f3f4f6; color:#111827; border-radius:6px; }
</style>

<script>
    // Tabs init
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('bg-primary'); b.classList.add('bg-light-bg'); b.classList.remove('text-white'); });
            btn.classList.add('bg-primary'); btn.classList.remove('bg-light-bg'); btn.classList.add('text-white');
            const tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            document.getElementById(tab).style.display = 'block';
            window.location.hash = tab;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // open tab from hash if present
    const hash = window.location.hash.replace('#','');
    if (hash && document.querySelector('[data-tab="tab-'+hash+'"]')) {
        document.querySelector('[data-tab="tab-'+hash+'"]').click();
    } else {
        document.querySelector('[data-tab="tab-list"]').click();
    }

    // If page loaded with ?edit=... then open add tab automatically (and keep query param)
    <?php if (isset($_GET['edit']) && intval($_GET['edit'])>0): ?>
        document.addEventListener('DOMContentLoaded', function(){
            const btn = document.querySelector('[data-tab="tab-add"]');
            if (btn) btn.click();
        });
    <?php endif; ?>

    // Foto buttons: open Foto tab and set select value + nama layanan
    document.querySelectorAll('.btn-open-foto').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-layanan-id');
            const name = this.getAttribute('data-layanan-name') || '';
            // open foto tab
            const fotoBtn = document.querySelector('[data-tab="tab-foto"]');
            if (fotoBtn) fotoBtn.click();
            // set select value after a short delay to ensure tab displayed
            setTimeout(() => {
                const sel = document.getElementById('id_layanan_foto');
                if (sel) {
                    sel.value = id;
                    sel.classList.add('has-value');
                    // show name
                    const nameEl = document.getElementById('selected_layanan_name');
                    if (nameEl) nameEl.querySelector('span').textContent = name || sel.options[sel.selectedIndex].text;
                }
            }, 150);
        });
    });

    // update selected layanan name on select change
    const selLayanan = document.getElementById('id_layanan_foto');
    if (selLayanan) {
        selLayanan.addEventListener('change', function(){
            const nameEl = document.getElementById('selected_layanan_name');
            if (nameEl) nameEl.querySelector('span').textContent = this.options[this.selectedIndex].text || '-';
        });
    }

    // preview upload foto
    const fotoInput = document.getElementById('foto_layanan');
    const previewContainer = document.getElementById('preview_container');
    const resetBtn = document.getElementById('reset_preview');
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e){
            const file = this.files && this.files[0];
            if (!file) {
                previewContainer.innerHTML = '<span class="text-xs text-gray-400">Preview</span>';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(evt){
                previewContainer.innerHTML = '<img src="'+evt.target.result+'" class="w-full h-full object-cover">';
            };
            reader.readAsDataURL(file);
        });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', function(){
            if (fotoInput) {
                fotoInput.value = '';
                previewContainer.innerHTML = '<span class="text-xs text-gray-400">Preview</span>';
            }
        });
    }

    // Edit Kategori modal
    function openEditKategori(id, nama, harga) {
        document.getElementById('modal-edit-kategori').classList.remove('hidden');
        document.getElementById('kategori_id').value = id;
        document.getElementById('kategori_nama').value = nama;
        document.getElementById('kategori_harga').value = harga;
    }
    function closeKategoriModal() {
        document.getElementById('modal-edit-kategori').classList.add('hidden');
    }

    // Edit Jadwal modal
    document.querySelectorAll('.btn-edit-jadwal').forEach(b => {
        b.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const tanggal = this.getAttribute('data-tanggal');
            const jam_mulai = this.getAttribute('data-jam_mulai');
            const jam_selesai = this.getAttribute('data-jam_selesai');
            const status = this.getAttribute('data-status');

            document.getElementById('jadwal_id').value = id;
            document.getElementById('jadwal_tanggal').value = tanggal;
            document.getElementById('jadwal_jam_mulai').value = jam_mulai;
            document.getElementById('jadwal_jam_selesai').value = jam_selesai;
            document.getElementById('jadwal_status').value = status;

            document.getElementById('modal-edit-jadwal').classList.remove('hidden');
        });
    });

    function closeJadwalModal() {
        document.getElementById('modal-edit-jadwal').classList.add('hidden');
    }
</script>


<?php
require_once '../includes/footer.php';
?>
