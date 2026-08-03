<?php
include('config.php');
include_once(__DIR__ . '/../includes/court_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);
$courts = pb_courts_for_business($conn, $adminBusinessId, false);
$courtId = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;
if ($courtId <= 0 && !empty($courts)) {
    $courtId = (int)$courts[0]['court_id'];
}
$court = $courtId > 0 ? pb_court_fetch($conn, $courtId) : null;
if ($court && (int)$court['business_id'] !== (int)$adminBusinessId) {
    $court = null;
    $courtId = 0;
}

function admin_avail_redirect($courtId) {
    global $adminBusinessId;
    header('Location: availability.php?business_id=' . (int)$adminBusinessId . '&court_id=' . (int)$courtId);
    exit;
}

if (isset($_POST['btnSaveRules']) && $court) {
    $conn->query('DELETE FROM court_availability_rules WHERE court_id=' . (int)$courtId);
    $weekdays = isset($_POST['weekday']) && is_array($_POST['weekday']) ? $_POST['weekday'] : array();
    $starts = isset($_POST['start_time']) && is_array($_POST['start_time']) ? $_POST['start_time'] : array();
    $ends = isset($_POST['end_time']) && is_array($_POST['end_time']) ? $_POST['end_time'] : array();
    foreach ($weekdays as $i => $wd) {
        if (empty($starts[$i]) || empty($ends[$i])) {
            continue;
        }
        $wd = (int)$wd;
        if ($wd < 0 || $wd > 6) {
            continue;
        }
        $st = $conn->real_escape_string($starts[$i]);
        $et = $conn->real_escape_string($ends[$i]);
        if (pb_time_to_minutes($et) <= pb_time_to_minutes($st)) {
            continue;
        }
        $conn->query(
            "INSERT INTO court_availability_rules (court_id, weekday, start_time, end_time)
             VALUES ($courtId, $wd, '$st', '$et')"
        );
    }
    admin_avail_redirect($courtId);
}

if (isset($_POST['btnAddException']) && $court) {
    $date = isset($_POST['exception_date']) ? trim($_POST['exception_date']) : '';
    $isClosed = isset($_POST['is_closed']) ? 1 : 0;
    $st = isset($_POST['ex_start_time']) ? trim($_POST['ex_start_time']) : '';
    $et = isset($_POST['ex_end_time']) ? trim($_POST['ex_end_time']) : '';
    $note = isset($_POST['exception_note']) ? trim($_POST['exception_note']) : '';
    if ($date !== '' && strtotime($date) !== false) {
        $safeDate = $conn->real_escape_string($date);
        $safeNote = $conn->real_escape_string($note);
        $safeSt = $st !== '' ? "'" . $conn->real_escape_string($st) . "'" : 'NULL';
        $safeEt = $et !== '' ? "'" . $conn->real_escape_string($et) . "'" : 'NULL';
        $conn->query(
            "INSERT INTO availability_exceptions
             (court_id, exception_date, start_time, end_time, is_closed, exception_note)
             VALUES ($courtId, '$safeDate', $safeSt, $safeEt, $isClosed, '$safeNote')"
        );
    }
    admin_avail_redirect($courtId);
}

if (isset($_GET['delete_exception']) && $court) {
    $exId = (int)$_GET['delete_exception'];
    $conn->query(
        "DELETE FROM availability_exceptions
         WHERE exception_id=$exId AND court_id=" . (int)$courtId
    );
    admin_avail_redirect($courtId);
}

$rules = $court ? pb_rules_for_court($conn, $courtId) : array();
$rulesByDay = array();
foreach ($rules as $r) {
    $rulesByDay[(int)$r['weekday']][] = $r;
}
$exceptions = array();
if ($court) {
    $exSql = $conn->query(
        "SELECT * FROM availability_exceptions
         WHERE court_id=" . (int)$courtId . "
         ORDER BY exception_date DESC
         LIMIT 60"
    );
    if ($exSql) {
        while ($row = $exSql->fetch_assoc()) {
            $exceptions[] = $row;
        }
    }
}

$weekdays = pb_weekday_labels();
$titleheader = 'Availability';
include('header.php');
include('navigation.php');
?>

<style>
.avail-card { background:#fff; border-radius:12px; padding:18px; margin:0 0 18px; box-shadow:0 2px 12px rgba(15,23,42,.06); }
.avail-card h2 { margin:0 0 12px; font-size:18px; }
.avail-row { display:grid; grid-template-columns:140px 1fr 1fr auto; gap:10px; align-items:center; margin:0 0 8px; }
.avail-row input, .avail-row select, .avail-card input, .avail-card select { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; }
.avail-btn { padding:9px 16px; border:none; border-radius:8px; background:#2d6a4f; color:#fff; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
.avail-btn.is-secondary { background:#fff; color:#0f172a; border:1px solid #cbd5e1; }
.avail-btn.is-danger { background:#c1121f; }
.avail-table { width:100%; border-collapse:collapse; }
.avail-table th, .avail-table td { padding:10px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:13px; }
@media (max-width:700px){ .avail-row { grid-template-columns:1fr; } }
</style>

<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal">
        <h1>Court Availability</h1>
        <p>Set weekly open hours and special closures for each court.</p>
        <div style="margin-top:10px;">
            <a class="avail-btn is-secondary" href="courts.php?business_id=<?php echo (int)$adminBusinessId; ?>">← Courts</a>
        </div>
    </div>

    <?php echo fh_admin_business_selector($conn, 'availability.php'); ?>

    <?php if (empty($courts)): ?>
        <div class="avail-card">No courts yet. <a href="courts.php?business_id=<?php echo (int)$adminBusinessId; ?>">Add a court</a> first.</div>
    <?php else: ?>
        <form class="avail-card" method="get" action="availability.php">
            <input type="hidden" name="business_id" value="<?php echo (int)$adminBusinessId; ?>">
            <label for="court_id"><b>Court</b></label>
            <select name="court_id" id="court_id" onchange="this.form.submit()" style="margin-top:8px; min-width:240px;">
                <?php foreach ($courts as $c): ?>
                <option value="<?php echo (int)$c['court_id']; ?>" <?php echo (int)$c['court_id'] === $courtId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['court_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($court): ?>
        <form class="avail-card" method="post" action="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>&court_id=<?php echo (int)$courtId; ?>">
            <h2>Weekly hours — <?php echo htmlspecialchars($court['court_name']); ?></h2>
            <p style="color:#64748b;font-size:13px;">Leave a day blank to mark it closed. Saving replaces all weekly rules for this court.</p>
            <?php foreach ($weekdays as $wd => $label):
                $rule = isset($rulesByDay[$wd][0]) ? $rulesByDay[$wd][0] : null;
            ?>
            <div class="avail-row">
                <strong><?php echo htmlspecialchars($label); ?></strong>
                <input type="hidden" name="weekday[]" value="<?php echo (int)$wd; ?>">
                <input type="time" name="start_time[]" value="<?php echo $rule ? substr($rule['start_time'], 0, 5) : ''; ?>" placeholder="Open">
                <input type="time" name="end_time[]" value="<?php echo $rule ? substr($rule['end_time'], 0, 5) : ''; ?>" placeholder="Close">
                <span style="font-size:12px;color:#64748b;">open → close</span>
            </div>
            <?php endforeach; ?>
            <button type="submit" name="btnSaveRules" value="1" class="avail-btn" style="margin-top:12px;">Save weekly hours</button>
        </form>

        <div class="avail-card">
            <h2>Special dates / closures</h2>
            <form method="post" action="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>&court_id=<?php echo (int)$courtId; ?>" style="display:grid; gap:10px; max-width:520px;">
                <label>Date <input type="date" name="exception_date" required></label>
                <label><input type="checkbox" name="is_closed" value="1" checked> Closed (all day if times blank; or closed only during times)</label>
                <label>Start time (optional) <input type="time" name="ex_start_time"></label>
                <label>End time (optional) <input type="time" name="ex_end_time"></label>
                <label>Note <input type="text" name="exception_note" placeholder="Holiday, maintenance…"></label>
                <button type="submit" name="btnAddException" value="1" class="avail-btn">Add exception</button>
            </form>

            <table class="avail-table" style="margin-top:18px;">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Hours</th><th>Note</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($exceptions)): ?>
                    <tr><td colspan="5">No exceptions yet.</td></tr>
                <?php else: foreach ($exceptions as $ex): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ex['exception_date']); ?></td>
                        <td><?php echo (int)$ex['is_closed'] ? 'Closed' : 'Special hours'; ?></td>
                        <td>
                            <?php
                            if (empty($ex['start_time']) && empty($ex['end_time'])) {
                                echo 'All day';
                            } else {
                                echo htmlspecialchars(substr((string)$ex['start_time'], 0, 5) . ' – ' . substr((string)$ex['end_time'], 0, 5));
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$ex['exception_note']); ?></td>
                        <td>
                            <a class="avail-btn is-danger" href="availability.php?business_id=<?php echo (int)$adminBusinessId; ?>&court_id=<?php echo (int)$courtId; ?>&delete_exception=<?php echo (int)$ex['exception_id']; ?>" onclick="return confirm('Delete this exception?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include('footer.php'); ?>
