<?php
/** Logika pesanan yang dipakai bersama oleh sisi pelanggan dan admin. */

function transisi_status(): array {
    return [
        'Menunggu'   => ['Diproses', 'Dibatalkan'],
        'Diproses'   => ['Dikirim', 'Dibatalkan'],
        'Dikirim'    => ['Selesai', 'Dibatalkan'],
        'Selesai'    => [],
        'Dibatalkan' => ['Menunggu'],
    ];
}

/**
 * Ubah status pesanan secara atomik: validasi alur, kembalikan/kurangi stok saat batal/buka kembali,
 * dan beri poin (sekali saja) saat Selesai. $milikPelanggan diisi bila yang mengubah adalah pelanggan
 * (hanya boleh membatalkan pesanan miliknya yang masih "Menunggu").
 * @return array [statusLama, statusBaru]
 * @throws AppError bila perubahan tidak diperbolehkan
 */
function ubah_status_pesanan(PDO $pdo, int $id, string $baru, ?int $milikPelanggan = null): array {
    $own = !$pdo->inTransaction();
    if ($own) { $pdo->beginTransaction(); }
    try {
        $sql = 'SELECT id, pelanggan_id, total_harga, ongkir, status FROM pesanan WHERE id = ?' . ($milikPelanggan !== null ? ' AND pelanggan_id = ?' : '') . ' FOR UPDATE';
        $st = $pdo->prepare($sql);
        $st->execute($milikPelanggan !== null ? [$id, $milikPelanggan] : [$id]);
        $o = $st->fetch();
        if (!$o) { throw new AppError('Pesanan tidak ditemukan.'); }
        $lama = $o['status'];
        if ($lama === $baru) { if ($own) { $pdo->commit(); } return [$lama, $baru]; }
        if (!in_array($baru, transisi_status()[$lama] ?? [], true)) {
            throw new AppError('Status tidak dapat diubah dari ' . $lama . ' ke ' . $baru . '.');
        }
        if ($milikPelanggan !== null && !($lama === 'Menunggu' && $baru === 'Dibatalkan')) {
            throw new AppError('Pesanan ini sudah tidak dapat dibatalkan. Silakan hubungi admin.');
        }
        $d = $pdo->prepare('SELECT produk_id, jumlah FROM detail_pesanan WHERE pesanan_id = ?');
        $d->execute([$id]);
        $baris = $d->fetchAll();
        if ($baru === 'Dibatalkan') {
            $u = $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id = ?');
            foreach ($baris as $b) { $u->execute([(int)$b['jumlah'], (int)$b['produk_id']]); }
        } elseif ($lama === 'Dibatalkan') {
            $u = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?');
            foreach ($baris as $b) {
                $u->execute([(int)$b['jumlah'], (int)$b['produk_id'], (int)$b['jumlah']]);
                if ($u->rowCount() === 0) { throw new AppError('Stok tidak cukup untuk membuka kembali pesanan ini.'); }
            }
        }
        $pdo->prepare('UPDATE pesanan SET status = ? WHERE id = ?')->execute([$baru, $id]);

        if ($baru === 'Selesai' && !empty($o['pelanggan_id'])) {
            $poin = hitungPoin((int)$o['total_harga'] - (int)$o['ongkir']);   // poin dihitung dari nilai barang, tanpa ongkir
            $ref = 'PESANAN-' . $id;
            if ($poin > 0 && !scalar($pdo, 'SELECT id FROM riwayat_poin WHERE referensi = ? LIMIT 1', [$ref])) {
                $pdo->prepare('UPDATE pelanggan SET poin = poin + ? WHERE id = ?')->execute([$poin, (int)$o['pelanggan_id']]);
                $pdo->prepare("INSERT INTO riwayat_poin (pelanggan_id, jumlah, tipe, keterangan, referensi) VALUES (?, ?, 'masuk', ?, ?)")
                    ->execute([(int)$o['pelanggan_id'], $poin, 'Poin dari pesanan #' . $id, $ref]);
            }
        }
        if ($own) { $pdo->commit(); }
        return [$lama, $baru];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
