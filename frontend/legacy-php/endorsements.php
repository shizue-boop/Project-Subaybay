<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$user_id = (int) $_SESSION['user_id'];
$is_reviewer = hasRole('principal') || hasRole('coordinator');
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$selected_status = $_GET['status'] ?? 'All';
$status_options = ['Pending', 'Approved', 'Returned for Revision', 'Rejected'];
if ($selected_status !== 'All' && !in_array($selected_status, $status_options, true)) {
    $selected_status = 'All';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_endorsement']) && hasRole('teacher')) {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $studentCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? AND teacher_id = ?");
    $studentCheck->execute([$student_id, $user_id]);
    if (!$studentCheck->fetch()) {
        $_SESSION['error'] = 'Select one of your assigned pupils.';
    } else {
        $document = null;
        if (!empty($_FILES['supporting_document']['name']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($_FILES['supporting_document']['type'], $allowed, true) || $_FILES['supporting_document']['size'] > 10 * 1024 * 1024) {
                $_SESSION['error'] = 'Supporting documents must be PDF, JPG, or PNG files up to 10MB.';
            } else {
                $directory = __DIR__ . '/uploads/endorsements';
                if (!is_dir($directory)) mkdir($directory, 0755, true);
                $extension = strtolower(pathinfo($_FILES['supporting_document']['name'], PATHINFO_EXTENSION));
                $document = 'uploads/endorsements/endorsement_' . $user_id . '_' . time() . '.' . $extension;
                if (!move_uploaded_file($_FILES['supporting_document']['tmp_name'], __DIR__ . '/' . $document)) $document = null;
            }
        }

        if (!isset($_SESSION['error'])) {
            $stmt = $pdo->prepare("INSERT INTO endorsements (student_id, teacher_id, assessment_tool, assessment_date, assessment_result, reading_level, reading_difficulty, teacher_remarks, supporting_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $user_id, trim($_POST['assessment_tool'] ?? ''), $_POST['assessment_date'] ?: null, trim($_POST['assessment_result'] ?? ''), trim($_POST['reading_level'] ?? ''), trim($_POST['reading_difficulty'] ?? ''), trim($_POST['teacher_remarks'] ?? ''), $document]);
            $endorsement_id = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO endorsement_history (endorsement_id, status, changed_by, remarks) VALUES (?, 'Pending', ?, 'Submitted by teacher')")->execute([$endorsement_id, $user_id]);
            $_SESSION['success'] = 'Endorsement submitted for review.';
        }
    }
    header('Location: endorsements.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_endorsement']) && $is_reviewer) {
    $endorsement_id = (int) ($_POST['endorsement_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $allowed_statuses = ['Approved', 'Returned for Revision', 'Rejected'];
    if (in_array($status, $allowed_statuses, true)) {
        $pdo->prepare("UPDATE endorsements SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([$status, $user_id, $endorsement_id]);
        $pdo->prepare("INSERT INTO endorsement_history (endorsement_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)")->execute([$endorsement_id, $status, $user_id, trim($_POST['review_remarks'] ?? '')]);
        $_SESSION['success'] = 'Endorsement status updated.';
    }
    header('Location: endorsements.php');
    exit;
}

if ($is_reviewer) {
    $query = "SELECT e.*, s.full_name, s.grade_level, s.section, u.full_name AS teacher_name FROM endorsements e JOIN students s ON e.student_id = s.id JOIN users u ON e.teacher_id = u.id";
    $params = [];
    if (in_array($selected_status, $status_options, true)) {
        $query .= " WHERE e.status = ?";
        $params[] = $selected_status;
    } else {
        $selected_status = 'All';
    }
    $query .= " ORDER BY FIELD(e.status, 'Pending', 'Returned for Revision', 'Approved', 'Rejected'), e.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $endorsements = $stmt->fetchAll();
    $status_counts = array_fill_keys(array_merge(['All'], $status_options), 0);
    foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM endorsements GROUP BY status") as $status_row) {
        $status_counts[$status_row['status']] = (int) $status_row['total'];
    }
    $students = [];
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, grade_level, section FROM students WHERE teacher_id = ? ORDER BY full_name");
    $stmt->execute([$user_id]);
    $students = $stmt->fetchAll();
    $query = "SELECT e.*, s.full_name, s.grade_level, s.section FROM endorsements e JOIN students s ON e.student_id = s.id WHERE e.teacher_id = ?";
    $params = [$user_id];
    if (in_array($selected_status, $status_options, true)) {
        $query .= " AND e.status = ?";
        $params[] = $selected_status;
    }
    $query .= " ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $endorsements = $stmt->fetchAll();
    $status_counts = array_fill_keys(array_merge(['All'], $status_options), 0);
    foreach ($endorsements as $endorsement) $status_counts[$endorsement['status']]++;
}

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Endorsement &amp; Approval</div>
<div class="page-subtitle">Submit and review pupil reading endorsements.</div>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="search-filter-bar">
    <?php foreach (array_merge(['All'], $status_options) as $status): ?>
        <a href="endorsements.php?status=<?php echo urlencode($status); ?>" class="btn <?php echo $selected_status === $status ? 'btn-primary' : 'btn-secondary'; ?>">
            <?php echo htmlspecialchars($status === 'All' ? 'Add' : $status); ?> (<?php echo $status_counts[$status]; ?>)
        </a>
    <?php endforeach; ?>
</div>

<?php if (hasRole('teacher') && $selected_status === 'All'): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><div class="card-title">Add / Endorse Pupil</div></div>
    <form method="POST" enctype="multipart/form-data">
        <div class="card-body">
            <div class="card-title" style="margin-bottom:12px;">Pupil Information</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Pupil</label><select name="student_id" class="form-select" required><option value="">Select pupil</option><?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name'] . ' - ' . $student['grade_level'] . ' ' . $student['section']); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="card-title" style="margin:20px 0 12px;">Assessment Information</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Assessment Used</label><input name="assessment_tool" class="form-input" required placeholder="Phil-IRI or CRLA-M"></div>
                <div class="form-group"><label class="form-label">Assessment Date</label><input type="date" name="assessment_date" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Assessment Result</label><input name="assessment_result" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Reading Level</label><input name="reading_level" class="form-input" required></div>
            </div>
            <div class="form-group"><label class="form-label">Teacher's Remarks</label><textarea name="teacher_remarks" class="form-input" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Supporting Document</label><input type="file" name="supporting_document" class="form-input" accept=".pdf,.jpg,.jpeg,.png"></div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit" name="submit_endorsement"><i class="fa-solid fa-paper-plane"></i> Submit Endorsement</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><div class="card-title"><?php echo $is_reviewer ? 'Endorsements for Review' : 'My Endorsements'; ?></div><span class="record-count"><?php echo count($endorsements); ?> records</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table"><thead><tr><th>Pupil</th><?php if ($is_reviewer): ?><th>Teacher</th><?php endif; ?><th>Assessment</th><th>Reading Level</th><th>Date</th><th>Status</th><th>Document</th><?php if ($is_reviewer): ?><th>Review</th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($endorsements as $endorsement): ?><tr>
            <td><strong><a href="endorsement_detail.php?id=<?php echo $endorsement['id']; ?>"><?php echo htmlspecialchars($endorsement['full_name']); ?></a></strong><br><small><?php echo htmlspecialchars($endorsement['grade_level'] . ' - ' . $endorsement['section']); ?></small></td>
            <?php if ($is_reviewer): ?><td><?php echo htmlspecialchars($endorsement['teacher_name']); ?></td><?php endif; ?>
            <td><?php echo htmlspecialchars($endorsement['assessment_tool']); ?></td><td><?php echo htmlspecialchars($endorsement['reading_level']); ?></td>
            <td><?php echo $endorsement['assessment_date'] ? date('M d, Y', strtotime($endorsement['assessment_date'])) : '-'; ?></td>
            <td><span class="badge-status <?php echo $endorsement['status'] === 'Approved' ? 'badge-green' : ($endorsement['status'] === 'Pending' ? 'badge-yellow' : 'badge-red'); ?>"><?php echo htmlspecialchars($endorsement['status']); ?></span></td>
            <td><a href="endorsement_detail.php?id=<?php echo $endorsement['id']; ?>">Details</a><?php if ($endorsement['supporting_document']): ?> · <a href="<?php echo htmlspecialchars($endorsement['supporting_document']); ?>" target="_blank" rel="noopener">Document</a><?php endif; ?></td>
            <?php if ($is_reviewer): ?><td><?php if ($endorsement['status'] === 'Pending' || $endorsement['status'] === 'Returned for Revision'): ?><form method="POST" onsubmit="return confirm('Are you sure you want to save this endorsement decision? Your decision and review date will be recorded.');" style="display:flex;gap:4px;align-items:center;"><input type="hidden" name="endorsement_id" value="<?php echo $endorsement['id']; ?>"><select name="status" class="select-input"><option>Approved</option><option>Returned for Revision</option><option>Rejected</option></select><button class="btn btn-sm btn-primary" name="review_endorsement">Save</button></form><?php else: ?>-<?php endif; ?></td><?php endif; ?>
        </tr><?php endforeach; ?>
        <?php if (!$endorsements): ?><tr><td colspan="8" class="text-center" style="padding:40px;color:#94A3B8;">No endorsements found.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php include 'footer.php'; ?>
