<footer class="footer-custom mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- About -->
            <div>
                <h3 class="text-lg font-bold mb-4"><?php echo SITE_NAME; ?></h3>
                <p class="text-gray-300 text-sm">
                    <?php echo SITE_TAGLINE; ?>. Kami menyediakan berbagai jenis kostum dan jasa makeup terbaik untuk kebutuhan Anda.
                </p>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h3 class="text-lg font-bold mb-4">Menu Cepat</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="<?php echo BASE_URL; ?>index.php" class="text-gray-300 hover:text-white transition">
                            <i class="fas fa-home mr-2"></i>Beranda
                        </a>
                    </li>
                    <?php if (isLoggedIn() && !isAdmin()): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>customer/orders.php" class="text-gray-300 hover:text-white transition">
                                <i class="fas fa-box mr-2"></i>Pesanan Saya
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>customer/cart.php" class="text-gray-300 hover:text-white transition">
                                <i class="fas fa-shopping-cart mr-2"></i>Keranjang
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Contact -->
            <div>
                <h3 class="text-lg font-bold mb-4">Kontak Kami</h3>
                <ul class="space-y-2 text-sm text-gray-300">
                    <li>
                        <i class="fas fa-phone mr-2"></i>
                        +62 812-3456-7890
                    </li>
                    <li>
                        <i class="fas fa-envelope mr-2"></i>
                        info@charitize.com
                    </li>
                    <li>
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        Jakarta, Indonesia
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-sm text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Flowbite JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>

</body>
</html>