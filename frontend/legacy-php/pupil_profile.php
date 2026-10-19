<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$student_id = (int) ($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$is_reviewer = hasRole('principal') || hasRole('coordinator');

$studentQuery = "SELECT s.*, u.full_name AS adviser_name FROM students s LEFT JOIN users u ON s.teacher_id = u.id WHERE s.id = ?";
if (!$is_reviewer) $studentQuery .= " AND s.teacher_id = ?";
$stmt = $pdo->prepare($studentQuery);
$is_reviewer ? $stmt->execute([$student_id]) : $stmt->execute([$student_id, $user_id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Pupil not found or you are not authorized to view this profile.');
}

$stmt = $pdo->prepare("SELECT * FROM assessments WHERE student_id = ? ORDER BY assessment_date DESC, id DESC");
$stmt->execute([$student_id]);
$assessments = $stmt->fetchAll();
$latestAssessment = $assessments[0] ?? null;

$stmt = $pdo->prepare("SELECT pu.*, u.full_name AS updated_by_name FROM progress_updates pu LEFT JOIN users u ON pu.updated_by = u.id WHERE pu.student_id = ? ORDER BY pu.update_date DESC, pu.id DESC");
$stmt->execute([$student_id]);
$progressHistory = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT rs.*, u.full_name AS teacher_name FROM remedial_sessions rs LEFT JOIN users u ON rs.teacher_id = u.id WHERE rs.student_id = ? ORDER BY rs.session_date DESC, rs.id DESC");
$stmt->execute([$student_id]);
$remedialSessions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT e.*, u.full_name AS reviewer_name FROM endorsements e LEFT JOIN users u ON e.reviewed_by = u.id WHERE e.student_id = ? ORDER BY e.created_at DESC, e.id DESC");
$stmt->execute([$student_id]);
$endorsements = $stmt->fetchAll();
$latestEndorsement = $endorsements[0] ?? null;

$stmt = $pdo->prepare("SELECT ra.*, rs.session_date FROM remedial_attendance ra JOIN remedial_sessions rs ON ra.session_id = rs.id WHERE ra.student_id = ? ORDER BY ra.attendance_date DESC, ra.id DESC");
$stmt->execute([$student_id]);
$attendanceHistory = $stmt->fetchAll();
$attended = count(array_filter($attendanceHistory, fn($row) => $row['status'] === 'Present'));
$missed = count(array_filter($attendanceHistory, fn($row) => $row['status'] === 'Absent'));
$activeRemedial = count(array_filter($remedialSessions, fn($row) => in_array($row['status'], ['Scheduled', 'In Progress'], true))) > 0;
$profileClass = in_array($student['reading_profile'], ['Independent', 'Grade Ready'], true) ? 'badge-green' : (in_array($student['reading_profile'], ['Instructional', 'Light Refresher'], true) ? 'badge-yellow' : 'badge-red');
$formatDate = fn($date) => $date ? date('M d, Y', strtotime($date)) : '-';
$initials = implode('', array_map(fn($word) => strtoupper($word[0]), explode(' ', $student['full_name'])));
if (strlen($initials) > 2) $initials = substr($initials, 0, 2);

include 'header.php';
include 'sidebar.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px;">
    <div><div class="page-title">Pupil Profile</div><div class="page-subtitle">Complete reading-monitoring record for <?php echo htmlspecialchars($student['full_name']); ?></div></div>
    <a href="<?php echo $is_reviewer ? 'students.php' : 'my_students.php'; ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Pupils</a>
</div>

<div class="grid-2">
    <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:18px;">
        <?php if (!empty($student['profile_image']) && file_exists($student['profile_image'])): ?><img src="<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Pupil profile" style="width:96px;height:96px;border-radius:50%;object-fit:cover;"><?php else: ?><div class="pupil-avatar" style="width:96px;height:96px;font-size:30px;"><?php echo htmlspecialchars($initials); ?></div><?php endif; ?>
        <div><h2 style="margin:0 0 6px;"><?php echo htmlspecialchars($student['full_name']); ?></h2><div style="color:var(--text-muted);">LRN: <?php echo htmlspecialchars($student['lrn']); ?></div><div style="color:var(--text-muted);"><?php echo htmlspecialchars($student['grade_level'] . ' - ' . $student['section']); ?></div></div>
    </div></div>
    <div class="card"><div class="card-header"><div class="card-title">Reading Information</div></div><div class="card-body"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;"><span>Current Reading Level</span><span class="badge-status <?php echo $profileClass; ?>"><?php echo htmlspecialchars($student['reading_profile'] ?? 'Not Assessed'); ?></span></div><p><strong>Assessment Used:</strong> <?php echo htmlspecialchars($latestAssessment['tool'] ?? '-'); ?></p><p><strong>Latest Assessment Result:</strong> <?php echo $latestAssessment ? htmlspecialchars($latestAssessment['total_score'] . '/30') : '-'; ?></p><p><strong>Assessment Date:</strong> <?php echo $formatDate($latestAssessment['assessment_date'] ?? null); ?></p></div></div>
</div>

<div class="grid-2">
    <div class="card"><div class="card-header"><div class="card-title">Personal Information</div></div><div class="card-body"><p><strong>Picture:</strong> <?php echo $student['profile_image'] ? 'Available' : 'Not provided'; ?></p><p><strong>LRN:</strong> <?php echo htmlspecialchars($student['lrn']); ?></p><p><strong>Full Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p><p><strong>Grade Level:</strong> <?php echo htmlspecialchars($student['grade_level']); ?></p><p><strong>Section:</strong> <?php echo htmlspecialchars($student['section']); ?></p><p><strong>Adviser:</strong> <?php echo htmlspecialchars($student['adviser_name'] ?? 'Unassigned'); ?></p><p><strong>Parent/Guardian:</strong> <?php echo htmlspecialchars($student['parent_guardian'] ?? 'Not provided'); ?></p><p><strong>Contact Number:</strong> <?php echo htmlspecialchars($student['contact_number'] ?? 'Not provided'); ?></p></div></div>
    <div class="card"><div class="card-header"><div class="card-title">Remedial Information</div></div><div class="card-body"><p><strong>Project Subaybay Status:</strong> <span class="badge-status <?php echo $activeRemedial ? 'badge-red' : 'badge-green'; ?>"><?php echo $activeRemedial ? 'Under Remedial Reading' : 'Monitoring'; ?></span></p><p><strong>Date Endorsed:</strong> <?php echo $formatDate($latestEndorsement['created_at'] ?? null); ?></p><p><strong>Date Approved:</strong> <?php echo $formatDate($latestEndorsement['reviewed_at'] ?? null); ?></p><p><strong>Assigned Teacher:</strong> <?php echo htmlspecialchars($student['adviser_name'] ?? 'Unassigned'); ?></p><p><strong>Intervention Status:</strong> <?php echo $activeRemedial ? 'Active' : 'No active session'; ?></p></div></div>
</div>

<div class="card"><div class="card-header"><div class="card-title">Assessment History</div><span class="record-count"><?php echo count($assessments); ?> records</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Date</th><th>Assessment Used</th><th>Result</th><th>Reading Level</th></tr></thead><tbody><?php foreach ($assessments as $assessment): ?><tr><td><?php echo $formatDate($assessment['assessment_date']); ?></td><td><?php echo htmlspecialchars($assessment['tool']); ?></td><td><?php echo htmlspecialchars($assessment['total_score'] . '/30'); ?></td><td><?php echo htmlspecialchars($assessment['profile']); ?></td></tr><?php endforeach; ?><?php if (!$assessments): ?><tr><td colspan="4" class="text-center" style="padding:30px;color:#94A3B8;">No assessment records.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="grid-2">
    <div class="card"><div class="card-header"><div class="card-title">Reading Progress</div></div><div class="card-body"><p><strong>Progress Summary:</strong> <?php echo count($progressHistory) ? 'Progress history is available.' : 'No progress updates recorded.'; ?></p><div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Date</th><th>Previous vs. Current</th><th>Summary</th></tr></thead><tbody><?php foreach ($progressHistory as $progress): ?><tr><td><?php echo $formatDate($progress['update_date']); ?></td><td><?php echo htmlspecialchars($progress['previous_profile'] . ' -> ' . $progress['current_profile']); ?></td><td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $progress['trend']))); ?></td></tr><?php endforeach; ?><?php if (!$progressHistory): ?><tr><td colspan="3" style="padding:20px;color:#94A3B8;">No progress history.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="card"><div class="card-header"><div class="card-title">Attendance</div></div><div class="card-body"><div class="stats-row"><div><h3><?php echo $attended; ?></h3><span>Sessions Attended</span></div><div><h3><?php echo $missed; ?></h3><span>Sessions Missed</span></div></div><div style="overflow-x:auto;margin-top:15px;"><table class="data-table"><thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead><tbody><?php foreach ($attendanceHistory as $attendance): ?><tr><td><?php echo $formatDate($attendance['attendance_date']); ?></td><td><span class="badge-status <?php echo $attendance['status'] === 'Present' ? 'badge-green' : 'badge-red'; ?>"><?php echo htmlspecialchars($attendance['status']); ?></span></td><td><?php echo htmlspecialchars($attendance['remarks'] ?? '-'); ?></td></tr><?php endforeach; ?><?php if (!$attendanceHistory): ?><tr><td colspan="3" style="padding:20px;color:#94A3B8;">No attendance history.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>

<div class="card"><div class="card-header"><div class="card-title">Remedial Session Records</div><span class="record-count"><?php echo count($remedialSessions); ?> sessions</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Session Date</th><th>Time</th><th>Assigned Teacher</th><th>Remarks</th><th>Status</th></tr></thead><tbody><?php foreach ($remedialSessions as $session): ?><tr><td><?php echo $formatDate($session['session_date']); ?></td><td><?php echo date('g:i A', strtotime($session['start_time'])); ?><?php if ($session['end_time']): ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?><?php endif; ?></td><td><?php echo htmlspecialchars($session['teacher_name'] ?? 'Unassigned'); ?></td><td><?php echo htmlspecialchars($session['remarks'] ?? $session['notes'] ?? '-'); ?></td><td><?php echo htmlspecialchars($session['status']); ?></td></tr><?php endforeach; ?><?php if (!$remedialSessions): ?><tr><td colspan="5" style="padding:30px;color:#94A3B8;">No remedial sessions.</td></tr><?php endif; ?></tbody></table></div></div>

<?php include 'footer.php'; ?>