<?php
$page_title = "Keranjang Belanja";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

requireLogin();

if (isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil semua item cart user
$stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items_result = $stmt->get_result();
$stmt->close();

$cartData = [];
$total = 0;

// Looping cart items
while ($item = $cart_items_result->fetch_assoc()) {
    if ($item['type'] === 'kostum') {
        // Ambil data kostum
        $stmt2 = $conn->prepare("SELECT nama_kostum AS name, deskripsi AS description, harga_sewa AS price, foto AS image FROM kostum WHERE id = ?");
        $stmt2->bind_param("i", $item['product_id']);
        $stmt2->execute();
        $product = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        // Ambil stok & ukuran dari kostum_variasi
        $stmt3 = $conn->prepare("SELECT ukuran, stok FROM kostum_variasi WHERE id = ?");
        $stmt3->bind_param("i", $item['variasi_id']);
        $stmt3->execute();
        $variasi = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();

        $cartData[] = [
            'cart_id' => $item['cart_id'],
            'type' => 'kostum',
            'quantity' => $item['quantity'],
            'product_name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'], // foto kostum
            'ukuran' => $variasi['ukuran'],
            'max_stok' => $variasi['stok']
        ];

    } elseif ($item['type'] === 'makeup') {
    // Ambil data layanan makeup dan jadwal
    $stmt2 = $conn->prepare("
        SELECT lm.nama_layanan AS name, lm.id_kategori_makeup AS category, lm.durasi AS duration,
               jm.tanggal, jm.jam_mulai, jm.jam_selesai 
        FROM layanan_makeup lm 
        JOIN jadwal_makeup jm ON jm.id = ? 
        WHERE lm.id = ?
    ");
    $stmt2->bind_param("ii", $item['jadwal_id'], $item['product_id']);
    $stmt2->execute();
    $product = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    // Ambil harga dari tabel kategori_makeup sesuai kategori
    $stmt3 = $conn->prepare("SELECT harga FROM kategori_makeup WHERE id = ?");
    $stmt3->bind_param("i", $product['category']); // category adalah id kategori
    $stmt3->execute();
    $kategori = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();
    $price = isset($kategori['harga']) ? (float)$kategori['harga'] : 0;

    // Ambil foto dari tabel foto_layanan_makeup
    $stmt4 = $conn->prepare("SELECT path_foto FROM foto_layanan_makeup WHERE id_layanan_makeup = ? LIMIT 1");
    $stmt4->bind_param("i", $item['product_id']);
    $stmt4->execute();
    $foto = $stmt4->get_result()->fetch_assoc();
    $stmt4->close();

    $cartData[] = [
        'cart_id' => $item['cart_id'],
        'type' => 'makeup',
        'quantity' => $item['quantity'],
        'product_name' => $product['name'],
        'price' => $price, // harga dari kategori_makeup
        'image' => $foto ? $foto['path_foto'] : null,
        'jadwal' => [
            'tanggal' => $product['tanggal'],
            'jam_mulai' => $product['jam_mulai'],
            'jam_selesai' => $product['jam_selesai']
        ]
    ];
}
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-accent">
            <i class="fas fa-shopping-cart mr-2"></i>Keranjang Belanja
        </h1>
        <p class="text-gray-600 mt-2">Pilih produk yang ingin Anda beli</p>
    </div>

    <?php if (count($cartData) > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-2 focus:ring-primary cursor-pointer" onchange="toggleSelectAll(this)">
                        <span class="ml-3 font-medium text-accent">Pilih Semua Produk</span>
                    </label>
                </div>

                <div class="space-y-4">
                    <?php foreach ($cartData as $item):
                        $subtotal = $item['price'] * $item['quantity'];
                        $total += $subtotal;
                        $image_path = $item['image'] 
                            ? ($item['type'] === 'kostum' 
                                ? '../assets/images/kostum/' . $item['image'] 
                                : '../assets/images/makeup/' . $item['image']) 
                            : 'https://via.placeholder.com/150?text=No+Image';
                    ?>
                    <div class="bg-white rounded-lg shadow-md p-6" id="cart-item-<?php echo $item['cart_id']; ?>">
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-shrink-0 flex items-start pt-2">
                                <input type="checkbox" 
                                       class="item-checkbox w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-2 focus:ring-primary cursor-pointer"
                                       data-cart-id="<?php echo $item['cart_id']; ?>"
                                       data-price="<?php echo $item['price']; ?>"
                                       data-quantity="<?php echo $item['quantity']; ?>"
                                       onchange="updateSelectedTotal()">
                            </div>

                            <div class="flex-shrink-0">
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="w-32 h-32 object-cover rounded-lg">
                            </div>

                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-accent mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                <p class="text-gray-600 mb-2">Harga: <span class="font-bold text-primary"><?php echo formatRupiah($item['price']); ?></span></p>
                                <p class="text-sm text-gray-500 mb-2">Tipe: <?php echo ucfirst($item['type']); ?></p>

                                <?php if ($item['type'] === 'kostum'): ?>
                                    <p class="text-sm text-gray-500 mb-2">Ukuran: <span class="font-bold"><?php echo $item['ukuran']; ?></span></p>
                                    <div class="flex items-center gap-3 mb-2">
                                        <input type="number" disabled id="quantity-<?php echo $item['cart_id']; ?>" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['max_stok']; ?>" class="w-20 text-center border-2 border-beige-dark rounded-lg py-2 font-bold" oninput="validateQty(<?php echo $item['cart_id']; ?>)">
                                    </div>
                                <?php elseif ($item['type'] === 'makeup' && $item['jadwal']): ?>
                                    <p class="text-sm text-gray-500 mb-2">Jadwal: <?php echo $item['jadwal']['tanggal'] . ' ' . $item['jadwal']['jam_mulai'] . ' - ' . $item['jadwal']['jam_selesai']; ?></p>
                                <?php endif; ?>

                                <div class="flex justify-end mt-2">
                                    <p class="text-xl font-bold text-primary" id="subtotal-<?php echo $item['cart_id']; ?>"><?php echo formatRupiah($subtotal); ?></p>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <button onclick="deleteCartItem(<?php echo $item['cart_id']; ?>)" class="text-red-500 hover:text-red-700 transition"><i class="fas fa-trash text-xl"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h3 class="text-xl font-bold text-accent mb-6">Ringkasan Pesanan</h3>
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between text-gray-600">
                            <span>Produk Dipilih</span>
                            <span class="font-medium" id="selected-count">0 item</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-medium" id="cart-subtotal">Rp 0</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-accent">Total</span>
                                <span class="text-2xl font-bold text-primary" id="cart-total">Rp 0</span>
                            </div>
                        </div>
                    </div>

                    <button id="checkoutBtn" onclick="proceedToCheckout()" class="block w-full btn-primary-custom text-center py-3 rounded-lg font-medium opacity-50 cursor-not-allowed" disabled>
                        <i class="fas fa-credit-card mr-2"></i>Lanjut ke Pembayaran
                    </button>

                    <p class="text-xs text-center text-gray-500 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>Pilih minimal 1 produk untuk checkout
                    </p>

                    <a href="<?php echo BASE_URL; ?>index.php" class="block text-center mt-4 text-gray-600 hover:text-primary transition">
                        <i class="fas fa-arrow-left mr-2"></i>Lanjut Belanja
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-2xl font-bold text-accent mb-2">Keranjang Kosong</h3>
            <p class="text-gray-600 mb-6">Belum ada produk di keranjang Anda</p>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn-primary-custom px-8 py-3 rounded-lg inline-block">
                <i class="fas fa-shopping-bag mr-2"></i>Mulai Belanja
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function formatRupiah(number) {
    return 'Rp ' + parseInt(number).toLocaleString('id-ID');
}

// Quantity handling
function increaseQty(cartId, max) {
    const input = document.getElementById(`quantity-${cartId}`);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
        updateSubtotal(cartId);
    }
}

function decreaseQty(cartId) {
    const input = document.getElementById(`quantity-${cartId}`);
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
        updateSubtotal(cartId);
    }
}

function validateQty(cartId) {
    const input = document.getElementById(`quantity-${cartId}`);
    const max = parseInt(input.max);
    if (parseInt(input.value) > max) input.value = max;
    if (parseInt(input.value) < 1) input.value = 1;
    updateSubtotal(cartId);
}

function updateSubtotal(cartId) {
    const input = document.getElementById(`quantity-${cartId}`);
    const price = parseInt(document.querySelector(`.item-checkbox[data-cart-id="${cartId}"]`).dataset.price);
    const subtotalElem = document.getElementById(`subtotal-${cartId}`);
    subtotalElem.textContent = formatRupiah(price * parseInt(input.value));

    // Update checkbox data-quantity
    const checkbox = document.querySelector(`.item-checkbox[data-cart-id="${cartId}"]`);
    checkbox.dataset.quantity = input.value;

    updateSelectedTotal();
}

// Checkbox & total handling
function toggleSelectAll(checkbox) {
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    itemCheckboxes.forEach(item => item.checked = checkbox.checked);
    updateSelectedTotal();
}

function updateSelectedTotal() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    const selectAllCheckbox = document.getElementById('selectAll');
    const totalCheckboxes = document.querySelectorAll('.item-checkbox').length;
    const checkoutBtn = document.getElementById('checkoutBtn');

    if (selectedItems.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (selectedItems.length === totalCheckboxes) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }

    let total = 0;
    selectedItems.forEach(checkbox => {
        total += parseInt(checkbox.dataset.price) * parseInt(checkbox.dataset.quantity);
    });

    document.getElementById('selected-count').textContent = `${selectedItems.length} item`;
    document.getElementById('cart-subtotal').textContent = formatRupiah(total);
    document.getElementById('cart-total').textContent = formatRupiah(total);

    if (selectedItems.length > 0) {
        checkoutBtn.disabled = false;
        checkoutBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        checkoutBtn.disabled = true;
        checkoutBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function deleteCartItem(cartId) {
    if (!confirm('Hapus item ini dari keranjang?')) return;

    fetch('<?php echo BASE_URL; ?>api/cart-handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&cart_id=${cartId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const elem = document.getElementById(`cart-item-${cartId}`);
            if (elem) elem.remove();
            updateSelectedTotal();
            window.location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(() => alert('Gagal menghapus item dari keranjang'));
}

function proceedToCheckout() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    if (selectedItems.length === 0) {
        alert('Pilih minimal 1 produk untuk checkout');
        return;
    }
    const selectedCartIds = Array.from(selectedItems).map(cb => cb.dataset.cartId);
    window.location.href = `<?php echo BASE_URL; ?>customer/checkout.php?items=${selectedCartIds.join(',')}`;
}

document.addEventListener('DOMContentLoaded', () => {
    updateSelectedTotal();
});
</script>

<?php require_once '../includes/footer.php'; ?>
