<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $pesan = post('pesan');
    if ($pesan !== '') {
        $pdo->prepare("INSERT INTO chat_messages (pelanggan_id, pengirim, pesan) VALUES (?, 'pelanggan', ?)")->execute([pelanggan_id(), mb_substr($pesan, 0, 1000)]);
    }
    redirect('pesan.php');
}
$st = $pdo->prepare('SELECT * FROM chat_messages WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC LIMIT 200');
$st->execute([pelanggan_id()]);
$pesanList = $st->fetchAll();

$page = ['title' => 'Chat admin', 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>
<div class="wrap" style="max-width:760px">
  <div class="page-h"><h1>Chat admin</h1><p>Tanyakan stok, produk, atau pesananmu. Untuk respons lebih cepat, gunakan WhatsApp.</p></div>
  <div class="stack" style="padding:16px 0 40px">
    <div class="chatbox" id="chatbox">
      <?php if (!$pesanList): ?><p class="muted center" style="margin:auto">Belum ada pesan. Tulis pesan pertamamu di bawah.</p><?php endif; ?>
      <?php foreach ($pesanList as $m): $me = $m['pengirim'] === 'pelanggan'; ?>
        <div class="msg <?= $me ? 'me' : 'them' ?>"><?= nl2br(e($m['pesan'])) ?><small><?= $me ? 'Kamu' : 'Admin' ?> · <?= tgl($m['created_at'], true) ?></small></div>
      <?php endforeach; ?>
    </div>
    <form method="post" style="display:flex;gap:10px" data-once>
      <?= csrf_field() ?>
      <input class="input" name="pesan" placeholder="Tulis pesan…" maxlength="1000" autocomplete="off" required style="flex:1">
      <button class="btn btn-primary">Kirim</button>
    </form>
    <a class="btn btn-wa" style="align-self:flex-start" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener"><?= icon_wa(18) ?> Lebih cepat via WhatsApp</a>
  </div>
</div>
<script>var c=document.getElementById('chatbox');if(c)c.scrollTop=c.scrollHeight;</script>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
