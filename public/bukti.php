<?php
// Menampilkan bukti pembayaran — hanya untuk admin atau pemilik pesanan.
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)qs('id');
$st = $pdo->prepare('SELECT pelanggan_id, bukti_bayar FROM pesanan WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
$boleh = $o && (admin_id() || (pelanggan_id() && (int)$o['pelanggan_id'] === pelanggan_id()));
$path = $o ? ROOT_PATH . '/uploads/bukti/' . basename((string)$o['bukti_bayar']) : '';
if (!$boleh || empty($o['bukti_bayar']) || !is_file($path)) { abort_page(404, 'Tidak ditemukan', 'Bukti pembayaran tidak tersedia.'); }
$tipe = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $tipe);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
