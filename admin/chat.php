<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../'; $A = '';
require_admin();

$id = (int)qs('id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $pid = (int)post('pelanggan_id'); $pesan = mb_substr(post('pesan'), 0, 1000);
    if ($pid > 0 && $pesan !== '' && scalar($pdo, 'SELECT id FROM pelanggan WHERE id = ?', [$pid])) {
        $pdo->prepare("INSERT INTO chat_messages (pelanggan_id, pengirim, pesan) VALUES (?, 'admin', ?)")->execute([$pid, $pesan]);
    }
    redirect('chat.php?id=' . $pid);
}

// Daftar percakapan: pesan terakhir per pelanggan; "perlu dibalas" bila pesan terakhir dari pelanggan.
$daftar = $pdo->query("SELECT pl.id, pl.nama, m.pesan, m.pengirim, m.created_at
    FROM pelanggan pl
    JOIN chat_messages m ON m.id = (SELECT MAX(id) FROM chat_messages WHERE pelanggan_id = pl.id)
    ORDER BY m.id DESC LIMIT 100")->fetchAll();
$aktif = null; $pesanList = [];
if ($id > 0) {
    $st = $pdo->prepare('SELECT id, nama, email, no_hp FROM pelanggan WHERE id = ?');
    $st->execute([$id]); $aktif = $st->fetch();
    if ($aktif) {
        $st = $pdo->prepare('SELECT * FROM chat_messages WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC LIMIT 300');
        $st->execute([$id]); $pesanList = $st->fetchAll();
    }
}
$perluBalas = 0; foreach ($daftar as $d) { if ($d['pengirim'] === 'pelanggan') { $perluBalas++; } }

$adm = ['title' => 'Chat pelanggan', 'nav' => 'chat'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="cgrid">
  <section class="card" style="padding:12px 16px">
    <div class="head" style="margin:6px 0 4px"><h2>Percakapan</h2><?php if ($perluBalas): ?><span class="badge b-bad"><?= $perluBalas ?> perlu dibalas</span><?php endif; ?></div>
    <?php if ($daftar): foreach ($daftar as $d): $on = $aktif && (int)$aktif['id'] === (int)$d['id']; $tunggu = $d['pengirim'] === 'pelanggan'; ?>
      <a href="chat.php?id=<?= (int)$d['id'] ?>" class="rowi" style="text-decoration:none;color:inherit;<?= $on ? 'background:var(--goldsoft);margin:0 -16px;padding:10px 16px;border-radius:10px' : '' ?>">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($d['nama'], 0, 1))) ?></span>
        <div class="grow"><b><?= e($d['nama']) ?></b><small><?= $tunggu ? '' : 'Kamu: ' ?><?= e(mb_strimwidth($d['pesan'], 0, 46, '…')) ?></small></div>
        <?php if ($tunggu): ?><span class="badge b-bad">Baru</span><?php endif; ?>
      </a>
    <?php endforeach; else: ?><p class="muted" style="margin:14px 0">Belum ada pesan dari pelanggan.</p><?php endif; ?>
  </section>

  <section class="card">
    <?php if ($aktif): ?>
      <div class="head"><h2><?= e($aktif['nama']) ?></h2><a href="pelanggan/edit.php?id=<?= (int)$aktif['id'] ?>" style="font-size:13px">Lihat profil</a></div>
      <div class="chatbox" id="chatbox" style="height:380px">
        <?php if (!$pesanList): ?><p class="muted center" style="margin:auto">Belum ada pesan. Mulai percakapan di bawah.</p><?php endif; ?>
        <?php foreach ($pesanList as $m): $admin = $m['pengirim'] === 'admin'; ?>
          <div class="msg <?= $admin ? 'me' : 'them' ?>"><?= nl2br(e($m['pesan'])) ?><small><?= $admin ? 'Admin' : e($aktif['nama']) ?> · <?= tgl($m['created_at'], true) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form method="post" style="display:flex;gap:10px;margin-top:12px" data-once>
        <?= csrf_field() ?><input type="hidden" name="pelanggan_id" value="<?= (int)$aktif['id'] ?>">
        <input class="input" name="pesan" placeholder="Tulis balasan…" maxlength="1000" autocomplete="off" required style="flex:1">
        <button class="btn btn-primary">Kirim</button>
      </form>
    <?php else: ?>
      <div class="empty" style="border:0"><span class="ic"><?= icon('chat', 30) ?></span><h3>Pilih percakapan</h3><p>Pilih pelanggan di sebelah kiri untuk membaca dan membalas pesan.</p></div>
    <?php endif; ?>
  </section>
</div>
<?php if ($aktif): ?><script>var c=document.getElementById('chatbox');if(c)c.scrollTop=c.scrollHeight;</script><?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
