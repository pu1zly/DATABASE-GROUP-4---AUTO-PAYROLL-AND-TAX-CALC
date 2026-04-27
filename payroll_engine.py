import mysql.connector
from decimal import Decimal
import json
from datetime import date

class PayrollEngine:
    """
    Python backend for the Automated Payroll System.
    Connects to the MySQL payroll_db and invokes the ProcessPayroll
    stored procedure, which handles all ACID transaction logic internally.
    """

    def __init__(self, host="localhost", user="root", password="", database="payroll_db"):
        try:
            self.conn = mysql.connector.connect(
                host=host,
                user=user,
                password=password,
                database=database
            )
            self.cursor = self.conn.cursor(dictionary=True)
            print("Successfully connected to payroll database.")
        except mysql.connector.Error as err:
            print(f"Database connection error: {err}")
            self.conn = None

    # ------------------------------------------------------------------
    # CORE: Process payroll for one employee over a date range.
    # The stored procedure (ProcessPayroll) handles the full
    # calculation waterfall inside a single ACID transaction:
    #   START TRANSACTION -> aggregate hours -> calculate -> INSERT -> COMMIT
    #   On any error: automatic ROLLBACK via EXIT HANDLER in the procedure.
    # ------------------------------------------------------------------
    def calculate_payroll(self, employee_id, start_date, end_date):
        """
        Calls the ProcessPayroll stored procedure.
        Returns a dict with status and the resulting payroll record.

        Args:
            employee_id (int): The internal DB id of the employee.
            start_date  (str): Period start in 'YYYY-MM-DD' format.
            end_date    (str): Period end   in 'YYYY-MM-DD' format.
        """
        if not self.conn:
            return {"status": "error", "message": "No database connection."}

        try:
            # Delegate all transaction logic to the stored procedure.
            # ProcessPayroll runs START TRANSACTION ... COMMIT internally.
            self.cursor.callproc('ProcessPayroll', [employee_id, start_date, end_date])
            self.conn.commit()  # Finalise any implicit session state

            # Retrieve the payroll record that was just inserted
            query = """
                SELECT * FROM payroll_records
                WHERE employee_id = %s
                ORDER BY processed_at DESC
                LIMIT 1
            """
            self.cursor.execute(query, (employee_id,))
            result = self.cursor.fetchone()

            return {
                "status": "success",
                "data": result
            }

        except mysql.connector.Error as err:
            # The stored procedure's EXIT HANDLER already issued a ROLLBACK.
            # We call rollback() here as a safety net for any session-level state.
            self.conn.rollback()
            return {"status": "error", "message": str(err)}

    # ------------------------------------------------------------------
    # HELPER: Get a full employee summary including latest payroll record
    # and aggregated hours from daily_logs.
    # ------------------------------------------------------------------
    def get_employee_summary(self, employee_id):
        """
        Fetches employee config, all-time logged hours, and the most
        recent payroll record for a given employee.

        Args:
            employee_id (int): The internal DB id of the employee.

        Returns:
            dict | None: Summary dict or None if employee not found.
        """
        if not self.conn:
            return None

        # --- Employee config ---
        self.cursor.execute(
            "SELECT * FROM employees WHERE id = %s",
            (employee_id,)
        )
        employee = self.cursor.fetchone()

        if not employee:
            return None

        # --- Aggregated hours from daily_logs ---
        self.cursor.execute("""
            SELECT
                COALESCE(SUM(reg_hours),  0) AS total_reg_hours,
                COALESCE(SUM(ot_hours),   0) AS total_ot_hours,
                COALESCE(SUM(sick_hours), 0) AS total_sick_hours,
                COALESCE(SUM(vac_hours),  0) AS total_vac_hours,
                COALESCE(SUM(reg_hours + ot_hours), 0) AS total_billable_hours
            FROM daily_logs
            WHERE employee_id = %s
        """, (employee_id,))
        hours = self.cursor.fetchone()

        # --- Latest payroll record ---
        self.cursor.execute("""
            SELECT * FROM payroll_records
            WHERE employee_id = %s
            ORDER BY processed_at DESC
            LIMIT 1
        """, (employee_id,))
        latest_payroll = self.cursor.fetchone()

        return {
            "employee":       employee,
            "hours_summary":  hours,
            "latest_payroll": latest_payroll
        }

    # ------------------------------------------------------------------
    # HELPER: List all employees (useful for batch processing or reports)
    # ------------------------------------------------------------------
    def get_all_employees(self):
        """Returns a list of all employees in the database."""
        if not self.conn:
            return []

        self.cursor.execute("SELECT * FROM employees ORDER BY full_name")
        return self.cursor.fetchall()

    # ------------------------------------------------------------------
    # HELPER: Process payroll for ALL employees in a given period.
    # Each employee is processed in its own stored-procedure transaction,
    # so a failure for one employee does not block the others.
    # ------------------------------------------------------------------
    def batch_process_payroll(self, start_date, end_date):
        """
        Runs ProcessPayroll for every employee in the database.

        Returns:
            dict: { "succeeded": [...], "failed": [...] }
        """
        employees = self.get_all_employees()
        results = {"succeeded": [], "failed": []}

        for emp in employees:
            result = self.calculate_payroll(emp["id"], start_date, end_date)
            if result["status"] == "success":
                results["succeeded"].append({
                    "employee_id": emp["id"],
                    "name": emp["full_name"],
                    "net_income": result["data"]["net_income"] if result["data"] else None
                })
            else:
                results["failed"].append({
                    "employee_id": emp["id"],
                    "name": emp["full_name"],
                    "error": result["message"]
                })

        return results

    # ------------------------------------------------------------------
    # Cleanup
    # ------------------------------------------------------------------
    def close(self):
        """Close the cursor and database connection."""
        if self.conn:
            self.cursor.close()
            self.conn.close()
            print("Database connection closed.")


# ----------------------------------------------------------------------
# Example usage — runs when the script is executed directly
# ----------------------------------------------------------------------
if __name__ == "__main__":
    engine = PayrollEngine(
        host="localhost",
        user="root",
        password="",
        database="payroll_db"
    )

    if engine.conn:
        # --- Example 1: Process payroll for employee with id=1 ---
        print("\n--- Processing Payroll for Employee ID 1 ---")
        result = engine.calculate_payroll(
            employee_id=1,
            start_date="2025-01-01",
            end_date="2025-01-31"
        )
        print(json.dumps(result, indent=4, default=str))

        # --- Example 2: Get a full summary for employee with id=1 ---
        print("\n--- Employee Summary for ID 1 ---")
        summary = engine.get_employee_summary(1)
        print(json.dumps(summary, indent=4, default=str))

        # --- Example 3: Batch process all employees ---
        # print("\n--- Batch Processing All Employees ---")
        # batch_result = engine.batch_process_payroll("2025-01-01", "2025-01-31")
        # print(json.dumps(batch_result, indent=4, default=str))

    engine.close()
