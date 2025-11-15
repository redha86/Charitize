<?php
// admin/kostum.php
$page_title = "Kelola Kostum - Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

$conn = getConnection();

// local paths (tidak mendefinisikan konstanta global baru)
$image_dir = __DIR__ . '/../assets/images/kostum/';        // server path untuk menyimpan gambar
$image_url = BASE_URL . 'assets/images/kostum/';           // url base untuk menampilkan gambar

// fallback helper: uploadImage() dan deleteImage() jika belum tersedia di project Anda
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

// helper flash
function showFlash($key = 'success_message') {
    if (isset($_SESSION[$key])) {
        $msg = addslashes($_SESSION[$key]);
        echo "<script>document.addEventListener('DOMContentLoaded', function(){ Swal.fire({ icon: 'success', title: 'Berhasil', text: '{$msg}', confirmButtonColor: '#7A6A54' }); });</script>";
        unset($_SESSION[$key]);
    }
}

// ======= PROCESS REQUESTS (KATEGORI / KOSTUM / VARIASI) =======
$errors = [];

// --- tambah kategori ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kategori_add') {
    $nama = sanitize($_POST['nama_kategori'] ?? '');
    if (empty($nama)) $errors[] = 'Nama kategori harus diisi';
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO kategori_kostum (nama_kategori) VALUES (?)");
        $stmt->bind_param("s", $nama);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Kategori berhasil ditambahkan';
            $stmt->close();
            redirect('admin/kostum.php#tab-kategori');
        } else {
            $errors[] = 'Gagal menambahkan kategori';
            $stmt->close();
        }
    }
}

// --- edit kategori ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kategori_edit') {
    $id = intval($_POST['kategori_id'] ?? 0);
    $nama = sanitize($_POST['nama_kategori'] ?? '');
    if (empty($nama)) $errors[] = 'Nama kategori harus diisi';
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE kategori_kostum SET nama_kategori = ? WHERE id = ?");
        $stmt->bind_param("si", $nama, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Kategori berhasil diupdate';
            $stmt->close();
            redirect('admin/kostum.php#tab-kategori');
        } else {
            $errors[] = 'Gagal mengupdate kategori';
            $stmt->close();
        }
    }
}

// --- delete kategori ---
if (isset($_GET['delete_kategori'])) {
    $id = intval($_GET['delete_kategori']);
    // cek apakah ada kostum memakai kategori ini
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM kostum WHERE id_kategori = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
    if ($cnt > 0) {
        $_SESSION['success_message'] = "Tidak bisa menghapus kategori: terdapat kostum pada kategori ini.";
    } else {
        $stmt = $conn->prepare("DELETE FROM kategori_kostum WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Kategori berhasil dihapus';
        } else {
            $_SESSION['success_message'] = 'Gagal menghapus kategori';
        }
        $stmt->close();
    }
    redirect('admin/kostum.php#tab-kategori');
}

// --- tambah kostum ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kostum_add') {
    $nama = sanitize($_POST['nama_kostum'] ?? '');
    $id_kategori = intval($_POST['id_kategori'] ?? 0);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $harga_sewa = floatval($_POST['harga_sewa'] ?? 0);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif','nonaktif']) ? $_POST['status'] : 'nonaktif';

    if (empty($nama)) $errors[] = 'Nama kostum harus diisi';
    if ($id_kategori <= 0) $errors[] = 'Kategori harus dipilih';
    if ($harga_sewa <= 0) $errors[] = 'Harga sewa harus lebih dari 0';

    $foto_name = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $up = uploadImage($_FILES['foto'], $image_dir, 'kostum_');
        if ($up['success']) $foto_name = $up['filename'];
        else $errors[] = $up['message'];
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO kostum (id_kategori, nama_kostum, deskripsi, harga_sewa, foto, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $id_kategori, $nama, $deskripsi, $harga_sewa, $foto_name, $status);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Kostum berhasil ditambahkan';
            $stmt->close();
            redirect('admin/kostum.php');
        } else {
            $errors[] = 'Gagal menambahkan kostum';
            $stmt->close();
        }
    }
}

// --- edit kostum ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kostum_edit') {
    $id = intval($_POST['kostum_id'] ?? 0);
    $nama = sanitize($_POST['nama_kostum'] ?? '');
    $id_kategori = intval($_POST['id_kategori'] ?? 0);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $harga_sewa = floatval($_POST['harga_sewa'] ?? 0);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif','nonaktif']) ? $_POST['status'] : 'nonaktif';

    if (empty($nama)) $errors[] = 'Nama kostum harus diisi';
    if ($id_kategori <= 0) $errors[] = 'Kategori harus dipilih';
    if ($harga_sewa <= 0) $errors[] = 'Harga sewa harus lebih dari 0';

    // ambil foto existing
    $stmt = $conn->prepare("SELECT foto FROM kostum WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $exist = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $foto_name = $exist['foto'] ?? '';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        // hapus existing file
        if (!empty($foto_name)) deleteImage($foto_name, $image_dir);
        $up = uploadImage($_FILES['foto'], $image_dir, 'kostum_');
        if ($up['success']) $foto_name = $up['filename'];
        else $errors[] = $up['message'];
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE kostum SET id_kategori = ?, nama_kostum = ?, deskripsi = ?, harga_sewa = ?, foto = ?, status = ? WHERE id = ?");
        $stmt->bind_param("issdssi", $id_kategori, $nama, $deskripsi, $harga_sewa, $foto_name, $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Kostum berhasil diupdate';
            $stmt->close();
            redirect('admin/kostum.php');
        } else {
            $errors[] = 'Gagal mengupdate kostum';
            $stmt->close();
        }
    }
}

// --- delete kostum (dan variasi) ---
if (isset($_GET['delete_kostum'])) {
    $id = intval($_GET['delete_kostum']);

    // ambil foto
    $stmt = $conn->prepare("SELECT foto FROM kostum WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // hapus variasi
    $stmt = $conn->prepare("DELETE FROM kostum_variasi WHERE id_kostum = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // hapus kostum
    $stmt = $conn->prepare("DELETE FROM kostum WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        if (!empty($r['foto'])) deleteImage($r['foto'], $image_dir);
        $_SESSION['success_message'] = 'Kostum dan variasinya berhasil dihapus';
    } else {
        $_SESSION['success_message'] = 'Gagal menghapus kostum';
    }
    $stmt->close();
    redirect('admin/kostum.php');
}

// --- tambah variasi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'variasi_add') {
    $id_kostum = intval($_POST['id_kostum_variasi'] ?? 0);
    $ukuran = sanitize($_POST['ukuran'] ?? '');
    $stok = intval($_POST['stok'] ?? 0);

    if ($id_kostum <= 0) $errors[] = 'Pilih kostum';
    if (!in_array($ukuran, ['S','M','L','XL'])) $errors[] = 'Ukuran tidak valid';
    if ($stok < 0) $errors[] = 'Stok tidak boleh negatif';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO kostum_variasi (id_kostum, ukuran, stok) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $id_kostum, $ukuran, $stok);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Variasi berhasil ditambahkan';
            $stmt->close();
            redirect('admin/kostum.php#tab-variasi');
        } else {
            $errors[] = 'Gagal menambahkan variasi';
            $stmt->close();
        }
    }
}

// --- edit variasi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'variasi_edit') {
    $id = intval($_POST['variasi_id'] ?? 0);
    $ukuran = sanitize($_POST['ukuran'] ?? '');
    $stok = intval($_POST['stok'] ?? 0);

    if (!in_array($ukuran, ['S','M','L','XL'])) $errors[] = 'Ukuran tidak valid';
    if ($stok < 0) $errors[] = 'Stok tidak boleh negatif';

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE kostum_variasi SET ukuran = ?, stok = ? WHERE id = ?");
        $stmt->bind_param("sii", $ukuran, $stok, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Variasi berhasil diupdate';
            $stmt->close();
            redirect('admin/kostum.php#tab-variasi');
        } else {
            $errors[] = 'Gagal mengupdate variasi';
            $stmt->close();
        }
    }
}

// --- delete variasi ---
if (isset($_GET['delete_variasi'])) {
    $id = intval($_GET['delete_variasi']);
    $stmt = $conn->prepare("DELETE FROM kostum_variasi WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Variasi berhasil dihapus';
    } else {
        $_SESSION['success_message'] = 'Gagal menghapus variasi';
    }
    $stmt->close();
    redirect('admin/kostum.php#tab-variasi');
}

// ======= FETCH DATA FOR UI =======
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

// count
if (!empty($search)) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM kostum k LEFT JOIN kategori_kostum kc ON k.id_kategori = kc.id WHERE (k.nama_kostum LIKE ? OR kc.nama_kategori LIKE ?)");
    $like = "%{$search}%";
    $count_stmt->bind_param("ss", $like, $like);
    $count_stmt->execute();
    $total_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $res = $conn->query("SELECT COUNT(*) AS total FROM kostum");
    $total_records = (int)$res->fetch_assoc()['total'];
}

// list
if (!empty($search)) {
    $list_sql = "SELECT k.*, kc.nama_kategori FROM kostum k LEFT JOIN kategori_kostum kc ON k.id_kategori = kc.id
                 WHERE (k.nama_kostum LIKE ? OR kc.nama_kategori LIKE ?)
                 ORDER BY k.id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($list_sql);
    $like = "%{$search}%";
    $stmt->bind_param("ssii", $like, $like, $limit, $offset);
    $stmt->execute();
    $kostum_list = $stmt->get_result();
    $stmt->close();
} else {
    $list_sql = "SELECT k.*, kc.nama_kategori FROM kostum k LEFT JOIN kategori_kostum kc ON k.id_kategori = kc.id
                 ORDER BY k.id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($list_sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $kostum_list = $stmt->get_result();
    $stmt->close();
}

$cats_q = $conn->query("SELECT * FROM kategori_kostum ORDER BY nama_kategori ASC");
$variations_q = $conn->query("SELECT kv.*, k.nama_kostum FROM kostum_variasi kv JOIN kostum k ON kv.id_kostum = k.id ORDER BY kv.id DESC LIMIT 300");

// ===== DETECT EDIT MODE (sebelum render UI) =====
$edit_mode = false;
$edit_data = null;
$active_tab = 'tab-list'; // default active tab

if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    if ($eid > 0) {
        $stmt = $conn->prepare("SELECT * FROM kostum WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $edit_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($edit_data) {
                $edit_mode = true;
                $active_tab = 'tab-add';
            }
        }
    }
}

// show possible flash
showFlash();

?>
<div class="flex">
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 bg-lighter-bg min-h-screen lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-20 lg:pb-16">
            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Kelola Kostum</h1>
                <p class="text-gray-600 mt-2 text-sm">Tambah, edit, dan hapus kostum, variasi, dan kategori</p>
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
                <nav class="flex gap-2" id="kostumTabs">
                    <button class="tab-btn px-4 py-2 rounded-md <?php echo $active_tab === 'tab-list' ? 'bg-primary text-white' : 'bg-light-bg text-accent'; ?>" data-tab="tab-list">Daftar Kostum</button>
                    <button class="tab-btn px-4 py-2 rounded-md <?php echo $active_tab === 'tab-add' ? 'bg-primary text-white' : 'bg-light-bg text-accent'; ?>" data-tab="tab-add"><?php echo $edit_mode ? 'Edit Kostum' : 'Tambah Kostum'; ?></button>
                    <button class="tab-btn px-4 py-2 rounded-md <?php echo $active_tab === 'tab-variasi' ? 'bg-primary text-white' : 'bg-light-bg text-accent'; ?>" data-tab="tab-variasi">Variasi</button>
                    <button class="tab-btn px-4 py-2 rounded-md <?php echo $active_tab === 'tab-kategori' ? 'bg-primary text-white' : 'bg-light-bg text-accent'; ?>" data-tab="tab-kategori">Kategori</button>
                </nav>
            </div>

            <!-- TAB: LIST -->
            <div id="tab-list" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display: <?php echo $active_tab === 'tab-list' ? 'block' : 'none'; ?>;">
                <div class="flex items-center justify-between mb-4">
                    <form method="GET" action="kostum.php" class="flex items-center gap-2">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama kostum atau kategori..." class="form-control-custom">
                        <button class="btn-primary-custom px-4 py-2">Cari</button>
                    </form>
                    <div class="text-sm text-gray-600">Menampilkan <?php echo ($offset+1); ?> - <?php echo min($offset + $limit, $total_records); ?> dari <?php echo $total_records; ?></div>
                </div>

                <?php if ($kostum_list && $kostum_list->num_rows > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php while ($k = $kostum_list->fetch_assoc()): ?>
                            <div class="border p-3 rounded-lg">
                                <div class="h-40 bg-gray-100 rounded overflow-hidden mb-3 flex items-center justify-center">
                                    <?php if (!empty($k['foto'])): ?>
                                        <img src="<?php echo $image_url . htmlspecialchars($k['foto']); ?>" class="object-cover w-full h-full" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-tshirt text-4xl text-gray-300"></i>
                                    <?php endif; ?>
                                </div>
                                <h4 class="font-semibold text-accent"><?php echo htmlspecialchars($k['nama_kostum']); ?></h4>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($k['nama_kategori'] ?? '-'); ?></p>
                                <p class="text-sm font-bold text-primary mt-2"><?php echo function_exists('formatRupiah') ? formatRupiah($k['harga_sewa']) : number_format($k['harga_sewa'],2,',','.'); ?></p>
                                <div class="flex items-center gap-2 mt-3">
                                    <a href="?edit=<?php echo $k['id']; ?>" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-edit mr-1"></i>Edit</a>
                                    <a href="?delete_kostum=<?php echo $k['id']; ?>" onclick="return confirm('Hapus kostum ini? Semua variasi juga akan dihapus.')" class="px-3 py-2 bg-red-600 text-white rounded text-sm"><i class="fas fa-trash mr-1"></i>Hapus</a>
                                    <a href="#tab-variasi" onclick="showVariasiFor(<?php echo $k['id']; ?>)" class="px-3 py-2 bg-gray-200 rounded text-sm text-accent"><i class="fas fa-layer-group mr-1"></i>Variasi</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php
                    $total_pages = max(1, ceil($total_records / $limit));
                    if ($total_pages > 1):
                    ?>
                    <div class="mt-6 flex justify-center items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border rounded">Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-2 border rounded <?php echo $i == $page ? 'bg-primary text-white' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border rounded">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">Tidak ada kostum</div>
                <?php endif; ?>
            </div>

            <!-- TAB: ADD / EDIT KOSTUM -->
            <div id="tab-add" class="tab-content bg-white rounded-lg shadow-md p-4 mb-6" style="display: <?php echo $active_tab === 'tab-add' ? 'block' : 'none'; ?>;">
                <h3 class="text-lg font-bold mb-4"><?php echo $edit_mode ? 'Edit Kostum' : 'Tambah Kostum Baru'; ?></h3>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="<?php echo $edit_mode ? 'kostum_edit' : 'kostum_add'; ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="kostum_id" value="<?php echo (int)$edit_data['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium">Nama Kostum *</label>
                            <input type="text" name="nama_kostum" required class="form-control-custom w-full" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nama_kostum']) : ''; ?>">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium">Kategori *</label>
                            <select name="id_kategori" required class="form-control-custom w-full">
                                <option value="">Pilih Kategori</option>
                                <?php
                                if ($cats_q && $cats_q->num_rows > 0) {
                                    $cats_q->data_seek(0);
                                    while ($c = $cats_q->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($edit_mode && $edit_data['id_kategori'] == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nama_kategori']); ?></option>
                                <?php
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 text-sm font-medium">Deskripsi</label>
                            <textarea name="deskripsi" rows="4" class="form-control-custom w-full"><?php echo $edit_mode ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium">Harga Sewa *</label>
                            <input type="number" name="harga_sewa" step="0.01" required class="form-control-custom w-full" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['harga_sewa']) : ''; ?>">
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium">Status</label>
                            <select name="status" class="form-control-custom w-full">
                                <option value="aktif" <?php echo ($edit_mode && $edit_data['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo ($edit_mode && $edit_data['status'] == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 text-sm font-medium">Foto</label>
                            <div class="flex items-center gap-3">
                                <label for="foto" class="btn-secondary-custom px-4 py-2 rounded cursor-pointer">Pilih Foto</label>
                                <span id="foto_name" class="text-sm text-gray-600">Tidak ada file dipilih</span>
                                <input type="file" name="foto" id="foto" class="hidden" accept="image/*" onchange="updateFilename(this,'foto_name')">
                            </div>
                            <?php if ($edit_mode && !empty($edit_data['foto'])): ?>
                                <div class="mt-3"><img src="<?php echo $image_url . htmlspecialchars($edit_data['foto']); ?>" class="w-48 h-32 object-cover rounded"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn-primary-custom px-5 py-2"><?php echo $edit_mode ? 'Update Kostum' : 'Tambah Kostum'; ?></button>
                        <?php if ($edit_mode): ?>
                            <a href="kostum.php" class="btn-secondary-custom px-5 py-2">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- TAB: VARIASI -->
<div id="tab-variasi" class="tab-content bg-white rounded-lg shadow-md p-6 mb-6" style="display: <?php echo $active_tab === 'tab-variasi' ? 'block' : 'none'; ?>;">
    <h3 class="text-xl font-bold mb-6">Kelola Variasi</h3>

    <!-- Form Tambah Variasi -->
    <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <input type="hidden" name="action" value="variasi_add">

        <!-- Pilih Kostum -->
        <div class="flex flex-col">
            <label class="mb-2 text-sm font-medium text-gray-700">Pilih Kostum *</label>
            <select name="id_kostum_variasi" required class="form-control-custom rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- Pilih Kostum --</option>
                <?php
                $kq = $conn->query("SELECT id, nama_kostum FROM kostum ORDER BY nama_kostum");
                while ($row = $kq->fetch_assoc()):
                ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_kostum']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Pilih Ukuran -->
        <div class="flex flex-col">
            <label class="mb-2 text-sm font-medium text-gray-700">Ukuran *</label>
            <select name="ukuran" required class="form-control-custom  rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Pilih</option>
                <option value="S">S</option>
                <option value="M">M</option>
                <option value="L">L</option>
                <option value="XL">XL</option>
            </select>
        </div>

        <!-- Input Stok -->
        <div class="flex flex-col">
            <label class="mb-2 text-sm font-medium text-gray-700">Stok *</label>
            <input type="number" name="stok" min="0" value="1" class="form-control-custom  rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Tombol Submit -->
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded shadow-sm transition duration-200">Tambah Variasi</button>
        </div>
    </form>

    <!-- List Variasi -->
    <?php if ($variations_q && $variations_q->num_rows > 0): ?>
        <div class="space-y-4">
            <?php
            $variations_q->data_seek(0);
            while ($v = $variations_q->fetch_assoc()):
            ?>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 border rounded-lg shadow-sm hover:shadow-md transition duration-200">
                <div class="mb-2 md:mb-0">
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($v['nama_kostum']); ?> 
                        <span class="text-sm text-gray-500">/ <?php echo htmlspecialchars($v['ukuran']); ?></span>
                    </div>
                    <div class="text-sm text-gray-600">Stok: <?php echo (int)$v['stok']; ?></div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded shadow-sm text-sm" 
                        onclick="openEditVarModal(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars(addslashes($v['ukuran'])); ?>', <?php echo $v['stok']; ?>)">
                        Edit
                    </button>
                    <a href="?delete_variasi=<?php echo $v['id']; ?>" onclick="return confirm('Hapus variasi ini?')" 
                       class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded shadow-sm text-sm">Hapus</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center text-gray-400 py-10">Belum ada variasi</div>
    <?php endif; ?>
</div>


            <!-- TAB: KATEGORI -->
<div id="tab-kategori" class="tab-content bg-white rounded-lg shadow-md p-6 mb-6" style="display: <?php echo $active_tab === 'tab-kategori' ? 'block' : 'none'; ?>;">
    <h3 class="text-xl font-bold mb-4">Kelola Kategori Kostum</h3>

    <!-- Form Tambah Kategori -->
    <form method="POST" class="flex gap-2 mb-6 items-center">
        <input type="hidden" name="action" value="kategori_add">
        <input type="text" name="nama_kategori" placeholder="Nama kategori" required
               class="form-control-custom w-full">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded shadow-sm transition duration-200">
            Tambah Kategori
        </button>
    </form>

    <!-- List Kategori -->
    <?php if ($cats_q && $cats_q->num_rows > 0): ?>
        <div class="space-y-3">
            <?php
            $cats_q->data_seek(0);
            while ($c = $cats_q->fetch_assoc()):
            ?>
            <div class="flex items-center justify-between p-3 border rounded-lg shadow-sm hover:shadow-md transition duration-200">
                <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($c['nama_kategori']); ?></div>
                <div class="flex gap-2">
                    <button class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm" 
                            onclick="openEditKategoriModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['nama_kategori'])); ?>')">
                        Edit
                    </button>
                    <a href="?delete_kategori=<?php echo $c['id']; ?>" onclick="return confirm('Hapus kategori ini?')" 
                       class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-sm">Hapus</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center text-gray-400 py-6">Belum ada kategori</div>
    <?php endif; ?>
</div>

        </div>
    </main>
</div>

<!-- Modals -->
<div id="modal-edit-variasi" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="font-bold mb-4">Edit Variasi</h3>
        <form id="formEditVar" method="POST">
            <input type="hidden" name="action" value="variasi_edit">
            <input type="hidden" name="variasi_id" id="variasi_id">
            <div class="mb-3">
                <label class="block mb-1 text-sm">Ukuran</label>
                <select name="ukuran" id="var_ukuran" class="form-control-custom">
                    <option value="S">S</option>
                    <option value="M">M</option>
                    <option value="L">L</option>
                    <option value="XL">XL</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block mb-1 text-sm">Stok</label>
                <input type="number" name="stok" id="var_stok" min="0" class="form-control-custom">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" class="btn-secondary-custom px-4 py-2" onclick="closeVarModal()">Batal</button>
                <button type="submit" class="btn-primary-custom px-4 py-2">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-kategori" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="font-bold mb-4">Edit Kategori</h3>
        <form id="formEditKategori" method="POST">
            <input type="hidden" name="action" value="kategori_edit">
            <input type="hidden" name="kategori_id" id="kategori_id">
            <div class="mb-3">
                <label class="block mb-1 text-sm">Nama Kategori</label>
                <input type="text" name="nama_kategori" id="kategori_nama" class="form-control-custom">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" class="btn-secondary-custom px-4 py-2" onclick="closeKategoriModal()">Batal</button>
                <button type="submit" class="btn-primary-custom px-4 py-2">Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
.tab-btn { cursor: pointer; }
.tab-content { display: block; }
</style>

<script>
    // tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('bg-primary'); b.classList.add('bg-light-bg'); b.classList.remove('text-white'); b.classList.add('text-accent'); });
            btn.classList.add('bg-primary'); btn.classList.remove('bg-light-bg'); btn.classList.add('text-white'); btn.classList.remove('text-accent');

            const tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            const el = document.getElementById(tab);
            if (el) el.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // pilih tab default berdasarkan server-side (aktif)
    document.addEventListener('DOMContentLoaded', function(){
        const active = '<?php echo $active_tab; ?>';
        const btn = document.querySelector('[data-tab="' + active + '"]');
        if (btn) btn.click();
    });

    function updateFilename(input, labelId){
        const label = document.getElementById(labelId);
        label.textContent = input.files && input.files[0] ? input.files[0].name : 'Tidak ada file dipilih';
    }

    function openEditVarModal(id, ukuran, stok) {
        document.getElementById('modal-edit-variasi').classList.remove('hidden');
        document.getElementById('variasi_id').value = id;
        document.getElementById('var_ukuran').value = ukuran;
        document.getElementById('var_stok').value = stok;
    }
    function closeVarModal() {
        document.getElementById('modal-edit-variasi').classList.add('hidden');
    }

    function openEditKategoriModal(id, nama) {
        document.getElementById('modal-edit-kategori').classList.remove('hidden');
        document.getElementById('kategori_id').value = id;
        document.getElementById('kategori_nama').value = nama;
    }
    function closeKategoriModal() {
        document.getElementById('modal-edit-kategori').classList.add('hidden');
    }

    function showVariasiFor(kostumId) {
        const btn = document.querySelector('[data-tab="tab-variasi"]');
        if (btn) btn.click();
        setTimeout(()=>{ window.location.hash = 'tab-variasi'; }, 200);
    }
</script>

<?php
require_once '../includes/footer.php';
?>
