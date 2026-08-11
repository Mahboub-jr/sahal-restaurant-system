<?php
include "library/conn.php";

// Default values
$current_year = date('Y');
$current_month = date('m');

// Get filter values
$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

// Get employees for dropdown
$employees = mysqli_query($conn, "SELECT id, name FROM employees ORDER BY name");

// Build query
$where = "WHERE YEAR(date) = $year";
if ($month > 0) {
    $where .= " AND MONTH(date) = $month";
}
if ($employee_id > 0) {
    $where .= " AND employee_id = $employee_id";
}

$sql = "
    SELECT e.id, e.name,
        COUNT(a.id) AS total_days,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_days,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_days
    FROM employees e
    LEFT JOIN attendance a ON e.id = a.employee_id $where
    GROUP BY e.id, e.name
    ORDER BY e.name
";
$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Attendance Report</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
    <div class="app-title">
        <h1><i class="bi bi-graph-up"></i> Attendance Report</h1>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">Report Filters</div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label>Year</label>
                            <select name="year" class="form-select" required>
                                <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>Month</label>
                            <select name="month" class="form-select">
                                <option value="0" <?= $month == 0 ? 'selected' : '' ?>>All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>Employee</label>
                            <select name="employee_id" class="form-select">
                                <option value="0" <?= $employee_id == 0 ? 'selected' : '' ?>>All Employees</option>
                                <?php mysqli_data_seek($employees, 0); while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-grid">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Generate</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-info text-white">Attendance Summary</div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($result)):
                                $percentage = $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100, 2) : 0;
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= $row['total_days'] ?></td>
                                    <td><?= $row['present_days'] ?></td>
                                    <td><?= $row['absent_days'] ?></td>
                                    <td><?= $percentage ?>%</td>
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
