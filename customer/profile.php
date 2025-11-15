<?php
$page_title = "Profile";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('auth/login.php');
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

$conn = getConnection();
$query = "SELECT name, email, username, phone, address FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field harus diisi";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok";
    } else {
        $conn = getConnection();
        
        $query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password berhasil diubah";
            } else {
                $error = "Gagal mengubah password";
            }
            $update_stmt->close();
        } else {
            $error = "Password saat ini tidak valid";
        }
        
        $stmt->close();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <i class="fas fa-user-circle text-5xl text-primary mb-4"></i>
            <h2 class="text-3xl font-bold text-accent">Profile Saya</h2>
            <p class="mt-2 text-gray-600">Informasi Akun</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Diri -->
        <div class="bg-white shadow-lg rounded-lg p-8 mb-6">
            <h3 class="text-xl font-semibold text-accent mb-4">Data Diri</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-600 font-medium mb-2">Nama Lengkap</label>
                    <div class="bg-gray-50 px-4 py-3 rounded-lg text-gray-800">
                        <?php echo htmlspecialchars($user_data['name']); ?>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-600 font-medium mb-2">Username</label>
                    <div class="bg-gray-50 px-4 py-3 rounded-lg text-gray-800">
                        <?php echo htmlspecialchars($user_data['username']); ?>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-600 font-medium mb-2">Email</label>
                    <div class="bg-gray-50 px-4 py-3 rounded-lg text-gray-800">
                        <?php echo htmlspecialchars($user_data['email']); ?>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-600 font-medium mb-2">No. Telepon</label>
                    <div class="bg-gray-50 px-4 py-3 rounded-lg text-gray-800">
                        <?php echo htmlspecialchars($user_data['phone'] ?? '-'); ?>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-600 font-medium mb-2">Alamat</label>
                    <div class="bg-gray-50 px-4 py-3 rounded-lg text-gray-800">
                        <?php echo nl2br(htmlspecialchars($user_data['address'] ?? '-')); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Ubah Password -->
        <div class="bg-white shadow-lg rounded-lg p-8">
            <h3 class="text-xl font-semibold text-accent mb-4">Ubah Password</h3>
            <form method="POST" action="">
                <!-- Current Password -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Password Saat Ini
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-lock text-gray-400"></i>
                        </span>
                        <input type="password" name="current_password" required
                               class="form-control-custom w-full pl-10 pr-10"
                               placeholder="Masukkan password saat ini">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Password Baru
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-key text-gray-400"></i>
                        </span>
                        <input type="password" name="new_password" required
                               class="form-control-custom w-full pl-10 pr-10"
                               placeholder="Masukkan password baru">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm New Password -->
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-2">
                        Konfirmasi Password Baru
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-key text-gray-400"></i>
                        </span>
                        <input type="password" name="confirm_password" required
                               class="form-control-custom w-full pl-10 pr-10"
                               placeholder="Masukkan ulang password baru">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary-custom w-full py-3 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i>Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    const newPasswordInput = document.querySelector('input[name="new_password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    const form = document.querySelector('form');

    function validatePassword() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword) {
            if (newPassword !== confirmPassword) {
                confirmPasswordInput.classList.add('border-red-500');
                confirmPasswordInput.classList.remove('border-gray-200');
                confirmPasswordInput.setCustomValidity('Password tidak cocok');
                
                let errorMessage = confirmPasswordInput.parentElement.querySelector('.password-error');
                if (!errorMessage) {
                    errorMessage = document.createElement('div');
                    errorMessage.className = 'text-red-500 text-sm mt-1 password-error';
                    errorMessage.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>Password tidak cocok';
                    confirmPasswordInput.parentElement.appendChild(errorMessage);
                }
            } else {
                confirmPasswordInput.classList.remove('border-red-500');
                confirmPasswordInput.classList.add('border-gray-200');
                confirmPasswordInput.setCustomValidity('');
                
                const errorMessage = confirmPasswordInput.parentElement.querySelector('.password-error');
                if (errorMessage) {
                    errorMessage.remove();
                }
            }
        }
    }

    newPasswordInput.addEventListener('input', validatePassword);
    confirmPasswordInput.addEventListener('input', validatePassword);

    form.addEventListener('submit', function(e) {
        validatePassword();
        if (!confirmPasswordInput.checkValidity()) {
            e.preventDefault();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
