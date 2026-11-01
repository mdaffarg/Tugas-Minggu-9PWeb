<?php
session_start();

require_once "includes/helpers.php";
require_once "config/database.php";

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Tambah produk ----
    if (isset($_POST['add_to_cart'])) {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity  = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if ($productId && $productId > 0
            && $quantity && $quantity >= 1 && $quantity <= 99) {

            $check = mysqli_prepare($conn, "SELECT id FROM products WHERE id = ?");
            mysqli_stmt_bind_param($check, "i", $productId);
            mysqli_stmt_execute($check);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            mysqli_stmt_close($check);

            if ($exists) {
                $current = $_SESSION['cart'][$productId] ?? 0;

                if ($current + $quantity <= 99) {
                    $_SESSION['cart'][$productId] = $current + $quantity;
                    flash('success', 'Produk berhasil ditambahkan ke keranjang.');
                } else {
                    flash('error', 'Jumlah produk dalam keranjang maksimal 99.');
                }
            } else {
                flash('error', 'Produk tidak ditemukan.');
            }
        } else {
            flash('error', 'ID produk atau jumlah tidak valid.');
        }
    }

    // ---- Ubah jumlah ----
    if (isset($_POST['update_cart']) && isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $qty) {
            $id  = (int) $id;
            $qty = (int) $qty;

            if (!isset($_SESSION['cart'][$id])) {
                continue;
            }

            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } elseif ($qty <= 99) {
                $_SESSION['cart'][$id] = $qty;
            }
        }
        flash('success', 'Keranjang berhasil diperbarui.');
    }

    // ---- Hapus item ----
    if (isset($_POST['remove_item'])) {
        unset($_SESSION['cart'][(int) $_POST['remove_item']]);
        flash('success', 'Produk berhasil dihapus dari keranjang.');
    }

    // Cegah form dikirim ulang saat halaman di-refresh.
    header("Location: cart.php");
    exit;
}

// Ambil data produk di keranjang.
$cartItems = [];
$total = 0;

foreach ($_SESSION['cart'] as $productId => $quantity) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, price, image FROM products WHERE id = ?"
    );
    $productId = (int) $productId;
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($product) {
        $product['quantity'] = (int) $quantity;
        $product['subtotal'] = $product['price'] * $quantity;
        $cartItems[] = $product;
        $total += $product['subtotal'];
    } else {
        unset($_SESSION['cart'][$productId]); // produk sudah dihapus owner
    }
}

$isCustomer = ($_SESSION['user_role'] ?? '') === 'customer';

$pageTitle = "Keranjang - Kopipiro";
require "includes/header.php";
?>

<h1>Keranjang Belanja</h1>

<?php if (empty($cartItems)) { ?>

    <div class="panel" style="text-align:center">
        <p>Keranjang kamu masih kosong.</p>
        <a href="index.php" class="btn">Lihat Menu</a>
    </div>

<?php } else { ?>

    <div class="panel">
        <form method="POST" action="cart.php">
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>

                    <?php foreach ($cartItems as $item) { ?>
                        <tr>
                            <td>
                                <?= e($item['name']) ?>
                            </td>
                            <td><?= rupiah($item['price']) ?></td>
                            <td>
                                <input type="number" name="qty[<?= (int) $item['id'] ?>]"
                                       value="<?= $item['quantity'] ?>" min="0" max="99">
                            </td>
                            <td><?= rupiah($item['subtotal']) ?></td>
                            <td>
                                <button type="submit" form="removeForm"
                                        name="remove_item" value="<?= (int) $item['id'] ?>"
                                        class="btn-danger btn-small">Hapus</button>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

            <div class="actions">
                <button type="submit" name="update_cart" class="btn-outline">
                    Perbarui Keranjang
                </button>
            </div>
        </form>

        <!-- Form terpisah untuk tombol Hapus (dihubungkan lewat atribut form=) -->
        <form id="removeForm" method="POST" action="cart.php"></form>

        <div class="summary-total">
            <span>Subtotal</span>
            <span><?= rupiah($total) ?></span>
        </div>

        <?php if ($isCustomer) { ?>
            <p class="discount">Diskon pelanggan 10% akan dihitung di halaman checkout.</p>
        <?php } else { ?>
            <p>Login sebagai pelanggan untuk mendapat diskon 10%.
               <a href="login.php">Login</a></p>
        <?php } ?>

        <div class="actions">
            <a href="index.php" class="btn btn-outline">← Lanjut Belanja</a>
            <a href="checkout.php" class="btn">Lanjut ke Checkout →</a>
        </div>
    </div>

<?php } ?>

<?php require "includes/footer.php"; ?>