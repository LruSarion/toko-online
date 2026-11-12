<?php
/**
 * Bootstrap bersama untuk semua halaman (toko & admin):
 * sesi aman, koneksi database, migrasi skema otomatis, dan fungsi bantu.
 */
if (defined('TOKO_BOOT')) { return; }
define('TOKO_BOOT', true);
define('ROOT_PATH', dirname(__DIR__));
define('SCHEMA_VERSION', 3);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Jakarta');
$APP = require ROOT_PATH . '/config/app.php';

if (empty($APP['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// ---------- Sesi ----------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/** Kesalahan yang pesannya aman ditampilkan ke pengguna (berbeda dari error database). */
class AppError extends Exception {}

// ---------- Fungsi bantu umum ----------
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rupiah($n): string { return 'Rp' . number_format((float)$n, 0, ',', '.'); }
function tgl($ts, bool $jam = false): string {
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    if (!$t) { return '-'; }
    $bln = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $s = date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $jam ? $s . ', ' . date('H.i', $t) : $s;
}
function qs(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function scalar(PDO $pdo, string $sql, array $params = []) { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn(); }

function origin(): string {
    global $APP;
    $u = rtrim((string)($APP['url'] ?? ''), '/');
    if ($u !== '') { return $u; }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
/** Ubah path relatif terhadap skrip aktif (mis. "../uploads/a.png") menjadi URL absolut. */
function abs_url(string $rel): string {
    $base = array_values(array_filter(explode('/', trim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/')), 'strlen'));
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '..') { array_pop($base); }
        elseif ($seg !== '.' && $seg !== '') { $base[] = $seg; }
    }
    return origin() . '/' . implode('/', $base);
}
function wa_link(string $text = ''): string {
    global $APP;
    return 'https://wa.me/' . $APP['whatsapp'] . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
function wa_number(string $hp): string {
    $d = preg_replace('/\D/', '', $hp);
    if ($d === '') { return ''; }
    if (strpos($d, '62') === 0) { return $d; }
    if ($d[0] === '0') { return '62' . substr($d, 1); }
    return '62' . $d;
}
function kode_pesanan($id, $tanggal): string {
    return 'TK' . date('ymd', (int)strtotime((string)$tanggal)) . str_pad((string)(int)$id, 4, '0', STR_PAD_LEFT);
}
/** Hanya izinkan tujuan redirect berupa file .php lokal (mencegah open redirect). */
function safe_next(?string $n, string $default = 'index.php'): string {
    $n = (string)$n;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $n) ? $n : $default;
}
function back_url(): string {
    $q = $_SERVER['QUERY_STRING'] ?? '';
    return basename($_SERVER['SCRIPT_NAME'] ?? 'index.php') . ($q !== '' ? '?' . $q : '');
}
function abort_page(int $code, string $judul, string $pesan): void {
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="id"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($judul) . '</title><body style="font-family:system-ui,sans-serif;background:#f5f4f0;color:#1b1e22;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="max-width:420px;padding:32px;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.08);text-align:center">'
       . '<h1 style="margin:0 0 8px;font-size:22px">' . e($judul) . '</h1><p style="color:#6a6f76;line-height:1.6">' . e($pesan) . '</p>'
       . '<p><a href="javascript:history.back()" style="color:#7d5f24;font-weight:600">&larr; Kembali</a></p></div></body></html>';
    exit;
}

// ---------- CSRF & flash ----------
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_guard(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || $t === '' || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
        abort_page(419, 'Sesi kedaluwarsa', 'Halaman sudah terlalu lama dibuka atau formulir tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}
function flash(string $type, string $msg, ?array $link = null): void { $_SESSION['_flash'][] = [$type, $msg, $link]; }
function flash_pull(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

// ---------- Autentikasi ----------
function pelanggan_id(): int { return (int)($_SESSION['pelanggan_id'] ?? 0); }
function admin_id(): int { return (int)($_SESSION['admin_id'] ?? 0); }
function require_login(): void {
    if (!pelanggan_id()) {
        flash('info', 'Silakan masuk dulu untuk melanjutkan.');
        redirect('login.php?next=' . urlencode(back_url()));
    }
}
function require_admin(string $loginPath = 'login.php'): void { if (!admin_id()) { redirect($loginPath); } }

/** Cek password admin (hash modern; password teks-biasa lama otomatis di-hash saat cocok). */
function admin_cek_password(PDO $pdo, array $admin, string $pw): bool {
    $h = (string)$admin['password'];
    if (preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $h)) {
        if (!password_verify($pw, $h)) { return false; }
        if (password_needs_rehash($h, PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE admin SET password = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$admin['id']]);
        }
        return true;
    }
    if ($h !== '' && hash_equals($h, $pw)) {
        $pdo->prepare('UPDATE admin SET password = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$admin['id']]);
        return true;
    }
    return false;
}

function throttle_key(string $tipe, string $id): string { return $tipe . ':' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($id)); }
function throttle_blocked(PDO $pdo, string $key, int $max = 5, int $menit = 15): bool {
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM percobaan_login WHERE kunci = ? AND waktu > ?');
        $s->execute([$key, date('Y-m-d H:i:s', time() - $menit * 60)]);
        return (int)$s->fetchColumn() >= $max;
    } catch (Throwable $e) { return false; }
}
function throttle_fail(PDO $pdo, string $key): void {
    try {
        $pdo->prepare('INSERT INTO percobaan_login (kunci, waktu) VALUES (?, ?)')->execute([$key, date('Y-m-d H:i:s')]);
        if (mt_rand(1, 40) === 1) { $pdo->prepare('DELETE FROM percobaan_login WHERE waktu < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]); }
    } catch (Throwable $e) { /* abaikan */ }
}
function throttle_clear(PDO $pdo, string $key): void {
    try { $pdo->prepare('DELETE FROM percobaan_login WHERE kunci = ?')->execute([$key]); } catch (Throwable $e) { /* abaikan */ }
}

// ---------- Gambar ----------
function make_variant(string $src, string $dst, int $max): bool {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) { return false; }
    $data = @file_get_contents($src);
    if ($data === false) { return false; }
    $im = @imagecreatefromstring($data);
    if (!$im) { return false; }
    $w = imagesx($im); $h = imagesy($im);
    $r = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if (!is_dir(dirname($dst))) { @mkdir(dirname($dst), 0755, true); }
    $ok = @imagewebp($out, $dst, 82);
    imagedestroy($im); imagedestroy($out);
    return $ok && is_file($dst);
}
/** URL gambar produk. $size: 'thumb' (640px), 'md' (1100px) atau 'orig'. Varian dibuat otomatis bila belum ada. */
function produk_img(?string $g, string $root, string $size = 'thumb'): string {
    if (!$g) { return ''; }
    $g = basename($g);
    $dir = ROOT_PATH . '/uploads/product/';
    // Jika file tidak terbaca dari path server (struktur hosting berbeda), tetap kembalikan URL aslinya.
    if (is_file($dir . $g) && $size !== 'orig') {
        $v = $size . '/' . pathinfo($g, PATHINFO_FILENAME) . '.webp';
        if (!is_file($dir . $v)) { make_variant($dir . $g, $dir . $v, $size === 'md' ? 1100 : 640); }
        if (is_file($dir . $v)) { return $root . 'uploads/product/' . $v; }
    }
    return $root . 'uploads/product/' . rawurlencode($g);
}
function hapus_gambar_produk(PDO $pdo, ?string $g, int $exceptId = 0): void {
    if (!$g) { return; }
    $g = basename($g);
    if ((int)scalar($pdo, 'SELECT COUNT(*) FROM produk WHERE gambar = ? AND id <> ?', [$g, $exceptId]) > 0) { return; }
    $dir = ROOT_PATH . '/uploads/product/';
    $stem = pathinfo($g, PATHINFO_FILENAME);
    foreach ([$g, 'thumb/' . $stem . '.webp', 'md/' . $stem . '.webp'] as $f) { if (is_file($dir . $f)) { @unlink($dir . $f); } }
}
/** Validasi & simpan upload gambar. Return [true, namaFile] atau [false, pesanError]. */
function upload_gambar(array $file, string $destDir, string $prefix, int $maxBytes = 4194304): array {
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { return [false, 'Ukuran gambar melebihi batas server.']; }
    if ($err !== UPLOAD_ERR_OK) { return [false, 'Upload gambar gagal. Coba lagi.']; }
    if ((int)$file['size'] > $maxBytes) { return [false, 'Ukuran gambar maksimal ' . round($maxBytes / 1048576) . ' MB.']; }
    $info = @getimagesize($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($map[$info['mime']])) { return [false, 'Format gambar harus JPG, PNG, atau WEBP.']; }
    if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
    $name = $prefix . bin2hex(random_bytes(10)) . '.' . $map[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], rtrim($destDir, '/') . '/' . $name)) { return [false, 'Gagal menyimpan gambar di server.']; }
    return [true, $name];
}

// ---------- Keranjang & ongkir ----------
function cart_count(): int { $c = $_SESSION['cart'] ?? []; return is_array($c) ? (int)array_sum(array_map('intval', $c)) : 0; }
/** Muat isi keranjang dari database (harga & stok terbaru) dan bersihkan item yang sudah tidak valid. */
function muat_keranjang(PDO $pdo): array {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) { $_SESSION['cart'] = []; }
    $items = []; $sub = 0; $catatan = [];
    if ($_SESSION['cart']) {
        $ids = array_map('intval', array_keys($_SESSION['cart']));
        $st = $pdo->prepare('SELECT * FROM produk WHERE aktif = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);
        $rows = [];
        foreach ($st->fetchAll() as $r) { $rows[(int)$r['id']] = $r; }
        foreach ($ids as $id) {
            $r = $rows[$id] ?? null;
            $stok = $r ? (int)$r['stok'] : 0;
            $j = (int)$_SESSION['cart'][$id];
            if (!$r || $stok < 1 || $j < 1) {
                unset($_SESSION['cart'][$id]);
                if ($r) { $catatan[] = '“' . $r['nama'] . '” sudah habis dan dihapus dari keranjang.'; }
                continue;
            }
            if ($j > $stok) { $j = $stok; $_SESSION['cart'][$id] = $j; $catatan[] = 'Jumlah “' . $r['nama'] . '” disesuaikan dengan stok tersedia (' . $stok . ').'; }
            $items[] = $r + ['jumlah' => $j, 'subtotal' => $j * (int)$r['harga']];
            $sub += $j * (int)$r['harga'];
        }
    }
    return ['items' => $items, 'subtotal' => $sub, 'catatan' => $catatan];
}
function hitung_ongkir(array $opsi, int $subtotal): int {
    global $APP;
    $min = (int)($APP['gratis_ongkir_min'] ?? 0);
    return ($min > 0 && $subtotal >= $min) ? 0 : (int)$opsi['biaya'];
}
function status_info(string $s): array {
    $m = [
        'Menunggu'   => ['warn', 'Menunggu Konfirmasi'],
        'Diproses'   => ['info', 'Diproses'],
        'Dikirim'    => ['violet', 'Dikirim'],
        'Selesai'    => ['ok', 'Selesai'],
        'Dibatalkan' => ['bad', 'Dibatalkan'],
    ];
    return $m[$s] ?? ['muted', $s];
}
function status_badge(string $s): string { $i = status_info($s); return '<span class="badge b-' . $i[0] . '">' . e($i[1]) . '</span>'; }

// ---------- Modul lain ----------
require_once __DIR__ . '/ui.php';
require_once ROOT_PATH . '/public/functions/poin.php';
require_once __DIR__ . '/pesanan.php';
require_once __DIR__ . '/migrate.php';

// ---------- Database ----------
if (!is_file(ROOT_PATH . '/config/database.php')) {
    abort_page(500, 'Konfigurasi belum lengkap', 'File config/database.php belum ada. Salin config/database.example.php menjadi config/database.php lalu isi kredensial database.');
}
require_once ROOT_PATH . '/config/database.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { abort_page(500, 'Database tidak tersedia', 'Koneksi database belum terbentuk. Periksa config/database.php.'); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
try { $pdo->exec('SET NAMES utf8mb4'); $pdo->exec("SET time_zone = '+07:00'"); } catch (Throwable $e) { /* abaikan */ }
ensure_schema($pdo);
