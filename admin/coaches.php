<?php
include('config.php');
include_once(__DIR__ . '/../includes/lesson_helper.php');
fh_auth_require_login();
$adminBusinessId = fh_admin_business_id($conn);

if (isset($_POST['btnSaveCoach'])) {
    $id = (int)$_POST['coach_id'];
    $name = $conn->real_escape_string(trim($_POST['coach_name']));
    $bio = $conn->real_escape_string(trim($_POST['coach_bio']));
    $status = isset($_POST['coach_status']) ? 1 : 0;
    if ($name !== '') {
        if ($id > 0) {
            $conn->query("UPDATE coaches SET coach_name='$name', coach_bio='$bio', coach_status=$status WHERE coach_id=$id AND business_id=$adminBusinessId");
        } else {
            $conn->query("INSERT INTO coaches (business_id, coach_name, coach_bio, coach_status) VALUES ($adminBusinessId, '$name', '$bio', $status)");
        }
    }
    header('Location: coaches.php?business_id=' . $adminBusinessId);
    exit;
}

if (isset($_POST['btnSaveLesson'])) {
    $id = (int)$_POST['lesson_id'];
    $title = $conn->real_escape_string(trim($_POST['lesson_title']));
    $desc = $conn->real_escape_string(trim($_POST['lesson_description']));
    $coachId = (int)$_POST['coach_id'];
    $duration = max(30, (int)$_POST['duration_minutes']);
    $price = (float)$_POST['price'];
    $capacity = max(1, (int)$_POST['capacity']);
    $status = isset($_POST['lesson_status']) ? 1 : 0;
    if ($title !== '') {
        if ($id > 0) {
            $conn->query("UPDATE lessons SET lesson_title='$title', lesson_description='$desc', coach_id=$coachId, duration_minutes=$duration, price=$price, capacity=$capacity, lesson_status=$status WHERE lesson_id=$id AND business_id=$adminBusinessId");
        } else {
            $conn->query("INSERT INTO lessons (business_id, coach_id, lesson_title, lesson_description, duration_minutes, price, capacity, lesson_status) VALUES ($adminBusinessId, $coachId, '$title', '$desc', $duration, $price, $capacity, $status)");
            $newId = (int)$conn->insert_id;
            if ($newId) pb_seed_lesson_week_rules($conn, $newId);
        }
    }
    header('Location: coaches.php?business_id=' . $adminBusinessId);
    exit;
}

$coaches = pb_coaches_for_business($conn, $adminBusinessId, false);
$lessons = pb_lessons_for_business($conn, $adminBusinessId, false);
$titleheader = 'Coaches & Lessons';
include('header.php');
include('navigation.php');
?>
<div class="w3-container admin-page-body">
    <div class="w3-container w3-teal"><h1>Coaches &amp; Lessons</h1><p>Manage instructors and bookable lesson offerings.</p></div>
    <?php echo fh_admin_business_selector($conn, 'coaches.php'); ?>

    <div class="w3-row-padding" style="margin-top:16px;">
        <div class="w3-half">
            <div class="w3-card w3-padding">
                <h2>Add / edit coach</h2>
                <form method="post">
                    <input type="hidden" name="coach_id" id="coach_id" value="0">
                    <label>Name</label><input class="w3-input w3-border" name="coach_name" id="coach_name" required>
                    <label>Bio</label><textarea class="w3-input w3-border" name="coach_bio" id="coach_bio" rows="3"></textarea>
                    <label><input type="checkbox" name="coach_status" id="coach_status" checked> Active</label>
                    <p><button class="w3-button w3-green" name="btnSaveCoach" value="1">Save coach</button></p>
                </form>
                <ul>
                <?php foreach ($coaches as $c): ?>
                    <li>
                        <?php echo htmlspecialchars($c['coach_name']); ?>
                        (<?php echo (int)$c['coach_status'] ? 'active' : 'off'; ?>)
                        <a href="#" onclick='editCoach(<?php echo json_encode($c); ?>);return false;'>Edit</a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="w3-half">
            <div class="w3-card w3-padding">
                <h2>Add / edit lesson</h2>
                <form method="post">
                    <input type="hidden" name="lesson_id" id="lesson_id" value="0">
                    <label>Title</label><input class="w3-input w3-border" name="lesson_title" id="lesson_title" required>
                    <label>Description</label><textarea class="w3-input w3-border" name="lesson_description" id="lesson_description" rows="3"></textarea>
                    <label>Coach</label>
                    <select class="w3-select w3-border" name="coach_id" id="lesson_coach_id">
                        <option value="0">—</option>
                        <?php foreach ($coaches as $c): if (!(int)$c['coach_status']) continue; ?>
                        <option value="<?php echo (int)$c['coach_id']; ?>"><?php echo htmlspecialchars($c['coach_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Duration (min)</label><input class="w3-input w3-border" type="number" name="duration_minutes" id="duration_minutes" value="60">
                    <label>Price</label><input class="w3-input w3-border" type="number" step="0.01" name="price" id="lesson_price" value="500">
                    <label>Capacity</label><input class="w3-input w3-border" type="number" name="capacity" id="capacity" value="4">
                    <label><input type="checkbox" name="lesson_status" id="lesson_status" checked> Active</label>
                    <p><button class="w3-button w3-green" name="btnSaveLesson" value="1">Save lesson</button></p>
                </form>
                <ul>
                <?php foreach ($lessons as $l): ?>
                    <li>
                        <?php echo htmlspecialchars($l['lesson_title']); ?> — ₱<?php echo number_format($l['price'], 2); ?>
                        <a href="#" onclick='editLesson(<?php echo json_encode($l); ?>);return false;'>Edit</a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<script>
function editCoach(c){
  document.getElementById('coach_id').value=c.coach_id;
  document.getElementById('coach_name').value=c.coach_name||'';
  document.getElementById('coach_bio').value=c.coach_bio||'';
  document.getElementById('coach_status').checked=String(c.coach_status)==='1';
}
function editLesson(l){
  document.getElementById('lesson_id').value=l.lesson_id;
  document.getElementById('lesson_title').value=l.lesson_title||'';
  document.getElementById('lesson_description').value=l.lesson_description||'';
  document.getElementById('lesson_coach_id').value=l.coach_id||0;
  document.getElementById('duration_minutes').value=l.duration_minutes||60;
  document.getElementById('lesson_price').value=l.price||0;
  document.getElementById('capacity').value=l.capacity||4;
  document.getElementById('lesson_status').checked=String(l.lesson_status)==='1';
}
</script>
<?php include('footer.php'); ?>
