<?php
include('config.php');
fh_auth_require_super_admin();

include_once(__DIR__ . '/../includes/geo_helper.php');
include_once(__DIR__ . '/../includes/portal_subscription_helper.php');
fh_geo_ensure_seeded($conn);
$geoCountries = fh_geo_get_countries($conn);
$defaultCountry = 'Philippines';
$defaultProvince = 'Bohol';
$defaultTown = 'Tagbilaran City';
$phCountryId = 0;
foreach ($geoCountries as $gc) {
    if ($gc['country_name'] === 'Philippines') {
        $phCountryId = (int)$gc['country_id'];
        break;
    }
}
$geoProvinces = $phCountryId > 0 ? fh_geo_get_provinces($conn, $phCountryId) : array();
$selectedProvinceId = 0;
foreach ($geoProvinces as $gp) {
    if ($gp['province_name'] === $defaultProvince) {
        $selectedProvinceId = (int)$gp['province_id'];
        break;
    }
}
$geoTowns = $selectedProvinceId > 0 ? fh_geo_get_towns($conn, $selectedProvinceId) : array();
$defaultZipcode = fh_geo_default_zipcode($conn, $defaultProvince, $defaultTown);
$selectedTownId = fh_geo_find_town_id($conn, $defaultProvince, $defaultTown);
$geoBarangays = $selectedTownId > 0 ? fh_geo_get_barangays($conn, $selectedTownId) : array();
$hasBarangayList = count($geoBarangays) > 0;

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'active', 'trial', 'expired', 'inactive'), true)
    ? $_GET['status']
    : 'all';

$baseWhere = 'WHERE ba.business_id > 1';
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $like = "'%" . $safe . "%'";
    $baseWhere .= " AND (
        ba.business_name LIKE $like OR
        ba.business_email LIKE $like OR
        ba.business_cont_person LIKE $like OR
        ba.business_contact LIKE $like OR
        ba.business_address LIKE $like OR
        ba.business_city LIKE $like OR
        ba.business_subdomain LIKE $like
    )";
}

$allowedSort = array(
    'id' => 'ba.business_id',
    'name' => 'ba.business_name',
    'email' => 'ba.business_email',
    'contact' => 'ba.business_cont_person',
    'signup' => 'ba.business_signup_date',
    'status' => 'ba.business_status',
);
$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'status';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';
if ($sortKey === 'status' && !isset($_GET['dir'])) {
    $sortDir = 'ASC';
}

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$businesses = array();
$allBusinesses = array();
$summaryCounts = array(
    'active' => 0,
    'trial' => 0,
    'expired' => 0,
    'inactive' => 0,
);
$allSql = $conn->query(
    "SELECT ba.*, bs.subscription_id, bs.signup_intent, bs.status AS subscription_status,
            bs.trial_ends_at, bs.current_period_end
     FROM business_account ba
     LEFT JOIN (
         SELECT bs1.*
         FROM business_subscriptions bs1
         INNER JOIN (
             SELECT business_id, MAX(subscription_id) AS max_subscription_id
             FROM business_subscriptions
             GROUP BY business_id
         ) latest
             ON latest.max_subscription_id = bs1.subscription_id
     ) bs ON bs.business_id = ba.business_id
     $baseWhere
     ORDER BY $sortCol $sortDir, ba.business_name ASC"
);
if ($allSql) {
    while ($row = $allSql->fetch_assoc()) {
        $row['portal_status_key'] = 'inactive';
        $row['portal_status_label'] = ((int)$row['business_status'] === 1) ? 'Active' : 'Inactive';
        $row['portal_status_class'] = ((int)$row['business_status'] === 1) ? 'is-success' : 'is-neutral';

        $subState = fh_portal_sub_access_state($conn, (int)$row['business_id']);
        if ((int)$row['business_status'] !== 1) {
            $row['portal_status_key'] = 'inactive';
            $row['portal_status_label'] = 'Inactive';
            $row['portal_status_class'] = 'is-neutral';
        } elseif ($subState['state'] === 'trialing') {
            $row['portal_status_key'] = 'trial';
            $row['portal_status_label'] = 'Trial Account';
            $row['portal_status_class'] = 'is-warning';
        } elseif ($subState['state'] === 'expired') {
            $row['portal_status_key'] = 'expired';
            $row['portal_status_label'] = 'Expired Trial';
            $row['portal_status_class'] = 'is-danger';
        } else {
            $row['portal_status_key'] = 'active';
            $row['portal_status_label'] = 'Active';
            $row['portal_status_class'] = 'is-success';
        }

        if (isset($summaryCounts[$row['portal_status_key']])) {
            $summaryCounts[$row['portal_status_key']]++;
        }
        $allBusinesses[] = $row;
    }
}

foreach ($allBusinesses as $row) {
    if ($filterStatus !== 'all' && $row['portal_status_key'] !== $filterStatus) {
        continue;
    }
    $businesses[] = $row;
}

$totalBusinesses = count($businesses);
$totalPages = $totalBusinesses > 0 ? (int)ceil($totalBusinesses / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalBusinesses > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalBusinesses);
$businesses = array_slice($businesses, $offset, $perPage);

$pendingCount = $summaryCounts['inactive'];
$activeCount = $summaryCounts['active'];
$trialCount = $summaryCounts['trial'];
$expiredCount = $summaryCounts['expired'];

if ($filterStatus === 'all') {
    $filteredLabel = '';
} else {
    $filteredLabel = ' (' . ucfirst($filterStatus) . ')';
}

function admin_business_list_url($overrides = array()) {
    global $page, $perPage, $searchQ, $filterStatus, $sortKey, $sortDir;
    $params = array(
        'page' => $page,
        'per_page' => $perPage,
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'status' => $filterStatus,
    );
    if ($searchQ !== '') {
        $params['q'] = $searchQ;
    }
    $params = array_merge($params, $overrides);
    if (isset($params['q']) && $params['q'] === '') {
        unset($params['q']);
    }
    return 'business_account.php?' . http_build_query($params);
}

function admin_business_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return admin_business_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_business_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

$titleheader = 'Business Account';
include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260804e" type="text/css" />
<style>
.admin-orders-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 18px;
    padding: 16px 18px;
    margin: 0 0 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
}
.admin-orders-filter .admin-business-filter-spacer {
    flex: 1 1 auto;
    min-width: 12px;
}
.admin-orders-filter label {
    display: block;
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-orders-filter input[type="search"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 160px;
}
.admin-orders-filter button,
.admin-business-toolbar-btn {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.admin-orders-filter button:hover,
.admin-business-toolbar-btn:hover {
    background: #1b4332;
    color: #fff;
    text-decoration: none;
}
.admin-business-tip {
    margin: 0 0 16px;
    padding: 12px 14px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    color: #92400e;
    font-size: 13px;
}
.admin-business-tip a {
    color: #2d6a4f;
    font-weight: 700;
}
.portal-orders-summary {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.portal-order-modal-dialog-business {
    width: min(760px, calc(100% - 32px));
}
.portal-order-modal-section > label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section > input[type="text"],
.portal-order-modal-section > input[type="email"],
.portal-order-modal-section > input[type="tel"],
.portal-order-modal-section > select,
.portal-order-modal-section .admin-business-row input,
.portal-order-modal-section .admin-business-row select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section .admin-business-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.portal-order-modal-section .admin-business-row label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.admin-business-readonly {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #f8fafc;
    color: #475569;
    min-height: 44px;
    display: flex;
    align-items: center;
}
.portal-order-modal-section .admin-business-hint {
    margin: -8px 0 14px;
    font-size: 12px;
    color: #94a3b8;
}
.portal-order-modal-section .admin-business-address-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
.portal-order-modal-section .admin-business-address-grid .portal-login-field {
    margin-bottom: 2px;
}
.portal-order-modal-section .admin-business-address-grid .portal-profile-field-full {
    grid-column: 1 / -1;
}
.portal-order-modal-section .admin-business-address-grid label {
    display: block;
    margin: 0 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-business-address-grid input,
.portal-order-modal-section .admin-business-address-grid select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 4px;
    background: #fff;
}
.portal-order-modal-section .portal-profile-section-title {
    margin: 4px 0 10px;
}
.portal-login-panel-alert {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 13px;
}
#errmsg { display: none; }
@media (max-width: 640px) {
    .portal-orders-summary {
        grid-template-columns: 1fr 1fr;
    }
    .portal-order-modal-section .admin-business-row,
    .portal-order-modal-section .admin-business-address-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="errmsg"></div>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Business Account Listing</h1>
        <p>Review partner applications, activate restaurants, and manage business details.</p>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'enter'): ?>
    <p class="admin-business-tip" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
        Unable to open that business dashboard. The account may no longer exist.
    </p>
    <?php endif; ?>

    <?php if ($pendingCount > 0): ?>
    <p class="admin-business-tip">
        <strong><?php echo number_format($pendingCount); ?> pending partner application<?php echo $pendingCount === 1 ? '' : 's'; ?></strong> waiting for review.
        Set status to <strong>Active</strong> to publish on the portal, then use <a href="copy_items.php">Copy Items</a> to populate their menu.
    </p>
    <?php endif; ?>

    <form class="admin-orders-filter" method="get" action="business_account.php">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Name, email, contact…">
        </div>
        <div>
            <label for="filterStatus">Status</label>
            <select id="filterStatus" name="status">
                <option value="all"<?php echo $filterStatus === 'all' ? ' selected' : ''; ?>>All businesses</option>
                <option value="active"<?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
                <option value="trial"<?php echo $filterStatus === 'trial' ? ' selected' : ''; ?>>Trial Account</option>
                <option value="expired"<?php echo $filterStatus === 'expired' ? ' selected' : ''; ?>>Expired Trial</option>
                <option value="inactive"<?php echo $filterStatus === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Apply Filter</button>
        </div>
        <div class="admin-business-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-business-toolbar-btn" id="btnAddBusiness">Add Business</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalBusinesses); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterStatus !== 'all') ? $filteredLabel : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($activeCount); ?></strong>
            <span>Active</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($trialCount); ?></strong>
            <span>Trial Account</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($expiredCount); ?></strong>
            <span>Expired Trial</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($pendingCount); ?></strong>
            <span>Inactive</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Restaurants</h2>
            <?php if ($totalBusinesses > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalBusinesses); ?> business<?php echo $totalBusinesses === 1 ? '' : 'es'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($businesses)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">🏪</div>
                <h2>No businesses found</h2>
                <p><?php echo ($searchQ !== '' || $filterStatus !== 'all')
                    ? 'No businesses match this filter. Try another search or status.'
                    : 'No partner businesses yet. Click <strong>Add Business</strong> to create one.'; ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalBusinesses); ?></strong>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="business_account.php">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <?php if ($searchQ !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="page" value="1">
                    <label for="perPageSelect">Rows per page</label>
                    <select id="perPageSelect" name="per_page" class="portal-orders-per-page-select" onchange="this.form.submit()">
                        <?php foreach ($allowedPerPage as $option): ?>
                            <option value="<?php echo $option; ?>"<?php echo $perPage === $option ? ' selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="portal-orders-table-wrap">
                <table class="portal-orders-table" id="businessTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('id')); ?>">
                                    ID <?php echo admin_business_sort_indicator('id'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('name')); ?>">
                                    Business Name <?php echo admin_business_sort_indicator('name'); ?>
                                </a>
                            </th>
                            <th>Address</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('contact')); ?>">
                                    Contact Person <?php echo admin_business_sort_indicator('contact'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('email')); ?>">
                                    Email <?php echo admin_business_sort_indicator('email'); ?>
                                </a>
                            </th>
                            <th>Phone</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('signup')); ?>">
                                    Sign-up Date <?php echo admin_business_sort_indicator('signup'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_business_sort_url('status')); ?>">
                                    Status <?php echo admin_business_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $row):
                            $bizId = (int)$row['business_id'];
                            $fullAddress = implode(', ', array_filter(array(
                                $row['business_address'],
                                !empty($row['business_barangay']) ? 'Brgy. ' . $row['business_barangay'] : '',
                                $row['business_city'],
                                $row['business_province'],
                                $row['business_country'],
                                $row['business_zipcode'],
                            )));
                        ?>
                        <tr class="portal-orders-row" data-business-id="<?php echo $bizId; ?>">
                            <td><strong>#<?php echo $bizId; ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($row['business_name']); ?>
                                <?php if (!empty($row['business_subdomain'])): ?>
                                    <br><span style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars($row['business_subdomain']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($fullAddress !== '' ? $fullAddress : '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['business_cont_person'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['business_email'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['business_contact'] ?: '—'); ?></td>
                            <td><?php echo !empty($row['business_signup_date']) ? date('M j, Y g:i A', strtotime($row['business_signup_date'])) : '—'; ?></td>
                            <td>
                                <span class="portal-orders-status <?php echo htmlspecialchars($row['portal_status_class']); ?>">
                                    <?php echo htmlspecialchars($row['portal_status_label']); ?>
                                </span>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <a class="portal-orders-action-btn" href="enter_business.php?business_id=<?php echo $bizId; ?>" title="Open venue dashboard" aria-label="Login to business dashboard #<?php echo $bizId; ?>"><i class="fa fa-sign-in" aria-hidden="true"></i></a>
                                    <button type="button" class="portal-orders-action-btn btn-business-edit" data-businessid="<?php echo $bizId; ?>" title="Edit business" aria-label="Edit business #<?php echo $bizId; ?>">✎</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="portal-orders-pagination">
                <?php if ($page > 1): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_business_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">&larr; Previous</span>
                <?php endif; ?>

                <div class="portal-orders-page-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <?php if ($p === $page): ?>
                            <span class="portal-orders-page-num is-current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_business_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_business_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="businessModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-business" role="dialog" aria-labelledby="businessModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="businessModalTitle">Add Business</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="businessFormMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-section">
                <input type="hidden" id="business_id" value="0">

                <div class="admin-business-row">
                    <div>
                        <label for="business_name">Business Name *</label>
                        <input type="text" id="business_name" placeholder="Restaurant name" required>
                    </div>
                    <div>
                        <label for="subdomain">Sub-domain *</label>
                        <input type="text" id="subdomain" placeholder="e.g. marias-carenderia" required>
                    </div>
                </div>
                <p class="admin-business-hint">Lowercase letters, numbers, and hyphens only. Used for the public menu link.</p>

                <div class="admin-business-row">
                    <div>
                        <label for="receipt_prefix">Receipt Prefix</label>
                        <input type="text" id="receipt_prefix" placeholder="e.g. EFH" maxlength="10" style="text-transform:uppercase;">
                    </div>
                    <div>
                        <label for="receipt_next">Next Receipt Counter</label>
                        <input type="number" id="receipt_next" min="1" step="1" placeholder="1">
                    </div>
                </div>
                <p class="admin-business-hint">Receipt numbers use this prefix plus an 8-digit counter, e.g. EFH-00000001. Leave prefix blank to auto-generate from the business name.</p>

                <div class="admin-business-row">
                    <div>
                        <label for="business_category">Category</label>
                        <select id="business_category" name="business_category">
                            <option>Restaurant</option>
                        </select>
                    </div>
                    <div>
                        <label for="status">Business Status</label>
                        <select id="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <p class="admin-business-hint">This controls whether the business account itself is enabled.</p>
                    </div>
                </div>

                <div class="admin-business-row">
                    <div>
                        <label for="subscription_status_view">Subscription Status</label>
                        <div id="subscription_status_view" class="admin-business-readonly">No subscription on file</div>
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <p class="admin-business-hint" style="margin-top:10px;">Trial and expired states are managed automatically from the partner subscription record.</p>
                    </div>
                </div>

                <h3 class="portal-profile-section-title">Business Address</h3>
                <div class="admin-business-address-grid portal-profile-address-grid">
                    <div class="portal-login-field portal-profile-field-full">
                        <label for="address">Complete Address *</label>
                        <input type="text" id="address" placeholder="House no., street" autocomplete="street-address" required>
                    </div>
                    <div class="portal-login-field">
                        <label for="country">Country</label>
                        <select id="country" data-ph-id="<?php echo (int)$phCountryId; ?>">
                            <?php echo fh_geo_options_html($geoCountries, 'country_id', 'country_name', $defaultCountry, 'Select country'); ?>
                        </select>
                    </div>
                    <div class="portal-login-field" id="province_field">
                        <label for="province">Province</label>
                        <select id="province">
                            <?php echo fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $defaultProvince, 'Select province'); ?>
                        </select>
                        <input type="text" id="province_text" placeholder="Enter province / state" style="display:none;">
                    </div>
                    <div class="portal-login-field" id="town_field">
                        <label for="town">Town / City</label>
                        <select id="town">
                            <?php echo fh_geo_options_html($geoTowns, 'town_id', 'town_name', $defaultTown, 'Select town / city'); ?>
                        </select>
                        <input type="text" id="town_text" placeholder="Enter town / city" style="display:none;">
                    </div>
                    <div class="portal-login-field" id="barangay_field">
                        <label for="barangay">Barangay</label>
                        <select id="barangay"<?php echo $hasBarangayList ? '' : ' style="display:none;"'; ?>>
                            <?php echo fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', '', 'Select barangay'); ?>
                        </select>
                        <input type="text" id="barangay_text" placeholder="Enter barangay"<?php echo $hasBarangayList ? ' style="display:none;"' : ''; ?>>
                    </div>
                    <div class="portal-login-field">
                        <label for="zipcode">Zipcode</label>
                        <input type="text" id="zipcode" value="<?php echo htmlspecialchars($defaultZipcode); ?>" placeholder="Zip / Postal code" autocomplete="postal-code" inputmode="numeric">
                    </div>
                </div>

                <h3 class="portal-profile-section-title">Contact Details</h3>
                <div class="admin-business-row">
                    <div>
                        <label for="contactperson">Contact Person</label>
                        <input type="text" id="contactperson" placeholder="Owner or manager name">
                    </div>
                    <div>
                        <label for="contactnumber">Contact Number</label>
                        <input type="tel" id="contactnumber" placeholder="09XXXXXXXXX">
                    </div>
                </div>
                <label for="email">Business Email</label>
                <input type="email" id="email" placeholder="orders@yourresto.com">
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveBusiness">Save</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#businessModal');
    var geoEndpoint = '../process.php';
    var defaultCountry = <?php echo json_encode($defaultCountry); ?>;
    var defaultProvince = <?php echo json_encode($defaultProvince); ?>;
    var defaultTown = <?php echo json_encode($defaultTown); ?>;
    var defaultZipcode = <?php echo json_encode($defaultZipcode); ?>;
    var defaultCountryHtml = <?php echo json_encode(fh_geo_options_html($geoCountries, 'country_id', 'country_name', $defaultCountry, 'Select country')); ?>;
    var defaultProvinceHtml = <?php echo json_encode(fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $defaultProvince, 'Select province')); ?>;
    var defaultTownHtml = <?php echo json_encode(fh_geo_options_html($geoTowns, 'town_id', 'town_name', $defaultTown, 'Select town / city')); ?>;
    var defaultBarangayHtml = <?php echo json_encode(fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', '', 'Select barangay')); ?>;
    var defaultHasBarangayList = <?php echo $hasBarangayList ? 'true' : 'false'; ?>;

    function openModal($el) {
        $el.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function closeModal($el) {
        $el.removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('.portal-order-modal.is-open').length) {
            $('body').css('overflow', '');
        }
    }

    function getProvince() {
        if ($('#province_text').is(':visible')) {
            return String($('#province_text').val() || '').trim();
        }
        return String($('#province').val() || '').trim();
    }

    function getTown() {
        if ($('#town_text').is(':visible')) {
            return String($('#town_text').val() || '').trim();
        }
        return String($('#town').val() || '').trim();
    }

    function getBarangay() {
        if ($('#barangay_text').is(':visible')) {
            return String($('#barangay_text').val() || '').trim();
        }
        return String($('#barangay').val() || '').trim();
    }

    function setBarangayMode(hasList) {
        if (hasList) {
            $('#barangay').show();
            $('#barangay_text').hide().val('');
        } else {
            $('#barangay').hide().html('<option value="">Select barangay</option>');
            $('#barangay_text').show();
        }
    }

    function setLocationMode(isPhilippines) {
        if (isPhilippines) {
            $('#province').show();
            $('#town').show();
            $('#province_text').hide().val('');
            $('#town_text').hide().val('');
        } else {
            $('#province').hide();
            $('#town').hide();
            $('#province_text').show();
            $('#town_text').show();
            setBarangayMode(false);
        }
    }

    function fillZipcodeFromTown() {
        var zip = '';
        if ($('#town').is(':visible')) {
            zip = String($('#town option:selected').data('zipcode') || '');
        }
        if (zip) {
            $('#zipcode').val(zip);
        }
    }

    function loadProvinces(countryId, selectedProvince, selectedTown, selectedBarangay) {
        var $province = $('#province');
        $province.prop('disabled', true).html('<option value="">Loading...</option>');
        $('#town').html('<option value="">Select town / city</option>');
        setBarangayMode(false);
        $.ajax({
            type: 'POST',
            url: geoEndpoint,
            data: { function: 'getProvinces', country_id: countryId },
            dataType: 'json',
            success: function (data) {
                var count = parseInt(data.count, 10) || 0;
                setLocationMode(count > 0);
                if (count > 0) {
                    $province.html(data.msg).prop('disabled', false);
                    if (selectedProvince) {
                        $province.val(selectedProvince);
                        var provinceId = $province.find('option:selected').data('id');
                        if (provinceId) {
                            loadTowns(provinceId, selectedTown, selectedBarangay);
                        }
                    }
                } else {
                    $province.html('<option value="">Select province</option>').prop('disabled', false);
                }
            },
            error: function () {
                $province.html('<option value="">Select province</option>').prop('disabled', false);
                setLocationMode(false);
            }
        });
    }

    function loadTowns(provinceId, selectedTown, selectedBarangay) {
        var $town = $('#town');
        $town.prop('disabled', true).html('<option value="">Loading...</option>');
        setBarangayMode(false);
        $.ajax({
            type: 'POST',
            url: geoEndpoint,
            data: { function: 'getTowns', province_id: provinceId },
            dataType: 'json',
            success: function (data) {
                $town.html(data.msg || '<option value="">Select town / city</option>').prop('disabled', false);
                if (selectedTown) {
                    $town.val(selectedTown);
                    fillZipcodeFromTown();
                    var townId = $town.find('option:selected').data('id');
                    if (townId) {
                        loadBarangays(townId, selectedBarangay);
                    }
                }
            },
            error: function () {
                $town.html('<option value="">Select town / city</option>').prop('disabled', false);
            }
        });
    }

    function loadBarangays(townId, selectedBarangay) {
        var $barangay = $('#barangay');
        $barangay.prop('disabled', true).html('<option value="">Loading...</option>');
        $.ajax({
            type: 'POST',
            url: geoEndpoint,
            data: { function: 'getBarangays', town_id: townId },
            dataType: 'json',
            success: function (data) {
                var count = parseInt(data.count, 10) || 0;
                if (count > 0) {
                    setBarangayMode(true);
                    $barangay.html(data.msg).prop('disabled', false);
                    if (selectedBarangay) {
                        $barangay.val(selectedBarangay);
                    }
                } else {
                    $barangay.prop('disabled', false);
                    setBarangayMode(false);
                    if (selectedBarangay) {
                        $('#barangay_text').val(selectedBarangay);
                    }
                }
            },
            error: function () {
                $barangay.prop('disabled', false);
                setBarangayMode(false);
            }
        });
    }

    function applyAddressDefaults() {
        $('#country').html(defaultCountryHtml).val(defaultCountry);
        $('#province').html(defaultProvinceHtml).show().val(defaultProvince);
        $('#town').html(defaultTownHtml).show().val(defaultTown);
        $('#province_text, #town_text').hide().val('');
        $('#zipcode').val(defaultZipcode);
        if (defaultHasBarangayList) {
            $('#barangay').html(defaultBarangayHtml).show();
            $('#barangay_text').hide().val('');
        } else {
            setBarangayMode(false);
        }
        setLocationMode(true);
    }

    function fillAddressFromBusiness(d) {
        var country = d.business_country || defaultCountry;
        var province = d.business_province || '';
        var town = d.business_city || '';
        var barangay = d.business_barangay || '';
        var zipcode = d.business_zipcode || '';

        $('#address').val(d.business_address || '');
        $('#zipcode').val(zipcode);
        $('#country').val(country);

        var phId = parseInt($('#country').data('ph-id'), 10) || 0;
        var countryId = $('#country').find('option:selected').data('id') || 0;
        var isPh = country === 'Philippines' || parseInt(countryId, 10) === phId;

        if (isPh) {
            setLocationMode(true);
            loadProvinces(countryId || phId, province, town, barangay);
        } else {
            setLocationMode(false);
            $('#province_text').val(province);
            $('#town_text').val(town);
            $('#barangay_text').val(barangay);
            setBarangayMode(false);
        }
    }

    function resetForm() {
        $('#business_id').val(0);
        $('#business_name, #subdomain, #address, #contactperson, #contactnumber, #email, #receipt_prefix, #receipt_next').val('');
        $('#status').val('1');
        $('#subscription_status_view').text('No subscription on file');
        $('#business_category').val('Restaurant');
        $('#businessFormMsg').hide().text('');
        $('#businessModalTitle').text('Add Business');
        applyAddressDefaults();
    }

    function showBusinessForm(businessId) {
        businessId = businessId || 0;
        resetForm();
        $('#business_id').val(businessId);
        $('#businessModalTitle').text(businessId ? 'Edit Business #' + businessId : 'Add Business');

        if (!businessId) {
            openModal($modal);
            return;
        }

        $.post('process.php', { function: 'LoadBusiness', business_id: businessId }, function (res) {
            if (!res.msg || !res.data) {
                $('#businessFormMsg').text(res.error || 'Could not load business.').show();
                openModal($modal);
                return;
            }
            var d = res.data;
            $('#business_id').val(d.business_id);
            $('#business_name').val(d.business_name || '');
            $('#subdomain').val(d.business_subdomain || '');
            $('#receipt_prefix').val(d.business_receipt_prefix || '');
            $('#receipt_next').val(d.business_receipt_next || '1');
            $('#contactperson').val(d.business_cont_person || '');
            $('#contactnumber').val(d.business_contact || '');
            $('#email').val(d.business_email || '');
            $('#status').val(String(parseInt(d.business_status, 10) === 1 ? 1 : 0));
            $('#subscription_status_view').text(d.portal_status_label || 'No subscription on file');
            fillAddressFromBusiness(d);
            openModal($modal);
        }, 'json').fail(function () {
            $('#businessFormMsg').text('Could not load business.').show();
            openModal($modal);
        });
    }

    function saveBusiness() {
        var $btn = $('#btnSaveBusiness').prop('disabled', true);
        $('#businessFormMsg').hide().text('');

        if (!$('#business_name').val().trim() || !$('#subdomain').val().trim() || !$('#address').val().trim()) {
            $('#businessFormMsg').text('Business name, sub-domain, and address are required.').show();
            $btn.prop('disabled', false);
            return;
        }

        $.post('process.php', {
            function: 'UpdateBusiness',
            business_id: $('#business_id').val(),
            business_name: $('#business_name').val(),
            address: $('#address').val(),
            city: getTown(),
            barangay: getBarangay(),
            province: getProvince(),
            country: $('#country').val(),
            zipcode: $('#zipcode').val(),
            email: $('#email').val(),
            contactperson: $('#contactperson').val(),
            contactnumber: $('#contactnumber').val(),
            status: $('#status').val(),
            subdomain: $('#subdomain').val(),
            receipt_prefix: $('#receipt_prefix').val(),
            receipt_next: $('#receipt_next').val()
        }, function (res) {
            if (res.msg) {
                location.reload();
                return;
            }
            $('#businessFormMsg').text(res.error || 'Could not save business.').show();
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $('#businessFormMsg').text('Something went wrong. Please try again.').show();
            $btn.prop('disabled', false);
        });
    }

    $('#btnAddBusiness').on('click', function () {
        showBusinessForm(0);
    });

    $(document).on('click', '.btn-business-edit', function () {
        showBusinessForm(parseInt($(this).data('businessid'), 10) || 0);
    });

    $('#country').on('change', function () {
        var countryId = $(this).find('option:selected').data('id') || 0;
        var countryName = $(this).val();
        var phId = parseInt($(this).data('ph-id'), 10) || 0;
        if (countryName === 'Philippines' || parseInt(countryId, 10) === phId) {
            setLocationMode(true);
            loadProvinces(countryId || phId);
        } else {
            setLocationMode(false);
            $('#province').html('<option value="">Select province</option>');
            $('#town').html('<option value="">Select town / city</option>');
            setBarangayMode(false);
        }
    });

    $('#province').on('change', function () {
        var provinceId = $(this).find('option:selected').data('id') || 0;
        if (provinceId) {
            loadTowns(provinceId);
        } else {
            $('#town').html('<option value="">Select town / city</option>');
            $('#zipcode').val('');
            setBarangayMode(false);
        }
    });

    $('#town').on('change', function () {
        fillZipcodeFromTown();
        var townId = $(this).find('option:selected').data('id');
        if (townId) {
            loadBarangays(townId);
        } else {
            setBarangayMode(false);
        }
    });

    $('#btnSaveBusiness').on('click', saveBusiness);

    $('.portal-order-modal-close, .portal-order-modal-dismiss').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $('.portal-order-modal-backdrop').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal($('.portal-order-modal.is-open').last());
        }
    });
})(jQuery);
</script>
<?php
include('footer.php');
?>
