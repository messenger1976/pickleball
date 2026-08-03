<?php

function fh_get_active_businesses($conn) {
    $list = array();
    $sql = $conn->query(
        "SELECT business_id, business_name, business_city, business_subdomain
         FROM business_account
         WHERE business_status = 1 AND business_id > 1
         ORDER BY business_name ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fh_resolve_business($conn, $businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return null;
    }
    $sql = $conn->query(
        "SELECT business_id, business_name, business_city, business_contact, business_email
         FROM business_account
         WHERE business_id = $businessId AND business_status = 1
         LIMIT 1"
    );
    if ($sql && $sql->num_rows > 0) {
        return $sql->fetch_assoc();
    }
    return null;
}

function fh_portal_business_id($conn) {
    if (isset($_GET['business_id']) && is_numeric($_GET['business_id'])) {
        $id = (int)$_GET['business_id'];
        if (fh_resolve_business($conn, $id)) {
            $_SESSION['business_id'] = $id;
        }
    }
    return isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : 0;
}

function fh_portal_menu_link($businessId) {
    if ($businessId > 0) {
        return 'store.php?business_id=' . (int)$businessId;
    }
    return 'home.php#restaurants';
}

function fh_store_link($businessId, $subdomain = '') {
    if (!empty($subdomain)) {
        return 'store.php?slug=' . urlencode($subdomain);
    }
    return 'store.php?business_id=' . (int)$businessId;
}

function fh_admin_business_id($conn) {
    if (!fh_auth_is_super_admin() && fh_auth_user_business_id() > 0) {
        $_SESSION['admin_business_id'] = fh_auth_user_business_id();
        return fh_auth_user_business_id();
    }
    if (isset($_GET['business_id']) && is_numeric($_GET['business_id'])) {
        $id = (int)$_GET['business_id'];
        if (fh_resolve_business($conn, $id) || fh_auth_is_super_admin()) {
            $_SESSION['admin_business_id'] = $id;
        }
    }
    if (!isset($_SESSION['admin_business_id']) || (int)$_SESSION['admin_business_id'] <= 0) {
        $businesses = fh_get_active_businesses($conn);
        $_SESSION['admin_business_id'] = !empty($businesses) ? (int)$businesses[0]['business_id'] : 2;
    }
    return (int)$_SESSION['admin_business_id'];
}

function fh_admin_business_selector($conn, $action = '') {
    if (!fh_auth_is_super_admin()) {
        $bizId = fh_auth_user_business_id();
        $biz = fh_resolve_business($conn, $bizId);
        if (!$biz) {
            $bizSql = $conn->query("SELECT business_name FROM business_account WHERE business_id = $bizId LIMIT 1");
            $biz = $bizSql ? $bizSql->fetch_assoc() : array('business_name' => 'Your Restaurant');
        }
        return '<div class="w3-panel w3-light-grey w3-leftbar w3-border-green" style="margin:12px 0;"><strong>Managing:</strong> ' . htmlspecialchars($biz['business_name']) . '</div>';
    }
    $businesses = fh_get_active_businesses($conn);
    $selectedId = fh_admin_business_id($conn);
    $html = '<form method="GET" action="' . htmlspecialchars($action) . '" class="w3-panel w3-light-grey w3-leftbar w3-border-green" style="margin:12px 0;">';
    $html .= '<label for="admin_business_id"><strong>Restaurant:</strong></label> ';
    $html .= '<select id="admin_business_id" name="business_id" onchange="this.form.submit()" style="max-width:320px;padding:8px;">';
    foreach ($businesses as $biz) {
        $sel = ((int)$biz['business_id'] === $selectedId) ? ' selected' : '';
        $html .= '<option value="' . (int)$biz['business_id'] . '"' . $sel . '>' . htmlspecialchars($biz['business_name']) . '</option>';
    }
    $html .= '</select></form>';
    return $html;
}

function fh_item_business_id($conn, $itemId) {
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return 0;
    }
    $sql = $conn->query("SELECT business_id FROM items WHERE id = $itemId LIMIT 1");
    if ($sql && $row = $sql->fetch_assoc()) {
        return (int)$row['business_id'];
    }
    return 0;
}

function fh_food_image($picture) {
    if (!empty($picture)) {
        return 'admin/' . ltrim($picture, '/');
    }
    return 'admin/images/20200608_135036.jpg';
}

function fh_cms_image($picture) {
    if (empty($picture)) {
        return '';
    }
    if (strpos($picture, 'http') === 0 || strpos($picture, 'admin/') === 0 || strpos($picture, 'images/') === 0) {
        return $picture;
    }
    return 'admin/' . ltrim($picture, '/');
}

function fh_sync_cart_business_session() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        unset($_SESSION['cart_business_id']);
    }
}

function fh_business_status_label($status) {
    $status = (int)$status;
    if ($status === 1) {
        return '<span style="background:#d8f3dc;color:#1b4332;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:bold;">Active</span>';
    }
    return '<span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:bold;">Pending</span>';
}

function fh_count_pending_businesses($conn) {
    $sql = $conn->query("SELECT COUNT(*) AS cnt FROM business_account WHERE business_status = 0 AND business_id > 1");
    if ($sql && $row = $sql->fetch_assoc()) {
        return (int)$row['cnt'];
    }
    return 0;
}

function fh_preorder_settings($conn, $businessId) {
    $businessId = (int)$businessId;
    $defaults = array('preorder_enabled' => 0, 'preorder_max_days' => 14);
    if ($businessId <= 0) {
        return $defaults;
    }
    $sql = $conn->query(
        "SELECT COALESCE(preorder_enabled, 0) AS preorder_enabled,
                COALESCE(preorder_max_days, 14) AS preorder_max_days
         FROM business_page
         WHERE business_id = $businessId
         LIMIT 1"
    );
    if ($sql && $row = $sql->fetch_assoc()) {
        $maxDays = (int)$row['preorder_max_days'];
        if ($maxDays < 1) {
            $maxDays = 14;
        }
        return array(
            'preorder_enabled' => (int)$row['preorder_enabled'] === 1 ? 1 : 0,
            'preorder_max_days' => $maxDays
        );
    }
    return $defaults;
}

function fh_preorder_enabled($conn, $businessId) {
    $settings = fh_preorder_settings($conn, $businessId);
    return $settings['preorder_enabled'] === 1;
}

function fh_get_preorder_items($conn, $businessId, $excludeItemIds = array()) {
    $businessId = (int)$businessId;
    $items = array();
    if ($businessId <= 0 || !fh_preorder_enabled($conn, $businessId)) {
        return $items;
    }
    $exclude = array();
    foreach ($excludeItemIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $exclude[] = $id;
        }
    }
    $excludeSql = '';
    if (!empty($exclude)) {
        $excludeSql = ' AND id NOT IN (' . implode(',', $exclude) . ')';
    }
    $sql = $conn->query(
        "SELECT id, name, price, picture, allow_preorder, business_id
         FROM items
         WHERE business_id = $businessId AND allow_preorder = 1 $excludeSql
         ORDER BY name ASC"
    );
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

function fh_clamp_menudate($dateStr, $maxDays = 14) {
    $maxDays = max(1, (int)$maxDays);
    $today = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+' . $maxDays . ' days'));
    if (empty($dateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $today;
    }
    if ($dateStr < $today) {
        return $today;
    }
    if ($dateStr > $maxDate) {
        return $maxDate;
    }
    return $dateStr;
}

function fh_item_orderable($conn, $itemId, $menudate) {
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return false;
    }
    $safeDate = $conn->real_escape_string($menudate);
    $sql = $conn->query(
        "SELECT i.id, i.business_id, i.allow_preorder
         FROM items i
         WHERE i.id = $itemId
         LIMIT 1"
    );
    if (!$sql || !$row = $sql->fetch_assoc()) {
        return false;
    }
    $businessId = (int)$row['business_id'];
    $onMenu = $conn->query(
        "SELECT menu_id FROM menu_listing
         WHERE menu_item = $itemId
           AND menu_date = '$safeDate'
           AND menu_business_id = $businessId
         LIMIT 1"
    );
    if ($onMenu && $onMenu->num_rows > 0) {
        return true;
    }
    if ((int)$row['allow_preorder'] === 1 && fh_preorder_enabled($conn, $businessId)) {
        return true;
    }
    return false;
}
