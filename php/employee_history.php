<?php
// employee_history.php - Employee Payroll History View
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get employee ID from URL parameter
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$employee_id) {
    header('Location: phase1.php');
    exit;
}

// Fetch employee details
$employee = getEmployeeById($pdo, $employee_id);
if (!$employee) {
    header('Location: phase1.php');
    exit;
}

// Fetch payroll history
$payroll_history = getEmployeePayrollHistory($pdo, $employee_id);

// Calculate statistics
$total_gross = 0;
$total_net = 0;
$total_tax = 0;
$record_count = count($payroll_history);

foreach ($payroll_history as $record) {
    $total_gross += $record['gross_income'];
    $total_net += $record['net_income'];
    $total_tax += $record['total_tax_withheld'];
}

$avg_gross = $record_count > 0 ? $total_gross / $record_count : 0;
$avg_net = $record_count > 0 ? $total_net / $record_count : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll History — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap:18px; flex-wrap:wrap;">
            <div>
                <h1>Payroll History</h1>
                <p><?php echo htmlspecialchars($employee['full_name']); ?> (<?php echo htmlspecialchars($employee['employee_id_code']); ?>)</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; min-width:180px;">
                <label for="currency-header-toggle" style="font-size:.75rem; font-weight:700; letter-spacing:.08em; color:var(--text-muted); text-transform:uppercase;">Currency</label>
                <select id="currency-header-toggle" data-currency-toggle style="border-radius:10px; border:1px solid var(--border); padding:10px 12px; background:#fff; color:#111; font-weight:600;">
                    <option value="USD">USD</option>
                    <option value="PHP">PHP</option>
                </select>
            </div>
            <a href="phase1.php?edit=<?php echo $employee['id']; ?>" class="btn-primary">✏️ Edit Employee</a>
        </header>

        <!-- Employee Summary Card -->
        <section class="card">
            <h2>Employee Summary</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Position</div>
                    <div style="font-size: 1.1rem; font-weight: 600;"><?php echo htmlspecialchars($employee['position']); ?></div>
                </div>
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Hourly Rate</div>
                    <div style="font-size: 1.1rem; font-weight: 600;"><span class="currency-amount" data-usd="<?php echo number_format($employee['hourly_rate'], 2, '.', ''); ?>">$<?php echo number_format($employee['hourly_rate'], 2); ?></span></div>
                </div>
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Tax Rate</div>
                    <div style="font-size: 1.1rem; font-weight: 600;"><?php echo $employee['tax_rate']; ?>%</div>
                </div>
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Status</div>
                    <div style="font-size: 1.1rem; font-weight: 600;">
                        <span style="<?php echo $employee['is_active'] ? 'color: #10b981;' : 'color: #ef4444;'; ?>">
                            <?php echo $employee['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistics -->
        <?php if ($record_count > 0): ?>
        <section class="card">
            <h2>Statistics (<?php echo $record_count; ?> records)</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Total Gross Income</div>
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); font-family: 'DM Mono';"><span class="currency-amount" data-usd="<?php echo number_format($total_gross, 2, '.', ''); ?>">$<?php echo number_format($total_gross, 2); ?></span></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Avg: <span class="currency-amount" data-usd="<?php echo number_format($avg_gross, 2, '.', ''); ?>">$<?php echo number_format($avg_gross, 2); ?></span></div>
                </div>
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Total Net Income</div>
                    <div style="font-size: 1.3rem; font-weight: 700; color: #10b981; font-family: 'DM Mono';"><span class="currency-amount" data-usd="<?php echo number_format($total_net, 2, '.', ''); ?>">$<?php echo number_format($total_net, 2); ?></span></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Avg: <span class="currency-amount" data-usd="<?php echo number_format($avg_net, 2, '.', ''); ?>">$<?php echo number_format($avg_net, 2); ?></span></div>
                </div>
                <div style="padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px;">Total Tax Withheld</div>
                    <div style="font-size: 1.3rem; font-weight: 700; color: #f59e0b; font-family: 'DM Mono';"><span class="currency-amount" data-usd="<?php echo number_format($total_tax, 2, '.', ''); ?>">$<?php echo number_format($total_tax, 2); ?></span></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Avg: <span class="currency-amount" data-usd="<?php echo number_format($total_tax / $record_count, 2, '.', ''); ?>">$<?php echo number_format($total_tax / $record_count, 2); ?></span></div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Payroll Records Table -->
        <section class="card">
            <h2>Payroll Records</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Gross Income</th>
                            <th>Deductions</th>
                            <th>Taxable</th>
                            <th>Tax</th>
                            <th>Net Income</th>
                            <th>Processed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payroll_history)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">No payroll records yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payroll_history as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M Y', strtotime($record['month_year'])); ?></strong>
                                </td>
                                <td class="mono">
                                    <strong><span class="currency-amount" data-usd="<?php echo number_format($record['gross_income'], 2, '.', ''); ?>">$<?php echo number_format($record['gross_income'], 2); ?></span></strong>
                                </td>
                                <td class="mono">
                                    <small>
                                        SSS: <span class="currency-amount" data-usd="<?php echo number_format($record['sss_deduction'], 2, '.', ''); ?>">$<?php echo number_format($record['sss_deduction'], 2); ?></span><br>
                                        PH: <span class="currency-amount" data-usd="<?php echo number_format($record['philhealth_deduction'], 2, '.', ''); ?>">$<?php echo number_format($record['philhealth_deduction'], 2); ?></span><br>
                                        PI: <span class="currency-amount" data-usd="<?php echo number_format($record['pagibig_deduction'], 2, '.', ''); ?>">$<?php echo number_format($record['pagibig_deduction'], 2); ?></span>
                                    </small>
                                </td>
                                <td class="mono">
                                    <span class="currency-amount" data-usd="<?php echo number_format($record['taxable_income'], 2, '.', ''); ?>">$<?php echo number_format($record['taxable_income'], 2); ?></span>
                                </td>
                                <td class="mono">
                                    <span style="color: #f59e0b;"><span class="currency-amount" data-usd="<?php echo number_format($record['total_tax_withheld'], 2, '.', ''); ?>">$<?php echo number_format($record['total_tax_withheld'], 2); ?></span></span>
                                </td>
                                <td class="mono">
                                    <strong style="color: #10b981;"><span class="currency-amount" data-usd="<?php echo number_format($record['net_income'], 2, '.', ''); ?>">$<?php echo number_format($record['net_income'], 2); ?></span></strong>
                                </td>
                                <td>
                                    <small style="color: var(--text-muted);">
                                        <?php echo date('M d, Y H:i', strtotime($record['processed_at'])); ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <a href="phase1.php" class="btn-secondary">← Back to Employee Config</a>
        </div>
    </main>

    <script src="script.js?v=2"></script>
</body>
</html>
