<?php

function pb_plans_for_business($conn, $businessId, $activeOnly = true) {
    $businessId = (int)$businessId;
    $where = "WHERE business_id=$businessId";
    if ($activeOnly) {
        $where .= ' AND plan_status=1';
    }
    $list = array();
    $sql = $conn->query("SELECT * FROM membership_plans $where ORDER BY price ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_plan_fetch($conn, $planId) {
    $planId = (int)$planId;
    $sql = $conn->query(
        "SELECT p.*, ba.business_name, ba.business_status
         FROM membership_plans p
         INNER JOIN business_account ba ON ba.business_id = p.business_id
         WHERE p.plan_id=$planId LIMIT 1"
    );
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_active_membership($conn, $customersId, $businessId) {
    $customersId = (int)$customersId;
    $businessId = (int)$businessId;
    $today = date('Y-m-d');
    $sql = $conn->query(
        "SELECT m.*, p.plan_name, p.discount_percent, p.billing_period
         FROM memberships m
         INNER JOIN membership_plans p ON p.plan_id = m.plan_id
         WHERE m.customers_id=$customersId
           AND m.business_id=$businessId
           AND m.membership_status='active'
           AND (m.current_period_end IS NULL OR m.current_period_end >= '$today')
         ORDER BY m.membership_id DESC
         LIMIT 1"
    );
    return ($sql && ($row = $sql->fetch_assoc())) ? $row : null;
}

function pb_memberships_for_customer($conn, $customersId) {
    $customersId = (int)$customersId;
    $list = array();
    $sql = $conn->query(
        "SELECT m.*, p.plan_name, p.price, p.billing_period, ba.business_name
         FROM memberships m
         INNER JOIN membership_plans p ON p.plan_id = m.plan_id
         INNER JOIN business_account ba ON ba.business_id = m.business_id
         WHERE m.customers_id=$customersId
         ORDER BY m.created_at DESC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function pb_membership_subscribe($conn, $planId, $customersId) {
    $plan = pb_plan_fetch($conn, $planId);
    if (!$plan || (int)$plan['plan_status'] !== 1 || (int)$plan['business_status'] !== 1) {
        return array('ok' => false, 'msg' => 'Plan not available.');
    }
    $existing = pb_active_membership($conn, $customersId, (int)$plan['business_id']);
    if ($existing) {
        return array('ok' => false, 'msg' => 'You already have an active membership at this venue.');
    }

    $period = strtolower($plan['billing_period']) === 'yearly' ? '+1 year' : '+1 month';
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime($period));
    $businessId = (int)$plan['business_id'];
    $customersId = (int)$customersId;

    $conn->query(
        "INSERT INTO memberships
         (plan_id, business_id, customers_id, membership_status, current_period_start, current_period_end, created_at)
         VALUES ($planId, $businessId, $customersId, 'pending', '$start', '$end', NOW())"
    );
    $membershipId = (int)$conn->insert_id;
    if ($membershipId <= 0) {
        return array('ok' => false, 'msg' => 'Could not create membership.');
    }

    return array(
        'ok' => true,
        'membership_id' => $membershipId,
        'business_id' => $businessId,
        'amount' => (float)$plan['price'],
        'plan_name' => $plan['plan_name'],
        'msg' => 'Membership created.',
    );
}

function pb_membership_renew($conn, $membershipId) {
    $membershipId = (int)$membershipId;
    $sql = $conn->query(
        "SELECT m.*, p.billing_period, p.price, p.plan_name
         FROM memberships m
         INNER JOIN membership_plans p ON p.plan_id = m.plan_id
         WHERE m.membership_id=$membershipId
         LIMIT 1"
    );
    if (!$sql || !($m = $sql->fetch_assoc())) {
        return array('ok' => false, 'msg' => 'Membership not found.');
    }
    $period = strtolower($m['billing_period']) === 'yearly' ? '+1 year' : '+1 month';
    $base = !empty($m['current_period_end']) ? $m['current_period_end'] : date('Y-m-d');
    if (strtotime($base) < time()) {
        $base = date('Y-m-d');
    }
    $start = date('Y-m-d', strtotime($base));
    $end = date('Y-m-d', strtotime($period, strtotime($start)));
    $conn->query(
        "UPDATE memberships
         SET membership_status='pending',
             current_period_start='$start',
             current_period_end='$end'
         WHERE membership_id=$membershipId"
    );
    return array(
        'ok' => true,
        'membership_id' => $membershipId,
        'business_id' => (int)$m['business_id'],
        'customers_id' => (int)$m['customers_id'],
        'amount' => (float)$m['price'],
        'plan_name' => $m['plan_name'],
        'current_period_start' => $start,
        'current_period_end' => $end,
        'msg' => 'Renewal period prepared.',
    );
}

function pb_membership_expire_stale($conn) {
    $today = date('Y-m-d');
    $conn->query(
        "UPDATE memberships
         SET membership_status='expired'
         WHERE membership_status='active'
           AND current_period_end IS NOT NULL
           AND current_period_end < '$today'"
    );
    return (int)$conn->affected_rows;
}

function pb_apply_member_discount($conn, $customersId, $businessId, $price) {
    $m = pb_active_membership($conn, $customersId, $businessId);
    if (!$m) {
        return array('price' => (float)$price, 'discount_percent' => 0, 'membership' => null);
    }
    $pct = (float)$m['discount_percent'];
    $newPrice = round((float)$price * (1 - ($pct / 100)), 2);
    return array('price' => $newPrice, 'discount_percent' => $pct, 'membership' => $m);
}
