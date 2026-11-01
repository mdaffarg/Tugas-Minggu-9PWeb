<?php
session_start();

require_once "../includes/helpers.php";
require_once "../config/database.php";

// Hanya kasir yang boleh masuk.
if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'cashier') {

    header("Location: ../login.php");
    exit;
}

$cashierId = (int) $_SESSION['user_id'];

// ---------- Ubah status pesanan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $orderId   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['new_status'] ?? '';

    if ($orderId && $orderId > 0
        && in_array($newStatus, ['processing', 'completed'], true)) {

        // Pastikan pesanan memang milik kasir ini.
        $check = mysqli_prepare(
            $conn,
            "SELECT status FROM orders WHERE id = ? AND cashier_id = ?"
        );
        mysqli_stmt_bind_param($check, "ii", $orderId, $cashierId);
        mysqli_stmt_execute($check);
        $order = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if (!$order) {
            flash('error', 'Pesanan tidak ditemukan atau bukan tugas kamu.');
        } else {
            $cur = $order['status'];

            // Urutan status: pending -> processing -> completed
            $valid = ($cur === 'pending' && $newStatus === 'processing')
                  || ($cur === 'processing' && $newStatus === 'completed');

            if ($valid) {
                $update = mysqli_prepare(
                    $conn,
                    "UPDATE orders SET status = ? WHERE id = ? AND cashier_id = ?"
                );
                mysqli_stmt_bind_param($update, "sii", $newStatus, $orderId, $cashierId);

                if (mysqli_stmt_execute($update)) {
                    flash('success', 'Status pesanan berhasil diperbarui.');
                } else {
                    flash('error', 'Gagal memperbarui status pesanan.');
                }
                mysqli_stmt_close($update);
            } else {
                flash('error', 'Perubahan status tidak diperbolehkan.');
            }
        }
    } else {
        flash('error', 'Data perubahan status tidak valid.');
    }

    header("Location: dashboard.php");
    exit;
}

// ---------- Ambil pesanan milik kasir ini saja ----------
$stmt = mysqli_prepare(
    $conn,
    "SELECT orders.*, users.name AS customer_name
     FROM orders
     LEFT JOIN users ON orders.user_id = users.id
     WHERE orders.cashier_id = ?
     ORDER BY orders.created_at DESC"
);
mysqli_stmt_bind_param($stmt, "i", $cashierId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
$count  = ['pending' => 0, 'processing' => 0, 'completed' => 0];

while ($o = mysqli_fetch_assoc($result)) {
    $orders[] = $o;
    if (isset($count[$o['status']])) {
        $count[$o['status']]++;
    }
}
mysqli_stmt_close($stmt);

$base      = "../";
$pageTitle = "Dashboard Kasir - Kopipiro";
require "../includes/header.php";
?>

<h1>Dashboard Kasir</h1>
<p>Selamat datang, <?= e($_SESSION['user_name']) ?>! Berikut pesanan yang ditugaskan kepadamu.</p>

<div class="sales-summary">
    <div class="summary-card">
        <h3>Menunggu</h3>
        <p><?= $count['pending'] ?></p>
    </div>
    <div class="summary-card">
        <h3>Diproses</h3>
        <p><?= $count['processing'] ?></p>
    </div>
    <div class="summary-card">
        <h3>Selesai</h3>
        <p><?= $count['completed'] ?></p>
    </div>
</div>

<h2>Daftar Pesanan Saya</h2>

<?php if (empty($orders)) { ?>

    <div class="panel"><p>Belum ada pesanan yang ditugaskan kepada kamu.</p></div>

<?php } else { ?>
    <?php foreach ($orders as $order) { ?>

        <div class="order-card">

            <div class="order-head">
                <h3>Pesanan #<?= (int) $order['id'] ?></h3>
                <span class="badge <?= e($order['status']) ?>">
                    <?= e(statusLabel($order['status'])) ?>
                </span>
            </div>

            <p class="order-meta">
                <?= e($order['created_at']) ?> ·
                Pelanggan: <strong><?= e($order['customer_name'] ?? 'Pelanggan Tamu') ?></strong>
            </p>

            <h4>Detail Produk</h4>
            <?php
            $oid = (int) $order['id'];
            $itemStmt = mysqli_prepare(
                $conn,
                "SELECT product_name, quantity, subtotal
                 FROM order_items WHERE order_id = ?"
            );
            mysqli_stmt_bind_param($itemStmt, "i", $oid);
            mysqli_stmt_execute($itemStmt);
            $itemResult = mysqli_stmt_get_result($itemStmt);
            ?>
            <ul class="order-items">
                <?php while ($item = mysqli_fetch_assoc($itemResult)) { ?>
                    <li>
                        <?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?>
                        — <?= rupiah($item['subtotal']) ?>
                    </li>
                <?php } ?>
            </ul>
            <?php mysqli_stmt_close($itemStmt); ?>

            <p><strong>Total: <?= rupiah($order['total']) ?></strong></p>

            <?php if ($order['status'] === 'pending') { ?>

                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="new_status" value="processing">
                    <button type="submit" name="update_status">Mulai Memproses</button>
                </form>

            <?php } elseif ($order['status'] === 'processing') { ?>

                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="new_status" value="completed">
                    <button type="submit" name="update_status">Selesaikan Pesanan</button>
                </form>

            <?php } else { ?>

                <p class="discount">✔ Pesanan sudah selesai.</p>

            <?php } ?>

        </div>

    <?php } ?>
<?php } ?>

<?php require "../includes/footer.php"; ?>