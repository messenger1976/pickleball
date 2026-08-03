<?php
include('admin/config.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/geo_helper.php');

fh_geo_ensure_seeded($conn);
$geoCountries = fh_geo_get_countries($conn);
$custCountry = 'Philippines';
$custProvince = 'Bohol';
$custTown = 'Tagbilaran City';
$custBarangay = '';
$custZipcode = fh_geo_default_zipcode($conn, $custProvince, $custTown);

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
$geoTowns = $selectedProvinceId > 0 ? fh_geo_get_towns($conn, $selectedProvinceId) : array();
$selectedTownId = fh_geo_find_town_id($conn, $custProvince, $custTown);
$geoBarangays = $selectedTownId > 0 ? fh_geo_get_barangays($conn, $selectedTownId) : array();
$hasBarangayList = count($geoBarangays) > 0;

$pagetitle = "Partner With Us — Pickleball Portal";
$submitted = false;
$submitMessage = '';

include('header.php');
?>
<style>
.partner-page {
    margin-top: 56px;
    min-height: calc(100vh - 56px);
}
.partner-hero {
    position: relative;
    min-height: min(42vh, 360px);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    background: var(--pb-court);
    color: #fff;
}
.partner-hero-media {
    position: absolute;
    inset: 0;
    background:
        url('images/brand/hero-court-outdoor.jpg') center / cover no-repeat;
    transform: scale(1.06);
    animation: pbKen 14s ease-out both;
}
.partner-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(105deg, rgba(11, 47, 36, 0.92) 0%, rgba(11, 47, 36, 0.62) 55%, rgba(11, 47, 36, 0.35) 100%),
        linear-gradient(180deg, transparent 20%, rgba(11, 47, 36, 0.75) 100%);
}
.partner-hero-inner {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 720px;
    margin: 0 auto;
    padding: 48px 20px 40px;
    animation: pbRise 0.85s ease-out both;
}
.partner-hero-badge {
    display: inline-block;
    border-left: 3px solid var(--pb-ball);
    padding-left: 12px;
    margin-bottom: 14px;
    color: var(--pb-ball);
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-size: 12px;
}
.partner-hero h1 {
    margin: 0 0 12px;
    font-family: var(--pb-font-display);
    font-size: clamp(2rem, 5vw, 3.2rem);
    line-height: 0.95;
    letter-spacing: -0.02em;
    text-transform: uppercase;
    max-width: 14ch;
}
.partner-hero .lead {
    margin: 0;
    max-width: 40ch;
    font-size: 1.05rem;
    line-height: 1.55;
    color: rgba(255, 255, 255, 0.88);
}
.partner-signup {
    max-width: 720px;
    margin: 0 auto 80px;
    padding: 36px 20px 0;
    animation: pbRise 0.9s ease-out 0.12s both;
}
.partner-signup-card {
    background: var(--pb-white);
    border: 1px solid var(--pb-line);
    border-radius: var(--pb-radius);
    padding: 28px 24px 32px;
    box-shadow: var(--pb-shadow);
}
.partner-signup .form-kicker {
    margin: 0 0 6px;
    color: var(--pb-leaf);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 12px;
}
.partner-signup .form-title {
    margin: 0 0 8px;
    font-family: var(--pb-font-display);
    color: var(--pb-court);
    font-size: 1.45rem;
    text-transform: uppercase;
    letter-spacing: -0.01em;
}
.partner-signup .intro {
    color: var(--pb-muted);
    margin: 0 0 22px;
    line-height: 1.5;
}
.partner-signup label {
    display: block;
    font-weight: 700;
    margin: 14px 0 6px;
    color: var(--pb-ink);
}
.partner-signup input[type=text],
.partner-signup input[type=email],
.partner-signup input[type=tel],
.partner-signup select,
.partner-signup textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--pb-line);
    border-radius: 8px;
    font-size: 15px;
    margin: 0 0 4px;
    box-sizing: border-box;
    background: #fff;
    color: var(--pb-ink);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.partner-signup input:focus,
.partner-signup select:focus,
.partner-signup textarea:focus {
    outline: none;
    border-color: var(--pb-leaf);
    box-shadow: 0 0 0 3px rgba(47, 143, 99, 0.15);
}
.partner-signup .row2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.partner-signup .partner-field-full {
    grid-column: 1 / -1;
}
.partner-signup .partner-address-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 12px;
    margin-top: 4px;
}
.partner-signup .partner-address-grid > div {
    min-width: 0;
}
.partner-signup .hint {
    font-size: 12px;
    color: var(--pb-muted);
    margin: 0 0 8px;
    line-height: 1.4;
}
.partner-signup .hint strong {
    color: var(--pb-court);
}
.partner-signup .field-error {
    display: block;
    min-height: 0;
    margin: 0 0 4px;
    font-size: 12px;
    font-weight: 600;
    color: #dc2626;
    line-height: 1.3;
}
.partner-signup .field-error:empty {
    display: none;
}
.partner-signup .btn-submit {
    background: var(--pb-ball);
    color: var(--pb-ink);
    border: none;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: 800;
    border-radius: 8px;
    cursor: pointer;
    width: 100%;
    margin-top: 20px;
    transition: transform 0.2s ease, background 0.2s ease;
}
.partner-signup .btn-submit:hover {
    background: var(--pb-ball-deep);
    transform: translateY(-1px);
}
.partner-success {
    text-align: center;
    padding: 48px 24px;
}
.partner-success h2 {
    margin: 0 0 12px;
    font-family: var(--pb-font-display);
    color: var(--pb-court);
    text-transform: uppercase;
}
.partner-success p {
    color: var(--pb-muted);
    line-height: 1.5;
}
.partner-back {
    text-align: center;
    margin-top: 24px;
}
.partner-back a {
    color: var(--pb-court);
    font-weight: 700;
    text-decoration: none;
}
.partner-back a:hover {
    color: var(--pb-leaf);
}
@media (max-width: 600px) {
    .partner-signup .row2,
    .partner-signup .partner-address-grid {
        grid-template-columns: 1fr;
    }
    .partner-hero h1 {
        max-width: none;
    }
}
</style>

<div class="partner-page">
    <section class="partner-hero" aria-label="Partner with Pickleball Portal">
        <div class="partner-hero-media" aria-hidden="true"></div>
        <div class="partner-hero-inner">
            <span class="partner-hero-badge">Venue partners</span>
            <h1>Pickleball Portal</h1>
            <p class="lead">List your courts, manage schedules, and take advance bookings across Bohol.</p>
        </div>
    </section>

    <div class="partner-signup">
        <div class="partner-signup-card" id="signupCard">
            <p class="form-kicker">Partner application</p>
            <h2 class="form-title">Register your venue</h2>
            <p class="intro">Tell us about your club or court facility. Our team reviews applications within 2–3 business days.</p>

            <div id="msgbox"></div>

            <form id="frmPartnerSignup" novalidate>
                <label for="business_name">Venue / Club Name *</label>
                <input type="text" id="business_name" name="business_name" placeholder="e.g. Bohol Pickleball Club" required />

                <label for="subdomain">Preferred short name (venue link) *</label>
                <input type="text" id="subdomain" name="subdomain" placeholder="e.g. bohol-pickleball" pattern="[a-z0-9-]+" required />
                <p class="hint">Lowercase letters, numbers, and hyphens only. Example: store.php?slug=<strong>bohol-pickleball</strong></p>

                <label for="address">Venue Address *</label>
                <input type="text" id="address" name="address" placeholder="Building, street, landmark" required autocomplete="street-address" />
                <span class="field-error" id="address-error"></span>

                <div class="partner-address-grid">
                    <div>
                        <label for="country">Country *</label>
                        <select id="country" name="country" data-ph-id="<?php echo (int)$phCountryId; ?>">
                            <?php echo fh_geo_options_html($geoCountries, 'country_id', 'country_name', $custCountry, 'Select country'); ?>
                        </select>
                        <span class="field-error" id="country-error"></span>
                    </div>
                    <div id="province_field">
                        <label for="province">Province *</label>
                        <select id="province" name="province" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                            <?php echo fh_geo_options_html($geoProvinces, 'province_id', 'province_name', $custProvince, 'Select province'); ?>
                        </select>
                        <input type="text" id="province_text" name="province_text" placeholder="Enter province / state" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custProvince) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                        <span class="field-error" id="province-error"></span>
                    </div>
                    <div id="town_field">
                        <label for="town">Town / City *</label>
                        <select id="town" name="town" <?php echo $isPhilippinesCountry ? '' : 'style="display:none;"'; ?>>
                            <?php echo fh_geo_options_html($geoTowns, 'town_id', 'town_name', $custTown, 'Select town / city'); ?>
                        </select>
                        <input type="text" id="town_text" name="town_text" placeholder="Enter town / city" value="<?php echo !$isPhilippinesCountry ? htmlspecialchars($custTown) : ''; ?>" <?php echo $isPhilippinesCountry ? 'style="display:none;"' : ''; ?> />
                        <span class="field-error" id="town-error"></span>
                    </div>
                    <div>
                        <label for="zipcode">Zipcode *</label>
                        <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($custZipcode); ?>" placeholder="Zip / Postal code" autocomplete="postal-code" inputmode="numeric" required />
                        <span class="field-error" id="zipcode-error"></span>
                    </div>
                    <div class="partner-field-full" id="barangay_field">
                        <label for="barangay">Barangay *</label>
                        <select id="barangay" name="barangay" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? '' : 'style="display:none;"'; ?>>
                            <?php echo fh_geo_options_html($geoBarangays, 'barangay_id', 'barangay_name', $custBarangay, 'Select barangay'); ?>
                        </select>
                        <input type="text" id="barangay_text" name="barangay_text" placeholder="Enter barangay" value="<?php echo (!$isPhilippinesCountry || !$hasBarangayList) ? htmlspecialchars($custBarangay) : ''; ?>" <?php echo ($isPhilippinesCountry && $hasBarangayList) ? 'style="display:none;"' : ''; ?> />
                        <span class="field-error" id="barangay-error"></span>
                    </div>
                </div>

                <label for="contactperson">Contact Person *</label>
                <input type="text" id="contactperson" name="contactperson" placeholder="Owner, manager, or club admin" required />

                <div class="row2">
                    <div>
                        <label for="email">Venue Email *</label>
                        <input type="email" id="email" name="email" placeholder="bookings@yourclub.com" required />
                    </div>
                    <div>
                        <label for="contactnumber">Contact Number *</label>
                        <input type="tel" id="contactnumber" name="contactnumber" placeholder="09XXXXXXXXX" required />
                    </div>
                </div>

                <label for="description">About your venue (optional)</label>
                <textarea id="description" name="description" rows="4" placeholder="Indoor/outdoor courts, surface type, amenities, operating hours..."></textarea>

                <button type="submit" class="btn-submit">Submit Application</button>
            </form>
        </div>

        <div class="partner-signup-card partner-success" id="successCard" style="display:none;">
            <h2>Application received</h2>
            <p id="successMsg"></p>
            <p style="margin-top:24px;">
                <a href="home.php" class="portal-btn portal-btn-primary">Back to Home</a>
            </p>
        </div>

        <p class="partner-back">
            <a href="home.php">← Back to Pickleball Portal</a>
        </p>
    </div>
</div>

<script>
$(document).ready(function() {
    if (typeof setCheckoutLocationMode === 'function' && $('#country').length) {
        setCheckoutLocationMode($('#country').val() === 'Philippines');
    }

    $('#subdomain').on('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    });

    function clearPartnerFieldError(fieldId) {
        $('#' + fieldId).removeClass('portal-input-error');
        $('#' + fieldId + '-error').text('');
    }

    function showPartnerFieldError(fieldId, message) {
        var $field = $('#' + fieldId);
        if (fieldId === 'province' && $('#province_text').is(':visible')) {
            $field = $('#province_text');
        } else if (fieldId === 'town' && $('#town_text').is(':visible')) {
            $field = $('#town_text');
        } else if (fieldId === 'barangay' && $('#barangay_text').is(':visible')) {
            $field = $('#barangay_text');
        }
        $field.addClass('portal-input-error');
        $('#' + fieldId + '-error').text(message);
    }

    function validatePartnerLocation() {
        ['address', 'country', 'province', 'town', 'barangay', 'zipcode'].forEach(clearPartnerFieldError);
        var hasError = false;
        var address = $.trim($('#address').val() || '');
        var country = typeof getCheckoutCountry === 'function' ? getCheckoutCountry() : ($('#country').val() || '');
        var province = typeof getCheckoutProvince === 'function' ? getCheckoutProvince() : ($('#province').val() || '');
        var town = typeof getCheckoutTown === 'function' ? getCheckoutTown() : ($('#town').val() || '');
        var barangay = typeof getCheckoutBarangay === 'function' ? getCheckoutBarangay() : ($('#barangay').val() || '');
        var zipcode = $.trim($('#zipcode').val() || '');

        if (address === '') {
            showPartnerFieldError('address', 'Venue address is required.');
            hasError = true;
        }
        if (country === '') {
            showPartnerFieldError('country', 'Country is required.');
            hasError = true;
        }
        if (province === '') {
            showPartnerFieldError('province', 'Province is required.');
            hasError = true;
        }
        if (town === '') {
            showPartnerFieldError('town', 'Town / City is required.');
            hasError = true;
        }
        if (barangay === '') {
            showPartnerFieldError('barangay', 'Barangay is required.');
            hasError = true;
        }
        if (zipcode === '') {
            showPartnerFieldError('zipcode', 'Zipcode is required.');
            hasError = true;
        }
        return !hasError;
    }

    $('#frmPartnerSignup').submit(function(e) {
        e.preventDefault();

        if (!validatePartnerLocation()) {
            $('#msgbox').html('Please fix the highlighted address fields.').css({
                display: 'block',
                background: '#f8d7da',
                color: '#721c24',
                padding: '12px',
                borderRadius: '8px',
                marginBottom: '12px'
            });
            return;
        }

        var country = typeof getCheckoutCountry === 'function' ? getCheckoutCountry() : ($('#country').val() || '');
        var province = typeof getCheckoutProvince === 'function' ? getCheckoutProvince() : ($('#province').val() || '');
        var city = typeof getCheckoutTown === 'function' ? getCheckoutTown() : ($('#town').val() || '');
        var barangay = typeof getCheckoutBarangay === 'function' ? getCheckoutBarangay() : ($('#barangay').val() || '');

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'PartnerSignup',
                business_name: $('#business_name').val(),
                subdomain: $('#subdomain').val(),
                address: $('#address').val(),
                barangay: barangay,
                city: city,
                province: province,
                country: country,
                zipcode: $('#zipcode').val(),
                contactperson: $('#contactperson').val(),
                email: $('#email').val(),
                contactnumber: $('#contactnumber').val(),
                description: $('#description').val()
            },
            dataType: 'json',
            success: function(data) {
                if (data.validation_status === 'T') {
                    $('#signupCard').hide();
                    $('#successMsg').text(data.msg);
                    $('#successCard').show();
                    return;
                }
                $('#msgbox').html(data.msg).css({
                    display: 'block',
                    background: '#f8d7da',
                    color: '#721c24',
                    padding: '12px',
                    borderRadius: '8px',
                    marginBottom: '12px'
                });
                setTimeout(function() { $('#msgbox').fadeOut('slow'); }, 6000);
            }
        });
    });
});
</script>

<?php include('footer.php'); ?>
