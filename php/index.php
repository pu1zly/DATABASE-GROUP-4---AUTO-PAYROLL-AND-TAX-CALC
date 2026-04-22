<?php
// index.php - Main Dashboard View
require_once 'db.php';

// Get selected month from GET param, default to current month
$selected_month = isset($_GET['month']) && !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch employees with payroll for the selected month
$payroll_display = getEmployeesWithPayrollByMonth($pdo, $selected_month);
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
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Dashboard</h1>
            <p>Payroll overview &amp; calculation breakdown</p>
        </header>

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
                                <td colspan="9" style="text-align:center; padding: 48px 20px;">
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
                                <td class="mono">$<?php echo number_format($rec['gross_income'], 2); ?></td>
                                <td class="mono">$<?php echo number_format($rec['sss_deduction'], 2); ?></td>
                                <td class="mono">$<?php echo number_format($rec['philhealth_deduction'], 2); ?></td>
                                <td class="mono">$<?php echo number_format($rec['pagibig_deduction'], 2); ?></td>
                                <td class="mono">$<?php echo number_format($rec['taxable_income'], 2); ?></td>
                                <td class="mono">$<?php echo number_format($rec['total_tax_withheld'], 2); ?></td>
                                <td class="net-income">$<?php echo number_format($rec['net_income'], 2); ?></td>
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
</body>
</html>
