-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 11, 2025 at 08:15 PM
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
-- Database: `barangay_request_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangay_officials`
--

CREATE TABLE `barangay_officials` (
  `id` int(11) NOT NULL,
  `punong_barangay` varchar(255) DEFAULT NULL,
  `sk_chairperson` varchar(255) DEFAULT NULL,
  `barangay_secretary` varchar(255) DEFAULT NULL,
  `barangay_treasurer` varchar(255) DEFAULT NULL,
  `other_official` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_officials`
--

INSERT INTO `barangay_officials` (`id`, `punong_barangay`, `sk_chairperson`, `barangay_secretary`, `barangay_treasurer`, `other_official`, `created_at`, `updated_at`) VALUES
(1, 'REMEDIOS S. BEDIA', 'albert jdfdhgh', 'fgfigmfk', 'marites dshdshd', 'fdfjdifd', '2025-03-11 17:31:25', '2025-03-11 18:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `is_read`, `is_system`, `created_at`) VALUES
(1, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:32:46'),
(2, NULL, 'New document request: Barangay Clearance (Request #6)', 0, 1, '2025-03-11 14:32:46'),
(3, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:33:06'),
(4, NULL, 'New document request: Barangay Clearance (Request #7)', 0, 1, '2025-03-11 14:33:06'),
(5, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:35:19'),
(6, NULL, 'New document request: Barangay Clearance (Request #9)', 0, 1, '2025-03-11 14:35:19'),
(7, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:38:48'),
(8, NULL, 'New document request: Barangay Clearance (Request #11)', 0, 1, '2025-03-11 14:38:48'),
(9, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:41:08'),
(10, NULL, 'New document request: Barangay Clearance (Request #12)', 0, 1, '2025-03-11 14:41:08'),
(11, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:41:56'),
(12, NULL, 'New document request: Barangay Clearance (Request #13)', 0, 1, '2025-03-11 14:41:56'),
(13, 6, 'Your request for Barangay Clearance has been submitted successfully.', 0, 0, '2025-03-11 14:52:57'),
(14, NULL, 'New document request: Barangay Clearance (Request #14)', 0, 1, '2025-03-11 14:52:57'),
(15, 6, 'Your request for Barangay Clearance (Request #14) has been cancelled.', 0, 0, '2025-03-11 15:27:53'),
(16, NULL, 'Request #14 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 15:27:53'),
(17, 6, 'Your request for Barangay Clearance (Request #13) has been cancelled.', 0, 0, '2025-03-11 15:28:10'),
(18, NULL, 'Request #13 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 15:28:10'),
(19, 6, 'Your request for Barangay Clearance (Request #12) has been cancelled.', 0, 0, '2025-03-11 15:34:23'),
(20, NULL, 'Request #12 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 15:34:23'),
(21, 6, 'Your request for Barangay Clearance (Request #11) has been cancelled.', 0, 0, '2025-03-11 15:44:05'),
(22, NULL, 'Request #11 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 15:44:05'),
(23, 6, 'Your request for Barangay Clearance (Request #9) has been cancelled.', 0, 0, '2025-03-11 16:09:27'),
(24, NULL, 'Request #9 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 16:09:27'),
(25, 6, 'Your request for Barangay Clearance (Request #5) has been cancelled.', 0, 0, '2025-03-11 16:15:55'),
(26, NULL, 'Request #5 for Barangay Clearance has been cancelled by the user.', 0, 1, '2025-03-11 16:15:55');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `urgent_request` tinyint(1) NOT NULL DEFAULT 0,
  `processing_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `payment_status` tinyint(1) NOT NULL DEFAULT 0,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`request_id`, `user_id`, `document_type`, `purpose`, `urgent_request`, `processing_fee`, `status`, `admin_remarks`, `created_at`, `updated_at`, `completed_at`, `payment_status`, `admin_notes`) VALUES
(1, 6, 'barangay_clearance', 'For Education', 1, 75.00, 'pending', NULL, '2025-03-11 14:01:11', '2025-03-11 14:01:11', NULL, 0, NULL),
(2, 6, 'barangay_clearance', 'For Loan Application', 1, 75.00, 'pending', NULL, '2025-03-11 14:24:55', '2025-03-11 14:24:55', NULL, 0, NULL),
(3, 6, 'barangay_clearance', 'For Loan Application', 1, 75.00, 'completed', '', '2025-03-11 14:25:15', '2025-03-11 19:12:04', NULL, 0, NULL),
(13, 6, 'barangay_clearance', 'For Government Transaction', 1, 75.00, 'rejected', 'asdasasa', '2025-03-11 14:41:56', '2025-03-11 19:05:57', NULL, 0, 'get this shit');

-- --------------------------------------------------------

--
-- Table structure for table `request_details`
--

CREATE TABLE `request_details` (
  `detail_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `age` int(3) NOT NULL,
  `address` text NOT NULL,
  `civil_status` varchar(50) NOT NULL,
  `residency_status` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_details`
--

INSERT INTO `request_details` (`detail_id`, `request_id`, `fullname`, `age`, `address`, `civil_status`, `residency_status`, `created_at`) VALUES
(1, 6, 'larry denver biaco', 32, 'sadfdsfds', 'widowed', 'temporary', '2025-03-11 14:32:46'),
(2, 7, 'larry denver biaco', 11, 'wwqwqwqw', 'married', 'temporary', '2025-03-11 14:33:06'),
(3, 9, 'larry denver biaco', 56, 'fgvfdgfdg', 'married', 'permanent', '2025-03-11 14:35:19'),
(4, 11, 'larry denver', 78, ' fsgfgsdf', 'married', 'temporary', '2025-03-11 14:38:48'),
(5, 12, 'denver', 78, ' fsgfgsdf', 'married', 'temporary', '2025-03-11 14:41:08'),
(6, 13, 'denver hehqah', 32, 'sdfsdfdsfs', 'married', 'permanent', '2025-03-11 14:41:56'),
(7, 14, 'denver hehqah', 34, 'sdfsdfdsf', 'married', 'permanent', '2025-03-11 14:52:57');

-- --------------------------------------------------------

--
-- Table structure for table `requirement_verification`
--

CREATE TABLE `requirement_verification` (
  `verification_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `requirement_name` varchar(100) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `zone` varchar(20) NOT NULL,
  `house_number` varchar(50) NOT NULL,
  `id_path` varchar(255) NOT NULL,
  `user_type` enum('resident','staff','admin') NOT NULL DEFAULT 'resident',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `first_name`, `middle_name`, `last_name`, `birthday`, `gender`, `contact_number`, `zone`, `house_number`, `id_path`, `user_type`, `status`, `created_at`, `updated_at`) VALUES
(6, 'larrydenverbiaco@gmail.com', '$2y$10$V0Fuq4KWqoXH5AY/yIqoKOlZrReaZh42E36MfxiBN.w/GZ7JdP/2O', 'larry', 'x', 'sasasas', '1999-03-14', 'male', '09165789087', 'zone3', '0976', 'uploads/ID_67cf36125ec5c.jpg', 'resident', 'active', '2025-03-10 18:57:22', '2025-03-11 13:45:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `request_details`
--
ALTER TABLE `request_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `requirement_verification`
--
ALTER TABLE `requirement_verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `request_details`
--
ALTER TABLE `request_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `requirement_verification`
--
ALTER TABLE `requirement_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `requirement_verification`
--
ALTER TABLE `requirement_verification`
  ADD CONSTRAINT `requirement_verification_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requirement_verification_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
