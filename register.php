<?php
session_start();

require_once "includes/helpers.php";
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $message = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid.";
    } elseif (strlen($password) < 8) {
        $message = "Kata sandi minimal 8 karakter.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $message = "Email sudah terdaftar.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmtInsert = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, 'customer')"
            );
            mysqli_stmt_bind_param($stmtInsert, "sss", $name, $email, $hashed);

            if (mysqli_stmt_execute($stmtInsert)) {
                $message = "Registrasi berhasil. Silakan login.";
            } else {
                $message = "Registrasi gagal. Silakan coba lagi.";
            }
            mysqli_stmt_close($stmtInsert);
        }
        mysqli_stmt_close($stmt);
    }
}

$pageTitle = "Daftar - Kopipiro";
require "includes/header.php";
?>

<div class="panel auth-card">
    <h1>Daftar Akun</h1>
    <p style="text-align:center;color:var(--muted)">Daftar dan nikmati diskon 10% setiap pesanan</p>

    <form action="register.php" method="POST">
        <div class="form-row">
            <label for="name">Nama</label>
            <input type="text" id="name" name="name" maxlength="100" required>
        </div>

        <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="100" required>
        </div>

        <div class="form-row">
            <label for="password">Kata sandi (min. 8 karakter)</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>

        <button type="submit">Daftar</button>
    </form>

    <p class="auth-links">Sudah punya akun? <a href="login.php">Login</a></p>
</div>

<?php require "includes/footer.php"; ?>