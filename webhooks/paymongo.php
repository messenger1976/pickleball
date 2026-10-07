<?php
/**
 * PayMongo webhook — marks payments paid on checkout.session.payment.paid.
 *
 * Each business account can register this same URL in its own PayMongo
 * dashboard. The handler resolves the payment (and therefore the business),
 * then verifies the signature with that business's webhook secret. When the
 * business has no webhook secret, the platform webhook secret is used.
 * If no secret is configured at all, verification is skipped (legacy/demo).
 */
include_once __DIR__ . '/../admin/db_config.php';
include_once __DIR__ . '/../admin/payment_config.php';
include_once __DIR__ . '/../includes/payment_helper.php';

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
header('Content-Type: application/json');

if (!$json) {
    http_response_code(400);
    echo json_encode(array('ok' => false));
    exit;
}

$type = isset($json['data']['attributes']['type']) ? $json['data']['attributes']['type'] : '';
$data = isset($json['data']['attributes']['data']) ? $json['data']['attributes']['data'] : array();

// Also accept nested checkout session payload shapes
$checkoutId = '';
$metaPaymentId = 0;
if (isset($data['id'])) {
    $checkoutId = $data['id'];
}
if (isset($data['attributes']['metadata']['payment_id'])) {
    $metaPaymentId = (int)$data['attributes']['metadata']['payment_id'];
}

// Resolve the payment first so we know which business's secret to verify with.
$payment = null;
if ($metaPaymentId > 0) {
    $payment = pb_payment_fetch($conn, $metaPaymentId);
}
if (!$payment && $checkoutId !== '') {
    $payment = pb_payment_fetch_by_txn($conn, $checkoutId);
}

// Determine which secret should have signed this event. null means we cannot
// verify (no secret available) and therefore skip verification.
$verifySecret = null;
if ($payment) {
    $isPlatformCharge = ((int)$payment['platform_subscription_id'] > 0);
    $bizSettings = pb_business_paymongo_settings($conn, (int)$payment['business_id']);
    if (!$isPlatformCharge && !empty($bizSettings['configured'])) {
        // Charged on the venue's own PayMongo account.
        if ($bizSettings['webhook_secret'] !== '') {
            $verifySecret = $bizSettings['webhook_secret'];
        }
    } elseif (PB_PAYMONGO_WEBHOOK_SECRET !== '') {
        // Charged on the platform account.
        $verifySecret = PB_PAYMONGO_WEBHOOK_SECRET;
    }
} elseif (PB_PAYMONGO_WEBHOOK_SECRET !== '') {
    $verifySecret = PB_PAYMONGO_WEBHOOK_SECRET;
}

if ($verifySecret !== null) {
    $signatureHeader = isset($_SERVER['HTTP_PAYMONGO_SIGNATURE']) ? $_SERVER['HTTP_PAYMONGO_SIGNATURE'] : '';
    if (!pb_paymongo_verify_signature($raw, $signatureHeader, $verifySecret)) {
        http_response_code(401);
        echo json_encode(array('ok' => false, 'msg' => 'Invalid signature'));
        exit;
    }
}

$paidEvents = array(
    'checkout_session.payment.paid',
    'payment.paid',
);

if (!in_array($type, $paidEvents, true) && strpos($type, 'paid') === false) {
    echo json_encode(array('ok' => true, 'ignored' => true));
    exit;
}

if ($payment) {
    pb_payment_mark_paid($conn, (int)$payment['payment_id'], $raw);
    echo json_encode(array('ok' => true));
    exit;
}

http_response_code(404);
echo json_encode(array('ok' => false, 'msg' => 'Payment not found'));
