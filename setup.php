<?php
// setup.php - JALANKAN SEKALI, lalu HAPUS FILE INI dari folder proyek.
include 'koneksi.php';
require 'includes/auth.php';

$error = '';
$selesai = false;
$nama = $username = '';

try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','user') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $n = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin'"))['n'];
    $selesai = $n > 0;
} catch (Throwable $err) {
    $error = 'Gagal membuat tabel users: ' . $err->getMessage();
}

// Admin pertama hanya bisa dibuat kalau belum ada admin sama sekali
if (!$selesai && !$error && isset($_POST['buat_admin'])) {
    $nama       = trim($_POST['nama']);
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];

    if (mb_strlen($nama) < 2) {
        $error = 'Nama minimal 2 karakter.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter, hanya huruf, angka, dan garis bawah.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s = mysqli_prepare($conn, "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, 'admin')");
            mysqli_stmt_bind_param($s, "sss", $nama, $username, $hash);
            mysqli_stmt_execute($s);
            flash_set('Admin berhasil dibuat. Sekarang hapus file setup.php lalu masuk.');
            redirect('login.php');
        } catch (Throwable $err) {
            $error = 'Gagal membuat admin. Username mungkin sudah dipakai.';
        }
    }
}

$judul = 'Setup - Inventaris Kampus';
$body_class = 'page-auth';
$tanpa_nav = true;
include 'includes/header.php';
?>

<main class="auth-wrap">
    <section class="card auth-card">
        <h1 class="auth-title">Setup awal</h1>

        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <?php if ($selesai): ?>
            <p class="auth-sub">Tabel <b>users</b> sudah siap dan akun admin sudah ada. Demi keamanan, <b>hapus file setup.php</b> dari folder proyek.</p>
            <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none">Ke halaman masuk</a>
        <?php elseif (!$error || isset($_POST['buat_admin'])): ?>
            <p class="auth-sub">Tabel <b>users</b> sudah dibuat. Buat akun admin pertama di bawah ini.</p>
            <form method="POST">
                <div class="field"><label for="nama">Nama admin</label><input type="text" id="nama" name="nama" value="<?= e($nama); ?>" required autofocus></div>
                <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= e($username); ?>" required></div>
                <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" minlength="6" required></div>
                <button type="submit" name="buat_admin" class="btn">Buat admin</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>