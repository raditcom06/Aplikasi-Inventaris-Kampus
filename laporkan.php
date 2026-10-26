<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_login();
pastikan_skema($conn);

$MAKS = 30; // batas barang per laporan

// Semua aset (untuk pilihan "tambah barang"), urut per ruangan
$semua = [];
$res = mysqli_query($conn, "SELECT assets.id, assets.kode_asset, assets.nama_barang, assets.kondisi,
                                   rooms.nama_ruangan, rooms.gedung
                            FROM assets
                            JOIN rooms ON assets.room_id = rooms.id
                            ORDER BY rooms.gedung, rooms.nama_ruangan, assets.nama_barang");
while ($r = mysqli_fetch_assoc($res)) {
    $r['id'] = (int) $r['id'];
    $semua[$r['id']] = $r;
}

// Barang yang sudah dipilih dari halaman sebelumnya (?ids=1,2,3 atau ?asset_id=1)
$ids = [];
if (isset($_GET['ids'])) {
    foreach (explode(',', $_GET['ids']) as $x) { $ids[] = (int) $x; }
}
if (isset($_GET['asset_id'])) { $ids[] = (int) $_GET['asset_id']; }

$awal = [];
foreach (array_unique($ids) as $id) {
    if (isset($semua[$id]) && count($awal) < $MAKS) {
        $awal[] = ['id' => $id, 'kondisi' => 'Rusak Ringan', 'deskripsi' => ''];
    }
}

$error = '';
$nama_pelapor = user_login()['nama'];

// Simpan laporan
if (isset($_POST['kirim_laporan'])) {
    $nama_pelapor = trim($_POST['nama_pelapor'] ?? '');
    $items = $_POST['item'] ?? [];
    $bersih = [];

    if (is_array($items)) {
        foreach ($items as $id => $it) {
            $id = (int) $id;
            if (!isset($semua[$id]) || !is_array($it)) continue;
            $kond = $it['kondisi'] ?? '';
            if (!in_array($kond, ['Rusak Ringan', 'Rusak Berat'], true)) $kond = 'Rusak Ringan';
            $bersih[$id] = [
                'id'        => $id,
                'kondisi'   => $kond,
                'deskripsi' => mb_substr(trim($it['deskripsi'] ?? ''), 0, 500),
            ];
        }
    }
    $bersih = array_slice($bersih, 0, $MAKS, true);

    if ($nama_pelapor === '') {
        $error = 'Nama pelapor wajib diisi.';
    } elseif (!$bersih) {
        $error = 'Tambahkan minimal satu barang yang rusak.';
    } else {
        foreach ($bersih as $b) {
            if ($b['deskripsi'] === '') { $error = 'Setiap barang perlu keterangan kerusakannya.'; break; }
        }
    }

    if (!$error) {
        mysqli_begin_transaction($conn);
        try {
            $uid = (int) user_login()['id'];
            $ins = mysqli_prepare($conn, "INSERT INTO damage_reports (asset_id, user_id, nama_pelapor, deskripsi_kerusakan, kondisi_dilaporkan) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "iisss", $aid, $uid, $nama_pelapor, $desk, $kdl);
            foreach ($bersih as $b) {
                $aid  = $b['id'];
                $desk = $b['deskripsi'];
                $kdl  = $b['kondisi'];
                mysqli_stmt_execute($ins);
            }
            // Kondisi tiap aset dihitung ulang dari laporan yang masih terbuka
            foreach ($bersih as $b) { hitung_ulang_kondisi($conn, $b['id']); }
            mysqli_commit($conn);

            $n = count($bersih);
            flash_set($n === 1 ? 'Laporan kerusakan terkirim' : "$n laporan kerusakan terkirim");
            redirect('index.php');
        } catch (Throwable $err) {
            mysqli_rollback($conn);
            $error = (stripos($err->getMessage(), 'Unknown column') !== false)
                ? 'Database belum diperbarui. Minta admin membuka migrasi.php.'
                : 'Laporan gagal disimpan. Coba kirim lagi.';
        }
    }
    // Ada error: tampilkan lagi isian user supaya tidak hilang
    $awal = array_values($bersih);
}

$judul = 'Laporkan Kerusakan';
$halaman = 'lapor';
$body_class = 'page-lapor page-multi';
include 'includes/header.php';
?>

<header class="hero">
    <div class="hero-inner">
        <a href="index.php" class="back">← Kembali ke inventaris</a>
        <h1>Laporkan kerusakan</h1>
        <p>Tambahkan semua barang yang rusak, lalu kirim sekaligus dalam satu laporan.</p>
    </div>
</header>

<main class="wrap">
    <section class="card">
    <?php if (!$semua): ?>
        <div class="notfound">
            <strong>Belum ada aset</strong>
            Belum ada barang di inventaris yang bisa dilaporkan.
            <br><a href="index.php">← Kembali ke daftar inventaris</a>
        </div>
    <?php else: ?>

        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <form method="POST" id="formLapor">
            <div class="field">
                <label for="nama_pelapor">Nama pelapor</label>
                <input type="text" id="nama_pelapor" name="nama_pelapor" value="<?= e($nama_pelapor); ?>" placeholder="Nama Anda (Mahasiswa/Dosen/Staf)" required>
            </div>

            <div class="li-title">
                <h2>Barang yang rusak</h2>
                <span class="pill" id="jumlahItem">0</span>
            </div>

            <div id="daftarItem"></div>
            <div class="li-kosong" id="kosongItem">Belum ada barang. Pilih dari daftar di bawah, lalu klik <b>+ Tambah</b>.</div>

            <div class="li-tambah">
                <select id="tambahPilih" aria-label="Pilih barang yang rusak"></select>
                <button type="button" class="btn-tambah" id="tambahBtn">+ Tambah</button>
            </div>

            <button type="submit" name="kirim_laporan" class="btn" id="btnKirim">Kirim laporan kerusakan</button>
        </form>

    <?php endif; ?>
    </section>
</main>

<script>
window.LAPOR = <?= json_encode(
    ['aset' => array_values($semua), 'awal' => $awal, 'maks' => $MAKS],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
); ?>;
</script>

<?php include 'includes/footer.php'; ?>