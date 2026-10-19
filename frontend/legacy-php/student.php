<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_image'");
    if ($checkCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER date_enrolled");
    }
} catch (Exception $e) {
    // Ignore if already exists
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$isCoordinator = hasRole('coordinator');

// Handle coordinator-only student profile updates and teacher assignment.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    if (!$isCoordinator) {
        $_SESSION['error'] = 'Only the reading coordinator can edit student profiles.';
        header("Location: students.php");
        exit();
    }

    $student_id = (int) ($_POST['student_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $date_enrolled = $_POST['date_enrolled'] ?? null;
    $parent_guardian = trim($_POST['parent_guardian'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $teacher_id = (int) ($_POST['teacher_id'] ?? 0);

    if ($student_id < 1 || $full_name === '' || $lrn === '' || $section === '' || $grade_level === '') {
        $_SESSION['error'] = 'Please fill in all required student fields.';
        header("Location: students.php");
        exit();
    }

    $teacherCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
    $teacherCheck->execute([$teacher_id]);
    if (!$teacherCheck->fetch()) {
        $_SESSION['error'] = 'Please select an active teacher.';
        header("Location: students.php");
        exit();
    }

    $duplicateCheck = $pdo->prepare("SELECT id FROM students WHERE lrn = ? AND id <> ?");
    $duplicateCheck->execute([$lrn, $student_id]);
    if ($duplicateCheck->fetch()) {
        $_SESSION['error'] = 'This LRN is already registered to another student.';
        header("Location: students.php");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE students SET lrn = ?, full_name = ?, section = ?, grade_level = ?, teacher_id = ?, date_enrolled = ?, parent_guardian = ?, contact_number = ? WHERE id = ?");
    $stmt->execute([$lrn, $full_name, $section, $grade_level, $teacher_id, $date_enrolled ?: null, $parent_guardian ?: null, $contact_number ?: null, $student_id]);
    $_SESSION['success'] = 'Student profile and teacher assignment updated.';
    header("Location: students.php");
    exit();
}

// Handle add student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $date_enrolled = $_POST['date_enrolled'] ?? date('Y-m-d');
    $profile_image = null;

    if (empty($full_name) || empty($lrn) || empty($section) || empty($grade_level)) {
        $_SESSION['error'] = 'Please fill in all required student fields.';
        header("Location: students.php");
        exit();
    }

    $check = $pdo->prepare("SELECT id FROM students WHERE lrn = ?");
    $check->execute([$lrn]);
    if ($check->fetch()) {
        $_SESSION['error'] = 'This LRN is already registered.';
        header("Location: students.php");
        exit();
    }

    if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        if ($file['error'] === UPLOAD_ERR_OK) {
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = 'Only JPG, PNG, GIF, and WebP images are allowed.';
                header("Location: students.php");
                exit();
            }

            if ($file['size'] > $max_size) {
                $_SESSION['error'] = 'Student image must be 5MB or less.';
                header("Location: students.php");
                exit();
            }

            $uploads_dir = 'uploads/students';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($full_name)) . '.' . $ext;
            $target = $uploads_dir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $_SESSION['error'] = 'Failed to upload student image.';
                header("Location: students.php");
                exit();
            }

            $profile_image = $target;
        }
    }

    // Find teacher for section
    $teacherStmt = $pdo->prepare("SELECT id FROM users WHERE section = ? AND role = 'teacher' AND status = 'active' LIMIT 1");
    $teacherStmt->execute([$section]);
    $teacher = $teacherStmt->fetch();
    $teacher_id = $teacher ? $teacher['id'] : null;

    $stmt = $pdo->prepare("INSERT INTO students (lrn, full_name, section, grade_level, teacher_id, date_enrolled, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$lrn, $full_name, $section, $grade_level, $teacher_id, $date_enrolled, $profile_image]);
    $_SESSION['success'] = 'Student enrolled successfully!';
    header("Location: students.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = 'Student removed.';
    header("Location: students.php");
    exit();
}

// Fetch all students with teacher info
$students = $pdo->query("
    SELECT s.*, u.full_name as teacher_name,
    CASE 
        WHEN s.reading_profile IN ('Grade Ready','Independent') THEN 'progress-green'
        WHEN s.reading_profile IN ('Light Refresher','Instructional') THEN 'progress-yellow'
        ELSE 'progress-red'
    END as progress_class
    FROM students s
    LEFT JOIN users u ON s.teacher_id = u.id
    ORDER BY s.grade_level, s.section, s.full_name
")->fetchAll();

$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Student List</div>
<div class="page-subtitle">Manage enrolled students</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="search-filter-bar">
    <input type="text" class="search-input" placeholder="Search by name or LRN..." onkeyup="searchTable('studentSearch', 'studentTable')">
    <select class="select-input">
        <option>All Sections</option>
        <option>Grade 1-A</option>
        <option>Grade 1-B</option>
        <option>Grade 2-A</option>
        <option>Grade 2-B</option>
        <option>Grade 3-A</option>
        <option>Grade 3-B</option>
    </select>
    <button type="button" class="btn btn-primary" onclick="openModal('enrollModal')">
        <i class="fa-solid fa-plus"></i> Add Student
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">All Students</div>
        <span class="record-count"><?php echo count($students); ?> enrolled</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table" id="studentTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>LRN</th>
                    <th>Section</th>
                    <th>Teacher</th>
                    <th>Reading Profile</th>
                    <th>Score</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): 
                    $profileClass = match($student['reading_profile']) {
                        'Grade Ready', 'Independent' => 'badge-green',
                        'Light Refresher', 'Instructional' => 'badge-blue',
                        'Moderate Refresher' => 'badge-yellow',
                        'Full Refresher', 'Frustration' => 'badge-red',
                        default => 'badge-blue'
                    };
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $student['full_name'])));
                    if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($student['profile_image']) && file_exists($student['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Student profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #E2E8F0;">
                            <?php else: ?>
                                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#3B82F6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php endif; ?>
                            <a href="pupil_profile.php?id=<?php echo $student['id']; ?>" style="font-weight:600;"><?php echo htmlspecialchars($student['full_name']); ?></a>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                    <td><?php echo htmlspecialchars($student['section']); ?></td>
                    <td><?php echo htmlspecialchars($student['teacher_name'] ?? 'Unassigned'); ?></td>
                    <td><span class="badge-status <?php echo $profileClass; ?>"><?php echo $student['reading_profile'] ?? 'Not Assessed'; ?></span></td>
                    <td>
                        <div class="progress-display">
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $student['progress_class']; ?>" style="width: <?php echo min(100, $student['score']); ?>%"></div>
                            </div>
                            <span style="font-size: 12px; font-weight: 600;"><?php echo $student['score']; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="pupil_profile.php?id=<?php echo $student['id']; ?>" class="btn-table-action" title="View pupil profile"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($isCoordinator): ?>
                                <button class="btn-table-action btn-edit" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit student profile and assign teacher"><i class="fa-solid fa-pen"></i></button>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $student['id']; ?>" class="btn-table-action btn-delete" onclick="return confirmDelete()"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isCoordinator): ?>
<div id="editStudentModal" class="modal">
    <div class="modal-header">
        <div class="modal-title">Edit Student Profile</div>
        <button type="button" class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
    </div>
    <form method="POST" action="">
        <div class="modal-body">
            <input type="hidden" name="student_id" id="editStudentId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="editStudentName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">LRN</label>
                    <input type="text" name="lrn" id="editStudentLrn" class="form-input" maxlength="30" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Section</label>
                    <input type="text" name="section" id="editStudentSection" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Grade Level</label>
                    <input type="text" name="grade_level" id="editStudentGrade" class="form-input" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned Teacher</label>
                <select name="teacher_id" id="editStudentTeacher" class="form-select" required>
                    <option value="">Select teacher</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo (int) $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date Enrolled</label>
                    <input type="date" name="date_enrolled" id="editStudentDate" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" id="editStudentContact" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Parent/Guardian</label>
                <input type="text" name="parent_guardian" id="editStudentGuardian" class="form-input">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
            <button type="submit" name="edit_student" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<script>
function editStudent(student) {
    document.getElementById('editStudentId').value = student.id;
    document.getElementById('editStudentName').value = student.full_name || '';
    document.getElementById('editStudentLrn').value = student.lrn || '';
    document.getElementById('editStudentSection').value = student.section || '';
    document.getElementById('editStudentGrade').value = student.grade_level || '';
    document.getElementById('editStudentTeacher').value = student.teacher_id || '';
    document.getElementById('editStudentDate').value = student.date_enrolled || '';
    document.getElementById('editStudentContact').value = student.contact_number || '';
    document.getElementById('editStudentGuardian').value = student.parent_guardian || '';
    openModal('editStudentModal');
}
</script>
<?php endif; ?>

<!-- Enroll Modal -->
<div id="enrollModal" class="modal">
    <div class="modal-header">
        <div class="modal-title">Enroll New Student</div>
        <button type="button" class="modal-close" onclick="closeModal('enrollModal')">&times;</button>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="Last, First Middle" required>
                </div>
                <div class="form-group">
                    <label class="form-label">LRN</label>
                    <input type="text" name="lrn" class="form-input" placeholder="12-digit LRN" maxlength="12" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Assigned Section</label>
                    <select name="section" class="form-select" required>
                        <option value="">Select section</option>
                        <option>Grade 1-A</option>
                        <option>Grade 1-B</option>
                        <option>Grade 2-A</option>
                        <option>Grade 2-B</option>
                        <option>Grade 3-A</option>
                        <option>Grade 3-B</option>
                        <option>Grade 4 - Bonifacio</option>
                        <option>Grade 4 - Rizal</option>
                        <option>Grade 5</option>
                        <option>Grade 6</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Grade Level</label>
                    <select name="grade_level" class="form-select" required>
                        <option value="">Select grade</option>
                        <option>Grade 1</option>
                        <option>Grade 2</option>
                        <option>Grade 3</option>
                        <option>Grade 4</option>
                        <option>Grade 5</option>
                        <option>Grade 6</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Date Enrolled</label>
                <input type="date" name="date_enrolled" class="form-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Profile Image</label>
                <input type="file" name="profile_image" class="form-input" accept="image/*">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('enrollModal')">Cancel</button>
            <button type="submit" name="enroll_student" class="btn btn-primary">Enroll Student</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>