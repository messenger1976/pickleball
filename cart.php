<?php

session_start();

include_once('admin/config.php');

include_once(__DIR__ . '/includes/business_helper.php');



if (isset($_REQUEST['Update'])) {

    $p_id = $_REQUEST['p_id'];

    $qty = $_REQUEST['qty'];

    $name = $_REQUEST['name'];

    $unit_price = $_REQUEST['unit_price'];

    foreach ($_SESSION['cart'] as $key => $val) {

        if ($p_id === $_SESSION['cart'][$key]['p_id']) {

            unset($_SESSION['cart'][$key]);

        }

    }

    if ($qty != 0) {

        $business_id = isset($_SESSION['cart_business_id']) ? (int)$_SESSION['cart_business_id'] : 0;

        $b = array("p_id" => "$p_id", "qty" => $qty, "price" => $unit_price, "name" => $name, "business_id" => $business_id);

        array_push($_SESSION['cart'], $b);

    }

    fh_sync_cart_business_session();

    header("Location: cart.php");

    exit;

}



if (isset($_REQUEST['Delete'])) {

    $p_id = $_REQUEST['p_id'];

    $qty = $_REQUEST['qty'];

    foreach ($_SESSION['cart'] as $key => $val) {

        if ($p_id === $_SESSION['cart'][$key]['p_id']) {

            unset($_SESSION['cart'][$key]);

        }

    }

    if ($qty != 0) {

        $b = array("p_id" => "$p_id", "qty" => $qty);

        array_push($_SESSION['cart'], $b);

    }

    fh_sync_cart_business_session();

    header("Location: cart.php");

    exit;

}



$pagetitle = "Shopping Cart — Food Park Portal";

$bodyClass = 'portal-order-page';

$extraStylesheets = array(

    'css/portal-home.css?v=20260704',

    'css/portal-order.css?v=20260706'

);

include('header.php');



$cartBusiness = null;

$menuUrl = 'index.php';

$bizId = 0;

if (isset($_SESSION['cart_business_id']) && (int)$_SESSION['cart_business_id'] > 0) {

    $bizId = (int)$_SESSION['cart_business_id'];

    $cartBusiness = fh_resolve_business($conn, $bizId);

    if ($cartBusiness) {

        $menuUrl = 'index.php?business_id=' . $bizId;

    }

}



$cartItems = isset($_SESSION['cart']) && !empty($_SESSION['cart']) ? $_SESSION['cart'] : array();

$itemCount = count($cartItems);

$priceTotal = 0;

$priceTax = 0;

$priceOther = 0;

$menudate = isset($_SESSION['menudate']) ? $_SESSION['menudate'] : date('Y-m-d');



$cartProductIds = array();

foreach ($cartItems as $item) {

    $priceTotal += ((float)$item['qty'] * (float)$item['price']);

    $cartProductIds[] = (int)$item['p_id'];

}

$priceGrand = round($priceTotal + $priceTax + $priceOther, 2);

$cartItemImages = array();
if (!empty($cartProductIds)) {
    $idsList = implode(',', array_map('intval', $cartProductIds));
    $imgSql = $conn->query("SELECT id, picture FROM items WHERE id IN ($idsList)");
    if ($imgSql) {
        while ($row = $imgSql->fetch_assoc()) {
            $cartItemImages[(int)$row['id']] = $row['picture'];
        }
    }
}

$suggestedItems = array();

$excludeSql = !empty($cartProductIds) ? ' AND i.id NOT IN (' . implode(',', array_map('intval', $cartProductIds)) . ')' : '';

$safeDate = $conn->real_escape_string($menudate);



if ($bizId > 0) {

    $suggestSql = $conn->query(

        "SELECT i.id, i.name, i.price, i.picture,

                COALESCE(NULLIF(ml.menu_item_price, 0), i.price) AS display_price

         FROM items i

         INNER JOIN menu_listing ml ON i.id = ml.menu_item

             AND ml.menu_date = '$safeDate'

             AND ml.menu_business_id = $bizId

         WHERE i.business_id = $bizId $excludeSql

         ORDER BY RAND()

         LIMIT 6"

    );

    if ($suggestSql) {

        while ($row = $suggestSql->fetch_assoc()) {

            $suggestedItems[] = $row;

        }

    }

    if (count($suggestedItems) < 4) {

        $fallbackSql = $conn->query(

            "SELECT i.id, i.name, i.price, i.picture, i.price AS display_price

             FROM items i

             WHERE i.business_id = $bizId $excludeSql

             ORDER BY RAND()

             LIMIT 6"

        );

        $existingIds = array();

        foreach ($suggestedItems as $si) {

            $existingIds[(int)$si['id']] = true;

        }

        if ($fallbackSql) {

            while ($row = $fallbackSql->fetch_assoc()) {

                if (!isset($existingIds[(int)$row['id']]) && count($suggestedItems) < 6) {

                    $suggestedItems[] = $row;

                    $existingIds[(int)$row['id']] = true;

                }

            }

        }

    }

} else {

    $suggestSql = $conn->query(

        "SELECT i.id, i.name, i.price, i.picture, i.price AS display_price, ba.business_name

         FROM items i

         INNER JOIN menu_listing ml ON i.id = ml.menu_item

             AND ml.menu_date = CURDATE()

             AND ml.menu_business_id = i.business_id

         INNER JOIN business_account ba ON i.business_id = ba.business_id AND ba.business_status = 1

         ORDER BY RAND()

         LIMIT 6"

    );

    if ($suggestSql) {

        while ($row = $suggestSql->fetch_assoc()) {

            $suggestedItems[] = $row;

        }

    }

}



$promoOptions = array(

    array(

        'badge' => 'Shop more & save time',

        'title' => 'Complete your meal!',

        'text' => 'Add these popular picks before checkout — fresh from today\'s menu.'

    ),

    array(

        'badge' => 'Recommended for you',

        'title' => 'You might also like…',

        'text' => 'Don\'t miss these dishes — one tap adds them to your cart.'

    ),

    array(

        'badge' => 'Today\'s specials',

        'title' => 'Hungry for more?',

        'text' => 'Pair your order with sides, drinks, or desserts from the kitchen.'

    )

);

$promo = $promoOptions[array_rand($promoOptions)];

?>



<div class="w3-panel w3-pale-green w3-border" id="msg_display">

    <h3>Success!</h3>

    <p>Added to Cart</p>

</div>



<div class="portal-order-hero portal-order-hero-compact">

    <div class="portal-order-hero-inner">

        <a class="portal-order-back" href="<?php echo htmlspecialchars($menuUrl); ?>">&larr; Continue Shopping</a>

        <span class="portal-order-badge">Your Order</span>

        <h1>Shopping Cart</h1>

        <p>

            <?php if ($itemCount > 0): ?>

                <?php echo (int)$itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?> in your cart

                <?php if ($cartBusiness): ?> &mdash; <?php echo htmlspecialchars($cartBusiness['business_name']); ?><?php endif; ?>

            <?php else: ?>

                Review your items before checkout

            <?php endif; ?>

        </p>

    </div>

</div>



<div class="portal-order-body">

    <?php if ($cartBusiness): ?>

    <div class="portal-cart-restaurant-bar">

        <span>Ordering from: <strong><?php echo htmlspecialchars($cartBusiness['business_name']); ?></strong></span>

        <a href="<?php echo htmlspecialchars($menuUrl); ?>">+ Add more items</a>

    </div>

    <?php endif; ?>



    <?php if ($itemCount > 0): ?>

    <div class="portal-cart-layout">

        <div class="portal-cart-main">

            <div class="portal-cart-panel">

                <div class="portal-cart-panel-head">

                    <h2>Cart Items</h2>

                    <span class="portal-cart-count"><?php echo (int)$itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?></span>

                </div>



                <div class="portal-cart-items">

                    <?php foreach ($cartItems as $item):

                        $p_id = $item['p_id'];

                        $qty = $item['qty'];

                        $name = $item['name'];

                        $unit_price = (float)$item['price'];

                        $priceSub = $qty * $unit_price;

                        $itemPicture = isset($cartItemImages[(int)$p_id]) ? $cartItemImages[(int)$p_id] : '';

                    ?>

                    <div class="portal-cart-item">

                        <div class="portal-cart-item-thumb">

                            <img src="<?php echo htmlspecialchars(fh_food_image($itemPicture)); ?>" alt="<?php echo htmlspecialchars($name); ?>" />

                        </div>

                        <div class="portal-cart-item-info">

                            <p class="portal-cart-item-name"><?php echo htmlspecialchars($name); ?></p>

                            <p class="portal-cart-item-unit">&#8369;<?php echo number_format($unit_price, 2); ?> each</p>

                        </div>



                        <form class="portal-cart-qty-form" action="?" method="POST">

                            <input type="hidden" name="p_id" value="<?php echo htmlspecialchars($p_id); ?>" />

                            <input type="hidden" name="unit_price" value="<?php echo htmlspecialchars($unit_price); ?>" />

                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" />

                            <input type="number" name="qty" value="<?php echo htmlspecialchars($qty); ?>" step="0.01" min="0" aria-label="Quantity" />

                            <button type="submit" name="Update" value="1" class="portal-cart-btn portal-cart-btn-update">Update</button>

                        </form>



                        <div class="portal-cart-item-total">

                            <span>Subtotal</span>

                            <strong>&#8369;<?php echo number_format($priceSub, 2); ?></strong>

                            <form action="?" method="POST" style="margin-top:8px;">

                                <input type="hidden" name="qty" value="0" />

                                <input type="hidden" name="p_id" value="<?php echo htmlspecialchars($p_id); ?>" />

                                <button type="submit" name="Delete" value="1" class="portal-cart-btn portal-cart-btn-remove">Remove</button>

                            </form>

                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>



                <div class="portal-cart-subtotal-row">

                    <span>Items subtotal</span>

                    <strong>&#8369;<?php echo number_format($priceTotal, 2); ?></strong>

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

                    <div class="portal-cart-summary-row">

                        <span>Subtotal</span>

                        <strong>&#8369;<?php echo number_format($priceTotal, 2); ?></strong>

                    </div>

                    <div class="portal-cart-summary-row">

                        <span>Other charges</span>

                        <strong>&#8369;<?php echo number_format($priceOther, 2); ?></strong>

                    </div>

                    <div class="portal-cart-summary-total">

                        <span>You pay</span>

                        <strong>&#8369;<?php echo number_format($priceGrand, 2); ?></strong>

                    </div>

                    <p class="portal-menu-sub" style="margin:16px 0 0;font-size:13px;">Use the <strong>Checkout</strong> button below to complete your order.</p>

                </div>

            </div>

        </aside>

    </div>



    <?php else: ?>

    <div class="portal-cart-empty">

        <div class="portal-cart-empty-icon">&#128722;</div>

        <h2>Your cart is empty</h2>

        <p>Browse a restaurant menu and add dishes to get started.</p>

        <a class="portal-btn" href="index.php">Browse Restaurants</a>

    </div>

    <?php endif; ?>



    <?php if (!empty($suggestedItems)): ?>

    <section class="portal-cart-suggest" aria-label="Suggested items">

        <div class="portal-cart-promo">

            <div class="portal-cart-promo-text">

                <span class="portal-cart-promo-badge"><?php echo htmlspecialchars($promo['badge']); ?></span>

                <h3><?php echo htmlspecialchars($promo['title']); ?></h3>

                <p><?php echo htmlspecialchars($promo['text']); ?></p>

            </div>

            <a class="portal-cart-promo-link" href="<?php echo htmlspecialchars($menuUrl); ?>">Browse full menu &rarr;</a>

        </div>



        <div class="portal-cart-suggest-head">

            <h2>Suggested for you</h2>

            <?php if ($cartBusiness): ?>

            <span class="portal-cart-count">From <?php echo htmlspecialchars($cartBusiness['business_name']); ?></span>

            <?php endif; ?>

        </div>



        <div class="portal-cart-suggest-grid">

            <?php foreach ($suggestedItems as $food):

                $fid = (int)$food['id'];

                $fprice = (float)$food['display_price'];

            ?>

            <article class="portal-cart-suggest-card">

                <img src="<?php echo htmlspecialchars(fh_food_image($food['picture'])); ?>" alt="<?php echo htmlspecialchars($food['name']); ?>" />

                <div class="portal-cart-suggest-card-body">

                    <h4><?php echo htmlspecialchars($food['name']); ?></h4>

                    <?php if (!$cartBusiness && !empty($food['business_name'])): ?>

                    <p style="margin:0 0 6px;font-size:11px;color:#94a3b8;"><?php echo htmlspecialchars($food['business_name']); ?></p>

                    <?php endif; ?>

                    <p class="price">&#8369;<?php echo number_format($fprice, 2); ?></p>

                    <input type="hidden" id="<?php echo $fid; ?>_qty" value="1" />

                    <input type="hidden" id="<?php echo $fid; ?>_price" value="<?php echo htmlspecialchars($fprice); ?>" />

                    <input type="hidden" id="<?php echo $fid; ?>_name" value="<?php echo htmlspecialchars($food['name'], ENT_QUOTES); ?>" />

                    <button type="button" class="btn-suggest-add" data-item-id="<?php echo $fid; ?>">+ Add to Cart</button>

                </div>

            </article>

            <?php endforeach; ?>

        </div>

    </section>

    <?php endif; ?>

</div>



<script>

var cart = new Cart();



$(document).ready(function() {

    $('.btn-suggest-add').on('click', function() {

        var id = $(this).data('item-id');

        var $btn = $(this);

        $btn.prop('disabled', true).text('Adding…');



        $.ajax({

            type: 'POST',

            url: 'process.php',

            data: {

                function: 'AddtoCart',

                p_id: id,

                qty: $('#' + id + '_qty').val(),

                price: $('#' + id + '_price').val(),

                name: $('#' + id + '_name').val()

            },

            dataType: 'json',

            success: function(return_data) {

                if (return_data.validation_status === 'F') {

                    $('#msg_display').removeClass('w3-pale-green').addClass('w3-pale-red');

                    $('#msg_display h3').text('Notice');

                    $('#msg_display p').html(return_data.msg);

                    $('#msg_display').show();

                    $btn.prop('disabled', false).text('+ Add to Cart');

                    setTimeout(function() { $('#msg_display').fadeOut('slow'); }, 4000);

                    return;

                }

                window.location.reload();

            },

            error: function() {

                $btn.prop('disabled', false).text('+ Add to Cart');

            }

        });

    });

});

</script>



<?php include('footer.php'); ?>


