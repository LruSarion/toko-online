<?php
require_once __DIR__ . '/../includes/bootstrap.php';
unset($_SESSION['pelanggan_id'], $_SESSION['pelanggan_nama'], $_SESSION['pelanggan_email'], $_SESSION['last_order_id']);
session_regenerate_id(true);
flash('info', 'Kamu sudah keluar. Sampai jumpa lagi!');
redirect('index.php');
