<?php
// sidebar.php - Shared Navigation Sidebar
// NOTE: session_start() is called by the parent file that includes this
require_once 'db.php';

// Redirect to login if not logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_user = getCurrentUser();
?>
<aside class="main-sidebar">
    <div class="sidebar-brand">
        <div class="brand-label">Payroll System</div>
        <h2>NetGain</h2>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Overview</span>
        <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Workflow</span>
        <a href="phase1.php" class="<?php echo in_array($current_page, ['phase1.php', 'employee_history.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span>
            <span>Directory</span>
        </a>
        <a href="phase2.php" class="<?php echo $current_page == 'phase2.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📅</span>
            <span>Timesheet</span>
        </a>
    </nav>

    <div class="sidebar-currency-switcher" style="padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: 18px;">
        <label style="display:block; font-size:.75rem; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,0.72); margin-bottom:8px;">Currency</label>
        <div class="currency-switcher">
            <span class="currency-label">USD</span>
            <label class="switch">
                <input type="checkbox" id="currency-toggle" data-currency-toggle>
                <span class="slider"></span>
            </label>
            <span class="currency-label">PHP</span>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($current_user['username'], 0, 1)); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($current_user['full_name'] ?: $current_user['username']); ?></div>
                <div class="user-role"><?php echo ucfirst($current_user['role']); ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <span>🚪</span>
            <span>Logout</span>
        </a>
    </div>
</aside>