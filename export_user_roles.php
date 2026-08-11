<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";
<?php
if (file_exists('library/tcpdf/tcpdf.php')) {
    echo "TCPDF found!";
} else {
    echo "TCPDF NOT FOUND!";
}
exit;
 
//require_once 'library/tcpdf/TCPDF-main/tcpdf.php'; // For PDF export

$role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$format = $_GET['format'] ?? 'excel';

$query = "SELECT name, email, role FROM users";
if ($role) $query .= " WHERE role = '$role'";
$query .= " ORDER BY id DESC";

$result = mysqli_query($conn, $query);

if ($format == 'excel') {
  header("Content-Type: application/vnd.ms-excel");
  header("Content-Disposition: attachment; filename=user_roles.xls");

  echo "Name\tEmail\tRole\n";
  while ($row = mysqli_fetch_assoc($result)) {
    echo "{$row['name']}\t{$row['email']}\t{$row['role']}\n";
  }
  exit();
}

if ($format == 'pdf') {
  $pdf = new TCPDF();
  $pdf->AddPage();
  $html = '<h3>User Roles Report</h3><table border="1" cellpadding="4"><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead><tbody>';

  while ($row = mysqli_fetch_assoc($result)) {
    $html .= "<tr><td>{$row['name']}</td><td>{$row['email']}</td><td>{$row['role']}</td></tr>";
  }

  $html .= '</tbody></table>';
  $pdf->writeHTML($html);
  $pdf->Output('user_roles.pdf', 'D');
  exit();
}
?>