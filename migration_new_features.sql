-- ============================================================
-- Migration: New Features
-- Adds: audit_log, notifications, user_settings tables
-- Run AFTER schema.sql (or on existing payroll_db)
-- ============================================================

USE payroll_db;

-- ============================================================
-- TABLE: audit_log
-- Tracks all write actions by users
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NULL,
    username     VARCHAR(50),
    action       ENUM('add','edit','delete','deactivate','reactivate','payroll','export','import','login','logout','settings') NOT NULL,
    target_type  VARCHAR(50),              -- 'employee', 'payroll', 'user', etc.
    target_id    INT NULL,
    description  TEXT,
    ip_address   VARCHAR(45),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: notifications
-- Per-user in-app notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,                   -- NULL = broadcast to all users
    type       ENUM('info','warning','success','danger') DEFAULT 'info',
    title      VARCHAR(150) NOT NULL,
    message    TEXT,
    is_read    BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: user_settings
-- Per-user UI and payroll preferences
-- ============================================================
CREATE TABLE IF NOT EXISTS user_settings (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL UNIQUE,
    theme                ENUM('light','dark','system') DEFAULT 'light',
    accent_color         VARCHAR(7)  DEFAULT '#e28413',
    density              ENUM('compact','comfortable','spacious') DEFAULT 'comfortable',
    default_currency     ENUM('USD','PHP') DEFAULT 'USD',
    exchange_rate        DECIMAL(8,4) DEFAULT 56.0000,
    default_pay_period   ENUM('monthly','semi-monthly','bi-weekly') DEFAULT 'monthly',
    notify_payroll_due   BOOLEAN DEFAULT TRUE,
    notify_new_employee  BOOLEAN DEFAULT TRUE,
    notify_cap_warning   BOOLEAN DEFAULT TRUE,
    notify_export        BOOLEAN DEFAULT FALSE,
    high_contrast        BOOLEAN DEFAULT FALSE,
    reduce_motion        BOOLEAN DEFAULT FALSE,
    large_text           BOOLEAN DEFAULT FALSE,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

COMMIT;
