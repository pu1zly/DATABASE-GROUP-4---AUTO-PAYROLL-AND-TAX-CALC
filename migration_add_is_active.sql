-- Migration: Add is_active column to employees table for soft-delete support
-- Run this migration to enable employee deactivation functionality

USE payroll_db;

-- Add is_active column if it doesn't exist
ALTER TABLE employees ADD COLUMN is_active BOOLEAN DEFAULT TRUE;

-- Optional: Update getEmployees() queries to filter by is_active = TRUE
-- This should be done in the PHP code to maintain backward compatibility

COMMIT;
