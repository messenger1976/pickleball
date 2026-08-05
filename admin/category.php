<?php
include('config.php');
fh_auth_require_login();
include_once(__DIR__ . '/../includes/category_helper.php');

$titleheader = 'Category Setup';
$categories = fh_category_list($conn, false);
$itemCounts = array();
foreach ($categories as $cat) {
    $itemCounts[(int)$cat['cat_id']] = fh_category_item_count($conn, $cat['cat_id']);
}
$activeCount = 0;
foreach ($categories as $cat) {
    if ((int)$cat['cat_status'] === 1) {
        $activeCount++;
    }
}

include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260804c" type="text/css" />
<style>
.admin-category-intro {
    margin: 0 0 18px;
    padding: 14px 16px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 12px;
    color: #065f46;
    font-size: 13px;
    line-height: 1.5;
}
.admin-category-name {
    font-weight: 700;
    color: #0f172a;
}
.admin-category-meta {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.portal-order-modal-section input[type="text"],
.portal-order-modal-section input[type="number"] {
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
.portal-order-modal-dialog-category {
    width: min(480px, calc(100% - 32px));
}
.admin-fooditems-head-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}
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
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Category Setup</h1>
        <p>Define the shared food categories used when creating menu items and browsing the portal home page.</p>
        <div class="admin-fooditems-head-actions">
            <a href="fooditems.php" class="admin-fooditems-toolbar-btn is-secondary">Go to Food Items</a>
            <button type="button" class="admin-fooditems-toolbar-btn" id="btnAddCategory">Add Category</button>
        </div>
    </div>

    <div class="admin-category-intro">
        Categories are shared across all restaurants. Assign them in <strong>Food Items</strong>.
        Active categories appear under <em>Browse by Category</em> on the portal home page.
    </div>

    <div class="portal-orders-summary">
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format(count($categories)); ?></strong>
            <span>Total categories</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format($activeCount); ?></strong>
            <span>Visible on home</span>
        </div>
        <div class="portal-orders-summary-card">
            <strong><?php echo number_format(array_sum($itemCounts)); ?></strong>
            <span>Items assigned</span>
        </div>
    </div>

    <div class="portal-cart-panel portal-orders-panel">
        <div class="portal-cart-panel-head">
            <h2>Food Categories</h2>
            <?php if (!empty($categories)): ?>
                <span class="portal-cart-count"><?php echo count($categories); ?> categor<?php echo count($categories) === 1 ? 'y' : 'ies'; ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($categories)): ?>
            <div class="portal-cart-empty portal-orders-empty">
                <div class="portal-cart-empty-icon">🏷</div>
                <h2>No categories yet</h2>
                <p>Click <strong>Add Category</strong> to create Main Dish, Drinks, Desserts, and more.</p>
            </div>
        <?php else: ?>
            <div class="portal-orders-table-wrap">
                <table class="portal-orders-table" id="categoryTable">
                    <thead>
                        <tr>
                            <th>Sort</th>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th class="portal-orders-col-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat):
                            $catId = (int)$cat['cat_id'];
                            $isActive = (int)$cat['cat_status'] === 1;
                            $count = isset($itemCounts[$catId]) ? (int)$itemCounts[$catId] : 0;
                            $sortVal = isset($cat['cat_sort']) ? (int)$cat['cat_sort'] : 0;
                        ?>
                        <tr class="portal-orders-row" data-cat-id="<?php echo $catId; ?>">
                            <td><strong><?php echo $sortVal; ?></strong></td>
                            <td>
                                <div class="admin-category-name"><?php echo htmlspecialchars($cat['cat_name']); ?></div>
                                <div class="admin-category-meta">ID #<?php echo $catId; ?></div>
                            </td>
                            <td><?php echo number_format($count); ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="portal-orders-status is-success">Active</span>
                                <?php else: ?>
                                    <span class="portal-orders-status is-neutral">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="portal-orders-col-action">
                                <div class="portal-orders-actions">
                                    <button type="button" class="portal-orders-action-btn btn-category-edit" data-catid="<?php echo $catId; ?>" title="Edit category" aria-label="Edit category #<?php echo $catId; ?>">✎</button>
                                    <button type="button" class="portal-orders-action-btn portal-orders-action-cancel btn-category-delete" data-catid="<?php echo $catId; ?>" data-catname="<?php echo htmlspecialchars($cat['cat_name'], ENT_QUOTES); ?>" title="Delete category" aria-label="Delete category #<?php echo $catId; ?>">✕</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="portal-order-modal" id="categoryModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-category" role="dialog" aria-labelledby="categoryModalTitle">
        <div class="portal-order-modal-header">
            <h2 id="categoryModalTitle">Add Category</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div class="portal-order-modal-section">
                <div id="categoryFormMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
                <input type="hidden" id="cat_id" value="0">

                <label for="cat_name">Category Name</label>
                <input type="text" id="cat_name" maxlength="150" placeholder="e.g. Main Dish, Drinks, Desserts" required>

                <label for="cat_sort">Sort Order</label>
                <input type="number" id="cat_sort" value="0" step="1">
                <p class="portal-order-modal-hint">Lower numbers appear first on the home page and in Food Items.</p>

                <label class="admin-fooditems-check" for="cat_status">
                    <input type="checkbox" id="cat_status" checked>
                    <span>Active (show on portal home page)</span>
                </label>
            </div>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Cancel</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary" id="btnSaveCategory">Save</button>
        </div>
    </div>
</div>

<div class="portal-order-modal" id="categoryDeleteModal" aria-hidden="true">
    <div class="portal-order-modal-backdrop"></div>
    <div class="portal-order-modal-dialog portal-order-modal-dialog-sm" role="dialog">
        <div class="portal-order-modal-header">
            <h2>Delete Category</h2>
            <button type="button" class="portal-order-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="portal-order-modal-body">
            <div id="categoryDeleteMsg" class="portal-login-panel-alert" style="display:none" role="alert"></div>
            <p class="portal-login-lead">Delete <strong id="categoryDeleteName"></strong>? Categories with assigned food items cannot be deleted.</p>
        </div>
        <div class="portal-order-modal-footer">
            <button type="button" class="portal-login-btn portal-login-btn-secondary portal-order-modal-dismiss">Keep Category</button>
            <button type="button" class="portal-login-btn portal-login-btn-primary portal-orders-cancel-confirm" id="btnConfirmDeleteCategory">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
(function ($) {
    var $modal = $('#categoryModal');
    var $deleteModal = $('#categoryDeleteModal');
    var pendingDeleteId = 0;

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
        $('#cat_id').val(0);
        $('#cat_name').val('');
        $('#cat_sort').val(0);
        $('#cat_status').prop('checked', true);
        $('#categoryFormMsg').hide().text('');
        $('#categoryModalTitle').text('Add Category');
    }

    function showAdd() {
        resetForm();
        openModal($modal);
        $('#cat_name').focus();
    }

    function showEdit(catId) {
        resetForm();
        $('#categoryModalTitle').text('Edit Category #' + catId);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'LoadCategory', cat_id: catId },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status !== 'T' || !res.data) {
                    alert(res.msg || 'Could not load category.');
                    return;
                }
                var d = res.data;
                $('#cat_id').val(d.cat_id);
                $('#cat_name').val(d.cat_name);
                $('#cat_sort').val(d.cat_sort || 0);
                $('#cat_status').prop('checked', parseInt(d.cat_status, 10) === 1);
                openModal($modal);
                $('#cat_name').focus();
            },
            error: function () {
                alert('Could not load category.');
            }
        });
    }

    function showDelete(catId, catName) {
        pendingDeleteId = catId;
        $('#categoryDeleteName').text(catName || ('#' + catId));
        $('#categoryDeleteMsg').hide().text('');
        openModal($deleteModal);
    }

    $('#btnAddCategory').on('click', showAdd);

    $(document).on('click', '.btn-category-edit', function () {
        showEdit(parseInt($(this).data('catid'), 10) || 0);
    });

    $(document).on('click', '.btn-category-delete', function () {
        showDelete(
            parseInt($(this).data('catid'), 10) || 0,
            $(this).attr('data-catname') || ''
        );
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

    $('#btnSaveCategory').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $('#categoryFormMsg').hide().text('');
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'SaveCategory',
                cat_id: $('#cat_id').val(),
                cat_name: $('#cat_name').val(),
                cat_sort: $('#cat_sort').val(),
                cat_status: $('#cat_status').is(':checked') ? 1 : 0
            },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status === 'T') {
                    location.reload();
                    return;
                }
                $('#categoryFormMsg').text(res.msg || 'Could not save category.').show();
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#categoryFormMsg').text('Something went wrong. Please try again.').show();
                $btn.prop('disabled', false);
            }
        });
    });

    $('#btnConfirmDeleteCategory').on('click', function () {
        if (!pendingDeleteId) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'DeleteCategory', cat_id: pendingDeleteId },
            dataType: 'json',
            success: function (res) {
                if (res.validation_status === 'T') {
                    location.reload();
                    return;
                }
                $('#categoryDeleteMsg').text(res.msg || 'Could not delete category.').show();
                $btn.prop('disabled', false);
            },
            error: function () {
                $('#categoryDeleteMsg').text('Something went wrong. Please try again.').show();
                $btn.prop('disabled', false);
            }
        });
    });
})(jQuery);
</script>

<?php include('footer.php'); ?>
