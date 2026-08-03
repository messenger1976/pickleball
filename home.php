<?php
include('admin/db_config.php');
include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/portal_news_helper.php');
include_once(__DIR__ . '/includes/court_helper.php');

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterType = isset($_GET['type']) ? trim($_GET['type']) : '';
$filterSurface = isset($_GET['surface']) ? trim($_GET['surface']) : '';
$filterRadius = isset($_GET['radius']) ? (float)$_GET['radius'] : 0;
$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : 9.6508;
$userLng = isset($_GET['lng']) ? (float)$_GET['lng'] : 123.8534;
if ($filterRadius < 0) {
    $filterRadius = 0;
}
if ($filterRadius > 100) {
    $filterRadius = 100;
}

$searchResults = array('venues' => array(), 'courts' => array());

if ($searchQuery !== '') {
    $safeSearch = $conn->real_escape_string($searchQuery);
    $like = "'%" . $safeSearch . "%'";

    $venueSql = $conn->query(
        "SELECT business_id, business_name, business_address, business_city, business_subdomain
         FROM business_account
         WHERE business_status = 1
           AND business_id > 1
           AND (business_name LIKE $like OR business_city LIKE $like OR business_address LIKE $like)
         ORDER BY business_name ASC
         LIMIT 12"
    );
    if ($venueSql) {
        while ($row = $venueSql->fetch_assoc()) {
            $searchResults['venues'][] = $row;
        }
    }

    $courtSql = $conn->query(
        "SELECT c.court_id, c.court_name, c.price_per_slot, c.court_type, c.court_surface,
                ba.business_name, ba.business_id, ba.business_subdomain
         FROM courts c
         INNER JOIN business_account ba ON ba.business_id = c.business_id AND ba.business_status = 1
         WHERE c.court_status = 1 AND c.court_name LIKE $like
         ORDER BY c.court_name ASC
         LIMIT 12"
    );
    if ($courtSql) {
        while ($row = $courtSql->fetch_assoc()) {
            $searchResults['courts'][] = $row;
        }
    }
}

$stats = array('venues' => 0, 'courts' => 0, 'bookings_today' => 0);
$statsRow = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM business_account WHERE business_status = 1 AND business_id > 1) AS venues,
        (SELECT COUNT(*) FROM courts WHERE court_status = 1) AS courts,
        (SELECT COUNT(*) FROM bookings WHERE DATE(start_datetime) = CURDATE() AND booking_status IN ('pending','confirmed')) AS bookings_today"
);
if ($statsRow && $row = $statsRow->fetch_assoc()) {
    $stats = $row;
}

$venues = array();
$venueSql = $conn->query(
    "SELECT ba.business_id, ba.business_name, ba.business_address, ba.business_city, ba.business_province,
            ba.business_contact, ba.business_subdomain, ba.business_lat, ba.business_lng,
            bp.page_hero_image, bp.page_tagline,
            (SELECT COUNT(*) FROM courts c WHERE c.business_id = ba.business_id AND c.court_status = 1) AS court_count
     FROM business_account ba
     LEFT JOIN business_page bp ON bp.business_id = ba.business_id
     WHERE ba.business_status = 1 AND ba.business_id > 1
       AND (bp.page_published = 1 OR bp.page_published IS NULL)
     ORDER BY ba.business_name ASC"
);
if ($venueSql) {
    while ($row = $venueSql->fetch_assoc()) {
        if ($filterType !== '' || $filterSurface !== '') {
            $bid = (int)$row['business_id'];
            $typeClause = $filterType !== '' ? " AND court_type = '" . $conn->real_escape_string($filterType) . "'" : '';
            $surfClause = $filterSurface !== '' ? " AND court_surface = '" . $conn->real_escape_string($filterSurface) . "'" : '';
            $check = $conn->query("SELECT court_id FROM courts WHERE business_id=$bid AND court_status=1 $typeClause $surfClause LIMIT 1");
            if (!$check || $check->num_rows < 1) {
                continue;
            }
        }
        if ($filterRadius > 0 && !empty($row['business_lat']) && !empty($row['business_lng'])) {
            $lat1 = deg2rad($userLat);
            $lat2 = deg2rad((float)$row['business_lat']);
            $dlat = deg2rad((float)$row['business_lat'] - $userLat);
            $dlng = deg2rad((float)$row['business_lng'] - $userLng);
            $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
            $km = 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
            $row['distance_km'] = round($km, 1);
            if ($km > $filterRadius) {
                continue;
            }
        }
        $venues[] = $row;
    }
}

$featuredCourts = array();
$featuredSql = $conn->query(
    "SELECT c.court_id, c.court_name, c.price_per_slot, c.court_type, c.court_surface, c.court_photo,
            ba.business_name, ba.business_id, ba.business_subdomain
     FROM courts c
     INNER JOIN business_account ba ON ba.business_id = c.business_id AND ba.business_status = 1
     WHERE c.court_status = 1
     ORDER BY c.court_id DESC
     LIMIT 8"
);
if ($featuredSql) {
    while ($row = $featuredSql->fetch_assoc()) {
        $featuredCourts[] = $row;
    }
}

$portalNews = fh_portal_news_fetch_active($conn);
$types = pb_court_types();
$surfaces = pb_court_surfaces();

$heroSlideshowImages = array(
    'images/brand/hero-court-outdoor.jpg',
    'images/brand/hero-match-play.jpg',
    'images/brand/hero-paddle-ball.jpg',
    'images/brand/hero-park-aerial.jpg',
);

$pagetitle = 'Pickleball Portal — Find & Book Courts Near You';
$bodyClass = 'portal-home-page';
$extraStylesheets = array(
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'css/portal-home.css?v=20260803c',
);
include('header.php');
?>

<div class="portal-home">

    <section class="portal-hero" id="portalHero">
        <div class="portal-hero-slideshow" aria-hidden="true">
            <?php foreach ($heroSlideshowImages as $i => $slideImg): ?>
            <div class="portal-hero-slide<?php echo $i === 0 ? ' is-active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($slideImg); ?>" alt="" class="portal-hero-media-img" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="portal-hero-overlay" aria-hidden="true"></div>
        <?php if (count($heroSlideshowImages) > 1): ?>
        <div class="portal-hero-dots" aria-label="Hero slideshow">
            <?php foreach ($heroSlideshowImages as $i => $slideImg): ?>
            <button type="button" class="portal-hero-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-slide="<?php echo $i; ?>" aria-label="Show slide <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="portal-hero-inner">
            <p class="portal-hero-badge">Pickleball Portal</p>
            <h1>Court time, booked.</h1>
            <p class="lead">Find community courts, check open slots, and reserve your game in advance.</p>

            <form class="portal-search-wrap" action="home.php" method="GET" role="search">
                <input type="text" name="q" placeholder="Search venues or city..." value="<?php echo htmlspecialchars($searchQuery); ?>" autocomplete="off" />
                <button type="submit">Search</button>
            </form>

            <div class="portal-hero-actions">
                <a href="#venues" class="portal-btn portal-btn-primary">Browse venues</a>
                <a href="partner_signup.php" class="portal-btn portal-btn-outline">Partner with us</a>
            </div>
        </div>
    </section>

    <?php if (!empty($portalNews)): ?>
    <div class="portal-section" style="padding-bottom:0;">
        <div class="portal-hero-news" role="note" style="max-width:960px;margin:0 auto;">
            <div class="portal-hero-news-body">
                <strong class="portal-hero-news-title"><?php echo htmlspecialchars($portalNews['news_title']); ?></strong>
                <p class="portal-hero-news-message"><?php echo nl2br(htmlspecialchars($portalNews['news_message'])); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="portal-stats">
        <div class="portal-stat">
            <strong><?php echo (int)$stats['venues']; ?></strong>
            <span>Partner Venues</span>
        </div>
        <div class="portal-stat">
            <strong><?php echo (int)$stats['courts']; ?></strong>
            <span>Active Courts</span>
        </div>
        <div class="portal-stat">
            <strong><?php echo (int)$stats['bookings_today']; ?></strong>
            <span>Bookings Today</span>
        </div>
    </div>

    <div class="portal-section">
        <form method="get" action="home.php" class="pb-filter-bar">
            <?php if ($searchQuery !== ''): ?>
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
            <?php endif; ?>
            <input type="hidden" name="lat" id="filter_lat" value="<?php echo htmlspecialchars((string)$userLat); ?>">
            <input type="hidden" name="lng" id="filter_lng" value="<?php echo htmlspecialchars((string)$userLng); ?>">
            <div>
                <label for="filter_type">Type</label>
                <select id="filter_type" name="type">
                    <option value="">All</option>
                    <?php foreach ($types as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filterType === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_surface">Surface</label>
                <select id="filter_surface" name="surface">
                    <option value="">All</option>
                    <?php foreach ($surfaces as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filterSurface === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_radius">Radius (km)</label>
                <select id="filter_radius" name="radius">
                    <option value="0" <?php echo $filterRadius <= 0 ? 'selected' : ''; ?>>Any distance</option>
                    <?php foreach (array(5, 10, 25, 50) as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo (int)$filterRadius === $r ? 'selected' : ''; ?>>Within <?php echo $r; ?> km</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="portal-btn portal-btn-outline" style="padding:10px 16px;" id="btnUseMyLocation">Use my location</button>
            <button type="submit" class="portal-btn portal-btn-primary" style="padding:10px 16px;">Apply filters</button>
        </form>
<script>
document.getElementById('btnUseMyLocation') && document.getElementById('btnUseMyLocation').addEventListener('click', function () {
  if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
  navigator.geolocation.getCurrentPosition(function (pos) {
    document.getElementById('filter_lat').value = pos.coords.latitude;
    document.getElementById('filter_lng').value = pos.coords.longitude;
    alert('Location set. Click Apply filters.');
  }, function () { alert('Could not get your location.'); });
});
</script>

        <?php if ($searchQuery !== ''): ?>
        <div class="portal-search-results">
            <h3>Search results for &ldquo;<?php echo htmlspecialchars($searchQuery); ?>&rdquo;</h3>
            <?php if (empty($searchResults['venues']) && empty($searchResults['courts'])): ?>
                <p>No matches found. Try a different keyword or browse venues below.</p>
            <?php else: ?>
                <?php if (!empty($searchResults['venues'])): ?>
                <h4 style="margin:16px 0 8px;color:#495057;">Venues</h4>
                <ul class="portal-search-list">
                    <?php foreach ($searchResults['venues'] as $r): ?>
                    <li>
                        <span><strong><?php echo htmlspecialchars($r['business_name']); ?></strong> — <?php echo htmlspecialchars($r['business_city']); ?></span>
                        <a href="<?php echo fh_store_link($r['business_id'], $r['business_subdomain']); ?>">View venue &rarr;</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (!empty($searchResults['courts'])): ?>
                <h4 style="margin:16px 0 8px;color:#495057;">Courts</h4>
                <ul class="portal-search-list">
                    <?php foreach ($searchResults['courts'] as $c): ?>
                    <li>
                        <span><?php echo htmlspecialchars($c['court_name']); ?> at <?php echo htmlspecialchars($c['business_name']); ?> — <em>₱<?php echo number_format($c['price_per_slot'], 2); ?></em></span>
                        <a href="<?php echo fh_store_link($c['business_id'], $c['business_subdomain']); ?>">Book &rarr;</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="portal-section-header" id="venues">
            <h2>Partner Venues</h2>
            <p>Browse clubs and courts near you. Open a venue to check availability and book a slot.</p>
        </div>

        <?php if (empty($venues)): ?>
            <div class="portal-empty">
                <p>Venues are being onboarded. Check back soon, or <a href="partner_signup.php">register as a partner</a>.</p>
            </div>
        <?php else: ?>
        <div class="portal-restaurant-grid">
            <?php foreach ($venues as $biz):
                $bizId = (int)$biz['business_id'];
                $imgSrc = !empty($biz['page_hero_image']) ? fh_cms_image($biz['page_hero_image']) : 'images/brand/court-outdoor.jpg';
            ?>
            <article class="portal-restaurant-card">
                <div class="card-img">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($biz['business_name']); ?>" />
                </div>
                <div class="card-body">
                    <span class="badge-open"><?php echo (int)$biz['court_count']; ?> court<?php echo (int)$biz['court_count'] === 1 ? '' : 's'; ?></span>
                    <h3><?php echo htmlspecialchars($biz['business_name']); ?></h3>
                    <?php if (!empty($biz['page_tagline'])): ?>
                    <p class="meta"><?php echo htmlspecialchars($biz['page_tagline']); ?></p>
                    <?php endif; ?>
                    <p class="meta"><?php echo htmlspecialchars($biz['business_address']); ?></p>
                    <p class="meta"><?php echo htmlspecialchars($biz['business_city'] . ', ' . $biz['business_province']); ?></p>
                    <div class="card-actions">
                        <a class="btn-order" href="<?php echo fh_store_link($bizId, $biz['business_subdomain']); ?>">View &amp; Book</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <section class="portal-section portal-section-alt" id="map">
        <div class="portal-section-header">
            <h2>Venue map</h2>
            <p>Explore partner venues around Tagbilaran / Bohol.</p>
        </div>
        <div id="homeVenueMap" style="height:380px;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.08);"></div>
    </section>

    <?php if (!empty($featuredCourts)): ?>
    <section class="portal-section portal-section-alt">
        <div class="portal-section-header">
            <h2>Featured Courts</h2>
            <p>Popular courts ready to reserve.</p>
        </div>
        <div class="portal-food-grid">
            <?php foreach ($featuredCourts as $court): ?>
            <div class="portal-food-card">
                <?php
                $photo = pb_court_photo_url($court['court_photo']);
                if ($photo === '') {
                    $photo = (isset($court['court_type']) && $court['court_type'] === 'indoor')
                        ? 'images/brand/court-indoor.jpg'
                        : 'images/brand/court-outdoor.jpg';
                }
                ?>
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($court['court_name']); ?>" loading="lazy" />
                <div class="food-info">
                    <h4><?php echo htmlspecialchars($court['court_name']); ?></h4>
                    <div class="price">₱<?php echo number_format($court['price_per_slot'], 2); ?> / slot</div>
                    <div class="from-store"><?php echo htmlspecialchars($court['business_name']); ?> · <?php echo htmlspecialchars(ucfirst($court['court_type'])); ?></div>
                    <a class="pb-book-link" href="<?php echo fh_store_link($court['business_id'], $court['business_subdomain']); ?>">Book →</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="portal-section">
        <div class="portal-section-header">
            <h2>Own a court or club?</h2>
            <p>Partner with us to get a customizable venue page, manage courts and schedules, and accept advance bookings.</p>
        </div>
        <div style="text-align:center;">
            <a href="partner_signup.php" class="portal-btn portal-btn-primary">Become a Partner</a>
        </div>
    </section>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.pbHomeVenues = <?php
$mapPoints = array();
foreach ($venues as $v) {
    if (empty($v['business_lat']) || empty($v['business_lng'])) continue;
    $mapPoints[] = array(
        'id' => (int)$v['business_id'],
        'name' => $v['business_name'],
        'lat' => (float)$v['business_lat'],
        'lng' => (float)$v['business_lng'],
        'url' => fh_store_link($v['business_id'], $v['business_subdomain']),
    );
}
echo json_encode($mapPoints);
?>;
</script>
<script src="js/home-map.js?v=20260803"></script>
<script src="js/portal-hero-slideshow.js?v=20260803"></script>
<?php include('footer.php'); ?>
