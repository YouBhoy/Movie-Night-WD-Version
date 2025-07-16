-- Seats Table Setup for Movie Night Registration System
-- This script creates the seats table if it doesn't exist

-- Create seats table
CREATE TABLE IF NOT EXISTS `seats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `row_letter` char(1) NOT NULL,
  `seat_position` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `status` enum('available','occupied','blocked','reserved') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seat` (`hall_id`, `shift_id`, `seat_number`),
  KEY `idx_hall_shift` (`hall_id`, `shift_id`),
  KEY `idx_status` (`status`),
  KEY `idx_row_position` (`row_letter`, `seat_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create stored procedure for generating seats
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `createSeatsForHallShift`(
    IN p_hall_id INT,
    IN p_shift_id INT,
    IN p_shift_name VARCHAR(100)
)
BEGIN
    DECLARE row_letter CHAR(1);
    DECLARE seat_pos INT;
    DECLARE seat_num VARCHAR(10);
    DECLARE max_rows INT DEFAULT 10;
    DECLARE max_seats_per_row INT DEFAULT 10;
    
    -- Set different layouts based on shift
    IF p_shift_name LIKE '%Crew A%' OR p_shift_name LIKE '%Crew B%' THEN
        SET max_rows = 8;
        SET max_seats_per_row = 12;
    ELSE
        SET max_rows = 10;
        SET max_seats_per_row = 10;
    END IF;
    
    -- Generate seats
    SET row_letter = 'A';
    WHILE row_letter <= CHAR(ASCII('A') + max_rows - 1) DO
        SET seat_pos = 1;
        WHILE seat_pos <= max_seats_per_row DO
            SET seat_num = CONCAT(row_letter, seat_pos);
            
            INSERT INTO seats (hall_id, shift_id, row_letter, seat_position, seat_number, status)
            VALUES (p_hall_id, p_shift_id, row_letter, seat_pos, seat_num, 'available')
            ON DUPLICATE KEY UPDATE status = 'available', updated_at = NOW();
            
            SET seat_pos = seat_pos + 1;
        END WHILE;
        SET row_letter = CHAR(ASCII(row_letter) + 1);
    END WHILE;
END$$

DELIMITER ;

-- Insert sample seats for existing halls and shifts (optional)
-- Uncomment the lines below if you want to create sample seats

/*
-- Sample seats for Hall 1, Shift 1 (Normal Shift)
CALL createSeatsForHallShift(1, 1, 'Normal Shift');

-- Sample seats for Hall 1, Shift 4 (Crew C)
CALL createSeatsForHallShift(1, 4, 'Crew C');

-- Sample seats for Hall 2, Shift 2 (Crew A)
CALL createSeatsForHallShift(2, 2, 'Crew A');

-- Sample seats for Hall 2, Shift 3 (Crew B)
CALL createSeatsForHallShift(2, 3, 'Crew B');
*/

-- Verify the setup
SELECT 'Seats table created successfully' as status;
SELECT COUNT(*) as total_seats FROM seats; 