<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle add teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $password = password_hash('password', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, section, grade_level, status, joined_date) VALUES (?, ?, ?, 'teacher', ?, ?, 'active', CURDATE())");
    try {
        $stmt->execute([$full_name, $email, $password, $section, $grade_level]);
        $_SESSION['success'] = 'Teacher account created successfully!';
    } catch (PDOException $exception) {
        $_SESSION['error'] = $exception->getCode() === '23000' ? 'That email is already in use.' : 'Teacher account could not be created.';
    }
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_teacher'])) {
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, section = ?, grade_level = ? WHERE id = ? AND role = 'teacher'");
    try {
        $stmt->execute([trim($_POST['full_name']), trim($_POST['email']), trim($_POST['section']), trim($_POST['grade_level']), (int) $_POST['teacher_id']]);
        $_SESSION['success'] = 'Teacher profile updated.';
    } catch (PDOException $exception) {
        $_SESSION['error'] = $exception->getCode() === '23000' ? 'That email is already in use.' : 'Teacher profile could not be updated.';
    }
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_teacher'])) {
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'teacher'");
    $stmt->execute([(int) $_POST['teacher_id']]);
    $_SESSION['success'] = 'Teacher account status updated.';
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_teacher'])) {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'teacher'");
    $stmt->execute([password_hash('password', PASSWORD_DEFAULT), (int) $_POST['teacher_id']]);
    $_SESSION['success'] = 'Teacher account reset. Temporary password: password';
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_principal'])) {
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ? AND role = 'principal'");
    try {
        $stmt->execute([trim($_POST['full_name']), trim($_POST['email']), (int) $_POST['principal_id']]);
        $_SESSION['success'] = 'Principal account updated.';
    } catch (PDOException $exception) {
        $_SESSION['error'] = $exception->getCode() === '23000' ? 'That email is already in use.' : 'Principal account could not be updated.';
    }
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_principal'])) {
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'principal'");
    $stmt->execute([(int) $_POST['principal_id']]);
    $_SESSION['success'] = 'Principal account status updated.';
    header("Location: teacher_accounts.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_principal'])) {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'principal'");
    $stmt->execute([password_hash('password', PASSWORD_DEFAULT), (int) $_POST['principal_id']]);
    $_SESSION['success'] = 'Principal account reset. Temporary password: password';
    header("Location: teacher_accounts.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'")->execute([$id]);
    $_SESSION['success'] = 'Teacher account deleted.';
    header("Location: teacher_accounts.php");
    exit();
}

// Fetch teachers with student count
$teachers = $pdo->query("
    SELECT u.*, COUNT(s.id) as student_count 
    FROM users u
    LEFT JOIN students s ON u.id = s.teacher_id
    WHERE u.role = 'teacher'
    GROUP BY u.id
    ORDER BY u.joined_date DESC
")->fetchAll();
$principal = $pdo->query("SELECT id, full_name, email, status, joined_date FROM users WHERE role = 'principal' ORDER BY id LIMIT 1")->fetch();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Teacher Accounts</div>
<div class="page-subtitle">Manage teacher accounts and assignments</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="grid-2" style="margin-bottom:24px;">
    <div class="card"><div class="card-header"><div class="card-title">Principal Account</div></div><div class="card-body">
        <?php if ($principal): ?>
            <p><strong><?php echo htmlspecialchars($principal['full_name']); ?></strong><br><?php echo htmlspecialchars($principal['email']); ?></p>
            <p><span class="badge-status <?php echo $principal['status'] === 'active' ? 'badge-green' : 'badge-red'; ?>"><?php echo ucfirst($principal['status']); ?></span></p>
            <div class="table-actions">
                <button class="btn-table-action btn-edit" onclick="editPrincipal(<?php echo htmlspecialchars(json_encode($principal), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit principal account"><i class="fa-solid fa-pen"></i></button>
                <form method="POST" style="display:inline"><input type="hidden" name="principal_id" value="<?php echo $principal['id']; ?>"><button class="btn-table-action" name="toggle_principal" title="Activate or deactivate principal account"><i class="fa-solid fa-power-off"></i></button></form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Reset the principal account password?')"><input type="hidden" name="principal_id" value="<?php echo $principal['id']; ?>"><button class="btn-table-action" name="reset_principal" title="Reset principal password"><i class="fa-solid fa-key"></i></button></form>
            </div>
        <?php else: ?>
            <p>No principal account was found.</p>
        <?php endif; ?>
    </div></div>
    <div class="card"><div class="card-header"><div class="card-title">Account Management</div></div><div class="card-body"><p>Add, edit, activate/deactivate, and reset teacher accounts.</p><button class="btn btn-primary" onclick="openModal('addTeacherModal')"><i class="fa-solid fa-user-plus"></i> Add User</button></div></div>
</div>

<div class="search-filter-bar">
    <input type="text" class="search-input" placeholder="Search teachers..." onkeyup="searchTable('teacherSearch', 'teacherTable')">
    <button class="btn btn-primary" onclick="openModal('addTeacherModal')">
        <i class="fa-solid fa-plus"></i> Add Teacher Account
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Teacher Accounts</div>
        <span class="record-count"><?php echo count($teachers); ?> total</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table" id="teacherTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Section</th>
                    <th>Students</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): 
                    $statusClass = $teacher['status'] === 'active' ? 'badge-green' : 'badge-red';
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $teacher['full_name'])));
                    if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="pupil-avatar"><?php echo $initials; ?></div>
                            <a href="teacher_profile.php?id=<?php echo $teacher['id']; ?>"><strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></a>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                    <td><?php echo htmlspecialchars($teacher['section'] ?? 'N/A'); ?></td>
                    <td><?php echo $teacher['student_count']; ?></td>
                    <td><span class="badge-status <?php echo $statusClass; ?>"><?php echo ucfirst($teacher['status']); ?></span></td>
                    <td><?php echo date('M Y', strtotime($teacher['joined_date'])); ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-table-action btn-edit" onclick="editTeacher(<?php echo htmlspecialchars(json_encode($teacher), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit user"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" style="display:inline"><input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>"><button class="btn-table-action" name="toggle_teacher" title="Activate or deactivate"><i class="fa-solid fa-power-off"></i></button></form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Reset this account password?')"><input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>"><button class="btn-table-action" name="reset_teacher" title="Reset account"><i class="fa-solid fa-key"></i></button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editTeacherModal" class="modal"><div class="modal-header"><div class="modal-title">Edit Teacher</div><button class="modal-close" onclick="closeModal('editTeacherModal')">&times;</button></div><form method="POST"><div class="modal-body"><input type="hidden" name="teacher_id" id="editTeacherId"><div class="form-group"><label class="form-label">Name</label><input class="form-input" name="full_name" id="editTeacherName" required></div><div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" name="email" id="editTeacherEmail" required></div><div class="form-row"><div class="form-group"><label class="form-label">Grade / Section</label><input class="form-input" name="section" id="editTeacherSection"></div><div class="form-group"><label class="form-label">Grade Level</label><input class="form-input" name="grade_level" id="editTeacherGrade"></div></div></div><div class="modal-footer"><button type="submit" name="edit_teacher" class="btn btn-primary">Save Changes</button></div></form></div>

<div id="editPrincipalModal" class="modal"><div class="modal-header"><div class="modal-title">Edit Principal Account</div><button class="modal-close" onclick="closeModal('editPrincipalModal')">&times;</button></div><form method="POST"><div class="modal-body"><input type="hidden" name="principal_id" id="editPrincipalId"><div class="form-group"><label class="form-label">Name</label><input class="form-input" name="full_name" id="editPrincipalName" required></div><div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" name="email" id="editPrincipalEmail" required></div></div><div class="modal-footer"><button type="submit" name="edit_principal" class="btn btn-primary">Save Changes</button></div></form></div>

<script>
function editTeacher(teacher) {
    document.getElementById('editTeacherId').value = teacher.id;
    document.getElementById('editTeacherName').value = teacher.full_name;
    document.getElementById('editTeacherEmail').value = teacher.email;
    document.getElementById('editTeacherSection').value = teacher.section || '';
    document.getElementById('editTeacherGrade').value = teacher.grade_level || '';
    openModal('editTeacherModal');
}

function editPrincipal(principal) {
    document.getElementById('editPrincipalId').value = principal.id;
    document.getElementById('editPrincipalName').value = principal.full_name;
    document.getElementById('editPrincipalEmail').value = principal.email;
    openModal('editPrincipalModal');
}
</script>

<!-- Add Teacher Modal -->
<div id="addTeacherModal" class="modal">
    <div class="modal-header">
        <div class="modal-title">Add Teacher Account</div>
        <button class="modal-close" onclick="closeModal('addTeacherModal')">&times;</button>
    </div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Section</label>
                    <input type="text" name="section" class="form-input" placeholder="e.g. Grade 2-A">
                </div>
                <div class="form-group">
                    <label class="form-label">Grade Level</label>
                    <input type="text" name="grade_level" class="form-input" placeholder="e.g. Grade 2">
                </div>
            </div>
            <p style="font-size: 12px; color: var(--text-muted);">Default password will be: <strong>password</strong></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addTeacherModal')">Cancel</button>
            <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
