<?php
include 'koneksi.php';
require 'includes/auth.php';

if (user_login()) redirect('index.php');

$error = '';
$nama = $username = '';

if (isset($_POST['daftar'])) {
    $nama      = trim($_POST['nama']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi'];

    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) {
        $error = 'Nama harus 2-100 karakter.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter, hanya huruf, angka, dan garis bawah.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $s = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
            mysqli_stmt_bind_param($s, "s", $username);
            mysqli_stmt_execute($s);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($s))) {
                $error = 'Username sudah dipakai.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Akun yang mendaftar SELALU berperan "user". Admin hanya dibuat lewat setup.php.
                $s = mysqli_prepare($conn, "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, 'user')");
                mysqli_stmt_bind_param($s, "sss", $nama, $username, $hash);
                mysqli_stmt_execute($s);
                flash_set('Akun berhasil dibuat. Silakan masuk.');
                redirect('login.php');
            }
        } catch (Throwable $err) {
            $error = 'Pendaftaran gagal. Pastikan setup.php sudah dijalankan.';
        }
    }
}

$judul = 'Daftar - Inventaris Kampus';
$body_class = 'page-auth';
$tanpa_nav = true;
include 'includes/header.php';
?>

<main class="auth-wrap">
    <section class="card auth-card">
        <div class="auth-brand">
            <span class="brand-logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></span>
            <strong>Inventaris Kampus</strong>
        </div>
        <h1 class="auth-title">Buat akun</h1>
        <p class="auth-sub">Akun baru berperan sebagai user: bisa melihat inventaris dan melapor kerusakan.</p>

        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="nama">Nama lengkap</label>
                <input type="text" id="nama" name="nama" value="<?= e($nama); ?>" required autofocus>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username); ?>" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw">
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="6" required>
                    <button type="button" class="pw-toggle" data-pw="password" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="field">
                <label for="konfirmasi">Ulangi password</label>
                <input type="password" id="konfirmasi" name="konfirmasi" autocomplete="new-password" required>
            </div>
            <button type="submit" name="daftar" class="btn">Daftar</button>
        </form>
        <p class="auth-alt">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </section>
</main>

<?php include 'includes/footer.php'; ?>