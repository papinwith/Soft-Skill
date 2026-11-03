-- issu.md #27 (revised): the organizer sets the activity's own assessment questions
-- directly in the activity create/edit form, always -- not via a separate "manage my
-- assessments" page. Each activity gets at most one dedicated assessment (1:1), created
-- automatically the first time the manager types custom questions for it. SET NULL (not
-- CASCADE) avoids a circular cascade with activities.assessment_id (also SET NULL on
-- delete); the activity-delete handlers explicitly clean up the orphaned assessment row.
ALTER TABLE assessments
    ADD COLUMN activity_id INT NULL AFTER created_by_manager_id,
    ADD UNIQUE KEY uniq_assessments_activity (activity_id),
    ADD CONSTRAINT fk_assessments_activity FOREIGN KEY (activity_id)
        REFERENCES activities(id) ON DELETE SET NULL;
