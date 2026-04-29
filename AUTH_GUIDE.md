# Authentication System Guide

## Overview

The NetGain Payroll System now includes a complete user authentication and registration system with secure password handling and duplicate credential prevention.

## What's New

### 1. **New Database Table: `users`**

A new table has been added to store user credentials:

```sql
CREATE TABLE users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) UNIQUE NOT NULL,
    email               VARCHAR(100) UNIQUE NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    full_name           VARCHAR(100),
    role                ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login          TIMESTAMP NULL
)
```

**Key Features:**
- `username` - Unique username (required for login)
- `email` - Unique email address (required during registration)
- `password_hash` - Securely hashed password using bcrypt
- `full_name` - Optional full name for display
- `role` - User role (admin, manager, or staff)
- `is_active` - Account status
- `created_at` - Account creation timestamp
- `last_login` - Last login timestamp

### 2. **Files Added/Modified**

#### New Files:
- **`login.php`** - Combined login and registration page
- **`logout.php`** - Session cleanup and redirect

#### Modified Files:
- **`db.php`** - Added authentication functions
- **`index.php`** - Added session protection
- **`phase1.php`** - Added session protection
- **`phase2.php`** - Added session protection
- **`sidebar.php`** - Added user profile display and logout button
- **`style.css`** - Added styles for user profile and logout button
- **`schema.sql`** - Added users table definition

### 3. **Authentication Functions in `db.php`**

#### `checkCredentialsExists($pdo, $username, $email)`
Checks if a username or email already exists in the database.

**Returns:**
```php
[
    'username_exists' => boolean,
    'email_exists' => boolean
]
```

#### `registerUser($pdo, $username, $email, $password, $full_name, $role)`
Registers a new user with validation and duplicate checking.

**Returns:**
```php
[
    'success' => boolean,
    'message' => string
]
```

**Validations:**
- Username must be at least 3 characters
- Email must be valid format
- Password must be at least 6 characters
- Passwords must match (confirmation)
- Username must not already exist
- Email must not already exist

#### `authenticateUser($pdo, $username, $password)`
Authenticates a user and starts a session.

**Returns:**
```php
[
    'success' => boolean,
    'user' => [
        'id' => int,
        'username' => string,
        'email' => string,
        'full_name' => string,
        'role' => string
    ]
]
```

#### `isUserLoggedIn()`
Checks if a user is currently logged in.

**Returns:** `boolean`

#### `getCurrentUser()`
Gets the current logged-in user's data.

**Returns:** `array` or `null`

## How to Use

### 1. **Database Setup**

Run the updated `schema.sql` file to create the users table:

```bash
mysql -u root -p payroll_db < schema.sql
```

Or execute the schema manually in phpMyAdmin/MySQL Workbench.

### 2. **Register a New User**

1. Navigate to `login.php`
2. Click "Create an account" link
3. Fill in the registration form:
   - **Full Name** (optional)
   - **Username** (required, 3+ characters, must be unique)
   - **Email** (required, must be unique)
   - **Password** (required, 6+ characters)
   - **Confirm Password** (required, must match)
4. Click "Create Account"
5. On success, you'll see a confirmation message
6. Click "Back to Login" to log in with your new credentials

### 3. **Duplicate Credential Prevention**

The system prevents duplicate registrations by checking:

**Username Check:**
- If the username already exists, you'll see the error:
  ```
  Username already exists. Please choose a different username.
  ```

**Email Check:**
- If the email already exists, you'll see the error:
  ```
  Email already registered. Please use a different email or login instead.
  ```

These checks happen during the registration process, preventing duplicate data in the database.

### 4. **Login**

1. Navigate to `login.php`
2. Enter your credentials:
   - **Username** - Your registered username
   - **Password** - Your registered password
3. Click "Sign In"
4. On success, you'll be redirected to the dashboard
5. On failure, you'll see: "Invalid username or password."

### 5. **Logout**

1. Look at the sidebar footer (bottom-left)
2. You'll see your user profile with:
   - Avatar with your initial
   - Your full name or username
   - Your role (admin, manager, staff)
3. Click the "🚪 Logout" button
4. Your session will be cleared and you'll be redirected to login

### 6. **Session Protection**

All dashboard pages require authentication:
- `index.php` - Dashboard
- `phase1.php` - Employee Configuration
- `phase2.php` - Timesheet Entry

If you try to access these pages without logging in, you'll be automatically redirected to `login.php`.

## Security Features

### 1. **Password Hashing**
- Passwords are hashed using PHP's `password_hash()` with bcrypt algorithm
- Passwords are never stored in plain text
- Verification uses `password_verify()` for secure comparison

### 2. **Unique Constraints**
- Username UNIQUE constraint in database
- Email UNIQUE constraint in database
- Prevents SQL injection through prepared statements (PDO)

### 3. **Session Management**
- Sessions use PHP's built-in `$_SESSION` superglobal
- Session data includes user ID, username, email, full name, and role
- `session_destroy()` clears all session data on logout

### 4. **Last Login Tracking**
- `last_login` timestamp is updated whenever a user logs in
- Useful for security audits and user activity tracking

## API Reference

### User Registration Flow

```php
// Step 1: Check for duplicates
$exists = checkCredentialsExists($pdo, $username, $email);
if ($exists['username_exists'] || $exists['email_exists']) {
    // Handle duplicate error
}

// Step 2: Register user
$result = registerUser($pdo, $username, $email, $password, $full_name);
if ($result['success']) {
    // Redirect to login
}
```

### User Login Flow

```php
// Step 1: Authenticate
$result = authenticateUser($pdo, $username, $password);

// Step 2: Store session
if ($result['success']) {
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user'] = $result['user'];
    // Redirect to dashboard
}
```

### Session Checking

```php
// Protect a page
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user
$user = getCurrentUser();
echo $user['username']; // Display username
```

## Troubleshooting

### 1. "Database connection error"
- Ensure MySQL server is running
- Check credentials in `db.php` (host, user, password)
- Verify database `payroll_db` exists

### 2. "Username already exists"
- Try a different username
- Usernames are case-sensitive
- Minimum 3 characters required

### 3. "Email already registered"
- Use a different email address
- Each email can only be registered once
- Consider resetting password if it's your old account

### 4. "Passwords do not match"
- Ensure both password fields are identical
- Check for extra spaces or typos
- Passwords are case-sensitive

### 5. "Invalid username or password"
- Verify username and password are correct
- Check for extra spaces
- Usernames and passwords are case-sensitive
- Ensure your account is active

### 6. Session Not Persisting
- Ensure PHP sessions are enabled on the server
- Check `session.save_path` configuration
- Clear browser cookies if having issues
- Try accessing from an incognito/private window

## Default Roles

Users can be assigned one of three roles:

| Role | Purpose |
|------|---------|
| `admin` | Full system access and administration |
| `manager` | Can manage employees and payroll |
| `staff` | Standard user access (default for new registrations) |

**Note:** Role enforcement is not yet implemented in the UI, but the infrastructure is in place for future use.

## Best Practices

1. **Strong Passwords**
   - Use at least 8 characters (minimum required: 6)
   - Mix uppercase, lowercase, numbers, and symbols
   - Don't use usernames as passwords

2. **Account Security**
   - Don't share your credentials
   - Log out after using the system
   - Regularly update your password

3. **Data Management**
   - Keep backups of the database
   - Monitor failed login attempts
   - Review the `last_login` field periodically

## Future Enhancements

Potential improvements to the authentication system:

- [ ] Password reset functionality
- [ ] Email verification
- [ ] Two-factor authentication (2FA)
- [ ] Role-based access control (RBAC) in UI
- [ ] Account lockout after failed attempts
- [ ] Password strength meter
- [ ] Social login integration
- [ ] API token authentication
- [ ] Session timeout
- [ ] Login attempt history
