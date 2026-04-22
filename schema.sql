-- Overhauled Database Schema for Automated Payroll System
-- Based on 4-Phase Specification

CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- 1. Employees Table (Phase 1: Configuration)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id_code VARCHAR(50) UNIQUE NOT NULL, -- Custom ID Code
    full_name VARCHAR(100) NOT NULL,
    position ENUM('Intern', 'Contractor', 'Regular Staff', 'Manager', 'Custom') NOT NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    tax_rate DECIMAL(5, 2) NOT NULL, -- Percentage (0, 10, 20, 30, or Custom)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Daily Timesheet Logs (Phase 2)
CREATE TABLE IF NOT EXISTS daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    log_date DATE NOT NULL,
    reg_hours DECIMAL(5, 2) DEFAULT 0.00,
    ot_hours DECIMAL(5, 2) DEFAULT 0.00,
    sick_hours DECIMAL(5, 2) DEFAULT 0.00,
    vac_hours DECIMAL(5, 2) DEFAULT 0.00,
    is_day_off BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY `unique_log` (employee_id, log_date)
) ENGINE=InnoDB;

-- 3. Monthly Work Summary (Aggregated from daily_logs)
CREATE TABLE IF NOT EXISTS monthly_work (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year DATE NOT NULL,
    reg_hours DECIMAL(10, 2) DEFAULT 0.00,
    ot_hours DECIMAL(10, 2) DEFAULT 0.00,
    sick_hours DECIMAL(10, 2) DEFAULT 0.00,
    vac_hours DECIMAL(10, 2) DEFAULT 0.00,
    total_monthly_hours DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Payroll Records (Phase 3 & 4: Calculation Waterfall & Final Output)
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year DATE NOT NULL,
    gross_income DECIMAL(10, 2) NOT NULL,
    sss_deduction DECIMAL(10, 2) NOT NULL,
    philhealth_deduction DECIMAL(10, 2) NOT NULL,
    pagibig_deduction DECIMAL(10, 2) NOT NULL,
    taxable_income DECIMAL(10, 2) NOT NULL,
    total_tax_withheld DECIMAL(10, 2) NOT NULL,
    net_income DECIMAL(10, 2) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;
