<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/booking_helper.php');
include_once(__DIR__ . '/includes/business_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
    header('Location: login.php?redirect=' . urlencode('my_bookings.php'));
    exit;
}

$cid = (int)$_SESSION['customers_id'];
$message = '';

if (isset($_POST['btnCancel'])) {
    $bookingId = (int)$_POST['booking_id'];
    $result = pb_booking_cancel($conn, $bookingId, $cid, isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : 'Cancelled by player');
    $message = $result['msg'];
}

$bookings = pb_bookings_for_customer($conn, $cid);
$createdId = isset($_GET['created']) ? (int)$_GET['created'] : 0;

$pagetitle = 'My Bookings — Pickleball Portal';
$extraStylesheets = array('css/portal-order.css?v=20260803');
include('header.php');
?>

<div class="portal-orders" style="max-width:960px;margin:calc(56px + 40px) auto 80px;padding:0 20px;">
    <h1>My Bookings</h1>

    <?php if ($createdId > 0): ?>
    <div class="w3-panel w3-pale-green w3-border">Your booking was submitted successfully. Receipt details are below.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
    <div class="w3-panel w3-pale-yellow w3-border"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <p style="font-size:13px;color:#64748b;">
        <button type="button" class="w3-button w3-small w3-light-grey" id="btnEnableReminders">Enable booking reminders</button>
        (browser notifications ~2 hours before play)
    </p>

    <?php if (empty($bookings)): ?>
        <p>You have no bookings yet. <a href="home.php#venues">Find a venue</a> to reserve a court.</p>
    <?php else: ?>
    <div style="display:grid;gap:16px;">
        <?php foreach ($bookings as $b):
            $canCancel = pb_booking_is_active($b['booking_status']) && (strtotime($b['start_datetime']) - time() >= 24 * 3600);
        ?>
        <article class="w3-card w3-padding" style="border-radius:12px;" id="booking-<?php echo (int)$b['booking_id']; ?>">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;">
                <div>
                    <strong><?php echo htmlspecialchars($b['booking_receipt_no'] ?: ('#' . $b['booking_id'])); ?></strong>
                    <span style="margin-left:8px;padding:3px 8px;border-radius:999px;background:#e2e8f0;font-size:12px;font-weight:700;"><?php echo htmlspecialchars(pb_booking_status_label($b['booking_status'])); ?></span>
                    <h3 style="margin:8px 0 4px;"><?php echo htmlspecialchars($b['business_name']); ?></h3>
                    <p style="margin:0;color:#475569;"><?php echo htmlspecialchars($b['court_name']); ?></p>
                    <p style="margin:6px 0 0;">
                        <?php echo htmlspecialchars(date('D, M j, Y', strtotime($b['start_datetime']))); ?>
                        · <?php echo htmlspecialchars(date('g:i A', strtotime($b['start_datetime'])) . ' – ' . date('g:i A', strtotime($b['end_datetime']))); ?>
                    </p>
                    <p style="margin:6px 0 0;">₱<?php echo number_format((float)$b['total_price'], 2); ?>
                        · <?php echo htmlspecialchars(str_replace('_', ' ', $b['payment_type'])); ?>
                        <?php if (!empty($b['payment_status'])): ?>
                        · <?php echo htmlspecialchars($b['payment_status']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                    <a href="<?php echo htmlspecialchars(fh_store_link($b['business_id'], $b['business_subdomain'])); ?>">View venue</a>
                    <a href="chat.php?business_id=<?php echo (int)$b['business_id']; ?>&booking_id=<?php echo (int)$b['booking_id']; ?>">Chat venue</a>
                    <?php if ($canCancel): ?>
                    <form method="post" onsubmit="return confirm('Cancel this booking?');">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                        <input type="hidden" name="cancel_reason" value="Cancelled by player">
                        <button type="submit" name="btnCancel" value="1" class="w3-button w3-red">Cancel booking</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
window.pbUpcomingBookings = <?php
$upcoming = array();
foreach ($bookings as $b) {
    if (!pb_booking_is_active($b['booking_status'])) {
        continue;
    }
    $upcoming[] = array(
        'id' => (int)$b['booking_id'],
        'title' => $b['business_name'] . ' — ' . $b['court_name'],
        'start' => $b['start_datetime'],
        'receipt' => $b['booking_receipt_no'],
    );
}
echo json_encode($upcoming);
?>;
(function () {
  var btn = document.getElementById('btnEnableReminders');
  if (!btn) return;
  btn.addEventListener('click', function () {
    if (!('Notification' in window)) {
      alert('Notifications are not supported in this browser.');
      return;
    }
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') {
        alert('Reminder permission was not granted.');
        return;
      }
      try {
        localStorage.setItem('pb_reminders', JSON.stringify(window.pbUpcomingBookings || []));
        localStorage.setItem('pb_reminders_enabled', '1');
      } catch (e) {}
      alert('Reminders enabled for your upcoming bookings on this device.');
      scheduleLocalReminders();
    });
  });

  function scheduleLocalReminders() {
    if (localStorage.getItem('pb_reminders_enabled') !== '1') return;
    if (Notification.permission !== 'granted') return;
    var list = [];
    try { list = JSON.parse(localStorage.getItem('pb_reminders') || '[]'); } catch (e) { list = []; }
    list.forEach(function (b) {
      var start = new Date(String(b.start).replace(' ', 'T')).getTime();
      var when = start - (2 * 60 * 60 * 1000);
      var delay = when - Date.now();
      if (delay <= 0 || delay > 7 * 24 * 60 * 60 * 1000) return;
      setTimeout(function () {
        new Notification('Pickleball reminder', {
          body: (b.title || 'Your booking') + ' starts in about 2 hours. Receipt: ' + (b.receipt || b.id),
          tag: 'booking-' + b.id
        });
      }, delay);
    });
  }
  scheduleLocalReminders();
})();
</script>
<?php include('footer.php'); ?>
