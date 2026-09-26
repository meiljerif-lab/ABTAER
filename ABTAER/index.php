<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location:login.php");
  exit;
}
include 'API/db_connect.php';
$user_id = $_SESSION['user_id'];
$isAdmin = false;
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $user_id);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
if ($roleRow && $roleRow['role'] === 'admin') {
  $isAdmin = true;
}
$stmt = $conn->prepare('SELECT categories.id AS category_id, categories.key_name, COALESCE(category_budgets.amount, 0) AS amount
    FROM categories
    LEFT JOIN category_budgets 
        ON category_budgets.category_id = categories.id 
        AND category_budgets.user_id = ?
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
  $categories[$row['category_id']] = [
    'key' => $row['key_name'],
    'name' => ucfirst($row['key_name']),
    'amount' => (float) $row['amount'],
    'spent' => 0, // TODO(PHP): Replace with actual spent amount
    'color' => 'var(--cat-' . $row['key_name'] . ')'
  ];
}
$stmt = $conn->prepare("SELECT id, requested_plan, reviewed_at 
    FROM subscription_requests 
    WHERE user_id = ? 
      AND status = 'rejected' 
      AND reviewed_at >= NOW() - INTERVAL 7 DAY
    ORDER BY reviewed_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recentRejections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt2 = $conn->prepare("SELECT expenses.category_id, SUM(expenses.amount) AS total FROM expenses WHERE DATE_FORMAT(expenses.created_at, '%Y,%m') = DATE_FORMAT(NOW(), '%Y,%m') AND user_id = ? GROUP BY expenses.category_id;");
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
while ($row2 = $result2->fetch_assoc()) {
  if (isset($categories[$row2['category_id']])) {
    $categories[$row2['category_id']]['spent'] = (float) $row2['total'];
  }
}
$totalBudget = array_sum(array_column($categories, 'amount'));

// cap for "day"
$stmt_day = $conn->prepare("SELECT total FROM period_budgets WHERE user_id = ? AND type = ?");
$period_type = 'day';
$stmt_day->bind_param('is', $user_id, $period_type);
$stmt_day->execute();
$row3 = $stmt_day->get_result()->fetch_assoc();
$dayBudget = $row3 ? (float) $row3['total'] : 0;

// spent for "day"
$stmt_day2 = $conn->prepare("SELECT SUM(amount) AS total_spent FROM expenses WHERE DATE(created_at) = CURDATE() AND user_id = ?");
$stmt_day2->bind_param('i', $user_id);
$stmt_day2->execute();
$row4 = $stmt_day2->get_result()->fetch_assoc();
$daySpent = $row4 && $row4['total_spent'] !== null ? (float) $row4['total_spent'] : 0;

// cap for "week"
$stmt_week = $conn->prepare("SELECT total FROM period_budgets WHERE user_id = ? AND type = ?");
$period_type = 'week';
$stmt_week->bind_param('is', $user_id, $period_type);
$stmt_week->execute();
$row5 = $stmt_week->get_result()->fetch_assoc();
$weekBudget = $row5 ? (float) $row5['total'] : 0;
// spent for "week"
$stmt_week2 = $conn->prepare("SELECT SUM(amount) AS total_spent FROM expenses WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE()) AND user_id = ?");
$stmt_week2->bind_param('i', $user_id);
$stmt_week2->execute();
$row6 = $stmt_week2->get_result()->fetch_assoc();
$weekSpent = $row6 && $row6['total_spent'] !== null ? (float) $row6['total_spent'] : 0;

// cap for "month"
$stmt_month = $conn->prepare("SELECT total FROM period_budgets WHERE user_id = ? AND type = ?");
$period_type = 'month';
$stmt_month->bind_param('is', $user_id, $period_type);
$stmt_month->execute();
$row7 = $stmt_month->get_result()->fetch_assoc();
$monthBudget = $row7 ? (float) $row7['total'] : 0;
// spent for "month"
$stmt_month2 = $conn->prepare("SELECT SUM(amount) AS total_spent FROM expenses WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND user_id = ?");
$stmt_month2->bind_param('i', $user_id);
$stmt_month2->execute();
$row8 = $stmt_month2->get_result()->fetch_assoc();
$monthSpent = $row8 && $row8['total_spent'] !== null ? (float) $row8['total_spent'] : 0;

$periods = [
  'day'   => ['label' => 'Today',      'spent' => $daySpent, 'budget' => $dayBudget],
  'week'  => ['label' => 'This Week',  'spent' => $weekSpent, 'budget' => $weekBudget],
  'month' => ['label' => 'This Month', 'spent' => $monthSpent, 'budget' => $monthBudget],
];

$stmt_recent = $conn->prepare("SELECT expenses.amount, categories.name AS category FROM expenses JOIN categories ON expenses.category_id = categories.id WHERE expenses.user_id = ? ORDER BY expenses.created_at DESC LIMIT 5");
$stmt_recent->bind_param('i', $user_id);
$stmt_recent->execute();
$recentExpenses = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_savings = $conn->prepare("SELECT balance FROM savings WHERE user_id = ?");
$stmt_savings->bind_param('i', $user_id);
$stmt_savings->execute();
$row9 = $stmt_savings->get_result()->fetch_assoc();
$savingsBalance = $row9 ? (float) $row9['balance'] : 0;

$assessmentSeries = [
  'week'  => ['labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 'values' => [120, 95, 60, 150, 80, 200, 40]],
  'month' => ['labels' => ['1st', '2nd', '3rd', '4th'], 'values' => [480, 500, 400, 260]],
];

function fmt_money($n)
{
  return number_format((float)$n, 0);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budget Tracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="Assets/favicon.png">
  <link rel="stylesheet" href="style.css">
</head>

<body>

  <div class="topbar">
    <div class="topbar-actions">
      <div class="notif-wrap">
        <button class="icon-btn" id="notifBtn" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M6 8a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z" />
            <path d="M10 20a2 2 0 0 0 4 0" />
          </svg>
          <span class="notif-badge" id="notifBadge" hidden>0</span>
        </button>
        <div class="notif-menu" id="notifMenu">
          <div class="notif-menu-head">Notifications</div>
          <ul class="notif-list" id="notifList">
            <?php if (empty($recentRejections)): ?>
              <li class="notif-empty">You're all caught up.</li>
            <?php else: ?>
              <?php foreach ($recentRejections as $r):
                $planLabel = $r['requested_plan'] === 'student_worker' ? 'Student/Worker' : ucfirst($r['requested_plan']);
              ?>
                <li class="notif-item warn">
                  <span class="dot"></span>
                  <span class="notif-body">
                    Your <?= htmlspecialchars($planLabel) ?> request was not approved.
                    <span class="notif-time"><?= date('M j', strtotime($r['reviewed_at'])) ?></span>
                  </span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="profile">
        <button class="profile-btn icon-btn" id="profileBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
          </svg>
        </button>
        <div class="profile-menu" id="profileMenu">
          <button data-nav="account.php">Your Account</button>
          <button data-nav="subscription.php">Subscription</button>
          <?php if ($isAdmin): ?>
            <button data-nav="admin.php">Admin Dashboard</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="logout">
        <form action="logout.php" method="POST">
          <button type="submit"><img width="17" height="17" src="https://img.icons8.com/ios/50/exit--v1.png" alt="exit--v1" /></button>
        </form>
      </div>
    </div>
  </div>

  <div class="app-shell">
    <div class="app-container">

      <!-- ============ SIDEBAR ============ -->
      <aside class="sidebar">
        <h1>Goals & Savings</h1>

        <?php foreach ($periods as $key => $p):
          $pct = $p['budget'] > 0 ? min(100, round($p['spent'] / $p['budget'] * 100)) : 0;
          $over = $p['spent'] > $p['budget'];
        ?>
          <div class="period-card" data-period="<?= $key ?>">
            <div class="period-head">
              <h2><?= htmlspecialchars($p['label']) ?></h2>
              <button type="button" class="period-edit-btn" data-edit="<?= $key ?>" aria-label="Edit <?= htmlspecialchars($p['label']) ?> budget">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 20h9" />
                  <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                </svg>
              </button>
            </div>
            <div class="spend-line">
              <strong class="spent-amt"><?= fmt_money($p['spent']) ?></strong> / <span class="budget-amt"><?= fmt_money($p['budget']) ?></span> spent
            </div>
            <div class="progress-track">
              <div class="progress-fill<?= $over ? ' over' : '' ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="period-edit-row" id="editRow-<?= $key ?>">
              <input type="number" min="0" step="0.01" class="period-budget-input" value="<?= $p['budget'] ?>" aria-label="<?= htmlspecialchars($p['label']) ?> budget amount">
              <button type="button" class="save-btn" data-save="<?= $key ?>">Save</button>
              <button type="button" class="cancel-btn" data-cancel="<?= $key ?>">Cancel</button>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="savings-card">
          <div class="period-head">
            <h2>Monthly Savings</h2>
            <span class="savings-info" title="Leftover daily budget rolls in automatically at day's end. Going over rolls back out.">?</span>
          </div>
          <div class="savings-amount" id="savingsAmount">₱<?= fmt_money($savingsBalance) ?></div>
          <p class="savings-sub">Unused daily budget adds in; overspending deducts out — automatically, once the day ends.</p>
        </div>

        <button type="button" class="reset-link" id="resetAllBtn">Reset everything to zero</button>

        <div class="sidebar-spacer"></div>

        <div class="support">
          <p>Support Us</p>

          <a class="donate-btn" href="donate.php">Donate Here →</a>
        </div>
      </aside>

      <!-- ============ MAIN DASHBOARD ============ -->
      <main class="main">
        <div class="tab-row"><span class="tab-pill">Dashboard</span></div>

        <div class="top-grid">

          <!-- Budget setup -->
          <section class="panel" id="budgetPanel">
            <div class="budget-total">
              <div>
                <div class="label">Total Budget</div>
                <div class="amount" id="totalBudgetAmt">₱<?= fmt_money($totalBudget) ?></div>
              </div>
              <button class="btn-set" id="setBudgetBtn">Set Budget</button>
              <div class="budget-actions">
                <button type="button" class="btn-save" id="saveBudgetBtn">Save</button>
                <button type="button" class="btn-cancel" id="cancelBudgetBtn">Cancel</button>
              </div>
            </div>

            <div class="cat-grid" id="categoryGrid">
              <?php foreach ($categories as $c): ?>
                <div class="cat-chip" data-key="<?= $c['key'] ?>">
                  <div><span class="dot" style="background:<?= $c['color'] ?>"></span><span class="name"><?= htmlspecialchars($c['name']) ?></span></div>
                  <div class="amt" data-view>₱<?= fmt_money($c['amount']) ?></div>
                  <input type="number" class="cat-input" min="0" step="0.01" value="<?= $c['amount'] ?>" aria-label="<?= htmlspecialchars($c['name']) ?> budget">
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <!-- Add expense -->
          <section class="panel">
            <h3>Expenses</h3>
            <form id="expenseForm">
              <div class="field">
                <label for="expAmount">Input Amount</label>
                <input type="number" id="expAmount" name="amount" min="0" step="0.01" placeholder="0.00" required>
              </div>
              <div class="field">
                <label for="expCategory">Type of Expense</label>
                <select id="expCategory" name="category" required>
                  <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['key'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn-submit">Submit</button>
              <div class="form-msg" id="expenseMsg"></div>
            </form>
          </section>

          <!-- Expenses history -->
          <section class="panel">
            <div class="panel-head">
              <h3>Expenses History</h3>
              <button class="link-btn" id="fullHistoryBtn">Show Full History</button>
            </div>
            <ul class="history-list" id="historyList">
              <?php if (empty($recentExpenses)): ?>
                <li class="empty-msg">No expense history yet. Add your first expense from the dashboard😊</li>
              <?php else: ?>
                <?php foreach ($recentExpenses as $e): ?>
                  <li>
                    <span class="cat"><?= htmlspecialchars($e['category']) ?></span>
                    <span class="amt">-₱<?= fmt_money($e['amount']) ?></span>
                  </li>
                <?php endforeach; ?>
              <?php endif ?>
            </ul>
          </section>
        </div>


        <!-- Expenses Assessment -->
        <section class="assessment">
          <div class="panel-head">
            <h3>Expenses Assessment</h3>
            <!-- TODO(PHP): on change, fetch api/get_assessment.php?period=week|month -->
            <select class="period-select" id="periodSelect">
              <option value="week">Weekly</option>
              <option value="month" selected>Monthly</option>
            </select>
          </div>

          <div class="assess-grid">
            <div class="pie-block">
              <canvas id="categoryPie" width="180" height="180"></canvas>
              <ul class="legend" id="categoryLegend"></ul>
            </div>

            <div class="bar-block">
              <div class="bar-legend"><span class="dot"></span>Total Spent</div>
              <canvas id="spendBar" height="220"></canvas>
            </div>
          </div>
        </section>

      </main>
    </div>
  </div>
  <!--Button to TOP-->
  <button onclick="topFunction()" id="myBtn" title="Go to top"><img width="30" height="30" src="https://img.icons8.com/ios/50/left-up2--v2.png" alt="left-up2--v2" /></button>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <script>
    // Data seeds passed from PHP to JS — replace with your live values.
    window.BUDGET_DATA = {
      categories: <?= json_encode(array_values($categories)) ?>,
      totalBudget: <?= json_encode($totalBudget) ?>,
      periods: <?= json_encode($periods) ?>,
      series: <?= json_encode($assessmentSeries) ?>,
      savings: {
        balance: <?= json_encode($savingsBalance) ?>
      }
    };
  </script>
  <script>
    let myButton = document.getElementById("myBtn");
    window.onscroll = function() {
      scrollFunction()
    };

    function scrollFunction() {
      if (document.body.scrollTop > 30 || document.documentElement.scrollTop > 30) {
        myButton.style.display = "block";
      } else {
        myButton.style.display = "none";
      }
    }

    function topFunction() {
      document.body.scrolltop = 0;
      document.documentElement.scrollTop = 0;
    }
  </script>
  <script src="app.js"></script>
</body>

</html>