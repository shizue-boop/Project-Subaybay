<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

$user_id = (int) $_SESSION['user_id'];
$is_admin = hasRole('principal') || hasRole('coordinator');
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_school_settings']) && $is_admin) {
    $keys = ['school_name', 'school_id', 'school_address', 'school_contact', 'school_email', 'school_logo', 'school_year', 'program_name', 'grade_levels_covered', 'assessment_information'];
    $save = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
    foreach ($keys as $key) $save->execute([$key, trim($_POST[$key] ?? ''), $user_id]);
    $_SESSION['success'] = 'School and reading program settings saved.';
    header('Location: settings.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $setting) $settings[$setting['setting_key']] = $setting['setting_value'];
$initials = implode('', array_map(fn($word) => strtoupper($word[0]), explode(' ', $user['full_name'])));
if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
$profileImagePath = !empty($user['profile_image']) ? __DIR__ . '/' . ltrim($user['profile_image'], '/\\') : '';
$profileImageUrl = $user['profile_image'] && is_file($profileImagePath)
    ? str_replace('\\', '/', $user['profile_image']) . '?v=' . filemtime($profileImagePath)
    : null;

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Settings</div>
<div class="page-subtitle">Manage your account, school information, and reading program settings.</div>
<div class="settings-page">
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="grid-2 settings-overview">
    <div class="card settings-card">
        <div class="card-header"><div class="card-title">My Profile</div><span class="settings-card-icon"><i class="fa-solid fa-user"></i></span></div>
        <div class="card-body">
            <div class="settings-identity">
                <?php if ($profileImageUrl): ?><img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile picture" class="settings-avatar"><?php else: ?><div class="settings-avatar settings-avatar-fallback"><?php echo htmlspecialchars($initials); ?></div><?php endif; ?>
                <div><h2><?php echo htmlspecialchars($user['full_name']); ?></h2><span><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></div>
            </div>
            <div class="settings-details"><div><span>Email</span><strong><?php echo htmlspecialchars($user['email']); ?></strong></div><div><span>Username</span><strong><?php echo htmlspecialchars($user['email']); ?></strong></div><div><span>Contact Number</span><strong><?php echo htmlspecialchars($user['contact_number'] ?? 'Not provided'); ?></strong></div></div>
            <a href="profile.php" class="btn btn-primary"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
        </div>
    </div>
    <div class="card settings-card">
        <div class="card-header"><div class="card-title">Account &amp; Security</div><span class="settings-card-icon"><i class="fa-solid fa-shield-halved"></i></span></div>
        <div class="card-body">
            <div class="security-status"><span class="security-check"><i class="fa-solid fa-check"></i></span><div><strong>Account secured</strong><span>Password-protected authenticated account</span></div></div>
            <div class="settings-details"><div><span>Account Status</span><strong class="status-active"><?php echo htmlspecialchars(ucfirst($user['status'])); ?></strong></div><div><span>Access Role</span><strong><?php echo htmlspecialchars(ucfirst($user['role'])); ?></strong></div></div>
            <a href="profile.php#change-password" class="btn btn-secondary"><i class="fa-solid fa-key"></i> Change Password</a>
        </div>
    </div>
</div>

<div class="card settings-section"><div class="card-header"><div><div class="card-title"><i class="fa-solid fa-school settings-section-icon"></i>School Information</div><div class="card-subtitle">Details shown across your reading monitoring records.</div></div><?php if (!$is_admin): ?><span class="record-count">Administrator managed</span><?php endif; ?></div><form method="POST"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">School Name</label><input class="form-input" name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div><div class="form-group"><label class="form-label">School ID</label><input class="form-input" name="school_id" value="<?php echo htmlspecialchars($settings['school_id'] ?? ''); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div></div><div class="form-group"><label class="form-label">School Address</label><textarea class="form-input" name="school_address" rows="2" <?php echo $is_admin ? '' : 'disabled'; ?>><?php echo htmlspecialchars($settings['school_address'] ?? ''); ?></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">School Contact Number</label><input class="form-input" name="school_contact" value="<?php echo htmlspecialchars($settings['school_contact'] ?? ''); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div><div class="form-group"><label class="form-label">School Email</label><input type="email" class="form-input" name="school_email" value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div><div class="form-group"><label class="form-label">School Logo</label><input class="form-input" name="school_logo" value="<?php echo htmlspecialchars($settings['school_logo'] ?? ''); ?>" placeholder="Logo path or URL" <?php echo $is_admin ? '' : 'disabled'; ?>></div></div></div>

<div class="card settings-section"><div class="card-header"><div><div class="card-title"><i class="fa-solid fa-book-open settings-section-icon"></i>Reading Program Settings</div><div class="card-subtitle">Define the active school year and assessment coverage.</div></div><?php if (!$is_admin): ?><span class="record-count">Administrator managed</span><?php endif; ?></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">School Year</label><input class="form-input" name="school_year" value="<?php echo htmlspecialchars($settings['school_year'] ?? ''); ?>" placeholder="2025-2026" <?php echo $is_admin ? '' : 'disabled'; ?>></div><div class="form-group"><label class="form-label">Program Name</label><input class="form-input" name="program_name" value="<?php echo htmlspecialchars($settings['program_name'] ?? 'Project Subaybay'); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div></div><div class="form-group"><label class="form-label">Grade Levels Covered</label><input class="form-input" name="grade_levels_covered" value="<?php echo htmlspecialchars($settings['grade_levels_covered'] ?? 'Grade 1, Grade 2, Grade 3, Grade 4, Grade 5, Grade 6'); ?>" <?php echo $is_admin ? '' : 'disabled'; ?>></div><div class="form-group"><label class="form-label">Assessment Information</label><textarea class="form-input" name="assessment_information" rows="3" <?php echo $is_admin ? '' : 'disabled'; ?>><?php echo htmlspecialchars($settings['assessment_information'] ?? ''); ?></textarea></div></div><?php if ($is_admin): ?><div class="card-footer"><button type="submit" name="save_school_settings" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button></div><?php endif; ?></form></div>

</div>

<?php include 'footer.php'; ?>