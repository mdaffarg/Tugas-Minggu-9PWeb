<?php
session_start();

require_once "../includes/helpers.php";
require_once "../config/database.php";

// Hanya owner yang boleh mengelola produk.
if (!isset($_SESSION['user_id'])
    || ($_SESSION['user_role'] ?? '') !== 'owner') {

    header("Location: ../login.php");
    exit;
}

$message    = "";
$categories = ['Kopi', 'Non Kopi', 'Makanan'];
$uploadDir  = __DIR__ . "/../uploads/";

// Validasi dan simpan gambar. Mengembalikan [namaFile|null, pesanError|null].
function uploadImage(array $file, string $dir): array
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, "Gagal mengunggah gambar."];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [null, "Ukuran gambar maksimal 2 MB."];
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    $info = @getimagesize($file['tmp_name']);

    if (!$info || !isset($allowed[$info[2]])) {
        return [null, "Format gambar harus JPG, PNG, atau WEBP."];
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Nama acak agar tidak bentrok dan aman.
    $name = bin2hex(random_bytes(8)) . "." . $allowed[$info[2]];

    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return [null, "Gagal menyimpan gambar ke folder uploads."];
    }

    return [$name, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- SIMPAN (tambah / ubah) ----------
    if (isset($_POST['save_product'])) {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $category    = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $rating      = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_FLOAT);

        if ($name === '' || !in_array($category, $categories, true)) {
            $message = "Nama dan kategori produk wajib diisi.";
        } elseif ($price === false || $price < 0) {
            $message = "Harga tidak valid.";
        } elseif ($rating === false || $rating < 0 || $rating > 5) {
            $message = "Rating harus antara 0 sampai 5.";
        } else {
            [$newImage, $uploadError] = uploadImage($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $uploadDir);

            if ($uploadError !== null) {
                $message = $uploadError;
            } elseif ($id === 0 && $newImage === null) {
                $message = "Gambar produk wajib diunggah.";
            } elseif ($id === 0) {
                // TAMBAH produk baru
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO products (name, category, description, price, rating, image)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, "sssdds", $name, $category, $description, $price, $rating, $newImage);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                flash('success', 'Produk berhasil ditambahkan.');
                header("Location: products.php");
                exit;
            } else {
                // UBAH produk
                if ($newImage !== null) {
                    // Hapus gambar lama.
                    $old = mysqli_prepare($conn, "SELECT image FROM products WHERE id = ?");
                    mysqli_stmt_bind_param($old, "i", $id);
                    mysqli_stmt_execute($old);
                    $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($old));
                    mysqli_stmt_close($old);

                    if (!empty($oldRow['image'])) {
                        @unlink($uploadDir . basename($oldRow['image']));
                    }

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE products SET name=?, category=?, description=?,
                         price=?, rating=?, image=? WHERE id=?"
                    );
                    mysqli_stmt_bind_param($stmt, "sssddsi", $name, $category, $description, $price, $rating, $newImage, $id);
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE products SET name=?, category=?, description=?,
                         price=?, rating=? WHERE id=?"
                    );
                    mysqli_stmt_bind_param($stmt, "sssddi", $name, $category, $description, $price, $rating, $id);
                }

                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                flash('success', 'Produk berhasil diperbarui.');
                header("Location: products.php");
                exit;
            }
        }
    }

    // ---------- HAPUS ----------
    if (isset($_POST['delete_product'])) {
        $id = (int) ($_POST['id'] ?? 0);

        $sel = mysqli_prepare($conn, "SELECT image FROM products WHERE id = ?");
        mysqli_stmt_bind_param($sel, "i", $id);
        mysqli_stmt_execute($sel);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));
        mysqli_stmt_close($sel);

        if ($row) {
            $del = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
            mysqli_stmt_bind_param($del, "i", $id);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            if (!empty($row['image'])) {
                @unlink($uploadDir . basename($row['image']));
            }
            flash('success', 'Produk berhasil dihapus.');
        } else {
            flash('error', 'Produk tidak ditemukan.');
        }

        header("Location: products.php");
        exit;
    }
}

// Mode edit: isi form dengan data produk.
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $editId);
    mysqli_stmt_execute($stmt);
    $editing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$products = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");

$base      = "../";
$pageTitle = "Kelola Produk - Kopipiro";
require "../includes/header.php";
?>

<h1>Kelola Produk</h1>
<p>Tambah produk baru, unggah gambar, dan atur harga serta kategori.</p>

<div class="admin-grid">

    <!-- FORM TAMBAH / EDIT -->
    <div class="panel">
        <h3><?= $editing ? 'Ubah Produk' : 'Tambah Produk' ?></h3>

        <form method="POST" action="products.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

            <div class="form-row">
                <label for="name">Nama produk</label>
                <input type="text" id="name" name="name" maxlength="100" required
                       value="<?= e($editing['name'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label for="category">Kategori</label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $cat) { ?>
                        <option value="<?= e($cat) ?>"
                            <?= (($editing['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-row">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <label for="price">Harga (Rp)</label>
                <input type="number" id="price" name="price" min="0" step="500" required
                       value="<?= e($editing['price'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label for="rating">Rating (0 - 5)</label>
                <input type="number" id="rating" name="rating" min="0" max="5" step="0.1" required
                       value="<?= e($editing['rating'] ?? '4.5') ?>">
            </div>

            <div class="form-row">
                <label for="image">
                    Gambar (JPG/PNG/WEBP, maks 2 MB)
                    <?= $editing ? '— kosongkan jika tidak diganti' : '' ?>
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       <?= $editing ? '' : 'required' ?>>
            </div>

            <div class="actions">
                <button type="submit" name="save_product">
                    <?= $editing ? 'Simpan Perubahan' : 'Tambah Produk' ?>
                </button>
                <?php if ($editing) { ?>
                    <a href="products.php" class="btn btn-outline">Batal</a>
                <?php } ?>
            </div>
        </form>
    </div>

    <!-- DAFTAR PRODUK -->
    <div class="panel">
        <h3>Daftar Produk</h3>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Gambar</th>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Rating</th>
                    <th>Aksi</th>
                </tr>

                <?php while ($p = mysqli_fetch_assoc($products)) { ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['image'])) { ?>
                                <img class="thumb" src="../uploads/<?= rawurlencode($p['image']) ?>" alt="">
                            <?php } else { ?>
                                <div class="thumb" style="text-align:center;line-height:52px">☕</div>
                            <?php } ?>
                        </td>
                        <td>
                            <strong><?= e($p['name']) ?></strong><br>
                            <small><?= e($p['category']) ?></small>
                        </td>
                        <td><?= rupiah($p['price']) ?></td>
                        <td>★ <?= number_format((float) $p['rating'], 1) ?></td>
                        <td>
                            <div class="order-form">
                                <a href="products.php?edit=<?= (int) $p['id'] ?>"
                                   class="btn btn-outline btn-small">Ubah</a>

                                <form method="POST" action="products.php" style="margin:0"
                                      onsubmit="return confirm('Hapus produk ini?');">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" name="delete_product"
                                            class="btn-danger btn-small">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>

</div>

<?php require "../includes/footer.php"; ?>