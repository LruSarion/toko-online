<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin('../login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
csrf_guard();

$id = (int)post('id');
$st = $pdo->prepare('SELECT id, nama, gambar FROM produk WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { flash('error', 'Produk tidak ditemukan.'); redirect('index.php'); }

try {
    // Produk yang pernah dipesan tidak dihapus agar riwayat pesanan tetap utuh — cukup disembunyikan.
    if ((int)scalar($pdo, 'SELECT COUNT(*) FROM detail_pesanan WHERE produk_id = ?', [$id]) > 0) {
        $pdo->prepare('UPDATE produk SET aktif = 0, unggulan = 0 WHERE id = ?')->execute([$id]);
        flash('info', '“' . $p['nama'] . '” pernah dipesan, jadi tidak dihapus permanen — produk disembunyikan dari toko.');
    } else {
        $pdo->prepare('DELETE FROM produk WHERE id = ?')->execute([$id]);
        hapus_gambar_produk($pdo, $p['gambar'], $id);
        flash('success', 'Produk “' . $p['nama'] . '” dihapus.');
    }
} catch (Throwable $e) {
    error_log('hapus produk: ' . $e->getMessage());
    flash('error', 'Produk gagal dihapus. Silakan coba lagi.');
}
redirect('index.php');
