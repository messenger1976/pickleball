<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/chat_helper.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/admin/payment_config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
    header('Location: login.php?redirect=' . urlencode('chat.php?' . http_build_query($_GET)));
    exit;
}

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$biz = fh_resolve_business($conn, $businessId);
if (!$biz) {
    header('Location: home.php');
    exit;
}

$cid = (int)$_SESSION['customers_id'];
$thread = pb_chat_get_or_create_thread($conn, $businessId, $cid, $bookingId);
$error = '';

if (isset($_POST['btnSend']) && $thread) {
    $result = pb_chat_post($conn, (int)$thread['thread_id'], 'customer', $cid, isset($_POST['message']) ? $_POST['message'] : '');
    if (empty($result['ok'])) {
        $error = $result['msg'];
    } else {
        header('Location: chat.php?business_id=' . $businessId . ($bookingId ? '&booking_id=' . $bookingId : ''));
        exit;
    }
}

$messages = $thread ? pb_chat_messages($conn, (int)$thread['thread_id']) : array();
$pagetitle = 'Chat — ' . $biz['business_name'];
include('header.php');
?>
<style>
.chat-box { max-width:720px; margin:calc(56px + 40px) auto 80px; padding:0 20px; }
.chat-log { background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:16px; min-height:320px; max-height:55vh; overflow:auto; }
.chat-bubble { margin:0 0 10px; padding:10px 12px; border-radius:12px; max-width:80%; }
.chat-bubble.me { background:#d8f3dc; margin-left:auto; }
.chat-bubble.them { background:#f1f5f9; }
.chat-meta { font-size:11px; color:#64748b; margin-top:4px; }
</style>
<div class="chat-box">
    <h1>Chat with <?php echo htmlspecialchars($biz['business_name']); ?></h1>
    <?php if ($error): ?><div class="w3-panel w3-pale-red"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="chat-log" id="chatLog">
        <?php if (empty($messages)): ?>
            <p style="color:#64748b;">Say hello — ask about courts, lessons, or your booking.</p>
        <?php else: foreach ($messages as $m):
            $mine = $m['sender_role'] === 'customer';
        ?>
        <div class="chat-bubble <?php echo $mine ? 'me' : 'them'; ?>">
            <?php echo nl2br(htmlspecialchars($m['message_body'])); ?>
            <div class="chat-meta"><?php echo htmlspecialchars($m['created_at']); ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <form method="post" style="margin-top:12px;display:flex;gap:8px;">
        <input class="w3-input w3-border" type="text" name="message" placeholder="Type a message…" required style="margin:0;">
        <button class="w3-button w3-green" type="submit" name="btnSend" value="1">Send</button>
    </form>
</div>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script>
(function(){
  var threadId = <?php echo (int)($thread ? $thread['thread_id'] : 0); ?>;
  var bizId = <?php echo (int)$businessId; ?>;
  var url = <?php echo json_encode(rtrim(PB_REALTIME_URL, '/')); ?>;
  if (!window.io || !threadId) return;
  try {
    var s = io(url, { transports: ['websocket', 'polling'] });
    s.on('connect', function(){ s.emit('join', ['thread:'+threadId, 'biz:'+bizId]); });
    s.on('chat_message', function(p){
      if (!p || Number(p.thread_id) !== threadId) return;
      if (p.sender_role === 'customer') return; // own messages already rendered on post
      var log = document.getElementById('chatLog');
      var div = document.createElement('div');
      div.className = 'chat-bubble them';
      div.innerHTML = (p.body || '').replace(/</g,'&lt;').replace(/\n/g,'<br>') +
        '<div class="chat-meta">'+(p.created_at||'')+'</div>';
      log.appendChild(div);
      log.scrollTop = log.scrollHeight;
    });
  } catch(e) {}
})();
</script>
<?php include('footer.php'); ?>
