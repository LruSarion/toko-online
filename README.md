# Tokkoku Laku — Toko Online (PHP + MySQL)

Versi yang sudah dikembangkan dari toko lama: tampilan baru yang lebih profesional, checkout lengkap (ongkir, metode bayar, bukti transfer), panel admin yang jauh lebih lengkap, dan penguatan keamanan. Tetap memakai **PHP + MySQL biasa tanpa framework**, jadi cocok untuk hosting gratis seperti InfinityFree.

---

## 1. Cara memasang (upgrade dari versi lama)

> **Backup dulu**: unduh semua file di hosting dan ekspor database dari phpMyAdmin.

1. **Simpan file `config/database.php` milikmu** (berisi host, nama database, user, password). File ini **tidak ada di zip ini** dengan sengaja, supaya kredensialmu tidak ikut tertimpa atau tersebar.
2. Upload seluruh isi zip ke folder `htdocs` (timpa file lama). Pastikan `config/database.php` milikmu tetap ada. Kalau belum ada, salin `config/database.example.php` menjadi `config/database.php` lalu isi kredensialnya.
3. Buka toko di browser. **Migrasi database berjalan otomatis** pada kunjungan pertama: hanya *menambah* tabel/kolom baru dan tidak pernah menghapus data. Semua produk, pelanggan, pesanan, dan poin lama tetap aman.
4. Masuk ke `/admin/login.php`, lalu **segera ganti password** di menu *Akun & sistem*.
5. Buka `config/app.php` dan sesuaikan (lihat bagian 2).

Pasang baru (tanpa data lama)? Impor `database/toko_online.sql` lewat phpMyAdmin, isi `config/database.php`, lalu buka toko. File SQL itu berisi data contoh, jadi hapus dulu produk contoh jika tidak dipakai.

---

## 2. Yang WAJIB kamu sesuaikan sebelum toko dibuka

Semua ada di **`config/app.php`**:

| Pengaturan | Keterangan |
|---|---|
| `ongkir` | **Angka ongkir hanya contoh** (Reguler Rp20.000, Ekspres Rp40.000, Ambil di toko Rp0). Ganti dengan tarif sebenarnya. |
| `rekening` | **Masih kosong.** Isi nomor rekening agar pelanggan bisa transfer. Selama kosong, pelanggan diarahkan menanyakan rekening lewat WhatsApp. |
| `metode_bayar` | Hapus "COD" jika tidak melayani bayar di tempat. |
| `gratis_ongkir_min` | Isi nominal belanja untuk gratis ongkir, atau biarkan `0` untuk menonaktifkan. |
| `whatsapp`, `email`, `alamat`, `url` | Dipakai di footer, tombol WhatsApp, invoice, dan sitemap. |
| `kategori` | Daftar kategori produk. Bisa ditambah atau diubah. |

---

## 3. Keamanan: kerjakan ini

- **Password admin lama lemah** (`admin123`, `michael123`). Saat pertama kali dimuat, password di database otomatis di-hash, tetapi **nilainya tetap sama**, jadi harus diganti lewat *Akun & sistem*. Dashboard menampilkan peringatan sampai password diganti.
- **Ganti password database** di panel hosting, lalu perbarui `config/database.php`. File itu sempat ikut di dalam zip yang kamu unggah, jadi anggap kredensialnya sudah terlihat pihak lain.
- Setelah terpasang, **hapus folder `database/`** dari hosting (hanya dipakai untuk pemasangan baru). Isinya dump lama dengan password admin teks biasa. Folder ini sudah diblokir lewat `.htaccess`, tetapi lebih aman jika dihapus.
- Folder `config/`, `includes/`, `admin/includes/`, `database/`, dan `uploads/bukti/` diblokir dari akses langsung lewat `.htaccess`. Bukti pembayaran hanya bisa dilihat pemilik pesanan dan admin (lewat `public/bukti.php`).

Yang sudah ditambahkan di kode: token CSRF di semua form, cookie sesi aman, pembatasan percobaan login (5 gagal = tunggu 15 menit), password admin ter-hash, validasi upload gambar, perlindungan open-redirect, dan penguncian stok saat checkout agar tidak terjual melebihi stok.

---

## 4. Fitur

**Toko**
- Beranda dengan hero, produk unggulan, filter kategori, rentang harga, "hanya yang tersedia", dan urutan.
- Halaman produk: harga coret + label diskon, stok, produk terkait, data terstruktur untuk Google (JSON-LD).
- Keranjang dengan validasi stok ulang. Checkout bertahap: data penerima, alamat, pengiriman, pembayaran, catatan.
- Pesanan: timeline status, no. resi, unggah bukti transfer, pembatalan oleh pelanggan (selama masih "Menunggu").
- Poin loyalitas (1 poin per Rp10.000 nilai barang, tanpa ongkir) dan chat dengan admin.
- Tombol WhatsApp di banyak titik, halaman Bantuan/FAQ, `sitemap.php` dinamis, `robots.txt`, tag Open Graph.
- Tampilan responsif (HP, tablet, desktop). Gambar produk dibuat versi ringan (WebP) otomatis jika server mendukung GD/WebP.

**Admin** (`/admin/`)
- Dashboard: pendapatan bulan ini, jumlah pesanan, grafik 14 hari, stok menipis, produk terlaris, pesanan terbaru.
- Produk: tambah/ubah/hapus, kategori, harga coret, stok, unggulan, tampil/sembunyi, unggah foto dengan pratinjau. Produk yang pernah dipesan **tidak dihapus permanen**, hanya disembunyikan, supaya riwayat pesanan tetap utuh.
- Pesanan: filter status, tanggal, dan pencarian. Alur status yang dijaga (Menunggu → Diproses → Dikirim → Selesai, atau Dibatalkan). Stok dikembalikan otomatis saat batal dan poin diberikan **sekali saja** saat selesai. Ada invoice cetak/PDF dan tombol hubungi pelanggan via WhatsApp.
- Pelanggan: ubah data, koreksi poin (dengan alasan), reset password, hapus akun (pesanannya tetap tersimpan sebagai pesanan tamu).
- Chat: daftar percakapan yang perlu dibalas.
- Akun & sistem: ganti password, info versi, jalankan ulang migrasi.

---

## 5. Struktur folder

```
config/        app.php (pengaturan toko), database.php (kredensial — milikmu)
includes/      bootstrap, migrasi database, logika pesanan, komponen tampilan
public/        halaman toko + assets (css, js, gambar)
admin/         panel admin
uploads/       foto produk (product/) dan bukti bayar (bukti/, privat)
database/      dump SQL untuk pemasangan baru
```

---

## 6. Catatan penting

- **Pengujian.** Server MySQL tidak tersedia di lingkungan pengembangan, jadi alur lengkap (belanja, checkout, status pesanan, stok, poin, admin, keamanan; 99 pemeriksaan otomatis dengan browser sungguhan) diuji pada **SQLite dengan lapisan penerjemah** untuk beberapa sintaks MySQL. Kode sudah dibuat memakai SQL standar, tetapi **belum pernah dijalankan pada MySQL/MariaDB sungguhan**. Bagian yang khusus MySQL (`information_schema`, `ALTER TABLE ... ADD INDEX`, `ON DUPLICATE KEY UPDATE`, `FOR UPDATE`) hanya diperiksa secara tertulis. Setelah upload, coba satu pesanan percobaan dari awal sampai selesai. Jika migrasi gagal, halaman *Akun & sistem* menampilkan log langkah per langkah.
- **Belum ada** payment gateway, cek ongkir otomatis lewat API kurir, atau notifikasi email. Konfirmasi pembayaran dilakukan manual oleh admin dari bukti transfer.
- `public/sitemap.xml` lama diganti `public/sitemap.php` (otomatis mengikuti produk aktif). Alamatnya sudah dicantumkan di `robots.txt`. Kirim ulang di Google Search Console jika perlu.
- Zip yang kamu unggah berisi folder `toko-online/toko-online/` bertingkat (salinan lama). Salinan itu **tidak disertakan**.
- Jika gambar produk tidak tampil setelah upload, pastikan folder `uploads/product/` ada dan bisa ditulis (permission 755/775).
