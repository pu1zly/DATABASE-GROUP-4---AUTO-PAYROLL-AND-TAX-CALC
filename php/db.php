<?php
// db.php - Overhauled Database Logic (Phase 1-4)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "payroll_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Phase 1: Configuration Helper
function configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("INSERT INTO employees (employee_id_code, full_name, position, hourly_rate, tax_rate) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$id_code, $name, $position, $hourly_rate, $tax_rate]);
}

// Phase 2: Daily Log Helper
function saveDailyLog($pdo, $employee_id, $date, $reg, $ot, $sick, $vac, $is_day_off) {
    $stmt = $pdo->prepare("INSERT INTO daily_logs (employee_id, log_date, reg_hours, ot_hours, sick_hours, vac_hours, is_day_off) 
                           VALUES (?, ?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE reg_hours=VALUES(reg_hours), ot_hours=VALUES(ot_hours), 
                           sick_hours=VALUES(sick_hours), vac_hours=VALUES(vac_hours), is_day_off=VALUES(is_day_off)");
    return $stmt->execute([$employee_id, $date, $reg, $ot, $sick, $vac, $is_day_off]);
}

// Phase 2: Monthly Aggregator
function aggregateMonthlyWork($pdo, $employee_id, $month_year) {
    $stmt = $pdo->prepare("SELECT SUM(reg_hours) as reg, SUM(ot_hours) as ot, SUM(sick_hours) as sick, SUM(vac_hours) as vac 
                           FROM daily_logs 
                           WHERE employee_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ?");
    $stmt->execute([$employee_id, $month_year]);
    $totals = $stmt->fetch();
    
    $total_hours = $totals['reg'] + $totals['ot'] + $totals['sick'] + $totals['vac'];
    
    // Save to monthly_work for record keeping
    $stmt = $pdo->prepare("INSERT INTO monthly_work (employee_id, month_year, reg_hours, ot_hours, sick_hours, vac_hours, total_monthly_hours) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employee_id, $month_year . "-01", $totals['reg'], $totals['ot'], $totals['sick'], $totals['vac'], $total_hours]);
    
    return $totals;
}

// Phase 3 & 4: Calculation Waterfall & Record Storage
function processPayrollRecord($pdo, $employee_id, $month_year, $reg, $ot, $sick, $vac) {
    // 1. Get Employee Info
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    
    if (!$emp) return false;

    // 2. Calculation Waterfall (Phase 3)
    $total_hours = $reg + $ot + $sick + $vac;
    
    // First: Gross Income
    $gross_income = $total_hours * $emp['hourly_rate'];
    
    // Second: Big Three Deductions
    $sss = min($gross_income * 0.05, 25.00); // 5% capped at $25
    $philhealth = $gross_income * 0.025;      // 2.5%
    $pagibig = 4.00;                          // Flat $4
    $total_deductions = $sss + $philhealth + $pagibig;

    // Third: Taxable Income
    $taxable_income = $gross_income - $total_deductions;

    // Fourth: Total Tax Amount
    $total_tax_withheld = $taxable_income * ($emp['tax_rate'] / 100);

    // Final: Monthly Net Income (Phase 4)
    $net_income = $taxable_income - $total_tax_withheld;

    // 3. Save Record
    $stmt = $pdo->prepare("INSERT INTO payroll_records (employee_id, month_year, gross_income, sss_deduction, philhealth_deduction, pagibig_deduction, taxable_income, total_tax_withheld, net_income) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$employee_id, $month_year, $gross_income, $sss, $philhealth, $pagibig, $taxable_income, $total_tax_withheld, $net_income]);
}

// Fetch all employees
function getEmployees($pdo) {
    return $pdo->query("SELECT * FROM employees ORDER BY id DESC")->fetchAll();
}

// Fetch all employees with their latest payroll record if available
function getEmployeesWithLatestPayroll($pdo) {
    $stmt = $pdo->query("
        SELECT 
            e.id as emp_id, e.full_name, e.employee_id_code, e.position,
            p.month_year, p.gross_income, p.sss_deduction, p.philhealth_deduction, 
            p.pagibig_deduction, p.taxable_income, p.total_tax_withheld, p.net_income
        FROM employees e
        LEFT JOIN (
            SELECT p1.*
            FROM payroll_records p1
            INNER JOIN (
                SELECT employee_id, MAX(processed_at) as max_processed
                FROM payroll_records
                GROUP BY employee_id
            ) p2 ON p1.employee_id = p2.employee_id AND p1.processed_at = p2.max_processed
        ) p ON e.id = p.employee_id
        ORDER BY e.id DESC
    ");
    return $stmt->fetchAll();
}
?>
