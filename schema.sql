-- Database Schema for Automated Payroll and Tax Calculator
-- Optimized for XAMPP (MySQL/MariaDB)

CREATE DATABASE IF NOT EXISTS payroll_db;
USE payroll_db;

-- 1. Employees Table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    tax_rate DECIMAL(5, 2) DEFAULT 0.00, -- Percentage
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Shifts Table
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME,
    total_hours DECIMAL(5, 2) DEFAULT 0.00,
    status ENUM('pending', 'processed') DEFAULT 'pending',
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Deductions Table (Modular for various types like SSS, PhilHealth, etc.)
CREATE TABLE IF NOT EXISTS deductions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Payroll Transactions Table (Ensures Payment Integrity)
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    gross_pay DECIMAL(10, 2) NOT NULL,
    total_deductions DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL,
    net_pay DECIMAL(10, 2) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transaction_hash VARCHAR(64), -- For integrity verification
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Stored Procedure for Atomic Payroll Processing (Transaction Logic)
DELIMITER //

CREATE PROCEDURE ProcessPayroll(
    IN p_employee_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    DECLARE v_gross_pay DECIMAL(10, 2) DEFAULT 0;
    DECLARE v_total_deductions DECIMAL(10, 2) DEFAULT 0;
    DECLARE v_tax_rate DECIMAL(5, 2);
    DECLARE v_tax_amount DECIMAL(10, 2);
    DECLARE v_net_pay DECIMAL(10, 2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payroll transaction failed';
    END;

    START TRANSACTION;

    -- 1. Calculate Gross Pay from shifts
    SELECT SUM(total_hours * e.hourly_rate) INTO v_gross_pay
    FROM shifts s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.employee_id = p_employee_id 
      AND s.clock_in >= p_start_date 
      AND s.clock_in <= p_end_date
      AND s.status = 'pending';

    -- 2. Get Tax Rate
    SELECT tax_rate INTO v_tax_rate FROM employees WHERE id = p_employee_id;

    -- 3. Calculate Total Deductions
    SELECT IFNULL(SUM(amount), 0) INTO v_total_deductions
    FROM deductions
    WHERE employee_id = p_employee_id;

    -- 4. Final Calculations
    SET v_tax_amount = v_gross_pay * (v_tax_rate / 100);
    SET v_net_pay = v_gross_pay - v_tax_amount - v_total_deductions;

    -- 5. Insert Record
    INSERT INTO payroll_records (employee_id, period_start, period_end, gross_pay, total_deductions, tax_amount, net_pay)
    VALUES (p_employee_id, p_start_date, p_end_date, v_gross_pay, v_total_deductions, v_tax_amount, v_net_pay);

    -- 6. Mark shifts as processed
    UPDATE shifts 
    SET status = 'processed'
    WHERE employee_id = p_employee_id 
      AND clock_in >= p_start_date 
      AND clock_in <= p_end_date;

    COMMIT;
END //

DELIMITER ;
