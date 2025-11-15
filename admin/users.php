<?php
$page_title = "Kelola Pengguna - Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

if (isset($_SESSION['success_message'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '" . $_SESSION['success_message'] . "',
                confirmButtonColor: '#7A6A54'
            });
        });
    </script>";
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '" . $_SESSION['error_message'] . "',
                confirmButtonColor: '#7A6A54'
            });
        });
    </script>";
    unset($_SESSION['error_message']);
}

$conn = getConnection();

if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Tidak dapat menghapus akun sendiri";
        redirect('admin/users.php');
    }
    
    $query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['count'] > 0) {
        $_SESSION['error_message'] = "Tidak dapat menghapus user yang memiliki riwayat pesanan";
        redirect('admin/users.php');
    }
    
    $query = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success_message'] = "Pengguna berhasil dihapus";
    redirect('admin/users.php');
}

$errors = [];
$edit_mode = false;
$user_data = null;

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $user_id = intval($_GET['edit']);
    
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user_data) {
        redirect('admin/users.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($name)) $errors[] = "Nama harus diisi";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email tidak valid";
    if (empty($username) || strlen($username) < 4) $errors[] = "Username minimal 4 karakter";
    if (empty($phone)) $errors[] = "Nomor telepon harus diisi";
    if (!in_array($role, ['admin', 'customer'])) $errors[] = "Role tidak valid";
    
    if (!$edit_mode && empty($password)) {
        $errors[] = "Password harus diisi";
    }
    
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter";
    }
    
    if (empty($errors)) {
        if ($edit_mode) {
            $user_id = intval($_POST['user_id']);
            $query = "SELECT * FROM users WHERE (email = ? OR username = ?) AND user_id != ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $email, $username, $user_id);
        } else {
            $query = "SELECT * FROM users WHERE email = ? OR username = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $email, $username);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email atau username sudah terdaftar";
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        if ($edit_mode) {
            $user_id = intval($_POST['user_id']);
            
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET name = ?, email = ?, username = ?, phone = ?, role = ?, password = ? WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssi", $name, $email, $username, $phone, $role, $hashed_password, $user_id);
            } else {
                $query = "UPDATE users SET name = ?, email = ?, username = ?, phone = ?, role = ? WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssi", $name, $email, $username, $phone, $role, $user_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Pengguna berhasil diupdate";
                redirect('admin/users.php');
            } else {
                $errors[] = "Gagal mengupdate pengguna";
            }
            $stmt->close();
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (name, email, username, password, phone, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $name, $email, $username, $hashed_password, $phone, $role);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Pengguna berhasil ditambahkan";
                redirect('admin/users.php');
            } else {
                $errors[] = "Gagal menambahkan pengguna";
            }
            $stmt->close();
        }
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['admins'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$stats['customers'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
?>

<div class="flex">
    <!-- Sidebar -->
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 bg-lighter-bg min-h-screen lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-20 lg:pb-16">
            <!-- Header -->
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Kelola Pengguna</h1>
                <p class="text-gray-600 mt-2 text-sm sm:text-base">Tambah, edit, dan hapus pengguna</p>
            </div>

            <!-- Success/Error Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                showSuccess('<?php echo $_SESSION['success_message']; ?>');
            </script>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <script>
                showError('<?php echo $_SESSION['error_message']; ?>');
            </script>
            <?php unset($_SESSION['error_message']); endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert-error mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-exclamation-circle text-lg sm:text-xl"></i>
                    <h4 class="font-bold text-sm sm:text-base">Terdapat kesalahan dalam form</h4>
                </div>
                <ul class="alert-list">
                    <?php foreach ($errors as $error): ?>
                        <li class="text-xs sm:text-sm"><i class="fas fa-dot-circle text-xs mr-2"></i><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Pengguna</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo $stats['total']; ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-2xl sm:text-3xl text-primary"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Admin</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo $stats['admins']; ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-secondary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-shield text-2xl sm:text-3xl text-secondary"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Customer</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo $stats['customers']; ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-2xl sm:text-3xl text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6 lg:mb-8">
                <h3 class="text-lg sm:text-xl font-bold text-accent mb-4 sm:mb-6">
                    <?php echo $edit_mode ? 'Edit Pengguna' : 'Tambah Pengguna Baru'; ?>
                </h3>

                <form method="POST" class="space-y-4">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="user_id" value="<?php echo $user_data['user_id']; ?>">
                    <?php endif; ?>

                    <!-- Name -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">Nama Lengkap *</label>
                        <input type="text" name="name" required
                               class="form-control-custom w-full text-sm sm:text-base"
                               value="<?php echo $edit_mode ? htmlspecialchars($user_data['name']) : ''; ?>">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">Email *</label>
                        <input type="email" name="email" required
                               class="form-control-custom w-full text-sm sm:text-base"
                               value="<?php echo $edit_mode ? htmlspecialchars($user_data['email']) : ''; ?>">
                    </div>

                    <!-- Username -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">Username *</label>
                        <input type="text" name="username" required
                               class="form-control-custom w-full text-sm sm:text-base"
                               value="<?php echo $edit_mode ? htmlspecialchars($user_data['username']) : ''; ?>">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">Nomor Telepon *</label>
                        <input type="tel" name="phone" required
                               class="form-control-custom w-full text-sm sm:text-base"
                               value="<?php echo $edit_mode ? htmlspecialchars($user_data['phone']) : ''; ?>">
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">Role *</label>
                        <select name="role" required class="form-control-custom w-full text-sm sm:text-base">
                            <option value="customer" <?php echo ($edit_mode && $user_data['role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                            <option value="admin" <?php echo ($edit_mode && $user_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-accent font-medium mb-2 text-sm sm:text-base">
                            Password <?php echo $edit_mode ? '(Kosongkan jika tidak ingin mengubah)' : '*'; ?>
                        </label>
                        <input type="password" name="password" 
                               <?php echo $edit_mode ? '' : 'required'; ?>
                               class="form-control-custom w-full text-sm sm:text-base"
                               placeholder="Minimal 6 karakter">
                    </div>

                    <!-- Buttons -->
                    <div class="flex flex-col gap-3 pt-4">
                        <button type="button" onclick="confirmUserSubmit()" class="w-full btn-primary-custom px-6 py-3 rounded-lg text-sm sm:text-base">
                            <i class="fas fa-save mr-2"></i><?php echo $edit_mode ? 'Update Pengguna' : 'Tambah Pengguna'; ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="users.php" class="w-full bg-gray-200 hover:bg-gray-300 px-6 py-3 rounded-lg font-medium transition text-center text-sm sm:text-base">
                                Batal
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-accent">Daftar Pengguna</h3>
                </div>

                <!-- Mobile Card View -->
                <div class="block lg:hidden">
                    <?php 
                    $users->data_seek(0);
                    while ($user = $users->fetch_assoc()): 
                    ?>
                        <div class="border-b border-gray-200 p-4">
                            <div class="flex items-start gap-3">
                                <!-- Icon -->
                                <div class="flex-shrink-0 mt-1">
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <div class="w-12 h-12 bg-primary bg-opacity-20 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user-shield text-primary text-xl"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-secondary bg-opacity-20 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-secondary text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-accent text-sm truncate"><?php echo htmlspecialchars($user['name']); ?></h4>
                                            <?php if ($user['role'] == 'admin'): ?>
                                                <span class="inline-block px-2 py-0.5 bg-primary bg-opacity-20 text-primary rounded-full text-xs font-medium mt-1">
                                                    <i class="fas fa-user-shield mr-1"></i>Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-block px-2 py-0.5 bg-secondary bg-opacity-20 text-secondary rounded-full text-xs font-medium mt-1">
                                                    <i class="fas fa-user mr-1"></i>Customer
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-600 space-y-1 mt-2">
                                        <p><i class="fas fa-envelope mr-1 w-4"></i><?php echo htmlspecialchars($user['email']); ?></p>
                                        <p><i class="fas fa-user-circle mr-1 w-4"></i><?php echo htmlspecialchars($user['username']); ?></p>
                                        <p><i class="fas fa-phone mr-1 w-4"></i><?php echo htmlspecialchars($user['phone']); ?></p>
                                        <p><i class="fas fa-calendar mr-1 w-4"></i><?php echo formatDate($user['created_at']); ?></p>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex gap-2 mt-3">
                                        <a href="?edit=<?php echo $user['user_id']; ?>" 
                                           class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-xs text-center">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button onclick="deleteUser(<?php echo $user['user_id']; ?>)" 
                                                    class="flex-1 bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded text-xs">
                                                <i class="fas fa-trash mr-1"></i>Hapus
                                            </button>
                                        <?php else: ?>
                                            <div class="flex-1 bg-gray-200 text-gray-500 px-3 py-2 rounded text-xs text-center">
                                                <i class="fas fa-lock mr-1"></i>Akun Anda
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Desktop Table View -->
<div class="hidden lg:block overflow-x-auto">
    <table class="table-custom w-full text-xs sm:text-sm">
        <thead>
            <tr>
                <th class="text-xs sm:text-sm text-left py-4">Nama</th>
                <th class="text-xs sm:text-sm text-left py-4">Email</th>
                <th class="text-xs sm:text-sm text-left py-4">Username</th>
                <th class="text-xs sm:text-sm text-left py-4">Telepon</th>
                <th class="text-xs sm:text-sm text-center py-4">Role</th>
                <th class="text-xs sm:text-sm text-center py-4">Terdaftar</th>
                <th class="text-xs sm:text-sm text-center py-4">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $users->data_seek(0);
            while ($user = $users->fetch_assoc()): 
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-4">
                    <div class="font-medium text-accent"><?php echo htmlspecialchars($user['name']); ?></div>
                </td>
                <td class="py-4">
                    <div class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></div>
                </td>
                <td class="py-4">
                    <div class="text-gray-600"><?php echo htmlspecialchars($user['username']); ?></div>
                </td>
                <td class="py-4">
                    <div class="text-gray-600"><?php echo htmlspecialchars($user['phone']); ?></div>
                </td>
                <td class="py-4">
                    <div class="flex justify-center">
                        <?php if ($user['role'] == 'admin'): ?>
                            <span class="px-3 py-1 bg-primary bg-opacity-20 text-primary rounded-full text-xs font-medium whitespace-nowrap">
                                <i class="fas fa-user-shield mr-1"></i>Admin
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-secondary bg-opacity-20 text-secondary rounded-full text-xs font-medium whitespace-nowrap">
                                <i class="fas fa-user mr-1"></i>Customer
                            </span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="py-4">
                    <div class="text-center text-xs sm:text-sm whitespace-nowrap">
                        <?php echo formatDate($user['created_at']); ?>
                    </div>
                </td>
                <td class="py-4">
                    <div class="flex justify-center gap-3">
                        <a href="?edit=<?php echo $user['user_id']; ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm sm:text-base transition-colors"
                           title="Edit Pengguna">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                            <button onclick="deleteUser(<?php echo $user['user_id']; ?>)" 
                                    class="text-red-600 hover:text-red-800 text-sm sm:text-base transition-colors"
                                    title="Hapus Pengguna">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
            </div>
        </div>
    </main>
</div>

<script>
function deleteUser(userId) {
    confirmAction('Hapus Pengguna?', 'Pengguna yang dihapus tidak dapat dikembalikan', 'Ya, Hapus')
    .then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete=' + userId;
        }
    });
}

function confirmUserSubmit() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    const name = formData.get('name');
    const email = formData.get('email');
    const role = formData.get('role');
    const actionType = <?php echo $edit_mode ? "'Update'" : "'Tambah'"; ?>;
    
    Swal.fire({
        title: actionType + ' Pengguna?',
        html: `
            <div class="text-left">
                <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                    <p class="text-sm"><strong>Nama:</strong> ${name}</p>
                    <p class="text-sm"><strong>Email:</strong> ${email}</p>
                    <p class="text-sm"><strong>Role:</strong> ${role}</p>
                </div>
                <p class="mt-4 text-sm text-gray-600">Apakah Anda yakin ingin ${actionType.toLowerCase()} pengguna ini?</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7A6A54',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Ya, ' + actionType,
        cancelButtonText: 'Batal',
        buttonsStyling: true,
        width: '90%',
        maxWidth: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>

<div class="lg:ml-64">
    <?php require_once '../includes/footer.php'; ?>
</div>