<?php
include('config.php');
include_once(__DIR__ . '/../includes/court_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

function admin_courts_redirect() {
    global $adminBusinessId;
    header('Location: courts.php?business_id=' . (int)$adminBusinessId);
    exit;
}

if (isset($_POST['btnSave'])) {
    $courtId = isset($_POST['court_id']) ? (int)$_POST['court_id'] : 0;
    $name = trim(isset($_POST['court_name']) ? $_POST['court_name'] : '');
    $type = isset($_POST['court_type']) ? $_POST['court_type'] : 'outdoor';
    $surface = isset($_POST['court_surface']) ? $_POST['court_surface'] : 'hard';
    $capacity = max(1, (int)(isset($_POST['court_capacity']) ? $_POST['court_capacity'] : 4));
    $price = (float)(isset($_POST['price_per_slot']) ? $_POST['price_per_slot'] : 0);
    $slotLen = max(15, (int)(isset($_POST['slot_length_minutes']) ? $_POST['slot_length_minutes'] : 60));
    $buffer = max(0, (int)(isset($_POST['buffer_minutes']) ? $_POST['buffer_minutes'] : 0));
    $status = isset($_POST['court_status']) ? 1 : 0;
    $businessId = $adminBusinessId;

    if ($name === '') {
        admin_courts_redirect();
    }

    $types = pb_court_types();
    $surfaces = pb_court_surfaces();
    if (!isset($types[$type])) {
        $type = 'outdoor';
    }
    if (!isset($surfaces[$surface])) {
        $surface = 'hard';
    }

    $photo = '';
    if ($courtId > 0) {
        $existing = pb_court_fetch($conn, $courtId);
        if (!$existing || (int)$existing['business_id'] !== $businessId) {
            admin_courts_redirect();
        }
        $photo = isset($existing['court_photo']) ? $existing['court_photo'] : '';
    }

    if (!empty($_FILES['court_photo']['name'])) {
        $targetDir = 'images/';
        $targetFile = $targetDir . basename($_FILES['court_photo']['name']);
        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true)) {
            if (move_uploaded_file($_FILES['court_photo']['tmp_name'], $targetFile)) {
                $photo = $targetFile;
            }
        }
    }

    $safeName = $conn->real_escape_string($name);
    $safeType = $conn->real_escape_string($type);
    $safeSurface = $conn->real_escape_string($surface);
    $safePhoto = $conn->real_escape_string($photo);

    if ($courtId > 0) {
        $conn->query(
            "UPDATE courts SET
                court_name='$safeName',
                court_type='$safeType',
                court_surface='$safeSurface',
                court_capacity=$capacity,
                court_photo='$safePhoto',
                price_per_slot=$price,
                slot_length_minutes=$slotLen,
                buffer_minutes=$buffer,
                court_status=$status
             WHERE court_id=$courtId AND business_id=$businessId"
        );
    } else {
        $conn->query(
            "INSERT INTO courts
             (business_id, court_name, court_type, court_surface, court_capacity, court_photo,
              price_per_slot, slot_length_minutes, buffer_minutes, court_status)
             VALUES
             ($businessId, '$safeName', '$safeType', '$safeSurface', $capacity, '$safePhoto',
              $price, $slotLen, $buffer, $status)"
        );
        $newId = (int)$conn->insert_id;
        if ($newId > 0) {
            pb_seed_default_week_rules($conn, $newId);
        }
    }
    admin_courts_redirect();
}

$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = 'WHERE business_id=' . (int)$adminBusinessId;
if ($searchQ !== '') {
    $safe = $conn->real_escape_string($searchQ);
    $where .= " AND court_name LIKE '%$safe%'";
}
$courts = array();
$sql = $conn->query("SELECT * FROM courts $where ORDER BY court_name ASC");
if ($sql) {
    while ($row = $sql->fetch_assoc()) {
        $courts[] = $row;
    }
}

$types = pb_court_types();
$surfaces = pb_court_surfaces();
$titleheader = 'Courts';
include('header.php');
include('navigation.php');
?>

<link rel="stylesheet" href="../css/portal-order.css?v=20260803" type="text/css" />
<style>
.admin-courts-filter { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; padding:14px 16px; margin:0 0 18px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(15,23,42,.06); }
.admin-courts-filter label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin:0 0 6px; }
.admin-courts-filter input { padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; min-width:200px; }
.admin-courts-filter button, .admin-courts-btn { padding:9px 16px; border:none; border-radius:8px; background:#2d6a4f; color:#fff; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
.admin-courts-btn.is-secondary { background:#fff; color:#0f172a; border:1px solid #cbd5e1; }
.admin-courts-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(15,23,42,.06); }
.admin-courts-table th, .admin-courts-table td { padding:12px 14px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; }
.admin-courts-table th { background:#f8fafc; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
.admin-court-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1000; align-items:center; justify-content:center; padding:20px; }
.admin-court-modal.is-open { display:flex; }
.admin-court-dialog { background:#fff; border-radius:14px; width:min(560px,100%); max-height:90vh; overflow:auto; padding:22px; }
.admin-court-dialog label { display:block; font-weight:700; font-size:13px; margin:10px 0 6px; }
.admin-court-dialog input, .admin-court-dialog select { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; box-sizing:border-box; }
.admin-court-actions { display:flex; gap:8px; flex-wrap:wrap; }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Courts</h1>
        <p>Add and manage pickleball courts for this venue — pricing, slot length, and photos.</p>
        <div style="margin-top:10px;">
            <button type="button" class="admin-courts-btn" onclick="openCourtModal()">+ Add Court</button>
            <a class="admin-courts-btn is-secondary" href="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>">Availability</a>
        </div>
    </div>

    <?php echo fh_admin_business_selector($conn, 'courts.php'); ?>

    <form class="admin-courts-filter" method="get" action="courts.php">
        <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
        <div>
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Court name">
        </div>
        <button type="submit">Filter</button>
    </form>

    <table class="admin-courts-table">
        <thead>
            <tr>
                <th>Court</th>
                <th>Type</th>
                <th>Surface</th>
                <th>Slot</th>
                <th>Price</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($courts)): ?>
            <tr><td colspan="7">No courts yet. Add your first court to start taking bookings.</td></tr>
        <?php else: foreach ($courts as $c): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($c['court_name']); ?></strong></td>
                <td><?php echo htmlspecialchars(ucfirst($c['court_type'])); ?></td>
                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $c['court_surface']))); ?></td>
                <td><?php echo (int)$c['slot_length_minutes']; ?> min<?php if ((int)$c['buffer_minutes'] > 0) echo ' (+' . (int)$c['buffer_minutes'] . ' buffer)'; ?></td>
                <td>₱<?php echo number_format((float)$c['price_per_slot'], 2); ?></td>
                <td><?php echo (int)$c['court_status'] === 1 ? 'Active' : 'Inactive'; ?></td>
                <td class="admin-court-actions">
                    <button type="button" class="admin-courts-btn is-secondary" onclick='editCourt(<?php echo json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>Edit</button>
                    <a class="admin-courts-btn is-secondary" href="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>&court_id=<?php echo (int)$c['court_id']; ?>">Hours</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="admin-court-modal" id="courtModal" role="dialog" aria-modal="true">
    <div class="admin-court-dialog">
        <h2 id="courtModalTitle">Add Court</h2>
        <form method="post" enctype="multipart/form-data" action="courts.php?business_id=<?php echo (int)$adminBusinessId; ?>">
            <input type="hidden" name="court_id" id="court_id" value="0">
            <label for="court_name">Court name</label>
            <input type="text" name="court_name" id="court_name" required>

            <label for="court_type">Type</label>
            <select name="court_type" id="court_type">
                <?php foreach ($types as $k => $label): ?>
                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="court_surface">Surface</label>
            <select name="court_surface" id="court_surface">
                <?php foreach ($surfaces as $k => $label): ?>
                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="court_capacity">Capacity (players)</label>
            <input type="number" name="court_capacity" id="court_capacity" min="1" value="4">

            <label for="price_per_slot">Price per slot (₱)</label>
            <input type="number" step="0.01" min="0" name="price_per_slot" id="price_per_slot" value="300">

            <label for="slot_length_minutes">Slot length (minutes)</label>
            <input type="number" name="slot_length_minutes" id="slot_length_minutes" min="15" step="15" value="60">

            <label for="buffer_minutes">Buffer between bookings (minutes)</label>
            <input type="number" name="buffer_minutes" id="buffer_minutes" min="0" value="0">

            <label for="court_photo">Photo</label>
            <input type="file" name="court_photo" id="court_photo" accept="image/*">

            <label><input type="checkbox" name="court_status" id="court_status" checked> Active (bookable)</label>

            <div style="margin-top:16px; display:flex; gap:10px;">
                <button type="submit" name="btnSave" value="1" class="admin-courts-btn">Save</button>
                <button type="button" class="admin-courts-btn is-secondary" onclick="closeCourtModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCourtModal() {
    document.getElementById('courtModalTitle').textContent = 'Add Court';
    document.getElementById('court_id').value = '0';
    document.getElementById('court_name').value = '';
    document.getElementById('court_type').value = 'outdoor';
    document.getElementById('court_surface').value = 'hard';
    document.getElementById('court_capacity').value = '4';
    document.getElementById('price_per_slot').value = '300';
    document.getElementById('slot_length_minutes').value = '60';
    document.getElementById('buffer_minutes').value = '0';
    document.getElementById('court_status').checked = true;
    document.getElementById('courtModal').classList.add('is-open');
}
function editCourt(c) {
    document.getElementById('courtModalTitle').textContent = 'Edit Court';
    document.getElementById('court_id').value = c.court_id;
    document.getElementById('court_name').value = c.court_name || '';
    document.getElementById('court_type').value = c.court_type || 'outdoor';
    document.getElementById('court_surface').value = c.court_surface || 'hard';
    document.getElementById('court_capacity').value = c.court_capacity || 4;
    document.getElementById('price_per_slot').value = c.price_per_slot || 0;
    document.getElementById('slot_length_minutes').value = c.slot_length_minutes || 60;
    document.getElementById('buffer_minutes').value = c.buffer_minutes || 0;
    document.getElementById('court_status').checked = String(c.court_status) === '1';
    document.getElementById('courtModal').classList.add('is-open');
}
function closeCourtModal() {
    document.getElementById('courtModal').classList.remove('is-open');
}
document.getElementById('courtModal').addEventListener('click', function(e) {
    if (e.target === this) closeCourtModal();
});
</script>

<?php include('footer.php'); ?>
