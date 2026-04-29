<?php
// index.php - Main Dashboard View
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user = getCurrentUser();

// Get selected month from GET param, default to current month
$selected_month = isset($_GET['month']) && !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch employees with payroll for the selected month
$payroll_display = getEmployeesWithPayrollByMonth($pdo, $selected_month);

// --- Summary statistics ---
// Active / Inactive headcount
$active_count = count(getEmployees($pdo));
$all_employees = getAllEmployeesIncludingInactive($pdo);
$inactive_count = count($all_employees) - $active_count;

// Payroll totals from current view
$total_gross = 0;
$total_net = 0;
$total_tax = 0;
foreach ($payroll_display as $rec) {
    $total_gross += $rec['gross_income'];
    $total_net   += $rec['net_income'];
    $total_tax   += $rec['total_tax_withheld'];
}
$avg_net = count($payroll_display) > 0 ? $total_net / count($payroll_display) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            color: var(--text);
        }
        .stat-value.highlight {
            color: var(--amber);
        }
        .stat-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--amaranth);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .export-btn:hover { background: #059669; }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header" style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:18px; justify-content:space-between;">
            <div>
                <h1>Dashboard</h1>
                <p>Payroll overview &amp; calculation breakdown</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; min-width:180px;">
                <label for="currency-header-toggle" style="font-size:.75rem; font-weight:700; letter-spacing:.08em; color:var(--text-muted); text-transform:uppercase;">Currency</label>
                <select id="currency-header-toggle" data-currency-toggle style="border-radius:10px; border:1px solid var(--border); padding:10px 12px; background:#fff; color:#111; font-weight:600;">
                    <option value="USD">USD</option>
                    <option value="PHP">PHP</option>
                </select>
            </div>
        </header>

        <!-- Summary Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Employees</div>
                <div class="stat-value"><?php echo $active_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Inactive Employees</div>
                <div class="stat-value"><?php echo $inactive_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Gross (<?php echo date('M Y', strtotime($selected_month . '-01')); ?>)</div>
                <div class="stat-value"><span class="currency-amount" data-usd="<?php echo number_format($total_gross, 2, '.', ''); ?>">$<?php echo number_format($total_gross, 2); ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Net</div>
                <div class="stat-value highlight"><span class="currency-amount" data-usd="<?php echo number_format($total_net, 2, '.', ''); ?>">$<?php echo number_format($total_net, 2); ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Tax Withheld</div>
                <div class="stat-value"><span class="currency-amount" data-usd="<?php echo number_format($total_tax, 2, '.', ''); ?>">$<?php echo number_format($total_tax, 2); ?></span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Net Income</div>
                <div class="stat-value"><span class="currency-amount" data-usd="<?php echo number_format($avg_net, 2, '.', ''); ?>">$<?php echo number_format($avg_net, 2); ?></span></div>
            </div>
        </div>

        <!-- Month Filter -->
        <section class="card" style="padding: 18px 24px; margin-bottom: 20px;">
            <form method="GET" id="month-filter-form" style="display:flex; align-items:center; gap: 16px; flex-wrap:wrap;">
                <label style="font-size:.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; white-space:nowrap;">
                    Filter by Month
                </label>
                <input
                    type="month"
                    name="month"
                    value="<?php echo htmlspecialchars($selected_month); ?>"
                    style="width: auto; min-width: 180px;"
                    onchange="this.form.submit()"
                >
                <span style="font-size:.875rem; color:var(--text-muted);">
                    Showing payroll for
                    <strong style="color:var(--text);">
                        <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
                    </strong>
                </span>
                <?php if ($selected_month !== date('Y-m')): ?>
                    <a href="index.php" style="font-size:.8rem; color:var(--primary); font-weight:600; text-decoration:none; margin-left:auto;">
                        ← Back to current month
                    </a>
                <?php endif; ?>
                <div style="margin-left:auto;">
                    <a href="export_payroll.php?month=<?php echo urlencode($selected_month); ?>" class="export-btn">
                        📥 Export CSV
                    </a>
                </div>
            </form>
        </section>

        <!-- Payroll Table -->
        <section class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Month</th>
                            <th>Gross</th>
                            <th>SSS</th>
                            <th>PhilHealth</th>
                            <th>Pag-IBIG</th>
                            <th>Taxable</th>
                            <th>Tax</th>
                            <th>Net Income</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payroll_display)): ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding: 48px 20px;">
                                    <div style="color:var(--text-muted); font-size:.95rem;">
                                        No payroll records found for
                                        <strong><?php echo date('F Y', strtotime($selected_month . '-01')); ?></strong>.
                                    </div>
                                    <div style="margin-top:10px; font-size:.85rem;">
                                        <a href="phase2.php" style="color:var(--primary); font-weight:600;">Go to Timesheet →</a>
                                        to process payroll for this month.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payroll_display as $rec): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($rec['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($rec['employee_id_code']); ?> &nbsp;·&nbsp;
                                    <span class="badge"><?php echo htmlspecialchars($rec['position']); ?></span></small>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?php echo date('M Y', strtotime($rec['month_year'])); ?>
                                </td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['gross_income'], 2, '.', ''); ?>">$<?php echo number_format($rec['gross_income'], 2); ?></span></td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['sss_deduction'], 2, '.', ''); ?>">$<?php echo number_format($rec['sss_deduction'], 2); ?></span></td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['philhealth_deduction'], 2, '.', ''); ?>">$<?php echo number_format($rec['philhealth_deduction'], 2); ?></span></td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['pagibig_deduction'], 2, '.', ''); ?>">$<?php echo number_format($rec['pagibig_deduction'], 2); ?></span></td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['taxable_income'], 2, '.', ''); ?>">$<?php echo number_format($rec['taxable_income'], 2); ?></span></td>
                                <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($rec['total_tax_withheld'], 2, '.', ''); ?>">$<?php echo number_format($rec['total_tax_withheld'], 2); ?></span></td>
                                <td class="net-income"><span class="currency-amount" data-usd="<?php echo number_format($rec['net_income'], 2, '.', ''); ?>">$<?php echo number_format($rec['net_income'], 2); ?></span></td>
                                <td>
                                    <?php
                                        $edit_month_param = date('Y-m', strtotime($rec['month_year']));
                                    ?>
                                    <a href="phase2.php?employee_id=<?php echo $rec['emp_id']; ?>&month=<?php echo $edit_month_param; ?>"
                                       style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:var(--primary-soft); color:var(--primary); border-radius:6px; font-size:.78rem; font-weight:700; text-decoration:none; white-space:nowrap; transition:background .15s;"
                                       onmouseover="this.style.background='#dbeafe'"
                                       onmouseout="this.style.background='var(--primary-soft)'">
                                        ✏️ Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="script.js?v=2"></script>
</body>
</html>