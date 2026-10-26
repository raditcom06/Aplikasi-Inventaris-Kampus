<?php
// includes/fungsi.php - fungsi bantu yang dipakai semua halaman
if (session_status() === PHP_SESSION_NONE) session_start();

function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function flash_set($pesan, $tipe = 'sukses') { $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe]; }
function flash_ambil() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function redirect($url) { header("Location: $url"); exit; }

function kelas_kondisi($k) { return $k === 'Baik' ? 'baik' : ($k === 'Rusak Ringan' ? 'ringan' : 'berat'); }