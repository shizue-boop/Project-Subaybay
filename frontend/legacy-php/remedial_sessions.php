<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_session'])) {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $teacher_id = (int) ($_POST['teacher_id'] ?? 0);
    $required = ['session_date', 'start_time', 'end_time', 'focus_area', 'session_type'];
    $valid = $student_id > 0 && $teacher_id > 0;
    foreach ($required as $field) $valid = $valid && trim($_POST[$field] ?? '') !== '';
    if (!$valid) {
        $_SESSION['error'] = 'Complete all session fields before saving.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO remedial_sessions (student_id, teacher_id, focus_area, session_type, status, session_date, start_time, end_time, notes) VALUES (?, ?, ?, ?, 'Scheduled', ?, ?, ?, ?)");
        $stmt->execute([$student_id, $teacher_id, trim($_POST['focus_area']), $_POST['session_type'], $_POST['session_date'], $_POST['start_time'], $_POST['end_time'], trim($_POST['remarks'] ?? '')]);
        $_SESSION['success'] = 'Remedial session scheduled.';
    }
    header('Location: remedial_sessions.php');
    exit;
}

$scheduled = $pdo->query("SELECT COUNT(*) FROM remedial_sessions WHERE status='Scheduled'")->fetchColumn();
$inProgress = $pdo->query("SELECT COUNT(*) FROM remedial_sessions WHERE status='In Progress'")->fetchColumn();
$completed = $pdo->query("SELECT COUNT(*) FROM remedial_sessions WHERE status='Completed'")->fetchColumn();

$sessions = $pdo->query("
    SELECT rs.*, s.full_name, s.grade_level, s.section, u.full_name as teacher_name
    FROM remedial_sessions rs
    JOIN students s ON rs.student_id = s.id
    LEFT JOIN users u ON rs.teacher_id = u.id
    ORDER BY rs.session_date DESC
")->fetchAll();

$remedialPupils = $pdo->query("SELECT s.id, s.full_name, s.grade_level, s.section, s.reading_profile, MAX(rs.session_date) AS latest_session, u.full_name AS teacher_name FROM students s JOIN remedial_sessions rs ON rs.student_id = s.id LEFT JOIN users u ON rs.teacher_id = u.id WHERE rs.status IN ('Scheduled', 'In Progress') GROUP BY s.id ORDER BY s.grade_level, s.full_name")->fetchAll();
$pupils = $pdo->query("SELECT id, full_name, grade_level, section FROM students ORDER BY full_name")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name, grade_level, section FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Remedial Sessions</div>
<div class="page-subtitle">Monitor remedial pupils and their session records.</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><div class="card-title">Remedial Pupils</div><span class="record-count"><?php echo count($remedialPupils); ?> pupils</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Pupil</th><th>Grade / Section</th><th>Reading Level</th><th>Assigned Teacher</th><th>Latest Session</th><th>Profile</th></tr></thead><tbody>
    <?php foreach ($remedialPupils as $pupil): ?><tr><td><strong><?php echo htmlspecialchars($pupil['full_name']); ?></strong></td><td><?php echo htmlspecialchars($pupil['grade_level'] . ' - ' . $pupil['section']); ?></td><td><?php echo htmlspecialchars($pupil['reading_profile'] ?? 'Not assessed'); ?></td><td><?php echo htmlspecialchars($pupil['teacher_name'] ?? 'Unassigned'); ?></td><td><?php echo $pupil['latest_session'] ? date('M d, Y', strtotime($pupil['latest_session'])) : '-'; ?></td><td><a href="pupil_profile.php?id=<?php echo $pupil['id']; ?>">View Profile</a></td></tr><?php endforeach; ?><?php if (!$remedialPupils): ?><tr><td colspan="6" style="padding:30px;color:#94A3B8;text-align:center;">No pupils currently under remedial reading.</td></tr><?php endif; ?></tbody></table></div>
</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <button class="btn btn-primary" onclick="openModal('newSessionModal')"><i class="fa-solid fa-plus"></i> New Session</button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-info" style="text-align:center;width:100%;"><h3><?php echo $scheduled; ?></h3><p>Scheduled</p></div></div>
    <div class="stat-card"><div class="stat-info" style="text-align:center;width:100%;"><h3><?php echo $inProgress; ?></h3><p>In Progress</p></div></div>
    <div class="stat-card"><div class="stat-info" style="text-align:center;width:100%;"><h3><?php echo $completed; ?></h3><p>Completed</p></div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
    <?php foreach ($sessions as $sess): 
        $statusClass = match($sess['status']) {
            'Scheduled' => 'scheduled',
            'In Progress' => 'in-progress',
            'Completed' => 'completed',
            default => 'scheduled'
        };
        $profileClass = match(true) {
            str_contains($sess['focus_area'],'Phonemic') => 'badge-red',
            str_contains($sess['focus_area'],'Fluency') => 'badge-yellow',
            str_contains($sess['focus_area'],'Comprehension') => 'badge-blue',
            default => 'badge-purple'
        };
    ?>
    <div class="intervention-card <?php echo $statusClass; ?>">
        <div class="intervention-header">
            <span class="badge-status <?php echo $statusClass === 'completed' ? 'badge-green' : ($statusClass === 'in-progress' ? 'badge-yellow' : 'badge-blue'); ?>"><?php echo $sess['status']; ?></span>
            <span class="badge-status <?php echo $profileClass; ?>"><?php echo $sess['focus_area']; ?></span>
        </div>
        <div class="intervention-pupil">
            <div class="pupil-avatar"><?php echo strtoupper(substr($sess['full_name'],0,1)); ?></div>
            <div>
                <div style="font-weight:600;"><?php echo htmlspecialchars($sess['full_name']); ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?php echo $sess['grade_level'].' - '.$sess['section']; ?></div>
            </div>
        </div>
        <div class="intervention-details">
            <p><strong>Teacher:</strong> <?php echo htmlspecialchars($sess['teacher_name'] ?? 'Unassigned'); ?></p>
            <p><strong>Date:</strong> <?php echo date('M d, Y',strtotime($sess['session_date'])); ?></p>
            <p><strong>Time:</strong> <?php echo date('g:i A',strtotime($sess['start_time'])).' - '.date('g:i A',strtotime($sess['end_time'])); ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="newSessionModal" class="modal">
    <div class="modal-header"><div class="modal-title">New Remedial Session</div><button class="modal-close" onclick="closeModal('newSessionModal')">&times;</button></div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Student</label>
                <select class="form-select" name="student_id" required><option value="">Select pupil</option><?php foreach ($pupils as $pupil): ?><option value="<?php echo $pupil['id']; ?>"><?php echo htmlspecialchars($pupil['full_name'] . ' - ' . $pupil['grade_level'] . ' ' . $pupil['section']); ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Focus Area</label><input type="text" class="form-input" name="focus_area"></div>
                <div class="form-group"><label class="form-label">Assigned Teacher</label><select class="form-select" name="teacher_id" required><option value="">Select teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Session Type</label>
                    <select class="form-select" name="session_type">
                        <option>Phonics Drill</option><option>Fluency Practice</option><option>Comprehension Guide</option>
                        <option>Guided Reading</option><option>Vocabulary Building</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Date</label><input type="date" class="form-input" name="session_date"></div>
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-input" name="start_time" required></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-input" name="end_time" required></div>
            </div>
            <div class="form-group"><label class="form-label">Remarks</label><textarea class="form-input" name="remarks" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('newSessionModal')">Cancel</button>
            <button type="submit" name="schedule_session" class="btn btn-primary">Schedule Session</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>