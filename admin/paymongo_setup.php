<?php
/**
 * Per-business PayMongo setup.
 *
 * Lets a venue (business admin) or the platform (super admin) connect a
 * PayMongo account for a single business. Payments for that venue's court
 * bookings and memberships are then collected into the venue's own PayMongo
 * account. Platform subscription fees are unaffected.
 */
include('config.php');
include_once(__DIR__ . '/../includes/payment_helper.php');
include_once(__DIR__ . '/../includes/business_helper.php');

fh_auth_require_login();

$isSuper = fh_auth_is_super_admin();
$isBizAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === FH_ROLE_BUSINESS_ADMIN;
if (!$isSuper && !$isBizAdmin) {
    header('Location: index.php');
    exit;
}

$adminBusinessId = fh_admin_business_id($conn);
if ($isBizAdmin && !$isSuper) {
    $adminBusinessId = fh_auth_user_business_id();
}
if ($adminBusinessId <= 0) {
    header('Location: index.php');
    exit;
}
if (!$isSuper && !fh_auth_can_access_business($adminBusinessId)) {
    header('Location: index.php');
    exit;
}

$tableReady = pb_paymongo_business_columns_ready($conn);
$settings = pb_business_paymongo_settings($conn, $adminBusinessId);
$bizName = fh_admin_business_name($conn, $adminBusinessId);
if ($bizName === '') {
    $bizName = 'Business #' . $adminBusinessId;
}

$saved = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSavePaymongo'])) {
    $result = pb_business_paymongo_save($conn, $adminBusinessId, array(
        'enabled' => isset($_POST['paymongo_enabled']) ? 1 : 0,
        'public_key' => isset($_POST['paymongo_public_key']) ? $_POST['paymongo_public_key'] : '',
        'secret_key' => isset($_POST['paymongo_secret_key']) ? $_POST['paymongo_secret_key'] : '',
        'webhook_secret' => isset($_POST['paymongo_webhook_secret']) ? $_POST['paymongo_webhook_secret'] : '',
        'clear_keys' => isset($_POST['paymongo_clear']) ? 1 : 0,
    ));
    if (!empty($result['ok'])) {
        $saved = true;
        $settings = pb_business_paymongo_settings($conn, $adminBusinessId);
    } else {
        $error = isset($result['msg']) ? $result['msg'] : 'Could not save settings.';
    }
}

$platformReady = pb_paymongo_configured();
$webhookUrl = pb_paymongo_webhook_url();

if (!empty($settings['configured'])) {
    $modeKey = 'business';
    $modeLabel = 'Venue PayMongo account';
} elseif ($platformReady) {
    $modeKey = 'platform';
    $modeLabel = 'Platform account (fallback)';
} else {
    $modeKey = 'demo';
    $modeLabel = 'Demo mode (no keys configured)';
}

$keyModeLabel = 'Unknown';
if ($settings['secret_key'] !== '') {
    $keyModeLabel = (strpos($settings['secret_key'], 'sk_live_') === 0) ? 'Live' : 'Test';
}
$maskedSecret = $settings['secret_key'] !== '' ? ('••••••••' . substr($settings['secret_key'], -4)) : '';

$titleheader = 'PayMongo Setup';
include('header.php');
include('navigation.php');
?>
<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
<style>
.admin-pm-wrap { max-width: 760px; }
.admin-pm-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
    padding: 22px 24px 28px;
    margin: 16px 0 20px;
}
.admin-pm-card h2 { margin: 0 0 8px; font-size: 1.15rem; color: #0f172a; }
.admin-pm-status {
    display: inline-block;
    margin: 8px 0 4px;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
}
.admin-pm-status.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.admin-pm-status.warn { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.admin-pm-status.bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.admin-pm-card label {
    display: block;
    margin: 14px 0 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-pm-card input[type="text"],
.admin-pm-card input[type="password"] {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    background: #fff;
}
.admin-pm-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
    font-weight: 600;
    color: #0f172a;
    text-transform: none;
    letter-spacing: 0;
    font-size: 14px;
}
.admin-pm-hint { margin: 6px 0 0; font-size: 12px; color: #94a3b8; }
.admin-pm-msg {
    padding: 12px 14px;
    border-radius: 10px;
    margin: 0 0 16px;
    font-size: 14px;
}
.admin-pm-msg.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.admin-pm-msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.admin-pm-webhook {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 6px;
}
.admin-pm-webhook input {
    flex: 1 1 auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
    background: #f8fafc;
}
.admin-pm-copy {
    padding: 10px 16px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}
.admin-pm-copy:hover { background: #f1f5f9; }
.admin-pm-actions { margin-top: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.admin-pm-save {
    padding: 11px 22px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.admin-pm-save:hover { background: #1b4332; }
.admin-pm-clear {
    margin-top: 16px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-size: 13px;
}
.admin-pm-clear label { margin: 0; display: flex; gap: 8px; align-items: flex-start; text-transform: none; letter-spacing: 0; font-size: 13px; color: #9a3412; }
.admin-pm-readonly-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    color: #334155;
}
.admin-pm-readonly-row:last-child { border-bottom: none; }
.admin-pm-readonly-row strong { color: #0f172a; }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>PayMongo Setup</h1>
        <p>Connect a PayMongo account so this venue collects its own court booking &amp; membership payments.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'paymongo_setup.php'); ?>

    <div class="admin-pm-wrap">
        <?php if (!$tableReady): ?>
            <div class="admin-pm-msg err">
                PayMongo columns are missing from the database. Run <code>sql/migrate_business_paymongo.sql</code>, then reload this page.
            </div>
        <?php endif; ?>

        <?php if ($saved): ?>
            <div class="admin-pm-msg ok">PayMongo settings saved.</div>
        <?php elseif ($error !== ''): ?>
            <div class="admin-pm-msg err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="admin-pm-card">
            <h2><?php echo htmlspecialchars($bizName); ?></h2>
            <?php
            $statusClass = $modeKey === 'business' ? 'ok' : ($modeKey === 'platform' ? 'warn' : 'bad');
            ?>
            <div class="admin-pm-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($modeLabel); ?></div>
            <div class="admin-pm-readonly-row">
                <span>Status</span>
                <strong><?php echo $settings['enabled'] === 1 ? 'Enabled' : 'Not enabled'; ?></strong>
            </div>
            <div class="admin-pm-readonly-row">
                <span>Current secret key</span>
                <strong><?php echo $maskedSecret !== '' ? htmlspecialchars($maskedSecret) . ' (' . htmlspecialchars($keyModeLabel) . ')' : '—'; ?></strong>
            </div>
            <div class="admin-pm-readonly-row">
                <span>Last updated</span>
                <strong><?php echo !empty($settings['updated_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($settings['updated_at']))) : '—'; ?></strong>
            </div>
            <?php if ($modeKey === 'platform'): ?>
                <p class="admin-pm-hint">This venue has no PayMongo account connected, so payments currently use the platform account. Fill in the keys below to collect payments directly.</p>
            <?php elseif ($modeKey === 'demo'): ?>
                <p class="admin-pm-hint">No PayMongo keys are configured anywhere, so checkout runs in demo mode. Add this venue's keys below (or set the platform keys) to accept real payments.</p>
            <?php endif; ?>
        </div>

        <form class="admin-pm-card" method="post" action="paymongo_setup.php?business_id=<?php echo (int)$adminBusinessId; ?>" autocomplete="off">
            <h2>PayMongo keys</h2>
            <p class="admin-pm-hint" style="margin-bottom:4px;">
                Find these in your PayMongo dashboard under <em>Developers &rarr; API Keys</em>.
            </p>

            <label class="admin-pm-check">
                <input type="checkbox" name="paymongo_enabled" value="1"<?php echo $settings['enabled'] === 1 ? ' checked' : ''; ?>>
                Use this venue's PayMongo account for bookings and memberships
            </label>

            <label for="paymongo_public_key">Public key</label>
            <input type="text" id="paymongo_public_key" name="paymongo_public_key" value="<?php echo htmlspecialchars($settings['public_key']); ?>" placeholder="pk_test_... or pk_live_...">

            <label for="paymongo_secret_key">Secret key</label>
            <input type="password" id="paymongo_secret_key" name="paymongo_secret_key" value="" placeholder="<?php echo $settings['secret_key'] !== '' ? 'Leave blank to keep the current key' : 'sk_test_... or sk_live_...'; ?>" autocomplete="new-password">
            <p class="admin-pm-hint">Stored securely server-side and never displayed again. Leave blank to keep the current key.</p>

            <label for="paymongo_webhook_secret">Webhook signing secret</label>
            <input type="password" id="paymongo_webhook_secret" name="paymongo_webhook_secret" value="" placeholder="<?php echo $settings['webhook_secret'] !== '' ? 'Leave blank to keep the current secret' : 'whsec_...'; ?>" autocomplete="new-password">
            <p class="admin-pm-hint">Used to verify incoming webhook events. Leave blank if you have not created a webhook yet.</p>

            <label>Webhook URL</label>
            <div class="admin-pm-webhook">
                <input type="text" id="paymongoWebhookUrl" value="<?php echo htmlspecialchars($webhookUrl); ?>" readonly onclick="this.select();">
                <button type="button" class="admin-pm-copy" id="btnCopyWebhook">Copy</button>
            </div>
            <p class="admin-pm-hint">Register this URL in PayMongo (Developers &rarr; Webhooks) and subscribe to <code>checkout_session.payment.paid</code>.</p>

            <?php if ($settings['secret_key'] !== '' || $settings['public_key'] !== '' || $settings['webhook_secret'] !== ''): ?>
            <div class="admin-pm-clear">
                <label>
                    <input type="checkbox" name="paymongo_clear" value="1">
                    Remove this venue's stored PayMongo keys and fall back to the platform account.
                </label>
            </div>
            <?php endif; ?>

            <div class="admin-pm-actions">
                <button type="submit" name="btnSavePaymongo" value="1" class="admin-pm-save"<?php echo $tableReady ? '' : ' disabled'; ?>>Save PayMongo setup</button>
                <span class="admin-pm-hint">Changes apply to new payments immediately.</span>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('btnCopyWebhook');
    var input = document.getElementById('paymongoWebhookUrl');
    if (btn && input) {
        btn.addEventListener('click', function () {
            input.select();
            var done = false;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value);
                done = true;
            }
            if (!done) {
                try { document.execCommand('copy'); } catch (e) {}
            }
            var old = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = old; }, 1500);
        });
    }
})();
</script>
<?php include('footer.php'); ?>
