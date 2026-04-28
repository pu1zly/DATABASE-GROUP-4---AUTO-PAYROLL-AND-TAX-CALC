<?php
// phase1.php - Employee Directory, Configuration, and Management
session_start();
require_once 'db.php';

if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = "";
$message_type = "";
$editing_employee = null;
$edit_id = null;

// Handle POST actions: deactivate, reactivate, delete, configure, bulk_import

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'deactivate' && isset($_POST['employee_id'])) {
        $emp_id = (int)$_POST['employee_id'];
        if (deactivateEmployee($pdo, $emp_id)) {
            $message = "Employee deactivated successfully.";
            $message_type = "success";
        } else {
            $message = "Error deactivating employee.";
            $message_type = "error";
        }
    }
    elseif ($action == 'reactivate' && isset($_POST['employee_id'])) {
        $emp_id = (int)$_POST['employee_id'];
        if (reactivateEmployee($pdo, $emp_id)) {
            $message = "Employee reactivated successfully.";
            $message_type = "success";
        } else {
            $message = "Error reactivating employee.";
            $message_type = "error";
        }
    }
    elseif ($action == 'delete' && isset($_POST['employee_id'])) {
        $emp_id = (int)$_POST['employee_id'];
        if (deleteEmployee($pdo, $emp_id)) {
            $message = "Employee permanently deleted.";
            $message_type = "success";
        } else {
            $message = "Error deleting employee.";
            $message_type = "error";
        }
    }
    elseif ($action == 'configure' && isset($_POST['configure_employee'])) {
        $edit_id      = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $id_code      = $_POST['employee_id_code'];
        $name         = $_POST['full_name'];
        $position     = $_POST['position'];
        $hourly_rate  = $_POST['hourly_rate'];
        $tax_rate     = ($position == 'Custom') ? $_POST['custom_tax_rate'] : $_POST['tax_rate'];

        if ($edit_id) {
            if (updateEmployee($pdo, $edit_id, $id_code, $name, $position, $hourly_rate, $tax_rate)) {
                $message = "Employee <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
                $message_type = "success";
                $edit_id = null;
            } else {
                $message = "Error updating employee.";
                $message_type = "error";
            }
        } else {
            if (configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate)) {
                $message = "Employee <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding employee.";
                $message_type = "error";
            }
        }
    }
    elseif ($action == 'bulk_import') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $result = bulkImportEmployees($pdo, $tmpPath);
            $message = "Bulk import complete: {$result['imported']} added, {$result['skipped']} skipped.";
            $message_type = ($result['skipped'] > 0 || !empty($result['errors'])) ? 'warning' : 'success';
            if (!empty($result['errors'])) {
                $message .= " Errors: " . implode('; ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) $message .= ' ...';
            }
        } else {
            $message = "No file uploaded or upload error.";
            $message_type = "error";
        }
    }
}

// If editing, fetch the employee data
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editing_employee = getEmployeeById($pdo, $edit_id);
    if (!$editing_employee) {
        header('Location: phase1.php');
        exit;
    }
}

$employees = getAllEmployeesIncludingInactive($pdo);
$active_employees = getEmployees($pdo);

// Extract inactive employees
$inactive_employees = array_filter($employees, function($emp) { 
    return $emp['is_active'] == 0; 
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Directory — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .btn-reactivate {
            border-color: #f59e0b;
            color: #f59e0b;
        }
        .btn-reactivate:hover {
            background: #fffbeb;
            border-color: #d97706;
        }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Employee Directory</h1>
            <p>Add, edit, manage, or bulk import employee profiles</p>
        </header>

        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="split-view">
            <!-- Add / Edit Employee Form -->
            <section class="card">
                <h2><?php echo $editing_employee ? "Edit Employee" : "Add New Employee"; ?></h2>
                <form method="POST" id="config-form">
                    <input type="hidden" name="action" value="configure">
                    <?php if ($editing_employee): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editing_employee['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>ID Code</label>
                        <input type="text" name="employee_id_code" placeholder="e.g. EMP-001" value="<?php echo htmlspecialchars($editing_employee['employee_id_code'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="e.g. Juan dela Cruz" value="<?php echo htmlspecialchars($editing_employee['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hourly Rate ($)</label>
                        <input type="number" step="0.01" name="hourly_rate" placeholder="25.00" value="<?php echo htmlspecialchars($editing_employee['hourly_rate'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select name="position" id="position-select" required>
                            <option value="">Select Position</option>
                            <option value="Intern" data-tax="0" <?php echo ($editing_employee && $editing_employee['position'] == 'Intern') ? 'selected' : ''; ?>>Intern — 0% Tax</option>
                            <option value="Contractor" data-tax="10" <?php echo ($editing_employee && $editing_employee['position'] == 'Contractor') ? 'selected' : ''; ?>>Contractor — 10% Tax</option>
                            <option value="Regular Staff" data-tax="20" <?php echo ($editing_employee && $editing_employee['position'] == 'Regular Staff') ? 'selected' : ''; ?>>Regular Staff — 20% Tax</option>
                            <option value="Manager" data-tax="30" <?php echo ($editing_employee && $editing_employee['position'] == 'Manager') ? 'selected' : ''; ?>>Manager — 30% Tax</option>
                            <option value="Custom" data-tax="" <?php echo ($editing_employee && $editing_employee['position'] == 'Custom') ? 'selected' : ''; ?>>Custom — Manual Entry</option>
                        </select>
                    </div>
                    <input type="hidden" name="tax_rate" id="tax_rate_hidden" value="<?php echo htmlspecialchars($editing_employee['tax_rate'] ?? ''); ?>">
                    <div id="custom-tax-container" class="form-group" style="display:<?php echo ($editing_employee && $editing_employee['position'] == 'Custom') ? 'block' : 'none'; ?>;">
                        <label>Custom Tax Rate (%)</label>
                        <input type="number" step="0.01" name="custom_tax_rate" placeholder="e.g. 15" value="<?php echo ($editing_employee && $editing_employee['position'] == 'Custom') ? htmlspecialchars($editing_employee['tax_rate']) : ''; ?>">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="configure_employee" class="btn-primary"><?php echo $editing_employee ? "Update Employee" : "Save Employee"; ?></button>
                        <?php if ($editing_employee): ?>
                            <a href="phase1.php" class="btn-secondary" style="padding: 10px 20px; text-decoration: none; text-align: center;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <!-- Active Employees List -->
            <section class="card">
                <h2>Active Employees (<?php echo count($active_employees); ?>)</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Rate/hr</th>
                                <th>Tax</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_employees)): ?>
                                <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No active employees.</td></tr>
                            <?php else: ?>
                                <?php foreach ($active_employees as $emp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($emp['employee_id_code']); ?></small>
                                    </td>
                                    <td><span class="badge"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                                    <td class="mono">$<?php echo number_format($emp['hourly_rate'], 2); ?></td>
                                    <td class="mono"><?php echo $emp['tax_rate']; ?>%</td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="phase1.php?edit=<?php echo $emp['id']; ?>" class="btn-small btn-edit">✏️ Edit</a>
                                            <a href="employee_history.php?id=<?php echo $emp['id']; ?>" class="btn-small btn-view">👁️ History</a>
                                            <button type="button" class="btn-small btn-delete" onclick="confirmDeactivate(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['full_name']); ?>')">🗑️ Deactivate</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Inactive Employees Section -->
                <?php if (!empty($inactive_employees)): ?>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <h3>Inactive Employees (<?php echo count($inactive_employees); ?>)</h3>
                        <table style="margin-top: 15px; opacity: 0.85;">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Rate/hr</th>
                                    <th>Tax</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inactive_employees as $emp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($emp['employee_id_code']); ?></small>
                                    </td>
                                    <td><span class="badge"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                                    <td class="mono">$<?php echo number_format($emp['hourly_rate'], 2); ?></td>
                                    <td class="mono"><?php echo $emp['tax_rate']; ?>%</td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="btn-small btn-reactivate" onclick="confirmReactivate(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['full_name']); ?>')">🔄 Reactivate</button>
                                            <button type="button" class="btn-small btn-delete" onclick="confirmDelete(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['full_name']); ?>')">🗑️ Delete Permanently</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Bulk Import Card -->
        <section class="card" style="margin-top: 24px;">
            <h2>Bulk Import Employees (CSV)</h2>
            <p style="color: var(--text-muted); font-size: .9rem; margin-bottom: 16px;">
                Upload a CSV file with columns: <code>employee_id_code, full_name, position, hourly_rate, tax_rate</code>.
                First row must contain the header.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="bulk_import">
                <div class="form-group">
                    <label>Select CSV file</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" class="btn-primary" style="width: auto; min-width: 200px;">Upload & Import</button>
            </form>
            <div style="margin-top: 12px; font-size: .85rem; color: var(--text-muted);">
                Example row: <code>EMP-101,John Doe,Regular Staff,25.00,20</code><br>
                Positions: Intern, Contractor, Regular Staff, Manager, Custom
            </div>
        </section>
    </main>

    <script>
        function initPositionSelect() {
            const select = document.getElementById('position-select');
            const hidden = document.getElementById('tax_rate_hidden');
            const customContainer = document.getElementById('custom-tax-container');
            
            function updateTaxDisplay() {
                const selected = select.options[select.selectedIndex];
                const taxRate = selected.getAttribute('data-tax');
                
                if (selected.value === 'Custom') {
                    customContainer.style.display = 'block';
                } else {
                    customContainer.style.display = 'none';
                    hidden.value = taxRate;
                }
            }
            
            select.addEventListener('change', updateTaxDisplay);
            updateTaxDisplay();
        }

        function confirmDeactivate(empId, empName) {
            if (confirm(`Are you sure you want to deactivate ${empName}?`)) {
                submitAction('deactivate', empId);
            }
        }

        function confirmReactivate(empId, empName) {
            if (confirm(`Reactivate ${empName}?`)) {
                submitAction('reactivate', empId);
            }
        }

        function confirmDelete(empId, empName) {
            if (confirm(`Permanently delete ${empName} and ALL their records?\nThis cannot be undone.`)) {
                submitAction('delete', empId);
            }
        }

        function submitAction(action, empId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="employee_id" value="${empId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', initPositionSelect);
    </script>
    <script src="script.js"></script>
</body>
</html>