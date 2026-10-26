<?php
// includes/skema.php - memastikan kolom yang dibutuhkan ada. Berjalan otomatis sekali per sesi.

function skema_laporan($conn) {
    $hasil = [];

    // tabel => [ kolom => [SQL tambah kolom, SQL lanjutan (opsional)] ]
    $rencana = [
        'damage_reports' => [
            'id'                 => ["ALTER TABLE damage_reports ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST", null],
            'user_id'            => ["ALTER TABLE damage_reports ADD COLUMN user_id INT NULL AFTER asset_id", null],
            'kondisi_dilaporkan' => ["ALTER TABLE damage_reports ADD COLUMN kondisi_dilaporkan VARCHAR(20) NOT NULL DEFAULT 'Rusak Ringan'", null],
            'created_at'         => ["ALTER TABLE damage_reports ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", null],
            // laporan lama (sebelum ada pelacakan) dianggap sudah selesai supaya tidak mengubah kondisi aset
            'status'             => ["ALTER TABLE damage_reports ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'terbuka'",
                                     "UPDATE damage_reports SET status = 'selesai'"],
        ],
        'assets' => [
            // kondisi dasar awal = kondisi aset saat ini
            'kondisi_dasar'      => ["ALTER TABLE assets ADD COLUMN kondisi_dasar VARCHAR(20) NOT NULL DEFAULT 'Baik'",
                                     "UPDATE assets SET kondisi_dasar = kondisi"],
        ],
    ];

    foreach ($rencana as $tabel => $kolomRencana) {
        try {
            $ada = [];
            $res = mysqli_query($conn, "SHOW COLUMNS FROM $tabel");
            while ($r = mysqli_fetch_assoc($res)) { $ada[$r['Field']] = true; }
        } catch (Throwable $err) {
            $hasil[] = ['gagal', "Tabel <b>$tabel</b> tidak bisa dibaca: " . htmlspecialchars($err->getMessage())];
            continue;
        }

        foreach ($kolomRencana as $nama => $sql) {
            if (isset($ada[$nama])) {
                $hasil[] = ['ok', "$tabel.<b>$nama</b> sudah ada"];
                continue;
            }
            try {
                mysqli_query($conn, $sql[0]);
                if ($sql[1]) mysqli_query($conn, $sql[1]);
                $hasil[] = ['ok', "$tabel.<b>$nama</b> berhasil ditambahkan"];
            } catch (Throwable $err) {
                $hasil[] = ['gagal', "$tabel.<b>$nama</b> gagal: " . htmlspecialchars($err->getMessage())];
            }
        }
    }
    return $hasil;
}

function pastikan_skema($conn) {
    if (!empty($_SESSION['skema_v2'])) return;
    $semua_ok = true;
    foreach (skema_laporan($conn) as $h) { if ($h[0] !== 'ok') $semua_ok = false; }
    if ($semua_ok) $_SESSION['skema_v2'] = true;
}