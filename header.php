<?php
// includes/header.php
// Set sebelum include: $judul, $halaman ('aset' | 'ruangan' | 'lapor'), $body_class,
// $tanpa_nav (true untuk login/register), $tunda_shell (true bila halaman membuka rangka sendiri),
// $opsi_shell (opsi untuk sidebar/topbar)
$judul       = $judul ?? 'Sistem Inventaris Fasilitas Kampus';
$halaman     = $halaman ?? '';
$body_class  = $body_class ?? '';
$tanpa_nav   = $tanpa_nav ?? false;
$tunda_shell = $tunda_shell ?? false;
$opsi_shell  = $opsi_shell ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Anton&family=Barlow+Condensed:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css'); ?>">
<script>
// Datang dari transisi zoom? Buka halaman dengan efek "portal" dari titik tombol yang ditekan.
try {
    var z = JSON.parse(sessionStorage.getItem('zoomHalaman') || 'null');
    if (z) {
        var d = document.documentElement;
        d.classList.add('zoom-in');
        d.style.setProperty('--zx', z.x + 'px');
        d.style.setProperty('--zy', z.y + 'px');
        d.style.setProperty('--pc', z.c);
        sessionStorage.removeItem('zoomHalaman');
    }
} catch (e) {}
</script>
</head>
<body class="<?= e($body_class); ?>">

<div class="halaman">
<?php if (!$tanpa_nav && !$tunda_shell) { buka_shell($halaman, $opsi_shell); } ?>