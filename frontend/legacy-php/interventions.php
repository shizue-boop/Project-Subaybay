<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_session'])) {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $related_endorsement_id = (int) ($_POST['related_endorsement_id'] ?? 0);
    $required = ['session_date', 'start_time', 'end_time', 'focus_area', 'session_type'];
    $valid = $student_id > 0;
    foreach ($required as $field) $valid = $valid && trim($_POST[$field] ?? '') !== '';
    $studentCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
    $studentCheck->execute([$student_id, $teacher_id]);
    if (!$studentCheck->fetch()) $valid = false;
    if (!$valid) {
        $_SESSION['error'] = 'Complete all session fields and select an assigned pupil.';
    } else {
        if ($related_endorsement_id > 0) {
            $checkEndorsement = $pdo->prepare("SELECT id FROM endorsements WHERE id = ? AND student_id = ? AND status = 'Approved'");
            $checkEndorsement->execute([$related_endorsement_id, $student_id]);
            if (!$checkEndorsement->fetchColumn()) {
                $_SESSION['error'] = 'Choose an approved endorsement belonging to the selected pupil.';
                header('Location: interventions.php');
                exit;
            }
        } else {
            $related_endorsement_id = null;
        }
        $stmt = $pdo->prepare("INSERT INTO remedial_sessions (student_id, related_endorsement_id, teacher_id, recorded_by, focus_area, session_type, status, session_date, start_time, end_time, notes) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?, ?)");
        $stmt->execute([$student_id, $related_endorsement_id, $teacher_id, $teacher_id, trim($_POST['focus_area']), $_POST['session_type'], $_POST['session_date'], $_POST['start_time'], $_POST['end_time'], trim($_POST['remarks'] ?? '')]);
        $_SESSION['success'] = 'Remedial session record saved.';
    }
    header('Location: interventions.php');
    exit;
}

$sessions = $pdo->prepare("
    SELECT rs.*, s.full_name, s.grade_level, s.section, recorder.full_name AS recorded_by_name
    FROM remedial_sessions rs
    JOIN students s ON rs.student_id = s.id
    LEFT JOIN users recorder ON recorder.id = rs.recorded_by
    WHERE rs.teacher_id = ?
    ORDER BY rs.session_date DESC
");
$sessions->execute([$teacher_id]);
$mySessions = $sessions->fetchAll();
$myPupils = $pdo->prepare("SELECT id, full_name, grade_level, section, reading_profile FROM students WHERE teacher_id = ? ORDER BY full_name");
$myPupils->execute([$teacher_id]);
$myPupils = $myPupils->fetchAll();
$approvedEndorsements = $pdo->prepare("SELECT e.id, e.student_id, e.assessment_tool, e.reading_level FROM endorsements e JOIN students s ON s.id = e.student_id WHERE e.status = 'Approved' AND s.teacher_id = ? ORDER BY e.id DESC");
$approvedEndorsements->execute([$teacher_id]);
$approvedEndorsements = $approvedEndorsements->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Interventions</div>
<div class="page-subtitle">Remedial pupils, attendance, and session records</div>

<div class="search-filter-bar">
    <a href="#remedialPupils" class="btn btn-secondary">My Remedial Pupils</a>
    <a href="remedial_attendance.php" class="btn btn-secondary">Attendance</a>
    <a href="#sessionRecords" class="btn btn-secondary">Session Records</a>
    <button class="btn btn-primary" onclick="openModal('addInterventionModal')"><i class="fa-solid fa-plus"></i> Add Session Record</button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div id="remedialPupils" class="card" style="margin-bottom:24px;"><div class="card-header"><div class="card-title">My Remedial Pupils</div><span class="record-count"><?php echo count($myPupils); ?> pupils</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Pupil</th><th>Grade / Section</th><th>Reading Level</th><th>Profile</th></tr></thead><tbody><?php foreach ($myPupils as $pupil): ?><tr><td><a href="pupil_profile.php?id=<?php echo $pupil['id']; ?>"><strong><?php echo htmlspecialchars($pupil['full_name']); ?></strong></a></td><td><?php echo htmlspecialchars($pupil['grade_level'] . ' - ' . $pupil['section']); ?></td><td><?php echo htmlspecialchars($pupil['reading_profile'] ?? 'Not assessed'); ?></td><td><a href="pupil_profile.php?id=<?php echo $pupil['id']; ?>">Pupil Profile</a></td></tr><?php endforeach; ?><?php if (!$myPupils): ?><tr><td colspan="4" style="padding:30px;color:#94A3B8;text-align:center;">No assigned pupils.</td></tr><?php endif; ?></tbody></table></div></div>

<div id="sessionRecords" class="card">
    <div class="card-header"><div class="card-title">Session Records</div><span class="record-count"><?php echo count($mySessions); ?> sessions</span></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Session Date</th><th>Pupil</th><th>Focus</th><th>Type</th><th>Status</th><th>Start Time</th><th>End Time</th><th>Remarks</th><th>Recorded By</th></tr></thead>
            <tbody>
                <?php foreach ($mySessions as $sess): 
                    $statusClass = match($sess['status']) {
                        'Completed' => 'badge-green',
                        'In Progress' => 'badge-yellow',
                        default => 'badge-blue'
                    };
                ?>
                <tr>
                    <td><?php echo date('M d, Y',strtotime($sess['session_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($sess['full_name']); ?></strong></td>
                    <td><?php echo $sess['focus_area']; ?></td>
                    <td><span class="badge-status badge-purple"><?php echo htmlspecialchars($sess['session_type']); ?></span></td>
                    <td><span class="badge-status <?php echo $statusClass; ?>"><?php echo $sess['status']; ?></span></td>
                    <td><?php echo date('g:i A',strtotime($sess['start_time'])); ?></td><td><?php echo $sess['end_time'] ? date('g:i A',strtotime($sess['end_time'])) : '-'; ?></td><td><?php echo htmlspecialchars($sess['notes'] ?? '-'); ?></td><td><?php echo htmlspecialchars($sess['recorded_by_name'] ?? 'Not recorded'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addInterventionModal" class="modal">
    <div class="modal-header"><div class="modal-title">Add Session Record</div><button class="modal-close" onclick="closeModal('addInterventionModal')">&times;</button></div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Pupil</label><select class="form-select" name="student_id" required><option value="">Select pupil</option><?php foreach ($myPupils as $pupil): ?><option value="<?php echo $pupil['id']; ?>"><?php echo htmlspecialchars($pupil['full_name'] . ' - ' . $pupil['grade_level'] . ' ' . $pupil['section']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Related Approved Endorsement or Intervention (optional)</label><select class="form-select" name="related_endorsement_id" id="teacherRelatedEndorsement"><option value="">No related record</option><?php foreach ($approvedEndorsements as $endorsement): ?><option value="<?php echo (int) $endorsement['id']; ?>" data-student-id="<?php echo (int) $endorsement['student_id']; ?>"><?php echo htmlspecialchars('Endorsement #' . $endorsement['id'] . ' - ' . ($endorsement['assessment_tool'] ?: 'Reading support') . ' - ' . ($endorsement['reading_level'] ?: 'Approved')); ?></option><?php endforeach; ?></select><small class="form-help">Only approved endorsements for the selected pupil are available.</small></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Reading Support / Intervention Provided</label><input type="text" class="form-input" name="focus_area" required></div>
                <div class="form-group"><label class="form-label">Session Type</label>
                    <select class="form-select" name="session_type" required><option>Phonics Drill</option><option>Fluency Practice</option><option>Guided Reading</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Session Date</label><input type="date" class="form-input" name="session_date" required></div>
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-input" name="start_time" required></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-input" name="end_time" required></div>
            </div>
            <div class="form-group"><label class="form-label">Remarks</label><textarea class="form-input" name="remarks" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Recorded By</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Current user'); ?>" readonly></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addInterventionModal')">Cancel</button>
            <button type="submit" name="schedule_session" class="btn btn-primary">Save Session Record</button>
        </div>
    </form>
</div>

<script>
(() => {
    const pupil = document.querySelector('#addInterventionModal select[name="student_id"]');
    const related = document.getElementById('teacherRelatedEndorsement');
    if (!pupil || !related) return;
    const filterEndorsements = () => {
        related.value = '';
        for (const option of related.options) {
            option.disabled = Boolean(option.dataset.studentId && option.dataset.studentId !== pupil.value);
            option.hidden = option.disabled;
        }
    };
    pupil.addEventListener('change', filterEndorsements);
    filterEndorsements();
})();
</script>

<?php include 'footer.php'; ?>
