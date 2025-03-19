-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 19, 2025 at 02:42 PM
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
  `name` varchar(255) NOT NULL,
  `position` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_officials`
--

INSERT INTO `barangay_officials` (`id`, `name`, `position`, `created_at`, `updated_at`) VALUES
(128, 'sasasdsasa', 'Punong Barangay', '2025-03-13 17:58:18', '2025-03-13 17:58:18'),
(129, 'sasas', 'Sangguniang Barangay Member', '2025-03-13 17:58:18', '2025-03-13 17:58:18'),
(130, 'wwwwwwwww', 'Sangguniang Barangay Member', '2025-03-13 17:58:18', '2025-03-13 17:58:18'),
(131, 'fgfgfg', 'SK Chairman', '2025-03-13 17:58:18', '2025-03-13 17:58:18');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unread` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `is_read`, `is_system`, `created_at`, `unread`) VALUES
(2, NULL, 'New document request: Barangay Clearance (Request #6)', 1, 1, '2025-03-11 14:32:46', NULL),
(4, NULL, 'New document request: Barangay Clearance (Request #7)', 1, 1, '2025-03-11 14:33:06', NULL),
(6, NULL, 'New document request: Barangay Clearance (Request #9)', 1, 1, '2025-03-11 14:35:19', NULL),
(8, NULL, 'New document request: Barangay Clearance (Request #11)', 1, 1, '2025-03-11 14:38:48', NULL),
(10, NULL, 'New document request: Barangay Clearance (Request #12)', 1, 1, '2025-03-11 14:41:08', NULL),
(12, NULL, 'New document request: Barangay Clearance (Request #13)', 1, 1, '2025-03-11 14:41:56', NULL),
(14, NULL, 'New document request: Barangay Clearance (Request #14)', 1, 1, '2025-03-11 14:52:57', NULL),
(16, NULL, 'Request #14 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 15:27:53', NULL),
(18, NULL, 'Request #13 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 15:28:10', NULL),
(20, NULL, 'Request #12 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 15:34:23', NULL),
(22, NULL, 'Request #11 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 15:44:05', NULL),
(24, NULL, 'Request #9 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 16:09:27', NULL),
(26, NULL, 'Request #5 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-11 16:15:55', NULL),
(28, NULL, 'New document request: Barangay Clearance (Request #15)', 1, 1, '2025-03-13 09:16:50', NULL),
(30, NULL, 'New document request: Barangay Clearance (Request #16)', 1, 1, '2025-03-13 09:18:28', NULL),
(32, NULL, 'New document request: Barangay Clearance (Request #17)', 1, 1, '2025-03-13 09:36:03', NULL),
(33, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-13 11:31:26', NULL),
(34, NULL, 'New document request: Barangay Clearance (Request #18)', 1, 1, '2025-03-13 11:31:26', NULL),
(35, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-13 16:47:08', NULL),
(36, NULL, 'New document request: Barangay Clearance (Request #19)', 1, 1, '2025-03-13 16:47:08', NULL),
(37, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-14 18:13:17', NULL),
(38, NULL, 'New document request: Barangay Clearance (Request #20)', 1, 1, '2025-03-14 18:13:17', NULL),
(39, 10, 'Your request for First Time Jobseeker Certificate has been submitted successfully.', 1, 0, '2025-03-14 18:15:13', NULL),
(40, NULL, 'New document request: First Time Jobseeker Certificate (Request #21)', 1, 1, '2025-03-14 18:15:13', NULL),
(41, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 10:13:10', NULL),
(42, NULL, 'New document request: Barangay Clearance (Request #22) - Payment via GCash', 1, 1, '2025-03-15 10:13:10', NULL),
(43, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-15 10:48:28', NULL),
(44, NULL, 'New document request: Barangay Clearance (Request #23) - Payment via Cash on Pickup', 1, 1, '2025-03-15 10:48:28', NULL),
(45, 10, 'Your request for Barangay Clearance (Request #23) has been cancelled.', 1, 0, '2025-03-15 10:48:33', NULL),
(46, NULL, 'Request #23 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-15 10:48:33', NULL),
(47, 10, 'Your payment proof for Request #22 has been submitted successfully. We will verify your payment shortly.', 1, 0, '2025-03-15 11:06:13', NULL),
(48, NULL, 'New payment proof submitted for Request #22. Reference: 32333131212434', 1, 1, '2025-03-15 11:06:13', NULL),
(49, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-15 11:12:12', NULL),
(50, NULL, 'New document request: Barangay Clearance (Request #24) - Payment via Cash on Pickup', 1, 1, '2025-03-15 11:12:12', NULL),
(51, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 11:45:59', NULL),
(52, NULL, 'New document request: Barangay Clearance (Request #25) - Payment via GCash', 1, 1, '2025-03-15 11:45:59', NULL),
(53, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 11:48:35', NULL),
(54, NULL, 'New document request: Barangay Clearance (Request #26) - Payment via GCash', 1, 1, '2025-03-15 11:48:35', NULL),
(55, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 11:52:31', NULL),
(56, NULL, 'New document request: Barangay Clearance (Request #27) - Payment via GCash', 1, 1, '2025-03-15 11:52:31', NULL),
(57, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 11:56:00', NULL),
(58, NULL, 'New document request: Barangay Clearance (Request #28) - Payment via GCash', 1, 1, '2025-03-15 11:56:00', NULL),
(59, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:00:15', NULL),
(60, NULL, 'New document request: Barangay Clearance (Request #29) - Payment via GCash', 1, 1, '2025-03-15 12:00:15', NULL),
(61, 10, 'Your request for Barangay Clearance (Request #29) has been cancelled.', 1, 0, '2025-03-15 12:00:25', NULL),
(62, NULL, 'Request #29 for Barangay Clearance has been cancelled by the user.', 1, 1, '2025-03-15 12:00:25', NULL),
(63, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:01:16', NULL),
(64, NULL, 'New document request: Barangay Clearance (Request #30) - Payment via GCash', 1, 1, '2025-03-15 12:01:16', NULL),
(65, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:09:56', NULL),
(66, NULL, 'New document request: Barangay Clearance (Request #31) - Payment via GCash', 1, 1, '2025-03-15 12:09:56', NULL),
(67, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:15:51', NULL),
(68, NULL, 'New document request: Barangay Clearance (Request #32) - Payment via GCash', 1, 1, '2025-03-15 12:15:51', NULL),
(69, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:18:30', NULL),
(70, NULL, 'New document request: Barangay Clearance (Request #33) - Payment via GCash', 1, 1, '2025-03-15 12:18:30', NULL),
(71, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:19:58', NULL),
(72, NULL, 'New document request: Barangay Clearance (Request #34) - Payment via GCash', 1, 1, '2025-03-15 12:19:58', NULL),
(73, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:20:50', NULL),
(74, NULL, 'New document request: Barangay Clearance (Request #35) - Payment via GCash', 1, 1, '2025-03-15 12:20:50', NULL),
(75, 10, 'Your request for Barangay Clearance has been submitted successfully.', 1, 0, '2025-03-15 12:23:44', NULL),
(76, NULL, 'New document request: Barangay Clearance (Request #36) - Payment via Cash on Pickup', 1, 1, '2025-03-15 12:23:44', NULL),
(77, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:25:12', NULL),
(78, NULL, 'New document request: Barangay Clearance (Request #37) - Payment via GCash', 1, 1, '2025-03-15 12:25:12', NULL),
(79, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using PayMaya.', 1, 0, '2025-03-15 12:27:33', NULL),
(80, NULL, 'New document request: Barangay Clearance (Request #38) - Payment via PayMaya', 1, 1, '2025-03-15 12:27:33', NULL),
(81, 10, 'Your request for Barangay Clearance has been submitted successfully. Please complete your payment using GCash.', 1, 0, '2025-03-15 12:33:21', NULL),
(82, NULL, 'New document request: Barangay Clearance (Request #39) - Payment via GCash', 1, 1, '2025-03-15 12:33:21', NULL),
(83, 10, 'Your request for Barangay Clearance has been submitted successfully. Payment proof has been received.', 1, 0, '2025-03-15 12:42:31', NULL),
(84, NULL, 'New document request: Barangay Clearance (Request #40) with payment proof', 1, 1, '2025-03-15 12:42:31', NULL),
(85, 10, 'Your request for Barangay Clearance has been submitted successfully. Payment proof has been received.', 1, 0, '2025-03-15 12:52:52', NULL),
(86, NULL, 'New document request: Barangay Clearance (Request #41) with payment proof', 1, 1, '2025-03-15 12:52:52', NULL),
(87, 10, 'Your request for Barangay Clearance has been submitted successfully. Payment proof has been received.', 1, 0, '2025-03-15 13:08:58', NULL),
(88, NULL, 'New document request: Barangay Clearance (Request #42) with payment proof', 1, 1, '2025-03-15 13:08:58', NULL),
(89, 10, 'Your request for Barangay Clearance has been submitted successfully. Payment proof has been received.', 1, 0, '2025-03-15 13:09:39', NULL),
(90, NULL, 'New document request: Barangay Clearance (Request #43) with payment proof', 1, 1, '2025-03-15 13:09:39', NULL),
(91, 10, 'Your request for Barangay Clearance has been submitted successfully. Payment proof has been received.', 1, 0, '2025-03-15 15:12:56', NULL),
(92, NULL, 'New document request: Barangay Clearance (Request #44) with payment proof', 1, 1, '2025-03-15 15:12:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `proof_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `payment_notes` text DEFAULT NULL,
  `status` enum('submitted','verified','rejected') DEFAULT 'submitted',
  `created_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_proofs`
--

INSERT INTO `payment_proofs` (`proof_id`, `request_id`, `user_id`, `payment_method`, `payment_reference`, `proof_image`, `payment_notes`, `status`, `created_at`, `verified_at`, `verified_by`, `remarks`) VALUES
(8, 43, 10, 'gcash', '32333131212434', 'uploads/payment_proofs/payment_proof_43_1742044179.jpg', 'wqwqwq', 'verified', '2025-03-15 21:09:39', '2025-03-15 23:09:53', 10, '0'),
(9, 44, 10, 'paymaya', '32333131212434', 'uploads/payment_proofs/payment_proof_44_1742051576.jpg', '', 'verified', '2025-03-15 23:12:56', '2025-03-15 23:24:12', 10, '0');

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
  `status` enum('pending','processing','ready','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `payment_status` tinyint(1) NOT NULL DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`request_id`, `user_id`, `document_type`, `purpose`, `urgent_request`, `processing_fee`, `status`, `admin_remarks`, `created_at`, `updated_at`, `completed_at`, `payment_status`, `admin_notes`, `payment_method`, `payment_reference`, `payment_proof`, `payment_notes`, `payment_date`) VALUES
(43, 10, 'barangay_clearance', 'For Employment', 1, 75.00, 'completed', 'sasasa\r\n\r\nDocument is ready for pickup. Please bring valid ID.\r\n\r\nRequest has been completed.', '2025-03-15 13:09:39', '2025-03-15 15:10:12', NULL, 1, NULL, 'gcash', NULL, NULL, NULL, NULL),
(44, 10, 'barangay_clearance', 'For Business', 1, 75.00, 'completed', '', '2025-03-15 15:12:56', '2025-03-15 15:24:12', NULL, 1, NULL, 'paymaya', NULL, NULL, NULL, NULL);

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
(7, 14, 'denver hehqah', 34, 'sdfsdfdsf', 'married', 'permanent', '2025-03-11 14:52:57'),
(8, 15, 'larry denver biaco', 22, 'sdfadsa', 'divorced', 'permanent', '2025-03-13 09:16:50'),
(9, 16, 'sasas', 32, 'sdsdsd', 'widowed', 'temporary', '2025-03-13 09:18:28'),
(10, 17, 'larry denver biaco', 21, 'SDEASDASD', 'married', 'permanent', '2025-03-13 09:36:03'),
(11, 18, 'larry denver biaco', 32, 'asdsadsda', 'widowed', 'temporary', '2025-03-13 11:31:26'),
(12, 19, 'allen', 18, 'talacuan', 'divorced', 'temporary', '2025-03-13 16:47:08'),
(13, 20, 'larry denver', 23, 'dsdssds', 'single', 'permanent', '2025-03-14 18:13:17'),
(14, 22, 'larry denver biaco', 32, 'dfsdfsdf', 'widowed', 'temporary', '2025-03-15 10:13:10'),
(15, 23, 'larry denver biaco', 21, 'dsdsdsd', 'single', 'permanent', '2025-03-15 10:48:28'),
(16, 24, 'larry denver biaco', 22, 'weqqw', 'single', 'permanent', '2025-03-15 11:12:12'),
(17, 25, 'larry denver biaco', 21, 'dsdsd', 'married', 'permanent', '2025-03-15 11:45:59'),
(18, 26, 'larry denver biaco', 21, 'dassasasasa', 'single', 'permanent', '2025-03-15 11:48:35'),
(19, 27, 'larry denver biaco', 21, 'asasasa', 'single', 'permanent', '2025-03-15 11:52:31'),
(20, 28, 'denver hehqah', 21, 'dsdsds', 'married', 'permanent', '2025-03-15 11:56:00'),
(21, 29, 'denver hehqah', 21, 'dsdsds', 'married', 'permanent', '2025-03-15 12:00:15'),
(22, 30, 'larry denver biaco', 34, 'fsdsd', 'single', 'permanent', '2025-03-15 12:01:16'),
(23, 31, 'larry denver biaco', 21, 'asasa', 'widowed', 'permanent', '2025-03-15 12:09:56'),
(24, 32, 'larry denver biaco', 21, 'dsdssd', 'married', 'temporary', '2025-03-15 12:15:51'),
(25, 33, 'denver hehqah', 21, 'ewewewe', 'single', 'permanent', '2025-03-15 12:18:30'),
(26, 34, 'denver hehqah', 21, 'dsdsd', 'married', 'permanent', '2025-03-15 12:19:58'),
(27, 35, 'denver hehqah', 24, 'dsdsdsd', 'widowed', 'permanent', '2025-03-15 12:20:50'),
(28, 36, 'larry denver biaco', 54, 'asasa', 'single', 'permanent', '2025-03-15 12:23:44'),
(29, 37, 'larry denver biaco', 21, 'dsdsds', 'single', 'permanent', '2025-03-15 12:25:12'),
(30, 38, 'larry denver biaco', 21, 'dsdsd', 'single', 'permanent', '2025-03-15 12:27:33'),
(31, 39, 'larry denver biaco', 21, 'sasasa', 'single', 'permanent', '2025-03-15 12:33:21'),
(32, 40, 'larry denver', 21, 'fddfdf', 'married', 'permanent', '2025-03-15 12:42:31'),
(33, 41, 'gfgfgfgfg', 21, 'sfdsfsdf', 'single', 'permanent', '2025-03-15 12:52:52'),
(34, 42, 'larry denver biaco', 23, 'dfsdfs', 'single', 'temporary', '2025-03-15 13:08:57'),
(35, 43, 'larry denver biaco', 21, 'dssds', 'widowed', 'permanent', '2025-03-15 13:09:39'),
(36, 44, 'wqwqwq', 21, 'sddsdds', 'divorced', 'temporary', '2025-03-15 15:12:56');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `first_name`, `middle_name`, `last_name`, `birthday`, `gender`, `contact_number`, `zone`, `house_number`, `id_path`, `user_type`, `status`, `created_at`, `updated_at`, `profile_pic`) VALUES
(10, 'larrydenverbiaco@gmail.com', '$2y$10$lEzLd9kU/bDt32Pcp2sOVedrhrjqYnav5gFWUU7nN4dPJy95d84D6', 'Larry Denver', 'J', 'Biaco', '1999-03-13', 'male', '09165789087', 'zone1', '0976', 'uploads/ID_67d2bd105a53b.png', 'resident', 'active', '2025-03-13 11:10:08', '2025-03-15 12:19:04', 'uploads/profile_pics/profile_10_1742041144.jpg');

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
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`proof_id`),
  ADD KEY `request_id` (`request_id`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `proof_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `request_details`
--
ALTER TABLE `request_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- Constraints for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_proofs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
