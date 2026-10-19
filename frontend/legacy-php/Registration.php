<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';

try {
    $userColumnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($userColumnCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN employee_id VARCHAR(80) DEFAULT NULL AFTER email");
    }
} catch (Exception $e) {
    // Ignore migration failures; the UI can still work even if the column is absent.
}

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$success = '';
$successDetails = [];

$submittedValues = [
    'role' => 'teacher',
    'first_name' => '',
    'last_name' => '',
    'employee_id' => '',
    'grade_level' => '',
    'section' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedValues['role'] = trim($_POST['role'] ?? 'teacher');
    $submittedValues['first_name'] = trim($_POST['first_name'] ?? '');
    $submittedValues['last_name'] = trim($_POST['last_name'] ?? '');
    $submittedValues['employee_id'] = trim($_POST['employee_id'] ?? '');
    $submittedValues['grade_level'] = trim($_POST['grade_level'] ?? '');
    $submittedValues['section'] = trim($_POST['section'] ?? '');
    $submittedValues['email'] = trim($_POST['email'] ?? '');

    $allowedRoles = ['principal', 'coordinator', 'teacher'];
    $role = in_array($submittedValues['role'], $allowedRoles, true) ? $submittedValues['role'] : 'teacher';
    $firstName = $submittedValues['first_name'];
    $lastName = $submittedValues['last_name'];
    $fullName = trim($firstName . ' ' . $lastName);
    $email = $submittedValues['email'];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $employeeId = $submittedValues['employee_id'];
    $gradeLevel = $submittedValues['grade_level'];
    $section = $submittedValues['section'];

    $isTeacher = $role === 'teacher';
    $requiredFieldsOk = !empty($firstName) && !empty($lastName) && !empty($email) && !empty($password) && !empty($confirmPassword);
    if ($isTeacher) {
        $requiredFieldsOk = $requiredFieldsOk && !empty($employeeId) && !empty($gradeLevel) && !empty($section);
    }

    if (!$requiredFieldsOk) {
        $error = 'Please complete all required fields before submitting.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must be at least 8 characters long, include one uppercase letter, and one number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare(
                'INSERT INTO users (full_name, email, password, role, section, grade_level, employee_id, status, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, "inactive", CURDATE())'
            );

            if ($ins->execute([$fullName, $email, $hashedPassword, $role, $section, $gradeLevel, $employeeId])) {
                $success = 'Registration submitted successfully. Your account is pending approval.';
                $roleLabels = [
                    'principal' => 'Principal',
                    'coordinator' => 'Reading Coordinator',
                    'teacher' => 'Teacher',
                ];
                $submittedRoleLabel = $roleLabels[$role];
                $successDetails = [
                    'Role' => $submittedRoleLabel,
                    'Name' => $fullName,
                ];
                if ($isTeacher) {
                    $successDetails['Employee ID'] = $employeeId;
                }
                $successDetails['Email'] = $email;
                if ($isTeacher) {
                    $assignedClass = $gradeLevel;
                    if ($section !== '') {
                        $assignedClass .= ($assignedClass !== '' ? ' — ' : '') . $section;
                    }
                    $successDetails['Assigned To'] = $assignedClass;
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Project Subaybay — Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --navy:#0B1220; --blue-accent:#5B9BFF; --blue-btn:#2F6FED;
            --amber:#EA9A1D; --green:#1E9E6B; --ink:#0F172A; --muted:#64748B;
            --line:#E2E8F0; --page-bg:#F3F5F9;
        }
        html,body{height:100%;}
        body{ font-family: ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:var(--page-bg); color:var(--ink); }
        .hidden{ display:none !important; }
        .panel-left{ background: radial-gradient(120% 140% at 0% 0%, #0E1A33 0%, var(--navy) 55%, #070C17 100%); }
        .card{ background:#fff; border-radius:18px; box-shadow: 0 30px 60px -25px rgba(15,23,42,.35), 0 2px 8px rgba(15,23,42,.06); }
        .role-card{ border:1.5px solid var(--line); border-radius:14px; transition: border-color .15s ease, box-shadow .15s ease; cursor:pointer; }
        .role-card:hover{ border-color:#C7D2E6; }
        .role-card.selected{ border-color: var(--blue-btn); box-shadow: 0 0 0 3px rgba(47,111,237,.14); }
        .badge{ width:42px;height:42px;border-radius:10px; display:flex;align-items:center;justify-content:center; color:#fff;font-weight:700;font-size:15px;flex:none; }
        .btn-primary{ background: var(--ink); color:#fff; border-radius:10px; font-weight:600; transition: background .15s ease, opacity .15s ease; }
        .btn-primary:hover:not(:disabled){ background:#1E293B; }
        .btn-primary:disabled{ background:#9AA6B7; cursor:not-allowed; }
        .btn-ghost{ border:1.5px solid var(--line); border-radius:9px; font-weight:600; background:#fff; transition:background .15s ease; }
        .btn-ghost:hover{ background:#F8FAFC; }
        .input-field{ border:1.5px solid var(--line); border-radius:10px; padding:11px 14px; width:100%; font-size:14.5px; outline:none; transition: border-color .15s ease, box-shadow .15s ease; }
        .input-field:focus{ border-color: var(--blue-btn); box-shadow: 0 0 0 3px rgba(47,111,237,.14); }
        .input-field.err{ border-color:#DC2626; box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
        .field-err-msg{ color:#DC2626; font-size:12.5px; margin-top:4px; display:none; }
        .step-dot{ width:26px;height:26px;border-radius:999px; display:flex;align-items:center;justify-content:center; font-size:12px;font-weight:700;flex:none; }
        .step-dot.pending{ background:#E5E9F0; color:#7C8AA0; }
        .step-dot.current{ background:var(--ink); color:#fff; }
        .step-dot.done{ background:#1E9E6B; color:#fff; }
        .step-line{ height:1.5px; width:26px; background:#DCE2EC; }
        .fade-screen{ animation: fadein .28s ease; }
        @keyframes fadein{ from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:translateY(0);} }
        .req-item{ color:#94A3B8; transition: color .15s ease; }
        .req-item.ok{ color:#1E9E6B; }
        .eye-btn{ color:#94A3B8; } .eye-btn:hover{ color:#475569; }
        .stat-card{ background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; }
        .link-blue{ color:var(--blue-accent); text-decoration:none; } .link-blue:hover{ text-decoration:underline; }
        @media (prefers-reduced-motion: reduce){ .fade-screen{ animation:none; } }
    </style>
</head>
<body>
    <div id="authWrap" class="min-h-screen flex flex-col md:flex-row">
        <aside class="panel-left text-white w-full md:w-[46%] lg:w-[42%] flex flex-col justify-between px-8 sm:px-12 py-10 min-h-[320px] md:min-h-screen">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background:var(--blue-btn)">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                </div>
                <div>
                    <div class="font-bold text-[17px] leading-tight">Project Subaybay</div>
                    <div class="text-[13px] leading-tight" style="color:var(--blue-accent)">Reading Progress Monitoring System</div>
                </div>
            </div>
            <div id="leftBody" class="my-10 max-w-md"></div>
            <div class="text-[12.5px]" style="color:#7E8CA8">© 2025 Project Subaybay — <span style="color:var(--blue-accent)">Cabilang Baybay Elementary School</span></div>
        </aside>

        <main class="flex-1 flex items-center justify-center px-5 sm:px-10 py-12">
            <div id="screenMount" class="w-full max-w-[520px]">
                <?php if ($success): ?>
                    <div class="card px-8 sm:px-10 py-10 fade-screen text-center">
                        <div class="w-16 h-16 rounded-full mx-auto mb-5 flex items-center justify-center" style="background:#E4F6EE">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1E9E6B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 12.5l2.5 2.5L16 9"></path></svg>
                        </div>
                        <h2 class="text-[22px] font-bold mb-2">Registration Submitted!</h2>
                        <p class="text-[14.5px] mb-7 max-w-sm mx-auto" style="color:var(--muted)">
                            Your account request has been submitted for approval. You can sign in once your account is activated.
                        </p>
                        <div class="text-left rounded-[12px] border px-6 py-5 mb-8" style="background:#EEF4FF; border-color:#DCE7FF">
                            <p class="text-[12px] font-bold tracking-wide mb-3" style="color:var(--blue-btn)">SUBMITTED DETAILS</p>
                            <?php $detailIndex = 0; foreach ($successDetails as $label => $value): ?>
                                <div class="flex items-center justify-between gap-4 py-2.5 <?php echo $detailIndex < count($successDetails) - 1 ? 'border-b' : ''; ?>" style="border-color:var(--line)">
                                    <span style="color:var(--muted)"><?php echo htmlspecialchars($label); ?></span>
                                    <span class="font-medium text-right break-words"><?php echo htmlspecialchars($value); ?></span>
                                </div>
                            <?php $detailIndex++; endforeach; ?>
                        </div>
                        <a href="index.php" class="btn-primary inline-flex w-full justify-center py-3 text-[15px]">Back to Login</a>
                    </div>
                <?php else: ?>
                    <div class="card px-8 sm:px-10 py-9 fade-screen <?php echo $error ? 'hidden' : ''; ?>" id="roleScreen">
                        <div class="mb-5">
                            <div class="text-[14px] font-semibold mb-1" style="color:var(--muted)">Cabilang Baybay Elementary School — Select your role to get started</div>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="role-card flex items-start gap-4 px-5 py-4 selected" data-role="principal" onclick="selectRole('principal')">
                                <div class="badge" style="background:#EA9A1D">P</div>
                                <div>
                                    <div class="text-[15px] font-semibold mb-0.5">Principal</div>
                                    <div class="text-[13.5px] leading-snug" style="color:var(--muted)">School administrator with view-only access and full reports.</div>
                                </div>
                            </div>
                            <div class="role-card flex items-start gap-4 px-5 py-4" data-role="coordinator" onclick="selectRole('coordinator')">
                                <div class="badge" style="background:#2F6FED">RC</div>
                                <div>
                                    <div class="text-[15px] font-semibold mb-0.5">Reading Coordinator</div>
                                    <div class="text-[13.5px] leading-snug" style="color:var(--muted)">Manages teachers, students, sessions, and reporting.</div>
                                </div>
                            </div>
                            <div class="role-card flex items-start gap-4 px-5 py-4" data-role="teacher" onclick="selectRole('teacher')">
                                <div class="badge" style="background:#1E9E6B">T</div>
                                <div>
                                    <div class="text-[15px] font-semibold mb-0.5">Teacher</div>
                                    <div class="text-[13.5px] leading-snug" style="color:var(--muted)">Monitors assigned class and tracking student reading progress.</div>
                                </div>
                            </div>
                        </div>

                        <button id="continueBtn" class="btn-primary w-full py-3 text-[15px]" type="button" onclick="goToRegistrationForm()">Continue as Teacher</button>

                        <p class="text-center text-[13.5px] mt-5" style="color:var(--muted)">
                            Already have an account? <a href="index.php" class="link-blue font-medium">Sign in</a>
                        </p>
                    </div>

                    <form id="registrationForm" class="card px-8 sm:px-10 py-9 fade-screen <?php echo $error ? '' : 'hidden'; ?>" method="POST" action="Registration.php" novalidate>
                        <div class="flex items-center gap-2 text-[14px] font-semibold mb-5">
                            <button class="flex items-center gap-2" type="button" onclick="showRoleScreen()"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg> Registration</button>
                        </div>
                        <p class="text-[14px] mb-6" style="color:var(--muted)">Cabilang Baybay Elementary School — Complete the form below</p>

                        <div class="flex items-center mb-7">
                            <div class="flex items-center flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="step-dot <?php echo $error ? 'done' : 'current'; ?>" id="personalStepDot"><?php echo $error ? '✓' : '1'; ?></span>
                                    <span class="text-[13.5px] font-semibold" style="color:var(--ink)">Personal Info</span>
                                </div>
                                <span class="step-line mx-2.5"></span>
                            </div>
                            <div class="flex items-center flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="step-dot <?php echo $error ? 'current' : 'pending'; ?>" id="accountStepDot">2</span>
                                    <span class="text-[13.5px] <?php echo $error ? 'font-semibold' : ''; ?>" style="color:<?php echo $error ? 'var(--ink)' : '#94A3B8'; ?>" id="accountStepLabel">Account Details</span>
                                </div>
                                <span class="step-line mx-2.5"></span>
                            </div>
                            <div class="flex items-center">
                                <div class="flex items-center gap-2">
                                    <span class="step-dot pending" id="reviewStepDot">3</span>
                                    <span class="text-[13.5px]" style="color:#94A3B8" id="reviewStepLabel">Review</span>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="role" id="roleInput" value="teacher">

                        <div class="flex items-center gap-2.5 border rounded-[10px] px-4 py-3 mb-6" style="border-color:var(--line); background:#F8FAFC">
                            <div class="badge !w-7 !h-7 !rounded-md !text-[11px]" id="roleBadge" style="background:#1E9E6B">T</div>
                            <span class="text-[13.5px]" style="color:var(--muted)">Registering as <b style="color:var(--ink)" id="roleLabel">Teacher</b></span>
                        </div>

                        <div id="personalStep" class="<?php echo $error ? 'hidden' : ''; ?>">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-[13.5px] font-semibold mb-1.5">First Name</label>
                                <input id="first_name" name="first_name" class="input-field" placeholder="Maria" value="<?php echo htmlspecialchars($submittedValues['first_name']); ?>">
                                <div class="field-err-msg" id="err-first">First name is required.</div>
                            </div>
                            <div>
                                <label class="block text-[13.5px] font-semibold mb-1.5">Last Name</label>
                                <input id="last_name" name="last_name" class="input-field" placeholder="Santos" value="<?php echo htmlspecialchars($submittedValues['last_name']); ?>">
                                <div class="field-err-msg" id="err-last">Last name is required.</div>
                            </div>
                        </div>

                        <div id="teacherFields">
                            <div class="mb-4">
                                <label class="block text-[13.5px] font-semibold mb-1.5">Employee ID</label>
                                <input id="employee_id" name="employee_id" class="input-field" placeholder="e.g. CBES-2025-001" value="<?php echo htmlspecialchars($submittedValues['employee_id']); ?>">
                                <div class="field-err-msg" id="err-empid">Employee ID is required.</div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-[13.5px] font-semibold mb-1.5">Grade Level</label>
                                    <select id="grade_level" name="grade_level" class="input-field bg-white">
                                        <option value="" <?php echo $submittedValues['grade_level'] === '' ? 'selected' : ''; ?> disabled>Select grade</option>
                                        <option value="Grade 1" <?php echo $submittedValues['grade_level'] === 'Grade 1' ? 'selected' : ''; ?>>Grade 1</option>
                                        <option value="Grade 2" <?php echo $submittedValues['grade_level'] === 'Grade 2' ? 'selected' : ''; ?>>Grade 2</option>
                                        <option value="Grade 3" <?php echo $submittedValues['grade_level'] === 'Grade 3' ? 'selected' : ''; ?>>Grade 3</option>
                                        <option value="Grade 4" <?php echo $submittedValues['grade_level'] === 'Grade 4' ? 'selected' : ''; ?>>Grade 4</option>
                                        <option value="Grade 5" <?php echo $submittedValues['grade_level'] === 'Grade 5' ? 'selected' : ''; ?>>Grade 5</option>
                                        <option value="Grade 6" <?php echo $submittedValues['grade_level'] === 'Grade 6' ? 'selected' : ''; ?>>Grade 6</option>
                                    </select>
                                    <div class="field-err-msg" id="err-grade">Please select a grade.</div>
                                </div>
                                <div>
                                    <label class="block text-[13.5px] font-semibold mb-1.5">Section</label>
                                    <input id="section" name="section" class="input-field" placeholder="e.g. Sampaguita" value="<?php echo htmlspecialchars($submittedValues['section']); ?>">
                                    <div class="field-err-msg" id="err-section">Section is required.</div>
                                </div>
                            </div>
                        </div>
                        <button class="btn-primary w-full py-3 text-[15px] mt-2" type="button" onclick="goToAccountStep()">Continue</button>
                        </div>

                        <div id="accountStep" class="<?php echo $error ? '' : 'hidden'; ?>">
                        <div class="mb-4">
                            <label class="block text-[13.5px] font-semibold mb-1.5">School Email Address</label>
                            <input id="email" name="email" type="email" class="input-field" placeholder="your@cbes.edu.ph" value="<?php echo htmlspecialchars($submittedValues['email']); ?>">
                            <div class="field-err-msg" id="err-email">Enter a valid email address.</div>
                        </div>

                        <div class="mb-2">
                            <label class="block text-[13.5px] font-semibold mb-1.5">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" class="input-field pr-11" placeholder="At least 8 characters" value="">
                                <button type="button" class="eye-btn absolute right-3 top-1/2 -translate-y-1/2" onclick="toggleVis('password')"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            </div>
                            <div class="field-err-msg" id="err-password">Password must meet the requirements.</div>
                            <ul class="text-[12.5px] mt-2 space-y-0.5 pl-4" style="list-style:disc; color:#94A3B8">
                                <li class="req-item" id="req-len">At least 8 characters</li>
                                <li class="req-item" id="req-upper">One uppercase letter</li>
                                <li class="req-item" id="req-num">One number</li>
                            </ul>
                        </div>

                        <div class="mb-2 mt-5">
                            <label class="block text-[13.5px] font-semibold mb-1.5">Confirm Password</label>
                            <div class="relative">
                                <input id="confirm_password" name="confirm_password" type="password" class="input-field pr-11" placeholder="Re-enter your password" value="">
                                <button type="button" class="eye-btn absolute right-3 top-1/2 -translate-y-1/2" onclick="toggleVis('confirm_password')"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            </div>
                            <div class="field-err-msg" id="err-confirm">Passwords do not match.</div>
                        </div>

                        <?php if ($error): ?>
                            <div class="mt-4 px-4 py-3 rounded-[10px] text-[13.5px]" style="background:#FEF2F2; color:#B91C1C; border:1px solid #FCA5A5"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="flex gap-3 mt-6">
                            <button class="btn-ghost flex-1 py-3 text-[15px]" type="button" onclick="showPersonalStep()">Back</button>
                            <button class="btn-primary flex-1 py-3 text-[15px]" type="button" onclick="goToReviewStep()">Continue</button>
                        </div>
                        </div>

                        <div id="reviewStep" class="hidden">
                            <div class="rounded-[12px] border px-6 py-5 mb-6" style="border-color:var(--line); background:#F8FAFC">
                                <p class="text-[12px] font-bold tracking-wide mb-3" style="color:#94A3B8">REVIEW YOUR INFORMATION</p>
                                <div class="flex items-center justify-between gap-4 py-2.5 border-b" style="border-color:var(--line)">
                                    <span style="color:var(--muted)">Role</span>
                                    <span id="reviewRole" class="font-medium text-right"></span>
                                </div>
                                <div class="flex items-center justify-between gap-4 py-2.5 border-b" style="border-color:var(--line)">
                                    <span style="color:var(--muted)">Full Name</span>
                                    <span id="reviewName" class="font-medium text-right"></span>
                                </div>
                                <div id="reviewEmployeeRow" class="flex items-center justify-between gap-4 py-2.5 border-b" style="border-color:var(--line)">
                                    <span style="color:var(--muted)">Employee ID</span>
                                    <span id="reviewEmployeeId" class="font-medium text-right"></span>
                                </div>
                                <div id="reviewClassRow" class="flex items-center justify-between gap-4 py-2.5 border-b" style="border-color:var(--line)">
                                    <span style="color:var(--muted)">Assigned Class</span>
                                    <span id="reviewClass" class="font-medium text-right"></span>
                                </div>
                                <div class="flex items-center justify-between gap-4 py-2.5 border-b" style="border-color:var(--line)">
                                    <span style="color:var(--muted)">Email</span>
                                    <span id="reviewEmail" class="font-medium text-right break-all"></span>
                                </div>
                                <div class="flex items-center justify-between gap-4 pt-2.5">
                                    <span style="color:var(--muted)">Password</span>
                                    <span id="reviewPassword" class="font-medium text-right break-all"></span>
                                </div>
                            </div>

                            <p class="text-[13.5px] mb-6" style="color:var(--muted)">By submitting, your account will be pending approval before you can log in.</p>
                            <div class="flex gap-3">
                                <button class="btn-ghost flex-1 py-3 text-[15px]" type="button" onclick="showAccountStep()">Back</button>
                                <button class="btn-primary flex-1 py-3 text-[15px]" type="submit">Submit Registration</button>
                            </div>
                        </div>
                        <p class="text-center text-[13.5px] mt-5" style="color:var(--muted)">Already have an account? <a href="index.php" class="link-blue font-medium">Sign in</a></p>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const roleMeta = {
            principal: {
                label: 'Principal', short: 'P', color: '#EA9A1D',
                headline: 'Oversee School', highlight: 'Reading Progress',
                description: 'View school-wide reading data, monitor reports, and track activity logs.'
            },
            coordinator: {
                label: 'Reading Coordinator', short: 'RC', color: '#2F6FED',
                headline: 'Coordinate the', highlight: 'Reading Program',
                description: 'Manage teachers, students, sessions, and generate comprehensive reports.'
            },
            teacher: {
                label: 'Teacher', short: 'T', color: '#1E9E6B',
                headline: 'Join the', highlight: 'Teaching Team',
                description: "Register to start monitoring your students' reading progress and scheduling remedial sessions."
            }
        };
        const selectedRole = { value: 'teacher' };
        const registrationSteps = [
            'Select your role',
            'Fill in your personal information',
            'Set up your school email and password',
            'Submit for approval'
        ];

        function selectRole(role) {
            selectedRole.value = role;
            const meta = roleMeta[role] || roleMeta.teacher;
            const roleInput = document.getElementById('roleInput');
            if (roleInput) {
                roleInput.value = roleMeta[role] ? role : 'teacher';
            }
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.toggle('selected', card.dataset.role === selectedRole.value);
            });
            const button = document.getElementById('continueBtn');
            if (button) {
                button.textContent = 'Continue as ' + meta.label;
            }
            const leftBody = document.getElementById('leftBody');
            if (leftBody) {
                leftBody.innerHTML = `
                    <div class="mb-8">
                        <h2 class="text-[36px] leading-[1.15] font-extrabold mb-4">${meta.headline} <span style="color:var(--blue-accent)">${meta.highlight}</span></h2>
                        <p class="text-[15px] leading-relaxed text-slate-300">${meta.description}</p>
                    </div>
                    <div class="space-y-3">
                        ${registrationSteps.map((step, index) => `
                            <div class="flex items-center gap-3 text-[14px] text-slate-300">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center bg-white/10 text-[12px] font-bold flex-none" style="color:var(--blue-accent)">${index + 1}</span>
                                <span>${step}</span>
                            </div>
                        `).join('')}
                    </div>`;
            }
            const roleBadge = document.getElementById('roleBadge');
            const roleLabel = document.getElementById('roleLabel');
            if (roleBadge && roleLabel) {
                roleBadge.textContent = meta.short;
                roleBadge.style.background = meta.color;
                roleLabel.textContent = meta.label;
            }
            const teacherFields = document.getElementById('teacherFields');
            if (teacherFields) {
                teacherFields.style.display = role === 'teacher' ? 'block' : 'none';
            }
        }

        function showRoleScreen() {
            const regForm = document.getElementById('registrationForm');
            const roleScreen = document.getElementById('roleScreen');
            if (regForm) regForm.classList.add('hidden');
            if (roleScreen) roleScreen.classList.remove('hidden');
        }

        function goToRegistrationForm() {
            const form = document.getElementById('registrationForm');
            const roleScreen = document.getElementById('roleScreen');
            if (form) form.classList.remove('hidden');
            if (roleScreen) roleScreen.classList.add('hidden');
            selectRole(selectedRole.value);
        }

        function updateRegistrationStep(step) {
            const personalStep = document.getElementById('personalStep');
            const accountStep = document.getElementById('accountStep');
            const reviewStep = document.getElementById('reviewStep');
            const personalDot = document.getElementById('personalStepDot');
            const accountDot = document.getElementById('accountStepDot');
            const reviewDot = document.getElementById('reviewStepDot');
            const personalLabel = personalDot?.nextElementSibling;
            const accountLabel = document.getElementById('accountStepLabel');
            const reviewLabel = document.getElementById('reviewStepLabel');
            const stepOrder = ['personal', 'account', 'review'];
            const currentIndex = stepOrder.indexOf(step);

            personalStep?.classList.toggle('hidden', step !== 'personal');
            accountStep?.classList.toggle('hidden', step !== 'account');
            reviewStep?.classList.toggle('hidden', step !== 'review');

            [personalDot, accountDot, reviewDot].forEach((dot, index) => {
                if (!dot) return;
                dot.className = 'step-dot ' + (index < currentIndex ? 'done' : index === currentIndex ? 'current' : 'pending');
                dot.textContent = index < currentIndex ? '✓' : String(index + 1);
            });

            [personalLabel, accountLabel, reviewLabel].forEach((label, index) => {
                if (!label) return;
                label.classList.toggle('font-semibold', index === currentIndex);
                label.style.color = index === currentIndex ? 'var(--ink)' : '#94A3B8';
            });
        }

        function goToAccountStep() {
            const first = document.getElementById('first_name').value.trim();
            const last = document.getElementById('last_name').value.trim();
            let ok = validate('first_name', 'err-first', !!first);
            ok = validate('last_name', 'err-last', !!last) && ok;

            if (selectedRole.value === 'teacher') {
                const employeeId = document.getElementById('employee_id').value.trim();
                const grade = document.getElementById('grade_level').value.trim();
                const section = document.getElementById('section').value.trim();
                ok = validate('employee_id', 'err-empid', !!employeeId) && ok;
                ok = validate('grade_level', 'err-grade', !!grade) && ok;
                ok = validate('section', 'err-section', !!section) && ok;
            }

            if (ok) {
                updateRegistrationStep('account');
            }
        }

        function showPersonalStep() {
            updateRegistrationStep('personal');
        }

        function showAccountStep() {
            updateRegistrationStep('account');
        }

        function updateReviewSummary() {
            const role = selectedRole.value;
            const isTeacher = role === 'teacher';
            const grade = document.getElementById('grade_level')?.value || '';
            const section = document.getElementById('section')?.value.trim() || '';
            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) element.textContent = value;
            };

            setText('reviewRole', roleMeta[role]?.label || roleMeta.teacher.label);
            setText('reviewName', [
                document.getElementById('first_name').value.trim(),
                document.getElementById('last_name').value.trim()
            ].filter(Boolean).join(' '));
            setText('reviewEmployeeId', document.getElementById('employee_id')?.value.trim() || '');
            setText('reviewClass', [grade, section].filter(Boolean).join(' — '));
            setText('reviewEmail', document.getElementById('email').value.trim());
            setText('reviewPassword', document.getElementById('password').value);
            document.getElementById('reviewEmployeeRow')?.classList.toggle('hidden', !isTeacher);
            document.getElementById('reviewClassRow')?.classList.toggle('hidden', !isTeacher);
        }

        function goToReviewStep() {
            const email = document.getElementById('email').value.trim();
            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            let ok = validate('email', 'err-email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
            const passOk = pass.length >= 8 && /[A-Z]/.test(pass) && /[0-9]/.test(pass);
            ok = validate('password', 'err-password', passOk) && ok;
            ok = validate('confirm_password', 'err-confirm', !!pass && pass === confirmPass) && ok;

            if (ok) {
                updateReviewSummary();
                updateRegistrationStep('review');
            }
        }

        function toggleVis(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function eyeIcon() {
            return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

        function backArrow() {
            return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>';
        }

        function setReq(id, passed) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('ok', passed);
        }

        function checkPasswordRules() {
            const passwordInput = document.getElementById('password');
            if (!passwordInput) return;
            const p = passwordInput.value || '';
            setReq('req-len', p.length >= 8);
            setReq('req-upper', /[A-Z]/.test(p));
            setReq('req-num', /[0-9]/.test(p));
        }

        document.getElementById('password')?.addEventListener('input', checkPasswordRules);

        function validate(id, errId, valid) {
            const input = document.getElementById(id);
            const err = document.getElementById(errId);
            if (input) input.classList.toggle('err', !valid);
            if (err) err.style.display = valid ? 'none' : 'block';
            return valid;
        }

        document.getElementById('registrationForm')?.addEventListener('submit', function (event) {
            const role = selectedRole.value;
            const email = document.getElementById('email').value.trim();
            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;

            let ok = validate('email', 'err-email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));

            const passOk = pass.length >= 8 && /[A-Z]/.test(pass) && /[0-9]/.test(pass);
            ok = validate('password', 'err-password', passOk) && ok;
            ok = validate('confirm_password', 'err-confirm', !!pass && pass === confirmPass) && ok;

            if (!ok) {
                event.preventDefault();
            }

            if (!ok) {
                updateRegistrationStep('account');
            }
        });

        window.addEventListener('DOMContentLoaded', function () {
            const roleInput = document.getElementById('roleInput');
            selectRole(roleInput?.value || selectedRole.value);
            updateRegistrationStep(<?php echo $error ? "'account'" : "'personal'"; ?>);
            checkPasswordRules();
        });
    </script>
</body>
</html>
