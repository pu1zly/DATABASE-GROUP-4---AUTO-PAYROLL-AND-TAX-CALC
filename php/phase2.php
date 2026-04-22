<?php
// phase2.php - 30-Day Timesheet
require_once 'db.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $employee_id = $_POST['employee_id'];
    $month_year = $_POST['month_year'];
    
    $total_reg = 0; $total_ot = 0; $total_sick = 0; $total_vac = 0;

    for ($i = 1; $i <= 30; $i++) {
        $date = $month_year . "-" . sprintf("%02d", $i);
        $is_day_off = isset($_POST["day_off_$i"]) ? 1 : 0;
        
        $reg = $is_day_off ? 0 : (float)$_POST["reg_$i"];
        $ot = $is_day_off ? 0 : (float)$_POST["ot_$i"];
        $sick = $is_day_off ? 0 : (float)$_POST["sick_$i"];
        $vac = $is_day_off ? 0 : (float)$_POST["vac_$i"];
        
        saveDailyLog($pdo, $employee_id, $date, $reg, $ot, $sick, $vac, $is_day_off);
        
        $total_reg += $reg;
        $total_ot += $ot;
        $total_sick += $sick;
        $total_vac += $vac;
    }

    if (processPayrollRecord($pdo, $employee_id, $month_year . "-01", $total_reg, $total_ot, $total_sick, $total_vac)) {
        $message = "30-day payroll processed successfully!";
        header("Location: index.php"); // Redirect to dashboard to see results
        exit;
    }
}
$employees = getEmployees($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 2: Timesheet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>
    
    <main class="content-area">
        <header class="content-header">
            <h1>Phase 2: Timesheet</h1>
            <p>Log 30-Day Work Hours and Day Offs</p>
        </header>

        <section class="card timesheet-card">
            <form method="POST" id="timesheet-form">
                <div class="timesheet-controls">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['full_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <input type="month" name="month_year" value="<?php echo date('Y-m'); ?>" required>
                    </div>
                    <div class="ts-summary-box">
                        <p>Total Monthly Hours</p>
                        <h2 id="ts-total">0.0</h2>
                    </div>
                    <button type="submit" name="process_payroll" class="btn-primary">Process 30 Days</button>
                </div>

                <div class="timesheet-grid-container">
                    <div class="timesheet-grid">
                        <div class="grid-header">Day</div>
                        <div class="grid-header">Reg</div>
                        <div class="grid-header">OT</div>
                        <div class="grid-header">Off</div>

                        <?php for ($i = 1; $i <= 30; $i++): ?>
                        <div class="day-row" id="row-<?php echo $i; ?>">
                            <span class="day-num"><?php echo $i; ?></span>
                            <input type="number" step="0.1" name="reg_<?php echo $i; ?>" value="8" class="ts-input">
                            <input type="number" step="0.1" name="ot_<?php echo $i; ?>" value="0" class="ts-input">
                            <div class="toggle-container">
                                <input type="checkbox" name="day_off_<?php echo $i; ?>" class="day-off-check" data-day="<?php echo $i; ?>">
                            </div>
                            <input type="hidden" name="sick_<?php echo $i; ?>" value="0">
                            <input type="hidden" name="vac_<?php echo $i; ?>" value="0">
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </form>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>
