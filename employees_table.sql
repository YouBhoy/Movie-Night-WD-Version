-- Update existing employees table for Movie Night Registration System
-- This updates the existing employees table to work with the new employee lookup system

-- Note: Your existing employees table already has the correct structure:
-- emp_number, full_name, department, is_active, etc.

-- Remove all department-related fields and updates

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