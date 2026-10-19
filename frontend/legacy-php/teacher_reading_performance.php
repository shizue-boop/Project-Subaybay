<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

$assessments = $pdo->prepare("
    SELECT a.*, s.full_name, s.section
    FROM assessments a
    JOIN students s ON a.student_id = s.id
    WHERE s.teacher_id = ?
    ORDER BY a.assessment_date DESC
");
$assessments->execute([$teacher_id]);
$myAssessments = $assessments->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Reading Performance</div>
<div class="page-subtitle">View your pupils' assessment records</div>

<div class="search-filter-bar">
    <input type="text" class="search-input" placeholder="Search by pupil name...">
    <select class="select-input"><option>All Profiles</option></select>
    <button class="btn btn-primary" onclick="alert('Log assessment via coordinator')"><i class="fa-solid fa-plus"></i> Log Assessment</button>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Assessment Records</div><span class="record-count"><?php echo count($myAssessments); ?> records</span></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Pupil</th><th>Section</th><th>Tool</th><th>Score</th><th>Profile</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($myAssessments as $a): 
                    $badgeClass = match($a['profile']) {
                        'Independent','Grade Ready' => 'badge-green',
                        'Instructional','Light Refresher' => 'badge-blue',
                        'Frustration','Full Refresher' => 'badge-red',
                        default => 'badge-yellow'
                    };
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($a['full_name']); ?></strong></td>
                    <td><?php echo $a['section']; ?></td>
                    <td><?php echo $a['tool']; ?></td>
                    <td><?php echo $a['total_score']; ?>/30</td>
                    <td><span class="badge-status <?php echo $badgeClass; ?>"><?php echo $a['profile']; ?></span></td>
                    <td><?php echo date('M d, Y',strtotime($a['assessment_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>