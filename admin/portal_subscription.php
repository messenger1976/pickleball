<?php
include('config.php');
fh_auth_require_super_admin();
include_once(__DIR__ . '/../includes/portal_subscription_helper.php');

$titleheader = 'Portal Subscription';
$settings = fh_portal_sub_get_settings($conn);
$preview = fh_portal_sub_pricing_preview($conn);
$tableOk = fh_portal_sub_settings_table_exists($conn);

$saved = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSaveSubscription'])) {
    $result = fh_portal_sub_save_settings($conn, array(
        'trial_enabled' => isset($_POST['trial_enabled']) ? 1 : 0,
        'trial_days' => isset($_POST['trial_days']) ? $_POST['trial_days'] : 7,
        'monthly_fee' => isset($_POST['monthly_fee']) ? $_POST['monthly_fee'] : 1500,
        'yearly_discount_pct' => isset($_POST['yearly_discount_pct']) ? $_POST['yearly_discount_pct'] : 10,
        'paymongo_fee_percent' => isset($_POST['paymongo_fee_percent']) ? $_POST['paymongo_fee_percent'] : 2.9,
        'paymongo_fee_fixed' => isset($_POST['paymongo_fee_fixed']) ? $_POST['paymongo_fee_fixed'] : 15,
        'signup_blurb' => isset($_POST['signup_blurb']) ? $_POST['signup_blurb'] : '',
    ));
    if ($result['ok']) {
        $saved = true;
        $settings = fh_portal_sub_get_settings($conn);
        $preview = fh_portal_sub_pricing_preview($conn);
    } else {
        $error = $result['msg'];
    }
}

include('header.php');
include('navigation.php');
?>
<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
<style>
.admin-sub-wrap { max-width: 720px; margin: 0 auto; }
.admin-sub-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
    padding: 22px 24px 28px;
    margin-bottom: 20px;
}
.admin-sub-card h2 {
    margin: 0 0 6px;
    font-size: 1.15rem;
    color: #0f172a;
}
.admin-sub-card .hint {
    margin: 0 0 18px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.45;
}
.admin-sub-card label {
    display: block;
    margin: 14px 0 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-sub-card input[type="text"],
.admin-sub-card input[type="number"],
.admin-sub-card textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}
.admin-sub-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.admin-sub-check {
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
.admin-sub-preview {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    padding: 14px 16px;
    margin-top: 18px;
    font-size: 13px;
    color: #065f46;
    line-height: 1.55;
}
.admin-sub-preview strong { font-weight: 800; }
.admin-sub-msg {
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 14px;
}
.admin-sub-msg.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.admin-sub-msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.admin-sub-save {
    margin-top: 20px;
    padding: 11px 22px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.admin-sub-save:hover { background: #1b4332; }
@media (max-width: 600px) {
    .admin-sub-row { grid-template-columns: 1fr; }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal"><h1>Partner Subscription Settings</h1></div>

    <div class="admin-sub-wrap" style="margin-top:20px;">
        <?php if (!$tableOk): ?>
            <div class="admin-sub-msg err">Run <code>sql/migrate_platform_subscription.sql</code> before using this page.</div>
        <?php else: ?>
            <?php if ($saved): ?>
                <div class="admin-sub-msg ok">Settings saved. Partner signup will use the updated pricing.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="admin-sub-msg err"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" class="admin-sub-card">
                <h2>Free Trial</h2>
                <p class="hint">Partners who choose Free Trial activate by email and get full venue access for the trial period.</p>

                <label class="admin-sub-check">
                    <input type="checkbox" name="trial_enabled" value="1" <?php echo !empty($settings['trial_enabled']) ? 'checked' : ''; ?> />
                    Enable Free Trial on partner signup
                </label>

                <label for="trial_days">Trial length (days)</label>
                <input type="number" id="trial_days" name="trial_days" min="1" max="365" value="<?php echo (int)$settings['trial_days']; ?>" required />

                <h2 style="margin-top:28px;">Subscription pricing</h2>
                <p class="hint">Partners pay subscription fee + PayMongo processing fee at checkout.</p>

                <div class="admin-sub-row">
                    <div>
                        <label for="monthly_fee">Monthly fee (₱)</label>
                        <input type="number" id="monthly_fee" name="monthly_fee" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$settings['monthly_fee']); ?>" required />
                    </div>
                    <div>
                        <label for="yearly_discount_pct">Yearly discount (%)</label>
                        <input type="number" id="yearly_discount_pct" name="yearly_discount_pct" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)$settings['yearly_discount_pct']); ?>" required />
                    </div>
                </div>

                <div class="admin-sub-row">
                    <div>
                        <label for="paymongo_fee_percent">PayMongo fee (%)</label>
                        <input type="number" id="paymongo_fee_percent" name="paymongo_fee_percent" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$settings['paymongo_fee_percent']); ?>" required />
                    </div>
                    <div>
                        <label for="paymongo_fee_fixed">PayMongo fixed fee (₱)</label>
                        <input type="number" id="paymongo_fee_fixed" name="paymongo_fee_fixed" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$settings['paymongo_fee_fixed']); ?>" required />
                    </div>
                </div>

                <label for="signup_blurb">Signup page blurb</label>
                <textarea id="signup_blurb" name="signup_blurb" rows="3"><?php echo htmlspecialchars($settings['signup_blurb']); ?></textarea>

                <div class="admin-sub-preview">
                    <div><strong>Monthly checkout:</strong>
                        <?php echo fh_portal_sub_format_money($preview['monthly']['subtotal']); ?>
                        + fee <?php echo fh_portal_sub_format_money($preview['monthly']['fee']); ?>
                        = <strong><?php echo fh_portal_sub_format_money($preview['monthly']['total']); ?></strong>
                    </div>
                    <div style="margin-top:6px;"><strong>Yearly checkout:</strong>
                        <?php echo fh_portal_sub_format_money($preview['yearly']['subtotal']); ?>
                        (<?php echo htmlspecialchars((string)$settings['yearly_discount_pct']); ?>% off)
                        + fee <?php echo fh_portal_sub_format_money($preview['yearly']['fee']); ?>
                        = <strong><?php echo fh_portal_sub_format_money($preview['yearly']['total']); ?></strong>
                    </div>
                </div>

                <button type="submit" class="admin-sub-save" name="btnSaveSubscription" value="1">Save settings</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include('footer.php'); ?>
