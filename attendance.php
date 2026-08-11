<?php
/**
 * Attendance -- mark and review.
 *
 * This page only READS and RENDERS. Every write goes to
 * actions/attendance.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Attendance';
$subtitle = 'Daily presence, one row per employee per day';

$employees = db_all('SELECT id, name FROM employees ORDER BY name');

$STATUSES = ['Present', 'Absent', 'Leave'];

$employeeF = query_int('employee_id');
$statusF   = one_of(query('status'), $STATUSES, '');
$fromDate  = query('from');
$toDate    = query('to');

$where  = [];
$params = [];
if ($employeeF > 0) {
    $where[]  = 'a.employee_id = ?';
    $params[] = $employeeF;
}
if ($statusF !== '') {
    $where[]  = 'a.status = ?';
    $params[] = $statusF;
}
if ($fromDate !== '') {
    $where[]  = 'a.date >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[]  = 'a.date <= ?';
    $params[] = $toDate;
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$records = db_all(
    "SELECT a.*, e.name FROM attendance a JOIN employees e ON a.employee_id = e.id $whereSql ORDER BY a.date DESC, e.name LIMIT 300",
    $params
);

$statusColour = ['Present' => 'ok', 'Absent' => 'bad', 'Leave' => 'warn'];

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Attendance</h1>
    <p class="page-head__sub"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?> shown (most recent 300)</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('attendance_report.php') ?>">
      <i class="bi bi-graph-up"></i> Report
    </a>
    <a class="btn btn-outline-secondary" href="<?= url('employees.php') ?>">
      <i class="bi bi-person-badge"></i> Staff
    </a>
  </div>
</div>

<?php if ($employees === []): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    No employees yet — <a href="<?= url('employees.php') ?>">add one</a> before marking attendance.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h2>Mark attendance</h2></div>
      <div class="card-body">
        <form method="post" action="<?= url('actions/attendance.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="create">
          <div class="mb-2">
            <label class="form-label" for="f_employee">Employee</label>
            <select class="form-select" id="f_employee" name="employee_id" required>
              <option value="">Choose…</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>"><?= e($emp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label" for="f_date">Date</label>
            <input class="form-control" type="date" id="f_date" name="date" value="<?= e(date('Y-m-d')) ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label" for="f_status">Status</label>
            <select class="form-select" id="f_status" name="status" required>
              <option value="Present">Present</option>
              <option value="Absent">Absent</option>
              <option value="Leave">Leave</option>
            </select>
          </div>
          <button class="btn btn-primary w-100" type="submit" <?= $employees === [] ? 'disabled' : '' ?>>
            <i class="bi bi-check-lg"></i> Record
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="employee_id">Employee</label>
            <select class="form-select" id="employee_id" name="employee_id">
              <option value="">All</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>" <?= $employeeF === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="">Any</option>
              <?php foreach ($STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="from">From</label>
            <input class="form-control" type="date" id="from" name="from" value="<?= e($fromDate) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="to">To</label>
            <input class="form-control" type="date" id="to" name="to" value="<?= e($toDate) ?>">
          </div>
          <div class="col-md-1">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if ($records === []): ?>
        <div class="card-body">
          <div class="empty">
            <div class="empty__icon"><i class="bi bi-calendar-check"></i></div>
            <div class="empty__title">No records match</div>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Status</th>
                <th style="width:60px" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td class="table__primary"><?= e($r['name']) ?></td>
                  <td class="table__secondary"><?= e(date('j M Y', strtotime((string) $r['date']))) ?></td>
                  <td><span class="badge-soft badge-soft--<?= $statusColour[$r['status']] ?? 'neutral' ?>"><?= e($r['status']) ?></span></td>
                  <td class="text-end">
                    <form method="post" action="<?= url('actions/attendance.php') ?>" class="m-0"
                          data-confirm="Remove this attendance record?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="do" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn btn-ghost btn-icon btn-sm" type="submit" title="Delete" style="color:var(--bad)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
