-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2025 at 09:12 AM
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
-- Database: `coret2_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `compliance_status` varchar(50) DEFAULT 'Compliant',
  `review_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`audit_id`, `user_id`, `action_type`, `module_name`, `record_id`, `action_time`, `ip_address`, `remarks`, `compliance_status`, `review_date`) VALUES
(1, 1, 'Create', 'Members', NULL, '2025-10-22 07:05:27', NULL, 'Added new member John Doe', 'Compliant', '2025-10-20 10:00:00'),
(2, 1, 'Update', 'Loans', NULL, '2025-10-22 07:05:27', NULL, 'Reviewed loan application #102', 'Non-Compliant', '2025-10-21 09:30:00'),
(3, 1, 'Review', 'Compliance', NULL, '2025-10-22 07:05:27', NULL, 'Started compliance audit', 'Pending', '2025-10-22 08:45:00'),
(4, 1, 'Edit', 'Savings', NULL, '2025-10-22 07:05:27', NULL, 'Updated savings record for ID 5', 'Non-Compliant', '2025-10-22 11:15:00'),
(5, 1, 'Verify', 'Disbursement', NULL, '2025-10-22 07:05:27', NULL, 'Approved disbursement ₱15,000', 'Compliant', '2025-10-22 14:00:00'),
(6, 5, 'Logout', 'Authentication', NULL, '2025-10-22 07:05:51', '::1', 'User Fernando M. Gonzales Jr. logged out.', 'Compliant', '2025-10-22 15:05:51'),
(7, 5, 'Login Failed - Wrong Password', 'Authentication', NULL, '2025-10-22 07:06:02', '::1', 'Incorrect password', 'Compliant', '2025-10-22 15:06:02'),
(8, 5, 'Login', 'Authentication', NULL, '2025-10-22 07:06:10', '::1', 'User logged in successfully', 'Compliant', '2025-10-22 15:06:10');

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `collection_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `amount_collected` decimal(12,2) NOT NULL,
  `collector_id` int(11) DEFAULT NULL,
  `status` enum('Full','Partial','Missed') DEFAULT 'Full',
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_logs`
--

CREATE TABLE `compliance_logs` (
  `compliance_id` int(11) NOT NULL,
  `audit_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `compliance_status` enum('Compliant','Non-Compliant','Under Review') DEFAULT 'Under Review',
  `review_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compliance_logs`
--

INSERT INTO `compliance_logs` (`compliance_id`, `audit_id`, `description`, `compliance_status`, `review_date`) VALUES
(26, 1, 'Member KYC documents verified', 'Compliant', '2025-10-20'),
(27, 2, 'Loan contract missing signature', 'Non-Compliant', '2025-10-21'),
(28, 3, 'Pending background verification', '', '2025-10-22'),
(29, 4, 'Savings account update delayed', 'Non-Compliant', '2025-10-22'),
(30, 5, 'Audit trail records matched successfully', 'Compliant', '2025-10-22');

-- --------------------------------------------------------

--
-- Table structure for table `disbursements`
--

CREATE TABLE `disbursements` (
  `disbursement_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `fund_source` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Released','Cancelled') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disbursements`
--

INSERT INTO `disbursements` (`disbursement_id`, `loan_id`, `member_id`, `disbursement_date`, `amount`, `fund_source`, `approved_by`, `status`, `remarks`, `created_at`) VALUES
(1, 1, 1, '2025-10-20', 5000.00, 'Central Fund', 1, 'Released', 'Initial release', '2025-10-20 11:16:33'),
(2, 2, 2, '2025-10-20', 10000.00, 'External Partner', 5, 'Released', 'Awaiting docs', '2025-10-20 11:16:33'),
(3, 101, 1, '2025-10-01', 5000.00, 'Main Fund', NULL, 'Pending', 'First disbursement', '2025-10-21 18:13:26'),
(4, 102, 2, '2025-10-02', 7500.00, 'Special Fund', 3, 'Released', 'Approved by Manager', '2025-10-21 18:13:26'),
(5, 103, 3, '2025-10-05', 3000.00, 'Main Fund', NULL, 'Pending', 'Pending approval', '2025-10-21 18:13:26'),
(6, 104, 4, '2025-10-08', 12000.00, 'Emergency Fund', 2, 'Released', 'Loan for equipment', '2025-10-21 18:13:26'),
(7, 105, 5, '2025-10-10', 4500.00, 'Main Fund', 5, 'Released', 'Monthly disbursement', '2025-10-21 18:13:26');

-- --------------------------------------------------------

--
-- Table structure for table `disbursement_logs`
--

CREATE TABLE `disbursement_logs` (
  `log_id` int(11) NOT NULL,
  `disbursement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('Request','Approve','Cancel','Status Change') NOT NULL,
  `old_status` enum('Pending','Released','Cancelled') DEFAULT NULL,
  `new_status` enum('Pending','Released','Cancelled') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_portfolio`
--

CREATE TABLE `loan_portfolio` (
  `loan_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `loan_type` varchar(50) DEFAULT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `loan_term` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Pending','Approved','Active','Completed','Defaulted') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_portfolio`
--

INSERT INTO `loan_portfolio` (`loan_id`, `member_id`, `loan_type`, `principal_amount`, `interest_rate`, `loan_term`, `start_date`, `end_date`, `status`) VALUES
(1, 1, 'Personal Loan', 5000.00, 5.00, 12, '2025-01-01', '2025-12-31', 'Pending'),
(2, 2, 'Business Loan', 3000.00, 6.00, 12, '2025-01-01', '2025-12-31', 'Pending'),
(3, 3, 'Education Loan', 2000.00, 4.50, 12, '2025-01-01', '2025-12-31', 'Pending'),
(4, 4, 'Car Loan', 2500.00, 5.50, 12, '2025-01-01', '2025-12-31', 'Pending'),
(5, 5, 'Home Loan', 1600.00, 5.00, 12, '2025-01-01', '2025-12-31', 'Pending'),
(18, 2, 'Personal', 35000.00, 1.50, 12, '2025-10-15', '2026-10-15', 'Defaulted'),
(19, 2, 'Personal', 35000.00, 1.50, 12, '2025-10-15', '2026-10-15', 'Defaulted'),
(20, 1, 'Personal', 50000.00, 1.50, 12, '2025-10-15', '2026-10-15', 'Approved'),
(25, 1, 'Personal', 50000.00, 1.80, 12, '2025-10-15', '2026-10-15', 'Approved'),
(26, 1, 'Personal', 50000.00, 1.80, 12, '2025-10-15', '2026-10-15', 'Defaulted'),
(27, 1, 'Personal', 50000.00, 2.80, 12, '2025-10-15', '2026-10-15', 'Defaulted'),
(28, 3, 'Personal', 42000.00, 0.50, 36, '2025-10-18', '2028-10-18', 'Approved'),
(35, 3, 'Education Loan', 30000.00, 4.00, 6, '2025-03-01', '2025-09-01', 'Defaulted'),
(36, 5, 'Emergency Loan', 9000.00, 5.00, 9, '2025-10-22', '2026-07-22', 'Active'),
(37, 5, 'Emergency Loan', 9000.00, 5.00, 9, '2025-10-22', '2026-07-22', 'Active'),
(38, 5, 'Emergency Loan', 9000.00, 5.00, 9, '2025-01-15', '2025-10-15', 'Active'),
(39, 5, 'Emergency Loan', 9000.00, 5.00, 9, '2025-01-15', '2025-10-15', 'Active'),
(40, 5, 'Emergency Loan', 9000.00, 5.00, 9, '2025-01-15', '2025-10-15', 'Active'),
(41, 1, 'Personal Loan', 10000.00, 5.00, 12, '2025-01-01', '2026-01-01', 'Active'),
(42, 1, 'Personal Loan', 10000.00, 5.00, 12, '2025-01-01', '2026-01-01', 'Active'),
(43, 1, 'Personal Loan', 10000.00, 5.00, 12, '2025-01-01', '2026-01-01', 'Active'),
(49, 1, 'Personal Loan', 5000.00, 5.00, 12, '2025-01-01', '2025-12-31', 'Pending'),
(50, 2, 'Business Loan', 3000.00, 6.00, 12, '2025-01-01', '2025-12-31', 'Pending'),
(51, 3, 'Education Loan', 2000.00, 4.50, 12, '2025-01-01', '2025-12-31', 'Pending'),
(52, 4, 'Car Loan', 2500.00, 5.50, 12, '2025-01-01', '2025-12-31', 'Pending'),
(53, 5, 'Home Loan', 1600.00, 5.00, 12, '2025-01-01', '2025-12-31', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `loan_schedule`
--

CREATE TABLE `loan_schedule` (
  `schedule_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount_due` decimal(12,2) DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `status` enum('Pending','Paid','Overdue') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_schedule`
--

INSERT INTO `loan_schedule` (`schedule_id`, `loan_id`, `due_date`, `amount_due`, `amount_paid`, `payment_date`, `status`) VALUES
(91, 18, '2025-11-15', 2960.42, 0.00, NULL, 'Pending'),
(92, 18, '2025-12-15', 2960.42, 0.00, NULL, 'Pending'),
(93, 18, '2026-01-15', 2960.42, 0.00, NULL, 'Pending'),
(94, 18, '2026-02-15', 2960.42, 0.00, NULL, 'Pending'),
(95, 18, '2026-03-15', 2960.42, 0.00, NULL, 'Pending'),
(96, 18, '2026-04-15', 2960.42, 0.00, NULL, 'Pending'),
(97, 18, '2026-05-15', 2960.42, 0.00, NULL, 'Pending'),
(98, 18, '2026-06-15', 2960.42, 0.00, NULL, 'Pending'),
(99, 18, '2026-07-15', 2960.42, 0.00, NULL, 'Pending'),
(100, 18, '2026-08-15', 2960.42, 0.00, NULL, 'Pending'),
(101, 18, '2026-09-15', 2960.42, 0.00, NULL, 'Pending'),
(102, 18, '2026-10-15', 2960.42, 0.00, NULL, 'Pending'),
(103, 19, '2025-11-15', 2960.42, 0.00, NULL, 'Pending'),
(104, 19, '2025-12-15', 2960.42, 0.00, NULL, 'Pending'),
(105, 19, '2026-01-15', 2960.42, 0.00, NULL, 'Pending'),
(106, 19, '2026-02-15', 2960.42, 0.00, NULL, 'Pending'),
(107, 19, '2026-03-15', 2960.42, 0.00, NULL, 'Pending'),
(108, 19, '2026-04-15', 2960.42, 0.00, NULL, 'Pending'),
(109, 19, '2026-05-15', 2960.42, 0.00, NULL, 'Pending'),
(110, 19, '2026-06-15', 2960.42, 0.00, NULL, 'Pending'),
(111, 19, '2026-07-15', 2960.42, 0.00, NULL, 'Pending'),
(112, 19, '2026-08-15', 2960.42, 0.00, NULL, 'Pending'),
(113, 19, '2026-09-15', 2960.42, 0.00, NULL, 'Pending'),
(114, 19, '2026-10-15', 2960.42, 0.00, NULL, 'Pending'),
(115, 20, '2025-11-15', 4229.17, 0.00, NULL, 'Pending'),
(116, 20, '2025-12-15', 4229.17, 0.00, NULL, 'Pending'),
(117, 20, '2026-01-15', 4229.17, 0.00, NULL, 'Pending'),
(118, 20, '2026-02-15', 4229.17, 0.00, NULL, 'Pending'),
(119, 20, '2026-03-15', 4229.17, 0.00, NULL, 'Pending'),
(120, 20, '2026-04-15', 4229.17, 0.00, NULL, 'Pending'),
(121, 20, '2026-05-15', 4229.17, 0.00, NULL, 'Pending'),
(122, 20, '2026-06-15', 4229.17, 0.00, NULL, 'Pending'),
(123, 20, '2026-07-15', 4229.17, 0.00, NULL, 'Pending'),
(124, 20, '2026-08-15', 4229.17, 0.00, NULL, 'Pending'),
(125, 20, '2026-09-15', 4229.17, 0.00, NULL, 'Pending'),
(126, 20, '2026-10-15', 4229.17, 0.00, NULL, 'Pending'),
(148, 25, '2025-11-15', 4241.67, 0.00, NULL, 'Pending'),
(149, 25, '2025-12-15', 4241.67, 0.00, NULL, 'Pending'),
(150, 25, '2026-01-15', 4241.67, 0.00, NULL, 'Pending'),
(151, 25, '2026-02-15', 4241.67, 0.00, NULL, 'Pending'),
(152, 25, '2026-03-15', 4241.67, 0.00, NULL, 'Pending'),
(153, 25, '2026-04-15', 4241.67, 0.00, NULL, 'Pending'),
(154, 25, '2026-05-15', 4241.67, 0.00, NULL, 'Pending'),
(155, 25, '2026-06-15', 4241.67, 0.00, NULL, 'Pending'),
(156, 25, '2026-07-15', 4241.67, 0.00, NULL, 'Pending'),
(157, 25, '2026-08-15', 4241.67, 0.00, NULL, 'Pending'),
(158, 25, '2026-09-15', 4241.67, 0.00, NULL, 'Pending'),
(159, 25, '2026-10-15', 4241.67, 0.00, NULL, 'Pending'),
(160, 26, '2025-11-15', 4241.67, 0.00, NULL, 'Pending'),
(161, 26, '2025-12-15', 4241.67, 0.00, NULL, 'Pending'),
(162, 26, '2026-01-15', 4241.67, 0.00, NULL, 'Pending'),
(163, 26, '2026-02-15', 4241.67, 0.00, NULL, 'Pending'),
(164, 26, '2026-03-15', 4241.67, 0.00, NULL, 'Pending'),
(165, 26, '2026-04-15', 4241.67, 0.00, NULL, 'Pending'),
(166, 26, '2026-05-15', 4241.67, 0.00, NULL, 'Pending'),
(167, 26, '2026-06-15', 4241.67, 0.00, NULL, 'Pending'),
(168, 26, '2026-07-15', 4241.67, 0.00, NULL, 'Pending'),
(169, 26, '2026-08-15', 4241.67, 0.00, NULL, 'Pending'),
(170, 26, '2026-09-15', 4241.67, 0.00, NULL, 'Pending'),
(171, 26, '2026-10-15', 4241.67, 0.00, NULL, 'Pending'),
(172, 27, '2025-11-15', 4283.33, 0.00, NULL, 'Pending'),
(173, 27, '2025-12-15', 4283.33, 0.00, NULL, 'Pending'),
(174, 27, '2026-01-15', 4283.33, 0.00, NULL, 'Pending'),
(175, 27, '2026-02-15', 4283.33, 0.00, NULL, 'Pending'),
(176, 27, '2026-03-15', 4283.33, 0.00, NULL, 'Pending'),
(177, 27, '2026-04-15', 4283.33, 0.00, NULL, 'Pending'),
(178, 27, '2026-05-15', 4283.33, 0.00, NULL, 'Pending'),
(179, 27, '2026-06-15', 4283.33, 0.00, NULL, 'Pending'),
(180, 27, '2026-07-15', 4283.33, 0.00, NULL, 'Pending'),
(181, 27, '2026-08-15', 4283.33, 0.00, NULL, 'Pending'),
(182, 27, '2026-09-15', 4283.33, 0.00, NULL, 'Pending'),
(183, 27, '2026-10-15', 4283.33, 0.00, NULL, 'Pending'),
(184, 28, '2025-11-18', 1172.50, 0.00, NULL, 'Pending'),
(185, 28, '2025-12-18', 1172.50, 0.00, NULL, 'Pending'),
(186, 28, '2026-01-18', 1172.50, 0.00, NULL, 'Pending'),
(187, 28, '2026-02-18', 1172.50, 0.00, NULL, 'Pending'),
(188, 28, '2026-03-18', 1172.50, 0.00, NULL, 'Pending'),
(189, 28, '2026-04-18', 1172.50, 0.00, NULL, 'Pending'),
(190, 28, '2026-05-18', 1172.50, 0.00, NULL, 'Pending'),
(191, 28, '2026-06-18', 1172.50, 0.00, NULL, 'Pending'),
(192, 28, '2026-07-18', 1172.50, 0.00, NULL, 'Pending'),
(193, 28, '2026-08-18', 1172.50, 0.00, NULL, 'Pending'),
(194, 28, '2026-09-18', 1172.50, 0.00, NULL, 'Pending'),
(195, 28, '2026-10-18', 1172.50, 0.00, NULL, 'Pending'),
(196, 28, '2026-11-18', 1172.50, 0.00, NULL, 'Pending'),
(197, 28, '2026-12-18', 1172.50, 0.00, NULL, 'Pending'),
(198, 28, '2027-01-18', 1172.50, 0.00, NULL, 'Pending'),
(199, 28, '2027-02-18', 1172.50, 0.00, NULL, 'Pending'),
(200, 28, '2027-03-18', 1172.50, 0.00, NULL, 'Pending'),
(201, 28, '2027-04-18', 1172.50, 0.00, NULL, 'Pending'),
(202, 28, '2027-05-18', 1172.50, 0.00, NULL, 'Pending'),
(203, 28, '2027-06-18', 1172.50, 0.00, NULL, 'Pending'),
(204, 28, '2027-07-18', 1172.50, 0.00, NULL, 'Pending'),
(205, 28, '2027-08-18', 1172.50, 0.00, NULL, 'Pending'),
(206, 28, '2027-09-18', 1172.50, 0.00, NULL, 'Pending'),
(207, 28, '2027-10-18', 1172.50, 0.00, NULL, 'Pending'),
(208, 28, '2027-11-18', 1172.50, 0.00, NULL, 'Pending'),
(209, 28, '2027-12-18', 1172.50, 0.00, NULL, 'Pending'),
(210, 28, '2028-01-18', 1172.50, 0.00, NULL, 'Pending'),
(211, 28, '2028-02-18', 1172.50, 0.00, NULL, 'Pending'),
(212, 28, '2028-03-18', 1172.50, 0.00, NULL, 'Pending'),
(213, 28, '2028-04-18', 1172.50, 0.00, NULL, 'Pending'),
(214, 28, '2028-05-18', 1172.50, 0.00, NULL, 'Pending'),
(215, 28, '2028-06-18', 1172.50, 0.00, NULL, 'Pending'),
(216, 28, '2028-07-18', 1172.50, 0.00, NULL, 'Pending'),
(217, 28, '2028-08-18', 1172.50, 0.00, NULL, 'Pending'),
(218, 28, '2028-09-18', 1172.50, 0.00, NULL, 'Pending'),
(219, 28, '2028-10-18', 1172.50, 0.00, NULL, 'Pending'),
(232, 35, '2025-04-01', 5200.00, 0.00, NULL, 'Pending'),
(233, 35, '2025-05-01', 5200.00, 0.00, NULL, 'Pending'),
(234, 35, '2025-06-01', 5200.00, 0.00, NULL, 'Pending'),
(235, 35, '2025-07-01', 5200.00, 0.00, NULL, 'Pending'),
(236, 35, '2025-08-01', 5200.00, 0.00, NULL, 'Pending'),
(237, 35, '2025-09-01', 5200.00, 0.00, NULL, 'Pending'),
(238, 36, '2025-11-22', 1050.00, 0.00, NULL, 'Pending'),
(239, 36, '2025-12-22', 1050.00, 0.00, NULL, 'Pending'),
(240, 36, '2026-01-22', 1050.00, 0.00, NULL, 'Pending'),
(241, 36, '2026-02-22', 1050.00, 0.00, NULL, 'Pending'),
(242, 36, '2026-03-22', 1050.00, 0.00, NULL, 'Pending'),
(243, 36, '2026-04-22', 1050.00, 0.00, NULL, 'Pending'),
(244, 36, '2026-05-22', 1050.00, 0.00, NULL, 'Pending'),
(245, 36, '2026-06-22', 1050.00, 0.00, NULL, 'Pending'),
(246, 36, '2026-07-22', 1050.00, 0.00, NULL, 'Pending'),
(247, 37, '2025-11-22', 1050.00, 0.00, NULL, 'Pending'),
(248, 37, '2025-12-22', 1050.00, 0.00, NULL, 'Pending'),
(249, 37, '2026-01-22', 1050.00, 0.00, NULL, 'Pending'),
(250, 37, '2026-02-22', 1050.00, 0.00, NULL, 'Pending'),
(251, 37, '2026-03-22', 1050.00, 0.00, NULL, 'Pending'),
(252, 37, '2026-04-22', 1050.00, 0.00, NULL, 'Pending'),
(253, 37, '2026-05-22', 1050.00, 0.00, NULL, 'Pending'),
(254, 37, '2026-06-22', 1050.00, 0.00, NULL, 'Pending'),
(255, 37, '2026-07-22', 1050.00, 0.00, NULL, 'Pending'),
(256, 38, '2025-02-15', 1050.00, 0.00, NULL, 'Pending'),
(257, 38, '2025-03-15', 1050.00, 0.00, NULL, 'Pending'),
(258, 38, '2025-04-15', 1050.00, 0.00, NULL, 'Pending'),
(259, 38, '2025-05-15', 1050.00, 0.00, NULL, 'Pending'),
(260, 38, '2025-06-15', 1050.00, 0.00, NULL, 'Pending'),
(261, 38, '2025-07-15', 1050.00, 0.00, NULL, 'Pending'),
(262, 38, '2025-08-15', 1050.00, 0.00, NULL, 'Pending'),
(263, 38, '2025-09-15', 1050.00, 0.00, NULL, 'Pending'),
(264, 38, '2025-10-15', 1050.00, 0.00, NULL, 'Pending'),
(265, 39, '2025-02-15', 1050.00, 0.00, NULL, 'Pending'),
(266, 39, '2025-03-15', 1050.00, 0.00, NULL, 'Pending'),
(267, 39, '2025-04-15', 1050.00, 0.00, NULL, 'Pending'),
(268, 39, '2025-05-15', 1050.00, 0.00, NULL, 'Pending'),
(269, 39, '2025-06-15', 1050.00, 0.00, NULL, 'Pending'),
(270, 39, '2025-07-15', 1050.00, 0.00, NULL, 'Pending'),
(271, 39, '2025-08-15', 1050.00, 0.00, NULL, 'Pending'),
(272, 39, '2025-09-15', 1050.00, 0.00, NULL, 'Pending'),
(273, 39, '2025-10-15', 1050.00, 0.00, NULL, 'Pending'),
(274, 40, '2025-02-15', 1050.00, 0.00, NULL, 'Pending'),
(275, 40, '2025-03-15', 1050.00, 0.00, NULL, 'Pending'),
(276, 40, '2025-04-15', 1050.00, 0.00, NULL, 'Pending'),
(277, 40, '2025-05-15', 1050.00, 0.00, NULL, 'Pending'),
(278, 40, '2025-06-15', 1050.00, 0.00, NULL, 'Pending'),
(279, 40, '2025-07-15', 1050.00, 0.00, NULL, 'Pending'),
(280, 40, '2025-08-15', 1050.00, 0.00, NULL, 'Pending'),
(281, 40, '2025-09-15', 1050.00, 0.00, NULL, 'Pending'),
(282, 40, '2025-10-15', 1050.00, 0.00, NULL, 'Pending'),
(283, 41, '2025-02-01', 875.00, 0.00, NULL, 'Pending'),
(284, 41, '2025-03-01', 875.00, 0.00, NULL, 'Pending'),
(285, 41, '2025-04-01', 875.00, 0.00, NULL, 'Pending'),
(286, 41, '2025-05-01', 875.00, 0.00, NULL, 'Pending'),
(287, 41, '2025-06-01', 875.00, 0.00, NULL, 'Pending'),
(288, 41, '2025-07-01', 875.00, 0.00, NULL, 'Pending'),
(289, 41, '2025-08-01', 875.00, 0.00, NULL, 'Pending'),
(290, 41, '2025-09-01', 875.00, 0.00, NULL, 'Pending'),
(291, 41, '2025-10-01', 875.00, 0.00, NULL, 'Pending'),
(292, 41, '2025-11-01', 875.00, 0.00, NULL, 'Pending'),
(293, 41, '2025-12-01', 875.00, 0.00, NULL, 'Pending'),
(294, 41, '2026-01-01', 875.00, 0.00, NULL, 'Pending'),
(295, 42, '2025-02-01', 875.00, 0.00, NULL, 'Pending'),
(296, 42, '2025-03-01', 875.00, 0.00, NULL, 'Pending'),
(297, 42, '2025-04-01', 875.00, 0.00, NULL, 'Pending'),
(298, 42, '2025-05-01', 875.00, 0.00, NULL, 'Pending'),
(299, 42, '2025-06-01', 875.00, 0.00, NULL, 'Pending'),
(300, 42, '2025-07-01', 875.00, 0.00, NULL, 'Pending'),
(301, 42, '2025-08-01', 875.00, 0.00, NULL, 'Pending'),
(302, 42, '2025-09-01', 875.00, 0.00, NULL, 'Pending'),
(303, 42, '2025-10-01', 875.00, 0.00, NULL, 'Pending'),
(304, 42, '2025-11-01', 875.00, 0.00, NULL, 'Pending'),
(305, 42, '2025-12-01', 875.00, 0.00, NULL, 'Pending'),
(306, 42, '2026-01-01', 875.00, 0.00, NULL, 'Pending'),
(307, 43, '2025-02-01', 875.00, 0.00, NULL, 'Pending'),
(308, 43, '2025-03-01', 875.00, 0.00, NULL, 'Pending'),
(309, 43, '2025-04-01', 875.00, 0.00, NULL, 'Pending'),
(310, 43, '2025-05-01', 875.00, 0.00, NULL, 'Pending'),
(311, 43, '2025-06-01', 875.00, 0.00, NULL, 'Pending'),
(312, 43, '2025-07-01', 875.00, 0.00, NULL, 'Pending'),
(313, 43, '2025-08-01', 875.00, 0.00, NULL, 'Pending'),
(314, 43, '2025-09-01', 875.00, 0.00, NULL, 'Pending'),
(315, 43, '2025-10-01', 875.00, 0.00, NULL, 'Pending'),
(316, 43, '2025-11-01', 875.00, 0.00, NULL, 'Pending'),
(317, 43, '2025-12-01', 875.00, 0.00, NULL, 'Pending'),
(318, 43, '2026-01-01', 875.00, 0.00, NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `member_code` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `membership_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `user_id`, `member_code`, `full_name`, `gender`, `birth_date`, `contact_no`, `address`, `membership_date`, `status`) VALUES
(1, 1, 'MBR001', 'Juan Dela Cruz', 'Male', '1990-01-01', '09123456789', 'Manila', '2025-01-10', 'Active'),
(2, 1, 'MBR002', 'Maria Santos', 'Female', '1988-02-15', '09223334444', 'Quezon City', '2025-02-12', 'Active'),
(3, 1, 'MBR003', 'Jose Ramos', 'Male', '1995-03-20', '09334445555', 'Cebu City', '2025-03-05', 'Active'),
(4, 1, 'MBR004', 'Ana Villanueva', 'Female', '1995-03-09', '09191234567', 'Taguig City', '2022-04-25', 'Active'),
(5, 1, 'MBR005', 'Rico Fernandez', 'Male', '1991-08-14', '09201234567', 'Cavite City', '2022-05-11', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `permission_logs`
--

CREATE TABLE `permission_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `action_name` varchar(50) NOT NULL,
  `action_status` enum('Success','Failed') DEFAULT 'Success',
  `action_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission_logs`
--

INSERT INTO `permission_logs` (`log_id`, `user_id`, `module_name`, `action_name`, `action_status`, `action_time`) VALUES
(1, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:01:57'),
(2, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:02:01'),
(3, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:03:57'),
(4, 5, 'compliance_audit', 'Access', 'Success', '2025-10-19 05:03:59'),
(5, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:04:01'),
(6, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:04:16'),
(7, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:04:37'),
(8, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:04:46'),
(9, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:11:18'),
(10, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:11:20'),
(11, 5, 'compliance_audit', 'Access', 'Success', '2025-10-19 05:11:22'),
(12, 5, 'compliance_audit', 'Access', 'Success', '2025-10-19 05:14:11'),
(13, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:14:13'),
(14, 5, 'compliance_audit', 'Access', 'Success', '2025-10-19 05:14:18'),
(15, 5, 'compliance_audit', 'Access', 'Success', '2025-10-19 05:27:56'),
(16, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 05:27:58'),
(17, 6, 'permission_logs', 'Access', '', '2025-10-19 05:30:15'),
(18, 6, 'permission_logs', 'Access', '', '2025-10-19 05:30:19'),
(19, 6, 'permission_logs', 'Access', '', '2025-10-19 05:30:24'),
(20, 6, 'permission_logs', 'Access', '', '2025-10-19 05:34:56'),
(21, 6, 'permission_logs', 'Access', '', '2025-10-19 05:35:00'),
(22, 6, 'compliance_audit', 'Access', '', '2025-10-19 05:35:07'),
(23, 6, 'permission_logs', 'Access', '', '2025-10-19 05:35:11'),
(24, 6, 'permission_logs', 'Access', '', '2025-10-19 05:40:52'),
(25, 6, 'permission_logs', 'Access', '', '2025-10-19 05:40:57'),
(26, 6, 'compliance_audit', 'Access', '', '2025-10-19 05:41:06'),
(27, 6, 'permission_logs', 'Access', '', '2025-10-19 05:41:10'),
(28, 6, 'permission_logs', 'Access', '', '2025-10-19 05:41:14'),
(29, 6, 'permission_logs', 'Access', '', '2025-10-19 05:44:50'),
(30, 6, 'permission_logs', 'Access', '', '2025-10-19 05:44:54'),
(31, 6, 'permission_logs', 'Access', '', '2025-10-19 05:45:30'),
(32, 6, 'compliance_audit', 'Access', '', '2025-10-19 05:45:35'),
(33, 6, 'permission_logs', 'Access', '', '2025-10-19 05:46:32'),
(34, 6, 'permission_logs', 'Access', '', '2025-10-19 06:00:07'),
(35, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:00:33'),
(36, 6, 'permission_logs', 'Access', '', '2025-10-19 06:01:40'),
(37, 6, 'permission_logs', 'Access', '', '2025-10-19 06:05:03'),
(38, 6, 'permission_logs', 'Access', '', '2025-10-19 06:05:06'),
(39, 6, 'permission_logs', 'Access', '', '2025-10-19 06:12:51'),
(40, 6, 'permission_logs', 'Access', '', '2025-10-19 06:13:01'),
(41, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:15:08'),
(42, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:15:13'),
(43, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:15:18'),
(44, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:15:24'),
(45, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:23:46'),
(46, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:23:50'),
(47, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:28:57'),
(48, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:29:00'),
(49, 6, 'permission_logs', 'Access', '', '2025-10-19 06:29:04'),
(50, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:29:11'),
(51, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:34:14'),
(52, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:34:15'),
(53, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:34:16'),
(54, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:34:16'),
(55, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:34:16'),
(56, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:35:50'),
(57, 6, 'compliance_audit', 'Access', '', '2025-10-19 06:35:54'),
(58, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:44'),
(59, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(60, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(61, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(62, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(63, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(64, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:45'),
(65, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:38:49'),
(66, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:40:12'),
(67, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:40:18'),
(68, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:41:36'),
(69, 6, 'compliance_logs', 'Access', '', '2025-10-19 06:46:56'),
(70, 6, 'permission_logs', 'Access', '', '2025-10-19 06:46:59'),
(71, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 06:47:25'),
(72, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 06:47:33'),
(73, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:52:07'),
(74, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:52:10'),
(75, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:52:12'),
(76, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 06:52:13'),
(77, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:52:14'),
(78, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:53:38'),
(79, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:53:41'),
(80, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 06:53:42'),
(81, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:53:43'),
(82, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 06:53:48'),
(83, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 06:55:27'),
(84, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:55:33'),
(85, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 06:55:34'),
(86, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 06:55:35'),
(87, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 07:04:34'),
(88, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 07:04:37'),
(89, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 07:04:38'),
(90, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 10:19:13'),
(91, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:19:16'),
(92, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 10:19:17'),
(93, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 10:21:10'),
(94, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 10:21:21'),
(95, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:21:22'),
(96, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:25:25'),
(97, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:25:28'),
(98, 5, 'role_permissions', 'Access', 'Success', '2025-10-19 10:25:29'),
(99, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 10:25:31'),
(100, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:25:32'),
(101, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:26:11'),
(102, 5, 'compliance_logs', 'Access', 'Success', '2025-10-19 10:26:24'),
(103, 5, 'permission_logs', 'Access', 'Success', '2025-10-19 10:26:35'),
(104, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:26:42'),
(105, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:26:58'),
(106, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:44:57'),
(107, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:48:00'),
(108, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-19 10:48:00'),
(109, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 08:21:32'),
(110, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 08:21:34'),
(111, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:21:59'),
(112, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:28'),
(113, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:28'),
(114, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:32'),
(115, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:33'),
(116, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:35'),
(117, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:36'),
(118, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:38'),
(119, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:39'),
(120, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:48'),
(121, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:53'),
(122, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:25:55'),
(123, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:26:04'),
(124, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:30:15'),
(125, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:30:17'),
(126, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:30:56'),
(127, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:30:57'),
(128, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:30:58'),
(129, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:31:01'),
(130, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:51:08'),
(131, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:51:11'),
(132, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:54:39'),
(133, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 09:54:40'),
(134, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:00:09'),
(135, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:00:13'),
(136, 5, 'permission_logs', 'Access', 'Success', '2025-10-20 10:00:45'),
(137, 5, 'role_permissions', 'Access', 'Success', '2025-10-20 10:00:45'),
(138, 5, 'permission_logs', 'Access', 'Success', '2025-10-20 10:00:46'),
(139, 5, 'role_permissions', 'Access', 'Success', '2025-10-20 10:00:47'),
(140, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 10:00:48'),
(141, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:00:50'),
(142, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:00:51'),
(143, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:02:36'),
(144, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:02:47'),
(145, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:03:11'),
(146, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:03:34'),
(147, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:03:34'),
(148, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:06:53'),
(149, 5, 'Savings Monitoring', 'Delete', 'Success', '2025-10-20 10:07:43'),
(150, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:20:55'),
(151, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:25:47'),
(152, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:25:49'),
(153, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:26:09'),
(154, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:29:50'),
(155, 5, 'permission_logs', 'Access', 'Success', '2025-10-20 10:30:00'),
(156, 5, 'role_permissions', 'Access', 'Success', '2025-10-20 10:30:00'),
(157, 5, 'role_permissions', 'Access', 'Success', '2025-10-20 10:30:02'),
(158, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:32:20'),
(159, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:32:59'),
(160, 5, 'Savings Monitoring', 'Add', 'Success', '2025-10-20 10:33:13'),
(161, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:33:42'),
(162, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:50:13'),
(163, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:52:00'),
(164, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:52:01'),
(165, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 10:52:02'),
(166, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 10:52:04'),
(167, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 10:52:05'),
(168, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 10:52:40'),
(169, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 10:54:27'),
(170, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 10:54:47'),
(171, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:01:13'),
(172, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:16:41'),
(173, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:17:10'),
(174, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:17:14'),
(175, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:24:22'),
(176, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:24:41'),
(177, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 11:24:54'),
(178, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:18:07'),
(179, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:18:10'),
(180, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:18:18'),
(181, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:18:23'),
(182, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:24:03'),
(183, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:24:39'),
(184, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:25:10'),
(185, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:25:16'),
(186, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:25:17'),
(187, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:25:27'),
(188, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:26:07'),
(189, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:27:39'),
(190, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:28:00'),
(191, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:38:38'),
(192, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:38:57'),
(193, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:39:03'),
(194, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:39:07'),
(195, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:39:12'),
(196, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:39:16'),
(197, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:39:19'),
(198, 5, 'Savings Monitoring', 'Delete', 'Success', '2025-10-20 12:39:32'),
(199, 6, 'permission_logs', 'Access', '', '2025-10-20 12:40:07'),
(200, 6, 'compliance_logs', 'Access', '', '2025-10-20 12:40:12'),
(201, 6, 'disbursement_tracker', 'Access', '', '2025-10-20 12:40:14'),
(202, 6, 'savings_monitoring', 'Access', '', '2025-10-20 12:40:16'),
(203, 1, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:40:33'),
(204, 1, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:40:33'),
(205, 1, 'compliance_logs', 'Access', 'Success', '2025-10-20 12:40:36'),
(206, 1, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:40:37'),
(207, 1, 'role_permissions', 'Access', 'Success', '2025-10-20 12:40:41'),
(208, 1, 'permission_logs', 'Access', 'Success', '2025-10-20 12:40:55'),
(209, 1, 'compliance_logs', 'Access', 'Success', '2025-10-20 12:40:57'),
(210, 1, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:41:08'),
(211, 1, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:41:12'),
(212, 1, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 12:41:15'),
(213, 1, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:41:41'),
(214, 1, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:41:44'),
(215, 1, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:41:45'),
(216, 1, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:41:45'),
(217, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 12:42:30'),
(218, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:42:31'),
(219, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:42:32'),
(220, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:42:34'),
(221, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:43:00'),
(222, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:43:03'),
(223, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:43:35'),
(224, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:43:54'),
(225, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:44:20'),
(226, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:44:50'),
(227, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:46:17'),
(228, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 12:46:18'),
(229, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:46:19'),
(230, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 12:46:21'),
(231, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:46:24'),
(232, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 12:46:25'),
(233, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:46:28'),
(234, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:54:51'),
(235, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:55:04'),
(236, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 12:55:34'),
(237, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:02:20'),
(238, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:03:35'),
(239, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:06:44'),
(240, 5, 'permission_logs', 'Access', 'Success', '2025-10-20 13:33:19'),
(241, 5, 'role_permissions', 'Access', 'Success', '2025-10-20 13:33:21'),
(242, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 13:33:21'),
(243, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 13:33:22'),
(244, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:47:15'),
(245, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 13:47:28'),
(246, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:47:29'),
(247, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:52:19'),
(248, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:52:23'),
(249, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:52:26'),
(250, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:52:29'),
(251, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 13:59:09'),
(252, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 14:04:20'),
(253, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 14:04:22'),
(254, 5, 'compliance_logs', 'Access', 'Success', '2025-10-20 14:04:24'),
(255, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 14:04:24'),
(256, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 14:06:17'),
(257, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 14:06:28'),
(258, 5, 'disbursement_tracker', 'Access', 'Success', '2025-10-20 14:06:29'),
(259, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:04:45'),
(260, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:04:54'),
(261, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:05:03'),
(262, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-20 15:05:44'),
(263, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-20 15:05:56'),
(264, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:09:50'),
(265, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:16:46'),
(266, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-20 15:16:54'),
(267, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:20:39'),
(268, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 07:20:44'),
(269, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 07:20:46'),
(270, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:22:26'),
(271, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:22:52'),
(272, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 07:23:36'),
(273, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:23:52'),
(274, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 07:24:26'),
(275, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 07:24:42'),
(276, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 07:24:58'),
(277, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 07:25:26'),
(278, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 07:25:51'),
(279, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 07:25:57'),
(280, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 07:26:04'),
(281, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 07:26:40'),
(282, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 07:27:32'),
(283, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 07:27:44'),
(284, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:27:48'),
(285, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 07:27:49'),
(286, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 07:27:51'),
(287, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:27:52'),
(288, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 07:27:55'),
(289, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:27:59'),
(290, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:28:16'),
(291, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:28:47'),
(292, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:29:06'),
(293, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:29:57'),
(294, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 07:30:30'),
(295, 6, 'Repayment_Tracker', 'Access', '', '2025-10-21 07:31:15'),
(296, 6, 'savings_monitoring', 'Access', '', '2025-10-21 07:31:20'),
(297, 6, 'Disbursement Tracker', 'Access', '', '2025-10-21 07:31:24'),
(298, 6, 'permission_logs', 'Access', '', '2025-10-21 07:31:36'),
(299, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:12:43'),
(300, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 09:12:52'),
(301, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 09:12:54'),
(302, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:12:58'),
(303, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 09:14:17'),
(304, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:15:08'),
(305, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 09:15:36'),
(306, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:15:37'),
(307, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:16:44'),
(308, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 09:17:00'),
(309, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 09:17:04'),
(310, 6, 'Repayment_Tracker', 'Access', '', '2025-10-21 09:17:58'),
(311, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:04:12'),
(312, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 15:08:07'),
(313, 5, 'Savings Monitoring', 'Edit', 'Success', '2025-10-21 15:08:11'),
(314, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 15:12:55'),
(315, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-21 15:13:16'),
(316, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-21 15:13:20'),
(317, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:14:08'),
(318, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:21:32'),
(319, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 15:23:55'),
(320, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 15:23:56'),
(321, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 15:23:57'),
(322, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 15:24:23'),
(323, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:28:27'),
(324, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 15:31:25'),
(325, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:34:08'),
(326, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:41:19'),
(327, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:43:51'),
(328, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:47:30'),
(329, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 15:49:32'),
(330, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 15:57:45'),
(331, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:04:05'),
(332, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:04:38'),
(333, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:04:40'),
(334, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:05:35'),
(335, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:05:42'),
(336, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:08:05'),
(337, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:09:17'),
(338, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:10:13'),
(339, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:10:28'),
(340, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:11:26'),
(341, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:12:59'),
(342, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:13:09'),
(343, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:14:58'),
(344, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:17:45'),
(345, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:19:27'),
(346, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:22:59'),
(347, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:25:46'),
(348, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:27:38'),
(349, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:27:56'),
(350, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:29:05'),
(351, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:29:06'),
(352, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:30:10'),
(353, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:31:03'),
(354, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:31:18'),
(355, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:33:44'),
(356, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:34:13'),
(357, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:34:30'),
(358, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:37:11'),
(359, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 16:38:03'),
(360, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:38:20'),
(361, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:44:42'),
(362, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-21 16:44:59'),
(363, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:45:54'),
(364, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:46:03'),
(365, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 16:52:14'),
(366, 5, 'Savings Monitoring', 'Add', 'Success', '2025-10-21 16:52:34'),
(367, 5, 'Disbursement Tracker', 'Edit', 'Success', '2025-10-21 16:58:14'),
(368, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:03:47'),
(369, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:03:48'),
(370, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:06:33'),
(371, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:07:48'),
(372, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:13:19'),
(373, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:13:36'),
(374, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 17:13:37'),
(375, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:14:09'),
(376, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 17:14:10'),
(377, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:14:11'),
(378, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:14:26'),
(379, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 17:14:31'),
(380, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 17:14:32'),
(381, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 17:15:25'),
(382, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 17:15:49'),
(383, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 17:16:01'),
(384, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:16:07'),
(385, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:17:52'),
(386, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:18:41'),
(387, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:18:52'),
(388, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:20:39'),
(389, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:21:42'),
(390, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 17:21:42'),
(391, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 17:21:43'),
(392, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 17:26:25'),
(393, 5, 'role_permissions', 'Access', 'Success', '2025-10-21 17:26:25'),
(394, 5, 'permission_logs', 'Access', 'Success', '2025-10-21 17:26:26'),
(395, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:36'),
(396, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:39'),
(397, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:54'),
(398, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:55'),
(399, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:56'),
(400, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:57'),
(401, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:26:59'),
(402, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:27:33'),
(403, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:27:38'),
(404, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 17:27:39'),
(405, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 17:27:40'),
(406, 5, 'Disbursement Tracker', 'Approve', 'Success', '2025-10-21 18:10:53'),
(407, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 18:11:01'),
(408, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:19:47'),
(409, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 22:19:48'),
(410, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 22:19:49'),
(411, 5, 'compliance_logs', 'Access', 'Success', '2025-10-21 22:20:01'),
(412, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 22:20:03'),
(413, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:20:05'),
(414, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:28:22'),
(415, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:35:59'),
(416, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:41:55'),
(417, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 22:49:49'),
(418, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 22:52:13'),
(419, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-21 23:00:44'),
(420, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:00:51'),
(421, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:01:31'),
(422, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:05:13'),
(423, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:05:15'),
(424, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:05:28'),
(425, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:07:45'),
(426, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:08:17'),
(427, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:10:29'),
(428, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:10:38'),
(429, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:10:45'),
(430, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:13:05'),
(431, 5, 'Savings Monitoring', 'Add', 'Success', '2025-10-21 23:15:17'),
(432, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:24:20'),
(433, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:24:37'),
(434, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:28:12'),
(435, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:28:13'),
(436, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:28:30'),
(437, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:31:39'),
(438, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-21 23:33:04'),
(439, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:26:35'),
(440, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 03:27:03'),
(441, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:27:05'),
(442, 5, 'Disbursement Tracker', 'Approve', 'Success', '2025-10-22 03:27:17'),
(443, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:27:45'),
(444, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:27:52'),
(445, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:29:04'),
(446, 5, 'Savings Monitoring', 'Add', 'Success', '2025-10-22 03:29:23'),
(447, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:31:19'),
(448, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:33:07'),
(449, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:33:43'),
(450, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:34:28'),
(451, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:34:46'),
(452, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:35:19'),
(453, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:38:27'),
(454, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:39:23'),
(455, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:39:32'),
(456, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:39:59'),
(457, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:40:53'),
(458, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 03:40:56'),
(459, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:42:28'),
(460, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:43:52'),
(461, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:44:05'),
(462, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:46:43'),
(463, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:48:46'),
(464, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:52:22'),
(465, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:53:18'),
(466, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:56:27'),
(467, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 03:58:43'),
(468, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:03:26'),
(469, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:04:14'),
(470, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:07:05'),
(471, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:39:58'),
(472, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 04:40:39'),
(473, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:41:42'),
(474, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:44:35'),
(475, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:46:14'),
(476, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:46:41'),
(477, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 04:47:15'),
(478, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 04:47:17'),
(479, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:48:07'),
(480, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 04:49:26'),
(481, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 04:56:06'),
(482, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 04:56:08'),
(483, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 04:56:09'),
(484, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 04:56:46'),
(485, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 05:04:56'),
(486, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 05:05:03'),
(487, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 05:05:06'),
(488, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 05:05:08'),
(489, 5, 'role_permissions', 'Access', 'Success', '2025-10-22 05:12:14'),
(490, 5, 'permission_logs', 'Access', 'Success', '2025-10-22 05:12:15'),
(491, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 05:36:35'),
(492, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 05:36:36'),
(493, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 05:36:37'),
(494, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 06:09:28'),
(495, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 06:09:29'),
(496, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 06:09:48'),
(497, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 06:09:50'),
(498, 5, 'Repayment_Tracker', 'Access', 'Success', '2025-10-22 07:10:33'),
(499, 5, 'savings_monitoring', 'Access', 'Success', '2025-10-22 07:10:45'),
(500, 5, 'compliance_logs', 'Access', 'Success', '2025-10-22 07:10:46');

-- --------------------------------------------------------

--
-- Table structure for table `repayments`
--

CREATE TABLE `repayments` (
  `repayment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `repayment_date` date NOT NULL,
  `method` varchar(50) DEFAULT NULL,
  `remarks` varchar(200) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `overdue_count` int(11) DEFAULT 0,
  `risk_level` varchar(20) DEFAULT NULL,
  `next_due` date DEFAULT NULL,
  `created_at` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repayments`
--

INSERT INTO `repayments` (`repayment_id`, `loan_id`, `amount`, `repayment_date`, `method`, `remarks`, `created_by`, `created_by_name`, `overdue_count`, `risk_level`, `next_due`, `created_at`) VALUES
(76, 2, 3200.00, '2025-01-10', 'Bank Transfer', 'On-time', NULL, 'Cashier', 0, 'Low', '2025-02-10', '2025-01-10'),
(77, 4, 2500.00, '2025-01-15', 'Check', 'Late start', NULL, 'Manager', 1, 'Medium', '2025-02-15', '2025-01-15'),
(78, 1, 4600.00, '2025-02-05', 'GCash', 'Monthly payment', NULL, 'Admin', 0, 'Low', '2025-03-05', '2025-02-05'),
(79, 2, 3000.00, '2025-02-20', 'Bank Transfer', 'Delayed by 10 days', NULL, 'Cashier', 2, 'Medium', '2025-03-20', '2025-02-20'),
(80, 5, 1600.00, '2025-02-28', 'Cash', 'Advance payment', NULL, 'Admin', 0, 'Low', '2025-03-28', '2025-02-28'),
(81, 3, 2000.00, '2025-03-01', 'Check', 'Late Payment', NULL, 'Manager', 2, 'High', '2025-04-01', '2025-03-01'),
(82, 4, 2300.00, '2025-03-12', 'Bank Transfer', 'Missed due date', NULL, 'Manager', 3, 'High', '2025-04-12', '2025-03-12'),
(83, 5, 1750.00, '2025-03-20', 'Cash', 'On-time', NULL, 'Cashier', 0, 'Low', '2025-04-20', '2025-03-20'),
(84, 1, 4700.00, '2025-04-05', 'Cash', 'Monthly payment', NULL, 'Admin', 0, 'Low', '2025-05-05', '2025-04-05'),
(85, 2, 3100.00, '2025-04-09', 'Bank Transfer', 'Late 3 days', NULL, 'Cashier', 1, 'Medium', '2025-05-09', '2025-04-09'),
(86, 4, 2400.00, '2025-04-22', 'Check', 'Still overdue', NULL, 'Manager', 3, 'High', '2025-05-22', '2025-04-22'),
(87, 5, 1700.00, '2025-05-01', 'GCash', 'On-time mobile payment', NULL, 'Admin', 0, 'Low', '2025-06-01', '2025-05-01'),
(88, 1, 4800.00, '2025-05-05', 'Cash', 'Good payer', NULL, 'Admin', 0, 'Low', '2025-06-05', '2025-05-05');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `report_type` varchar(100) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `perm_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `action_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`perm_id`, `role_id`, `module_name`, `action_name`, `can_view`, `can_add`, `can_edit`, `can_delete`) VALUES
(18, 16, 'User Management', '', 1, 1, 1, 1),
(19, 16, 'Loan Portfolio', '', 1, 1, 1, 1),
(20, 16, 'Savings Monitoring', '', 1, 1, 1, 1),
(21, 16, 'Disbursement Tracker', '', 1, 1, 1, 1),
(22, 16, 'Compliance & Audit Trail', '', 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `savings`
--

CREATE TABLE `savings` (
  `saving_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('Deposit','Withdrawal') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `recorded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `savings`
--

INSERT INTO `savings` (`saving_id`, `member_id`, `transaction_date`, `transaction_type`, `amount`, `balance`, `recorded_by`) VALUES
(19, 1, '2025-10-16', 'Deposit', 7000.00, 12000.00, 1),
(20, 2, '2025-10-16', 'Deposit', 10000.00, 10000.00, 1),
(21, 3, '2025-10-17', 'Deposit', 8000.00, 8000.00, 1),
(22, 3, '2025-10-18', 'Withdrawal', 4000.00, 9000.00, 5),
(33, 5, '2025-10-22', 'Withdrawal', 10000.00, 10000.00, 5),
(34, 5, '2025-10-22', 'Deposit', 1500.00, 11500.00, 5),
(35, 5, '2025-10-22', 'Deposit', 2450.00, 13950.00, 5);

-- --------------------------------------------------------

--
-- Table structure for table `system_info`
--

CREATE TABLE `system_info` (
  `id` int(11) NOT NULL,
  `meta_field` varchar(100) NOT NULL,
  `meta_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_info`
--

INSERT INTO `system_info` (`id`, `meta_field`, `meta_value`) VALUES
(1, 'system_name', 'Microfinance EIS'),
(2, 'system_tagline', 'Integrated Loan, Savings, and Collection Monitoring'),
(3, 'address', 'Manila, Philippines'),
(4, 'contact', '+63 912 345 6789'),
(5, 'email', 'info@coret2.local'),
(6, 'logo', 'dist/img/logo.jpg'),
(7, 'cover', 'dist/img/default-cover.png'),
(8, 'theme_color', '#004aad'),
(9, 'footer_text', '© 2025 Core Transaction 2. All rights reserved.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('Admin','Manager','Officer','Compliance','Member') DEFAULT 'Member',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `username`, `password_hash`, `full_name`, `email`, `role`, `status`, `date_created`) VALUES
(1, 16, 'admin', '$2y$10$sOjM6AjMPhLIsJuDg0.FGODa0i.L086A4SdfLhy9xafoyhFCXv.8G', 'Compliance Auditor', 'fg708304@gmail.com', 'Manager', 'Active', '2025-10-12 06:30:49'),
(5, 16, 'Nands', '$2y$10$EfcejnGlguyfQcoPxphZUOr9HwONu9jqYf7EGI3Dbmbg7Q0mXlMxi', 'Fernando M. Gonzales Jr.', 'fg708304@gmail.com', 'Admin', 'Active', '2025-10-18 10:18:46'),
(6, NULL, 'User1', '$2y$10$PtDaLIgd9WkgmO/9.2DpfezOyG9deDaBtwy1TCBTw1.oCkWIGP8EW', 'Noby', 'alcorizatrixie@gmail.com', 'Member', 'Active', '2025-10-19 05:29:45'),
(7, NULL, 'Fernando', '$2y$10$hSka/B.mslNccC3D5LtXMelRnZjrKjGk8r/oMYojO6LW2GL7SCksG', 'Noby Gonzales', 'fg708304@gmail.com', 'Compliance', 'Active', '2025-10-22 05:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`, `description`, `created_at`) VALUES
(16, 'Admin', 'Full access to system', '2025-10-18 10:33:35'),
(17, 'Manager', 'Manage loans and users', '2025-10-18 10:33:35'),
(18, 'Officer', 'Handle day-to-day operations', '2025-10-18 10:33:35'),
(19, 'Auditor', 'View audit logs and reports', '2025-10-18 10:33:35'),
(20, 'Member', 'Basic access to own account', '2025-10-18 10:33:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_collection_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_collection_summary` (
`loan_id` int(11)
,`full_name` varchar(100)
,`total_collected` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_member_savings`
-- (See below for the actual view)
--
CREATE TABLE `v_member_savings` (
`member_id` int(11)
,`member_name` varchar(100)
,`total_savings` decimal(34,2)
,`transaction_count` bigint(21)
,`last_transaction_date` date
);

-- --------------------------------------------------------

--
-- Structure for view `v_collection_summary`
--
DROP TABLE IF EXISTS `v_collection_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_collection_summary`  AS SELECT `l`.`loan_id` AS `loan_id`, `m`.`full_name` AS `full_name`, sum(`c`.`amount_collected`) AS `total_collected` FROM ((`collections` `c` join `loan_portfolio` `l` on(`c`.`loan_id` = `l`.`loan_id`)) join `members` `m` on(`l`.`member_id` = `m`.`member_id`)) GROUP BY `l`.`loan_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_member_savings`
--
DROP TABLE IF EXISTS `v_member_savings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_member_savings`  AS SELECT `m`.`member_id` AS `member_id`, `m`.`full_name` AS `member_name`, ifnull(sum(`s`.`amount`),0) AS `total_savings`, count(`s`.`saving_id`) AS `transaction_count`, max(`s`.`transaction_date`) AS `last_transaction_date` FROM (`members` `m` left join `savings` `s` on(`m`.`member_id` = `s`.`member_id`)) GROUP BY `m`.`member_id`, `m`.`full_name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`collection_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `collector_id` (`collector_id`);

--
-- Indexes for table `compliance_logs`
--
ALTER TABLE `compliance_logs`
  ADD PRIMARY KEY (`compliance_id`),
  ADD KEY `audit_id` (`audit_id`);

--
-- Indexes for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD PRIMARY KEY (`disbursement_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `disbursement_logs`
--
ALTER TABLE `disbursement_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_logs_disbursement` (`disbursement_id`),
  ADD KEY `fk_logs_user` (`user_id`);

--
-- Indexes for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `loan_schedule`
--
ALTER TABLE `loan_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `permission_logs`
--
ALTER TABLE `permission_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `repayments`
--
ALTER TABLE `repayments`
  ADD PRIMARY KEY (`repayment_id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`perm_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `savings`
--
ALTER TABLE `savings`
  ADD PRIMARY KEY (`saving_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `system_info`
--
ALTER TABLE `system_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meta_field` (`meta_field`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_role_id` (`role_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `collection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `compliance_logs`
--
ALTER TABLE `compliance_logs`
  MODIFY `compliance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `disbursements`
--
ALTER TABLE `disbursements`
  MODIFY `disbursement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `disbursement_logs`
--
ALTER TABLE `disbursement_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `loan_schedule`
--
ALTER TABLE `loan_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=319;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `permission_logs`
--
ALTER TABLE `permission_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=501;

--
-- AUTO_INCREMENT for table `repayments`
--
ALTER TABLE `repayments`
  MODIFY `repayment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `perm_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `savings`
--
ALTER TABLE `savings`
  MODIFY `saving_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `system_info`
--
ALTER TABLE `system_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `collections`
--
ALTER TABLE `collections`
  ADD CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `collections_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `collections_ibfk_3` FOREIGN KEY (`collector_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `compliance_logs`
--
ALTER TABLE `compliance_logs`
  ADD CONSTRAINT `compliance_logs_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `audit_trail` (`audit_id`);

--
-- Constraints for table `disbursement_logs`
--
ALTER TABLE `disbursement_logs`
  ADD CONSTRAINT `fk_logs_disbursement` FOREIGN KEY (`disbursement_id`) REFERENCES `disbursements` (`disbursement_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `loan_portfolio`
--
ALTER TABLE `loan_portfolio`
  ADD CONSTRAINT `loan_portfolio_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`);

--
-- Constraints for table `loan_schedule`
--
ALTER TABLE `loan_schedule`
  ADD CONSTRAINT `loan_schedule_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`);

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `permission_logs`
--
ALTER TABLE `permission_logs`
  ADD CONSTRAINT `permission_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `repayments`
--
ALTER TABLE `repayments`
  ADD CONSTRAINT `repayments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_portfolio` (`loan_id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE CASCADE;

--
-- Constraints for table `savings`
--
ALTER TABLE `savings`
  ADD CONSTRAINT `savings_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `savings_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
