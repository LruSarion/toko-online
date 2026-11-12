<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin('../login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
csrf_guard();

$id = (int)post('id');
$nama = scalar($pdo, 'SELECT nama FROM pelanggan WHERE id = ?', [$id]);
if ($nama === false) { flash('error', 'Pelanggan tidak ditemukan.'); redirect('index.php'); }

try {
    $pdo->beginTransaction();
    // Pesanan dipertahankan (jadi pesanan tamu) agar laporan penjualan dan stok tetap benar.
    $pdo->prepare('UPDATE pesanan SET pelanggan_id = NULL WHERE pelanggan_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM riwayat_poin WHERE pelanggan_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM chat_messages WHERE pelanggan_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM pelanggan WHERE id = ?')->execute([$id]);
    $pdo->commit();
    flash('success', 'Pelanggan “' . $nama . '” dihapus.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('hapus pelanggan: ' . $e->getMessage());
    flash('error', 'Pelanggan gagal dihapus. Silakan coba lagi.');
}
redirect('index.php');
