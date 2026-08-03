<?php

function fh_dashboard_business_scope($businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return array('join' => '', 'where' => '', 'bizFilter' => '');
    }
    return array(
        'join' => ' INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id INNER JOIN items i ON od.orders_details_menu_id = i.id ',
        'where' => " AND i.business_id = $businessId ",
        'bizFilter' => " AND (o.orders_business_id = $businessId OR o.orders_business_id = 0) ",
        'bizId' => $businessId
    );
}

function fh_dashboard_get_stats($conn, $businessId = 0) {
    $businessId = (int)$businessId;
    $stats = array(
        'revenue_today' => 0,
        'revenue_week' => 0,
        'revenue_month' => 0,
        'revenue_total' => 0,
        'orders_today' => 0,
        'orders_week' => 0,
        'orders_pending' => 0,
        'orders_total' => 0,
        'items_count' => 0,
        'menu_today' => 0,
        'customers_count' => 0,
        'partners_active' => 0,
        'partners_pending' => 0,
        'users_count' => 0,
        'cms_published' => 0,
        'sales_chart' => array('labels' => array(), 'revenue' => array(), 'orders' => array()),
        'status_chart' => array('labels' => array(), 'values' => array()),
        'top_items' => array(),
        'business_chart' => array('labels' => array(), 'revenue' => array(), 'orders' => array()),
        'recent_orders' => array()
    );

    if ($businessId > 0) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM items WHERE business_id = $businessId");
        if ($r) { $stats['items_count'] = (int)$r->fetch_assoc()['c']; }
        $r = $conn->query("SELECT COUNT(*) AS c FROM menu_listing WHERE menu_business_id = $businessId AND menu_date = CURDATE()");
        if ($r) { $stats['menu_today'] = (int)$r->fetch_assoc()['c']; }
        $r = $conn->query("SELECT page_published FROM business_page WHERE business_id = $businessId LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) { $stats['cms_published'] = (int)$row['page_published']; }

        $baseFrom = "FROM orders o
            INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id
            INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId
            WHERE o.orders_status <> 'Cancelled'";

        $r = $conn->query("SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS rev, COUNT(DISTINCT o.orders_id) AS cnt $baseFrom AND DATE(o.orders_transdate) = CURDATE()");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_today'] = (float)$row['rev'];
            $stats['orders_today'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS rev, COUNT(DISTINCT o.orders_id) AS cnt $baseFrom AND o.orders_transdate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_week'] = (float)$row['rev'];
            $stats['orders_week'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS rev $baseFrom AND MONTH(o.orders_transdate) = MONTH(CURDATE()) AND YEAR(o.orders_transdate) = YEAR(CURDATE())");
        if ($r && $row = $r->fetch_assoc()) { $stats['revenue_month'] = (float)$row['rev']; }
        $r = $conn->query("SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS rev, COUNT(DISTINCT o.orders_id) AS cnt $baseFrom");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_total'] = (float)$row['rev'];
            $stats['orders_total'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COUNT(DISTINCT o.orders_id) AS c FROM orders o INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId WHERE o.orders_status = 'Pending'");
        if ($r) { $stats['orders_pending'] = (int)$r->fetch_assoc()['c']; }

        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $stats['sales_chart']['labels'][] = date('D', strtotime($d));
            $r = $conn->query("SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS rev, COUNT(DISTINCT o.orders_id) AS cnt $baseFrom AND DATE(o.orders_transdate) = '$d'");
            $rev = 0; $cnt = 0;
            if ($r && $row = $r->fetch_assoc()) { $rev = (float)$row['rev']; $cnt = (int)$row['cnt']; }
            $stats['sales_chart']['revenue'][] = $rev;
            $stats['sales_chart']['orders'][] = $cnt;
        }

        $r = $conn->query("SELECT o.orders_status, COUNT(DISTINCT o.orders_id) AS c FROM orders o INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId GROUP BY o.orders_status ORDER BY c DESC");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['status_chart']['labels'][] = $row['orders_status'];
                $stats['status_chart']['values'][] = (int)$row['c'];
            }
        }

        $r = $conn->query("SELECT i.name, SUM(od.orders_details_qty) AS qty, SUM(od.orders_details_qty * i.price) AS rev FROM orders_details od INNER JOIN items i ON od.orders_details_menu_id = i.id INNER JOIN orders o ON od.orders_details_orders_id = o.orders_id WHERE i.business_id = $businessId AND o.orders_status <> 'Cancelled' GROUP BY i.id ORDER BY qty DESC LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['top_items'][] = array('name' => $row['name'], 'qty' => (float)$row['qty'], 'revenue' => (float)$row['rev']);
            }
        }

        $r = $conn->query("SELECT o.orders_id, o.orders_transdate, o.orders_status, o.orders_customers_fname, o.orders_customers_lname, SUM(od.orders_details_qty * i.price) AS total FROM orders o INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId GROUP BY o.orders_id ORDER BY o.orders_transdate DESC LIMIT 8");
        if ($r) {
            while ($row = $r->fetch_assoc()) { $stats['recent_orders'][] = $row; }
        }
    } else {
        $r = $conn->query("SELECT COUNT(*) AS c FROM business_account WHERE business_status = 1 AND business_id > 1");
        if ($r) { $stats['partners_active'] = (int)$r->fetch_assoc()['c']; }
        $stats['partners_pending'] = fh_count_pending_businesses($conn);
        $r = $conn->query("SELECT COUNT(*) AS c FROM customers");
        if ($r) { $stats['customers_count'] = (int)$r->fetch_assoc()['c']; }
        $r = $conn->query("SELECT COUNT(*) AS c FROM user WHERE user_status = 1");
        if ($r) { $stats['users_count'] = (int)$r->fetch_assoc()['c']; }
        $r = $conn->query("SELECT COUNT(*) AS c FROM items");
        if ($r) { $stats['items_count'] = (int)$r->fetch_assoc()['c']; }

        $r = $conn->query("SELECT COALESCE(SUM(orders_total_amount), 0) AS rev, COUNT(*) AS cnt FROM orders WHERE orders_status <> 'Cancelled' AND DATE(orders_transdate) = CURDATE()");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_today'] = (float)$row['rev'];
            $stats['orders_today'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COALESCE(SUM(orders_total_amount), 0) AS rev, COUNT(*) AS cnt FROM orders WHERE orders_status <> 'Cancelled' AND orders_transdate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_week'] = (float)$row['rev'];
            $stats['orders_week'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COALESCE(SUM(orders_total_amount), 0) AS rev FROM orders WHERE orders_status <> 'Cancelled' AND MONTH(orders_transdate) = MONTH(CURDATE()) AND YEAR(orders_transdate) = YEAR(CURDATE())");
        if ($r && $row = $r->fetch_assoc()) { $stats['revenue_month'] = (float)$row['rev']; }
        $r = $conn->query("SELECT COALESCE(SUM(orders_total_amount), 0) AS rev, COUNT(*) AS cnt FROM orders WHERE orders_status <> 'Cancelled'");
        if ($r && $row = $r->fetch_assoc()) {
            $stats['revenue_total'] = (float)$row['rev'];
            $stats['orders_total'] = (int)$row['cnt'];
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE orders_status = 'Pending'");
        if ($r) { $stats['orders_pending'] = (int)$r->fetch_assoc()['c']; }

        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $stats['sales_chart']['labels'][] = date('D', strtotime($d));
            $r = $conn->query("SELECT COALESCE(SUM(orders_total_amount), 0) AS rev, COUNT(*) AS cnt FROM orders WHERE orders_status <> 'Cancelled' AND DATE(orders_transdate) = '$d'");
            $rev = 0; $cnt = 0;
            if ($r && $row = $r->fetch_assoc()) { $rev = (float)$row['rev']; $cnt = (int)$row['cnt']; }
            $stats['sales_chart']['revenue'][] = $rev;
            $stats['sales_chart']['orders'][] = $cnt;
        }

        $r = $conn->query("SELECT orders_status, COUNT(*) AS c FROM orders GROUP BY orders_status ORDER BY c DESC");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['status_chart']['labels'][] = $row['orders_status'];
                $stats['status_chart']['values'][] = (int)$row['c'];
            }
        }

        $r = $conn->query("SELECT i.name, SUM(od.orders_details_qty) AS qty, SUM(od.orders_details_qty * i.price) AS rev FROM orders_details od INNER JOIN items i ON od.orders_details_menu_id = i.id INNER JOIN orders o ON od.orders_details_orders_id = o.orders_id WHERE o.orders_status <> 'Cancelled' GROUP BY i.id ORDER BY qty DESC LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['top_items'][] = array('name' => $row['name'], 'qty' => (float)$row['qty'], 'revenue' => (float)$row['rev']);
            }
        }

        $r = $conn->query("SELECT ba.business_name, COUNT(o.orders_id) AS cnt, COALESCE(SUM(o.orders_total_amount), 0) AS rev FROM business_account ba LEFT JOIN orders o ON ba.business_id = o.orders_business_id AND o.orders_status <> 'Cancelled' WHERE ba.business_id > 1 AND ba.business_status = 1 GROUP BY ba.business_id ORDER BY rev DESC LIMIT 8");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stats['business_chart']['labels'][] = $row['business_name'];
                $stats['business_chart']['orders'][] = (int)$row['cnt'];
                $stats['business_chart']['revenue'][] = (float)$row['rev'];
            }
        }

        $r = $conn->query("SELECT o.orders_id, o.orders_transdate, o.orders_status, o.orders_customers_fname, o.orders_customers_lname, o.orders_total_amount AS total, ba.business_name FROM orders o LEFT JOIN business_account ba ON o.orders_business_id = ba.business_id ORDER BY o.orders_transdate DESC LIMIT 8");
        if ($r) {
            while ($row = $r->fetch_assoc()) { $stats['recent_orders'][] = $row; }
        }
    }

    return $stats;
}

function fh_dashboard_money($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function fh_dashboard_status_class($status) {
    $map = array(
        'Pending' => 'dash-badge-pending',
        'Confirmed' => 'dash-badge-confirmed',
        'Delivered' => 'dash-badge-delivered',
        'Paid' => 'dash-badge-paid',
        'Cancelled' => 'dash-badge-cancelled',
        'Processing' => 'dash-badge-confirmed'
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

function fh_dashboard_get_map_locations($conn, $businessId = 0, $businessRow = null) {
    $businessId = (int)$businessId;
    $locations = array(
        'business' => null,
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
        if ($bizAddress !== '') {
            $locations['business'] = array(
                'id' => $businessId,
                'name' => isset($businessRow['business_name']) ? $businessRow['business_name'] : 'Restaurant',
                'address' => $bizAddress,
                'type' => 'business',
            );
        }

        $sql = "SELECT o.orders_customers_id, o.orders_customers_fname, o.orders_customers_lname,
                       o.orders_customers_contact, o.orders_customers_email, o.orders_delivery_address,
                       o.orders_transdate
                FROM orders o
                INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id
                INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId
                WHERE o.orders_status <> 'Cancelled'
                  AND TRIM(o.orders_delivery_address) <> ''
                ORDER BY o.orders_transdate DESC
                LIMIT 300";
        $seen = array();
        $r = $conn->query($sql);
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $cid = (int)$row['orders_customers_id'];
                if (isset($seen[$cid])) {
                    continue;
                }
                $seen[$cid] = true;
                $name = trim($row['orders_customers_fname'] . ' ' . $row['orders_customers_lname']);
                $locations['customers'][] = array(
                    'id' => $cid,
                    'name' => $name !== '' ? $name : 'Customer #' . $cid,
                    'address' => trim($row['orders_delivery_address']),
                    'phone' => trim((string)$row['orders_customers_contact']),
                    'email' => trim((string)$row['orders_customers_email']),
                    'type' => 'customer',
                );
                if (count($locations['customers']) >= 80) {
                    break;
                }
            }
        }

        $profileSql = $conn->query(
            "SELECT c.customers_id, c.customers_firstname, c.customers_lastname,
                    c.customers_phone, c.customers_email, c.customers_complete_address
             FROM customers c
             WHERE TRIM(c.customers_complete_address) <> ''
               AND EXISTS (
                   SELECT 1 FROM orders o
                   INNER JOIN orders_details od ON o.orders_id = od.orders_details_orders_id
                   INNER JOIN items i ON od.orders_details_menu_id = i.id AND i.business_id = $businessId
                   WHERE o.orders_customers_id = c.customers_id
               )
             ORDER BY c.customers_lastname, c.customers_firstname
             LIMIT 120"
        );
        if ($profileSql) {
            while ($row = $profileSql->fetch_assoc()) {
                $cid = (int)$row['customers_id'];
                if (isset($seen[$cid])) {
                    continue;
                }
                $seen[$cid] = true;
                $name = trim($row['customers_firstname'] . ' ' . $row['customers_lastname']);
                $locations['customers'][] = array(
                    'id' => $cid,
                    'name' => $name !== '' ? $name : 'Customer #' . $cid,
                    'address' => trim($row['customers_complete_address']),
                    'phone' => trim((string)$row['customers_phone']),
                    'email' => trim((string)$row['customers_email']),
                    'type' => 'customer',
                );
                if (count($locations['customers']) >= 80) {
                    break;
                }
            }
        }
    } else {
        $r = $conn->query(
            "SELECT customers_id, customers_firstname, customers_lastname,
                    customers_phone, customers_email, customers_complete_address
             FROM customers
             WHERE TRIM(customers_complete_address) <> ''
             ORDER BY customers_lastname, customers_firstname
             LIMIT 100"
        );
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $name = trim($row['customers_firstname'] . ' ' . $row['customers_lastname']);
                $locations['customers'][] = array(
                    'id' => (int)$row['customers_id'],
                    'name' => $name !== '' ? $name : 'Customer #' . (int)$row['customers_id'],
                    'address' => trim($row['customers_complete_address']),
                    'phone' => trim((string)$row['customers_phone']),
                    'email' => trim((string)$row['customers_email']),
                    'type' => 'customer',
                );
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
            'header' => "User-Agent: FoodParkPortal/1.0\r\nAccept: application/json\r\n",
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
