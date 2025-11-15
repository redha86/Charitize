<?php
$cart_count = 0;
if (isset($_SESSION['user_id']) && !isAdmin()) {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    $query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_count = $result->fetch_assoc()['count'];
    $stmt->close();
}
?>
<nav class="navbar-custom sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="<?php echo BASE_URL; ?>index.php" class="flex items-center gap-1">
                    <span class="text-xl font-bold text-accent"><?php echo SITE_NAME; ?></span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="<?php echo BASE_URL; ?>index.php" class="nav-link-custom hover:text-primary">
                    <i class="fas fa-home mr-1"></i> Beranda
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-link-custom hover:text-primary">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard Admin
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>customer/orders.php" class="nav-link-custom hover:text-primary">
                            <i class="fas fa-box mr-1"></i> Pesanan Saya
                        </a>
                        <a href="<?php echo BASE_URL; ?>customer/cart.php" class="nav-link-custom hover:text-primary relative">
                            <i class="fas fa-shopping-cart mr-1"></i> Keranjang
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge" data-count="<?php echo $cart_count; ?>"><?php echo $cart_count; ?></span>
                            <?php else: ?>
                                <span class="cart-badge" data-count="0" style="display: none;">0</span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    
                    <div class="relative">
                        <button id="userMenuButton" type="button" class="flex items-center space-x-2 text-accent hover:text-primary">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        
                        <!-- Dropdown -->
                        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2">
                            <?php if (!isAdmin()): ?>
                            <a href="<?php echo BASE_URL; ?>customer/profile.php" class="block px-4 py-2 text-accent hover:bg-light-bg">
                                <i class="fas fa-user-cog mr-2"></i> Profile
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="block px-4 py-2 text-accent hover:bg-light-bg">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="nav-link-custom hover:text-primary">
                        <i class="fas fa-sign-in-alt mr-1"></i> Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn-primary-custom px-4 py-2 rounded-lg text-white">
                        <i class="fas fa-user-plus mr-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobileMenuButton" type="button" class="text-accent">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden pb-4">
            <div class="flex flex-col space-y-3">
                <a href="<?php echo BASE_URL; ?>index.php" class="nav-link-custom hover:text-primary">
                    <i class="fas fa-home mr-1"></i> Beranda
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-link-custom hover:text-primary">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard Admin
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>customer/orders.php" class="nav-link-custom hover:text-primary">
                            <i class="fas fa-box mr-1"></i> Pesanan Saya
                        </a>
                        <a href="<?php echo BASE_URL; ?>customer/cart.php" class="nav-link-custom hover:text-primary relative inline-flex items-center">
                            <i class="fas fa-shopping-cart mr-1"></i> Keranjang
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge ml-2" data-count="<?php echo $cart_count; ?>"><?php echo $cart_count; ?></span>
                            <?php else: ?>
                                <span class="cart-badge ml-2" data-count="0" style="display: none;">0</span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>customer/profile.php" class="nav-link-custom hover:text-primary">
                            <i class="fas fa-user-cog mr-1"></i> Profile
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo BASE_URL; ?>auth/logout.php" class="nav-link-custom hover:text-primary">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="nav-link-custom hover:text-primary">
                        <i class="fas fa-sign-in-alt mr-1"></i> Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>auth/register.php" class="nav-link-custom hover:text-primary">
                        <i class="fas fa-user-plus mr-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    
    const userMenuButton = document.getElementById('userMenuButton');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuButton) {
        userMenuButton.addEventListener('click', () => {
            userDropdown.classList.toggle('hidden');
        });
        
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    }
    
    const mobileMenuButton = document.getElementById('mobileMenuButton');
    const mobileMenu = document.getElementById('mobileMenu');
    
    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar-custom');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar-custom');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    }
});
</script>