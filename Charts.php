<?php
require 'db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login-Register.php");
    exit;
}

$user_id = $_SESSION['user_id'];


// --- Pie Chart: Categories ---
$categoryData = [];
$stmt = $conn->prepare("
    SELECT e.category, SUM(e.price) AS total
    FROM expenses e
    JOIN salaries s ON e.salary_id = s.id
    WHERE s.user_id = ?
    GROUP BY e.category
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $categoryData[$row['category']] = $row['total'];
}


// --- Bar/Line Chart: Monthly ---
$monthlyData = [];
$stmt = $conn->prepare("
    SELECT MONTH(e.expense_date) AS month, SUM(e.price) AS total
    FROM expenses e
    JOIN salaries s ON e.salary_id = s.id
    WHERE s.user_id = ?
    GROUP BY MONTH(e.expense_date)
    ORDER BY month
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $monthlyData[$row['month']] = $row['total'];
}


// --- Subcategory Totals Across All Categories ---
$subCategoryData = [];
$sql = "
    SELECT e.subcategory, SUM(e.price) AS total
    FROM expenses e
    JOIN salaries s ON e.salary_id = s.id
    WHERE s.user_id = ?
      AND e.subcategory IS NOT NULL
      AND e.subcategory != ''
    GROUP BY e.subcategory
    ORDER BY total DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subCategoryData[$row['subcategory']] = $row['total'];
}


?>
<!DOCTYPE html>
<html>
<head>
  <title>SpendIt - Charts</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg-light: #f4f6f9;
      --card-light: #ffffff;
      --text-light: #333;
      --bg-dark: #121212;
      --card-dark: #1e1e1e;
      --text-dark: #f4f4f4;
      --accent: #007bff;
    }
    body {
      font-family: Arial, sans-serif;
      background: var(--bg-light);
      color: var(--text-light);
      margin: 0;
      padding: 0;
      transition: background 0.4s, color 0.4s;
    }
    body.dark {
      background: var(--bg-dark);
      color: var(--text-dark);
    }
    .header {
      text-align: center;
      padding: 15px;
      background: var(--accent);
      color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .header button {
      background: white;
      color: var(--accent);
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
    }
    .header button:hover {
      background: #e9ecef;
    }
    .toggle-btn {
      background: transparent;
      color: white;
      border: 1px solid white;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
    }
    .chart-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 25px;
      padding: 25px;
      max-width: 1200px;
      margin: auto;
    }
    .chart-card {
      background: var(--card-light);
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      padding: 20px;
      transition: background 0.4s, color 0.4s;
    }
    body.dark .chart-card {
      background: var(--card-dark);
      box-shadow: 0 2px 5px rgba(255,255,255,0.05);
    }
    .chart-card h3 {
      text-align: center;
      margin-bottom: 15px;
    }
    canvas {
      max-height: 300px;
    }
  </style>
</head>

<body>
  <div class="header">
    <h2>💰 SpendIt - Spending Overview</h2>
    <button onclick="window.location.href='Indexpage.php'">🏠 Home</button>
    <button class="toggle-btn" id="modeToggle">🌙 Dark Mode</button>
  </div>

  <div class="chart-grid">
    <div class="chart-card">
      <h3>Spending by Category</h3>
      <canvas id="pieChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>Monthly Totals</h3>
      <canvas id="barChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>Monthly Spending Trend</h3>
      <canvas id="lineChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>Food Sub-Categories</h3>
      <canvas id="subCategoryChart"></canvas>
    </div>
  </div>

  <script>
    const categoryData = <?php echo json_encode($categoryData); ?>;
    const monthlyData = <?php echo json_encode($monthlyData); ?>;
    const subCategoryData = <?php echo json_encode($subCategoryData); ?>;

    // --- Chart options ---
    function chartOptions(isDark = false) {
      return {
        plugins: { 
          legend: { 
            position: 'bottom',
            labels: { color: isDark ? '#fff' : '#000' }
          }
        },
        scales: {
          x: { ticks: { color: isDark ? '#fff' : '#000' } },
          y: { ticks: { color: isDark ? '#fff' : '#000' } }
        },
        maintainAspectRatio: false
      };
    }

    // --- Charts setup ---
    let isDark = false;
    const charts = {};

    function createCharts() {
      charts.pie = new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
          labels: Object.keys(categoryData),
          datasets: [{
            data: Object.values(categoryData),
            backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4CAF50','#9C27B0']
          }]
        },
        options: chartOptions(isDark)
      });

      charts.bar = new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
          labels: Object.keys(monthlyData),
          datasets: [{
            label: 'Monthly Spending',
            data: Object.values(monthlyData),
            backgroundColor: '#36A2EB'
          }]
        },
        options: chartOptions(isDark)
      });

      charts.line = new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
          labels: Object.keys(monthlyData),
          datasets: [{
            label: 'Spending Trend',
            data: Object.values(monthlyData),
            borderColor: '#FF6384',
            fill: false
          }]
        },
        options: chartOptions(isDark)
      });

      charts.sub = new Chart(document.getElementById('subCategoryChart'), {
        type: 'doughnut',
        data: {
          labels: Object.keys(subCategoryData),
          datasets: [{
            data: Object.values(subCategoryData),
            backgroundColor: ['#FF9800','#2196F3','#8BC34A','#E91E63']
          }]
        },
        options: chartOptions(isDark)
      });
    }

    createCharts();

    // --- Dark mode toggle ---
    document.getElementById('modeToggle').addEventListener('click', () => {
      document.body.classList.toggle('dark');
      isDark = document.body.classList.contains('dark');
      document.getElementById('modeToggle').textContent = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';

      // Recreate all charts with dark/light color
      Object.values(charts).forEach(chart => chart.destroy());
      createCharts();
    });
  </script>
</body>
</html>
