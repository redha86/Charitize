<?php
$page_title = "Login";
require_once '../includes/header.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/index.php');
    } else {
        redirect('index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_email = sanitize($_POST['username_email']);
    $password = $_POST['password'];
    
    if (empty($username_email) || empty($password)) {
        $error = "Username/Email dan password harus diisi";
    } else {
        $conn = getConnection();
        
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username_email, $username_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    redirect('admin/index.php');
                } else {
                    redirect('index.php');
                }
            } else {
                $error = "Password salah";
            }
        } else {
            $error = "Username atau email tidak ditemukan";
        }
        
        $stmt->close();
    }
}
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-accent">Selamat Datang</h2>
            <p class="mt-2 text-gray-600">Login ke akun Anda</p>
        </div>

        <!-- Alert Error -->
        <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white shadow-lg rounded-lg p-8">
            <form method="POST" action="">
                <!-- Username/Email -->
                <div class="mb-4">
                    <label class="block text-accent font-medium mb-2">
                        Username atau Email
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-user text-gray-400"></i>
                        </span>
                        <input type="text" name="username_email" required
                               class="form-control-custom w-full pl-10"
                               placeholder="Username atau email"
                               value="<?php echo isset($username_email) ? $username_email : ''; ?>">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-lock text-gray-400"></i>
                        </span>
                        <input type="password" name="password" required
                               class="form-control-custom w-full pl-10 pr-10"
                               placeholder="Masukkan password">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary-custom w-full py-3 rounded-lg font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </form>

            <!-- Register Link -->
            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Belum punya akun? 
                    <a href="register.php" class="text-primary font-medium hover:underline">Daftar sekarang</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    const toggleButton = document.querySelector('.toggle-password');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
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
    }
</script>

<?php require_once '../includes/footer.php'; ?>