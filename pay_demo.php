<?php
/**
 * Demo checkout when PayMongo keys are not configured.
 */
include('admin/db_config.php');
include_once(__DIR__ . '/includes/payment_helper.php');

$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$payment = pb_payment_fetch($conn, $paymentId);
if (!$payment) {
    header('Location: home.php');
    exit;
}

if (isset($_POST['btnPay'])) {
    pb_payment_mark_paid($conn, $paymentId, '{"demo":true}');
    $platformSubId = isset($payment['platform_subscription_id']) ? (int)$payment['platform_subscription_id'] : 0;
    if ($platformSubId > 0) {
        session_start();
        $bizId = (int)$payment['business_id'];
        if (!empty($_SESSION['user_id'])) {
            header('Location: admin/subscription.php?paid=1&business_id=' . $bizId);
        } else {
            header('Location: partner_activate.php?paid=1');
        }
        exit;
    }
    if ((int)$payment['booking_id'] > 0) {
        header('Location: my_bookings.php?paid=1&created=' . (int)$payment['booking_id']);
    } else {
        header('Location: my_memberships.php?paid=1');
    }
    exit;
}

$pagetitle = 'Demo Payment — Pickleball Portal';
include('header.php');

$platformSubId = isset($payment['platform_subscription_id']) ? (int)$payment['platform_subscription_id'] : 0;
$cancelHref = $platformSubId > 0
    ? ('admin/subscription.php?business_id=' . (int)$payment['business_id'])
    : 'my_bookings.php';
?>
<div style="max-width:520px;margin:calc(56px + 48px) auto 80px;padding:0 20px;">
    <div class="w3-card w3-padding" style="border-radius:14px;">
        <h1>Demo payment</h1>
        <p>PayMongo keys are not configured, so this local demo checkout simulates a successful online payment.</p>
        <p><strong>Amount:</strong> ₱<?php echo number_format((float)$payment['amount'], 2); ?></p>
        <?php if ($platformSubId > 0): ?>
        <p style="color:#065f46;font-size:13px;">This payment is for a venue platform subscription.</p>
        <?php endif; ?>
        <p style="color:#64748b;font-size:13px;">Add keys in <code>admin/payment_config.php</code> or env vars to use real PayMongo Checkout.</p>
        <form method="post">
            <button type="submit" name="btnPay" value="1" class="w3-button w3-green w3-large">Simulate successful payment</button>
            <a class="w3-button w3-light-grey" href="<?php echo htmlspecialchars($cancelHref); ?>">Cancel</a>
        </form>
    </div>
</div>
<?php include('footer.php'); ?>
