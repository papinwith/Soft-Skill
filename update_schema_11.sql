-- Track who created each activity_supervisor account so manager/manage_staff.php can scope
-- its list to "accounts I added" instead of showing every organizer in the system
-- (issu.md #34). NULL = self-registered via register.php, or created by Admin.
ALTER TABLE activity_supervisors
    ADD COLUMN created_by_manager_id INT NULL AFTER status,
    ADD CONSTRAINT fk_activity_supervisors_created_by FOREIGN KEY (created_by_manager_id)
        REFERENCES activity_supervisors(id) ON DELETE SET NULL;
