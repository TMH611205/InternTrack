ALTER TABLE internships
    ADD COLUMN IF NOT EXISTS training_plan TEXT NULL AFTER description;
