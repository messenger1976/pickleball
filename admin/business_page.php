<?php
include('config.php');
fh_auth_require_login();

$bizId = fh_admin_business_id($conn);
if (!fh_auth_can_access_business($bizId)) {
    header('Location: index.php');
    exit;
}
fh_ensure_business_page($conn, $bizId);

$bizSql = $conn->query("SELECT * FROM business_account WHERE business_id = $bizId LIMIT 1");
$biz = $bizSql ? $bizSql->fetch_assoc() : null;
$page = fh_get_business_page($conn, $bizId);
if (!$page) {
    fh_ensure_business_page($conn, $bizId);
    $page = fh_get_business_page($conn, $bizId);
}

$titleheader = 'Venue Page (CMS)';
include('header.php');
include('navigation.php');
?>

<style>
.cms-preview { border: 2px dashed #40916c; padding: 12px; margin: 12px 0; background: #f8f9fa; }
.cms-preview img { max-width: 100%; max-height: 180px; border-radius: 8px; }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Venue Page — CMS</h1>
        <p style="margin:0;">Customize your public pickleball venue landing page and booking settings</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'business_page.php'); ?>

    <div class="w3-panel w3-light-grey">
        <strong>Live preview:</strong>
        <a href="../<?php echo fh_store_link($bizId, $biz ? $biz['business_subdomain'] : ''); ?>" target="_blank">Open venue page →</a>
    </div>

    <form id="cmsForm" class="w3-container w3-card w3-padding" enctype="multipart/form-data">
        <input type="hidden" name="business_id" id="business_id" value="<?php echo (int)$bizId; ?>" />

        <label><b>Headline / Tagline</b></label>
        <input class="w3-input w3-border" type="text" id="page_tagline" value="<?php echo htmlspecialchars($page['page_tagline']); ?>" />

        <label><b>About Your Venue</b></label>
        <textarea class="w3-input w3-border" id="page_about" rows="5"><?php echo htmlspecialchars($page['page_about']); ?></textarea>

        <label><b>Opening Hours</b></label>
        <input class="w3-input w3-border" type="text" id="page_hours" value="<?php echo htmlspecialchars($page['page_hours']); ?>" placeholder="Mon-Sun 6:00 AM - 10:00 PM" />

        <label><b>Highlights</b> (comma-separated)</label>
        <input class="w3-input w3-border" type="text" id="page_specialties" value="<?php echo htmlspecialchars($page['page_specialties']); ?>" placeholder="Open play, Lessons, Pro shop" />

        <label><b>Amenities</b></label>
        <textarea class="w3-input w3-border" id="page_amenities" rows="3" placeholder="Parking, restrooms, water stations, shade, lighting…"><?php echo htmlspecialchars(isset($page['page_amenities']) ? $page['page_amenities'] : ''); ?></textarea>

        <label><b>Theme Color</b></label>
        <input class="w3-input w3-border" type="color" id="page_theme_color" value="<?php echo htmlspecialchars($page['page_theme_color'] ?: '#2d6a4f'); ?>" style="height:44px;padding:4px;" />

        <div class="w3-row-padding">
            <div class="w3-half">
                <label><b>Hero / Cover Image</b></label>
                <?php if (!empty($page['page_hero_image'])): ?>
                <div class="cms-preview"><img src="<?php echo htmlspecialchars(fh_cms_image($page['page_hero_image'])); ?>" alt="Hero" /></div>
                <?php endif; ?>
                <input type="file" id="page_hero_image" name="page_hero_image" accept="image/*" />
            </div>
            <div class="w3-half">
                <label><b>Banner Image (optional)</b></label>
                <?php if (!empty($page['page_banner_image'])): ?>
                <div class="cms-preview"><img src="<?php echo htmlspecialchars(fh_cms_image($page['page_banner_image'])); ?>" alt="Banner" /></div>
                <?php endif; ?>
                <input type="file" id="page_banner_image" name="page_banner_image" accept="image/*" />
            </div>
        </div>

        <label><input type="checkbox" id="page_published" <?php echo (int)$page['page_published'] === 1 ? 'checked' : ''; ?> /> Publish venue page (visible to players)</label>

        <hr style="margin:20px 0;border:none;border-top:1px solid #ccc;" />
        <h3>Booking settings</h3>
        <label><input type="checkbox" id="auto_confirm" <?php echo !isset($page['auto_confirm']) || (int)$page['auto_confirm'] === 1 ? 'checked' : ''; ?> /> Auto-confirm bookings (instant confirmation)</label>
        <label style="display:block;margin-top:12px;"><b>Max days ahead players can book</b></label>
        <input class="w3-input w3-border" type="number" id="booking_max_days" min="1" max="90" value="<?php echo (int)(!empty($page['booking_max_days']) ? $page['booking_max_days'] : (!empty($page['preorder_max_days']) ? $page['preorder_max_days'] : 14)); ?>" style="max-width:120px;" />
        <label style="display:block;margin-top:12px;"><b>Cancellation policy note</b></label>
        <input class="w3-input w3-border" type="text" id="cancellation_note" value="<?php echo htmlspecialchars(isset($page['cancellation_note']) ? $page['cancellation_note'] : 'Cancel at least 24 hours before your booking.'); ?>" />

        <br/><br/>
        <button type="button" class="w3-button w3-green w3-large" onclick="saveCms()">Save Venue Page</button>
    </form>
</div>

<script>
function saveCms() {
    var fd = new FormData();
    fd.append('function', 'SaveBusinessPage');
    fd.append('business_id', $('#business_id').val());
    fd.append('page_tagline', $('#page_tagline').val());
    fd.append('page_about', $('#page_about').val());
    fd.append('page_hours', $('#page_hours').val());
    fd.append('page_specialties', $('#page_specialties').val());
    fd.append('page_amenities', $('#page_amenities').val());
    fd.append('page_theme_color', $('#page_theme_color').val());
    fd.append('page_published', $('#page_published').is(':checked') ? 1 : 0);
    fd.append('auto_confirm', $('#auto_confirm').is(':checked') ? 1 : 0);
    fd.append('booking_max_days', $('#booking_max_days').val() || 14);
    fd.append('cancellation_note', $('#cancellation_note').val() || '');
    fd.append('preorder_enabled', 0);
    fd.append('preorder_max_days', $('#booking_max_days').val() || 14);
    if ($('#page_hero_image')[0].files[0]) {
        fd.append('page_hero_image', $('#page_hero_image')[0].files[0]);
    }
    if ($('#page_banner_image')[0].files[0]) {
        fd.append('page_banner_image', $('#page_banner_image')[0].files[0]);
    }
    $.ajax({
        url: 'process.php',
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
            if (data.msg === true) {
                alert('Venue page saved successfully!');
                window.location.reload();
            } else {
                alert(data.error || 'Save failed.');
            }
        }
    });
}
</script>

<?php include('footer.php'); ?>
