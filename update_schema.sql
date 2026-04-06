ALTER TABLE assessment_responses DROP FOREIGN KEY assessment_responses_ibfk_2;
ALTER TABLE assessment_responses MODIFY type ENUM('pre','post','baseline') NOT NULL;
ALTER TABLE assessment_responses MODIFY activity_id INT(11) NULL;
ALTER TABLE assessment_responses ADD CONSTRAINT assessment_responses_ibfk_2 FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE;

ALTER TABLE ai_reports DROP FOREIGN KEY ai_reports_ibfk_2;
ALTER TABLE ai_reports MODIFY type ENUM('pre','post','baseline') NOT NULL;
ALTER TABLE ai_reports MODIFY activity_id INT(11) NULL;
ALTER TABLE ai_reports ADD CONSTRAINT ai_reports_ibfk_2 FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE;
