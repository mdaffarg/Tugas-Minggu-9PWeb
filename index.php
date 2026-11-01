<?php
session_start();

require_once "includes/helpers.php";
require_once "config/database.php";

$isLoggedIn = isset($_SESSION['user_id']);
$isCustomer = $isLoggedIn && ($_SESSION['user_role'] ?? '') === 'customer';
$selected   = trim($_GET['kategori'] ?? '');

// Daftar kategori untuk tombol filter.
$categories = [];
$catResult = mysqli_query(
    $conn,
    "SELECT DISTINCT category FROM products
     WHERE category <> '' ORDER BY category ASC"
);
while ($row = mysqli_fetch_assoc($catResult)) {
    $categories[] = $row['category'];
}

// Ambil produk (dengan atau tanpa filter kategori).
if ($selected !== '') {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM products WHERE category = ? ORDER BY name ASC"
    );
    mysqli_stmt_bind_param($stmt, "s", $selected);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query(
        $conn,
        "SELECT * FROM products ORDER BY category ASC, name ASC"
    );
}

$pageTitle = "Kopipiro Coffee Shop";
require "includes/header.php";
?>

<section class="hero-section">
    <div class="hero-content">
        <p class="hero-label">WELCOME TO KOPIPIRO</p>
        <h1>Temukan Rasa Favoritmu, <span>di Setiap Tegukan.</span></h1>
        <p class="hero-description">
            Nikmati pilihan kopi, minuman non-kafein, dan makanan
            favorit yang dibuat untuk menemani harimu.
        </p>
        <a href="#menu" class="hero-button">Jelajahi Menu</a>
    </div>
</section>

<?php if (!in_array($_SESSION['user_role'] ?? '', ['owner', 'cashier'], true)) { ?>
<section>
    <div class="account-info">
        <?php if ($isCustomer) { ?>
            <div>
                <h3>Halo, <?= e($_SESSION['user_name']) ?>!</h3>
                <p class="discount">Diskon pelanggan 10% otomatis berlaku di checkout.</p>
            </div>
        <?php } else { ?>
            <div>
                <h3>Selamat berbelanja!</h3>
                <p class="discount">Login atau daftar untuk mendapatkan diskon 10%.</p>
            </div>
            <div class="account-actions">
                <a href="login.php" class="btn btn-outline btn-small">Login</a>
                <a href="register.php" class="btn btn-small">Daftar Akun</a>
            </div>
        <?php } ?>
    </div>
</section>
<?php } ?>

<section id="menu">

    <div class="section-heading">
        <p class="section-label">OUR COLLECTION</p>
        <h2>Menu Kopipiro</h2>
        <p>Pilih menu favoritmu dan nikmati setiap sajiannya.</p>
    </div>

    <div class="filter-bar">
        <a href="index.php#menu"
           class="filter-chip <?= $selected === '' ? 'active' : '' ?>">Semua</a>

        <?php foreach ($categories as $cat) { ?>
            <a href="index.php?kategori=<?= urlencode($cat) ?>#menu"
               class="filter-chip <?= $selected === $cat ? 'active' : '' ?>">
                <?= e($cat) ?>
            </a>
        <?php } ?>
    </div>

    <div class="product-container">

        <?php if ($result && mysqli_num_rows($result) > 0) { ?>
            <?php while ($product = mysqli_fetch_assoc($result)) { ?>

                <article class="product-card">

                    <div class="product-image-wrapper">
                        <?php if (!empty($product['image'])) { ?>
                            <img src="uploads/<?= rawurlencode($product['image']) ?>"
                                 alt="<?= e($product['name']) ?>"
                                 class="product-image">
                        <?php } else { ?>
                            <div class="product-image-placeholder">☕</div>
                        <?php } ?>
                    </div>

                    <div class="product-content">
                        <p class="product-category"><?= e($product['category']) ?></p>
                        <h3><?= e($product['name']) ?></h3>
                        <p class="product-description"><?= e($product['description']) ?></p>

                        <div class="product-details">
                            <p class="product-price">
                                <?= rupiah($product['price']) ?>
                                <?php if ($isCustomer) { ?>
                                    <small>Harga kamu: <?= rupiah($product['price'] * 0.9) ?></small>
                                <?php } ?>
                            </p>
                            <p class="product-rating">
                                ★ <?= number_format((float) $product['rating'], 1) ?>/5
                            </p>
                        </div>

                        <form action="cart.php" method="POST" class="product-form">
                            <input type="hidden" name="product_id"
                                   value="<?= (int) $product['id'] ?>">

                            <div class="quantity-group">
                                <label for="qty-<?= (int) $product['id'] ?>">Jumlah</label>
                                <input type="number" id="qty-<?= (int) $product['id'] ?>"
                                       name="quantity" value="1" min="1" max="99" required>
                            </div>

                            <button type="submit" name="add_to_cart"
                                    class="add-to-cart-button">+ Keranjang</button>
                        </form>
                    </div>

                </article>

            <?php } ?>
        <?php } else { ?>

            <div class="empty-message">
                <h3>Belum Ada Produk</h3>
                <p>Belum ada produk pada kategori ini. Silakan kembali lagi nanti.</p>
            </div>

        <?php } ?>

    </div>
</section>

<?php require "includes/footer.php"; ?>