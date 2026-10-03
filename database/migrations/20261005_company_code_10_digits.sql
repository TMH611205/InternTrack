-- Mã doanh nghiệp gồm đúng 10 chữ số. Mã cũ không đúng định dạng được thay bằng mã tạm 01 + 8 chữ số theo id;
-- quản trị viên cập nhật lại mã thật ở trang Doanh nghiệp.
UPDATE companies
SET company_code = CONCAT('01', LPAD(id, 8, '0'))
WHERE company_code NOT REGEXP '^[0-9]{10}$';
