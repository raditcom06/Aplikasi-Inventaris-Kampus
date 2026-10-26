<?php
// migrasi.php - menjalankan pembaruan tabel secara manual dan menampilkan hasilnya.
// (Pembaruan juga berjalan otomatis, halaman ini hanya untuk melihat statusnya.)
include 'koneksi.php';
require 'includes/auth.php';
wajib_admin();

$hasil = skema_laporan($conn);

$judul = 'Migrasi Database';
$body_class = 'page-auth';
$tanpa_nav = true;
include 'includes/header.php';
?>

<main class="auth-wrap">
    <section class="card auth-card">
        <h1 class="auth-title">Migrasi database</h1>
        <p class="auth-sub">Status tabel <b>damage_reports</b> untuk fitur laporan milik sendiri.</p>
        <ul class="migrasi">
            <?php foreach ($hasil as $h): ?>
                <li class="<?= $h[0]; ?>"><?= $h[1]; ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="index.php" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:20px">Kembali ke inventaris</a>
    </section>
</main>

<?php include 'includes/footer.php'; ?>