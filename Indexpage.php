<?php



session_start();

if (!isset($_SESSION['user_id'])) {header("Location: Login-Register.php"); exit;}


$user_id = $_SESSION['user_id'];

require 'db.php';
$message = "Welcome back!";

// Default: selected or current month/year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

/* ---------- Delete Salary ---------- */
if (isset($_GET['delete_salary'])) {
    $id = intval($_GET['delete_salary']);
    $sql = "DELETE FROM salaries WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?month=$month&year=$year&msg=salary_deleted");
    exit;
}

/* ---------- Edit Expense ---------- */
if (isset($_POST['edit_expense'])) {
    $id = intval($_POST['id']);
    $item_name = $_POST['item_name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $subcategory = $_POST['subcategory'] ?? null;
    $expense_date = $_POST['expense_date'];

    $sql = "UPDATE expenses 
            SET item_name = ?, price = ?, category = ?, SubCategory = ?, expense_date = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsssi", $item_name, $price, $category, $subcategory, $expense_date, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: ?month=$month&year=$year&msg=expense_updated");
    exit;
}

/* ---------- Delete Expense ---------- */
if (isset($_GET['delete_expense'])) {
    $id = intval($_GET['delete_expense']);
    $sql = "DELETE FROM expenses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?month=$month&year=$year&msg=expense_deleted");
    exit;
}

/* ---------- Add Expense ---------- */
if (isset($_POST['add_expense'])) {
    $item_name    = $_POST['item_name'];
    $price        = $_POST['price'];
    $category     = $_POST['category'];
    $subcategory  = $_POST['subcategory'] ?? null;
    $expense_date = $_POST['expense_date'];

    // Find latest salary before expense date
    $sql = "SELECT id 
            FROM salaries 
            WHERE user_id = ?
              AND pay_date <= ?
            ORDER BY pay_date DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $expense_date);
    $stmt->execute();
    $salary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$salary) {
        die("❌ No salary found before this expense date.");
    }

    $salary_id = $salary['id'];

    $sql = "INSERT INTO expenses 
            (item_name, price, category, SubCategory, expense_date, salary_id)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsssi", $item_name, $price, $category, $subcategory, $expense_date, $salary_id);
    $stmt->execute();
    $stmt->close();

    header("Location: ?month=$month&year=$year&msg=expense_added");
    exit;
}

/* ---------- Add Salary ---------- */
if (isset($_POST['add_salary'])) {
    $amount = $_POST['amount'];
    $pay_date = $_POST['pay_date'];

    $sql = "INSERT INTO salaries (amount, pay_date, user_id)
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsi", $amount, $pay_date, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: ?month=$month&year=$year&msg=salary_added");
    exit;
}

/* ---------- Messages ---------- */
if (isset($_GET['msg'])) {
    $messages = [
        'salary_deleted' => "🗑️ Salary deleted!",
        'expense_deleted' => "🗑️ Expense deleted!",
        'salary_added' => "✅ Salary added!",
        'expense_added' => "✅ Expense added!",
        'expense_updated' => "✏️ Expense updated!"
    ];
    $message = $messages[$_GET['msg']] ?? "";
}

/* ---------- Dashboard Totals ---------- */

// Total salary
$sql = "SELECT COALESCE(SUM(amount),0) AS total_salary
        FROM salaries
        WHERE user_id = ?
          AND MONTH(pay_date) = ?
          AND YEAR(pay_date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$total_salary = $stmt->get_result()->fetch_assoc()['total_salary'];
$stmt->close();

// Total spent
$sql = "SELECT COALESCE(SUM(e.price),0) AS total_spent
        FROM expenses e
        JOIN salaries s ON e.salary_id = s.id
        WHERE s.user_id = ?
          AND MONTH(e.expense_date) = ?
          AND YEAR(e.expense_date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$total_spent = $stmt->get_result()->fetch_assoc()['total_spent'];
$stmt->close();

$balance = $total_salary - $total_spent;

/* ---------- PREVIOUS MONTH COMPARISON (RESTORED) ---------- */

// Calculate previous month/year
$prev_month = $month - 1;
$prev_year  = $year;

if ($prev_month == 0) {
    $prev_month = 12;
    $prev_year--;
}

// Previous month spent
$sql = "SELECT COALESCE(SUM(e.price),0) AS prev_total_spent
        FROM expenses e
        JOIN salaries s ON e.salary_id = s.id
        WHERE s.user_id = ?
          AND MONTH(e.expense_date) = ?
          AND YEAR(e.expense_date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $prev_month, $prev_year);
$stmt->execute();
$prev_total_spent = $stmt->get_result()->fetch_assoc()['prev_total_spent'];
$stmt->close();

// Differences
$diff = $total_spent - $prev_total_spent;

if ($prev_total_spent > 0) {
    $percent_change = ($diff / $prev_total_spent) * 100;
} else {
    $percent_change = 0;
}

/* ---------- Salaries List ---------- */
$sql = "SELECT s.id, s.amount, s.pay_date,
               COALESCE(SUM(e.price),0) AS spent,
               (s.amount - COALESCE(SUM(e.price),0)) AS remaining
        FROM salaries s
        LEFT JOIN expenses e ON e.salary_id = s.id
        WHERE s.user_id = ?
          AND MONTH(s.pay_date) = ?
          AND YEAR(s.pay_date) = ?
        GROUP BY s.id
        ORDER BY s.pay_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$salaries = $stmt->get_result();
$stmt->close();

/* ---------- Expenses List ---------- */
$sql = "SELECT e.*, s.pay_date AS salary_date
        FROM expenses e
        JOIN salaries s ON e.salary_id = s.id
        WHERE s.user_id = ?
          AND MONTH(e.expense_date) = ?
          AND YEAR(e.expense_date) = ?
        ORDER BY e.expense_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$expenses = $stmt->get_result();
$stmt->close();

/* ---------- Grouped Expenses by Category ---------- */
$sql = "SELECT e.category, COALESCE(SUM(e.price),0) AS total
        FROM expenses e
        JOIN salaries s ON e.salary_id = s.id
        WHERE s.user_id = ?
          AND MONTH(e.expense_date) = ?
          AND YEAR(e.expense_date) = ?
        GROUP BY e.category
        ORDER BY total DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$categories = $stmt->get_result();
$stmt->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SpendIt - Dashboard</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f4f7f8ff; }
    .container { max-width: 950px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; }
    .message { margin: 10px 0; font-weight: bold; }
    form { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    input, button { padding: 8px; margin: 5px 0; width: 100%; }
    button { background: #2c3e50; color: #fff; border: none; cursor: pointer; }
    button:hover { background: #34495e; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #2c3e50; color: white; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .summary { background: #ecf0f1; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
    .summary p { margin: 5px 0; font-size: 16px; }
    a.delete { color: red; text-decoration: none; font-weight: bold; }
    .progress-bar { width: 100%; background: #ddd; border-radius: 5px; margin-top: 5px; height: 12px; }
    .progress { height: 100%; border-radius: 5px; }
    .comparison { background: #fdf6e3; padding: 12px; border-radius: 8px; margin-top: 20px; }
    .comparison p { margin: 5px 0; }
    body {
  font-family: Arial, sans-serif;
  margin: 20px;
  background: #f4f6f8;
  color: #222;
  transition: background 0.3s, color 0.3s;
}

/* ✅ close body correctly and add proper dark theme styles */
body.dark {
  background: #121212;
  color: #e0e0e0;
}

body.dark .container {
  background: #1e1e1e;
  color: #e0e0e0;
  box-shadow: 0 0 8px rgba(255,255,255,0.05);
}

body.dark table {
  background: #1c1c1c;
  border-color: #333;
  color: #f0f0f0;
}

body.dark th {
  background: #333;
  color: #fff;
}

body.dark td {
  background: #1e1e1e;
  color: #ddd;
}

body.dark input,
body.dark select {
  background: #2b2b2b;
  color: #f0f0f0;
  border: 1px solid #555;
}

body.dark button {
  background: #3c6e71;
  color: #fff;
}

body.dark button:hover {
  background: #284b63;
}

body.dark .summary,
body.dark .comparison {
  background: #242424;
  color: #e0e0e0;
}

body.dark a.delete {
  color: #ff7373;
}


button {
  background: #9bb6b6ff;
  color: #782fa8ff;
  border: none;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 4px;
}
button:hover { background: #34495e; }
body.dark #weatherCard {
  background: #242424;
  color: #dd0c28ff;
  box-shadow: 0 0 6px rgba(255,255,255,0.1);
}


  </style>
</head>
<body>
<div class="container">
  <h1>📊 SpendIt Dashboard</h1>

<form method="GET" style="display:flex; gap:10px; align-items:center; margin-bottom:20px;">
    
    <!-- Month Selector -->
    <select name="month" required>
        <?php
        for ($m = 1; $m <= 12; $m++) {
            $selected = ($m == $month) ? 'selected' : '';
            echo "<option value='$m' $selected>" . date('F', mktime(0,0,0,$m,1)) . "</option>";
        }
        ?>
    </select>

    <!-- Year Selector -->
    <select name="year" required>
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $selected = ($y == $year) ? 'selected' : '';
            echo "<option value='$y' $selected>$y</option>";
        }
        ?>
    </select>

    <!-- FORCE FETCH BUTTON -->
    <button type="submit"
        style="
            padding:6px 8px;
            border-radius:10px;
            border:none;
            background:#3b82f6;
            color:white;
            font-weight:bold;
            cursor:pointer;
            width:80px;
        ">
        View
    </button>

</form>


  <a href="logout.php" style="flex:left; margin-bottom:10px; background:#3399FF; color:white; padding:10px 15px; border-radius:8px; text-decoration:none;">🚪 Logout</a>

  <div id="weatherCard" style="float:right; margin:10px; padding:10px; background:#ecf0f1; border-radius:8px; box-shadow:0 0 4px rgba(0,0,0,0.1);">
  🌦️ Loading weather...
</div>
<div style="clear:both;"></div>

  <button id="modeToggle" style="float:left; width: 150px;">
  🌙 Dark Mode
</button>

<a href="Charts.php">
  <button style="float:right; padding:6px 6px; font-size:12px; width:auto;">
    View Charts
  </button>
</a>


  </form>




  <p>📅 Viewing: <?php echo date("F", mktime(0,0,0,$month,1)) . " " . $year; ?></p>

  <div class="summary">
    <p>💵 Total Salary: <strong><?php echo number_format($total_salary, 2); ?></strong></p>
    <p>💸 Total Spent: <strong><?php echo number_format($total_spent, 2); ?></strong></p>
    <p>💰 Balance: <strong><?php echo number_format($balance, 2); ?></strong></p>
  </div>

  <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>

  <div class="grid">
    <!-- Add Expense -->
    <div>
      <h2>Add New Expense</h2>
      <form method="POST">
        <input type="hidden" name="add_expense" value="1">
        <label>Item Name:</label>
        <input type="text" name="item_name" required>

        <label>Price:</label>
        <input type="number" step="0.01" name="price" required>

        <label>Category:</label>
        
        <input type="text" name="category" placeholder="Food, Bills, etc.">
<label>Sub-Category:</label>
<input type="text" name="subcategory" placeholder="Bread, Taxi, Netflix, etc">

        <label>Date:</label>
        <input type="date" name="expense_date" required>

        <button type="submit">Add Expense</button>
      </form>
    </div>

    <!-- Add Salary -->
    <div>
      <h2>Add Salary</h2>
      <form method="POST">
        <input type="hidden" name="add_salary" value="1">
        <label>Amount:</label>
        <input type="number" step="0.01" name="amount" required>

        <label>Pay Date:</label>
        <input type="date" name="pay_date" required>

        <button type="submit">Add Salary</button>
      </form>
    </div>
  </div>

  <!-- Salary List -->
  <h2>Salaries This Month</h2>
  <table>
    <tr>
      <th>Date</th>
      <th>Salary Amount</th>
      <th>Spent</th>
      <th>Remaining</th>
      <th>Progress</th>
      <th>Action</th>
    </tr>
    <?php while ($row = $salaries->fetch_assoc()) { 
      $percent = $row['amount'] > 0 ? max(0, min(100, ($row['remaining'] / $row['amount']) * 100)) : 0;
    ?>
      <tr>
        <td><?php echo $row['pay_date']; ?></td>
        <td><?php echo number_format($row['amount'], 2); ?></td>
        <td><?php echo number_format($row['spent'], 2); ?></td>
        <td><?php echo number_format($row['remaining'], 2); ?></td>
        <td>
          <div class="progress-bar">
            <div class="progress" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? 'red' : ($percent < 70 ? 'orange' : 'green'); ?>;"></div>
          </div>
        </td>
        <td><a class="delete" href="?delete_salary=<?php echo $row['id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>">❌ Delete</a></td>
      </tr>
    <?php } ?>
  </table>

  <!-- Expense List -->
  <h2>Expenses This Month</h2>
  <a href="Exportexcel.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
   target="_blank"
   style="display:inline-block; background:#2c3e50; color:white; padding:10px 15px; border-radius:8px; text-decoration:none; margin-bottom:10px;">
   ⬇️ Download Excel
</a>




  <table>
    <tr>
      <th>Date</th>
      <th>Item</th>
      <th>Category</th>
      <th>Sub-Category</th>
      <th>Price</th>
      <th>From Salary (Date)</th>
      <th>Action</th>
    </tr>
    <?php while ($row = $expenses->fetch_assoc()) { ?>
      <tr>
        <td><?php echo $row['expense_date']; ?></td>
        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
        <td><?php echo htmlspecialchars($row['category']); ?></td>
<td><?php echo htmlspecialchars($row['subcategory'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['price']); ?></td>

        <td><?php echo $row['salary_date'] ?? "❓ None"; ?></td>
       <td>
  <a class="delete" href="?delete_expense=<?php echo $row['id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>">❌ Delete</a>
  <button type="button" onclick="openEditForm(<?php echo htmlspecialchars(json_encode($row)); ?>)">✏️ Edit</button>
</td>

    <?php } ?>
  </table>

  <!-- Edit Expense Popup -->
<div id="editForm" style="display:none; background:#fff; border-radius:10px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.3); position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:999;">
  <h3>Edit Expense</h3>
  <form method="POST">
    <input type="hidden" name="edit_expense" value="1">
    <input type="hidden" name="id" id="edit_id">

    <label>Item Name:</label>
    <input type="text" name="item_name" id="edit_item" required><br>

    <label>Price:</label>
    <input type="number" step="0.01" name="price" id="edit_price" required><br>

    <label>Category:</label>
    <input type="text" name="category" id="edit_category"><br>

    <label>Subcategory:</label>
    <input type="text" name="subcategory" id="edit_subcategory"><br>

    <label>Date:</label>
    <input type="date" name="expense_date" id="edit_date" required><br>

    <button type="submit">💾 Save</button>
    <button type="button" onclick="document.getElementById('editForm').style.display='none'">❌ Cancel</button>
  </form>
</div>


  <!-- Grouped Expenses by Category -->
  <h2>Expenses by Category (<?php echo date("F", mktime(0,0,0,$month,1)) . " $year"; ?>)</h2>
  <table>
    <tr>
      <th>Category</th>
      <th>Total Spent</th>
    </tr>
    <?php while ($row = $categories->fetch_assoc()) { ?>
      <tr>
        <td><?php echo htmlspecialchars($row['category'] ?: 'Uncategorized'); ?></td>
        <td><?php echo number_format($row['total'], 2); ?></td>
      </tr>
    <?php } ?>
  </table>

  <!-- Month-over-Month Comparison -->
  <div class="comparison">
    <h2>📈 Month-over-Month Comparison</h2>
    <p>Last Month (<?php echo date("F", mktime(0,0,0,$prev_month,1)) . " $prev_year"; ?>): <strong><?php echo number_format($prev_total_spent, 2); ?></strong></p>
    <p>This Month (<?php echo date("F", mktime(0,0,0,$month,1)) . " $year"; ?>): <strong><?php echo number_format($total_spent, 2); ?></strong></p>
    <p>Difference: 
      <strong style="color:<?php echo $diff > 0 ? 'red' : ($diff < 0 ? 'green' : 'black'); ?>">
        <?php echo number_format($diff, 2); ?> 
        (<?php echo number_format($percent_change, 1); ?>%)

    
    </p>
  </div>
</div>
<script>
const toggleBtn = document.getElementById('modeToggle');
const body = document.body;

// Load mode from localStorage
if (localStorage.getItem('theme') === 'dark') {
  body.classList.add('dark');
  toggleBtn.textContent = '☀️ Light Mode';
}

toggleBtn.addEventListener('click', () => {
  body.classList.toggle('dark');
  const isDark = body.classList.contains('dark');
  toggleBtn.textContent = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
});
</script>
<script>
// Weather API Integration
async function loadWeather() {
  const weatherBox = document.getElementById('weatherCard');

  try {
    // Try to get user location
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;
      await fetchWeather(lat, lon);
    }, async () => {
      // Fallback: Montego Bay, Jamaica
      await fetchWeather(18.4667, -77.9167);
    });
  } catch {
    await fetchWeather(18.4667, -77.9167);
  }

  async function fetchWeather(lat, lon) {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`;
    const res = await fetch(url);
    const data = await res.json();

    if (!data.current_weather) {
      weatherBox.textContent = "Weather unavailable 🌦️";
      return;
    }

    const temp = data.current_weather.temperature;
    const wind = data.current_weather.windspeed;
    const code = data.current_weather.weathercode;

    // Convert weather codes to text
    const weatherDesc = {
      0: "Clear ☀️",
      1: "Mainly clear 🌤️",
      2: "Partly cloudy ⛅",
      3: "Overcast ☁️",
      45: "Fog 🌫️",
      48: "Rime fog 🌫️",
      51: "Light drizzle 🌦️",
      61: "Rain 🌧️",
      71: "Snow 🌨️",
      95: "Thunderstorm ⛈️"
    }[code] || "🌈";

    weatherBox.innerHTML = `
      <strong>Weather</strong><br>
      ${weatherDesc}<br>
      🌡️ ${temp}°C<br>
      💨 ${wind} km/h
    `;
  }
}

document.addEventListener('DOMContentLoaded', loadWeather);
</script>

<script>
function openEditForm(data) {
  document.getElementById('editForm').style.display = 'block';
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_item').value = data.item_name;
  document.getElementById('edit_price').value = data.price;
  document.getElementById('edit_category').value = data.category;
  document.getElementById('edit_subcategory').value = data.SubCategory || data.subcategory || '';
  document.getElementById('edit_date').value = data.expense_date;
}
</script>


</body>
</html>
