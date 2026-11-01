<?php
// Header + navigasi bersama. Halaman di subfolder set $base = "../" dulu.
$base       = $base ?? "";
$pageTitle  = $pageTitle ?? "Kopipiro Coffee Shop";
$role       = $_SESSION['user_role'] ?? null;
$isLoggedIn = isset($_SESSION['user_id']);
$current    = basename($_SERVER['SCRIPT_NAME']);

$cartCount = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
    ? array_sum($_SESSION['cart'])
    : 0;

// Ambil pesan flash, atau $message dari halaman.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (!empty($message)) {
    $flash = [
        'type' => (stripos($message, 'berhasil') !== false) ? 'success' : 'error',
        'text' => $message,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-content">

        <a href="<?= $base ?>index.php" class="brand-name">Kopipiro<span>.</span></a>

        <nav class="navigation">
            <?php if ($role === 'owner') { ?>
                <a href="<?= $base ?>owner/dashboard.php"
                   class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= $base ?>owner/products.php"
                   class="<?= $current === 'products.php' ? 'active' : '' ?>">Kelola Produk</a>
                <a href="<?= $base ?>index.php"
                   class="<?= $current === 'index.php' ? 'active' : '' ?>">Lihat Menu</a>

            <?php } elseif ($role === 'cashier') { ?>
                <a href="<?= $base ?>cashier/dashboard.php"
                   class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Pesanan Saya</a>

            <?php } else { ?>
                <a href="<?= $base ?>index.php"
                   class="<?= $current === 'index.php' ? 'active' : '' ?>">Beranda</a>
                <a href="<?= $base ?>cart.php"
                   class="<?= in_array($current, ['cart.php', 'checkout.php']) ? 'active' : '' ?>">
                    Keranjang
                    <?php if ($cartCount > 0) { ?>
                        <span class="cart-badge"><?= (int) $cartCount ?></span>
                    <?php } ?>
                </a>
            <?php } ?>
        </nav>

        <div class="header-auth">
            <?php if ($isLoggedIn) { ?>
                <span class="header-user">Halo, <?= e($_SESSION['user_name']) ?></span>
                <a href="<?= $base ?>logout.php" class="btn btn-outline btn-small">Logout</a>
            <?php } else { ?>
                <a href="<?= $base ?>login.php" class="btn btn-outline btn-small">Login</a>
                <a href="<?= $base ?>register.php" class="btn btn-small">Daftar</a>
            <?php } ?>
        </div>

    </div>
</header>

<main class="container">

<?php if ($flash) { ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['text']) ?>
    </div>
<?php } ?>