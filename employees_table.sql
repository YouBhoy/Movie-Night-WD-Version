-- Update existing employees table for Movie Night Registration System
-- This updates the existing employees table to work with the new employee lookup system

-- Note: Your existing employees table already has the correct structure:
-- emp_number, full_name, department, is_active, etc.

-- Update existing employee data to include proper departments for shift mapping
-- You can customize these department assignments based on your organization

UPDATE `employees` SET `department` = 'Engineering' WHERE `emp_number` = 'WD001';
UPDATE `employees` SET `department` = 'Marketing' WHERE `emp_number` = 'WD002';
UPDATE `employees` SET `department` = 'IT' WHERE `emp_number` = 'WD003';
UPDATE `employees` SET `department` = 'HR' WHERE `emp_number` = 'WD004';
UPDATE `employees` SET `department` = 'Finance' WHERE `emp_number` = 'WD005';
UPDATE `employees` SET `department` = 'Testing' WHERE `emp_number` = 'WD007';

-- Add more sample employees if needed (optional)
-- INSERT INTO `employees` (`emp_number`, `full_name`, `email`, `department`, `is_active`, `max_attendees`) VALUES
-- ('WD008', 'New Employee', 'new.employee@wd.com', 'Engineering', 1, 3),
-- ('WD009', 'Another Employee', 'another.employee@wd.com', 'Marketing', 1, 3);

-- Hall assignment logic based on shift:
-- - "Normal Shift" or "Crew C" → Cinema Hall 1
-- - "Crew A" or "Crew B" → Cinema Hall 2

-- Update existing shifts table to match employee shifts
-- Make sure your shifts table has these shift names:
-- - Normal Shift
-- - Crew A  
-- - Crew B
-- - Crew C

-- Example shifts table update (if needed):
/*
UPDATE shifts SET shift_name = 'Normal Shift' WHERE id = 1;
UPDATE shifts SET shift_name = 'Crew A' WHERE id = 2;
UPDATE shifts SET shift_name = 'Crew B' WHERE id = 3;
UPDATE shifts SET shift_name = 'Crew C' WHERE id = 4;
*/ 