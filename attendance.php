<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Record attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
  $employee_id = intval($_POST['employee_id']);
  $date = $_POST['date'];
  $status = $_POST['status'];

  $check = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $employee_id AND date = '$date'");
  if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "INSERT INTO attendance (employee_id, date, status) VALUES ($employee_id, '$date', '$status')");
    $message = "Attendance recorded.";
  } else {
    $message = "Attendance already marked for this employee on this date.";
  }
}

// Filters
$where = [];
if (!empty($_GET['employee_id'])) {
  $employee_id = intval($_GET['employee_id']);
  $where[] = "a.employee_id = $employee_id";
}
if (!empty($_GET['status'])) {
  $status = mysqli_real_escape_string($conn, $_GET['status']);
  $where[] = "a.status = '$status'";
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
  $from = $_GET['from'];
  $to = $_GET['to'];
  $where[] = "a.date BETWEEN '$from' AND '$to'";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT a.*, e.name FROM attendance a JOIN employees e ON a.employee_id = e.id $where_clause ORDER BY a.date DESC";
$attendance = mysqli_query($conn, $sql);

if (!$attendance) {
  die("Query Error: " . mysqli_error($conn));
}

$employees = mysqli_query($conn, "SELECT id, name FROM employees ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Employee Attendance</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-calendar-check"></i> Employee Attendance</h1>
  </div>

  <div class="row">
    <!-- Attendance Form -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header bg-success text-white">Mark Attendance</div>
        <div class="card-body">
          <?php if (isset($message)): ?>
            <div class="alert alert-info"><?= $message ?></div>
          <?php endif; ?>
          <form method="POST">
            <div class="mb-3">
              <label>Employee</label>
              <select name="employee_id" class="form-select" required>
                <option value="">-- Select Employee --</option>
                <?php mysqli_data_seek($employees, 0); while ($emp = mysqli_fetch_assoc($employees)): ?>
                  <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="mb-3">
              <label>Date</label>
              <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label>Status</label>
              <select name="status" class="form-select" required>
                <option value="Present">Present</option>
                <option value="Absent">Absent</option>
              </select>
            </div>
            <button type="submit" name="mark_attendance" class="btn btn-success w-100">Submit</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Attendance Records -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-info text-white">Attendance Records</div>
        <div class="card-body table-responsive">
          <form method="GET" class="row g-2 mb-4">
            <div class="col-md-4">
              <select name="employee_id" class="form-select">
                <option value="">All Employees</option>
                <?php mysqli_data_seek($employees, 0); while ($emp = mysqli_fetch_assoc($employees)): ?>
                  <option value="<?= $emp['id'] ?>" <?= isset($_GET['employee_id']) && $_GET['employee_id'] == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-2">
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Present" <?= isset($_GET['status']) && $_GET['status'] == 'Present' ? 'selected' : '' ?>>Present</option>
                <option value="Absent" <?= isset($_GET['status']) && $_GET['status'] == 'Absent' ? 'selected' : '' ?>>Absent</option>
              </select>
            </div>
            <div class="col-md-2">
              <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? '' ?>" placeholder="From">
            </div>
            <div class="col-md-2">
              <input type="date" name="to" class="form-control" value="<?= $_GET['to'] ?? '' ?>" placeholder="To">
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
            </div>
          </form>

          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; while ($row = mysqli_fetch_assoc($attendance)): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= $row['date'] ?></td>
                  <td>
                    <span class="badge bg-<?= $row['status'] == 'Present' ? 'success' : 'danger' ?>">
                      <?= $row['status'] ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
