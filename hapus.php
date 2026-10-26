<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_login();
pastikan_skema($conn);

// Hapus hanya lewat POST (dari modal konfirmasi)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

$jenis = $_POST['jenis'] ?? '';
$id    = (int) ($_POST['id'] ?? 0);

// Aset dan ruangan khusus admin. Laporan boleh dihapus pemiliknya.
if ($jenis !== 'laporan') wajib_admin();

if ($jenis === 'laporan') {
    $mulai = false;
    try {
        if (is_admin()) {
            $s = mysqli_prepare($conn, "SELECT asset_id FROM damage_reports WHERE id = ?");
            mysqli_stmt_bind_param($s, "i", $id);
        } else {
            $uid = (int) user_login()['id'];
            $s = mysqli_prepare($conn, "SELECT asset_id FROM damage_reports WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($s, "ii", $id, $uid);
        }
        mysqli_stmt_execute($s);
        $lap = mysqli_fetch_assoc(mysqli_stmt_get_result($s));

        if (!$lap) {
            flash_set('Laporan tidak ditemukan atau bukan milik Anda.', 'error');
        } else {
            mysqli_begin_transaction($conn);
            $mulai = true;
            $d = mysqli_prepare($conn, "DELETE FROM damage_reports WHERE id = ?");
            mysqli_stmt_bind_param($d, "i", $id);
            mysqli_stmt_execute($d);
            // Kondisi aset di daftar inventaris dihitung ulang tanpa laporan ini
            hitung_ulang_kondisi($conn, (int) $lap['asset_id']);
            mysqli_commit($conn);
            flash_set('Laporan dihapus, kondisi barang diperbarui');
        }
    } catch (Throwable $err) {
        if ($mulai) mysqli_rollback($conn);
        flash_set('Laporan gagal dihapus', 'error');
    }
    redirect('index.php');
}

if ($jenis === 'aset') {
    mysqli_begin_transaction($conn);
    try {
        $s = mysqli_prepare($conn, "DELETE FROM damage_reports WHERE asset_id = ?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);

        $s = mysqli_prepare($conn, "DELETE FROM assets WHERE id = ?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);

        mysqli_commit($conn);
        flash_set('Aset berhasil dihapus');
    } catch (Throwable $err) {
        mysqli_rollback($conn);
        flash_set('Aset gagal dihapus', 'error');
    }
    redirect('index.php');
}

if ($jenis === 'ruangan') {
    $s = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM assets WHERE room_id = ?");
    mysqli_stmt_bind_param($s, "i", $id);
    mysqli_stmt_execute($s);
    $n = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($s))['n'];

    if ($n > 0) {
        flash_set("Ruangan masih dipakai $n aset. Pindahkan atau hapus asetnya dulu.", 'error');
    } else {
        try {
            $s = mysqli_prepare($conn, "DELETE FROM rooms WHERE id = ?");
            mysqli_stmt_bind_param($s, "i", $id);
            mysqli_stmt_execute($s);
            flash_set('Ruangan berhasil dihapus');
        } catch (Throwable $err) {
            flash_set('Ruangan gagal dihapus', 'error');
        }
    }
    redirect('ruangan.php');
}

redirect('index.php');