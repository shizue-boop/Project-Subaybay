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

$grades = ['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
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
$materialTypes = [
    'Reading Passage',
    'Word Recognition',
    'Phonological Awareness',
    'Fluency Activity',
    'Comprehension Activity',
    'Teacher Guide',
    'Other Practice',
];

if (isset($_GET['delete'])) {
    $material_id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT file_path FROM learning_materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();
    if ($material) {
        $pdo->prepare("DELETE FROM learning_materials WHERE id = ?")->execute([$material_id]);
        if (!empty($material['file_path']) && is_file(__DIR__ . '/' . $material['file_path'])) unlink(__DIR__ . '/' . $material['file_path']);
        $_SESSION['success'] = 'Learning material deleted.';
    }
    header('Location: learning_materials.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = trim($_POST['title'] ?? '');
    $file = $_FILES['file'] ?? null;
    $allowed = ['pdf' => 'PDF', 'doc' => 'DOCX', 'docx' => 'DOCX', 'mp3' => 'MP3', 'ppt' => 'PPT', 'pptx' => 'PPT', 'xls' => 'XLSX', 'xlsx' => 'XLSX'];
    $extension = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
    $materialType = trim($_POST['material_type'] ?? '');
    $gradeLevel = trim($_POST['grade_level'] ?? '');
    $readingLevel = trim($_POST['reading_level'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $alignmentPart = trim($_POST['alignment_part'] ?? '');
    $skillFocus = trim($_POST['skill_focus'] ?? '');
    if (
        $title === '' || !$file || $file['error'] !== UPLOAD_ERR_OK ||
        !isset($allowed[$extension]) || $file['size'] > 20 * 1024 * 1024 ||
        !in_array($materialType, $materialTypes, true) ||
        !in_array($gradeLevel, $grades, true) ||
        !in_array($readingLevel, $readingProfiles, true) ||
        $language === '' || mb_strlen($language) > 80 ||
        !in_array($alignmentPart, $alignmentParts, true) ||
        !in_array($skillFocus, $skillFocuses, true) ||
        ($alignmentPart === 'Part 1 - Foundational Skills' && !in_array($skillFocus, ['Alphabet Knowledge', 'Phonological Awareness', 'Decoding & Word Recognition', 'Sentence Reading'], true)) ||
        ($alignmentPart === 'Part 2 - Fluency & Comprehension' && !in_array($skillFocus, ['Reading Fluency', 'Accuracy & Pacing', 'Prosody', 'Reading Comprehension'], true))
    ) {
        $_SESSION['error'] = 'Complete the CRLA-alignment fields and provide a supported file up to 20MB.';
    } else {
        $directory = __DIR__ . '/uploads/materials';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $_SESSION['error'] = 'The materials upload folder could not be created.';
        } else {
            $relativePath = 'uploads/materials/material_' . $_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $relativePath)) {
                $stmt = $pdo->prepare("INSERT INTO learning_materials (title, description, material_type, grade_level, reading_level, language, alignment_part, skill_focus, file_format, file_size, file_path, uploaded_by, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                $size = number_format($file['size'] / 1048576, 2) . ' MB';
                $stmt->execute([$title, trim($_POST['description'] ?? ''), $materialType, $gradeLevel, $readingLevel, $language, $alignmentPart, $skillFocus, $allowed[$extension], $size, $relativePath, $_SESSION['user_id']]);
                $_SESSION['success'] = 'CRLA-aligned learning material uploaded.';
            } else {
                $_SESSION['error'] = 'The file could not be saved.';
            }
        }
    }
    header('Location: learning_materials.php');
    exit;
}

$materials = $pdo->query("
    SELECT lm.*, u.full_name as uploader_name
    FROM learning_materials lm
    LEFT JOIN users u ON lm.uploaded_by = u.id
    ORDER BY lm.upload_date DESC
")->fetchAll();
$languages = array_values(array_unique(array_filter(array_column($materials, 'language'))));
sort($languages, SORT_NATURAL | SORT_FLAG_CASE);

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Learning Materials</div>
<div class="page-subtitle">Organize supplementary reading practice around CRLA skills, grade levels, and learner profiles.</div>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title">CRLA-aligned learning focus</div></div>
    <div class="card-body">
        <p><strong>Part 1 — Foundational skills:</strong> Practice can build alphabet knowledge and phonological awareness in Grade 1, alongside decoding and word recognition; Grades 2–3 can focus on decoding, word recognition, and sentence reading.</p>
        <p><strong>Part 2 — Fluency and comprehension:</strong> Provide separate practice for reading accuracy, pacing, prosody, and understanding text.</p>
        <p><strong>Suggested instructional use:</strong> Start Low Emerging learners with foundational skills; use decoding, word recognition, and sentence practice for High Emerging and Developing learners; build fluency and comprehension for Transitioning and Reading At Grade Level learners. These are practice suggestions, not CRLA scoring rules.</p>
        <p style="color:var(--text-muted);">Do not upload or share restricted CRLA assessment passages, learner sheets, answer keys, or administration materials. Use original practice content instead.</p>
    </div>
</div>

<div class="search-filter-bar">
    <input type="text" class="search-input" id="matSearch" placeholder="Search materials..." oninput="filterMaterials()">
    <select class="select-input" id="gradeFilter" onchange="filterMaterials()"><?php foreach ($grades as $grade): ?><option value="<?php echo htmlspecialchars($grade); ?>"><?php echo htmlspecialchars($grade); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="languageFilter" onchange="filterMaterials()"><option value="">All Languages</option><?php foreach ($languages as $language): ?><option value="<?php echo htmlspecialchars($language); ?>"><?php echo htmlspecialchars($language); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="partFilter" onchange="filterMaterials()"><option value="">All CRLA Parts</option><?php foreach ($alignmentParts as $part): ?><option value="<?php echo htmlspecialchars($part); ?>"><?php echo htmlspecialchars($part); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="profileFilter" onchange="filterMaterials()"><?php foreach ($readingProfiles as $profile): ?><option value="<?php echo htmlspecialchars($profile); ?>"><?php echo htmlspecialchars($profile); ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary" onclick="openModal('uploadModal')"><i class="fa-solid fa-upload"></i> Upload Material</button>
</div>

<div class="materials-grid" id="matGrid">
    <?php foreach ($materials as $mat):
        $displayProfile = in_array($mat['reading_level'], $readingProfiles, true) ? $mat['reading_level'] : 'All Profiles';
        $icon = match($mat['skill_focus']) {
            'Phonological Awareness' => 'fa-ear-listen',
            'Reading Fluency', 'Accuracy & Pacing', 'Prosody' => 'fa-headphones',
            'Reading Comprehension' => 'fa-book-open-reader',
            'Alphabet Knowledge', 'Decoding & Word Recognition', 'Sentence Reading' => 'fa-spell-check',
            default => 'fa-book'
        };
    ?>
    <div class="material-card" data-grade="<?php echo htmlspecialchars($mat['grade_level']); ?>" data-language="<?php echo htmlspecialchars($mat['language']); ?>" data-part="<?php echo htmlspecialchars($mat['alignment_part']); ?>" data-profile="<?php echo htmlspecialchars($displayProfile); ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div class="material-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
            <a href="material_detail.php?id=<?php echo $mat['id']; ?>" title="View material"><i class="fa-solid fa-eye"></i></a>
        </div>
        <div class="material-title"><a href="material_detail.php?id=<?php echo $mat['id']; ?>"><?php echo htmlspecialchars($mat['title']); ?></a></div>
        <div class="material-meta">
            <span class="meta-type"><?php echo htmlspecialchars($mat['skill_focus']); ?></span>
            <span class="meta-grade"><?php echo htmlspecialchars($mat['grade_level']); ?></span>
            <span class="meta-format"><?php echo htmlspecialchars($mat['language']); ?></span>
        </div>
        <div style="color:var(--text-muted);font-size:12px;"><?php echo htmlspecialchars($mat['alignment_part']); ?> · <?php echo htmlspecialchars($displayProfile); ?></div>
        <div class="material-footer">
            <span><?php echo htmlspecialchars($mat['uploader_name'] ?? 'System'); ?> • <?php echo htmlspecialchars($mat['file_size'] ?? ''); ?></span>
            <span><?php echo $mat['upload_date'] ? date('M d, Y', strtotime($mat['upload_date'])) : '-'; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($materials): ?><div id="noMaterialsMatch" class="card" style="display:none;">No materials match those filters.</div><?php else: ?><div class="card">No learning materials have been uploaded yet.</div><?php endif; ?>
</div>

<div id="uploadModal" class="modal">
    <div class="modal-header"><div class="modal-title">Upload Material</div><button class="modal-close" onclick="closeModal('uploadModal')">&times;</button></div>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Material Title</label><input type="text" class="form-input" name="title" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Practice Type</label>
                    <select class="form-select" name="material_type" required><?php foreach ($materialTypes as $type): ?><option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label class="form-label">Grade</label>
                    <select class="form-select" name="grade_level" required><option value="">Select grade</option><?php foreach ($grades as $grade): ?><option value="<?php echo htmlspecialchars($grade); ?>"><?php echo htmlspecialchars($grade); ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">CRLA Part / Alignment</label><select class="form-select" name="alignment_part" required><?php foreach ($alignmentParts as $part): ?><option value="<?php echo htmlspecialchars($part); ?>"><?php echo htmlspecialchars($part); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Skill Focus</label><select class="form-select" name="skill_focus" required><?php foreach ($skillFocuses as $skill): ?><option value="<?php echo htmlspecialchars($skill); ?>"><?php echo htmlspecialchars($skill); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Language</label><input type="text" class="form-input" name="language" placeholder="e.g., Filipino, English, Tagalog, or All Languages" maxlength="80" required></div>
                <div class="form-group"><label class="form-label">Recommended CRLA Reading Profile</label><select class="form-select" name="reading_level" required><?php foreach ($readingProfiles as $profile): ?><option value="<?php echo htmlspecialchars($profile); ?>"><?php echo htmlspecialchars($profile); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label class="form-label">Upload Supplementary Practice File (max 20MB)</label><input type="file" class="form-input" name="file" required accept=".pdf,.doc,.docx,.mp3,.ppt,.pptx,.xls,.xlsx"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
            <button type="submit" name="upload_material" class="btn btn-primary">Save / Upload</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>