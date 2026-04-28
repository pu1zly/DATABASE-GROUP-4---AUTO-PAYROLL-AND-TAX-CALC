<?php
// db.php - Database Logic
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "payroll_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Phase 1: Add employee (duplicate check)
function configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id_code = ?");
    $stmt->execute([$id_code]);
    if ($stmt->fetchColumn() > 0) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO employees (employee_id_code, full_name, position, hourly_rate, tax_rate) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$id_code, $name, $position, $hourly_rate, $tax_rate]);
}

// Phase 2: Save daily log
function saveDailyLog($pdo, $employee_id, $date, $reg, $ot, $sick, $vac, $is_day_off) {
    $stmt = $pdo->prepare("INSERT INTO daily_logs (employee_id, log_date, reg_hours, ot_hours, sick_hours, vac_hours, is_day_off) 
                           VALUES (?, ?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                               reg_hours   = VALUES(reg_hours), 
                               ot_hours    = VALUES(ot_hours), 
                               sick_hours  = VALUES(sick_hours), 
                               vac_hours   = VALUES(vac_hours), 
                               is_day_off  = VALUES(is_day_off)");
    return $stmt->execute([$employee_id, $date, $reg, $ot, $sick, $vac, $is_day_off]);
}

// Phase 3 & 4: Calculate and store payroll record
function processPayrollRecord($pdo, $employee_id, $month_year, $reg, $ot, $sick, $vac) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp) return false;

    $total_hours        = $reg + $ot + $sick + $vac;
    $gross_income       = $total_hours * $emp['hourly_rate'];
    $sss                = min($gross_income * 0.05, 25.00);
    $philhealth         = $gross_income * 0.025;
    $pagibig            = 4.00;
    $total_deductions   = $sss + $philhealth + $pagibig;
    $taxable_income     = $gross_income - $total_deductions;
    $total_tax_withheld = $taxable_income * ($emp['tax_rate'] / 100);
    $net_income         = $taxable_income - $total_tax_withheld;

    $stmt = $pdo->prepare("INSERT INTO payroll_records 
        (employee_id, month_year, gross_income, sss_deduction, philhealth_deduction, pagibig_deduction, taxable_income, total_tax_withheld, net_income) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$employee_id, $month_year, $gross_income, $sss, $philhealth, $pagibig, $taxable_income, $total_tax_withheld, $net_income]);
}

// Fetch all active employees
function getEmployees($pdo) {
    return $pdo->query("SELECT * FROM employees WHERE is_active = TRUE ORDER BY id DESC")->fetchAll();
}

// Fetch all employees including inactive
function getAllEmployeesIncludingInactive($pdo) {
    return $pdo->query("SELECT * FROM employees ORDER BY id DESC")->fetchAll();
}

// Get single employee by ID
function getEmployeeById($pdo, $employee_id) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    return $stmt->fetch();
}

// Update employee (duplicate check, no position restriction)
function updateEmployee($pdo, $employee_id, $id_code, $name, $position, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id_code = ? AND id != ?");
    $stmt->execute([$id_code, $employee_id]);
    if ($stmt->fetchColumn() > 0) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE employees SET employee_id_code=?, full_name=?, position=?, hourly_rate=?, tax_rate=? WHERE id=?");
    return $stmt->execute([$id_code, $name, $position, $hourly_rate, $tax_rate, $employee_id]);
}

// Soft-delete
function deactivateEmployee($pdo, $employee_id) {
    $stmt = $pdo->prepare("UPDATE employees SET is_active = FALSE WHERE id = ?");
    return $stmt->execute([$employee_id]);
}

// Reactivate
function reactivateEmployee($pdo, $employee_id) {
    $stmt = $pdo->prepare("UPDATE employees SET is_active = TRUE WHERE id = ?");
    return $stmt->execute([$employee_id]);
}

// Permanent delete (cascade)
function deleteEmployee($pdo, $employee_id) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    return $stmt->execute([$employee_id]);
}

// Payroll history
function getEmployeePayrollHistory($pdo, $employee_id) {
    $stmt = $pdo->prepare("SELECT * FROM payroll_records WHERE employee_id = ? ORDER BY month_year DESC, processed_at DESC");
    $stmt->execute([$employee_id]);
    return $stmt->fetchAll();
}

// Check if payroll record exists for employee+month
function payrollRecordExists($pdo, $employee_id, $month_year) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM payroll_records WHERE employee_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?");
    $stmt->execute([$employee_id, $month_year]);
    return (int)$stmt->fetch()['cnt'] > 0;
}

// Get daily logs keyed by day (1-30)
function getDailyLogsByMonth($pdo, $employee_id, $month_year) {
    $stmt = $pdo->prepare("SELECT DAY(log_date) as day_num, reg_hours, ot_hours, sick_hours, vac_hours, is_day_off FROM daily_logs WHERE employee_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ? ORDER BY log_date ASC");
    $stmt->execute([$employee_id, $month_year]);
    $rows = $stmt->fetchAll();
    $keyed = [];
    foreach ($rows as $row) $keyed[(int)$row['day_num']] = $row;
    return $keyed;
}

// Update existing payroll record (for edit)
function updatePayrollRecord($pdo, $employee_id, $month_year, $reg, $ot, $sick, $vac) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp) return false;

    $total_hours    = $reg + $ot + $sick + $vac;
    $gross_income   = $total_hours * $emp['hourly_rate'];
    $sss            = min($gross_income * 0.05, 25.00);
    $philhealth     = $gross_income * 0.025;
    $pagibig        = 4.00;
    $taxable_income = $gross_income - ($sss + $philhealth + $pagibig);
    $tax            = $taxable_income * ($emp['tax_rate'] / 100);
    $net            = $taxable_income - $tax;

    $stmt = $pdo->prepare("UPDATE payroll_records SET gross_income=?, sss_deduction=?, philhealth_deduction=?, pagibig_deduction=?, taxable_income=?, total_tax_withheld=?, net_income=?, processed_at=NOW() WHERE employee_id=? AND DATE_FORMAT(month_year, '%Y-%m')=? ORDER BY processed_at DESC LIMIT 1");
    return $stmt->execute([$gross_income, $sss, $philhealth, $pagibig, $taxable_income, $tax, $net, $employee_id, $month_year]);
}

// Dashboard helpers
function getEmployeesWithLatestPayroll($pdo) {
    return $pdo->query("
        SELECT e.id AS emp_id, e.full_name, e.employee_id_code, e.position,
               p.month_year, p.gross_income, p.sss_deduction, p.philhealth_deduction,
               p.pagibig_deduction, p.taxable_income, p.total_tax_withheld, p.net_income
        FROM employees e
        LEFT JOIN (
            SELECT p1.* FROM payroll_records p1
            INNER JOIN (SELECT employee_id, MAX(processed_at) AS max_processed FROM payroll_records GROUP BY employee_id) p2
            ON p1.employee_id = p2.employee_id AND p1.processed_at = p2.max_processed
        ) p ON e.id = p.employee_id
        ORDER BY e.id DESC
    ")->fetchAll();
}
function getEmployeesWithPayrollByMonth($pdo, $month_year) {
    $month_start = $month_year . '-01';
    $stmt = $pdo->prepare("
        SELECT e.id AS emp_id, e.full_name, e.employee_id_code, e.position,
               p.month_year, p.gross_income, p.sss_deduction, p.philhealth_deduction,
               p.pagibig_deduction, p.taxable_income, p.total_tax_withheld, p.net_income
        FROM employees e
        INNER JOIN (
            SELECT p1.* FROM payroll_records p1
            INNER JOIN (SELECT employee_id, MAX(processed_at) AS max_processed FROM payroll_records WHERE DATE_FORMAT(month_year, '%Y-%m') = DATE_FORMAT(?, '%Y-%m') GROUP BY employee_id) p2
            ON p1.employee_id = p2.employee_id AND p1.processed_at = p2.max_processed
        ) p ON e.id = p.employee_id
        ORDER BY e.full_name ASC
    ");
    $stmt->execute([$month_start]);
    return $stmt->fetchAll();
}

// Bulk import (now accepts any position string)
function bulkImportEmployees($pdo, $filePath) {
    $imported = 0;
    $skipped = 0;
    $errors = [];
    if (($handle = fopen($filePath, 'r')) === false) {
        return ['imported'=>0, 'skipped'=>0, 'errors'=>['Could not open file.']];
    }
    $header = fgetcsv($handle);
    if (!$header || count($header) < 5) {
        fclose($handle);
        return ['imported'=>0, 'skipped'=>0, 'errors'=>['CSV must have at least 5 columns: employee_id_code, full_name, position, hourly_rate, tax_rate']];
    }
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);
    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count($row) < 5) continue;
        $data = @array_combine($header, $row);
        if ($data === false) { $errors[] = "Row $rowNum: unable to parse."; $skipped++; continue; }
        $id_code    = trim($data['employee_id_code'] ?? '');
        $full_name  = trim($data['full_name'] ?? '');
        $position   = trim($data['position'] ?? '');
        $hourly_rate = trim($data['hourly_rate'] ?? '');
        $tax_rate    = trim($data['tax_rate'] ?? '');
        if (empty($id_code) || empty($full_name) || $position === '' || $hourly_rate === '' || $tax_rate === '') {
            $errors[] = "Row $rowNum: missing required fields."; $skipped++; continue;
        }
        if (!is_numeric($hourly_rate) || floatval($hourly_rate) < 0) {
            $errors[] = "Row $rowNum: invalid hourly rate."; $skipped++; continue;
        }
        if (!is_numeric($tax_rate) || floatval($tax_rate) < 0 || floatval($tax_rate) > 100) {
            $errors[] = "Row $rowNum: invalid tax rate (must be 0-100)."; $skipped++; continue;
        }
        // Check duplicate id_code
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id_code = ?");
        $stmt->execute([$id_code]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Row $rowNum: employee_id_code '{$id_code}' already exists."; $skipped++; continue;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO employees (employee_id_code, full_name, position, hourly_rate, tax_rate, is_active) VALUES (?, ?, ?, ?, ?, TRUE)");
            $stmt->execute([$id_code, $full_name, $position, $hourly_rate, $tax_rate]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Row $rowNum: database error - " . $e->getMessage();
            $skipped++;
        }
    }
    fclose($handle);
    return ['imported'=>$imported, 'skipped'=>$skipped, 'errors'=>$errors];
}

// --- Authentication functions (unchanged) ---
function checkCredentialsExists($pdo, $username, $email) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
    $stmt->execute([$username]); $username_exists = (int)$stmt->fetch()['cnt'] > 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE email = ?");
    $stmt->execute([$email]); $email_exists = (int)$stmt->fetch()['cnt'] > 0;
    return ['username_exists'=>$username_exists, 'email_exists'=>$email_exists];
}
function registerUser($pdo, $username, $email, $password, $full_name='', $role='staff') {
    $exists = checkCredentialsExists($pdo, $username, $email);
    if ($exists['username_exists']) return ['success'=>false, 'message'=>'Username already exists.'];
    if ($exists['email_exists'])    return ['success'=>false, 'message'=>'Email already registered.'];
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, TRUE)");
        $stmt->execute([$username, $email, $password_hash, $full_name, $role]);
        return ['success'=>true, 'message'=>'Registration successful!'];
    } catch (PDOException $e) { return ['success'=>false, 'message'=>'Registration failed.']; }
}
function authenticateUser($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=TRUE");
        $stmt->execute([$username]); $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success'=>false, 'message'=>'Invalid username or password.'];
        }
        $stmt = $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?");
        $stmt->execute([$user['id']]);
        return ['success'=>true, 'user'=>['id'=>$user['id'], 'username'=>$user['username'], 'email'=>$user['email'], 'full_name'=>$user['full_name'], 'role'=>$user['role']]];
    } catch (PDOException $e) { return ['success'=>false, 'message'=>'Login failed.']; }
}
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, is_active FROM users WHERE id=?");
    $stmt->execute([$user_id]); return $stmt->fetch();
}
function isUserLoggedIn() { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
function getCurrentUser() { return isUserLoggedIn() ? $_SESSION['user'] : null; }
?>