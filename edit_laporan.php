<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_login();
pastikan_skema($conn);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$me = (int) user_login()['id'];
$lap = null;
$error = '';

try {
    $s = mysqli_prepare($conn, "SELECT dr.*, a.nama_barang, a.kode_asset, a.kondisi AS kondisi_aset, r.nama_ruangan, r.gedung
                                FROM damage_reports dr
                                JOIN assets a ON dr.asset_id = a.id
                                JOIN rooms r ON a.room_id = r.id
                                WHERE dr.id = ?");
    mysqli_stmt_bind_param($s, "i", $id);
    mysqli_stmt_execute($s);
    $lap = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
} catch (Throwable $err) {
    $error = 'Database belum diperbarui. Minta admin membuka migrasi.php.';
}

// User hanya boleh mengubah laporannya sendiri; admin boleh semuanya
if ($lap && !is_admin() && (int) $lap['user_id'] !== $me) {
    flash_set('Anda hanya bisa mengubah laporan yang Anda buat sendiri.', 'error');
    redirect('index.php');
}

if ($lap && !is_admin() && ($lap['status'] ?? 'terbuka') === 'selesai') {
    flash_set('Laporan ini sudah selesai ditangani dan tidak bisa diubah.', 'error');
    redirect('index.php');
}

if ($lap && isset($_POST['simpan_laporan'])) {
    $kondisi = in_array($_POST['kondisi'] ?? '', ['Rusak Ringan', 'Rusak Berat'], true) ? $_POST['kondisi'] : 'Rusak Ringan';
    $deskripsi = mb_substr(trim($_POST['deskripsi'] ?? ''), 0, 500);

    $lap['kondisi_dilaporkan'] = $kondisi;
    $lap['deskripsi_kerusakan'] = $deskripsi;

    if ($deskripsi === '') {
        $error = 'Keterangan kerusakan wajib diisi.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $u = mysqli_prepare($conn, "UPDATE damage_reports SET kondisi_dilaporkan = ?, deskripsi_kerusakan = ? WHERE id = ?");
            mysqli_stmt_bind_param($u, "ssi", $kondisi, $deskripsi, $id);
            mysqli_stmt_execute($u);

            // Kondisi aset di daftar inventaris ikut berubah
            hitung_ulang_kondisi($conn, (int) $lap['asset_id']);
            mysqli_commit($conn);
            flash_set('Laporan berhasil diperbarui');
            redirect('index.php');
        } catch (Throwable $err) {
            mysqli_rollback($conn);
            $error = 'Perubahan gagal disimpan. Coba lagi.';
        }
    }
}

$judul = 'Ubah Laporan';
$halaman = 'aset';
$body_class = 'page-lapor page-edit';
include 'includes/header.php';
?>

<header class="hero">
    <div class="hero-inner">
        <a href="index.php" class="back">← Kembali ke inventaris</a>
        <h1>Ubah laporan</h1>
        <p>Perbarui tingkat kerusakan atau keterangan pada laporan Anda.</p>
    </div>
</header>

<main class="wrap">
    <section class="card">
    <?php if (!$lap): ?>
        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>
        <div class="notfound">
            <strong>Laporan tidak ditemukan</strong>
            Laporan yang mau diubah tidak ada.
            <br><a href="index.php">← Kembali ke daftar inventaris</a>
        </div>
    <?php else: ?>
        <div class="asset-info">
            <div class="asset-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
            </div>
            <div>
                <h2><?= e($lap['nama_barang']); ?></h2>
                <small><span class="code"><?= e($lap['kode_asset']); ?></span><?= e($lap['nama_ruangan']); ?> (<?= e($lap['gedung']); ?>)</small>
            </div>
        </div>

        <?php if ($error): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="id" value="<?= (int) $lap['id']; ?>">

            <div class="field">
                <span class="legend">Seberapa parah kerusakannya?</span>
                <div class="choices">
                    <div class="choice r">
                        <input type="radio" name="kondisi" id="k1" value="Rusak Ringan" <?= $lap['kondisi_dilaporkan'] !== 'Rusak Berat' ? 'checked' : ''; ?>>
                        <label for="k1"><strong><i class="dot"></i>Rusak ringan</strong><span>Masih bisa dipakai, ada kendala kecil</span></label>
                    </div>
                    <div class="choice b">
                        <input type="radio" name="kondisi" id="k2" value="Rusak Berat" <?= $lap['kondisi_dilaporkan'] === 'Rusak Berat' ? 'checked' : ''; ?>>
                        <label for="k2"><strong><i class="dot"></i>Rusak berat</strong><span>Tidak bisa dipakai sama sekali</span></label>
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="deskripsi">Keterangan kerusakan</label>
                <textarea id="deskripsi" name="deskripsi" maxlength="500" rows="4" required><?= e($lap['deskripsi_kerusakan']); ?></textarea>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-ghost">Batal</a>
                <button type="submit" name="simpan_laporan" class="btn">Simpan perubahan</button>
            </div>
        </form>
    <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>