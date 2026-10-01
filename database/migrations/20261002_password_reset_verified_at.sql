ALTER TABLE password_reset_otps
    ADD COLUMN verified_at DATETIME NULL AFTER attempts;