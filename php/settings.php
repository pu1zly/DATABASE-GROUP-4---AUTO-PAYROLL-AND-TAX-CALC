<?php
// settings.php - User & System Settings
session_start();
require_once 'db.php';

if (!isUserLoggedIn()) { header('Location: login.php'); exit; }

$current_user = getCurrentUser();
$settings_defaults = [
    'theme'               => 'light',
    'accent_color'        => '#e28413',
    'density'             => 'comfortable',
    'default_currency'    => 'USD',
    'exchange_rate'       => 56.00,
    'default_pay_period'  => 'monthly',
    'notify_payroll_due'  => 1,
    'notify_new_employee' => 1,
    'notify_cap_warning'  => 1,
    'notify_export'       => 1,
    'high_contrast'       => 0,
    'reduce_motion'       => 0,
    'large_text'          => 0,
];
$settings_raw = getUserSettings($pdo, $current_user['id']);
$settings     = is_array($settings_raw) ? array_merge($settings_defaults, $settings_raw) : $settings_defaults;
$message      = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save_settings') {
        // Only relevant fields are now theme, and other defaults are still saved but hidden
        $data = [
            'theme'              => in_array($_POST['theme'] ?? '', ['light','dark','system']) ? $_POST['theme'] : 'light',
            // these are no longer in the UI but keep defaults if not set
            'accent_color'       => $settings['accent_color'],
            'density'            => $settings['density'],
            'default_currency'   => $settings['default_currency'],
            'exchange_rate'      => $settings['exchange_rate'],
            'default_pay_period' => $settings['default_pay_period'],
            'notify_payroll_due'  => $settings['notify_payroll_due'],
            'notify_new_employee' => $settings['notify_new_employee'],
            'notify_cap_warning'  => $settings['notify_cap_warning'],
            'notify_export'       => $settings['notify_export'],
            'high_contrast'  => $settings['high_contrast'],
            'reduce_motion'  => $settings['reduce_motion'],
            'large_text'     => $settings['large_text'],
        ];
        if (saveUserSettings($pdo, $current_user['id'], $data)) {
            logAudit($pdo, 'settings', 'user', $current_user['id'], "Updated settings");
            $settings_raw = getUserSettings($pdo, $current_user['id']);
            $settings     = is_array($settings_raw) ? array_merge($settings_defaults, $settings_raw) : $settings_defaults;
            $message      = 'Settings saved successfully.';
            $message_type = 'success';
        } else {
            $message = 'Error saving settings.'; $message_type = 'error';
        }
    }

    if ($_POST['action'] === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $user_row = getUserById($pdo, $current_user['id']);
        if (!password_verify($current, $user_row['password_hash'])) {
            $message = 'Current password is incorrect.'; $message_type = 'error';
        } elseif (strlen($new_pass) < 6) {
            $message = 'New password must be at least 6 characters.'; $message_type = 'error';
        } elseif ($new_pass !== $confirm) {
            $message = 'New passwords do not match.'; $message_type = 'error';
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([$hash, $current_user['id']]);
            logAudit($pdo, 'settings', 'user', $current_user['id'], "Changed password");
            $message = 'Password changed successfully.'; $message_type = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Settings-specific styles */
        .settings-grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 24px;
            margin: 0 44px 44px;
        }
        .settings-nav {
            position: sticky;
            top: 20px;
            align-self: start;
        }
        .settings-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-mid);
            text-decoration: none;
            font-size: .86rem;
            font-weight: 500;
            transition: background .15s, color .15s;
            margin-bottom: 2px;
        }
        .settings-nav a:hover  { background: var(--linen-dark); color: var(--text-dark); }
        .settings-nav a.active { background: var(--amber-soft); color: var(--amber-dark); font-weight: 600; }
        .setting-panel { display: none; }
        .setting-panel.active { display: block; }
        .setting-section {
            background: var(--surface-raised);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 26px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
        }
        .setting-section h3 {
            font-family: var(--font-display);
            font-size: .9rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-dark);
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-subtle);
        }
        /* Theme cards */
        .theme-cards { display: flex; gap: 10px; flex-wrap: wrap; }
        .theme-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 10px 14px;
            cursor: pointer;
            transition: all .15s;
            text-align: center;
            flex: 1; min-width: 80px; max-width: 110px;
        }
        .theme-card .preview {
            height: 40px;
            border-radius: 6px;
            border: 1px solid var(--border-subtle);
            margin-bottom: 7px;
        }
        .theme-card .tc-label { font-size: .8rem; font-weight: 600; color: var(--text-mid); }
        .theme-card input[type=radio] { display: none; }
        .theme-card:has(input:checked),
        .theme-card.sel {
            border-color: var(--amber);
            background: var(--amber-soft);
        }
        /* Shortcut list */
        .shortcut-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-subtle);
            font-size: .875rem;
        }
        .shortcut-row:last-child { border-bottom: none; }
        .shortcut-row .sh-desc { color: var(--text-mid); }
        .shortcut-row .sh-keys { display: flex; gap: 4px; }
        kbd {
            display: inline-block;
            padding: 2px 7px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: .75rem;
            font-family: var(--font-mono);
            background: var(--linen-light);
            color: var(--text-mid);
        }
        /* Danger zone */
        .danger-section { border-color: rgba(139,38,53,.3) !important; }
        .danger-confirm {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1.5px solid rgba(139,38,53,.2);
            border-radius: var(--radius);
            padding: 12px 16px;
            cursor: pointer;
            transition: background .15s;
            margin-bottom: 10px;
        }
        .danger-confirm:hover { background: var(--amaranth-soft); }
        .danger-confirm:last-child { margin-bottom: 0; }
        .danger-confirm input[type=checkbox] { width: 16px; height: 16px; margin-top: 2px; flex-shrink: 0; cursor: pointer; accent-color: var(--amaranth); }
        .danger-confirm .dc-title { font-size: .875rem; font-weight: 600; color: var(--amaranth); }
        .danger-confirm .dc-sub   { font-size: .78rem; color: var(--text-muted); margin-top: 2px; }

        @media (max-width: 900px) {
            .settings-grid { grid-template-columns: 1fr; margin: 0 16px 44px; }
            .settings-nav { position: static; display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 16px; }
            .settings-nav a { flex: 1; min-width: 120px; justify-content: center; }
        }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Settings</h1>
            <p>Appearance, shortcuts, security, and system options</p>
        </header>

        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>" style="margin: 0 44px 20px;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Left nav -->
            <nav class="settings-nav" id="settings-nav">
                <a href="#appearance" class="active" data-panel="appearance">🎨 Appearance</a>
                <a href="#shortcuts"  data-panel="shortcuts">⌨️ Shortcuts</a>
                <a href="#security"   data-panel="security">🔑 Security</a>
                <a href="#danger"     data-panel="danger">⚠️ Danger Zone</a>
            </nav>

            <!-- Panels -->
            <div>
                <form method="POST" id="settings-form">
                    <input type="hidden" name="action" value="save_settings">

                    <!-- APPEARANCE (Theme only) -->
                    <div class="setting-panel active" id="panel-appearance">
                        <div class="setting-section">
                            <h3>Theme</h3>
                            <div class="theme-cards">
                                <label class="theme-card <?php echo $settings['theme']==='light' ? 'sel' : ''; ?>">
                                    <input type="radio" name="theme" value="light" <?php echo $settings['theme']==='light' ? 'checked' : ''; ?>>
                                    <div class="preview" style="background:#f7f7f4;"></div>
                                    <div class="tc-label">Light</div>
                                </label>
                                <label class="theme-card <?php echo $settings['theme']==='dark' ? 'sel' : ''; ?>">
                                    <input type="radio" name="theme" value="dark" <?php echo $settings['theme']==='dark' ? 'checked' : ''; ?>>
                                    <div class="preview" style="background:#1d2220;"></div>
                                    <div class="tc-label">Dark</div>
                                </label>
                                <label class="theme-card <?php echo $settings['theme']==='system' ? 'sel' : ''; ?>">
                                    <input type="radio" name="theme" value="system" <?php echo $settings['theme']==='system' ? 'checked' : ''; ?>>
                                    <div class="preview" style="background:linear-gradient(135deg,#f7f7f4 50%,#1d2220 50%);"></div>
                                    <div class="tc-label">System</div>
                                </label>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn-primary">Save Appearance</button>
                        </div>
                    </div>

                </form><!-- end settings-form -->

                <!-- SHORTCUTS (no form needed) -->
                <div class="setting-panel" id="panel-shortcuts">
                    <div class="setting-section">
                        <h3>Keyboard Shortcuts</h3>
                        <div class="shortcut-row"><span class="sh-desc">Open command palette</span><div class="sh-keys"><kbd>Ctrl</kbd><kbd>K</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Go to Dashboard</span><div class="sh-keys"><kbd>G</kbd><kbd>D</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Go to Directory</span><div class="sh-keys"><kbd>G</kbd><kbd>E</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Go to Timesheet</span><div class="sh-keys"><kbd>G</kbd><kbd>T</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Go to Notifications</span><div class="sh-keys"><kbd>G</kbd><kbd>N</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Go to Settings</span><div class="sh-keys"><kbd>G</kbd><kbd>S</kbd></div></div>
                        <div class="shortcut-row"><span class="sh-desc">Close modal / palette</span><div class="sh-keys"><kbd>Esc</kbd></div></div>
                    </div>
                    <div class="setting-section">
                        <h3>About</h3>
                        <div style="font-size:.875rem; color:var(--text-muted); line-height:1.7;">
                            NetGain Payroll System — Group 4 Final Project<br>
                            Lesson 9 Implementation<br>
                            Stack: PHP 8 · MySQL (InnoDB) · Python 3 · HTML/CSS/JS
                        </div>
                    </div>
                </div>

                <!-- SECURITY -->
                <div class="setting-panel" id="panel-security">
                    <div class="setting-section">
                        <h3>Change Password</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="new-pass" required placeholder="At least 6 characters">
                                <div id="new-pass-hint" style="font-size:.78rem; margin-top:4px;"></div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm-pass" required placeholder="••••••••">
                                <div id="confirm-pass-hint" style="font-size:.78rem; margin-top:4px;"></div>
                            </div>
                            <button type="submit" class="btn-primary">Change Password</button>
                        </form>
                    </div>
                    <div class="setting-section">
                        <h3>Session Info</h3>
                        <div style="font-size:.875rem; color:var(--text-muted);">
                            Logged in as <strong style="color:var(--text-dark);"><?php echo htmlspecialchars($current_user['username']); ?></strong>
                            · Role: <strong style="color:var(--text-dark);"><?php echo ucfirst($current_user['role']); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- DANGER ZONE -->
                <div class="setting-panel" id="panel-danger">
                    <div class="setting-section danger-section">
                        <h3 style="color:var(--amaranth);">Danger Zone</h3>
                        <p style="font-size:.83rem; color:var(--text-muted); margin-bottom:16px;">
                            These actions are irreversible. Check each box to confirm before submitting.
                        </p>
                        <label class="danger-confirm">
                            <input type="checkbox" id="confirm-reset">
                            <div>
                                <div class="dc-title">Reset my settings to defaults</div>
                                <div class="dc-sub">Restores appearance, regional, and notification settings to factory defaults.</div>
                            </div>
                        </label>
                        <label class="danger-confirm">
                            <input type="checkbox" id="confirm-purge" disabled>
                            <div>
                                <div class="dc-title" style="color:var(--text-muted);">Purge all payroll records (admin only)</div>
                                <div class="dc-sub">Permanently deletes all processed payroll data. Requires admin role.</div>
                            </div>
                        </label>
                        <div style="margin-top:16px;">
                            <button type="button" class="btn-danger" id="danger-submit" disabled
                                onclick="handleDanger()">
                                Execute Checked Actions
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script src="script.js?v=2"></script>
    <script>
    // Sync saved DB theme to localStorage on page load
    (function() {
        var dbTheme = <?php echo json_encode($settings['theme']); ?>;
        if (window.applyTheme) window.applyTheme(dbTheme);
    })();
    // Settings panel navigation
    const navLinks = document.querySelectorAll('.settings-nav a');
    const panels   = document.querySelectorAll('.setting-panel');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            navLinks.forEach(l => l.classList.remove('active'));
            panels.forEach(p  => p.classList.remove('active'));
            this.classList.add('active');
            const panelId = 'panel-' + this.dataset.panel;
            document.getElementById(panelId)?.classList.add('active');
            history.replaceState(null, '', '#' + this.dataset.panel);
        });
    });

    // Sync theme to localStorage on settings form submit
    document.getElementById('settings-form')?.addEventListener('submit', function() {
        const sel = this.querySelector('input[name=theme]:checked');
        if (sel && window.applyTheme) window.applyTheme(sel.value);
    });

    // Restore panel from hash
    const hash = location.hash.replace('#', '');
    if (hash) {
        const link = document.querySelector('.settings-nav a[data-panel="' + hash + '"]');
        if (link) link.click();
    }

    // Theme card visual feedback + live apply
    document.querySelectorAll('input[name=theme]').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('sel'));
            this.closest('.theme-card').classList.add('sel');
            if (window.applyTheme) window.applyTheme(this.value);
        });
    });

    // Password strength hints
    const newPass = document.getElementById('new-pass');
    const newHint = document.getElementById('new-pass-hint');
    const conPass = document.getElementById('confirm-pass');
    const conHint = document.getElementById('confirm-pass-hint');
    newPass?.addEventListener('input', function() {
        const l = this.value.length;
        if (l === 0) { newHint.textContent = ''; newHint.style.color=''; return; }
        if (l < 6)   { newHint.textContent = 'Too short (min 6)'; newHint.style.color='var(--amaranth)'; }
        else if (l < 10) { newHint.textContent = 'Fair password'; newHint.style.color='var(--amber-dark)'; }
        else         { newHint.textContent = 'Strong password'; newHint.style.color='var(--success)'; }
        validateConfirm();
    });
    conPass?.addEventListener('input', validateConfirm);
    function validateConfirm() {
        if (!conPass.value) { conHint.textContent=''; return; }
        if (conPass.value === newPass.value) { conHint.textContent='Passwords match'; conHint.style.color='var(--success)'; }
        else { conHint.textContent='Does not match'; conHint.style.color='var(--amaranth)'; }
    }

    // Danger zone: enable submit only when at least one box is checked
    const dBoxes  = document.querySelectorAll('#confirm-reset');
    const dSubmit = document.getElementById('danger-submit');
    dBoxes.forEach(b => b.addEventListener('change', () => {
        dSubmit.disabled = !Array.from(dBoxes).some(x => x.checked);
    }));

    function handleDanger() {
        const reset = document.getElementById('confirm-reset').checked;
        if (reset) {
            if (confirm('Reset all settings to defaults?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="save_settings">' +
                    '<input type="hidden" name="theme" value="light">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    }

    // Global keyboard navigation shortcuts (G+key)
    let gPressed = false;
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (e.key === 'g' || e.key === 'G') { gPressed = true; setTimeout(() => gPressed = false, 1200); return; }
        if (gPressed) {
            const map = { d:'index.php', e:'phase1.php', t:'phase2.php', n:'notifications.php', s:'settings.php' };
            const dest = map[e.key.toLowerCase()];
            if (dest) { gPressed = false; window.location.href = dest; }
        }
    });
    </script>
</body>
</html>