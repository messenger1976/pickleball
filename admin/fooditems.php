<?php
include('config.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

function admin_fooditems_redirect() {
    global $adminBusinessId;
    $qs = http_build_query(array('business_id' => (int)$adminBusinessId));
    header('Location: fooditems.php?' . $qs);
    exit;
}

if (isset($_POST['btnSave'])) {
    $cat_id = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : 0;
    if ($cat_id <= 0) {
        $cat_id = 1;
    }
    $itemname = $_POST['itemname'];
    $itemprice = $_POST['itemprice'];
    $item_id = (int)$_POST['item_id'];
    $business_id = $adminBusinessId;
    $allow_preorder = isset($_POST['allow_preorder']) ? 1 : 0;
    $target_dir = 'images/';
    $hasUpload = !empty($_FILES['itempix']['name']);
    $uploadOk = 0;
    $target_file = '';

    if ($hasUpload) {
        $target_file = $target_dir . basename($_FILES['itempix']['name']);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = @getimagesize($_FILES['itempix']['tmp_name']);
        if ($check === false) {
            $uploadOk = 0;
        }

        if (file_exists($target_file)) {
            $uploadOk = 0;
        }

        if ($_FILES['itempix']['size'] > 50000000) {
            $uploadOk = 0;
        }

        if ($imageFileType != 'jpg' && $imageFileType != 'png' && $imageFileType != 'jpeg' && $imageFileType != 'gif') {
            $uploadOk = 0;
        }
    }

    if (!$hasUpload || $uploadOk == 0) {
        if ($item_id == 0) {
            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price,business_id,allow_preorder) VALUES ('$cat_id','$itemname','$itemprice','$business_id','$allow_preorder')");
            $stmtheader->execute();
        } else {
            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice',business_id='$business_id',allow_preorder='$allow_preorder' where id=$item_id and business_id=$business_id");
            $stmtheader->execute();
        }
        admin_fooditems_redirect();
    }

    if (move_uploaded_file($_FILES['itempix']['tmp_name'], $target_file)) {
        if ($item_id == 0) {
            $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price, picture, business_id,allow_preorder) VALUES ('$cat_id','$itemname','$itemprice','$target_file','$business_id','$allow_preorder')");
            $stmtheader->execute();
        } else {
            $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice', picture='$target_file', business_id='$business_id',allow_preorder='$allow_preorder' where id=$item_id and business_id=$business_id");
            $stmtheader->execute();
        }
        admin_fooditems_redirect();
    }

    if ($item_id == 0) {
        $stmtheader = $conn->prepare("INSERT INTO items (cat_id,name,price,business_id,allow_preorder) VALUES ('$cat_id','$itemname','$itemprice','$business_id','$allow_preorder')");
        $stmtheader->execute();
    } else {
        $stmtheader = $conn->prepare("update items set cat_id='$cat_id',name='$itemname',price='$itemprice',business_id='$business_id',allow_preorder='$allow_preorder' where id=$item_id and business_id=$business_id");
        $stmtheader->execute();
    }
    admin_fooditems_redirect();
}

$allowedSort = array(
    'id' => 'items.id',
    'name' => 'items.name',
    'price' => 'items.price',
    'category' => 'category.cat_name',
    'allow_preorder' => 'items.allow_preorder',
);

$sortKey = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'name';
$sortCol = $allowedSort[$sortKey];
$sortDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterPreorder = isset($_GET['preorder']) && in_array($_GET['preorder'], array('all', 'yes', 'no'), true)
    ? $_GET['preorder']
    : 'all';
$filterCatId = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;

$allowedPerPage = array(10, 25, 50, 100);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}

include_once(__DIR__ . '/../includes/category_helper.php');
$categoryOptions = fh_category_list($conn, true);

$baseWhere = "WHERE items.business_id=" . (int)$adminBusinessId;
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $baseWhere .= " AND items.name LIKE '%$safe%'";
}
if ($filterPreorder === 'yes') {
    $baseWhere .= ' AND items.allow_preorder=1';
} elseif ($filterPreorder === 'no') {
    $baseWhere .= ' AND (items.allow_preorder=0 OR items.allow_preorder IS NULL)';
}
if ($filterCatId > 0) {
    $baseWhere .= ' AND items.cat_id=' . $filterCatId;
}

$countResult = $conn->query("SELECT COUNT(*) AS c FROM items $baseWhere");
$totalItems = ($countResult && ($countRow = $countResult->fetch_assoc())) ? (int)$countRow['c'] : 0;
$totalPages = $totalItems > 0 ? (int)ceil($totalItems / $perPage) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$showingFrom = $totalItems > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalItems);

$preorderCountResult = $conn->query(
    "SELECT COUNT(*) AS c FROM items WHERE business_id=" . (int)$adminBusinessId . " AND allow_preorder=1"
);
$preorderCount = ($preorderCountResult && ($prow = $preorderCountResult->fetch_assoc())) ? (int)$prow['c'] : 0;

$items = array();
$itemsSql = $conn->query(
    "SELECT items.*, category.cat_name
     FROM items
     LEFT JOIN category ON category.cat_id = items.cat_id
     $baseWhere
     ORDER BY $sortCol $sortDir, items.id ASC
     LIMIT $offset, $perPage"
);
if ($itemsSql) {
    while ($row = $itemsSql->fetch_assoc()) {
        $items[] = $row;
    }
}

function admin_fooditems_list_url($overrides = array()) {
    global $sortKey, $sortDir, $page, $perPage, $adminBusinessId, $searchQ, $filterPreorder, $filterCatId;
    $q = array_merge(array(
        'sort' => $sortKey,
        'dir' => strtolower($sortDir),
        'page' => $page,
        'per_page' => $perPage,
        'business_id' => $adminBusinessId,
        'preorder' => $filterPreorder,
        'cat_id' => $filterCatId,
    ), $overrides);
    if ($searchQ !== '' && !array_key_exists('q', $overrides)) {
        $q['q'] = $searchQ;
    } elseif (isset($overrides['q']) && $overrides['q'] !== '') {
        $q['q'] = $overrides['q'];
    } else {
        unset($q['q']);
    }
    if (isset($q['cat_id']) && (int)$q['cat_id'] <= 0) {
        unset($q['cat_id']);
    }
    if (isset($q['preorder']) && $q['preorder'] === 'all') {
        // keep for form state consistency
    }
    return 'fooditems.php?' . http_build_query($q);
}

function admin_fooditems_sort_url($column) {
    global $sortKey, $sortDir, $page;
    $newDir = 'asc';
    if ($column === $sortKey && strtolower($sortDir) === 'asc') {
        $newDir = 'desc';
    }
    return admin_fooditems_list_url(array(
        'sort' => $column,
        'dir' => $newDir,
        'page' => (int)$page,
    ));
}

function admin_fooditems_sort_indicator($column) {
    global $sortKey, $sortDir;
    if ($column !== $sortKey) {
        return '<span class="portal-orders-sort-icon" aria-hidden="true">↕</span>';
    }
    return strtolower($sortDir) === 'asc'
        ? '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↑</span>'
        : '<span class="portal-orders-sort-icon is-active" aria-hidden="true">↓</span>';
}

function admin_fooditems_image($picture) {
    $img = fh_food_image($picture);
    if (strpos($img, 'admin/') === 0) {
        return substr($img, 6);
    }
    return $img;
}

$titleheader = 'Food Items';
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
.admin-orders-filter .admin-fooditems-filter-spacer {
    flex: 1 1 auto;
    min-width: 12px;
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
.admin-orders-filter input[type="search"],
.admin-orders-filter select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    background: #fff;
    min-width: 160px;
}
.admin-orders-filter button,
.admin-fooditems-toolbar-btn {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #2d6a4f;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.admin-orders-filter button:hover,
.admin-fooditems-toolbar-btn:hover {
    background: #1b4332;
    color: #fff;
    text-decoration: none;
}
.admin-fooditems-toolbar-btn.is-secondary {
    background: #fff;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.admin-fooditems-toolbar-btn.is-secondary:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.admin-fooditems-head-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
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
.admin-fooditems-thumb {
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
.admin-fooditems-name-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
#image-holder {
    margin: 10px 0 16px;
    width: 100%;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    overflow: hidden;
}
#image-holder:empty {
    display: none;
}
.admin-fooditems-preview {
    width: 100%;
    height: 280px;
    border: none;
    border-radius: 0;
    margin: 0;
    display: block;
    object-fit: cover;
    background: #e2e8f0;
}
.portal-order-modal-section input[type="text"],
.portal-order-modal-section input[type="number"],
.portal-order-modal-section input[type="file"],
.portal-order-modal-section select {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    margin: 0 0 14px;
}
.portal-order-modal-section .admin-fooditems-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 4px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.portal-order-modal-section .admin-fooditems-check input {
    width: auto;
    margin: 2px 0 0;
}
.portal-order-modal-dialog-food {
    width: min(640px, calc(100% - 32px));
}
#errmsg { display: none; }
</style>

<div id="errmsg"></div>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Food Listing</h1>
        <p>Manage menu items for this restaurant — add, edit, or remove dishes and mark pre-order availability.</p>
        <div class="admin-fooditems-head-actions">
            <a href="category.php" class="admin-fooditems-toolbar-btn is-secondary">Category Setup</a>
            <a href="copy_items.php?business_id=<?php echo (int)$adminBusinessId; ?>" class="admin-fooditems-toolbar-btn is-secondary">Copy From Another Restaurant</a>
        </div>
    </div>

    <?php echo fh_admin_business_selector($conn, 'fooditems.php'); ?>

    <form class="admin-orders-filter" method="get" action="fooditems.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        <input type="hidden" name="page" value="1">
        <div>
            <label for="filterSearch">Search</label>
            <input type="search" id="filterSearch" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Item name…">
        </div>
        <div>
            <label for="filterCategory">Category</label>
            <select id="filterCategory" name="cat_id">
                <option value="0"<?php echo $filterCatId <= 0 ? ' selected' : ''; ?>>All categories</option>
                <?php foreach ($categoryOptions as $catOpt): ?>
                <option value="<?php echo (int)$catOpt['cat_id']; ?>"<?php echo $filterCatId === (int)$catOpt['cat_id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($catOpt['cat_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filterPreorder">Pre-order</label>
            <select id="filterPreorder" name="preorder">
                <option value="all"<?php echo $filterPreorder === 'all' ? ' selected' : ''; ?>>All items</option>
                <option value="yes"<?php echo $filterPreorder === 'yes' ? ' selected' : ''; ?>>Pre-order only</option>
                <option value="no"<?php echo $filterPreorder === 'no' ? ' selected' : ''; ?>>Regular only</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button type="submit">Apply Filter</button>
        </div>
        <div class="admin-fooditems-filter-spacer" aria-hidden="true"></div>
        <div>
            <label>&nbsp;</label>
            <button type="button" class="admin-fooditems-toolbar-btn" id="btnAddFoodItem">Add Item</button>
        </div>
    </form>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($totalItems); ?></strong>
            <span>Items<?php echo $searchQ !== '' || $filterPreorder !== 'all' || $filterCatId > 0 ? ' (filtered)' : ''; ?></span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($preorderCount); ?></strong>
            <span>Available for pre-order</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo (int)$page; ?></strong>
            <span>Page of <?php echo max(1, $totalPages); ?></span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Menu Items</h2>
            <?php if ($totalItems > 0): ?>
                <span class="portal-cart-count"><?php echo number_format($totalItems); ?> item<?php echo $totalItems === 1 ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($items)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">🍽️</div>
                <h2>No food items found</h2>
                <p><?php echo ($searchQ !== '' || $filterPreorder !== 'all' || $filterCatId > 0)
                    ? 'No items match this filter. Try another search or clear the category / pre-order filters.'
                    : 'No menu items yet for this restaurant. Click <strong>Add Item</strong> to create one.'; ?></p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-toolbar">
                <p class="portal-orders-showing">
                    Showing <strong><?php echo number_format($showingFrom); ?></strong>–<strong><?php echo number_format($showingTo); ?></strong>
                    of <strong><?php echo number_format($totalItems); ?></strong> item<?php echo $totalItems === 1 ? '' : 's'; ?>
                </p>
                <form class="portal-orders-per-page-form" method="get" action="fooditems.php">
                    <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars(strtolower($sortDir)); ?>">
                    <input type="hidden" name="preorder" value="<?php echo htmlspecialchars($filterPreorder); ?>">
                    <input type="hidden" name="cat_id" value="<?php echo (int)$filterCatId; ?>">
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
                <table class="portal-orders-table" id="foodItemsTable">
                    <thead>
                        <tr>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_fooditems_sort_url('id')); ?>">
                                    ID <?php echo admin_fooditems_sort_indicator('id'); ?>
                                </a>
                            </th>
                            <th>Picture</th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_fooditems_sort_url('name')); ?>">
                                    Name <?php echo admin_fooditems_sort_indicator('name'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_fooditems_sort_url('category')); ?>">
                                    Category <?php echo admin_fooditems_sort_indicator('category'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable portal-orders-col-amount">
                                <a href="<?php echo htmlspecialchars(admin_fooditems_sort_url('price')); ?>">
                                    Price <?php echo admin_fooditems_sort_indicator('price'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-sortable">
                                <a href="<?php echo htmlspecialchars(admin_fooditems_sort_url('allow_preorder')); ?>">
                                    Pre-order <?php echo admin_fooditems_sort_indicator('allow_preorder'); ?>
                                </a>
                            </th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row):
                            $itemId = (int)$row['id'];
                            $img = admin_fooditems_image($row['picture']);
                            $isPreorder = !empty($row['allow_preorder']);
                            $pixAttr = htmlspecialchars($row['picture'] ?: '', ENT_QUOTES);
                        ?>
                        <tr class="portal-orders-row" data-item-id="<?php echo $itemId; ?>">
                            <td><strong>#<?php echo $itemId; ?></strong></td>
                            <td>
                                <button type="button" class="admin-food-thumb-btn btn-food-preview"
                                    data-src="<?php echo htmlspecialchars($img); ?>"
                                    data-title="<?php echo htmlspecialchars($row['name']); ?>"
                                    title="View larger image"
                                    aria-label="View larger image of <?php echo htmlspecialchars($row['name']); ?>">
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="admin-fooditems-thumb">
                                </button>
                            </td>
                            <td>
                                <div class="admin-fooditems-name-cell">
                                    <span><?php echo htmlspecialchars($row['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars(!empty($row['cat_name']) ? $row['cat_name'] : '—'); ?></td>
                            <td class="portal-orders-col-amount"><strong>₱<?php echo number_format((float)$row['price'], 2); ?></strong></td>
                            <td>
                                <?php if ($isPreorder): ?>
                                    <span class="portal-orders-status is-warning">Pre-order</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">Regular</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-fooditem-edit" data-itemid="<?php echo $itemId; ?>" title="Edit item" aria-label="Edit item #<?php echo $itemId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel btn-fooditem-delete" data-itemid="<?php echo $itemId; ?>" data-itempix="<?php echo $pixAttr; ?>" title="Delete item" aria-label="Delete item #<?php echo $itemId; ?>">✕</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="portal-orders-pagination">
                <?php if ($page > 1): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_fooditems_list_url(array('page' => $page - 1))); ?>">&larr; Previous</a>
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
                            <a class="portal-orders-page-num" href="<?php echo htmlspecialchars(admin_fooditems_list_url(array('page' => $p))); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a class="portal-orders-page-btn" href="<?php echo htmlspecialchars(admin_fooditems_list_url(array('page' => $page + 1))); ?>">Next &rarr;</a>
                <?php else: ?>
                    <span class="portal-orders-page-btn is-disabled">Next &rarr;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="foodItemModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-food" role="dialog" aria-labelledby="foodItemModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="foodItemModalTitle">Add Item</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <form id="frmAddEdit" action="fooditems.php?business_id=<?php echo (int)$adminBusinessId; ?>" method="post" enctype="multipart/form-data">
            <div class="portal-order-modal-body">
                <div class="portal-order-modal-section">
                    <input type="hidden" id="item_id" name="item_id" value="0">

                    <label for="itemname">Name</label>
                    <input type="text" id="itemname" name="itemname" placeholder="Enter item name" required>

                    <label for="cat_id">Category</label>
                    <select id="cat_id" name="cat_id" required>
                        <?php if (empty($categoryOptions)): ?>
                        <option value="0">No categories — create one in Category Setup</option>
                        <?php else: ?>
                            <?php foreach ($categoryOptions as $catOpt): ?>
                            <option value="<?php echo (int)$catOpt['cat_id']; ?>">
                                <?php echo htmlspecialchars($catOpt['cat_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="portal-order-modal-hint"><a href="category.php">Manage categories</a> if you need to add or rename one.</p>

                    <label for="itemprice">Price</label>
                    <input type="number" id="itemprice" name="itemprice" placeholder="Enter price" step="0.01" min="0" required>

                    <label for="itempix">Picture</label>
                    <input type="file" id="itempix" name="itempix" accept="image/jpeg,image/png,image/gif,image/jpg">
                    <p class="portal-order-modal-hint">Optional when editing — leave empty to keep the current image. JPG, PNG, or GIF.</p>
                    <div id="image-holder"></div>

                    <label class="admin-fooditems-check" for="allow_preorder">
                        <input type="checkbox" id="allow_preorder" name="allow_preorder" value="1">
                        <span>Available for pre-order (standing / short order menu)</span>
                    </label>
                </div>
            </div>
            <div class="portal-order-modal-footer">
                <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
                <button type="submit" class="portal-login-btn portal-login-btn-primary" name="btnSave" value="1">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="portal-order-modal" id="foodItemDeleteModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Delete Item <span id="foodDeleteItemId"></span></h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="foodDeleteMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Are you sure you want to delete this food item? This cannot be undone.</p>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep Item</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnConfirmDeleteItem">Confirm Delete</button>
        </div>
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
    var $modal = $('#foodItemModal');
    var $deleteModal = $('#foodItemDeleteModal');
    var pendingDeleteId = 0;
    var pendingDeletePix = '';

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

    function resetForm() {
        $('#item_id').val(0);
        $('#itemname').val('');
        $('#itemprice').val('');
        $('#itempix').val('');
        $('#cat_id').prop('selectedIndex', 0);
        $('#allow_preorder').prop('checked', false);
        $('#image-holder').empty();
        $('#foodItemModalTitle').text('Add Item');
    }

    function showAdd() {
        resetForm();
        openModal($modal);
    }

    function showEdit(itemId) {
        resetForm();
        $('#foodItemModalTitle').text('Edit Item #' + itemId);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'EditRecord',
                item_id: itemId
            },
            dataType: 'json',
            success: function (data) {
                $('#item_id').val(data.id);
                $('#itemname').val(data.name);
                $('#itemprice').val(data.price);
                if (data.cat_id) {
                    $('#cat_id').val(String(data.cat_id));
                }
                $('#allow_preorder').prop('checked', parseInt(data.allow_preorder, 10) === 1);
                if (data.picture) {
                    $('#image-holder').html('<img src="' + data.picture + '" class="admin-fooditems-preview" alt="">');
                }
                openModal($modal);
            }
        });
    }

    function showDelete(itemId, itemPix) {
        pendingDeleteId = itemId;
        pendingDeletePix = itemPix || '';
        $('#foodDeleteItemId').text('#' + itemId);
        $('#foodDeleteMsg').hide().text('');
        openModal($deleteModal);
    }

    function showPreview(src, title) {
        $('#foodPreviewTitle').text(title || 'Picture');
        $('#foodPreviewImg').attr('src', src || '').attr('alt', title || '');
        openModal($('#foodPreviewModal'));
    }

    $('#btnAddFoodItem').on('click', showAdd);

    $(document).on('click', '.btn-fooditem-edit', function () {
        showEdit(parseInt($(this).data('itemid'), 10) || 0);
    });

    $(document).on('click', '.btn-fooditem-delete', function () {
        showDelete(
            parseInt($(this).data('itemid'), 10) || 0,
            $(this).attr('data-itempix') || ''
        );
    });

    $(document).on('click', '.btn-food-preview', function () {
        showPreview($(this).data('src'), $(this).data('title'));
    });

    $('.portal-order-modal-close, .portal-order-modal-dismiss').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $('.portal-order-modal-backdrop').on('click', function () {
        closeModal($(this).closest('.portal-order-modal'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal($('.portal-order-modal.is-open').last());
        }
    });

    $('#itempix').on('change', function () {
        var countFiles = this.files.length;
        var imgPath = this.value;
        var extn = imgPath.substring(imgPath.lastIndexOf('.') + 1).toLowerCase();
        var $holder = $('#image-holder');
        $holder.empty();
        if (extn === 'gif' || extn === 'png' || extn === 'jpg' || extn === 'jpeg') {
            if (typeof FileReader === 'undefined') {
                alert('This browser does not support FileReader.');
                return;
            }
            for (var i = 0; i < countFiles; i++) {
                (function (file) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        $('<img />', {
                            src: e.target.result,
                            class: 'admin-fooditems-preview'
                        }).appendTo($holder);
                    };
                    reader.readAsDataURL(file);
                })(this.files[i]);
            }
        } else if (imgPath) {
            alert('Please select only images (JPG, PNG, GIF).');
            this.value = '';
        }
    });

    $('#btnConfirmDeleteItem').on('click', function () {
        if (!pendingDeleteId) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'DeleteRecord',
                item_id: pendingDeleteId,
                item_pix: pendingDeletePix
            },
            dataType: 'json',
            success: function (data) {
                if (data && data.msg) {
                    window.location.reload();
                    return;
                }
                $('#foodDeleteMsg').text('Could not delete this item. Please try again.').show();
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#foodDeleteMsg').text('Could not delete this item. Please try again.').show();
                $btn.prop('disabled', false);
            }
        });
    });
})(jQuery);
</script>
<?php
include('footer.php');
?>
