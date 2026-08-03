<?php
include('config.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

function admin_menusetup_redirect($extra = array()) {
    global $adminBusinessId;
    $params = array_merge(array('business_id' => (int)$adminBusinessId), $extra);
    header('Location: menusetup.php?' . http_build_query($params));
    exit;
}

if (isset($_REQUEST['menudate']) && $_REQUEST['menudate'] !== '') {
    $_SESSION['menudate'] = date('Y-m-d', strtotime($_REQUEST['menudate']));
} elseif (!isset($_SESSION['menudate'])) {
    $_SESSION['menudate'] = date('Y-m-d');
}
$menudate = date('Y-m-d', strtotime($_SESSION['menudate']));

if (isset($_POST['flag']) && (int)$_POST['flag'] === 1) {
    $item_id = (int)$_POST['item_id'];
    $price = isset($_POST['price']) ? $_POST['price'] : 0;
    $safeDate = $conn->real_escape_string($menudate);
    $safePrice = $conn->real_escape_string($price);
    $exists = $conn->query(
        "SELECT menu_id FROM menu_listing
         WHERE menu_date='$safeDate' AND menu_item=$item_id AND menu_business_id=$adminBusinessId
         LIMIT 1"
    );
    if ($exists && $exists->num_rows === 0 && $item_id > 0) {
        $stmtheader = $conn->prepare(
            "INSERT INTO menu_listing (menu_date,menu_item,menu_item_price,menu_business_id)
             VALUES ('$safeDate','$item_id','$safePrice','$adminBusinessId')"
        );
        $stmtheader->execute();
    }
    admin_menusetup_redirect(array(
        'menudate' => $menudate,
        'q' => isset($_POST['q']) ? $_POST['q'] : '',
        'status' => isset($_POST['status']) ? $_POST['status'] : 'all',
    ));
}

if (isset($_POST['flag']) && (int)$_POST['flag'] === 0) {
    $menu_id = (int)$_POST['menu_id'];
    if ($menu_id > 0) {
        $stmtheader = $conn->prepare(
            "DELETE FROM menu_listing WHERE menu_id=$menu_id AND menu_business_id=$adminBusinessId"
        );
        $stmtheader->execute();
    }
    admin_menusetup_redirect(array(
        'menudate' => $menudate,
        'q' => isset($_POST['q']) ? $_POST['q'] : '',
        'status' => isset($_POST['status']) ? $_POST['status'] : 'all',
    ));
}

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) && in_array($_GET['status'], array('all', 'on', 'off'), true)
    ? $_GET['status']
    : 'all';

$allowedSort = array(
    'name' => 'items.name',
    'price' => 'items.price',
    'status' => 'menu_listing.menu_id',
);

$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'name';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

$safeMenuDate = $conn->real_escape_string($menudate);
$joinSql = "LEFT JOIN menu_listing ON (
    items.id = menu_listing.menu_item
    AND menu_listing.menu_date = '$safeMenuDate'
    AND menu_listing.menu_business_id = $adminBusinessId
)";
$baseWhere = "WHERE items.business_id = $adminBusinessId";
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $baseWhere .= " AND items.name LIKE '%$safe%'";
}
if ($filterStatus === 'on') {
    $baseWhere .= ' AND menu_listing.menu_id IS NOT NULL';
} elseif ($filterStatus === 'off') {
    $baseWhere .= ' AND menu_listing.menu_id IS NULL';
}

$countResult = $conn->query("SELECT COUNT(*) AS c FROM items $joinSql $baseWhere");
$totalItems = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalItems > 0 ? (int)ceil($totalItems / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalItems > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalItems);

$onMenuResult = $conn->query(
    "SELECT COUNT(*) AS c FROM menu_listing
     WHERE menu_date='$safeMenuDate' AND menu_business_id=$adminBusinessId"
);
$onMenuCount = ($onMenuResult && ($orow = $onMenuResult->fetch_assoc())) ? (int)$orow['c'] : 0;

$catalogResult = $conn->query(
    "SELECT COUNT(*) AS c FROM items WHERE business_id=$adminBusinessId"
);
$catalogCount = ($catalogResult && ($crow = $catalogResult->fetch_assoc())) ? (int)$crow['c'] : 0;
$offMenuCount = max(0, $catalogCount - $onMenuCount);

$items = array();
$itemsSql = $conn->query(
    "SELECT items.*, menu_listing.menu_id, menu_listing.menu_item, menu_listing.menu_item_price
     FROM items
     $joinSql
     $baseWhere
     ORDER BY $sortCol $sortDir, items.name ASC
     LIMIT $offset, $perPage"
);
if ($itemsSql) {
    while ($row = $itemsSql->fetch_assoc()) {
        $items[] = $row;
    }
}

function admin_menusetup_list_url($overrides = array()) {
    global $sortKey, $sortDir, $page, $perPage, $adminBusinessId, $searchQ, $filterStatus, $menudate;
    $q = array_merge(array(
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'page' => $page,
        'per_page' => $perPage,
        'business_id' => $adminBusinessId,
        'menudate' => $menudate,
        'status' => $filterStatus,
    ), $overrides);
    if ($searchQ !== '' && !array_key_exists('q', $overrides)) {
        $q['q'] = $searchQ;
    } elseif (isset($overrides['q']) && $overrides['q'] !== '') {
        $q['q'] = $overrides['q'];
    } else {
        unset($q['q']);
    }
    return 'menusetup.php?' . http_build_query($q);
}

function admin_menusetup_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return admin_menusetup_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_menusetup_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function admin_menusetup_image($picture) {
    $img = fh_food_image($picture);
    if (strpos($img, 'admin/') === 0) {
        return substr($img, 6);
    }
    return $img;
}

$titleheader = 'Menu Setup';
include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260714b" type="text/css" />
<style>
.admin-orders-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 18px;
    padding: 16px 18px;
    margin: 0 0 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
}
.admin-orders-filter label {
    display: block;
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.admin-orders-filter input[type="date"],
.admin-orders-filter input[type="search"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 140px;
}
.admin-orders-filter button {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.admin-orders-filter button:hover {
    background: #1b4332;
}
.admin-food-thumb-btn {
    display: block;
    padding: 0;
    margin: 0;
    border: none;
    background: transparent;
    cursor: zoom-in;
    border-radius: 10px;
    line-height: 0;
}
.admin-food-thumb-btn:focus {
    outline: 2px solid #2d6a4f;
    outline-offset: 2px;
}
.admin-menusetup-thumb {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: block;
}
.portal-order-modal-dialog-preview {
    width: min(640px, calc(100% - 32px));
}
.admin-food-preview-img {
    display: block;
    width: 100%;
    max-height: min(70vh, 560px);
    object-fit: contain;
    border-radius: 10px;
    background: #0f172a;
}
.admin-menusetup-name-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.admin-menusetup-price-input {
    width: 110px;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    box-sizing: border-box;
}
.admin-menusetup-price-input:disabled {
    background: #f8fafc;
    color: #64748b;
}
.admin-menusetup-action-form {
    margin: 0;
    display: inline;
}
.admin-menusetup-btn-add,
.admin-menusetup-btn-remove {
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    min-width: 84px;
}
.admin-menusetup-btn-add {
    background: #2d6a4f;
    color: #fff;
}
.admin-menusetup-btn-add:hover {
    background: #1b4332;
}
.admin-menusetup-btn-remove {
    background: #fee2e2;
    color: #b91c1c;
}
.admin-menusetup-btn-remove:hover {
    background: #fecaca;
}
#errmsg { display: none; }
</style>

<div id="errmsg"></div>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Menu Setup</h1>
        <p>Choose a menu date, then add or remove catalog items for that day’s offerings.</p>
    </div>

    <?php echo fh_admin_business_selector($conn, 'menusetup.php'); ?>

    <form class="admin-orders-filter" method="get" action="menusetup.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="menudate">Menu Date</label>
            <input type="date" id="menudate" name="menudate" value="<?php echo htmlspecialchars($menudate); ?>">
        </div>
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Item name…">
        </div>
        <div>
            <label for="filterStatus">Status</label>
            <select id="filterStatus" name="status">
                <option value="all"<?php echo $filterStatus === 'all' ? ' selected' : ''; ?>>All items</option>
                <option value="on"<?php echo $filterStatus === 'on' ? ' selected' : ''; ?>>On menu</option>
                <option value="off"<?php echo $filterStatus === 'off' ? ' selected' : ''; ?>>Not on menu</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit" name="btnDate" value="1">Apply Filter</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($onMenuCount); ?></strong>
            <span>On menu · <?php echo date('M j, Y', strtotime($menudate)); ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($offMenuCount); ?></strong>
            <span>Not on menu</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalItems); ?></strong>
            <span>Showing<?php echo ($searchQ !== '' || $filterStatus !== 'all') ? ' (filtered)' : ''; ?></span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Daily Menu</h2>
            <?php if ($totalItems > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalItems); ?> item<?php echo $totalItems === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($items)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">📋</div>
                <h2>No items found</h2>
                <p><?php
                    if ($catalogCount === 0) {
                        echo 'No food items in this restaurant catalog yet. Add items under <strong>Food Items</strong> first.';
                    } elseif ($searchQ !== '' || $filterStatus !== 'all') {
                        echo 'No items match this filter. Try another search or status.';
                    } else {
                        echo 'No items available for this menu date.';
                    }
                ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalItems); ?></strong> item<?php echo $totalItems === 1 ? '' : 's'; ?>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="menusetup.php">
                    <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
                    <input type="hidden" name="menudate" value="<?php echo htmlspecialchars($menudate); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                    <?php if ($searchQ !== ''): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="page" value="1">
                    <label for="perPageSelect">Rows per page</label>
                    <select id="perPageSelect" name="per_page" class="portal-orders-per-page-select" onchange="this.form.submit()">
                        <?php foreach ($allowedPerPage as $option): ?>
                            <option value="<?php echo $option; ?>"<?php echo $perPage === $option ? ' selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="portal-orders-table-wrap">
                <table class="portal-orders-table" id="menuSetupTable">
                    <thead>
                        <tr>
                            <th>Picture</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_menusetup_sort_url('name')); ?>">
                                    Name <?php echo admin_menusetup_sort_indicator('name'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable portal-orders-col-amount">
                                <a href="<?php echo htmlspecialchars(admin_menusetup_sort_url('price')); ?>">
                                    Price <?php echo admin_menusetup_sort_indicator('price'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_menusetup_sort_url('status')); ?>">
                                    Status <?php echo admin_menusetup_sort_indicator('status'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row):
                            $itemId = (int)$row['id'];
                            $menuId = !empty($row['menu_id']) ? (int)$row['menu_id'] : 0;
                            $onMenu = $menuId > 0;
                            $formId = 'menu-row-' . $itemId;
                            $img = admin_menusetup_image($row['picture']);
                            $displayPrice = $onMenu && isset($row['menu_item_price']) && $row['menu_item_price'] !== null && $row['menu_item_price'] !== ''
                                ? $row['menu_item_price']
                                : $row['price'];
                            $formAction = 'menusetup.php?' . http_build_query(array(
                                'business_id' => $adminBusinessId,
                                'menudate' => $menudate,
                                'q' => $searchQ,
                                'status' => $filterStatus,
                                'sort' => $sortKey,
                                'dir' => strtolower($sortDir),
                                'per_page' => $perPage,
                                'page' => $page,
                            ));
                        ?>
                        <tr class="portal-orders-row" data-item-id="<?php echo $itemId; ?>">
                            <td>
                                <button type="button" class="admin-food-thumb-btn btn-food-preview"
                                    data-src="<?php echo htmlspecialchars($img); ?>"
                                    data-title="<?php echo htmlspecialchars($row['name']); ?>"
                                    title="View larger image"
                                    aria-label="View larger image of <?php echo htmlspecialchars($row['name']); ?>">
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="admin-menusetup-thumb">
                                </button>
                            </td>
                            <td>
                                <div class="admin-menusetup-name-cell">
                                    <span><?php echo htmlspecialchars($row['name']); ?></span>
                                </div>
                            </td>
                            <td class="portal-orders-col-amount">
                                <?php if ($onMenu): ?>
                                    <strong>₱<?php echo number_format((float)$displayPrice, 2); ?></strong>
                                <?php else: ?>
                                    <input type="number" class="admin-menusetup-price-input" form="<?php echo $formId; ?>" name="price" value="<?php echo htmlspecialchars($displayPrice); ?>" step="0.01" min="0" required>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($onMenu): ?>
                                    <span class="portal-orders-status is-success">On menu</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">Not on menu</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <form class="admin-menusetup-action-form" id="<?php echo $formId; ?>" method="post" action="<?php echo htmlspecialchars($formAction); ?>">
                                    <input type="hidden" name="menudate" value="<?php echo htmlspecialchars($menudate); ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="menu_id" value="<?php echo $menuId; ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                    <?php if ($onMenu): ?>
                                        <input type="hidden" name="flag" value="0">
                                        <input type="hidden" name="price" value="<?php echo htmlspecialchars($displayPrice); ?>">
                                        <button type="submit" class="admin-menusetup-btn-remove">Remove</button>
                                    <?php else: ?>
                                        <input type="hidden" name="flag" value="1">
                                        <button type="submit" class="admin-menusetup-btn-add">Add</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="portal-orders-pagination">
                <?php if ($page > 1): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_menusetup_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">&larr; Previous</span>
                <?php endif; ?>

                <div class="portal-orders-page-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <?php if ($p === $page): ?>
                            <span class="portal-orders-page-num is-current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_menusetup_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_menusetup_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="foodPreviewModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-preview" role="dialog" aria-labelledby="foodPreviewTitle">
        <div class="portal-order-modal-header">
            <h2 id="foodPreviewTitle">Picture</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <img id="foodPreviewImg" class="admin-food-preview-img" src="" alt="">
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Close</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $previewModal = $('#foodPreviewModal');

    function openModal($el) {
        $el.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function closeModal($el) {
        $el.removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('.portal-order-modal.is-open').length) {
            $('body').css('overflow', '');
        }
    }

    $(document).on('click', '.btn-food-preview', function () {
        var title = $(this).data('title') || 'Picture';
        $('#foodPreviewTitle').text(title);
        $('#foodPreviewImg').attr('src', $(this).data('src') || '').attr('alt', title);
        openModal($previewModal);
    });

    $('.portal-order-modal-close, .portal-order-modal-dismiss, .portal-order-modal-backdrop').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal($('.portal-order-modal.is-open').last());
        }
    });
})(jQuery);
</script>
<?php
include('footer.php');
?>
