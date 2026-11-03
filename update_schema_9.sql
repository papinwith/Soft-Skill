-- Self-registered activity organizers (register.php, reg_role=manager) start as 'pending'
-- and need Admin approval before they can log in -- open self-service signup for a
-- privileged role isn't realistic in real deployment (issu.md #30). Accounts created by an
-- already-approved manager (manager/manage_staff.php) or by Admin directly stay 'active'.
ALTER TABLE activity_supervisors
    ADD COLUMN status ENUM('active','pending') NOT NULL DEFAULT 'active' AFTER phone;
