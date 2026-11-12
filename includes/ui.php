<?php
/** Komponen tampilan yang dipakai bersama: ikon SVG, kartu produk, pagination. */

function icon(string $name, int $size = 20, string $class = ''): string {
    static $p = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2.5l2.2 11h10.6l2-8H6.5"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'down'     => '<path d="m6 9 6 6 6-6"/>',
        'right'    => '<path d="m9 6 6 6-6 6"/>',
        'left'     => '<path d="m15 6-6 6 6 6"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'minus'    => '<path d="M5 12h14"/>',
        'check'    => '<path d="m5 12 4.5 4.5L19 7"/>',
        'truck'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'shield'   => '<path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'headset'  => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="7" rx="1.5"/><rect x="17" y="14" width="4" height="7" rx="1.5"/>',
        'package'  => '<path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
        'card'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'pin'      => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9M12 8c-2.5 0-4-1-4-2.5S9 3 10.5 3C12 3 12 6 12 8zm0 0c2.5 0 4-1 4-2.5S15 3 13.5 3C12 3 12 6 12 8z"/>',
        'home'     => '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/>',
        'logout'   => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'filter'   => '<path d="M4 5h16l-6 8v6l-4-2v-4z"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'print'    => '<path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/>',
        'chart'    => '<path d="M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 5a3.5 3.5 0 0 1 0 7M18 14.5c2 .8 3.5 2.6 3.5 5.5"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'alert'    => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17.2v.1"/>',
        'chat'     => '<path d="M4 5h16v11H9l-5 4z"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'cat-smartphone' => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>',
        'cat-laptop'     => '<rect x="5" y="5" width="14" height="10" rx="1.5"/><path d="M2.5 19h19"/>',
        'cat-smartwatch' => '<rect x="7.5" y="6.5" width="9" height="11" rx="3"/><path d="M9 6.5 9.8 2.5h4.4l.8 4M9 17.5l.8 4h4.4l.8-4"/>',
        'cat-monitor'    => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
        'cat-pc'         => '<rect x="6" y="2.5" width="12" height="19" rx="1.5"/><circle cx="12" cy="9" r="2.5"/><path d="M9 17h6"/>',
        'cat-aksesoris'  => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="7" rx="1.5"/><rect x="17" y="14" width="4" height="7" rx="1.5"/>',
        'cat-gaming'     => '<rect x="2.5" y="7" width="19" height="11" rx="5"/><path d="M7.5 10.5v4M5.5 12.5h4"/><circle cx="15.5" cy="11.5" r=".8"/><circle cx="18" cy="13.5" r=".8"/>',
        'cat-lain'       => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>',
    ];
    $d = $p[$name] ?? $p['package'];
    return '<svg class="i ' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
function icon_wa(int $size = 22): string {
    return '<svg class="i" width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16 3C8.82 3 3 8.82 3 16c0 2.3.6 4.56 1.74 6.55L3 29l6.62-1.7A12.93 12.93 0 0 0 16 29c7.18 0 13-5.82 13-13S23.18 3 16 3Zm0 23.6c-1.98 0-3.92-.53-5.62-1.54l-.4-.24-3.93 1.01 1.05-3.83-.26-.42A10.63 10.63 0 1 1 16 26.6Zm5.83-7.96c-.32-.16-1.9-.94-2.2-1.05-.3-.11-.52-.16-.74.16-.22.33-.84 1.05-1.03 1.27-.19.22-.38.24-.7.08-.32-.16-1.35-.5-2.57-1.58-.95-.85-1.59-1.9-1.78-2.22-.19-.33-.02-.5.14-.66.15-.15.32-.38.49-.57.16-.19.22-.33.33-.55.11-.22.05-.41-.03-.57-.08-.16-.74-1.78-1.01-2.44-.27-.64-.54-.55-.74-.56h-.63c-.22 0-.57.08-.87.41-.3.33-1.14 1.11-1.14 2.71s1.17 3.14 1.33 3.36c.16.22 2.3 3.51 5.57 4.92.78.34 1.39.54 1.87.69.79.25 1.51.21 2.08.13.63-.09 1.9-.78 2.17-1.54.27-.76.27-1.41.19-1.54-.08-.13-.3-.21-.63-.37Z"/></svg>';
}
function kategori_icon(string $k): string {
    $k = mb_strtolower($k);
    if (strpos($k, 'phone') !== false || strpos($k, 'hp') !== false) { return 'cat-smartphone'; }
    if (strpos($k, 'laptop') !== false) { return 'cat-laptop'; }
    if (strpos($k, 'watch') !== false) { return 'cat-smartwatch'; }
    if (strpos($k, 'tv') !== false || strpos($k, 'monitor') !== false) { return 'cat-monitor'; }
    if (strpos($k, 'komputer') !== false || strpos($k, 'pc') !== false) { return 'cat-pc'; }
    if (strpos($k, 'aksesoris') !== false) { return 'cat-aksesoris'; }
    if (strpos($k, 'gaming') !== false) { return 'cat-gaming'; }
    return 'cat-lain';
}

/** Kartu produk untuk grid katalog. */
function product_card(array $p, string $root = '../'): string {
    global $APP;
    $img = produk_img($p['gambar'] ?? '', $root, 'thumb');
    $stok = (int)$p['stok']; $harga = (int)$p['harga']; $coret = (int)($p['harga_coret'] ?? 0);
    $disk = ($coret > $harga && $harga > 0) ? (int)round(($coret - $harga) / $coret * 100) : 0;
    $url = 'product.php?id=' . (int)$p['id'];
    ob_start(); ?>
    <article class="pcard<?= $stok < 1 ? ' is-out' : '' ?>">
      <a class="pimg" href="<?= $url ?>" tabindex="-1" aria-hidden="true">
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="noimg"><?= icon('package', 40) ?></span><?php endif; ?>
        <?php if ($disk): ?><span class="tag tag-sale">-<?= $disk ?>%</span><?php endif; ?>
        <?php if ($stok < 1): ?><span class="tag tag-out">Stok habis</span><?php elseif ($stok <= (int)$APP['stok_rendah']): ?><span class="tag tag-low">Sisa <?= $stok ?></span><?php endif; ?>
      </a>
      <div class="pbody">
        <?php if (!empty($p['kategori'])): ?><a class="pcat" href="index.php?kategori=<?= urlencode($p['kategori']) ?>#produk"><?= e($p['kategori']) ?></a><?php endif; ?>
        <h3 class="pname"><a href="<?= $url ?>"><?= e($p['nama']) ?></a></h3>
        <div class="pprice"><strong><?= rupiah($harga) ?></strong><?php if ($disk): ?><s><?= rupiah($coret) ?></s><?php endif; ?></div>
        <form method="post" action="cart.php" class="padd" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tambah">
          <input type="hidden" name="produk_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="<?= e(back_url()) ?>">
          <button class="btn btn-primary btn-block btn-sm" <?= $stok < 1 ? 'disabled' : '' ?>><?= $stok < 1 ? 'Stok habis' : icon('cart', 16) . ' Tambah' ?></button>
        </form>
      </div>
    </article>
    <?php return ob_get_clean();
}

/** Navigasi halaman. $query = parameter GET yang dipertahankan (tanpa "page"). */
function paginate_links(int $page, int $pages, array $query = []): string {
    if ($pages <= 1) { return ''; }
    $mk = function (int $p) use ($query) {
        $q = $query;
        if ($p > 1) { $q['page'] = $p; } else { unset($q['page']); }
        return '?' . http_build_query($q);
    };
    $h = '<nav class="pager" aria-label="Halaman">';
    if ($page > 1) { $h .= '<a href="' . e($mk($page - 1)) . '" rel="prev" aria-label="Sebelumnya">&lsaquo;</a>'; }
    $set = [];
    foreach ([1, $page - 1, $page, $page + 1, $pages] as $x) { if ($x >= 1 && $x <= $pages) { $set[$x] = $x; } }
    ksort($set);
    $prev = 0;
    foreach ($set as $x) {
        if ($x - $prev > 1) { $h .= '<span class="gap">…</span>'; }
        $h .= $x === $page ? '<span class="cur" aria-current="page">' . $x . '</span>' : '<a href="' . e($mk($x)) . '">' . $x . '</a>';
        $prev = $x;
    }
    if ($page < $pages) { $h .= '<a href="' . e($mk($page + 1)) . '" rel="next" aria-label="Berikutnya">&rsaquo;</a>'; }
    return $h . '</nav>';
}

function render_flash(): string {
    $out = '';
    foreach (flash_pull() as $f) {
        $type = in_array($f[0], ['success', 'error', 'info'], true) ? $f[0] : 'info';
        $out .= '<div class="toast t-' . $type . '" role="status"><span>' . e($f[1]) . '</span>';
        if (!empty($f[2]['href'])) { $out .= ' <a href="' . e($f[2]['href']) . '">' . e($f[2]['label'] ?? 'Lihat') . '</a>'; }
        $out .= '<button type="button" class="toast-x" aria-label="Tutup">' . icon('x', 16) . '</button></div>';
    }
    return $out;
}
