CREATE TABLE `cinema_halls` (
  `id` int(11) NOT NULL,
  `hall_name` varchar(100) NOT NULL,
  `max_attendees_per_booking` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `emp_number` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `shift_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_time` (`admin_user`,`created_at`),
  ADD KEY `idx_action_time` (`action`,`created_at`);

ALTER TABLE `cinema_halls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_number` (`emp_number`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_emp_lookup` (`emp_number`,`is_active`),
  ADD KEY `idx_emp_number` (`emp_number`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `fk_shift` (`shift_id`);

ALTER TABLE `event_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_public` (`is_public`);

ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hall_shift` (`hall_id`,`shift_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_registration_date` (`registration_date`);

ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_seat` (`hall_id`,`shift_id`,`seat_number`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `idx_status_lookup` (`hall_id`,`shift_id`,`status`);

ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hall_id` (`hall_id`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

ALTER TABLE `cinema_halls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `event_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

ALTER TABLE `seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1951;

ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `employees`
  ADD CONSTRAINT `fk_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`);