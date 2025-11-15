<?php
$page_title = "Register";
require_once '../includes/header.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    
    if (empty($name)) {
        $errors[] = "Nama harus diisi";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email tidak valid";
    }
    
    if (empty($username) || strlen($username) < 4) {
        $errors[] = "Username minimal 4 karakter";
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Password tidak cocok";
    }
    
    if (empty($phone)) {
        $errors[] = "Nomor telepon harus diisi";
    }
    
    if (empty($errors)) {
        $conn = getConnection();
        
        $query = "SELECT * FROM users WHERE email = ? OR username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email atau username sudah terdaftar";
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (name, email, username, password, phone, role) VALUES (?, ?, ?, ?, ?, 'customer')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $name, $email, $username, $hashed_password, $phone);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Terjadi kesalahan saat registrasi";
        }
        $stmt->close();
    }
}
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-accent">Buat Akun Baru</h2>
            <p class="mt-2 text-gray-600">Daftar untuk mulai berbelanja</p>
        </div>

        <!-- Alert Success -->
        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span>Registrasi berhasil! Silakan <a href="login.php" class="font-bold underline">login</a></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alert Errors -->
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle mr-2 mt-1"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white shadow-lg rounded-lg p-8">
            <form method="POST" action="" id="registerForm">
                <!-- Name -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required
                           class="form-control-custom w-full"
                           placeholder="Masukkan nama lengkap"
                           value="<?php echo isset($name) ? $name : ''; ?>">
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                           class="form-control-custom w-full"
                           placeholder="contoh@email.com"
                           value="<?php echo isset($email) ? $email : ''; ?>">
                </div>

                <!-- Username -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" required
                           class="form-control-custom w-full"
                           placeholder="Minimal 4 karakter"
                           value="<?php echo isset($username) ? $username : ''; ?>">
                </div>

                <!-- Phone -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Nomor Telepon <span class="text-red-500">*</span>
                    </label>
                    <input type="tel" name="phone" required
                           class="form-control-custom w-full"
                           placeholder="08xxxxxxxxxx"
                           value="<?php echo isset($phone) ? $phone : ''; ?>">
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" required
                               class="form-control-custom w-full pr-10"
                               placeholder="Minimal 6 karakter">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-2">
                        Konfirmasi Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="confirm_password" required
                               class="form-control-custom w-full pr-10"
                               placeholder="Ulangi password">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary-custom w-full py-3 rounded-lg font-medium">
                    <i class="fas fa-user-plus mr-2"></i>Daftar Sekarang
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Sudah punya akun? 
                    <a href="login.php" class="text-primary font-medium hover:underline">Login disini</a>
                </p>
            </div>
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

    const passwordInput = document.querySelector('input[name="password"]');
    const confirmInput = document.querySelector('input[name="confirm_password"]');

    function validatePasswordMatch() {
        if (confirmInput.value) {
            if (passwordInput.value !== confirmInput.value) {
                confirmInput.setCustomValidity('Password tidak cocok');
                confirmInput.classList.add('border-red-500');
                confirmInput.classList.remove('border-gray-200');
            } else {
                confirmInput.setCustomValidity('');
                confirmInput.classList.remove('border-red-500');
                confirmInput.classList.add('border-gray-200');
            }
        }
    }

    passwordInput.addEventListener('input', validatePasswordMatch);
    confirmInput.addEventListener('input', validatePasswordMatch);
</script>

<?php require_once '../includes/footer.php'; ?>