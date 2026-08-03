<?php
/**
 * PayMongo webhook — marks payments paid on checkout.session.payment.paid
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

$paidEvents = array(
    'checkout_session.payment.paid',
    'payment.paid',
);

if (!in_array($type, $paidEvents, true) && strpos($type, 'paid') === false) {
    echo json_encode(array('ok' => true, 'ignored' => true));
    exit;
}

$payment = null;
if ($metaPaymentId > 0) {
    $payment = pb_payment_fetch($conn, $metaPaymentId);
}
if (!$payment && $checkoutId !== '') {
    $payment = pb_payment_fetch_by_txn($conn, $checkoutId);
}

if ($payment) {
    pb_payment_mark_paid($conn, (int)$payment['payment_id'], $raw);
    echo json_encode(array('ok' => true));
    exit;
}

http_response_code(404);
echo json_encode(array('ok' => false, 'msg' => 'Payment not found'));
