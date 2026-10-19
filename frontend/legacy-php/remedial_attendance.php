<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$user_id = (int) $_SESSION['user_id'];
$is_reviewer = hasRole('principal') || hasRole('coordinator');
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_attendance'])) {
    $session_id = (int) ($_POST['session_id'] ?? 0);
    $status = $_POST['status'] ?? 'Present';
    $sessionQuery = $is_reviewer
        ? "SELECT id, student_id FROM remedial_sessions WHERE id = ?"
        : "SELECT rs.id, rs.student_id FROM remedial_sessions rs JOIN students s ON s.id = rs.student_id WHERE rs.id = ? AND s.teacher_id = ?";
    $sessionStmt = $pdo->prepare($sessionQuery);
    $is_reviewer ? $sessionStmt->execute([$session_id]) : $sessionStmt->execute([$session_id, $user_id]);
    $session = $sessionStmt->fetch();

    if (!$session || !in_array($status, ['Present', 'Absent'], true)) {
        $_SESSION['error'] = 'That remedial session is not available for attendance recording.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO remedial_attendance (session_id, student_id, attendance_date, status, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)");
        $stmt->execute([$session_id, $session['student_id'], $_POST['attendance_date'] ?: date('Y-m-d'), $status, trim($_POST['remarks'] ?? ''), $user_id]);
        $_SESSION['success'] = 'Attendance recorded.';
    }
    header('Location: remedial_attendance.php');
    exit;
}

$sessionSql = "SELECT rs.id, rs.session_date, rs.start_time, rs.end_time, s.full_name, s.grade_level, s.section, u.full_name AS teacher_name,
    COALESCE(ra.status, 'Not recorded') AS attendance_status, ra.attendance_date, ra.remarks
    FROM remedial_sessions rs JOIN students s ON rs.student_id = s.id LEFT JOIN users u ON rs.teacher_id = u.id
    LEFT JOIN remedial_attendance ra ON ra.session_id = rs.id AND ra.attendance_date = CURDATE()";
if (!$is_reviewer) $sessionSql .= " WHERE s.teacher_id = ?";
$sessionSql .= " ORDER BY rs.session_date DESC, rs.start_time DESC";
$stmt = $pdo->prepare($sessionSql);
$is_reviewer ? $stmt->execute() : $stmt->execute([$user_id]);
$sessions = $stmt->fetchAll();

$attendanceScope = $is_reviewer ? '' : " JOIN students s ON s.id = ra.student_id WHERE s.teacher_id = " . $user_id;
$present = (int) $pdo->query("SELECT COUNT(*) FROM remedial_attendance ra" . $attendanceScope . ($is_reviewer ? " WHERE ra.status = 'Present'" : " AND ra.status = 'Present'"))->fetchColumn();
$absent = (int) $pdo->query("SELECT COUNT(*) FROM remedial_attendance ra" . $attendanceScope . ($is_reviewer ? " WHERE ra.status = 'Absent'" : " AND ra.status = 'Absent'"))->fetchColumn();
$historySql = "SELECT ra.*, s.full_name, s.grade_level, s.section, u.full_name AS recorded_by_name FROM remedial_attendance ra JOIN students s ON ra.student_id = s.id LEFT JOIN users u ON ra.recorded_by = u.id";
if (!$is_reviewer) $historySql .= " WHERE s.teacher_id = ?";
$historySql .= " ORDER BY ra.attendance_date DESC, ra.id DESC";
$historyStmt = $pdo->prepare($historySql);
$is_reviewer ? $historyStmt->execute() : $historyStmt->execute([$user_id]);
$attendanceHistory = $historyStmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Remedial Attendance</div>
<div class="page-subtitle">Record and review attendance from actual remedial sessions.</div>
<div class="card-title" style="margin:20px 0 12px;">Attendance Summary</div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-info"><h3><?php echo $present; ?></h3><p>Sessions Attended</p></div></div>
    <div class="stat-card"><div class="stat-info"><h3><?php echo $absent; ?></h3><p>Sessions Missed</p></div></div>
</div>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><div class="card-title">Record Attendance</div><span class="record-count">Today's Attendance</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Date</th><th>Pupil</th><th>Teacher</th><th>Time</th><th>Status</th><th>Record</th></tr></thead><tbody>
<?php foreach ($sessions as $session): ?><tr>
<td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td><td><strong><?php echo htmlspecialchars($session['full_name']); ?></strong><br><small><?php echo htmlspecialchars($session['grade_level'] . ' - ' . $session['section']); ?></small></td><td><?php echo htmlspecialchars($session['teacher_name'] ?? 'Unassigned'); ?></td><td><?php echo date('g:i A', strtotime($session['start_time'])); ?><?php if ($session['end_time']): ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?><?php endif; ?></td>
<td><span class="badge-status <?php echo $session['attendance_status'] === 'Present' ? 'badge-green' : ($session['attendance_status'] === 'Absent' ? 'badge-red' : 'badge-blue'); ?>"><?php echo htmlspecialchars($session['attendance_status']); ?></span></td>
<td><form method="POST" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;"><input type="hidden" name="session_id" value="<?php echo $session['id']; ?>"><input type="date" name="attendance_date" class="form-input" style="width:135px" value="<?php echo date('Y-m-d'); ?>"><select name="status" class="select-input"><option>Present</option><option>Absent</option></select><input type="text" name="remarks" class="form-input" style="width:150px" placeholder="Remarks"><button class="btn btn-sm btn-primary" name="record_attendance">Save</button></form></td>
</tr><?php endforeach; ?><?php if (!$sessions): ?><tr><td colspan="6" class="text-center" style="padding:40px;color:#94A3B8;">No remedial sessions found.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="card"><div class="card-header"><div class="card-title">Attendance History</div><span class="record-count"><?php echo count($attendanceHistory); ?> records</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Date</th><th>Pupil</th><th>Grade / Section</th><th>Status</th><th>Remarks</th><th>Recorded By</th></tr></thead><tbody><?php foreach ($attendanceHistory as $attendance): ?><tr><td><?php echo date('M d, Y', strtotime($attendance['attendance_date'])); ?></td><td><a href="pupil_profile.php?id=<?php echo $attendance['student_id']; ?>"><?php echo htmlspecialchars($attendance['full_name']); ?></a></td><td><?php echo htmlspecialchars($attendance['grade_level'] . ' - ' . $attendance['section']); ?></td><td><span class="badge-status <?php echo $attendance['status'] === 'Present' ? 'badge-green' : 'badge-red'; ?>"><?php echo htmlspecialchars($attendance['status']); ?></span></td><td><?php echo htmlspecialchars($attendance['remarks'] ?? '-'); ?></td><td><?php echo htmlspecialchars($attendance['recorded_by_name'] ?? 'System'); ?></td></tr><?php endforeach; ?><?php if (!$attendanceHistory): ?><tr><td colspan="6" style="padding:30px;color:#94A3B8;text-align:center;">No attendance history.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include 'footer.php'; ?>