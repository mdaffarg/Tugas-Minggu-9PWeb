<?php
session_start();

require_once "includes/helpers.php";
require_once "config/database.php";

// Arahkan pengguna sesuai perannya.
function redirectByRole(string $role): void
{
    if ($role === 'owner') {
        header("Location: owner/dashboard.php");
    } elseif ($role === 'cashier') {
        header("Location: cashier/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// Jika sudah login, tidak perlu ke halaman login lagi.
if (isset($_SESSION['user_id'])) {
    redirectByRole($_SESSION['user_role'] ?? 'customer');
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $message = "Email dan kata sandi wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, email, password, role FROM users WHERE email = ?"
        );
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user["password"])) {
            // Simpan keranjang tamu agar tidak hilang setelah login.
            $cart = $_SESSION['cart'] ?? [];

            session_regenerate_id(true);

            $_SESSION['cart']       = $cart;
            $_SESSION["user_id"]    = $user["id"];
            $_SESSION["user_name"]  = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"]  = $user["role"];

            redirectByRole($user["role"]);
        } else {
            $message = "Email atau kata sandi salah.";
        }
    }
}

$pageTitle = "Login - Kopipiro";
require "includes/header.php";
?>

<div class="panel auth-card">
    <h1>Login</h1>
    <p style="text-align:center;color:var(--muted)">Masuk untuk mendapat diskon 10%</p>

    <form action="login.php" method="POST">
        <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-row">
            <label for="password">Kata sandi</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">Login</button>
    </form>

    <p class="auth-links">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
</div>

<?php require "includes/footer.php"; ?>