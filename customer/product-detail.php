<?php
$page_title = "Detail Produk";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id == 0) {
    redirect('index.php');
}

$conn = getConnection();

$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE p.product_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirect('index.php');
}

$product = $result->fetch_assoc();
$stmt->close();

$stats_query = "SELECT 
                COUNT(*) as total_reviews,
                COALESCE(AVG(rating), 0) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
                FROM product_reviews 
                WHERE product_id = ? AND status = 'approved'";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$reviews_query = "SELECT pr.*, u.name as user_name 
                  FROM product_reviews pr
                  JOIN users u ON pr.user_id = u.user_id
                  WHERE pr.product_id = ? AND pr.status = 'approved'
                  ORDER BY pr.created_at DESC
                  LIMIT 50";

$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();

$can_review = false;
$user_purchased_order = null;

if (isLoggedIn() && !isAdmin()) {
    $user_id = $_SESSION['user_id'];
    
    $purchase_query = "SELECT o.order_id 
                       FROM orders o
                       JOIN order_items oi ON o.order_id = oi.order_id
                       LEFT JOIN product_reviews pr ON (pr.product_id = oi.product_id 
                                                       AND pr.user_id = o.user_id 
                                                       AND pr.order_id = o.order_id)
                       WHERE o.user_id = ? 
                       AND o.status = 'completed'
                       AND oi.product_id = ?
                       AND pr.review_id IS NULL
                       ORDER BY o.created_at DESC
                       LIMIT 1";
    
    $stmt = $conn->prepare($purchase_query);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $purchase_result = $stmt->get_result();
    
    if ($purchase_result->num_rows > 0) {
        $can_review = true;
        $user_purchased_order = $purchase_result->fetch_assoc();
    }
    $stmt->close();
}

$related_query = "SELECT * FROM products 
                  WHERE category_id = ? AND product_id != ? AND status = 'active' 
                  LIMIT 4";
$stmt = $conn->prepare($related_query);
$stmt->bind_param("ii", $product['category_id'], $product_id);
$stmt->execute();
$related_products = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2 text-gray-600">
            <li><a href="<?php echo BASE_URL; ?>index.php" class="hover:text-primary">Beranda</a></li>
            <li><i class="fas fa-chevron-right text-xs"></i></li>
            <li><a href="<?php echo BASE_URL; ?>index.php?category=<?php echo $product['category_id']; ?>" class="hover:text-primary">
                <?php echo $product['category_name']; ?>
            </a></li>
            <li><i class="fas fa-chevron-right text-xs"></i></li>
            <li class="text-accent font-medium"><?php echo htmlspecialchars($product['product_name']); ?></li>
        </ol>
    </nav>

    <!-- Product Detail -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white rounded-lg shadow-lg p-8 mb-8">
        <!-- Product Image -->
        <div>
            <?php 
            $image_path = !empty($product['image']) 
                ? '../assets/images/products/' . $product['image'] 
                : 'https://via.placeholder.com/600x600?text=No+Image';
            ?>
            <img src="<?php echo $image_path; ?>" 
                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                 class="w-full rounded-lg shadow-md">
        </div>

        <!-- Product Info -->
        <div>
            <!-- Category Badge -->
            <span class="inline-block bg-secondary text-white px-4 py-1 rounded-full text-sm font-medium mb-4">
                <?php echo $product['category_name']; ?>
            </span>

            <!-- Product Name -->
            <h1 class="text-3xl font-bold text-accent mb-4">
                <?php echo htmlspecialchars($product['product_name']); ?>
            </h1>

            <!-- Rating Summary -->
            <?php if ($rating_stats['total_reviews'] > 0): ?>
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center">
                        <?php 
                        $avg_rating = round($rating_stats['avg_rating'], 1);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="fas fa-star <?php echo $i <= $avg_rating ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-lg font-bold text-accent"><?php echo number_format($avg_rating, 1); ?></span>
                    <span class="text-gray-600">(<?php echo $rating_stats['total_reviews']; ?> ulasan)</span>
                    <a href="#reviews-section" class="text-primary hover:text-accent text-sm">Lihat semua ulasan</a>
                </div>
            <?php endif; ?>

            <!-- Price -->
            <div class="mb-6">
                <span class="text-4xl font-bold text-primary">
                    <?php echo formatRupiah($product['price']); ?>
                </span>
            </div>

            <!-- Stock Status -->
            <div class="mb-6">
                <?php if ($product['stock'] > 0): ?>
                    <div class="flex items-center text-green-600">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span class="font-medium">Stok Tersedia: <?php echo $product['stock']; ?> unit</span>
                    </div>
                <?php else: ?>
                    <div class="flex items-center text-red-600">
                        <i class="fas fa-times-circle mr-2"></i>
                        <span class="font-medium">Stok Habis</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-accent mb-3">Deskripsi Produk</h3>
                <p class="text-gray-700 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>
            </div>

            <!-- Quantity Selector -->
            <?php if (isLoggedIn() && !isAdmin() && $product['stock'] > 0): ?>
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-2">Jumlah</label>
                    <div class="flex items-center gap-3">
                        <button onclick="decreaseQty()" class="w-10 h-10 bg-gray-200 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>"
                               class="w-20 text-center border-2 border-beige-dark rounded-lg py-2 font-bold">
                        <button onclick="increaseQty()" class="w-10 h-10 bg-gray-200 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="flex gap-4">
                <?php if (isLoggedIn() && !isAdmin()): ?>
                    <?php if ($product['stock'] > 0): ?>
                        <button onclick="addToCart(<?php echo $product_id; ?>)" 
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

    <!-- REVIEWS SECTION -->
    <div id="reviews-section" class="bg-white rounded-lg shadow-lg p-8 mb-8">
        <h2 class="text-2xl font-bold text-accent mb-6">
            <i class="fas fa-star mr-2"></i>Ulasan Produk
        </h2>

        <?php if ($rating_stats['total_reviews'] > 0): ?>
            <!-- Rating Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8 pb-8 border-b border-gray-200">
                <!-- Average Rating -->
                <div class="text-center">
                    <div class="text-5xl font-bold text-accent mb-2">
                        <?php echo number_format($rating_stats['avg_rating'], 1); ?>
                    </div>
                    <div class="flex justify-center mb-2">
                        <?php 
                        $avg_rating = round($rating_stats['avg_rating']);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="fas fa-star text-2xl <?php echo $i <= $avg_rating ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-gray-600"><?php echo $rating_stats['total_reviews']; ?> ulasan</p>
                </div>

                <!-- Rating Breakdown -->
                <div class="lg:col-span-2 space-y-2">
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = $rating_stats["rating_$i"];
                        $percentage = $rating_stats['total_reviews'] > 0 
                            ? ($count / $rating_stats['total_reviews']) * 100 
                            : 0;
                    ?>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1 w-24">
                                <span class="text-sm font-medium"><?php echo $i; ?></span>
                                <i class="fas fa-star text-yellow-400 text-sm"></i>
                            </div>
                            <div class="flex-1 h-4 bg-gray-200 rounded-full overflow-hidden">
                                <div class="rating-bar h-full bg-yellow-400 transition-all duration-500" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-600 w-16 text-right"><?php echo $count; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Write Review Button -->
        <?php if ($can_review): ?>
            <div class="mb-8">
                <button onclick="showReviewModal()" class="btn-primary-custom px-6 py-3 rounded-lg">
                    <i class="fas fa-edit mr-2"></i>Tulis Ulasan
                </button>
            </div>
        <?php elseif (isLoggedIn() && !isAdmin()): ?>
        <?php endif; ?>

        <!-- Reviews List -->
        <?php if ($reviews->num_rows > 0): ?>
            <div class="space-y-6">
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-card border border-gray-200 rounded-lg p-6 hover:shadow-md transition">
                        <div class="flex items-start gap-4">
                            <!-- User Avatar -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center text-xl font-bold">
                                    <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                                </div>
                            </div>

                            <!-- Review Content -->
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="font-bold text-accent"><?php echo htmlspecialchars($review['user_name']); ?></h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="flex">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-sm <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-sm text-gray-500">
                                                <?php echo formatDate($review['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700 leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">Belum ada ulasan untuk produk ini</p>
                <?php if ($can_review): ?>
                    <p class="text-gray-500 mt-2">Jadilah yang pertama memberikan ulasan!</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Related Products -->
    <?php if ($related_products->num_rows > 0): ?>
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-accent mb-6">Produk Terkait</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($related = $related_products->fetch_assoc()): ?>
                    <div class="card-custom product-card">
                        <?php 
                        $rel_image_path = !empty($related['image']) 
                            ? '../assets/images/products/' . $related['image'] 
                            : 'https://via.placeholder.com/400x300?text=No+Image';
                        ?>
                        <img src="<?php echo $rel_image_path; ?>" 
                             alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                             class="product-image">
                        
                        <div class="product-body">
                            <h3 class="product-title">
                                <?php echo htmlspecialchars($related['product_name']); ?>
                            </h3>
                            <div class="product-price mb-4">
                                <?php echo formatRupiah($related['price']); ?>
                            </div>
                            <a href="product-detail.php?id=<?php echo $related['product_id']; ?>" 
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

<!-- Review Modal -->
<?php if ($can_review): ?>
<div id="reviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-accent">Tulis Ulasan Produk</h3>
                <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form id="reviewForm" onsubmit="submitReview(event)">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <input type="hidden" name="order_id" value="<?php echo $user_purchased_order['order_id']; ?>">

                <!-- Product Info -->
                <div class="bg-light-bg rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-4">
                        <img src="<?php echo $image_path; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                             class="w-20 h-20 object-cover rounded-lg">
                        <div>
                            <h4 class="font-bold text-accent"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $product['category_name']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Rating -->
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-3">
                        Rating <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2" id="starRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="rating-star text-4xl text-gray-300 hover:text-yellow-400 transition"
                                    data-rating="<?php echo $i; ?>"
                                    onclick="setRating(<?php echo $i; ?>)">
                                <i class="fas fa-star"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <p class="text-sm text-gray-500 mt-2" id="ratingText">Pilih rating Anda</p>
                </div>

                <!-- Review Text -->
                <div class="mb-6">
                    <label class="block text-accent font-medium mb-2">
                        Ulasan Anda <span class="text-red-500">*</span>
                    </label>
                    <textarea name="review_text" 
                              required
                              rows="5"
                              minlength="10"
                              maxlength="1000"
                              class="form-control-custom w-full"
                              placeholder="Ceritakan pengalaman Anda dengan produk ini... (minimal 10 karakter)"></textarea>
                    <p class="text-xs text-gray-500 mt-1">10 - 1000 karakter</p>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-3">
                    <button type="button" 
                            onclick="closeReviewModal()"
                            class="flex-1 bg-gray-200 hover:bg-gray-300 py-3 rounded-lg font-medium transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="flex-1 btn-primary-custom py-3 rounded-lg font-medium">
                        <i class="fas fa-paper-plane mr-2"></i>Kirim Ulasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const maxStock = <?php echo $product['stock']; ?>;

function increaseQty() {
    const qtyInput = document.getElementById('quantity');
    let currentQty = parseInt(qtyInput.value);
    if (currentQty < maxStock) {
        qtyInput.value = currentQty + 1;
    }
}

function decreaseQty() {
    const qtyInput = document.getElementById('quantity');
    let currentQty = parseInt(qtyInput.value);
    if (currentQty > 1) {
        qtyInput.value = currentQty - 1;
    }
}

function addToCart(productId) {
    if (!productId) {
        showError('ID produk tidak valid');
        return;
    }
    const quantity = parseInt(document.getElementById('quantity').value);
    if (isNaN(quantity) || quantity < 1) {
        showError('Jumlah tidak valid');
        return;
    }
    
    Swal.fire({
        title: 'Menambahkan ke keranjang...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('<?php echo BASE_URL; ?>api/cart-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof updateCartBadge === 'function' && data.cart_count !== undefined) {
                updateCartBadge(data.cart_count);
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message,
                confirmButtonColor: '#7A6A54',
                confirmButtonText: 'Lihat Keranjang',
                showCancelButton: true,
                cancelButtonText: 'Lanjut Belanja',
                cancelButtonColor: '#6B7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo BASE_URL; ?>customer/cart.php';
                } else {
                    document.getElementById('quantity').value = 1;
                }
            });
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        Swal.close();
        showError('Terjadi kesalahan saat menambahkan ke keranjang');
    });
}

<?php if ($can_review): ?>
let selectedRating = 0;

function showReviewModal() {
    document.getElementById('reviewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('reviewForm').reset();
    resetRating();
}

function setRating(rating) {
    selectedRating = rating;
    document.getElementById('ratingInput').value = rating;
    
    const stars = document.querySelectorAll('.rating-star');
    const ratingTexts = ['', 'Sangat Buruk', 'Buruk', 'Cukup', 'Baik', 'Sangat Baik'];
    
    stars.forEach((star, index) => {
        const starIcon = star.querySelector('i');
        if (index < rating) {
            starIcon.classList.remove('text-gray-300');
            starIcon.classList.add('text-yellow-400');
        } else {
            starIcon.classList.remove('text-yellow-400');
            starIcon.classList.add('text-gray-300');
        }
    });
    
    document.getElementById('ratingText').textContent = ratingTexts[rating];
}

function resetRating() {
    selectedRating = 0;
    document.getElementById('ratingInput').value = '';
    document.getElementById('ratingText').textContent = 'Pilih rating Anda';
    
    const stars = document.querySelectorAll('.rating-star i');
    stars.forEach(star => {
        star.classList.remove('text-yellow-400');
        star.classList.add('text-gray-300');
    });
}

function submitReview(event) {
    event.preventDefault();
    
    if (selectedRating === 0) {
        showError('Silakan pilih rating terlebih dahulu');
        return;
    }
    
    const formData = new FormData(event.target);
    
    Swal.fire({
        title: 'Mengirim ulasan...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('<?php echo BASE_URL; ?>api/submit-review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message,
                confirmButtonColor: '#7A6A54'
            }).then(() => {
                location.reload();
            });
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        Swal.close();
        showError('Terjadi kesalahan saat mengirim ulasan');
    });
}
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>