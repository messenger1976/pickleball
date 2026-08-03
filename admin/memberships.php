<?php
include('config.php');
include_once(__DIR__ . '/../includes/membership_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

if (isset($_POST['btnSavePlan'])) {
    $id = (int)$_POST['plan_id'];
    $name = $conn->real_escape_string(trim($_POST['plan_name']));
    $desc = $conn->real_escape_string(trim($_POST['plan_description']));
    $price = (float)$_POST['price'];
    $period = $_POST['billing_period'] === 'yearly' ? 'yearly' : 'monthly';
    $discount = (float)$_POST['discount_percent'];
    $status = isset($_POST['plan_status']) ? 1 : 0;
    if ($name !== '') {
        if ($id > 0) {
            $conn->query("UPDATE membership_plans SET plan_name='$name', plan_description='$desc', price=$price, billing_period='$period', discount_percent=$discount, plan_status=$status WHERE plan_id=$id AND business_id=$adminBusinessId");
        } else {
            $conn->query("INSERT INTO membership_plans (business_id, plan_name, plan_description, price, billing_period, discount_percent, plan_status) VALUES ($adminBusinessId, '$name', '$desc', $price, '$period', $discount, $status)");
        }
    }
    header('Location: memberships.php?business_id=' . $adminBusinessId);
    exit;
}

$plans = pb_plans_for_business($conn, $adminBusinessId, false);
$members = array();
$mSql = $conn->query(
    "SELECT m.*, p.plan_name, c.customers_firstname, c.customers_lastname, c.customers_email
     FROM memberships m
     INNER JOIN membership_plans p ON p.plan_id=m.plan_id
     LEFT JOIN customers c ON c.customers_id=m.customers_id
     WHERE m.business_id=$adminBusinessId
     ORDER BY m.created_at DESC LIMIT 100"
);
if ($mSql) {
    while ($row = $mSql->fetch_assoc()) {
        $members[] = $row;
    }
}

$titleheader = 'Memberships';
include('header.php');
include('navigation.php');
?>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal"><h1>Membership Plans</h1></div>
    <?php echo fh_admin_business_selector($conn, 'memberships.php'); ?>

    <div class="w3-card w3-padding" style="margin-top:16px;max-width:560px;">
        <h2>Plan</h2>
        <form method="post">
            <input type="hidden" name="plan_id" id="plan_id" value="0">
            <label>Name</label><input class="w3-input w3-border" name="plan_name" id="plan_name" required>
            <label>Description</label><textarea class="w3-input w3-border" name="plan_description" id="plan_description" rows="3"></textarea>
            <label>Price</label><input class="w3-input w3-border" type="number" step="0.01" name="price" id="plan_price" value="999">
            <label>Billing</label>
            <select class="w3-select w3-border" name="billing_period" id="billing_period">
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
            <label>Booking discount %</label><input class="w3-input w3-border" type="number" step="0.01" name="discount_percent" id="discount_percent" value="10">
            <label><input type="checkbox" name="plan_status" id="plan_status" checked> Active</label>
            <p><button class="w3-button w3-green" name="btnSavePlan" value="1">Save plan</button></p>
        </form>
        <ul>
        <?php foreach ($plans as $p): ?>
            <li><?php echo htmlspecialchars($p['plan_name']); ?> — ₱<?php echo number_format($p['price'],2); ?>/<?php echo htmlspecialchars($p['billing_period']); ?>
            <a href="#" onclick='editPlan(<?php echo json_encode($p); ?>);return false;'>Edit</a></li>
        <?php endforeach; ?>
        </ul>
    </div>

    <div class="w3-card w3-padding" style="margin-top:16px;">
        <h2>Subscribers</h2>
        <table class="w3-table w3-bordered">
            <tr><th>Member</th><th>Plan</th><th>Status</th><th>Period</th></tr>
            <?php if (empty($members)): ?>
            <tr><td colspan="4">No subscribers yet.</td></tr>
            <?php else: foreach ($members as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars(trim($m['customers_firstname'].' '.$m['customers_lastname'])); ?><br><small><?php echo htmlspecialchars($m['customers_email']); ?></small></td>
                <td><?php echo htmlspecialchars($m['plan_name']); ?></td>
                <td><?php echo htmlspecialchars($m['membership_status']); ?></td>
                <td><?php echo htmlspecialchars($m['current_period_start'].' → '.$m['current_period_end']); ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
<script>
function editPlan(p){
  document.getElementById('plan_id').value=p.plan_id;
  document.getElementById('plan_name').value=p.plan_name||'';
  document.getElementById('plan_description').value=p.plan_description||'';
  document.getElementById('plan_price').value=p.price||0;
  document.getElementById('billing_period').value=p.billing_period||'monthly';
  document.getElementById('discount_percent').value=p.discount_percent||0;
  document.getElementById('plan_status').checked=String(p.plan_status)==='1';
}
</script>
<?php include('footer.php'); ?>
