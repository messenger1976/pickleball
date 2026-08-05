<?php
include('config.php');
include_once(__DIR__ . '/../includes/dashboard_helper.php');
fh_auth_require_login();

$titleheader = 'Dashboard';
$isSuper = fh_auth_is_super_admin();
$isManagingVenue = $isSuper && fh_auth_is_managing_business();
$bizId = $isSuper ? fh_admin_business_id($conn) : fh_auth_user_business_id();
if (!$isSuper && $bizId <= 0) {
    $bizId = fh_admin_business_id($conn);
}
if ($isManagingVenue) {
    $managedId = fh_auth_managing_business_id();
    if ($managedId > 0) {
        $bizId = $managedId;
        $_SESSION['admin_business_id'] = $managedId;
    }
}

$biz = null;
if ($bizId > 0) {
    $bizSql = $conn->query("SELECT * FROM business_account WHERE business_id = $bizId LIMIT 1");
    $biz = $bizSql ? $bizSql->fetch_assoc() : null;
}

// While managing a venue, force Business Admin–style venue dashboard (no portal overview).
if ($isManagingVenue) {
    $statsScope = $bizId;
} else {
    $statsScope = ($isSuper && (!isset($_GET['business_id']) || (int)$_GET['business_id'] <= 0)) ? 0 : $bizId;
    if ($isSuper && isset($_GET['view']) && $_GET['view'] === 'portal') {
        $statsScope = 0;
    }
}
$isPortalDash = $isSuper && !$isManagingVenue && $statsScope === 0;
$stats = fh_dashboard_get_stats($conn, $statsScope);

$mapLocations = fh_dashboard_get_map_locations($conn, $statsScope, $biz);
$mapPinCount = count($mapLocations['venues']) + ($mapLocations['business'] && empty($mapLocations['venues']) ? 1 : 0);
if (!empty($mapLocations['venues'])) {
    $mapPinCount = count($mapLocations['venues']);
}
$bizQs = $statsScope > 0 ? '?business_id=' . (int)$statsScope : ($bizId > 0 ? '?business_id=' . (int)$bizId : '');

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
            <h1><?php echo $isPortalDash ? 'Portal Analytics' : 'Venue Analytics'; ?></h1>
            <p>
                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></strong>
                &mdash; <?php echo fh_auth_role_label($_SESSION['user_role']); ?>
                <?php if ($biz && $statsScope > 0): ?> &mdash; <?php echo htmlspecialchars($biz['business_name']); ?><?php endif; ?>
            </p>
        </div>
        <div class="dash-header-meta">
            <span class="dash-pill"><?php echo date('l, M j, Y'); ?></span>
            <?php if ($isSuper && !$isManagingVenue): ?>
            <a href="?view=portal" class="dash-pill dash-pill-blue" style="text-decoration:none;">Portal Overview</a>
            <?php if ($bizId > 0): ?>
            <a href="?business_id=<?php echo $bizId; ?>" class="dash-pill dash-pill-green" style="text-decoration:none;">Venue View</a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($statsScope > 0 && $stats['cms_published']): ?>
            <span class="dash-pill dash-pill-green">Venue Live</span>
            <?php elseif ($statsScope > 0): ?>
            <span class="dash-pill dash-pill-orange">Venue Draft</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isPortalDash && $stats['partners_pending'] > 0): ?>
    <div class="dash-alert">
        <strong><?php echo $stats['partners_pending']; ?> partner application<?php echo $stats['partners_pending'] > 1 ? 's' : ''; ?></strong> waiting for review.
        <a href="business_account.php">Review now →</a>
    </div>
    <?php endif; ?>

    <?php if ($stats['bookings_pending'] > 0): ?>
    <div class="dash-alert">
        <strong><?php echo $stats['bookings_pending']; ?> pending booking<?php echo $stats['bookings_pending'] > 1 ? 's' : ''; ?></strong> need attention.
        <a href="bookings.php<?php echo $bizQs; ?>">Review bookings →</a>
    </div>
    <?php endif; ?>

    <?php if ($statsScope > 0 && $stats['courts_active'] === 0): ?>
    <div class="dash-alert">
        No active courts yet. <a href="courts.php<?php echo $bizQs; ?>">Add a court →</a>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="dash-kpi-grid">
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">Revenue Today</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_today']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['bookings_today']; ?> booking<?php echo $stats['bookings_today'] !== 1 ? 's' : ''; ?> today</div>
        </div>
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">This Week</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_week']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['bookings_week']; ?> bookings (7 days)</div>
        </div>
        <div class="dash-kpi revenue">
            <div class="dash-kpi-label">This Month</div>
            <div class="dash-kpi-value"><?php echo fh_dashboard_money($stats['revenue_month']); ?></div>
            <div class="dash-kpi-sub">All-time: <?php echo fh_dashboard_money($stats['revenue_total']); ?></div>
        </div>
        <div class="dash-kpi orders">
            <div class="dash-kpi-label">Pending Bookings</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['bookings_pending']); ?></div>
            <div class="dash-kpi-sub"><a href="bookings.php<?php echo $bizQs; ?>">Manage bookings →</a></div>
        </div>

        <?php if ($isPortalDash): ?>
        <div class="dash-kpi partners">
            <div class="dash-kpi-label">Active Venues</div>
            <div class="dash-kpi-value"><?php echo $stats['partners_active']; ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['partners_pending']; ?> pending</div>
        </div>
        <div class="dash-kpi inventory">
            <div class="dash-kpi-label">Players</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['customers_count']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['users_count']; ?> admin users</div>
        </div>
        <?php else: ?>
        <div class="dash-kpi inventory">
            <div class="dash-kpi-label">Active Courts</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['courts_active']); ?></div>
            <div class="dash-kpi-sub"><?php echo $stats['courts_total']; ?> total · <?php echo $stats['utilization_today']; ?>% util. today</div>
        </div>
        <div class="dash-kpi cms">
            <div class="dash-kpi-label">Court Utilization</div>
            <div class="dash-kpi-value"><?php echo $stats['utilization_today']; ?>%</div>
            <div class="dash-inventory-bar">
                <div class="dash-progress"><div class="dash-progress-fill" style="width:<?php echo min(100, $stats['utilization_today']); ?>%"></div></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="dash-kpi membership">
            <div class="dash-kpi-label">Active Members</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['memberships_active']); ?></div>
            <div class="dash-kpi-sub">
                <?php if ($stats['membership_mrr'] > 0): ?>
                MRR <?php echo fh_dashboard_money($stats['membership_mrr']); ?>
                <?php else: ?>
                <?php echo $stats['memberships_pending']; ?> pending
                <?php endif; ?>
            </div>
        </div>
        <div class="dash-kpi chat">
            <div class="dash-kpi-label">Open Chats</div>
            <div class="dash-kpi-value"><?php echo number_format($stats['chats_open']); ?></div>
            <div class="dash-kpi-sub">
                <?php if ($stats['chats_unread'] > 0): ?>
                <a href="chat_inbox.php"><?php echo $stats['chats_unread']; ?> unread →</a>
                <?php else: ?>
                <a href="chat_inbox.php">Inbox →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dash-card" style="margin-bottom:20px;">
        <h3>Quick Actions</h3>
        <div class="dash-actions">
            <?php if ($isPortalDash): ?>
            <a href="business_account.php" class="dash-action"><span class="dash-action-icon">🏢</span><span>Business Accounts</span></a>
            <a href="users.php" class="dash-action"><span class="dash-action-icon">👥</span><span>Users &amp; Roles</span></a>
            <a href="customers.php" class="dash-action"><span class="dash-action-icon">🧑</span><span>Players</span></a>
            <a href="bookings.php" class="dash-action"><span class="dash-action-icon">📋</span><span>All Bookings</span></a>
            <a href="portal_news.php" class="dash-action"><span class="dash-action-icon">📰</span><span>Portal News</span></a>
            <a href="portal_subscription.php" class="dash-action"><span class="dash-action-icon">💳</span><span>Subscription Settings</span></a>
            <?php else: ?>
            <a href="bookings.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">📋</span><span>Bookings</span></a>
            <a href="courts.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">🎾</span><span>Courts</span></a>
            <a href="availability.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">📅</span><span>Availability</span></a>
            <a href="coaches.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">🧑‍🏫</span><span>Coaches &amp; Lessons</span></a>
            <a href="memberships.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">🪪</span><span>Memberships</span></a>
            <a href="chat_inbox.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">💬</span><span>Chat Inbox</span></a>
            <a href="business_page.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">🏪</span><span>Venue CMS</span></a>
            <?php if (fh_auth_can_manage_users()): ?>
            <a href="users.php<?php echo $bizQs; ?>" class="dash-action"><span class="dash-action-icon">👤</span><span>Staff Users</span></a>
            <?php endif; ?>
            <?php if ($biz): ?>
            <a href="../<?php echo fh_store_link($bizId, $biz['business_subdomain']); ?>" target="_blank" class="dash-action"><span class="dash-action-icon">🌐</span><span>View Venue</span></a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Schedule + Venue Snapshot -->
    <div class="dash-grid-2">
        <div class="dash-card">
            <h3>
                Today's Schedule
                <span style="font-weight:400;color:#94a3b8;">— <?php echo count($stats['today_schedule']); ?> session<?php echo count($stats['today_schedule']) === 1 ? '' : 's'; ?></span>
            </h3>
            <?php if (empty($stats['today_schedule'])): ?>
            <p class="dash-empty">No bookings scheduled for today.</p>
            <?php else: ?>
            <ul class="dash-schedule">
                <?php foreach ($stats['today_schedule'] as $slot): ?>
                <li class="dash-schedule-item">
                    <div class="dash-schedule-time">
                        <?php echo date('g:i A', strtotime($slot['start_datetime'])); ?>
                        <span>– <?php echo date('g:i A', strtotime($slot['end_datetime'])); ?></span>
                    </div>
                    <div class="dash-schedule-body">
                        <div class="dash-schedule-title">
                            <?php
                            $who = trim($slot['customer_fname'] . ' ' . $slot['customer_lname']);
                            echo htmlspecialchars($who !== '' ? $who : 'Guest');
                            ?>
                            <span class="dash-badge <?php echo fh_dashboard_status_class($slot['booking_status']); ?>"><?php echo htmlspecialchars(ucfirst($slot['booking_status'])); ?></span>
                        </div>
                        <div class="dash-schedule-meta">
                            <?php echo htmlspecialchars($slot['court_name'] ? $slot['court_name'] : ucfirst($slot['booking_type'] ? $slot['booking_type'] : 'court')); ?>
                            · <?php echo fh_dashboard_money($slot['total_price']); ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <a href="bookings.php<?php echo $bizQs; ?>" class="dash-store-link">View all bookings →</a>
        </div>

        <div class="dash-card">
            <h3>Venue Snapshot</h3>
            <div class="dash-snapshot">
                <?php if ($isPortalDash): ?>
                <div class="dash-snapshot-row"><span>Active venues</span><strong><?php echo $stats['partners_active']; ?></strong></div>
                <div class="dash-snapshot-row"><span>Total bookings</span><strong><?php echo number_format($stats['bookings_total']); ?></strong></div>
                <div class="dash-snapshot-row"><span>Upcoming</span><strong><?php echo number_format($stats['bookings_upcoming']); ?></strong></div>
                <div class="dash-snapshot-row"><span>Players</span><strong><?php echo number_format($stats['customers_count']); ?></strong></div>
                <?php else: ?>
                <div class="dash-snapshot-row"><span>Active courts</span><strong><?php echo $stats['courts_active']; ?> / <?php echo $stats['courts_total']; ?></strong></div>
                <div class="dash-snapshot-row"><span>Coaches</span><strong><?php echo $stats['coaches_active']; ?></strong></div>
                <div class="dash-snapshot-row"><span>Lessons</span><strong><?php echo $stats['lessons_active']; ?></strong></div>
                <div class="dash-snapshot-row"><span>Upcoming bookings</span><strong><?php echo $stats['bookings_upcoming']; ?></strong></div>
                <?php endif; ?>
                <div class="dash-snapshot-row"><span>Paid this month</span><strong><?php echo fh_dashboard_money($stats['payments_paid_month']); ?></strong></div>
                <div class="dash-snapshot-row"><span>Pending payments</span><strong><?php echo number_format($stats['payments_pending']); ?></strong></div>
                <?php if ($stats['review_count'] > 0): ?>
                <div class="dash-snapshot-row"><span>Avg rating</span><strong><?php echo $stats['review_avg']; ?> ★ <span class="dash-muted">(<?php echo $stats['review_count']; ?>)</span></strong></div>
                <?php endif; ?>
                <?php if ($statsScope > 0): ?>
                <div class="dash-snapshot-row"><span>Venue page</span><strong><?php echo $stats['cms_published'] ? 'Published' : 'Draft'; ?></strong></div>
                <?php endif; ?>
            </div>
            <?php if ($statsScope > 0 && $biz): ?>
            <a href="business_page.php?business_id=<?php echo (int)$statsScope; ?>" class="dash-store-link">Edit Venue CMS →</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Venue Map -->
    <div class="dash-card dash-map-card">
        <h3>
            <?php echo $statsScope > 0 ? 'Venue Location' : 'Venue Map'; ?>
            <?php if ($mapPinCount > 0): ?>
            <span style="font-weight:400;color:#94a3b8;">— <?php echo number_format($mapPinCount); ?> pin<?php echo $mapPinCount === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </h3>
        <?php if ($mapPinCount === 0): ?>
        <div class="dash-map-box">
            <div class="dash-map-empty">
                No venue coordinates yet. Add latitude/longitude on the business account to show locations here.
            </div>
        </div>
        <?php else: ?>
        <div class="dash-map-box">
            <div id="customerMap"></div>
        </div>
        <div class="dash-map-legend">
            <span><i class="pin-business"></i> Venue</span>
            <span class="dash-map-attribution">Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors</span>
        </div>
        <div class="dash-map-status" id="mapGeocodeStatus" aria-live="polite"></div>
        <?php endif; ?>
    </div>

    <!-- Charts Row -->
    <div class="dash-grid-2">
        <div class="dash-card">
            <h3>Booking Analytics — Last 7 Days</h3>
            <div class="dash-chart-box"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="dash-card">
            <h3>Bookings by Status</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="dash-grid-3">
        <div class="dash-card">
            <h3>Top Courts</h3>
            <?php if (empty($stats['top_courts'])): ?>
            <p class="dash-empty">No court booking data yet.</p>
            <?php else: ?>
            <?php foreach ($stats['top_courts'] as $court): ?>
            <div class="dash-top-item">
                <div>
                    <div class="dash-top-item-name"><?php echo htmlspecialchars($court['name']); ?></div>
                    <div class="dash-top-item-meta"><?php echo number_format($court['qty']); ?> booking<?php echo $court['qty'] !== 1 ? 's' : ''; ?></div>
                </div>
                <div class="dash-top-item-rev"><?php echo fh_dashboard_money($court['revenue']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($isPortalDash && !empty($stats['business_chart']['labels'])): ?>
        <div class="dash-card" style="grid-column: span 2;">
            <h3>Revenue by Venue</h3>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="businessChart"></canvas></div>
        </div>
        <?php else: ?>
        <div class="dash-card">
            <h3>Court vs Lesson</h3>
            <?php if (empty($stats['type_chart']['labels'])): ?>
            <p class="dash-empty">No booking type data yet.</p>
            <?php else: ?>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="typeChart"></canvas></div>
            <?php endif; ?>
        </div>
        <div class="dash-card">
            <h3>Top Courts Chart</h3>
            <?php if (empty($stats['top_courts'])): ?>
            <p class="dash-empty">No chart data yet.</p>
            <?php else: ?>
            <div class="dash-chart-box dash-chart-box-sm"><canvas id="topCourtsChart"></canvas></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Bookings -->
    <div class="dash-card">
        <h3>Recent Bookings <?php if ($isPortalDash): ?><span style="font-weight:400;color:#94a3b8;">— All venues</span><?php endif; ?></h3>
        <div style="overflow-x:auto;">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>When</th>
                        <?php if ($isPortalDash): ?><th>Venue</th><?php endif; ?>
                        <th>Player</th>
                        <th>Court / Type</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th align="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['recent_bookings'])): ?>
                    <tr><td colspan="8" style="color:#94a3b8;text-align:center;padding:24px;">No bookings yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($stats['recent_bookings'] as $bk): ?>
                    <tr>
                        <td>
                            <strong>
                                <?php
                                $ref = !empty($bk['booking_receipt_no']) ? $bk['booking_receipt_no'] : '#' . (int)$bk['booking_id'];
                                echo htmlspecialchars($ref);
                                ?>
                            </strong>
                        </td>
                        <td><?php echo date('M j, g:i A', strtotime($bk['start_datetime'])); ?></td>
                        <?php if ($isPortalDash): ?>
                        <td><?php echo htmlspecialchars($bk['business_name'] ? $bk['business_name'] : '—'); ?></td>
                        <?php endif; ?>
                        <td><?php
                            $player = trim($bk['customer_fname'] . ' ' . $bk['customer_lname']);
                            echo htmlspecialchars($player !== '' ? $player : 'Guest');
                        ?></td>
                        <td>
                            <?php echo htmlspecialchars($bk['court_name'] ? $bk['court_name'] : '—'); ?>
                            <?php if (!empty($bk['booking_type']) && $bk['booking_type'] !== 'court'): ?>
                            <span class="dash-muted">· <?php echo htmlspecialchars($bk['booking_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="dash-badge <?php echo fh_dashboard_status_class($bk['booking_status']); ?>"><?php echo htmlspecialchars(ucfirst($bk['booking_status'])); ?></span></td>
                        <td><span class="dash-badge <?php echo fh_dashboard_status_class(isset($bk['payment_status']) ? $bk['payment_status'] : 'unpaid'); ?>"><?php echo htmlspecialchars(ucfirst(isset($bk['payment_status']) ? $bk['payment_status'] : 'unpaid')); ?></span></td>
                        <td align="right"><strong><?php echo fh_dashboard_money($bk['total_price']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($isPortalDash): ?>
    <?php echo fh_admin_business_selector($conn, 'index.php'); ?>
    <p style="font-size:13px;color:#64748b;">Select a venue above to switch to venue-specific analytics.</p>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    var salesLabels = <?php echo json_encode($stats['sales_chart']['labels']); ?>;
    var salesRevenue = <?php echo json_encode($stats['sales_chart']['revenue']); ?>;
    var salesBookings = <?php echo json_encode($stats['sales_chart']['bookings']); ?>;

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
                    label: 'Bookings',
                    data: salesBookings,
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
                y1: { position: 'right', title: { display: true, text: 'Bookings' }, grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
            }
        }
    });

    var statusLabels = <?php echo json_encode($stats['status_chart']['labels']); ?>;
    var statusValues = <?php echo json_encode($stats['status_chart']['values']); ?>;
    var statusColors = {
        'Pending': '#f59e0b',
        'Confirmed': '#3b82f6',
        'Completed': '#10b981',
        'Cancelled': '#ef4444'
    };
    var statusBg = statusLabels.map(function(l) { return statusColors[l] || '#64748b'; });

    if (statusLabels.length) {
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{ data: statusValues, backgroundColor: statusBg, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    var topCourts = <?php echo json_encode($stats['top_courts']); ?>;
    if (document.getElementById('topCourtsChart') && topCourts.length) {
        new Chart(document.getElementById('topCourtsChart'), {
            type: 'bar',
            data: {
                labels: topCourts.map(function(i) { return i.name.length > 18 ? i.name.substring(0, 16) + '…' : i.name; }),
                datasets: [{
                    label: 'Bookings',
                    data: topCourts.map(function(i) { return i.qty; }),
                    backgroundColor: '#40916c',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { color: '#f1f5f9' }, ticks: { precision: 0 } }, y: { grid: { display: false } } }
            }
        });
    }

    var typeLabels = <?php echo json_encode($stats['type_chart']['labels']); ?>;
    var typeValues = <?php echo json_encode($stats['type_chart']['values']); ?>;
    if (document.getElementById('typeChart') && typeLabels.length) {
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: typeLabels,
                datasets: [{ data: typeValues, backgroundColor: ['#2d6a4f', '#0891b2', '#7c3aed'], borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    <?php if ($isPortalDash && !empty($stats['business_chart']['labels'])): ?>
    new Chart(document.getElementById('businessChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($stats['business_chart']['labels']); ?>,
            datasets: [
                { label: 'Revenue (₱)', data: <?php echo json_encode($stats['business_chart']['revenue']); ?>, backgroundColor: '#2d6a4f', borderRadius: 6 },
                { label: 'Bookings', data: <?php echo json_encode($stats['business_chart']['bookings']); ?>, backgroundColor: '#93c5fd', borderRadius: 6 }
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
    venues: <?php echo json_encode($mapLocations['venues']); ?>,
    customers: []
};
<?php endif; ?>
</script>
<?php if ($mapPinCount > 0): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="js/dashboard-map.js?v=20260804a"></script>
<?php endif; ?>

<?php include('footer.php'); ?>
