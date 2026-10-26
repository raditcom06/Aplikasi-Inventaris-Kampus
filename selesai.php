<?php
// selesai.php - admin menandai laporan kerusakan sudah selesai ditangani
include 'koneksi.php';
require 'includes/auth.php';
wajib_admin();
pastikan_skema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
$id = (int) ($_POST['id'] ?? 0);
$mulai = false;

try {
    $s = mysqli_prepare($conn, "SELECT asset_id FROM damage_reports WHERE id = ?");
    mysqli_stmt_bind_param($s, "i", $id);
    mysqli_stmt_execute($s);
    $lap = mysqli_fetch_assoc(mysqli_stmt_get_result($s));

    if (!$lap) {
        flash_set('Laporan tidak ditemukan.', 'error');
    } else {
        mysqli_begin_transaction($conn);
        $mulai = true;
        $u = mysqli_prepare($conn, "UPDATE damage_reports SET status = 'selesai' WHERE id = ?");
        mysqli_stmt_bind_param($u, "i", $id);
        mysqli_stmt_execute($u);
        hitung_ulang_kondisi($conn, (int) $lap['asset_id']);
        mysqli_commit($conn);
        flash_set('Laporan ditandai selesai, kondisi barang diperbarui');
    }
} catch (Throwable $err) {
    if ($mulai) mysqli_rollback($conn);
    flash_set('Gagal memperbarui laporan', 'error');
}
redirect('index.php#laporan');