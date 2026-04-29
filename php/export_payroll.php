<?php
// export_payroll.php – Export payroll records for a specific month as CSV
session_start();
require_once 'db.php';

if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$month = $_GET['month'] ?? date('Y-m');

// Fetch employees with payroll for the given month
$records = getEmployeesWithPayrollByMonth($pdo, $month);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="payroll_' . $month . '.csv"');

$output = fopen('php://output', 'w');

// CSV column headers
fputcsv($output, [
    'Employee ID',
    'Full Name',
    'ID Code',
    'Position',
    'Month',
    'Gross Income',
    'SSS Deduction',
    'PhilHealth Deduction',
    'Pag-IBIG Deduction',
    'Taxable Income',
    'Tax Withheld',
    'Net Income'
]);

foreach ($records as $rec) {
    fputcsv($output, [
        $rec['emp_id'],
        $rec['full_name'],
        $rec['employee_id_code'],
        $rec['position'],
        date('F Y', strtotime($rec['month_year'])),
        number_format($rec['gross_income'], 2, '.', ''),
        number_format($rec['sss_deduction'], 2, '.', ''),
        number_format($rec['philhealth_deduction'], 2, '.', ''),
        number_format($rec['pagibig_deduction'], 2, '.', ''),
        number_format($rec['taxable_income'], 2, '.', ''),
        number_format($rec['total_tax_withheld'], 2, '.', ''),
        number_format($rec['net_income'], 2, '.', '')
    ]);
}

fclose($output);
exit;