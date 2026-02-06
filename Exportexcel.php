<?php
// Exportexcel.php
// Replace the file contents with this. Make sure there is NO whitespace before <?php

require 'db.php';

// --- Read month/year from GET and validate ---
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// Basic validation/fallback
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 9999) $year = (int)date('Y');

// --- Fetch expenses for the selected month/year ---
$sql = "SELECT e.expense_date, e.item_name, e.category, COALESCE(e.SubCategory, '') AS SubCategory, e.price, s.pay_date AS salary_date
        FROM expenses e
        LEFT JOIN salaries s ON e.salary_id = s.id
        WHERE MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?
        ORDER BY e.expense_date DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo "DB prepare error: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Totals: total_spent and total_salary ---
$total_spent = 0.00;
$total_salary = 0.00;

$sql2 = "SELECT SUM(price) AS total_spent FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("ii", $month, $year);
$stmt2->execute();
$row2 = $stmt2->get_result()->fetch_assoc();
$total_spent = (float)($row2['total_spent'] ?? 0.00);
$stmt2->close();

$sql3 = "SELECT SUM(amount) AS total_salary FROM salaries WHERE MONTH(pay_date) = ? AND YEAR(pay_date) = ?";
$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("ii", $month, $year);
$stmt3->execute();
$row3 = $stmt3->get_result()->fetch_assoc();
$total_salary = (float)($row3['total_salary'] ?? 0.00);
$stmt3->close();

$remaining = $total_salary - $total_spent;

// --- Send headers and output table ---
$filename = "SpendIt_expenses_{$month}_{$year}.xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");

// Use UTF-8 BOM so Excel on Windows opens UTF-8 correctly
echo "\xEF\xBB\xBF";

echo "<table border='1'>";
echo "<tr>
        <th>Date</th>
        <th>Item</th>
        <th>Category</th>
        <th>Sub-Category</th>
        <th style='text-align:right'>Price</th>
        <th>From Salary (Date)</th>
      </tr>";

foreach ($rows as $r) {
    $date = htmlspecialchars($r['expense_date']);
    $item = htmlspecialchars($r['item_name']);
    $cat  = htmlspecialchars($r['category']);
    $sub  = htmlspecialchars($r['SubCategory']);
    $price = number_format((float)$r['price'], 2);
    $salary_date = $r['salary_date'] ? htmlspecialchars($r['salary_date']) : 'N/A';

    echo "<tr>";
    echo "<td>{$date}</td>";
    echo "<td>{$item}</td>";
    echo "<td>{$cat}</td>";
    echo "<td>{$sub}</td>";
    echo "<td style='text-align:right'>{$price}</td>";
    echo "<td>{$salary_date}</td>";
    echo "</tr>";
}

// Totals row (separate section)
echo "<tr><td colspan='6'></td></tr>";
echo "<tr>
        <td colspan='4' style='font-weight:bold;text-align:right'>Total Spent:</td>
        <td style='text-align:right;font-weight:bold'>" . number_format($total_spent, 2) . "</td>
        <td></td>
      </tr>";
echo "<tr>
        <td colspan='4' style='font-weight:bold;text-align:right'>Total Salary:</td>
        <td style='text-align:right;font-weight:bold'>" . number_format($total_salary, 2) . "</td>
        <td></td>
      </tr>";
echo "<tr>
        <td colspan='4' style='font-weight:bold;text-align:right'>Remaining Balance (Salary - Spent):</td>
        <td style='text-align:right;font-weight:bold'>" . number_format($remaining, 2) . "</td>
        <td></td>
      </tr>";

echo "</table>";

// Close DB
$conn->close();
exit;
