
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(origin(), '/');

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Halaman utama
echo '  <url>' . "\n";
echo '    <loc>' . $base . '/</loc>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";

// Produk aktif
$stmt = $pdo->query(
    'SELECT id, create_at
     FROM produk
     WHERE aktif = 1
     ORDER BY id DESC
     LIMIT 2000'
);

foreach ($stmt as $r) {
    $id = (int) $r['id'];

    echo '  <url>' . "\n";
    echo '    <loc>' . $base . '/product.php?id=' . $id . '</loc>' . "\n";

    if (!empty($r['create_at'])) {
        $timestamp = strtotime((string) $r['create_at']);

        if ($timestamp !== false) {
            echo '    <lastmod>' . date('Y-m-d', $timestamp) . '</lastmod>' . "\n";
        }
    }

    echo '    <priority>0.8</priority>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>';
