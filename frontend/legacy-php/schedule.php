<?php
require_once __DIR__ . '/../../backend/legacy-php/config.php';
requireLogin();

if (!hasRole('principal') && !hasRole('coordinator')) {
    header("Location: dashboard.php");
    exit();
}

$events = $pdo->query("
    SELECT se.*, u.full_name as teacher_name
    FROM schedule_events se
    LEFT JOIN users u ON se.teacher_id = u.id
    WHERE se.event_date BETWEEN '2025-04-14' AND '2025-04-18'
    ORDER BY se.event_date, se.start_time
")->fetchAll();

$upcoming = $pdo->query("
    SELECT se.*, u.full_name as teacher_name
    FROM schedule_events se
    LEFT JOIN users u ON se.teacher_id = u.id
    WHERE se.event_date >= CURDATE()
    ORDER BY se.event_date, se.start_time
    LIMIT 5
")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="page-title">Schedule</div>
<div class="page-subtitle">Weekly reading program schedule</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn-icon" style="border:1px solid var(--border-color);border-radius:8px;"><i class="fa-solid fa-chevron-left"></i></button>
        <span style="font-weight:600;">April 14-18, 2025</span>
        <button class="btn-icon" style="border:1px solid var(--border-color);border-radius:8px;"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <button class="btn btn-primary" onclick="openModal('addEventModal')"><i class="fa-solid fa-plus"></i> Add Event</button>
</div>

<div class="calendar-grid" style="margin-bottom:24px;">
    <?php 
    $days = ['Mon'=>14,'Tue'=>15,'Wed'=>16,'Thu'=>17,'Fri'=>18];
    foreach ($days as $day=>$date): 
        $dayEvents = array_filter($events, fn($e)=>date('d',strtotime($e['event_date']))==str_pad($date,2,'0',STR_PAD_LEFT));
    ?>
    <div class="calendar-day">
        <div class="calendar-day-header"><?php echo $day; ?></div>
        <div class="calendar-day-number"><?php echo $date; ?></div>
        <?php foreach ($dayEvents as $evt): ?>
        <div class="calendar-event <?php echo strtolower($evt['event_type']); ?>">
            <div style="font-weight:600;"><?php echo htmlspecialchars($evt['title']); ?></div>
            <div><?php echo date('g:i A',strtotime($evt['start_time'])).' - '.date('g:i A',strtotime($evt['end_time'])); ?></div>
            <div style="opacity:0.8;"><?php echo htmlspecialchars($evt['teacher_name'] ?? ''); ?></div>
            <span class="badge-status badge-sm" style="margin-top:4px;font-size:9px;padding:2px 6px;"><?php echo $evt['event_type']; ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($dayEvents)): ?><div style="text-align:center;color:#94A3B8;font-size:12px;margin-top:20px;">No events</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Upcoming This Week</div></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Event</th><th>Time</th><th>Teacher</th><th>Type</th></tr></thead>
            <tbody>
                <?php foreach ($upcoming as $up): ?>
                <tr>
                    <td><strong><?php echo strtoupper(date('M d',strtotime($up['event_date']))); ?></strong></td>
                    <td><?php echo htmlspecialchars($up['title']); ?></td>
                    <td><?php echo date('g:i A',strtotime($up['start_time'])).' - '.date('g:i A',strtotime($up['end_time'])); ?></td>
                    <td><?php echo htmlspecialchars($up['teacher_name'] ?? 'TBA'); ?></td>
                    <td><span class="badge-status badge-blue"><?php echo $up['event_type']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addEventModal" class="modal">
    <div class="modal-header"><div class="modal-title">Add Event</div><button class="modal-close" onclick="closeModal('addEventModal')">&times;</button></div>
    <form method="POST" action="">
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Event Title</label><input type="text" class="form-input" name="title"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Type</label>
                    <select class="form-select" name="event_type"><option>Remedial</option><option>Group</option><option>Assessment</option><option>Review</option></select>
                </div>
                <div class="form-group"><label class="form-label">Date</label><input type="date" class="form-input" name="event_date"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-input" name="start_time"></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-input" name="end_time"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addEventModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Event</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>