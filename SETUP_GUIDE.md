# Step-by-Step Setup Guide

Follow these steps to "piece together" the automated payroll and tax calculator system using XAMPP and Python.
(**download kayo ng XAMPP love you**)
---

### **Step 1: Start XAMPP Services**
1.  Open the **XAMPP Control Panel**.
2.  Click **Start** next to **Apache**.
3.  Click **Start** next to **MySQL**.

---

### **Step 2: Database Setup (Student A)**
1.  Open your web browser and go to `http://localhost/phpmyadmin`.
2.  Click on the **New** tab in the left sidebar.
3.  Enter `payroll_db` as the database name and click **Create**.
4.  Click on the `payroll_db` database you just created.
5.  Click the **Import** tab at the top.
6.  Click **Choose File** and select the `schema.sql` file from the project folder.
7.  Scroll to the bottom and click **Go** (or **Import**).
    *   *This sets up the tables and the stored procedure for transaction integrity.*

---

### **Step 3: Web Portal Setup (Student C & D)**
1.  Navigate to your XAMPP installation folder (usually `C:\xampp\htdocs`).
2.  Create a new folder named `payroll`.
3.  Copy all files from the `php/` folder of the project into `C:\xampp\htdocs\payroll\`.
    *   *Files to copy: `index.php`, `db.php`, `style.css`, `script.js`.*
4.  In your browser, go to `http://localhost/payroll/index.php`.
    *   *You should now see the Payroll & Tax Management Portal.*

---

### **Step 4: Python Backend Setup (Student B)**
1.  Ensure you have Python installed on your computer.
2.  Open a terminal (Command Prompt or PowerShell).
3.  Install the MySQL connector library:
    ```bash
    pip install mysql-connector-python
    ```
4.  Place `payroll_engine.py` in a folder of your choice. <- kung saan nyo linagay, yun yung folder location
5.  To test the connection, run the script:
    ```bash

    **IMPORTANT**
    'pag irurun nyo sa terminal gawin nyo muna is palitan yung directory like: cd C:\Users\Floyd\Downloads\test\payroll_system_v4. kumbaga yung location ng payroll_system_v4. then run: python payroll_engine.py'
    
    ```
    *   *It should print "Successfully connected to payroll database."*

---

### **Step 5: Testing the Workflow**
1.  **Add an Employee:** Use the web portal to add a test employee (e.g., John Doe, $20/hr, 10% tax).
2.  **Log a Shift:** Select the employee and log a shift (e.g., 8 hours).
3.  **Add a Deduction:** Add a deduction like "SSS" for $50.
4.  **Process Payroll:** You can now use the `payroll_engine.py` or call the `ProcessPayroll` procedure in SQL to calculate the final net pay with transaction integrity.
