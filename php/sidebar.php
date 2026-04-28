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
        <a href="phase1.php" class="<?php echo $current_page == 'phase1.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span>Employee Config</span>
        </a>
        <a href="phase2.php" class="<?php echo $current_page == 'phase2.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📅</span>
            <span>Timesheet</span>
        </a>
    </nav>

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
