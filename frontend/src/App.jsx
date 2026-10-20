import { useEffect, useRef, useState } from 'react'
import { BrowserRouter, Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { api, apiFileObjectUrl, clearToken, downloadApiFile, getToken, getUser, isUserCacheFresh, openApiFile, saveToken, saveUser } from './api'
import './App.css'

const localNow = new Date()
const todayDate = `${localNow.getFullYear()}-${String(localNow.getMonth() + 1).padStart(2, '0')}-${String(localNow.getDate()).padStart(2, '0')}`

const adminLinks = [
  ['dashboard.php', 'Dashboard', 'fa-gauge-high'], ['students.php', 'Pupils', 'fa-graduation-cap'],
  ['endorsements.php', 'Endorsement & Approval', 'fa-file-signature'], ['remedial_sessions.php', 'Remedial Monitoring', 'fa-hand-holding-heart'],
  ['remedial_attendance.php', 'Attendance', 'fa-calendar-check'], ['learning_materials.php', 'Learning Materials', 'fa-book'],
  ['reports.php', 'Reports', 'fa-file-lines'], ['teacher_accounts.php', 'User Management', 'fa-user-tie'], ['settings.php', 'Settings', 'fa-gear'],
]
const teacherLinks = [
  ['teacher_dashboard.php', 'Dashboard', 'fa-gauge-high'], ['my_students.php', 'Pupils', 'fa-users'],
  ['endorsements.php', 'Endorsement & Approval', 'fa-file-signature'], ['interventions.php', 'Remedial Monitoring', 'fa-hand-holding-heart'],
  ['remedial_attendance.php', 'Attendance', 'fa-calendar-check'], ['teacher_learning_materials.php', 'Learning Materials', 'fa-book'],
  ['reports.php', 'Reports', 'fa-file-lines'], ['settings.php', 'Settings', 'fa-gear'],
]

function Login() {
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event) {
    event.preventDefault()
    setError(''); setBusy(true)
    const body = Object.fromEntries(new FormData(event.currentTarget))
    try {
      const result = await api('/auth/login', { method: 'POST', body })
      saveToken(result.token)
      saveUser(result.user)
      navigate(result.user.role === 'teacher' ? '/teacher_dashboard.php' : '/dashboard.php')
    } catch (failure) {
      if (failure.status === 403) setError(failure.message)
      else if (failure.status === 422) setError(failure.errors?.email?.[0] || 'Invalid email or password.')
      else setError('Unable to reach the sign-in service. Check that the backend and database are running, then try again.')
    } finally { setBusy(false) }
  }
  return <div className="login-page"><div className="login-container">
    <div className="login-left"><div className="logo-icon"><i className="fa-solid fa-book-open-reader" /></div><h1>Project Subaybay</h1><p>Reading Monitoring System for tracking and improving student reading performance across all grade levels.</p></div>
    <div className="login-right"><h2>Welcome Back!</h2><p className="subtitle">Sign in to your account to continue</p>
      {error && <div className="alert alert-danger">{error}</div>}
      <form className="login-form" onSubmit={submit}>
        <div className="form-group"><label className="form-label" htmlFor="email">Email Address</label><input id="email" type="email" name="email" className="form-input" placeholder="Enter your email" required /></div>
        <div className="form-group"><label className="form-label" htmlFor="password">Password</label><input id="password" type="password" name="password" className="form-input" placeholder="Enter your password" required /></div>
        <button className="btn-login" type="submit" disabled={busy}><i className="fa-solid fa-arrow-right-to-bracket" /> {busy ? 'Signing In…' : 'Sign In'}</button>
      </form><div className="login-footer">Don't have an account? <Link to="/Registration.php">Register here</Link></div>
    </div>
  </div></div>
}

function Registration() {
  const [role, setRole] = useState('principal')
  const [step, setStep] = useState(-1)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(null)
  const [values, setValues] = useState({ first_name: '', last_name: '', employee_id: '', grade_level: '', section: '', email: '', password: '', password_confirmation: '' })
  const [showPassword, setShowPassword] = useState({})
  function setField(event) { setValues((current) => ({ ...current, [event.target.name]: event.target.value })); setError('') }
  function nextStep(target) {
    const value = (key) => values[key].trim()
    if (target === 1 && (!value('first_name') || !value('last_name') || (role === 'teacher' && (!value('employee_id') || !value('grade_level') || !value('section'))))) { setError('Complete the required personal information before continuing.'); return }
    if (target === 2) {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value('email'))) { setError('Enter a valid email address.'); return }
      if (values.password.length < 8 || !/[A-Z]/.test(values.password) || !/[0-9]/.test(values.password)) { setError('Password must be at least 8 characters long, include one uppercase letter, and one number.'); return }
      if (values.password !== values.password_confirmation) { setError('Passwords do not match.'); return }
    }
    setError(''); setStep(target)
  }
  async function submit(event) {
    event.preventDefault(); setError(''); setSuccess(null)
    try { setSuccess(await api('/auth/register', { method: 'POST', body: { ...values, role } })) }
    catch (failure) { setError(failure.message || Object.values(failure.errors || {}).flat().join(' ')) }
  }
  const roles = [['principal', 'P', 'Principal', 'School administrator with view-only access and full reports.', '#EA9A1D'], ['coordinator', 'RC', 'Reading Coordinator', 'Manages teachers, students, sessions, and reporting.', '#2F6FED'], ['teacher', 'T', 'Teacher', 'Monitors assigned class and tracks student reading progress.', '#1E9E6B']]
  const selectedRole = roles.find(([key]) => key === role)
  return <div className="registration-page"><aside className="registration-aside"><Link to="/index.php" className="registration-brand"><span className="registration-brand-icon"><i className="fa-solid fa-book-open-reader" /></span><span><b>Project Subaybay</b><small>Reading Progress Monitoring System</small></span></Link><div className="registration-aside-copy"><h1>Every reader deserves a clear path forward.</h1><p>Support student reading growth with meaningful assessment and progress tracking.</p><div className="registration-aside-stat"><i className="fa-solid fa-book-open" /><span>Reading progress monitoring<br /><b>Cabilang Baybay Elementary School</b></span></div></div><small className="registration-copyright">© 2025 Project Subaybay — Cabilang Baybay Elementary School</small></aside>
    <main className="registration-main"><div className="registration-content">
      {success ? <div className="registration-card registration-success"><div className="success-mark"><i className="fa-solid fa-check" /></div><h2>Registration Submitted!</h2><p>Your account request has been submitted for approval. You can sign in once your account is activated.</p><section><b>SUBMITTED DETAILS</b>{Object.entries(success.details || {}).map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}</section><Link className="btn-login" to="/index.php">Back to Login</Link></div> : step === -1 ? <div className="registration-card"><p className="registration-kicker">Cabilang Baybay Elementary School</p><h2>Select your role to get started</h2><div className="role-options">{roles.map(([key, badgeText, label, description, color]) => <button type="button" key={key} className={`role-option ${role === key ? 'selected' : ''}`} onClick={() => setRole(key)}><span className="role-badge" style={{ background: color }}>{badgeText}</span><span><b>{label}</b><small>{description}</small></span><i className={`fa-solid ${role === key ? 'fa-circle-check' : 'fa-circle'}`} /></button>)}</div><button type="button" className="btn-login" onClick={() => { setError(''); setStep(0) }}>Continue as {selectedRole[2]}</button><div className="login-footer">Already have an account? <Link to="/index.php">Sign in</Link></div></div> : <form className="registration-card" onSubmit={submit}>
        <button className="registration-back" type="button" onClick={() => { setError(''); setStep(step === 0 ? -1 : step - 1) }}><i className="fa-solid fa-arrow-left" /> Registration</button><p className="registration-kicker">Cabilang Baybay Elementary School — Complete the form below</p>
        <div className="registration-steps">{['Personal Info', 'Account Details', 'Review'].map((name, index) => <div key={name} className={`registration-step ${step >= index ? 'current' : ''}`}><span>{step > index ? '✓' : index + 1}</span><b>{name}</b>{index < 2 && <i />}</div>)}</div>
        <div className="selected-role-note"><span className="role-badge" style={{ background: selectedRole[4] }}>{selectedRole[1]}</span> Registering as <b>{selectedRole[2]}</b></div>
        {error && <div className="alert alert-danger">{error}</div>}
        {step === 0 && <div className="registration-fields"><div className="registration-field-row"><div className="form-group"><label>First Name</label><input name="first_name" className="form-input" placeholder="Maria" value={values.first_name} onChange={setField} autoComplete="given-name" /></div><div className="form-group"><label>Last Name</label><input name="last_name" className="form-input" placeholder="Santos" value={values.last_name} onChange={setField} autoComplete="family-name" /></div></div>{role === 'teacher' && <><div className="form-group"><label>Employee ID</label><input name="employee_id" className="form-input" placeholder="e.g. CBES-2025-001" value={values.employee_id} onChange={setField} /></div><div className="registration-field-row"><div className="form-group"><label>Grade Level</label><select name="grade_level" className="form-select" value={values.grade_level} onChange={setField}><option value="">Select grade</option>{Array.from({ length: 6 }, (_, i) => `Grade ${i + 1}`).map((grade) => <option key={grade}>{grade}</option>)}</select></div><div className="form-group"><label>Section</label><input name="section" className="form-input" placeholder="e.g. Sampaguita" value={values.section} onChange={setField} /></div></div></>}<button type="button" className="btn-login" onClick={() => nextStep(1)}>Continue</button></div>}
        {step === 1 && <div className="registration-fields"><div className="form-group"><label>Email Address</label><input type="email" name="email" className="form-input" placeholder="your@cbes.edu.ph" value={values.email} onChange={setField} autoComplete="email" /></div>{['password', 'password_confirmation'].map((name, index) => <div className="form-group" key={name}><label>{index ? 'Confirm Password' : 'Password'}</label><div className="password-input-wrap"><input type={showPassword[name] ? 'text' : 'password'} name={name} className="form-input" placeholder={index ? 'Re-enter your password' : 'At least 8 characters'} value={values[name]} onChange={setField} autoComplete={index ? 'new-password' : 'new-password'} /><button type="button" aria-label={showPassword[name] ? 'Hide password' : 'Show password'} onClick={() => setShowPassword((current) => ({ ...current, [name]: !current[name] }))}><i className={`fa-solid ${showPassword[name] ? 'fa-eye-slash' : 'fa-eye'}`} /></button></div></div>)}<div className="password-requirements"><span className={values.password.length >= 8 ? 'valid' : ''}>8 or more characters</span><span className={/[A-Z]/.test(values.password) ? 'valid' : ''}>One uppercase letter</span><span className={/[0-9]/.test(values.password) ? 'valid' : ''}>One number</span></div><button type="button" className="btn-login" onClick={() => nextStep(2)}>Continue</button></div>}
        {step === 2 && <div className="registration-fields"><h3>Review your information</h3><div className="registration-review">{[['Role', selectedRole[2]], ['Name', `${values.first_name} ${values.last_name}`], ...(role === 'teacher' ? [['Employee ID', values.employee_id], ['Assigned To', `${values.grade_level} — ${values.section}`]] : []), ['Email', values.email]].map(([label, value]) => <div key={label}><span>{label}</span><b>{value}</b></div>)}</div><button className="btn-login" type="submit">Submit Registration</button></div>}
        <div className="login-footer">Already have an account? <Link to="/index.php">Sign in</Link></div>
      </form>}
    </div></main>
  </div>
}

function Protected({ children }) {
  const hasToken = Boolean(getToken())
  const cachedUser = hasToken ? getUser() : null
  const [checked, setChecked] = useState(Boolean(cachedUser))
  const [user, setUser] = useState(cachedUser)
  useEffect(() => {
    if (!getToken()) { setChecked(true); return }
    if (cachedUser && isUserCacheFresh()) return
    api('/auth/me')
      .then(({ user: current }) => { saveUser(current); setUser(current) })
      .catch(() => { clearToken(); setUser(null) })
      .finally(() => setChecked(true))
  }, [])
  if (!checked) return null
  return user ? children(user) : <Navigate to="/index.php" replace />
}

function Shell({ user, children }) {
  const navigate = useNavigate()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const links = user.role === 'teacher' ? teacherLinks : adminLinks
  async function logout() { try { await api('/auth/logout', { method: 'POST' }) } catch {} clearToken(); navigate('/index.php') }
  const initials = (user.full_name || 'User').split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
  useEffect(() => { setMenuOpen(false) }, [location.pathname])
  useEffect(() => {
    if (!menuOpen) return undefined
    const closeOnEscape = (event) => { if (event.key === 'Escape') setMenuOpen(false) }
    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [menuOpen])
  return <div className="app-container"><aside id="primary-navigation" className={`sidebar${menuOpen ? ' active' : ''}`}><div className="sidebar-header"><div className="logo-icon"><i className="fa-solid fa-book-open-reader" /></div><div className="logo-text"><h1>Project<br />Subaybay</h1><span>Reading Monitoring</span></div></div>
    <div className="sidebar-menu"><p className="menu-label">MAIN MENU</p><ul>{links.map(([path, label, icon]) => <li key={path} className={location.pathname.endsWith(path) ? 'active' : ''}><Link to={`/${path}`} onClick={() => setMenuOpen(false)}><i className={`fa-solid ${icon}`} /><span>{label}</span></Link></li>)}</ul></div>
    <div className="sidebar-footer"><div className="user-profile"><div className="avatar">{initials}</div><div className="user-info"><span className="user-name">{user.full_name}</span><span className="user-role">{user.role === 'coordinator' ? 'Reading Coordinator' : user.role === 'principal' ? 'Principal' : user.section || 'Teacher'}</span></div></div><Link to="/profile.php" className="logout-btn"><i className="fa-solid fa-user-gear" /><span>My Profile</span></Link><button className="logout-btn" id="logoutBtn" onClick={logout}><i className="fa-solid fa-arrow-right-from-bracket" /><span>Log Out</span></button></div>
  </aside>{menuOpen && <button type="button" className="sidebar-overlay active" aria-label="Close navigation menu" onClick={() => setMenuOpen(false)} />}<main className="main-content"><header className="top-header"><button type="button" className="hamburger-menu" aria-label={menuOpen ? 'Close navigation menu' : 'Open navigation menu'} aria-expanded={menuOpen} aria-controls="primary-navigation" onClick={() => setMenuOpen((open) => !open)}><i className={`fa-solid ${menuOpen ? 'fa-xmark' : 'fa-bars'}`} /></button><div className="breadcrumb"><span className="current">Reading Performance Dashboard</span></div><div className="header-actions"><div className="dropdown"><button className="btn-dropdown"><span>School Year 2024-2025</span><i className="fa-solid fa-chevron-down" /></button></div><div className="dropdown"><button className="btn-dropdown"><span>Period: End of Year</span><i className="fa-solid fa-chevron-down" /></button></div><Link to="/profile.php" className="btn-icon" title="My Profile"><i className="fa-solid fa-gear" /></Link><Link to="/profile.php" className="user-avatar-small">{initials}</Link></div></header><div className="page-content">{children}</div></main></div>
}

function PaginationControls({ pagination, onPageChange }) {
  if (!pagination || pagination.last_page <= 1) return null
  return <nav className="pagination-controls" aria-label="Table pages">
    <button type="button" className="btn btn-sm btn-secondary" disabled={pagination.current_page <= 1} onClick={() => onPageChange(pagination.current_page - 1)}>Previous</button>
    <span>Page {pagination.current_page} of {pagination.last_page}</span>
    <button type="button" className="btn btn-sm btn-secondary" disabled={pagination.current_page >= pagination.last_page} onClick={() => onPageChange(pagination.current_page + 1)}>Next</button>
  </nav>
}

function Dashboard() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [studentSearch, setStudentSearch] = useState('')
  const [debouncedStudentSearch, setDebouncedStudentSearch] = useState('')
  const [studentPage, setStudentPage] = useState(1)
  const [studentResult, setStudentResult] = useState({ data: [], pagination: null })
  useEffect(() => { api('/dashboard').then(setData).catch((failure) => setError(failure.message)) }, [])
  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedStudentSearch(studentSearch.trim()), 250)
    return () => window.clearTimeout(timeout)
  }, [studentSearch])
  useEffect(() => {
    // The authenticated user is cached before this protected page mounts, so
    // start the teacher's table request alongside the dashboard request.
    if (getUser()?.role !== 'teacher') return undefined
    let current = true
    const query = new URLSearchParams({ page: String(studentPage), per_page: '25' })
    if (debouncedStudentSearch) query.set('search', debouncedStudentSearch)
    api(`/students?${query}`).then((result) => { if (current) setStudentResult(result) }).catch((failure) => { if (current) setError(failure.message) })
    return () => { current = false }
  }, [studentPage, debouncedStudentSearch])
  if (error) return <div className="alert alert-danger">{error}</div>
  if (!data) return <div className="spinner" aria-label="Loading" />
  const m = data.metrics
  const teacher = data.role === 'teacher'
  const students = studentResult.data || []
  const levelValues = [['Independent', Number(m.independent || 0), '#20c55e'], ['Instructional', Number(m.instructional || 0), '#f59e0b'], ['Frustration', Number(m.frustration || 0), '#f04444']]
  const levelTotal = levelValues.reduce((total, [, value]) => total + value, 0)
  const progressRows = data.recent_progress || []
  const attendanceNote = `${m.present_attendance} present / ${m.absent_attendance} absent`
  const quickStats = teacher
    ? [['/my_students.php', 'blue', 'fa-users', m.students, 'My Assigned Pupils', 'View pupil list'], ['/interventions.php', 'purple', 'fa-hand-holding-heart', m.remedial_pupils, 'Pupils in Remedial Reading', 'View remedial sessions'], ['/endorsements.php', 'yellow', 'fa-file-signature', m.pending_endorsements, 'Pending Endorsements', 'Review submissions'], ['/remedial_attendance.php', 'green', 'fa-calendar-check', `${m.attendance_rate}%`, 'Attendance Summary', attendanceNote]]
    : [['/interventions.php', 'purple', 'fa-hand-holding-heart', m.remedial_pupils, 'Pupils in Remedial Reading', 'View remedial sessions'], ['/endorsements.php', 'orange', 'fa-file-signature', m.pending_endorsements, 'Pending Endorsements', 'Review submissions'], ['/remedial_attendance.php', 'blue', 'fa-calendar-check', `${m.attendance_rate}%`, 'Attendance Summary', attendanceNote]]
  return <div className={teacher ? 'dashboard-page teacher-dashboard' : 'dashboard-page'}><div className={`page-title ${teacher ? '' : 'dashboard-page-title'}`}>{teacher ? 'Teacher Dashboard' : 'Reading Performance Dashboard'}</div><div className="page-subtitle">{teacher ? 'Overview of your assigned pupils' : 'Monitoring reading levels across all grade levels'}</div>
    <div className="stats-grid">{[[teacher ? 'blue' : 'blue', teacher ? 'fa-users' : 'fa-graduation-cap', m.students, teacher ? 'Total Assigned Pupils' : 'Total Pupils Monitored'], ['green', 'fa-user-check', m.independent, 'Independent Level'], ['yellow', 'fa-book-open', m.instructional, 'Instructional Level'], ['red', 'fa-triangle-exclamation', m.frustration, 'Frustration Level']].map(([color, icon, amount, label]) => <div className="stat-card" key={label}><div className={`stat-icon ${color}`}><i className={`fa-solid ${icon}`} /></div><div className="stat-info"><h3>{amount}</h3><p>{label}</p></div></div>)}</div>
    <div className={`stats-grid ${teacher ? '' : 'dashboard-secondary-stats'}`}>{quickStats.map(([to, color, icon, value, label, note]) => <Link to={to} className="stat-card dashboard-quick-link" key={label}><div className={`stat-icon ${color}`}><i className={`fa-solid ${icon}`} /></div><div className="stat-info"><h3>{value}</h3><p>{label}</p><small>{note}</small></div></Link>)}</div>
    {!teacher && <><div className="grid-2 dashboard-charts"><div className="card"><div className="card-header"><div><div className="card-title">Reading Level Distribution</div><div className="card-subtitle">2024-2025: End of Year</div></div></div><div className="card-body"><DashboardDonut values={levelValues} total={levelTotal} /></div></div><div className="card"><div className="card-header"><div><div className="card-title">Grade-Level Reading Performance</div><div className="card-subtitle">Percentage of pupils per reading level by grade</div></div></div><div className="card-body"><DashboardGradeChart rows={data.grade_distribution || []} /></div></div></div><div className="card intervention-table-card"><div className="card-header"><div><div className="card-title">Pupils Needing Immediate Intervention</div><div className="card-subtitle">Pupils currently at Frustration level</div></div><span className="record-count">{data.intervention_pupils?.length || 0} pupils</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table intervention-table"><thead><tr><th>Pupil Name</th><th>Grade</th><th>Reading Level</th><th>Last Assessment Date</th></tr></thead><tbody>{(data.intervention_pupils || []).map((student) => <tr key={student.id}><td><Link className="intervention-pupil-link" to={`/pupil_profile.php?id=${student.id}`}><span className="intervention-initial">{(student.full_name || '?').trim().charAt(0).toUpperCase()}</span>{student.full_name}</Link></td><td>{student.grade_level}</td><td><span className="badge-status badge-red">{student.reading_profile}</span></td><td>{student.last_assessment || 'N/A'}</td></tr>)}{!data.intervention_pupils?.length && <tr><td colSpan="4">No pupils currently need immediate intervention.</td></tr>}</tbody></table></div></div></div><div className="grid-2 dashboard-lower-grid"><DashboardTable title="Recent Reading Assessments" subtitle="Latest Phil-IRI / CRLA results" rows={data.recent_assessments} columns={['full_name', 'grade_level', 'tool', 'profile', 'total_score', 'assessment_date']} /><DashboardProgress rows={progressRows} /></div><DashboardMaterials rows={data.material_history} /></>}
    {teacher && <><div className="card"><div className="card-header"><div><div className="card-title">My Students Overview</div><div className="search-filter-bar dashboard-search"><input className="search-input" placeholder="Search pupils..." value={studentSearch} onChange={(event) => { setStudentSearch(event.target.value); setStudentPage(1) }} /></div></div><span className="record-count">{studentResult.pagination?.total ?? students.length} pupils</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Pupil</th><th>LRN</th><th>Grade / Section</th><th>Reading Level</th><th>Score</th><th>Last Assessment</th></tr></thead><tbody>{students.map((student) => <tr key={student.id}><td><Link to={`/pupil_profile.php?id=${student.id}`}>{student.full_name}</Link></td><td>{student.lrn}</td><td>{student.grade_level} - {student.section}</td><td>{student.reading_profile || 'Not assessed'}</td><td>{student.score}</td><td>{student.latest_assessment || '—'}</td></tr>)}{!students.length && <tr><td colSpan="6">No assigned pupils found.</td></tr>}</tbody></table></div><PaginationControls pagination={studentResult.pagination} onPageChange={setStudentPage} /></div></div><div className="card intervention-alert"><div className="card-header"><div className="card-title">Pupils Needing Immediate Intervention</div><span className="record-count">{data.intervention_pupils?.length || 0} pupils</span></div><div className="card-body"><div className="pupil-list-inline">{(data.intervention_pupils || []).map((student) => <Link to={`/pupil_profile.php?id=${student.id}`} className="pupil-list-item" key={student.id}><b>{student.full_name}</b><span>{student.grade_level}</span></Link>)}{!data.intervention_pupils?.length && <div className="empty-state">No pupils currently at Frustration level.</div>}</div></div></div><div className="card"><div className="card-header"><div className="card-title"><i className="fa-solid fa-chart-line" /> Reading Performance Overview</div></div><div className="card-body"><div className="grid-2"><div className="distribution-summary">{[['Independent', m.independent, 'bar-green'], ['Instructional', m.instructional, 'bar-yellow'], ['Frustration', m.frustration, 'bar-red']].map(([label, value, color]) => <div className="distribution-item" key={label}><div><span>{label}</span><b>{value}</b></div><div className="distribution-track"><span className={color} style={{ width: `${m.students ? Math.round(value / m.students * 100) : 0}%` }} /></div></div>)}</div><div className="grid-2">{(data.grade_distribution || []).map((grade) => <div className="stat-card" key={grade.grade_level}><div className="stat-info"><p>{grade.grade_level}</p><h3>{grade.independent + grade.instructional + grade.frustration}</h3></div></div>)}</div></div></div></div><div className="grid-2"><DashboardTable title="Upcoming Intervention Sessions" rows={data.upcoming_sessions} columns={['full_name', 'grade_level', 'session_date', 'start_time', 'session_type', 'status']} /><DashboardProgress rows={progressRows} /></div><DashboardMaterials rows={data.material_history} /></>}
  </div>
}

function DashboardDonut({ values, total }) {
  const radius = 72
  const circumference = 2 * Math.PI * radius
  return <div className="dashboard-donut-layout"><svg className="dashboard-donut" viewBox="0 0 180 180" role="img" aria-label={`Reading level distribution: ${values.map(([label, value]) => `${label} ${total ? Math.round(value / total * 100) : 0}%`).join(', ')}`}><circle cx="90" cy="90" r={radius} fill="none" stroke="#e8edf4" strokeWidth="27" />{values.map(([label, value, color], index) => { const length = total ? value / total * circumference : 0; const offset = values.slice(0, index).reduce((sum, [, previousValue]) => sum + (total ? previousValue / total * circumference : 0), 0); return <circle key={label} cx="90" cy="90" r={radius} fill="none" stroke={color} strokeWidth="27" strokeDasharray={`${length} ${circumference - length}`} strokeDashoffset={-offset} transform="rotate(-90 90 90)" /> })}</svg><div className="dashboard-donut-legend">{values.map(([label, value, color]) => { const percentage = total ? Math.round(value / total * 100) : 0; return <div className="dashboard-legend-row" key={label}><div className="dashboard-legend-title"><span style={{ background: color }} />{label}</div><div className="distribution-track"><span style={{ width: `${percentage}%`, background: color }} /></div><small>{percentage}%</small></div> })}</div></div>
}

function DashboardGradeChart({ rows }) {
  const grades = Array.from({ length: 6 }, (_, index) => `Grade ${index + 1}`).map((label) => rows.find((row) => row.grade_level === label) || { grade_level: label, independent: 0, instructional: 0, frustration: 0 })
  const series = [['independent', '#20c55e'], ['instructional', '#f59e0b'], ['frustration', '#f04444']]
  return <div className="dashboard-grade-chart" role="img" aria-label="Percentage of pupils at each reading level by grade"><div className="dashboard-chart-plot"><div className="dashboard-y-labels">{[60, 50, 40, 30, 20, 10, 0].map((value) => <span key={value}>{value}%</span>)}</div><div className="dashboard-chart-area">{[0, 10, 20, 30, 40, 50, 60].map((value) => <span className="dashboard-chart-gridline" key={value} style={{ bottom: `${value / 60 * 100}%` }} />)}{grades.map((grade) => { const count = series.reduce((sum, [key]) => sum + Number(grade[key] || 0), 0); return <div className="dashboard-grade-group" key={grade.grade_level}>{series.map(([key, color]) => { const percent = count ? Number(grade[key] || 0) / count * 100 : 0; return <span className="dashboard-grade-bar" key={key} title={`${grade.grade_level}: ${key} ${Math.round(percent)}%`} style={{ height: `${Math.min(60, percent) / 60 * 100}%`, background: color }} /> })}</div> })}</div></div><div className="dashboard-x-labels">{grades.map((grade, index) => <span key={grade.grade_level}>Gr. {index + 1}</span>)}</div><div className="dashboard-chart-legend">{series.map(([key, color]) => <span key={key}><i style={{ background: color }} />{key[0].toUpperCase() + key.slice(1)}</span>)}</div></div>
}

function DashboardTable({ title, subtitle, rows = [], columns }) {
  return <div className="card"><div className="card-header"><div><div className="card-title">{title}</div>{subtitle && <div className="card-subtitle">{subtitle}</div>}</div></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr>{columns.map((key) => <th key={key}>{key.replaceAll('_', ' ')}</th>)}</tr></thead><tbody>{(rows || []).map((row, index) => <tr key={row.id || index}>{columns.map((key) => <td key={key}>{row[key] ?? '—'}</td>)}</tr>)}{!rows?.length && <tr><td colSpan={columns.length}>No records found.</td></tr>}</tbody></table></div></div></div>
}

function DashboardProgress({ rows = [] }) {
  return <div className="card"><div className="card-header"><div><div className="card-title">Latest Progress Updates</div><div className="card-subtitle">Reading level changes</div></div><Link className="view-all" to="/progress_updates.php">View All</Link></div><div className="card-body">{rows?.map((row) => <div className="progress-item" key={row.id}><div><b>{row.full_name}</b><small>{row.grade_level} {row.section} · {row.update_date}</small></div><div className="progress-levels"><span className="badge-status badge-blue">{row.previous_profile}</span><i className={`fa-solid ${row.trend === 'improved' ? 'fa-arrow-up trend-up' : row.trend === 'declined' ? 'fa-arrow-down trend-down' : 'fa-minus trend-flat'}`} /><span className="badge-status badge-green">{row.current_profile}</span></div></div>)}{!rows?.length && <div className="empty-state">No recent progress updates.</div>}</div></div>
}

function DashboardMaterials({ rows = [] }) {
  return <DashboardTable title="History of Learning Materials Given to Pupils" subtitle="Recent materials distributed to pupils or class sections" rows={rows} columns={['title', 'material_type', 'given_to', 'grade_level', 'date_given', 'given_by_name']} />
}

function StudentAvatar({ student }) {
  const avatarRef = useRef(null)
  const [image, setImage] = useState('')
  useEffect(() => {
    let objectUrl
    let active = true
    const loadImage = () => {
      if (!student.profile_image) return
      apiFileObjectUrl(`/students/${student.id}/image`).then((url) => {
        if (!active) { URL.revokeObjectURL(url); return }
        objectUrl = url
        setImage(url)
      }).catch(() => { if (active) setImage('') })
    }
    let observer
    if (student.profile_image && 'IntersectionObserver' in window && avatarRef.current) {
      observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) { observer.disconnect(); loadImage() }
      }, { rootMargin: '100px' })
      observer.observe(avatarRef.current)
    } else loadImage()
    return () => { active = false; observer?.disconnect(); if (objectUrl) URL.revokeObjectURL(objectUrl) }
  }, [student.id, student.profile_image])
  const initials = (student.full_name || '').split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
  return <div ref={avatarRef} className="pupil-avatar">{image ? <img src={image} alt="" /> : initials}</div>
}

function MaterialsGrid({ rows, canDelete, onDelete, onError }) {
  const iconForSkill = (skill) => skill === 'Phonological Awareness' ? 'fa-ear-listen' : ['Reading Fluency', 'Accuracy & Pacing', 'Prosody'].includes(skill) ? 'fa-headphones' : skill === 'Reading Comprehension' ? 'fa-book-open-reader' : ['Alphabet Knowledge', 'Decoding & Word Recognition', 'Sentence Reading'].includes(skill) ? 'fa-spell-check' : 'fa-book'
  return <div className="materials-grid">{rows.map((material) => <article className="material-card" key={material.id}><div className="material-card-tools"><div className="material-icon"><i className={`fa-solid ${iconForSkill(material.skill_focus)}`} /></div><Link to={`/material_detail.php?id=${material.id}`} title="View material"><i className="fa-solid fa-eye" /></Link></div><div className="material-title"><Link to={`/material_detail.php?id=${material.id}`}>{material.title}</Link></div><div className="material-meta"><span className="meta-type">{material.skill_focus}</span><span className="meta-grade">{material.grade_level}</span><span className="meta-format">{material.language}</span></div><div className="material-alignment">{material.alignment_part} · {material.reading_level || 'All Profiles'}</div><div className="material-footer"><span>{material.uploader_name || 'System'} · {material.file_size || ''}</span><span>{material.upload_date || '—'}</span></div><div className="material-card-actions"><button className="btn btn-sm btn-primary" type="button" onClick={() => downloadApiFile(`/materials/${material.id}/file`, material.file_path?.split(/[\\/]/).pop() || `material-${material.id}`).catch((failure) => onError(failure.message))}><i className="fa-solid fa-download" /> Download</button>{canDelete && <button className="btn btn-sm btn-danger" type="button" onClick={() => onDelete(material)} title="Delete learning material"><i className="fa-solid fa-trash" /></button>}</div></article>)}{!rows.length && <div className="card material-empty-state">No materials match those filters.</div>}</div>
}

function ScheduleCalendar({ rows, weekOffset, onWeekOffsetChange }) {
  const [today] = useState(() => new Date())
  const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - ((today.getDay() + 6) % 7) + weekOffset * 7)
  const week = Array.from({ length: 5 }, (_, index) => { const date = new Date(monday); date.setDate(monday.getDate() + index); return date })
  const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  const dateRange = week[0].toLocaleDateString('en', { month: 'long', day: 'numeric' }) === week[4].toLocaleDateString('en', { month: 'long' })
    ? `${week[0].toLocaleDateString('en', { month: 'long', day: 'numeric' })}–${week[4].toLocaleDateString('en', { day: 'numeric', year: 'numeric' })}`
    : `${week[0].toLocaleDateString('en', { month: 'short', day: 'numeric' })}–${week[4].toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' })}`
  const upcoming = rows.filter((row) => row.event_date >= dateKey(today)).sort((a, b) => `${a.event_date} ${a.start_time}`.localeCompare(`${b.event_date} ${b.start_time}`)).slice(0, 5)
  return <div className="schedule-calendar"><div className="schedule-week-controls"><div><button className="btn-icon" type="button" aria-label="Previous week" onClick={() => onWeekOffsetChange((offset) => offset - 1)}><i className="fa-solid fa-chevron-left" /></button><strong>{dateRange}</strong><button className="btn-icon" type="button" aria-label="Next week" onClick={() => onWeekOffsetChange((offset) => offset + 1)}><i className="fa-solid fa-chevron-right" /></button></div></div><div className="calendar-grid">{week.map((date) => { const key = dateKey(date); const events = rows.filter((row) => row.event_date === key); const day = date.toLocaleDateString('en', { weekday: 'short' }); return <div className="calendar-day" key={key}><div className="calendar-day-header">{day}</div><div className="calendar-day-number">{date.getDate()}</div>{events.map((event) => <div className={`calendar-event ${(event.event_type || 'remedial').toLowerCase()}`} key={event.id}><div className="calendar-event-title">{event.title}</div><div>{event.start_time} – {event.end_time}</div><div>{event.teacher_name || 'TBA'}</div><span className="badge-status badge-sm">{event.event_type}</span></div>)}{!events.length && <div className="calendar-empty">No events</div>}</div>})}</div><div className="card"><div className="card-header"><div className="card-title">Upcoming This Week</div></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Date</th><th>Event</th><th>Time</th><th>Teacher</th><th>Type</th></tr></thead><tbody>{upcoming.map((event) => <tr key={event.id}><td><strong>{event.event_date}</strong></td><td>{event.title}</td><td>{event.start_time} – {event.end_time}</td><td>{event.teacher_name || 'TBA'}</td><td><span className="badge-status badge-blue">{event.event_type}</span></td></tr>)}{!upcoming.length && <tr><td colSpan="5">No upcoming events.</td></tr>}</tbody></table></div></div></div></div>
}

function StudentPage({ user }) {
  const [records, setRecords] = useState([])
  const [teachers, setTeachers] = useState([])
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [section, setSection] = useState('All Sections')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [loading, setLoading] = useState(true)
  const [sections, setSections] = useState([])
  const [modal, setModal] = useState(false)
  const [editing, setEditing] = useState(null)
  const [error, setError] = useState('')
  const isAdmin = user.role === 'principal' || user.role === 'coordinator'
  const canEdit = user.role === 'coordinator'
  async function load(currentPage = page, query = debouncedSearch, currentSection = section, includeOptions = false) {
    const params = new URLSearchParams({ page: String(currentPage), per_page: '25' })
    if (query) params.set('search', query)
    if (currentSection !== 'All Sections') params.set('section', currentSection)
    if (includeOptions) params.set('include_options', '1')
    const result = await api(`/students?${params}`)
    setRecords(result.data || []); setTeachers(result.teachers || []); setPagination(result.pagination || null)
    if (includeOptions) setSections(result.sections || [])
  }
  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedSearch(search.trim()), 250)
    return () => window.clearTimeout(timeout)
  }, [search])
  useEffect(() => {
    let current = true
    const params = new URLSearchParams({ page: String(page), per_page: '25', include_options: '1' })
    if (debouncedSearch) params.set('search', debouncedSearch)
    if (section !== 'All Sections') params.set('section', section)
    api(`/students?${params}`).then((result) => {
      if (current) {
        setRecords(result.data || []); setTeachers(result.teachers || []); setPagination(result.pagination || null); setSections(result.sections || [])
        setLoading(false)
      }
    }).catch((failure) => { if (current) { setError(failure.message); setLoading(false) } })
    return () => { current = false }
  }, [page, debouncedSearch, section])
  function beginEdit(student) { setEditing(student); setModal(true); setError('') }
  function beginCreate() { setEditing(null); setModal(true); setError('') }
  async function save(event) {
    event.preventDefault(); setError('')
    const formData = new FormData(event.currentTarget)
    const values = Object.fromEntries(formData)
    values.teacher_id = values.teacher_id ? Number(values.teacher_id) : null
    try {
      if (editing) await api(`/students/${editing.id}`, { method: 'PUT', body: values })
      else { if (!values.teacher_id) formData.delete('teacher_id'); await api('/students', { method: 'POST', body: formData }) }
      setModal(false); await load()
    }
    catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) }
  }
  async function remove(student) {
    if (!window.confirm('Are you sure you want to delete this item?')) return
    try { await api(`/students/${student.id}`, { method: 'DELETE' }); await load() } catch (failure) { setError(failure.message) }
  }
  const badge = (profile) => ['Grade Ready', 'Independent'].includes(profile) ? 'badge-green' : ['Light Refresher', 'Instructional'].includes(profile) ? 'badge-blue' : ['Moderate Refresher'].includes(profile) ? 'badge-yellow' : ['Full Refresher', 'Frustration'].includes(profile) ? 'badge-red' : 'badge-blue'
  return <><div className="page-title">{isAdmin ? 'Student List' : 'My Students'}</div><div className="page-subtitle">{isAdmin ? 'Manage enrolled students' : 'View your class reading performance'}</div>
    {error && <div className="alert alert-danger">{error}</div>}
    <div className="search-filter-bar"><input className="search-input" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search by name or LRN..." /><select className="select-input" value={section} onChange={(event) => { setSection(event.target.value); setPage(1) }}><option>All Sections</option>{sections.map((name) => <option key={name}>{name}</option>)}</select>{isAdmin && <button className="btn btn-primary" onClick={beginCreate}><i className="fa-solid fa-plus" /> Add Student</button>}</div>
    <div className="card"><div className="card-header"><div className="card-title">All Students</div><span className="record-count">{loading && !pagination ? 'Loading…' : `${pagination?.total ?? records.length} enrolled`}</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Student</th><th>LRN</th><th>Section</th><th>Teacher</th><th>Reading Profile</th><th>Score</th><th>Actions</th></tr></thead><tbody>
      {records.map((student) => <tr key={student.id}><td><div style={{ display: 'flex', alignItems: 'center', gap: 10 }}><StudentAvatar student={student} /><Link to={`/pupil_profile.php?id=${student.id}`} style={{ fontWeight: 600 }}>{student.full_name}</Link></div></td><td>{student.lrn}</td><td>{student.section}</td><td>{student.teacher_name || 'Unassigned'}</td><td><span className={`badge-status ${badge(student.reading_profile)}`}>{student.reading_profile || 'Not Assessed'}</span></td><td><div className="progress-display"><div className="progress-bar"><div className={`progress-fill ${['Independent', 'Grade Ready'].includes(student.reading_profile) ? 'progress-green' : ['Instructional', 'Light Refresher'].includes(student.reading_profile) ? 'progress-yellow' : 'progress-red'}`} style={{ width: `${Math.min(100, Number(student.score || 0))}%` }} /></div><span style={{ fontSize: 12, fontWeight: 600 }}>{student.score}</span></div></td><td><div className="table-actions"><Link to={`/pupil_profile.php?id=${student.id}`} className="btn-table-action" title="View pupil profile"><i className="fa-solid fa-eye" /></Link>{canEdit && <button className="btn-table-action btn-edit" onClick={() => beginEdit(student)} title="Edit student profile and assign teacher"><i className="fa-solid fa-pen" /></button>}{isAdmin && <button className="btn-table-action btn-delete" onClick={() => remove(student)} title="Delete student"><i className="fa-solid fa-trash" /></button>}</div></td></tr>)}
      {!records.length && <tr><td colSpan="7">{loading ? 'Loading students…' : 'No students found.'}</td></tr>}</tbody></table></div><PaginationControls pagination={pagination} onPageChange={setPage} /></div></div>
    {modal && <><div className="modal-overlay active" onClick={() => setModal(false)} /><div className="modal active"><div className="modal-header"><div className="modal-title">{editing ? 'Edit Student Profile' : 'Enroll Student'}</div><button className="modal-close" type="button" onClick={() => setModal(false)}>&times;</button></div><form onSubmit={save} encType="multipart/form-data"><div className="modal-body">{error && <div className="alert alert-danger">{error}</div>}<div className="form-row"><div className="form-group"><label className="form-label">Full Name</label><input name="full_name" className="form-input" placeholder="Last, First Middle" defaultValue={editing?.full_name} required /></div><div className="form-group"><label className="form-label">LRN</label><input name="lrn" className="form-input" maxLength="12" placeholder="12-digit LRN" defaultValue={editing?.lrn} required /></div></div><div className="form-row"><div className="form-group"><label className="form-label">Section</label><input name="section" className="form-input" defaultValue={editing?.section} required /></div><div className="form-group"><label className="form-label">Grade Level</label><select name="grade_level" className="form-select" defaultValue={editing?.grade_level || ''} required><option value="">Select grade</option>{Array.from({ length: 6 }, (_, index) => `Grade ${index + 1}`).map((grade) => <option key={grade}>{grade}</option>)}</select></div></div><div className="form-row"><div className="form-group"><label className="form-label">Date Enrolled</label><input type="date" name="date_enrolled" className="form-input" defaultValue={editing?.date_enrolled || todayDate} /></div><div className="form-group"><label className="form-label">Teacher</label><select name="teacher_id" className="form-select" required={Boolean(editing)} defaultValue={editing?.teacher_id || ''}><option value="">Assign by section</option>{teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.full_name} ({teacher.grade_level} {teacher.section})</option>)}</select></div></div><div className="form-row"><div className="form-group"><label className="form-label">Parent / Guardian</label><input name="parent_guardian" className="form-input" defaultValue={editing?.parent_guardian || ''} /></div><div className="form-group"><label className="form-label">Contact Number</label><input name="contact_number" className="form-input" defaultValue={editing?.contact_number || ''} /></div></div>{!editing && <div className="form-group"><label className="form-label">Profile Photo</label><input name="profile_image" type="file" className="form-input" accept="image/*" /></div>}</div><div className="modal-footer"><button type="button" className="btn btn-secondary" onClick={() => setModal(false)}>Cancel</button><button type="submit" className="btn btn-primary">{editing ? 'Save Changes' : 'Enroll Student'}</button></div></form></div></>}
  </>
}

const featureConfig = {
  assessments: { title: 'Reading Performance', subtitle: 'Assessment records and performance tracking', endpoint: '/assessments', rows: (r) => r.data || [], cols: [['full_name', 'Pupil'], ['grade_level', 'Grade'], ['tool', 'Assessment'], ['total_score', 'Score'], ['profile', 'Reading Profile'], ['assessment_date', 'Date']], create: true, fields: [['student_id', 'Pupil', 'student'], ['tool', 'Assessment Used', 'select', ['CRLA-M', 'Phil-IRI']], ['task1_score', 'Task 1 Score', 'number', { min: 0, max: 10 }], ['words_score', 'Words Score', 'number', { min: 0, max: 10 }], ['sentences_score', 'Sentences Score', 'number', { min: 0, max: 10 }], ['profile', 'Reading Profile', 'select', ['Grade Ready', 'Light Refresher', 'Moderate Refresher', 'Full Refresher', 'Independent', 'Instructional', 'Frustration']], ['assessment_date', 'Assessment Date', 'date']] },
  progress: { title: 'Progress Updates', subtitle: 'Track student reading level changes over time', endpoint: '/progress-updates', rows: (r) => r.data || [], cols: [['full_name', 'Pupil'], ['grade_level', 'Grade'], ['previous_profile', 'Previous Profile'], ['current_profile', 'Current Profile'], ['trend', 'Trend'], ['update_date', 'Date'], ['remarks', 'Remarks']], create: true, fields: [['student_id', 'Pupil', 'student'], ['previous_profile', 'Previous Profile', 'text'], ['current_profile', 'Current Profile', 'text'], ['trend', 'Trend', 'select', ['improved', 'no_change', 'declined']], ['update_date', 'Update Date', 'date'], ['remarks', 'Remarks', 'textarea']] },
  sessions: { title: 'Remedial Sessions', subtitle: 'One-on-one remedial reading sessions', endpoint: '/remedial-sessions', rows: (r) => r.data || [], cols: [['full_name', 'Pupil'], ['grade_level', 'Grade'], ['section', 'Section'], ['teacher_name', 'Teacher'], ['focus_area', 'Focus Area'], ['session_type', 'Session Type'], ['session_date', 'Date'], ['start_time', 'Start Time'], ['status', 'Status']], create: true, fields: [['student_id', 'Pupil', 'student'], ['teacher_id', 'Assigned Teacher', 'teacher'], ['session_date', 'Date', 'date'], ['start_time', 'Start Time', 'time'], ['end_time', 'End Time', 'time'], ['focus_area', 'Focus Area', 'text'], ['session_type', 'Session Type', 'select', ['Phonics Drill', 'Fluency Practice', 'Comprehension Guide', 'Guided Reading', 'Vocabulary Building', 'Oral Reading Fluency', 'Word Recognition']], ['notes', 'Remarks', 'textarea']] },
  endorsements: { title: 'Endorsement & Approval', subtitle: 'Review and manage pupil endorsements', endpoint: '/endorsements', rows: (r) => r.data || [], cols: [['full_name', 'Pupil'], ['grade_level', 'Grade'], ['teacher_name', 'Teacher'], ['assessment_tool', 'Assessment'], ['reading_level', 'Reading Level'], ['status', 'Status'], ['created_at', 'Submitted']], create: true, fields: [['student_id', 'Pupil', 'student'], ['assessment_tool', 'Assessment Used', 'text'], ['assessment_date', 'Assessment Date', 'date'], ['assessment_result', 'Assessment Result', 'text'], ['reading_level', 'Reading Level', 'text'], ['reading_difficulty', 'Reading Difficulty', 'textarea'], ['teacher_remarks', 'Teacher Remarks', 'textarea'], ['supporting_document', 'Supporting Document', 'file']] },
  materials: { title: 'Learning Materials', subtitle: 'Organize supplementary reading practice around CRLA skills, grade levels, and learner profiles.', endpoint: '/materials', rows: (r) => r.data || [], cols: [['title', 'Title'], ['material_type', 'Type'], ['grade_level', 'Grade'], ['language', 'Language'], ['skill_focus', 'Skill Focus'], ['uploader_name', 'Uploaded By'], ['upload_date', 'Date']], create: true, fields: [['title', 'Material Title', 'text'], ['description', 'Description', 'textarea'], ['material_type', 'Practice Type', 'select', ['Reading Passage', 'Word Recognition', 'Phonological Awareness', 'Fluency Activity', 'Comprehension Activity', 'Teacher Guide', 'Other Practice']], ['grade_level', 'Grade', 'select', ['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']], ['alignment_part', 'CRLA Part / Alignment', 'select', ['General Reading Practice', 'Part 1 - Foundational Skills', 'Part 2 - Fluency & Comprehension']], ['skill_focus', 'Skill Focus', 'select', ['General Reading Practice', 'Alphabet Knowledge', 'Phonological Awareness', 'Decoding & Word Recognition', 'Sentence Reading', 'Reading Fluency', 'Accuracy & Pacing', 'Prosody', 'Reading Comprehension', 'Teacher Guidance']], ['language', 'Language', 'text'], ['reading_level', 'Recommended CRLA Reading Profile', 'select', ['All Profiles', 'Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Transitioning Reader', 'Reading At Grade Level']], ['file', 'Upload Supplementary Practice File (max 20MB)', 'file']] },
  reports: { title: 'Reports', subtitle: 'Generate and download performance reports', endpoint: '/reports', rows: (r) => r.data || [], cols: [['report_title', 'Report'], ['report_type', 'Type'], ['generator_name', 'Generated By'], ['generated_date', 'Date'], ['status', 'Status']], create: true, fields: [['report_type', 'Report Type', 'select', ['activity', 'progress', 'attendance']], ['start_date', 'Start Date', 'date'], ['end_date', 'End Date', 'date']] },
  categories: { title: 'Grade Categories', subtitle: 'Define reading profile categories and scoring bands per grade level.', endpoint: '/grade-categories', rows: (r) => r.data || [], cols: [['grade_level', 'Grade'], ['assessment_type', 'Assessment'], ['category_name', 'Category'], ['score_range', 'Score Range'], ['recommended_action', 'Recommended Action']], create: true, fields: [['grade_level', 'Grade Level', 'select', ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']], ['category_name', 'Category', 'select', ['Full Refresher', 'Moderate Refresher', 'Light Refresher', 'Grade Ready']], ['score_range', 'Score Range', 'text'], ['recommended_action', 'Recommended Action', 'textarea']] },
  schedule: { title: 'Schedule', subtitle: 'Weekly reading program schedule', endpoint: '/schedule', rows: (r) => r.data || [], cols: [['title', 'Event'], ['event_type', 'Type'], ['grade_level', 'Grade'], ['section', 'Section'], ['event_date', 'Date'], ['start_time', 'Start'], ['end_time', 'End'], ['teacher_name', 'Teacher']], create: true, fields: [['title', 'Event Title', 'text'], ['event_type', 'Event Type', 'select', ['Remedial', 'Group', 'Assessment', 'Review']], ['grade_level', 'Grade Level', 'text'], ['section', 'Section', 'text'], ['event_date', 'Date', 'date'], ['start_time', 'Start Time', 'time'], ['end_time', 'End Time', 'time'], ['description', 'Description', 'textarea']] },
  teachers: { title: 'Teacher Accounts', subtitle: 'Manage teacher accounts and assignments', endpoint: '/teachers', rows: (r) => r.teachers || [], cols: [['full_name', 'Teacher'], ['email', 'Email'], ['grade_level', 'Grade'], ['section', 'Section'], ['student_count', 'Pupils'], ['status', 'Status']], create: true, fields: [['full_name', 'Name', 'text'], ['email', 'Email', 'email'], ['section', 'Grade / Section', 'text'], ['grade_level', 'Grade Level', 'text']] },
}

function FeaturePage({ kind, user }) {
  const config = featureConfig[kind]
  const [result, setResult] = useState(null)
  const [students, setStudents] = useState([])
  const [teachers, setTeachers] = useState([])
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [page, setPage] = useState(1)
  const [reloadKey, setReloadKey] = useState(0)
  const [scheduleWeekOffset, setScheduleWeekOffset] = useState(0)
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('All')
  const [materialFilters, setMaterialFilters] = useState({ grade: 'All Grades', language: '', part: '', profile: 'All Profiles' })
  const [modal, setModal] = useState(false)
  const [editingRow, setEditingRow] = useState(null)
  const [editingPrincipal, setEditingPrincipal] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const isAdmin = user.role === 'principal' || user.role === 'coordinator'
  const serverPaginated = ['assessments', 'progress', 'sessions', 'endorsements', 'materials', 'reports', 'teachers'].includes(kind)
  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedSearch(search.trim()), 250)
    return () => window.clearTimeout(timeout)
  }, [search])
  useEffect(() => { setPage(1) }, [debouncedSearch, statusFilter, materialFilters])
  useEffect(() => {
    const needsStudents = config.fields?.some((field) => field[2] === 'student') && kind !== 'endorsements'
    const needsTeachers = kind === 'sessions' && isAdmin
    let current = true
    Promise.all([
      needsStudents ? api('/students?options=1') : Promise.resolve(null),
      needsTeachers ? api('/teachers') : Promise.resolve(null),
    ]).then(([studentResult, teacherResult]) => {
      if (!current) return
      if (studentResult) setStudents(studentResult.data || [])
      if (teacherResult) setTeachers(teacherResult.teachers || [])
    }).catch((failure) => { if (current) setError(failure.message) })
    return () => { current = false }
  }, [kind, isAdmin])
  useEffect(() => {
    let current = true
    const params = new URLSearchParams()
    if (serverPaginated) {
      params.set('page', String(page))
      params.set('per_page', '25')
      if (debouncedSearch) params.set('search', debouncedSearch)
      if (kind === 'endorsements' && statusFilter !== 'All') params.set('status', statusFilter)
      if (kind === 'materials') {
        if (materialFilters.grade !== 'All Grades') params.set('grade', materialFilters.grade)
        if (materialFilters.language) params.set('language', materialFilters.language)
        if (materialFilters.part) params.set('part', materialFilters.part)
        if (materialFilters.profile !== 'All Profiles') params.set('profile', materialFilters.profile)
      }
    }
    if (kind === 'schedule') {
      const today = new Date()
      const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - ((today.getDay() + 6) % 7) + scheduleWeekOffset * 7)
      const friday = new Date(monday)
      friday.setDate(monday.getDate() + 4)
      const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
      params.set('from', dateKey(monday))
      params.set('to', dateKey(friday))
    }
    const url = params.size ? `${config.endpoint}?${params}` : config.endpoint
    api(url).then((data) => { if (current) { setResult(data); setError(''); if (kind === 'endorsements') setStudents(data.students || []) } }).catch((failure) => { if (current) setError(failure.message) }).finally(() => { if (current) setLoading(false) })
    return () => { current = false }
  }, [kind, config.endpoint, page, debouncedSearch, statusFilter, materialFilters, reloadKey, serverPaginated, scheduleWeekOffset])
  const sourceRows = config.rows(result || {})
  const rows = serverPaginated ? sourceRows : sourceRows.filter((row) => JSON.stringify(row).toLowerCase().includes(search.toLowerCase()) && (kind !== 'endorsements' || statusFilter === 'All' || row.status === statusFilter) && (kind !== 'materials' || ((materialFilters.grade === 'All Grades' || row.grade_level === materialFilters.grade) && (!materialFilters.language || row.language === materialFilters.language) && (!materialFilters.part || row.alignment_part === materialFilters.part) && (materialFilters.profile === 'All Profiles' || row.reading_level === materialFilters.profile))))
  const load = () => setReloadKey((current) => current + 1)
  async function submit(event) {
    event.preventDefault(); setError('')
    const form = new FormData(event.currentTarget)
    if (kind === 'materials' && !(form.get('file') instanceof File && form.get('file').size > 0)) { setError('Select a supplementary practice file to upload.'); return }
    const hasFile = config.fields.some((field) => field[2] === 'file')
    let body = hasFile ? form : Object.fromEntries(form)
    if (!hasFile) for (const key of ['student_id', 'teacher_id', 'task1_score', 'words_score', 'sentences_score', 'min_score', 'max_score']) if (body[key] !== undefined) body[key] = body[key] === '' ? null : Number(body[key])
    try {
      let result
      if (kind === 'teachers' && editingPrincipal) result = await api('/teachers/principal', { method: 'PUT', body: Object.fromEntries(form) })
      else if (kind === 'teachers' && editingRow) result = await api(`/teachers/${editingRow.id}`, { method: 'PUT', body: Object.fromEntries(form) })
      else result = await api(config.endpoint, { method: 'POST', body })
      if (result?.message) setNotice(`${result.message}${result.temporary_password ? ` Temporary password: ${result.temporary_password}` : ''}`)
      setModal(false); setEditingRow(null); await load()
    }
    catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) }
  }
  async function accountAction(row, action) {
    if (action === 'reset-password' && !window.confirm(`Reset ${row.full_name}'s password?`)) return
    if (action === 'toggle' && !window.confirm(`${row.status === 'active' ? 'Deactivate' : 'Activate'} ${row.full_name}'s account?`)) return
    try { const endpoint = row.role === 'principal' ? `/teachers/principal/${action}` : `/teachers/${row.id}/${action}`; const result = await api(endpoint, { method: 'POST' }); setNotice(result.message); await load() }
    catch (failure) { setError(failure.message) }
  }
  async function deleteMaterial(row) {
    if (!window.confirm(`Delete “${row.title}”? This also removes its uploaded file.`)) return
    try { await api(`/materials/${row.id}`, { method: 'DELETE' }); await load() }
    catch (failure) { setError(failure.message) }
  }
  async function review(row, status) {
    try { await api(`/endorsements/${row.id}/review`, { method: 'POST', body: { status, review_remarks: '' } }); await load() }
    catch (failure) { setError(failure.message) }
  }
  return <><div className="page-title">{config.title}</div><div className="page-subtitle">{config.subtitle}</div>{error && <div className="alert alert-danger">{error}</div>}{notice && <div className="alert alert-success">{notice}<button type="button" className="alert-dismiss" onClick={() => setNotice('')} aria-label="Dismiss">&times;</button></div>}{loading && !result && <div className="spinner" aria-label="Loading records" />}
    <div className="search-filter-bar"><input className="search-input" placeholder={kind === 'materials' ? 'Search materials...' : kind === 'teachers' ? 'Search teachers...' : 'Search...'} value={search} onChange={(event) => setSearch(event.target.value)} />{kind === 'materials' && <><select className="select-input" value={materialFilters.grade} onChange={(event) => setMaterialFilters((current) => ({ ...current, grade: event.target.value }))}>{['All Grades', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'].map((grade) => <option key={grade}>{grade}</option>)}</select><select className="select-input" value={materialFilters.language} onChange={(event) => setMaterialFilters((current) => ({ ...current, language: event.target.value }))}><option value="">All Languages</option>{(result?.languages || [...new Set(sourceRows.map((row) => row.language).filter(Boolean))].sort()).map((language) => <option key={language}>{language}</option>)}</select><select className="select-input" value={materialFilters.part} onChange={(event) => setMaterialFilters((current) => ({ ...current, part: event.target.value }))}><option value="">All CRLA Parts</option>{['General Reading Practice', 'Part 1 - Foundational Skills', 'Part 2 - Fluency & Comprehension'].map((part) => <option key={part}>{part}</option>)}</select><select className="select-input" value={materialFilters.profile} onChange={(event) => setMaterialFilters((current) => ({ ...current, profile: event.target.value }))}>{['All Profiles', 'Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Transitioning Reader', 'Reading At Grade Level'].map((profile) => <option key={profile}>{profile}</option>)}</select></>}{config.create && (kind !== 'teachers' || isAdmin) && (kind !== 'endorsements' || user.role === 'teacher') && (kind !== 'categories' || isAdmin) && (kind !== 'materials' || isAdmin) && !(kind === 'assessments' && user.role === 'teacher') && <button className="btn btn-primary" onClick={() => { setError(''); setEditingRow(null); setEditingPrincipal(false); setModal(true) }}><i className="fa-solid fa-plus" /> {kind === 'reports' ? 'Generate Report' : kind === 'sessions' ? 'Schedule Session' : kind === 'assessments' ? 'Log Assessment' : kind === 'endorsements' ? 'Submit Endorsement' : kind === 'categories' ? 'Add Grade Category' : kind === 'materials' ? <><i className="fa-solid fa-upload" /> Upload Material</> : kind === 'teachers' ? 'Add Teacher Account' : 'Add New'}</button>}</div>
    {kind === 'endorsements' && result?.counts && <div className="endorsement-status-filters">{Object.entries(result.counts).map(([status, total]) => <button type="button" className={`btn ${statusFilter === status ? 'btn-primary' : 'btn-secondary'}`} key={status} onClick={() => setStatusFilter(status)}>{status}<span>{total}</span></button>)}</div>}
    {kind === 'materials' && <><div className="card materials-guidance"><div className="card-header"><div className="card-title">CRLA-aligned learning focus</div></div><div className="card-body"><p><strong>Part 1 — Foundational skills:</strong> Practice alphabet knowledge, phonological awareness, decoding, word recognition, and sentence reading.</p><p><strong>Part 2 — Fluency and comprehension:</strong> Provide practice for reading accuracy, pacing, prosody, and understanding text.</p><p><strong>Suggested instructional use:</strong> Match supplementary practice to the learner’s reading profile and grade level.</p><p className="material-guidance-note">Use original supplementary practice content. Follow your school’s rules for restricted assessment materials.</p></div></div><MaterialsGrid rows={rows} canDelete={isAdmin} onDelete={deleteMaterial} onError={setError} /><PaginationControls pagination={result?.pagination} onPageChange={setPage} /></>}
    {kind === 'schedule' && <ScheduleCalendar rows={rows} weekOffset={scheduleWeekOffset} onWeekOffsetChange={setScheduleWeekOffset} />}
    {kind === 'teachers' && result?.principal && <div className="card principal-account-card"><div className="card-header"><div className="card-title">Principal Account</div><span className={`badge-status ${result.principal.status === 'active' ? 'badge-green' : 'badge-red'}`}>{result.principal.status}</span></div><div className="card-body principal-account-row"><div><b>{result.principal.full_name}</b><div>{result.principal.email}</div></div><div className="table-actions"><button className="btn-table-action btn-edit" title="Edit principal account" onClick={() => { setEditingRow(result.principal); setEditingPrincipal(true); setError(''); setModal(true) }}><i className="fa-solid fa-pen" /></button><button className="btn-table-action" title="Activate or deactivate principal account" onClick={() => accountAction({ ...result.principal, role: 'principal' }, 'toggle')}><i className="fa-solid fa-power-off" /></button><button className="btn-table-action" title="Reset principal password" onClick={() => accountAction({ ...result.principal, role: 'principal' }, 'reset-password')}><i className="fa-solid fa-key" /></button></div></div></div>}
    {kind === 'categories' ? <div className="category-groups">{[...new Set(rows.map((row) => row.grade_level))].map((grade) => { const group = rows.filter((row) => row.grade_level === grade); const theme = (name) => ({ 'Full Refresher': 'full', 'Moderate Refresher': 'moderate', 'Light Refresher': 'light', 'Grade Ready': 'ready' })[name] || 'light'; return <div className="card" key={grade}><div className="card-header"><div><div className="card-title">{grade}</div><div className="card-subtitle">{group[0]?.assessment_type || ''}</div></div></div><div className="card-body"><div className="category-grid">{group.map((row) => <div className={`category-card ${theme(row.category_name)}`} key={row.id}><div className="category-name">{row.category_name}</div><div className="category-detail"><strong>Score Range:</strong> {row.score_range}</div><div className="category-detail"><strong>Recommended Action:</strong> {row.recommended_action}</div></div>)}</div></div></div>})}{!rows.length && <div className="card"><div className="card-body empty-state">No grade categories found.</div></div>}</div> : <div className="card"><div className="card-header"><div className="card-title">{kind === 'teachers' ? 'Teacher Accounts' : config.title}</div><span className="record-count">{result?.pagination?.total ?? rows.length} records</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr>{config.cols.map(([, title]) => <th key={title}>{title}</th>)}{(kind === 'endorsements' && result?.reviewer || kind === 'reports' || kind === 'teachers') && <th>Actions</th>}</tr></thead><tbody>{rows.map((row, index) => <tr key={row.id || index}>{config.cols.map(([key]) => <td key={key}>{kind === 'teachers' && key === 'full_name' ? <Link to={`/teacher_profile.php?id=${row.id}`}>{row[key]}</Link> : row[key] ?? '—'}</td>)}{kind === 'endorsements' && <td><div className="table-actions"><Link className="btn btn-sm btn-secondary" to={`/endorsement_detail.php?id=${row.id}`}>Details</Link>{result?.reviewer && ['Pending', 'Returned for Revision'].includes(row.status) && <><button className="btn btn-sm btn-primary" onClick={() => review(row, 'Approved')}>Approve</button><button className="btn btn-sm btn-secondary" onClick={() => review(row, 'Returned for Revision')}>Return</button><button className="btn btn-sm btn-danger" onClick={() => review(row, 'Rejected')}>Reject</button></>}</div></td>}{kind === 'reports' && <td><button className="btn btn-sm btn-primary" onClick={() => downloadApiFile(`/reports/${row.id}/download`, row.file_path?.split(/[\\/]/).pop() || `report-${row.id}.html`).catch((failure) => setError(failure.message))}><i className="fa-solid fa-download" /> Download</button></td>}{kind === 'teachers' && <td><div className="table-actions"><button className="btn-table-action btn-edit" title="Edit user" onClick={() => { setEditingRow(row); setEditingPrincipal(false); setError(''); setModal(true) }}><i className="fa-solid fa-pen" /></button><button className="btn-table-action" title={row.status === 'active' ? 'Deactivate account' : 'Activate account'} onClick={() => accountAction(row, 'toggle')}><i className="fa-solid fa-power-off" /></button><button className="btn-table-action" title="Reset account password" onClick={() => accountAction(row, 'reset-password')}><i className="fa-solid fa-key" /></button></div></td>}</tr>)}{!rows.length && <tr><td colSpan={config.cols.length + (kind === 'endorsements' && result?.reviewer || kind === 'reports' || kind === 'teachers' ? 1 : 0)}>No records found.</td></tr>}</tbody></table></div></div></div>}
    {serverPaginated && kind !== 'materials' && <PaginationControls pagination={result?.pagination} onPageChange={setPage} />}
    {modal && <><div className="modal-overlay active" onClick={() => { setModal(false); setEditingRow(null); setEditingPrincipal(false) }} /><div className="modal active"><div className="modal-header"><div className="modal-title">{kind === 'reports' ? 'Generate Report' : kind === 'teachers' ? (editingPrincipal ? 'Edit Principal Account' : editingRow ? 'Edit Teacher' : 'Add Teacher Account') : config.title}</div><button className="modal-close" type="button" onClick={() => { setModal(false); setEditingRow(null); setEditingPrincipal(false) }}>&times;</button></div><form onSubmit={submit} encType="multipart/form-data"><div className="modal-body">{error && <div className="alert alert-danger">{error}</div>}{config.fields.filter(([name]) => !(kind === 'sessions' && name === 'teacher_id' && !isAdmin) && !(kind === 'teachers' && editingPrincipal && !['full_name', 'email'].includes(name))).map(([name, label, type, options]) => <div className="form-group" key={name}><label className="form-label" htmlFor={`${kind}-${name}`}>{label}</label>{type === 'textarea' ? <textarea className="form-textarea" id={`${kind}-${name}`} name={name} rows="3" defaultValue={editingRow?.[name] || ''} required={!['remarks', 'reading_difficulty', 'teacher_remarks', 'description', 'recommended_action'].includes(name)} /> : (type === 'select' || type === 'student' || type === 'teacher') ? <select className="form-select" id={`${kind}-${name}`} name={name} defaultValue={editingRow?.[name] || ''} required><option value="">Select</option>{(type === 'student' ? students.map((student) => [student.id, `${student.full_name} - ${student.grade_level} ${student.section}`]) : type === 'teacher' ? teachers.map((teacher) => [teacher.id, `${teacher.full_name} (${teacher.grade_level || ''} ${teacher.section || ''})`]) : options.map((option) => [option, option])).map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select> : <input className="form-input" id={`${kind}-${name}`} type={type} name={name} defaultValue={editingRow?.[name] || ''} min={typeof options === 'object' ? options.min : undefined} max={typeof options === 'object' ? options.max : undefined} required={!['notes', 'remarks', 'reading_difficulty', 'teacher_remarks', 'description', 'file'].includes(name)} accept={type === 'file' ? '.pdf,.doc,.docx,.mp3,.ppt,.pptx,.xls,.xlsx' : undefined} />}</div>)}</div><div className="modal-footer"><button type="button" className="btn btn-secondary" onClick={() => { setModal(false); setEditingRow(null); setEditingPrincipal(false) }}>Cancel</button><button type="submit" className="btn btn-primary">{editingRow ? 'Save Changes' : 'Save'}</button></div></form></div></>}
  </>
}

function AttendancePage() {
  const [data, setData] = useState(null); const [error, setError] = useState('')
  const [sessionPage, setSessionPage] = useState(1); const [historyPage, setHistoryPage] = useState(1)
  async function load(currentSessionPage = sessionPage, currentHistoryPage = historyPage) {
    const params = new URLSearchParams({ sessions_page: String(currentSessionPage), history_page: String(currentHistoryPage), per_page: '25' })
    setData(await api(`/attendance?${params}`))
  }
  useEffect(() => { let current = true; load().catch((failure) => { if (current) setError(failure.message) }); return () => { current = false } }, [sessionPage, historyPage])
  async function save(event, row) { event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget)); form.session_id = row.id; try { await api('/attendance', { method: 'POST', body: form }); await load() } catch (failure) { setError(failure.message) } }
  return <><div className="page-title">Remedial Attendance</div><div className="page-subtitle">Record and review attendance from actual remedial sessions.</div>{error && <div className="alert alert-danger">{error}</div>}{!data && <div className="spinner" aria-label="Loading attendance" />}<div className="stats-grid"><div className="stat-card"><div className="stat-info"><h3>{data?.history_counts?.Present ?? 0}</h3><p>Sessions Attended</p></div></div><div className="stat-card"><div className="stat-info"><h3>{data?.history_counts?.Absent ?? 0}</h3><p>Sessions Missed</p></div></div></div><div className="card"><div className="card-header"><div className="card-title">Record Attendance</div><span className="record-count">Today's Attendance</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Date</th><th>Pupil</th><th>Teacher</th><th>Time</th><th>Status</th><th>Record</th></tr></thead><tbody>{(data?.sessions || []).map((row) => <tr key={row.id}><td>{todayDate}</td><td><Link to={`/pupil_profile.php?id=${row.student_id}`}>{row.full_name}</Link></td><td>{row.teacher_name || '—'}</td><td>{row.start_time} – {row.end_time}</td><td><span className={`badge-status ${row.attendance_status === 'Present' ? 'badge-green' : row.attendance_status === 'Absent' ? 'badge-red' : 'badge-blue'}`}>{row.attendance_status || 'Not recorded'}</span></td><td><form onSubmit={(event) => save(event, row)} className="attendance-form"><input type="hidden" name="attendance_date" value={todayDate} /><select className="select-input" name="status" defaultValue={row.attendance_status || 'Present'}><option>Present</option><option>Absent</option></select><input className="form-input" name="remarks" placeholder="Remarks" defaultValue={row.remarks || ''} /><button className="btn btn-sm btn-primary">Save</button></form></td></tr>)}{!data?.sessions?.length && <tr><td colSpan="6">No remedial sessions found.</td></tr>}</tbody></table></div><PaginationControls pagination={data?.session_pagination} onPageChange={setSessionPage} /></div></div><div className="card"><div className="card-header"><div className="card-title">Attendance History</div><span className="record-count">{data?.history_pagination?.total ?? data?.history?.length ?? 0} records</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Date</th><th>Pupil</th><th>Grade / Section</th><th>Status</th><th>Remarks</th><th>Recorded By</th></tr></thead><tbody>{(data?.history || []).map((row) => <tr key={row.id}><td>{row.attendance_date}</td><td><Link to={`/pupil_profile.php?id=${row.student_id}`}>{row.full_name}</Link></td><td>{row.grade_level} - {row.section}</td><td><span className={`badge-status ${row.status === 'Present' ? 'badge-green' : 'badge-red'}`}>{row.status}</span></td><td>{row.remarks || '—'}</td><td>{row.recorded_by_name || 'System'}</td></tr>)}{!data?.history?.length && <tr><td colSpan="6">No attendance history.</td></tr>}</tbody></table></div><PaginationControls pagination={data?.history_pagination} onPageChange={setHistoryPage} /></div></div></>
}

function EndorsementDetail() {
  const id = new URLSearchParams(location.search).get('id')
  const [data, setData] = useState(null)
  const [history, setHistory] = useState([])
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  async function load() {
    const [record, changes] = await Promise.all([api(`/endorsements/${id}`), api(`/endorsements/${id}/history`)])
    setData(record); setHistory(changes.data || [])
  }
  useEffect(() => { if (id) load().catch((failure) => setError(failure.message)); else setError('Endorsement not found.') }, [id])
  async function review(event) {
    event.preventDefault(); const body = Object.fromEntries(new FormData(event.currentTarget))
    try { const result = await api(`/endorsements/${id}/review`, { method: 'POST', body }); setMessage(result.message); setError(''); await load() }
    catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) }
  }
  if (error && !data) return <div className="alert alert-danger">{error}</div>
  if (!data) return <div className="spinner" aria-label="Loading" />
  const e = data.data
  const badgeClass = e.status === 'Approved' ? 'badge-green' : e.status === 'Rejected' ? 'badge-red' : e.status === 'Returned for Revision' ? 'badge-yellow' : 'badge-blue'
  return <><div className="detail-heading"><div><div className="page-title">Endorsement Details</div><div className="page-subtitle">Review the complete pupil endorsement record.</div></div><Link to="/endorsements.php" className="btn btn-secondary"><i className="fa-solid fa-arrow-left" /> Back to Endorsements</Link></div>{error && <div className="alert alert-danger">{error}</div>}{message && <div className="alert alert-success">{message}</div>}
    <div className="card endorsement-status-card"><div className="card-header"><div className="card-title">Endorsement Status</div><span className={`badge-status ${badgeClass}`}>{e.status}</span></div><div className="card-body"><div className="grid-2"><div><p><strong>Pupil:</strong> <Link to={`/pupil_profile.php?id=${e.student_id}`}>{e.full_name}</Link></p><p><strong>LRN:</strong> {e.lrn}</p><p><strong>Grade / Section:</strong> {e.grade_level} - {e.section}</p></div><div><p><strong>Teacher:</strong> {e.teacher_name}</p><p><strong>Submitted:</strong> {e.created_at}</p><p><strong>Reviewed By:</strong> {e.reviewer_name || '—'}</p><p><strong>Date Approved:</strong> {e.reviewed_at || '—'}</p></div></div></div></div>
    <div className="grid-2"><div className="card"><div className="card-header"><div className="card-title">Pupil Information</div></div><div className="card-body"><p><strong>Full Name:</strong> {e.full_name}</p><p><strong>Grade Level:</strong> {e.grade_level}</p><p><strong>Section:</strong> {e.section}</p></div></div><div className="card"><div className="card-header"><div className="card-title">Teacher Information</div></div><div className="card-body"><p><strong>Teacher:</strong> {e.teacher_name}</p><p><strong>Assessment Used:</strong> {e.assessment_tool}</p><p><strong>Assessment Date:</strong> {e.assessment_date || '—'}</p></div></div></div>
    <div className="card"><div className="card-header"><div className="card-title">Assessment Result</div></div><div className="card-body"><p><strong>Assessment Result:</strong> {e.assessment_result}</p><p><strong>Reading Level:</strong> {e.reading_level}</p><p><strong>Identified Reading Difficulty:</strong><br />{e.reading_difficulty || 'Not provided'}</p><p><strong>Teacher's Remarks:</strong><br />{e.teacher_remarks || 'Not provided'}</p><p><strong>Supporting Document:</strong> {e.supporting_document ? <button type="button" className="link-button" onClick={() => openApiFile(`/endorsements/${id}/document`).catch((failure) => setError(failure.message))}><i className="fa-solid fa-file-arrow-down" /> View / Download</button> : 'Not provided'}</p></div></div>
    {data.reviewer && ['Pending', 'Returned for Revision'].includes(e.status) && <div className="card"><div className="card-header"><div className="card-title">Review Endorsement</div></div><form onSubmit={review}><div className="card-body"><div className="form-group"><label className="form-label">Decision</label><select name="status" className="form-select" required defaultValue="Approved"><option>Approved</option><option>Returned for Revision</option><option>Rejected</option></select></div><div className="form-group"><label className="form-label">Review Remarks</label><textarea name="review_remarks" className="form-textarea" rows="3" /></div></div><div className="card-footer"><button type="submit" className="btn btn-primary">Save Decision</button></div></form></div>}
    <div className="card"><div className="card-header"><div className="card-title">Endorsement History</div></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Date</th><th>Status</th><th>Changed By</th><th>Remarks</th></tr></thead><tbody>{history.map((entry) => <tr key={entry.id}><td>{entry.created_at}</td><td>{entry.status}</td><td>{entry.changed_by_name}</td><td>{entry.remarks || '—'}</td></tr>)}{!history.length && <tr><td colSpan="4">No history recorded.</td></tr>}</tbody></table></div></div></div>
  </>
}

function PupilProfile() {
  const id = new URLSearchParams(location.search).get('id')
  const [record, setRecord] = useState(null)
  const [error, setError] = useState('')
  useEffect(() => { if (id) api(`/students/${id}/details`).then(setRecord).catch((failure) => setError(failure.message)) }, [id])
  if (error) return <div className="alert alert-danger">{error}</div>
  if (!record) return <div className="spinner" aria-label="Loading" />
  const student = record.student
  return <><div className="page-title">{student.full_name}</div><div className="page-subtitle">Pupil Profile · {student.grade_level} {student.section}</div>
    <div className="stats-grid">{[['LRN', student.lrn], ['Reading Profile', student.reading_profile || 'Not Assessed'], ['Latest Score', student.score || '—'], ['Teacher', student.teacher_name || 'Unassigned']].map(([label, value]) => <div className="stat-card" key={label}><div className="stat-info"><p>{label}</p><h3>{value}</h3></div></div>)}</div>
    {[['Reading Assessments', record.assessments, ['tool', 'assessment_date', 'total_score', 'profile']], ['Progress Updates', record.progress, ['update_date', 'previous_profile', 'current_profile', 'trend']], ['Endorsements', record.endorsements, ['created_at', 'assessment_tool', 'reading_level', 'status']], ['Remedial Sessions', record.sessions, ['session_date', 'focus_area', 'session_type', 'status']]].map(([sectionTitle, rows, keys]) => <div className="card" key={sectionTitle}><div className="card-header"><div className="card-title">{sectionTitle}</div></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr>{keys.map((key) => <th key={key}>{key.replaceAll('_', ' ')}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={row.id}>{keys.map((key) => <td key={key}>{row[key] ?? '—'}</td>)}</tr>)}{!rows.length && <tr><td colSpan={keys.length}>No records found.</td></tr>}</tbody></table></div></div></div>)}
  </>
}

function ProfilePage() {
  const [data, setData] = useState(null); const [error, setError] = useState(''); const [message, setMessage] = useState('')
  const [profileImageUrl, setProfileImageUrl] = useState('')
  async function load() { setData(await api('/profile')) }
  useEffect(() => { load().catch((failure) => setError(failure.message)) }, [])
  useEffect(() => {
    let activeUrl
    if (data?.user?.profile_image) apiFileObjectUrl(`/users/${data.user.id}/image`).then((url) => { activeUrl = url; setProfileImageUrl(url) }).catch(() => setProfileImageUrl(''))
    else setProfileImageUrl('')
    return () => { if (activeUrl) URL.revokeObjectURL(activeUrl) }
  }, [data?.user?.id, data?.user?.profile_image])
  async function update(event) { event.preventDefault(); setError(''); setMessage(''); try { const result = await api('/profile', { method: 'PUT', body: Object.fromEntries(new FormData(event.currentTarget)) }); setMessage(result.message); await load() } catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) } }
  async function uploadImage(event) { event.preventDefault(); setError(''); setMessage(''); try { const result = await api('/profile/image', { method: 'POST', body: new FormData(event.currentTarget) }); setMessage(result.message); await load(); event.currentTarget.reset() } catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) } }
  async function toggleStatus() { if (!window.confirm(`Are you sure you want to ${data.user.status === 'active' ? 'deactivate' : 'activate'} your account?`)) return; try { const result = await api('/profile/toggle-status', { method: 'POST' }); setMessage(result.message); await load() } catch (failure) { setError(failure.message) } }
  if (!data) return error ? <div className="alert alert-danger">{error}</div> : <div className="spinner" aria-label="Loading" />
  const user = data.user
  const roleLabel = user.role === 'coordinator' ? 'Reading Coordinator' : user.role === 'principal' ? 'Principal' : 'Teacher'
  return <><div className="page-title">My Profile</div><div className="page-subtitle">Manage your account information</div>{error && <div className="alert alert-danger">{error}</div>}{message && <div className="alert alert-success">{message}</div>}<div className="profile-layout"><div><div className="card profile-summary-card"><div className="profile-large-avatar">{profileImageUrl ? <img src={profileImageUrl} alt="Profile" /> : (user.full_name || 'U').split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()}</div><h2>{user.full_name}</h2><p>{roleLabel}</p><form onSubmit={uploadImage} className="profile-upload-form"><label className="form-label" htmlFor="profile-image">Profile Photo</label><input className="form-input" id="profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" required /><small>PNG, JPG, GIF or WebP · Maximum 5MB</small><button type="submit" className="btn btn-secondary"><i className="fa-solid fa-camera" /> Upload Photo</button></form></div><div className="card profile-summary-card"><div className="card-header"><div className="card-title">Activity Summary</div></div><div className="card-body">{Object.entries(data.stats || {}).map(([key, value]) => <div className="profile-stat-row" key={key}><span>{({ total_students: 'Assigned Students', sessions: 'Intervention Sessions', assessments: 'Assessments Logged', total_teachers: 'Total Teachers', total_reports: 'Reports Generated' })[key] || key}</span><b>{value}</b></div>)}</div></div></div><div><div className="card"><div className="card-header"><div className="card-title">Personal Information</div><span className={`badge-status ${user.status === 'active' ? 'badge-green' : 'badge-red'}`}>{user.status}</span></div><div className="card-body"><form onSubmit={update}><div className="form-row"><div className="form-group"><label className="form-label">Full Name</label><input className="form-input" name="full_name" defaultValue={user.full_name} required /></div><div className="form-group"><label className="form-label">Email Address</label><input className="form-input" type="email" name="email" defaultValue={user.email} required /></div></div><div className="form-row"><div className="form-group"><label className="form-label">Grade Level</label><input className="form-input" name="grade_level" defaultValue={user.grade_level || ''} /></div><div className="form-group"><label className="form-label">Section</label><input className="form-input" name="section" defaultValue={user.section || ''} /></div></div><div className="form-group"><label className="form-label">Contact Number</label><input className="form-input" name="contact_number" defaultValue={user.contact_number || ''} /></div><button className="btn btn-primary">Save Changes</button></form></div></div><div className="card"><div className="card-header"><div className="card-title">Change Password</div></div><div className="card-body"><form onSubmit={async (event) => { event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget)); try { const r = await api('/profile/password', { method: 'POST', body: form }); setMessage(r.message); setError(''); event.currentTarget.reset() } catch (failure) { setError(Object.values(failure.errors || {}).flat().join(' ') || failure.message) } }}><div className="form-row">{[['current_password', 'Current Password'], ['new_password', 'New Password'], ['confirm_password', 'Confirm Password']].map(([name, label]) => <div className="form-group" key={name}><label className="form-label">{label}</label><input className="form-input" type="password" name={name} required /></div>)}</div><button className="btn btn-primary">Update Password</button></form></div></div>{['principal', 'coordinator'].includes(user.role) && <div className="card"><div className="card-header"><div className="card-title">Account Status</div></div><div className="card-body"><p>Deactivate your account to prevent sign in until it is reactivated.</p><button type="button" className={`btn ${user.status === 'active' ? 'btn-danger' : 'btn-success'}`} onClick={toggleStatus}><i className="fa-solid fa-power-off" /> {user.status === 'active' ? 'Deactivate' : 'Activate'} Account</button></div></div>}</div></div></>
}

function SettingsPage({ user }) {
  const keys = ['school_name', 'school_id', 'school_address', 'school_contact', 'school_email', 'school_logo', 'school_year', 'program_name', 'grade_levels_covered', 'assessment_information']
  const labels = ['School Name', 'School ID', 'School Address', 'School Contact', 'School Email', 'School Logo', 'School Year', 'Program Name', 'Grade Levels Covered', 'Assessment Information']
  const isAdmin = ['principal', 'coordinator'].includes(user.role)
  const [settings, setSettings] = useState(null); const [error, setError] = useState(''); const [message, setMessage] = useState('')
  async function load() { setSettings((await api('/settings')).settings || {}) }
  useEffect(() => { load().catch((failure) => setError(failure.message)) }, [])
  async function save(event) { event.preventDefault(); const form = Object.fromEntries(new FormData(event.currentTarget)); try { const result = await api('/settings', { method: 'PUT', body: form }); setMessage(result.message) } catch (failure) { setError(failure.message) } }
  if (!settings) return error ? <div className="alert alert-danger">{error}</div> : <div className="spinner" aria-label="Loading" />
  return <><div className="page-title">Settings</div><div className="page-subtitle">School and reading program information</div><div className="settings-actions"><Link to="/profile.php" className="btn btn-primary"><i className="fa-solid fa-user-pen" /> Edit Profile</Link><Link to="/profile.php#change-password" className="btn btn-secondary"><i className="fa-solid fa-key" /> Change Password</Link></div>{error && <div className="alert alert-danger">{error}</div>}{message && <div className="alert alert-success">{message}</div>}<form onSubmit={save}><div className="card settings-section"><div className="card-header"><div><div className="card-title"><i className="fa-solid fa-school" /> School Information</div><div className="card-subtitle">Details shown across your reading monitoring records.</div></div>{!isAdmin && <span className="record-count">Administrator managed</span>}</div><div className="card-body"><div className="form-row">{keys.slice(0, 2).map((key, i) => <div className="form-group" key={key}><label className="form-label" htmlFor={key}>{labels[i]}</label><input className="form-input" id={key} name={key} defaultValue={settings[key] || ''} disabled={!isAdmin} /></div>)}</div><div className="form-group"><label className="form-label" htmlFor="school_address">School Address</label><textarea className="form-textarea" id="school_address" name="school_address" rows="2" defaultValue={settings.school_address || ''} disabled={!isAdmin} /></div><div className="form-row">{keys.slice(3, 6).map((key) => <div className="form-group" key={key}><label className="form-label" htmlFor={key}>{labels[keys.indexOf(key)]}</label><input className="form-input" id={key} name={key} defaultValue={settings[key] || ''} disabled={!isAdmin} /></div>)}</div></div></div><div className="card settings-section"><div className="card-header"><div><div className="card-title"><i className="fa-solid fa-book-open" /> Reading Program Settings</div><div className="card-subtitle">Define the active school year and assessment coverage.</div></div>{!isAdmin && <span className="record-count">Administrator managed</span>}</div><div className="card-body"><div className="form-row">{keys.slice(6, 8).map((key) => <div className="form-group" key={key}><label className="form-label" htmlFor={key}>{labels[keys.indexOf(key)]}</label><input className="form-input" id={key} name={key} defaultValue={settings[key] || ''} disabled={!isAdmin} /></div>)}</div><div className="form-group"><label className="form-label" htmlFor="grade_levels_covered">Grade Levels Covered</label><input className="form-input" id="grade_levels_covered" name="grade_levels_covered" defaultValue={settings.grade_levels_covered || ''} disabled={!isAdmin} /></div><div className="form-group"><label className="form-label" htmlFor="assessment_information">Assessment Information</label><textarea className="form-textarea" id="assessment_information" name="assessment_information" rows="3" defaultValue={settings.assessment_information || ''} disabled={!isAdmin} /></div></div>{isAdmin && <div className="card-footer"><button className="btn btn-primary"><i className="fa-solid fa-floppy-disk" /> Save Settings</button></div>}</div></form></>
}

function MaterialDetail() {
  const id = new URLSearchParams(location.search).get('id')
  const [material, setMaterial] = useState(null); const [error, setError] = useState('')
  useEffect(() => { api(`/materials/${id}`).then(({ data }) => { if (!data) throw new Error('Learning material not found.'); setMaterial(data) }).catch((failure) => setError(failure.message)) }, [id])
  if (error) return <div className="alert alert-danger">{error}</div>
  if (!material) return <div className="spinner" aria-label="Loading" />
  return <><div className="page-title">{material.title}</div><div className="page-subtitle">Learning Material Details</div><div className="card"><div className="card-header"><div className="card-title">Material Information</div></div><div className="card-body">{[['Type', material.material_type], ['Grade Level', material.grade_level], ['Reading Level', material.reading_level], ['Language', material.language], ['Alignment', material.alignment_part], ['Skill Focus', material.skill_focus], ['Uploaded By', material.uploader_name], ['Description', material.description]].map(([label, value]) => <p key={label}><strong>{label}:</strong> {value || '—'}</p>)}<button className="btn btn-primary" onClick={() => openApiFile(`/materials/${material.id}/file` ).catch((failure) => setError(failure.message))}>Download Material</button></div></div></>
}

function TeacherProfile() {
  const id = new URLSearchParams(location.search).get('id')
  const [data, setData] = useState(null); const [error, setError] = useState('')
  useEffect(() => { if (id) api(`/teachers/${id}/profile`).then(setData).catch((failure) => setError(failure.message)) }, [id])
  if (error) return <div className="alert alert-danger">{error}</div>
  if (!data) return <div className="spinner" aria-label="Loading" />
  const t = data.teacher
  return <><div className="page-title">Teacher Profile</div><div className="page-subtitle">Employee and assigned-pupil information</div><div className="grid-2"><div className="card"><div className="card-header"><div className="card-title">Teacher Information</div></div><div className="card-body"><p><strong>Name:</strong> {t.full_name}</p><p><strong>Email:</strong> {t.email}</p><p><strong>Grade / Section:</strong> {t.grade_level || '—'} / {t.section || '—'}</p><p><strong>Status:</strong> {t.status}</p><p><strong>Joined:</strong> {t.joined_date || '—'}</p></div></div><div className="card"><div className="card-header"><div className="card-title">Assigned Pupils</div><span className="record-count">{data.students.length} pupils</span></div><div className="card-body" style={{ padding: 0 }}><div className="table-responsive"><table className="data-table"><thead><tr><th>Pupil</th><th>LRN</th><th>Grade / Section</th><th>Reading Level</th></tr></thead><tbody>{data.students.map((student) => <tr key={student.id}><td><Link to={`/pupil_profile.php?id=${student.id}`}>{student.full_name}</Link></td><td>{student.lrn}</td><td>{student.grade_level} - {student.section}</td><td>{student.reading_profile || 'Not assessed'}</td></tr>)}</tbody></table></div></div></div></div></>
}

function LogoutRoute() { const navigate = useNavigate(); useEffect(() => { clearToken(); navigate('/index.php', { replace: true }) }, [navigate]); return null }

function AppRoutes() {
  return <Routes><Route path="/" element={<Login />} /><Route path="/index.php" element={<Login />} /><Route path="/Registration.php" element={<Registration />} />
    <Route path="/dashboard.php" element={<Protected>{(user) => <Shell user={user}><Dashboard /></Shell>}</Protected>} />
    <Route path="/teacher_dashboard.php" element={<Protected>{(user) => <Shell user={user}><Dashboard /></Shell>}</Protected>} />
    <Route path="/students.php" element={<Protected>{(user) => <Shell user={user}><StudentPage user={user} /></Shell>}</Protected>} />
    <Route path="/my_students.php" element={<Protected>{(user) => <Shell user={user}><StudentPage user={user} /></Shell>}</Protected>} />
    <Route path="/student.php" element={<Protected>{(user) => <Shell user={user}><StudentPage user={user} /></Shell>}</Protected>} />
    <Route path="/pupil_profile.php" element={<Protected>{(user) => <Shell user={user}><PupilProfile /></Shell>}</Protected>} />
    <Route path="/profile.php" element={<Protected>{(user) => <Shell user={user}><ProfilePage /></Shell>}</Protected>} />
    {[
      ['/endorsements.php', 'endorsements'],
      ['/reading_performance.php', 'assessments'], ['/teacher_reading_performance.php', 'assessments'],
      ['/progress_update.php', 'progress'], ['/progress_updates.php', 'progress'],
      ['/remedial_sessions.php', 'sessions'], ['/interventions.php', 'sessions'],
      ['/remedial_attendance.php', 'attendance'], ['/learning_materials.php', 'materials'], ['/teacher_learning_materials.php', 'materials'],
      ['/reports.php', 'reports'], ['/grade_categories.php', 'categories'], ['/schedule.php', 'schedule'], ['/teacher_accounts.php', 'teachers'],
    ].map(([path, kind]) => <Route key={path} path={path} element={<Protected>{(user) => <Shell user={user}>{kind === 'attendance' ? <AttendancePage /> : <FeaturePage kind={kind} user={user} />}</Shell>}</Protected>} />)}
    <Route path="/endorsement_detail.php" element={<Protected>{(user) => <Shell user={user}><EndorsementDetail /></Shell>}</Protected>} />
    <Route path="/settings.php" element={<Protected>{(user) => <Shell user={user}><SettingsPage user={user} /></Shell>}</Protected>} />
    <Route path="/material_detail.php" element={<Protected>{(user) => <Shell user={user}><MaterialDetail /></Shell>}</Protected>} />
    <Route path="/teacher_profile.php" element={<Protected>{(user) => <Shell user={user}><TeacherProfile /></Shell>}</Protected>} />
    <Route path="/logout.php" element={<LogoutRoute />} />
    <Route path="*" element={<Navigate to="/index.php" replace />} />
  </Routes>
}

export default function App() { return <BrowserRouter><AppRoutes /></BrowserRouter> }
