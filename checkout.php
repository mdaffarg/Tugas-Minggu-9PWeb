<?php
session_start();
require_once "config/database.php";

if (empty($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    header("Location: cart.php");
    exit;
}

$items = [];
$subtotal = 0;
foreach ($_SESSION["cart"] as $productId => $storedQuantity) {
    $productId = (int)$productId;
    $qty = max(1, min(99, (int)$storedQuantity));
    $stmt = mysqli_prepare($conn, "SELECT id, name, price FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        unset($_SESSION["cart"][$productId]);
        continue;
    }

    $row["quantity"] = $qty;
    $row["line_total"] = (float)$row["price"] * $qty;
    $subtotal += $row["line_total"];
    $items[] = $row;
}
if (!$items) {
    header("Location: cart.php");
    exit;
}

$isCustomer = isset($_SESSION["user_id"]) && ($_SESSION["user_role"] ?? "") === "customer";
$discount = $isCustomer ? round($subtotal * 0.10, 2) : 0;
$total = $subtotal - $discount;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {
    if (!$items) {
        $error = "Keranjang kosong atau produk tidak tersedia.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $userId = $isCustomer ? (int)$_SESSION["user_id"] : null;
            $status = "pending";
            $orderStmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, cashier_id, subtotal, discount, total, status) VALUES (?, NULL, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($orderStmt, "iddds", $userId, $subtotal, $discount, $total, $status);
            if (!mysqli_stmt_execute($orderStmt)) throw new Exception("Gagal menyimpan pesanan.");
            $orderId = mysqli_insert_id($conn);
            mysqli_stmt_close($orderStmt);

            $itemStmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $productId = (int)$item["id"];
                $productName = $item["name"];
                $price = (float)$item["price"];
                $quantity = (int)$item["quantity"];
                $lineTotal = (float)$item["line_total"];
                mysqli_stmt_bind_param($itemStmt, "iisdid", $orderId, $productId, $productName, $price, $quantity, $lineTotal);
                if (!mysqli_stmt_execute($itemStmt)) throw new Exception("Gagal menyimpan detail pesanan.");
            }
            mysqli_stmt_close($itemStmt);
            mysqli_commit($conn);
            unset($_SESSION["cart"]);
            $_SESSION["last_order_id"] = $orderId;
            header("Location: checkout.php?success=1");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = "Pesanan belum berhasil disimpan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - Kopipiro</title><link rel="stylesheet" href="assets/css/style.css"></head><body>
<header class="topbar"><a class="brand" href="index.php">Kopipiro<span>.</span><small>COFFEE & BITES</small></a><nav class="nav-links"><a href="index.php">Beranda</a><a href="cart.php">Keranjang</a></nav></header>
<main class="page-wrap">
<?php if (isset($_GET["success"])): ?>
    <div class="success-state"><div class="empty-icon">✓</div><h1>Pesanan berhasil dibuat!</h1><p>Terima kasih telah memesan di Kopipiro. Simpan nomor pesananmu: <strong>#<?= (int)($_SESSION["last_order_id"] ?? 0) ?></strong>.</p><a class="button button-primary" href="index.php">Kembali ke Beranda</a></div>
<?php else: ?>
    <div class="page-title"><span class="eyebrow">FINAL STEP</span><h1>Checkout</h1><p>Pastikan rincian pesananmu sudah benar.</p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div><?php endif; ?>
    <div class="checkout-layout">
        <section class="panel"><h2>Rincian Produk</h2>
            <?php foreach ($items as $item): ?>
                <div class="checkout-item"><div><strong><?= htmlspecialchars($item["name"], ENT_QUOTES, "UTF-8") ?></strong><p><?= (int)$item["quantity"] ?> × Rp<?= number_format((float)$item["price"], 0, ",", ".") ?></p></div><strong>Rp<?= number_format($item["line_total"], 0, ",", ".") ?></strong></div>
            <?php endforeach; ?>
        </section>
        <aside class="panel order-summary"><h2>Ringkasan Pembayaran</h2>
            <div class="summary-row"><span>Subtotal</span><strong>Rp<?= number_format($subtotal, 0, ",", ".") ?></strong></div>
            <div class="summary-row"><span>Diskon <?= $isCustomer ? "(10%)" : "" ?></span><strong class="discount-text">− Rp<?= number_format($discount, 0, ",", ".") ?></strong></div>
            <?php if (!$isCustomer): ?><p class="muted small">Kamu berbelanja sebagai tamu. Login sebagai customer untuk mendapatkan diskon 10% pada pesanan berikutnya.</p><?php endif; ?>
            <div class="summary-total"><span>Total pembayaran</span><strong>Rp<?= number_format($total, 0, ",", ".") ?></strong></div>
            <form method="POST"><button class="button button-primary button-block" type="submit" name="place_order">Buat Pesanan</button></form>
            <a class="text-link centered" href="cart.php">Kembali ke keranjang</a>
        </aside>
    </div>
<?php endif; ?>
</main><footer class="footer">© <?= date("Y") ?> Kopipiro Coffee Shop.</footer></body></html>
