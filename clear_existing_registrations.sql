-- Clear existing registrations to test the new employee lookup system
-- This script will remove all existing registrations and free up seats

-- Clear all existing registrations
DELETE FROM registrations WHERE status = 'active';

-- Reset all seats to available status
UPDATE seats SET status = 'available', updated_at = NOW() WHERE status = 'occupied';

-- Verify the cleanup
SELECT 'Registrations cleared' as status, COUNT(*) as count FROM registrations WHERE status = 'active';
SELECT 'Seats reset to available' as status, COUNT(*) as count FROM seats WHERE status = 'available';

-- Optional: If you want to keep some test data, you can selectively delete:
-- DELETE FROM registrations WHERE emp_number IN ('WD001', 'WD002', 'WD003');
-- UPDATE seats SET status = 'available' WHERE seat_number IN ('B9', 'B10', 'B11', 'A1', 'A2', 'A3'); 