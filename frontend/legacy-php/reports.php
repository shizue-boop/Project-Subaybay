<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator') && !hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$isTeacher = hasRole('teacher');
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'] ?? 'activity';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $allowedTypes = ['activity', 'progress', 'attendance'];

    if (!in_array($reportType, $allowedTypes, true) || !$startDate || !$endDate || $startDate > $endDate) {
        $_SESSION['error'] = 'Choose a valid report type and date range.';
    } else {
        $studentFilter = $isTeacher ? ' AND s.teacher_id = ?' : '';
        $studentParams = $isTeacher ? [$user_id] : [];
        $titleTypes = ['activity' => 'Teacher Activity Report', 'progress' => 'Student Progress Report', 'attendance' => 'Attendance Report'];
        $reportTitle = $titleTypes[$reportType] . ' (' . $startDate . ' to ' . $endDate . ')';
        $reportRows = [];

        if ($reportType === 'progress') {
            $stmt = $pdo->prepare("SELECT s.full_name, s.grade_level, s.section, a.tool, a.total_score, a.profile, a.assessment_date FROM assessments a JOIN students s ON s.id = a.student_id WHERE a.assessment_date BETWEEN ? AND ?" . $studentFilter . " ORDER BY a.assessment_date DESC, s.full_name");
            $stmt->execute(array_merge([$startDate, $endDate], $studentParams));
            $reportRows = $stmt->fetchAll();
            $headers = ['Student', 'Grade / Section', 'Assessment', 'Score', 'Reading Level', 'Date'];
        } elseif ($reportType === 'attendance') {
            $stmt = $pdo->prepare("SELECT s.full_name, s.grade_level, s.section, ra.status, ra.remarks, ra.attendance_date FROM remedial_attendance ra JOIN students s ON s.id = ra.student_id WHERE ra.attendance_date BETWEEN ? AND ?" . $studentFilter . " ORDER BY ra.attendance_date DESC, s.full_name");
            $stmt->execute(array_merge([$startDate, $endDate], $studentParams));
            $reportRows = $stmt->fetchAll();
            $headers = ['Student', 'Grade / Section', 'Status', 'Remarks', 'Date'];
        } else {
            $activityWhere = $isTeacher ? ' AND %s = ?' : '';
            $activityParams = $isTeacher ? [$user_id] : [];
            $queries = [
                ['Assessments', "SELECT COUNT(*) FROM assessments a WHERE a.assessment_date BETWEEN ? AND ?" . sprintf($activityWhere, 'a.recorded_by')],
                ['Progress updates', "SELECT COUNT(*) FROM progress_updates p WHERE p.update_date BETWEEN ? AND ?" . sprintf($activityWhere, 'p.updated_by')],
                ['Attendance records', "SELECT COUNT(*) FROM remedial_attendance ra WHERE ra.attendance_date BETWEEN ? AND ?" . sprintf($activityWhere, 'ra.recorded_by')],
                ['Remedial sessions', "SELECT COUNT(*) FROM remedial_sessions rs WHERE rs.session_date BETWEEN ? AND ?" . sprintf($activityWhere, 'rs.teacher_id')],
                ['Endorsements', "SELECT COUNT(*) FROM endorsements e WHERE DATE(e.created_at) BETWEEN ? AND ?" . sprintf($activityWhere, 'e.teacher_id')],
                ['Materials distributed', "SELECT COUNT(*) FROM material_distribution md WHERE md.date_given BETWEEN ? AND ?" . sprintf($activityWhere, 'md.given_by')],
            ];
            foreach ($queries as [$label, $query]) {
                $stmt = $pdo->prepare($query);
                $stmt->execute(array_merge([$startDate, $endDate], $activityParams));
                $reportRows[] = ['activity' => $label, 'total' => (int) $stmt->fetchColumn()];
            }
            $headers = ['Activity', 'Total'];
        }

        $escape = fn($value) => htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . $escape($reportTitle) . '</title><style>body{font-family:Arial,sans-serif;color:#1e293b;margin:40px}h1{margin-bottom:4px}p{color:#64748b}table{border-collapse:collapse;width:100%;margin-top:24px}th,td{border:1px solid #cbd5e1;padding:10px;text-align:left}th{background:#e2e8f0}</style></head><body><h1>' . $escape($reportTitle) . '</h1><p>Generated on ' . date('M d, Y h:i A') . '</p><table><thead><tr>';
        foreach ($headers as $header) $html .= '<th>' . $escape($header) . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($reportRows as $row) {
            $html .= '<tr>';
            if ($reportType === 'progress') {
                $values = [$row['full_name'], $row['grade_level'] . ' - ' . $row['section'], $row['tool'], $row['total_score'], $row['profile'], $row['assessment_date']];
            } elseif ($reportType === 'attendance') {
                $values = [$row['full_name'], $row['grade_level'] . ' - ' . $row['section'], $row['status'], $row['remarks'] ?: '-', $row['attendance_date']];
            } else {
                $values = [$row['activity'], $row['total']];
            }
            foreach ($values as $value) $html .= '<td>' . $escape($value) . '</td>';
            $html .= '</tr>';
        }
        if (!$reportRows) $html .= '<tr><td colspan="' . count($headers) . '">No activity found for this period.</td></tr>';
        $html .= '</tbody></table></body></html>';

        $reportsDir = 'uploads/reports';
        if (!is_dir($reportsDir)) mkdir($reportsDir, 0755, true);
        $filePath = $reportsDir . '/report_' . $user_id . '_' . time() . '.html';
        file_put_contents($filePath, $html);
        $stmt = $pdo->prepare("INSERT INTO reports (report_title, report_type, generated_by, generated_date, file_format, status, file_path) VALUES (?, ?, ?, NOW(), 'HTML', 'Ready', ?)");
        $stmt->execute([$reportTitle, $reportType, $user_id, $filePath]);
        $_SESSION['success'] = 'Report generated successfully.';
    }
    header('Location: reports.php');
    exit();
}

$reportsQuery = "
    SELECT r.*, u.full_name as generator_name
    FROM reports r
    LEFT JOIN users u ON r.generated_by = u.id
";
$reportsParams = [];
if ($isTeacher) {
    $reportsQuery .= " WHERE r.generated_by = ?";
    $reportsParams[] = $user_id;
}
$reportsQuery .= " ORDER BY r.created_at DESC";
$reportsStmt = $pdo->prepare($reportsQuery);
$reportsStmt->execute($reportsParams);
$reports = $reportsStmt->fetchAll();

$totalReports = count($reports);
$avgScore = $pdo->query("SELECT AVG(score) FROM students WHERE score > 0" . ($isTeacher ? " AND teacher_id = " . $user_id : ''))->fetchColumn() ?? 0;
$totalAssessed = $pdo->query("SELECT COUNT(*) FROM assessments a JOIN students s ON s.id = a.student_id" . ($isTeacher ? " WHERE s.teacher_id = " . $user_id : ''))->fetchColumn();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Reports</div>
<div class="page-subtitle">Generate and download reading performance reports</div>

<div class="card" style="background:linear-gradient(135deg,#1E293B 0%,#334155 100%);color:white;margin-bottom:24px;">
    <form method="POST" class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <div style="font-size:18px;font-weight:700;margin-bottom:4px;">Generate New Report</div>
            <div style="font-size:13px;opacity:0.8;">Create a report from recorded activity for a selected period.</div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select name="report_type" class="select-input" required>
                <option value="activity">Activity Summary</option>
                <option value="progress">Student Progress</option>
                <option value="attendance">Attendance</option>
            </select>
            <input type="date" name="start_date" class="form-input" value="<?php echo date('Y-m-01'); ?>" required>
            <input type="date" name="end_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
            <button type="submit" name="generate_report" class="btn" style="background:white;color:#1E293B;"><i class="fa-solid fa-file-export"></i> Generate</button>
        </div>
    </form>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-file-lines"></i></div><div class="stat-info"><h3><?php echo $totalReports; ?></h3><p>Reports Generated</p></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-chart-pie"></i></div><div class="stat-info"><h3><?php echo round($avgScore); ?>%</h3><p>Avg School Score</p></div></div>
    <div class="stat-card"><div class="stat-icon purple"><i class="fa-solid fa-users"></i></div><div class="stat-info"><h3><?php echo $totalAssessed; ?></h3><p>Students Assessed</p></div></div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Generated Reports</div></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Report</th><th>Type</th><th>Generated</th><th>Format</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($reports as $rep): 
                    $statusClass = $rep['status'] === 'Ready' ? 'badge-green' : 'badge-yellow';
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($rep['report_title']); ?></strong></td>
                    <td><span class="badge-status badge-blue"><?php echo $rep['report_type']; ?></span></td>
                    <td><?php echo $rep['generated_date'] ? date('M d, Y',strtotime($rep['generated_date'])) : '-'; ?></td>
                    <td><?php echo $rep['file_format']; ?></td>
                    <td><span class="badge-status <?php echo $statusClass; ?>"><?php echo $rep['status']; ?></span></td>
                    <td><?php if (!empty($rep['file_path']) && is_file($rep['file_path'])): ?><a class="btn btn-sm btn-primary" style="padding:6px 12px;" href="<?php echo htmlspecialchars($rep['file_path']); ?>" download><i class="fa-solid fa-download"></i> Download</a><?php else: ?><span style="color:#94A3B8;">Unavailable</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$reports): ?><tr><td colspan="6" style="padding:30px;color:#94A3B8;text-align:center;">No reports generated yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>