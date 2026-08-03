<?php
include('config.php');
fh_auth_require_login();

$targetBusinessId = fh_admin_business_id($conn);
$targetBusiness = fh_resolve_business($conn, $targetBusinessId);
$businesses = fh_get_active_businesses($conn);

$sourceBusinessId = isset($_GET['source_business_id']) ? (int)$_GET['source_business_id'] : 0;
if ($sourceBusinessId === $targetBusinessId) {
    $sourceBusinessId = 0;
}
if ($sourceBusinessId <= 0 && count($businesses) > 1) {
    foreach ($businesses as $biz) {
        if ((int)$biz['business_id'] !== $targetBusinessId) {
            $sourceBusinessId = (int)$biz['business_id'];
            break;
        }
    }
}

$sourceItems = array();
if ($sourceBusinessId > 0) {
    $itemSql = $conn->query(
        "SELECT id, name, price, picture FROM items WHERE business_id = $sourceBusinessId ORDER BY name ASC"
    );
    if ($itemSql) {
        while ($row = $itemSql->fetch_assoc()) {
            $sourceItems[] = $row;
        }
    }
}

$titleheader = 'Copy Items';
include('header.php');
include('navigation.php');
?>

<style>
.copy-panel {
    margin: 12px 0;
    padding: 16px;
    background: #f8f9fa;
    border-left: 4px solid #40916c;
}
.copy-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.copy-item-row img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 6px;
}
.copy-item-row label {
    flex: 1;
    cursor: pointer;
    margin: 0;
}
.copy-actions {
    margin: 16px 0;
}
.copy-actions button {
    width: auto;
    display: inline-block;
    margin-right: 8px;
    padding: 10px 18px;
}
#copyResult {
    display: none;
    margin: 12px 0;
    padding: 12px;
    border-radius: 6px;
}
</style>

<div class="w3-container-padding admin-page-body">

    <div class="w3-container w3-teal">
        <h1>Copy Food Items Between Restaurants</h1>
    </div>

    <?php echo fh_admin_business_selector($conn, 'copy_items.php'); ?>

    <div class="copy-panel">
        <p><strong>Copy TO:</strong> <?php echo htmlspecialchars($targetBusiness ? $targetBusiness['business_name'] : 'Unknown'); ?></p>
        <p style="margin:0;color:#666;font-size:14px;">Duplicate items from another restaurant into this one. Existing items with the same name are skipped.</p>
    </div>

    <form method="GET" action="copy_items.php" class="w3-panel w3-light-grey">
        <label for="source_business_id"><strong>Copy FROM:</strong></label>
        <select id="source_business_id" name="source_business_id" onchange="this.form.submit()" style="padding:8px;max-width:320px;">
            <option value="0">— Select source restaurant —</option>
            <?php foreach ($businesses as $biz):
                if ((int)$biz['business_id'] === $targetBusinessId) continue;
            ?>
            <option value="<?php echo (int)$biz['business_id']; ?>" <?php echo $sourceBusinessId === (int)$biz['business_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($biz['business_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="business_id" value="<?php echo (int)$targetBusinessId; ?>" />
    </form>

    <div id="copyResult" class="w3-panel"></div>

    <?php if ($sourceBusinessId <= 0): ?>
        <div class="w3-panel w3-pale-yellow">Select a source restaurant to view items available for copying.</div>
    <?php elseif (empty($sourceItems)): ?>
        <div class="w3-panel w3-pale-yellow">This restaurant has no food items yet.</div>
    <?php else: ?>
        <div class="copy-actions">
            <button type="button" id="btnSelectAll" class="w3-button w3-light-grey">Select All</button>
            <button type="button" id="btnSelectNone" class="w3-button w3-light-grey">Select None</button>
            <button type="button" id="btnCopySelected" class="w3-button w3-green">Copy Selected</button>
            <button type="button" id="btnCopyAll" class="w3-button w3-blue">Copy All (<?php echo count($sourceItems); ?>)</button>
        </div>

        <div class="w3-panel w3-white w3-border">
            <?php foreach ($sourceItems as $item): ?>
            <div class="copy-item-row">
                <input type="checkbox" class="copy-item-check" value="<?php echo (int)$item['id']; ?>" id="item_<?php echo (int)$item['id']; ?>" />
                <?php if (!empty($item['picture'])): ?>
                <img src="<?php echo htmlspecialchars($item['picture']); ?>" alt="" />
                <?php endif; ?>
                <label for="item_<?php echo (int)$item['id']; ?>">
                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                    <span style="color:#888;"> — ₱<?php echo number_format($item['price'], 2); ?></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('#btnSelectAll').click(function() {
        $('.copy-item-check').prop('checked', true);
    });
    $('#btnSelectNone').click(function() {
        $('.copy-item-check').prop('checked', false);
    });

    function runCopy(copyAll) {
        var itemIds = [];
        if (!copyAll) {
            $('.copy-item-check:checked').each(function() {
                itemIds.push($(this).val());
            });
            if (itemIds.length === 0) {
                alert('Please select at least one item to copy.');
                return;
            }
        }

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                function: 'CopyItems',
                source_business_id: <?php echo (int)$sourceBusinessId; ?>,
                target_business_id: <?php echo (int)$targetBusinessId; ?>,
                copy_all: copyAll ? 1 : 0,
                item_ids: itemIds
            },
            dataType: 'json',
            success: function(data) {
                var panel = $('#copyResult');
                if (data.msg === true) {
                    panel.removeClass('w3-pale-red').addClass('w3-pale-green');
                    panel.html('<strong>Done!</strong> ' + data.summary + ' <a href="fooditems.php?business_id=<?php echo (int)$targetBusinessId; ?>">View items →</a>');
                } else {
                    panel.removeClass('w3-pale-green').addClass('w3-pale-red');
                    panel.html('<strong>Error:</strong> ' + (data.error || 'Copy failed.'));
                }
                panel.show();
            }
        });
    }

    $('#btnCopySelected').click(function() { runCopy(false); });
    $('#btnCopyAll').click(function() {
        if (confirm('Copy all <?php echo count($sourceItems); ?> items to <?php echo htmlspecialchars($targetBusiness ? $targetBusiness['business_name'] : 'this restaurant'); ?>?')) {
            runCopy(true);
        }
    });
});
</script>

<?php include('footer.php'); ?>
