<?php
// phase1.php - Employee Configuration
require_once 'db.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['configure_employee'])) {
    $id_code      = $_POST['employee_id_code'];
    $name         = $_POST['full_name'];
    $position     = $_POST['position'];
    $hourly_rate  = $_POST['hourly_rate'];
    $tax_rate     = ($position == 'Custom') ? $_POST['custom_tax_rate'] : $_POST['tax_rate'];

    if (configureEmployee($pdo, $id_code, $name, $position, $hourly_rate, $tax_rate)) {
        $message = "Employee <strong>" . htmlspecialchars($name) . "</strong> configured successfully!";
    }
}
$employees = getEmployees($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Config — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Employee Config</h1>
            <p>Set up employee profiles, positions, and tax rules</p>
        </header>

        <?php if ($message): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="split-view">
            <section class="card">
                <h2>Add New Employee</h2>
                <form method="POST" id="config-form">
                    <div class="form-group">
                        <label>ID Code</label>
                        <input type="text" name="employee_id_code" placeholder="e.g. EMP-001" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="e.g. Juan dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label>Hourly Rate ($)</label>
                        <input type="number" step="0.01" name="hourly_rate" placeholder="25.00" required>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <select name="position" id="position-select" required>
                            <option value="">Select Position</option>
                            <option value="Intern"        data-tax="0">Intern — 0% Tax</option>
                            <option value="Contractor"    data-tax="10">Contractor — 10% Tax</option>
                            <option value="Regular Staff" data-tax="20">Regular Staff — 20% Tax</option>
                            <option value="Manager"       data-tax="30">Manager — 30% Tax</option>
                            <option value="Custom"        data-tax="">Custom — Manual Entry</option>
                        </select>
                    </div>
                    <input type="hidden" name="tax_rate" id="tax_rate_hidden">
                    <div id="custom-tax-container" class="form-group" style="display:none;">
                        <label>Custom Tax Rate (%)</label>
                        <input type="number" step="0.01" name="custom_tax_rate" placeholder="e.g. 15">
                    </div>
                    <button type="submit" name="configure_employee" class="btn-primary">Save Employee</button>
                </form>
            </section>

            <section class="card">
                <h2>Configured Employees</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Rate/hr</th>
                                <th>Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">No employees yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($emp['employee_id_code']); ?></small>
                                    </td>
                                    <td><span class="badge"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                                    <td class="mono">$<?php echo number_format($emp['hourly_rate'], 2); ?></td>
                                    <td class="mono"><?php echo $emp['tax_rate']; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
    <script src="script.js"></script>
</body>
</html>
