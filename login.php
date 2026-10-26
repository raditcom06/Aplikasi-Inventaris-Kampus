<?php
include 'koneksi.php';
require 'includes/auth.php';

if (user_login()) redirect('index.php');

$error = '';
$username = '';

if (isset($_POST['masuk'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $s = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
        mysqli_stmt_bind_param($s, "s", $username);
        mysqli_stmt_execute($s);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));

        if ($row && password_verify($password, $row['password'])) {
            login_user($row);
            $_SESSION['intro'] = true; // tampilkan intro 3D sekali setelah login
            flash_set('Selamat datang, ' . $row['nama'] . '!');
            redirect('index.php');
        }
        $error = 'Username atau password salah.';
    } catch (Throwable $err) {
        $error = 'Tabel users belum ada. Jalankan setup.php dulu.';
    }
}

$judul = 'Masuk - Inventaris Kampus';
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
        <h1 class="auth-title">Masuk</h1>
        <p class="auth-sub">Masuk untuk melihat inventaris dan melaporkan kerusakan.</p>

        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username); ?>" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw">
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-pw="password" aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" name="masuk" class="btn">Masuk</button>
        </form>
        <p class="auth-alt">Belum punya akun? <a href="register.php">Daftar</a></p>
    </section>
</main>

<?php include 'includes/footer.php'; ?>