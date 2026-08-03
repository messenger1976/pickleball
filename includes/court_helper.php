<?php

include_once __DIR__ . '/business_helper.php';
include_once __DIR__ . '/auth_helper.php';

function pb_court_types() {
    return array('indoor' => 'Indoor', 'outdoor' => 'Outdoor');
}

function pb_court_surfaces() {
    return array(
        'hard' => 'Hard',
        'concrete' => 'Concrete',
        'asphalt' => 'Asphalt',
        'wood' => 'Wood',
        'sport_court' => 'Sport Court',
        'other' => 'Other',
    );
}

function pb_weekday_labels() {
    return array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    );
}

function pb_court_fetch($conn, $courtId) {
    $courtId = (int)$courtId;
    if ($courtId <= 0) {
        return null;
    }
    $sql = $conn->query(
        "SELECT c.*, ba.business_name, ba.business_subdomain, ba.business_status
         FROM courts c
         INNER JOIN business_account ba ON ba.business_id = c.business_id
         WHERE c.court_id = $courtId
         LIMIT 1"
    );
    if ($sql && ($row = $sql->fetch_assoc())) {
        return $row;
    }
    return null;
}

function pb_courts_for_business($conn, $businessId, $activeOnly = true) {
    $businessId = (int)$businessId;
    $list = array();
    $where = "WHERE business_id = $businessId";
    if ($activeOnly) {
        $where .= ' AND court_status = 1';
    }
    $sql = $conn->query("SELECT * FROM courts $where ORDER BY court_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_court_photo_url($photo) {
    $photo = trim((string)$photo);
    if ($photo === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $photo)) {
        return $photo;
    }
    if (strpos($photo, 'admin/') === 0 || strpos($photo, 'images/') === 0) {
        return $photo;
    }
    return 'admin/' . ltrim($photo, '/');
}

function pb_booking_settings($conn, $businessId) {
    $page = fh_get_business_page($conn, $businessId);
    $maxDays = 14;
    $autoConfirm = 1;
    $cancelNote = '';
    $amenities = '';
    if ($page) {
        if (isset($page['booking_max_days']) && (int)$page['booking_max_days'] > 0) {
            $maxDays = min(90, (int)$page['booking_max_days']);
        } elseif (isset($page['preorder_max_days']) && (int)$page['preorder_max_days'] > 0) {
            $maxDays = min(90, (int)$page['preorder_max_days']);
        }
        if (isset($page['auto_confirm'])) {
            $autoConfirm = (int)$page['auto_confirm'] ? 1 : 0;
        }
        $cancelNote = isset($page['cancellation_note']) ? (string)$page['cancellation_note'] : '';
        $amenities = isset($page['page_amenities']) ? (string)$page['page_amenities'] : '';
    }
    return array(
        'auto_confirm' => $autoConfirm,
        'booking_max_days' => $maxDays,
        'cancellation_note' => $cancelNote,
        'amenities' => $amenities,
    );
}

function pb_clamp_booking_date($dateStr, $maxDays = 14) {
    $maxDays = max(1, min(90, (int)$maxDays));
    $today = date('Y-m-d');
    $max = date('Y-m-d', strtotime('+' . $maxDays . ' days'));
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return $today;
    }
    $d = date('Y-m-d', $ts);
    if ($d < $today) {
        return $today;
    }
    if ($d > $max) {
        return $max;
    }
    return $d;
}

function pb_rules_for_court($conn, $courtId) {
    $courtId = (int)$courtId;
    $list = array();
    $sql = $conn->query(
        "SELECT * FROM court_availability_rules
         WHERE court_id = $courtId
         ORDER BY weekday ASC, start_time ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_exceptions_for_court_date($conn, $courtId, $date) {
    $courtId = (int)$courtId;
    $safeDate = $conn->real_escape_string($date);
    $list = array();
    $sql = $conn->query(
        "SELECT * FROM availability_exceptions
         WHERE court_id = $courtId AND exception_date = '$safeDate'
         ORDER BY is_closed DESC, start_time ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_time_to_minutes($time) {
    $parts = explode(':', (string)$time);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;
    return ($h * 60) + $m;
}

function pb_minutes_to_time($minutes) {
    $minutes = (int)$minutes;
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d:00', $h, $m);
}

/**
 * Generate bookable slots for a court on a calendar date.
 * Returns array of [start, end, price, label].
 */
function pb_generate_slots($conn, $court, $date) {
    $courtId = (int)$court['court_id'];
    $slotLen = max(15, (int)$court['slot_length_minutes']);
    $buffer = max(0, (int)$court['buffer_minutes']);
    $basePrice = (float)$court['price_per_slot'];
    $weekday = (int)date('w', strtotime($date));

    $exceptions = pb_exceptions_for_court_date($conn, $courtId, $date);
    foreach ($exceptions as $ex) {
        if ((int)$ex['is_closed'] === 1 && empty($ex['start_time']) && empty($ex['end_time'])) {
            return array();
        }
    }

    $windows = array();
    $dayClosedPartial = false;
    foreach ($exceptions as $ex) {
        if ((int)$ex['is_closed'] === 1 && !empty($ex['start_time']) && !empty($ex['end_time'])) {
            $dayClosedPartial = true;
            continue;
        }
        if ((int)$ex['is_closed'] === 0 && !empty($ex['start_time']) && !empty($ex['end_time'])) {
            $price = $ex['override_price'] !== null && $ex['override_price'] !== ''
                ? (float)$ex['override_price']
                : $basePrice;
            $windows[] = array(
                'start' => pb_time_to_minutes($ex['start_time']),
                'end' => pb_time_to_minutes($ex['end_time']),
                'price' => $price,
            );
        }
    }

    if (empty($windows)) {
        $rules = pb_rules_for_court($conn, $courtId);
        foreach ($rules as $rule) {
            if ((int)$rule['weekday'] !== $weekday) {
                continue;
            }
            $price = $rule['price_override'] !== null && $rule['price_override'] !== ''
                ? (float)$rule['price_override']
                : $basePrice;
            $windows[] = array(
                'start' => pb_time_to_minutes($rule['start_time']),
                'end' => pb_time_to_minutes($rule['end_time']),
                'price' => $price,
            );
        }
    }

    if (empty($windows)) {
        return array();
    }

    $closedRanges = array();
    foreach ($exceptions as $ex) {
        if ((int)$ex['is_closed'] === 1 && !empty($ex['start_time']) && !empty($ex['end_time'])) {
            $closedRanges[] = array(
                pb_time_to_minutes($ex['start_time']),
                pb_time_to_minutes($ex['end_time']),
            );
        }
    }

    $busy = array();
    $safeDate = $conn->real_escape_string($date);
    $bookSql = $conn->query(
        "SELECT start_datetime, end_datetime
         FROM bookings
         WHERE court_id = $courtId
           AND booking_status IN ('pending','confirmed')
           AND DATE(start_datetime) = '$safeDate'"
    );
    if ($bookSql) {
        while ($b = $bookSql->fetch_assoc()) {
            $startMin = pb_time_to_minutes(date('H:i:s', strtotime($b['start_datetime'])));
            $endMin = pb_time_to_minutes(date('H:i:s', strtotime($b['end_datetime'])));
            $busy[] = array(
                max(0, $startMin - $buffer),
                $endMin + $buffer,
            );
        }
    }

    $slots = array();
    $nowTs = time();
    $isToday = ($date === date('Y-m-d'));

    foreach ($windows as $win) {
        for ($t = $win['start']; $t + $slotLen <= $win['end']; $t += $slotLen) {
            $slotEnd = $t + $slotLen;
            $blocked = false;
            foreach ($closedRanges as $cr) {
                if ($t < $cr[1] && $slotEnd > $cr[0]) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }
            foreach ($busy as $br) {
                if ($t < $br[1] && $slotEnd > $br[0]) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
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
                'price' => $win['price'],
                'label' => substr($startStr, 0, 5) . ' – ' . substr($endStr, 0, 5),
            );
        }
    }

    return $slots;
}

function pb_seed_default_week_rules($conn, $courtId, $open = '06:00:00', $close = '22:00:00') {
    $courtId = (int)$courtId;
    $existing = $conn->query("SELECT rule_id FROM court_availability_rules WHERE court_id = $courtId LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        return;
    }
    $open = $conn->real_escape_string($open);
    $close = $conn->real_escape_string($close);
    for ($d = 0; $d <= 6; $d++) {
        $conn->query(
            "INSERT INTO court_availability_rules (court_id, weekday, start_time, end_time)
             VALUES ($courtId, $d, '$open', '$close')"
        );
    }
}
