<?php
// login.php - Authentication Page (Login & Registration)
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$error_message = '';
$success_message = '';
$is_register_mode = isset($_GET['mode']) && $_GET['mode'] === 'register';

// Handle Registration
if ($is_register_mode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validation
    if (empty($username)) {
        $error_message = 'Username is required.';
    } elseif (empty($email)) {
        $error_message = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif (empty($password)) {
        $error_message = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($username) < 3) {
        $error_message = 'Username must be at least 3 characters long.';
    } else {
        // Attempt registration
        $result = registerUser($pdo, $username, $email, $password, $full_name, 'staff');
        if ($result['success']) {
            $success_message = $result['message'];
            $_GET['mode'] = 'login'; // Switch to login mode
            $is_register_mode = false;
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle Login
if (!$is_register_mode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        $result = authenticateUser($pdo, $username, $password);
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['user'] = $result['user'];
            header('Location: index.php');
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_register_mode ? 'Register' : 'Login'; ?> — NetGain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&family=Libre+Franklin:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gunmetal:      #2e3532;
            --gunmetal-deep: #1d2220;
            --gunmetal-mid:  #3d4541;
            --amaranth:      #8b2635;
            --amber:         #e28413;
            --amber-dark:    #c4720f;
            --linen:         #e0e2db;
            --dust:          #d2d4c8;
            --text-dark:     #1a1f1d;
            --text-muted:    #727a74;
            --border:        #d2d4c8;
            --surface:       #f7f7f4;
            --font-display:  'Bebas Neue', 'Arial Narrow', sans-serif;
            --font-body:     'Libre Franklin', 'Helvetica Neue', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: var(--font-body);
            background: var(--gunmetal-deep);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            min-height: 100vh;
            position: relative;
        }

        /* Subtle texture overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 40px,
                rgba(255,255,255,.012) 40px,
                rgba(255,255,255,.012) 80px
            );
            pointer-events: none;
        }

        .auth-wrapper {
            display: flex;
            width: 100%;
            max-width: 860px;
            min-height: 540px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0,0,0,.5);
            position: relative;
            z-index: 1;
        }

        /* Left panel — brand */
        .auth-brand {
            width: 280px;
            flex-shrink: 0;
            background: var(--gunmetal);
            border-right: 2px solid var(--amaranth);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 36px 28px;
        }

        .auth-brand-top { }

        .brand-eyebrow {
            font-size: .58rem;
            font-weight: 700;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #4f5c57;
            margin-bottom: 6px;
        }

        .brand-name {
            font-family: var(--font-display);
            font-size: 3rem;
            color: #dde1df;
            letter-spacing: .06em;
            line-height: 1;
        }

        .brand-tagline {
            margin-top: 14px;
            font-size: .8rem;
            color: #5a6460;
            line-height: 1.6;
        }

        .brand-accent {
            width: 40px;
            height: 3px;
            background: var(--amber);
            border-radius: 2px;
            margin-top: 20px;
        }

        .brand-footer {
            font-size: .72rem;
            color: #3a4540;
            letter-spacing: .04em;
        }

        /* Right panel — form */
        .auth-content {
            flex: 1;
            background: var(--surface);
            padding: 40px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-title {
            font-family: var(--font-display);
            font-size: 1.8rem;
            color: var(--text-dark);
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .auth-subtitle {
            font-size: .82rem;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: .65rem;
            font-weight: 700;
            color: var(--gunmetal-mid);
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: .1em;
        }

        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: .88rem;
            font-family: inherit;
            background: #fff;
            color: var(--text-dark);
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--amber);
            box-shadow: 0 0 0 3px rgba(226,132,19,.15);
        }

        .form-group input::placeholder { color: #b0b6b2; }

        .alert {
            padding: 11px 14px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: .83rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fdf0f1;
            border: 1px solid #e8bec3;
            color: #7a1e2a;
        }

        .alert-success {
            background: #eaf2e8;
            border: 1px solid #b5d4b0;
            color: #2e5229;
        }

        .btn {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 7px;
            font-size: .8rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--amaranth);
            color: #fff;
        }

        .btn-primary:hover {
            background: #7a1e2a;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(139,38,53,.3);
        }

        .btn-primary:active { transform: translateY(0); }

        .divider {
            display: flex;
            align-items: center;
            margin: 22px 0;
            gap: 12px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: .75rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .auth-toggle {
            text-align: center;
            font-size: .83rem;
            color: var(--text-muted);
        }

        .auth-toggle a {
            color: var(--amaranth);
            text-decoration: none;
            font-weight: 700;
            transition: color .2s;
        }

        .auth-toggle a:hover { color: var(--amber); }

        .password-requirements {
            background: #f0f1ec;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .password-requirements ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }

        .password-requirements li { margin: 3px 0; }

        @media (max-width: 640px) {
            .auth-wrapper { flex-direction: column; max-width: 440px; }
            .auth-brand {
                width: 100%;
                flex-direction: row;
                align-items: center;
                padding: 20px 24px;
                border-right: none;
                border-bottom: 2px solid var(--amaranth);
            }
            .auth-brand-top { display: flex; align-items: center; gap: 16px; }
            .brand-tagline, .brand-accent, .brand-footer { display: none; }
            .brand-name { font-size: 2rem; }
            .auth-content { padding: 28px 24px; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <!-- Brand Panel -->
        <div class="auth-brand">
            <div class="auth-brand-top">
                <div class="brand-eyebrow">Payroll System</div>
                <div class="brand-name">NetGain</div>
                <div class="brand-accent"></div>
                <p class="brand-tagline">Streamlined payroll management for modern teams.</p>
            </div>
            <div class="brand-footer">© <?php echo date('Y'); ?> NetGain</div>
        </div>

        <!-- Form Panel -->
        <div class="auth-content">
            <div class="auth-title"><?php echo $is_register_mode ? 'Create Account' : 'Welcome Back'; ?></div>
            <div class="auth-subtitle"><?php echo $is_register_mode ? 'Fill in the details below to register.' : 'Sign in to access your dashboard.'; ?></div>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_register_mode): ?>
                <!-- REGISTRATION FORM -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            placeholder="John Doe"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="johndoe"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="john@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="••••••••"
                            required
                        >
                        <div class="password-requirements">
                            <strong>Password must contain:</strong>
                            <ul>
                                <li>At least 6 characters</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="••••••••"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>

                <div class="divider">
                    <span>Already have an account?</span>
                </div>

                <div class="auth-toggle">
                    <a href="login.php?mode=login">Back to Login</a>
                </div>

            <?php else: ?>
                <!-- LOGIN FORM -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="johndoe"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="••••••••"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>

                <div class="divider">
                    <span>New to NetGain?</span>
                </div>

                <div class="auth-toggle">
                    <a href="login.php?mode=register">Create an account</a>
                </div>

            <?php endif; ?>
        </div><!-- /auth-content -->
    </div><!-- /auth-wrapper -->
</body>
</html>