<?php
require 'includes/auth.php';
unset($_SESSION['user']);
session_regenerate_id(true);
flash_set('Anda sudah keluar.');
redirect('login.php');