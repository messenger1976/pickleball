<?php
include('config.php');
include_once(__DIR__ . '/../includes/chat_helper.php');
include_once(__DIR__ . '/../admin/payment_config.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

$threadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$threads = pb_chat_threads_for_business($conn, $adminBusinessId, true);
$thread = $threadId > 0 ? pb_chat_thread_fetch($conn, $threadId) : null;
if ($thread && (int)$thread['business_id'] !== (int)$adminBusinessId) {
    $thread = null;
}

if (isset($_POST['btnSend']) && $thread) {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    pb_chat_post($conn, (int)$thread['thread_id'], 'partner', $uid, isset($_POST['message']) ? $_POST['message'] : '');
    header('Location: chat_inbox.php?business_id=' . $adminBusinessId . '&thread_id=' . (int)$thread['thread_id']);
    exit;
}

if (isset($_POST['btnModerate']) && $thread) {
    $status = isset($_POST['thread_status']) ? $_POST['thread_status'] : 'open';
    pb_chat_set_status($conn, (int)$thread['thread_id'], $status);
    header('Location: chat_inbox.php?business_id=' . $adminBusinessId . '&thread_id=' . (int)$thread['thread_id']);
    exit;
}

$messages = $thread ? pb_chat_messages($conn, (int)$thread['thread_id']) : array();
$titleheader = 'Chat Inbox';
include('header.php');
include('navigation.php');
?>
<style>
.chat-admin { display:grid; grid-template-columns:280px 1fr; gap:16px; }
.chat-admin-list a { display:block; padding:10px; border-bottom:1px solid #e2e8f0; text-decoration:none; color:#0f172a; }
.chat-admin-list a.is-active { background:#ecfdf5; }
.chat-bubble { margin:0 0 10px; padding:10px 12px; border-radius:12px; max-width:80%; background:#f1f5f9; }
.chat-bubble.partner { background:#d8f3dc; margin-left:auto; }
.chat-status { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; background:#e2e8f0; }
.chat-status.flagged { background:#fee2e2; color:#991b1b; }
.chat-status.closed { background:#f1f5f9; color:#64748b; }
.chat-status.open { background:#dcfce7; color:#166534; }
@media(max-width:800px){ .chat-admin{grid-template-columns:1fr;} }
</style>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal"><h1>Player Chat</h1><p>Reply to players and moderate conversations.</p></div>
    <?php echo fh_admin_business_selector($conn, 'chat_inbox.php'); ?>
    <div class="chat-admin w3-card w3-padding" style="margin-top:16px;">
        <div class="chat-admin-list">
            <?php if (empty($threads)): ?>
                <p>No conversations yet.</p>
            <?php else: foreach ($threads as $t): ?>
                <a class="<?php echo (int)$t['thread_id']===$threadId?'is-active':''; ?>" href="chat_inbox.php?business_id=<?php echo (int)$adminBusinessId; ?>&thread_id=<?php echo (int)$t['thread_id']; ?>">
                    <strong><?php echo htmlspecialchars(trim($t['customers_firstname'].' '.$t['customers_lastname']) ?: 'Player'); ?></strong>
                    <span class="chat-status <?php echo htmlspecialchars($t['thread_status']); ?>"><?php echo htmlspecialchars($t['thread_status']); ?></span><br>
                    <small><?php echo htmlspecialchars(mb_strimwidth((string)$t['last_message'], 0, 60, '…')); ?></small>
                </a>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <?php if (!$thread): ?>
                <p>Select a conversation.</p>
            <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;">
                    <span class="chat-status <?php echo htmlspecialchars($thread['thread_status']); ?>"><?php echo htmlspecialchars($thread['thread_status']); ?></span>
                    <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <input type="hidden" name="btnModerate" value="1">
                        <button class="w3-button w3-small w3-green" name="thread_status" value="open" type="submit">Reopen</button>
                        <button class="w3-button w3-small w3-light-grey" name="thread_status" value="closed" type="submit">Close</button>
                        <button class="w3-button w3-small w3-red" name="thread_status" value="flagged" type="submit">Flag</button>
                    </form>
                </div>
                <div style="max-height:50vh;overflow:auto;margin-bottom:12px;" id="adminChatLog">
                    <?php foreach ($messages as $m): ?>
                    <div class="chat-bubble <?php echo $m['sender_role']==='partner'?'partner':''; ?>">
                        <?php echo nl2br(htmlspecialchars($m['message_body'])); ?>
                        <div style="font-size:11px;color:#64748b;"><?php echo htmlspecialchars($m['created_at']); ?> · <?php echo htmlspecialchars($m['sender_role']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($thread['thread_status'] !== 'closed'): ?>
                <form method="post" style="display:flex;gap:8px;">
                    <input class="w3-input w3-border" name="message" required placeholder="Reply…" style="margin:0;">
                    <button class="w3-button w3-green" name="btnSend" value="1">Send</button>
                </form>
                <?php else: ?>
                <p style="color:#64748b;">This thread is closed. Reopen to reply.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include('footer.php'); ?>
