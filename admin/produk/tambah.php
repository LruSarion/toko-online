<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/produk_save.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$lama = null;
$err = [];
$form = ['nama' => '', 'kategori' => '', 'harga' => '', 'harga_coret' => '', 'stok' => '0', 'deskripsi' => '', 'unggulan' => 0, 'aktif' => 1, 'gambar' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    [$err, $form, $id] = produk_simpan($pdo, null);
    if (!$err) {
        flash('success', 'Produk “' . $form['nama'] . '” berhasil ditambahkan.', ['href' => 'edit.php?id=' . $id, 'label' => 'Edit']);
        redirect('index.php');
    }
}
$adm = ['title' => 'Tambah produk', 'nav' => 'produk'];
require __DIR__ . '/../includes/layout_top.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../includes/layout_bottom.php';
