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

// Chart data — per-employee names + values for the bar chart
$chart_labels  = [];
$chart_gross   = [];
$chart_net     = [];
$chart_tax     = [];
$total_sss      = 0;
$total_ph       = 0;
$total_pagibig  = 0;
foreach ($payroll_display as $rec) {
    // Shorten name to first name + last initial for chart readability
    $parts = explode(' ', $rec['full_name']);
    $short = $parts[0] . (count($parts) > 1 ? ' ' . strtoupper(substr(end($parts), 0, 1)) . '.' : '');
    $chart_labels[] = $short;
    $chart_gross[]  = round((float)$rec['gross_income'], 2);
    $chart_net[]    = round((float)$rec['net_income'], 2);
    $chart_tax[]    = round((float)$rec['total_tax_withheld'], 2);
    $total_sss     += (float)$rec['sss_deduction'];
    $total_ph      += (float)$rec['philhealth_deduction'];
    $total_pagibig += (float)$rec['pagibig_deduction'];
}
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        /* ── Charts ──────────────────────────────────────────── */
        .charts-grid {
            display: grid;
            grid-template-columns: 260px 260px 1fr;
            gap: 20px;
            margin: 0 44px 24px;
            align-items: start;
        }
        .chart-card {
            background: var(--surface, #f7f7f4);
            border: 1px solid var(--border, #d2d4c8);
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(26,31,29,.07);
        }
        .chart-title {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--text-muted, #727a74);
            margin-bottom: 14px;
        }
        .chart-canvas-wrap {
            position: relative;
        }
        @media (max-width: 1100px) {
            .charts-grid { grid-template-columns: 1fr 1fr; }
            .chart-card.chart-bar { grid-column: 1 / -1; }
        }
        @media (max-width: 700px) {
            .charts-grid { grid-template-columns: 1fr; margin: 0 16px 20px; }
            .chart-card.chart-bar { grid-column: 1; }
        }
    </style>
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

        <!-- Charts Row -->
        <?php if ($active_count + $inactive_count > 0): ?>
        <div class="charts-grid">

            <!-- Donut: Headcount -->
            <div class="chart-card">
                <div class="chart-title">Headcount</div>
                <div class="chart-canvas-wrap" style="height:190px;">
                    <canvas id="chart-headcount"></canvas>
                </div>
            </div>

            <!-- Donut: Payroll Composition -->
            <div class="chart-card">
                <div class="chart-title">Payroll Breakdown</div>
                <div class="chart-canvas-wrap" style="height:190px;">
                    <?php if (!empty($payroll_display)): ?>
                        <canvas id="chart-composition"></canvas>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:190px;color:var(--text-muted);font-size:.8rem;">No payroll this month</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bar: Per-employee Net vs Gross -->
            <div class="chart-card chart-bar">
                <div class="chart-title">Gross vs Net — <?php echo date('F Y', strtotime($selected_month . '-01')); ?></div>
                <div class="chart-canvas-wrap" style="height:190px;">
                    <?php if (!empty($payroll_display)): ?>
                        <canvas id="chart-employees"></canvas>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:190px;color:var(--text-muted);font-size:.8rem;">No payroll records for this month</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endif; ?>

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
    <script>
    // ── Shared palette ───────────────────────────────────────
    const AMBER   = '#e28413';
    const SUCCESS = '#4a6741';
    const DANGER  = '#8b2635';
    const BLUE    = '#2563eb';
    const MUTED   = '#d2d4c8';

    Chart.defaults.font.family = "'DM Sans', 'Libre Franklin', sans-serif";
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#727a74';

    // ── 1. Headcount donut ───────────────────────────────────
    (function() {
        const ctx = document.getElementById('chart-headcount');
        if (!ctx) return;
        const active   = <?php echo (int)$active_count; ?>;
        const inactive = <?php echo (int)$inactive_count; ?>;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [active, inactive],
                    backgroundColor: [SUCCESS, MUTED],
                    borderColor: ['#fff', '#fff'],
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, boxWidth: 11, boxHeight: 11, borderRadius: 3, useBorderRadius: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed}`
                        }
                    }
                }
            }
        });
    })();

    // ── 2. Payroll composition donut ─────────────────────────
    (function() {
        const ctx = document.getElementById('chart-composition');
        if (!ctx) return;
        const net    = <?php echo round($total_net,    2); ?>;
        const tax    = <?php echo round($total_tax,    2); ?>;
        const sss    = <?php echo round($total_sss,    2); ?>;
        const ph     = <?php echo round($total_ph,     2); ?>;
        const pagibig= <?php echo round($total_pagibig,2); ?>;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Net Pay', 'Income Tax', 'SSS', 'PhilHealth', 'Pag-IBIG'],
                datasets: [{
                    data: [net, tax, sss, ph, pagibig],
                    backgroundColor: [SUCCESS, DANGER, AMBER, BLUE, '#7c3aed'],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 10, boxWidth: 10, boxHeight: 10, borderRadius: 3, useBorderRadius: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                                const pct   = total > 0 ? ((ctx.parsed/total)*100).toFixed(1) : 0;
                                return ` ${ctx.label}: $${ctx.parsed.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    })();

    // ── 3. Per-employee grouped bar ──────────────────────────
    (function() {
        const ctx = document.getElementById('chart-employees');
        if (!ctx) return;
        const labels = <?php echo json_encode($chart_labels); ?>;
        const gross  = <?php echo json_encode($chart_gross);  ?>;
        const net    = <?php echo json_encode($chart_net);    ?>;
        const tax    = <?php echo json_encode($chart_tax);    ?>;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Gross',
                        data: gross,
                        backgroundColor: 'rgba(226,132,19,.75)',
                        borderColor: AMBER,
                        borderWidth: 1.5,
                        borderRadius: 4,
                    },
                    {
                        label: 'Net Pay',
                        data: net,
                        backgroundColor: 'rgba(74,103,65,.75)',
                        borderColor: SUCCESS,
                        borderWidth: 1.5,
                        borderRadius: 4,
                    },
                    {
                        label: 'Tax',
                        data: tax,
                        backgroundColor: 'rgba(139,38,53,.65)',
                        borderColor: DANGER,
                        borderWidth: 1.5,
                        borderRadius: 4,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 35, minRotation: 0 }
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: {
                            callback: v => '$' + Number(v).toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, boxWidth: 11, boxHeight: 11, borderRadius: 3, useBorderRadius: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: $${ctx.parsed.y.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}`
                        }
                    }
                }
            }
        });
    })();
    </script>
</body>
</html>