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
if ($filterDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $parsed = strtotime($filterDate);
    $filterDate = $parsed ? date('Y-m-d', $parsed) : '';
}
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker3.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
.bk-filter {
    padding: 16px 18px;
    margin: 0 0 18px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
}
.bk-filter .form-label-sm {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin: 0 0 6px;
}
.bk-filter .btn-filter {
    background: #2d6a4f;
    border-color: #2d6a4f;
    font-weight: 700;
}
.bk-filter .btn-filter:hover {
    background: #1b4332;
    border-color: #1b4332;
}
.bk-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
}
.bk-table th,
.bk-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}
.bk-table th {
    background: #f8fafc;
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
}
.bk-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: #e2e8f0;
}
.bk-badge.confirmed { background: #d8f3dc; color: #1b4332; }
.bk-badge.pending { background: #fff3cd; color: #856404; }
.bk-badge.cancelled { background: #f8d7da; color: #842029; }
.bk-badge.completed { background: #cfe2ff; color: #084298; }
.bk-update-form .custom-select {
    margin-bottom: 6px;
    max-width: 140px;
}
.bk-update-form .btn-save {
    background: #2d6a4f;
    border-color: #2d6a4f;
    font-weight: 700;
}
.bk-update-form .btn-save:hover {
    background: #1b4332;
    border-color: #1b4332;
}
.datepicker {
    z-index: 1060 !important;
}
.datepicker table tr td.active,
.datepicker table tr td.active:hover,
.datepicker table tr td.active.disabled,
.datepicker table tr td.active.disabled:hover {
    background: #2d6a4f !important;
    background-image: none !important;
}
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Bookings</h1>
        <p>Confirm, complete, or cancel court reservations for this venue.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'bookings.php'); ?>

    <form class="bk-filter" method="get" action="bookings.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2 mb-md-0">
                <label class="form-label-sm" for="status">Status</label>
                <select name="status" id="status" class="custom-select">
                    <option value="">All</option>
                    <?php foreach (array('pending', 'confirmed', 'cancelled', 'completed') as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3 mb-2 mb-md-0">
                <label class="form-label-sm" for="date">Date</label>
                <div class="input-group date" id="bookingDatePicker" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                    <input type="text" class="form-control" name="date" id="date" value="<?php echo htmlspecialchars($filterDate); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                    </div>
                </div>
            </div>
            <div class="form-group col-md-4 mb-2 mb-md-0">
                <label class="form-label-sm" for="q">Search</label>
                <input type="search" class="form-control" name="q" id="q" value="<?php echo htmlspecialchars($filterQ); ?>" placeholder="Name, email, receipt">
            </div>
            <div class="form-group col-md-2 mb-0">
                <button type="submit" class="btn btn-success btn-block btn-filter">Filter</button>
            </div>
        </div>
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
                    <form class="bk-update-form" method="post" action="bookings.php?business_id=<?php echo (int)$adminBusinessId; ?>&status=<?php echo urlencode($filterStatus); ?>&date=<?php echo urlencode($filterDate); ?>&q=<?php echo urlencode($filterQ); ?>">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                        <select name="booking_status" class="custom-select custom-select-sm">
                            <?php foreach (array('pending', 'confirmed', 'completed', 'cancelled') as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $b['booking_status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="btnUpdateStatus" value="1" class="btn btn-sm btn-success btn-save">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function ($) {
    $('#bookingDatePicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        clearBtn: true,
        orientation: 'bottom auto',
        container: 'body'
    });
})(jQuery);
</script>

<?php include('footer.php'); ?>
