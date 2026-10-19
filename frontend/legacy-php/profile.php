<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

// Check if profile_image column exists, if not create it
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($checkCol->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER joined_date");
    }
} catch (Exception $e) {
    // Column might already exist or error occurred, continue anyway
}

$user_id = $_SESSION['user_id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');

    // Check if email is taken by another user
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $user_id]);
    if ($check->fetch()) {
        $_SESSION['error'] = 'Email is already used by another account.';
    } else {
        $update = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, section = ?, grade_level = ? WHERE id = ?");
        if ($update->execute([$full_name, $email, $section, $grade_level, $user_id])) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['section'] = $section;
            $_SESSION['grade_level'] = $grade_level;
            $_SESSION['success'] = 'Profile updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update profile.';
        }
    }
    header("Location: profile.php");
    exit();
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = 'Only image files (JPG, PNG, GIF, WebP) are allowed.';
        } elseif ($file['size'] > $max_size) {
            $_SESSION['error'] = 'Image size must not exceed 5MB.';
        } else {
            // Create uploads directory if it doesn't exist
            $uploads_dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
                $_SESSION['error'] = 'Failed to create the profile image directory.';
                header("Location: profile.php");
                exit();
            }

            $file_ext = match ($file['type']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            };
            $filename = 'profile_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $relative_filepath = 'uploads/profiles/' . $filename;
            $filepath = $uploads_dir . '/' . $filename;

            // Remove the previous image only after the replacement has been saved.
            $old_image = $user['profile_image'] ?? null;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update database
                $update_img = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                if ($update_img->execute([$relative_filepath, $user_id])) {
                    $old_image_path = $old_image ? __DIR__ . '/' . ltrim($old_image, '/\\') : '';
                    if ($old_image && is_file($old_image_path) && $old_image !== $relative_filepath) {
                        unlink($old_image_path);
                    }
                    $_SESSION['success'] = 'Profile image updated successfully!';
                    $user['profile_image'] = $relative_filepath;
                } else {
                    $_SESSION['error'] = 'Failed to save image info.';
                    unlink($filepath);
                }
            } else {
                $_SESSION['error'] = 'Failed to upload image.';
            }
        }
    } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'An error occurred during upload.';
    }

    header("Location: profile.php");
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $_SESSION['error'] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $_SESSION['error'] = 'New passwords do not match.';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
        $_SESSION['success'] = 'Password changed successfully!';
    }
    header("Location: profile.php");
    exit();
}

// Handle account status toggle (coordinator/principal only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $user_id]);
    $_SESSION['success'] = 'Account status updated to ' . $new_status . '.';
    header("Location: profile.php");
    exit();
}

// Get stats based on role
$stats = [];
if ($user['role'] === 'teacher') {
    $stats['total_students'] = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ?")->execute([$user_id]) ? $pdo->query("SELECT COUNT(*) FROM students WHERE teacher_id = $user_id")->fetchColumn() : 0;
    $stats['sessions'] = $pdo->query("SELECT COUNT(*) FROM remedial_sessions WHERE teacher_id = $user_id")->fetchColumn();
    $stats['assessments'] = $pdo->query("SELECT COUNT(*) FROM assessments a JOIN students s ON a.student_id = s.id WHERE s.teacher_id = $user_id")->fetchColumn();
} else {
    $stats['total_teachers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['total_reports'] = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
}

$role_label = match($user['role']) {
    'principal' => 'Principal',
    'coordinator' => 'Reading Coordinator',
    'teacher' => 'Teacher',
    default => 'User'
};

$user_initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $user['full_name'])));
if (strlen($user_initials) > 2) $user_initials = substr($user_initials, 0, 2);

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">My Profile</div>
<div class="page-subtitle">Manage your account settings and preferences</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px;">
    <!-- Left: Profile Card -->
    <div>
        <div class="card" style="text-align: center; padding: 32px 20px;">
            <?php if (!empty($user['profile_image']) && is_file(__DIR__ . '/' . ltrim($user['profile_image'], '/\\'))): ?>
            <div class="profile-image-container" style="margin-bottom: 16px;">
                <?php $profileImagePath = __DIR__ . '/' . ltrim($user['profile_image'], '/\\'); ?>
                <img src="<?php echo htmlspecialchars(str_replace('\\', '/', $user['profile_image']) . '?v=' . filemtime($profileImagePath)); ?>" alt="Profile" class="profile-image" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 15px rgba(37,99,235,0.3);">
            </div>
            <?php else: ?>
            <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #2563EB, #3B82F6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: 700; margin: 0 auto 16px; box-shadow: 0 4px 15px rgba(37,99,235,0.3);">
                <?php echo $user_initials; ?>
            </div>
            <?php endif; ?>
            
            <h2 style="font-size: 18px; margin-bottom: 4px;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p style="color: #64748B; font-size: 13px; margin-bottom: 12px;"><?php echo $role_label; ?></p>
            
            <!-- Image Upload Form -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm" style="margin-bottom: 16px;">
                <label for="profileImageInput" class="image-upload-area" id="uploadArea" style="display: block; cursor: pointer;">
                    <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display: none;">
                    <i class="fa-solid fa-camera" style="font-size: 24px; color: var(--primary); margin-bottom: 8px; display: block;"></i>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Click to upload or drag & drop</p>
                    <p style="font-size: 10px; color: #94A3B8; margin: 4px 0 0 0;">PNG, JPG, GIF (Max 5MB)</p>
                </label>
            </form>
            
            <span class="badge-status <?php echo $user['status'] === 'active' ? 'badge-green' : 'badge-red'; ?>" style="font-size: 12px;">
                <?php echo ucfirst($user['status']); ?>
            </span>

            <script>
            // Immediate inline script to handle file upload
            document.addEventListener('DOMContentLoaded', function() {
                const uploadArea = document.getElementById('uploadArea');
                const fileInput = document.getElementById('profileImageInput');
                const uploadForm = document.getElementById('uploadForm');
                
                if (uploadArea && fileInput) {
                    // File input change - auto submit
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files.length > 0) {
                            const file = this.files[0];
                            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            const maxSize = 5 * 1024 * 1024;
                            
                            if (!validTypes.includes(file.type)) {
                                alert('Only JPG, PNG, GIF, WebP allowed');
                                fileInput.value = '';
                                return;
                            }
                            if (file.size > maxSize) {
                                alert('Image must be under 5MB');
                                fileInput.value = '';
                                return;
                            }
                            if (uploadForm) {
                                uploadForm.submit();
                            }
                        }
                    });
                    
                    // Drag and drop
                    uploadArea.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        uploadArea.classList.add('dragover');
                    });
                    uploadArea.addEventListener('dragleave', () => {
                        uploadArea.classList.remove('dragover');
                    });
                    uploadArea.addEventListener('drop', (e) => {
                        e.preventDefault();
                        uploadArea.classList.remove('dragover');
                        if (e.dataTransfer && e.dataTransfer.files) {
                            fileInput.files = e.dataTransfer.files;
                            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                }
            });
            </script>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: left;">
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Email</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <?php if ($user['section']): ?>
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Section</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($user['section']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($user['grade_level']): ?>
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Grade Level</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($user['grade_level']); ?></div>
                </div>
                <?php endif; ?>
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Member Since</div>
                    <div style="font-size: 13px; font-weight: 500;"><?php echo $user['joined_date'] ? date('F Y', strtotime($user['joined_date'])) : 'N/A'; ?></div>
                </div>
                <div>
                    <div style="font-size: 11px; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">User ID</div>
                    <div style="font-size: 13px; font-weight: 500; font-family: monospace;">#<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card" style="margin-top: 16px;">
            <div class="card-header">
                <div class="card-title">Activity Summary</div>
            </div>
            <div class="card-body">
                <?php if ($user['role'] === 'teacher'): ?>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Assigned Students</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['total_students']; ?></span>
                </div>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Intervention Sessions</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['sessions']; ?></span>
                </div>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Assessments Logged</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['assessments']; ?></span>
                </div>
                <?php else: ?>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Total Teachers</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['total_teachers']; ?></span>
                </div>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Total Students</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['total_students']; ?></span>
                </div>
                <div class="stat-row-item">
                    <span style="font-size: 13px; color: #64748B;">Reports Generated</span>
                    <span style="font-size: 13px; font-weight: 700;"><?php echo $stats['total_reports']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Forms -->
    <div>
        <!-- Edit Profile -->
        <div class="card" id="change-password" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-user-pen" style="margin-right: 8px;"></i>Edit Profile</div>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" class="form-input" value="<?php echo htmlspecialchars($user['section'] ?? ''); ?>" placeholder="e.g. Grade 2-A">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Grade Level</label>
                            <input type="text" name="grade_level" class="form-input" value="<?php echo htmlspecialchars($user['grade_level'] ?? ''); ?>" placeholder="e.g. Grade 2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-input" value="<?php echo $role_label; ?>" disabled style="background: #F8FAFC; color: #94A3B8;">
                        <p style="font-size: 11px; color: #94A3B8; margin-top: 4px;">Role can only be changed by an administrator.</p>
                    </div>
                    <div class="button-container" style="margin-bottom: 0;">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-lock" style="margin-right: 8px;"></i>Change Password</div>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validatePasswordForm()">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" id="currentPass" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" id="newPass" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" id="confirmPass" required>
                        </div>
                    </div>
                    <p style="font-size: 11px; color: #94A3B8; margin-bottom: 16px;">Password must be at least 6 characters long.</p>
                    <div class="button-container" style="margin-bottom: 0;">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fa-solid fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Status -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-shield-halved" style="margin-right: 8px;"></i>Account Status</div>
            </div>
            <div class="card-body">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: <?php echo $user['status'] === 'active' ? '#F0FDF4' : '#FEF2F2'; ?>; border-radius: 10px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: <?php echo $user['status'] === 'active' ? '#22C55E' : '#EF4444'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fa-solid <?php echo $user['status'] === 'active' ? 'fa-check' : 'fa-ban'; ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14px;">Account is <?php echo $user['status']; ?></div>
                            <div style="font-size: 12px; color: #64748B;"><?php echo $user['status'] === 'active' ? 'You have full access to the system.' : 'Your account is currently disabled.'; ?></div>
                        </div>
                    </div>
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> your account?')">
                        <button type="submit" name="toggle_status" class="btn <?php echo $user['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                            <i class="fa-solid <?php echo $user['status'] === 'active' ? 'fa-power-off' : 'fa-power-off'; ?>"></i>
                            <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Account
                        </button>
                    </form>
                </div>
                <p style="font-size: 12px; color: #94A3B8;">Deactivating your account will prevent you from logging in until it is reactivated by an administrator.</p>
            </div>
        </div>
    </div>
</div>

<script>
function validatePasswordForm() {
    var newPass = document.getElementById('newPass').value;
    var confirmPass = document.getElementById('confirmPass').value;
    if (newPass !== confirmPass) {
        alert('New passwords do not match!');
        return false;
    }
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters!');
        return false;
    }
    return true;
}
</script>

<?php include 'footer.php'; ?>