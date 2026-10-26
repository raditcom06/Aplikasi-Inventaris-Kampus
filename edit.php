<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_admin();
pastikan_skema($conn);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$s = mysqli_prepare($conn, "SELECT * FROM assets WHERE id = ?");
mysqli_stmt_bind_param($s, "i", $id);
mysqli_stmt_execute($s);
$asset = mysqli_fetch_assoc(mysqli_stmt_get_result($s));

$rooms = [];
$res = mysqli_query($conn, "SELECT * FROM rooms ORDER BY gedung, nama_ruangan");
while ($r = mysqli_fetch_assoc($res)) { $rooms[] = $r; }

$daftar_kondisi = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

if ($asset && isset($_POST['simpan_edit'])) {
    $kode    = trim($_POST['kode_asset']);
    $nama    = trim($_POST['nama_barang']);
    $room_id = (int) $_POST['room_id'];
    $kondisi = in_array($_POST['kondisi'], $daftar_kondisi, true) ? $_POST['kondisi'] : $asset['kondisi'];

    mysqli_begin_transaction($conn);
    try {
        $u = mysqli_prepare($conn, "UPDATE assets SET kode_asset = ?, nama_barang = ?, room_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($u, "ssii", $kode, $nama, $room_id, $id);
        mysqli_stmt_execute($u);

        // Kondisi hanya disentuh kalau admin memang mengubahnya
        if ($kondisi !== $asset['kondisi']) {
            $d = mysqli_prepare($conn, "UPDATE assets SET kondisi_dasar = ? WHERE id = ?");
            mysqli_stmt_bind_param($d, "si", $kondisi, $id);
            mysqli_stmt_execute($d);

            // Kondisi diturunkan (misalnya jadi Baik) = laporan terkait dianggap selesai
            if (peringkat_kondisi($kondisi) < peringkat_kondisi($asset['kondisi'])) {
                $c = mysqli_prepare($conn, "UPDATE damage_reports SET status = 'selesai' WHERE asset_id = ? AND status = 'terbuka'");
                mysqli_stmt_bind_param($c, "i", $id);
                mysqli_stmt_execute($c);
            }
        }
        hitung_ulang_kondisi($conn, $id);
        mysqli_commit($conn);
        flash_set('Aset berhasil diperbarui');
        redirect('index.php');
    } catch (Throwable $err) {
        mysqli_rollback($conn);
        $error = 'Perubahan gagal disimpan. Cek lagi datanya.';
        $asset = array_merge($asset, ['kode_asset' => $kode, 'nama_barang' => $nama, 'room_id' => $room_id, 'kondisi' => $kondisi]);
    }
}

$judul = 'Edit Aset';
$halaman = 'aset';
$body_class = 'page-lapor page-edit';
include 'includes/header.php';
?>

<header class="hero">
    <div class="hero-inner">
        <a href="index.php" class="back">← Kembali ke inventaris</a>
        <h1>Edit aset</h1>
        <p>Perbarui data barang, lokasi, atau kondisinya.</p>
    </div>
</header>

<main class="wrap">
    <section class="card">
    <?php if (!$asset): ?>
        <div class="notfound">
            <strong>Aset tidak ditemukan</strong>
            Barang yang mau diedit tidak ada di inventaris.
            <br><a href="index.php">← Kembali ke daftar inventaris</a>
        </div>
    <?php else: ?>
        <?php if (!empty($error)): ?><div class="alert"><?= e($error); ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="id" value="<?= (int) $asset['id']; ?>">

            <div class="field">
                <label for="kode_asset">Kode aset</label>
                <input type="text" id="kode_asset" name="kode_asset" value="<?= e($asset['kode_asset']); ?>" required>
            </div>
            <div class="field">
                <label for="nama_barang">Nama barang</label>
                <input type="text" id="nama_barang" name="nama_barang" value="<?= e($asset['nama_barang']); ?>" required>
            </div>
            <div class="field">
                <label for="room_id">Ruangan</label>
                <select id="room_id" name="room_id" required>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int) $r['id']; ?>" <?= (int) $r['id'] === (int) $asset['room_id'] ? 'selected' : ''; ?>>
                            <?= e($r['nama_ruangan']); ?> (<?= e($r['gedung']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="kondisi">Kondisi</label>
                <select id="kondisi" name="kondisi" required>
                    <?php foreach ($daftar_kondisi as $k): ?>
                        <option value="<?= e($k); ?>" <?= $k === $asset['kondisi'] ? 'selected' : ''; ?>><?= e($k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-ghost">Batal</a>
                <button type="submit" name="simpan_edit" class="btn">Simpan perubahan</button>
            </div>
        </form>
    <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>