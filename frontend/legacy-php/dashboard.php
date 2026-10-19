<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: teacher_dashboard.php");
    exit();
}

// Fetch statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$independentCount = $pdo->query("SELECT COUNT(*) FROM students WHERE reading_profile = 'Independent'")->fetchColumn();
$instructionalCount = $pdo->query("SELECT COUNT(*) FROM students WHERE reading_profile = 'Instructional'")->fetchColumn();
$frustrationCount = $pdo->query("SELECT COUNT(*) FROM students WHERE reading_profile = 'Frustration'")->fetchColumn();
$readingLevelTotal = $independentCount + $instructionalCount + $frustrationCount;
$independentPercent = $readingLevelTotal > 0 ? round(($independentCount / $readingLevelTotal) * 100) : 0;
$instructionalPercent = $readingLevelTotal > 0 ? round(($instructionalCount / $readingLevelTotal) * 100) : 0;
$frustrationPercent = $readingLevelTotal > 0 ? round(($frustrationCount / $readingLevelTotal) * 100) : 0;

// Overview metrics from the active remedial, endorsement, and attendance records.
$remedialPupilsCount = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM remedial_sessions WHERE status IN ('Scheduled', 'In Progress')")->fetchColumn();
$pendingEndorsements = $pdo->query("SELECT COUNT(*) FROM endorsements WHERE status = 'Pending'")->fetchColumn();
$presentAttendance = $pdo->query("SELECT COUNT(*) FROM remedial_attendance WHERE status = 'Present'")->fetchColumn();
$absentAttendance = $pdo->query("SELECT COUNT(*) FROM remedial_attendance WHERE status = 'Absent'")->fetchColumn();
$attendanceTotal = $presentAttendance + $absentAttendance;
$attendanceRate = $attendanceTotal > 0 ? round(($presentAttendance / $attendanceTotal) * 100) : 0;

// Pupils needing intervention
$interventionPupils = $pdo->query("
    SELECT s.full_name, s.grade_level, s.reading_profile, 
           DATE_FORMAT(a.assessment_date, '%b %d, %Y') as last_assessment
    FROM students s
    LEFT JOIN assessments a ON s.id = a.student_id
    WHERE s.reading_profile = 'Frustration'
    GROUP BY s.id
    ORDER BY a.assessment_date DESC
    LIMIT 10
")->fetchAll();

// Recent assessments
$recentAssessments = $pdo->query("
    SELECT s.full_name, s.grade_level, a.tool, a.profile, a.total_score, 
           DATE_FORMAT(a.assessment_date, '%b %d, %Y') as assess_date
    FROM assessments a
    JOIN students s ON a.student_id = s.id
    ORDER BY a.assessment_date DESC, a.id DESC
    LIMIT 6
")->fetchAll();

// Latest progress updates
$progressUpdates = $pdo->query("
    SELECT s.full_name, s.grade_level, pu.previous_profile, pu.current_profile,
           DATE_FORMAT(pu.update_date, '%b %d, %Y') as update_date,
           u.full_name as updated_by
    FROM progress_updates pu
    JOIN students s ON pu.student_id = s.id
    LEFT JOIN users u ON pu.updated_by = u.id
    ORDER BY pu.update_date DESC
    LIMIT 6
")->fetchAll();

// Learning materials history
$materialHistory = $pdo->query("
    SELECT lm.title, lm.material_type, md.given_to, md.grade_level,
           DATE_FORMAT(md.date_given, '%b %d, %Y') as date_given,
           u.full_name as given_by
    FROM material_distribution md
    JOIN learning_materials lm ON md.material_id = lm.id
    LEFT JOIN users u ON md.given_by = u.id
    ORDER BY md.date_given DESC
    LIMIT 6
")->fetchAll();

// Grade distribution for chart
$gradeDist = $pdo->query("
    SELECT grade_level, 
           SUM(CASE WHEN reading_profile = 'Independent' THEN 1 ELSE 0 END) as independent,
           SUM(CASE WHEN reading_profile = 'Instructional' THEN 1 ELSE 0 END) as instructional,
           SUM(CASE WHEN reading_profile = 'Frustration' THEN 1 ELSE 0 END) as frustration
    FROM students
    GROUP BY grade_level
    ORDER BY grade_level
")->fetchAll();

$chartData = [
    'levels' => [(int) $independentCount, (int) $instructionalCount, (int) $frustrationCount],
    'grades' => [
        'labels' => array_map(fn($row) => str_replace('Grade ', 'Gr. ', $row['grade_level']), $gradeDist),
        'independent' => array_map(fn($row) => (int) $row['independent'], $gradeDist),
        'instructional' => array_map(fn($row) => (int) $row['instructional'], $gradeDist),
        'frustration' => array_map(fn($row) => (int) $row['frustration'], $gradeDist),
    ],
];

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Reading Performance Dashboard</div>
<div class="page-subtitle">Monitoring reading levels across all grade levels</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalStudents; ?></h3>
            <p>Total Pupils Monitored</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $independentCount; ?></h3>
            <p>Independent Level</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $instructionalCount; ?></h3>
            <p>Instructional Level</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $frustrationCount; ?></h3>
            <p>Frustration Level</p>
        </div>
    </div>
</div>

<!-- Overview Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-hand-holding-heart"></i></div>
        <div class="stat-info"><h3><?php echo $remedialPupilsCount; ?></h3><p>Pupils in Remedial Reading</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fa-solid fa-file-signature"></i></div>
        <div class="stat-info"><h3><?php echo $pendingEndorsements; ?></h3><p>Pending Endorsements</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-info"><h3><?php echo $attendanceRate; ?>%</h3><p>Attendance Summary</p><small><?php echo $presentAttendance; ?> present / <?php echo $absentAttendance; ?> absent</small></div>
    </div>
</div>

<div class="grid-2">
    <!-- Reading Level Distribution -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Reading Level Distribution</div>
                <div class="card-subtitle">2024-2025: End of Year</div>
            </div>
        </div>
        <div class="card-body">
            <div class="stats-row">
                <div style="width: 180px; height: 180px; flex-shrink: 0;">
                    <canvas id="levelDistributionChart"></canvas>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="width: 10px; height: 10px; background: #22C55E; border-radius: 2px;"></span>
                            <span style="font-size: 13px; font-weight: 600;">Independent</span>
                        </div>
                        <div style="height: 6px; background: #E2E8F0; border-radius: 3px; margin-left: 18px;">
                            <div style="width: <?php echo $independentPercent; ?>%; height: 100%; background: #22C55E; border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 12px; color: #64748B; margin-left: 18px;"><?php echo $independentPercent; ?>%</span>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="width: 10px; height: 10px; background: #F59E0B; border-radius: 2px;"></span>
                            <span style="font-size: 13px; font-weight: 600;">Instructional</span>
                        </div>
                        <div style="height: 6px; background: #E2E8F0; border-radius: 3px; margin-left: 18px;">
                            <div style="width: <?php echo $instructionalPercent; ?>%; height: 100%; background: #F59E0B; border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 12px; color: #64748B; margin-left: 18px;"><?php echo $instructionalPercent; ?>%</span>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="width: 10px; height: 10px; background: #EF4444; border-radius: 2px;"></span>
                            <span style="font-size: 13px; font-weight: 600;">Frustration</span>
                        </div>
                        <div style="height: 6px; background: #E2E8F0; border-radius: 3px; margin-left: 18px;">
                            <div style="width: <?php echo $frustrationPercent; ?>%; height: 100%; background: #EF4444; border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 12px; color: #64748B; margin-left: 18px;"><?php echo $frustrationPercent; ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade-Level Reading Performance -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Grade-Level Reading Performance</div>
                <div class="card-subtitle">Percentage of pupils per reading level by grade</div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="gradePerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Pupils Needing Immediate Intervention -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Pupils Needing Immediate Intervention</div>
            <div class="card-subtitle">Pupils currently at Frustration level</div>
        </div>
        <span class="record-count"><?php echo count($interventionPupils); ?> pupils</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table" id="interventionTable">
            <thead>
                <tr>
                    <th>Pupil Name</th>
                    <th>Grade</th>
                    <th>Reading Level</th>
                    <th>Last Assessment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interventionPupils as $pupil): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="pupil-avatar"><?php echo strtoupper(substr($pupil['full_name'], 0, 1)); ?></div>
                            <?php echo htmlspecialchars($pupil['full_name']); ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($pupil['grade_level']); ?></td>
                    <td><span class="badge-status badge-red"><?php echo $pupil['reading_profile']; ?></span></td>
                    <td><?php echo $pupil['last_assessment'] ?? 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($interventionPupils)): ?>
                <tr><td colspan="4" class="text-center" style="padding: 40px; color: #94A3B8;">No pupils at frustration level</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <!-- Recent Reading Assessments -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Recent Reading Assessments</div>
                <div class="card-subtitle">Latest Phil-IRI / CRLA results</div>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pupil Name</th>
                        <th>Grade</th>
                        <th>Tool</th>
                        <th>Level</th>
                        <th>Score</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAssessments as $assess): 
                        $badgeClass = match($assess['profile']) {
                            'Independent', 'Grade Ready' => 'badge-green',
                            'Instructional', 'Light Refresher', 'Moderate Refresher' => 'badge-yellow',
                            'Frustration', 'Full Refresher' => 'badge-red',
                            default => 'badge-blue'
                        };
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="pupil-avatar" style="width: 28px; height: 28px; font-size: 11px;">
                                    <?php echo strtoupper(substr($assess['full_name'], 0, 1)); ?>
                                </div>
                                <?php echo htmlspecialchars($assess['full_name']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($assess['grade_level']); ?></td>
                        <td><?php echo $assess['tool']; ?></td>
                        <td><span class="badge-status <?php echo $badgeClass; ?>"><?php echo $assess['profile']; ?></span></td>
                        <td><strong><?php echo $assess['total_score']; ?>%</strong></td>
                        <td><?php echo $assess['assess_date']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Latest Progress Updates -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Latest Progress Updates</div>
                <div class="card-subtitle">Reading level changes</div>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($progressUpdates as $update): 
                $prevClass = match($update['previous_profile']) {
                    'Independent', 'Grade Ready' => 'badge-green',
                    'Instructional', 'Light Refresher' => 'badge-yellow',
                    'Frustration', 'Full Refresher' => 'badge-red',
                    default => 'badge-blue'
                };
                $currClass = match($update['current_profile']) {
                    'Independent', 'Grade Ready' => 'badge-green',
                    'Instructional', 'Light Refresher' => 'badge-yellow',
                    'Frustration', 'Full Refresher' => 'badge-red',
                    default => 'badge-blue'
                };
                $arrow = ($update['current_profile'] === $update['previous_profile']) ? '→' : 
                         (in_array($update['current_profile'], ['Independent','Grade Ready']) && !in_array($update['previous_profile'], ['Independent','Grade Ready']) ? '↑' : '↓');
                $arrowColor = $arrow === '↑' ? 'var(--success)' : ($arrow === '↓' ? 'var(--danger)' : 'var(--text-muted)');
            ?>
            <div class="progress-item">
                <div class="flex items-center gap-4">
                    <div class="pupil-avatar"><?php echo strtoupper(substr($update['full_name'], 0, 1)); ?></div>
                    <div>
                        <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($update['full_name']); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            <?php echo $update['grade_level']; ?> • Updated by <?php echo $update['updated_by'] ?? 'System'; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                    <span class="badge-status <?php echo $prevClass; ?>" style="font-size: 10px;"><?php echo $update['previous_profile']; ?></span>
                    <span style="color: <?php echo $arrowColor; ?>; font-weight: bold;"><?php echo $arrow; ?></span>
                    <span class="badge-status <?php echo $currClass; ?>" style="font-size: 10px;"><?php echo $update['current_profile']; ?></span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted);"><?php echo $update['update_date']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- History of Learning Materials -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">History of Learning Materials Given to Pupils</div>
            <div class="card-subtitle">All reading materials distributed to pupils or class sections</div>
        </div>
        <span class="record-count"><?php echo count($materialHistory); ?> records</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Material Title</th>
                    <th>Type</th>
                    <th>Given To</th>
                    <th>Grade</th>
                    <th>Date Given</th>
                    <th>Given By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materialHistory as $mat): 
                    $typeClass = match($mat['material_type']) {
                        'Flashcards' => 'badge-purple',
                        'Storybook' => 'badge-blue',
                        'Workbook' => 'badge-green',
                        'Module' => 'badge-purple',
                        'Worksheet' => 'badge-orange',
                        default => 'badge-blue'
                    };
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($mat['title']); ?></strong></td>
                    <td><span class="badge-status <?php echo $typeClass; ?>"><?php echo $mat['material_type']; ?></span></td>
                    <td><?php echo htmlspecialchars($mat['given_to']); ?></td>
                    <td><?php echo htmlspecialchars($mat['grade_level']); ?></td>
                    <td><?php echo $mat['date_given']; ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div class="pupil-avatar" style="width: 24px; height: 24px; font-size: 10px;">
                                <?php echo strtoupper(substr($mat['given_by'] ?? 'S', 0, 1)); ?>
                            </div>
                            <?php echo htmlspecialchars($mat['given_by'] ?? 'System'); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>window.dashboardChartData = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<?php include 'footer.php'; ?>