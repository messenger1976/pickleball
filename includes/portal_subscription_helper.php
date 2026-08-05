<?php
/**
 * Platform partner Free Trial + Subscription helpers.
 */

function fh_portal_sub_settings_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $check = $conn->query("SHOW TABLES LIKE 'portal_subscription_settings'");
    $exists = ($check && $check->num_rows > 0);
    return $exists;
}

function fh_portal_sub_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $check = $conn->query("SHOW TABLES LIKE 'business_subscriptions'");
    $exists = ($check && $check->num_rows > 0);
    return $exists;
}

function fh_portal_sub_defaults() {
    return array(
        'settings_id' => 0,
        'trial_enabled' => 1,
        'trial_days' => 7,
        'monthly_fee' => 1500.00,
        'yearly_discount_pct' => 10.00,
        'paymongo_fee_percent' => 2.90,
        'paymongo_fee_fixed' => 15.00,
        'signup_blurb' => 'Choose a free trial or subscribe to list your courts on Pickleball Portal.',
    );
}

function fh_portal_sub_get_settings($conn) {
    $defaults = fh_portal_sub_defaults();
    if (!$conn || !fh_portal_sub_settings_table_exists($conn)) {
        return $defaults;
    }
    $sql = $conn->query('SELECT * FROM portal_subscription_settings ORDER BY settings_id ASC LIMIT 1');
    if ($sql && ($row = $sql->fetch_assoc())) {
        return array_merge($defaults, $row);
    }
    return $defaults;
}

function fh_portal_sub_save_settings($conn, $data) {
    if (!fh_portal_sub_settings_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'Subscription settings table is not installed. Run sql/migrate_platform_subscription.sql.');
    }

    $trialEnabled = !empty($data['trial_enabled']) ? 1 : 0;
    $trialDays = max(1, (int)(isset($data['trial_days']) ? $data['trial_days'] : 7));
    $monthlyFee = max(0, round((float)(isset($data['monthly_fee']) ? $data['monthly_fee'] : 1500), 2));
    $yearlyDiscount = max(0, min(100, round((float)(isset($data['yearly_discount_pct']) ? $data['yearly_discount_pct'] : 10), 2)));
    $feePercent = max(0, round((float)(isset($data['paymongo_fee_percent']) ? $data['paymongo_fee_percent'] : 2.9), 2));
    $feeFixed = max(0, round((float)(isset($data['paymongo_fee_fixed']) ? $data['paymongo_fee_fixed'] : 15), 2));
    $blurb = trim(isset($data['signup_blurb']) ? $data['signup_blurb'] : '');
    if ($blurb === '') {
        $blurb = fh_portal_sub_defaults()['signup_blurb'];
    }
    $safeBlurb = $conn->real_escape_string(substr($blurb, 0, 500));

    $existing = fh_portal_sub_get_settings($conn);
    $settingsId = (int)$existing['settings_id'];

    if ($settingsId > 0) {
        $ok = $conn->query(
            "UPDATE portal_subscription_settings SET
                trial_enabled=$trialEnabled,
                trial_days=$trialDays,
                monthly_fee=$monthlyFee,
                yearly_discount_pct=$yearlyDiscount,
                paymongo_fee_percent=$feePercent,
                paymongo_fee_fixed=$feeFixed,
                signup_blurb='$safeBlurb'
             WHERE settings_id=$settingsId"
        );
    } else {
        $ok = $conn->query(
            "INSERT INTO portal_subscription_settings
             (trial_enabled, trial_days, monthly_fee, yearly_discount_pct, paymongo_fee_percent, paymongo_fee_fixed, signup_blurb)
             VALUES ($trialEnabled, $trialDays, $monthlyFee, $yearlyDiscount, $feePercent, $feeFixed, '$safeBlurb')"
        );
        $settingsId = (int)$conn->insert_id;
    }

    if (!$ok) {
        return array('ok' => false, 'msg' => 'Unable to save subscription settings.');
    }
    return array('ok' => true, 'msg' => 'Subscription settings saved.', 'settings_id' => $settingsId);
}

/**
 * Compute subscription subtotal, PayMongo fee, and total.
 * $billingCycle: 'monthly' | 'yearly'
 * $months: 1-12 when monthly; ignored for yearly (always 12 with discount).
 */
function fh_portal_sub_compute_amount($settings, $billingCycle, $months = 1) {
    $monthly = (float)$settings['monthly_fee'];
    $discountPct = (float)$settings['yearly_discount_pct'];
    $feePct = (float)$settings['paymongo_fee_percent'];
    $feeFixed = (float)$settings['paymongo_fee_fixed'];

    $billingCycle = $billingCycle === 'yearly' ? 'yearly' : 'monthly';
    $months = max(1, min(12, (int)$months));

    if ($billingCycle === 'yearly') {
        $months = 12;
        $subtotal = round($monthly * 12 * (1 - ($discountPct / 100)), 2);
    } else {
        $subtotal = round($monthly * $months, 2);
    }

    $fee = round(($subtotal * ($feePct / 100)) + $feeFixed, 2);
    $total = round($subtotal + $fee, 2);

    return array(
        'billing_cycle' => $billingCycle,
        'months' => $months,
        'subtotal' => $subtotal,
        'fee' => $fee,
        'total' => $total,
        'monthly_fee' => $monthly,
        'yearly_discount_pct' => $discountPct,
        'paymongo_fee_percent' => $feePct,
        'paymongo_fee_fixed' => $feeFixed,
    );
}

function fh_portal_sub_pricing_preview($conn) {
    $settings = fh_portal_sub_get_settings($conn);
    $monthly = fh_portal_sub_compute_amount($settings, 'monthly', 1);
    $yearly = fh_portal_sub_compute_amount($settings, 'yearly', 12);
    return array(
        'settings' => $settings,
        'monthly' => $monthly,
        'yearly' => $yearly,
    );
}

function fh_portal_sub_make_token() {
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return md5(uniqid((string)mt_rand(), true) . microtime(true));
    }
}

function fh_portal_sub_fetch($conn, $subscriptionId) {
    $subscriptionId = (int)$subscriptionId;
    if ($subscriptionId <= 0 || !fh_portal_sub_table_exists($conn)) {
        return null;
    }
    $sql = $conn->query("SELECT * FROM business_subscriptions WHERE subscription_id=$subscriptionId LIMIT 1");
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function fh_portal_sub_fetch_by_business($conn, $businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0 || !fh_portal_sub_table_exists($conn)) {
        return null;
    }
    $sql = $conn->query(
        "SELECT * FROM business_subscriptions
         WHERE business_id=$businessId
         ORDER BY subscription_id DESC
         LIMIT 1"
    );
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function fh_portal_sub_fetch_by_token($conn, $token) {
    $token = trim((string)$token);
    if ($token === '' || !fh_portal_sub_table_exists($conn)) {
        return null;
    }
    $safe = $conn->real_escape_string($token);
    $sql = $conn->query(
        "SELECT * FROM business_subscriptions
         WHERE activation_token='$safe'
         LIMIT 1"
    );
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function fh_portal_sub_create_pending($conn, $payload) {
    if (!fh_portal_sub_table_exists($conn)) {
        return array('ok' => false, 'msg' => 'Subscriptions table is not installed.', 'subscription_id' => 0);
    }

    $businessId = (int)$payload['business_id'];
    $ownerUserId = (int)(isset($payload['owner_user_id']) ? $payload['owner_user_id'] : 0);
    $intent = (isset($payload['signup_intent']) && $payload['signup_intent'] === 'subscribe') ? 'subscribe' : 'trial';
    $billingCycle = null;
    $months = 0;
    $amounts = array('subtotal' => 0, 'fee' => 0, 'total' => 0);

    if ($intent === 'subscribe') {
        $settings = fh_portal_sub_get_settings($conn);
        $cycle = (isset($payload['billing_cycle']) && $payload['billing_cycle'] === 'yearly') ? 'yearly' : 'monthly';
        $monthsReq = isset($payload['months']) ? (int)$payload['months'] : 1;
        $amounts = fh_portal_sub_compute_amount($settings, $cycle, $monthsReq);
        $billingCycle = $amounts['billing_cycle'];
        $months = (int)$amounts['months'];
    }

    $token = fh_portal_sub_make_token();
    $safeToken = $conn->real_escape_string($token);
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
    $cycleSql = $billingCycle !== null ? ("'" . $conn->real_escape_string($billingCycle) . "'") : 'NULL';
    $subtotal = (float)$amounts['subtotal'];
    $fee = (float)$amounts['fee'];
    $total = (float)$amounts['total'];

    $ok = $conn->query(
        "INSERT INTO business_subscriptions
         (business_id, owner_user_id, signup_intent, status, billing_cycle, months_purchased,
          activation_token, token_expires_at, amount_subtotal, amount_fee, amount_total, created_at)
         VALUES
         ($businessId, $ownerUserId, '$intent', 'pending_activation', $cycleSql, $months,
          '$safeToken', '$expires', $subtotal, $fee, $total, NOW())"
    );
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Unable to create subscription record.', 'subscription_id' => 0);
    }
    return array(
        'ok' => true,
        'subscription_id' => (int)$conn->insert_id,
        'token' => $token,
        'token_expires_at' => $expires,
    );
}

function fh_portal_sub_activate_trial($conn, $subscriptionId) {
    $sub = fh_portal_sub_fetch($conn, $subscriptionId);
    if (!$sub) {
        return array('ok' => false, 'msg' => 'Subscription not found.');
    }
    if ($sub['status'] !== 'pending_activation') {
        return array('ok' => false, 'msg' => 'This activation link has already been used.', 'subscription' => $sub);
    }

    $settings = fh_portal_sub_get_settings($conn);
    $days = max(1, (int)$settings['trial_days']);
    $trialEnds = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    $sid = (int)$sub['subscription_id'];
    $bizId = (int)$sub['business_id'];
    $userId = (int)$sub['owner_user_id'];

    $conn->query(
        "UPDATE business_subscriptions SET
            status='trialing',
            trial_ends_at='$trialEnds',
            current_period_start=NOW(),
            current_period_end='$trialEnds',
            activation_token=NULL,
            token_expires_at=NULL,
            updated_at=NOW()
         WHERE subscription_id=$sid"
    );
    $conn->query("UPDATE business_account SET business_status=1 WHERE business_id=$bizId");
    if ($userId > 0) {
        $conn->query("UPDATE user SET user_status=1 WHERE user_id=$userId");
    }

    $sub = fh_portal_sub_fetch($conn, $sid);
    return array('ok' => true, 'msg' => 'Trial activated.', 'subscription' => $sub, 'trial_days' => $days);
}

/**
 * Mark subscription pending_payment and store checkout amount snapshot.
 */
function fh_portal_sub_prepare_payment($conn, $subscriptionId, $billingCycle, $months) {
    $sub = fh_portal_sub_fetch($conn, $subscriptionId);
    if (!$sub) {
        return array('ok' => false, 'msg' => 'Subscription not found.');
    }
    $settings = fh_portal_sub_get_settings($conn);
    $amounts = fh_portal_sub_compute_amount($settings, $billingCycle, $months);
    $sid = (int)$subscriptionId;
    $cycle = $conn->real_escape_string($amounts['billing_cycle']);
    $monthsN = (int)$amounts['months'];
    $subtotal = (float)$amounts['subtotal'];
    $fee = (float)$amounts['fee'];
    $total = (float)$amounts['total'];

    $conn->query(
        "UPDATE business_subscriptions SET
            status='pending_payment',
            billing_cycle='$cycle',
            months_purchased=$monthsN,
            amount_subtotal=$subtotal,
            amount_fee=$fee,
            amount_total=$total,
            updated_at=NOW()
         WHERE subscription_id=$sid"
    );

    return array(
        'ok' => true,
        'subscription' => fh_portal_sub_fetch($conn, $sid),
        'amounts' => $amounts,
    );
}

/**
 * Apply a successful payment: activate/extend paid period and unlock venue + owner.
 */
function fh_portal_sub_apply_payment($conn, $subscriptionId, $paymentId = 0) {
    $sub = fh_portal_sub_fetch($conn, $subscriptionId);
    if (!$sub) {
        return array('ok' => false, 'msg' => 'Subscription not found.');
    }

    $months = max(1, (int)$sub['months_purchased']);
    if ($sub['billing_cycle'] === 'yearly') {
        $months = 12;
    }

    $now = time();
    $base = $now;
    // Extend from current paid end if still active
    if ($sub['status'] === 'active' && !empty($sub['current_period_end'])) {
        $endTs = strtotime($sub['current_period_end']);
        if ($endTs && $endTs > $now) {
            $base = $endTs;
        }
    }

    $periodStart = date('Y-m-d H:i:s', $now);
    $periodEnd = date('Y-m-d H:i:s', strtotime('+' . $months . ' months', $base));

    $sid = (int)$subscriptionId;
    $bizId = (int)$sub['business_id'];
    $userId = (int)$sub['owner_user_id'];
    $payId = (int)$paymentId;

    $conn->query(
        "UPDATE business_subscriptions SET
            status='active',
            trial_ends_at=NULL,
            current_period_start='$periodStart',
            current_period_end='$periodEnd',
            activation_token=NULL,
            token_expires_at=NULL,
            last_payment_id=$payId,
            updated_at=NOW()
         WHERE subscription_id=$sid"
    );
    $conn->query("UPDATE business_account SET business_status=1 WHERE business_id=$bizId");
    if ($userId > 0) {
        $conn->query("UPDATE user SET user_status=1 WHERE user_id=$userId");
    }

    return array('ok' => true, 'subscription' => fh_portal_sub_fetch($conn, $sid));
}

/**
 * Effective access status for a venue: active | trialing | expired | pending | none
 */
function fh_portal_sub_access_state($conn, $businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0 || !fh_portal_sub_table_exists($conn)) {
        // Legacy venues without subscription rows remain accessible
        return array(
            'state' => 'none',
            'subscription' => null,
            'is_access_ok' => true,
            'is_expired' => false,
            'days_left' => null,
            'label' => 'No subscription required',
        );
    }

    $sub = fh_portal_sub_fetch_by_business($conn, $businessId);
    if (!$sub) {
        return array(
            'state' => 'none',
            'subscription' => null,
            'is_access_ok' => true,
            'is_expired' => false,
            'days_left' => null,
            'label' => 'No subscription on file',
        );
    }

    $now = time();
    $status = $sub['status'];

    if ($status === 'pending_activation' || $status === 'pending_payment') {
        return array(
            'state' => 'pending',
            'subscription' => $sub,
            'is_access_ok' => false,
            'is_expired' => false,
            'days_left' => null,
            'label' => $status === 'pending_payment' ? 'Payment pending' : 'Activation pending',
        );
    }

    if ($status === 'trialing') {
        $ends = !empty($sub['trial_ends_at']) ? strtotime($sub['trial_ends_at']) : 0;
        if ($ends && $ends >= $now) {
            $daysLeft = (int)ceil(($ends - $now) / 86400);
            return array(
                'state' => 'trialing',
                'subscription' => $sub,
                'is_access_ok' => true,
                'is_expired' => false,
                'days_left' => $daysLeft,
                'label' => 'Free trial — ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left',
                'ends_at' => $sub['trial_ends_at'],
            );
        }
        $conn->query("UPDATE business_subscriptions SET status='expired', updated_at=NOW() WHERE subscription_id=" . (int)$sub['subscription_id']);
        $sub['status'] = 'expired';
        return array(
            'state' => 'expired',
            'subscription' => $sub,
            'is_access_ok' => false,
            'is_expired' => true,
            'days_left' => 0,
            'label' => 'Free trial ended — please subscribe',
            'ends_at' => $sub['trial_ends_at'],
        );
    }

    if ($status === 'active') {
        $ends = !empty($sub['current_period_end']) ? strtotime($sub['current_period_end']) : 0;
        if ($ends && $ends >= $now) {
            $daysLeft = (int)ceil(($ends - $now) / 86400);
            return array(
                'state' => 'active',
                'subscription' => $sub,
                'is_access_ok' => true,
                'is_expired' => false,
                'days_left' => $daysLeft,
                'label' => 'Active until ' . date('M j, Y', $ends),
                'ends_at' => $sub['current_period_end'],
            );
        }
        $conn->query("UPDATE business_subscriptions SET status='expired', updated_at=NOW() WHERE subscription_id=" . (int)$sub['subscription_id']);
        $sub['status'] = 'expired';
        return array(
            'state' => 'expired',
            'subscription' => $sub,
            'is_access_ok' => false,
            'is_expired' => true,
            'days_left' => 0,
            'label' => 'Subscription expired — please renew',
            'ends_at' => $sub['current_period_end'],
        );
    }

    if ($status === 'expired') {
        return array(
            'state' => 'expired',
            'subscription' => $sub,
            'is_access_ok' => false,
            'is_expired' => true,
            'days_left' => 0,
            'label' => 'Subscription expired — please renew',
            'ends_at' => $sub['current_period_end'] ?: $sub['trial_ends_at'],
        );
    }

    return array(
        'state' => $status,
        'subscription' => $sub,
        'is_access_ok' => false,
        'is_expired' => false,
        'days_left' => null,
        'label' => ucfirst(str_replace('_', ' ', $status)),
    );
}

function fh_portal_sub_activation_url($token) {
    include_once __DIR__ . '/../admin/payment_config.php';
    return rtrim(pb_app_base_url(), '/') . '/partner_activate.php?token=' . urlencode($token);
}

function fh_portal_sub_send_activation_email($to, $businessName, $token, $intent) {
    include_once __DIR__ . '/email_helper.php';
    $url = fh_portal_sub_activation_url($token);
    $name = htmlspecialchars($businessName !== '' ? $businessName : 'Partner');
    $safeUrl = htmlspecialchars($url);

    if ($intent === 'subscribe') {
        $subject = 'Pickleball Portal — Activate & pay your subscription';
        $html = '<html><body>'
            . '<p>Hi ' . $name . ',</p>'
            . '<p>Thanks for registering with Pickleball Portal. Click the link below to activate your account and complete subscription payment via PayMongo:</p>'
            . '<p><a href="' . $safeUrl . '">Activate &amp; Pay Subscription</a></p>'
            . '<p>Or copy this link:<br>' . $safeUrl . '</p>'
            . '<p>This link expires in 7 days.</p>'
            . '<p>Thank you,<br>Pickleball Portal</p>'
            . '</body></html>';
    } else {
        $subject = 'Pickleball Portal — Activate your free trial';
        $html = '<html><body>'
            . '<p>Hi ' . $name . ',</p>'
            . '<p>Thanks for registering with Pickleball Portal. Click the link below to activate your free trial account:</p>'
            . '<p><a href="' . $safeUrl . '">Activate Free Trial</a></p>'
            . '<p>Or copy this link:<br>' . $safeUrl . '</p>'
            . '<p>This link expires in 7 days.</p>'
            . '<p>Thank you,<br>Pickleball Portal</p>'
            . '</body></html>';
    }

    return pb_send_mail($to, $subject, $html);
}

function fh_portal_sub_format_money($amount) {
    return '₱' . number_format((float)$amount, 2);
}
