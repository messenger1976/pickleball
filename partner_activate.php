<?php
include('admin/config.php');
include_once(__DIR__ . '/includes/portal_subscription_helper.php');
include_once(__DIR__ . '/includes/payment_helper.php');
include_once(__DIR__ . '/includes/email_helper.php');

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$paidFlag = isset($_GET['paid']) ? (int)$_GET['paid'] : null;
$mode = 'error';
$message = 'This activation link is invalid or has expired.';
$trialDays = 7;
$loginUrl = 'admin/login.php';
$subscriptionUrl = 'admin/subscription.php';

$sub = $token !== '' ? fh_portal_sub_fetch_by_token($conn, $token) : null;
// After payment, token may already be cleared — fall back by looking up recent sub via payment return only
if (!$sub && $token !== '') {
    // Token cleared after pay; try fetch by token already null — use paid flow message only
}

if ($paidFlag === 1) {
    $mode = 'paid';
    $message = 'Payment received. Your venue subscription is now active. You can sign in to the admin panel.';
    if ($sub) {
        $fresh = fh_portal_sub_fetch($conn, (int)$sub['subscription_id']);
        if ($fresh && $fresh['status'] === 'pending_payment') {
            $mode = 'pending_pay';
            $message = 'Payment is still processing. If you already paid, wait a moment then sign in.';
        }
    } else {
        // Token cleared after successful apply_payment — treat as success
        $mode = 'paid';
    }
} elseif ($paidFlag === 0) {
    $mode = 'error';
    $message = 'Payment was cancelled. Use your activation email link again when you are ready to pay.';
} elseif ($sub) {
    $expired = !empty($sub['token_expires_at']) && strtotime($sub['token_expires_at']) < time();
    if ($expired && in_array($sub['status'], array('pending_activation', 'pending_payment'), true)) {
        $mode = 'error';
        $message = 'This activation link has expired. Please contact support or sign up again.';
    } elseif ($sub['status'] === 'trialing' || $sub['status'] === 'active') {
        $mode = 'already';
        $message = 'Your account is already activated. You can sign in to the admin panel.';
    } elseif ($sub['status'] === 'pending_activation' || $sub['status'] === 'pending_payment') {
        $intent = $sub['signup_intent'];
        if ($intent === 'trial') {
            $result = fh_portal_sub_activate_trial($conn, (int)$sub['subscription_id']);
            if ($result['ok']) {
                $mode = 'trial';
                $trialDays = isset($result['trial_days']) ? (int)$result['trial_days'] : (int)fh_portal_sub_get_settings($conn)['trial_days'];
                $message = 'Your free trial is active.';
            } else {
                $mode = 'error';
                $message = $result['msg'];
            }
        } else {
            $cycle = (!empty($sub['billing_cycle']) && $sub['billing_cycle'] === 'yearly') ? 'yearly' : 'monthly';
            $months = max(1, (int)$sub['months_purchased']);
            if ((int)$sub['months_purchased'] <= 0) {
                $months = $cycle === 'yearly' ? 12 : 1;
            }
            $prep = fh_portal_sub_prepare_payment($conn, (int)$sub['subscription_id'], $cycle, $months);
            if (!$prep['ok']) {
                $mode = 'error';
                $message = $prep['msg'];
            } else {
                // Keep activation token until payment completes
                $conn->query(
                    "UPDATE business_subscriptions SET
                        activation_token='" . $conn->real_escape_string($token) . "',
                        token_expires_at='" . $conn->real_escape_string($sub['token_expires_at']) . "'
                     WHERE subscription_id=" . (int)$sub['subscription_id']
                );
                $amounts = $prep['amounts'];
                $bizId = (int)$sub['business_id'];
                $successUrl = pb_app_base_url() . '/partner_activate.php?paid=1&token=' . urlencode($token);
                $cancelUrl = pb_app_base_url() . '/partner_activate.php?paid=0&token=' . urlencode($token);
                $checkout = pb_paymongo_create_checkout($conn, array(
                    'amount' => $amounts['total'],
                    'description' => 'Pickleball Portal venue subscription',
                    'business_id' => $bizId,
                    'platform_subscription_id' => (int)$sub['subscription_id'],
                    'credentials' => 'platform',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ));
                if (!empty($checkout['ok']) && !empty($checkout['checkout_url'])) {
                    header('Location: ' . $checkout['checkout_url']);
                    exit;
                }
                $mode = 'error';
                $message = isset($checkout['msg']) ? $checkout['msg'] : 'Unable to start payment checkout.';
            }
        }
    }
}

$pagetitle = 'Activate Partner Account — Pickleball Portal';
include('header.php');
?>
<style>
.activate-page {
    margin-top: 56px;
    min-height: calc(100vh - 56px);
    padding: 48px 20px 80px;
    background: linear-gradient(180deg, #f0fdf4 0%, #fff 40%);
}
.activate-card {
    max-width: 520px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 36px 28px;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
    text-align: center;
}
.activate-card h1 {
    margin: 0 0 12px;
    font-family: var(--pb-font-display, Georgia, serif);
    color: #0b2f24;
    font-size: 1.6rem;
    text-transform: uppercase;
}
.activate-card p {
    color: #475569;
    line-height: 1.55;
    margin: 0 0 12px;
}
.activate-card .trial-badge {
    display: inline-block;
    margin: 8px 0 18px;
    padding: 8px 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 999px;
    color: #065f46;
    font-weight: 800;
    font-size: 14px;
}
.activate-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-top: 22px;
}
.activate-actions a {
    display: inline-block;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 800;
    text-decoration: none;
}
.activate-actions .primary {
    background: #f4c430;
    color: #0b2f24;
}
.activate-actions .secondary {
    background: #0b2f24;
    color: #fff;
}
</style>

<div class="activate-page">
    <div class="activate-card">
        <?php if ($mode === 'trial'): ?>
            <h1>Welcome aboard</h1>
            <div class="trial-badge">You have <?php echo (int)$trialDays; ?> days free trial use</div>
            <p><?php echo htmlspecialchars($message); ?></p>
            <p>You can upgrade to a paid plan anytime from Business Account → Subscription.</p>
            <div class="activate-actions">
                <a class="primary" href="<?php echo htmlspecialchars($loginUrl); ?>">Sign in to Admin</a>
                <a class="secondary" href="<?php echo htmlspecialchars($subscriptionUrl); ?>">View Subscription</a>
            </div>
        <?php elseif ($mode === 'paid' || $mode === 'already'): ?>
            <h1>Account ready</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="activate-actions">
                <a class="primary" href="<?php echo htmlspecialchars($loginUrl); ?>">Sign in to Admin</a>
            </div>
        <?php elseif ($mode === 'pending_pay'): ?>
            <h1>Payment processing</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="activate-actions">
                <a class="primary" href="<?php echo htmlspecialchars($loginUrl); ?>">Sign in</a>
            </div>
        <?php else: ?>
            <h1>Activation</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="activate-actions">
                <a class="secondary" href="partner_signup.php">Partner signup</a>
                <a class="primary" href="home.php">Home</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include('footer.php'); ?>
