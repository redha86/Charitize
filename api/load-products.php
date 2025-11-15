<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$conn = getConnection();

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where_conditions = ["p.status = 'active'"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

$count_where = str_replace("p.", "", $where_clause);
$count_query = "SELECT COUNT(*) as total FROM products WHERE $count_where";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmt->close();

$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          WHERE $where_clause 
          ORDER BY p.created_at DESC 
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$category_name = 'Semua Produk';
if ($category > 0) {
    $cat_query = "SELECT category_name FROM categories WHERE category_id = ?";
    $stmt = $conn->prepare($cat_query);
    $stmt->bind_param("i", $category);
    $stmt->execute();
    $cat_result = $stmt->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        $category_name = $cat_row['category_name'];
    }
    $stmt->close();
}

ob_start();
if ($products->num_rows > 0) {
    while ($product = $products->fetch_assoc()) {
        $image_path = !empty($product['image']) 
            ? BASE_URL . 'assets/images/products/' . $product['image'] 
            : 'https://via.placeholder.com/400x300?text=No+Image';
        ?>
        <div class="card-custom product-card">
            <!-- Product Image -->
            <div class="relative overflow-hidden">
                <img src="<?php echo $image_path; ?>" 
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                     class="product-image">
                
                <!-- Stock Badge -->
                <?php if ($product['stock'] > 0): ?>
                    <span class="absolute top-3 right-3 badge-stock">
                        <i class="fas fa-box mr-1"></i>Stok: <?php echo $product['stock']; ?>
                    </span>
                <?php else: ?>
                    <span class="absolute top-3 right-3 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium">
                        Stok Habis
                    </span>
                <?php endif; ?>
            </div>

            <!-- Product Body -->
            <div class="product-body">
                <!-- Category -->
                <span class="text-xs text-secondary font-medium">
                    <?php echo $product['category_name']; ?>
                </span>
                
                <!-- Product Name -->
                <h3 class="product-title">
                    <?php echo htmlspecialchars($product['product_name']); ?>
                </h3>
                
                <!-- Description -->
                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                    <?php echo htmlspecialchars(substr($product['description'], 0, 80)) . '...'; ?>
                </p>
                
                <!-- Price -->
                <div class="product-price mb-4">
                    <?php echo formatRupiah($product['price']); ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-2">
                    <a href="customer/product-detail.php?id=<?php echo $product['product_id']; ?>" 
                       class="flex-1 btn-secondary-custom text-center py-2 rounded-lg text-white text-sm">
                        <i class="fas fa-eye mr-1"></i>Detail
                    </a>
                    
                    <?php if (isLoggedIn() && !isAdmin() && $product['stock'] > 0): ?>
                        <button onclick="addToCart(<?php echo $product['product_id']; ?>)" 
                                class="flex-1 btn-primary-custom py-2 rounded-lg text-white text-sm">
                            <i class="fas fa-cart-plus mr-1"></i>Keranjang
                        </button>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="auth/login.php" 
                           class="flex-1 btn-primary-custom text-center py-2 rounded-lg text-white text-sm">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    ?>
    <div class="col-span-full text-center py-16">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-accent mb-2">Produk tidak ditemukan</h3>
        <p class="text-gray-600 mb-4">Coba kata kunci lain atau lihat semua produk</p>
        <a href="<?php echo BASE_URL; ?>index.php" class="btn-primary-custom px-6 py-2 rounded-lg inline-block">
            Lihat Semua Produk
        </a>
    </div>
    <?php
}
$products_html = ob_get_clean();

ob_start();
?>
<div>
    <h2 class="text-2xl font-bold text-accent">
        <?php if (!empty($search)): ?>
            Hasil pencarian "<?php echo htmlspecialchars($search); ?>"
        <?php else: ?>
            <?php echo $category_name; ?>
        <?php endif; ?>
    </h2>
    <p class="text-gray-600 mt-1"><?php echo $total_products; ?> produk ditemukan</p>
</div>
<?php
$results_info = ob_get_clean();

ob_start();
if ($total_pages > 1) {
    ?>
    <nav class="flex gap-2">
        <?php if ($page > 1): ?>
            <a href="#" data-page="<?php echo $page - 1; ?>" class="pagination-link px-4 py-2 bg-white border border-beige-dark rounded-lg hover:bg-light-bg transition">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="px-4 py-2 bg-primary text-white rounded-lg"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="#" data-page="<?php echo $i; ?>" class="pagination-link px-4 py-2 bg-white border border-beige-dark rounded-lg hover:bg-light-bg transition">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="#" data-page="<?php echo $page + 1; ?>" class="pagination-link px-4 py-2 bg-white border border-beige-dark rounded-lg hover:bg-light-bg transition">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </nav>
    <?php
}
$pagination_html = ob_get_clean();

echo json_encode([
    'success' => true,
    'products_html' => $products_html,
    'results_info' => $results_info,
    'pagination_html' => $pagination_html,
    'total_products' => $total_products,
    'total_pages' => $total_pages,
    'current_page' => $page
]);
?>