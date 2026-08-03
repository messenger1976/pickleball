<?php

function fh_order_is_pending($status) {
    return strtolower(trim((string)$status)) === 'pending';
}

function fh_order_is_cancelled($status) {
    $s = strtolower(trim((string)$status));
    return $s === 'cancelled' || $s === 'canceled';
}

function fh_order_is_terminal($status) {
    $s = strtolower(trim((string)$status));
    return $s === 'delivered' || $s === 'cancelled' || $s === 'canceled';
}

/**
 * Build a short store code from the business name (e.g. "Eulie's Food Hauz" -> EFH).
 */
function fh_business_derive_receipt_prefix($businessName) {
    $businessName = trim((string)$businessName);
    if ($businessName === '') {
        return 'ORD';
    }
    $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\'\s]/', ' ', $businessName));
    $prefix = '';
    foreach ($words as $word) {
        $word = trim($word, "'\"");
        if ($word === '') {
            continue;
        }
        $prefix .= strtoupper($word[0]);
        if (strlen($prefix) >= 5) {
            break;
        }
    }
    if (strlen($prefix) < 2) {
        $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $businessName));
        $prefix = $clean !== '' ? $clean : 'ORD';
    }
    return substr($prefix, 0, 10);
}

function fh_business_receipt_prefix($conn, $businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return 'ORD';
    }
    $sql = $conn->query(
        "SELECT business_name, business_receipt_prefix
         FROM business_account
         WHERE business_id = $businessId
         LIMIT 1"
    );
    if (!$sql || !($row = $sql->fetch_assoc())) {
        return 'ORD';
    }
    $prefix = strtoupper(trim((string)$row['business_receipt_prefix']));
    if ($prefix !== '') {
        return substr($prefix, 0, 10);
    }
    return fh_business_derive_receipt_prefix($row['business_name']);
}

/**
 * Reserve the next per-store receipt number (e.g. EFH-00000001).
 */
function fh_order_allocate_receipt_no($conn, $businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return '';
    }

    $conn->begin_transaction();
    try {
        $sql = $conn->query(
            "SELECT business_name, business_receipt_prefix, business_receipt_next
             FROM business_account
             WHERE business_id = $businessId
             FOR UPDATE"
        );
        if (!$sql || !($row = $sql->fetch_assoc())) {
            $conn->rollback();
            return '';
        }

        $prefix = strtoupper(trim((string)$row['business_receipt_prefix']));
        if ($prefix === '') {
            $prefix = fh_business_derive_receipt_prefix($row['business_name']);
            $safePrefix = $conn->real_escape_string($prefix);
            $conn->query(
                "UPDATE business_account
                 SET business_receipt_prefix = '$safePrefix'
                 WHERE business_id = $businessId"
            );
        }

        $seq = (int)$row['business_receipt_next'];
        if ($seq < 1) {
            $seq = 1;
        }

        $receiptNo = $prefix . '-' . str_pad((string)$seq, 8, '0', STR_PAD_LEFT);
        $conn->query(
            "UPDATE business_account
             SET business_receipt_next = " . ($seq + 1) . "
             WHERE business_id = $businessId"
        );
        $conn->commit();
        return $receiptNo;
    } catch (Exception $e) {
        $conn->rollback();
        return '';
    }
}

function fh_order_display_no($order) {
    if (is_array($order) && !empty($order['orders_receipt_no'])) {
        return $order['orders_receipt_no'];
    }
    if (is_array($order) && isset($order['orders_id'])) {
        return '#' . (int)$order['orders_id'];
    }
    return '';
}

/**
 * Persist status and stamp orders_completed_at when entering Delivered/Cancelled.
 * Clears the stamp if the order is moved back to an open status.
 */
function fh_order_set_status($conn, $orderId, $status, $extraSets = array()) {
    $orderId = (int)$orderId;
    $order = fh_order_fetch($conn, $orderId);
    if (!$order) {
        return false;
    }

    $safeStatus = $conn->real_escape_string($status);
    $sets = array("orders_status = '$safeStatus'");
    foreach ($extraSets as $col => $value) {
        $sets[] = $col . " = '" . $conn->real_escape_string($value) . "'";
    }

    $wasTerminal = fh_order_is_terminal($order['orders_status']);
    $isTerminal = fh_order_is_terminal($status);

    if ($isTerminal && !$wasTerminal) {
        $sets[] = "orders_completed_at = NOW()";
    } elseif ($isTerminal && $wasTerminal && empty($order['orders_completed_at'])) {
        $sets[] = "orders_completed_at = NOW()";
    } elseif (!$isTerminal) {
        $sets[] = "orders_completed_at = NULL";
    }

    return (bool)$conn->query("UPDATE orders SET " . implode(', ', $sets) . " WHERE orders_id = $orderId");
}

function fh_order_recalculate_total($conn, $orderId) {
    $orderId = (int)$orderId;
    $result = $conn->query(
        "SELECT COALESCE(SUM(od.orders_details_qty * i.price), 0) AS total
         FROM orders_details od
         INNER JOIN items i ON od.orders_details_menu_id = i.id
         WHERE od.orders_details_orders_id = $orderId"
    );
    $total = 0;
    if ($result && $row = $result->fetch_assoc()) {
        $total = round((float)$row['total'], 2);
    }
    $conn->query("UPDATE orders SET orders_total_amount = $total WHERE orders_id = $orderId");
    return $total;
}

function fh_order_get_items($conn, $orderId) {
    $orderId = (int)$orderId;
    $items = array();
    $sql = $conn->query(
        "SELECT od.orders_details_id, od.orders_details_orders_id, od.orders_details_qty,
                i.id AS item_id, i.name, i.price, i.picture
         FROM orders_details od
         INNER JOIN items i ON od.orders_details_menu_id = i.id
         WHERE od.orders_details_orders_id = $orderId
         ORDER BY i.name ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $qty = (float)$row['orders_details_qty'];
            $price = (float)$row['price'];
            $row['line_total'] = round($qty * $price, 2);
            $row['picture_url'] = fh_food_image($row['picture']);
            $items[] = $row;
        }
    }
    return $items;
}

function fh_order_fetch($conn, $orderId) {
    $orderId = (int)$orderId;
    $sql = $conn->query(
        "SELECT o.*, ba.business_name, c.name AS company_name, d.name AS department_name
         FROM orders o
         LEFT JOIN business_account ba ON o.orders_business_id = ba.business_id
         LEFT JOIN company c ON o.orders_customers_company = c.id
         LEFT JOIN department d ON o.orders_customers_department = d.id
         WHERE o.orders_id = $orderId
         LIMIT 1"
    );
    return ($sql && $sql->num_rows > 0) ? $sql->fetch_assoc() : null;
}

function fh_order_fetch_for_customer($conn, $orderId, $customerId) {
    $order = fh_order_fetch($conn, $orderId);
    if (!$order || (int)$order['orders_customers_id'] !== (int)$customerId) {
        return null;
    }
    return $order;
}

function fh_order_fetch_for_admin($conn, $orderId) {
    if (!fh_auth_is_logged_in()) {
        return null;
    }
    $order = fh_order_fetch($conn, $orderId);
    if (!$order) {
        return null;
    }
    if (fh_auth_is_super_admin()) {
        return $order;
    }
    $bizId = fh_auth_user_business_id();
    $orderBiz = (int)$order['orders_business_id'];
    if ($orderBiz === $bizId || $orderBiz === 0) {
        return $order;
    }
    return null;
}

function fh_order_shipping_label($order) {
    if (!$order) {
        return '';
    }
    $shippingLabels = array('delivery' => 'Delivery', 'pickup' => 'Pick-up', 'dinein' => 'Dine-in');
    $type = isset($order['orders_shipping_type']) ? strtolower(trim($order['orders_shipping_type'])) : '';
    $label = isset($shippingLabels[$type]) ? $shippingLabels[$type] : ucfirst($type);
    if (!empty($order['orders_shipping_date'])) {
        $shipTime = !empty($order['orders_shipping_time'])
            ? date('g:i A', strtotime($order['orders_shipping_time']))
            : '';
        $label .= ' · ' . date('M j', strtotime($order['orders_shipping_date']))
            . ($shipTime !== '' ? ' ' . $shipTime : '');
    }
    if ($type === 'dinein' && !empty($order['orders_dinein_table'])) {
        $label .= ' · Table ' . $order['orders_dinein_table'];
    }
    return $label;
}

function fh_order_format_detail_payload($conn, $order) {
    if (!$order) {
        return null;
    }
    $orderId = (int)$order['orders_id'];
    $items = fh_order_get_items($conn, $orderId);
    $isPending = fh_order_is_pending($order['orders_status']);

    return array(
        'order_id' => $orderId,
        'receipt_no' => !empty($order['orders_receipt_no']) ? $order['orders_receipt_no'] : '',
        'order_no' => fh_order_display_no($order),
        'trans_date' => date('M j, Y g:i A', strtotime($order['orders_transdate'])),
        'menu_date' => date('M j, Y', strtotime($order['orders_menudate'])),
        'status' => $order['orders_status'],
        'is_pending' => $isPending,
        'is_cancelled' => fh_order_is_cancelled($order['orders_status']),
        'payment' => $order['orders_payment_type'],
        'shipping' => fh_order_shipping_label($order),
        'shipping_type' => $order['orders_shipping_type'],
        'shipping_date' => !empty($order['orders_shipping_date']) ? date('M j, Y', strtotime($order['orders_shipping_date'])) : '',
        'shipping_time' => !empty($order['orders_shipping_time']) ? date('g:i A', strtotime($order['orders_shipping_time'])) : '',
        'dinein_table' => !empty($order['orders_dinein_table']) ? $order['orders_dinein_table'] : '',
        'email' => $order['orders_customers_email'],
        'firstname' => $order['orders_customers_fname'],
        'lastname' => $order['orders_customers_lname'],
        'fullname' => trim($order['orders_customers_fname'] . ' ' . $order['orders_customers_lname']),
        'contact' => $order['orders_customers_contact'],
        'company' => !empty($order['company_name']) ? $order['company_name'] : 'N/A',
        'department' => !empty($order['department_name']) ? $order['department_name'] : 'N/A',
        'address' => $order['orders_delivery_address'],
        'remarks' => $order['orders_remarks'],
        'restaurant' => !empty($order['business_name']) ? $order['business_name'] : '—',
        'total_amount' => round((float)$order['orders_total_amount'], 2),
        'total_formatted' => number_format((float)$order['orders_total_amount'], 2),
        'item_count' => count($items),
        'items' => $items
    );
}

function fh_order_delete_item($conn, $orderId, $detailId, $allowWhenNotPending = false) {
    $orderId = (int)$orderId;
    $detailId = (int)$detailId;

    $order = fh_order_fetch($conn, $orderId);
    if (!$order) {
        return array('ok' => false, 'msg' => 'Order not found.');
    }
    if (!fh_order_is_pending($order['orders_status']) && !$allowWhenNotPending) {
        return array('ok' => false, 'msg' => 'Items can only be removed while the order is Pending.');
    }

    $check = $conn->query(
        "SELECT orders_details_id FROM orders_details
         WHERE orders_details_id = $detailId AND orders_details_orders_id = $orderId
         LIMIT 1"
    );
    if (!$check || $check->num_rows === 0) {
        return array('ok' => false, 'msg' => 'Item not found on this order.');
    }

    $countSql = $conn->query("SELECT COUNT(*) AS c FROM orders_details WHERE orders_details_orders_id = $orderId");
    $itemCount = $countSql ? (int)$countSql->fetch_assoc()['c'] : 0;
    if ($itemCount <= 1) {
        return array('ok' => false, 'msg' => 'Cannot remove the last item. Cancel the order instead.');
    }

    $conn->query("DELETE FROM orders_details WHERE orders_details_id = $detailId AND orders_details_orders_id = $orderId");
    $total = fh_order_recalculate_total($conn, $orderId);

    return array('ok' => true, 'msg' => 'Item removed.', 'total' => $total, 'item_count' => $itemCount - 1);
}

function fh_order_cancel($conn, $orderId, $reason) {
    $order = fh_order_fetch($conn, $orderId);
    if (!$order) {
        return array('ok' => false, 'msg' => 'Order not found.');
    }
    if (!fh_order_is_pending($order['orders_status'])) {
        return array('ok' => false, 'msg' => 'Only pending orders can be cancelled.');
    }
    $reason = trim($reason);
    if ($reason === '') {
        return array('ok' => false, 'msg' => 'Please provide a cancellation reason.');
    }

    $existing = trim((string)$order['orders_remarks']);
    $newRemarks = $existing === ''
        ? '[Cancelled] ' . $reason
        : $existing . "\n[Cancelled] " . $reason;

    fh_order_set_status($conn, $orderId, 'Cancelled', array(
        'orders_remarks' => $newRemarks,
    ));

    return array('ok' => true, 'msg' => 'Order has been cancelled.');
}

function fh_order_update_address($conn, $orderId, $address, $requirePending = true) {
    $order = fh_order_fetch($conn, $orderId);
    if (!$order) {
        return array('ok' => false, 'msg' => 'Order not found.');
    }
    if ($requirePending && !fh_order_is_pending($order['orders_status'])) {
        return array('ok' => false, 'msg' => 'Address can only be updated while the order is Pending.');
    }
    if (fh_order_is_cancelled($order['orders_status'])) {
        return array('ok' => false, 'msg' => 'Cancelled orders cannot be edited.');
    }

    $address = trim($address);
    if ($address === '') {
        return array('ok' => false, 'msg' => 'Delivery address is required.');
    }

    $safeAddress = $conn->real_escape_string($address);
    $conn->query("UPDATE orders SET orders_delivery_address = '$safeAddress' WHERE orders_id = $orderId");

    return array('ok' => true, 'msg' => 'Delivery address updated.');
}
