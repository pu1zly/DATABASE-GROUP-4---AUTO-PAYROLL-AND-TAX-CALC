<?php
// sidebar.php - Shared Navigation Sidebar
require_once 'db.php';

if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_user = getCurrentUser();
$unread_count = countUnreadNotifications($pdo, $current_user['id']);
?>
<!-- Apply stored theme immediately to prevent flash of unstyled content -->
<script>(function(){try{var t=localStorage.getItem('ngTheme')||'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}}());</script>

<?php
// Fetch user preferences from DB
$prefs = getUserSettings($pdo, $current_user['id']);
// Accessibility booleans
$highContrast = !empty($prefs['high_contrast']);
$reduceMotion = !empty($prefs['reduce_motion']);
$largeText    = !empty($prefs['large_text']);
// Theme (fallback to light if system is stored but now disabled)
$theme = in_array($prefs['theme'] ?? '', ['light','dark']) ? $prefs['theme'] : 'light';
// Accent color
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $prefs['accent_color'] ?? '') ? $prefs['accent_color'] : '#e28413';
// Density
$density = in_array($prefs['density'] ?? '', ['compact','comfortable','spacious']) ? $prefs['density'] : 'comfortable';
?>
<script>
  // Override theme from DB (ensures stored theme is used)
  document.documentElement.setAttribute('data-theme', '<?php echo $theme; ?>');
  // Accessibility attributes
  document.documentElement.setAttribute('data-high-contrast', '<?php echo $highContrast ? 'true' : 'false'; ?>');
  document.documentElement.setAttribute('data-reduce-motion', '<?php echo $reduceMotion ? 'true' : 'false'; ?>');
  document.documentElement.setAttribute('data-large-text',    '<?php echo $largeText    ? 'true' : 'false'; ?>');
  // Density
  document.documentElement.setAttribute('data-density', '<?php echo $density; ?>');
</script>
<style>
  /* Apply accent color dynamically as custom properties */
  :root {
    --accent: <?php echo $accent; ?>;
    --accent-soft: <?php echo $accent; ?>1a;   /* ~10% opacity */
    --accent-dark: <?php
        // Darken the accent by 15% for hover states
        $hex = ltrim($accent, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = max(0, $r - 40);
        $g = max(0, $g - 40);
        $b = max(0, $b - 40);
        echo sprintf('#%02x%02x%02x', $r, $g, $b);
    ?>;
  }
  /* Override the amber-based tokens with the chosen accent */
  :root, html[data-theme] {
    --amber: var(--accent);
    --amber-dark: var(--accent-dark);
    --amber-soft: var(--accent-soft);
  }
</style>

<aside class="main-sidebar" id="main-sidebar">
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

        <span class="nav-section-label">System</span>
        <a href="notifications.php" class="<?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" style="position:relative;">
            <span class="nav-icon">🔔</span>
            <span>Notifications</span>
            <?php if ($unread_count > 0): ?>
                <span class="notif-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span>Settings</span>
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

<!-- Command Palette overlay (unchanged) -->
<!-- ... -->

<!-- Command Palette overlay (Ctrl+K) -->
<div id="cmd-overlay" class="cmd-overlay" role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="cmd-box">
        <div class="cmd-search-row">
            <span class="cmd-icon">⌕</span>
            <input type="text" id="cmd-input" placeholder="Search employees, actions, pages…" autocomplete="off" spellcheck="false">
            <kbd class="cmd-esc">Esc</kbd>
        </div>
        <div class="cmd-results" id="cmd-results"></div>
    </div>
</div>

<style>
/* Notification badge */
.notif-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--amaranth);
    color: #fff;
    font-size: .58rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 20px;
    min-width: 16px;
    text-align: center;
    line-height: 1.4;
    letter-spacing: .02em;
}
/* Command palette */
.cmd-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(26,31,29,.6);
    backdrop-filter: blur(4px);
    z-index: 2000;
    align-items: flex-start;
    justify-content: center;
    padding-top: 18vh;
}
.cmd-overlay.open { display: flex; }
.cmd-box {
    background: var(--surface-raised);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    width: 100%;
    max-width: 560px;
    border: 1px solid var(--border);
    overflow: hidden;
}
.cmd-search-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-subtle);
}
.cmd-icon { font-size: 1.1rem; color: var(--text-muted); }
#cmd-input {
    flex: 1;
    border: none;
    background: transparent;
    font-family: var(--font-body);
    font-size: .95rem;
    color: var(--text-dark);
    outline: none;
    padding: 0;
    box-shadow: none;
}
.cmd-esc {
    font-size: .7rem;
    padding: 2px 6px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--linen-light);
    color: var(--text-muted);
    font-family: var(--font-mono);
    cursor: pointer;
}
.cmd-results { max-height: 340px; overflow-y: auto; padding: 8px 0; }
.cmd-section-label {
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--text-faint);
    padding: 8px 18px 4px;
}
.cmd-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 18px;
    cursor: pointer;
    transition: background .1s;
    font-size: .875rem;
    color: var(--text-dark);
    text-decoration: none;
}
.cmd-item:hover, .cmd-item.focused {
    background: var(--amber-soft);
}
.cmd-item .cmd-item-icon { font-size: .95rem; width: 20px; text-align: center; flex-shrink: 0; }
.cmd-item .cmd-item-label { flex: 1; }
.cmd-item .cmd-item-badge {
    font-size: .65rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: var(--linen-dark);
    color: var(--gunmetal);
}
.cmd-item .cmd-item-badge.action  { background: var(--success-soft); color: var(--success); }
.cmd-item .cmd-item-badge.page    { background: #eff6ff; color: #2563eb; }
.cmd-item .cmd-item-badge.employee{ background: #f0f9ff; color: #0369a1; }
.cmd-empty { padding: 20px 18px; font-size: .875rem; color: var(--text-muted); text-align: center; }
</style>

<script>
// ── Command Palette ──────────────────────────────────────────
(function() {
    const overlay  = document.getElementById('cmd-overlay');
    const input    = document.getElementById('cmd-input');
    const results  = document.getElementById('cmd-results');

    const pages = [
        { label: 'Dashboard', icon: '📊', href: 'index.php',         badge: 'page' },
        { label: 'Employee Directory', icon: '👥', href: 'phase1.php', badge: 'page' },
        { label: 'Timesheet', icon: '📅', href: 'phase2.php',         badge: 'page' },
        { label: 'Notifications', icon: '🔔', href: 'notifications.php', badge: 'page' },
        { label: 'Audit Log', icon: '🗂', href: 'audit_log.php',       badge: 'page' },
        { label: 'Settings', icon: '⚙️', href: 'settings.php',        badge: 'page' },
    ];
    const actions = [
        { label: 'Add new employee', icon: '➕', href: 'phase1.php', badge: 'action' },
        { label: 'Process payroll', icon: '💳', href: 'phase2.php',  badge: 'action' },
        { label: 'Export CSV',      icon: '📥', href: 'export_payroll.php', badge: 'action' },
        { label: 'Logout',          icon: '🚪', href: 'logout.php',  badge: 'action' },
    ];
    const all = [...pages, ...actions];

    function open() { overlay.classList.add('open'); input.value = ''; renderResults(''); input.focus(); }
    function close() { overlay.classList.remove('open'); input.blur(); }

    function renderResults(q) {
        const lq = q.toLowerCase().trim();
        const filtered = lq ? all.filter(i => i.label.toLowerCase().includes(lq)) : all;
        if (filtered.length === 0) {
            results.innerHTML = '<div class="cmd-empty">No results for "' + q + '"</div>';
            return;
        }
        results.innerHTML = filtered.map((item, idx) => `
            <a class="cmd-item" href="${item.href}" data-idx="${idx}">
                <span class="cmd-item-icon">${item.icon}</span>
                <span class="cmd-item-label">${item.label}</span>
                <span class="cmd-item-badge ${item.badge}">${item.badge}</span>
            </a>`).join('');
    }

    input.addEventListener('input', () => renderResults(input.value));
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); open(); }
        if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });
    document.querySelector('.cmd-esc')?.addEventListener('click', close);

    // Expose for keyboard shortcut hints
    window.openCmdPalette = open;
})();
</script>
