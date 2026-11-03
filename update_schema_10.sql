-- Audit log of admin/manager actions (issu.md #28). actor_role/id/name are a snapshot, not an
-- FK, since the actor can come from any of the three account tables (admins,
-- activity_supervisors, students) -- same reasoning as password_reset_tokens.role.
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_role ENUM('admin','manager','student') NOT NULL,
    actor_id INT NOT NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    detail VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_logs_created (created_at),
    INDEX idx_activity_logs_actor (actor_role, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
