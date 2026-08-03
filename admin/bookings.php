<?php
include('config.php');
include_once(__DIR__ . '/../includes/booking_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

if (isset($_POST['btnUpdateStatus'])) {
    $bookingId = (int)$_POST['booking_id'];
    $status = isset($_POST['booking_status']) ? $_POST['booking_status'] : '';
    $booking = pb_booking_fetch($conn, $bookingId);
    if ($booking && fh_auth_can_access_business((int)$booking['business_id'])) {
        pb_booking_set_status($conn, $bookingId, $status);
    }
    $qs = http_build_query(array(
        'business_id' => $adminBusinessId,
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'date' => isset($_GET['date']) ? $_GET['date'] : '',
        'q' => isset($_GET['q']) ? $_GET['q'] : '',
    ));
    header('Location: bookings.php?' . $qs);
    exit;
}

$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$filterQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filters = array();
if ($filterStatus !== '') {
    $filters['status'] = $filterStatus;
}
if ($filterDate !== '') {
    $filters['date'] = $filterDate;
}
if ($filterQ !== '') {
    $filters['q'] = $filterQ;
}
$bookings = pb_bookings_for_business($conn, $adminBusinessId, $filters);

$titleheader = 'Bookings';
include('header.php');
include('navigation.php');
?>

<style>
.bk-filter { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; padding:14px 16px; margin:0 0 18px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(15,23,42,.06); }
.bk-filter label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin:0 0 6px; }
.bk-filter input, .bk-filter select { padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; }
.bk-btn { padding:9px 16px; border:none; border-radius:8px; background:#2d6a4f; color:#fff; font-weight:700; cursor:pointer; }
.bk-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(15,23,42,.06); }
.bk-table th, .bk-table td { padding:12px 14px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; vertical-align:top; }
.bk-table th { background:#f8fafc; font-size:11px; text-transform:uppercase; color:#64748b; }
.bk-badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#e2e8f0; }
.bk-badge.confirmed { background:#d8f3dc; color:#1b4332; }
.bk-badge.pending { background:#fff3cd; color:#856404; }
.bk-badge.cancelled { background:#f8d7da; color:#842029; }
.bk-badge.completed { background:#cfe2ff; color:#084298; }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Bookings</h1>
        <p>Confirm, complete, or cancel court reservations for this venue.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'bookings.php'); ?>

    <form class="bk-filter" method="get" action="bookings.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All</option>
                <?php foreach (array('pending','confirmed','cancelled','completed') as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="date">Date</label>
            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filterDate); ?>">
        </div>
        <div>
            <label for="q">Search</label>
            <input type="search" name="q" id="q" value="<?php echo htmlspecialchars($filterQ); ?>" placeholder="Name, email, receipt">
        </div>
        <button type="submit" class="bk-btn">Filter</button>
    </form>

    <table class="bk-table">
        <thead>
            <tr>
                <th>Receipt</th>
                <th>When</th>
                <th>Court</th>
                <th>Player</th>
                <th>Pay</th>
                <th>Status</th>
                <th>Update</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($bookings)): ?>
            <tr><td colspan="7">No bookings found.</td></tr>
        <?php else: foreach ($bookings as $b): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($b['booking_receipt_no'] ?: ('#' . $b['booking_id'])); ?></strong></td>
                <td>
                    <?php echo htmlspecialchars(date('M j, Y', strtotime($b['start_datetime']))); ?><br>
                    <span style="color:#64748b;"><?php echo htmlspecialchars(date('g:i A', strtotime($b['start_datetime'])) . ' – ' . date('g:i A', strtotime($b['end_datetime']))); ?></span>
                </td>
                <td><?php echo htmlspecialchars($b['court_name']); ?></td>
                <td>
                    <?php echo htmlspecialchars(trim($b['customer_fname'] . ' ' . $b['customer_lname'])); ?><br>
                    <span style="color:#64748b;font-size:12px;"><?php echo htmlspecialchars($b['customer_email']); ?><br><?php echo htmlspecialchars($b['customer_contact']); ?></span>
                </td>
                <td>₱<?php echo number_format((float)$b['total_price'], 2); ?><br>
                    <span style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars(str_replace('_', ' ', $b['payment_type'])); ?>
                    <?php if (!empty($b['payment_status'])): ?> · <?php echo htmlspecialchars($b['payment_status']); endif; ?></span>
                </td>
                <td><span class="bk-badge <?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo htmlspecialchars(pb_booking_status_label($b['booking_status'])); ?></span></td>
                <td>
                    <form method="post" action="bookings.php?business_id=<?php echo (int)$adminBusinessId; ?>&status=<?php echo urlencode($filterStatus); ?>&date=<?php echo urlencode($filterDate); ?>&q=<?php echo urlencode($filterQ); ?>">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                        <select name="booking_status" style="margin-bottom:6px;padding:6px;border-radius:6px;border:1px solid #cbd5e1;">
                            <?php foreach (array('pending','confirmed','completed','cancelled') as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $b['booking_status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="btnUpdateStatus" value="1" class="bk-btn">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include('footer.php'); ?>
