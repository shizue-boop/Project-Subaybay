<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();
if (!hasRole('principal') && !hasRole('coordinator')) { header('Location: dashboard.php'); exit; }

$teacher_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT u.*, COUNT(s.id) AS assigned_pupils FROM users u LEFT JOIN students s ON s.teacher_id = u.id WHERE u.id = ? AND u.role = 'teacher' GROUP BY u.id");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();
if (!$teacher) { http_response_code(404); exit('Teacher account not found.'); }
$stmt = $pdo->prepare("SELECT id, full_name, lrn, grade_level, section, reading_profile FROM students WHERE teacher_id = ? ORDER BY full_name");
$stmt->execute([$teacher_id]);
$pupils = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px;"><div><div class="page-title">Teacher Profile</div><div class="page-subtitle">Employee and assigned-pupil information.</div></div><a href="teacher_accounts.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to User Management</a></div>
<div class="grid-2"><div class="card"><div class="card-header"><div class="card-title">Teacher Information</div><span class="badge-status <?php echo $teacher['status'] === 'active' ? 'badge-green' : 'badge-red'; ?>"><?php echo ucfirst($teacher['status']); ?></span></div><div class="card-body"><p><strong>Name:</strong> <?php echo htmlspecialchars($teacher['full_name']); ?></p><p><strong>Employee Information:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p><p><strong>Grade / Section:</strong> <?php echo htmlspecialchars(($teacher['grade_level'] ?? '-') . ' / ' . ($teacher['section'] ?? '-')); ?></p><p><strong>Account Status:</strong> <?php echo htmlspecialchars(ucfirst($teacher['status'])); ?></p><p><strong>Joined:</strong> <?php echo $teacher['joined_date'] ? date('M d, Y', strtotime($teacher['joined_date'])) : '-'; ?></p></div></div><div class="card"><div class="card-header"><div class="card-title">Assigned Pupils</div><span class="record-count"><?php echo count($pupils); ?> pupils</span></div><div class="card-body" style="padding:0;overflow-x:auto;"><table class="data-table"><thead><tr><th>Pupil</th><th>LRN</th><th>Grade / Section</th><th>Reading Level</th></tr></thead><tbody><?php foreach ($pupils as $pupil): ?><tr><td><a href="pupil_profile.php?id=<?php echo $pupil['id']; ?>"><?php echo htmlspecialchars($pupil['full_name']); ?></a></td><td><?php echo htmlspecialchars($pupil['lrn']); ?></td><td><?php echo htmlspecialchars($pupil['grade_level'] . ' - ' . $pupil['section']); ?></td><td><?php echo htmlspecialchars($pupil['reading_profile'] ?? 'Not assessed'); ?></td></tr><?php endforeach; ?><?php if (!$pupils): ?><tr><td colspan="4" style="padding:30px;color:#94A3B8;text-align:center;">No assigned pupils.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php include 'footer.php'; ?>