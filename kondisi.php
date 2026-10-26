<?php
// includes/kondisi.php
// Kondisi sebuah aset = yang TERBURUK antara kondisi dasar (diatur admin)
// dan semua laporan yang masih terbuka. Jadi kalau laporan diubah atau dihapus,
// kondisi aset di daftar inventaris ikut berubah.

function peringkat_kondisi($k) {
    $p = ['Baik' => 0, 'Rusak Ringan' => 1, 'Rusak Berat' => 2];
    return $p[$k] ?? 0;
}

function hitung_ulang_kondisi($conn, $asset_id) {
    $asset_id = (int) $asset_id;

    $s = mysqli_prepare($conn, "SELECT kondisi_dasar FROM assets WHERE id = ?");
    mysqli_stmt_bind_param($s, "i", $asset_id);
    mysqli_stmt_execute($s);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    if (!$row) return;

    $hasil = $row['kondisi_dasar'];

    $q = mysqli_prepare($conn, "SELECT kondisi_dilaporkan FROM damage_reports WHERE asset_id = ? AND status = 'terbuka'");
    mysqli_stmt_bind_param($q, "i", $asset_id);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    while ($r = mysqli_fetch_assoc($res)) {
        if (peringkat_kondisi($r['kondisi_dilaporkan']) > peringkat_kondisi($hasil)) {
            $hasil = $r['kondisi_dilaporkan'];
        }
    }

    $u = mysqli_prepare($conn, "UPDATE assets SET kondisi = ? WHERE id = ?");
    mysqli_stmt_bind_param($u, "si", $hasil, $asset_id);
    mysqli_stmt_execute($u);
}