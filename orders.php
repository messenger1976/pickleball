<?php
include('admin/config.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/order_helper.php');

if (!isset($_SESSION['customers_id'])) {
    header('Location: login.php');
    exit;
}

$customer_id = (int)$_SESSION['customers_id'];

$allowedSort = array(
    'orders_id' => 'o.orders_id',
    'orders_transdate' => 'o.orders_transdate',
    'orders_menudate' => 'o.orders_menudate',
    'orders_total_amount' => 'o.orders_total_amount',
    'orders_status' => 'o.orders_status'
);

$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'orders_id';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$allowedPerPage = array(10, 25, 50, 100);
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

$countResult = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE orders_customers_id = $customer_id");
$totalOrders = $countResult ? (int)$countResult->fetch_assoc()['c'] : 0;
$totalPages = $totalOrders > 0 ? (int)ceil($totalOrders / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orders = array();
$ordersSql = $conn->query(
    "SELECT o.*, ba.business_name
     FROM orders o
     LEFT JOIN business_account ba ON o.orders_business_id = ba.business_id
     WHERE o.orders_customers_id = $customer_id
     ORDER BY $sortCol $sortDir
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

function orders_list_url($overrides = array()) {
    global $sortKey, $sortDir, $page, $perPage;
    $q = array_merge(array(
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'page' => $page,
        'per_page' => $perPage,
    ), $overrides);
    return 'orders.php?' . http_build_query($q);
}

function orders_sort_url($column, $currentSort, $currentDir, $page) {
    $newDir = 'desc';
    if ($column === $currentSort && strtolower($currentDir) === 'desc') {
        $newDir = 'asc';
    }
    return orders_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function orders_sort_indicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($currentDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function orders_is_pending($status) {
    return strtolower(trim((string)$status)) === 'pending';
}

function orders_status_class($status) {
    $status = strtolower(trim((string)$status));
    if ($status === 'completed' || $status === 'delivered') {
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

$showingFrom = $totalOrders > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalOrders);

$pagetitle = 'My Orders — Food Park Portal';
$bodyClass = 'portal-order-page';
$extraStylesheets = array(
    'css/portal-home.css?v=20260704',
    'css/portal-order.css?v=20260804c
);
include('header.php');
?>

<div class="portal-order-hero portal-order-hero-compact">
    <div class="portal-order-hero-inner">
        <a class="portal-order-back" href="home.php">&larr; Back to Home</a>
        <span class="portal-order-badge">Orders</span>
        <h1>My Orders</h1>
        <p>Review your order history, track status, and expand any order to see the items you purchased.</p>
    </div>
</div>

<div class="portal-order-body portal-orders-body">
    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalOrders); ?></strong>
            <span>Total Orders</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo (int)$page; ?></strong>
            <span>Page of <?php echo max(1, $totalPages); ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo (int)$perPage; ?></strong>
            <span>Per page</span>
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
                <h2>No orders yet</h2>
                <p>When you place an order, it will appear here with full item details.</p>
                <a href="home.php" class="portal-btn portal-btn-primary">Browse Restaurants</a>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalOrders); ?></strong> order<?php echo $totalOrders === 1 ? '' : 's'; ?>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="orders.php">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
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
                                <a href="<?php echo htmlspecialchars(orders_sort_url('orders_id', $sortKey, $sortDir, $page)); ?>">
                                    Order No. <?php echo orders_sort_indicator('orders_id', $sortKey, $sortDir); ?>
                                </a>
                            </th>
                            <th>Receipt No.</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(orders_sort_url('orders_transdate', $sortKey, $sortDir, $page)); ?>">
                                    Trans Date <?php echo orders_sort_indicator('orders_transdate', $sortKey, $sortDir); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(orders_sort_url('orders_menudate', $sortKey, $sortDir, $page)); ?>">
                                    Menu Date <?php echo orders_sort_indicator('orders_menudate', $sortKey, $sortDir); ?>
                                </a>
                            </th>
                            <th>Restaurant</th>
                            <th>Payment</th>
                            <th>Shipping</th>
                            <th class="portal-orders-sortable portal-orders-col-amount">
                                <a href="<?php echo htmlspecialchars(orders_sort_url('orders_total_amount', $sortKey, $sortDir, $page)); ?>">
                                    Grand Total <?php echo orders_sort_indicator('orders_total_amount', $sortKey, $sortDir); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(orders_sort_url('orders_status', $sortKey, $sortDir, $page)); ?>">
                                    Status <?php echo orders_sort_indicator('orders_status', $sortKey, $sortDir); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-items">Items</th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $orderId => $order):
                            $itemCount = count($order['items']);
                            $statusClass = orders_status_class($order['orders_status']);
                            $restaurant = !empty($order['business_name']) ? $order['business_name'] : '—';
                            $isPending = orders_is_pending($order['orders_status']);
                        ?>
                        <tr class="portal-orders-row" data-order-id="<?php echo $orderId; ?>">
                            <td class="portal-orders-col-expand">
                                <button type="button" class="portal-orders-expand-btn" data-order-id="<?php echo $orderId; ?>" aria-expanded="false" aria-controls="order-details-<?php echo $orderId; ?>" title="Show order items">
                                    <span class="portal-orders-expand-icon">▶</span>
                                </button>
                            </td>
                            <td><strong>#<?php echo $orderId; ?></strong></td>
                            <td><?php echo !empty($order['orders_receipt_no']) ? htmlspecialchars($order['orders_receipt_no']) : '—'; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($order['orders_transdate'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($order['orders_menudate'])); ?></td>
                            <td><?php echo htmlspecialchars($restaurant); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($order['orders_payment_type'])); ?></td>
                            <td><?php echo htmlspecialchars(fh_order_shipping_label($order)); ?></td>
                            <td class="portal-orders-col-amount"><strong>₱<?php echo number_format((float)$order['orders_total_amount'], 2); ?></strong></td>
                            <td><span class="portal-orders-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['orders_status']); ?></span></td>
                            <td class="portal-orders-col-items"><?php echo (int)$itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?></td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-edit" data-order-id="<?php echo $orderId; ?>" title="Edit order" aria-label="Edit order #<?php echo $orderId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel<?php echo $isPending ? '' : ' is-disabled'; ?>" data-order-id="<?php echo $orderId; ?>" title="Cancel order" aria-label="Cancel order #<?php echo $orderId; ?>"<?php echo $isPending ? '' : ' disabled'; ?>>✕</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="portal-orders-detail-row" id="order-details-<?php echo $orderId; ?>" hidden>
                            <td colspan="12">
                                <div class="portal-orders-detail-panel">
                                    <div class="portal-orders-detail-head">
                                        <h3>Order #<?php echo $orderId; ?> — Items</h3>
                                        <?php if (!empty($order['orders_remarks'])): ?>
                                            <p class="portal-orders-remarks"><strong>Remarks:</strong> <?php echo htmlspecialchars($order['orders_remarks']); ?></p>
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
                                                    $img = fh_food_image($item['picture']);
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
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(orders_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(orders_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(orders_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="orderEditModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog" role="dialog" aria-labelledby="editModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="editModalTitle">Edit Order <span id="editOrderId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="editOrderMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <div class="portal-order-modal-meta">
                <div><span>Status</span><strong id="editOrderStatus" class="portal-orders-status"></strong></div>
                <div><span>Trans Date</span><strong id="editTransDate"></strong></div>
                <div><span>Menu Date</span><strong id="editMenuDate"></strong></div>
                <div><span>Restaurant</span><strong id="editRestaurant"></strong></div>
                <div><span>Payment</span><strong id="editPayment"></strong></div>
                <div><span>Shipping</span><strong id="editShipping"></strong></div>
            </div>
            <div class="portal-order-modal-section">
                <h3>Customer Information</h3>
                <div class="portal-order-modal-info-grid">
                    <div><span>Name</span><strong id="editFullName"></strong></div>
                    <div><span>Email</span><strong id="editEmail"></strong></div>
                    <div><span>Contact</span><strong id="editContact"></strong></div>
                    <div><span>Company</span><strong id="editCompany"></strong></div>
                    <div><span>Department</span><strong id="editDepartment"></strong></div>
                </div>
                <label for="editAddress">Delivery Address</label>
                <textarea id="editAddress" rows="3" placeholder="Delivery address"></textarea>
                <p class="portal-order-modal-hint">Address can only be changed while the order is Pending.</p>
                <p class="portal-order-modal-remarks"><strong>Remarks:</strong> <span id="editRemarks"></span></p>
            </div>
            <div class="portal-order-modal-section">
                <h3>Order Items</h3>
                <div id="editOrderItems"></div>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Close</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveOrderAddress">Save Address</button>
        </div>
    </div>
</div>

<div class="portal-order-modal" id="orderCancelModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog" aria-labelledby="cancelModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="cancelModalTitle">Cancel Order <span id="cancelOrderId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="cancelOrderMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Please tell us why you want to cancel this order. This action cannot be undone.</p>
            <label for="cancelReason">Cancellation reason</label>
            <textarea id="cancelReason" rows="4" placeholder="e.g. Ordered by mistake, change of plans…"></textarea>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep Order</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnConfirmCancelOrder">Confirm Cancellation</button>
        </div>
    </div>
</div>

<script src="js/portal-orders.js?v=20260716"></script>

<?php include('footer.php'); ?>
