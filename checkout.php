<?php
session_start();

require_once "includes/helpers.php";
require_once "config/database.php";

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message   = "";
$cartItems = [];
$subtotal  = 0;

// Diskon 10% hanya untuk pelanggan yang login.
$isCustomer = isset($_SESSION['user_id'])
    && ($_SESSION['user_role'] ?? '') === 'customer';

// Ambil ulang harga dari database (jangan percaya data dari browser).
foreach ($_SESSION['cart'] as $productId => $quantity) {
    $productId = (int) $productId;
    $quantity  = (int) $quantity;

    if ($productId <= 0 || $quantity <= 0) {
        continue;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, price FROM products WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($product) {
        $product['quantity']      = $quantity;
        $product['item_subtotal'] = $product['price'] * $quantity;

        $cartItems[] = $product;
        $subtotal   += $product['item_subtotal'];
    }
}

// Hitung diskon dan total (setelah subtotal terisi).
$discount = $isCustomer ? round($subtotal * 0.10) : 0;
$total    = $subtotal - $discount;

// Proses tombol "Buat Pesanan".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    if (empty($cartItems)) {
        $message = "Keranjang kosong. Tidak dapat membuat pesanan.";
    } else {
        $userId = $isCustomer ? (int) $_SESSION['user_id'] : null;

        try {
            mysqli_begin_transaction($conn);

            $stmtOrder = mysqli_prepare(
                $conn,
                "INSERT INTO orders (user_id, subtotal, discount, total, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            mysqli_stmt_bind_param($stmtOrder, "iddd", $userId, $subtotal, $discount, $total);
            mysqli_stmt_execute($stmtOrder);
            $orderId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmtOrder);

            $stmtItem = mysqli_prepare(
                $conn,
                "INSERT INTO order_items
                 (order_id, product_id, product_name, price, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($cartItems as $item) {
                if ($item['quantity'] < 1 || $item['quantity'] > 99) {
                    throw new Exception("Jumlah produk tidak valid.");
                }

                $pid          = (int) $item['id'];
                $productName  = $item['name'];
                $price        = (float) $item['price'];
                $quantity     = (int) $item['quantity'];
                $itemSubtotal = (float) $item['item_subtotal'];

                mysqli_stmt_bind_param(
                    $stmtItem, "iisdid",
                    $orderId, $pid, $productName, $price, $quantity, $itemSubtotal
                );
                mysqli_stmt_execute($stmtItem);
            }

            mysqli_stmt_close($stmtItem);
            mysqli_commit($conn);

            $_SESSION['cart'] = [];

            header("Location: checkout.php?success=1");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = "Pesanan gagal disimpan. Silakan coba lagi.";
        }
    }
}

$pageTitle = "Checkout - Kopipiro";
require "includes/header.php";
?>

<h1>Checkout</h1>

<?php if (isset($_GET['success'])) { ?>

    <div class="panel" style="text-align:center">
        <h2>🎉 Pesanan berhasil dibuat!</h2>
        <p>Terima kasih sudah memesan di Kopipiro.</p>
        <p>Pesananmu sudah tersimpan dan akan segera diproses oleh kasir kami.</p>
        <a href="index.php" class="btn">Kembali ke Menu</a>
    </div>

<?php } elseif (empty($cartItems)) { ?>

    <div class="panel" style="text-align:center">
        <p>Keranjang kamu masih kosong.</p>
        <a href="index.php" class="btn">Kembali ke Menu</a>
    </div>

<?php } else { ?>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
                <?php foreach ($cartItems as $item) { ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= rupiah($item['price']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= rupiah($item['item_subtotal']) ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <div class="summary-line">
            <span>Subtotal</span>
            <span><?= rupiah($subtotal) ?></span>
        </div>

        <div class="summary-line <?= $isCustomer ? 'discount' : '' ?>">
            <span>Diskon <?= $isCustomer ? '10%' : '0%' ?></span>
            <span>- <?= rupiah($discount) ?></span>
        </div>

        <div class="summary-total">
            <span>Total Pembayaran</span>
            <span><?= rupiah($total) ?></span>
        </div>

        <?php if (!$isCustomer) { ?>
            <p style="margin-top:14px">
                💡 <a href="login.php">Login</a> atau
                <a href="register.php">daftar</a> sebagai pelanggan untuk
                mendapat diskon 10%.
            </p>
        <?php } ?>

        <form action="checkout.php" method="POST" class="actions">
            <a href="cart.php" class="btn btn-outline">← Kembali ke Keranjang</a>
            <button type="submit" name="place_order">Buat Pesanan</button>
        </form>
    </div>

<?php } ?>

<?php require "includes/footer.php"; ?>