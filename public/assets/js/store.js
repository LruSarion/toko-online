/* Tokkoku Laku — interaksi ringan (tanpa library) */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var rupiah = function (n) { return 'Rp' + Math.round(n).toLocaleString('id-ID'); };

  // Toast: tombol tutup + hapus otomatis
  $$('.toast').forEach(function (t) {
    var x = $('.toast-x', t);
    if (x) x.addEventListener('click', function () { t.remove(); });
    setTimeout(function () { t.remove(); }, 6600);
  });

  // Gambar produk yang gagal dimuat (file hilang) diganti ikon netral, bukan ikon "gambar rusak"
  var ICON_BOX = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/></svg>';
  var patahkan = function (img) {
    if (!img || img.getAttribute('data-fb') || !/uploads\/product\//.test(img.getAttribute('src') || '')) return;
    img.setAttribute('data-fb', '1');
    var p = img.parentNode;
    if (p && window.getComputedStyle(p).position === 'static') p.style.position = 'relative';
    var s = document.createElement('span');
    s.className = 'noimg'; s.innerHTML = ICON_BOX;
    img.style.display = 'none';
    if (p) p.insertBefore(s, img);
  };
  document.addEventListener('error', function (e) { if (e.target && e.target.tagName === 'IMG') patahkan(e.target); }, true);
  $$('img').forEach(function (i) { if (i.complete && i.naturalWidth === 0) patahkan(i); });

  // Menu akun: tutup saat klik di luar
  document.addEventListener('click', function (e) {
    $$('details.acct[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // Cegah klik ganda pada form
  $$('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = $('button[type=submit], button:not([type])', f);
      if (b) setTimeout(function () { b.classList.add('is-off'); }, 0);
    });
  });

  // Konfirmasi sebelum aksi berbahaya
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // Auto-submit filter / urutan
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form) el.form.submit(); });
  });

  // Panel filter terbuka di desktop
  var fb = $('.fbox');
  if (fb && window.matchMedia('(min-width: 901px)').matches) fb.open = true;

  // Stepper jumlah
  $$('.qty').forEach(function (q) {
    var inp = $('input', q), max = parseInt(inp.getAttribute('max') || '999', 10);
    $$('[data-step]', q).forEach(function (b) {
      b.addEventListener('click', function () {
        var v = (parseInt(inp.value, 10) || 1) + parseInt(b.getAttribute('data-step'), 10);
        inp.value = Math.max(1, Math.min(max, v));
        inp.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    inp.addEventListener('change', function () {
      var v = parseInt(inp.value, 10) || 1;
      inp.value = Math.max(1, Math.min(max, v));
    });
  });
  // Ubah jumlah di keranjang → kirim form otomatis
  $$('form.cart-qty .qty input').forEach(function (inp) {
    inp.addEventListener('change', function () { inp.form.submit(); });
  });

  // Checkout: hitung ulang ongkir & total saat opsi kirim berubah
  var ship = $$('input[name=kurir]');
  var sumEl = $('#sum');
  if (ship.length && sumEl) {
    var sub = parseInt(sumEl.getAttribute('data-sub') || '0', 10);
    var free = parseInt(sumEl.getAttribute('data-free') || '0', 10);
    var upd = function () {
      var r = ship.filter(function (i) { return i.checked; })[0];
      if (!r) return;
      var biaya = parseInt(r.getAttribute('data-biaya') || '0', 10);
      if (free > 0 && sub >= free) biaya = 0;
      $('#sum-ongkir').textContent = biaya === 0 ? 'Gratis' : rupiah(biaya);
      $('#sum-total').textContent = rupiah(sub + biaya);
    };
    ship.forEach(function (r) { r.addEventListener('change', upd); });
    upd();
  }
})();
