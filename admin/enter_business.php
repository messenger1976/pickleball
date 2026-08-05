<?php
include('config.php');
fh_auth_require_super_admin();

if (isset($_GET['exit']) && (int)$_GET['exit'] === 1) {
    fh_auth_exit_business();
    header('Location: index.php');
    exit;
}

$businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
if ($businessId <= 1 || !fh_auth_enter_business($conn, $businessId)) {
    header('Location: business_account.php?error=enter');
    exit;
}

header('Location: index.php?business_id=' . $businessId);
exit;
