<?php
session_start();

require_once "../includes/helpers.php";
require_once "../config/database.php";

// Hanya owner yang boleh masuk.
if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'owner') {

    header("Location: ../login.php");
    exit;
}

// ---------- Penugasan pesanan ke kasir ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_order'])) {

    $orderId   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $cashierId = filter_input(INPUT_POST, 'cashier_id', FILTER_VALIDATE_INT);

    if ($orderId && $orderId > 0 && $cashierId && $cashierId > 0) {

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE id = ? AND role = 'cashier'"
        );
        mysqli_stmt_bind_param($check, "i", $cashierId);
        mysqli_stmt_execute($check);
        $cashierOk = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($cashierOk) {
            $update = mysqli_prepare(
                $conn,
                "UPDATE orders SET cashier_id = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($update, "ii", $cashierId, $orderId);

            if (mysqli_stmt_execute($update)) {
                flash('success', 'Pesanan berhasil ditugaskan kepada kasir.');
            } else {
                flash('error', 'Gagal menugaskan pesanan.');
            }
            mysqli_stmt_close($update);
        } else {
            flash('error', 'Akun kasir tidak ditemukan.');
        }
    } else {
        flash('error', 'Data penugasan tidak valid.');
    }

    header("Location: dashboard.php");
    exit;
}

// ---------- Ringkasan penjualan ----------
$totalRevenue = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(total), 0) AS v FROM orders WHERE status = 'completed'"
))['v'];

$totalCompleted = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS v FROM orders WHERE status = 'completed'"
))['v'];

$totalActive = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS v FROM orders WHERE status IN ('pending','processing')"
))['v'];

// Data grafik penjualan per tanggal.
$salesLabels = [];
$salesValues = [];

$querySales = mysqli_query(
    $conn,
    "SELECT DATE(created_at) AS sale_date, SUM(total) AS revenue
     FROM orders
     WHERE status = 'completed'
     GROUP BY DATE(created_at)
     ORDER BY sale_date ASC"
);
while ($sale = mysqli_fetch_assoc($querySales)) {
    $salesLabels[] = $sale['sale_date'];
    $salesValues[] = (float) $sale['revenue'];
}

// Daftar kasir.
$cashiers = [];
$cashierResult = mysqli_query(
    $conn,
    "SELECT id, name FROM users WHERE role = 'cashier' ORDER BY name ASC"
);
while ($c = mysqli_fetch_assoc($cashierResult)) {
    $cashiers[] = $c;
}

// Seluruh pesanan.
$orders = [];
$orderResult = mysqli_query(
    $conn,
    "SELECT orders.*, users.name AS customer_name, cashiers.name AS cashier_name
     FROM orders
     LEFT JOIN users ON orders.user_id = users.id
     LEFT JOIN users AS cashiers ON orders.cashier_id = cashiers.id
     ORDER BY orders.created_at DESC"
);
while ($o = mysqli_fetch_assoc($orderResult)) {
    $orders[] = $o;
}

$base      = "../";
$pageTitle = "Dashboard Owner - Kopipiro";
require "../includes/header.php";
?>

<h1>Dashboard Pemilik</h1>
<p>Selamat datang, <?= e($_SESSION['user_name']) ?>!</p>

<h2>Ringkasan Penjualan</h2>
<div class="sales-summary">
    <div class="summary-card">
        <h3>Total Pendapatan</h3>
        <p><?= rupiah($totalRevenue) ?></p>
    </div>
    <div class="summary-card">
        <h3>Pesanan Selesai</h3>
        <p><?= (int) $totalCompleted ?></p>
    </div>
    <div class="summary-card">
        <h3>Pesanan Aktif</h3>
        <p><?= (int) $totalActive ?></p>
    </div>
</div>

<h2>Grafik Penjualan</h2>
<div class="chart-box">
    <?php if (empty($salesLabels)) { ?>
        <p>Belum ada pesanan selesai untuk ditampilkan di grafik.</p>
    <?php } else { ?>
        <canvas id="salesChart" height="110"></canvas>
    <?php } ?>
</div>

<h2>Ekspor Laporan</h2>
<p>
    <a href="export_orders.php" class="btn">⬇ Download Laporan Excel</a>
    <a href="products.php" class="btn btn-outline">Kelola Produk</a>
</p>

<h2>Seluruh Pesanan</h2>

<?php if (empty($orders)) { ?>

    <p>Belum ada pesanan masuk.</p>

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
                Pelanggan: <strong><?= e($order['customer_name'] ?? 'Pelanggan Tamu') ?></strong> ·
                Kasir: <strong><?= e($order['cashier_name'] ?? 'Belum ditugaskan') ?></strong>
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

            <p>
                Subtotal <?= rupiah($order['subtotal']) ?> ·
                Diskon <?= rupiah($order['discount']) ?> ·
                <strong>Total <?= rupiah($order['total']) ?></strong>
            </p>

            <h4>Tugaskan kepada Kasir</h4>

            <?php if (!empty($cashiers)) { ?>
                <form method="POST" action="dashboard.php" class="order-form">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

                    <select name="cashier_id" required>
                        <option value="">Pilih kasir</option>
                        <?php foreach ($cashiers as $c) { ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= ((int) $order['cashier_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php } ?>
                    </select>

                    <button type="submit" name="assign_order">Simpan Penugasan</button>
                </form>
            <?php } else { ?>
                <p>Belum ada akun kasir yang tersedia.</p>
            <?php } ?>

        </div>

    <?php } ?>
<?php } ?>

<?php if (!empty($salesLabels)) { ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($salesLabels) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode($salesValues) ?>,
            backgroundColor: '#c8894b',
            borderColor: '#6f4e37',
            borderWidth: 1,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function (value) {
                        return 'Rp' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>
<?php } ?>

<?php require "../includes/footer.php"; ?>
