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
    if ((int)$payment['booking_id'] > 0) {
        header('Location: my_bookings.php?paid=1&created=' . (int)$payment['booking_id']);
    } else {
        header('Location: my_memberships.php?paid=1');
    }
    exit;
}

$pagetitle = 'Demo Payment — Pickleball Portal';
include('header.php');
?>
<div style="max-width:520px;margin:calc(56px + 48px) auto 80px;padding:0 20px;">
    <div class="w3-card w3-padding" style="border-radius:14px;">
        <h1>Demo payment</h1>
        <p>PayMongo keys are not configured, so this local demo checkout simulates a successful online payment.</p>
        <p><strong>Amount:</strong> ₱<?php echo number_format((float)$payment['amount'], 2); ?></p>
        <p style="color:#64748b;font-size:13px;">Add keys in <code>admin/payment_config.php</code> or env vars to use real PayMongo Checkout.</p>
        <form method="post">
            <button type="submit" name="btnPay" value="1" class="w3-button w3-green w3-large">Simulate successful payment</button>
            <a class="w3-button w3-light-grey" href="my_bookings.php">Cancel</a>
        </form>
    </div>
</div>
<?php include('footer.php'); ?>
