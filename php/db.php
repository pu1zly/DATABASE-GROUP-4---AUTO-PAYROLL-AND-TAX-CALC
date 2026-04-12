<?php
// db.php - Modular Database Connection
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

// Helper for fetching all employees
function getAllEmployees($pdo) {
    $stmt = $pdo->query("SELECT * FROM employees");
    return $stmt->fetchAll();
}

// Helper for adding an employee
function addEmployee($pdo, $first_name, $last_name, $email, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, email, hourly_rate, tax_rate) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$first_name, $last_name, $email, $hourly_rate, $tax_rate]);
}

// Helper for logging a shift
function logShift($pdo, $employee_id, $clock_in, $clock_out) {
    $start = new DateTime($clock_in);
    $end = new DateTime($clock_out);
    $interval = $start->diff($end);
    $total_hours = $interval->h + ($interval->i / 60) + ($interval->days * 24);
    
    $stmt = $pdo->prepare("INSERT INTO shifts (employee_id, clock_in, clock_out, total_hours) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$employee_id, $clock_in, $clock_out, $total_hours]);
}

// Helper for adding a deduction
function addDeduction($pdo, $employee_id, $description, $amount) {
    $stmt = $pdo->prepare("INSERT INTO deductions (employee_id, description, amount) VALUES (?, ?, ?)");
    return $stmt->execute([$employee_id, $description, $amount]);
}

// Helper for updating an employee
function updateEmployee($pdo, $id, $first_name, $last_name, $email, $hourly_rate, $tax_rate) {
    $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, hourly_rate = ?, tax_rate = ? WHERE id = ?");
    return $stmt->execute([$first_name, $last_name, $email, $hourly_rate, $tax_rate, $id]);
}

// Helper for getting a single employee
function getEmployee($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Helper for getting shifts by date
function getShiftsByDate($pdo, $date) {
    $stmt = $pdo->prepare("SELECT s.*, e.first_name, e.last_name 
                           FROM shifts s 
                           JOIN employees e ON s.employee_id = e.id 
                           WHERE DATE(s.clock_in) = ? 
                           ORDER BY s.clock_in ASC");
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}
?>
