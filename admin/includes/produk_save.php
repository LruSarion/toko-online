<?php
/**
 * Validasi + simpan produk (dipakai tambah.php dan edit.php).
 * @param array|null $lama baris produk yang sedang diedit (null = produk baru)
 * @return array [errors, form, idProduk|null]
 */
function produk_simpan(PDO $pdo, ?array $lama): array
{
    global $APP;
    $angka = function (string $k, string $d = '') { return preg_replace('/\D+/', '', post($k, $d)); };
    $f = [
        'nama'        => post('nama'),
        'kategori'    => post('kategori'),
        'harga'       => $angka('harga'),
        'harga_coret' => $angka('harga_coret'),
        'stok'        => $angka('stok', '0'),
        'deskripsi'   => post('deskripsi'),
        'unggulan'    => isset($_POST['unggulan']) ? 1 : 0,
        'aktif'       => isset($_POST['aktif']) ? 1 : 0,
        'gambar'      => $lama['gambar'] ?? null,
    ];
    $err = [];
    $MAKS = 2000000000;

    if ($f['nama'] === '') { $err['nama'] = 'Nama produk wajib diisi.'; }
    elseif (mb_strlen($f['nama']) > 100) { $err['nama'] = 'Nama produk maksimal 100 karakter.'; }

    if ($f['harga'] === '' || (int)$f['harga'] < 1) { $err['harga'] = 'Harga wajib diisi (minimal Rp1).'; }
    elseif ((int)$f['harga'] > $MAKS || strlen($f['harga']) > 10) { $err['harga'] = 'Harga terlalu besar.'; }

    if ($f['harga_coret'] !== '') {
        if (strlen($f['harga_coret']) > 10 || (int)$f['harga_coret'] > $MAKS) { $err['harga_coret'] = 'Harga coret terlalu besar.'; }
        elseif (!isset($err['harga']) && (int)$f['harga_coret'] <= (int)$f['harga']) { $err['harga_coret'] = 'Harga coret harus lebih tinggi dari harga jual (atau kosongkan).'; }
    }
    if (strlen($f['stok']) > 7) { $err['stok'] = 'Stok terlalu besar.'; }

    $kategoriLama = (string)($lama['kategori'] ?? '');
    if ($f['kategori'] !== '' && !in_array($f['kategori'], $APP['kategori'], true) && $f['kategori'] !== $kategoriLama) { $err['kategori'] = 'Pilih kategori dari daftar.'; }
    if (mb_strlen($f['deskripsi']) > 5000) { $err['deskripsi'] = 'Deskripsi maksimal 5000 karakter.'; }

    // Upload gambar (opsional)
    $baru = null;
    $adaFile = isset($_FILES['gambar']) && (int)($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($adaFile && !$err) {
        [$ok, $hasil] = upload_gambar($_FILES['gambar'], ROOT_PATH . '/uploads/product', 'p_');
        if ($ok) { $baru = $hasil; } else { $err['gambar'] = $hasil; }
    } elseif ($adaFile) {
        $err['gambar'] = 'Pilih ulang gambar setelah memperbaiki isian lain.';
    }
    $fotoBaru = [];
    $multi = $_FILES['foto_tambahan'] ?? null;
    if ($multi && is_array($multi['name'] ?? null)) {
        $jumlah = count($multi['name']);
        if ($jumlah > 12) { $err['gambar'] = 'Maksimal 12 foto tambahan sekali unggah.'; }
        for ($i=0; $i<min($jumlah,12); $i++) {
            if (($multi['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $one = ['name'=>$multi['name'][$i], 'type'=>$multi['type'][$i] ?? '', 'tmp_name'=>$multi['tmp_name'][$i] ?? '', 'error'=>$multi['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size'=>$multi['size'][$i] ?? 0];
            if ($err) break;
            [$ok,$hasil] = upload_gambar($one, ROOT_PATH . '/uploads/product', 'p_');
            if ($ok) $fotoBaru[] = $hasil; else $err['gambar'] = $hasil;
        }
    }
    if ($err) { foreach($fotoBaru as $fn) @unlink(ROOT_PATH.'/uploads/product/'.$fn); if($baru) @unlink(ROOT_PATH.'/uploads/product/'.$baru); return [$err, $f, null]; }

    $hapus = $lama && !$baru && isset($_POST['hapus_gambar']);
    $gambarAkhir = $baru ?? ($hapus ? null : ($lama['gambar'] ?? null));
    $coret = $f['harga_coret'] === '' ? null : (int)$f['harga_coret'];
    $kat = $f['kategori'] === '' ? null : $f['kategori'];
    try {
        if ($lama) {
            $pdo->prepare('UPDATE produk SET nama = ?, kategori = ?, harga = ?, harga_coret = ?, stok = ?, deskripsi = ?, gambar = ?, unggulan = ?, aktif = ? WHERE id = ?')
                ->execute([$f['nama'], $kat, (int)$f['harga'], $coret, (int)$f['stok'], $f['deskripsi'], $gambarAkhir, $f['unggulan'], $f['aktif'], (int)$lama['id']]);
            $id = (int)$lama['id'];
        } else {
            $pdo->prepare('INSERT INTO produk (nama, kategori, harga, harga_coret, stok, deskripsi, gambar, unggulan, aktif) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$f['nama'], $kat, (int)$f['harga'], $coret, (int)$f['stok'], $f['deskripsi'], $gambarAkhir, $f['unggulan'], $f['aktif']]);
            $id = (int)$pdo->lastInsertId();
        }
    } catch (Throwable $ex) {
        error_log('produk_simpan: ' . $ex->getMessage());
        if ($baru) { @unlink(ROOT_PATH . '/uploads/product/' . $baru); }
        return [['_' => 'Produk gagal disimpan. Silakan coba lagi.'], $f, null];
    }
    if ($fotoBaru) {
        $ins = $pdo->prepare('INSERT INTO produk_foto (produk_id, gambar, urutan) VALUES (?,?,?)');
        $max = (int)scalar($pdo, 'SELECT COALESCE(MAX(urutan),0) FROM produk_foto WHERE produk_id=?', [$id]);
        foreach ($fotoBaru as $fn) { $ins->execute([$id, $fn, ++$max]); }
    }
    if ($lama && !empty($lama['gambar']) && ($baru || $hapus)) { hapus_gambar_produk($pdo, $lama['gambar'], $id); }
    $f['gambar'] = $gambarAkhir;
    return [[], $f, $id];
}
