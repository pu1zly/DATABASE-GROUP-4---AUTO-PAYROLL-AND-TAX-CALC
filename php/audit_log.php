<?php
// audit_log.php - System Audit Log Viewer
session_start();
require_once 'db.php';

if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user = getCurrentUser();

// Filters
$search = trim($_GET['search'] ?? '');
$limit  = max(25, min(500, (int)($_GET['limit'] ?? 100)));

// Fetch logs
$logs = getAuditLogs($pdo, $limit, $search);
$total_shown = count($logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .audit-action-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .badge-login     { background: #dbeafe; color: #1d4ed8; }
        .badge-add,
        .badge-import    { background: var(--success-soft); color: var(--success); }
        .badge-edit      { background: var(--amber-soft); color: var(--amber-dark); }
        .badge-delete    { background: var(--amaranth-soft); color: var(--amaranth); }
        .badge-settings  { background: #f3f4f6; color: #374151; }
        .badge-payroll   { background: #fef9c3; color: #854d0e; }
        .badge-export    { background: var(--success-soft); color: var(--success); }
        .badge-default   { background: var(--linen-dark); color: var(--text-mid); }

        .target-pill {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 20px;
            font-size: .65rem;
            font-weight: 700;
            background: var(--linen-dark);
            color: var(--text-muted);
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .log-description {
            font-size: .82rem;
            color: var(--text-mid);
            max-width: 380px;
        }

        .log-time {
            font-size: .72rem;
            font-family: var(--font-mono);
            color: var(--text-faint);
            white-space: nowrap;
        }

        .log-ip {
            font-size: .7rem;
            font-family: var(--font-mono);
            color: var(--text-faint);
        }

        .log-user {
            font-weight: 600;
            font-size: .85rem;
            color: var(--text-dark);
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-bar input[type=text] {
            min-width: 240px;
            padding: 9px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: .875rem;
            font-family: var(--font-body);
            color: var(--text-dark);
            background: var(--surface-raised);
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .search-bar input[type=text]:focus {
            border-color: var(--amber);
            box-shadow: 0 0 0 3px var(--amber-glow);
        }
        .search-bar select {
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: .82rem;
            font-family: var(--font-body);
            color: var(--text-dark);
            background: var(--surface-raised);
            outline: none;
            cursor: pointer;
        }
        .result-count {
            font-size: .78rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
            margin-left: auto;
        }
        @media (max-width: 768px) {
            .log-ip, .log-description { display: none; }
        }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Audit Log</h1>
            <p>Full record of system actions and user activity</p>
        </header>

        <section class="card" style="margin: 0 44px 22px;">
            <!-- Search / Filter bar -->
            <form method="GET" class="search-bar" style="margin-bottom:20px;">
                <input type="text"
                       name="search"
                       placeholder="Search by user, action, or description…"
                       value="<?php echo htmlspecialchars($search); ?>"
                       autocomplete="off">
                <select name="limit" onchange="this.form.submit()">
                    <option value="25"  <?php echo $limit===25  ? 'selected':''; ?>>Show 25</option>
                    <option value="50"  <?php echo $limit===50  ? 'selected':''; ?>>Show 50</option>
                    <option value="100" <?php echo $limit===100 ? 'selected':''; ?>>Show 100</option>
                    <option value="250" <?php echo $limit===250 ? 'selected':''; ?>>Show 250</option>
                    <option value="500" <?php echo $limit===500 ? 'selected':''; ?>>Show 500</option>
                </select>
                <button type="submit" class="btn-primary" style="padding:9px 18px;">Search</button>
                <?php if ($search): ?>
                    <a href="audit_log.php" class="btn-secondary" style="padding:9px 14px; font-size:.8rem;">✕ Clear</a>
                <?php endif; ?>
                <span class="result-count"><?php echo $total_shown; ?> record<?php echo $total_shown !== 1 ? 's' : ''; ?></span>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:140px;">Timestamp</th>
                            <th style="width:120px;">User</th>
                            <th style="width:90px;">Action</th>
                            <th style="width:90px;">Target</th>
                            <th>Description</th>
                            <th style="width:110px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:48px 20px; color:var(--text-muted);">
                                    <?php if ($search): ?>
                                        No records match "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                        <a href="audit_log.php" style="color:var(--amber-dark); font-weight:600; margin-left:6px;">Clear search →</a>
                                    <?php else: ?>
                                        No audit entries yet. Actions across the system will be recorded here.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $action_badge_map = [
                                'login'    => 'badge-login',
                                'logout'   => 'badge-settings',
                                'add'      => 'badge-add',
                                'edit'     => 'badge-edit',
                                'delete'   => 'badge-delete',
                                'import'   => 'badge-import',
                                'export'   => 'badge-export',
                                'settings' => 'badge-settings',
                                'payroll'  => 'badge-payroll',
                                'notifications' => 'badge-settings',
                            ];
                            foreach ($logs as $log):
                                $action_class = $action_badge_map[$log['action']] ?? 'badge-default';
                            ?>
                            <tr>
                                <td class="log-time">
                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?><br>
                                    <span style="font-size:.65rem; color:var(--text-faint);">
                                        <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="log-user"><?php echo htmlspecialchars($log['username']); ?></span>
                                    <?php if ($log['user_id']): ?>
                                        <br><span style="font-size:.65rem; font-family:var(--font-mono); color:var(--text-faint);">#<?php echo $log['user_id']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="audit-action-badge <?php echo $action_class; ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['target_type']): ?>
                                        <span class="target-pill"><?php echo htmlspecialchars($log['target_type']); ?></span>
                                        <?php if ($log['target_id']): ?>
                                            <br><span style="font-size:.65rem; font-family:var(--font-mono); color:var(--text-faint);">#<?php echo $log['target_id']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-faint); font-size:.78rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="log-description">
                                    <?php echo $log['description'] ? htmlspecialchars($log['description']) : '<span style="color:var(--text-faint);">—</span>'; ?>
                                </td>
                                <td class="log-ip">
                                    <?php echo $log['ip_address'] ? htmlspecialchars($log['ip_address']) : '—'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_shown >= $limit): ?>
                <div style="margin-top:14px; text-align:center; font-size:.82rem; color:var(--text-muted);">
                    Showing the <?php echo $limit; ?> most recent records.
                    <a href="?<?php echo http_build_query(['search'=>$search,'limit'=>min($limit+100,500)]); ?>"
                       style="color:var(--amber-dark); font-weight:600; margin-left:4px;">
                        Load more →
                    </a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="script.js?v=2"></script>
</body>
</html>
