<?php

include('admin/config.php');

include_once(__DIR__ . '/includes/business_helper.php');
include_once(__DIR__ . '/includes/category_helper.php');



$selectedBusinessId = fh_portal_business_id($conn);

$selectedBusiness = $selectedBusinessId > 0 ? fh_resolve_business($conn, $selectedBusinessId) : null;

if ($selectedBusinessId > 0 && !$selectedBusiness) {

    $selectedBusinessId = 0;

    unset($_SESSION['business_id']);

}

$filterCatId = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$filterCategory = $filterCatId > 0 ? fh_category_fetch($conn, $filterCatId) : null;
if ($filterCategory && (int)$filterCategory['cat_status'] !== 1) {
    $filterCategory = null;
    $filterCatId = 0;
}
$portalCategories = fh_category_list($conn, true);



if (isset($_POST['btnDate']) && isset($_POST['menudate'])) {

    $_SESSION['menudate'] = $_POST['menudate'];

} elseif (isset($_POST['btnPreorderDate']) && isset($_POST['preorder_menudate'])) {

    $preBizId = fh_portal_business_id($conn);

    $preSettings = fh_preorder_settings($conn, $preBizId);

    $_SESSION['menudate'] = fh_clamp_menudate($_POST['preorder_menudate'], $preSettings['preorder_max_days']);

} else {

    if (!isset($_SESSION['menudate'])) {

        $_SESSION['menudate'] = date('Y-m-d');

    }

}

$menudate = $_SESSION['menudate'];



$restaurantImages = array(

    2 => 'admin/images/20200608_135036.jpg',

    3 => 'admin/images/20200611_150723.jpg'

);



$extraStylesheets = array(

    'css/portal-home.css?v=20260714c',

    'css/portal-order.css?v=20260714'

);

$bodyClass = 'portal-order-page';

$pagetitle = $selectedBusiness ? $selectedBusiness['business_name'] . ' — Menu' : 'Food Park Portal — Order Food';

include('header.php');

?>



<div class="w3-panel w3-pale-green w3-border" id="msg_display">

    <h3>Success!</h3>

    <p>Added to Cart</p>

</div>



<?php if (!$selectedBusiness): ?>

    <?php

    $restaurants = array();

    $restoSql = "SELECT business_id, business_name, business_address, business_city, business_province,
                business_contact, business_subdomain
         FROM business_account
         WHERE business_status = 1 AND business_id > 1";
    if ($filterCatId > 0) {
        $restoSql .= " AND business_id IN (
            SELECT DISTINCT business_id FROM items WHERE cat_id = $filterCatId AND business_id > 1
         )";
    }
    $restoSql .= " ORDER BY business_name ASC";

    $restoList = $conn->query($restoSql);

    if ($restoList) {

        while ($row = $restoList->fetch_assoc()) {

            $restaurants[] = $row;

        }

    }

    ?>



    <div class="portal-order-hero">

        <h1><?php echo $filterCategory ? htmlspecialchars($filterCategory['cat_name']) : 'Order Food Online'; ?></h1>

        <p><?php if ($filterCategory): ?>
            Choose a partner restaurant that offers <strong><?php echo htmlspecialchars($filterCategory['cat_name']); ?></strong> items.
        <?php else: ?>
            Choose a partner restaurant below to browse today's menu, add items to your cart, and checkout.
        <?php endif; ?></p>

    </div>



    <div class="portal-order-alert">

        <div class="portal-order-alert-inner">

            <strong><?php echo $filterCategory ? 'Browsing by category' : 'Choose a restaurant to start ordering'; ?></strong>

            <a href="home.php#restaurants">Browse all partner restaurants &rarr;</a>

        </div>

    </div>



    <div class="portal-order-body portal-order-body-picker">

        <?php if (!empty($portalCategories)): ?>
        <div class="portal-section-header" id="category-browse">
            <h2>Browse by Category</h2>
            <p>Filter partner restaurants by the kind of food you are craving.</p>
        </div>
        <div class="portal-categories" style="margin:0 0 28px;">
            <a class="portal-cat-pill<?php echo $filterCatId <= 0 ? ' is-active' : ''; ?>" href="index.php">All</a>
            <?php foreach ($portalCategories as $cat): ?>
            <a class="portal-cat-pill<?php echo $filterCatId === (int)$cat['cat_id'] ? ' is-active' : ''; ?>" href="index.php?cat_id=<?php echo (int)$cat['cat_id']; ?>#category-browse"><?php echo htmlspecialchars($cat['cat_name']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="portal-section-header">

            <h2>Partner Restaurants</h2>

            <p><?php if ($filterCategory): ?>
                Restaurants with <?php echo htmlspecialchars($filterCategory['cat_name']); ?> on their menu.
            <?php else: ?>
                Each restaurant has its own daily menu. Select one to view available dishes for <?php echo date('l, M j, Y'); ?>.
            <?php endif; ?></p>

        </div>



        <?php if (empty($restaurants)): ?>

            <div class="portal-empty">

                <p><?php echo $filterCategory
                    ? 'No restaurants currently list items in this category. Try another category or <a href="index.php">view all restaurants</a>.'
                    : 'Restaurants are being onboarded. Please check back soon.'; ?></p>

            </div>

        <?php else: ?>

        <div class="portal-restaurant-grid">

            <?php foreach ($restaurants as $biz):

                $bizId = (int)$biz['business_id'];

                $imgSrc = isset($restaurantImages[$bizId]) ? $restaurantImages[$bizId] : 'admin/images/20200608_135052.jpg';
                $menuHref = 'index.php?business_id=' . $bizId;
                if ($filterCatId > 0) {
                    $menuHref .= '&cat_id=' . $filterCatId . '#category-' . $filterCatId;
                }

            ?>

            <article class="portal-restaurant-card">

                <div class="card-img">

                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($biz['business_name']); ?>" />

                </div>

                <div class="card-body">

                    <span class="badge-open">Open for Orders</span>

                    <h3><?php echo htmlspecialchars($biz['business_name']); ?></h3>

                    <?php if (!empty($biz['business_address'])): ?>

                    <p class="meta"><?php echo htmlspecialchars($biz['business_address']); ?></p>

                    <?php endif; ?>

                    <p class="meta"><?php echo htmlspecialchars(trim($biz['business_city'] . ', ' . $biz['business_province'], ', ')); ?></p>

                    <?php if (!empty($biz['business_contact'])): ?>

                    <p class="meta">&#128222; <?php echo htmlspecialchars($biz['business_contact']); ?></p>

                    <?php endif; ?>

                    <div class="card-actions">

                        <a class="btn-order" href="<?php echo fh_store_link($bizId, $biz['business_subdomain']); ?>">Visit Store</a>

                        <a class="btn-order" href="<?php echo htmlspecialchars($menuHref); ?>">View Menu &amp; Order</a>

                    </div>

                </div>

            </article>

            <?php endforeach; ?>

        </div>

        <?php endif; ?>

    </div>



<?php else: ?>

    <div class="portal-order-hero portal-order-hero-compact">

        <div class="portal-order-hero-inner">

            <a class="portal-order-back" href="index.php">&larr; All Restaurants</a>

            <span class="portal-order-badge">Daily Menu</span>

            <h1><?php echo htmlspecialchars($selectedBusiness['business_name']); ?></h1>

            <p>

                <?php if (!empty($selectedBusiness['business_city'])): ?>

                    <?php echo htmlspecialchars($selectedBusiness['business_city']); ?> &mdash;

                <?php endif; ?>

                Menu for <?php echo date('l, F j, Y', strtotime($menudate)); ?>

            </p>

        </div>

    </div>



    <div class="portal-order-body">

        <form class="portal-menu-toolbar" id="menudate" action="./?business_id=<?php echo (int)$selectedBusinessId; ?><?php echo $filterCatId > 0 ? '&cat_id=' . $filterCatId : ''; ?>" method="POST">

            <label for="menudate">Menu Date:</label>

            <input type="date" id="menudate" name="menudate" value="<?php echo htmlspecialchars($menudate); ?>" />

            <input type="submit" name="btnDate" value="Update Menu" />

            <a href="home.php" style="margin-left:auto;font-size:13px;color:#64748b;text-decoration:none;font-weight:600;">Portal Home</a>

        </form>



        <h2 class="portal-menu-heading">Today's Menu Items</h2>

        <p class="portal-menu-sub">Click <strong>Add to Cart</strong> on any dish. When you're done, use the cart button below to checkout.</p>



        <?php

        $bid = (int)$selectedBusinessId;

        $dailyMenuIds = array();

        $itemsByCategory = array();

        $orderSql = fh_category_has_sort_column($conn)
            ? 'COALESCE(category.cat_sort, 9999) ASC, category.cat_name ASC, items.name ASC'
            : 'category.cat_name ASC, items.name ASC';

        $itemsql = $conn->query(

            "SELECT items.*, menu_listing.menu_id, menu_listing.menu_item_price,
                    category.cat_id AS category_id, category.cat_name
             FROM items
             INNER JOIN menu_listing ON items.id = menu_listing.menu_item
                 AND menu_listing.menu_date = '$menudate'
                 AND menu_listing.menu_business_id = $bid
             LEFT JOIN category ON category.cat_id = items.cat_id
             WHERE items.business_id = $bid
             ORDER BY $orderSql"

        );

        $itemcount = $itemsql ? $itemsql->num_rows : 0;

        if ($itemsql) {
            while ($row = $itemsql->fetch_assoc()) {
                $dailyMenuIds[] = (int)$row['id'];
                $catKey = !empty($row['category_id']) ? (int)$row['category_id'] : 0;
                if (!isset($itemsByCategory[$catKey])) {
                    $itemsByCategory[$catKey] = array(
                        'cat_id' => $catKey,
                        'cat_name' => !empty($row['cat_name']) ? $row['cat_name'] : 'Other',
                        'items' => array(),
                    );
                }
                $itemsByCategory[$catKey]['items'][] = $row;
            }
        }



        if ($itemcount > 0):

            if (!empty($portalCategories) && count($itemsByCategory) > 1):
        ?>
        <div class="portal-categories" style="margin:0 0 20px;">
            <?php foreach ($itemsByCategory as $catBlock): ?>
            <a class="portal-cat-pill<?php echo $filterCatId === (int)$catBlock['cat_id'] ? ' is-active' : ''; ?>" href="#category-<?php echo (int)$catBlock['cat_id']; ?>"><?php echo htmlspecialchars($catBlock['cat_name']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($itemsByCategory as $catBlock): ?>

        <section class="portal-menu-category" id="category-<?php echo (int)$catBlock['cat_id']; ?>">
            <h3 class="portal-menu-heading" style="font-size:1.15rem;margin-top:8px;"><?php echo htmlspecialchars($catBlock['cat_name']); ?></h3>
            <div class="portal-food-grid">

            <?php foreach ($catBlock['items'] as $row):

                $displayPrice = $row['menu_item_price'] > 0 ? $row['menu_item_price'] : $row['price'];

            ?>

            <article class="portal-menu-item-card">

                <img src="<?php echo htmlspecialchars(fh_food_image($row['picture'])); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" />

                <div class="portal-menu-item-body">

                    <h3><?php echo htmlspecialchars($row['name']); ?></h3>

                    <p class="portal-menu-item-price">&#8369;<?php echo number_format($displayPrice, 2); ?></p>

                    <input type="hidden" id="<?php echo (int)$row['id']; ?>_qty" value="1" />

                    <input type="hidden" id="<?php echo (int)$row['id']; ?>_price" value="<?php echo htmlspecialchars($displayPrice); ?>" />

                    <input type="hidden" id="<?php echo (int)$row['id']; ?>_name" value="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>" />

                    <button class="btnaddtocart" id="<?php echo (int)$row['id']; ?>" type="button">Add to Cart</button>

                </div>

            </article>

            <?php endforeach; ?>

            </div>
        </section>

        <?php endforeach; ?>

        <?php else: ?>

        <div class="portal-menu-empty">

            <h2>No menu available for this date</h2>

            <p>There are no items on the menu at <?php echo htmlspecialchars($selectedBusiness['business_name']); ?> for <?php echo date('F j, Y', strtotime($menudate)); ?>. Try another date or check back later.</p>

        </div>

        <?php endif; ?>



        <?php

        $preSettings = fh_preorder_settings($conn, $bid);

        $preorderItems = fh_get_preorder_items($conn, $bid, $dailyMenuIds);

        $preorderMax = (int)$preSettings['preorder_max_days'];

        $preorderMinDate = date('Y-m-d');

        $preorderMaxDate = date('Y-m-d', strtotime('+' . $preorderMax . ' days'));

        $preorderDate = fh_clamp_menudate($menudate, $preorderMax);

        if (!empty($preorderItems)):

        ?>

        <section class="portal-preorder-section">

            <h2 class="portal-menu-heading">Pre-order / Short Order</h2>

            <p class="portal-menu-sub">These dishes are always available. Pick a service date up to <?php echo $preorderMax; ?> days ahead, then add to cart.</p>

            <form class="portal-menu-toolbar portal-preorder-toolbar" action="./?business_id=<?php echo $bid; ?>" method="POST">

                <label for="preorder_menudate">Pre-order for:</label>

                <input type="date" id="preorder_menudate" name="preorder_menudate" value="<?php echo htmlspecialchars($preorderDate); ?>" min="<?php echo $preorderMinDate; ?>" max="<?php echo $preorderMaxDate; ?>" />

                <input type="submit" name="btnPreorderDate" value="Update Date" />

            </form>

            <div class="portal-food-grid">

                <?php foreach ($preorderItems as $row):

                    $displayPrice = $row['price'];

                    $itemId = (int)$row['id'];

                ?>

                <article class="portal-menu-item-card portal-preorder-card">

                    <span class="portal-preorder-badge">Pre-order</span>

                    <img src="<?php echo htmlspecialchars(fh_food_image($row['picture'])); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" />

                    <div class="portal-menu-item-body">

                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>

                        <p class="portal-menu-item-price">&#8369;<?php echo number_format($displayPrice, 2); ?></p>

                        <input type="hidden" id="<?php echo $itemId; ?>_qty" value="1" />

                        <input type="hidden" id="<?php echo $itemId; ?>_price" value="<?php echo htmlspecialchars($displayPrice); ?>" />

                        <input type="hidden" id="<?php echo $itemId; ?>_name" value="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>" />

                        <button class="btnaddtocart btn-preorder" id="<?php echo $itemId; ?>" type="button">Add to Cart</button>

                    </div>

                </article>

                <?php endforeach; ?>

            </div>

        </section>

        <?php endif; ?>

    </div>

<?php endif; ?>



<!-- Start of HubSpot Embed Code -->

<script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/7751337.js"></script>

<!-- End of HubSpot Embed Code -->

<script>
(function () {
    function scrollToCategoryTarget() {
        var hash = window.location.hash || '';
        if (!hash || hash.indexOf('#category-') !== 0) {
            return;
        }
        var el = document.querySelector(hash);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(scrollToCategoryTarget, 80);
        });
    } else {
        setTimeout(scrollToCategoryTarget, 80);
    }
})();
</script>

<?php

include('footer.php');

?>


