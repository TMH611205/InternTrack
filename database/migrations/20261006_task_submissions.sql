-- Minh chứng hoàn thành nhiệm vụ do sinh viên gửi: liên kết (ví dụ Git), tệp đính kèm và ghi chú.
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS submission_link VARCHAR(500) NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS submission_file VARCHAR(255) NULL AFTER submission_link,
    ADD COLUMN IF NOT EXISTS submission_note TEXT NULL AFTER submission_file,
    ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER submission_note;
