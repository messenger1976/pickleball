<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/membership_helper.php');
include_once(__DIR__ . '/includes/payment_helper.php');
include_once(__DIR__ . '/includes/business_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
    header('Location: login.php?redirect=' . urlencode('subscribe.php?' . http_build_query($_GET)));
    exit;
}

$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$plan = pb_plan_fetch($conn, $planId);
$error = '';

if (!$plan || (int)$plan['plan_status'] !== 1) {
    $error = 'Membership plan not found.';
}

if ($error === '' && isset($_POST['btnJoin'])) {
    $sub = pb_membership_subscribe($conn, $planId, (int)$_SESSION['customers_id']);
    if (!empty($sub['ok'])) {
        $checkout = pb_paymongo_create_checkout($conn, array(
            'membership_id' => $sub['membership_id'],
            'customers_id' => (int)$_SESSION['customers_id'],
            'business_id' => $sub['business_id'],
            'amount' => $sub['amount'],
            'description' => 'Membership: ' . $sub['plan_name'],
            'success_url' => pb_app_base_url() . '/my_memberships.php?paid=1',
            'cancel_url' => pb_app_base_url() . '/my_memberships.php?paid=0',
        ));
        if (!empty($checkout['ok'])) {
            // For demo/online: activate after payment; pending until paid
            header('Location: ' . $checkout['checkout_url']);
            exit;
        }
        $error = isset($checkout['msg']) ? $checkout['msg'] : 'Payment start failed.';
    } else {
        $error = $sub['msg'];
    }
}

$pagetitle = 'Join Membership — Pickleball Portal';
include('header.php');
?>
<div style="max-width:640px;margin:calc(56px + 40px) auto 80px;padding:0 20px;">
    <h1>Join membership</h1>
    <?php if ($error !== '' && !$plan): ?>
        <div class="w3-panel w3-pale-red"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($plan): ?>
        <?php if ($error !== ''): ?><div class="w3-panel w3-pale-red"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="w3-card w3-padding" style="border-radius:12px;">
            <h2><?php echo htmlspecialchars($plan['plan_name']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars((string)$plan['plan_description'])); ?></p>
            <p><strong>₱<?php echo number_format((float)$plan['price'], 2); ?></strong> / <?php echo htmlspecialchars($plan['billing_period']); ?></p>
            <?php if ((float)$plan['discount_percent'] > 0): ?>
            <p><?php echo rtrim(rtrim(number_format((float)$plan['discount_percent'], 2), '0'), '.'); ?>% off court bookings at <?php echo htmlspecialchars($plan['business_name']); ?>.</p>
            <?php endif; ?>
            <form method="post">
                <button class="w3-button w3-green w3-large" type="submit" name="btnJoin" value="1">Pay &amp; join</button>
                <a class="w3-button w3-light-grey" href="<?php echo htmlspecialchars(fh_store_link((int)$plan['business_id'])); ?>">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php include('footer.php'); ?>
