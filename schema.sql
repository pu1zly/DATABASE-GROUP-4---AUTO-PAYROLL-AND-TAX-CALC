-- ============================================================
-- Automated Payroll System - Database Schema
-- Includes: Tables + Stored Procedure with ACID Transaction
-- ============================================================

CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- ============================================================
-- TABLE 1: employees
-- Stores employee configuration data (Phase 1)
-- ============================================================
CREATE TABLE IF NOT EXISTS employees (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    employee_id_code    VARCHAR(50) UNIQUE NOT NULL,  -- Custom ID Code
    full_name           VARCHAR(100) NOT NULL,
    position            ENUM('Intern', 'Contractor', 'Regular Staff', 'Manager', 'Custom') NOT NULL,
    hourly_rate         DECIMAL(10, 2) NOT NULL,
    tax_rate            DECIMAL(5, 2) NOT NULL,        -- Percentage (0, 10, 20, 30, or Custom)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 2: daily_logs
-- Daily timesheet entries per employee (Phase 2)
-- ============================================================
CREATE TABLE IF NOT EXISTS daily_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT NOT NULL,
    log_date        DATE NOT NULL,
    reg_hours       DECIMAL(5, 2) DEFAULT 0.00,
    ot_hours        DECIMAL(5, 2) DEFAULT 0.00,
    sick_hours      DECIMAL(5, 2) DEFAULT 0.00,
    vac_hours       DECIMAL(5, 2) DEFAULT 0.00,
    is_day_off      BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_log (employee_id, log_date)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 3: monthly_work
-- Aggregated monthly hours summary (derived from daily_logs)
-- ============================================================
CREATE TABLE IF NOT EXISTS monthly_work (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    employee_id             INT NOT NULL,
    month_year              DATE NOT NULL,   -- Stored as first day of the month
    reg_hours               DECIMAL(10, 2) DEFAULT 0.00,
    ot_hours                DECIMAL(10, 2) DEFAULT 0.00,
    sick_hours              DECIMAL(10, 2) DEFAULT 0.00,
    vac_hours               DECIMAL(10, 2) DEFAULT 0.00,
    total_monthly_hours     DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 4: payroll_records
-- Final payroll output after the Calculation Waterfall (Phase 3 & 4)
-- ============================================================
CREATE TABLE IF NOT EXISTS payroll_records (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    employee_id             INT NOT NULL,
    month_year              DATE NOT NULL,
    gross_income            DECIMAL(10, 2) NOT NULL,
    sss_deduction           DECIMAL(10, 2) NOT NULL,   -- 5% of Gross, capped at $25
    philhealth_deduction    DECIMAL(10, 2) NOT NULL,   -- 2.5% of Gross
    pagibig_deduction       DECIMAL(10, 2) NOT NULL,   -- Flat $4.00
    taxable_income          DECIMAL(10, 2) NOT NULL,   -- Gross - (SSS + PH + PI)
    total_tax_withheld      DECIMAL(10, 2) NOT NULL,   -- Taxable x Tax Rate
    net_income              DECIMAL(10, 2) NOT NULL,   -- Final Take-Home Pay
    processed_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- STORED PROCEDURE: ProcessPayroll
--
-- Calculates and saves one employee's payroll for a given
-- date range inside a single ACID transaction.
--
-- If ANY step fails, the entire operation is rolled back
-- so no partial or corrupt data is ever saved.
--
-- Usage:
--   CALL ProcessPayroll(1, '2025-01-01', '2025-01-31');
-- ============================================================

DELIMITER $$

CREATE PROCEDURE ProcessPayroll(
    IN p_employee_id    INT,
    IN p_start_date     DATE,
    IN p_end_date       DATE
)
BEGIN
    -- ---- Local variables for the calculation waterfall ----
    DECLARE v_hourly_rate       DECIMAL(10, 2);
    DECLARE v_tax_rate          DECIMAL(5, 2);
    DECLARE v_reg_hours         DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_ot_hours          DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_total_hours       DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_gross             DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_sss               DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_philhealth        DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_pagibig           DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_taxable           DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_tax               DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_net               DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_month_year        DATE;

    -- ---- Error handler: rolls back ALL changes on any SQL error ----
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;  -- Re-throws the error so the caller knows what went wrong
    END;

    -- Derive the month_year value (always the 1st of the start month)
    SET v_month_year = DATE_FORMAT(p_start_date, '%Y-%m-01');

    -- ===========================================================
    -- START TRANSACTION
    -- All reads and writes below are atomic. Either ALL succeed
    -- or NONE are committed to the database.
    -- ===========================================================
    START TRANSACTION;

        -- STEP 1: Fetch employee hourly rate and tax rate
        SELECT hourly_rate, tax_rate
        INTO v_hourly_rate, v_tax_rate
        FROM employees
        WHERE id = p_employee_id;

        -- STEP 2: Aggregate regular and overtime hours from daily_logs
        SELECT
            COALESCE(SUM(reg_hours), 0),
            COALESCE(SUM(ot_hours), 0)
        INTO v_reg_hours, v_ot_hours
        FROM daily_logs
        WHERE employee_id = p_employee_id
          AND log_date BETWEEN p_start_date AND p_end_date
          AND is_day_off = FALSE;

        SET v_total_hours = v_reg_hours + v_ot_hours;

        -- STEP 3: Upsert the monthly_work summary
        INSERT INTO monthly_work (
            employee_id, month_year,
            reg_hours, ot_hours, total_monthly_hours
        )
        VALUES (
            p_employee_id, v_month_year,
            v_reg_hours, v_ot_hours, v_total_hours
        )
        ON DUPLICATE KEY UPDATE
            reg_hours           = v_reg_hours,
            ot_hours            = v_ot_hours,
            total_monthly_hours = v_total_hours;

        -- STEP 4: Calculation Waterfall (Phase 3)
        SET v_gross      = v_total_hours * v_hourly_rate;
        SET v_sss        = LEAST(v_gross * 0.05, 25.00);  -- 5% of Gross, capped at $25
        SET v_philhealth = v_gross * 0.025;               -- 2.5% of Gross
        SET v_pagibig    = 4.00;                          -- Flat $4.00
        SET v_taxable    = v_gross - (v_sss + v_philhealth + v_pagibig);
        SET v_tax        = v_taxable * (v_tax_rate / 100);
        SET v_net        = v_taxable - v_tax;             -- Final Take-Home Pay

        -- STEP 5: Write the final payroll record
        INSERT INTO payroll_records (
            employee_id,
            month_year,
            gross_income,
            sss_deduction,
            philhealth_deduction,
            pagibig_deduction,
            taxable_income,
            total_tax_withheld,
            net_income
        )
        VALUES (
            p_employee_id,
            v_month_year,
            v_gross,
            v_sss,
            v_philhealth,
            v_pagibig,
            v_taxable,
            v_tax,
            v_net
        );

    -- ===========================================================
    -- COMMIT
    -- Only reached if ALL steps above completed without error.
    -- ===========================================================
    COMMIT;

END$$

DELIMITER ;
