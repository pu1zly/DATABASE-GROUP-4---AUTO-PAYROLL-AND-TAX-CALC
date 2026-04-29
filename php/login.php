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
    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #DBEAFE;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --text: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --bg: #FFFFFF;
            --bg-light: #F9FAFB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .auth-container {
            background: var(--bg);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .auth-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 32px 24px;
            text-align: center;
            color: white;
        }

        .auth-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .auth-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .auth-content {
            padding: 32px 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #DC2626;
        }

        .alert-success {
            background: #DCFCE7;
            border: 1px solid #BBF7D0;
            color: #16A34A;
        }

        .btn {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            gap: 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: 13px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .auth-toggle {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
        }

        .auth-toggle a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-toggle a:hover {
            color: var(--primary-dark);
        }

        .password-requirements {
            background: var(--bg-light);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .password-requirements ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }

        .password-requirements li {
            margin: 4px 0;
        }

        @media (max-width: 480px) {
            .auth-container {
                max-width: 100%;
            }

            .auth-header {
                padding: 24px 20px;
            }

            .auth-header h1 {
                font-size: 24px;
            }

            .auth-content {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>NetGain</h1>
            <p><?php echo $is_register_mode ? 'Create your account' : 'Welcome back'; ?></p>
        </div>

        <div class="auth-content">
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
        </div>
    </div>
</body>
</html>
