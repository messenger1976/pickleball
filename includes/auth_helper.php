<?php

define('FH_ROLE_SUPER', 'super_admin');
define('FH_ROLE_BUSINESS_ADMIN', 'business_admin');
define('FH_ROLE_BUSINESS_STAFF', 'business_staff');

function fh_auth_load_user($conn, $userId) {
    $userId = (int)$userId;
    $sql = $conn->query("SELECT * FROM user WHERE user_id = $userId AND user_status = 1 LIMIT 1");
    if ($sql && $sql->num_rows > 0) {
        return $sql->fetch_assoc();
    }
    return null;
}

function fh_auth_is_logged_in() {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function fh_auth_is_super_admin() {
    return fh_auth_is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === FH_ROLE_SUPER;
}

function fh_auth_user_business_id() {
    if (!fh_auth_is_logged_in()) {
        return 0;
    }
    if (fh_auth_is_super_admin()) {
        return 0;
    }
    return isset($_SESSION['user_business_account']) ? (int)$_SESSION['user_business_account'] : 0;
}

function fh_auth_can_manage_users() {
    if (!fh_auth_is_logged_in()) {
        return false;
    }
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    return $role === FH_ROLE_SUPER || $role === FH_ROLE_BUSINESS_ADMIN;
}

function fh_auth_can_access_business($businessId) {
    $businessId = (int)$businessId;
    if ($businessId <= 0) {
        return false;
    }
    if (fh_auth_is_super_admin()) {
        return true;
    }
    return fh_auth_user_business_id() === $businessId;
}

function fh_auth_require_login($redirect = 'login.php') {
    if (!fh_auth_is_logged_in()) {
        header("Location: $redirect");
        exit;
    }
}

function fh_auth_require_super_admin($redirect = 'index.php') {
    fh_auth_require_login();
    if (!fh_auth_is_super_admin()) {
        header("Location: $redirect");
        exit;
    }
}

/**
 * Soft-lock venue users whose trial/subscription expired onto subscription.php.
 * Super Admin (portal or managing) is never locked.
 */
function fh_auth_subscription_soft_lock($conn) {
    if (!fh_auth_is_logged_in() || !$conn) {
        return;
    }
    if (fh_auth_is_super_admin()) {
        return;
    }
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    if ($role !== FH_ROLE_BUSINESS_ADMIN && $role !== FH_ROLE_BUSINESS_STAFF) {
        return;
    }

    $page = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    $allowed = array('subscription.php', 'login.php', 'logout.php', 'process.php', 'profile.php');
    if (in_array($page, $allowed, true)) {
        return;
    }

    include_once __DIR__ . '/portal_subscription_helper.php';
    if (!function_exists('fh_portal_sub_access_state')) {
        return;
    }

    $bizId = fh_auth_user_business_id();
    if ($bizId <= 0) {
        return;
    }

    $access = fh_portal_sub_access_state($conn, $bizId);
    if (!empty($access['is_access_ok'])) {
        return;
    }
    // Only soft-lock when a subscription record exists and is expired/pending
    if ($access['state'] === 'none') {
        return;
    }
    if ($access['state'] === 'expired' || $access['state'] === 'pending') {
        // Staff cannot pay — send them a clear page; admins go to subscription
        if ($role === FH_ROLE_BUSINESS_STAFF) {
            header('Location: profile.php?sub_expired=1');
            exit;
        }
        header('Location: subscription.php');
        exit;
    }
}

function fh_auth_role_label($role) {
    switch ($role) {
        case FH_ROLE_SUPER: return 'Super Admin';
        case FH_ROLE_BUSINESS_ADMIN: return 'Business Admin';
        case FH_ROLE_BUSINESS_STAFF: return 'Business Staff';
        default: return ucfirst(str_replace('_', ' ', $role));
    }
}

function fh_auth_set_session($userRow) {
    $_SESSION['user_id'] = (int)$userRow['user_id'];
    $_SESSION['user_name'] = $userRow['user_name'];
    $_SESSION['user_fullname'] = $userRow['user_fullname'];
    $_SESSION['user_role'] = $userRow['user_role'];
    $_SESSION['user_email'] = isset($userRow['user_email']) ? $userRow['user_email'] : '';
    $_SESSION['user_business_account'] = (int)$userRow['user_business_account'];
    $_SESSION['user_avatar'] = !empty($userRow['user_avatar']) ? $userRow['user_avatar'] : '';
    if ((int)$userRow['user_business_account'] > 0) {
        $_SESSION['admin_business_id'] = (int)$userRow['user_business_account'];
    }
    // Preserve Super Admin venue-managing context across profile refresh;
    // clear it when the logged-in user is no longer Super Admin.
    if ($userRow['user_role'] !== FH_ROLE_SUPER) {
        unset($_SESSION['super_admin_managing_business']);
    }
}

/**
 * Super Admin enters a venue dashboard without changing login identity.
 * Keeps user_role = super_admin; only sets working business context.
 */
function fh_auth_enter_business($conn, $businessId) {
    if (!fh_auth_is_super_admin()) {
        return false;
    }
    $businessId = (int)$businessId;
    if ($businessId <= 1) {
        return false;
    }
    $sql = $conn->query(
        "SELECT business_id, business_name, business_status
         FROM business_account
         WHERE business_id = $businessId
         LIMIT 1"
    );
    if (!$sql || !$sql->fetch_assoc()) {
        return false;
    }
    $_SESSION['admin_business_id'] = $businessId;
    $_SESSION['super_admin_managing_business'] = $businessId;
    return true;
}

function fh_auth_exit_business() {
    unset($_SESSION['super_admin_managing_business']);
}

function fh_auth_is_managing_business() {
    return fh_auth_is_super_admin()
        && isset($_SESSION['super_admin_managing_business'])
        && (int)$_SESSION['super_admin_managing_business'] > 0;
}

function fh_auth_managing_business_id() {
    if (!fh_auth_is_managing_business()) {
        return 0;
    }
    return (int)$_SESSION['super_admin_managing_business'];
}

function fh_auth_avatar_url($avatar = null) {
    if ($avatar === null) {
        $avatar = isset($_SESSION['user_avatar']) ? $_SESSION['user_avatar'] : '';
    }
    $avatar = trim((string)$avatar);
    if ($avatar === '') {
        return 'images/img_avatar.jpg';
    }
    return $avatar;
}

function fh_auth_refresh_session($conn) {
    if (!fh_auth_is_logged_in()) {
        return;
    }
    $uid = (int)$_SESSION['user_id'];
    $sql = $conn->query("SELECT * FROM user WHERE user_id = $uid LIMIT 1");
    if ($sql && ($row = $sql->fetch_assoc())) {
        fh_auth_set_session($row);
    }
}

function fh_get_business_page($conn, $businessId) {
    $businessId = (int)$businessId;
    $sql = $conn->query("SELECT bp.*, ba.business_name, ba.business_address, ba.business_city, ba.business_contact, ba.business_subdomain, ba.business_status FROM business_page bp INNER JOIN business_account ba ON bp.business_id = ba.business_id WHERE bp.business_id = $businessId LIMIT 1");
    if ($sql && $sql->num_rows > 0) {
        return $sql->fetch_assoc();
    }
    return null;
}

function fh_ensure_business_page($conn, $businessId) {
    $businessId = (int)$businessId;
    $exists = $conn->query("SELECT page_id FROM business_page WHERE business_id = $businessId LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        return;
    }
    $biz = fh_resolve_business($conn, $businessId);
    if (!$biz) {
        $bizSql = $conn->query("SELECT business_name, business_city FROM business_account WHERE business_id = $businessId LIMIT 1");
        $biz = $bizSql ? $bizSql->fetch_assoc() : array('business_name' => 'Restaurant', 'business_city' => '');
    }
    $name = $conn->real_escape_string($biz['business_name']);
    $city = $conn->real_escape_string(isset($biz['business_city']) ? $biz['business_city'] : '');
    $conn->query("INSERT INTO business_page (business_id, page_tagline, page_about, page_hours, page_specialties, page_amenities, auto_confirm, booking_max_days, cancellation_note) VALUES ($businessId, 'Welcome to $name', '$name offers pickleball courts in $city. Book your court online.', 'Mon-Sun 6:00 AM - 10:00 PM', 'Open play, Court rental', 'Parking, restrooms', 1, 14, 'Cancel at least 24 hours before your booking.')");
}

function fh_get_manageable_roles() {
    if (fh_auth_is_super_admin()) {
        return array(FH_ROLE_SUPER, FH_ROLE_BUSINESS_ADMIN, FH_ROLE_BUSINESS_STAFF);
    }
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === FH_ROLE_BUSINESS_ADMIN) {
        return array(FH_ROLE_BUSINESS_ADMIN, FH_ROLE_BUSINESS_STAFF);
    }
    return array();
}

function fh_get_all_businesses_admin($conn) {
    $list = array();
    $sql = $conn->query("SELECT business_id, business_name, business_city, business_status FROM business_account WHERE business_id > 1 ORDER BY business_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}
