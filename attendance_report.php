<?php
/**
 * Attendance report -- read-only summary, aggregated in SQL.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Attendance report';
$subtitle = 'Present / absent / leave, by employee';

$currentYear = (int) date('Y');

$year       = query_int('year') > 0 ? query_int('year') : $currentYear;
$month      = query_int('month');
$employeeF  = query_int('employee_id');

$employees = db_all('SELECT id, name FROM employees ORDER BY name');

$where  = ['YEAR(a.date) = ?'];
$params = [$year];
if ($month > 0) {
    $where[]  = 'MONTH(a.date) = ?';
    $params[] = $month;
}
if ($employeeF > 0) {
    $where[]  = 'a.employee_id = ?';
    $params[] = $employeeF;
}
$attendanceWhere = implode(' AND ', $where);

$rows = db_all(
    "SELECT e.id, e.name,
            COUNT(a.id) AS total_days,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_days,
            SUM(CASE WHEN a.status = 'Absent'  THEN 1 ELSE 0 END) AS absent_days,
            SUM(CASE WHEN a.status = 'Leave'   THEN 1 ELSE 0 END) AS leave_days
       FROM employees e
       LEFT JOIN attendance a ON a.employee_id = e.id AND $attendanceWhere
      GROUP BY e.id, e.name
      ORDER BY e.name",
    $params
);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Attendance report</h1>
    <p class="page-head__sub"><?= e($year) ?><?= $month > 0 ? ' · ' . e(date('F', mktime(0, 0, 0, $month, 1))) : '' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('attendance.php') ?>">
      <i class="bi bi-calendar-check"></i> Attendance
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="year">Year</label>
        <select class="form-select" id="year" name="year">
          <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="month">Month</label>
        <select class="form-select" id="month" name="month">
          <option value="0">All months</option>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= e(date('F', mktime(0, 0, 0, $m, 1))) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="employee_id">Employee</label>
        <select class="form-select" id="employee_id" name="employee_id">
          <option value="0">All employees</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?= (int) $emp['id'] ?>" <?= $employeeF === (int) $emp['id'] ? 'selected' : '' ?>><?= e($emp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Generate</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($rows === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-graph-up"></i></div>
        <div class="empty__title">No employees to report on</div>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Employee</th>
            <th class="text-center">Total days</th>
            <th class="text-center">Present</th>
            <th class="text-center">Absent</th>
            <th class="text-center">Leave</th>
            <th class="text-end">Attendance rate</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php $rate = $r['total_days'] > 0 ? round(($r['present_days'] / $r['total_days']) * 100, 1) : null; ?>
            <tr>
              <td class="table__primary"><?= e($r['name']) ?></td>
              <td class="text-center"><?= (int) $r['total_days'] ?></td>
              <td class="text-center"><?= (int) $r['present_days'] ?></td>
              <td class="text-center"><?= (int) $r['absent_days'] ?></td>
              <td class="text-center"><?= (int) $r['leave_days'] ?></td>
              <td class="text-end fw-semi"><?= $rate !== null ? $rate . '%' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
