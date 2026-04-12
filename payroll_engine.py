import mysql.connector
from decimal import Decimal
import json

class PayrollEngine:
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
            print(f"Error: {err}")
            self.conn = None

    def calculate_payroll(self, employee_id, start_date, end_date):
        """Calls the SQL stored procedure for atomic transaction integrity."""
        if not self.conn:
            return {"status": "error", "message": "Database connection failed"}
        
        try:
            # Using the stored procedure for atomic transaction
            self.cursor.callproc('ProcessPayroll', [employee_id, start_date, end_date])
            self.conn.commit()
            
            # Fetch the latest record for this employee
            query = """
                SELECT * FROM payroll_records 
                WHERE employee_id = %s 
                ORDER BY processed_at DESC LIMIT 1
            """
            self.cursor.execute(query, (employee_id,))
            result = self.cursor.fetchone()
            
            return {
                "status": "success",
                "data": result
            }
        except mysql.connector.Error as err:
            self.conn.rollback()
            return {"status": "error", "message": str(err)}

    def get_employee_summary(self, employee_id):
        """Fetch all details for an employee's current pay cycle."""
        if not self.conn:
            return None
        
        query = "SELECT * FROM employees WHERE id = %s"
        self.cursor.execute(query, (employee_id,))
        employee = self.cursor.fetchone()
        
        if not employee:
            return None
            
        # Get pending shifts
        query = "SELECT SUM(total_hours) as total_hours FROM shifts WHERE employee_id = %s AND status = 'pending'"
        self.cursor.execute(query, (employee_id,))
        shifts = self.cursor.fetchone()
        
        # Get deductions
        query = "SELECT * FROM deductions WHERE employee_id = %s"
        self.cursor.execute(query, (employee_id,))
        deductions = self.cursor.fetchall()
        
        return {
            "employee": employee,
            "pending_hours": shifts['total_hours'] if shifts['total_hours'] else 0,
            "deductions": deductions
        }

    def close(self):
        if self.conn:
            self.cursor.close()
            self.conn.close()

if __name__ == "__main__":
    # Example usage (can be integrated with a listener or web API)
    engine = PayrollEngine()
    # summary = engine.get_employee_summary(1)
    # print(json.dumps(summary, indent=4, default=str))
    engine.close()
