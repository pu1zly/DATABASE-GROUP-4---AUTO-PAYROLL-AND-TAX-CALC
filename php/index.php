<?php
// index.php - Enhanced Payroll Management Portal
require_once 'db.php';

$message = "";
$edit_mode = false;
$edit_employee = null;

// Handle Edit Request (Load Employee Data)
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $edit_employee = getEmployee($pdo, $_GET['edit']);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_employee'])) {
        if (addEmployee($pdo, $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['hourly_rate'], $_POST['tax_rate'])) {
            $message = "Employee added successfully!";
        }
    } elseif (isset($_POST['update_employee'])) {
        if (updateEmployee($pdo, $_POST['id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['hourly_rate'], $_POST['tax_rate'])) {
            $message = "Employee updated successfully!";
            header("Location: index.php"); // Refresh to clear edit mode
            exit;
        }
    } elseif (isset($_POST['log_shift'])) {
        if (logShift($pdo, $_POST['employee_id'], $_POST['clock_in'], $_POST['clock_out'])) {
            $message = "Shift logged successfully!";
        }
    } elseif (isset($_POST['add_deduction'])) {
        if (addDeduction($pdo, $_POST['employee_id'], $_POST['description'], $_POST['amount'])) {
            $message = "Deduction added successfully!";
        }
    }
}

$employees = getAllEmployees($pdo);
$deduction_types = ["SSS Contribution", "PhilHealth", "Pag-IBIG Fund", "Health Insurance", "Loan Payment", "Tax Adjustment", "Other"];

// Handle Shift Filter
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$shifts_on_date = getShiftsByDate($pdo, $filter_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll & Tax Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Payroll & Tax Management Portal</h1>
        
        <?php if ($message): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="modular-sections">
            <!-- Add/Edit Employee Section -->
            <section id="employee-form" class="card">
                <h2><?php echo $edit_mode ? 'Update Employee' : 'Add Employee'; ?></h2>
                <form method="POST">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_employee['id']; ?>">
                    <?php endif; ?>
                    <input type="text" name="first_name" placeholder="First Name" value="<?php echo $edit_mode ? $edit_employee['first_name'] : ''; ?>" required>
                    <input type="text" name="last_name" placeholder="Last Name" value="<?php echo $edit_mode ? $edit_employee['last_name'] : ''; ?>" required>
                    <input type="email" name="email" placeholder="Email" value="<?php echo $edit_mode ? $edit_employee['email'] : ''; ?>" required>
                    <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" placeholder="Hourly Rate" value="<?php echo $edit_mode ? $edit_employee['hourly_rate'] : ''; ?>" required>
                    <input type="number" step="0.01" name="tax_rate" id="tax_rate" placeholder="Tax Rate (%)" value="<?php echo $edit_mode ? $edit_employee['tax_rate'] : ''; ?>" required>
                    
                    <div class="calculation-preview">
                        <strong>Est. Net Hourly: </strong>
                        <span id="net_hourly_preview">$0.00</span>
                    </div>

                    <button type="submit" name="<?php echo $edit_mode ? 'update_employee' : 'add_employee'; ?>">
                        <?php echo $edit_mode ? 'Update Employee' : 'Add Employee'; ?>
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="index.php" class="btn-cancel">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Log Shift Section -->
            <section id="log-shift" class="card">
                <h2>Log Shift</h2>
                <form method="POST">
                    <select name="employee_id" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Clock In:</label>
                    <input type="datetime-local" name="clock_in" required>
                    <label>Clock Out:</label>
                    <input type="datetime-local" name="clock_out" required>
                    <button type="submit" name="log_shift">Log Shift</button>
                </form>
            </section>

            <!-- Add Deduction Section -->
            <section id="add-deduction" class="card">
                <h2>Add Deduction</h2>
                <form method="POST">
                    <select name="employee_id" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Deduction Type:</label>
                    <select name="description" required>
                        <option value="">Select Type</option>
                        <?php foreach ($deduction_types as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" name="amount" placeholder="Amount" required>
                    <button type="submit" name="add_deduction">Add Deduction</button>
                </form>
            </section>
        </div>

        <!-- Daily Shift Report -->
        <section id="shift-report" class="card">
            <h2>Daily Shift Report</h2>
            <form method="GET" class="filter-form">
                <label>View Shifts for Date:</label>
                <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
            </form>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Total Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts_on_date)): ?>
                        <tr><td colspan="5" style="text-align:center;">No shifts recorded for this day.</td></tr>
                    <?php else: ?>
                        <?php foreach ($shifts_on_date as $shift): ?>
                        <tr>
                            <td><?php echo $shift['first_name'] . ' ' . $shift['last_name']; ?></td>
                            <td><?php echo date('h:i A', strtotime($shift['clock_in'])); ?></td>
                            <td><?php echo $shift['clock_out'] ? date('h:i A', strtotime($shift['clock_out'])) : 'Still In'; ?></td>
                            <td><?php echo number_format($shift['total_hours'], 2); ?> hrs</td>
                            <td><span class="badge <?php echo $shift['status']; ?>"><?php echo ucfirst($shift['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Employee List Table -->
        <section id="employee-list" class="card">
            <h2>Employee List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Hourly Rate</th>
                        <th>Tax Rate</th>
                        <th>Net Hourly Income</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <?php 
                        $net_hourly = $emp['hourly_rate'] - ($emp['hourly_rate'] * ($emp['tax_rate'] / 100));
                    ?>
                    <tr>
                        <td><?php echo $emp['id']; ?></td>
                        <td><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></td>
                        <td>$<?php echo number_format($emp['hourly_rate'], 2); ?></td>
                        <td><?php echo $emp['tax_rate']; ?>%</td>
                        <td class="net-income">$<?php echo number_format($net_hourly, 2); ?></td>
                        <td>
                            <a href="?edit=<?php echo $emp['id']; ?>" class="btn-edit">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <script src="script.js"></script>
</body>
</html>
