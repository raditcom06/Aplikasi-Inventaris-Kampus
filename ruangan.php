<?php
include 'koneksi.php';
require 'includes/auth.php';
wajib_admin();

// Simpan (tambah atau edit)
if (isset($_POST['simpan_ruangan'])) {
    $id     = (int) ($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama_ruangan']);
    $gedung = trim($_POST['gedung']);

    try {
        if ($id > 0) {
            $s = mysqli_prepare($conn, "UPDATE rooms SET nama_ruangan = ?, gedung = ? WHERE id = ?");
            mysqli_stmt_bind_param($s, "ssi", $nama, $gedung, $id);
            flash_set('Ruangan berhasil diperbarui');
        } else {
            $s = mysqli_prepare($conn, "INSERT INTO rooms (nama_ruangan, gedung) VALUES (?, ?)");
            mysqli_stmt_bind_param($s, "ss", $nama, $gedung);
            flash_set('Ruangan berhasil ditambahkan');
        }
        mysqli_stmt_execute($s);
    } catch (Throwable $err) {
        flash_set('Ruangan gagal disimpan', 'error');
    }
    redirect('ruangan.php');
}

// Mode edit (?edit=ID)
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $s = mysqli_prepare($conn, "SELECT * FROM rooms WHERE id = ?");
    mysqli_stmt_bind_param($s, "i", $eid);
    mysqli_stmt_execute($s);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
}

// Daftar ruangan + jumlah aset di dalamnya
$rooms = [];
$res = mysqli_query($conn, "SELECT rooms.*, COUNT(assets.id) AS jumlah
                            FROM rooms
                            LEFT JOIN assets ON assets.room_id = rooms.id
                            GROUP BY rooms.id
                            ORDER BY rooms.gedung, rooms.nama_ruangan");
while ($r = mysqli_fetch_assoc($res)) { $rooms[] = $r; }
$total = count($rooms);

$judul = 'Data Ruangan';
$halaman = 'ruangan';
$body_class = 'page-index page-ruangan';
include 'includes/header.php';
?>

<header class="hero">
    <div class="hero-inner">
        <h1>Data ruangan</h1>
        <p>Kelola lokasi tempat aset kampus disimpan.</p>
    </div>
</header>

<main class="wrap">

    <section class="card form-card">
        <h2><?= $edit ? 'Edit ruangan' : 'Tambah ruangan baru'; ?></h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0; ?>">
            <div class="field">
                <label for="nama_ruangan">Nama ruangan</label>
                <input type="text" id="nama_ruangan" name="nama_ruangan" placeholder="Contoh: Lab Komputer 1"
                       value="<?= $edit ? e($edit['nama_ruangan']) : ''; ?>" required>
            </div>
            <div class="field">
                <label for="gedung">Gedung</label>
                <input type="text" id="gedung" name="gedung" placeholder="Contoh: Gedung A"
                       value="<?= $edit ? e($edit['gedung']) : ''; ?>" required>
            </div>
            <div class="form-actions">
                <?php if ($edit): ?><a href="ruangan.php" class="btn-ghost">Batal</a><?php endif; ?>
                <button type="submit" name="simpan_ruangan" class="btn"><?= $edit ? 'Simpan perubahan' : 'Simpan ruangan'; ?></button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="toolbar">
            <h2>Daftar ruangan <span class="pill"><?= $total; ?></span></h2>
            <div class="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="cari" placeholder="Cari ruangan atau gedung..." autocomplete="off">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nama ruangan</th><th>Gedung</th><th>Jumlah aset</th><th>Aksi</th></tr>
                </thead>
                <tbody id="tbody">
                    <?php foreach ($rooms as $i => $r): ?>
                    <tr style="animation-delay: <?= min($i, 12) * 0.05 + 0.2; ?>s">
                        <td><b><?= e($r['nama_ruangan']); ?></b></td>
                        <td><?= e($r['gedung']); ?></td>
                        <td><span class="pill <?= $r['jumlah'] ? '' : 'nol'; ?>"><?= (int) $r['jumlah']; ?></span></td>
                        <td>
                            <div class="aksi-group">
                                <a class="aksi icon edit" href="ruangan.php?edit=<?= (int) $r['id']; ?>" title="Edit" aria-label="Edit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </a>
                                <button type="button" class="aksi icon del" title="Hapus" aria-label="Hapus"
                                        data-hapus="ruangan" data-id="<?= (int) $r['id']; ?>" data-nama="<?= e($r['nama_ruangan']); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty <?= $total === 0 ? 'show' : ''; ?>" id="empty">
            <strong><?= $total === 0 ? 'Belum ada ruangan' : 'Tidak ada ruangan yang cocok'; ?></strong>
            <?= $total === 0 ? 'Tambahkan ruangan pertama lewat form di samping.' : 'Coba ubah kata kunci pencarian.'; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>