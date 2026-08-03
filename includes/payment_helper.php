<?php
include_once __DIR__ . '/../admin/payment_config.php';
include_once __DIR__ . '/realtime_helper.php';

function pb_payment_create_row($conn, $data) {
    $bookingId = (int)(isset($data['booking_id']) ? $data['booking_id'] : 0);
    $membershipId = (int)(isset($data['membership_id']) ? $data['membership_id'] : 0);
    $customersId = (int)(isset($data['customers_id']) ? $data['customers_id'] : 0);
    $businessId = (int)(isset($data['business_id']) ? $data['business_id'] : 0);
    $amount = (float)$data['amount'];
    $provider = $conn->real_escape_string(isset($data['provider']) ? $data['provider'] : 'paymongo');
    $txn = $conn->real_escape_string(isset($data['provider_txn_id']) ? $data['provider_txn_id'] : '');
    $url = $conn->real_escape_string(isset($data['checkout_url']) ? $data['checkout_url'] : '');
    $status = $conn->real_escape_string(isset($data['payment_status']) ? $data['payment_status'] : 'pending');
    $conn->query(
        "INSERT INTO payments
         (booking_id, membership_id, customers_id, business_id, amount, provider, provider_txn_id, checkout_url, payment_status, created_at)
         VALUES ($bookingId, $membershipId, $customersId, $businessId, $amount, '$provider', '$txn', '$url', '$status', NOW())"
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

    $paymentId = pb_payment_create_row($conn, array(
        'booking_id' => isset($opts['booking_id']) ? $opts['booking_id'] : 0,
        'membership_id' => isset($opts['membership_id']) ? $opts['membership_id'] : 0,
        'customers_id' => isset($opts['customers_id']) ? $opts['customers_id'] : 0,
        'business_id' => isset($opts['business_id']) ? $opts['business_id'] : 0,
        'amount' => $amount,
        'provider' => 'paymongo',
        'payment_status' => 'pending',
    ));

    if (!pb_paymongo_configured()) {
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
                    'booking_id' => (string)(isset($opts['booking_id']) ? $opts['booking_id'] : 0),
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
            'Authorization: Basic ' . base64_encode(PB_PAYMONGO_SECRET_KEY . ':'),
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
