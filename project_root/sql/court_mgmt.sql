-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 10, 2026 at 04:40 AM
-- Server version: 5.7.36
-- PHP Version: 7.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `court_mgmt`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `status`, `created_at`) VALUES
(5, 1, '2026-01-03', 'Present', '2026-01-03 05:52:36'),
(7, 1, '2026-01-03', 'Present', '2026-01-03 06:07:56'),
(10, 1, '2026-01-03', 'Present', '2026-01-03 06:08:11'),
(11, 1, '2026-01-03', 'Present', '2026-01-03 06:31:59'),
(14, 1, '2026-01-03', 'Present', '2026-01-03 06:33:43'),
(19, 1, '2026-01-03', 'Present', '2026-01-03 06:35:38'),
(20, 1, '2026-01-03', 'Present', '2026-01-03 06:35:38'),
(21, 1, '2026-01-03', 'Present', '2026-01-03 06:35:39'),
(22, 1, '2026-01-03', 'Present', '2026-01-03 06:35:39'),
(23, 1, '2026-01-03', 'Present', '2026-01-03 06:35:39'),
(24, 1, '2026-01-03', 'Present', '2026-01-03 06:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `kind` enum('request','complaint') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Low','Normal','High','Urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `status` enum('submitted','in_review','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `requested_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_idx` (`employee_id`),
  KEY `status_idx` (`status`),
  KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `employee_id`, `kind`, `category`, `subject`, `description`, `priority`, `status`, `resolution_notes`, `created_by`, `created_at`, `updated_at`, `requested_date`, `completed_date`) VALUES
(1, 3, 'request', 'ddsadf', 'dfsdf', 'dsfdsf', 'Normal', 'completed', '', NULL, '2025-08-23 10:54:23', '2025-08-23 10:54:35', NULL, NULL),
(2, 3, 'complaint', 'Printer Repair', 'asdasdsa', 'asdsad', 'High', 'completed', 'sdsaddsadsadsadasdsada', NULL, '2025-08-23 11:04:45', '2025-08-23 11:05:12', '2025-08-08', NULL),
(3, 3, 'complaint', 'CFMS-DC', 'sadafd', 'sadad', 'Low', 'completed', 'sadadsadsadsad', NULL, '2025-08-23 11:07:42', '2025-08-23 11:08:09', '2025-08-02', '2025-08-23'),
(4, 3, 'request', 'CFMS-DC', 'adsfsaf', 'dsfdfdfdsfsfsfs', 'Normal', 'completed', 'awsdsdsd', NULL, '2025-08-23 11:12:43', '2025-08-23 11:18:27', '2025-08-02', '2025-08-23'),
(5, 3, 'complaint', 'Cartridge Refill', 'adwasd', 'sadasdad', 'Normal', 'completed', '', NULL, '2025-08-23 11:19:23', '2025-08-23 11:28:55', '2025-01-01', '2025-08-23'),
(6, 6, 'complaint', 'CFMS-DC', 'dsds', 'sdasd', 'Low', 'completed', '', NULL, '2025-08-23 11:37:22', '2025-08-23 11:37:40', '2025-01-01', '2025-08-23'),
(7, 6, 'request', 'Internet Connectivity', 'adads', 'sadsadsadd', 'Normal', 'completed', 'sdadas', NULL, '2025-08-26 07:45:51', '2025-08-26 07:46:15', '2025-08-01', '2025-08-26'),
(8, 6, 'request', 'CFMS-DC', 'sadadsad', 'asdsadd', 'Normal', 'completed', 'sadaasdad', NULL, '2025-08-27 05:08:50', '2025-08-27 05:09:21', '2025-08-08', '2025-08-27'),
(9, 6, 'request', 'Printer Repair', 'sdsaddsad', 'asdsadsadsd', 'Urgent', 'completed', 'dfdsfdsfs', NULL, '2025-08-27 06:14:28', '2025-08-27 06:14:50', '2025-08-27', '2025-08-27'),
(10, 14, 'complaint', 'CFMS-DC', 'Case Unlock', 'Please Unlock case', 'Urgent', 'completed', '', NULL, '2026-01-05 05:21:14', '2026-01-05 05:21:49', '2026-01-05', '2026-01-05');

-- --------------------------------------------------------

--
-- Table structure for table `courts`
--

DROP TABLE IF EXISTS `courts`;
CREATE TABLE IF NOT EXISTS `courts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `district` varchar(255) DEFAULT NULL,
  `taluka` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `courts`
--

INSERT INTO `courts` (`id`, `name`, `district`, `taluka`) VALUES
(3, 'District & Sessions Court', 'Jamshoro', NULL),
(4, 'Additional District & Sessions Court-I, Kotri', 'Jamshoro', NULL),
(5, 'Additional District & Sessions Court-II, Kotri', 'Jamshoro', NULL),
(6, 'Additional District & Sessions Court, Sehwan', 'Jamshoro', NULL),
(7, 'Senior Civil Court-I, Kotri', 'Jamshoro', NULL),
(8, 'Senior Civil Court-II, Kotri', 'Jamshoro', NULL),
(9, 'Senior Civil Court, Sehwan', 'Jamshoro', NULL),
(10, 'Civil Court-I, Kotri', 'Jamshoro', NULL),
(11, 'Civil Court-II, Kotri', 'Jamshoro', NULL),
(12, 'Family Court Jamshoro', 'Jamshoro', NULL),
(13, 'Consumer Protection Court', 'Jamshoro', NULL),
(14, 'Civil Court-I, Sehwan', 'Jamshoro', NULL),
(15, 'Civil Court-II, Sehwan', 'Jamshoro', NULL),
(16, 'Civil Court-III, Sehwan At Thano Bula Khan', 'Jamshoro', NULL),
(17, 'Civil Court Manjhan', 'Jamshoro', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
CREATE TABLE IF NOT EXISTS `districts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `name`) VALUES
(9, 'Dadu'),
(18, 'Ghotki'),
(7, 'Hyderabad'),
(1, 'Jamshoro'),
(20, 'Kambar Shahdadkot @ Kambar'),
(5, 'Karachi (Central)'),
(4, 'Karachi (East)'),
(6, 'Karachi (Malir)'),
(2, 'Karachi (South)'),
(3, 'Karachi (West)'),
(17, 'Khairpur'),
(19, 'Larkana'),
(23, 'Matiyari'),
(11, 'Mirpurkhas'),
(14, 'Naushero Feroz'),
(13, 'Sanghar'),
(15, 'Shaheed Benazirabad'),
(16, 'Sukkur'),
(21, 'Tando Allahyar'),
(22, 'Tando Muhammad Khan'),
(10, 'Tharparker @ Mithi'),
(8, 'Thata'),
(12, 'Umerkot');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','reader','employee','librarian') DEFAULT 'employee',
  `court_id` int(11) DEFAULT NULL,
  `taluka` varchar(255) DEFAULT NULL,
  `bps` varchar(20) DEFAULT NULL,
  `post` varchar(100) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `cnic` varchar(50) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `court_id` (`court_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `username`, `password_hash`, `role`, `court_id`, `taluka`, `bps`, `post`, `contact`, `cnic`, `joining_date`, `created_at`) VALUES
(1, 'Super Admin', 'admin', '$2y$10$EVviZFXBEHMssDrjURiMLesyVmgWUWibD/4w.OkDib8t6SelQc7PO', 'admin', NULL, NULL, 'BPS-21', 'Administrator', '0123456789', '12345-6789012-3', '2020-01-01', '2025-08-11 05:15:11'),
(11, 'xee', 'xee', '$2y$10$LvP6LddlcnbWJ5cjm5w5juUs1GYu2H1sHrl4nu2QOLVaur3DtmNQ2', 'reader', 6, NULL, '1', 'Naib Qasid', NULL, NULL, NULL, '2026-01-05 03:49:59'),
(12, 'zeeshan', 'zeeshan', '$2y$10$KVj/5Xs0SxseLWDw9GwzvORZw/SxyVswGoCOEzRH9yrWKbkvS4zSC', 'reader', 6, NULL, '2', 'Farash', NULL, NULL, NULL, '2026-01-05 03:57:49'),
(14, 'asad', 'asad', '$2y$10$3dUHX6hqvs3aIeBOSAhMce66.xR/OS846VrVcG5mGf3/UQw7GkLnW', 'reader', 3, NULL, '14', 'Reader', NULL, NULL, NULL, '2026-01-05 05:16:55');

-- --------------------------------------------------------

--
-- Table structure for table `employee_details`
--

DROP TABLE IF EXISTS `employee_details`;
CREATE TABLE IF NOT EXISTS `employee_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `father_name` varchar(150) NOT NULL,
  `post_id` int(11) NOT NULL,
  `court_id` int(11) NOT NULL,
  `bps` varchar(20) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_appointment` date NOT NULL,
  `date_of_retirement` date NOT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_post` (`post_id`),
  KEY `fk_employee_details_court` (`court_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `employee_details`
--

INSERT INTO `employee_details` (`id`, `name`, `father_name`, `post_id`, `court_id`, `bps`, `date_of_birth`, `date_of_appointment`, `date_of_retirement`, `cnic`, `pic`, `created_at`, `updated_at`) VALUES
(1, 'Zeeshan Alii', 'Shoukat Hussain', 14, 17, '14', NULL, '2025-12-08', '2025-12-08', '4120487635337', '1755669891_theta.png', '2025-08-19 10:04:16', '2026-01-07 09:31:38'),
(3, 'Xee', 'Xee', 17, 17, '16', '2025-08-21', '2025-08-21', '2025-08-21', NULL, '1755760272_theta.png', '2025-08-21 07:11:12', '2026-01-07 09:31:38'),
(4, 'DSFDSFDSF', 'SDFDSF', 18, 17, '14', '1990-12-12', '2020-12-12', '2040-12-12', '5464564654', '1767761608_MBSindhi2010.png', '2026-01-02 07:33:29', '2026-01-07 09:31:38');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `court_id` int(11) DEFAULT NULL,
  `emp_detail_id` int(11) DEFAULT NULL,
  `file_path` text NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`),
  KEY `court_id` (`court_id`),
  KEY `idx_emp_detail` (`emp_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `owner_id`, `court_id`, `emp_detail_id`, `file_path`, `file_name`, `created_at`, `category`) VALUES
(2, 1, 3, 4, 'C:\\wamp64\\www\\a\\backend\\config/../../uploads/Judgment/1767424051_2023_S_C_M_R_679.docx', '2023_S_C_M_R_679.docx', '2026-01-03 07:07:31', 'Judgment'),
(3, 1, 3, 3, 'C:\\wamp64\\www\\a\\backend\\config/../../uploads/Judgment/1767597264_loan_act.pdf', 'loan_act.pdf', '2026-01-05 07:14:24', 'Judgment');

-- --------------------------------------------------------

--
-- Table structure for table `file_categories`
--

DROP TABLE IF EXISTS `file_categories`;
CREATE TABLE IF NOT EXISTS `file_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `file_categories`
--

INSERT INTO `file_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Judgment', '2026-01-02 06:01:54'),
(2, 'Service Book', '2026-01-06 07:19:21');

-- --------------------------------------------------------

--
-- Table structure for table `judicial_officers`
--

DROP TABLE IF EXISTS `judicial_officers`;
CREATE TABLE IF NOT EXISTS `judicial_officers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `officer_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `post` varchar(255) NOT NULL,
  `bps` varchar(20) NOT NULL,
  `court_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) DEFAULT 'Posted',
  `joining_date` date DEFAULT NULL,
  `transferred_date` date DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `transferred_district` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `court_id` (`court_id`),
  KEY `fk_district` (`district_id`),
  KEY `fk_post` (`post_id`),
  KEY `fk_officer` (`officer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `judicial_officers`
--

INSERT INTO `judicial_officers` (`id`, `officer_id`, `post_id`, `name`, `post`, `bps`, `court_id`, `created_at`, `status`, `joining_date`, `transferred_date`, `district_id`, `transferred_district`) VALUES
(8, NULL, NULL, 'Ali Ahmed Jan', 'District & Sessions Judge', '21', 3, '2025-08-18 09:55:15', 'Posted', '2025-08-18', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `judicial_officer_names`
--

DROP TABLE IF EXISTS `judicial_officer_names`;
CREATE TABLE IF NOT EXISTS `judicial_officer_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `officer_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `judicial_officer_names`
--

INSERT INTO `judicial_officer_names` (`id`, `officer_name`, `created_at`) VALUES
(1, 'Xee', '2025-08-18 09:44:47'),
(2, 'wdssd', '2025-08-18 09:52:15');

-- --------------------------------------------------------

--
-- Table structure for table `judicial_post`
--

DROP TABLE IF EXISTS `judicial_post`;
CREATE TABLE IF NOT EXISTS `judicial_post` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `judicial_post`
--

INSERT INTO `judicial_post` (`id`, `post_name`) VALUES
(1, 'District & Sessions Judge'),
(2, 'Addtional District & Sessions Judge'),
(3, 'Senior/ Assistant Sessions Judge'),
(4, 'Civil Judge & Judicial Magistrate'),
(5, 'Family Judge'),
(6, 'Consumer Protection Judge');

-- --------------------------------------------------------

--
-- Table structure for table `judicial_posts`
--

DROP TABLE IF EXISTS `judicial_posts`;
CREATE TABLE IF NOT EXISTS `judicial_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `judicial_posts`
--

INSERT INTO `judicial_posts` (`id`, `post_name`) VALUES
(1, 'District & Sessions Judge, Jamshoro'),
(2, 'Addtional District & Sessions Judge'),
(3, 'Senior/ Assistant Sessions Judge'),
(4, 'Civil Judge & Judicial Magistrate'),
(5, 'Family Judge'),
(6, 'Consumer Protection Judge'),
(7, 'District & Sessions Judge, Jamshoro'),
(8, 'Addtional District & Sessions Judge'),
(9, 'Senior/ Assistant Sessions Judge'),
(10, 'Civil Judge & Judicial Magistrate'),
(11, 'Family Judge'),
(12, 'Consumer Protection Judge');

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
CREATE TABLE IF NOT EXISTS `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_detail_id` int(11) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_leaves_employee_details` (`employee_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `employee_detail_id`, `leave_type`, `start_date`, `end_date`, `status`, `remarks`, `created_at`) VALUES
(14, 1, 'Casual', '2025-08-31', '2025-08-31', 'approved', '', '2025-08-16 06:56:15'),
(36, 4, 'Casual', '2026-01-06', '2026-01-06', 'pending', 'sdsd', '2026-01-06 09:55:35'),
(37, 3, 'Optional', '2026-01-06', '2026-01-06', 'pending', 'sadsadasd', '2026-01-06 09:55:53'),
(38, 4, 'Optional', '2026-01-07', '2026-01-08', 'pending', 'sadadasdad', '2026-01-07 04:40:36'),
(39, 1, 'Earned', '2026-01-08', '2026-01-08', 'pending', '', '2026-01-07 04:40:55'),
(40, 4, 'Earned', '2026-01-10', '2026-01-10', 'pending', '', '2026-01-07 05:03:23'),
(41, 4, 'Casual', '2026-01-20', '2026-01-25', 'pending', 'sdadsd', '2026-01-07 06:28:26');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `created_at`) VALUES
(1, 'Casual', '2025-08-16 06:44:51'),
(2, 'Sick', '2025-08-16 06:44:51'),
(3, 'Earned', '2025-08-16 06:44:51'),
(4, 'Optional', '2025-08-16 06:49:29'),
(5, 'Maternity', '2025-08-23 09:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `library_books`
--

DROP TABLE IF EXISTS `library_books`;
CREATE TABLE IF NOT EXISTS `library_books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `rack_no` varchar(50) DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT '1',
  `available_qty` int(11) NOT NULL DEFAULT '1',
  `file_path` text,
  `file_name` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `publisher` varchar(255) DEFAULT NULL,
  `edition` varchar(100) DEFAULT NULL,
  `published_year` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `language` varchar(100) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lib_cat` (`category_id`),
  KEY `fk_lib_uploader` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `library_categories`
--

DROP TABLE IF EXISTS `library_categories`;
CREATE TABLE IF NOT EXISTS `library_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `library_loans`
--

DROP TABLE IF EXISTS `library_loans`;
CREATE TABLE IF NOT EXISTS `library_loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('issued','returned','overdue','cancelled') DEFAULT 'issued',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lib_loan_book` (`book_id`),
  KEY `fk_lib_loan_borrower` (`borrower_id`),
  KEY `fk_lib_loan_issuer` (`issued_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `official_transfer_orders`
--

DROP TABLE IF EXISTS `official_transfer_orders`;
CREATE TABLE IF NOT EXISTS `official_transfer_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(100) NOT NULL,
  `order_date` date NOT NULL,
  `judge_id` int(11) NOT NULL,
  `include_superintendent` tinyint(1) DEFAULT '0',
  `employees_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `judge_id` (`judge_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `official_transfer_orders`
--

INSERT INTO `official_transfer_orders` (`id`, `order_no`, `order_date`, `judge_id`, `include_superintendent`, `employees_data`, `created_at`, `updated_at`) VALUES
(1, '300', '2026-01-20', 8, 1, '{\"1\": {\"to\": \"6\", \"remarks\": \"fddsf\"}, \"3\": {\"to\": \"6\", \"remarks\": \"dsfsf\"}, \"4\": {\"to\": \"6\", \"remarks\": \"dsdfg\"}}', '2026-01-07 08:15:00', '2026-01-07 09:23:48'),
(2, '301', '2026-01-07', 8, 1, '{\"1\": {\"to\": \"17\", \"remarks\": \"fdsdfds\"}, \"3\": {\"to\": \"17\", \"remarks\": \"sdfdsf\"}, \"4\": {\"to\": \"17\", \"remarks\": \"sfsdfds\"}}', '2026-01-07 09:31:08', '2026-01-07 09:31:08');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serial_no` int(11) DEFAULT '0',
  `court_name` varchar(100) NOT NULL,
  `post_name` varchar(255) NOT NULL,
  `bps` int(11) NOT NULL,
  `sanctioned_strength` int(11) DEFAULT '0',
  `working_strength` int(11) DEFAULT '0',
  `vacant` int(11) GENERATED ALWAYS AS ((`sanctioned_strength` - `working_strength`)) STORED,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `serial_no`, `court_name`, `post_name`, `bps`, `sanctioned_strength`, `working_strength`) VALUES
(9, 1, 'District Court Jamshoro', 'Office Superintendent', 17, 1, 1),
(10, 2, 'District Court Jamshoro', 'Assistant Superintendent', 16, 1, 0),
(11, 3, 'District Court Jamshoro', 'Assistant Identification and Surety Branch', 16, 1, 1),
(12, 4, 'District Court Jamshoro', 'Stenographer', 16, 9, 7),
(13, 5, 'District Court Jamshoro', 'Librarian', 14, 1, 1),
(14, 6, 'District Court Jamshoro', 'Data Coder', 14, 1, 1),
(15, 7, 'District Court Jamshoro', 'Hardware and Network Technician', 14, 1, 1),
(16, 8, 'District Court Jamshoro', 'Computer Operator', 16, 2, 2),
(17, 9, 'District Court Jamshoro', 'Accountant', 16, 1, 1),
(18, 10, 'District Court Jamshoro', 'Assistant Accountant', 14, 1, 1),
(19, 11, 'District Court Jamshoro', 'Nazir of District Court', 16, 4, 4),
(20, 12, 'District Court Jamshoro', 'COC of District Court', 16, 2, 2),
(21, 15, 'District Court Jamshoro', 'Reader of the Senior Civil Judge/Civil Judge', 14, 8, 8),
(23, 16, 'District Court Jamshoro', 'Assistant Record Keeper of the District Court', 11, 1, 1),
(24, 17, 'District Court Jamshoro', 'Senior Clerk', 14, 1, 1),
(25, 18, 'District Court Jamshoro', 'English Clerk of Civil Court', 11, 1, 1),
(26, 19, 'District Court Jamshoro', 'Junior Clerk', 11, 37, 37),
(27, 20, 'District Court Jamshoro', 'Driver', 7, 5, 4),
(28, 21, 'District Court Jamshoro', 'CCTV Operator', 5, 1, 1),
(29, 22, 'District Court Jamshoro', 'Bailiff', 5, 20, 20),
(30, 23, 'District Court Jamshoro', 'Book Binder', 5, 1, 1),
(31, 24, 'District Court Jamshoro', 'Naib Qasid', 3, 30, 30),
(32, 25, 'District Court Jamshoro', 'Naib Qasid', 2, 2, 2),
(33, 26, 'District Court Jamshoro', 'Chowkidar', 3, 12, 12),
(34, 27, 'District Court Jamshoro', 'Chowkidar', 2, 2, 2),
(35, 28, 'District Court Jamshoro', 'Farash', 3, 3, 3),
(36, 29, 'District Court Jamshoro', 'Farash', 2, 1, 1),
(37, 30, 'District Court Jamshoro', 'Malhi', 3, 1, 1),
(38, 31, 'District Court Jamshoro', 'Malhi', 2, 1, 1),
(39, 32, 'District Court Jamshoro', 'Sweeper', 3, 9, 9),
(40, 33, 'Consumer Protection Court', 'Assistant', 16, 1, 0),
(41, 34, 'Consumer Protection Court', 'Reader', 14, 1, 1),
(42, 35, 'Consumer Protection Court', 'Data Processing Assistant', 12, 1, 1),
(43, 36, 'Consumer Protection Court', 'Junior Clerk', 11, 1, 1),
(44, 37, 'Consumer Protection Court', 'Bailiff', 4, 1, 1),
(45, 38, 'Consumer Protection Court', 'Driver', 4, 1, 1),
(46, 39, 'Consumer Protection Court', 'Naib Qasid', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff_report_meta`
--

DROP TABLE IF EXISTS `staff_report_meta`;
CREATE TABLE IF NOT EXISTS `staff_report_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `admn_no` varchar(100) DEFAULT NULL,
  `report_date_text` varchar(100) DEFAULT NULL,
  `judge_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_report_date` (`report_date`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `staff_report_meta`
--

INSERT INTO `staff_report_meta` (`id`, `report_date`, `admn_no`, `report_date_text`, `judge_id`, `created_at`) VALUES
(1, '2025-08-15', '122', '12-08-2025', NULL, '2025-08-15 05:58:02'),
(2, '2025-08-01', '11', '12-08-2025', NULL, '2025-08-15 06:14:20'),
(3, '2025-08-19', 'wedaasd', '18-08-2025', NULL, '2025-08-19 05:07:14'),
(4, '2025-09-19', 'wdsd', 'sadsd', NULL, '2025-08-19 07:51:04'),
(5, '2025-09-20', '30', '2025-08-12', 8, '2025-08-19 07:55:11'),
(6, '2025-08-23', '12121', '33232', 8, '2025-08-23 07:37:40'),
(7, '2025-08-26', 'dwsdsdd', 'sdsdsd', 8, '2025-08-26 07:56:47'),
(8, '2025-08-27', '20', '28-08-2025', 8, '2025-08-27 06:10:03'),
(9, '2026-01-05', '786', '05-01-2026', 8, '2026-01-05 06:01:33'),
(10, '2026-01-07', '', '', 8, '2026-01-07 07:43:27');

-- --------------------------------------------------------

--
-- Table structure for table `talukas`
--

DROP TABLE IF EXISTS `talukas`;
CREATE TABLE IF NOT EXISTS `talukas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
CREATE TABLE IF NOT EXISTS `transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `from_court_id` int(11) DEFAULT NULL,
  `to_court_id` int(11) DEFAULT NULL,
  `date_of_transfer` date DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_history`
--

DROP TABLE IF EXISTS `transfer_history`;
CREATE TABLE IF NOT EXISTS `transfer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `old_court_id` int(11) DEFAULT NULL,
  `new_court_id` int(11) NOT NULL,
  `type` enum('Transfer','Posting') DEFAULT 'Transfer',
  `remarks` text,
  `transfer_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `old_court_id` (`old_court_id`),
  KEY `new_court_id` (`new_court_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `transfer_history`
--

INSERT INTO `transfer_history` (`id`, `employee_id`, `old_court_id`, `new_court_id`, `type`, `remarks`, `transfer_date`, `created_at`) VALUES
(1, 1, 6, 8, 'Transfer', '', '2025-02-02', '2025-08-21 05:38:21'),
(2, 1, 8, 3, 'Posting', '', '2025-02-02', '2025-08-21 05:38:29'),
(3, 1, 3, 13, 'Posting', '', '2025-01-01', '2025-08-21 05:38:57'),
(4, 1, 13, 3, 'Transfer', '', '2025-08-21', '2025-08-21 06:49:17'),
(5, 1, 3, 7, 'Posting', '', '2025-08-21', '2025-08-21 06:49:31'),
(6, 1, 7, 12, 'Transfer', '', '2025-08-21', '2025-08-21 06:55:55'),
(7, 1, 12, 14, 'Transfer', '', '2025-08-21', '2025-08-21 06:56:03'),
(8, 3, 5, 10, 'Transfer', '', '2025-08-21', '2025-08-21 07:11:33'),
(9, 1, 14, 10, 'Transfer', '', '2025-08-21', '2025-08-21 07:11:33'),
(10, 3, 10, 6, 'Transfer', '', '2025-08-21', '2025-08-21 09:06:32'),
(11, 1, 10, 12, 'Transfer', '', '2025-08-21', '2025-08-21 09:06:32'),
(14, 3, 6, 8, 'Transfer', '', '2025-01-01', '2025-08-23 04:25:14'),
(15, 3, 8, 6, 'Transfer', '', '2222-09-11', '2025-08-23 05:11:25'),
(16, 3, 6, 10, 'Transfer', 'sdsadda', '2025-01-01', '2025-08-23 05:15:11'),
(17, 3, 10, 10, 'Transfer', 'sdsadda', '2025-01-01', '2025-08-23 05:15:30'),
(18, 3, 10, 8, 'Transfer', 'dsadsad', '2025-01-01', '2025-08-23 05:16:13'),
(19, 1, 12, 12, 'Transfer', '', '2025-01-01', '2025-08-23 05:16:24'),
(20, 3, 8, 8, 'Transfer', 'sadd', '2025-01-01', '2025-08-23 07:46:54'),
(21, 3, 8, 14, 'Transfer', 'dadd', '2025-01-01', '2025-08-23 07:47:05'),
(22, 3, 14, 3, 'Transfer', 'dssd', '2029-01-01', '2025-08-23 08:59:16'),
(23, 3, 3, 13, 'Transfer', 'Transferred', '2026-05-01', '2026-01-05 07:25:19'),
(24, 1, 12, 9, 'Transfer', 'sdsddsd', '2026-01-07', '2026-01-07 07:29:41'),
(25, 3, 13, 16, 'Transfer', '22222', '2026-01-10', '2026-01-07 07:34:45'),
(26, 4, 3, 3, 'Transfer', 'fdsfdfs', '2026-01-10', '2026-01-07 07:47:14'),
(27, 3, 16, 3, 'Transfer', 'sdfsdfdsf', '2026-01-10', '2026-01-07 07:47:14'),
(28, 1, 9, 3, 'Transfer', 'dsfdsfdsf', '2026-01-10', '2026-01-07 07:47:14'),
(29, 1, 3, 6, 'Transfer', 'fddsf', '2026-01-20', '2026-01-07 09:24:13'),
(30, 3, 3, 6, 'Transfer', 'dsfsf', '2026-01-20', '2026-01-07 09:24:13'),
(31, 4, 3, 6, 'Transfer', 'dsdfg', '2026-01-20', '2026-01-07 09:24:13'),
(32, 1, 6, 17, 'Transfer', 'fdsdfds', '2026-01-20', '2026-01-07 09:31:38'),
(33, 3, 6, 17, 'Transfer', 'sdfdsf', '2026-01-20', '2026-01-07 09:31:38'),
(34, 4, 6, 17, 'Transfer', 'sfsdfds', '2026-01-20', '2026-01-07 09:31:38');

-- --------------------------------------------------------

--
-- Table structure for table `transfer_postings`
--

DROP TABLE IF EXISTS `transfer_postings`;
CREATE TABLE IF NOT EXISTS `transfer_postings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `transfer_type` enum('Transfer','Posting') NOT NULL,
  `from_court_id` int(11) NOT NULL,
  `to_court_id` int(11) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Stand-in structure for view `users`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
`id` int(11)
,`name` varchar(255)
,`username` varchar(100)
,`password_hash` varchar(255)
,`role` enum('admin','reader','employee','librarian')
,`court_id` int(11)
,`bps` varchar(20)
,`post` varchar(100)
,`contact` varchar(100)
,`cnic` varchar(50)
,`joining_date` date
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `users`
--
DROP TABLE IF EXISTS `users`;

DROP VIEW IF EXISTS `users`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `users`  AS SELECT `employees`.`id` AS `id`, `employees`.`name` AS `name`, `employees`.`username` AS `username`, `employees`.`password_hash` AS `password_hash`, `employees`.`role` AS `role`, `employees`.`court_id` AS `court_id`, `employees`.`bps` AS `bps`, `employees`.`post` AS `post`, `employees`.`contact` AS `contact`, `employees`.`cnic` AS `cnic`, `employees`.`joining_date` AS `joining_date`, `employees`.`created_at` AS `created_at` FROM `employees` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_files_emp_detail` FOREIGN KEY (`emp_detail_id`) REFERENCES `employee_details` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `fk_leaves_employee_details` FOREIGN KEY (`employee_detail_id`) REFERENCES `employee_details` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_books`
--
ALTER TABLE `library_books`
  ADD CONSTRAINT `fk_lib_cat` FOREIGN KEY (`category_id`) REFERENCES `library_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lib_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `library_loans`
--
ALTER TABLE `library_loans`
  ADD CONSTRAINT `fk_lib_loan_book` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lib_loan_borrower` FOREIGN KEY (`borrower_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lib_loan_issuer` FOREIGN KEY (`issued_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
