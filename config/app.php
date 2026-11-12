<?php
/**
 * Pengaturan toko. Ubah nilai di bawah sesuai kebutuhan — tidak perlu menyentuh kode lain.
 */
return [
    'nama'     => 'Tokkoku Laku',
    'tagline'  => 'Gadget & elektronik pilihan',
    'url'      => 'http://localhost/toko-online',     // tanpa "/" di akhir; dipakai untuk canonical, OG & sitemap
    'whatsapp' => '6281933485477',                 // format internasional, tanpa tanda +
    'email'    => '',                              // opsional, tampil di footer
    'alamat'   => '',                              // opsional, tampil di footer
    'promo_bar' => 'Butuh bantuan memilih produk? Tanya kami lewat WhatsApp.',   // kosongkan untuk menyembunyikan
    'google_verification' => '',      // dari kode lama; kosongkan jika tidak dipakai

    'kategori'    => ['Smartphone', 'Laptop', 'Smartwatch', 'TV / Monitor', 'Komputer / PC', 'Aksesoris', 'Gaming', 'Lainnya'],
    'per_halaman' => 12,    // produk per halaman di toko
    'stok_rendah' => 5,     // batas stok "menipis"

    /*
     * OPSI PENGIRIMAN — ANGKA DI BAWAH HANYA CONTOH. Sesuaikan dengan ongkir sebenarnya sebelum toko dibuka.
     * 'kode' harus unik. Biaya 0 = gratis / tanpa ongkir.
     */
    'ongkir' => [
        ['kode' => 'reguler', 'nama' => 'Reguler',       'estimasi' => '3–5 hari kerja',                   'biaya' => 20000],
        ['kode' => 'ekspres', 'nama' => 'Ekspres',       'estimasi' => '1–2 hari kerja',                   'biaya' => 40000],
        ['kode' => 'ambil',   'nama' => 'Ambil di toko', 'estimasi' => 'Siap diambil setelah dikonfirmasi', 'biaya' => 0],
    ],
    'gratis_ongkir_min' => 0,   // belanja minimal (Rp) untuk gratis ongkir; 0 = fitur nonaktif

    // Metode pembayaran yang tampil saat checkout: 'Nama' => 'keterangan singkat'
    'metode_bayar' => [
        'Transfer Bank' => 'Transfer manual, lalu unggah bukti pembayaran.',
        'COD'           => 'Bayar di tempat saat barang diterima (sesuai ketersediaan area).',
    ],
    // Rekening untuk transfer. Kosong = pelanggan diminta menghubungi admin via WhatsApp untuk nomor rekening.
    // Contoh isi: ['bank' => 'BCA', 'nomor' => '1234567890', 'nama' => 'Nama Pemilik'],
    'rekening' => [],

    'debug' => true,   // true hanya saat pengembangan lokal
];
