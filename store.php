<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/auth_helper.php');
include_once(__DIR__ . '/includes/court_helper.php');
include_once(__DIR__ . '/includes/lesson_helper.php');
include_once(__DIR__ . '/includes/membership_helper.php');
include_once(__DIR__ . '/admin/payment_config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$businessId = 0;
if (isset($_GET['slug']) && trim($_GET['slug']) !== '') {
    $slug = $conn->real_escape_string(trim($_GET['slug']));
    $bizSql = $conn->query("SELECT business_id FROM business_account WHERE business_subdomain='$slug' AND business_status=1 LIMIT 1");
    if ($bizSql && $row = $bizSql->fetch_assoc()) {
        $businessId = (int)$row['business_id'];
    }
} elseif (isset($_GET['business_id']) && is_numeric($_GET['business_id'])) {
    $businessId = (int)$_GET['business_id'];
}

$page = $businessId > 0 ? fh_get_business_page($conn, $businessId) : null;
if (!$page || (int)$page['page_published'] !== 1 || (int)$page['business_status'] !== 1) {
    header('Location: home.php');
    exit;
}

$bizExtra = $conn->query("SELECT business_lat, business_lng FROM business_account WHERE business_id=$businessId LIMIT 1");
$bizLoc = $bizExtra ? $bizExtra->fetch_assoc() : array('business_lat' => null, 'business_lng' => null);

$_SESSION['business_id'] = $businessId;

$theme = !empty($page['page_theme_color']) ? $page['page_theme_color'] : '#2d6a4f';
$heroImage = !empty($page['page_hero_image']) ? fh_cms_image($page['page_hero_image']) : 'images/brand/hero-match-play.jpg';
$settings = pb_booking_settings($conn, $businessId);
$bookingDate = isset($_GET['date']) ? pb_clamp_booking_date($_GET['date'], $settings['booking_max_days']) : date('Y-m-d');
$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+' . (int)$settings['booking_max_days'] . ' days'));

$courts = pb_courts_for_business($conn, $businessId, true);
$selectedCourtId = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;
if ($selectedCourtId <= 0 && !empty($courts)) {
    $selectedCourtId = (int)$courts[0]['court_id'];
}
$selectedCourt = null;
foreach ($courts as $c) {
    if ((int)$c['court_id'] === $selectedCourtId) {
        $selectedCourt = $c;
        break;
    }
}
$slots = $selectedCourt ? pb_generate_slots($conn, $selectedCourt, $bookingDate) : array();

$lessons = pb_lessons_for_business($conn, $businessId, true);
$selectedLessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($selectedLessonId <= 0 && !empty($lessons)) {
    $selectedLessonId = (int)$lessons[0]['lesson_id'];
}
$selectedLesson = null;
foreach ($lessons as $l) {
    if ((int)$l['lesson_id'] === $selectedLessonId) {
        $selectedLesson = $l;
        break;
    }
}
$lessonSlots = $selectedLesson ? pb_generate_lesson_slots($conn, $selectedLesson, $bookingDate) : array();
$plans = pb_plans_for_business($conn, $businessId, true);

$specialties = array_filter(array_map('trim', explode(',', (string)$page['page_specialties'])));
$amenities = trim((string)(isset($page['page_amenities']) ? $page['page_amenities'] : ''));
$storeAddress = trim($page['business_address'] . ', ' . $page['business_city']);
$mapLat = !empty($bizLoc['business_lat']) ? (float)$bizLoc['business_lat'] : 9.6508;
$mapLng = !empty($bizLoc['business_lng']) ? (float)$bizLoc['business_lng'] : 123.8534;

$cid = isset($_SESSION['customers_id']) ? (int)$_SESSION['customers_id'] : 0;
$chatUrl = $cid > 0
    ? ('chat.php?business_id=' . $businessId)
    : ('login.php?redirect=' . urlencode('chat.php?business_id=' . $businessId));

$pagetitle = $page['business_name'] . ' — Pickleball Portal';
$extraStylesheets = array(
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'css/store.css?v=20260803b',
);
$bodyClass = 'store-order-page';
include('header.php');
?>
<style>
:root { --store-theme: <?php echo htmlspecialchars($theme); ?>; }
.pb-slot-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
.pb-slot-btn { border:1px solid #cbd5e1; background:#fff; border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:700; color:#1b4332; text-decoration:none; display:inline-block; }
.pb-slot-btn:hover { border-color: var(--store-theme); background:#f0fdf4; }
.pb-slot-btn .price { display:block; font-size:12px; font-weight:600; color:#64748b; margin-top:4px; }
.pb-book-bar { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin:12px 0 0; }
.pb-book-bar label { display:block; font-size:12px; font-weight:700; color:#64748b; margin-bottom:6px; }
.pb-book-bar select, .pb-book-bar input { padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; min-width:160px; }
#venueMap { height:260px; border-radius:12px; margin-top:12px; }
.pb-live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#94a3b8; margin-right:6px; }
.pb-live-dot.is-on { background:#22c55e; }
</style>

<div class="store-page">
    <section class="store-hero" style="background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.55)), url('<?php echo htmlspecialchars($heroImage); ?>');">
        <div class="store-hero-inner">
            <span class="store-badge">Pickleball Partner Venue</span>
            <h1><?php echo htmlspecialchars($page['business_name']); ?></h1>
            <p class="store-tagline"><?php echo htmlspecialchars($page['page_tagline']); ?></p>
            <div class="store-hero-actions">
                <a href="#book" class="store-btn store-btn-primary">Book a court</a>
                <?php if (!empty($lessons)): ?><a href="#lessons" class="store-btn store-btn-outline">Lessons</a><?php endif; ?>
                <a href="<?php echo htmlspecialchars($chatUrl); ?>" class="store-btn store-btn-outline">Message venue</a>
            </div>
        </div>
    </section>

    <section class="store-section">
        <div class="store-grid">
            <div class="store-about">
                <h2>About</h2>
                <p><?php echo nl2br(htmlspecialchars($page['page_about'])); ?></p>
                <?php if (!empty($specialties)): ?>
                <div class="store-tags">
                    <?php foreach ($specialties as $tag): ?>
                    <span class="store-tag"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($amenities !== ''): ?>
                <h3 style="margin-top:18px;">Amenities</h3>
                <p><?php echo nl2br(htmlspecialchars($amenities)); ?></p>
                <?php endif; ?>
                <h3 style="margin-top:18px;">Map</h3>
                <div id="venueMap"></div>
            </div>
            <aside class="store-sidebar">
                <div class="store-info-card">
                    <h3>Hours</h3>
                    <p><?php echo htmlspecialchars($page['page_hours']); ?></p>
                    <h3>Location</h3>
                    <p><?php echo htmlspecialchars($storeAddress); ?></p>
                    <?php if (!empty($page['business_contact'])): ?>
                    <h3>Contact</h3>
                    <p><?php echo htmlspecialchars($page['business_contact']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($settings['cancellation_note'])): ?>
                    <h3>Cancellation</h3>
                    <p><?php echo htmlspecialchars($settings['cancellation_note']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($plans)): ?>
                <div class="store-info-card" style="margin-top:14px;">
                    <h3>Memberships</h3>
                    <?php foreach ($plans as $plan): ?>
                    <p style="margin:0 0 10px;">
                        <strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong><br>
                        ₱<?php echo number_format((float)$plan['price'], 2); ?> / <?php echo htmlspecialchars($plan['billing_period']); ?>
                        <?php if ((float)$plan['discount_percent'] > 0): ?>
                        · <?php echo rtrim(rtrim(number_format((float)$plan['discount_percent'], 2), '0'), '.'); ?>% off bookings
                        <?php endif; ?>
                        <br>
                        <a href="subscribe.php?plan_id=<?php echo (int)$plan['plan_id']; ?>">Join →</a>
                    </p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <section class="store-section" id="book">
        <h2>Book a court <span style="font-size:13px;font-weight:600;color:#64748b;"><span class="pb-live-dot" id="liveDot"></span><span id="liveLabel">Checking live updates…</span></span></h2>
        <?php if (empty($courts)): ?>
            <p>This venue has not published bookable courts yet.</p>
        <?php else: ?>
        <form class="pb-book-bar" method="get" action="store.php" id="courtFilterForm">
            <?php if (!empty($page['business_subdomain'])): ?>
            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($page['business_subdomain']); ?>">
            <?php else: ?>
            <input type="hidden" name="business_id" value="<?php echo (int)$businessId; ?>">
            <?php endif; ?>
            <div>
                <label for="court_id">Court</label>
                <select name="court_id" id="court_id" onchange="this.form.submit()">
                    <?php foreach ($courts as $c): ?>
                    <option value="<?php echo (int)$c['court_id']; ?>" <?php echo (int)$c['court_id'] === $selectedCourtId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['court_name'] . ' · ' . ucfirst($c['court_type']) . ' · ₱' . number_format($c['price_per_slot'], 2)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date">Date</label>
                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($bookingDate); ?>" min="<?php echo htmlspecialchars($minDate); ?>" max="<?php echo htmlspecialchars($maxDate); ?>" onchange="this.form.submit()">
            </div>
        </form>
        <div id="slotGrid" class="pb-slot-grid">
            <?php if (empty($slots)): ?>
                <p>No open slots on this date.</p>
            <?php else: foreach ($slots as $slot):
                $bookUrl = 'book.php?' . http_build_query(array('court_id' => $selectedCourtId, 'start' => $slot['start'], 'end' => $slot['end']));
            ?>
            <a class="pb-slot-btn" href="<?php echo htmlspecialchars($bookUrl); ?>">
                <?php echo htmlspecialchars($slot['label']); ?>
                <span class="price">₱<?php echo number_format($slot['price'], 2); ?></span>
            </a>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($lessons)): ?>
    <section class="store-section" id="lessons">
        <h2>Lessons with a coach</h2>
        <form class="pb-book-bar" method="get" action="store.php">
            <?php if (!empty($page['business_subdomain'])): ?>
            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($page['business_subdomain']); ?>">
            <?php else: ?>
            <input type="hidden" name="business_id" value="<?php echo (int)$businessId; ?>">
            <?php endif; ?>
            <input type="hidden" name="court_id" value="<?php echo (int)$selectedCourtId; ?>">
            <div>
                <label for="lesson_id">Lesson</label>
                <select name="lesson_id" id="lesson_id" onchange="this.form.submit()">
                    <?php foreach ($lessons as $l): ?>
                    <option value="<?php echo (int)$l['lesson_id']; ?>" <?php echo (int)$l['lesson_id'] === $selectedLessonId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['lesson_title'] . (!empty($l['coach_name']) ? ' · ' . $l['coach_name'] : '') . ' · ₱' . number_format($l['price'], 2)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="lesson_date">Date</label>
                <input type="date" name="date" id="lesson_date" value="<?php echo htmlspecialchars($bookingDate); ?>" min="<?php echo htmlspecialchars($minDate); ?>" max="<?php echo htmlspecialchars($maxDate); ?>" onchange="this.form.submit()">
            </div>
        </form>
        <?php if ($selectedLesson): ?>
        <p style="margin-top:12px;color:#475569;"><?php echo htmlspecialchars($selectedLesson['lesson_description'] ?: ''); ?> · <?php echo (int)$selectedLesson['duration_minutes']; ?> min</p>
        <?php endif; ?>
        <div class="pb-slot-grid">
            <?php if (empty($lessonSlots)): ?>
                <p>No lesson slots on this date.</p>
            <?php else: foreach ($lessonSlots as $slot):
                $bookUrl = 'book.php?' . http_build_query(array(
                    'type' => 'lesson',
                    'lesson_id' => $selectedLessonId,
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ));
            ?>
            <a class="pb-slot-btn" href="<?php echo htmlspecialchars($bookUrl); ?>">
                <?php echo htmlspecialchars($slot['label']); ?>
                <span class="price">₱<?php echo number_format($slot['price'], 2); ?> · <?php echo (int)$slot['seats_left']; ?> left</span>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script>
window.pbVenue = {
  businessId: <?php echo (int)$businessId; ?>,
  courtId: <?php echo (int)$selectedCourtId; ?>,
  date: <?php echo json_encode($bookingDate); ?>,
  lat: <?php echo json_encode($mapLat); ?>,
  lng: <?php echo json_encode($mapLng); ?>,
  realtimeUrl: <?php echo json_encode(rtrim(PB_REALTIME_URL, '/')); ?>
};
</script>
<script src="js/venue-live.js?v=20260803"></script>
<?php include('footer.php'); ?>
