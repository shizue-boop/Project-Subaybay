<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$material_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT lm.*, u.full_name AS uploader_name FROM learning_materials lm LEFT JOIN users u ON lm.uploaded_by = u.id WHERE lm.id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();
if (!$material) {
    http_response_code(404);
    exit('Learning material not found.');
}

$is_admin = hasRole('principal') || hasRole('coordinator');
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if (
    (isset($_GET['download']) || isset($_GET['view'])) &&
    $material['file_path'] &&
    is_file(__DIR__ . '/' . $material['file_path'])
) {
    $path = __DIR__ . '/' . $material['file_path'];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mp3' => 'audio/mpeg',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $inline = isset($_GET['view']) && $extension === 'pdf';
    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_material']) && $is_admin) {
    $title = trim($_POST['title'] ?? '');
    $gradeLevel = trim($_POST['grade_level'] ?? '');
    $readingLevel = trim($_POST['reading_level'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $alignmentPart = trim($_POST['alignment_part'] ?? '');
    $skillFocus = trim($_POST['skill_focus'] ?? '');
    $alignmentParts = [
        'General Reading Practice',
        'Part 1 - Foundational Skills',
        'Part 2 - Fluency & Comprehension',
    ];
    $skillFocuses = [
        'General Reading Practice',
        'Alphabet Knowledge',
        'Phonological Awareness',
        'Decoding & Word Recognition',
        'Sentence Reading',
        'Reading Fluency',
        'Accuracy & Pacing',
        'Prosody',
        'Reading Comprehension',
        'Teacher Guidance',
    ];
    $readingProfiles = [
        'All Profiles',
        'Low Emerging Reader',
        'High Emerging Reader',
        'Developing Reader',
        'Transitioning Reader',
        'Reading At Grade Level',
    ];
    if (
        $title === '' || mb_strlen($title) > 200 ||
        !in_array($gradeLevel, ['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'], true) ||
        !in_array($readingLevel, $readingProfiles, true) ||
        $language === '' || mb_strlen($language) > 80 ||
        !in_array($alignmentPart, $alignmentParts, true) ||
        !in_array($skillFocus, $skillFocuses, true) ||
        ($alignmentPart === 'Part 1 - Foundational Skills' && !in_array($skillFocus, ['Alphabet Knowledge', 'Phonological Awareness', 'Decoding & Word Recognition', 'Sentence Reading'], true)) ||
        ($alignmentPart === 'Part 2 - Fluency & Comprehension' && !in_array($skillFocus, ['Reading Fluency', 'Accuracy & Pacing', 'Prosody', 'Reading Comprehension'], true))
    ) {
        $error = 'Provide a valid title and complete all CRLA-alignment fields.';
    } else {
        $stmt = $pdo->prepare("UPDATE learning_materials SET title = ?, description = ?, material_type = ?, grade_level = ?, reading_level = ?, language = ?, alignment_part = ?, skill_focus = ? WHERE id = ?");
        $stmt->execute([$title, trim($_POST['description'] ?? ''), trim($_POST['material_type'] ?? ''), $gradeLevel, $readingLevel, $language, $alignmentPart, $skillFocus, $material_id]);
        $_SESSION['success'] = 'Learning material updated.';
        header('Location: material_detail.php?id=' . $material_id);
        exit;
    }
}

include 'header.php';
include 'sidebar.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px;">
    <div><div class="page-title">Material Details</div><div class="page-subtitle">View and manage this learning material.</div></div>
    <a href="<?php echo $is_admin ? 'learning_materials.php' : 'teacher_learning_materials.php'; ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Materials</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php
$displayProfiles = ['All Profiles', 'Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Transitioning Reader', 'Reading At Grade Level'];
$displayReadingProfile = in_array($material['reading_level'], $displayProfiles, true) ? $material['reading_level'] : 'All Profiles';
?>
<div class="card"><div class="card-header"><div class="card-title"><?php echo htmlspecialchars($material['title']); ?></div><span class="badge-status badge-blue"><?php echo htmlspecialchars($material['material_type']); ?></span></div><div class="card-body"><div class="grid-2"><div><p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($material['description'] ?: 'Not provided')); ?></p><p><strong>Grade Level:</strong> <?php echo htmlspecialchars($material['grade_level']); ?></p><p><strong>Language:</strong> <?php echo htmlspecialchars($material['language'] ?? 'All Languages'); ?></p><p><strong>CRLA Part:</strong> <?php echo htmlspecialchars($material['alignment_part'] ?? 'General Reading Practice'); ?></p><p><strong>Skill Focus:</strong> <?php echo htmlspecialchars($material['skill_focus'] ?? 'General Reading Practice'); ?></p><p><strong>Recommended Reading Profile:</strong> <?php echo htmlspecialchars($displayReadingProfile); ?></p></div><div><p><strong>Material Type:</strong> <?php echo htmlspecialchars($material['material_type']); ?></p><p><strong>Uploaded By:</strong> <?php echo htmlspecialchars($material['uploader_name'] ?? 'System'); ?></p><p><strong>Date Uploaded:</strong> <?php echo $material['upload_date'] ? date('M d, Y', strtotime($material['upload_date'])) : '-'; ?></p><p><strong>File:</strong> <?php echo htmlspecialchars(($material['file_format'] ?? '') . ' · ' . ($material['file_size'] ?? '')); ?></p></div></div><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;"><?php if ($material['file_path']): ?><a class="btn btn-primary" href="material_detail.php?id=<?php echo $material_id; ?>&download=1"><i class="fa-solid fa-download"></i> Download</a><?php if (strtolower(pathinfo($material['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?><a class="btn btn-secondary" href="material_detail.php?id=<?php echo $material_id; ?>&view=1"><i class="fa-solid fa-eye"></i> View PDF</a><?php endif; ?><?php endif; ?><button class="btn btn-secondary" onclick="window.print()">Print</button><?php if ($is_admin): ?><a class="btn btn-danger" href="learning_materials.php?delete=<?php echo $material_id; ?>" onclick="return confirm('Delete this material?')"><i class="fa-solid fa-trash"></i> Delete</a><?php endif; ?></div></div></div>

<?php if ($is_admin): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header"><div class="card-title">Edit Material</div></div>
    <form method="POST">
        <div class="card-body">
            <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" value="<?php echo htmlspecialchars($material['title']); ?>" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="3"><?php echo htmlspecialchars($material['description'] ?? ''); ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Grade Level</label><select class="form-select" name="grade_level" required><?php foreach (['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'] as $grade): ?><option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $material['grade_level'] === $grade ? 'selected' : ''; ?>><?php echo htmlspecialchars($grade); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Language</label><input class="form-input" name="language" maxlength="80" value="<?php echo htmlspecialchars($material['language'] ?? 'All Languages'); ?>" required></div>
                <div class="form-group"><label class="form-label">Recommended CRLA Reading Profile</label><select class="form-select" name="reading_level" required><?php foreach (['All Profiles', 'Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Transitioning Reader', 'Reading At Grade Level'] as $profile): ?><option value="<?php echo htmlspecialchars($profile); ?>" <?php echo ($material['reading_level'] ?: 'All Profiles') === $profile ? 'selected' : ''; ?>><?php echo htmlspecialchars($profile); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Material Type</label><input class="form-input" name="material_type" value="<?php echo htmlspecialchars($material['material_type']); ?>" required></div>
                <div class="form-group"><label class="form-label">CRLA Part / Alignment</label><select class="form-select" name="alignment_part" required><?php foreach (['General Reading Practice', 'Part 1 - Foundational Skills', 'Part 2 - Fluency & Comprehension'] as $part): ?><option value="<?php echo htmlspecialchars($part); ?>" <?php echo ($material['alignment_part'] ?? 'General Reading Practice') === $part ? 'selected' : ''; ?>><?php echo htmlspecialchars($part); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Skill Focus</label><select class="form-select" name="skill_focus" required><?php foreach (['General Reading Practice', 'Alphabet Knowledge', 'Phonological Awareness', 'Decoding & Word Recognition', 'Sentence Reading', 'Reading Fluency', 'Accuracy & Pacing', 'Prosody', 'Reading Comprehension', 'Teacher Guidance'] as $skill): ?><option value="<?php echo htmlspecialchars($skill); ?>" <?php echo ($material['skill_focus'] ?? 'General Reading Practice') === $skill ? 'selected' : ''; ?>><?php echo htmlspecialchars($skill); ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
        <div class="card-footer"><button type="submit" name="update_material" class="btn btn-primary">Save Changes</button></div>
    </form>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>