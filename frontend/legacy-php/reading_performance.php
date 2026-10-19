<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}
$isCoordinator = hasRole('coordinator');

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Handle log assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_assessment'])) {
    if (!$isCoordinator) {
        $_SESSION['error'] = 'Only the reading coordinator can change student reading levels.';
        header("Location: reading_performance.php");
        exit();
    }

    $student_id = intval($_POST['student_id'] ?? 0);
    $tool = $_POST['tool'] ?? 'CRLA-M';
    $task1 = intval($_POST['task1_score'] ?? 0);
    $words = intval($_POST['words_score'] ?? 0);
    $sentences = intval($_POST['sentences_score'] ?? 0);
    $total = $task1 + $words + $sentences;
    $profile = $_POST['profile'] ?? 'Grade Ready';
    $date = $_POST['assessment_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO assessments (student_id, tool, task1_score, words_score, sentences_score, total_score, profile, assessment_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $tool, $task1, $words, $sentences, $total, $profile, $date, $_SESSION['user_id']]);

    // Update student profile
    $pdo->prepare("UPDATE students SET reading_profile = ?, score = ? WHERE id = ?")
        ->execute([$profile, $total, $student_id]);

    $_SESSION['success'] = 'Assessment logged successfully!';
    header("Location: reading_performance.php");
    exit();
}

// Fetch assessments
$assessments = $pdo->query("
    SELECT a.*, s.full_name, s.section, s.grade_level
    FROM assessments a
    JOIN students s ON a.student_id = s.id
    ORDER BY a.assessment_date DESC
    LIMIT 20
")->fetchAll();

// Fetch students for dropdown
$students = $pdo->query("SELECT id, full_name, section FROM students ORDER BY full_name")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Reading Performance</div>
<div class="page-subtitle">Track and manage student reading assessments</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="search-filter-bar">
    <input type="text" class="search-input" placeholder="Search by student name..." onkeyup="searchTable('assessSearch', 'assessTable')">
    <select class="select-input">
        <option>All Profiles</option>
        <option>Grade Ready</option>
        <option>Light Refresher</option>
        <option>Moderate Refresher</option>
        <option>Full Refresher</option>
    </select>
    <?php if ($isCoordinator): ?>
        <button class="btn btn-primary" onclick="openModal('logAssessmentModal')">
            <i class="fa-solid fa-plus"></i> Log Assessment
        </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Assessment Records (CRLA-M)</div>
        <span class="record-count"><?php echo count($assessments); ?> records</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table" id="assessTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Section</th>
                    <th>Task 1</th>
                    <th>Words</th>
                    <th>Sentences</th>
                    <th>Total</th>
                    <th>Profile</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessments as $a): 
                    $profileClass = match($a['profile']) {
                        'Grade Ready', 'Independent' => 'badge-green',
                        'Light Refresher', 'Instructional' => 'badge-blue',
                        'Moderate Refresher' => 'badge-yellow',
                        'Full Refresher', 'Frustration' => 'badge-red',
                        default => 'badge-blue'
                    };
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($a['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($a['section']); ?></td>
                    <td><?php echo $a['task1_score']; ?>/10</td>
                    <td><?php echo $a['words_score']; ?>/10</td>
                    <td><?php echo $a['sentences_score']; ?>/10</td>
                    <td><strong><?php echo $a['total_score']; ?>/30</strong></td>
                    <td><span class="badge-status <?php echo $profileClass; ?>"><?php echo $a['profile']; ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($a['assessment_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isCoordinator): ?>
<!-- Log Assessment Modal -->
<div id="logAssessmentModal" class="modal">
    <div class="modal-header">
        <div class="modal-title">Log New Assessment</div>
        <button class="modal-close" onclick="closeModal('logAssessmentModal')">&times;</button>
    </div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">Select student</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name'] . ' - ' . $s['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tool</label>
                    <select name="tool" class="form-select">
                        <option>CRLA-M</option>
                        <option>Phil-IRI</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="assessment_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Task 1</label>
                    <input type="number" name="task1_score" class="form-input" max="10" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Words</label>
                    <input type="number" name="words_score" class="form-input" max="10" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Sentences</label>
                    <input type="number" name="sentences_score" class="form-input" max="10" min="0" value="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Reading Profile</label>
                <select name="profile" class="form-select">
                    <option>Grade Ready</option>
                    <option>Light Refresher</option>
                    <option>Moderate Refresher</option>
                    <option>Full Refresher</option>
                    <option>Independent</option>
                    <option>Instructional</option>
                    <option>Frustration</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('logAssessmentModal')">Cancel</button>
            <button type="submit" name="log_assessment" class="btn btn-primary">Save Assessment</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>