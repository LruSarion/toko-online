<?php
/**
 * Migrasi skema database — hanya MENAMBAH tabel/kolom/index yang belum ada (tidak pernah menghapus data).
 * Dijalankan otomatis sekali oleh ensure_schema() setelah kode baru dipasang; aman diulang.
 */
function jalankan_migrasi(PDO $pdo): array {
    $log = []; $gagal = false;
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $ada = function (string $sql, array $p) use ($pdo): bool { $s = $pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn() > 0; };
    $tabel = function (string $t) use ($ada, $db): bool { return $ada('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$db, $t]); };
    $kolom = function (string $t, string $c) use ($ada, $db): bool { return $ada('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$db, $t, $c]); };
    $index = function (string $t, string $i) use ($ada, $db): bool { return $ada('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?', [$db, $t, $i]); };
    $jalan = function (string $sql, string $label) use ($pdo, &$log, &$gagal) {
        try { $pdo->exec($sql); $log[] = 'OK  ' . $label; }
        catch (Throwable $e) { $gagal = true; $log[] = 'GAGAL  ' . $label . ' — ' . $e->getMessage(); }
    };

    if (!$tabel('pengaturan')) {
        $jalan('CREATE TABLE pengaturan (kunci VARCHAR(50) NOT NULL PRIMARY KEY, nilai VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'Tabel pengaturan');
    }
    if (!$tabel('produk_foto')) {
        $jalan("CREATE TABLE produk_foto (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, produk_id INT NOT NULL, gambar VARCHAR(255) NOT NULL, urutan INT NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_produk_foto (produk_id, urutan)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Tabel foto produk');
    }
    if (!$tabel('ulasan_produk')) {
        $jalan("CREATE TABLE ulasan_produk (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, produk_id INT NOT NULL, pesanan_id INT NOT NULL, pelanggan_id INT NOT NULL, rating TINYINT NOT NULL, ulasan TEXT NOT NULL, foto VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_ulasan_order_product (pesanan_id, produk_id), KEY idx_ulasan_produk (produk_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Tabel ulasan produk');
    }
    if (!$tabel('percobaan_login')) {
        $jalan('CREATE TABLE percobaan_login (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, kunci VARCHAR(80) NOT NULL, waktu DATETIME NOT NULL, KEY idx_kunci_waktu (kunci, waktu)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'Tabel percobaan_login');
    }
    if (!$tabel('chat_messages')) {
        $jalan("CREATE TABLE chat_messages (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, pelanggan_id INT NOT NULL, pengirim ENUM('pelanggan','admin') NOT NULL DEFAULT 'pelanggan', pesan TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_chat_pelanggan (pelanggan_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'Tabel chat_messages');
    }
    $tambah = [
        'produk' => [
            'kategori'    => 'VARCHAR(60) NULL',
            'harga_coret' => 'INT NULL',
            'unggulan'    => 'TINYINT(1) NOT NULL DEFAULT 0',
            'aktif'       => 'TINYINT(1) NOT NULL DEFAULT 1',
        ],
        'pesanan' => [
            'ongkir'       => 'INT NOT NULL DEFAULT 0',
            'kurir'        => 'VARCHAR(60) NULL',
            'no_resi'      => 'VARCHAR(80) NULL',
            'metode_bayar' => 'VARCHAR(40) NULL',
            'catatan'      => 'TEXT NULL',
            'bukti_bayar'  => 'VARCHAR(255) NULL',
        ],
    ];
    foreach ($tambah as $t => $cols) {
        foreach ($cols as $c => $def) {
            if (!$kolom($t, $c)) { $jalan("ALTER TABLE `$t` ADD COLUMN `$c` $def", "Kolom $t.$c"); }
        }
    }
    if (!$index('produk', 'idx_produk_kategori')) { $jalan('ALTER TABLE produk ADD INDEX idx_produk_kategori (kategori)', 'Index produk.kategori'); }
    if (!$index('pesanan', 'idx_pesanan_status')) { $jalan('ALTER TABLE pesanan ADD INDEX idx_pesanan_status (status, tanggal_pesanan)', 'Index pesanan.status'); }

    // Amankan password admin lama yang masih berupa teks biasa
    try {
        $up = $pdo->prepare('UPDATE admin SET password = ? WHERE id = ?');
        foreach ($pdo->query('SELECT id, password FROM admin')->fetchAll() as $a) {
            if (!preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', (string)$a['password'])) {
                $up->execute([password_hash((string)$a['password'], PASSWORD_DEFAULT), (int)$a['id']]);
                $log[] = 'OK  Password admin #' . (int)$a['id'] . ' diamankan (hash)';
            }
        }
    } catch (Throwable $e) { $log[] = 'LEWAT  Tabel admin tidak dapat diproses — ' . $e->getMessage(); }

    if (!$gagal) {
        $jalan("INSERT INTO pengaturan (kunci, nilai) VALUES ('schema_version', '" . (int)SCHEMA_VERSION . "') ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)", 'Versi skema ' . (int)SCHEMA_VERSION);
    }
    return $log;
}
function ensure_schema(PDO $pdo): void {
    try { $v = (int)$pdo->query("SELECT nilai FROM pengaturan WHERE kunci = 'schema_version'")->fetchColumn(); }
    catch (Throwable $e) { $v = 0; }
    if ($v < SCHEMA_VERSION) { jalankan_migrasi($pdo); }
}
