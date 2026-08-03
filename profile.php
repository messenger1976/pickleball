<?php
include('admin/config.php');

if (!isset($_SESSION['customers_id'])) {
    header('Location: login.php');
    exit;
}

$customer_id = (int)$_SESSION['customers_id'];
$usersql = $conn->query("SELECT * FROM customers WHERE customers_id = $customer_id LIMIT 1");
$row = $usersql ? $usersql->fetch_assoc() : null;

if (!$row) {
    unset($_SESSION['customers_id']);
    header('Location: login.php');
    exit;
}

$email = $row['customers_email'];
$firstname = $row['customers_firstname'];
$lastname = $row['customers_lastname'];
$address = $row['customers_complete_address'];
$contact = $row['customers_phone'];
$profilePic = !empty($row['customers_profile_pic']) ? $row['customers_profile_pic'] : '';
$custCountry = !empty($row['customers_country']) ? $row['customers_country'] : 'Philippines';
$custProvince = !empty($row['customers_province']) ? $row['customers_province'] : 'Bohol';
$custTown = !empty($row['customers_town']) ? $row['customers_town'] : 'Tagbilaran City';
$custBarangay = !empty($row['customers_barangay']) ? $row['customers_barangay'] : '';
$custZipcode = !empty($row['customers_zipcode']) ? $row['customers_zipcode'] : '';

include_once(__DIR__ . '/includes/geo_helper.php');
fh_geo_ensure_seeded($conn);
$geoCountries = fh_geo_get_countries($conn);
$phCountryId = 0;
foreach ($geoCountries as $gc) {
    if ($gc['country_name'] === 'Philippines') {
        $phCountryId = (int)$gc['country_id'];
        break;
    }
}
$selectedCountryId = $phCountryId;
foreach ($geoCountries as $gc) {
    if ($gc['country_name'] === $custCountry) {
        $selectedCountryId = (int)$gc['country_id'];
        break;
    }
}
$geoProvinces = $selectedCountryId > 0 ? fh_geo_get_provinces($conn, $selectedCountryId) : array();
$selectedProvinceId = 0;
foreach ($geoProvinces as $gp) {
    if ($gp['province_name'] === $custProvince) {
        $selectedProvinceId = (int)$gp['province_id'];
        break;
    }
}
$geoTowns = $selectedProvinceId > 0 ? fh_geo_get_towns($conn, $selectedProvinceId) : array();
if ($custZipcode === '' && $custProvince !== '' && $custTown !== '') {
    $custZipcode = fh_geo_default_zipcode($conn, $custProvince, $custTown);
}
$selectedTownId = fh_geo_find_town_id($conn, $custProvince, $custTown);
$geoBarangays = $selectedTownId > 0 ? fh_geo_get_barangays($conn, $selectedTownId) : array();
$hasBarangayList = count($geoBarangays) > 0;
$isPhilippinesCountry = ($custCountry === 'Philippines');

$displayName = trim($firstname . ' ' . $lastname);
if ($displayName === '') {
    $displayName = 'Your Profile';
}

$initials = '';
if ($firstname !== '') {
    $initials .= strtoupper(substr($firstname, 0, 1));
}
if ($lastname !== '') {
    $initials .= strtoupper(substr($lastname, 0, 1));
}
if ($initials === '') {
    $initials = 'U';
}

$pagetitle = 'My Profile — Food Park Portal';
$bodyClass = 'portal-order-page';
$extraStylesheets = array(
    'css/portal-home.css?v=20260704',
    'css/portal-order.css?v=20260714q'
);
include('header.php');
?>

<div class="portal-order-hero portal-order-hero-compact">
    <div class="portal-order-hero-inner">
        <a class="portal-order-back" href="home.php">&larr; Back to Home</a>
        <span class="portal-order-badge">Account</span>
        <h1>My Profile</h1>
        <p>Manage your personal details, profile photo, and account security.</p>
    </div>
</div>

<div class="portal-order-body portal-profile-body">
    <div class="portal-profile-layout">
        <aside class="portal-profile-aside">
            <div class="portal-cart-panel portal-profile-avatar-card">
                <div class="portal-profile-avatar-wrap">
                    <?php if ($profilePic !== ''): ?>
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile photo" class="portal-profile-avatar-img" id="profileAvatarImg">
                    <?php else: ?>
                        <div class="portal-profile-avatar-placeholder" id="profileAvatarPlaceholder"><?php echo htmlspecialchars($initials); ?></div>
                        <img src="" alt="Profile photo" class="portal-profile-avatar-img" id="profileAvatarImg" style="display:none">
                    <?php endif; ?>
                </div>
                <h2><?php echo htmlspecialchars($displayName); ?></h2>
                <p class="portal-profile-email"><?php echo htmlspecialchars($email); ?></p>

                <form id="frmAvatarUpload" enctype="multipart/form-data">
                    <label class="portal-profile-upload-btn" for="avatarInput">
                        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                        Change Photo
                    </label>
                </form>
                <p class="portal-profile-upload-hint">JPG, PNG, GIF, or WebP &middot; Max 2 MB</p>
                <div id="avatarMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>
            </div>

            <div class="portal-profile-quick-links">
                <a href="orders.php">View My Orders</a>
                <a href="logout.php">Sign Out</a>
            </div>
        </aside>

        <div class="portal-profile-main">
            <div class="portal-cart-panel">
                <div class="portal-cart-panel-head">
                    <h2>Personal Information</h2>
                </div>
                <div class="portal-login-panel-body portal-profile-form-body">
                    <div id="profileMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                    <form action="#" method="POST" id="ProfileForm">
                        <input type="hidden" id="customer_id" name="customer_id" value="<?php echo $customer_id; ?>">

                        <div class="portal-profile-grid">
                            <div class="portal-login-field">
                                <label for="email">Email</label>
                                <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                            </div>
                            <div class="portal-login-field">
                                <label for="contact">Contact Number</label>
                                <input type="tel" id="contact" name="contact" placeholder="e.g. 0915xxxxxxx" value="<?php echo htmlspecialchars($contact); ?>">
                                <span class="portal-login-field-error" id="contact-error"></span>
                            </div>
                            <div class="portal-login-field">
                                <label for="fname">First Name</label>
                                <input type="text" id="fname" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" required>
                                <span class="portal-login-field-error" id="fname-error"></span>
                            </div>
                            <div class="portal-login-field">
                                <label for="lname">Last Name</label>
                                <input type="text" id="lname" name="lastname" value="<?php echo htmlspecialchars($lastname); ?>" required>
                                <span class="portal-login-field-error" id="lname-error"></span>
                            </div>
                        </div>

                        <input type="hidden" id="company" name="company" value="0" />
                        <input type="hidden" id="department" name="department" value="0" />

                        <h3 class="portal-profile-section-title">Delivery Address</h3>
                        <div class="portal-profile-grid portal-profile-address-grid">
                            <div class="portal-login-field portal-profile-field-full">
                                <label for="address">Complete Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>" placeholder="House no., street, barangay" autocomplete="street-address">
                                <span class="portal-login-field-error" id="address-error"></span>
                            </div>
                            <div class="portal-login-field">
                                <label for="country">Country</label>
                                <select id="country" name="country" data-ph-id="<?php echo (int)$phCountryId; ?>">
                                    <?php echo fh_geo_options_html($geoCountries, 'country_id', 'country_name', $custCountry, 'Select country'); ?>
                                </select>
                                <span class="portal-login-field-error" id="country-error"></span>
                            </div>
                            <div class="portal-login-field" id="province_field">
                                <label for="province">Province</label>
                                <select id="province" name="province" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                                    <?php echo fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $custProvince, 'Select province'); ?>
                                </select>
                                <input type="text" id="province_text" name="province_text" placeholder="Enter province / state" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custProvince) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                                <span class="portal-login-field-error" id="province-error"></span>
                            </div>
                            <div class="portal-login-field" id="town_field">
                                <label for="town">Town / City</label>
                                <select id="town" name="town" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                                    <?php echo fh_geo_options_html($geoTowns, 'town_id', 'town_name', $custTown, 'Select town / city'); ?>
                                </select>
                                <input type="text" id="town_text" name="town_text" placeholder="Enter town / city" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custTown) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                                <span class="portal-login-field-error" id="town-error"></span>
                            </div>
                            <div class="portal-login-field">
                                <label for="zipcode">Zipcode</label>
                                <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($custZipcode); ?>" placeholder="Zip / Postal code" autocomplete="postal-code" inputmode="numeric" />
                                <span class="portal-login-field-error" id="zipcode-error"></span>
                            </div>
                            <div class="portal-login-field portal-profile-field-full" id="barangay_field">
                                <label for="barangay">Barangay</label>
                                <select id="barangay" name="barangay" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? '' : 'style="display:none;"'; ?>>
                                    <?php echo fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', $custBarangay, 'Select barangay'); ?>
                                </select>
                                <input type="text" id="barangay_text" name="barangay_text" placeholder="Enter barangay" value="<?php echo (!$isPhilippinesCountry || !$hasBarangayList) ? htmlspecialchars($custBarangay) : ''; ?>" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? 'style="display:none;"' : ''; ?> />
                                <span class="portal-login-field-error" id="barangay-error"></span>
                            </div>
                        </div>

                        <button type="submit" class="portal-login-btn portal-login-btn-primary" id="btnSaveProfile">Save Changes</button>
                    </form>
                </div>
            </div>

            <div class="portal-cart-panel portal-profile-security-panel">
                <div class="portal-cart-panel-head">
                    <h2>Change Password</h2>
                </div>
                <div class="portal-login-panel-body portal-profile-form-body">
                    <p class="portal-login-lead">For your security, enter your current password before setting a new one.</p>
                    <div id="passwordMsgbox" class="portal-login-panel-alert" style="display:none" role="alert" aria-live="polite"></div>

                    <form action="#" method="POST" id="frmChangePassword">
                        <div class="portal-login-field">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password" autocomplete="current-password">
                            <span class="portal-login-field-error" id="current_password-error"></span>
                        </div>
                        <div class="portal-login-field">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters" autocomplete="new-password">
                            <span class="portal-login-field-error" id="new_password-error"></span>
                        </div>
                        <div class="portal-login-field">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" autocomplete="new-password">
                            <span class="portal-login-field-error" id="confirm_password-error"></span>
                        </div>

                        <ul class="portal-profile-password-rules">
                            <li>At least 8 characters long</li>
                            <li>Must be different from your current password</li>
                            <li>Both new password fields must match</li>
                        </ul>

                        <button type="submit" class="portal-login-btn portal-login-btn-secondary" id="btnChangePassword">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
