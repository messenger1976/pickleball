<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/auth_helper.php');
include_once(__DIR__ . '/includes/court_helper.php');
include_once(__DIR__ . '/includes/booking_helper.php');
include_once(__DIR__ . '/includes/payment_helper.php');
include_once(__DIR__ . '/includes/membership_helper.php');
include_once(__DIR__ . '/includes/lesson_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customers_id']) || (int)$_SESSION['customers_id'] <= 0) {
    $return = 'book.php?' . http_build_query($_GET);
    header('Location: login.php?redirect=' . urlencode($return));
    exit;
}

$bookingType = isset($_GET['type']) && $_GET['type'] === 'lesson' ? 'lesson' : 'court';
$courtId = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;
$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end = isset($_GET['end']) ? trim($_GET['end']) : '';

$court = null;
$lesson = null;
$error = '';

if ($bookingType === 'lesson') {
    $lesson = pb_lesson_fetch($conn, $lessonId);
    if (!$lesson || (int)$lesson['lesson_status'] !== 1) {
        $error = 'Lesson not found or unavailable.';
    }
} else {
    $court = pb_court_fetch($conn, $courtId);
    if (!$court || (int)$court['court_status'] !== 1 || (int)$court['business_status'] !== 1) {
        $error = 'Court not found or unavailable.';
    } elseif ($start === '' || $end === '') {
        $error = 'Missing booking time.';
    }
}

$customer = null;
$cid = (int)$_SESSION['customers_id'];
$cSql = $conn->query("SELECT * FROM customers WHERE customers_id = $cid LIMIT 1");
if ($cSql) {
    $customer = $cSql->fetch_assoc();
}

$businessId = $court ? (int)$court['business_id'] : ($lesson ? (int)$lesson['business_id'] : 0);
$basePrice = $court ? (float)$court['price_per_slot'] : ($lesson ? (float)$lesson['price'] : 0);
$memberInfo = $businessId > 0 ? pb_apply_member_discount($conn, $cid, $businessId, $basePrice) : array('price' => $basePrice, 'discount_percent' => 0);

if ($error === '' && isset($_POST['btnConfirm'])) {
    $payType = (isset($_POST['payment_type']) && $_POST['payment_type'] === 'online') ? 'online' : 'pay_at_venue';
    $payload = array(
        'customers_id' => $cid,
        'start_datetime' => $start,
        'end_datetime' => $end,
        'customer_fname' => isset($_POST['fname']) ? $_POST['fname'] : ($customer ? $customer['customers_firstname'] : ''),
        'customer_lname' => isset($_POST['lname']) ? $_POST['lname'] : ($customer ? $customer['customers_lastname'] : ''),
        'customer_email' => isset($_POST['email']) ? $_POST['email'] : ($customer ? $customer['customers_email'] : ''),
        'customer_contact' => isset($_POST['contact']) ? $_POST['contact'] : ($customer ? $customer['customers_phone'] : ''),
        'booking_remarks' => isset($_POST['remarks']) ? $_POST['remarks'] : '',
        'payment_type' => $payType,
    );

    if ($bookingType === 'lesson') {
        $payload['lesson_id'] = $lessonId;
        $result = pb_lesson_booking_create($conn, $payload);
    } else {
        $payload['court_id'] = $courtId;
        $result = pb_booking_create($conn, $payload);
    }

    if (!empty($result['ok'])) {
        if ($payType === 'online') {
            $checkout = pb_paymongo_create_checkout($conn, array(
                'booking_id' => $result['booking_id'],
                'customers_id' => $cid,
                'business_id' => isset($result['business_id']) ? $result['business_id'] : $businessId,
                'amount' => $result['total_price'],
                'description' => 'Booking ' . $result['receipt_no'],
                'success_url' => pb_app_base_url() . '/my_bookings.php?paid=1&created=' . $result['booking_id'],
                'cancel_url' => pb_app_base_url() . '/my_bookings.php?paid=0&created=' . $result['booking_id'],
            ));
            if (!empty($checkout['ok']) && !empty($checkout['checkout_url'])) {
                header('Location: ' . $checkout['checkout_url']);
                exit;
            }
            $error = isset($checkout['msg']) ? $checkout['msg'] : 'Could not start online payment.';
        } else {
            header('Location: my_bookings.php?created=' . (int)$result['booking_id']);
            exit;
        }
    } else {
        $error = isset($result['msg']) ? $result['msg'] : 'Booking failed.';
    }
}

$pagetitle = 'Confirm Booking — Pickleball Portal';
$extraStylesheets = array('css/portal-order.css?v=20260803');
include('header.php');

$fname = $customer ? $customer['customers_firstname'] : '';
$lname = $customer ? $customer['customers_lastname'] : '';
$email = $customer ? $customer['customers_email'] : '';
$contact = $customer ? $customer['customers_phone'] : '';
$titleName = $court ? $court['business_name'] : ($lesson ? $lesson['business_name'] : '');
$itemLabel = $court ? ('Court: ' . $court['court_name']) : ($lesson ? ('Lesson: ' . $lesson['lesson_title']) : '');
?>

<div class="portal-checkout" style="max-width:720px;margin:calc(56px + 40px) auto 80px;padding:0 20px;">
    <h1>Confirm booking</h1>

    <?php if ($error !== '' && !$court && !$lesson): ?>
        <div class="w3-panel w3-pale-red w3-border"><?php echo htmlspecialchars($error); ?></div>
        <p><a href="home.php">← Back to venues</a></p>
    <?php elseif ($court || $lesson): ?>
        <?php if ($error !== ''): ?>
        <div class="w3-panel w3-pale-red w3-border"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="w3-card w3-padding" style="border-radius:12px;margin-bottom:20px;">
            <h2 style="margin-top:0;"><?php echo htmlspecialchars($titleName); ?></h2>
            <p><strong><?php echo htmlspecialchars($itemLabel); ?></strong></p>
            <p><strong>When:</strong> <?php echo htmlspecialchars(date('D, M j, Y g:i A', strtotime($start)) . ' – ' . date('g:i A', strtotime($end))); ?></p>
            <p><strong>Price:</strong> ₱<?php echo number_format((float)$memberInfo['price'], 2); ?>
                <?php if (!empty($memberInfo['discount_percent'])): ?>
                <span style="color:#2d6a4f;">(member <?php echo rtrim(rtrim(number_format($memberInfo['discount_percent'], 2), '0'), '.'); ?>% off)</span>
                <?php endif; ?>
            </p>
        </div>

        <form method="post" class="w3-card w3-padding" style="border-radius:12px;">
            <label><b>First name</b></label>
            <input class="w3-input w3-border" type="text" name="fname" value="<?php echo htmlspecialchars($fname); ?>" required>

            <label><b>Last name</b></label>
            <input class="w3-input w3-border" type="text" name="lname" value="<?php echo htmlspecialchars($lname); ?>" required>

            <label><b>Email</b></label>
            <input class="w3-input w3-border" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

            <label><b>Contact number</b></label>
            <input class="w3-input w3-border" type="tel" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required>

            <label><b>Notes (optional)</b></label>
            <textarea class="w3-input w3-border" name="remarks" rows="3" placeholder="Number of players, open play request…"></textarea>

            <h3 style="margin-top:18px;">Payment</h3>
            <label style="display:block;margin:8px 0;"><input type="radio" name="payment_type" value="pay_at_venue" checked> Pay at venue</label>
            <label style="display:block;margin:8px 0;"><input type="radio" name="payment_type" value="online"> Pay online (card / GCash / Maya)</label>

            <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" name="btnConfirm" value="1" class="w3-button w3-green w3-large">Confirm reservation</button>
                <a class="w3-button w3-light-grey w3-large" href="<?php echo htmlspecialchars(fh_store_link($businessId)); ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include('footer.php'); ?>
