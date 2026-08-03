<?php

include_once __DIR__ . '/order_helper.php';
include_once __DIR__ . '/court_helper.php';

function pb_booking_status_label($status) {
    $s = strtolower(trim((string)$status));
    $map = array(
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'completed' => 'Completed',
    );
    return isset($map[$s]) ? $map[$s] : ucfirst($s);
}

function pb_booking_is_active($status) {
    $s = strtolower(trim((string)$status));
    return $s === 'pending' || $s === 'confirmed';
}

function pb_booking_fetch($conn, $bookingId) {
    $bookingId = (int)$bookingId;
    $sql = $conn->query(
        "SELECT b.*, c.court_name, c.court_type, c.court_surface, c.buffer_minutes,
                ba.business_name, ba.business_subdomain, ba.business_address, ba.business_city, ba.business_contact
         FROM bookings b
         INNER JOIN courts c ON c.court_id = b.court_id
         INNER JOIN business_account ba ON ba.business_id = b.business_id
         WHERE b.booking_id = $bookingId
         LIMIT 1"
    );
    if ($sql && ($row = $sql->fetch_assoc())) {
        return $row;
    }
    return null;
}

function pb_booking_fetch_for_customer($conn, $bookingId, $customerId) {
    $booking = pb_booking_fetch($conn, $bookingId);
    if (!$booking) {
        return null;
    }
    if ((int)$booking['customers_id'] !== (int)$customerId) {
        return null;
    }
    return $booking;
}

function pb_bookings_for_customer($conn, $customerId, $limit = 50) {
    $customerId = (int)$customerId;
    $limit = max(1, min(200, (int)$limit));
    $list = array();
    $sql = $conn->query(
        "SELECT b.*, c.court_name, ba.business_name, ba.business_subdomain
         FROM bookings b
         INNER JOIN courts c ON c.court_id = b.court_id
         INNER JOIN business_account ba ON ba.business_id = b.business_id
         WHERE b.customers_id = $customerId
         ORDER BY b.start_datetime DESC
         LIMIT $limit"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_bookings_for_business($conn, $businessId, $filters = array()) {
    $businessId = (int)$businessId;
    $where = "WHERE b.business_id = $businessId";
    if (!empty($filters['status'])) {
        $safe = $conn->real_escape_string($filters['status']);
        $where .= " AND b.booking_status = '$safe'";
    }
    if (!empty($filters['date'])) {
        $safeDate = $conn->real_escape_string($filters['date']);
        $where .= " AND DATE(b.start_datetime) = '$safeDate'";
    }
    if (!empty($filters['q'])) {
        $safeQ = $conn->real_escape_string($filters['q']);
        $where .= " AND (b.customer_fname LIKE '%$safeQ%' OR b.customer_lname LIKE '%$safeQ%' OR b.customer_email LIKE '%$safeQ%' OR b.booking_receipt_no LIKE '%$safeQ%' OR c.court_name LIKE '%$safeQ%')";
    }
    $list = array();
    $sql = $conn->query(
        "SELECT b.*, c.court_name
         FROM bookings b
         INNER JOIN courts c ON c.court_id = b.court_id
         $where
         ORDER BY b.start_datetime DESC
         LIMIT 200"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Atomically create a booking if the slot is free (with buffer).
 * Returns ['ok'=>bool, 'booking_id'=>int, 'status'=>string, 'receipt_no'=>string, 'msg'=>string]
 */
function pb_booking_create($conn, $payload) {
    $courtId = (int)$payload['court_id'];
    $customerId = (int)$payload['customers_id'];
    $start = $payload['start_datetime'];
    $end = $payload['end_datetime'];
    $fname = trim((string)$payload['customer_fname']);
    $lname = trim((string)$payload['customer_lname']);
    $email = trim((string)$payload['customer_email']);
    $contact = trim((string)$payload['customer_contact']);
    $remarks = isset($payload['booking_remarks']) ? trim((string)$payload['booking_remarks']) : '';
    $paymentType = isset($payload['payment_type']) ? trim((string)$payload['payment_type']) : 'pay_at_venue';
    if ($paymentType !== 'online') {
        $paymentType = 'pay_at_venue';
    }

    $court = pb_court_fetch($conn, $courtId);
    if (!$court || (int)$court['court_status'] !== 1 || (int)$court['business_status'] !== 1) {
        return array('ok' => false, 'msg' => 'Court is not available.');
    }

    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        return array('ok' => false, 'msg' => 'Invalid booking time.');
    }
    if ($startTs <= time()) {
        return array('ok' => false, 'msg' => 'Cannot book a time in the past.');
    }

    $date = date('Y-m-d', $startTs);
    $settings = pb_booking_settings($conn, (int)$court['business_id']);
    $clamped = pb_clamp_booking_date($date, $settings['booking_max_days']);
    if ($clamped !== $date) {
        return array('ok' => false, 'msg' => 'Selected date is outside the allowed booking window.');
    }

    $slots = pb_generate_slots($conn, $court, $date);
    $matched = null;
    foreach ($slots as $slot) {
        if ($slot['start'] === date('Y-m-d H:i:s', $startTs) || $slot['start'] === date('Y-m-d H:i:00', $startTs)) {
            if (strtotime($slot['end']) === $endTs || date('Y-m-d H:i:s', strtotime($slot['end'])) === date('Y-m-d H:i:s', $endTs)) {
                $matched = $slot;
                break;
            }
        }
        // Tolerate H:i vs H:i:s formatting
        if (strtotime($slot['start']) === $startTs && strtotime($slot['end']) === $endTs) {
            $matched = $slot;
            break;
        }
    }
    if (!$matched) {
        return array('ok' => false, 'msg' => 'That time slot is no longer available.');
    }

    $price = (float)$matched['price'];
    include_once __DIR__ . '/membership_helper.php';
    $disc = pb_apply_member_discount($conn, $customerId, (int)$court['business_id'], $price);
    $price = (float)$disc['price'];

    $buffer = max(0, (int)$court['buffer_minutes']);
    $busyStart = date('Y-m-d H:i:s', $startTs - ($buffer * 60));
    $busyEnd = date('Y-m-d H:i:s', $endTs + ($buffer * 60));
    $status = $settings['auto_confirm'] ? 'confirmed' : 'pending';
    $paymentStatus = 'unpaid';
    if ($paymentType === 'online') {
        $status = 'pending';
        $paymentStatus = 'pending';
    }

    // Allocate receipt before locking bookings (avoid nested transactions).
    $receiptNo = fh_order_allocate_receipt_no($conn, (int)$court['business_id']);
    if ($receiptNo === '') {
        $receiptNo = 'BK-' . date('YmdHis') . '-' . $courtId;
    }

    $conn->begin_transaction();
    try {
        $safeBusyStart = $conn->real_escape_string($busyStart);
        $safeBusyEnd = $conn->real_escape_string($busyEnd);
        $lockSql = $conn->query(
            "SELECT booking_id FROM bookings
             WHERE court_id = $courtId
               AND booking_type='court'
               AND booking_status IN ('pending','confirmed')
               AND start_datetime < '$safeBusyEnd'
               AND end_datetime > '$safeBusyStart'
             FOR UPDATE"
        );
        if ($lockSql && $lockSql->num_rows > 0) {
            $conn->rollback();
            return array('ok' => false, 'msg' => 'That time slot was just booked. Please choose another.');
        }

        $safeReceipt = $conn->real_escape_string($receiptNo);
        $safeF = $conn->real_escape_string($fname);
        $safeL = $conn->real_escape_string($lname);
        $safeE = $conn->real_escape_string($email);
        $safeC = $conn->real_escape_string($contact);
        $safeStart = $conn->real_escape_string(date('Y-m-d H:i:s', $startTs));
        $safeEnd = $conn->real_escape_string(date('Y-m-d H:i:s', $endTs));
        $safeStatus = $conn->real_escape_string($status);
        $safePay = $conn->real_escape_string($paymentType);
        $safePayStatus = $conn->real_escape_string($paymentStatus);
        $safeRemarks = $conn->real_escape_string($remarks);
        if (!empty($disc['discount_percent'])) {
            $safeRemarks = $conn->real_escape_string(
                trim($remarks . (strlen($remarks) ? ' | ' : '') . 'Member discount ' . $disc['discount_percent'] . '%')
            );
        }
        $businessId = (int)$court['business_id'];

        $ok = $conn->query(
            "INSERT INTO bookings
             (booking_type, court_id, business_id, customers_id, booking_receipt_no, customer_fname, customer_lname,
              customer_email, customer_contact, start_datetime, end_datetime, booking_status,
              total_price, payment_type, payment_status, booking_remarks, booking_created)
             VALUES
             ('court', $courtId, $businessId, $customerId, '$safeReceipt', '$safeF', '$safeL',
              '$safeE', '$safeC', '$safeStart', '$safeEnd', '$safeStatus',
              $price, '$safePay', '$safePayStatus', '$safeRemarks', NOW())"
        );
        if (!$ok) {
            $conn->rollback();
            return array('ok' => false, 'msg' => 'Could not save booking. Please try again.');
        }
        $bookingId = (int)$conn->insert_id;
        $conn->commit();

        include_once __DIR__ . '/realtime_helper.php';
        pb_realtime_publish('booking_updated', array(
            'business_id' => $businessId,
            'court_id' => $courtId,
            'booking_id' => $bookingId,
            'event' => 'created',
        ));

        include_once __DIR__ . '/email_helper.php';
        $mailBooking = pb_booking_fetch($conn, $bookingId);
        if ($mailBooking) {
            @pb_email_booking_created($mailBooking);
        }

        return array(
            'ok' => true,
            'booking_id' => $bookingId,
            'status' => $status,
            'receipt_no' => $receiptNo,
            'total_price' => $price,
            'business_id' => $businessId,
            'payment_type' => $paymentType,
            'msg' => $status === 'confirmed'
                ? 'Booking confirmed.'
                : 'Booking submitted. Waiting for venue confirmation.',
        );
    } catch (Exception $e) {
        $conn->rollback();
        return array('ok' => false, 'msg' => 'Booking failed. Please try again.');
    }
}

function pb_booking_cancel($conn, $bookingId, $byCustomerId = 0, $reason = '', $force = false) {
    $booking = pb_booking_fetch($conn, $bookingId);
    if (!$booking) {
        return array('ok' => false, 'msg' => 'Booking not found.');
    }
    if (!pb_booking_is_active($booking['booking_status'])) {
        return array('ok' => false, 'msg' => 'This booking cannot be cancelled.');
    }
    if ($byCustomerId > 0 && (int)$booking['customers_id'] !== (int)$byCustomerId) {
        return array('ok' => false, 'msg' => 'Not allowed.');
    }
    if ($byCustomerId > 0 && !$force) {
        $startTs = strtotime($booking['start_datetime']);
        if ($startTs - time() < 24 * 3600) {
            return array('ok' => false, 'msg' => 'Cancellations require at least 24 hours notice.');
        }
    }

    $safeReason = $conn->real_escape_string(substr(trim($reason), 0, 255));
    $id = (int)$bookingId;
    $ok = $conn->query(
        "UPDATE bookings
         SET booking_status = 'cancelled',
             cancelled_at = NOW(),
             cancel_reason = '$safeReason',
             booking_updated = NOW()
         WHERE booking_id = $id
           AND booking_status IN ('pending','confirmed')"
    );
    if (!$ok || $conn->affected_rows < 1) {
        return array('ok' => false, 'msg' => 'Could not cancel booking.');
    }
    include_once __DIR__ . '/realtime_helper.php';
    pb_realtime_publish('booking_updated', array(
        'business_id' => (int)$booking['business_id'],
        'court_id' => (int)$booking['court_id'],
        'booking_id' => (int)$bookingId,
        'event' => 'cancelled',
    ));
    include_once __DIR__ . '/email_helper.php';
    @pb_email_booking_cancelled($booking, $reason);
    return array('ok' => true, 'msg' => 'Booking cancelled.');
}

function pb_booking_set_status($conn, $bookingId, $status) {
    $allowed = array('pending', 'confirmed', 'cancelled', 'completed');
    $status = strtolower(trim($status));
    if (!in_array($status, $allowed, true)) {
        return array('ok' => false, 'msg' => 'Invalid status.');
    }
    $id = (int)$bookingId;
    $safe = $conn->real_escape_string($status);
    $extra = "booking_updated = NOW()";
    if ($status === 'cancelled') {
        $extra .= ", cancelled_at = NOW()";
    }
    $ok = $conn->query("UPDATE bookings SET booking_status = '$safe', $extra WHERE booking_id = $id");
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Update failed.');
    }
    return array('ok' => true, 'msg' => 'Booking updated.');
}
