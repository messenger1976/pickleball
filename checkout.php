<?php
include('admin/config.php');
include_once(__DIR__ . '/includes/business_helper.php');

if (!isset($_SESSION['cart']) || count($_SESSION['cart']) <= 0) {
    $redirectBiz = isset($_SESSION['cart_business_id']) ? (int)$_SESSION['cart_business_id'] : fh_portal_business_id($conn);
    header('Location: ' . ($redirectBiz > 0 ? 'index.php?business_id=' . $redirectBiz : 'home.php'));
    exit;
}

$isGuestCheckout = !isset($_SESSION['customers_id']);
if ($isGuestCheckout && !isset($_GET['guest'])) {
    // Must sign in or explicitly continue as guest (from login page)
    header('Location: login.php?from=cart');
    exit;
}
if (isset($_GET['guest'])) {
    $isGuestCheckout = true;
}

$checkoutBusiness = null;
if (isset($_SESSION['cart_business_id']) && (int)$_SESSION['cart_business_id'] > 0) {
    $checkoutBusiness = fh_resolve_business($conn, $_SESSION['cart_business_id']);
}

$customer_id = 0;
$firstname = '';
$lastname = '';
$email = '';
$company = 0;
$department = 0;
$address = '';
$contact = '';
$email_flag = '';
$custCountry = 'Philippines';
$custProvince = 'Bohol';
$custTown = 'Tagbilaran City';
$custBarangay = '';
$custZipcode = '';

if (!$isGuestCheckout && isset($_SESSION['customers_id'])) {
    $customer_id = (int)$_SESSION['customers_id'];
    $usersql = $conn->query("SELECT * FROM customers WHERE customers_id = $customer_id LIMIT 1");
    $row = $usersql ? $usersql->fetch_assoc() : null;
    if ($row) {
        $email = $row['customers_email'];
        $firstname = $row['customers_firstname'];
        $lastname = $row['customers_lastname'];
        $company = $row['customers_company'];
        $department = $row['customers_department'];
        $address = $row['customers_complete_address'];
        $contact = $row['customers_phone'];
        $email_flag = ' readonly ';
        if (!empty($row['customers_country'])) {
            $custCountry = $row['customers_country'];
        }
        if (!empty($row['customers_province'])) {
            $custProvince = $row['customers_province'];
        }
        if (!empty($row['customers_town'])) {
            $custTown = $row['customers_town'];
        }
        if (!empty($row['customers_barangay'])) {
            $custBarangay = $row['customers_barangay'];
        }
        if (isset($row['customers_zipcode']) && $row['customers_zipcode'] !== '') {
            $custZipcode = $row['customers_zipcode'];
        }
    } else {
        unset($_SESSION['customers_id']);
        header('Location: login.php?from=cart');
        exit;
    }
}

$menudate = isset($_SESSION['menudate']) ? $_SESSION['menudate'] : date('Y-m-d');
$isPreorderCheckout = (strtotime($menudate) > strtotime(date('Y-m-d')));
$preorderMaxDays = 14;
if ($checkoutBusiness) {
    $preSettings = fh_preorder_settings($conn, (int)$checkoutBusiness['business_id']);
    $preorderMaxDays = (int)$preSettings['preorder_max_days'];
}
$shippingDateMin = date('Y-m-d');
$shippingDateMax = date('Y-m-d', strtotime('+' . max(1, $preorderMaxDays) . ' days'));
$shippingDateDefault = fh_clamp_menudate($menudate, $preorderMaxDays);
$price_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $price_total += ((float)$item['qty'] * (float)$item['price']);
}
$price_tax = 0;
$price_other = 0;
$price_grand = round($price_total + $price_tax + $price_other, 2);
$_SESSION['total_amount'] = $price_grand;

$pagetitle = 'Checkout — Food Park Portal';
$bodyClass = 'portal-order-page';
$extraStylesheets = array(
    'css/portal-home.css?v=20260704',
    'css/portal-order.css?v=20260715a',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css'
);
include('header.php');
?>

<div class="portal-order-hero portal-order-hero-compact">
    <div class="portal-order-hero-inner">
        <a class="portal-order-back" href="cart.php">&larr; Back to Cart</a>
        <span class="portal-order-badge">Checkout</span>
        <h1>Complete Your Order</h1>
        <p>
            <?php if ($checkoutBusiness): ?>
                Ordering from <?php echo htmlspecialchars($checkoutBusiness['business_name']); ?>
            <?php else: ?>
                Enter your details and confirm your order below.
            <?php endif; ?>
            <?php if ($isGuestCheckout): ?> &mdash; Guest checkout<?php endif; ?>
        </p>
    </div>
</div>

<div class="portal-order-body">
    <?php if ($checkoutBusiness): ?>
    <div class="portal-cart-restaurant-bar">
        <span>Your order will be sent to: <strong><?php echo htmlspecialchars($checkoutBusiness['business_name']); ?></strong></span>
    </div>
    <?php endif; ?>

    <div class="portal-checkout-layout">
        <div class="portal-checkout-main">
            <div class="portal-cart-panel">
                <div class="portal-cart-panel-head">
                    <h2>Customer Information</h2>
                </div>
                <div class="portal-checkout-form-wrap">
                    <form id="checkout" action="#" method="POST" novalidate>
                        <input type="hidden" id="customer_id" value="<?php echo (int)$customer_id; ?>" />
                        <div id="checkoutMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                        <div class="portal-checkout-grid">
                            <div class="portal-checkout-section">
                                <h3>Delivery Address</h3>
                                <div class="portal-checkout-field">
                                    <label for="fname">First Name <span class="portal-checkout-required">*</span></label>
                                    <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($firstname); ?>" autocomplete="given-name">
                                    <span class="portal-checkout-field-error" id="fname-error"></span>
                                </div>
                                <div class="portal-checkout-field">
                                    <label for="lname">Last Name <span class="portal-checkout-required">*</span></label>
                                    <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($lastname); ?>" autocomplete="family-name">
                                    <span class="portal-checkout-field-error" id="lname-error"></span>
                                </div>
                                <div class="portal-checkout-field">
                                    <label for="email">Email <?php if ($isGuestCheckout): ?><span class="portal-checkout-required">*</span><?php endif; ?></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" <?php echo $email_flag; ?> autocomplete="email">
                                    <span class="portal-checkout-field-error" id="email-error"></span>
                                </div>
                                <input type="hidden" id="company" name="company" value="0" />
                                <input type="hidden" id="department" name="department" value="0" />
                                <div class="portal-checkout-field">
                                    <label for="address">Complete Address <span class="portal-checkout-required portal-checkout-required-delivery">*</span></label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>" autocomplete="street-address" placeholder="House no., street, barangay">
                                    <span class="portal-checkout-field-error" id="address-error"></span>
                                </div>
                                <?php
                                include_once(__DIR__ . '/includes/geo_helper.php');
                                fh_geo_ensure_seeded($conn);
                                $geoCountries = fh_geo_get_countries($conn);
                                $phCountryId = 0;
                                $selectedCountryId = 0;
                                foreach ($geoCountries as $gc) {
                                    if ($gc['country_name'] === 'Philippines') {
                                        $phCountryId = (int)$gc['country_id'];
                                    }
                                    if ($gc['country_name'] === $custCountry) {
                                        $selectedCountryId = (int)$gc['country_id'];
                                    }
                                }
                                if ($selectedCountryId <= 0) {
                                    $selectedCountryId = $phCountryId;
                                    $custCountry = 'Philippines';
                                }
                                $isPhilippinesCountry = ($custCountry === 'Philippines');
                                $geoProvinces = $selectedCountryId > 0 ? fh_geo_get_provinces($conn, $selectedCountryId) : array();
                                $selectedProvinceId = 0;
                                foreach ($geoProvinces as $gp) {
                                    if ($gp['province_name'] === $custProvince) {
                                        $selectedProvinceId = (int)$gp['province_id'];
                                        break;
                                    }
                                }
                                if ($isPhilippinesCountry && $selectedProvinceId <= 0) {
                                    $custProvince = 'Bohol';
                                    foreach ($geoProvinces as $gp) {
                                        if ($gp['province_name'] === 'Bohol') {
                                            $selectedProvinceId = (int)$gp['province_id'];
                                            break;
                                        }
                                    }
                                }
                                $geoTowns = $selectedProvinceId > 0 ? fh_geo_get_towns($conn, $selectedProvinceId) : array();
                                if ($isPhilippinesCountry && $custZipcode === '') {
                                    $custZipcode = fh_geo_default_zipcode($conn, $custProvince, $custTown);
                                }
                                $selectedTownId = fh_geo_find_town_id($conn, $custProvince, $custTown);
                                $geoBarangays = $selectedTownId > 0 ? fh_geo_get_barangays($conn, $selectedTownId) : array();
                                $hasBarangayList = count($geoBarangays) > 0;
                                ?>
                                <div class="portal-checkout-field">
                                    <label for="country">Country <span class="portal-checkout-required">*</span></label>
                                    <select id="country" name="country" data-ph-id="<?php echo (int)$phCountryId; ?>">
                                        <?php echo fh_geo_options_html($geoCountries, 'country_id', 'country_name', $custCountry, 'Select country'); ?>
                                    </select>
                                    <span class="portal-checkout-field-error" id="country-error"></span>
                                </div>
                                <div class="portal-checkout-field" id="province_field">
                                    <label for="province">Province <span class="portal-checkout-required">*</span></label>
                                    <select id="province" name="province" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                                        <?php echo fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $custProvince, 'Select province'); ?>
                                    </select>
                                    <input type="text" id="province_text" name="province_text" placeholder="Enter province / state" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custProvince) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                                    <span class="portal-checkout-field-error" id="province-error"></span>
                                </div>
                                <div class="portal-checkout-field" id="town_field">
                                    <label for="town">Town / City <span class="portal-checkout-required">*</span></label>
                                    <select id="town" name="town" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                                        <?php echo fh_geo_options_html($geoTowns, 'town_id', 'town_name', $custTown, 'Select town / city'); ?>
                                    </select>
                                    <input type="text" id="town_text" name="town_text" placeholder="Enter town / city" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custTown) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                                    <span class="portal-checkout-field-error" id="town-error"></span>
                                </div>
                                <div class="portal-checkout-field" id="barangay_field">
                                    <label for="barangay">Barangay <span class="portal-checkout-required">*</span></label>
                                    <select id="barangay" name="barangay" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? '' : 'style="display:none;"'; ?>>
                                        <?php echo fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', $custBarangay, 'Select barangay'); ?>
                                    </select>
                                    <input type="text" id="barangay_text" name="barangay_text" placeholder="Enter barangay" value="<?php echo (!$isPhilippinesCountry || !$hasBarangayList) ? htmlspecialchars($custBarangay) : ''; ?>" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? 'style="display:none;"' : ''; ?> />
                                    <span class="portal-checkout-field-error" id="barangay-error"></span>
                                </div>
                                <div class="portal-checkout-field">
                                    <label for="zipcode">Zipcode</label>
                                    <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($custZipcode); ?>" placeholder="Zip / Postal code" autocomplete="postal-code" inputmode="numeric" />
                                    <span class="portal-checkout-field-error" id="zipcode-error"></span>
                                </div>
                                <div class="portal-checkout-field">
                                    <label for="contact">Contact Number <span class="portal-checkout-required">*</span></label>
                                    <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>" placeholder="e.g. 09171234567" autocomplete="tel">
                                    <span class="portal-checkout-field-error" id="contact-error"></span>
                                </div>
                            </div>

                            <div class="portal-checkout-section">
                                <h3>Payment</h3>
                                <div class="portal-checkout-options">
                                    <label class="portal-checkout-option">
                                        <input class="w3-radio" type="radio" id="cod" name="payment" value="cod" checked>
                                        Cash on Delivery
                                    </label>
                                </div>
                                <span class="portal-checkout-field-error" id="payment-error"></span>

                                <h3>Shipping Method</h3>
                                <div class="portal-checkout-options">
                                    <label class="portal-checkout-option">
                                        <input class="w3-radio" type="radio" id="delivery" name="shipping" value="delivery" checked>
                                        Delivery
                                    </label>
                                    <label class="portal-checkout-option">
                                        <input class="w3-radio" type="radio" id="pickup" name="shipping" value="pickup">
                                        Pick-up
                                    </label>
                                    <label class="portal-checkout-option portal-checkout-option-dinein">
                                        <input class="w3-radio" type="radio" id="dinein" name="shipping" value="dinein">
                                        <span>Dine-in</span>
                                        <span class="portal-dinein-table-wrap" id="dinein_table_wrap" hidden>
                                            <label for="dinein_table">Table No.</label>
                                            <input type="text" id="dinein_table" name="dinein_table" form="checkout" inputmode="numeric" maxlength="10" placeholder="e.g. 5" autocomplete="off" />
                                        </span>
                                    </label>
                                </div>
                                <span class="portal-checkout-field-error" id="dinein_table-error"></span>
                                <span class="portal-checkout-field-error" id="shipping-error"></span>

                                <h3>Remarks</h3>
                                <textarea id="remarks" name="remarks" rows="4" placeholder="Special instructions for your order (optional)"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <aside class="portal-cart-summary">
            <div class="portal-cart-panel">
                <div class="portal-cart-panel-head">
                    <h2>Order Summary</h2>
                </div>
                <div class="portal-cart-summary-body">
                    <div class="portal-cart-summary-row">
                        <span>Transaction date</span>
                        <strong><?php echo date('M j, Y'); ?></strong>
                    </div>
                    <div class="portal-cart-summary-row">
                        <span>Menu date</span>
                        <strong><?php echo date('M j, Y', strtotime($menudate)); ?></strong>
                    </div>
                    <?php if ($isPreorderCheckout): ?>
                    <div class="portal-cart-summary-row">
                        <span>Order type</span>
                        <strong>Pre-order</strong>
                    </div>
                    <?php endif; ?>
                    <div class="portal-cart-summary-row">
                        <span>Subtotal</span>
                        <strong>&#8369;<?php echo number_format($price_total, 2); ?></strong>
                    </div>
                    <div class="portal-cart-summary-row">
                        <span>Other charges</span>
                        <strong>&#8369;<?php echo number_format($price_other, 2); ?></strong>
                    </div>
                    <div class="portal-cart-summary-total">
                        <span>You pay</span>
                        <strong>&#8369;<?php echo number_format($price_grand, 2); ?></strong>
                    </div>
                    <p class="portal-menu-sub" style="margin:16px 0 0;font-size:13px;">Tap <strong>Confirm</strong> below to place your order.</p>
                </div>
            </div>
            <div class="portal-cart-panel portal-shipping-details-panel">
                <div class="portal-cart-panel-head">
                    <h2>Shipping Method Details</h2>
                </div>
                <div class="portal-cart-summary-body">
                    <p class="portal-shipping-details-hint"><?php echo $isPreorderCheckout ? 'Choose when you want this pre-order fulfilled.' : 'Choose preferred date and time for your order.'; ?></p>
                    <div class="portal-checkout-field">
                        <label for="shipping_date"><span id="shipping_date_label">Delivery Date</span> <span class="portal-checkout-required">*</span></label>
                        <div class="fh-picker-field">
                            <input type="text" class="fh-picker-input" id="shipping_date" name="shipping_date" form="checkout" value="<?php echo htmlspecialchars($shippingDateDefault); ?>" placeholder="Select date" autocomplete="off" readonly />
                            <button type="button" class="fh-picker-btn" id="shipping_date_btn" aria-label="Open date picker"><i class="fa fa-calendar"></i></button>
                        </div>
                        <span class="portal-checkout-field-error" id="shipping_date-error"></span>
                    </div>
                    <div class="portal-checkout-field">
                        <label for="shipping_time"><span id="shipping_time_label">Delivery Time</span> <span class="portal-checkout-required">*</span></label>
                        <div class="fh-picker-field">
                            <input type="text" class="fh-picker-input" id="shipping_time" name="shipping_time" form="checkout" value="12:00" placeholder="Select time" autocomplete="off" readonly />
                            <button type="button" class="fh-picker-btn" id="shipping_time_btn" aria-label="Open time picker"><i class="fa fa-clock-o"></i></button>
                        </div>
                        <span class="portal-checkout-field-error" id="shipping_time-error"></span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<iframe name="smsiframe" id="smsiframe" style="display:none;"></iframe>
<form id="smsformID" target="smsiframe" autocomplete="off" name="myform" method="POST" action="https://www.txtlibre.com/send_message-202001031230.php" style="display:none;">
    <input type="text" id="frm_senders_namex" name="frm_senders_namex" maxlength="15" />
    <input type="hidden" id="frm_sen" name="frm_sen" value="0530" />
    <input type="text" id="frm_senders_num" name="frm_senders_num" maxlength="13" />
    <input type="text" id="frm_recepient_num" name="frm_recepient_num" maxlength="13" />
    <textarea id="frm_messagex" name="frm_messagex" maxlength="180"></textarea>
    <input type="submit" id="btnSubmitSMS" name="submit" value="send message" />
</form>

<script>
var cart = new Cart();
window.fhCheckoutGuest = <?php echo $isGuestCheckout ? 'true' : 'false'; ?>;
window.fhCheckoutPreorder = <?php echo $isPreorderCheckout ? 'true' : 'false'; ?>;
window.fhShippingDateMin = <?php echo json_encode($shippingDateMin); ?>;
window.fhShippingDateMax = <?php echo json_encode($shippingDateMax); ?>;
window.fhShippingDateDefault = <?php echo json_encode($shippingDateDefault); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
(function() {
    if (typeof flatpickr === 'undefined' || !document.getElementById('shipping_date')) {
        return;
    }

    function clearPickerError(inputId) {
        var input = document.getElementById(inputId);
        var err = document.getElementById(inputId + '-error');
        if (input) {
            input.classList.remove('portal-input-error');
        }
        if (err) {
            err.textContent = '';
        }
        var msg = document.getElementById('checkoutMsgbox');
        if (msg) {
            msg.style.display = 'none';
        }
    }

    var datePicker = flatpickr('#shipping_date', {
        dateFormat: 'Y-m-d',
        altInput: false,
        minDate: window.fhShippingDateMin,
        maxDate: window.fhShippingDateMax,
        defaultDate: window.fhShippingDateDefault,
        disableMobile: true,
        position: 'auto',
        onChange: function() {
            clearPickerError('shipping_date');
        }
    });

    var timePicker = flatpickr('#shipping_time', {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        time_24hr: true,
        defaultDate: '12:00',
        minuteIncrement: 15,
        disableMobile: true,
        position: 'auto',
        onChange: function() {
            clearPickerError('shipping_time');
        }
    });

    var dateBtn = document.getElementById('shipping_date_btn');
    var timeBtn = document.getElementById('shipping_time_btn');
    if (dateBtn) {
        dateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            datePicker.toggle();
        });
    }
    if (timeBtn) {
        timeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            timePicker.toggle();
        });
    }
})();
</script>

<?php include('footer.php'); ?>
