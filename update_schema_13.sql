-- OTP verification (verify_organizer.php) had no attempt limit: a 6-digit code with no
-- throttling is brute-forceable within its 15-minute validity window. Track failed attempts
-- and lock the OTP out after too many wrong guesses.
ALTER TABLE activity_supervisors
    ADD COLUMN otp_attempts INT NOT NULL DEFAULT 0 AFTER otp_expires_at;
