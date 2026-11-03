-- issu.md #27: let activity managers create their own assessment question sets (tagged
-- per-skill, same mechanism admins already use) instead of only picking the standard one.
-- NULL = standard/admin-owned assessment, visible to every manager; non-NULL = owned by
-- that one manager, editable only by them.
ALTER TABLE assessments
    ADD COLUMN created_by_manager_id INT NULL AFTER description,
    ADD CONSTRAINT fk_assessments_manager FOREIGN KEY (created_by_manager_id)
        REFERENCES activity_supervisors(id) ON DELETE SET NULL;
