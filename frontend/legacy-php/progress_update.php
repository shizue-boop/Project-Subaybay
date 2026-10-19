<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

$improved = $pdo->query("SELECT COUNT(*) FROM progress_updates WHERE trend = 'improved'")->fetchColumn();
$noChange = $pdo->query("SELECT COUNT(*) FROM progress_updates WHERE trend = 'no_change'")->fetchColumn();
$declined = $pdo->query("SELECT COUNT(*) FROM progress_updates WHERE trend = 'declined'")->fetchColumn();

$updates = $pdo->query("
    SELECT pu.*, s.full_name, s.section, s.grade_level
    FROM progress_updates pu
    JOIN students s ON pu.student_id = s.id
    ORDER BY pu.update_date DESC
")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Progress Updates</div>
<div class="page-subtitle">Track student reading progress over time</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-arrow-trend-up"></i></div>
        <div class="stat-info"><h3><?php echo $improved; ?></h3><p>Improved</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#F1F5F9;color:#64748B;"><i class="fa-solid fa-minus"></i></div>
        <div class="stat-info"><h3><?php echo $noChange; ?></h3><p>No Change</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-arrow-trend-down"></i></div>
        <div class="stat-info"><h3><?php echo $declined; ?></h3><p>Declined</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Student Progress Summary</div>
    </div>
    <div class="card-body">
        <?php foreach ($updates as $upd): 
            $prevClass = match($upd['previous_profile']) {
                'Independent','Grade Ready' => 'badge-green',
                'Instructional','Light Refresher' => 'badge-blue',
                'Frustration','Full Refresher' => 'badge-red',
                default => 'badge-yellow'
            };
            $currClass = match($upd['current_profile']) {
                'Independent','Grade Ready' => 'badge-green',
                'Instructional','Light Refresher' => 'badge-blue',
                'Frustration','Full Refresher' => 'badge-red',
                default => 'badge-yellow'
            };
            $trendIcon = $upd['trend'] === 'improved' ? '<i class="fa-solid fa-arrow-up" style="color:var(--success)"></i>' : 
                        ($upd['trend'] === 'declined' ? '<i class="fa-solid fa-arrow-down" style="color:var(--danger)"></i>' : 
                        '<i class="fa-solid fa-minus" style="color:var(--text-muted)"></i>');
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;border-bottom:1px solid var(--border-color);">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="pupil-avatar"><?php echo strtoupper(substr($upd['full_name'],0,1)); ?></div>
                <div>
                    <div style="font-weight:600;"><?php echo htmlspecialchars($upd['full_name']); ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?php echo $upd['grade_level'].' - '.$upd['section'].' • '.date('M d, Y',strtotime($upd['update_date'])); ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge-status <?php echo $prevClass; ?>"><?php echo $upd['previous_profile']; ?></span>
                <span style="color:var(--text-muted);font-weight:bold;">→</span>
                <span class="badge-status <?php echo $currClass; ?>"><?php echo $upd['current_profile']; ?></span>
                <?php echo $trendIcon; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>