<?php
// phase2.php - 30-Day Timesheet (new + edit mode)
session_start();
require_once 'db.php';

function getDaysInMonth(string $monthYear): int {
    $timestamp = strtotime($monthYear . '-01');
    return $timestamp ? (int)date('t', $timestamp) : 30;
}

// Check if user is logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ── AJAX: return employee IDs that already have a record for a given month
if (isset($_GET['ajax']) && $_GET['ajax'] === 'taken_employees' && isset($_GET['month'])) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT employee_id 
        FROM payroll_records 
        WHERE DATE_FORMAT(month_year, '%Y-%m') = ?
    ");
    $stmt->execute([$_GET['month']]);
    $taken = array_map('intval', array_column($stmt->fetchAll(), 'employee_id'));
    header('Content-Type: application/json');
    echo json_encode($taken);
    exit;
}

// ── Detect edit mode from URL params (from dashboard Edit button)
$edit_employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$edit_month       = isset($_GET['month']) ? $_GET['month'] : null; // YYYY-MM
$is_edit_mode     = $edit_employee_id && $edit_month;

$selected_month = $is_edit_mode ? $edit_month : date('Y-m');
$selected_month = $selected_month ?: date('Y-m');
$days_in_month  = getDaysInMonth($selected_month);

// Pre-load existing daily logs if editing
$existing_logs = [];
$edit_employee = null;
if ($is_edit_mode) {
    $existing_logs = getDailyLogsByMonth($pdo, $edit_employee_id, $edit_month);
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$edit_employee_id]);
    $edit_employee = $stmt->fetch();
}

// ── Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month_year  = $_POST['month_year'];
    $is_editing  = isset($_POST['is_editing']) && $_POST['is_editing'] === '1';

    $total_reg = 0; $total_ot = 0;
    $num_days = getDaysInMonth($month_year);

    for ($i = 1; $i <= $num_days; $i++) {
        $date       = $month_year . "-" . sprintf("%02d", $i);
        $is_day_off = isset($_POST["day_off_$i"]) ? 1 : 0;
        $reg        = $is_day_off ? 0 : (float)$_POST["reg_$i"];
        $ot         = $is_day_off ? 0 : (float)$_POST["ot_$i"];
        saveDailyLog($pdo, $employee_id, $date, $reg, $ot, 0, 0, $is_day_off);
        $total_reg += $reg;
        $total_ot  += $ot;
    }

    if ($is_editing) {
        $ok = updatePayrollRecord($pdo, $employee_id, $month_year, $total_reg, $total_ot, 0, 0);
    } else {
        $ok = processPayrollRecord($pdo, $employee_id, $month_year . "-01", $total_reg, $total_ot, 0, 0);
    }

    if ($ok) {
        header("Location: index.php?month=" . substr($month_year, 0, 7));
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
    <title><?php echo $is_edit_mode ? 'Edit Timesheet' : 'Timesheet'; ?> — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Excel-style Timesheet ───────────────────────────── */
        .ts-card {
            background: #fff;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            width: 80%;
            margin: 0 auto;
        }

        .ts-toolbar {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 16px;
            align-items: flex-end;
            padding: 22px 28px;
            background: #fff;
            border-bottom: 2px solid var(--border);
        }

        .ts-summary-box {
            background: var(--primary-soft);
            border: 1.5px solid rgba(59,110,248,.2);
            border-radius: 8px;
            padding: 8px 16px;
            text-align: center;
        }
        .ts-summary-box p  { font-size: .68rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 1px; }
        .ts-summary-box h2 { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin: 0; line-height: 1; }

        .edit-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fffbeb;
            border: 1.5px solid #fcd34d;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            font-size: .875rem;
            color: #92400e;
            font-weight: 500;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .edit-banner::before { content: '✏️'; font-size: 1.1rem; }

        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'DM Mono', monospace;
            font-size: .9rem;
        }

        .excel-table thead th {
            background: #1e293b;
            color: #f1f5f9;
            font-family: 'DM Sans', sans-serif;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            padding: 13px 20px;
            border-right: 1px solid #334155;
            text-align: center;
            white-space: nowrap;
            user-select: none;
        }
        .excel-table thead th:last-child { border-right: none; }

        .excel-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .1s;
        }
        .excel-table tbody tr:last-child { border-bottom: none; }
        .excel-table tbody tr:hover td  { background: #f0f6ff; }

        .excel-table tbody td {
            padding: 0;
            border-right: 1px solid var(--border);
            vertical-align: middle;
        }
        .excel-table tbody td:last-child { border-right: none; }

        .row-num {
            padding: 0 20px;
            font-size: .85rem;
            font-weight: 700;
            color: #475569;
            background: #f8fafc;
            border-right: 2px solid #cbd5e1 !important;
            text-align: center;
            white-space: nowrap;
            width: 130px;
        }

        .excel-table input[type="number"] {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-family: 'DM Mono', monospace;
            font-size: .9rem;
            color: var(--text);
            padding: 12px 20px;
            text-align: center;
            box-shadow: none;
            border-radius: 0;
            transition: background .15s;
        }
        .excel-table input[type="number"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 2px var(--primary);
        }

        .row-dayoff td                   { background: #f8fafc !important; }
        .row-dayoff .row-num             { color: #94a3b8; }
        .row-dayoff input[type="number"] {
            color: #cbd5e1 !important;
            pointer-events: none;
            background: #f8fafc !important;
        }

        .check-cell {
            text-align: center;
            padding: 0 16px;
            width: 100px;
        }
        .day-off-check {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .excel-table tfoot td {
            background: #1e293b;
            color: #f1f5f9;
            font-family: 'DM Sans', sans-serif;
            font-size: .8rem;
            font-weight: 700;
            padding: 13px 20px;
            border-right: 1px solid #334155;
            text-align: center;
        }
        .excel-table tfoot td:last-child { border-right: none; }
        .tfoot-val { font-family: 'DM Mono', monospace; font-size: .95rem; color: #93c5fd; }

        .field-locked { opacity: .6; pointer-events: none; }

        @media (max-width: 1100px) {
            .ts-card, .edit-banner { width: 96%; }
            .ts-toolbar { grid-template-columns: 1fr 1fr; }
            .ts-summary-box { grid-column: span 2; }
        }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1><?php echo $is_edit_mode ? 'Edit Timesheet' : 'Timesheet'; ?></h1>
            <p><?php echo $is_edit_mode
                ? 'Editing record for <strong>' . htmlspecialchars($edit_employee['full_name']) . '</strong> — ' . date('F Y', strtotime($edit_month . '-01'))
                : 'Log work hours and days off per employee'; ?></p>
        </header>

        <?php if ($is_edit_mode): ?>
        <div class="edit-banner">
            Editing existing record for
            <strong><?php echo htmlspecialchars($edit_employee['full_name']); ?></strong>
            (<?php echo date('F Y', strtotime($edit_month . '-01')); ?>).
            Changes will overwrite the current payroll record.
        </div>
        <?php endif; ?>

        <div class="ts-card">
            <form method="POST" id="timesheet-form">
                <input type="hidden" name="is_editing" value="<?php echo $is_edit_mode ? '1' : '0'; ?>">

                <div class="ts-toolbar">
                    <!-- Employee dropdown — locked in edit mode, filtered by month in new mode -->
                    <div class="form-group <?php echo $is_edit_mode ? 'field-locked' : ''; ?>" style="margin:0;">
                        <label>Employee</label>
                        <select name="employee_id" id="employee-select" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp):
                                $is_selected = $is_edit_mode && $emp['id'] === $edit_employee_id;
                            ?>
                                <option value="<?php echo $emp['id']; ?>"
                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                    (<?php echo htmlspecialchars($emp['employee_id_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Month picker — locked in edit mode -->
                    <div class="form-group <?php echo $is_edit_mode ? 'field-locked' : ''; ?>" style="margin:0;">
                        <label>Month</label>
                        <input type="month" name="month_year" id="month-picker"
                               value="<?php echo $is_edit_mode ? htmlspecialchars($edit_month) : date('Y-m'); ?>"
                               required>
                    </div>

                    <div class="ts-summary-box">
                        <p>Total Hours</p>
                        <h2 id="ts-total">0.0</h2>
                    </div>

                    <button type="submit" name="process_payroll" class="btn-primary"
                            style="width:auto; min-width:160px; white-space:nowrap;">
                        <?php echo $is_edit_mode ? 'Save Changes →' : 'Process Month →'; ?>
                    </button>
                </div>

                <!-- Excel Table -->
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Regular Hrs</th>
                            <th>Overtime Hrs</th>
                            <th>Day Off</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 31; $i++):
                            $log        = $existing_logs[$i] ?? null;
                            $reg_val    = $log ? (float)$log['reg_hours'] : 8;
                            $ot_val     = $log ? (float)$log['ot_hours']  : 0;
                            $is_day_off = $log ? (bool)$log['is_day_off'] : false;
                            $is_visible = $i <= $days_in_month;
                        ?>
                        <tr class="day-row <?php echo $is_day_off ? 'row-dayoff' : ''; ?>" id="row-<?php echo $i; ?>" data-day="<?php echo $i; ?>" <?php echo $is_visible ? '' : 'style="display:none;"'; ?>>
                            <td class="row-num">Day <?php echo sprintf('%02d', $i); ?></td>
                            <td>
                                <input type="number" step="0.1" min="0"
                                       name="reg_<?php echo $i; ?>"
                                       value="<?php echo $is_day_off ? 0 : $reg_val; ?>"
                                       class="ts-input"
                                       <?php echo $is_visible ? '' : 'disabled'; ?>>
                            </td>
                            <td>
                                <input type="number" step="0.1" min="0"
                                       name="ot_<?php echo $i; ?>"
                                       value="<?php echo $is_day_off ? 0 : $ot_val; ?>"
                                       class="ts-input"
                                       <?php echo $is_visible ? '' : 'disabled'; ?>>
                            </td>
                            <td class="check-cell">
                                <input type="checkbox"
                                       name="day_off_<?php echo $i; ?>"
                                       class="day-off-check"
                                       data-day="<?php echo $i; ?>"
                                       <?php echo $is_day_off ? 'checked' : ''; ?>
                                       <?php echo $is_visible ? '' : 'disabled'; ?>>
                            </td>
                            <input type="hidden" name="sick_<?php echo $i; ?>" value="0" <?php echo $is_visible ? '' : 'disabled'; ?>>
                            <input type="hidden" name="vac_<?php echo $i; ?>"  value="0" <?php echo $is_visible ? '' : 'disabled'; ?>>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Month Total</td>
                            <td class="tfoot-val" id="foot-reg">0.0 hrs</td>
                            <td class="tfoot-val" id="foot-ot">0.0 hrs</td>
                            <td class="tfoot-val" id="foot-off">0 days</td>
                        </tr>
                    </tfoot>
                </table>
            </form>
        </div>
    </main>

    <script src="script.js"></script>
    <script>
    (function () {
        const isEditMode = <?php echo $is_edit_mode ? 'true' : 'false'; ?>;
        const monthPicker = document.getElementById('month-picker');
        const employeeSelect = document.getElementById('employee-select');

        // ── Hide employees who already have a record for the selected month
        // Store original options once so we can restore them on month change
        const allEmployeeOptions = Array.from(employeeSelect.options).map(opt => ({
            value: opt.value,
            text:  opt.textContent,
            el:    opt.cloneNode(true)
        }));

        function refreshTakenEmployees() {
            if (isEditMode) return; // don't filter in edit mode
            const month = monthPicker.value;
            if (!month) return;

            fetch('phase2.php?ajax=taken_employees&month=' + month)
                .then(r => r.json())
                .then(takenIds => {
                    // Remember current selection
                    const currentVal = employeeSelect.value;

                    // Rebuild the dropdown, skipping taken employees
                    employeeSelect.innerHTML = '';
                    allEmployeeOptions.forEach(item => {
                        if (!item.value) {
                            // Always keep the placeholder option
                            employeeSelect.appendChild(item.el.cloneNode(true));
                            return;
                        }
                        if (takenIds.includes(parseInt(item.value))) {
                            return; // skip — already has a record this month
                        }
                        employeeSelect.appendChild(item.el.cloneNode(true));
                    });

                    // Restore selection if still available, else reset
                    if (currentVal && !takenIds.includes(parseInt(currentVal))) {
                        employeeSelect.value = currentVal;
                    } else {
                        employeeSelect.value = '';
                    }
                });
        }

        if (!isEditMode) {
            monthPicker.addEventListener('change', function () {
                refreshTakenEmployees();
                updateMonthDays();
            });
            refreshTakenEmployees(); // run on page load
        }

        function getDaysInMonthFromValue(value) {
            if (!value) return 30;
            const parts = value.split('-').map(Number);
            if (parts.length !== 2 || !parts[0] || !parts[1]) return 30;
            return new Date(parts[0], parts[1], 0).getDate();
        }

        function updateMonthDays() {
            const visibleDays = getDaysInMonthFromValue(monthPicker.value);
            document.querySelectorAll('.day-row').forEach(row => {
                const day = parseInt(row.getAttribute('data-day'), 10);
                const rowInputs = row.querySelectorAll('input');
                if (day <= visibleDays) {
                    row.style.display = '';
                    rowInputs.forEach(input => input.disabled = false);
                } else {
                    row.style.display = 'none';
                    rowInputs.forEach(input => {
                        input.disabled = true;
                        if (input.type === 'number') input.value = 0;
                        if (input.type === 'checkbox') input.checked = false;
                    });
                }
            });
            updateFooter();
        }

        updateMonthDays();

        // ── Totals footer
        function updateFooter() {
            let totalReg = 0, totalOt = 0, totalOff = 0;
            document.querySelectorAll('.day-row').forEach(row => {
                const isOff = row.querySelector('.day-off-check').checked;
                if (isOff) {
                    totalOff++;
                } else {
                    totalReg += parseFloat(row.querySelector('input[name^="reg_"]').value) || 0;
                    totalOt  += parseFloat(row.querySelector('input[name^="ot_"]').value)  || 0;
                }
            });
            const tsTotal = document.getElementById('ts-total');
            if (tsTotal) tsTotal.textContent = (totalReg + totalOt).toFixed(1);
            document.getElementById('foot-reg').textContent = totalReg.toFixed(1) + ' hrs';
            document.getElementById('foot-ot').textContent  = totalOt.toFixed(1)  + ' hrs';
            document.getElementById('foot-off').textContent = totalOff + ' day' + (totalOff !== 1 ? 's' : '');
        }

        document.querySelectorAll('.day-off-check').forEach(chk => {
            chk.addEventListener('change', function () {
                const row = document.getElementById('row-' + this.getAttribute('data-day'));
                if (this.checked) {
                    row.classList.add('row-dayoff');
                    row.querySelectorAll('input[type="number"]').forEach(inp => inp.value = 0);
                } else {
                    row.classList.remove('row-dayoff');
                    row.querySelector('input[name^="reg_"]').value = 8;
                    row.querySelector('input[name^="ot_"]').value  = 0;
                }
                updateFooter();
            });
        });

        document.getElementById('timesheet-form').addEventListener('input', updateFooter);
        updateFooter();
    })();
    </script>
</body>
</html>
