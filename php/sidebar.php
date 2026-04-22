<?php
// sidebar.php - Shared Navigation Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="main-sidebar">
    <div class="sidebar-brand">
        <h2>Manus Payroll</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="phase1.php" class="<?php echo $current_page == 'phase1.php' ? 'active' : ''; ?>">
            <span class="icon">⚙️</span> Phase 1: Config
        </a>
        <a href="phase2.php" class="<?php echo $current_page == 'phase2.php' ? 'active' : ''; ?>">
            <span class="icon">📅</span> Phase 2: Timesheet
        </a>
    </nav>
    <div class="sidebar-footer">
        <p>Lesson 9 - Final Project</p>
    </div>
</aside>
