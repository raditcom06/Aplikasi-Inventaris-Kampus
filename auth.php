<?php
// includes/auth.php - sesi login dan pengecekan peran (admin / user)
require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/skema.php';
require_once __DIR__ . '/kondisi.php';
require_once __DIR__ . '/ui.php';

function user_login() { return $_SESSION['user'] ?? null; }
function is_admin()   { return (user_login()['role'] ?? '') === 'admin'; }

// Halaman yang butuh login (admin maupun user)
function wajib_login() {
    if (!user_login()) redirect('login.php');
}

// Halaman khusus admin; user biasa dikembalikan ke index
function wajib_admin() {
    wajib_login();
    if (!is_admin()) {
        flash_set('Halaman ini khusus admin.', 'error');
        redirect('index.php');
    }
}

function login_user($row) {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $row['id'],
        'nama'     => $row['nama'],
        'username' => $row['username'],
        'role'     => $row['role'],
    ];
}