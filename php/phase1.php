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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Single employee actions
    if (in_array($action, ['deactivate','reactivate','delete']) && isset($_POST['employee_id'])) {
        $emp_id = (int)$_POST['employee_id'];
        switch ($action) {
            case 'deactivate':
                if (deactivateEmployee($pdo, $emp_id)) {
                    $message = "Employee deactivated successfully."; $message_type = "success";
                } else {
                    $message = "Error deactivating employee."; $message_type = "error";
                }
                break;
            case 'reactivate':
                if (reactivateEmployee($pdo, $emp_id)) {
                    $message = "Employee reactivated successfully."; $message_type = "success";
                } else {
                    $message = "Error reactivating employee."; $message_type = "error";
                }
                break;
            case 'delete':
                if (deleteEmployee($pdo, $emp_id)) {
                    $message = "Employee permanently deleted."; $message_type = "success";
                } else {
                    $message = "Error deleting employee."; $message_type = "error";
                }
                break;
        }
    }
    // Bulk actions
    elseif (in_array($action, ['bulk_deactivate','bulk_reactivate','bulk_delete']) && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $ids = array_map('intval', $_POST['selected_ids']);
        $count = 0;
        foreach ($ids as $id) {
            $ok = false;
            switch ($action) {
                case 'bulk_deactivate': $ok = deactivateEmployee($pdo, $id); break;
                case 'bulk_reactivate': $ok = reactivateEmployee($pdo, $id); break;
                case 'bulk_delete':     $ok = deleteEmployee($pdo, $id); break;
            }
            if ($ok) $count++;
        }
        $verb = ['bulk_deactivate'=>'deactivated','bulk_reactivate'=>'reactivated','bulk_delete'=>'deleted'][$action];
        $message = "$count employee(s) $verb successfully.";
        $message_type = "success";
    }
    // Configure employee (add/edit)
    elseif ($action == 'configure' && isset($_POST['configure_employee'])) {
        $edit_id      = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $id_code      = $_POST['employee_id_code'];
        $name         = $_POST['full_name'];
        $position     = $_POST['position'];
        $custom_pos   = trim($_POST['custom_position'] ?? '');

        // If "Custom" is chosen, use the custom position – and enforce it not empty
        if ($position === 'Custom') {
            if (empty($custom_pos)) {
                $message = "Custom position title cannot be empty.";
                $message_type = "error";
                // preserve form values for re-display
                $editing_employee = $edit_id ? getEmployeeById($pdo, $edit_id) : null;
                goto skip_configure;
            }
            $position = $custom_pos;
        }

        $hourly_rate  = $_POST['hourly_rate'];
        $tax_rate     = ($_POST['position'] === 'Custom') ? $_POST['custom_tax_rate'] : $_POST['tax_rate'];

        if ($edit_id) {
            if (updateEmployee($pdo, $edit_id, $id_code, $name, $position, $hourly_rate, $tax_rate)) {
                $message = "Employee <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
                $message_type = "success"; $edit_id = null;
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id_code = ? AND id != ?");
                $stmt->execute([$id_code, $edit_id]);
                if ($stmt->fetchColumn() > 0) {
                    $message = "Update failed: Employee ID Code <strong>" . htmlspecialchars($id_code) . "</strong> is already used by another employee.";
                } else {
                    $message = "Error updating employee.";
                }
                $message_type = "error";
            }
        } else {
            if (configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate)) {
                $message = "Employee <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
                $message_type = "success";
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id_code = ?");
                $stmt->execute([$id_code]);
                if ($stmt->fetchColumn() > 0) {
                    $message = "Error: Employee ID Code <strong>" . htmlspecialchars($id_code) . "</strong> already exists.";
                } else {
                    $message = "Error adding employee.";
                }
                $message_type = "error";
            }
        }
        skip_configure:
    }
    // Bulk CSV import
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
            $message = "No file uploaded or upload error."; $message_type = "error";
        }
    }
}

// Detect edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editing_employee = getEmployeeById($pdo, $edit_id);
    if (!$editing_employee) {
        header('Location: phase1.php'); exit;
    }
}

$employees = getAllEmployeesIncludingInactive($pdo);
$active_employees = getEmployees($pdo);
$inactive_employees = array_filter($employees, function($emp) { return $emp['is_active'] == 0; });

function isStandardPosition($pos) {
    return in_array($pos, ['Intern', 'Contractor', 'Regular Staff', 'Manager', 'Custom']);
}
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
        .btn-reactivate { border-color: #f59e0b; color: #f59e0b; }
        .btn-reactivate:hover { background: #fffbeb; border-color: #d97706; }
        .bulk-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: var(--surface-2);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .bulk-bar select, .bulk-bar button {
            font-size: 0.8rem;
            padding: 6px 10px;
        }
        .bulk-bar button {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .bulk-bar button.danger {
            background: var(--danger);
        }
        .bulk-bar button:hover { opacity: 0.9; }
        .select-all-checkbox {
            margin-right: 8px;
        }
        .required-star { color: #ef4444; margin-left: 4px; }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header" style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:18px; justify-content:space-between;">
            <div>
                <h1>Employee Directory</h1>
                <p>Add, edit, manage, or bulk import employee profiles</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; min-width:180px;">
                <label for="currency-header-toggle" style="font-size:.75rem; font-weight:700; letter-spacing:.08em; color:var(--text-muted); text-transform:uppercase;">Currency</label>
                <select id="currency-header-toggle" data-currency-toggle style="border-radius:10px; border:1px solid var(--border); padding:10px 12px; background:#fff; color:#111; font-weight:600;">
                    <option value="USD">USD</option>
                    <option value="PHP">PHP</option>
                </select>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="split-view">
            <!-- Add / Edit Employee Form -->
            <section class="card">
                <h2><?php echo $editing_employee ? "Edit Employee" : "Add New Employee"; ?></h2>
                <form method="POST" id="config-form" novalidate>
                    <input type="hidden" name="action" value="configure">
                    <?php if ($editing_employee): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editing_employee['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>ID Code <span class="required-star">*</span></label>
                        <input type="text" name="employee_id_code" placeholder="e.g. EMP-001" value="<?php echo htmlspecialchars($editing_employee['employee_id_code'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name <span class="required-star">*</span></label>
                        <input type="text" name="full_name" placeholder="e.g. Juan dela Cruz" value="<?php echo htmlspecialchars($editing_employee['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hourly Rate ($) <span class="required-star">*</span></label>
                        <input type="number" step="0.01" name="hourly_rate" placeholder="25.00" value="<?php echo htmlspecialchars($editing_employee['hourly_rate'] ?? ''); ?>" required>
                    </div>

                    <!-- Position -->
                    <div class="form-group">
                        <label>Position <span class="required-star">*</span></label>
                        <select name="position" id="position-select" required>
                            <option value="">Select Position</option>
                            <option value="Intern" data-tax="0" <?php echo ($editing_employee && $editing_employee['position'] == 'Intern') ? 'selected' : ''; ?>>Intern — 0% Tax</option>
                            <option value="Contractor" data-tax="10" <?php echo ($editing_employee && $editing_employee['position'] == 'Contractor') ? 'selected' : ''; ?>>Contractor — 10% Tax</option>
                            <option value="Regular Staff" data-tax="20" <?php echo ($editing_employee && $editing_employee['position'] == 'Regular Staff') ? 'selected' : ''; ?>>Regular Staff — 20% Tax</option>
                            <option value="Manager" data-tax="30" <?php echo ($editing_employee && $editing_employee['position'] == 'Manager') ? 'selected' : ''; ?>>Manager — 30% Tax</option>
                            <option value="Custom" data-tax="" <?php echo ($editing_employee && ($editing_employee['position'] == 'Custom' || !isStandardPosition($editing_employee['position']))) ? 'selected' : ''; ?>>Custom → Type Your Own</option>
                        </select>
                        <div id="custom-position-container" class="form-group" style="margin-top:8px; display:<?php echo ($editing_employee && ($editing_employee['position'] == 'Custom' || !isStandardPosition($editing_employee['position']))) ? 'block' : 'none'; ?>;">
                            <label>Custom Position Title <span class="required-star">*</span></label>
                            <input type="text" name="custom_position" id="custom-position-input" placeholder="e.g. Software Engineer" value="<?php echo ($editing_employee && !isStandardPosition($editing_employee['position']) ? htmlspecialchars($editing_employee['position']) : ''); ?>" required>
                        </div>
                    </div>
                    <input type="hidden" name="tax_rate" id="tax_rate_hidden" value="<?php echo htmlspecialchars($editing_employee['tax_rate'] ?? ''); ?>">
                    <div id="custom-tax-container" class="form-group" style="display:<?php echo ($editing_employee && $editing_employee['position'] == 'Custom') ? 'block' : 'none'; ?>;">
                        <label>Custom Tax Rate (%) <span class="required-star">*</span></label>
                        <input type="number" step="0.01" name="custom_tax_rate" id="custom-tax-input" placeholder="e.g. 15" value="<?php echo ($editing_employee && $editing_employee['position'] == 'Custom') ? htmlspecialchars($editing_employee['tax_rate']) : ''; ?>" required>
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
                <?php if (!empty($active_employees)): ?>
                <form method="POST" id="active-form">
                    <input type="hidden" name="action" value="bulk_deactivate">
                    <div class="bulk-bar">
                        <label class="select-all-checkbox">
                            <input type="checkbox" id="select-all-active"> Select All
                        </label>
                        <button type="button" onclick="submitBulk('active-form', 'bulk_deactivate', 'Deactivate selected employees?')">🗑️ Deactivate Selected</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>ID Code</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Rate/hr</th>
                                    <th>Tax</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_employees as $emp): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?php echo $emp['id']; ?>" class="active-check"></td>
                                    <td><code><?php echo htmlspecialchars($emp['employee_id_code']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                                    <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($emp['hourly_rate'], 2, '.', ''); ?>">$<?php echo number_format($emp['hourly_rate'], 2); ?></span></td>
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
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php else: ?>
                    <p style="text-align:center; color:var(--text-muted); padding:30px;">No active employees.</p>
                <?php endif; ?>

                <!-- Inactive Employees Section -->
                <?php if (!empty($inactive_employees)): ?>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <h3>Inactive Employees (<?php echo count($inactive_employees); ?>)</h3>
                        <form method="POST" id="inactive-form">
                            <input type="hidden" name="action" id="inactive-action" value="bulk_reactivate">
                            <div class="bulk-bar">
                                <label class="select-all-checkbox">
                                    <input type="checkbox" id="select-all-inactive"> Select All
                                </label>
                                <button type="button" onclick="submitBulk('inactive-form', 'bulk_reactivate', 'Reactivate selected employees?')">🔄 Reactivate Selected</button>
                                <button type="button" class="danger" onclick="submitBulk('inactive-form', 'bulk_delete', 'Permanently delete selected employees? This cannot be undone.')">🗑️ Delete Permanently Selected</button>
                            </div>
                            <div class="table-container">
                                <table style="opacity:0.85;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;"></th>
                                            <th>ID Code</th>
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
                                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $emp['id']; ?>" class="inactive-check"></td>
                                            <td><code><?php echo htmlspecialchars($emp['employee_id_code']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                                            <td class="mono"><span class="currency-amount" data-usd="<?php echo number_format($emp['hourly_rate'], 2, '.', ''); ?>">$<?php echo number_format($emp['hourly_rate'], 2); ?></span></td>
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
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Bulk Import Card -->
        <section class="card" style="margin-top: 24px;">
            <h2>Bulk Import Employees (CSV)</h2>
            <p style="color: var(--text-muted); font-size: .9rem; margin-bottom: 16px;">
                Upload a CSV file with columns: <code>employee_id_code, full_name, position, hourly_rate, tax_rate</code>.
                First row must contain the header. Position can be any job title.
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
                Example row: <code>EMP-101,John Doe,Software Engineer,25.00,20</code>
            </div>
        </section>
    </main>

    <script>
        // Position & Tax UI
        function initFormControls() {
            const posSelect = document.getElementById('position-select');
            const taxHidden = document.getElementById('tax_rate_hidden');
            const customTaxContainer = document.getElementById('custom-tax-container');
            const customTaxInput = document.getElementById('custom-tax-input');
            const customPosContainer = document.getElementById('custom-position-container');
            const customPosInput = document.getElementById('custom-position-input');

            function updateDisplay() {
                const selectedOption = posSelect.options[posSelect.selectedIndex];
                const taxRate = selectedOption.getAttribute('data-tax');
                const isCustom = posSelect.value === 'Custom';

                // Custom position field
                customPosContainer.style.display = isCustom ? 'block' : 'none';
                if (isCustom) {
                    customPosInput.setAttribute('required', 'required');
                } else {
                    customPosInput.removeAttribute('required');
                    customPosInput.value = ''; // clear when not in use
                }

                // Tax
                if (isCustom) {
                    customTaxContainer.style.display = 'block';
                    customTaxInput.setAttribute('required', 'required');
                    taxHidden.value = ''; // will be taken from custom input
                } else {
                    customTaxContainer.style.display = 'none';
                    customTaxInput.removeAttribute('required');
                    taxHidden.value = taxRate;
                }
            }

            posSelect.addEventListener('change', updateDisplay);
            updateDisplay();
        }

        // Single-row actions
        function confirmDeactivate(empId, empName) {
            if (confirm(`Are you sure you want to deactivate ${empName}?`)) {
                submitSingleAction('deactivate', empId);
            }
        }
        function confirmReactivate(empId, empName) {
            if (confirm(`Reactivate ${empName}?`)) {
                submitSingleAction('reactivate', empId);
            }
        }
        function confirmDelete(empId, empName) {
            if (confirm(`Permanently delete ${empName} and ALL their records?\nThis cannot be undone.`)) {
                submitSingleAction('delete', empId);
            }
        }
        function submitSingleAction(action, empId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="employee_id" value="${empId}">`;
            document.body.appendChild(form);
            form.submit();
        }

        // Bulk select all
        function setupSelectAll(masterId, checkClass) {
            const master = document.getElementById(masterId);
            if (!master) return;
            master.addEventListener('change', function() {
                document.querySelectorAll('.' + checkClass).forEach(cb => cb.checked = this.checked);
            });
        }

        // Bulk form submission
        function submitBulk(formId, action, confirmMsg) {
            const form = document.getElementById(formId);
            if (!form) return;
            const checkboxes = form.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one employee.');
                return;
            }
            if (confirmMsg && !confirm(confirmMsg)) return;
            form.querySelector('input[name="action"]').value = action;
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            initFormControls();
            setupSelectAll('select-all-active', 'active-check');
            setupSelectAll('select-all-inactive', 'inactive-check');
        });
    </script>
    <script src="script.js?v=2"></script>
</body>
</html>