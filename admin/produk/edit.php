<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/produk_save.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$id = (int)qs('id');
$st = $pdo->prepare('SELECT * FROM produk WHERE id = ?');
$st->execute([$id]);
$lama = $st->fetch();
if (!$lama) { flash('error', 'Produk tidak ditemukan.'); redirect('index.php'); }

$err = [];
$form = $lama;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    [$err, $form, $idOk] = produk_simpan($pdo, $lama);
    if (!$err) {
        flash('success', 'Perubahan produk “' . $form['nama'] . '” disimpan.');
        redirect('index.php');
    }
}
$adm = ['title' => 'Edit produk', 'nav' => 'produk'];
require __DIR__ . '/../includes/layout_top.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../includes/layout_bottom.php';
