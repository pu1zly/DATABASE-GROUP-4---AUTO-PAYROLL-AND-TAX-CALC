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

// Phase 1: Add or update employee
function configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("INSERT INTO employees (employee_id_code, full_name, position, hourly_rate, tax_rate) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$id_code, $name, $position, $hourly_rate, $tax_rate]);
}

// Phase 2: Save a single day's log
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
    // Get employee info
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp) return false;

    // Calculation waterfall
    $total_hours        = $reg + $ot + $sick + $vac;
    $gross_income       = $total_hours * $emp['hourly_rate'];
    $sss                = min($gross_income * 0.05, 25.00);   // 5%, capped at $25
    $philhealth         = $gross_income * 0.025;              // 2.5%
    $pagibig            = 4.00;                               // Flat $4
    $total_deductions   = $sss + $philhealth + $pagibig;
    $taxable_income     = $gross_income - $total_deductions;
    $total_tax_withheld = $taxable_income * ($emp['tax_rate'] / 100);
    $net_income         = $taxable_income - $total_tax_withheld;

    $stmt = $pdo->prepare("INSERT INTO payroll_records 
        (employee_id, month_year, gross_income, sss_deduction, philhealth_deduction, pagibig_deduction, taxable_income, total_tax_withheld, net_income) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$employee_id, $month_year, $gross_income, $sss, $philhealth, $pagibig, $taxable_income, $total_tax_withheld, $net_income]);
}

// Fetch all employees
function getEmployees($pdo) {
    return $pdo->query("SELECT * FROM employees ORDER BY id DESC")->fetchAll();
}

// Check if a payroll record already exists for employee+month
function payrollRecordExists($pdo, $employee_id, $month_year) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM payroll_records 
        WHERE employee_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?
    ");
    $stmt->execute([$employee_id, $month_year]);
    return (int)$stmt->fetch()['cnt'] > 0;
}

// Fetch daily logs for an employee+month, keyed by day number (1-30)
function getDailyLogsByMonth($pdo, $employee_id, $month_year) {
    $stmt = $pdo->prepare("
        SELECT DAY(log_date) as day_num, reg_hours, ot_hours, sick_hours, vac_hours, is_day_off
        FROM daily_logs
        WHERE employee_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ?
        ORDER BY log_date ASC
    ");
    $stmt->execute([$employee_id, $month_year]);
    $rows = $stmt->fetchAll();
    // Key by day number for easy lookup
    $keyed = [];
    foreach ($rows as $row) {
        $keyed[(int)$row['day_num']] = $row;
    }
    return $keyed;
}

// Update payroll record in place (for edits)
function updatePayrollRecord($pdo, $employee_id, $month_year, $reg, $ot, $sick, $vac) {
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

    // Update the most recent payroll record for this employee+month
    $stmt = $pdo->prepare("
        UPDATE payroll_records 
        SET gross_income = ?, sss_deduction = ?, philhealth_deduction = ?,
            pagibig_deduction = ?, taxable_income = ?, total_tax_withheld = ?,
            net_income = ?, processed_at = NOW()
        WHERE employee_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?
        ORDER BY processed_at DESC LIMIT 1
    ");
    return $stmt->execute([
        $gross_income, $sss, $philhealth, $pagibig,
        $taxable_income, $total_tax_withheld, $net_income,
        $employee_id, $month_year
    ]);
}

// Dashboard: fetch employees with their LATEST payroll record (original behavior)
function getEmployeesWithLatestPayroll($pdo) {
    $stmt = $pdo->query("
        SELECT 
            e.id AS emp_id, e.full_name, e.employee_id_code, e.position,
            p.month_year, p.gross_income, p.sss_deduction, p.philhealth_deduction,
            p.pagibig_deduction, p.taxable_income, p.total_tax_withheld, p.net_income
        FROM employees e
        LEFT JOIN (
            SELECT p1.*
            FROM payroll_records p1
            INNER JOIN (
                SELECT employee_id, MAX(processed_at) AS max_processed
                FROM payroll_records
                GROUP BY employee_id
            ) p2 ON p1.employee_id = p2.employee_id AND p1.processed_at = p2.max_processed
        ) p ON e.id = p.employee_id
        ORDER BY e.id DESC
    ");
    return $stmt->fetchAll();
}

// Dashboard: fetch employees who have payroll records for a specific month (YYYY-MM)
function getEmployeesWithPayrollByMonth($pdo, $month_year) {
    // month_year is stored as a DATE (YYYY-MM-01) in payroll_records
    $month_start = $month_year . '-01';

    $stmt = $pdo->prepare("
        SELECT 
            e.id AS emp_id, e.full_name, e.employee_id_code, e.position,
            p.month_year, p.gross_income, p.sss_deduction, p.philhealth_deduction,
            p.pagibig_deduction, p.taxable_income, p.total_tax_withheld, p.net_income
        FROM employees e
        INNER JOIN (
            SELECT p1.*
            FROM payroll_records p1
            INNER JOIN (
                SELECT employee_id, MAX(processed_at) AS max_processed
                FROM payroll_records
                WHERE DATE_FORMAT(month_year, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
                GROUP BY employee_id
            ) p2 ON p1.employee_id = p2.employee_id AND p1.processed_at = p2.max_processed
        ) p ON e.id = p.employee_id
        ORDER BY e.full_name ASC
    ");
    $stmt->execute([$month_start]);
    return $stmt->fetchAll();
}

// ============================================================
// AUTHENTICATION FUNCTIONS (User Registration & Login)
// ============================================================

/**
 * Check if username or email already exists
 * Returns array with 'username_exists' and 'email_exists' booleans
 */
function checkCredentialsExists($pdo, $username, $email) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $username_exists = (int)$stmt->fetch()['cnt'] > 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $email_exists = (int)$stmt->fetch()['cnt'] > 0;
    
    return [
        'username_exists' => $username_exists,
        'email_exists' => $email_exists
    ];
}

/**
 * Register a new user
 * Returns array with 'success' status and 'message'
 */
function registerUser($pdo, $username, $email, $password, $full_name = '', $role = 'staff') {
    // Check for duplicate credentials
    $exists = checkCredentialsExists($pdo, $username, $email);
    
    if ($exists['username_exists']) {
        return [
            'success' => false,
            'message' => 'Username already exists. Please choose a different username.'
        ];
    }
    
    if ($exists['email_exists']) {
        return [
            'success' => false,
            'message' => 'Email already registered. Please use a different email or login instead.'
        ];
    }
    
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, is_active) 
                              VALUES (?, ?, ?, ?, ?, TRUE)");
        $stmt->execute([$username, $email, $password_hash, $full_name, $role]);
        
        return [
            'success' => true,
            'message' => 'Registration successful! You can now login.'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again later.'
        ];
    }
}

/**
 * Authenticate user (login)
 * Returns array with 'success' status and 'user' data (if successful)
 */
function authenticateUser($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Login failed. Please try again later.'
        ];
    }
}

/**
 * Get user by ID
 */
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    if (isUserLoggedIn()) {
        return $_SESSION['user'];
    }
    return null;
}
?>
