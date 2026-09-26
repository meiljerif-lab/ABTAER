/**
 * app.js — Budget Tracker interactivity
 * ------------------------------------------------------------
 * Every network call is wrapped so the UI still works with demo
 * data if the endpoint isn't built yet. Search "TODO(PHP)" for
 * every spot that expects a real backend response.
 */

document.addEventListener('DOMContentLoaded', () => {
  initProfileMenu();
  initNotifications();
  initSetBudget();
  initPeriodBudgets();
  initExpenseForm();
  initFullHistory();
  initAssessment();
  initResetAll();
});

/* ---------------- Notifications ---------------- */
let notifications = [];
let unreadCount = 0;
const WARN_THRESHOLD = 90;  // % of budget used

function initNotifications() {
  const btn = document.getElementById('notifBtn');
  const menu = document.getElementById('notifMenu');

  unreadCount = document.querySelectorAll('#notifList .notif-item').length;
  renderBadge();

  Object.keys(window.BUDGET_DATA.periods).forEach(syncNotifiedLevel);

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    if (open) markAllRead();
  });

  document.addEventListener('click', () => {
    menu.classList.remove('open');
    btn.setAttribute('aria-expanded', false);
  });
}

function addNotification(message, level) {
  notifications.unshift({ message, level, time: new Date() });
  if (notifications.length > 20) notifications.pop();
  unreadCount++;
  renderNotifications();
}

function markAllRead() {
  unreadCount = 0;
  renderBadge();
}

function renderBadge() {
  const badge = document.getElementById('notifBadge');
  if (unreadCount > 0) {
    badge.hidden = false;
    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
  } else {
    badge.hidden = true;
  }
}

function renderNotifications() {
  const list = document.getElementById('notifList');
  renderBadge();

  if (notifications.length === 0) {
    list.innerHTML = '<li class="notif-empty">You\'re all caught up.</li>';
    return;
  }

  list.innerHTML = notifications.map(n => `
    <li class="notif-item ${n.level}">
      <span class="dot"></span>
      <span class="notif-body">
        ${n.message}
        <span class="notif-time">${n.time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
      </span>
    </li>
  `).join('');
}

// Keeps a period's "have we already warned about this?" state lined up with its
// current spend/budget, WITHOUT firing a new notification. Call this after
// setting/editing a budget. checkBudgetThreshold() is the one that actually notifies.
function syncNotifiedLevel(period) {
  const p = window.BUDGET_DATA.periods[period];
  if (!p) return;
  const pct = p.budget > 0 ? (p.spent / p.budget) * 100 : 0;
  p.notifiedLevel = pct >= 100 ? 'exceeded' : pct >= WARN_THRESHOLD ? 'warn' : 'none';
}

// Call this after spend changes (e.g. a new expense). Fires a notification the
// moment a period newly crosses the warn (90%) or exceeded (100%) line.
function checkBudgetThreshold(period) {
  const p = window.BUDGET_DATA.periods[period];
  if (!p || p.budget <= 0) return;
  const pct = Math.round((p.spent / p.budget) * 100);

  if (pct >= 100 && p.notifiedLevel !== 'exceeded') {
    addNotification(`You've exceeded your ${p.label.toLowerCase()} budget — ₱${p.spent.toLocaleString()} of ₱${p.budget.toLocaleString()}.`, 'exceeded');
    p.notifiedLevel = 'exceeded';
  } else if (pct >= WARN_THRESHOLD && pct < 100 && p.notifiedLevel === 'none') {
    addNotification(`You're close to your ${p.label.toLowerCase()} budget — ${pct}% used.`, 'warn');
    p.notifiedLevel = 'warn';
  }
}

// Shared renderer so the sidebar card always reflects window.BUDGET_DATA.periods[period].
function renderPeriodCard(period) {
  const card = document.querySelector(`.period-card[data-period="${period}"]`);
  const p = window.BUDGET_DATA.periods[period];
  if (!card || !p) return;

  const pct = p.budget > 0 ? Math.min(100, Math.round((p.spent / p.budget) * 100)) : 0;
  const over = p.spent > p.budget;

  card.querySelector('.spent-amt').textContent = p.spent.toLocaleString();
  card.querySelector('.budget-amt').textContent = p.budget.toLocaleString();
  const fill = card.querySelector('.progress-fill');
  fill.style.width = pct + '%';
  fill.classList.toggle('over', over);
}

function renderSavings() {
  const el = document.getElementById('savingsAmount');
  const balance = window.BUDGET_DATA.savings.balance;
  const negative = balance < 0;
  el.textContent = (negative ? '-₱' : '₱') + Math.abs(balance).toLocaleString();
  el.classList.toggle('negative', negative);
}

function initProfileMenu() {
  const btn = document.getElementById('profileBtn');
  const menu = document.getElementById('profileMenu');

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
  });
  menu.querySelectorAll('button[data-nav]').forEach(item => {
    item.addEventListener('click', () => {
      window.location.href = item.dataset.nav;
    });
  })
  menu.querySelectorAll('button[data-coming-soon]').forEach(item => {
    item.addEventListener('click', () => {
      alert(item.dataset.comingSoon + ' — coming soon.');
    });
  });

  document.addEventListener('click', () => {
    menu.classList.remove('open');
    btn.setAttribute('aria-expanded', false);
  });
}
/* ---------------- Set budget (per category, total = sum) ---------------- */
function initSetBudget() {
  const panel = document.getElementById('budgetPanel');
  const setBtn = document.getElementById('setBudgetBtn');
  const saveBtn = document.getElementById('saveBudgetBtn');
  const cancelBtn = document.getElementById('cancelBudgetBtn');
  const totalEl = document.getElementById('totalBudgetAmt');
  const inputs = () => Array.from(panel.querySelectorAll('.cat-input'));

  const computeSum = () =>
    inputs().reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);

  const renderTotal = (value) => { totalEl.textContent = '₱' + value.toLocaleString(); };

  setBtn.addEventListener('click', () => {
    panel.classList.add('is-editing');
    renderTotal(computeSum());
    inputs()[0]?.focus();
  });

  // Total updates live as any category amount changes — it's a sum, not a separate field.
  inputs().forEach(input => {
    input.addEventListener('input', () => renderTotal(computeSum()));
  });

  cancelBtn.addEventListener('click', () => {
    window.BUDGET_DATA.categories.forEach(c => {
      const chip = panel.querySelector(`.cat-chip[data-key="${c.key}"] .cat-input`);
      if (chip) chip.value = c.amount;
    });
    renderTotal(window.BUDGET_DATA.totalBudget);
    panel.classList.remove('is-editing');
  });

  saveBtn.addEventListener('click', async () => {
    const updated = window.BUDGET_DATA.categories.map(c => {
      const input = panel.querySelector(`.cat-chip[data-key="${c.key}"] .cat-input`);
      const amount = parseFloat(input.value);
      return { key: c.key, amount: isNaN(amount) || amount < 0 ? 0 : amount };
    });

    // TODO(PHP): POST { categories: [{key, amount}, ...] } to api/set_category_budgets.php
    // The endpoint should upsert each category's budget_amount and return the same
    // shape back (plus the computed total) so the frontend can just trust the response.
    try {
      const res = await fetch('API/set_category_budgets.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ categories: updated })
      });
      if (!res.ok) throw new Error('Request failed');
    } catch (err) {
      console.warn('set_category_budgets.php not reachable yet — updating UI only.', err);
    }

    const total = updated.reduce((sum, c) => sum + c.amount, 0);

    // Update local state + visible chip amounts
    window.BUDGET_DATA.categories.forEach(c => {
      const found = updated.find(u => u.key === c.key);
      c.amount = found.amount;
      const view = panel.querySelector(`.cat-chip[data-key="${c.key}"] .amt`);
      if (view) view.textContent = '₱' + c.amount.toLocaleString();
    });
    window.BUDGET_DATA.totalBudget = total;
    renderTotal(total);

    panel.classList.remove('is-editing');
    renderAssessment(document.getElementById('periodSelect').value); // categories list may have changed shape
  });
}

/* ---------------- Per-period budget editor (day / week / month) ---------------- */
function initPeriodBudgets() {
  document.querySelectorAll('.period-edit-btn').forEach(btn => {
    const period = btn.dataset.edit;
    const row = document.getElementById(`editRow-${period}`);

    btn.addEventListener('click', () => {
      // Close any other open editor first so only one is active at a time.
      document.querySelectorAll('.period-edit-row.open').forEach(open => {
        if (open !== row) open.classList.remove('open');
      });
      row.classList.toggle('open');
      if (row.classList.contains('open')) row.querySelector('input').focus();
    });
  });

  document.querySelectorAll('.cancel-btn[data-cancel]').forEach(btn => {
    const period = btn.dataset.cancel;
    btn.addEventListener('click', () => {
      const row = document.getElementById(`editRow-${period}`);
      const card = row.closest('.period-card');
      row.querySelector('input').value = window.BUDGET_DATA.periods[period].budget;
      row.classList.remove('open');
    });
  });

  document.querySelectorAll('.save-btn[data-save]').forEach(btn => {
    const period = btn.dataset.save;
    btn.addEventListener('click', () => savePeriodBudget(period));
  });
}

async function savePeriodBudget(period) {
  const row = document.getElementById(`editRow-${period}`);
  const input = row.querySelector('input');
  const value = parseFloat(input.value);

  if (isNaN(value) || value < 0) {
    alert('Enter a valid budget amount.');
    return;
  }

  // TODO(PHP): POST { period, total: value } to api/set_budget.php — the stub already
  // accepts a "period" field (day/week/month) alongside the existing overall total.
  try {
    const res = await fetch('API/set_budget.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ period, total: value })
    });
    if (!res.ok) throw new Error('Request failed');
  } catch (err) {
    console.warn('set_budget.php not reachable yet — updating UI only.', err);
  }

  // Update local state + UI
  const periodData = window.BUDGET_DATA.periods[period];
  periodData.budget = value;
  renderPeriodCard(period);
  syncNotifiedLevel(period); // realign warn/exceeded state to the new budget, no new alert


  row.classList.remove('open');
}

/* ---------------- Add expense ---------------- */
function initExpenseForm() {
  const form = document.getElementById('expenseForm');
  const msg = document.getElementById('expenseMsg');
  const historyList = document.getElementById('historyList');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const amount = parseFloat(document.getElementById('expAmount').value);
    const categorySelect = document.getElementById('expCategory');
    const category = categorySelect.value;
    const categoryLabel = categorySelect.options[categorySelect.selectedIndex].text;

    if (isNaN(amount) || amount <= 0) {
      msg.textContent = 'Enter a valid amount.';
      msg.className = 'form-msg err';
      return;
    }

    // TODO(PHP): POST { amount, category } to api/add_expense.php
    // Expected JSON response: { success: true, expense: {category, amount, created_at} }
    try {
      const res = await fetch('API/add_expense.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ amount, category })
      });
      if (!res.ok) throw new Error('Request failed');
    } catch (err) {
      console.warn('add_expense.php not reachable yet — updating UI only.', err);
    }

    // Optimistically prepend to the visible history
    const li = document.createElement('li');
    li.innerHTML = `<span class="cat">${categoryLabel}</span><span class="amt">-₱${amount.toLocaleString()}</span>`;
    historyList.prepend(li);
    historyList.querySelector('.empty-msg')?.remove();
    if (historyList.children.length > 5) historyList.lastElementChild.remove();


    // An expense made today counts toward today's, this week's, and this
    // month's spend — bump all three progress bars and check for alerts.
    // TODO(PHP): once add_expense.php returns updated totals from the DB,
    // prefer those over this optimistic client-side math.
    // Which category slice grows in the Assessment pie
    const catObj = window.BUDGET_DATA.categories.find(c => c.key === category);
    if (catObj) catObj.spent += amount;

    ['day', 'week', 'month'].forEach(period => {
      const p = window.BUDGET_DATA.periods[period];
      if (!p) return;
      p.spent += amount;
      renderPeriodCard(period);
      checkBudgetThreshold(period);
    });


    // Today's spend also lands in the weekly (Saturday, e.g.) and monthly
    // (current week-of-month) bars of the Expenses Assessment chart below.
    bumpAssessmentSeries(amount);
    renderAssessment(document.getElementById('periodSelect').value);

    msg.textContent = 'Expense added.';
    msg.className = 'form-msg ok';
    form.reset();
  });
}

/* ---------------- Full history ---------------- */
function initFullHistory() {
  const btn = document.getElementById('fullHistoryBtn');
  btn.addEventListener('click', async () => {
    window.location.href = 'history.php';
  });
}

/* ---------------- Expenses Assessment (pie + bar) ---------------- */
let pieChart, barChart;

function initAssessment() {
  const select = document.getElementById('periodSelect');
  renderAssessment(select.value);
  select.addEventListener('change', () => renderAssessment(select.value));
}

async function renderAssessment(period) {
  // TODO(PHP): const res = await fetch(`api/get_assessment.php?period=${period}`);
  //            const data = await res.json();
  // Expected shape: { categories: [{name, amount, color}], series: {labels:[], values:[]} }
  // For now we read from window.BUDGET_DATA, which app.js keeps updated in place
  // as expenses come in (see bumpAssessmentSeries below).
  // Assessment pie shows how actual EXPENSES break down by category —
  // not the budget cap (that's what the category chips / Set Budget editor show).
  const res = await fetch(`API/get_assessment.php?period=${period}`);
  const data = await res.json();
  const categories = data.categories;
  const series = data.series;

  renderPie(categories);
  renderBar(series, period);
}

// Mon=0 .. Sun=6, matching the "week" series' label order.
function currentWeekdayIndex() {
  return (new Date().getDay() + 6) % 7;
}

// Which of the 4 "week of month" buckets today falls into (0-indexed, capped at 3).
function currentMonthWeekIndex() {
  return Math.min(3, Math.floor((new Date().getDate() - 1) / 7));
}

// Adds a newly-logged expense into today's slot of both the weekly (by weekday)
// and monthly (by week-of-month) assessment series, so the bar chart reflects
// it immediately regardless of which period the dropdown is currently showing.
function bumpAssessmentSeries(amount) {
  window.BUDGET_DATA.series.week.values[currentWeekdayIndex()] += amount;
  window.BUDGET_DATA.series.month.values[currentMonthWeekIndex()] += amount;
}

function resolveColor(cssVarExpr) {
  // Turns "var(--cat-utilities)" into its computed hex so Chart.js can use it.
  const match = cssVarExpr.match(/--[\w-]+/);
  if (!match) return cssVarExpr;
  return getComputedStyle(document.documentElement).getPropertyValue(match[0]).trim();
}

function renderPie(categories) {
  const ctx = document.getElementById('categoryPie');
  const legend = document.getElementById('categoryLegend');

  legend.innerHTML = categories.map(c =>
    `<li><span class="dot" style="background:${c.color}"></span>${c.name}<span class="val">₱${c.amount.toLocaleString()}</span></li>`
  ).join('');

  if (pieChart) pieChart.destroy();
  pieChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: categories.map(c => c.name),
      datasets: [{
        data: categories.map(c => c.amount),
        backgroundColor: categories.map(c => c.color),
        borderColor: '#fff',
        borderWidth: 2
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      responsive: true,
      maintainAspectRatio: true,
    }
  });
}

function renderBar(series, period) {
  const ctx = document.getElementById('spendBar');
  const highlightIdx = period === 'week' ? currentWeekdayIndex() : currentMonthWeekIndex();
  const pine = resolveColor('var(--pine)');
  const rust = resolveColor('var(--rust)');

  if (barChart) barChart.destroy();
  barChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: series.labels,
      datasets: [{
        label: period === 'week' ? 'Daily spend' : 'Weekly spend',
        data: series.values,
        backgroundColor: series.values.map((_, i) => i === highlightIdx ? rust : pine),
        borderRadius: 6,
        maxBarThickness: 46
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#E3E1D5' } },
        x: { grid: { display: false } }
      }
    }
  });
}

/* ---------------- Reset everything to zero ---------------- */
function initResetAll() {
  const btn = document.getElementById('resetAllBtn');
  btn.addEventListener('click', async () => {
    const sure = confirm(
      'This clears every budget, expense, chart, and notification back to zero. This can\'t be undone. Continue?'
    );
    if (!sure) return;

    // TODO(PHP): POST to api/reset_data.php — it should wipe/zero this user's
    // rows in budgets, categories, expenses (and periods, if you store spend
    // separately from a live SUM()).
    try {
      const res = await fetch('API/reset_data.php', { method: 'POST' });
      if (!res.ok) throw new Error('Request failed');
    } catch (err) {
      console.warn('reset_data.php not reachable yet — resetting the UI only.', err);
    }

    resetUiToZero();
  });
}

function resetUiToZero() {
  const data = window.BUDGET_DATA;

  // Categories -> 0, and out of edit mode if it happened to be open
  data.categories.forEach(c => { c.amount = 0; c.spent = 0; });
  data.totalBudget = 0;
  document.getElementById('budgetPanel').classList.remove('is-editing');
  document.getElementById('totalBudgetAmt').textContent = '₱0';
  document.querySelectorAll('.cat-chip').forEach(chip => {
    chip.querySelector('.amt').textContent = '₱0';
    chip.querySelector('.cat-input').value = 0;
  });

  // Day / Week / Month sidebar cards -> 0 spent, 0 budget, cleared alert state
  Object.keys(data.periods).forEach(period => {
    const p = data.periods[period];
    p.spent = 0;
    p.budget = 0;
    p.notifiedLevel = 'none';
    renderPeriodCard(period);
    const editInput = document.querySelector(`#editRow-${period} .period-budget-input`);
    if (editInput) editInput.value = 0;
  });

  // Weekly / monthly assessment series -> all zero
  data.series.week.values = data.series.week.values.map(() => 0);
  data.series.month.values = data.series.month.values.map(() => 0);
  renderAssessment(document.getElementById('periodSelect').value);

  // Expense history -> empty
  const historyList = document.getElementById('historyList');
  historyList.innerHTML = '<li class="history-empty">No expenses yet.</li>';

  // Notifications -> cleared
  notifications = [];
  unreadCount = 0;
  renderNotifications();

  // Savings -> zero, and forget the client-side rollover history
  data.savings.balance = 0;
  renderSavings();

}
