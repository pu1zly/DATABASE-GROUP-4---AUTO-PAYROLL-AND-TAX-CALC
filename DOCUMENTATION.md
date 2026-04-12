# Automated Payroll & Tax Calculator System
**Final Project: Lesson 9**

This project is a modular, automated payroll and tax calculator designed for easy implementation using XAMPP (PHP/MySQL) and Python. It ensures payment integrity through SQL database transactions and a modular architecture.

---

## 1. System Architecture (Task Distribution)

### **Student A: Database Lead**
*   **ERD & SQL Scripting:** Designed the schema in `schema.sql`.
*   **Transaction Logic:** Implemented `ProcessPayroll` stored procedure to ensure atomic operations (calculating pay and marking shifts as processed in one transaction).

### **Student B: Backend (Python)**
*   **Payroll Engine:** `payroll_engine.py` handles the logic for connecting to the database and invoking the SQL transaction logic.
*   **Modular Design:** Can be easily extended to include RFID listeners or external API connections.

### **Student C: Web/PHP**
*   **CRUD Portal:** `php/index.php` and `php/db.php` provide the web interface for managing employees, logging shifts, and adding deductions.
*   **XAMPP Compatibility:** Designed to run in any standard XAMPP `htdocs` environment.

### **Student D: Frontend/UI**
*   **CSS Styling:** `php/style.css` provides a modern, responsive layout.
*   **JS Validations:** `php/script.js` ensures data integrity at the user input level (e.g., preventing negative hours or invalid dates).

---

## 2. Implementation Guide (How to "Piece it Together")

### **Step 1: Database Setup**
1.  Open XAMPP and start **Apache** and **MySQL**.
2.  Go to `phpMyAdmin` (usually `http://localhost/phpmyadmin`).
3.  Create a new database named `payroll_db`.
4.  Import the `schema.sql` file provided in the project folder.

### **Step 2: PHP Portal Setup**
1.  Copy the contents of the `php/` folder into your XAMPP `htdocs` directory (e.g., `C:/xampp/htdocs/payroll/`).
2.  Access the portal at `http://localhost/payroll/index.php`.

### **Step 3: Python Backend Setup**
1.  Install the required Python library:
    ```bash
    pip install mysql-connector-python
    ```
2.  Run the `payroll_engine.py` to test the connection or integrate it into your automated processing tasks.

---

## 3. Key Features
*   **Modular Deductions:** Add any number of deductions (SSS, PhilHealth, etc.) per employee.
*   **Payment Integrity:** Uses `START TRANSACTION` and `COMMIT` in SQL to prevent partial updates.
*   **Shift Management:** Tracks clock-in/out and calculates hours automatically.
*   **Tax Calculation:** Automatic tax deduction based on employee-specific tax rates.
