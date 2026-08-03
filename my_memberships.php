<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/membership_helper.php');
include_once(__DIR__ . '/includes/payment_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
    header('Location: login.php?redirect=my_memberships.php');
    exit;
}

$cid = (int)$_SESSION['customers_id'];
pb_membership_expire_stale($conn);
$message = '';

if (isset($_POST['btnRenew'])) {
    $mid = (int)$_POST['membership_id'];
    $renew = pb_membership_renew($conn, $mid);
    if (!empty($renew['ok']) && (int)$renew['customers_id'] === $cid) {
        $checkout = pb_paymongo_create_checkout($conn, array(
            'membership_id' => $renew['membership_id'],
            'customers_id' => $cid,
            'business_id' => $renew['business_id'],
            'amount' => $renew['amount'],
            'description' => 'Renew: ' . $renew['plan_name'],
            'success_url' => pb_app_base_url() . '/my_memberships.php?paid=1',
            'cancel_url' => pb_app_base_url() . '/my_memberships.php?paid=0',
        ));
        if (!empty($checkout['ok'])) {
            header('Location: ' . $checkout['checkout_url']);
            exit;
        }
        $message = isset($checkout['msg']) ? $checkout['msg'] : 'Could not start renewal payment.';
    } else {
        $message = isset($renew['msg']) ? $renew['msg'] : 'Renewal failed.';
    }
}

$list = pb_memberships_for_customer($conn, $cid);
$pagetitle = 'My Memberships — Pickleball Portal';
include('header.php');
?>
<div style="max-width:860px;margin:calc(56px + 40px) auto 80px;padding:0 20px;">
    <h1>My Memberships</h1>
    <?php if (isset($_GET['paid']) && $_GET['paid'] === '1'): ?>
    <div class="w3-panel w3-pale-green">Payment received. Your membership is active.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
    <div class="w3-panel w3-pale-yellow"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (empty($list)): ?>
        <p>No memberships yet. Browse a venue and join a plan.</p>
    <?php else: foreach ($list as $m):
        $canRenew = in_array($m['membership_status'], array('active', 'expired', 'pending'), true);
    ?>
        <div class="w3-card w3-padding" style="border-radius:12px;margin-bottom:12px;">
            <strong><?php echo htmlspecialchars($m['plan_name']); ?></strong> at <?php echo htmlspecialchars($m['business_name']); ?><br>
            Status: <?php echo htmlspecialchars($m['membership_status']); ?>
            · <?php echo htmlspecialchars($m['current_period_start'] . ' → ' . $m['current_period_end']); ?>
            · ₱<?php echo number_format((float)$m['price'], 2); ?> / <?php echo htmlspecialchars($m['billing_period']); ?>
            <?php if ($canRenew): ?>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="membership_id" value="<?php echo (int)$m['membership_id']; ?>">
                <button type="submit" name="btnRenew" value="1" class="w3-button w3-green w3-small">Renew / extend period</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php include('footer.php'); ?>
