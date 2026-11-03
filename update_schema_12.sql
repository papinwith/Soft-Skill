-- Supports the central organizer account creating new activity_supervisors accounts that must
-- be verified via an emailed OTP before they can log in (issu.md #38).
ALTER TABLE activity_supervisors
    ADD COLUMN otp_code VARCHAR(10) NULL AFTER status,
    ADD COLUMN otp_expires_at DATETIME NULL AFTER otp_code;
