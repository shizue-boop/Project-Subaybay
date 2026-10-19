<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_image'");
    if ($checkCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER date_enrolled");
    }
} catch (Exception $e) {
    // Ignore if column exists
}

$teacher_id = $_SESSION['user_id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$students = $pdo->prepare("SELECT s.*, DATE_FORMAT(MAX(a.assessment_date), '%b %d, %Y') AS last_assessment FROM students s LEFT JOIN assessments a ON a.student_id = s.id WHERE s.teacher_id = ? GROUP BY s.id ORDER BY s.full_name");
$students->execute([$teacher_id]);
$myStudents = $students->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">My Pupils</div>
<div class="page-subtitle">View and manage your assigned pupils</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="search-filter-bar">
    <input type="text" class="search-input" placeholder="Search pupils..." onkeyup="searchTable('pupilSearch','pupilTable')">
    <select class="select-input"><option>All Levels</option><option>Independent</option><option>Instructional</option><option>Frustration</option></select>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Assigned Pupils</div><span class="record-count"><?php echo count($myStudents); ?> pupils</span></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table" id="pupilTable">
            <thead><tr><th>Name</th><th>LRN</th><th>Grade & Section</th><th>Reading Level</th><th>Score</th><th>Last Assessment</th></tr></thead>
            <tbody>
                <?php foreach ($myStudents as $s): 
                    $badgeClass = match($s['reading_profile']) {
                        'Independent','Grade Ready' => 'badge-green',
                        'Instructional','Light Refresher' => 'badge-blue',
                        'Frustration','Full Refresher' => 'badge-red',
                        default => 'badge-yellow'
                    };
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $s['full_name'])));
                    if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($s['profile_image']) && file_exists($s['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($s['profile_image']); ?>" alt="Student profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #E2E8F0;">
                            <?php else: ?>
                                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#3B82F6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php endif; ?>
                            <a href="pupil_profile.php?id=<?php echo $s['id']; ?>" style="font-weight:600;"><?php echo htmlspecialchars($s['full_name']); ?></a>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($s['lrn']); ?></td>
                    <td><?php echo htmlspecialchars($s['grade_level'].' - '.$s['section']); ?></td>
                    <td><span class="badge-status <?php echo $badgeClass; ?>"><?php echo $s['reading_profile'] ?? 'Not Assessed'; ?></span></td>
                    <td><?php echo $s['score']; ?>%</td>
                    <td><?php echo $s['last_assessment'] ?? '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($myStudents)): ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding:40px; color:#94A3B8;">No pupils assigned yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>