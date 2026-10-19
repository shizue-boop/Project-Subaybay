<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$role = $_SESSION['role'] ?? 'teacher';
$user_name = $_SESSION['full_name'] ?? 'User';
$user_initials = implode('', array_map(function($word) { return strtoupper($word[0]); }, explode(' ', $user_name)));
if(strlen($user_initials) > 2) $user_initials = substr($user_initials, 0, 2);
$user_image = null;
$user_image_url = null;
if (!empty($_SESSION['user_id'])) {
    $userImageStmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $userImageStmt->execute([(int) $_SESSION['user_id']]);
    $user_image = $userImageStmt->fetchColumn();
    $userImagePath = $user_image ? __DIR__ . '/' . ltrim($user_image, '/\\') : '';
    if ($user_image && is_file($userImagePath)) {
        $user_image_url = str_replace('\\', '/', $user_image) . '?v=' . filemtime($userImagePath);
    } else {
        $user_image = null;
    }
}

// Menu items based on role
$menu_items = [];
if ($role === 'principal' || $role === 'coordinator') {
    $menu_items = [
        ['dashboard', 'Dashboard', 'fa-gauge-high'],
        ['students', 'Pupils', 'fa-graduation-cap'],
        ['endorsements', 'Endorsement & Approval', 'fa-file-signature'],
        ['remedial_sessions', 'Remedial Monitoring', 'fa-hand-holding-heart'],
        ['remedial_attendance', 'Attendance', 'fa-calendar-check'],
        ['learning_materials', 'Learning Materials', 'fa-book'],
        ['reports', 'Reports', 'fa-file-lines'],
        ['teacher_accounts', 'User Management', 'fa-user-tie'],
        ['settings', 'Settings', 'fa-gear'],
    ];
} else {
    $menu_items = [
        ['teacher_dashboard', 'Dashboard', 'fa-gauge-high'],
        ['my_students', 'Pupils', 'fa-users'],
        ['endorsements', 'Endorsement & Approval', 'fa-file-signature'],
        ['interventions', 'Remedial Monitoring', 'fa-hand-holding-heart'],
        ['remedial_attendance', 'Attendance', 'fa-calendar-check'],
        ['teacher_learning_materials', 'Learning Materials', 'fa-book'],
        ['reports', 'Reports', 'fa-file-lines'],
        ['settings', 'Settings', 'fa-gear'],
    ];
}
?>
<div class="app-container">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fa-solid fa-book-open-reader"></i>
        </div>
        <div class="logo-text">
            <h1>Project<br>Subaybay</h1>
            <span>Reading Monitoring</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <p class="menu-label">MAIN MENU</p>
        <ul>
            <?php foreach ($menu_items as $item): 
                $is_active = ($current_page === $item[0]) ? 'active' : '';
                $page_file = $item[0] . '.php';
            ?>
            <li class="<?php echo $is_active; ?>">
                <a href="<?php echo $page_file; ?>">
                    <i class="fa-solid <?php echo $item[2]; ?>"></i>
                    <span><?php echo $item[1]; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-profile">
            <?php if ($user_image_url): ?>
                <img class="avatar" src="<?php echo htmlspecialchars($user_image_url); ?>" alt="<?php echo htmlspecialchars($user_name); ?> profile picture" style="object-fit:cover;">
            <?php else: ?>
                <div class="avatar"><?php echo htmlspecialchars($user_initials); ?></div>
            <?php endif; ?>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role">
                    <?php 
                    if($role === 'principal') echo 'Principal';
                    elseif($role === 'coordinator') echo 'Reading Coordinator';
                    else echo $_SESSION['section'] ?? 'Teacher';
                    ?>
                </span>
            </div>
        </div>
        <a href="profile.php" class="logout-btn" style="margin-bottom: 4px;">
            <i class="fa-solid fa-user-gear"></i>
            <span>My Profile</span>
        </a>
        <a href="logout.php" class="logout-btn" id="logoutBtn" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Log Out</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="top-header">
        <div class="breadcrumb">
            <?php if($role === 'teacher'): ?>
                <span>Teacher</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span class="current"><?php echo $menu_items[array_search($current_page, array_column($menu_items, 0))][1] ?? 'Dashboard'; ?></span>
            <?php else: ?>
                <span class="current">Reading Performance Dashboard</span>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <div class="dropdown">
                <button class="btn-dropdown">
                    <span>School Year 2024-2025</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
            </div>
            <div class="dropdown">
                <button class="btn-dropdown">
                    <span>Period: End of Year</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
            </div>
            <a href="profile.php" class="btn-icon" title="My Profile" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-gear"></i>
            </a>
            <a href="profile.php" style="text-decoration: none;">
                <?php if ($user_image_url): ?>
                    <img class="user-avatar-small" src="<?php echo htmlspecialchars($user_image_url); ?>" alt="<?php echo htmlspecialchars($user_name); ?> profile picture" style="object-fit:cover;">
                <?php else: ?>
                    <div class="user-avatar-small"><?php echo htmlspecialchars($user_initials); ?></div>
                <?php endif; ?>
            </a>
        </div>
    </header>
    <div class="page-content">