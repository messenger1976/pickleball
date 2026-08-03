<?php
include('config.php');
fh_auth_require_super_admin();

$customersJoin = "
    FROM customers c
    LEFT JOIN company co ON c.customers_company = co.id
    LEFT JOIN department d ON c.customers_department = d.id";

function customers_search_where($conn, $q) {
    $q = trim($q);
    if ($q === '') {
        return '';
    }
    $safe = $conn->real_escape_string($q);
    $like = "'%" . $safe . "%'";
    return "WHERE (
        c.customers_email LIKE $like OR
        c.customers_firstname LIKE $like OR
        c.customers_lastname LIKE $like OR
        c.customers_phone LIKE $like OR
        co.name LIKE $like OR
        d.name LIKE $like
    )";
}

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'verified', 'unverified'), true)
    ? $_GET['status']
    : 'all';
$whereSql = customers_search_where($conn, $searchQ);

if ($filterStatus === 'verified') {
    $whereSql .= ($whereSql === '' ? 'WHERE ' : ' AND ') . 'c.customers_verify=1';
} elseif ($filterStatus === 'unverified') {
    $whereSql .= ($whereSql === '' ? 'WHERE ' : ' AND ') . '(c.customers_verify=0 OR c.customers_verify IS NULL)';
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = $conn->query(
        "SELECT c.customers_id, c.customers_email, c.customers_firstname, c.customers_lastname,
                c.customers_phone, c.customers_complete_address, c.customers_verify,
                co.name AS company_name, d.name AS department_name
         $customersJoin
         $whereSql
         ORDER BY c.customers_lastname, c.customers_firstname, c.customers_email"
    );

    $filename = 'customers-' . date('Y-m-d') . ($searchQ !== '' || $filterStatus !== 'all' ? '-filtered' : '') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, array(
        'ID', 'Email', 'First Name', 'Last Name', 'Phone',
        'Company', 'Department', 'Address', 'Status'
    ));

    if ($exportSql) {
        while ($row = $exportSql->fetch_assoc()) {
            fputcsv($out, array(
                (int)$row['customers_id'],
                $row['customers_email'],
                $row['customers_firstname'],
                $row['customers_lastname'],
                $row['customers_phone'],
                $row['company_name'],
                $row['department_name'],
                $row['customers_complete_address'],
                (int)$row['customers_verify'] === 1 ? 'Verified' : 'Unverified',
            ));
        }
    }
    fclose($out);
    exit;
}

$allowedSort = array(
    'email' => 'c.customers_email',
    'lastname' => 'c.customers_lastname',
    'firstname' => 'c.customers_firstname',
    'phone' => 'c.customers_phone',
    'company' => 'co.name',
    'department' => 'd.name',
    'status' => 'c.customers_verify',
);

$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'lastname';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$countResult = $conn->query("SELECT COUNT(*) AS c $customersJoin $whereSql");
$totalCustomers = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalCustomers > 0 ? (int)ceil($totalCustomers / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalCustomers > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalCustomers);

$verifiedResult = $conn->query("SELECT COUNT(*) AS c FROM customers WHERE customers_verify=1");
$verifiedCount = ($verifiedResult && ($vrow = $verifiedResult->fetch_assoc())) ? (int)$vrow['c'] : 0;
$allResult = $conn->query('SELECT COUNT(*) AS c FROM customers');
$allCount = ($allResult && ($arow = $allResult->fetch_assoc())) ? (int)$arow['c'] : 0;
$unverifiedCount = max(0, $allCount - $verifiedCount);

function customers_list_url($overrides = array()) {
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
    return 'customers.php?' . http_build_query($params);
}

function customers_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return customers_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function customers_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function customers_export_url() {
    global $searchQ, $filterStatus;
    $params = array('export' => 'csv', 'status' => $filterStatus);
    if ($searchQ !== '') {
        $params['q'] = $searchQ;
    }
    return 'customers.php?' . http_build_query($params);
}

$titleheader = 'Customers';

include_once(__DIR__ . '/../includes/geo_helper.php');
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
$selectedCountryId = $phCountryId;
$geoProvinces = $selectedCountryId > 0 ? fh_geo_get_provinces($conn, $selectedCountryId) : array();
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

$companysql = $conn->query('SELECT id, name FROM company ORDER BY name ASC');
$companies = array();
if ($companysql) {
    while ($crow = $companysql->fetch_assoc()) {
        $companies[] = $crow;
    }
}

$items = array();
$itemsql = $conn->query(
    "SELECT c.*, co.name AS company_name, d.name AS department_name
     $customersJoin
     $whereSql
     ORDER BY $sortCol $sortDir, c.customers_email ASC
     LIMIT $offset, $perPage"
);
if ($itemsql) {
    while ($row = $itemsql->fetch_assoc()) {
        $items[] = $row;
    }
}

include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260714b" type="text/css" />
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
.admin-orders-filter .admin-customers-filter-spacer {
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
.admin-customers-toolbar-btn {
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
.admin-customers-toolbar-btn:hover {
    background: #1b4332;
    color: #fff;
    text-decoration: none;
}
.admin-customers-toolbar-btn.is-secondary {
    background: #fff;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.admin-customers-toolbar-btn.is-secondary:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.admin-customers-tip {
    margin: 0 0 16px;
    padding: 12px 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    color: #065f46;
    font-size: 13px;
}
.portal-order-modal-dialog-customer {
    width: min(720px, calc(100% - 32px));
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
.portal-order-modal-section > input[type="password"],
.portal-order-modal-section .admin-customers-row input {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
}
.portal-order-modal-section .admin-customers-address-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
    margin-top: 4px;
}
.portal-order-modal-section .admin-customers-address-grid .portal-login-field {
    margin-bottom: 2px;
}
.portal-order-modal-section .admin-customers-address-grid .portal-profile-field-full {
    grid-column: 1 / -1;
}
.portal-order-modal-section .admin-customers-address-grid label {
    display: block;
    margin: 0 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-customers-address-grid input,
.portal-order-modal-section .admin-customers-address-grid select {
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
    margin: 8px 0 10px;
}
.portal-order-modal-section .admin-customers-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.portal-order-modal-section .admin-customers-row label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-customers-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 10px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.portal-order-modal-section .admin-customers-check input {
    width: auto;
    margin: 2px 0 0;
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
@media (max-width: 640px) {
    .portal-order-modal-section .admin-customers-row,
    .portal-order-modal-section .admin-customers-address-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Customers</h1>
        <p>Manage portal customer accounts, contact details, and company assignments.</p>
    </div>

    <p class="admin-customers-tip">
        <strong>Tip:</strong> New customers added here are marked as verified and can sign in immediately.
        Password is required when creating an account; leave blank when editing to keep the current password.
    </p>

    <form class="admin-orders-filter" method="get" action="customers.php">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Name, email, phone, company…">
        </div>
        <div>
            <label for="filterStatus">Status</label>
            <select id="filterStatus" name="status">
                <option value="all"<?php echo $filterStatus === 'all' ? ' selected' : ''; ?>>All customers</option>
                <option value="verified"<?php echo $filterStatus === 'verified' ? ' selected' : ''; ?>>Verified</option>
                <option value="unverified"<?php echo $filterStatus === 'unverified' ? ' selected' : ''; ?>>Unverified</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Apply Filter</button>
        </div>
        <div class="admin-customers-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <a class="admin-customers-toolbar-btn is-secondary" href="<?php echo htmlspecialchars(customers_export_url()); ?>">Export CSV</a>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-customers-toolbar-btn" id="btnAddCustomer">Add Customer</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalCustomers); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterStatus !== 'all') ? ' (filtered)' : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($verifiedCount); ?></strong>
            <span>Verified</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($unverifiedCount); ?></strong>
            <span>Unverified</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Customer Accounts</h2>
            <?php if ($totalCustomers > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalCustomers); ?> customer<?php echo $totalCustomers === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($items)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">👥</div>
                <h2>No customers found</h2>
                <p><?php echo ($searchQ !== '' || $filterStatus !== 'all')
                    ? 'No customers match this filter. Try another search or status.'
                    : 'No customers yet. Click <strong>Add Customer</strong> to create one.'; ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalCustomers); ?></strong> customer<?php echo $totalCustomers === 1 ? '' : 's'; ?>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="customers.php">
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
                <table class="portal-orders-table" id="customerTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('email')); ?>">
                                    Email <?php echo customers_sort_indicator('email'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('lastname')); ?>">
                                    Last Name <?php echo customers_sort_indicator('lastname'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('firstname')); ?>">
                                    First Name <?php echo customers_sort_indicator('firstname'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('phone')); ?>">
                                    Phone <?php echo customers_sort_indicator('phone'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('company')); ?>">
                                    Company <?php echo customers_sort_indicator('company'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('department')); ?>">
                                    Department <?php echo customers_sort_indicator('department'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(customers_sort_url('status')); ?>">
                                    Status <?php echo customers_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row):
                            $customerId = (int)$row['customers_id'];
                            $isVerified = (int)$row['customers_verify'] === 1;
                        ?>
                        <tr class="portal-orders-row" data-customer-id="<?php echo $customerId; ?>">
                            <td><?php echo htmlspecialchars($row['customers_email']); ?></td>
                            <td><?php echo htmlspecialchars($row['customers_lastname'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['customers_firstname'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['customers_phone'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['company_name'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['department_name'] ?: '—'); ?></td>
                            <td>
                                <?php if ($isVerified): ?>
                                    <span class="portal-orders-status is-success">Verified</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-warning">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-customer-edit" data-customerid="<?php echo $customerId; ?>" title="Edit customer" aria-label="Edit customer #<?php echo $customerId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel btn-customer-delete" data-customerid="<?php echo $customerId; ?>" title="Delete customer" aria-label="Delete customer #<?php echo $customerId; ?>">✕</button>
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
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(customers_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(customers_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(customers_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="customerModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-customer" role="dialog" aria-labelledby="customerModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="customerModalTitle">Add Customer</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="customerFormMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-section">
                <input type="hidden" id="customers_id" value="0">
                <input type="hidden" id="customers_company" value="0">
                <input type="hidden" id="customers_department" value="0">

                <label for="customers_email">Email *</label>
                <input type="email" id="customers_email" maxlength="120" required>

                <label for="customers_password">Password <span id="passwordHint" class="portal-order-modal-hint" style="display:inline;margin:0;">(required for new customers)</span></label>
                <input type="password" id="customers_password" autocomplete="new-password">

                <div class="admin-customers-row">
                    <div>
                        <label for="customers_firstname">First Name</label>
                        <input type="text" id="customers_firstname">
                    </div>
                    <div>
                        <label for="customers_lastname">Last Name</label>
                        <input type="text" id="customers_lastname">
                    </div>
                </div>

                <label for="customers_phone">Phone</label>
                <input type="text" id="customers_phone" placeholder="e.g. 0915xxxxxxx">

                <h3 class="portal-profile-section-title">Delivery Address</h3>
                <div class="admin-customers-address-grid portal-profile-address-grid">
                    <div class="portal-login-field portal-profile-field-full">
                        <label for="customers_complete_address">Complete Address</label>
                        <input type="text" id="customers_complete_address" placeholder="House no., street" autocomplete="street-address">
                    </div>
                    <div class="portal-login-field">
                        <label for="customers_country">Country</label>
                        <select id="customers_country" data-ph-id="<?php echo (int)$phCountryId; ?>">
                            <?php echo fh_geo_options_html($geoCountries, 'country_id', 'country_name', $defaultCountry, 'Select country'); ?>
                        </select>
                    </div>
                    <div class="portal-login-field" id="customers_province_field">
                        <label for="customers_province">Province</label>
                        <select id="customers_province">
                            <?php echo fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $defaultProvince, 'Select province'); ?>
                        </select>
                        <input type="text" id="customers_province_text" placeholder="Enter province / state" style="display:none;">
                    </div>
                    <div class="portal-login-field" id="customers_town_field">
                        <label for="customers_town">Town / City</label>
                        <select id="customers_town">
                            <?php echo fh_geo_options_html($geoTowns, 'town_id', 'town_name', $defaultTown, 'Select town / city'); ?>
                        </select>
                        <input type="text" id="customers_town_text" placeholder="Enter town / city" style="display:none;">
                    </div>
                    <div class="portal-login-field">
                        <label for="customers_zipcode">Zipcode</label>
                        <input type="text" id="customers_zipcode" value="<?php echo htmlspecialchars($defaultZipcode); ?>" placeholder="Zip / Postal code" autocomplete="postal-code" inputmode="numeric">
                    </div>
                    <div class="portal-login-field portal-profile-field-full" id="customers_barangay_field">
                        <label for="customers_barangay">Barangay</label>
                        <select id="customers_barangay"<?php echo $hasBarangayList ? '' : ' style="display:none;"'; ?>>
                            <?php echo fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', '', 'Select barangay'); ?>
                        </select>
                        <input type="text" id="customers_barangay_text" placeholder="Enter barangay"<?php echo $hasBarangayList ? ' style="display:none;"' : ''; ?>>
                    </div>
                </div>

                <label class="admin-customers-check" for="customers_verify">
                    <input type="checkbox" id="customers_verify" checked>
                    <span>Account verified (can sign in)</span>
                </label>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveCustomer">Save Customer</button>
        </div>
    </div>
</div>

<div class="portal-order-modal" id="customerDeleteModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Delete Customer <span id="customerDeleteId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="customerDeleteMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Are you sure you want to delete this customer? This cannot be undone.</p>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep Customer</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnConfirmDeleteCustomer">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#customerModal');
    var $deleteModal = $('#customerDeleteModal');
    var pendingDeleteId = 0;
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

    function getCountry() {
        return String($('#customers_country').val() || '').trim();
    }

    function getProvince() {
        if ($('#customers_province_text').is(':visible')) {
            return String($('#customers_province_text').val() || '').trim();
        }
        return String($('#customers_province').val() || '').trim();
    }

    function getTown() {
        if ($('#customers_town_text').is(':visible')) {
            return String($('#customers_town_text').val() || '').trim();
        }
        return String($('#customers_town').val() || '').trim();
    }

    function getBarangay() {
        if ($('#customers_barangay_text').is(':visible')) {
            return String($('#customers_barangay_text').val() || '').trim();
        }
        return String($('#customers_barangay').val() || '').trim();
    }

    function setBarangayMode(hasList) {
        if (hasList) {
            $('#customers_barangay').show();
            $('#customers_barangay_text').hide().val('');
        } else {
            $('#customers_barangay').hide().html('<option value="">Select barangay</option>');
            $('#customers_barangay_text').show();
        }
    }

    function setLocationMode(isPhilippines) {
        if (isPhilippines) {
            $('#customers_province').show();
            $('#customers_town').show();
            $('#customers_province_text').hide().val('');
            $('#customers_town_text').hide().val('');
        } else {
            $('#customers_province').hide();
            $('#customers_town').hide();
            $('#customers_province_text').show();
            $('#customers_town_text').show();
            setBarangayMode(false);
        }
    }

    function fillZipcodeFromTown() {
        var zip = '';
        if ($('#customers_town').is(':visible')) {
            zip = String($('#customers_town option:selected').data('zipcode') || '');
        }
        if (zip) {
            $('#customers_zipcode').val(zip);
        }
    }

    function loadProvinces(countryId, selectedProvince, selectedTown, selectedBarangay) {
        var $province = $('#customers_province');
        $province.prop('disabled', true).html('<option value="">Loading...</option>');
        $('#customers_town').html('<option value="">Select town / city</option>');
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
        var $town = $('#customers_town');
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
        var $barangay = $('#customers_barangay');
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
                        $('#customers_barangay_text').val(selectedBarangay);
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
        $('#customers_country').html(defaultCountryHtml).val(defaultCountry);
        $('#customers_province').html(defaultProvinceHtml).show().val(defaultProvince);
        $('#customers_town').html(defaultTownHtml).show().val(defaultTown);
        $('#customers_province_text, #customers_town_text').hide().val('');
        $('#customers_zipcode').val(defaultZipcode);
        if (defaultHasBarangayList) {
            $('#customers_barangay').html(defaultBarangayHtml).show();
            $('#customers_barangay_text').hide().val('');
        } else {
            setBarangayMode(false);
        }
        setLocationMode(true);
    }

    function fillAddressFromCustomer(d) {
        var country = d.customers_country || defaultCountry;
        var province = d.customers_province || '';
        var town = d.customers_town || '';
        var barangay = d.customers_barangay || '';
        var zipcode = d.customers_zipcode || '';

        $('#customers_complete_address').val(d.customers_complete_address || '');
        $('#customers_zipcode').val(zipcode);
        $('#customers_country').val(country);

        var phId = parseInt($('#customers_country').data('ph-id'), 10) || 0;
        var countryId = $('#customers_country').find('option:selected').data('id') || 0;
        var isPh = country === 'Philippines' || parseInt(countryId, 10) === phId;

        if (isPh) {
            setLocationMode(true);
            if (countryId || phId) {
                loadProvinces(countryId || phId, province, town, barangay);
            }
        } else {
            setLocationMode(false);
            $('#customers_province_text').val(province);
            $('#customers_town_text').val(town);
            $('#customers_barangay_text').val(barangay);
            setBarangayMode(false);
        }
    }

    function resetForm() {
        $('#customers_id').val(0);
        $('#customers_email').val('');
        $('#customers_password').val('');
        $('#customers_firstname, #customers_lastname, #customers_phone, #customers_complete_address').val('');
        $('#customers_company').val(0);
        $('#customers_department').val(0);
        $('#customers_verify').prop('checked', true);
        $('#customerFormMsg').hide().text('');
        $('#customerModalTitle').text('Add Customer');
        $('#passwordHint').text('(required for new customers)');
        applyAddressDefaults();
    }

    function showCustomerForm(customerId) {
        customerId = customerId || 0;
        resetForm();
        $('#customers_id').val(customerId);
        $('#customerModalTitle').text(customerId ? 'Edit Customer #' + customerId : 'Add Customer');
        $('#passwordHint').text(customerId ? '(leave blank to keep current password)' : '(required for new customers)');

        if (!customerId) {
            openModal($modal);
            return;
        }

        $.post('process.php', { function: 'LoadCustomer', customers_id: customerId }, function (res) {
            if (res.validation_status !== 'T' || !res.data) {
                $('#customerFormMsg').text(res.msg || 'Could not load customer.').show();
                openModal($modal);
                return;
            }
            var d = res.data;
            $('#customers_id').val(d.customers_id);
            $('#customers_email').val(d.customers_email || '');
            $('#customers_firstname').val(d.customers_firstname || '');
            $('#customers_lastname').val(d.customers_lastname || '');
            $('#customers_phone').val(d.customers_phone || '');
            $('#customers_company').val(d.customers_company || 0);
            $('#customers_department').val(d.customers_department || 0);
            $('#customers_verify').prop('checked', parseInt(d.customers_verify, 10) === 1);
            fillAddressFromCustomer(d);
            openModal($modal);
        }, 'json').fail(function () {
            $('#customerFormMsg').text('Could not load customer.').show();
            openModal($modal);
        });
    }

    function saveCustomer() {
        var $btn = $('#btnSaveCustomer').prop('disabled', true);
        $('#customerFormMsg').hide().text('');
        $.post('process.php', {
            function: 'SaveCustomer',
            customers_id: $('#customers_id').val(),
            customers_email: $('#customers_email').val(),
            customers_password: $('#customers_password').val(),
            customers_firstname: $('#customers_firstname').val(),
            customers_lastname: $('#customers_lastname').val(),
            customers_phone: $('#customers_phone').val(),
            customers_company: $('#customers_company').val() || 0,
            customers_department: $('#customers_department').val() || 0,
            customers_complete_address: $('#customers_complete_address').val(),
            customers_country: getCountry(),
            customers_province: getProvince(),
            customers_town: getTown(),
            customers_barangay: getBarangay(),
            customers_zipcode: $('#customers_zipcode').val(),
            customers_verify: $('#customers_verify').is(':checked') ? 1 : 0
        }, function (res) {
            if (res.validation_status === 'T') {
                location.reload();
                return;
            }
            $('#customerFormMsg').text(res.msg || 'Could not save customer.').show();
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $('#customerFormMsg').text('Something went wrong. Please try again.').show();
            $btn.prop('disabled', false);
        });
    }

    function showDelete(customerId) {
        pendingDeleteId = customerId;
        $('#customerDeleteId').text('#' + customerId);
        $('#customerDeleteMsg').hide().text('');
        openModal($deleteModal);
    }

    $('#btnAddCustomer').on('click', function () {
        showCustomerForm(0);
    });

    $(document).on('click', '.btn-customer-edit', function () {
        showCustomerForm(parseInt($(this).data('customerid'), 10) || 0);
    });

    $(document).on('click', '.btn-customer-delete', function () {
        showDelete(parseInt($(this).data('customerid'), 10) || 0);
    });

    $('#customers_country').on('change', function () {
        var countryId = $(this).find('option:selected').data('id') || 0;
        var countryName = $(this).val();
        var phId = parseInt($(this).data('ph-id'), 10) || 0;
        if (countryName === 'Philippines' || parseInt(countryId, 10) === phId) {
            setLocationMode(true);
            loadProvinces(countryId || phId);
        } else {
            setLocationMode(false);
            $('#customers_province').html('<option value="">Select province</option>');
            $('#customers_town').html('<option value="">Select town / city</option>');
            setBarangayMode(false);
        }
    });

    $('#customers_province').on('change', function () {
        var provinceId = $(this).find('option:selected').data('id') || 0;
        if (provinceId) {
            loadTowns(provinceId);
        } else {
            $('#customers_town').html('<option value="">Select town / city</option>');
            $('#customers_zipcode').val('');
            setBarangayMode(false);
        }
    });

    $('#customers_town').on('change', function () {
        fillZipcodeFromTown();
        var townId = $(this).find('option:selected').data('id');
        if (townId) {
            loadBarangays(townId);
        } else {
            setBarangayMode(false);
        }
    });

    $('#btnSaveCustomer').on('click', saveCustomer);

    $('#btnConfirmDeleteCustomer').on('click', function () {
        if (!pendingDeleteId) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.post('process.php', { function: 'DeleteCustomer', customers_id: pendingDeleteId }, function (res) {
            if (res.validation_status === 'T') {
                location.reload();
                return;
            }
            $('#customerDeleteMsg').text(res.msg || 'Could not delete customer.').show();
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $('#customerDeleteMsg').text('Something went wrong. Please try again.').show();
            $btn.prop('disabled', false);
        });
    });

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

<?php include('footer.php'); ?>
