<?php
// includes/ui.php - potongan tampilan yang dipakai berulang (ikon, tombol aksi, rangka aplikasi)

function ikon($nama) {
    $p = [
        'edit'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'hapus'   => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'awas'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'pin'     => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'kotak'   => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'daftar'  => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'kisi'    => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'gedung'  => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
        'bintang' => '<path d="M12 3l2.4 6.6L21 12l-6.6 2.4L12 21l-2.4-6.6L3 12l6.6-2.4z"/>',
        'keluar'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'cari'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bel'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'tambah'  => '<path d="M12 5v14M5 12h14"/>',
        'centang' => '<path d="M20 6 9 17l-5-5"/>',
        'titik3'  => '<circle cx="12" cy="5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="19" r="1.3"/>',
        'jam'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$nama] ?? '') . '</svg>';
}

// Tombol aksi untuk satu aset. Dipakai di tabel dan di kartu 3D.
function aksi_aset($row, $admin, $laporanPerAset) {
    $punya = !$admin && isset($laporanPerAset[$row['id']]);
    ob_start();
    ?>
    <div class="aksi-group">
        <?php if ($punya): ?>
            <a class="aksi edit" data-zoom="blue" href="edit_laporan.php?id=<?= (int) $laporanPerAset[$row['id']]; ?>">Ubah laporan</a>
            <button type="button" class="aksi icon del" title="Hapus laporan" aria-label="Hapus laporan"
                    data-hapus="laporan" data-id="<?= (int) $laporanPerAset[$row['id']]; ?>" data-nama="<?= e($row['nama_barang']); ?>"><?= ikon('hapus'); ?></button>
        <?php else: ?>
            <a class="aksi" data-zoom="red" href="laporkan.php?asset_id=<?= (int) $row['id']; ?>"><?= ikon('awas'); ?> Lapor rusak</a>
        <?php endif; ?>
        <?php if ($admin): ?>
            <a class="aksi icon edit" data-zoom="blue" href="edit.php?id=<?= (int) $row['id']; ?>" title="Edit" aria-label="Edit"><?= ikon('edit'); ?></a>
            <button type="button" class="aksi icon del" title="Hapus" aria-label="Hapus"
                    data-hapus="aset" data-id="<?= (int) $row['id']; ?>" data-nama="<?= e($row['nama_barang']); ?>"><?= ikon('hapus'); ?></button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Rangka aplikasi: sidebar gelap + panel abu-abu berisi isi halaman.
// $opsi: cari (bool), notif (int), perhatian (int), modal_aset (bool)
function buka_shell($aktif = '', $opsi = []) {
    $GLOBALS['shell_terbuka'] = true;
    $u = user_login();
    $admin = is_admin();
    $cariAtas = !empty($opsi['cari']);
    $notif = (int) ($opsi['notif'] ?? 0);
    $perhatian = (int) ($opsi['perhatian'] ?? 0);
    $modalAset = !empty($opsi['modal_aset']);
    $inisial = $u ? strtoupper(mb_substr($u['nama'], 0, 1)) : '?';
    ?>
<div class="app">
    <aside class="sidebar" id="navLinks">
        <a class="logo" href="index.php">
            <span class="logo-tanda"><?= ikon('kotak'); ?></span>
            <span>inventaris</span>
        </a>

        <nav class="menu">
            <a href="index.php" class="<?= $aktif === 'aset' ? 'active' : ''; ?>"><?= ikon('kisi'); ?> Dashboard
                <?php if ($perhatian > 0): ?><span class="lencana"><?= $perhatian; ?></span><?php endif; ?></a>
            <a href="index.php#daftar" data-no-zoom><?= ikon('kotak'); ?> Aset</a>
            <?php if ($admin): ?>
                <a href="ruangan.php" class="<?= $aktif === 'ruangan' ? 'active' : ''; ?>"><?= ikon('gedung'); ?> Ruangan</a>
            <?php endif; ?>
            <a href="laporkan.php" class="<?= $aktif === 'lapor' ? 'active' : ''; ?>"><?= ikon('awas'); ?> Lapor kerusakan</a>
            <a href="index.php?intro=1"><?= ikon('bintang'); ?> Intro</a>
        </nav>

        <div class="promo">
            <svg class="promo-gambar" viewBox="0 0 120 90" aria-hidden="true">
                <circle cx="28" cy="62" r="18" fill="#b9a8f5"/>
                <circle cx="86" cy="38" r="26" fill="#17181b"/>
                <circle cx="60" cy="68" r="12" fill="#fff" opacity=".9"/>
                <g transform="rotate(-12 60 34)">
                    <rect x="44" y="18" width="34" height="30" rx="8" fill="#d6f25a" stroke="#17181b" stroke-width="2.5"/>
                    <path d="M44 31h34" stroke="#17181b" stroke-width="2.5"/>
                </g>
            </svg>
            <?php if ($admin): ?>
                <b>Aset baru?</b>
                <p>Catat barang dan ruangannya supaya inventaris selalu rapi.</p>
                <?php if ($modalAset): ?>
                    <button type="button" class="promo-tombol" data-buka="modalAset">Tambah aset</button>
                <?php else: ?>
                    <a class="promo-tombol" href="index.php?tambah=1">Tambah aset</a>
                <?php endif; ?>
            <?php else: ?>
                <b>Ada yang rusak?</b>
                <p>Laporkan barang yang rusak agar cepat ditangani.</p>
                <a class="promo-tombol" data-zoom="red" href="laporkan.php">Lapor sekarang</a>
            <?php endif; ?>
        </div>

        <a class="menu-keluar" data-zoom="ink" href="logout.php"><?= ikon('keluar'); ?> Keluar</a>
    </aside>

    <div class="panel">
        <header class="topbar">
            <button type="button" class="hamburger" id="navToggle" aria-label="Buka menu"><?= ikon('menu'); ?></button>
            <div class="pengguna">
                <span class="avatar"><?= e($inisial); ?></span>
                <div>
                    <b><?= e($u['nama'] ?? ''); ?></b>
                    <small><?= $admin ? 'Administrator' : 'Pengguna'; ?> &middot; @<?= e($u['username'] ?? ''); ?></small>
                </div>
            </div>
            <span class="ruang"></span>
            <?php if ($cariAtas): ?>
            <label class="cari-atas">
                <?= ikon('cari'); ?>
                <input type="search" id="cari" placeholder="Cari kode, barang, ruangan..." autocomplete="off">
            </label>
            <?php endif; ?>
            <?php if (isset($opsi['notif'])): ?>
            <a class="lonceng" href="index.php#laporan" data-no-zoom title="Laporan terbuka" aria-label="Laporan terbuka">
                <?= ikon('bel'); ?>
                <?php if ($notif > 0): ?><i><?= $notif; ?></i><?php endif; ?>
            </a>
            <?php endif; ?>
            <a class="tombol-keluar" data-zoom="ink" href="logout.php" title="Keluar" aria-label="Keluar"><?= ikon('keluar'); ?><span>Keluar</span></a>
        </header>
<?php
}

function tutup_shell() {
    if (!empty($GLOBALS['shell_terbuka'])) {
        echo "    </div>\n</div>\n";
        $GLOBALS['shell_terbuka'] = false;
    }
}