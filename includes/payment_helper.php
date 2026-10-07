<?php
include_once __DIR__ . '/../admin/payment_config.php';
include_once __DIR__ . '/realtime_helper.php';

/**
 * Per-business PayMongo gateway settings.
 *
 * Every business account may connect its own PayMongo account. Court bookings
 * and membership payments then use the venue's keys; when a venue has not
 * connected an account, the platform keys (PB_PAYMONGO_* config) are used.
 * Platform subscription fees always use the platform account.
 */

function pb_paymongo_business_columns_ready($conn) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    if ($conn) {
        $res = $conn->query("SHOW COLUMNS FROM business_account LIKE 'business_paymongo_secret_key'");
        if ($res && $res->num_rows > 0) {
            $ready = true;
        }
    }
    return $ready;
}

function pb_business_paymongo_settings($conn, $businessId) {
    $businessId = (int)$businessId;
    $settings = array(
        'business_id' => $businessId,
        'enabled' => 0,
        'public_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
        'updated_at' => '',
        'configured' => false,
    );
    if ($businessId <= 0 || !pb_paymongo_business_columns_ready($conn)) {
        return $settings;
    }
    $sql = $conn->query(
        "SELECT business_paymongo_enabled, business_paymongo_public_key,
                business_paymongo_secret_key, business_paymongo_webhook_secret,
                business_paymongo_updated_at
         FROM business_account
         WHERE business_id = $businessId
         LIMIT 1"
    );
    if (!$sql || !($row = $sql->fetch_assoc())) {
        return $settings;
    }
    $settings['enabled'] = ((int)$row['business_paymongo_enabled'] === 1) ? 1 : 0;
    $settings['public_key'] = isset($row['business_paymongo_public_key']) ? (string)$row['business_paymongo_public_key'] : '';
    $settings['secret_key'] = isset($row['business_paymongo_secret_key']) ? (string)$row['business_paymongo_secret_key'] : '';
    $settings['webhook_secret'] = isset($row['business_paymongo_webhook_secret']) ? (string)$row['business_paymongo_webhook_secret'] : '';
    $settings['updated_at'] = isset($row['business_paymongo_updated_at']) ? (string)$row['business_paymongo_updated_at'] : '';
    $settings['configured'] = ($settings['enabled'] === 1 && $settings['secret_key'] !== '');
    return $settings;
}

/**
 * Save a business's PayMongo credentials. Secret fields left blank keep the
 * existing stored value (write-only semantics).
 * Returns ['ok'=>bool, 'msg'=>string].
 */
function pb_business_paymongo_save($conn, $businessId, $data) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return array('ok' => false, 'msg' => 'Choose a business account first.');
    }
    if (!pb_paymongo_business_columns_ready($conn)) {
        return array('ok' => false, 'msg' => 'PayMongo columns are missing. Run sql/migrate_business_paymongo.sql first.');
    }

    $current = pb_business_paymongo_settings($conn, $businessId);
    $enabled = !empty($data['enabled']) ? 1 : 0;
    $clear = !empty($data['clear_keys']);

    $publicKey = trim((string)(isset($data['public_key']) ? $data['public_key'] : ''));
    $secretKey = trim((string)(isset($data['secret_key']) ? $data['secret_key'] : ''));
    $webhookSecret = trim((string)(isset($data['webhook_secret']) ? $data['webhook_secret'] : ''));

    if ($clear) {
        $publicKey = '';
        $secretKey = '';
        $webhookSecret = '';
        $enabled = 0;
    } else {
        if ($secretKey === '') {
            $secretKey = $current['secret_key'];
        }
        if ($publicKey === '') {
            $publicKey = $current['public_key'];
        }
        if ($webhookSecret === '') {
            $webhookSecret = $current['webhook_secret'];
        }
    }

    if ($enabled === 1 && $secretKey === '') {
        return array('ok' => false, 'msg' => 'A PayMongo secret key is required to enable this business account.');
    }
    if ($secretKey !== '' && strpos($secretKey, 'sk_') !== 0) {
        return array('ok' => false, 'msg' => 'The secret key should start with "sk_".');
    }
    if ($publicKey !== '' && strpos($publicKey, 'pk_') !== 0) {
        return array('ok' => false, 'msg' => 'The public key should start with "pk_".');
    }
    if ($webhookSecret !== '' && strpos($webhookSecret, 'whsec_') !== 0) {
        return array('ok' => false, 'msg' => 'The webhook secret should start with "whsec_".');
    }

    $safePublic = $conn->real_escape_string($publicKey);
    $safeSecret = $conn->real_escape_string($secretKey);
    $safeWebhook = $conn->real_escape_string($webhookSecret);
    $ok = $conn->query(
        "UPDATE business_account SET
            business_paymongo_enabled = $enabled,
            business_paymongo_public_key = " . ($publicKey === '' ? 'NULL' : "'$safePublic'") . ",
            business_paymongo_secret_key = " . ($secretKey === '' ? 'NULL' : "'$safeSecret'") . ",
            business_paymongo_webhook_secret = " . ($webhookSecret === '' ? 'NULL' : "'$safeWebhook'") . ",
            business_paymongo_updated_at = NOW()
         WHERE business_id = $businessId"
    );
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not save PayMongo settings: ' . $conn->error);
    }
    return array('ok' => true, 'msg' => 'PayMongo settings saved.');
}

function pb_paymongo_platform_credentials() {
    return array(
        'secret' => PB_PAYMONGO_SECRET_KEY,
        'public' => PB_PAYMONGO_PUBLIC_KEY,
        'webhook_secret' => PB_PAYMONGO_WEBHOOK_SECRET,
        'business_id' => 0,
        'source' => 'platform',
    );
}

/**
 * Resolve which PayMongo account should process a payment.
 *
 * $mode: 'auto'     -> business account when configured, else platform
 *        'business' -> only the business account (demo when missing)
 *        'platform' -> always the platform account (subscription fees)
 */
function pb_paymongo_resolve_credentials($conn, $businessId, $mode = 'auto') {
    $businessId = (int)$businessId;
    $mode = ($mode === 'platform' || $mode === 'business') ? $mode : 'auto';

    if ($mode !== 'platform' && $businessId > 0) {
        $settings = pb_business_paymongo_settings($conn, $businessId);
        if (!empty($settings['configured'])) {
            return array(
                'secret' => $settings['secret_key'],
                'public' => $settings['public_key'],
                'webhook_secret' => $settings['webhook_secret'],
                'business_id' => $businessId,
                'source' => 'business',
            );
        }
        if ($mode === 'business') {
            return array(
                'secret' => '',
                'public' => '',
                'webhook_secret' => '',
                'business_id' => $businessId,
                'source' => 'none',
            );
        }
    }
    return pb_paymongo_platform_credentials();
}

function pb_paymongo_configured_for_business($conn, $businessId, $mode = 'auto') {
    $creds = pb_paymongo_resolve_credentials($conn, $businessId, $mode);
    return $creds['secret'] !== '';
}

function pb_paymongo_webhook_url() {
    return pb_app_base_url() . '/webhooks/paymongo.php';
}

/**
 * Verify a PayMongo webhook signature header (t=..,te=..,li=..).
 */
function pb_paymongo_verify_signature($rawPayload, $signatureHeader, $secret) {
    if ($secret === '' || $signatureHeader === '') {
        return false;
    }
    $parts = array();
    foreach (explode(',', $signatureHeader) as $chunk) {
        $kv = explode('=', $chunk, 2);
        if (count($kv) === 2) {
            $parts[trim($kv[0])] = trim($kv[1]);
        }
    }
    if (empty($parts['t'])) {
        return false;
    }
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $rawPayload, $secret);
    foreach (array('li', 'te') as $key) {
        if (!empty($parts[$key]) && hash_equals($expected, $parts[$key])) {
            return true;
        }
    }
    return false;
}

function pb_payment_create_row($conn, $data) {
    $bookingId = (int)(isset($data['booking_id']) ? $data['booking_id'] : 0);
    $membershipId = (int)(isset($data['membership_id']) ? $data['membership_id'] : 0);
    $platformSubId = (int)(isset($data['platform_subscription_id']) ? $data['platform_subscription_id'] : 0);
    $customersId = (int)(isset($data['customers_id']) ? $data['customers_id'] : 0);
    $businessId = (int)(isset($data['business_id']) ? $data['business_id'] : 0);
    $amount = (float)$data['amount'];
    $provider = $conn->real_escape_string(isset($data['provider']) ? $data['provider'] : 'paymongo');
    $txn = $conn->real_escape_string(isset($data['provider_txn_id']) ? $data['provider_txn_id'] : '');
    $url = $conn->real_escape_string(isset($data['checkout_url']) ? $data['checkout_url'] : '');
    $status = $conn->real_escape_string(isset($data['payment_status']) ? $data['payment_status'] : 'pending');
    $conn->query(
        "INSERT INTO payments
         (booking_id, membership_id, platform_subscription_id, customers_id, business_id, amount, provider, provider_txn_id, checkout_url, payment_status, created_at)
         VALUES ($bookingId, $membershipId, $platformSubId, $customersId, $businessId, $amount, '$provider', '$txn', '$url', '$status', NOW())"
    );
    return (int)$conn->insert_id;
}

function pb_payment_fetch($conn, $paymentId) {
    $paymentId = (int)$paymentId;
    $sql = $conn->query("SELECT * FROM payments WHERE payment_id=$paymentId LIMIT 1");
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_payment_fetch_by_txn($conn, $txnId) {
    $safe = $conn->real_escape_string($txnId);
    $sql = $conn->query("SELECT * FROM payments WHERE provider_txn_id='$safe' LIMIT 1");
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_payment_mark_paid($conn, $paymentId, $raw = '') {
    $paymentId = (int)$paymentId;
    $safeRaw = $conn->real_escape_string(substr((string)$raw, 0, 60000));
    $conn->query(
        "UPDATE payments
         SET payment_status='paid', updated_at=NOW(), raw_payload='$safeRaw'
         WHERE payment_id=$paymentId"
    );
    $pay = pb_payment_fetch($conn, $paymentId);
    if ($pay && (int)$pay['booking_id'] > 0) {
        $bid = (int)$pay['booking_id'];
        $conn->query(
            "UPDATE bookings
             SET payment_status='paid', payment_id=$paymentId, booking_status='confirmed', booking_updated=NOW()
             WHERE booking_id=$bid"
        );
        $b = $conn->query("SELECT business_id, court_id FROM bookings WHERE booking_id=$bid LIMIT 1");
        if ($b && ($br = $b->fetch_assoc())) {
            pb_realtime_publish('booking_updated', array(
                'business_id' => (int)$br['business_id'],
                'court_id' => (int)$br['court_id'],
                'booking_id' => $bid,
                'event' => 'paid',
            ));
        }
    }
    if ($pay && (int)$pay['membership_id'] > 0) {
        $mid = (int)$pay['membership_id'];
        $conn->query(
            "UPDATE memberships
             SET membership_status='active', payment_id=$paymentId
             WHERE membership_id=$mid"
        );
    }
    if ($pay && (int)$pay['platform_subscription_id'] > 0) {
        include_once __DIR__ . '/portal_subscription_helper.php';
        fh_portal_sub_apply_payment($conn, (int)$pay['platform_subscription_id'], $paymentId);
    }
    return true;
}

/**
 * Create PayMongo Checkout Session (or demo checkout URL when keys missing).
 * Returns ['ok'=>bool, 'payment_id'=>int, 'checkout_url'=>string, 'msg'=>string]
 */
function pb_paymongo_create_checkout($conn, $opts) {
    $amount = round((float)$opts['amount'], 2);
    $description = isset($opts['description']) ? $opts['description'] : 'Pickleball booking';
    $successUrl = isset($opts['success_url']) ? $opts['success_url'] : (pb_app_base_url() . '/my_bookings.php?paid=1');
    $cancelUrl = isset($opts['cancel_url']) ? $opts['cancel_url'] : (pb_app_base_url() . '/my_bookings.php?paid=0');

    $businessId = (int)(isset($opts['business_id']) ? $opts['business_id'] : 0);
    $credMode = isset($opts['credentials']) ? $opts['credentials'] : 'auto';
    $credentials = pb_paymongo_resolve_credentials($conn, $businessId, $credMode);

    $paymentId = pb_payment_create_row($conn, array(
        'booking_id' => isset($opts['booking_id']) ? $opts['booking_id'] : 0,
        'membership_id' => isset($opts['membership_id']) ? $opts['membership_id'] : 0,
        'platform_subscription_id' => isset($opts['platform_subscription_id']) ? $opts['platform_subscription_id'] : 0,
        'customers_id' => isset($opts['customers_id']) ? $opts['customers_id'] : 0,
        'business_id' => $businessId,
        'amount' => $amount,
        'provider' => 'paymongo',
        'payment_status' => 'pending',
    ));

    if ($credentials['secret'] === '') {
        // Demo mode: local fake checkout page
        $demoUrl = pb_app_base_url() . '/pay_demo.php?payment_id=' . $paymentId;
        $safeUrl = $conn->real_escape_string($demoUrl);
        $txn = 'demo_' . $paymentId;
        $conn->query("UPDATE payments SET checkout_url='$safeUrl', provider_txn_id='$txn' WHERE payment_id=$paymentId");
        if (!empty($opts['booking_id'])) {
            $bid = (int)$opts['booking_id'];
            $conn->query("UPDATE bookings SET payment_id=$paymentId, payment_status='pending', payment_type='online' WHERE booking_id=$bid");
        }
        if (!empty($opts['membership_id'])) {
            $mid = (int)$opts['membership_id'];
            $conn->query("UPDATE memberships SET payment_id=$paymentId WHERE membership_id=$mid");
        }
        return array('ok' => true, 'payment_id' => $paymentId, 'checkout_url' => $demoUrl, 'demo' => true, 'msg' => 'Demo checkout');
    }

    $amountCentavos = (int)round($amount * 100);
    $payload = array(
        'data' => array(
            'attributes' => array(
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'description' => $description,
                'line_items' => array(array(
                    'currency' => 'PHP',
                    'amount' => $amountCentavos,
                    'description' => $description,
                    'name' => $description,
                    'quantity' => 1,
                )),
                'payment_method_types' => array('card', 'gcash', 'paymaya'),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => array(
                    'payment_id' => (string)$paymentId,
                    'business_id' => (string)$businessId,
                    'booking_id' => (string)(isset($opts['booking_id']) ? $opts['booking_id'] : 0),
                    'platform_subscription_id' => (string)(isset($opts['platform_subscription_id']) ? $opts['platform_subscription_id'] : 0),
                ),
            ),
        ),
    );

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($credentials['secret'] . ':'),
        ),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ));
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $code >= 400) {
        $conn->query("UPDATE payments SET payment_status='failed', updated_at=NOW() WHERE payment_id=$paymentId");
        return array('ok' => false, 'payment_id' => $paymentId, 'msg' => 'PayMongo error: ' . ($err ?: $raw));
    }

    $json = json_decode($raw, true);
    $checkoutId = isset($json['data']['id']) ? $json['data']['id'] : '';
    $checkoutUrl = isset($json['data']['attributes']['checkout_url']) ? $json['data']['attributes']['checkout_url'] : '';
    if ($checkoutUrl === '') {
        $conn->query("UPDATE payments SET payment_status='failed', updated_at=NOW() WHERE payment_id=$paymentId");
        return array('ok' => false, 'payment_id' => $paymentId, 'msg' => 'No checkout URL from PayMongo.');
    }

    $safeUrl = $conn->real_escape_string($checkoutUrl);
    $safeTxn = $conn->real_escape_string($checkoutId);
    $safeRaw = $conn->real_escape_string(substr($raw, 0, 60000));
    $conn->query(
        "UPDATE payments
         SET checkout_url='$safeUrl', provider_txn_id='$safeTxn', raw_payload='$safeRaw', updated_at=NOW()
         WHERE payment_id=$paymentId"
    );

    if (!empty($opts['booking_id'])) {
        $bid = (int)$opts['booking_id'];
        $conn->query("UPDATE bookings SET payment_id=$paymentId, payment_status='pending', payment_type='online' WHERE booking_id=$bid");
    }
    if (!empty($opts['membership_id'])) {
        $mid = (int)$opts['membership_id'];
        $conn->query("UPDATE memberships SET payment_id=$paymentId WHERE membership_id=$mid");
    }

    return array('ok' => true, 'payment_id' => $paymentId, 'checkout_url' => $checkoutUrl, 'msg' => 'Checkout created');
}
