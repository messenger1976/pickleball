<?php
include('config.php');
include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
include_once(__DIR__ . '/../includes/payment_helper.php');

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

$access = fh_portal_sub_access_state($conn, $adminBusinessId);
$preview = fh_portal_sub_pricing_preview($conn);
$settings = $preview['settings'];
$paidMsg = '';
if (isset($_GET['paid'])) {
    $paidMsg = ((int)$_GET['paid'] === 1)
        ? 'Payment successful. Your subscription has been updated.'
        : 'Payment was cancelled. You can try again when ready.';
}

$titleheader = 'Subscription';
include('header.php');
include('navigation.php');
?>
<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
<style>
.admin-sub-wrap { max-width: 720px; }
.admin-sub-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
    padding: 22px 24px 28px;
    margin: 16px 0 20px;
}
.admin-sub-card h2 { margin: 0 0 8px; font-size: 1.15rem; color: #0f172a; }
.admin-sub-status {
    display: inline-block;
    margin: 8px 0 12px;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
}
.admin-sub-status.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.admin-sub-status.warn { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.admin-sub-status.bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.admin-sub-card label {
    display: block;
    margin: 14px 0 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-sub-card select {
    width: 100%;
    max-width: 280px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
}
.admin-sub-total {
    margin-top: 14px;
    padding: 12px 14px;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 14px;
    color: #334155;
    line-height: 1.5;
}
.admin-sub-btn {
    margin-top: 16px;
    padding: 11px 22px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.admin-sub-btn:hover { background: #1b4332; }
.admin-sub-btn:disabled { opacity: 0.6; cursor: wait; }
.admin-sub-msg {
    padding: 12px 14px;
    border-radius: 10px;
    margin: 16px 0 0;
    font-size: 14px;
}
.admin-sub-msg.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.admin-sub-msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.admin-sub-msg.info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal"><h1>Venue Subscription</h1></div>
    <?php echo fh_admin_business_selector($conn, 'subscription.php'); ?>

    <div class="admin-sub-wrap">
        <?php if ($paidMsg !== ''): ?>
            <div class="admin-sub-msg <?php echo ((int)$_GET['paid'] === 1) ? 'ok' : 'info'; ?>"><?php echo htmlspecialchars($paidMsg); ?></div>
        <?php endif; ?>

        <div class="admin-sub-card">
            <h2>Current status</h2>
            <?php
            $stateClass = 'ok';
            if ($access['state'] === 'expired' || $access['state'] === 'pending') {
                $stateClass = $access['is_expired'] ? 'bad' : 'warn';
            } elseif ($access['state'] === 'trialing') {
                $stateClass = 'warn';
            }
            ?>
            <div class="admin-sub-status <?php echo $stateClass; ?>"><?php echo htmlspecialchars($access['label']); ?></div>
            <?php if ($access['state'] === 'trialing'): ?>
                <p style="color:#64748b;font-size:13px;margin:0;">Subscribe below to keep access after your free trial ends.</p>
            <?php elseif ($access['is_expired']): ?>
                <p style="color:#64748b;font-size:13px;margin:0;">Renew your subscription to unlock court management and bookings.</p>
            <?php endif; ?>
        </div>

        <div class="admin-sub-card">
            <h2>Pay / renew subscription</h2>
            <p style="color:#64748b;font-size:13px;margin:0 0 8px;">
                Monthly base <?php echo fh_portal_sub_format_money($settings['monthly_fee']); ?>.
                Yearly includes <?php echo htmlspecialchars((string)$settings['yearly_discount_pct']); ?>% discount.
                Checkout total includes PayMongo fee.
            </p>

            <label for="billing_cycle">Plan</label>
            <select id="billing_cycle">
                <option value="monthly">Monthly (choose months)</option>
                <option value="yearly">Yearly (12 months, <?php echo htmlspecialchars((string)$settings['yearly_discount_pct']); ?>% off)</option>
            </select>

            <div id="monthsWrap">
                <label for="months">Number of months</label>
                <select id="months">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m === 1 ? 'selected' : ''; ?>><?php echo $m; ?> month<?php echo $m === 1 ? '' : 's'; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="admin-sub-total" id="totalPreview">Calculating…</div>
            <button type="button" class="admin-sub-btn" id="btnCheckout">Pay with PayMongo</button>
            <div id="checkoutMsg"></div>
        </div>
    </div>
</div>

<script>
(function() {
    var pricing = {
        monthly_fee: <?php echo json_encode((float)$settings['monthly_fee']); ?>,
        yearly_discount_pct: <?php echo json_encode((float)$settings['yearly_discount_pct']); ?>,
        paymongo_fee_percent: <?php echo json_encode((float)$settings['paymongo_fee_percent']); ?>,
        paymongo_fee_fixed: <?php echo json_encode((float)$settings['paymongo_fee_fixed']); ?>
    };

    function money(n) {
        return '₱' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function compute() {
        var cycle = $('#billing_cycle').val();
        var months = parseInt($('#months').val(), 10) || 1;
        var subtotal = 0;
        if (cycle === 'yearly') {
            months = 12;
            subtotal = pricing.monthly_fee * 12 * (1 - (pricing.yearly_discount_pct / 100));
        } else {
            subtotal = pricing.monthly_fee * months;
        }
        subtotal = Math.round(subtotal * 100) / 100;
        var fee = Math.round(((subtotal * (pricing.paymongo_fee_percent / 100)) + pricing.paymongo_fee_fixed) * 100) / 100;
        var total = Math.round((subtotal + fee) * 100) / 100;
        $('#totalPreview').html(
            '<strong>Subscription:</strong> ' + money(subtotal) +
            '<br><strong>PayMongo fee:</strong> ' + money(fee) +
            '<br><strong>Total due:</strong> ' + money(total)
        );
        return { cycle: cycle, months: months, total: total };
    }

    function syncMonths() {
        if ($('#billing_cycle').val() === 'yearly') {
            $('#monthsWrap').hide();
        } else {
            $('#monthsWrap').show();
        }
        compute();
    }

    $('#billing_cycle, #months').on('change', syncMonths);
    syncMonths();

    $('#btnCheckout').on('click', function() {
        var btn = $(this);
        var calc = compute();
        btn.prop('disabled', true).text('Starting checkout…');
        $('#checkoutMsg').removeClass('admin-sub-msg ok err').empty();
        $.ajax({
            type: 'POST',
            url: 'process.php',
            dataType: 'json',
            data: {
                function: 'StartVenueSubscriptionCheckout',
                billing_cycle: calc.cycle,
                months: calc.months,
                business_id: <?php echo (int)$adminBusinessId; ?>
            },
            success: function(data) {
                if (data.validation_status === 'T' && data.checkout_url) {
                    window.location.href = data.checkout_url;
                    return;
                }
                btn.prop('disabled', false).text('Pay with PayMongo');
                $('#checkoutMsg').attr('class', 'admin-sub-msg err').text(data.msg || 'Unable to start checkout.');
            },
            error: function() {
                btn.prop('disabled', false).text('Pay with PayMongo');
                $('#checkoutMsg').attr('class', 'admin-sub-msg err').text('Network error. Please try again.');
            }
        });
    });
})();
</script>
<?php include('footer.php'); ?>
