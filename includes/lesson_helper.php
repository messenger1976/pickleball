<?php

function pb_coaches_for_business($conn, $businessId, $activeOnly = true) {
    $businessId = (int)$businessId;
    $where = "WHERE business_id=$businessId";
    if ($activeOnly) {
        $where .= ' AND coach_status=1';
    }
    $list = array();
    $sql = $conn->query("SELECT * FROM coaches $where ORDER BY coach_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_lessons_for_business($conn, $businessId, $activeOnly = true) {
    $businessId = (int)$businessId;
    $where = "WHERE l.business_id=$businessId";
    if ($activeOnly) {
        $where .= ' AND l.lesson_status=1';
    }
    $list = array();
    $sql = $conn->query(
        "SELECT l.*, c.coach_name
         FROM lessons l
         LEFT JOIN coaches c ON c.coach_id = l.coach_id
         $where
         ORDER BY l.lesson_title ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_lesson_fetch($conn, $lessonId) {
    $lessonId = (int)$lessonId;
    $sql = $conn->query(
        "SELECT l.*, c.coach_name, ba.business_name, ba.business_status, ba.business_subdomain
         FROM lessons l
         LEFT JOIN coaches c ON c.coach_id = l.coach_id
         INNER JOIN business_account ba ON ba.business_id = l.business_id
         WHERE l.lesson_id=$lessonId
         LIMIT 1"
    );
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_lesson_rules($conn, $lessonId) {
    $lessonId = (int)$lessonId;
    $list = array();
    $sql = $conn->query("SELECT * FROM lesson_availability_rules WHERE lesson_id=$lessonId ORDER BY weekday, start_time");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_seed_lesson_week_rules($conn, $lessonId, $open = '08:00:00', $close = '18:00:00') {
    $lessonId = (int)$lessonId;
    $exists = $conn->query("SELECT rule_id FROM lesson_availability_rules WHERE lesson_id=$lessonId LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        return;
    }
    $open = $conn->real_escape_string($open);
    $close = $conn->real_escape_string($close);
    for ($d = 1; $d <= 6; $d++) { // Mon-Sat
        $conn->query(
            "INSERT INTO lesson_availability_rules (lesson_id, weekday, start_time, end_time)
             VALUES ($lessonId, $d, '$open', '$close')"
        );
    }
}

function pb_generate_lesson_slots($conn, $lesson, $date) {
    include_once __DIR__ . '/court_helper.php';
    $lessonId = (int)$lesson['lesson_id'];
    $slotLen = max(30, (int)$lesson['duration_minutes']);
    $weekday = (int)date('w', strtotime($date));
    $rules = pb_lesson_rules($conn, $lessonId);
    $windows = array();
    foreach ($rules as $rule) {
        if ((int)$rule['weekday'] !== $weekday) {
            continue;
        }
        $windows[] = array(
            'start' => pb_time_to_minutes($rule['start_time']),
            'end' => pb_time_to_minutes($rule['end_time']),
        );
    }
    if (empty($windows)) {
        return array();
    }

    $busy = array();
    $safeDate = $conn->real_escape_string($date);
    $bookSql = $conn->query(
        "SELECT start_datetime, end_datetime FROM bookings
         WHERE lesson_id=$lessonId
           AND booking_type='lesson'
           AND booking_status IN ('pending','confirmed')
           AND DATE(start_datetime)='$safeDate'"
    );
    if ($bookSql) {
        while ($b = $bookSql->fetch_assoc()) {
            $busy[] = array(
                pb_time_to_minutes(date('H:i:s', strtotime($b['start_datetime']))),
                pb_time_to_minutes(date('H:i:s', strtotime($b['end_datetime']))),
            );
        }
    }

    $capacity = max(1, (int)$lesson['capacity']);
    $slots = array();
    $nowTs = time();
    $isToday = ($date === date('Y-m-d'));
    $price = (float)$lesson['price'];

    foreach ($windows as $win) {
        for ($t = $win['start']; $t + $slotLen <= $win['end']; $t += $slotLen) {
            $slotEnd = $t + $slotLen;
            $countBusy = 0;
            foreach ($busy as $br) {
                if ($t < $br[1] && $slotEnd > $br[0]) {
                    $countBusy++;
                }
            }
            if ($countBusy >= $capacity) {
                continue;
            }
            $startStr = pb_minutes_to_time($t);
            $endStr = pb_minutes_to_time($slotEnd);
            $startDt = $date . ' ' . $startStr;
            if ($isToday && strtotime($startDt) <= $nowTs) {
                continue;
            }
            $slots[] = array(
                'start' => $startDt,
                'end' => $date . ' ' . $endStr,
                'start_time' => substr($startStr, 0, 5),
                'end_time' => substr($endStr, 0, 5),
                'price' => $price,
                'seats_left' => $capacity - $countBusy,
                'label' => substr($startStr, 0, 5) . ' – ' . substr($endStr, 0, 5),
            );
        }
    }
    return $slots;
}

function pb_lesson_booking_create($conn, $payload) {
    include_once __DIR__ . '/booking_helper.php';
    include_once __DIR__ . '/realtime_helper.php';
    include_once __DIR__ . '/order_helper.php';

    $lessonId = (int)$payload['lesson_id'];
    $lesson = pb_lesson_fetch($conn, $lessonId);
    if (!$lesson || (int)$lesson['lesson_status'] !== 1 || (int)$lesson['business_status'] !== 1) {
        return array('ok' => false, 'msg' => 'Lesson not available.');
    }
    $start = $payload['start_datetime'];
    $end = $payload['end_datetime'];
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs === false || $endTs === false || $endTs <= $startTs || $startTs <= time()) {
        return array('ok' => false, 'msg' => 'Invalid lesson time.');
    }
    $date = date('Y-m-d', $startTs);
    $slots = pb_generate_lesson_slots($conn, $lesson, $date);
    $matched = null;
    foreach ($slots as $slot) {
        if (strtotime($slot['start']) === $startTs && strtotime($slot['end']) === $endTs) {
            $matched = $slot;
            break;
        }
    }
    if (!$matched) {
        return array('ok' => false, 'msg' => 'That lesson slot is full or unavailable.');
    }

    include_once __DIR__ . '/court_helper.php';
    $settings = pb_booking_settings($conn, (int)$lesson['business_id']);
    $status = $settings['auto_confirm'] ? 'confirmed' : 'pending';
    $paymentType = isset($payload['payment_type']) ? $payload['payment_type'] : 'pay_at_venue';
    $paymentStatus = ($paymentType === 'online') ? 'pending' : 'unpaid';
    if ($paymentType === 'online') {
        $status = 'pending';
    }

    $receiptNo = fh_order_allocate_receipt_no($conn, (int)$lesson['business_id']);
    if ($receiptNo === '') {
        $receiptNo = 'LS-' . date('YmdHis');
    }

    $safe = function ($v) use ($conn) {
        return $conn->real_escape_string(trim((string)$v));
    };
    $businessId = (int)$lesson['business_id'];
    $coachId = (int)$lesson['coach_id'];
    $customerId = (int)$payload['customers_id'];
    $price = (float)$matched['price'];

    $ok = $conn->query(
        "INSERT INTO bookings
         (booking_type, court_id, lesson_id, coach_id, business_id, customers_id, booking_receipt_no,
          customer_fname, customer_lname, customer_email, customer_contact,
          start_datetime, end_datetime, booking_status, total_price, payment_type, payment_status, booking_remarks, booking_created)
         VALUES
         ('lesson', 0, $lessonId, $coachId, $businessId, $customerId, '" . $safe($receiptNo) . "',
          '" . $safe($payload['customer_fname']) . "', '" . $safe($payload['customer_lname']) . "',
          '" . $safe($payload['customer_email']) . "', '" . $safe($payload['customer_contact']) . "',
          '" . $safe(date('Y-m-d H:i:s', $startTs)) . "', '" . $safe(date('Y-m-d H:i:s', $endTs)) . "',
          '" . $safe($status) . "', $price, '" . $safe($paymentType) . "', '" . $safe($paymentStatus) . "',
          '" . $safe(isset($payload['booking_remarks']) ? $payload['booking_remarks'] : '') . "', NOW())"
    );
    if (!$ok) {
        return array('ok' => false, 'msg' => 'Could not save lesson booking.');
    }
    $bookingId = (int)$conn->insert_id;
    pb_realtime_publish('booking_updated', array(
        'business_id' => $businessId,
        'lesson_id' => $lessonId,
        'booking_id' => $bookingId,
        'event' => 'created',
    ));
    include_once __DIR__ . '/email_helper.php';
    $mailBooking = pb_booking_fetch($conn, $bookingId);
    if ($mailBooking) {
        $mailBooking['lesson_title'] = $lesson['lesson_title'];
        @pb_email_booking_created($mailBooking);
    }
    return array(
        'ok' => true,
        'booking_id' => $bookingId,
        'status' => $status,
        'receipt_no' => $receiptNo,
        'total_price' => $price,
        'business_id' => $businessId,
        'msg' => 'Lesson booked.',
    );
}
