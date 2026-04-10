-- Creates the test database alongside the main app database.
-- This script runs once when the PostgreSQL data volume is first initialized.
CREATE DATABASE app_test;
GRANT ALL PRIVILEGES ON DATABASE app_test TO app;
