<?php
include('config.php');
include_once(__DIR__ . '/../includes/court_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

function admin_courts_redirect($extra = array()) {
    global $adminBusinessId;
    $params = array_merge(array('business_id' => (int)$adminBusinessId), $extra);
    header('Location: courts.php?' . http_build_query($params));
    exit;
}

if (isset($_POST['btnSave'])) {
    $courtId = isset($_POST['court_id']) ? (int)$_POST['court_id'] : 0;
    $name = trim(isset($_POST['court_name']) ? $_POST['court_name'] : '');
    $type = isset($_POST['court_type']) ? $_POST['court_type'] : 'outdoor';
    $surface = isset($_POST['court_surface']) ? $_POST['court_surface'] : 'hard';
    $capacity = max(1, (int)(isset($_POST['court_capacity']) ? $_POST['court_capacity'] : 4));
    $price = (float)(isset($_POST['price_per_slot']) ? $_POST['price_per_slot'] : 0);
    $slotLen = max(15, (int)(isset($_POST['slot_length_minutes']) ? $_POST['slot_length_minutes'] : 60));
    $buffer = max(0, (int)(isset($_POST['buffer_minutes']) ? $_POST['buffer_minutes'] : 0));
    $status = isset($_POST['court_status']) ? 1 : 0;
    $businessId = $adminBusinessId;

    if ($name === '') {
        admin_courts_redirect();
    }

    $types = pb_court_types();
    $surfaces = pb_court_surfaces();
    if (!isset($types[$type])) {
        $type = 'outdoor';
    }
    if (!isset($surfaces[$surface])) {
        $surface = 'hard';
    }

    $photo = '';
    if ($courtId > 0) {
        $existing = pb_court_fetch($conn, $courtId);
        if (!$existing || (int)$existing['business_id'] !== $businessId) {
            admin_courts_redirect();
        }
        $photo = isset($existing['court_photo']) ? $existing['court_photo'] : '';
    }

    if (!empty($_FILES['court_photo']['name'])) {
        $targetDir = 'images/';
        $targetFile = $targetDir . basename($_FILES['court_photo']['name']);
        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true)) {
            if (move_uploaded_file($_FILES['court_photo']['tmp_name'], $targetFile)) {
                $photo = $targetFile;
            }
        }
    }

    $safeName = $conn->real_escape_string($name);
    $safeType = $conn->real_escape_string($type);
    $safeSurface = $conn->real_escape_string($surface);
    $safePhoto = $conn->real_escape_string($photo);

    if ($courtId > 0) {
        $conn->query(
            "UPDATE courts SET
                court_name='$safeName',
                court_type='$safeType',
                court_surface='$safeSurface',
                court_capacity=$capacity,
                court_photo='$safePhoto',
                price_per_slot=$price,
                slot_length_minutes=$slotLen,
                buffer_minutes=$buffer,
                court_status=$status
             WHERE court_id=$courtId AND business_id=$businessId"
        );
    } else {
        $conn->query(
            "INSERT INTO courts
             (business_id, court_name, court_type, court_surface, court_capacity, court_photo,
              price_per_slot, slot_length_minutes, buffer_minutes, court_status)
             VALUES
             ($businessId, '$safeName', '$safeType', '$safeSurface', $capacity, '$safePhoto',
              $price, $slotLen, $buffer, $status)"
        );
        $newId = (int)$conn->insert_id;
        if ($newId > 0) {
            pb_seed_default_week_rules($conn, $newId);
        }
    }
    admin_courts_redirect();
}

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'active', 'inactive'), true)
    ? $_GET['status']
    : 'all';
$filterType = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$types = pb_court_types();
$surfaces = pb_court_surfaces();
if ($filterType !== 'all' && !isset($types[$filterType])) {
    $filterType = 'all';
}

$allowedSort = array(
    'name' => 'court_name',
    'type' => 'court_type',
    'surface' => 'court_surface',
    'price' => 'price_per_slot',
    'status' => 'court_status',
);
$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'name';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$where = 'WHERE business_id=' . (int)$adminBusinessId;
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $where .= " AND (court_name LIKE '%$safe%' OR court_surface LIKE '%$safe%')";
}
if ($filterStatus === 'active') {
    $where .= ' AND court_status=1';
} elseif ($filterStatus === 'inactive') {
    $where .= ' AND court_status=0';
}
if ($filterType !== 'all') {
    $where .= " AND court_type='" . $conn->real_escape_string($filterType) . "'";
}

$countResult = $conn->query("SELECT COUNT(*) AS c FROM courts $where");
$totalCourts = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalCourts > 0 ? (int)ceil($totalCourts / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalCourts > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalCourts);

$scopeWhere = 'WHERE business_id=' . (int)$adminBusinessId;
$activeResult = $conn->query("SELECT COUNT(*) AS c FROM courts $scopeWhere AND court_status=1");
$activeCount = ($activeResult && ($arow = $activeResult->fetch_assoc())) ? (int)$arow['c'] : 0;
$inactiveResult = $conn->query("SELECT COUNT(*) AS c FROM courts $scopeWhere AND court_status=0");
$inactiveCount = ($inactiveResult && ($irow = $inactiveResult->fetch_assoc())) ? (int)$irow['c'] : 0;

$courts = array();
$sql = $conn->query(
    "SELECT * FROM courts $where
     ORDER BY $sortCol $sortDir, court_name ASC
     LIMIT $offset, $perPage"
);
if ($sql) {
    while ($row = $sql->fetch_assoc()) {
        $courts[] = $row;
    }
}

function admin_courts_list_url($overrides = array()) {
    global $page, $perPage, $searchQ, $filterStatus, $filterType, $sortKey, $sortDir, $adminBusinessId;
    $params = array(
        'business_id' => (int)$adminBusinessId,
        'page' => $page,
        'per_page' => $perPage,
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'status' => $filterStatus,
        'type' => $filterType,
    );
    if ($searchQ !== '') {
        $params['q'] = $searchQ;
    }
    $params = array_merge($params, $overrides);
    if (isset($params['q']) && $params['q'] === '') {
        unset($params['q']);
    }
    return 'courts.php?' . http_build_query($params);
}

function admin_courts_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return admin_courts_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_courts_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

$titleheader = 'Courts';
include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
<style>
.admin-courts-tip {
    margin: 0 0 16px;
    padding: 12px 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    color: #065f46;
    font-size: 13px;
    line-height: 1.5;
}
.admin-orders-filter .admin-courts-filter-spacer {
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
.admin-orders-filter input[type="search"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 150px;
}
.admin-orders-filter button,
.admin-courts-toolbar-btn {
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
.admin-courts-toolbar-btn:hover {
    background: #1b4332;
    color: #fff;
    text-decoration: none;
}
.admin-courts-toolbar-btn.is-secondary {
    background: #fff;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.admin-courts-toolbar-btn.is-secondary:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.admin-courts-head-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}
.admin-court-name {
    font-weight: 700;
    color: #0f172a;
}
.admin-court-meta {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.admin-court-thumb {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    object-fit: cover;
    background: #e2e8f0;
    vertical-align: middle;
    margin-right: 10px;
}
.admin-court-thumb-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #2d6a4f;
    font-size: 18px;
    vertical-align: middle;
    margin-right: 10px;
}
.admin-court-cell {
    display: flex;
    align-items: center;
}
.portal-order-modal-dialog-court {
    width: min(580px, calc(100% - 32px));
}
.portal-order-modal-section > label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section > input[type="text"],
.portal-order-modal-section > input[type="number"],
.portal-order-modal-section > input[type="file"],
.portal-order-modal-section > select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section .admin-courts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.portal-order-modal-section .admin-courts-row label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.portal-order-modal-section .admin-courts-row input,
.portal-order-modal-section .admin-courts-row select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
    background: #fff;
}
.portal-order-modal-section .admin-courts-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 4px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.portal-order-modal-section .admin-courts-check input {
    width: auto;
    margin: 2px 0 0;
}
.admin-court-photo-preview {
    margin: 0 0 14px;
    font-size: 12px;
    color: #64748b;
}
.admin-court-photo-preview img {
    display: block;
    max-width: 160px;
    max-height: 100px;
    border-radius: 8px;
    margin-top: 6px;
    object-fit: cover;
}
@media (max-width: 640px) {
    .portal-order-modal-section .admin-courts-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Courts</h1>
        <p>Add and manage pickleball courts for this venue — pricing, slot length, and photos.</p>
        <div class="admin-courts-head-actions">
            <a class="admin-courts-toolbar-btn is-secondary" href="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>">Availability</a>
            <button type="button" class="admin-courts-toolbar-btn" id="btnAddCourt">Add Court</button>
        </div>
    </div>

    <?php echo fh_admin_business_selector($conn, 'courts.php'); ?>

    <p class="admin-courts-tip">
        New courts get default weekly hours automatically. Use <strong>Availability</strong> to fine-tune open times and closures.
        Only <em>Active</em> courts appear for online booking.
    </p>

    <form class="admin-orders-filter" method="get" action="courts.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Court name or surface…">
        </div>
        <div>
            <label for="filterType">Type</label>
            <select id="filterType" name="type">
                <option value="all"<?php echo $filterType === 'all' ? ' selected' : ''; ?>>All types</option>
                <?php foreach ($types as $k => $label): ?>
                <option value="<?php echo htmlspecialchars($k); ?>"<?php echo $filterType === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filterStatus">Status</label>
            <select id="filterStatus" name="status">
                <option value="all"<?php echo $filterStatus === 'all' ? ' selected' : ''; ?>>All statuses</option>
                <option value="active"<?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
                <option value="inactive"<?php echo $filterStatus === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Apply Filter</button>
        </div>
        <div class="admin-courts-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-courts-toolbar-btn" id="btnAddCourtFilter">Add Court</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalCourts); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterStatus !== 'all' || $filterType !== 'all') ? ' (filtered)' : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($activeCount); ?></strong>
            <span>Active</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($inactiveCount); ?></strong>
            <span>Inactive</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Venue Courts</h2>
            <?php if ($totalCourts > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalCourts); ?> court<?php echo $totalCourts === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($courts)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">🎾</div>
                <h2>No courts found</h2>
                <p><?php echo ($searchQ !== '' || $filterStatus !== 'all' || $filterType !== 'all')
                    ? 'No courts match this filter. Try another search, type, or status.'
                    : 'No courts yet. Click <strong>Add Court</strong> to create your first bookable court.'; ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalCourts); ?></strong>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="courts.php">
                    <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>">
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
                <table class="portal-orders-table" id="courtTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_courts_sort_url('name')); ?>">
                                    Court <?php echo admin_courts_sort_indicator('name'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_courts_sort_url('type')); ?>">
                                    Type <?php echo admin_courts_sort_indicator('type'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_courts_sort_url('surface')); ?>">
                                    Surface <?php echo admin_courts_sort_indicator('surface'); ?>
                                </a>
                            </th>
                            <th>Slot</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_courts_sort_url('price')); ?>">
                                    Price <?php echo admin_courts_sort_indicator('price'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_courts_sort_url('status')); ?>">
                                    Status <?php echo admin_courts_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courts as $c):
                            $courtId = (int)$c['court_id'];
                            $isActive = (int)$c['court_status'] === 1;
                            $photo = !empty($c['court_photo']) ? $c['court_photo'] : '';
                            $typeLabel = isset($types[$c['court_type']]) ? $types[$c['court_type']] : ucfirst($c['court_type']);
                            $surfaceLabel = isset($surfaces[$c['court_surface']]) ? $surfaces[$c['court_surface']] : ucfirst(str_replace('_', ' ', $c['court_surface']));
                        ?>
                        <tr class="portal-orders-row" data-court-id="<?php echo $courtId; ?>">
                            <td>
                                <div class="admin-court-cell">
                                    <?php if ($photo !== ''): ?>
                                        <img class="admin-court-thumb" src="<?php echo htmlspecialchars($photo); ?>" alt="">
                                    <?php else: ?>
                                        <span class="admin-court-thumb-placeholder" aria-hidden="true">🎾</span>
                                    <?php endif; ?>
                                    <div>
                                        <div class="admin-court-name"><?php echo htmlspecialchars($c['court_name']); ?></div>
                                        <div class="admin-court-meta">Capacity <?php echo (int)$c['court_capacity']; ?> · #<?php echo $courtId; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($typeLabel); ?></td>
                            <td><?php echo htmlspecialchars($surfaceLabel); ?></td>
                            <td>
                                <?php echo (int)$c['slot_length_minutes']; ?> min
                                <?php if ((int)$c['buffer_minutes'] > 0): ?>
                                <div class="admin-court-meta">+<?php echo (int)$c['buffer_minutes']; ?> buffer</div>
                                <?php endif; ?>
                            </td>
                            <td><strong>₱<?php echo number_format((float)$c['price_per_slot'], 2); ?></strong></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="portal-orders-status is-success">Active</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-court-edit" data-court='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>' title="Edit court" aria-label="Edit court #<?php echo $courtId; ?>">✎</button>
                                    <a class="portal-orders-action-btn" href="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>&court_id=<?php echo $courtId; ?>" title="Manage hours" aria-label="Manage hours for court #<?php echo $courtId; ?>">📅</a>
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
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_courts_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_courts_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_courts_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="courtModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-court" role="dialog" aria-labelledby="courtModalTitle">
        <form method="post" enctype="multipart/form-data" action="courts.php?business_id=<?php echo (int)$adminBusinessId; ?>" id="courtForm">
            <div class="portal-order-modal-header">
                <h2 id="courtModalTitle">Add Court</h2>
                <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="portal-order-modal-body">
                <div class="portal-order-modal-section">
                    <input type="hidden" name="court_id" id="court_id" value="0">

                    <label for="court_name">Court name *</label>
                    <input type="text" name="court_name" id="court_name" maxlength="150" placeholder="e.g. Court 1 — Outdoor" required>

                    <div class="admin-courts-row">
                        <div>
                            <label for="court_type">Type</label>
                            <select name="court_type" id="court_type">
                                <?php foreach ($types as $k => $label): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="court_surface">Surface</label>
                            <select name="court_surface" id="court_surface">
                                <?php foreach ($surfaces as $k => $label): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="admin-courts-row">
                        <div>
                            <label for="court_capacity">Capacity (players)</label>
                            <input type="number" name="court_capacity" id="court_capacity" min="1" value="4">
                        </div>
                        <div>
                            <label for="price_per_slot">Price per slot (₱)</label>
                            <input type="number" step="0.01" min="0" name="price_per_slot" id="price_per_slot" value="300">
                        </div>
                    </div>

                    <div class="admin-courts-row">
                        <div>
                            <label for="slot_length_minutes">Slot length (minutes)</label>
                            <input type="number" name="slot_length_minutes" id="slot_length_minutes" min="15" step="15" value="60">
                        </div>
                        <div>
                            <label for="buffer_minutes">Buffer (minutes)</label>
                            <input type="number" name="buffer_minutes" id="buffer_minutes" min="0" value="0">
                        </div>
                    </div>

                    <label for="court_photo">Photo</label>
                    <div class="admin-court-photo-preview" id="courtPhotoPreview" style="display:none;">
                        Current photo:
                        <img id="courtPhotoPreviewImg" src="" alt="Court photo">
                    </div>
                    <input type="file" name="court_photo" id="court_photo" accept="image/*">
                    <p class="portal-order-modal-hint">JPG, PNG, or GIF. Leave blank to keep the existing photo when editing.</p>

                    <label class="admin-courts-check" for="court_status">
                        <input type="checkbox" name="court_status" id="court_status" value="1" checked>
                        <span>Active (bookable online)</span>
                    </label>
                </div>
            </div>
            <div class="portal-order-modal-footer">
                <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
                <button type="submit" name="btnSave" value="1" class="portal-login-btn portal-login-btn-primary">Save Court</button>
            </div>
        </form>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#courtModal');

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

    function resetForm() {
        $('#court_id').val(0);
        $('#court_name').val('');
        $('#court_type').val('outdoor');
        $('#court_surface').val('hard');
        $('#court_capacity').val(4);
        $('#price_per_slot').val(300);
        $('#slot_length_minutes').val(60);
        $('#buffer_minutes').val(0);
        $('#court_photo').val('');
        $('#court_status').prop('checked', true);
        $('#courtPhotoPreview').hide();
        $('#courtPhotoPreviewImg').attr('src', '');
        $('#courtModalTitle').text('Add Court');
    }

    function showAdd() {
        resetForm();
        openModal($modal);
        $('#court_name').focus();
    }

    function showEdit(c) {
        resetForm();
        $('#courtModalTitle').text('Edit Court #' + c.court_id);
        $('#court_id').val(c.court_id);
        $('#court_name').val(c.court_name || '');
        $('#court_type').val(c.court_type || 'outdoor');
        $('#court_surface').val(c.court_surface || 'hard');
        $('#court_capacity').val(c.court_capacity || 4);
        $('#price_per_slot').val(c.price_per_slot || 0);
        $('#slot_length_minutes').val(c.slot_length_minutes || 60);
        $('#buffer_minutes').val(c.buffer_minutes || 0);
        $('#court_status').prop('checked', String(c.court_status) === '1');
        if (c.court_photo) {
            $('#courtPhotoPreviewImg').attr('src', c.court_photo);
            $('#courtPhotoPreview').show();
        }
        openModal($modal);
        $('#court_name').focus();
    }

    $('#btnAddCourt, #btnAddCourtFilter').on('click', showAdd);

    $(document).on('click', '.btn-court-edit', function () {
        var raw = $(this).attr('data-court');
        try {
            showEdit(JSON.parse(raw));
        } catch (e) {
            alert('Could not load court details.');
        }
    });

    $modal.on('click', '.portal-order-modal-close, .portal-order-modal-dismiss, .portal-order-modal-backdrop', function () {
        closeModal($modal);
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.hasClass('is-open')) {
            closeModal($modal);
        }
    });
})(jQuery);
</script>

<?php include('footer.php'); ?>
