<?php
// sidebar.php - Shared Navigation Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
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
        <p>Lesson 9 · Final Project</p>
    </div>
</aside>
