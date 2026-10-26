<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$endorsement_id = (int) ($_GET['id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$is_reviewer = hasRole('principal') || hasRole('coordinator');

$query = "SELECT e.*, s.full_name, s.lrn, s.grade_level, s.section, s.profile_image, t.full_name AS teacher_name, r.full_name AS reviewer_name FROM endorsements e JOIN students s ON e.student_id = s.id JOIN users t ON e.teacher_id = t.id LEFT JOIN users r ON e.reviewed_by = r.id WHERE e.id = ?";
$params = [$endorsement_id];
if (!$is_reviewer) {
    $query .= " AND e.teacher_id = ?";
    $params[] = $user_id;
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$endorsement = $stmt->fetch();
if (!$endorsement) {
    http_response_code(404);
    exit('Endorsement not found or you are not authorized to view it.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_endorsement']) && $is_reviewer) {
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['Approved', 'Returned for Revision', 'Rejected'], true)) {
        $pdo->prepare("UPDATE endorsements SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([$status, $user_id, $endorsement_id]);
        $pdo->prepare("INSERT INTO endorsement_history (endorsement_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)")->execute([$endorsement_id, $status, $user_id, trim($_POST['review_remarks'] ?? '')]);
        header('Location: endorsement_detail.php?id=' . $endorsement_id);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT eh.*, u.full_name AS changed_by_name FROM endorsement_history eh JOIN users u ON eh.changed_by = u.id WHERE eh.endorsement_id = ? ORDER BY eh.created_at DESC, eh.id DESC");
$stmt->execute([$endorsement_id]);
$history = $stmt->fetchAll();
$status_class = $endorsement['status'] === 'Approved' ? 'badge-green' : ($endorsement['status'] === 'Pending' ? 'badge-yellow' : 'badge-red');
$format_date = fn($date) => $date ? date('M d, Y', strtotime($date)) : '-';

include 'header.php';
include 'sidebar.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px;">
    <div><div class="page-title">Endorsement Details</div><div class="page-subtitle">Review the complete pupil endorsement record.</div></div>
    <a href="endorsements.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Endorsements</a>
</div>

<div class="card" style="margin-bottom:24px;"><div class="card-header"><div class="card-title">Endorsement Status</div><span class="badge-status <?php echo $status_class; ?>"><?php echo htmlspecialchars($endorsement['status']); ?></span></div><div class="card-body"><div class="grid-2"><div><p><strong>Pupil:</strong> <a href="pupil_profile.php?id=<?php echo $endorsement['student_id']; ?>"><?php echo htmlspecialchars($endorsement['full_name']); ?></a></p><p><strong>LRN:</strong> <?php echo htmlspecialchars($endorsement['lrn']); ?></p><p><strong>Grade / Section:</strong> <?php echo htmlspecialchars($endorsement['grade_level'] . ' - ' . $endorsement['section']); ?></p></div><div><p><strong>Teacher:</strong> <?php echo htmlspecialchars($endorsement['teacher_name']); ?></p><p><strong>Submitted:</strong> <?php echo $format_date($endorsement['created_at']); ?></p><p><strong>Reviewed By:</strong> <?php echo htmlspecialchars($endorsement['reviewer_name'] ?? '-'); ?></p><p><strong>Date Reviewed:</strong> <?php echo $format_date($endorsement['reviewed_at']); ?></p></div></div></div></div>

<div class="grid-2">
    <div class="card"><div class="card-header"><div class="card-title">Pupil Information</div></div><div class="card-body"><p><strong>Full Name:</strong> <?php echo htmlspecialchars($endorsement['full_name']); ?></p><p><strong>Grade Level:</strong> <?php echo htmlspecialchars($endorsement['grade_level']); ?></p><p><strong>Section:</strong> <?php echo htmlspecialchars($endorsement['section']); ?></p></div></div>
    <div class="card"><div class="card-header"><div class="card-title">Teacher Information</div></div><div class="card-body"><p><strong>Teacher:</strong> <?php echo htmlspecialchars($endorsement['teacher_name']); ?></p><p><strong>Assessment Used:</strong> <?php echo htmlspecialchars($endorsement['assessment_tool']); ?></p><p><strong>Assessment Date:</strong> <?php echo $format_date($endorsement['assessment_date']); ?></p></div></div>
</div>

<div class="card"><div class="card-header"><div class="card-title">Assessment Result</div></div><div class="card-body"><p><strong>Assessment Result:</strong> <?php echo htmlspecialchars($endorsement['assessment_result']); ?></p><p><strong>Reading Level:</strong> <?php echo htmlspecialchars($endorsement['reading_level']); ?></p><p><strong>Reason for Endorsement:</strong><br><?php echo nl2br(htmlspecialchars($endorsement['reading_difficulty'] ?: 'Not provided')); ?></p><p><strong>Teacher's Remarks:</strong><br><?php echo nl2br(htmlspecialchars($endorsement['teacher_remarks'] ?: 'Not provided')); ?></p><p><strong>Supporting Document:</strong> <?php if ($endorsement['supporting_document']): ?><a href="<?php echo htmlspecialchars($endorsement['supporting_document']); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-down"></i> View / Download</a><?php else: ?>Not provided<?php endif; ?></p></div></div>

<?php if ($is_reviewer && in_array($endorsement['status'], ['Pending', 'Returned for Revision'], true)): ?><div class="card"><div class="card-header"><div class="card-title">Review Endorsement</div></div><form method="POST" onsubmit="return confirm('Are you sure you want to save this endorsement decision? Your decision and review date will be recorded.');"><div class="card-body"><div class="form-group"><label class="form-label">Decision</label><select name="status" class="form-select" required><option value="Approved">Approved</option><option value="Returned for Revision">Returned for Revision</option><option value="Rejected">Rejected</option></select></div><div class="form-group"><label class="form-label">Review Remarks</label><textarea name="review_remarks" class="form-input" rows="3"></textarea></div></div><div class="card-footer endorsement-review-actions"><button type="submit" name="review_endorsement" class="btn btn-primary">Save Decision</button></div></form></div><?php endif; ?>

<div class="card"><div class="card-header"><div class="card-title">Endorsement History</div></div><div class="card-body"><table class="data-table"><thead><tr><th>Date</th><th>Status</th><th>Changed By</th><th>Remarks</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?php echo $format_date($entry['created_at']); ?></td><td><?php echo htmlspecialchars($entry['status']); ?></td><td><?php echo htmlspecialchars($entry['changed_by_name']); ?></td><td><?php echo htmlspecialchars($entry['remarks'] ?: '-'); ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="4" style="padding:30px;color:#94A3B8;">No history recorded.</td></tr><?php endif; ?></tbody></table></div></div>

<?php include 'footer.php'; ?>
