<?php
include('config.php');
include_once(__DIR__ . '/../includes/dashboard_helper.php');
fh_auth_require_login();

$titleheader = 'Dashboard';
$isSuper = fh_auth_is_super_admin();
$bizId = $isSuper ? fh_admin_business_id($conn) : fh_auth_user_business_id();
if (!$isSuper && $bizId <= 0) {
    $bizId = fh_admin_business_id($conn);
}

$biz = null;
if ($bizId > 0) {
    $bizSql = $conn->query("SELECT * FROM business_account WHERE business_id = $bizId LIMIT 1");
    $biz = $bizSql ? $bizSql->fetch_assoc() : null;
}

$statsScope = ($isSuper && (!isset($_GET['business_id']) || (int)$_GET['business_id'] <= 0)) ? 0 : $bizId;
if ($isSuper && isset($_GET['view']) && $_GET['view'] === 'portal') {
    $statsScope = 0;
}
$stats = fh_dashboard_get_stats($conn, $statsScope);

$menuCoverage = ($stats['items_count'] > 0) ? round(($stats['menu_today'] / $stats['items_count']) * 100) : 0;
$mapLocations = fh_dashboard_get_map_locations($conn, $statsScope, $biz);
$mapPinCount = count($mapLocations['customers']) + ($mapLocations['business'] ? 1 : 0);

include('header.php');
include('navigation.php');
?>
<link rel="stylesheet" href="css/dashboard.css" type="text/css" />
<?php if ($mapPinCount > 0): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<?php endif; ?>

<div class="dash-wrap">

    <div class="dash-header">
        <div>
            <h1><?php echo $isSuper && $statsScope === 0 ? 'Portal Dashboard' : 'Restaurant Dashboard'; ?></h1>
            <p>
                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></strong>
                &mdash; <?php echo fh_auth_role_label($_SESSION['user_role']); ?>
                <?php if ($biz && $statsScope > 0): ?> &mdash; <?php echo htmlspecialchars($biz['business_name']); ?><?php endif; ?>
            </p>
        </div>
        <div class="dash-header-meta">
            <span class="dash-pill"><?php echo date('l, M j, Y'); ?></span>
            <?php if ($isSuper): ?>
            <a href="?view=portal" class="dash-pill dash-pill-blue" style="text-decoration:none;">Portal Overview</a>
            <?php if ($bizId > 0): ?>
            <a href="?business_id=<?php echo $bizId; ?>" class="dash-pill dash-pill-green" style="text-decoration:none;">Restaurant View</a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($statsScope > 0 && $stats['cms_published']): ?>
            <span class="dash-pill dash-pill-green">Store Live</span>
            <?php elseif ($statsScope > 0): ?>
            <span class="dash-pill dash-pill-orange">Store Draft</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isSuper && $stats['partners_pending'] > 0 && $statsScope === 0): ?>
    <div class="dash-alert">
        <strong><?php echo $stats['partners_pending']; ?> partner application<?php echo $stats['partners_pending'] > 1 ? 's' : ''; ?></strong> waiting for review.
        <a href="business_account.php">Review now →</a>
    </div>
    <?php endif; ?>

    <?php if ($statsScope > 0 && $stats['menu_today'] === 0 && $stats['items_count'] > 0): ?>
    <div class="dash-alert">
        No items on today's menu yet. <a href="menusetup.php?business_id=<?php echo $bizId; ?>">Set up today's menu →</a>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="dash-kpi-grid">
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">Revenue Today</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_today']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['orders_today']; ?> order<?php echo $stats['orders_today'] !== 1 ? 's' : ''; ?></div>
        </div>
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">This Week</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_week']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['orders_week']; ?> orders (7 days)</div>
        </div>
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">This Month</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_month']); ?></div>
            <div class="dash-kpi-sub">Total: <?php echo fh_dashboard_money($stats['revenue_total']); ?></div>
        </div>
        <div class="dash-kpi orders">
            <div class="dash-kpi-label">Pending Orders</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['orders_pending']); ?></div>
            <div class="dash-kpi-sub"><a href="orders.php<?php echo $bizId ? '?business_id='.$bizId : ''; ?>">View orders →</a></div>
        </div>

        <?php if ($isSuper && $statsScope === 0): ?>
        <div class="dash-kpi partners">
            <div class="dash-kpi-label">Active Restaurants</div>
            <div class="dash-kpi-value"><?php echo $stats['partners_active']; ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['partners_pending']; ?> pending</div>
        </div>
        <div class="dash-kpi inventory">
            <div class="dash-kpi-label">Portal Customers</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['customers_count']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['users_count']; ?> admin users</div>
        </div>
        <?php else: ?>
        <div class="dash-kpi inventory">
            <div class="dash-kpi-label">Food Inventory</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['items_count']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['menu_today']; ?> on today's menu</div>
        </div>
        <div class="dash-kpi cms">
            <div class="dash-kpi-label">Menu Coverage</div>
            <div class="dash-kpi-value"><?php echo $menuCoverage; ?>%</div>
            <div class="dash-inventory-bar">
                <div class="dash-progress"><div class="dash-progress-fill" style="width:<?php echo min(100, $menuCoverage); ?>%"></div></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="dash-card" style="margin-bottom:20px;">
        <h3>Quick Actions</h3>
        <div class="dash-actions">
            <?php if ($isSuper && $statsScope === 0): ?>
            <a href="business_account.php" class="dash-action"><span class="dash-action-icon">🏢</span><span>Business Accounts</span></a>
            <a href="users.php" class="dash-action"><span class="dash-action-icon">👥</span><span>Users &amp; Roles</span></a>
            <a href="copy_items.php" class="dash-action"><span class="dash-action-icon">📋</span><span>Copy Items</span></a>
            <a href="customers.php" class="dash-action"><span class="dash-action-icon">🧑</span><span>Customers</span></a>
            <a href="orders.php" class="dash-action"><span class="dash-action-icon">📦</span><span>All Orders</span></a>
            <a href="category.php" class="dash-action"><span class="dash-action-icon">🏷</span><span>Category Setup</span></a>
            <?php else: ?>
            <a href="category.php" class="dash-action"><span class="dash-action-icon">🏷</span><span>Category Setup</span></a>
            <a href="fooditems.php?business_id=<?php echo $bizId; ?>" class="dash-action"><span class="dash-action-icon">🍽</span><span>Food Items</span></a>
            <a href="menusetup.php?business_id=<?php echo $bizId; ?>" class="dash-action"><span class="dash-action-icon">📅</span><span>Menu Setup</span></a>
            <a href="orders.php?business_id=<?php echo $bizId; ?>" class="dash-action"><span class="dash-action-icon">📦</span><span>Orders</span></a>
            <a href="business_page.php?business_id=<?php echo $bizId; ?>" class="dash-action"><span class="dash-action-icon">🏪</span><span>Store CMS</span></a>
            <a href="copy_items.php?business_id=<?php echo $bizId; ?>" class="dash-action"><span class="dash-action-icon">📋</span><span>Copy Items</span></a>
            <?php if (fh_auth_can_manage_users()): ?>
            <a href="users.php" class="dash-action"><span class="dash-action-icon">👤</span><span>Staff Users</span></a>
            <?php endif; ?>
            <?php if ($biz): ?>
            <a href="../<?php echo fh_store_link($bizId, $biz['business_subdomain']); ?>" target="_blank" class="dash-action"><span class="dash-action-icon">🌐</span><span>View Store</span></a>
            <a href="../index.php?business_id=<?php echo $bizId; ?>" target="_blank" class="dash-action"><span class="dash-action-icon">🛒</span><span>Order Page</span></a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer Delivery Map -->
    <div class="dash-card dash-map-card">
        <h3>
            <?php echo $statsScope > 0 ? 'Customer Delivery Map' : 'Customer Address Map'; ?>
            <?php if ($mapPinCount > 0): ?>
            <span style="font-weight:400;color:#94a3b8;">— <?php echo number_format(count($mapLocations['customers'])); ?> customer<?php echo count($mapLocations['customers']) === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </h3>
        <?php if ($mapPinCount === 0): ?>
        <div class="dash-map-box">
            <div class="dash-map-empty">
                No customer addresses found yet. Addresses appear from order delivery details or customer profiles.
            </div>
        </div>
        <?php else: ?>
        <div class="dash-map-box">
            <div id="customerMap"></div>
        </div>
        <div class="dash-map-legend">
            <?php if ($mapLocations['business']): ?>
            <span><i class="pin-business"></i> Restaurant location</span>
            <?php endif; ?>
            <span><i class="pin-customer"></i> Customer address</span>
            <span class="dash-map-attribution">Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors</span>
        </div>
        <div class="dash-map-status" id="mapGeocodeStatus" aria-live="polite"></div>
        <?php endif; ?>
    </div>

    <!-- Charts Row -->
    <div class="dash-grid-2">
        <div class="dash-card">
            <h3>Sales Analytics — Last 7 Days</h3>
            <div class="dash-chart-box"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="dash-card">
            <h3>Orders by Status</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="dash-grid-3">
        <div class="dash-card">
            <h3>Top Selling Items</h3>
            <?php if (empty($stats['top_items'])): ?>
            <p style="color:#94a3b8;font-size:14px;">No sales data yet.</p>
            <?php else: ?>
            <?php foreach ($stats['top_items'] as $item): ?>
            <div class="dash-top-item">
                <div>
                    <div class="dash-top-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="dash-top-item-meta"><?php echo number_format($item['qty'], 0); ?> sold</div>
                </div>
                <div class="dash-top-item-rev"><?php echo fh_dashboard_money($item['revenue']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($isSuper && $statsScope === 0 && !empty($stats['business_chart']['labels'])): ?>
        <div class="dash-card" style="grid-column: span 2;">
            <h3>Revenue by Restaurant</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="businessChart"></canvas></div>
        </div>
        <?php elseif ($statsScope > 0): ?>
        <div class="dash-card">
            <h3>Inventory &amp; CMS</h3>
            <div class="dash-inventory-bar">
                <div class="dash-inventory-label"><span>Total food items</span><strong><?php echo $stats['items_count']; ?></strong></div>
                <div class="dash-progress"><div class="dash-progress-fill" style="width:100%"></div></div>
            </div>
            <div class="dash-inventory-bar">
                <div class="dash-inventory-label"><span>On today's menu</span><strong><?php echo $stats['menu_today']; ?> / <?php echo $stats['items_count']; ?></strong></div>
                <div class="dash-progress"><div class="dash-progress-fill" style="width:<?php echo min(100, $menuCoverage); ?>%"></div></div>
            </div>
            <div class="dash-inventory-bar">
                <div class="dash-inventory-label"><span>Store page</span><strong><?php echo $stats['cms_published'] ? 'Published' : 'Draft'; ?></strong></div>
            </div>
            <?php if ($biz): ?>
            <a href="business_page.php?business_id=<?php echo $bizId; ?>" class="dash-store-link">Edit Store CMS →</a>
            <a href="../<?php echo fh_store_link($bizId, $biz['business_subdomain']); ?>" target="_blank" class="dash-store-link">Preview store page ↗</a>
            <?php endif; ?>
        </div>
        <div class="dash-card">
            <h3>Top Items Chart</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="topItemsChart"></canvas></div>
        </div>
        <?php else: ?>
        <div class="dash-card" style="grid-column: span 2;">
            <h3>Top Items Chart</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="topItemsChart"></canvas></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Orders -->
    <div class="dash-card">
        <h3>Recent Orders <?php if ($isSuper && $statsScope === 0): ?><span style="font-weight:400;color:#94a3b8;">— All restaurants</span><?php endif; ?></h3>
        <div style="overflow-x:auto;">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <?php if ($isSuper && $statsScope === 0): ?><th>Restaurant</th><?php endif; ?>
                        <th>Customer</th>
                        <th>Status</th>
                        <th align="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['recent_orders'])): ?>
                    <tr><td colspan="6" style="color:#94a3b8;text-align:center;padding:24px;">No orders yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($stats['recent_orders'] as $order): ?>
                    <tr>
                        <td><strong>#<?php echo (int)$order['orders_id']; ?></strong></td>
                        <td><?php echo date('M j, g:i A', strtotime($order['orders_transdate'])); ?></td>
                        <?php if ($isSuper && $statsScope === 0): ?>
                        <td><?php echo htmlspecialchars($order['business_name'] ? $order['business_name'] : 'Unassigned'); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars(trim($order['orders_customers_fname'] . ' ' . $order['orders_customers_lname'])); ?></td>
                        <td><span class="dash-badge <?php echo fh_dashboard_status_class($order['orders_status']); ?>"><?php echo htmlspecialchars($order['orders_status']); ?></span></td>
                        <td align="right"><strong><?php echo fh_dashboard_money(isset($order['total']) ? $order['total'] : 0); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($isSuper && $statsScope === 0): ?>
    <?php echo fh_admin_business_selector($conn, 'index.php'); ?>
    <p style="font-size:13px;color:#64748b;">Select a restaurant above to switch to restaurant-specific analytics.</p>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    var salesLabels = <?php echo json_encode($stats['sales_chart']['labels']); ?>;
    var salesRevenue = <?php echo json_encode($stats['sales_chart']['revenue']); ?>;
    var salesOrders = <?php echo json_encode($stats['sales_chart']['orders']); ?>;

    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: salesLabels,
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: salesRevenue,
                    borderColor: '#2d6a4f',
                    backgroundColor: 'rgba(64, 145, 108, 0.12)',
                    fill: true,
                    tension: 0.35,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: salesOrders,
                    borderColor: '#2563eb',
                    backgroundColor: 'transparent',
                    borderDash: [4, 4],
                    tension: 0.35,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { position: 'left', title: { display: true, text: 'Revenue ₱' }, grid: { color: '#f1f5f9' } },
                y1: { position: 'right', title: { display: true, text: 'Orders' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    var statusLabels = <?php echo json_encode($stats['status_chart']['labels']); ?>;
    var statusValues = <?php echo json_encode($stats['status_chart']['values']); ?>;
    var statusColors = ['#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444','#64748b'];

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusValues, backgroundColor: statusColors.slice(0, statusLabels.length), borderWidth: 0 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    var topItems = <?php echo json_encode($stats['top_items']); ?>;
    if (document.getElementById('topItemsChart') && topItems.length) {
        new Chart(document.getElementById('topItemsChart'), {
            type: 'bar',
            data: {
                labels: topItems.map(function(i) { return i.name.length > 18 ? i.name.substring(0, 16) + '…' : i.name; }),
                datasets: [{
                    label: 'Qty Sold',
                    data: topItems.map(function(i) { return i.qty; }),
                    backgroundColor: '#40916c',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
            }
        });
    }

    <?php if ($isSuper && $statsScope === 0 && !empty($stats['business_chart']['labels'])): ?>
    new Chart(document.getElementById('businessChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($stats['business_chart']['labels']); ?>,
            datasets: [
                { label: 'Revenue (₱)', data: <?php echo json_encode($stats['business_chart']['revenue']); ?>, backgroundColor: '#2d6a4f', borderRadius: 6 },
                { label: 'Orders', data: <?php echo json_encode($stats['business_chart']['orders']); ?>, backgroundColor: '#93c5fd', borderRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { grid: { color: '#f1f5f9' } } }
        }
    });
    <?php endif; ?>
})();

<?php if ($mapPinCount > 0): ?>
window.fhDashboardMap = {
    defaultCenter: <?php echo json_encode($mapLocations['default_center']); ?>,
    business: <?php echo json_encode($mapLocations['business']); ?>,
    customers: <?php echo json_encode($mapLocations['customers']); ?>
};
<?php endif; ?>
</script>
<?php if ($mapPinCount > 0): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="js/dashboard-map.js?v=20260704b"></script>
<?php endif; ?>

<?php include('footer.php'); ?>
