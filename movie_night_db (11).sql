-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 20, 2025 at 03:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `movie_night_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `createSeatsForHallShift` (IN `p_hall_id` INT, IN `p_shift_id` INT, IN `p_shift_name` VARCHAR(100))   BEGIN
    DECLARE v_row_letter VARCHAR(2);
    DECLARE v_position INT;
    DECLARE v_seat_number VARCHAR(10);
    DECLARE done INT DEFAULT FALSE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Delete existing seats for this hall/shift combination
    DELETE FROM seats WHERE hall_id = p_hall_id AND shift_id = p_shift_id;
    
    IF p_hall_id = 1 THEN
        -- CINEMA HALL 1 - Standard layout
        -- Rows A through L (skipping I)
        SET @rows = 'A,B,C,D,E,F,G,H,J,K,L';
        SET @row_count = 1;
        
        WHILE @row_count <= 11 DO
            SET v_row_letter = SUBSTRING_INDEX(SUBSTRING_INDEX(@rows, ',', @row_count), ',', -1);
            
            IF p_shift_id = 1 THEN
                -- Normal Shift gets seats 1-6
                SET v_position = 1;
                WHILE v_position <= 6 DO
                    SET v_seat_number = CONCAT(v_row_letter, v_position);
                    INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status)
                    VALUES (p_hall_id, p_shift_id, v_seat_number, v_row_letter, v_position, 'available');
                    SET v_position = v_position + 1;
                END WHILE;
            ELSEIF p_shift_id = 2 THEN
                -- Crew C (Day Shift) gets seats 7-11
                SET v_position = 7;
                WHILE v_position <= 11 DO
                    SET v_seat_number = CONCAT(v_row_letter, v_position);
                    INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status)
                    VALUES (p_hall_id, p_shift_id, v_seat_number, v_row_letter, v_position, 'available');
                    SET v_position = v_position + 1;
                END WHILE;
            END IF;
            
            SET @row_count = @row_count + 1;
        END WHILE;
        
    ELSEIF p_hall_id = 2 THEN
        -- CINEMA HALL 2 - Special layout based on crew assignments
        -- 12 rows A through M, skipping I
        SET @rows = 'A,B,C,D,E,F,G,H,J,K,L,M';
        SET @row_count = 1;
        
        WHILE @row_count <= 12 DO
            SET v_row_letter = SUBSTRING_INDEX(SUBSTRING_INDEX(@rows, ',', @row_count), ',', -1);
            
            IF LOCATE('CREW A', p_shift_name) > 0 THEN
                -- CREW A (OFF/REST DAY) gets seats 1-6 in each row (left section)
                SET v_position = 1;
                WHILE v_position <= 6 DO
                    SET v_seat_number = CONCAT(v_row_letter, v_position);
                    INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status)
                    VALUES (p_hall_id, p_shift_id, v_seat_number, v_row_letter, v_position, 'available');
                    SET v_position = v_position + 1;
                END WHILE;
            ELSEIF LOCATE('CREW B', p_shift_name) > 0 THEN
                -- CREW B (OFF/REST DAY) gets seats 7-12 in each row (right section)
                SET v_position = 7;
                WHILE v_position <= 12 DO
                    SET v_seat_number = CONCAT(v_row_letter, v_position);
                    INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status)
                    VALUES (p_hall_id, p_shift_id, v_seat_number, v_row_letter, v_position, 'available');
                    SET v_position = v_position + 1;
                END WHILE;
            END IF;
            
            SET @row_count = @row_count + 1;
        END WHILE;
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `findSmartSeatSuggestions` (IN `p_hall_id` INT, IN `p_shift_id` INT, IN `p_preferred_row` VARCHAR(2), IN `p_attendee_count` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_row_letter VARCHAR(2);
    DECLARE v_consecutive_count INT;
    DECLARE v_start_position INT;
    DECLARE v_distance INT;
    
    -- Cursor for nearby rows (preferred row first, then adjacent rows)
    DECLARE row_cursor CURSOR FOR
        SELECT DISTINCT s.row_letter,
               ABS(ASCII(s.row_letter) - ASCII(p_preferred_row)) as distance
        FROM seats s
        WHERE s.hall_id = p_hall_id 
        AND s.shift_id = p_shift_id
        ORDER BY distance, s.row_letter;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_suggestions (
        row_letter VARCHAR(2),
        start_position INT,
        consecutive_count INT,
        distance_from_preferred INT,
        suggested_seats JSON
    );
    
    DELETE FROM temp_suggestions;
    
    OPEN row_cursor;
    read_loop: LOOP
        FETCH row_cursor INTO v_row_letter, v_distance;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Find consecutive seats in this row
        SELECT MAX(consecutive_count), MIN(start_pos)
        INTO v_consecutive_count, v_start_position
        FROM (
            SELECT COUNT(*) as consecutive_count,
                   MIN(seat_position) as start_pos
            FROM (
                SELECT seat_position,
                       seat_position - ROW_NUMBER() OVER (ORDER BY seat_position) as grp
                FROM seats
                WHERE hall_id = p_hall_id 
                AND shift_id = p_shift_id
                AND row_letter = v_row_letter
                AND status = 'available'
            ) grouped
            GROUP BY grp
            HAVING COUNT(*) >= p_attendee_count
        ) consecutive_groups;
        
        -- If found suitable consecutive seats, add to suggestions
        IF v_consecutive_count >= p_attendee_count THEN
            SET @suggested_seats = JSON_ARRAY();
            SET @counter = 0;
            WHILE @counter < p_attendee_count DO
                SET @suggested_seats = JSON_ARRAY_APPEND(
                    @suggested_seats, 
                    '$', 
                    CONCAT(v_row_letter, v_start_position + @counter)
                );
                SET @counter = @counter + 1;
            END WHILE;
            
            INSERT INTO temp_suggestions VALUES (
                v_row_letter, 
                v_start_position, 
                v_consecutive_count, 
                v_distance,
                @suggested_seats
            );
        END IF;
    END LOOP;
    CLOSE row_cursor;
    
    -- Return best suggestions
    SELECT * FROM temp_suggestions 
    ORDER BY distance_from_preferred, consecutive_count DESC 
    LIMIT 3;
    
    DROP TEMPORARY TABLE temp_suggestions;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `freeSeatsByRegistration` (IN `p_registration_id` INT)   BEGIN
    DECLARE v_selected_seats JSON;
    DECLARE v_hall_id INT;
    DECLARE v_shift_id INT;
    DECLARE v_emp_number VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Get registration details
    SELECT selected_seats, hall_id, shift_id, emp_number
    INTO v_selected_seats, v_hall_id, v_shift_id, v_emp_number
    FROM registrations 
    WHERE id = p_registration_id AND status = 'active';
    
    IF v_selected_seats IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Registration not found or already cancelled';
    END IF;
    
    -- Release the seats
    UPDATE seats 
    SET status = 'available'
    WHERE hall_id = v_hall_id 
    AND shift_id = v_shift_id 
    AND JSON_CONTAINS(v_selected_seats, JSON_QUOTE(seat_number));
    
    -- Mark registration as cancelled
    UPDATE registrations 
    SET status = 'cancelled'
    WHERE id = p_registration_id;
    
    COMMIT;
    
    SELECT v_selected_seats as released_seats, v_emp_number as emp_number;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `getSeatsForHallShift` (IN `p_hall_id` INT, IN `p_shift_id` INT)   BEGIN
    DECLARE v_seat_count INT DEFAULT 0;
    DECLARE v_shift_name VARCHAR(100);
    
    -- Check if seats exist
    SELECT COUNT(*) INTO v_seat_count 
    FROM seats 
    WHERE hall_id = p_hall_id AND shift_id = p_shift_id;
    
    -- If no seats exist, create them
    IF v_seat_count = 0 THEN
        SELECT shift_name INTO v_shift_name 
        FROM shifts 
        WHERE id = p_shift_id AND hall_id = p_hall_id AND is_active = 1;
        
        IF v_shift_name IS NOT NULL THEN
            CALL createSeatsForHallShift(p_hall_id, p_shift_id, v_shift_name);
        END IF;
    END IF;
    
    -- Return all seats
    SELECT id, seat_number, row_letter, seat_position, status 
    FROM seats 
    WHERE hall_id = p_hall_id AND shift_id = p_shift_id 
    ORDER BY row_letter, seat_position;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `reserveSeats` (IN `p_emp_number` VARCHAR(20), IN `p_staff_name` VARCHAR(255), IN `p_hall_id` INT, IN `p_shift_id` INT, IN `p_attendee_count` INT, IN `p_selected_seats` JSON, IN `p_ip_address` VARCHAR(45), IN `p_user_agent` TEXT)   BEGIN
    DECLARE v_registration_id INT DEFAULT 0;
    DECLARE v_movie_name VARCHAR(255);
    DECLARE v_screening_time VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Get movie details
    SELECT setting_value INTO v_movie_name FROM event_settings WHERE setting_key = 'movie_name';
    SELECT setting_value INTO v_screening_time FROM event_settings WHERE setting_key = 'screening_time';
    
    -- Reserve the seats
    UPDATE seats 
    SET status = 'occupied'
    WHERE hall_id = p_hall_id 
    AND shift_id = p_shift_id 
    AND status = 'available'
    AND JSON_CONTAINS(p_selected_seats, JSON_QUOTE(seat_number));
    
    -- Verify all seats were reserved
    IF ROW_COUNT() != p_attendee_count THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to reserve all selected seats';
    END IF;
    
    -- Create registration record
    INSERT INTO registrations (
        emp_number, staff_name, attendee_count, hall_id, shift_id,
        selected_seats, movie_name, screening_time, ip_address, user_agent, 
        status, registration_date
    ) VALUES (
        p_emp_number, p_staff_name, p_attendee_count, p_hall_id, p_shift_id,
        p_selected_seats, COALESCE(v_movie_name, 'Western Digital Movie Night'), 
        COALESCE(v_screening_time, 'TBD'), p_ip_address, p_user_agent, 
        'active', NOW()
    );
    
    SET v_registration_id = LAST_INSERT_ID();
    
    COMMIT;
    
    SELECT v_registration_id as registration_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `validateBookingRequest` (IN `p_emp_number` VARCHAR(20), IN `p_attendee_count` INT, IN `p_hall_id` INT, IN `p_shift_id` INT, IN `p_selected_seats` JSON)   BEGIN
    DECLARE v_employee_exists INT DEFAULT 0;
    DECLARE v_already_registered INT DEFAULT 0;
    DECLARE v_registration_enabled VARCHAR(10);
    DECLARE v_seat_count INT DEFAULT 0;
    DECLARE v_available_count INT DEFAULT 0;
    DECLARE v_max_attendees INT DEFAULT 0;
    DECLARE v_error_msg VARCHAR(255);
    
    -- Check if employee exists and is active
    SELECT COUNT(*) INTO v_employee_exists 
    FROM employees 
    WHERE emp_number = p_emp_number AND is_active = 1;
    
    IF v_employee_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Employee number not found or inactive';
    END IF;
    
    -- Check if employee already registered
    SELECT COUNT(*) INTO v_already_registered 
    FROM registrations 
    WHERE emp_number = p_emp_number AND status = 'active';
    
    IF v_already_registered > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Employee already registered for this event';
    END IF;
    
    -- Check if registration is enabled
    SELECT setting_value INTO v_registration_enabled 
    FROM event_settings 
    WHERE setting_key = 'registration_enabled';
    
    IF v_registration_enabled != 'true' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Registration is currently disabled';
    END IF;
    
    -- Check max attendees for hall
    SELECT max_attendees_per_booking INTO v_max_attendees 
    FROM cinema_halls 
    WHERE id = p_hall_id AND is_active = 1;
    
    IF v_max_attendees IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid hall ID';
    END IF;
    
    IF p_attendee_count > v_max_attendees THEN
        SET v_error_msg = CONCAT('Maximum ', v_max_attendees, ' attendees allowed for this hall');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END IF;
    
    -- Validate seat count matches attendee count
    SELECT JSON_LENGTH(p_selected_seats) INTO v_seat_count;
    
    IF v_seat_count != p_attendee_count THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seat count does not match attendee count';
    END IF;
    
    -- Check if all selected seats exist and are available
    SELECT COUNT(*) INTO v_available_count
    FROM seats s
    WHERE s.hall_id = p_hall_id 
    AND s.shift_id = p_shift_id 
    AND s.status = 'available'
    AND JSON_CONTAINS(p_selected_seats, JSON_QUOTE(s.seat_number));
    
    IF v_available_count != p_attendee_count THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'One or more selected seats are no longer available';
    END IF;
    
    -- Return success
    SELECT 'valid' as validation_status;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `validateSeatGaps` (IN `p_hall_id` INT, IN `p_shift_id` INT, IN `p_selected_seats` JSON, IN `p_attendee_count` INT)   BEGIN
    DECLARE v_gap_count INT DEFAULT 0;
    DECLARE v_suggestion_row VARCHAR(2) DEFAULT '';
    DECLARE v_suggested_seats JSON DEFAULT JSON_ARRAY();
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_row_letter VARCHAR(2);
    DECLARE v_consecutive_count INT;
    DECLARE v_start_position INT;
    
    -- Cursor to check each row for consecutive seats
    DECLARE row_cursor CURSOR FOR
        SELECT DISTINCT row_letter 
        FROM seats 
        WHERE hall_id = p_hall_id AND shift_id = p_shift_id 
        ORDER BY row_letter;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Check for gaps in selected seats
    SELECT COUNT(*) INTO v_gap_count
    FROM seats s1
    WHERE s1.hall_id = p_hall_id 
    AND s1.shift_id = p_shift_id
    AND s1.status = 'available'
    AND NOT JSON_CONTAINS(p_selected_seats, JSON_QUOTE(s1.seat_number))
    AND EXISTS (
        SELECT 1 FROM seats s2 
        WHERE s2.hall_id = p_hall_id 
        AND s2.shift_id = p_shift_id
        AND s2.row_letter = s1.row_letter
        AND s2.seat_position = s1.seat_position - 1
        AND JSON_CONTAINS(p_selected_seats, JSON_QUOTE(s2.seat_number))
    )
    AND EXISTS (
        SELECT 1 FROM seats s3 
        WHERE s3.hall_id = p_hall_id 
        AND s3.shift_id = p_shift_id
        AND s3.row_letter = s1.row_letter
        AND s3.seat_position = s1.seat_position + 1
        AND JSON_CONTAINS(p_selected_seats, JSON_QUOTE(s3.seat_number))
    );
    
    -- If gaps detected, find alternative consecutive seats
    IF v_gap_count > 0 THEN
        OPEN row_cursor;
        read_loop: LOOP
            FETCH row_cursor INTO v_row_letter;
            IF done THEN
                LEAVE read_loop;
            END IF;
            
            -- Check for consecutive available seats in this row
            SELECT COUNT(*) as consecutive_count,
                   MIN(seat_position) as start_pos
            INTO v_consecutive_count, v_start_position
            FROM (
                SELECT seat_position,
                       seat_position - ROW_NUMBER() OVER (ORDER BY seat_position) as grp
                FROM seats
                WHERE hall_id = p_hall_id 
                AND shift_id = p_shift_id
                AND row_letter = v_row_letter
                AND status = 'available'
            ) grouped
            GROUP BY grp
            HAVING COUNT(*) >= p_attendee_count
            ORDER BY COUNT(*) DESC, MIN(seat_position)
            LIMIT 1;
            
            -- If found enough consecutive seats, create suggestion
            IF v_consecutive_count >= p_attendee_count THEN
                SET v_suggestion_row = v_row_letter;
                
                -- Build suggested seats array
                SET v_suggested_seats = JSON_ARRAY();
                SET @counter = 0;
                WHILE @counter < p_attendee_count DO
                    SET v_suggested_seats = JSON_ARRAY_APPEND(
                        v_suggested_seats, 
                        '$', 
                        CONCAT(v_row_letter, v_start_position + @counter)
                    );
                    SET @counter = @counter + 1;
                END WHILE;
                
                LEAVE read_loop;
            END IF;
        END LOOP;
        CLOSE row_cursor;
    END IF;
    
    -- Return results
    SELECT 
        v_gap_count as gap_count,
        v_suggestion_row as suggested_row,
        v_suggested_seats as suggested_seats,
        CASE 
            WHEN v_gap_count = 0 THEN 'valid'
            WHEN v_suggestion_row != '' THEN 'suggestion_available'
            ELSE 'manual_selection_required'
        END as validation_status;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_user` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_user`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'admin', 'export_registrations', 'registrations', NULL, '{\"export_type\":\"csv\",\"record_count\":3}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 08:09:19'),
(2, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-09 16:10:16\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 08:10:16'),
(3, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 16:10:21\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 08:10:21'),
(4, 'admin', 'update_settings', 'event_settings', NULL, '{\"movie_name\":\"Movie Name\",\"movie_date\":\"Set date\",\"movie_time\":\"Set Time\",\"movie_location\":\"Cinema Complex\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 08:10:58'),
(5, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 17:45:18\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:45:18'),
(6, 'admin', 'export_registrations', 'registrations', NULL, '{\"export_type\":\"csv\",\"record_count\":4}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:45:57'),
(7, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 17:46:11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 09:46:11'),
(8, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-09 17:48:40\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:48:40'),
(9, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 17:48:45\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:48:45'),
(10, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-09 17:49:33\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:49:33'),
(11, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 17:49:37\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-09 09:49:37'),
(12, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 21:04:11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 13:04:11'),
(13, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-09 23:51:21\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 15:51:21'),
(14, 'admin', 'login', NULL, NULL, '{\"login_time\":\"2025-07-09 23:54:43\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-09 15:54:43'),
(15, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-12 15:13:33\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-12 07:13:33'),
(16, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-12 17:22:21\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-12 09:22:21'),
(17, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":1,\"shift_id\":2,\"seats_count\":56}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 05:48:54'),
(18, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-13 14:09:14\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:09:14'),
(19, 'admin', 'add_seat', 'seats', 322, '{\"hall_id\":1,\"shift_id\":1,\"seat_number\":\"A7\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:13:19'),
(20, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":1,\"shift_id\":1,\"seats_count\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:13:24'),
(21, 'admin', 'add_seat', 'seats', 393, '{\"hall_id\":1,\"shift_id\":1,\"seat_number\":\"A11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:14:07'),
(22, 'admin', 'delete_seat', 'seats', 329, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:14:15'),
(23, 'admin', 'delete_seat', 'seats', 390, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:14:17'),
(24, 'admin', 'delete_seat', 'seats', 391, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:14:19'),
(25, 'admin', 'delete_seat', 'seats', 392, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:14:21'),
(26, 'admin', 'delete_seat', 'seats', 321, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 06:15:33'),
(27, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":1,\"shift_id\":1,\"seats_count\":69}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 07:04:42'),
(28, 'admin', 'delete_seat', 'seats', 462, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 07:05:04'),
(29, 'admin', 'delete_seat', 'seats', 461, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 07:05:06'),
(30, 'admin', 'delete_seat', 'seats', 400, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 07:11:40'),
(31, 'admin', 'add_seat', 'seats', 463, '{\"hall_id\":1,\"shift_id\":1,\"seat_number\":\"A12\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 07:14:58'),
(32, 'admin', 'add_seat', 'seats', 464, '{\"hall_id\":2,\"shift_id\":3,\"seat_number\":\"A13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 08:01:55'),
(33, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":1,\"shift_id\":1,\"seats_count\":68}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 08:17:54'),
(34, 'admin', 'delete_seat', 'seats', 471, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 08:28:19'),
(35, 'admin', 'delete_seat', 'seats', 532, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 08:28:21'),
(36, 'admin', 'delete_seat', 'seats', 464, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 08:34:58'),
(37, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":2,\"shift_id\":4,\"seats_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:27:46'),
(38, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":2,\"shift_id\":4,\"seats_count\":80}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:33:56'),
(39, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":2,\"shift_id\":4,\"seats_count\":84}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:34:33'),
(40, 'admin', 'delete_seat', 'seats', 691, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:34:48'),
(41, 'admin', 'delete_seat', 'seats', 698, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:34:50'),
(42, 'admin', 'delete_seat', 'seats', 705, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:34:58'),
(43, 'admin', 'delete_seat', 'seats', 712, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:00'),
(44, 'admin', 'delete_seat', 'seats', 719, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:02'),
(45, 'admin', 'delete_seat', 'seats', 726, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:04'),
(46, 'admin', 'delete_seat', 'seats', 733, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:05'),
(47, 'admin', 'delete_seat', 'seats', 740, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:07'),
(48, 'admin', 'delete_seat', 'seats', 765, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:08'),
(49, 'admin', 'delete_seat', 'seats', 766, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:11'),
(50, 'admin', 'delete_seat', 'seats', 768, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:27'),
(51, 'admin', 'delete_seat', 'seats', 767, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:35:29'),
(52, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":2,\"shift_id\":4,\"seats_count\":75}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:49:08'),
(53, 'admin', 'delete_seat', 'seats', 841, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:49:19'),
(54, 'admin', 'delete_seat', 'seats', 842, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:49:20'),
(55, 'admin', 'delete_seat', 'seats', 843, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-13 09:49:22'),
(56, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":1,\"shift_id\":2,\"seats_count\":63}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 12:18:19'),
(57, 'admin', 'add_hall', 'cinema_halls', 3, '{\"hall_name\":\"Cinema Hall 3\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 12:18:44'),
(58, 'admin', 'add_shift', 'shifts', 5, '{\"hall_id\":3,\"shift_name\":\"Test Shift\",\"shift_code\":\"TEST_SHIFT\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 12:18:56'),
(59, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":3,\"shift_id\":5,\"seats_count\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 12:19:54'),
(60, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":3,\"shift_id\":5,\"seats_count\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 13:07:42'),
(61, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":3,\"shift_id\":5,\"seats_count\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 13:59:37'),
(62, 'admin', 'add_hall', 'cinema_halls', 4, '{\"hall_name\":\"Cinema Hall 4\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 14:42:23'),
(63, 'admin', 'add_shift', 'shifts', 6, '{\"hall_id\":4,\"shift_name\":\"Test Shift 2\",\"shift_code\":\"TEST_SHIFT_2\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 14:42:30'),
(64, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":4,\"shift_id\":6,\"seats_count\":20}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 14:42:47'),
(65, 'admin', 'delete_seat', 'seats', 942, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 14:44:03'),
(66, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":4,\"shift_id\":6,\"seats_count\":20}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 14:44:07'),
(67, 'admin', 'update_hall', 'cinema_halls', 4, '{\"hall_name\":\"Poopie Hall\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 15:32:42'),
(68, 'admin', 'update_hall', 'cinema_halls', 4, '{\"hall_name\":\"Poopie Hall\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 15:32:43'),
(69, 'admin', 'add_shift', 'shifts', 7, '{\"hall_id\":4,\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 15:33:58'),
(70, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":4,\"shift_id\":7,\"seats_count\":18}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-15 15:34:34'),
(71, 'admin', 'update_hall', 'cinema_halls', 4, '{\"hall_name\":\"Poopie Hall\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:26:35'),
(72, 'admin', 'deactivate_shift', 'shifts', 5, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:29:36'),
(73, 'admin', 'restore_shift', 'shifts', 5, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:29:44'),
(74, 'admin', 'update_shift', 'shifts', 3, '{\"shift_name\":\"Crew A (Off\\/Rest Day)\",\"shift_code\":\"CREW_A_(OFF\\/REST_DAY)\",\"seat_count\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:33:51'),
(75, 'admin', 'update_hall', 'cinema_halls', 4, '{\"hall_name\":\"Poopie Hall\",\"max_attendees\":3,\"total_seats\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:34:10'),
(76, 'admin', 'update_shift', 'shifts', 4, '{\"shift_name\":\"Crew B (Off\\/Rest Day)\",\"shift_code\":\"CREW_B_(OFF\\/REST_DAY)\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:34:28'),
(77, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:45:05'),
(78, 'admin', 'update_shift', 'shifts', 6, '{\"shift_name\":\"Test Shift 2\",\"shift_code\":\"TEST_SHIFT_2\",\"seat_count\":72,\"hall_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:45:11'),
(79, 'admin', 'update_shift', 'shifts', 5, '{\"shift_name\":\"Test Shift\",\"shift_code\":\"TEST_SHIFT\",\"seat_count\":72,\"hall_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:45:21'),
(80, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":3}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:45:30'),
(81, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:45:38'),
(82, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:51:05'),
(83, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 01:51:16'),
(84, 'admin', 'deactivate_hall', 'cinema_halls', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 02:48:50'),
(85, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 02:57:05'),
(86, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:01:47'),
(87, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:01:54'),
(88, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:04:21'),
(89, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:11:17'),
(90, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":6}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:12:34'),
(91, 'admin', 'update_shift', 'shifts', 7, '{\"shift_name\":\"Pee pee shift\",\"shift_code\":\"PEE_PEE_SHIFT\",\"seat_count\":72,\"hall_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:12:44'),
(92, 'admin', 'restore_hall', 'cinema_halls', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:12:54'),
(93, 'admin', 'deactivate_hall', 'cinema_halls', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:12:58'),
(94, 'admin', 'deactivate_shift', 'shifts', 7, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:56:50'),
(95, 'admin', 'deactivate_shift', 'shifts', 5, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:56:57'),
(96, 'admin', 'deactivate_shift', 'shifts', 6, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:57:00'),
(97, 'admin', 'deactivate_hall', 'cinema_halls', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 03:57:08'),
(98, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-16 20:44:10\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 12:44:10'),
(99, 'admin', 'restore_hall', 'cinema_halls', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 13:24:01'),
(100, 'admin', 'deactivate_hall', 'cinema_halls', 4, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-16 13:24:05'),
(101, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-16 21:32:36\"}', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '2025-07-16 13:32:36'),
(102, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-17 18:51:52\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-17 10:51:52'),
(103, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-17 18:52:50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-17 10:52:50'),
(104, 'admin', 'add_seat', 'seats', 997, '{\"hall_id\":1,\"shift_id\":1,\"seat_number\":\"A11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-17 15:22:43'),
(105, 'admin', 'delete_seat', 'seats', 997, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-17 15:22:46'),
(106, 'admin', 'add_hall', 'cinema_halls', 7, '{\"hall_name\":\"1\",\"max_attendees\":3,\"total_seats\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:44:32'),
(107, 'admin', 'add_shift', 'shifts', 9, '{\"hall_id\":7,\"shift_name\":\"boy boy\",\"shift_code\":\"BOY_BOY\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:44:45'),
(108, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":7,\"shift_id\":9,\"seats_count\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:45:35'),
(109, 'admin', 'delete_seat', 'seats', 1000, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:47:37'),
(110, 'admin', 'delete_seat', 'seats', 999, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:47:39'),
(111, 'admin', 'delete_seat', 'seats', 998, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:47:41'),
(112, 'admin', 'employee_activated', 'employee', 26, 'Employee activated: WD69 - JJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:53:16'),
(113, 'admin', 'employee_deactivated', 'employee', 26, 'Employee deactivated: WD69 - JJ (Registration cancelled, seats freed)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:53:39'),
(114, 'admin', 'employee_activated', 'employee', 26, 'Employee activated: WD69 - JJ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:53:44'),
(115, 'admin', 'employee_deactivated', 'employee', 26, 'Employee deactivated: WD69 - JJ (Registration cancelled, seats freed)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:54:17'),
(116, 'admin', 'save_seat_layout', 'seats', NULL, '{\"hall_id\":7,\"shift_id\":9,\"seats_count\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 08:55:24'),
(117, 'admin', 'add_shift', 'shifts', 10, '{\"hall_id\":7,\"shift_name\":\"boy boy 2\",\"shift_code\":\"BOY_BOY_2\",\"seat_count\":72}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 09:44:30'),
(118, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-19 17:52:40\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 09:52:40'),
(119, 'admin', 'logout', NULL, NULL, '{\"logout_time\":\"2025-07-19 17:53:36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-19 09:53:36');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','viewer') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$64KK.wiPkJeDLtCYPR624eCWm9UniHH.LsCMG./cRfNlrHOTjlucC', 'admin', 1, '2025-07-19 09:53:42', '2025-07-12 07:53:13', '2025-07-19 09:53:42'),
(2, 'manager', '$2y$10$NEW_HASH_HERE', 'manager', 1, NULL, '2025-07-12 07:53:13', '2025-07-12 07:53:23');

-- --------------------------------------------------------

--
-- Table structure for table `cinema_halls`
--

CREATE TABLE `cinema_halls` (
  `id` int(11) NOT NULL,
  `hall_name` varchar(100) NOT NULL,
  `max_attendees_per_booking` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cinema_halls`
--

INSERT INTO `cinema_halls` (`id`, `hall_name`, `max_attendees_per_booking`, `total_seats`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Cinema Hall 1', 3, 72, 1, '2025-07-09 05:26:18', '2025-07-09 05:26:18'),
(2, 'Cinema Hall 2', 3, 72, 1, '2025-07-09 05:26:18', '2025-07-09 05:26:18'),
(3, 'Cinema Hall 3', 3, 72, 0, '2025-07-15 12:18:44', '2025-07-16 03:57:08'),
(4, 'Poopie Hall', 3, 71, 0, '2025-07-15 14:42:23', '2025-07-16 13:24:05'),
(6, 'Unassigned', 0, 0, 0, '2025-07-16 03:12:28', '2025-07-16 03:12:28'),
(7, '1', 3, 72, 1, '2025-07-19 08:44:32', '2025-07-19 08:44:32');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `emp_number` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `shift_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `emp_number`, `full_name`, `is_active`, `shift_id`) VALUES
(1, 'WD001', 'John Smith', 1, 4),
(2, 'WD002', 'Sarah Johnson', 1, 4),
(3, 'WD003', 'Mike Chen', 1, 1),
(4, 'WD004', 'Lisa Rodriguez', 1, NULL),
(5, 'WD005', 'David Kim', 1, 2),
(6, 'WD007', 'Test User', 1, NULL),
(15, 'WD009', 'JOHNSON JOHNSON', 1, 3),
(16, 'TEST001', 'Test User', 1, NULL),
(17, 'TEST002', 'Test User', 1, 4),
(18, 'TEST003', 'Test User', 0, NULL),
(20, 'BRO123', 'Mike', 1, 2),
(21, 'BRO111', 'BRRUH', 1, 4),
(22, 'BRO12', 'NAME FULL', 1, 6),
(23, 'PP1', 'PooPoo2', 1, 7),
(24, 'BRO2', 'hehehe', 1, 4),
(25, 'TST3', 'NAMEEE', 1, 4),
(26, 'WD69', 'JJ', 0, 9);

-- --------------------------------------------------------

--
-- Table structure for table `event_settings`
--

CREATE TABLE `event_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','url','color') NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_settings`
--

INSERT INTO `event_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'movie_name', 'Super Cool Movie', 'text', 'Name of the movie being shown', 1, '2025-07-09 05:26:19', '2025-07-16 13:32:52'),
(2, 'screening_time', 'Friday | 16 May \'25 | 8.30 PM', 'text', 'Complete screening time display', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(3, 'event_description', 'Join us for an exclusive screening of Movie Name! Enjoy complimentary popcorn, drinks, and a great movie experience with your colleagues and families.', 'text', 'Event description', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(4, 'registration_enabled', 'true', 'boolean', 'Enable/disable registration', 0, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(5, 'allow_temp_registration', 'true', 'boolean', 'Allow temporary registrations', 0, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(6, 'movie_date', 'Set date', 'text', 'Movie screening date', 1, '2025-07-09 05:26:19', '2025-07-09 16:04:49'),
(7, 'movie_time', 'poopi time', 'text', 'Movie screening time', 1, '2025-07-09 05:26:19', '2025-07-16 13:35:02'),
(8, 'movie_location', 'Cinema Complex', 'text', 'Screening location', 1, '2025-07-09 05:26:19', '2025-07-09 16:04:49'),
(9, 'site_logo', 'uploads/logo_1751030847.png', 'url', 'Site logo path', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(10, 'primary_color', '#fafaff', 'color', 'Primary theme color (gold)', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(11, 'secondary_color', '#090b0b', 'color', 'Secondary theme color (blue)', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(12, 'background_theme', 'light', 'text', 'Background theme (dark/light)', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(13, 'hero_background_image', 'https://images.unsplash.com/photo-1489599735734-79b4169c4388?w=1920', 'url', 'Hero section background image', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(14, 'custom_css', '', 'text', 'Custom CSS for styling', 0, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(15, 'footer_text', '© 2025 Western Digital – Internal Movie Night Event', 'text', 'Footer text', 1, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(16, 'max_registrations', '100', 'number', 'Maximum total registrations allowed', 0, '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(17, 'admin_session_timeout', '3600', 'number', 'Admin session timeout in seconds', 0, '2025-07-12 07:53:13', '2025-07-12 07:53:13'),
(18, 'admin_max_login_attempts', '5', 'number', 'Maximum admin login attempts before lockout', 0, '2025-07-12 07:53:13', '2025-07-12 07:53:13'),
(19, 'admin_lockout_duration', '900', 'number', 'Admin lockout duration in seconds', 0, '2025-07-12 07:53:13', '2025-07-12 07:53:13'),
(22, 'venue_name', 'Cinama hall', 'text', NULL, 0, '2025-07-12 12:07:30', '2025-07-12 12:07:30'),
(23, 'max_attendees', '3', 'text', NULL, 0, '2025-07-12 12:07:30', '2025-07-12 12:13:11'),
(24, 'shift_labels', '', 'text', NULL, 0, '2025-07-12 12:07:30', '2025-07-12 12:07:30'),
(30, 'default_seat_count', '72', 'text', NULL, 0, '2025-07-16 13:35:26', '2025-07-16 13:35:26'),
(31, 'company_name', 'Western Digital', 'text', NULL, 0, '2025-07-16 13:35:29', '2025-07-16 13:35:29');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `success`, `message`, `created_at`) VALUES
(36, '::1', 'admin', 1, 'Successful login', '2025-07-19 09:52:46'),
(37, '::1', 'admin', 1, 'Successful login', '2025-07-19 09:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `request_count` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `identifier`, `request_count`, `created_at`) VALUES
(10, '::1', 1, '2025-07-09 05:49:41'),
(11, '::1', 1, '2025-07-09 05:49:54'),
(12, '::1', 1, '2025-07-09 05:50:08'),
(13, '::1', 1, '2025-07-09 05:50:14'),
(42, '::1', 1, '2025-07-09 16:07:14');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `emp_number` varchar(20) NOT NULL,
  `staff_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attendee_count` int(11) NOT NULL,
  `hall_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `selected_seats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `screening_time` varchar(100) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('active','cancelled','completed') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `emp_number`, `staff_name`, `email`, `attendee_count`, `hall_id`, `shift_id`, `selected_seats`, `movie_name`, `screening_time`, `registration_date`, `ip_address`, `user_agent`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(12, 'WD001', 'John Smith', NULL, 3, 1, 1, '[\"A1\",\"B1\",\"B2\"]', 'SuperHero Movie', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-12 06:53:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-12 06:53:36', '2025-07-17 15:15:26'),
(13, 'WD009', 'JOHNSON JOHNSON', NULL, 3, 1, 1, '[\"A5\",\"B5\",\"C5\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-12 12:26:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-12 12:26:33', '2025-07-17 15:15:43'),
(14, 'WD005', 'David Kim', NULL, 3, 2, 4, '[\"A12\",\"B12\",\"C12\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-13 05:53:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-13 05:53:26', '2025-07-16 05:10:14'),
(15, 'BRO123', 'Mike', NULL, 3, 1, 2, '[\"A7\",\"A8\",\"A9\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-15 12:17:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-15 12:17:20', '2025-07-15 12:17:20'),
(16, 'BRO12', 'NAME FULL', NULL, 3, 4, 0, '[\"A1\",\"A2\",\"A3\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-15 14:43:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-15 14:43:52', '2025-07-16 03:57:00'),
(17, 'PP1', 'PooPoo2', NULL, 3, 4, 0, '[\"E5\",\"G10\",\"J10\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-15 15:35:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-15 15:35:48', '2025-07-16 03:56:50'),
(19, 'TST3', 'NAMEEE', NULL, 3, 2, 3, '[\"A1\",\"A2\",\"A3\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 09:42:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-16 09:42:09', '2025-07-16 09:42:19'),
(22, 'TST3', 'NAMEEE', NULL, 2, 1, 1, '[\"A2\",\"A3\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 09:56:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-16 09:56:21', '2025-07-16 09:56:41'),
(23, 'TST3', 'NAMEEE', NULL, 3, 2, 4, '[\"A7\",\"A8\",\"B8\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 09:56:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-16 09:56:57', '2025-07-16 09:56:57'),
(24, 'BRO111', 'BRRUH', NULL, 3, 2, 4, '[\"B7\",\"C7\",\"C8\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 10:03:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-16 10:03:41', '2025-07-16 10:03:41'),
(25, 'BRO2', 'hehehe', NULL, 3, 2, 4, '[\"A9\",\"B9\",\"C9\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 10:38:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-16 10:38:05', '2025-07-16 10:38:05'),
(26, 'WD002', 'Sarah Johnson', NULL, 3, 2, 4, '[\"A10\",\"B10\",\"C11\"]', 'Bladerunner 2099', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-16 13:31:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'active', NULL, '2025-07-16 13:31:59', '2025-07-16 13:31:59'),
(27, 'WD69', 'JJ', NULL, 3, 7, 9, '[\"A1\",\"A2\",\"B2\"]', 'Super Cool Movie', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-19 08:46:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-19 08:46:39', '2025-07-19 08:53:39'),
(28, 'WD69', 'JJ', NULL, 3, 7, 9, '[\"A3\",\"A4\",\"B3\"]', 'Super Cool Movie', 'Friday | 16 May \'25 | 8.30 PM', '2025-07-19 08:53:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', 'cancelled', NULL, '2025-07-19 08:53:47', '2025-07-19 08:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `seats`
--

CREATE TABLE `seats` (
  `id` int(11) NOT NULL,
  `hall_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `row_letter` varchar(2) NOT NULL,
  `seat_position` int(11) NOT NULL,
  `status` enum('available','occupied','blocked','reserved') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seats`
--

INSERT INTO `seats` (`id`, `hall_id`, `shift_id`, `seat_number`, `row_letter`, `seat_position`, `status`, `created_at`, `updated_at`) VALUES
(122, 2, 3, 'A1', 'A', 1, 'available', '2025-07-09 05:26:19', '2025-07-16 09:42:19'),
(123, 2, 3, 'A2', 'A', 2, 'available', '2025-07-09 05:26:19', '2025-07-16 09:42:19'),
(124, 2, 3, 'A3', 'A', 3, 'available', '2025-07-09 05:26:19', '2025-07-16 09:42:19'),
(125, 2, 3, 'A4', 'A', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(126, 2, 3, 'A5', 'A', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(127, 2, 3, 'A6', 'A', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(128, 2, 3, 'B1', 'B', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(129, 2, 3, 'B2', 'B', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(130, 2, 3, 'B3', 'B', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(131, 2, 3, 'B4', 'B', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(132, 2, 3, 'B5', 'B', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(133, 2, 3, 'B6', 'B', 6, 'available', '2025-07-09 05:26:19', '2025-07-13 06:08:36'),
(134, 2, 3, 'C1', 'C', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(135, 2, 3, 'C2', 'C', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(136, 2, 3, 'C3', 'C', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(137, 2, 3, 'C4', 'C', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(138, 2, 3, 'C5', 'C', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(139, 2, 3, 'C6', 'C', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(140, 2, 3, 'D1', 'D', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(141, 2, 3, 'D2', 'D', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(142, 2, 3, 'D3', 'D', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(143, 2, 3, 'D4', 'D', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(144, 2, 3, 'D5', 'D', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(145, 2, 3, 'D6', 'D', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(146, 2, 3, 'E1', 'E', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(147, 2, 3, 'E2', 'E', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(148, 2, 3, 'E3', 'E', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(149, 2, 3, 'E4', 'E', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(150, 2, 3, 'E5', 'E', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(151, 2, 3, 'E6', 'E', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(152, 2, 3, 'F1', 'F', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(153, 2, 3, 'F2', 'F', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(154, 2, 3, 'F3', 'F', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(155, 2, 3, 'F4', 'F', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(156, 2, 3, 'F5', 'F', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(157, 2, 3, 'F6', 'F', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(158, 2, 3, 'G1', 'G', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(159, 2, 3, 'G2', 'G', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(160, 2, 3, 'G3', 'G', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(161, 2, 3, 'G4', 'G', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(162, 2, 3, 'G5', 'G', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(163, 2, 3, 'G6', 'G', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(164, 2, 3, 'H1', 'H', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(165, 2, 3, 'H2', 'H', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(166, 2, 3, 'H3', 'H', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(167, 2, 3, 'H4', 'H', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(168, 2, 3, 'H5', 'H', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(169, 2, 3, 'H6', 'H', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(170, 2, 3, 'J1', 'J', 1, 'available', '2025-07-09 05:26:19', '2025-07-12 06:51:39'),
(171, 2, 3, 'J2', 'J', 2, 'available', '2025-07-09 05:26:19', '2025-07-12 06:51:39'),
(172, 2, 3, 'J3', 'J', 3, 'available', '2025-07-09 05:26:19', '2025-07-12 06:51:39'),
(173, 2, 3, 'J4', 'J', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(174, 2, 3, 'J5', 'J', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(175, 2, 3, 'J6', 'J', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(176, 2, 3, 'K1', 'K', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(177, 2, 3, 'K2', 'K', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(178, 2, 3, 'K3', 'K', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(179, 2, 3, 'K4', 'K', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(180, 2, 3, 'K5', 'K', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(181, 2, 3, 'K6', 'K', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(182, 2, 3, 'L1', 'L', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(183, 2, 3, 'L2', 'L', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(184, 2, 3, 'L3', 'L', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(185, 2, 3, 'L4', 'L', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(186, 2, 3, 'L5', 'L', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(187, 2, 3, 'L6', 'L', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(188, 2, 3, 'M1', 'M', 1, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(189, 2, 3, 'M2', 'M', 2, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(190, 2, 3, 'M3', 'M', 3, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(191, 2, 3, 'M4', 'M', 4, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(192, 2, 3, 'M5', 'M', 5, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(193, 2, 3, 'M6', 'M', 6, 'available', '2025-07-09 05:26:19', '2025-07-09 05:26:19'),
(465, 1, 1, 'A1', 'A', 1, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:26'),
(466, 1, 1, 'A2', 'A', 2, 'available', '2025-07-13 08:17:54', '2025-07-16 09:56:41'),
(467, 1, 1, 'A3', 'A', 3, 'available', '2025-07-13 08:17:54', '2025-07-16 09:56:41'),
(468, 1, 1, 'A4', 'A', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(469, 1, 1, 'A5', 'A', 5, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:43'),
(470, 1, 1, 'A6', 'A', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(472, 1, 1, 'B1', 'B', 1, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:26'),
(473, 1, 1, 'B2', 'B', 2, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:26'),
(474, 1, 1, 'B3', 'B', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(475, 1, 1, 'B4', 'B', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(476, 1, 1, 'B5', 'B', 5, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:43'),
(477, 1, 1, 'B6', 'B', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(478, 1, 1, 'C1', 'C', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(479, 1, 1, 'C2', 'C', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(480, 1, 1, 'C3', 'C', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(481, 1, 1, 'C4', 'C', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(482, 1, 1, 'C5', 'C', 5, 'available', '2025-07-13 08:17:54', '2025-07-17 15:15:43'),
(483, 1, 1, 'C6', 'C', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(484, 1, 1, 'D1', 'D', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(485, 1, 1, 'D2', 'D', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(486, 1, 1, 'D3', 'D', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(487, 1, 1, 'D4', 'D', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(488, 1, 1, 'D5', 'D', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(489, 1, 1, 'D6', 'D', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(490, 1, 1, 'E1', 'E', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(491, 1, 1, 'E2', 'E', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(492, 1, 1, 'E3', 'E', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(493, 1, 1, 'E4', 'E', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(494, 1, 1, 'E5', 'E', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(495, 1, 1, 'E6', 'E', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(496, 1, 1, 'F1', 'F', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(497, 1, 1, 'F2', 'F', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(498, 1, 1, 'F3', 'F', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(499, 1, 1, 'F4', 'F', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(500, 1, 1, 'F5', 'F', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(501, 1, 1, 'F6', 'F', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(502, 1, 1, 'G1', 'G', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(503, 1, 1, 'G2', 'G', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(504, 1, 1, 'G3', 'G', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(505, 1, 1, 'G4', 'G', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(506, 1, 1, 'G5', 'G', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(507, 1, 1, 'G6', 'G', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(508, 1, 1, 'H1', 'H', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(509, 1, 1, 'H2', 'H', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(510, 1, 1, 'H3', 'H', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(511, 1, 1, 'H4', 'H', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(512, 1, 1, 'H5', 'H', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(513, 1, 1, 'H6', 'H', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(514, 1, 1, 'J1', 'J', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(515, 1, 1, 'J2', 'J', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(516, 1, 1, 'J3', 'J', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(517, 1, 1, 'J4', 'J', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(518, 1, 1, 'J5', 'J', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(519, 1, 1, 'J6', 'J', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(520, 1, 1, 'K1', 'K', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(521, 1, 1, 'K2', 'K', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(522, 1, 1, 'K3', 'K', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(523, 1, 1, 'K4', 'K', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(524, 1, 1, 'K5', 'K', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(525, 1, 1, 'K6', 'K', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(526, 1, 1, 'L1', 'L', 1, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(527, 1, 1, 'L2', 'L', 2, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(528, 1, 1, 'L3', 'L', 3, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(529, 1, 1, 'L4', 'L', 4, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(530, 1, 1, 'L5', 'L', 5, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(531, 1, 1, 'L6', 'L', 6, 'available', '2025-07-13 08:17:54', '2025-07-13 08:17:54'),
(769, 2, 4, 'A7', 'A', 7, 'occupied', '2025-07-13 09:49:08', '2025-07-16 09:56:57'),
(770, 2, 4, 'A8', 'A', 8, 'occupied', '2025-07-13 09:49:08', '2025-07-16 09:56:57'),
(771, 2, 4, 'A9', 'A', 9, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:38:05'),
(772, 2, 4, 'A10', 'A', 10, 'occupied', '2025-07-13 09:49:08', '2025-07-16 13:31:59'),
(773, 2, 4, 'A11', 'A', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(774, 2, 4, 'A12', 'A', 12, 'available', '2025-07-13 09:49:08', '2025-07-16 05:10:14'),
(775, 2, 4, 'B7', 'B', 7, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:03:41'),
(776, 2, 4, 'B8', 'B', 8, 'occupied', '2025-07-13 09:49:08', '2025-07-16 09:56:57'),
(777, 2, 4, 'B9', 'B', 9, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:38:05'),
(778, 2, 4, 'B10', 'B', 10, 'occupied', '2025-07-13 09:49:08', '2025-07-16 13:31:59'),
(779, 2, 4, 'B11', 'B', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(780, 2, 4, 'B12', 'B', 12, 'available', '2025-07-13 09:49:08', '2025-07-16 05:10:14'),
(781, 2, 4, 'C7', 'C', 7, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:03:41'),
(782, 2, 4, 'C8', 'C', 8, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:03:41'),
(783, 2, 4, 'C9', 'C', 9, 'occupied', '2025-07-13 09:49:08', '2025-07-16 10:38:05'),
(784, 2, 4, 'C10', 'C', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(785, 2, 4, 'C11', 'C', 11, 'occupied', '2025-07-13 09:49:08', '2025-07-16 13:31:59'),
(786, 2, 4, 'C12', 'C', 12, 'available', '2025-07-13 09:49:08', '2025-07-16 05:10:14'),
(787, 2, 4, 'D7', 'D', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(788, 2, 4, 'D8', 'D', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(789, 2, 4, 'D9', 'D', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(790, 2, 4, 'D10', 'D', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(791, 2, 4, 'D11', 'D', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(792, 2, 4, 'D12', 'D', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(793, 2, 4, 'E7', 'E', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(794, 2, 4, 'E8', 'E', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(795, 2, 4, 'E9', 'E', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(796, 2, 4, 'E10', 'E', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(797, 2, 4, 'E11', 'E', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(798, 2, 4, 'E12', 'E', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(799, 2, 4, 'F7', 'F', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(800, 2, 4, 'F8', 'F', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(801, 2, 4, 'F9', 'F', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(802, 2, 4, 'F10', 'F', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(803, 2, 4, 'F11', 'F', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(804, 2, 4, 'F12', 'F', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(805, 2, 4, 'G7', 'G', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(806, 2, 4, 'G8', 'G', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(807, 2, 4, 'G9', 'G', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(808, 2, 4, 'G10', 'G', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(809, 2, 4, 'G11', 'G', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(810, 2, 4, 'G12', 'G', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(811, 2, 4, 'H7', 'H', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(812, 2, 4, 'H8', 'H', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(813, 2, 4, 'H9', 'H', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(814, 2, 4, 'H10', 'H', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(815, 2, 4, 'H11', 'H', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(816, 2, 4, 'H12', 'H', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(817, 2, 4, 'J7', 'J', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(818, 2, 4, 'J8', 'J', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(819, 2, 4, 'J9', 'J', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(820, 2, 4, 'J10', 'J', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(821, 2, 4, 'J11', 'J', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(822, 2, 4, 'J12', 'J', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(823, 2, 4, 'K7', 'K', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(824, 2, 4, 'K8', 'K', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(825, 2, 4, 'K9', 'K', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(826, 2, 4, 'K10', 'K', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(827, 2, 4, 'K11', 'K', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(828, 2, 4, 'K12', 'K', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(829, 2, 4, 'L7', 'L', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(830, 2, 4, 'L8', 'L', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(831, 2, 4, 'L9', 'L', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(832, 2, 4, 'L10', 'L', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(833, 2, 4, 'L11', 'L', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(834, 2, 4, 'L12', 'L', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(835, 2, 4, 'M7', 'M', 7, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(836, 2, 4, 'M8', 'M', 8, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(837, 2, 4, 'M9', 'M', 9, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(838, 2, 4, 'M10', 'M', 10, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(839, 2, 4, 'M11', 'M', 11, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(840, 2, 4, 'M12', 'M', 12, 'available', '2025-07-13 09:49:08', '2025-07-13 09:49:08'),
(844, 1, 2, 'A7', 'A', 7, 'occupied', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(845, 1, 2, 'A8', 'A', 8, 'occupied', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(846, 1, 2, 'A9', 'A', 9, 'occupied', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(847, 1, 2, 'A10', 'A', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(848, 1, 2, 'A11', 'A', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(849, 1, 2, 'B7', 'B', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(850, 1, 2, 'B8', 'B', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(851, 1, 2, 'B9', 'B', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(852, 1, 2, 'B10', 'B', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(853, 1, 2, 'B11', 'B', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(854, 1, 2, 'C7', 'C', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(855, 1, 2, 'C8', 'C', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(856, 1, 2, 'C9', 'C', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(857, 1, 2, 'C10', 'C', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(858, 1, 2, 'C11', 'C', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(859, 1, 2, 'D7', 'D', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(860, 1, 2, 'D8', 'D', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(861, 1, 2, 'D9', 'D', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(862, 1, 2, 'D10', 'D', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(863, 1, 2, 'D11', 'D', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(864, 1, 2, 'E7', 'E', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(865, 1, 2, 'E8', 'E', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(866, 1, 2, 'E9', 'E', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(867, 1, 2, 'E10', 'E', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(868, 1, 2, 'E11', 'E', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(869, 1, 2, 'F7', 'F', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(870, 1, 2, 'F8', 'F', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(871, 1, 2, 'F9', 'F', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(872, 1, 2, 'F10', 'F', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(873, 1, 2, 'F11', 'F', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(874, 1, 2, 'G7', 'G', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(875, 1, 2, 'G8', 'G', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(876, 1, 2, 'G9', 'G', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(877, 1, 2, 'G10', 'G', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(878, 1, 2, 'G11', 'G', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(879, 1, 2, 'H7', 'H', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(880, 1, 2, 'H8', 'H', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(881, 1, 2, 'H9', 'H', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(882, 1, 2, 'H10', 'H', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(883, 1, 2, 'H11', 'H', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(884, 1, 2, 'J7', 'J', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(885, 1, 2, 'J8', 'J', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(886, 1, 2, 'J9', 'J', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(887, 1, 2, 'J10', 'J', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(888, 1, 2, 'J11', 'J', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(889, 1, 2, 'K7', 'K', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(890, 1, 2, 'K8', 'K', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(891, 1, 2, 'K9', 'K', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(892, 1, 2, 'K10', 'K', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(893, 1, 2, 'K11', 'K', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(894, 1, 2, 'L7', 'L', 7, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(895, 1, 2, 'L8', 'L', 8, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(896, 1, 2, 'L9', 'L', 9, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(897, 1, 2, 'L10', 'L', 10, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(898, 1, 2, 'L11', 'L', 11, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(899, 1, 2, 'A12', 'A', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(900, 1, 2, 'B12', 'B', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(901, 1, 2, 'C12', 'C', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(902, 1, 2, 'D12', 'D', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(903, 1, 2, 'E12', 'E', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(904, 1, 2, 'F12', 'F', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(905, 1, 2, 'G12', 'G', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(906, 1, 2, 'H12', 'H', 12, 'available', '2025-07-15 12:18:19', '2025-07-15 12:18:19'),
(927, 3, 5, 'A1', 'A', 1, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(928, 3, 5, 'A2', 'A', 2, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(929, 3, 5, 'A3', 'A', 3, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(930, 3, 5, 'A4', 'A', 4, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(931, 3, 5, 'B1', 'B', 1, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(932, 3, 5, 'B2', 'B', 2, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(933, 3, 5, 'B3', 'B', 3, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(934, 3, 5, 'B4', 'B', 4, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(935, 3, 5, 'C1', 'C', 1, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(936, 3, 5, 'C2', 'C', 2, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(937, 3, 5, 'C3', 'C', 3, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(938, 3, 5, 'C4', 'C', 4, 'available', '2025-07-15 13:59:37', '2025-07-15 13:59:37'),
(959, 4, 6, 'A1', 'A', 1, 'occupied', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(960, 4, 6, 'A2', 'A', 2, 'occupied', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(961, 4, 6, 'A4', 'A', 4, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(962, 4, 6, 'B1', 'B', 1, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(963, 4, 6, 'B2', 'B', 2, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(964, 4, 6, 'B3', 'B', 3, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(965, 4, 6, 'B4', 'B', 4, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(966, 4, 6, 'C1', 'C', 1, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(967, 4, 6, 'C2', 'C', 2, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(968, 4, 6, 'C3', 'C', 3, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(969, 4, 6, 'C4', 'C', 4, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(970, 4, 6, 'D1', 'D', 1, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(971, 4, 6, 'D2', 'D', 2, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(972, 4, 6, 'D3', 'D', 3, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(973, 4, 6, 'D4', 'D', 4, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(974, 4, 6, 'E1', 'E', 1, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(975, 4, 6, 'E2', 'E', 2, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(976, 4, 6, 'E3', 'E', 3, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(977, 4, 6, 'E4', 'E', 4, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(978, 4, 6, 'A3', 'A', 3, 'available', '2025-07-15 14:44:07', '2025-07-15 14:44:07'),
(979, 4, 7, 'A1', 'A', 1, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(980, 4, 7, 'B2', 'B', 2, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(981, 4, 7, 'A2', 'A', 2, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(982, 4, 7, 'A3', 'A', 3, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(983, 4, 7, 'E6', 'E', 6, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(984, 4, 7, 'D6', 'D', 6, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(985, 4, 7, 'C6', 'C', 6, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(986, 4, 7, 'C5', 'C', 5, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(987, 4, 7, 'D5', 'D', 5, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(988, 4, 7, 'E5', 'E', 5, 'occupied', '2025-07-15 15:34:34', '2025-07-15 15:35:48'),
(989, 4, 7, 'D4', 'D', 4, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(990, 4, 7, 'C4', 'C', 4, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(991, 4, 7, 'B4', 'B', 4, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(992, 4, 7, 'A4', 'A', 4, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(993, 4, 7, 'A5', 'A', 5, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(994, 4, 7, 'B5', 'B', 5, 'available', '2025-07-15 15:34:34', '2025-07-15 15:34:34'),
(995, 4, 7, 'G10', 'G', 10, 'occupied', '2025-07-15 15:34:34', '2025-07-15 15:35:48'),
(996, 4, 7, 'J10', 'J', 10, 'occupied', '2025-07-15 15:34:34', '2025-07-15 15:35:48'),
(1010, 7, 9, 'A3', 'A', 3, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1011, 7, 9, 'A4', 'A', 4, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1012, 7, 9, 'B1', 'B', 1, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1013, 7, 9, 'B3', 'B', 3, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1014, 7, 9, 'B4', 'B', 4, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1015, 7, 9, 'C1', 'C', 1, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1016, 7, 9, 'C2', 'C', 2, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1017, 7, 9, 'C3', 'C', 3, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1018, 7, 9, 'C4', 'C', 4, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1019, 7, 9, 'B2', 'B', 2, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1020, 7, 9, 'A2', 'A', 2, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24'),
(1021, 7, 9, 'A1', 'A', 1, 'available', '2025-07-19 08:55:24', '2025-07-19 08:55:24');

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

CREATE TABLE `security_audit_log` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_audit_log`
--

INSERT INTO `security_audit_log` (`id`, `event_type`, `user_id`, `ip_address`, `user_agent`, `details`, `risk_level`, `created_at`) VALUES
(1, 'admin_login_failed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'medium', '2025-07-12 07:56:06'),
(2, 'admin_login_failed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'medium', '2025-07-12 07:56:08'),
(3, 'admin_login_failed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'medium', '2025-07-12 07:56:12'),
(4, 'admin_login_failed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'medium', '2025-07-12 07:58:53'),
(5, 'admin_login_failed', 'admin', '', '', '[]', 'medium', '2025-07-12 08:21:28'),
(6, 'admin_login_failed', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'medium', '2025-07-12 08:27:58'),
(7, 'admin_login_failed', 'admin', '', '', '[]', 'medium', '2025-07-12 08:30:34'),
(8, 'admin_login_failed', 'admin', '', '', '[]', 'medium', '2025-07-12 08:32:14'),
(9, 'admin_login_failed', 'admin', '', '', '[]', 'medium', '2025-07-12 08:44:10'),
(10, 'admin_login_success', 'admin', '', '', '[]', 'low', '2025-07-12 08:46:52'),
(11, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-12 09:22:19'),
(12, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-12 09:22:23'),
(13, 'admin_login_failed', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'medium', '2025-07-12 12:32:59'),
(14, 'admin_login_failed', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'medium', '2025-07-12 12:33:00'),
(15, 'admin_login_failed', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'medium', '2025-07-12 12:33:07'),
(16, 'admin_login_success', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'low', '2025-07-12 12:34:03'),
(17, 'admin_login_success', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'low', '2025-07-12 13:35:30'),
(18, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-13 05:01:48'),
(19, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-13 06:12:51'),
(20, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-16 01:22:53'),
(21, 'admin_login_success', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'low', '2025-07-16 11:29:08'),
(22, 'admin_login_success', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'low', '2025-07-16 11:29:16'),
(23, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-16 12:44:12'),
(24, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-16 15:10:59'),
(25, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-17 10:51:26'),
(26, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-17 10:52:16'),
(27, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-17 10:53:40'),
(28, 'admin_login_success', 'admin', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Mobile Safari/537.36', '[]', 'low', '2025-07-17 15:17:32'),
(29, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-18 08:52:23'),
(30, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-19 08:44:10'),
(31, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-19 09:52:46'),
(32, 'admin_login_success', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '[]', 'low', '2025-07-19 09:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `hall_id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `shift_code` varchar(20) NOT NULL,
  `seat_prefix` varchar(5) NOT NULL,
  `seat_count` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `hall_id`, `shift_name`, `shift_code`, `seat_prefix`, `seat_count`, `start_time`, `end_time`, `is_active`, `created_at`) VALUES
(1, 1, 'Normal Shift', 'NORMAL_SHIFT', '', 72, '19:00:00', '22:00:00', 1, '2025-07-09 05:26:18'),
(2, 1, 'Crew C (Day Shift)', 'CREW_C', '', 60, '14:00:00', '17:00:00', 1, '2025-07-09 05:26:18'),
(3, 2, 'Crew A (Off/Rest Day)', 'CREW_A_(OFF/REST_DAY', '', 71, '19:00:00', '22:00:00', 1, '2025-07-09 05:26:18'),
(4, 2, 'Crew B (Off/Rest Day)', 'CREW_B_(OFF/REST_DAY', '', 72, '19:00:00', '22:00:00', 1, '2025-07-09 05:26:18'),
(5, 2, 'Test Shift', 'TEST_SHIFT', '', 72, '19:00:00', '22:00:00', 0, '2025-07-15 12:18:56'),
(6, 2, 'Test Shift 2', 'TEST_SHIFT_2', '', 72, '19:00:00', '22:00:00', 0, '2025-07-15 14:42:30'),
(7, 0, 'Pee pee shift', 'PEE_PEE_SHIFT', '', 72, '19:00:00', '22:00:00', 0, '2025-07-15 15:33:58'),
(8, 0, 'Unassigned', 'UNASSIGNED', '', 0, '00:00:00', '00:00:00', 0, '2025-07-16 03:56:18'),
(9, 7, 'boy boy', 'BOY_BOY', '', 72, '19:00:00', '22:00:00', 1, '2025-07-19 08:44:45'),
(10, 7, 'boy boy 2', 'BOY_BOY_2', '', 72, '19:00:00', '22:00:00', 1, '2025-07-19 09:44:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_time` (`admin_user`,`created_at`),
  ADD KEY `idx_action_time` (`action`,`created_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `cinema_halls`
--
ALTER TABLE `cinema_halls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_number` (`emp_number`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_emp_lookup` (`emp_number`,`is_active`),
  ADD KEY `idx_emp_number` (`emp_number`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `fk_shift` (`shift_id`);

--
-- Indexes for table `event_settings`
--
ALTER TABLE `event_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_public` (`is_public`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`created_at`),
  ADD KEY `idx_success_time` (`success`,`created_at`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_time` (`identifier`,`created_at`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hall_shift` (`hall_id`,`shift_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_registration_date` (`registration_date`);

--
-- Indexes for table `seats`
--
ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_seat` (`hall_id`,`shift_id`,`seat_number`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `idx_status_lookup` (`hall_id`,`shift_id`,`status`);

--
-- Indexes for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_risk_level` (`risk_level`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hall_id` (`hall_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cinema_halls`
--
ALTER TABLE `cinema_halls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `event_settings`
--
ALTER TABLE `event_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1022;

--
-- AUTO_INCREMENT for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
