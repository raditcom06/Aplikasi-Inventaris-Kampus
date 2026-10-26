<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_login();
$admin = is_admin();
pastikan_skema($conn);

// Proses Tambah Barang (prepared statement biar aman dari SQL injection)
if (isset($_POST['tambah_aset'])) {
    wajib_admin();
    $kode_asset  = trim($_POST['kode_asset']);
    $nama_barang = trim($_POST['nama_barang']);
    $room_id     = (int) $_POST['room_id'];
    $kondisi     = in_array($_POST['kondisi'], ['Baik', 'Rusak Ringan', 'Rusak Berat'], true) ? $_POST['kondisi'] : 'Baik';

    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO assets (kode_asset, nama_barang, room_id, kondisi, kondisi_dasar) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssiss", $kode_asset, $nama_barang, $room_id, $kondisi, $kondisi);
        mysqli_stmt_execute($stmt);
        flash_set('Aset berhasil disimpan');
    } catch (Throwable $err) {
        flash_set('Aset gagal disimpan', 'error');
    }
    redirect('index.php');
}

// ---------- Data ----------
$assets = [];
$res = mysqli_query($conn, "SELECT assets.*, rooms.nama_ruangan, rooms.gedung
                            FROM assets
                            JOIN rooms ON assets.room_id = rooms.id
                            ORDER BY assets.id DESC");
while ($r = mysqli_fetch_assoc($res)) { $assets[] = $r; }

$rooms = [];
$res = mysqli_query($conn, "SELECT * FROM rooms ORDER BY gedung, nama_ruangan");
while ($r = mysqli_fetch_assoc($res)) { $rooms[] = $r; }

$total = count($assets);
$baik = $ringan = $berat = 0;
foreach ($assets as $a) {
    if ($a['kondisi'] === 'Baik') $baik++;
    elseif ($a['kondisi'] === 'Rusak Ringan') $ringan++;
    else $berat++;
}
$perhatian = $ringan + $berat;
$jumlahRuangan = count($rooms);

// Laporan milik user yang login (untuk panel "Laporan saya")
$laporanSaya = [];
$perluMigrasi = false;
if (!$admin) {
    try {
        $uid = (int) user_login()['id'];
        $q = mysqli_prepare($conn, "SELECT dr.id, dr.asset_id, dr.status, dr.deskripsi_kerusakan, dr.kondisi_dilaporkan, dr.created_at, a.nama_barang
                                    FROM damage_reports dr
                                    JOIN assets a ON dr.asset_id = a.id
                                    WHERE dr.user_id = ?
                                    ORDER BY dr.id DESC");
        mysqli_stmt_bind_param($q, "i", $uid);
        mysqli_stmt_execute($q);
        $hasilQ = mysqli_stmt_get_result($q);
        while ($r = mysqli_fetch_assoc($hasilQ)) { $laporanSaya[] = $r; }
    } catch (Throwable $err) {
        $perluMigrasi = true;
    }
}

// Barang yang sudah dilaporkan user ini (laporan terbaru yang masih terbuka)
$laporanPerAset = [];
foreach ($laporanSaya as $lp) {
    if (($lp['status'] ?? 'terbuka') === 'terbuka' && !isset($laporanPerAset[$lp['asset_id']])) {
        $laporanPerAset[$lp['asset_id']] = $lp['id'];
    }
}

// Ringkasan laporan kerusakan: jumlah, 12 bulan terakhir, dan daftar terbuka (admin)
$bulanNama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$laporanTerbuka = 0;
$laporanSelesai = 0;
$seriBulan = [];
$laporanAdmin = [];
for ($i = 11; $i >= 0; $i--) {
    $t = strtotime(date('Y-m-01') . " -$i month");
    $seriBulan[date('Y-m', $t)] = ['label' => $bulanNama[(int) date('n', $t) - 1], 'dilapor' => 0, 'selesai' => 0, 'sekarang' => $i === 0];
}
try {
    $r = mysqli_query($conn, "SELECT status, COUNT(*) AS n FROM damage_reports GROUP BY status");
    while ($x = mysqli_fetch_assoc($r)) {
        if ($x['status'] === 'terbuka') $laporanTerbuka += (int) $x['n'];
        else $laporanSelesai += (int) $x['n'];
    }

    $awalSeri = array_keys($seriBulan)[0] . '-01';
    $s = mysqli_prepare($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, status, COUNT(*) AS n
                                FROM damage_reports WHERE created_at >= ? GROUP BY ym, status");
    mysqli_stmt_bind_param($s, "s", $awalSeri);
    mysqli_stmt_execute($s);
    $hs = mysqli_stmt_get_result($s);
    while ($x = mysqli_fetch_assoc($hs)) {
        if (!isset($seriBulan[$x['ym']])) continue;
        $seriBulan[$x['ym']]['dilapor'] += (int) $x['n'];
        if ($x['status'] === 'selesai') $seriBulan[$x['ym']]['selesai'] += (int) $x['n'];
    }

    if ($admin) {
        $r = mysqli_query($conn, "SELECT dr.id, dr.nama_pelapor, dr.kondisi_dilaporkan, dr.deskripsi_kerusakan, dr.created_at, a.nama_barang
                                  FROM damage_reports dr
                                  JOIN assets a ON dr.asset_id = a.id
                                  WHERE dr.status = 'terbuka'
                                  ORDER BY dr.id DESC LIMIT 6");
        while ($x = mysqli_fetch_assoc($r)) { $laporanAdmin[] = $x; }
    }
} catch (Throwable $err) {
    // kolom belum tersedia: ringkasan laporan dikosongkan, halaman tetap jalan
}
$totalLaporan = $laporanTerbuka + $laporanSelesai;
$persenSelesai = $totalLaporan ? (int) round($laporanSelesai / $totalLaporan * 100) : 0;
$maksBulan = 1;
foreach ($seriBulan as $b) { $maksBulan = max($maksBulan, $b['dilapor']); }

// Turunan untuk widget
$pB = $total ? (int) round($baik / $total * 100) : 0;
$pR = $total ? (int) round($ringan / $total * 100) : 0;
$pH = $total ? max(0, 100 - $pB - $pR) : 0;
$kondisiList = [
    ['kelas' => 'baik',   'label' => 'Baik',         'n' => $baik,   'filter' => 'Baik',         'persen' => $pB],
    ['kelas' => 'ringan', 'label' => 'Rusak ringan', 'n' => $ringan, 'filter' => 'Rusak Ringan', 'persen' => $pR],
    ['kelas' => 'berat',  'label' => 'Rusak berat',  'n' => $berat,  'filter' => 'Rusak Berat',  'persen' => $pH],
];
$urutGelembung = $kondisiList;
usort($urutGelembung, function ($a, $b) { return $b['n'] <=> $a['n']; });
$maksN = max($baik, $ringan, $berat, 1);
$posisiGelembung = ['left:3%;top:24px', 'right:3%;top:4px', 'left:37%;top:142px'];

// peta titik: tiap titik = satu (atau beberapa) aset
$slotTitik = 60;
$perTitik = max(1, (int) ceil($total / $slotTitik));
$nR = (int) ceil($ringan / $perTitik);
$nH = (int) ceil($berat / $perTitik);
$nB = min((int) ceil($baik / $perTitik), max(0, $slotTitik - $nR - $nH));

$notifJumlah = $admin ? $laporanTerbuka : count(array_filter($laporanSaya, function ($l) { return ($l['status'] ?? 'terbuka') === 'terbuka'; }));
$hariIni = date('d') . ' ' . $bulanNama[(int) date('n') - 1] . ' ' . date('Y');

// Intro: sekali setelah login, atau lewat index.php?intro=1
$intro = !empty($_SESSION['intro']) || isset($_GET['intro']);
unset($_SESSION['intro']);

$judul = 'Dashboard - Inventaris Fasilitas Kampus';
$halaman = 'aset';
$body_class = 'page-dash' . ($intro ? ' intro-aktif' : '');
$tunda_shell = $intro;
$opsi_shell = ['cari' => true, 'notif' => $notifJumlah, 'perhatian' => $perhatian, 'modal_aset' => $admin];
include 'includes/header.php';
?>

<?php if ($intro):
    $namaDepan = explode(' ', trim(user_login()['nama']))[0];
    $perhatian = $ringan + $berat;
    $judulIntro = 'ASET KAMPUS';
    $hurufHtml = '';
    foreach (preg_split('//u', $judulIntro, -1, PREG_SPLIT_NO_EMPTY) as $n => $hr) {
        $hurufHtml .= $hr === ' ' ? '<i class="spasi"></i>' : '<span style="--n:' . $n . '">' . e($hr) . '</span>';
    }
?>
<section class="intro-wrap" id="intro" aria-label="Pembuka">
    <div class="intro-pin">
        <div class="intro-canvas" aria-hidden="true"></div>

        <div class="ht">
            <div class="ht-eko" style="--s:-1.12;--sy:.62;--o:.13;--u:-.9" aria-hidden="true"><?= $judulIntro; ?></div>
            <div class="ht-eko" style="--s:-.62;--sy:.82;--o:.26;--u:-.5" aria-hidden="true"><?= $judulIntro; ?></div>
            <h1 class="ht-utama" aria-label="Aset Kampus"><?= $hurufHtml; ?></h1>
            <div class="ht-eko" style="--s:.66;--sy:.82;--o:.26;--u:.5" aria-hidden="true"><?= $judulIntro; ?></div>
            <div class="ht-eko" style="--s:1.18;--sy:.55;--o:.13;--u:.9" aria-hidden="true"><?= $judulIntro; ?></div>
        </div>

        <svg class="intro-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
        <nav class="intro-nav">
            <button type="button" id="introLewati">Lewati</button>
            <a href="logout.php" data-zoom="ink">Keluar</a>
        </nav>

        <div class="intro-hiasan" aria-hidden="true">
            <span class="meta" style="left:10.8%;top:15.5%"><?= date('Y'); ?></span>
            <span class="meta kanan" style="right:10.5%;top:15.5%"><?= $admin ? 'Admin' : 'User'; ?></span>

            <i class="bintang" style="left:31%;top:18%;--d:22"></i>
            <i class="bintang" style="left:69%;top:18%;--d:-22"></i>
            <i class="bintang" style="left:31%;top:90.5%;--d:-18"></i>
            <i class="bintang" style="left:69.2%;top:90.5%;--d:18"></i>
            <i class="silang" style="left:50%;top:18.3%;--d:12"></i>
            <i class="silang" style="left:2.7%;top:73%;--d:-10"></i>
            <i class="silang" style="left:2.7%;top:76.4%;--d:-10"></i>
            <i class="silang" style="left:2.7%;top:79.8%;--d:-10"></i>
            <i class="caret" style="left:97.8%;top:29.8%;--d:8"></i>
            <i class="silang" style="left:97.8%;top:32.8%;--d:8"></i>

            <div class="meta bawah" style="left:10.8%"><small>Pengguna</small><b><?= e(mb_strtoupper($namaDepan)); ?></b></div>
            <div class="meta bawah kanan" style="right:10.5%"><small>Total aset</small><b><?= $total; ?><?= $perhatian ? ' / ' . $perhatian . ' perlu perhatian' : ''; ?></b></div>
        </div>

        <button type="button" class="gulir-badge" id="introMulai" aria-label="Gulir ke bawah">
            <svg class="cincin" viewBox="0 0 120 120" aria-hidden="true">
                <defs><path id="lingkarGulir" d="M60,60 m-47,0 a47,47 0 1,1 94,0 a47,47 0 1,1 -94,0"/></defs>
                <text><textPath href="#lingkarGulir" textLength="292" lengthAdjust="spacing">GULIR KE BAWAH &#10022; GULIR KE BAWAH &#10022;</textPath></text>
            </svg>
            <svg class="bumi" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                <circle cx="24" cy="24" r="19"/><ellipse cx="24" cy="24" rx="8" ry="19"/><ellipse cx="24" cy="24" rx="15" ry="19"/>
                <path d="M5 24h38M8 14h32M8 34h32"/>
            </svg>
        </button>
    </div>
</section>
<?php endif; ?>

<?php if ($intro) { buka_shell($halaman, $opsi_shell); } ?>

<main class="isi">

    <div class="judul-halaman">
        <div>
            <h1>Ringkasan Inventaris</h1>
            <p>Pantau kondisi aset kampus dan laporan kerusakan hari ini.</p>
        </div>
        <div class="judul-kanan">
            <span class="tanggal"><?= ikon('jam'); ?> <?= $hariIni; ?></span>
        </div>
    </div>

    <section class="dash">

        <!-- Total aset + gelembung kondisi -->
        <article class="w w-total">
            <div class="w-head"><span class="w-ikon"><?= ikon('kotak'); ?></span><b>Total aset</b><span class="w-menu"><?= ikon('titik3'); ?></span></div>
            <div class="angka">
                <span class="pil"><?= $perhatian; ?> perlu perhatian</span>
                <span data-count="<?= $total; ?>">0</span><small>aset terdaftar</small>
            </div>

            <div class="gelembung-area">
                <?php foreach ($urutGelembung as $rank => $k):
                    $d = (int) round(78 + 92 * sqrt($k['n'] / $maksN)); ?>
                <button type="button" class="gelembung g-<?= $k['kelas']; ?>" data-filter-klik="<?= e($k['filter']); ?>"
                        style="width:<?= $d; ?>px;height:<?= $d; ?>px;<?= $posisiGelembung[$rank]; ?>;animation-delay:<?= 0.25 + $rank * 0.12; ?>s"
                        title="Tampilkan: <?= e($k['label']); ?>">
                    <span><b data-count="<?= $k['n']; ?>">0</b><small><?= e($k['label']); ?></small></span>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($kondisiList as $k): ?>
            <div class="baris-prog p-<?= $k['kelas']; ?>" data-filter-klik="<?= e($k['filter']); ?>">
                <div class="prog-atas">
                    <span class="nilai"><b data-count="<?= $k['persen']; ?>">0</b><small>%</small></span>
                    <span class="label"><?= e($k['label']); ?> <i class="dot <?= $k['kelas']; ?>"></i></span>
                </div>
                <div class="prog"><i data-w="<?= $k['persen']; ?>"></i></div>
            </div>
            <?php endforeach; ?>
        </article>

        <!-- Perlu perhatian -->
        <article class="w w-perhatian">
            <div class="w-head"><span class="w-ikon"><?= ikon('awas'); ?></span><b>Perlu perhatian</b><span class="w-menu"><?= ikon('titik3'); ?></span></div>
            <div class="angka-baris">
                <div class="angka"><span data-count="<?= $perhatian; ?>">0</span><small>aset</small></div>
                <div class="sisi"><small>Rusak berat</small><b><?= $berat; ?></b></div>
            </div>
        </article>

        <!-- Laporan terbuka -->
        <article class="w w-laporan">
            <div class="w-head"><span class="w-ikon"><?= ikon('bel'); ?></span><b>Laporan terbuka</b><span class="w-menu"><?= ikon('titik3'); ?></span></div>
            <div class="angka-baris">
                <div class="angka"><span data-count="<?= $laporanTerbuka; ?>">0</span><small>laporan</small></div>
                <div class="sisi"><small>Selesai</small><b><?= $laporanSelesai; ?></b></div>
            </div>
        </article>

        <!-- Peta titik aset -->
        <article class="w w-peta">
            <div class="w-head"><span class="w-ikon"><?= ikon('kisi'); ?></span><b>Kesehatan aset</b><span class="w-menu"><?= ikon('titik3'); ?></span></div>
            <div class="angka"><span class="pil"><?= $jumlahRuangan; ?> ruangan</span><span data-count="<?= $pB; ?>">0</span><small>% baik</small></div>
            <div class="titik-grid">
                <?php for ($i = 0; $i < $slotTitik; $i++):
                    $kls = $i < $nB ? 'b' : ($i < $nB + $nR ? 'r' : ($i < $nB + $nR + $nH ? 'h' : '')); ?>
                <i class="titik <?= $kls; ?>" style="--i:<?= $i; ?>"></i>
                <?php endfor; ?>
            </div>
            <div class="legenda">
                <span><i class="dot baik"></i> baik</span>
                <span><i class="dot ringan"></i> ringan</span>
                <span><i class="dot berat"></i> berat</span>
                <em>1 titik = <?= $perTitik; ?> aset</em>
            </div>
        </article>

        <!-- Grafik laporan -->
        <article class="w w-grafik gelap">
            <div class="w-head">
                <span class="w-ikon"><?= ikon('awas'); ?></span><b>Laporan kerusakan</b>
                <select class="pil-pilih" id="rentang" aria-label="Rentang bulan">
                    <option value="6">6 bulan</option>
                    <option value="12">12 bulan</option>
                </select>
            </div>
            <div class="stat-garis">
                <div class="sg lime"><i></i><div><b><span data-count="<?= $persenSelesai; ?>">0</span><small>%</small></b><small>Selesai ditangani</small></div></div>
                <div class="sg lav"><i></i><div><b><span data-count="<?= $totalLaporan; ?>">0</span></b><small>Total laporan</small></div></div>
            </div>
            <div class="grafik">
                <?php $ib = 0; foreach ($seriBulan as $b):
                    $h1 = $b['dilapor'] ? max(10, (int) round($b['dilapor'] / $maksBulan * 100)) : 4;
                    $h2 = $b['selesai'] ? max(10, (int) round($b['selesai'] / $maksBulan * 100)) : 4; ?>
                <div class="bln <?= $b['sekarang'] ? 'sekarang' : ''; ?>" style="--i:<?= $ib; ?>"
                     title="<?= e($b['label']); ?>: <?= $b['dilapor']; ?> dilaporkan, <?= $b['selesai']; ?> selesai">
                    <div class="batang-grup">
                        <i class="batang b1" style="--h:<?= $h1; ?>%"></i>
                        <i class="batang b2" style="--h:<?= $h2; ?>%"></i>
                    </div>
                    <span><?= e($b['label']); ?><?= $b['sekarang'] ? ' &#8599;' : ''; ?></span>
                </div>
                <?php $ib++; endforeach; ?>
            </div>
        </article>
    </section>

    <section class="bawah">

        <!-- Daftar aset -->
        <article class="card" id="daftar">
            <div class="kartu-kepala">
                <h2>Daftar aset <span class="pill"><?= $total; ?></span></h2>
                <?php if ($admin): ?>
                    <button type="button" class="btn-kecil" data-buka="modalAset"><?= ikon('tambah'); ?> Tambah aset</button>
                <?php endif; ?>
            </div>

            <div class="kontrol">
                <div class="chips" id="chips">
                    <button type="button" class="chip active" data-filter="all">Semua</button>
                    <button type="button" class="chip" data-filter="Baik">Baik</button>
                    <button type="button" class="chip" data-filter="Rusak Ringan">Rusak ringan</button>
                    <button type="button" class="chip" data-filter="Rusak Berat">Rusak berat</button>
                </div>
                <div class="view-toggle" data-aktif="tabel" role="group" aria-label="Pilih tampilan">
                    <span class="vt-geser"></span>
                    <button type="button" class="vt active" data-view="tabel"><?= ikon('daftar'); ?> Tabel</button>
                    <button type="button" class="vt" data-view="kartu"><?= ikon('kisi'); ?> Kartu 3D</button>
                </div>
            </div>

            <div id="inventaris" data-view="tabel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Kode</th><th>Nama barang</th><th>Lokasi ruangan</th><th>Kondisi</th><th>Aksi</th></tr>
                        </thead>
                        <tbody id="tbody">
                            <?php foreach ($assets as $i => $row): ?>
                            <tr data-kondisi="<?= e($row['kondisi']); ?>" style="animation-delay: <?= min($i, 12) * 0.05 + 0.2; ?>s">
                                <td><span class="code" title="Klik untuk menyalin"><?= e($row['kode_asset']); ?></span></td>
                                <td><b><?= e($row['nama_barang']); ?></b></td>
                                <td class="room"><?= e($row['nama_ruangan']); ?><small><?= e($row['gedung']); ?></small></td>
                                <td>
                                    <span class="badge <?= kelas_kondisi($row['kondisi']); ?>"><?= e($row['kondisi']); ?></span>
                                    <?php if (!$admin && isset($laporanPerAset[$row['id']])): ?><span class="tag-saya">Dilaporkan Anda</span><?php endif; ?>
                                </td>
                                <td><?= aksi_aset($row, $admin, $laporanPerAset); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="asset-grid">
                    <?php foreach ($assets as $i => $row): $k = kelas_kondisi($row['kondisi']); ?>
                    <article class="asset-card <?= $k; ?>" data-kondisi="<?= e($row['kondisi']); ?>" style="animation-delay: <?= min($i, 12) * 0.06 + 0.1; ?>s">
                        <div class="ac-inner">
                            <div class="ac-top">
                                <span class="ac-icon"><?= ikon('kotak'); ?></span>
                                <span class="badge <?= $k; ?>"><?= e($row['kondisi']); ?></span>
                            </div>
                            <h3 class="ac-title"><?= e($row['nama_barang']); ?></h3>
                            <div class="ac-meta">
                                <span class="code" title="Klik untuk menyalin"><?= e($row['kode_asset']); ?></span>
                                <?php if (!$admin && isset($laporanPerAset[$row['id']])): ?><span class="tag-saya">Dilaporkan Anda</span><?php endif; ?>
                            </div>
                            <p class="ac-room"><?= ikon('pin'); ?><span><?= e($row['nama_ruangan']); ?><small><?= e($row['gedung']); ?></small></span></p>
                            <div class="ac-aksi"><?= aksi_aset($row, $admin, $laporanPerAset); ?></div>
                        </div>
                        <span class="ac-shine"></span>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="empty <?= $total === 0 ? 'show' : ''; ?>" id="empty">
                <strong><?= $total === 0 ? 'Belum ada aset' : 'Tidak ada aset yang cocok'; ?></strong>
                <?= $total === 0 ? ($admin ? 'Klik "Tambah aset" untuk mulai mencatat.' : 'Belum ada aset yang terdaftar.') : 'Coba ubah kata kunci atau filter kondisi.'; ?>
            </div>
        </article>

        <!-- Laporan -->
        <article class="card" id="laporan">
            <?php if ($admin): ?>
                <div class="kartu-kepala"><h2>Laporan terbuka <span class="pill"><?= $laporanTerbuka; ?></span></h2></div>
                <?php if (!$laporanAdmin): ?>
                    <div class="lp-kosong">Tidak ada laporan yang menunggu. Semua aman.</div>
                <?php else: ?>
                <div class="laporan-list">
                    <?php foreach ($laporanAdmin as $lp): ?>
                    <article class="lp-item">
                        <div class="lp-top">
                            <strong><?= e($lp['nama_barang']); ?></strong>
                            <span class="badge <?= kelas_kondisi($lp['kondisi_dilaporkan']); ?>"><?= e($lp['kondisi_dilaporkan']); ?></span>
                        </div>
                        <p class="lp-desc"><?= e($lp['deskripsi_kerusakan']); ?></p>
                        <div class="lp-bottom">
                            <small><?= e($lp['nama_pelapor']); ?><?= $lp['created_at'] ? ' &middot; ' . e(date('d/m H:i', strtotime($lp['created_at']))) : ''; ?></small>
                            <form method="POST" action="selesai.php" data-no-zoom>
                                <input type="hidden" name="id" value="<?= (int) $lp['id']; ?>">
                                <button type="submit" class="aksi" title="Tandai selesai"><?= ikon('centang'); ?> Selesai</button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="kartu-kepala"><h2>Laporan saya <span class="pill"><?= count($laporanSaya); ?></span></h2></div>
                <a href="laporkan.php" data-zoom="red" class="btn btn-lime">+ Laporkan barang rusak</a>

                <?php if ($perluMigrasi): ?>
                    <div class="lp-kosong">Fitur ini belum aktif. Muat ulang halaman, atau minta admin membuka <b>migrasi.php</b>.</div>
                <?php elseif (!$laporanSaya): ?>
                    <div class="lp-kosong">Belum ada laporan. Klik tombol di atas untuk melaporkan barang yang rusak.</div>
                <?php else: ?>
                <div class="laporan-list">
                    <?php foreach ($laporanSaya as $lp): ?>
                    <article class="lp-item">
                        <div class="lp-top">
                            <strong><?= e($lp['nama_barang']); ?></strong>
                            <?php if (($lp['status'] ?? 'terbuka') === 'selesai'): ?>
                                <span class="badge baik">Selesai &#10003;</span>
                            <?php else: ?>
                                <span class="badge <?= kelas_kondisi($lp['kondisi_dilaporkan']); ?>"><?= e($lp['kondisi_dilaporkan']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="lp-desc"><?= e($lp['deskripsi_kerusakan']); ?></p>
                        <div class="lp-bottom">
                            <small><?= $lp['created_at'] ? e(date('d/m/Y H:i', strtotime($lp['created_at']))) : ''; ?></small>
                            <div class="aksi-group">
                                <?php if (($lp['status'] ?? 'terbuka') !== 'selesai'): ?>
                                <a class="aksi icon edit" data-zoom="blue" href="edit_laporan.php?id=<?= (int) $lp['id']; ?>" title="Ubah laporan" aria-label="Ubah laporan"><?= ikon('edit'); ?></a>
                                <?php endif; ?>
                                <button type="button" class="aksi icon del" title="Hapus laporan" aria-label="Hapus laporan"
                                        data-hapus="laporan" data-id="<?= (int) $lp['id']; ?>" data-nama="<?= e($lp['nama_barang']); ?>"><?= ikon('hapus'); ?></button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    </section>
</main>

<?php if ($admin): ?>
<div class="modal" id="modalAset" aria-hidden="true">
    <div class="modal-box lebar" role="dialog" aria-modal="true" aria-labelledby="judulAset">
        <button type="button" class="modal-x" data-tutup aria-label="Tutup"><?= ikon('tambah'); ?></button>
        <h3 id="judulAset">Tambah aset baru</h3>
        <p>Catat barang baru beserta lokasi dan kondisinya.</p>
        <form method="POST" id="formAset">
            <div class="field">
                <label for="kode_asset">Kode aset</label>
                <input type="text" id="kode_asset" name="kode_asset" placeholder="Contoh: AST-LAB-001" required>
            </div>
            <div class="field">
                <label for="nama_barang">Nama barang</label>
                <input type="text" id="nama_barang" name="nama_barang" placeholder="Contoh: Proyektor Epson" required>
            </div>
            <div class="field">
                <label for="room_id">Ruangan</label>
                <select id="room_id" name="room_id" required>
                    <option value="">-- Pilih ruangan --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int) $r['id']; ?>"><?= e($r['nama_ruangan']); ?> (<?= e($r['gedung']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="kondisi">Kondisi</label>
                <select id="kondisi" name="kondisi" required>
                    <option value="Baik">Baik</option>
                    <option value="Rusak Ringan">Rusak Ringan</option>
                    <option value="Rusak Berat">Rusak Berat</option>
                </select>
            </div>
            <button type="submit" name="tambah_aset" class="btn" id="btnSimpan">Simpan aset</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($intro): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="assets/intro.js?v=<?= @filemtime(__DIR__ . '/assets/intro.js'); ?>" defer></script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>