<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_section = $_SESSION['section'] ?? '';

// Fetch teacher's students
$students = $pdo->prepare("
    SELECT s.*, DATE_FORMAT(a.assessment_date, '%b %d, %Y') as last_assessment
    FROM students s
    LEFT JOIN assessments a ON s.id = a.student_id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.full_name
");
$students->execute([$teacher_id]);
$myStudents = $students->fetchAll();

$totalAssigned = count($myStudents);
$independentCount = count(array_filter($myStudents, fn($s) => $s['reading_profile'] === 'Independent'));
$instructionalCount = count(array_filter($myStudents, fn($s) => $s['reading_profile'] === 'Instructional'));
$frustrationCount = count(array_filter($myStudents, fn($s) => $s['reading_profile'] === 'Frustration'));

$remedialPupilsStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM remedial_sessions WHERE teacher_id = ? AND status IN ('Scheduled', 'In Progress')");
$remedialPupilsStmt->execute([$teacher_id]);
$remedialPupilsCount = (int) $remedialPupilsStmt->fetchColumn();

$pendingEndorsementsStmt = $pdo->prepare("SELECT COUNT(*) FROM endorsements WHERE teacher_id = ? AND status = 'Pending'");
$pendingEndorsementsStmt->execute([$teacher_id]);
$pendingEndorsements = (int) $pendingEndorsementsStmt->fetchColumn();

$attendanceStmt = $pdo->prepare("SELECT ra.status, COUNT(*) AS total FROM remedial_attendance ra JOIN remedial_sessions rs ON ra.session_id = rs.id WHERE rs.teacher_id = ? GROUP BY ra.status");
$attendanceStmt->execute([$teacher_id]);
$attendanceCounts = ['Present' => 0, 'Absent' => 0];
foreach ($attendanceStmt->fetchAll() as $attendanceRow) {
    $attendanceCounts[$attendanceRow['status']] = (int) $attendanceRow['total'];
}
$presentAttendance = $attendanceCounts['Present'];
$absentAttendance = $attendanceCounts['Absent'];
$attendanceTotal = $presentAttendance + $absentAttendance;
$attendanceRate = $attendanceTotal > 0 ? round(($presentAttendance / $attendanceTotal) * 100) : 0;

// Immediate intervention pupils
$interventionPupils = array_filter($myStudents, fn($s) => $s['reading_profile'] === 'Frustration');

// Upcoming intervention sessions
$sessions = $pdo->prepare("
    SELECT rs.*, s.full_name, s.grade_level, s.section
    FROM remedial_sessions rs
    JOIN students s ON rs.student_id = s.id
    WHERE rs.teacher_id = ? AND rs.session_date >= CURDATE()
    ORDER BY rs.session_date, rs.start_time
    LIMIT 5
");
$sessions->execute([$teacher_id]);
$upcomingSessions = $sessions->fetchAll();

// Recent progress updates for teacher's students
$progress = $pdo->prepare("
    SELECT pu.*, s.full_name, s.grade_level, s.section
    FROM progress_updates pu
    JOIN students s ON pu.student_id = s.id
    WHERE s.teacher_id = ?
    ORDER BY pu.update_date DESC
    LIMIT 6
");
$progress->execute([$teacher_id]);
$recentProgress = $progress->fetchAll();

// Learning materials history
$materials = $pdo->prepare("
    SELECT md.*, lm.title, lm.material_type, s.full_name as student_name
    FROM material_distribution md
    JOIN learning_materials lm ON md.material_id = lm.id
    LEFT JOIN students s ON md.given_to = s.full_name
    WHERE md.given_by = ? OR s.teacher_id = ?
    ORDER BY md.date_given DESC
    LIMIT 5
");
$materials->execute([$teacher_id, $teacher_id]);
$materialHistory = $materials->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Teacher Dashboard</div>
<div class="page-subtitle">Overview of your assigned pupils</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalAssigned; ?></h3>
            <p>Total Assigned Pupils</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $independentCount; ?></h3>
            <p>Independent Level<br><small>On grade level</small></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $instructionalCount; ?></h3>
            <p>Instructional Level<br><small>Needs guided support</small></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $frustrationCount; ?></h3>
            <p>Frustration Level<br><small>Needs immediate help</small></p>
        </div>
    </div>
</div>

<!-- Dashboard Overview -->
<div class="stats-grid">
    <a href="my_students.php" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info"><h3><?php echo $totalAssigned; ?></h3><p>My Assigned Pupils</p><small>View pupil list</small></div>
    </a>
    <a href="interventions.php" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon purple"><i class="fa-solid fa-hand-holding-heart"></i></div>
        <div class="stat-info"><h3><?php echo $remedialPupilsCount; ?></h3><p>Pupils in Remedial Reading</p><small>View remedial sessions</small></div>
    </a>
    <a href="endorsements.php?status=Pending" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon yellow"><i class="fa-solid fa-file-signature"></i></div>
        <div class="stat-info"><h3><?php echo $pendingEndorsements; ?></h3><p>Pending Endorsements</p><small>Review submissions</small></div>
    </a>
    <a href="remedial_attendance.php" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-info"><h3><?php echo $attendanceRate; ?>%</h3><p>Attendance Summary</p><small><?php echo $presentAttendance; ?> present / <?php echo $absentAttendance; ?> absent</small></div>
    </a>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- My Students Overview -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">My Students Overview</div>
            <div class="search-filter-bar" style="margin-top: 10px;">
                <input type="text" class="search-input" placeholder="Search pupil..." style="max-width: 200px;" onkeyup="searchTable('studentSearch', 'studentTable')">
                <select class="select-input">
                    <option>All Levels</option>
                    <option>Independent</option>
                    <option>Instructional</option>
                    <option>Frustration</option>
                </select>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table" id="studentTable">
                <thead>
                    <tr>
                        <th>PUPIL NAME</th>
                        <th>GRADE & SECTION</th>
                        <th>READING LEVEL</th>
                        <th>LAST ASSESSMENT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myStudents as $student): 
                        $badgeClass = match($student['reading_profile']) {
                            'Independent', 'Grade Ready' => 'badge-green',
                            'Instructional', 'Light Refresher' => 'badge-blue',
                            'Frustration', 'Full Refresher' => 'badge-red',
                            default => 'badge-yellow'
                        };
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['grade_level'] . ' - ' . $student['section']); ?></td>
                        <td><span class="badge-status <?php echo $badgeClass; ?>"><?php echo $student['reading_profile'] ?? 'N/A'; ?></span></td>
                        <td><?php echo $student['last_assessment'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding: 12px 16px; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border-color);">
                Showing <?php echo count($myStudents); ?> of <?php echo count($myStudents); ?> pupils
            </div>
        </div>
    </div>

    <!-- Immediate Intervention -->
    <div class="card" style="background: #FEF2F2; border: 1px solid #FECACA;">
        <div class="card-header">
            <div class="card-title" style="color: #991B1B;">
                <i class="fa-solid fa-triangle-exclamation"></i> Immediate Intervention
            </div>
            <span class="badge-status badge-red"><?php echo count($interventionPupils); ?></span>
        </div>
        <div class="card-body">
            <?php foreach ($interventionPupils as $pupil): ?>
            <div class="pupil-list-item">
                <div class="pupil-info">
                    <span class="pupil-name"><?php echo htmlspecialchars($pupil['full_name']); ?></span>
                    <span class="pupil-meta"><?php echo htmlspecialchars($pupil['grade_level'] . ' - ' . $pupil['section']); ?> • <?php echo $pupil['last_assessment'] ?? 'N/A'; ?></span>
                </div>
                <span class="badge-status badge-red">Frustration</span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($interventionPupils)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-check-circle" style="color: var(--success);"></i>
                <p>No pupils need immediate intervention</p>
            </div>
            <?php endif; ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #FECACA; font-size: 12px; color: #991B1B; text-align: center;">
                <?php echo count($interventionPupils); ?> pupils need priority support
            </div>
        </div>
    </div>
</div>

<!-- Reading Performance Overview -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-chart-line"></i> Reading Performance Overview</div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <div>
                <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase;">Level Distribution by Month</div>
                <div class="chart-container">
                    <canvas id="monthlyDistributionChart"></canvas>
                </div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase;">Average Reading Score Over Time</div>
                <div class="chart-container">
                    <canvas id="scoreTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Upcoming Intervention Sessions -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-calendar-check"></i> Upcoming Intervention Sessions</div>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Pupil</th>
                        <th>Session Type</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingSessions as $session): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($session['full_name']); ?></strong></td>
                        <td><span class="badge-status badge-blue"><?php echo $session['session_type']; ?></span></td>
                        <td><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($upcomingSessions)): ?>
                    <tr><td colspan="4" class="text-center" style="padding: 40px; color: #94A3B8;">No upcoming sessions</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Progress Updates -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-arrow-trend-up"></i> Recent Reading Progress</div>
            <a href="teacher_reading_performance.php" class="view-all">View All</a>
        </div>
        <div class="card-body">
            <?php foreach ($recentProgress as $upd): 
                $prevClass = match($upd['previous_profile']) {
                    'Independent', 'Grade Ready' => 'badge-green',
                    'Instructional', 'Light Refresher' => 'badge-blue',
                    'Frustration', 'Full Refresher' => 'badge-red',
                    default => 'badge-yellow'
                };
                $currClass = match($upd['current_profile']) {
                    'Independent', 'Grade Ready' => 'badge-green',
                    'Instructional', 'Light Refresher' => 'badge-blue',
                    'Frustration', 'Full Refresher' => 'badge-red',
                    default => 'badge-yellow'
                };
                $trendIcon = $upd['trend'] === 'improved' ? '<i class="fa-solid fa-arrow-up trend-up"></i>' : 
                            ($upd['trend'] === 'declined' ? '<i class="fa-solid fa-arrow-down trend-down"></i>' : '<i class="fa-solid fa-minus trend-flat"></i>');
            ?>
            <div class="progress-item">
                <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($upd['full_name']); ?></div>
                <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                    <span class="badge-status <?php echo $prevClass; ?>" style="font-size: 10px;"><?php echo $upd['previous_profile']; ?></span>
                    <span style="color: var(--text-muted);">→</span>
                    <span class="badge-status <?php echo $currClass; ?>" style="font-size: 10px;"><?php echo $upd['current_profile']; ?></span>
                    <?php echo $trendIcon; ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($upd['update_date'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Learning Materials History -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-book"></i> Learning Materials History</div>
        <a href="teacher_learning_materials.php" class="view-all">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date Given</th>
                    <th>Pupil Name</th>
                    <th>Material</th>
                    <th>Topic</th>
                    <th>Given By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materialHistory as $mat): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($mat['date_given'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($mat['given_to']); ?></strong></td>
                    <td><?php echo htmlspecialchars($mat['title']); ?></td>
                    <td><span class="badge-status badge-blue"><?php echo $mat['material_type']; ?></span></td>
                    <td>Teacher Cruz</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($materialHistory)): ?>
                <tr><td colspan="5" class="text-center" style="padding: 40px; color: #94A3B8;">No materials distributed yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div style="padding: 12px 16px; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border-color);">
            Showing <?php echo min(5, count($materialHistory)); ?> most recent entries
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>