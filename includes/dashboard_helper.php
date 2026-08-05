<?php

/**
 * Pickleball portal / venue dashboard analytics.
 */

function fh_dashboard_biz_clause($businessId, $alias = 'b') {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return '';
    }
    return " AND {$alias}.business_id = {$businessId} ";
}

function fh_dashboard_table_exists($conn, $table) {
    static $cache = array();
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    if ($table === '') {
        return false;
    }
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $r = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $cache[$table] = ($r && $r->num_rows > 0);
    return $cache[$table];
}

function fh_dashboard_get_stats($conn, $businessId = 0) {
    $businessId = (int)$businessId;
    $biz = fh_dashboard_biz_clause($businessId, 'b');
    $bizCourt = fh_dashboard_biz_clause($businessId, 'c');
    $bizOnly = $businessId > 0 ? " AND business_id = $businessId " : '';

    $stats = array(
        'revenue_today' => 0,
        'revenue_week' => 0,
        'revenue_month' => 0,
        'revenue_total' => 0,
        'bookings_today' => 0,
        'bookings_week' => 0,
        'bookings_month' => 0,
        'bookings_pending' => 0,
        'bookings_upcoming' => 0,
        'bookings_total' => 0,
        'courts_active' => 0,
        'courts_total' => 0,
        'utilization_today' => 0,
        'coaches_active' => 0,
        'lessons_active' => 0,
        'memberships_active' => 0,
        'memberships_pending' => 0,
        'membership_mrr' => 0,
        'chats_open' => 0,
        'chats_unread' => 0,
        'payments_paid_month' => 0,
        'payments_pending' => 0,
        'review_avg' => 0,
        'review_count' => 0,
        'customers_count' => 0,
        'partners_active' => 0,
        'partners_pending' => 0,
        'users_count' => 0,
        'cms_published' => 0,
        'sales_chart' => array('labels' => array(), 'revenue' => array(), 'bookings' => array()),
        'status_chart' => array('labels' => array(), 'values' => array()),
        'type_chart' => array('labels' => array(), 'values' => array()),
        'top_courts' => array(),
        'business_chart' => array('labels' => array(), 'revenue' => array(), 'bookings' => array()),
        'recent_bookings' => array(),
        'today_schedule' => array(),
    );

    if (!fh_dashboard_table_exists($conn, 'bookings')) {
        return $stats;
    }

    $activeSql = "b.booking_status IN ('pending','confirmed','completed')";
    $revSql = "b.booking_status IN ('confirmed','completed','pending')";

    // —— Core booking revenue & counts ——
    $r = $conn->query("SELECT COALESCE(SUM(b.total_price),0) AS rev, COUNT(*) AS cnt
        FROM bookings b WHERE $revSql $biz AND DATE(b.start_datetime) = CURDATE()");
    if ($r && $row = $r->fetch_assoc()) {
        $stats['revenue_today'] = (float)$row['rev'];
        $stats['bookings_today'] = (int)$row['cnt'];
    }

    $r = $conn->query("SELECT COALESCE(SUM(b.total_price),0) AS rev, COUNT(*) AS cnt
        FROM bookings b WHERE $revSql $biz AND b.start_datetime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    if ($r && $row = $r->fetch_assoc()) {
        $stats['revenue_week'] = (float)$row['rev'];
        $stats['bookings_week'] = (int)$row['cnt'];
    }

    $r = $conn->query("SELECT COALESCE(SUM(b.total_price),0) AS rev, COUNT(*) AS cnt
        FROM bookings b WHERE $revSql $biz
          AND MONTH(b.start_datetime) = MONTH(CURDATE()) AND YEAR(b.start_datetime) = YEAR(CURDATE())");
    if ($r && $row = $r->fetch_assoc()) {
        $stats['revenue_month'] = (float)$row['rev'];
        $stats['bookings_month'] = (int)$row['cnt'];
    }

    $r = $conn->query("SELECT COALESCE(SUM(b.total_price),0) AS rev, COUNT(*) AS cnt
        FROM bookings b WHERE $revSql $biz");
    if ($r && $row = $r->fetch_assoc()) {
        $stats['revenue_total'] = (float)$row['rev'];
        $stats['bookings_total'] = (int)$row['cnt'];
    }

    $r = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE b.booking_status = 'pending' $biz");
    if ($r) {
        $stats['bookings_pending'] = (int)$r->fetch_assoc()['c'];
    }

    $r = $conn->query("SELECT COUNT(*) AS c FROM bookings b
        WHERE b.booking_status IN ('pending','confirmed') $biz AND b.start_datetime >= NOW()");
    if ($r) {
        $stats['bookings_upcoming'] = (int)$r->fetch_assoc()['c'];
    }

    // —— Courts & utilization ——
    if (fh_dashboard_table_exists($conn, 'courts')) {
        $r = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN court_status = 1 THEN 1 ELSE 0 END) AS active
            FROM courts c WHERE 1=1 $bizCourt");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['courts_total'] = (int)$row['total'];
            $stats['courts_active'] = (int)$row['active'];
        }

        // Rough utilization: booked court-hours today / (active courts × 8h day)
        $bookedHours = 0;
        $r = $conn->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, b.start_datetime, b.end_datetime)),0) AS mins
            FROM bookings b WHERE $activeSql $biz AND DATE(b.start_datetime) = CURDATE()");
        if ($r && $row = $r->fetch_assoc()) {
            $bookedHours = ((int)$row['mins']) / 60.0;
        }
        $capacityHours = max(1, $stats['courts_active']) * 8.0;
        $stats['utilization_today'] = (int)min(100, round(($bookedHours / $capacityHours) * 100));
    }

    // —— Coaches / lessons ——
    if (fh_dashboard_table_exists($conn, 'coaches')) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM coaches WHERE coach_status = 1 $bizOnly");
        if ($r) {
            $stats['coaches_active'] = (int)$r->fetch_assoc()['c'];
        }
    }
    if (fh_dashboard_table_exists($conn, 'lessons')) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM lessons WHERE lesson_status = 1 $bizOnly");
        if ($r) {
            $stats['lessons_active'] = (int)$r->fetch_assoc()['c'];
        }
    }

    // —— Memberships ——
    if (fh_dashboard_table_exists($conn, 'memberships')) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM memberships WHERE membership_status = 'active' $bizOnly");
        if ($r) {
            $stats['memberships_active'] = (int)$r->fetch_assoc()['c'];
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM memberships WHERE membership_status = 'pending' $bizOnly");
        if ($r) {
            $stats['memberships_pending'] = (int)$r->fetch_assoc()['c'];
        }
        if (fh_dashboard_table_exists($conn, 'membership_plans')) {
            $r = $conn->query("SELECT COALESCE(SUM(
                    CASE WHEN mp.billing_period = 'yearly' THEN mp.price / 12 ELSE mp.price END
                ), 0) AS mrr
                FROM memberships m
                INNER JOIN membership_plans mp ON m.plan_id = mp.plan_id
                WHERE m.membership_status = 'active' " . ($businessId > 0 ? " AND m.business_id = $businessId " : ''));
            if ($r && $row = $r->fetch_assoc()) {
                $stats['membership_mrr'] = (float)$row['mrr'];
            }
        }
    }

    // —— Chat ——
    if (fh_dashboard_table_exists($conn, 'chat_threads')) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM chat_threads WHERE thread_status = 'open' $bizOnly");
        if ($r) {
            $stats['chats_open'] = (int)$r->fetch_assoc()['c'];
        }
        if (fh_dashboard_table_exists($conn, 'chat_messages')) {
            $r = $conn->query("SELECT COUNT(*) AS c FROM chat_messages cm
                INNER JOIN chat_threads ct ON cm.thread_id = ct.thread_id
                WHERE cm.read_at IS NULL AND cm.sender_role = 'customer'
                  AND ct.thread_status = 'open' " . ($businessId > 0 ? " AND ct.business_id = $businessId " : ''));
            if ($r) {
                $stats['chats_unread'] = (int)$r->fetch_assoc()['c'];
            }
        }
    }

    // —— Payments ——
    if (fh_dashboard_table_exists($conn, 'payments')) {
        $r = $conn->query("SELECT COALESCE(SUM(amount),0) AS amt FROM payments
            WHERE payment_status = 'paid' $bizOnly
              AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['payments_paid_month'] = (float)$row['amt'];
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE payment_status IN ('pending','unpaid') $bizOnly");
        if ($r) {
            $stats['payments_pending'] = (int)$r->fetch_assoc()['c'];
        }
    }

    // —— Reviews ——
    if (fh_dashboard_table_exists($conn, 'reviews')) {
        $r = $conn->query("SELECT COALESCE(AVG(rating),0) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE 1=1 $bizOnly");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['review_avg'] = round((float)$row['avg_r'], 1);
            $stats['review_count'] = (int)$row['cnt'];
        }
    }

    // —— CMS ——
    if ($businessId > 0 && fh_dashboard_table_exists($conn, 'business_page')) {
        $r = $conn->query("SELECT page_published FROM business_page WHERE business_id = $businessId LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['cms_published'] = (int)$row['page_published'];
        }
    }

    // —— Portal-only counts ——
    if ($businessId <= 0) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM business_account WHERE business_status = 1 AND business_id > 1");
        if ($r) {
            $stats['partners_active'] = (int)$r->fetch_assoc()['c'];
        }
        if (function_exists('fh_count_pending_businesses')) {
            $stats['partners_pending'] = fh_count_pending_businesses($conn);
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM customers");
        if ($r) {
            $stats['customers_count'] = (int)$r->fetch_assoc()['c'];
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM user WHERE user_status = 1");
        if ($r) {
            $stats['users_count'] = (int)$r->fetch_assoc()['c'];
        }
    }

    // —— 7-day sales chart ——
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $stats['sales_chart']['labels'][] = date('D', strtotime($d));
        $r = $conn->query("SELECT COALESCE(SUM(b.total_price),0) AS rev, COUNT(*) AS cnt
            FROM bookings b WHERE $revSql $biz AND DATE(b.start_datetime) = '$d'");
        $rev = 0;
        $cnt = 0;
        if ($r && $row = $r->fetch_assoc()) {
            $rev = (float)$row['rev'];
            $cnt = (int)$row['cnt'];
        }
        $stats['sales_chart']['revenue'][] = $rev;
        $stats['sales_chart']['bookings'][] = $cnt;
    }

    // —— Status chart ——
    $r = $conn->query("SELECT b.booking_status, COUNT(*) AS c FROM bookings b WHERE 1=1 $biz
        GROUP BY b.booking_status ORDER BY c DESC");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stats['status_chart']['labels'][] = ucfirst($row['booking_status']);
            $stats['status_chart']['values'][] = (int)$row['c'];
        }
    }

    // —— Booking type chart (court vs lesson) ——
    $r = $conn->query("SELECT COALESCE(NULLIF(b.booking_type,''),'court') AS btype, COUNT(*) AS c
        FROM bookings b WHERE $revSql $biz GROUP BY btype ORDER BY c DESC");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stats['type_chart']['labels'][] = ucfirst($row['btype']);
            $stats['type_chart']['values'][] = (int)$row['c'];
        }
    }

    // —— Top courts ——
    if (fh_dashboard_table_exists($conn, 'courts')) {
        $r = $conn->query("SELECT c.court_name AS name, COUNT(b.booking_id) AS qty,
                COALESCE(SUM(b.total_price),0) AS revenue
            FROM courts c
            LEFT JOIN bookings b ON b.court_id = c.court_id AND b.booking_status <> 'cancelled'
            WHERE 1=1 $bizCourt
            GROUP BY c.court_id
            ORDER BY qty DESC, revenue DESC
            LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['top_courts'][] = array(
                    'name' => $row['name'],
                    'qty' => (int)$row['qty'],
                    'revenue' => (float)$row['revenue'],
                );
            }
        }
    }

    // —— Revenue by venue (portal) ——
    if ($businessId <= 0) {
        $r = $conn->query("SELECT ba.business_name, COUNT(b.booking_id) AS cnt,
                COALESCE(SUM(CASE WHEN b.booking_status <> 'cancelled' THEN b.total_price ELSE 0 END),0) AS rev
            FROM business_account ba
            LEFT JOIN bookings b ON ba.business_id = b.business_id
            WHERE ba.business_id > 1 AND ba.business_status = 1
            GROUP BY ba.business_id
            ORDER BY rev DESC
            LIMIT 8");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['business_chart']['labels'][] = $row['business_name'];
                $stats['business_chart']['bookings'][] = (int)$row['cnt'];
                $stats['business_chart']['revenue'][] = (float)$row['rev'];
            }
        }
    }

    // —— Recent bookings ——
    $joinBiz = $businessId <= 0
        ? ' LEFT JOIN business_account ba ON b.business_id = ba.business_id '
        : '';
    $selectBiz = $businessId <= 0 ? ', ba.business_name ' : ', NULL AS business_name ';
    $r = $conn->query("SELECT b.booking_id, b.booking_receipt_no, b.start_datetime, b.end_datetime,
            b.booking_status, b.total_price, b.booking_type, b.payment_status, b.payment_type,
            b.customer_fname, b.customer_lname, c.court_name $selectBiz
        FROM bookings b
        LEFT JOIN courts c ON b.court_id = c.court_id
        $joinBiz
        WHERE 1=1 $biz
        ORDER BY b.booking_created DESC
        LIMIT 10");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stats['recent_bookings'][] = $row;
        }
    }

    // —— Today's schedule ——
    $r = $conn->query("SELECT b.booking_id, b.start_datetime, b.end_datetime, b.booking_status,
            b.customer_fname, b.customer_lname, b.booking_type, b.total_price,
            c.court_name
        FROM bookings b
        LEFT JOIN courts c ON b.court_id = c.court_id
        WHERE $activeSql $biz AND DATE(b.start_datetime) = CURDATE()
        ORDER BY b.start_datetime ASC
        LIMIT 12");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $stats['today_schedule'][] = $row;
        }
    }

    return $stats;
}

function fh_dashboard_money($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function fh_dashboard_status_class($status) {
    $status = strtolower(trim((string)$status));
    $map = array(
        'pending' => 'dash-badge-pending',
        'confirmed' => 'dash-badge-confirmed',
        'completed' => 'dash-badge-delivered',
        'cancelled' => 'dash-badge-cancelled',
        'paid' => 'dash-badge-paid',
        'unpaid' => 'dash-badge-pending',
        'failed' => 'dash-badge-cancelled',
    );
    return isset($map[$status]) ? $map[$status] : 'dash-badge-default';
}

function fh_dashboard_build_address($street, $city = '', $province = '', $country = '', $zip = '') {
    $parts = array();
    foreach (array($street, $city, $province, $country, $zip) as $part) {
        $part = trim((string)$part);
        if ($part !== '') {
            $parts[] = $part;
        }
    }
    return implode(', ', $parts);
}

/**
 * Map pins: venues with lat/lng (preferred) or address for geocoding.
 */
function fh_dashboard_get_map_locations($conn, $businessId = 0, $businessRow = null) {
    $businessId = (int)$businessId;
    $locations = array(
        'business' => null,
        'venues' => array(),
        'customers' => array(),
        'default_center' => array('lat' => 9.6729, 'lng' => 123.8733),
    );

    if ($businessId > 0 && is_array($businessRow)) {
        $bizAddress = fh_dashboard_build_address(
            isset($businessRow['business_address']) ? $businessRow['business_address'] : '',
            isset($businessRow['business_city']) ? $businessRow['business_city'] : '',
            isset($businessRow['business_province']) ? $businessRow['business_province'] : '',
            isset($businessRow['business_country']) ? $businessRow['business_country'] : '',
            isset($businessRow['business_zipcode']) ? $businessRow['business_zipcode'] : ''
        );
        $lat = isset($businessRow['business_lat']) ? $businessRow['business_lat'] : null;
        $lng = isset($businessRow['business_lng']) ? $businessRow['business_lng'] : null;
        $pin = array(
            'id' => $businessId,
            'name' => isset($businessRow['business_name']) ? $businessRow['business_name'] : 'Venue',
            'address' => $bizAddress,
            'type' => 'business',
        );
        if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            $pin['lat'] = (float)$lat;
            $pin['lng'] = (float)$lng;
            $locations['default_center'] = array('lat' => (float)$lat, 'lng' => (float)$lng);
        }
        if ($bizAddress !== '' || isset($pin['lat'])) {
            $locations['business'] = $pin;
            $locations['venues'][] = $pin;
        }
        return $locations;
    }

    // Portal overview: all active venues with coordinates or address
    $r = $conn->query(
        "SELECT business_id, business_name, business_address, business_city, business_province,
                business_country, business_zipcode, business_lat, business_lng, business_contact
         FROM business_account
         WHERE business_status = 1 AND business_id > 1
         ORDER BY business_name
         LIMIT 80"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $address = fh_dashboard_build_address(
                $row['business_address'],
                $row['business_city'],
                $row['business_province'],
                $row['business_country'],
                $row['business_zipcode']
            );
            $pin = array(
                'id' => (int)$row['business_id'],
                'name' => $row['business_name'],
                'address' => $address,
                'phone' => isset($row['business_contact']) ? trim((string)$row['business_contact']) : '',
                'type' => 'business',
            );
            if ($row['business_lat'] !== null && $row['business_lng'] !== null
                && $row['business_lat'] !== '' && $row['business_lng'] !== '') {
                $pin['lat'] = (float)$row['business_lat'];
                $pin['lng'] = (float)$row['business_lng'];
            }
            if ($address !== '' || isset($pin['lat'])) {
                $locations['venues'][] = $pin;
            }
        }
    }

    return $locations;
}

function fh_dashboard_geocode_address($address) {
    $address = trim((string)$address);
    if ($address === '') {
        return null;
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
        'format' => 'json',
        'q' => $address,
        'countrycodes' => 'ph',
        'limit' => 1,
    ));

    $context = stream_context_create(array(
        'http' => array(
            'header' => "User-Agent: PickleballPortal/1.0\r\nAccept: application/json\r\n",
            'timeout' => 12,
        ),
    ));

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
        return null;
    }

    return array(
        'lat' => (float)$data[0]['lat'],
        'lng' => (float)$data[0]['lon'],
    );
}
