<?php
// index.php - Main Dashboard View
require_once 'db.php';
$payroll_display = getEmployeesWithLatestPayroll($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>
    
    <main class="content-area">
        <header class="content-header">
            <h1>Dashboard</h1>
            <p>Employee Payroll Overview & Calculation Waterfall</p>
        </header>

        <section class="card full-width">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Month</th>
                            <th>Gross</th>
                            <th>SSS</th>
                            <th>PH</th>
                            <th>PI</th>
                            <th>Taxable</th>
                            <th>Tax</th>
                            <th>Net Income</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payroll_display)): ?>
                            <tr><td colspan="9" style="text-align:center;">No employees found. Please go to Phase 1 to add employees.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payroll_display as $rec): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $rec['full_name']; ?></strong><br>
                                    <small><?php echo $rec['employee_id_code']; ?> | <?php echo $rec['position']; ?></small>
                                </td>
                                <td><?php echo $rec['month_year'] ? date('M Y', strtotime($rec['month_year'])) : '-'; ?></td>
                                <td><?php echo $rec['gross_income'] !== null ? '$' . number_format($rec['gross_income'], 2) : '-'; ?></td>
                                <td><?php echo $rec['sss_deduction'] !== null ? '$' . number_format($rec['sss_deduction'], 2) : '-'; ?></td>
                                <td><?php echo $rec['philhealth_deduction'] !== null ? '$' . number_format($rec['philhealth_deduction'], 2) : '-'; ?></td>
                                <td><?php echo $rec['pagibig_deduction'] !== null ? '$' . number_format($rec['pagibig_deduction'], 2) : '-'; ?></td>
                                <td><?php echo $rec['taxable_income'] !== null ? '$' . number_format($rec['taxable_income'], 2) : '-'; ?></td>
                                <td><?php echo $rec['total_tax_withheld'] !== null ? '$' . number_format($rec['total_tax_withheld'], 2) : '-'; ?></td>
                                <td class="net-income"><?php echo $rec['net_income'] !== null ? '$' . number_format($rec['net_income'], 2) : '-'; ?></td>
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
