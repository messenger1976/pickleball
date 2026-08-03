<?php
include('config.php');
include_once(__DIR__ . '/../includes/order_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

$allowedStatuses = array('All', 'Pending', 'Cancelled', 'Confirmed', 'Processing', 'Delivered', 'Paid');

if (isset($_REQUEST['filterorderdate']) && $_REQUEST['filterorderdate'] !== '') {
    $_SESSION['menudate'] = date('Y-m-d', strtotime($_REQUEST['filterorderdate']));
} elseif (!isset($_SESSION['menudate'])) {
    $_SESSION['menudate'] = date('Y-m-d');
}
$filterDate = date('Y-m-d', strtotime($_SESSION['menudate']));

$filterStatus = isset($_REQUEST['filterstatus']) && in_array($_REQUEST['filterstatus'], $allowedStatuses, true)
    ? $_REQUEST['filterstatus']
    : 'All';

if ($filterStatus !== 'All') {
    $statusSql = "AND orders.orders_status='" . $conn->real_escape_string($filterStatus) . "'";
} else {
    $statusSql = "AND orders.orders_status<>'Cancelled'";
}

$businessFilter = "AND (orders.orders_business_id=$adminBusinessId OR orders.orders_business_id=0)";

$allowedSort = array(
    'orders_id' => 'orders.orders_id',
    'orders_transdate' => 'orders.orders_transdate',
    'orders_menudate' => 'orders.orders_menudate',
    'orders_total_amount' => 'orders.orders_total_amount',
    'orders_status' => 'orders.orders_status',
    'customer' => 'orders.orders_customers_lname',
);

$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'orders_transdate';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$baseWhere = "WHERE orders_menudate='" . $conn->real_escape_string($filterDate) . "' $statusSql $businessFilter";

$countResult = $conn->query("SELECT COUNT(*) AS c FROM orders $baseWhere");
$totalOrders = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalOrders > 0 ? (int)ceil($totalOrders / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalOrders > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalOrders);

$orders = array();
$ordersSql = $conn->query(
    "SELECT orders.*, company.name AS company_name, department.code AS department_code,
            business_account.business_name
     FROM orders
     LEFT JOIN company ON orders.orders_customers_company = company.id
     LEFT JOIN department ON orders.orders_customers_department = department.id
     LEFT JOIN business_account ON orders.orders_business_id = business_account.business_id
     $baseWhere
     ORDER BY $sortCol $sortDir, orders.orders_id DESC
     LIMIT $offset, $perPage"
);

if ($ordersSql) {
    while ($row = $ordersSql->fetch_assoc()) {
        $orderId = (int)$row['orders_id'];
        $orders[$orderId] = $row;
        $orders[$orderId]['items'] = array();
    }
}

if (!empty($orders)) {
    $orderIds = implode(',', array_map('intval', array_keys($orders)));
    $itemsSql = $conn->query(
        "SELECT od.orders_details_orders_id, od.orders_details_qty, i.id, i.name, i.price, i.picture
         FROM orders_details od
         INNER JOIN items i ON od.orders_details_menu_id = i.id
         WHERE od.orders_details_orders_id IN ($orderIds)
         ORDER BY i.name ASC"
    );
    if ($itemsSql) {
        while ($item = $itemsSql->fetch_assoc()) {
            $oid = (int)$item['orders_details_orders_id'];
            if (isset($orders[$oid])) {
                $orders[$oid]['items'][] = $item;
            }
        }
    }
}

function admin_orders_list_url($overrides = array()) {
    global $sortKey, $sortDir, $page, $perPage, $filterDate, $filterStatus, $adminBusinessId;
    $q = array_merge(array(
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'page' => $page,
        'per_page' => $perPage,
        'filterorderdate' => $filterDate,
        'filterstatus' => $filterStatus,
        'business_id' => $adminBusinessId,
    ), $overrides);
    return 'orders.php?' . http_build_query($q);
}

function admin_orders_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'desc';
    if ($column === $sortKey && strtolower($sortDir) === 'desc') {
        $newDir = 'asc';
    }
    return admin_orders_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_orders_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function admin_orders_status_class($status) {
    $status = strtolower(trim((string)$status));
    if ($status === 'completed' || $status === 'delivered' || $status === 'paid' || $status === 'confirmed') {
        return 'is-success';
    }
    if ($status === 'cancelled' || $status === 'canceled') {
        return 'is-danger';
    }
    if ($status === 'pending' || $status === 'processing') {
        return 'is-warning';
    }
    return 'is-neutral';
}

function admin_orders_is_pending($status) {
    return strtolower(trim((string)$status)) === 'pending';
}

function admin_orders_food_image($picture) {
    $img = fh_food_image($picture);
    if (strpos($img, 'admin/') === 0) {
        return substr($img, 6);
    }
    return $img;
}

$titleheader = 'Orders';
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
.admin-orders-filter label {
    display: block;
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-orders-filter input[type="date"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 140px;
}
.admin-orders-filter button {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.admin-orders-filter button:hover {
    background: #1b4332;
}
.admin-orders-detail-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px 16px;
    margin: 0 0 14px;
    padding: 12px 14px;
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.admin-orders-detail-meta span {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
    margin-bottom: 2px;
}
.admin-orders-detail-meta strong {
    font-size: 13px;
    color: #334155;
    font-weight: 600;
}
#my-iframe, #formID { display: none; }
.admin-order-status-select {
    padding: 6px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}
.portal-orders-col-elapsed {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.order-elapsed {
    display: inline-block;
    min-width: 7.5em;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    font-family: ui-monospace, "Cascadia Mono", "Segoe UI Mono", Consolas, monospace;
}
.order-elapsed.is-running {
    color: #b45309;
}
.order-elapsed.is-done {
    color: #15803d;
}
.order-elapsed.is-cancelled {
    color: #b91c1c;
}
.order-elapsed.is-unknown {
    color: #94a3b8;
    font-weight: 600;
}
</style>

<div id="errmsg"></div>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Orders Listing</h1>
        <p>Filter by menu date and status, expand any order for line items, and update fulfillment details.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'orders.php'); ?>

    <iframe name="my-iframe" id="my-iframe"></iframe>

    <form class="admin-orders-filter" method="get" action="orders.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterorderdate">Menu / Order Date</label>
            <input type="date" id="filterorderdate" name="filterorderdate" value="<?php echo htmlspecialchars($filterDate); ?>">
        </div>
        <div>
            <label for="filterstatus">Status</label>
            <select id="filterstatus" name="filterstatus">
                <?php foreach ($allowedStatuses as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption); ?>"<?php echo $filterStatus === $statusOption ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($statusOption === 'All' ? 'All (excl. Cancelled)' : $statusOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit" name="btnDate">Apply Filter</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalOrders); ?></strong>
            <span>Orders on <?php echo date('M j, Y', strtotime($filterDate)); ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo (int)$page; ?></strong>
            <span>Page of <?php echo max(1, $totalPages); ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo htmlspecialchars($filterStatus === 'All' ? 'Active' : $filterStatus); ?></strong>
            <span>Status filter</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Order History</h2>
            <?php if ($totalOrders > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalOrders); ?> order<?php echo $totalOrders === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">📋</div>
                <h2>No orders found</h2>
                <p>No orders match this menu date and status filter. Try another date or choose a different status.</p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalOrders); ?></strong> order<?php echo $totalOrders === 1 ? '' : 's'; ?>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="orders.php">
                    <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="filterorderdate" value="<?php echo htmlspecialchars($filterDate); ?>">
                    <input type="hidden" name="filterstatus" value="<?php echo htmlspecialchars($filterStatus); ?>">
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
                <table class="portal-orders-table" id="ordersTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-col-expand" aria-label="Expand"></th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('orders_id')); ?>">
                                    Order No. <?php echo admin_orders_sort_indicator('orders_id'); ?>
                                </a>
                            </th>
                            <th>Receipt No.</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('orders_transdate')); ?>">
                                    Trans Date <?php echo admin_orders_sort_indicator('orders_transdate'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('orders_menudate')); ?>">
                                    Menu Date <?php echo admin_orders_sort_indicator('orders_menudate'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('customer')); ?>">
                                    Customer <?php echo admin_orders_sort_indicator('customer'); ?>
                                </a>
                            </th>
                            <th>Restaurant</th>
                            <th>Payment</th>
                            <th>Shipping</th>
                            <th class="portal-orders-sortable portal-orders-col-amount">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('orders_total_amount')); ?>">
                                    Grand Total <?php echo admin_orders_sort_indicator('orders_total_amount'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-elapsed">Duration</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_orders_sort_url('orders_status')); ?>">
                                    Status <?php echo admin_orders_sort_indicator('orders_status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-items">Items</th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $orderId => $order):
                            $itemCount = count($order['items']);
                            $statusClass = admin_orders_status_class($order['orders_status']);
                            $restaurant = !empty($order['business_name']) ? $order['business_name'] : 'Unassigned';
                            $fullname = trim($order['orders_customers_lname'] . ', ' . $order['orders_customers_fname']);
                            $isPending = admin_orders_is_pending($order['orders_status']);
                            $isTerminal = fh_order_is_terminal($order['orders_status']);
                            $startTs = strtotime($order['orders_transdate']);
                            $endTs = (!empty($order['orders_completed_at']))
                                ? strtotime($order['orders_completed_at'])
                                : 0;
                            $elapsedClass = 'is-running';
                            if ($isTerminal) {
                                $elapsedClass = fh_order_is_cancelled($order['orders_status'])
                                    ? 'is-cancelled'
                                    : 'is-done';
                                if (!$endTs) {
                                    $elapsedClass = 'is-unknown';
                                }
                            }
                            $shippingLabel = fh_order_shipping_label($order);
                            $addressParts = array_filter(array(
                                $order['orders_delivery_address'],
                                $order['orders_barangay'],
                                $order['orders_town'],
                                $order['orders_province'],
                                $order['orders_zipcode'],
                                $order['orders_country'],
                            ));
                            $fullAddress = implode(', ', $addressParts);
                        ?>
                        <tr class="portal-orders-row" data-order-id="<?php echo $orderId; ?>">
                            <td class="portal-orders-col-expand">
                                <button type="button" class="portal-orders-expand-btn" data-order-id="<?php echo $orderId; ?>" aria-expanded="false" aria-controls="order-details-<?php echo $orderId; ?>" title="Show order details">
                                    <span class="portal-orders-expand-icon">▶</span>
                                </button>
                            </td>
                            <td><strong>#<?php echo $orderId; ?></strong></td>
                            <td><?php echo !empty($order['orders_receipt_no']) ? htmlspecialchars($order['orders_receipt_no']) : '—'; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($order['orders_transdate'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($order['orders_menudate'])); ?></td>
                            <td><?php echo htmlspecialchars($fullname); ?></td>
                            <td><?php echo htmlspecialchars($restaurant); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($order['orders_payment_type'])); ?></td>
                            <td><?php echo htmlspecialchars($shippingLabel); ?></td>
                            <td class="portal-orders-col-amount"><strong>₱<?php echo number_format((float)$order['orders_total_amount'], 2); ?></strong></td>
                            <td class="portal-orders-col-elapsed">
                                <span class="order-elapsed <?php echo $elapsedClass; ?>"
                                      data-start="<?php echo (int)$startTs; ?>"
                                      data-end="<?php echo $isTerminal && $endTs ? (int)$endTs : ''; ?>"
                                      data-terminal="<?php echo $isTerminal ? '1' : '0'; ?>"
                                      title="<?php echo $isTerminal ? 'Time from order start until ' . htmlspecialchars($order['orders_status']) : 'Elapsed time since order started'; ?>">—</span>
                            </td>
                            <td><span class="portal-orders-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['orders_status']); ?></span></td>
                            <td class="portal-orders-col-items"><?php echo (int)$itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?></td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-admin-order-edit" data-orderid="<?php echo $orderId; ?>" title="Edit order" aria-label="Edit order #<?php echo $orderId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel btn-admin-order-cancel<?php echo $isPending ? '' : ' is-disabled'; ?>" data-orderid="<?php echo $orderId; ?>" title="Cancel order" aria-label="Cancel order #<?php echo $orderId; ?>"<?php echo $isPending ? '' : ' disabled'; ?>>✕</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="portal-orders-detail-row" id="order-details-<?php echo $orderId; ?>" hidden>
                            <td colspan="14">
                                <div class="portal-orders-detail-panel">
                                    <div class="portal-orders-detail-head">
                                        <h3>Order #<?php echo $orderId; ?> — Details</h3>
                                        <?php if (!empty($order['orders_remarks'])): ?>
                                            <p class="portal-orders-remarks"><strong>Remarks:</strong> <?php echo htmlspecialchars($order['orders_remarks']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-orders-detail-meta">
                                        <div>
                                            <span>Contact</span>
                                            <strong><?php echo htmlspecialchars($order['orders_customers_contact'] ?: '—'); ?></strong>
                                        </div>
                                        <div>
                                            <span>Email</span>
                                            <strong><?php echo htmlspecialchars($order['orders_customers_email'] ?: '—'); ?></strong>
                                        </div>
                                        <div>
                                            <span>Company</span>
                                            <strong><?php echo htmlspecialchars($order['company_name'] ?: '—'); ?></strong>
                                        </div>
                                        <div>
                                            <span>Department</span>
                                            <strong><?php echo htmlspecialchars($order['department_code'] ?: '—'); ?></strong>
                                        </div>
                                        <div style="grid-column: 1 / -1;">
                                            <span>Delivery Address</span>
                                            <strong><?php echo htmlspecialchars($fullAddress !== '' ? $fullAddress : '—'); ?></strong>
                                        </div>
                                        <?php if (strtolower($order['orders_shipping_type']) === 'dinein' && !empty($order['orders_dinein_table'])): ?>
                                        <div>
                                            <span>Table No.</span>
                                            <strong><?php echo htmlspecialchars($order['orders_dinein_table']); ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($itemCount === 0): ?>
                                        <p class="portal-orders-no-items">No line items found for this order.</p>
                                    <?php else: ?>
                                        <table class="portal-orders-items-table">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="portal-orders-col-qty">Qty</th>
                                                    <th class="portal-orders-col-amount">Unit Price</th>
                                                    <th class="portal-orders-col-amount">Line Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order['items'] as $item):
                                                    $qty = (int)$item['orders_details_qty'];
                                                    $unitPrice = (float)$item['price'];
                                                    $lineTotal = $qty * $unitPrice;
                                                    $img = admin_orders_food_image($item['picture']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="portal-orders-item-cell">
                                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="portal-orders-item-thumb">
                                                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="portal-orders-col-qty"><?php echo $qty; ?></td>
                                                    <td class="portal-orders-col-amount">₱<?php echo number_format($unitPrice, 2); ?></td>
                                                    <td class="portal-orders-col-amount"><strong>₱<?php echo number_format($lineTotal, 2); ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="portal-orders-detail-total-label">Order Grand Total</td>
                                                    <td class="portal-orders-col-amount"><strong>₱<?php echo number_format((float)$order['orders_total_amount'], 2); ?></strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    <?php endif; ?>
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
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_orders_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_orders_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_orders_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="adminOrderEditModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Edit Order <span id="adminEditOrderId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="adminEditOrderMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-meta">
                <div><span>Trans Date</span><strong id="adminEditTransDate"></strong></div>
                <div><span>Menu Date</span><strong id="adminEditMenuDate"></strong></div>
                <div><span>Restaurant</span><strong id="adminEditRestaurant"></strong></div>
                <div><span>Payment</span><strong id="adminEditPayment"></strong></div>
                <div><span>Shipping</span><strong id="adminEditShipping"></strong></div>
                <div><span>Status</span>
                    <select id="adminEditStatus" class="admin-order-status-select">
                        <option>Pending</option>
                        <option>Confirmed</option>
                        <option>Processing</option>
                        <option>Delivered</option>
                        <option>Paid</option>
                        <option>Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="portal-order-modal-section">
                <h3>Customer Information</h3>
                <div class="portal-order-modal-info-grid">
                    <div><span>Name</span><strong id="adminEditFullName"></strong></div>
                    <div><span>Email</span><strong id="adminEditEmail"></strong></div>
                    <div><span>Contact</span><strong id="adminEditContact"></strong></div>
                    <div><span>Company</span><strong id="adminEditCompany"></strong></div>
                    <div><span>Department</span><strong id="adminEditDepartment"></strong></div>
                </div>
                <label for="adminEditAddress">Delivery Address</label>
                <textarea id="adminEditAddress" rows="3"></textarea>
                <label for="adminEditRemarks" style="margin-top:12px;">Remarks</label>
                <textarea id="adminEditRemarks" rows="2"></textarea>
            </div>
            <div class="portal-order-modal-section">
                <h3>Order Items</h3>
                <p class="portal-order-modal-hint">Items can only be removed while the order is Pending.</p>
                <div id="adminEditOrderItems"></div>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Close</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnAdminSaveOrder">Save Changes</button>
        </div>
    </div>
</div>

<div class="portal-order-modal" id="adminOrderCancelModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Cancel Order <span id="adminCancelOrderId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="adminCancelOrderMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Provide a reason for cancelling this order. Only pending orders can be cancelled.</p>
            <label for="adminCancelReason">Cancellation reason</label>
            <textarea id="adminCancelReason" rows="4"></textarea>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep Order</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnAdminConfirmCancel">Confirm Cancellation</button>
        </div>
    </div>
</div>

<form id="formID" target="my-iframe" autocomplete="off" name="myform" method="POST" action="http://www.txtlibre.com/send_message-202001031230.php" style="display:none;">
    <input type="text" id="frm_senders_namex" name="frm_senders_namex" />
    <input type="hidden" id="frm_sen" name="frm_sen" value="0530"/>
    <input type="text" id="frm_senders_num" name="frm_senders_num" />
    <input type="text" id="frm_recepient_num" name="frm_recepient_num" />
    <textarea id="frm_messagex" name="frm_messagex"></textarea>
    <input type="submit" id="btnSubmitSMS" name="submit" value="send message"/>
</form>

<script src="js/admin-orders.js?v=20260714c"></script>
<?php
include('footer.php');
?>
