<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

// Fetch grade categories grouped by grade
$categories = $pdo->query("
    SELECT * FROM grade_categories 
    ORDER BY FIELD(grade_level, 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'), 
    FIELD(category_name, 'Full Refresher', 'Moderate Refresher', 'Light Refresher', 'Grade Ready')
")->fetchAll();

$grouped = [];
foreach ($categories as $cat) {
    $grouped[$cat['grade_level']][] = $cat;
}

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Grade Categories</div>
<div class="page-subtitle">Define reading profile categories and scoring bands per grade level.</div>

<div class="button-container">
    <button class="btn btn-primary" onclick="openModal('addCategoryModal')">
        <i class="fa-solid fa-plus"></i> Add Grade Category
    </button>
</div>

<?php foreach ($grouped as $grade => $cats): 
    $assessmentType = $cats[0]['assessment_type'] ?? '';
?>
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title"><?php echo htmlspecialchars($grade); ?></div>
            <div class="card-subtitle"><?php echo htmlspecialchars($assessmentType); ?></div>
        </div>
        <button class="btn-table-action btn-edit" onclick="alert('Edit grade - implement as needed')"><i class="fa-solid fa-pen"></i></button>
    </div>
    <div class="card-body">
        <div class="category-grid">
            <?php foreach ($cats as $cat): 
                $cardClass = match($cat['category_name']) {
                    'Full Refresher' => 'full',
                    'Moderate Refresher' => 'moderate',
                    'Light Refresher' => 'light',
                    'Grade Ready' => 'ready',
                    default => 'light'
                };
            ?>
            <div class="category-card <?php echo $cardClass; ?>">
                <div class="category-name"><?php echo $cat['category_name']; ?></div>
                <div class="category-detail">
                    <strong>Score Range:</strong>
                    <?php echo htmlspecialchars($cat['score_range']); ?>
                </div>
                <div class="category-detail">
                    <strong>Recommended Action:</strong>
                    <?php echo htmlspecialchars($cat['recommended_action']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal">
    <div class="modal-header">
        <div class="modal-title">Add Grade Category</div>
        <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
    </div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Grade Level</label>
                    <select class="form-select" name="grade_level">
                        <option>Grade 1</option>
                        <option>Grade 2</option>
                        <option>Grade 3</option>
                        <option>Grade 4</option>
                        <option>Grade 5</option>
                        <option>Grade 6</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_name">
                        <option>Full Refresher</option>
                        <option>Moderate Refresher</option>
                        <option>Light Refresher</option>
                        <option>Grade Ready</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Score Range</label>
                <input type="text" class="form-input" name="score_range" placeholder="e.g. 0-14 (Part 1) / 0-6 (Part 2)">
            </div>
            <div class="form-group">
                <label class="form-label">Recommended Action</label>
                <textarea class="form-textarea" name="recommended_action" rows="3" placeholder="Describe recommended intervention..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Category</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>