
<?php
session_start();

require_once "../config/database.php";

// Pastikan hanya owner yang bisa mengunduh laporan.
if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'owner') {

    header("Location: ../login.php");
    exit;
}

// Atur nama dan format file CSV.
$filename = "laporan_pesanan_kopipiro_" . date("Y-m-d") . ".csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// Membuat file CSV untuk diunduh.
$output = fopen("php://output", "w");

// BOM agar karakter UTF-8 terbaca dengan baik di Excel.
fwrite($output, "\xEF\xBB\xBF");

// Header kolom.
fputcsv($output, [
    'ID Pesanan',
    'Nama Pelanggan',
    'Nama Kasir',
    'Tanggal Pesanan',
    'Subtotal',
    'Diskon',
    'Total',
    'Status'
], ';');

// Ambil seluruh pesanan.
$query = mysqli_query(
    $conn,
    "SELECT orders.*,
            users.name AS customer_name,
            cashiers.name AS cashier_name
     FROM orders
     LEFT JOIN users
        ON orders.user_id = users.id
     LEFT JOIN users AS cashiers
        ON orders.cashier_id = cashiers.id
     ORDER BY orders.created_at DESC"
);

// Masukkan data ke dalam CSV.
while ($order = mysqli_fetch_assoc($query)) {

    fputcsv($output, [
        $order['id'],
        $order['customer_name'] ?? 'Pelanggan Tamu',
        $order['cashier_name'] ?? 'Belum ditugaskan',
        $order['created_at'],
        $order['subtotal'],
        $order['discount'],
        $order['total'],
        $order['status']
    ], ';');
}

fclose($output);
exit;
