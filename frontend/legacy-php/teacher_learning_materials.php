<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('teacher')) {
    header("Location: dashboard.php");
    exit();
}

$materials = $pdo->query("
    SELECT lm.*, u.full_name as uploader_name
    FROM learning_materials lm
    LEFT JOIN users u ON lm.uploaded_by = u.id
    ORDER BY lm.upload_date DESC
")->fetchAll();
$grades = ['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
$alignmentParts = [
    'General Reading Practice',
    'Part 1 - Foundational Skills',
    'Part 2 - Fluency & Comprehension',
];
$readingProfiles = [
    'All Profiles',
    'Low Emerging Reader',
    'High Emerging Reader',
    'Developing Reader',
    'Transitioning Reader',
    'Reading At Grade Level',
];
$languages = array_values(array_unique(array_filter(array_column($materials, 'language'))));
sort($languages, SORT_NATURAL | SORT_FLAG_CASE);

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Learning Materials</div>
<div class="page-subtitle">Supplementary practice organized around CRLA skills, grade levels, and reading profiles.</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title">Choose practice by CRLA skill</div></div>
    <div class="card-body">
        <p><strong>Part 1 — Foundational skills:</strong> Practice can build alphabet knowledge and phonological awareness in Grade 1, alongside decoding and word recognition; Grades 2–3 can focus on decoding, word recognition, and sentence reading.</p>
        <p><strong>Part 2 — Fluency and comprehension:</strong> Reinforce accuracy, pacing, prosody, and understanding through separate practice activities.</p>
        <p><strong>Suggested instructional use:</strong> Start Low Emerging learners with foundational skills; use decoding, word recognition, and sentence practice for High Emerging and Developing learners; build fluency and comprehension for Transitioning and Reading At Grade Level learners. These are practice suggestions, not CRLA scoring rules.</p>
        <p style="color:var(--text-muted);">CRLA administration guidance restricts sharing assessment tasks and passages. Use original practice materials; do not share CRLA learner sheets, passages, answer keys, or administration tools.</p>
    </div>
</div>

<div class="search-filter-bar">
    <input type="text" class="search-input" id="matSearch" placeholder="Search materials..." oninput="filterMaterials()">
    <select class="select-input" id="gradeFilter" onchange="filterMaterials()"><?php foreach ($grades as $grade): ?><option value="<?php echo htmlspecialchars($grade); ?>"><?php echo htmlspecialchars($grade); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="languageFilter" onchange="filterMaterials()"><option value="">All Languages</option><?php foreach ($languages as $language): ?><option value="<?php echo htmlspecialchars($language); ?>"><?php echo htmlspecialchars($language); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="partFilter" onchange="filterMaterials()"><option value="">All CRLA Parts</option><?php foreach ($alignmentParts as $part): ?><option value="<?php echo htmlspecialchars($part); ?>"><?php echo htmlspecialchars($part); ?></option><?php endforeach; ?></select>
    <select class="select-input" id="profileFilter" onchange="filterMaterials()"><?php foreach ($readingProfiles as $profile): ?><option value="<?php echo htmlspecialchars($profile); ?>"><?php echo htmlspecialchars($profile); ?></option><?php endforeach; ?></select>
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
        <div class="material-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
        <div class="material-title"><a href="material_detail.php?id=<?php echo $mat['id']; ?>"><?php echo htmlspecialchars($mat['title']); ?></a></div>
        <div class="material-meta">
            <span class="meta-type"><?php echo htmlspecialchars($mat['skill_focus']); ?></span>
            <span class="meta-grade"><?php echo htmlspecialchars($mat['grade_level']); ?></span>
            <span class="meta-format"><?php echo htmlspecialchars($mat['language']); ?></span>
        </div>
        <div style="color:var(--text-muted);font-size:12px;"><?php echo htmlspecialchars($mat['alignment_part']); ?> · <?php echo htmlspecialchars($displayProfile); ?></div>
        <?php if (!empty($mat['description'])): ?><div><?php echo htmlspecialchars($mat['description']); ?></div><?php endif; ?>
        <div class="material-footer">
            <span><?php echo htmlspecialchars($mat['file_size'] ?? ''); ?></span>
            <span style="display:flex;gap:6px;"><a class="btn btn-sm btn-secondary" href="material_detail.php?id=<?php echo $mat['id']; ?>"><i class="fa-solid fa-eye"></i> View</a><?php if ($mat['file_path']): ?><a class="btn btn-sm btn-primary" href="material_detail.php?id=<?php echo $mat['id']; ?>&download=1"><i class="fa-solid fa-download"></i> Download</a><?php endif; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($materials): ?><div id="noMaterialsMatch" class="card" style="display:none;">No materials match those filters.</div><?php else: ?><div class="card">No learning materials have been uploaded yet.</div><?php endif; ?>
</div>

<?php include 'footer.php'; ?>