<?php
// notifications.php - In-App Notifications Page
session_start();
require_once 'db.php';

if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user = getCurrentUser();

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'mark_read' && isset($_POST['id'])) {
        markNotificationRead($pdo, (int)$_POST['id'], $current_user['id']);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($_POST['action'] === 'mark_all_read') {
        markAllNotificationsRead($pdo, $current_user['id']);
        logAudit($pdo, 'notifications', 'user', $current_user['id'], "Marked all notifications as read");
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// Fetch notifications
$notifications  = getNotifications($pdo, $current_user['id']);
$unread_total   = countUnreadNotifications($pdo, $current_user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Notification list ─────────────────────────────── */
        .notif-list { display: flex; flex-direction: column; gap: 0; }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-subtle);
            transition: background .15s;
            position: relative;
            cursor: pointer;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: var(--linen-light); }

        .notif-item.unread {
            background: var(--amber-soft);
            border-left: 3px solid var(--amber);
        }
        .notif-item.unread:hover { background: #fde8c4; }

        .notif-icon {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .notif-icon.info    { background: #eff6ff; }
        .notif-icon.success { background: var(--success-soft); }
        .notif-icon.warning { background: var(--amber-soft); }
        .notif-icon.error   { background: var(--amaranth-soft); }

        .notif-body { flex: 1; min-width: 0; }
        .notif-title {
            font-size: .9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 3px;
            line-height: 1.3;
        }
        .notif-msg {
            font-size: .82rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .notif-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
        }
        .notif-time {
            font-size: .72rem;
            color: var(--text-faint);
            font-family: var(--font-mono);
        }
        .notif-type-badge {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 20px;
        }
        .notif-type-badge.info    { background: #dbeafe; color: #1d4ed8; }
        .notif-type-badge.success { background: var(--success-soft); color: var(--success); }
        .notif-type-badge.warning { background: var(--amber-soft); color: var(--amber-dark); border: 1px solid rgba(226,132,19,.2); }
        .notif-type-badge.error   { background: var(--amaranth-soft); color: var(--amaranth); }

        .notif-unread-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--amber);
            flex-shrink: 0;
            margin-top: 6px;
        }

        .notif-read-btn {
            flex-shrink: 0;
            padding: 5px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--text-muted);
            font-size: .72rem;
            font-weight: 600;
            font-family: var(--font-body);
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
            letter-spacing: .02em;
        }
        .notif-read-btn:hover {
            background: var(--linen-dark);
            color: var(--text-dark);
            border-color: var(--dust-dark);
        }

        .empty-state {
            text-align: center;
            padding: 64px 20px;
            color: var(--text-muted);
        }
        .empty-state .es-icon { font-size: 3rem; margin-bottom: 16px; opacity: .5; }
        .empty-state h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-mid); margin-bottom: 6px; }
        .empty-state p  { font-size: .875rem; }

        .notif-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .notif-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--amber-soft);
            border: 1px solid rgba(226,132,19,.2);
            color: var(--amber-dark);
            font-size: .8rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .filter-tabs {
            display: flex;
            gap: 4px;
            background: var(--linen-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 3px;
        }
        .filter-tab {
            padding: 6px 14px;
            border-radius: 5px;
            border: none;
            background: transparent;
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s;
            font-family: var(--font-body);
        }
        .filter-tab.active {
            background: var(--surface-raised);
            color: var(--text-dark);
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body class="paged-layout">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <header class="content-header">
            <h1>Notifications</h1>
            <p>System alerts and activity updates</p>
        </header>

        <section class="card" style="margin: 0 44px 22px;">
            <div class="notif-toolbar">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <?php if ($unread_total > 0): ?>
                        <span class="notif-count-badge">
                            🔔 <?php echo $unread_total; ?> unread
                        </span>
                    <?php else: ?>
                        <span style="font-size:.85rem; color:var(--text-muted);">All caught up!</span>
                    <?php endif; ?>

                    <div class="filter-tabs" id="filter-tabs">
                        <button class="filter-tab active" data-filter="all">All</button>
                        <button class="filter-tab" data-filter="unread">Unread</button>
                        <button class="filter-tab" data-filter="success">Success</button>
                        <button class="filter-tab" data-filter="info">Info</button>
                        <button class="filter-tab" data-filter="warning">Warning</button>
                        <button class="filter-tab" data-filter="error">Error</button>
                    </div>
                </div>

                <?php if ($unread_total > 0): ?>
                <button class="btn-secondary" id="mark-all-btn" style="font-size:.8rem; padding:8px 14px;">
                    ✓ Mark all as read
                </button>
                <?php endif; ?>
            </div>

            <div class="notif-list" id="notif-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="es-icon">🔔</div>
                        <h3>No notifications yet</h3>
                        <p>System events like new employees, payroll processing, and exports will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $type_icons = [
                        'info'    => '💬',
                        'success' => '✅',
                        'warning' => '⚠️',
                        'error'   => '❌',
                    ];
                    foreach ($notifications as $notif):
                        $type    = $notif['type'] ?? 'info';
                        $is_read = (bool)$notif['is_read'];
                        $icon    = $type_icons[$type] ?? '💬';
                    ?>
                    <div class="notif-item <?php echo $is_read ? '' : 'unread'; ?>"
                         data-id="<?php echo $notif['id']; ?>"
                         data-type="<?php echo htmlspecialchars($type); ?>"
                         data-read="<?php echo $is_read ? '1' : '0'; ?>">

                        <div class="notif-icon <?php echo htmlspecialchars($type); ?>"><?php echo $icon; ?></div>

                        <div class="notif-body">
                            <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notif-meta">
                                <span class="notif-time"><?php echo date('M d, Y · H:i', strtotime($notif['created_at'])); ?></span>
                                <span class="notif-type-badge <?php echo htmlspecialchars($type); ?>"><?php echo ucfirst($type); ?></span>
                            </div>
                        </div>

                        <?php if (!$is_read): ?>
                            <div class="notif-unread-dot" title="Unread"></div>
                            <button class="notif-read-btn" data-id="<?php echo $notif['id']; ?>">Mark read</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="script.js?v=2"></script>
    <script>
    // ── Mark single notification as read ────────────────────
    document.querySelectorAll('.notif-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id  = this.dataset.id;
            const row = this.closest('.notif-item');
            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_read&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    row.classList.remove('unread');
                    row.dataset.read = '1';
                    this.remove();
                    row.querySelector('.notif-unread-dot')?.remove();
                    updateUnreadBadge();
                }
            });
        });
    });

    // ── Mark all as read ────────────────────────────────────
    document.getElementById('mark-all-btn')?.addEventListener('click', function() {
        fetch('notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notif-item.unread').forEach(row => {
                    row.classList.remove('unread');
                    row.dataset.read = '1';
                    row.querySelector('.notif-unread-dot')?.remove();
                    row.querySelector('.notif-read-btn')?.remove();
                });
                this.remove();
                document.querySelector('.notif-count-badge')?.remove();
                const statusEl = document.createElement('span');
                statusEl.style.cssText = 'font-size:.85rem; color:var(--text-muted);';
                statusEl.textContent   = 'All caught up!';
                this.closest('.notif-toolbar').querySelector('div').prepend(statusEl);
            }
        });
    });

    // ── Filter tabs ─────────────────────────────────────────
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.notif-item').forEach(item => {
                if (filter === 'all') {
                    item.style.display = '';
                } else if (filter === 'unread') {
                    item.style.display = item.dataset.read === '0' ? '' : 'none';
                } else {
                    item.style.display = item.dataset.type === filter ? '' : 'none';
                }
            });
        });
    });

    function updateUnreadBadge() {
        const remaining = document.querySelectorAll('.notif-item[data-read="0"]').length;
        const badge = document.querySelector('.notif-count-badge');
        if (badge) {
            if (remaining === 0) {
                badge.remove();
            } else {
                badge.textContent = `🔔 ${remaining} unread`;
            }
        }
    }
    </script>
</body>
</html>
