<?php
// includes/footer.php - modal hapus, toast, dan script
$flash = flash_ambil();
tutup_shell();
?>

</div><!-- /.halaman -->

<div class="modal" id="modalHapus" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalJudul">
        <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
        </div>
        <h3 id="modalJudul">Hapus data ini?</h3>
        <p id="modalTeks"></p>
        <form method="POST" action="hapus.php" data-zoom="red">
            <input type="hidden" name="jenis" id="hapusJenis">
            <input type="hidden" name="id" id="hapusId">
            <div class="modal-actions">
                <button type="button" class="btn-ghost" data-tutup>Batal</button>
                <button type="submit" class="btn-danger">Ya, hapus</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"
     <?= $flash ? 'data-flash="' . e($flash['pesan']) . '" data-tipe="' . e($flash['tipe']) . '"' : ''; ?>>
    <i>✓</i><span id="toastMsg"></span>
</div>

<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/../assets/app.js'); ?>" defer></script>
</body>
</html>