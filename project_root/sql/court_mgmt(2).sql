-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 16, 2026 at 06:19 AM
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `court_id` int(11) DEFAULT NULL,
  `kind` enum('request','complaint') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfms_case_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `complaint_id_unique` (`complaint_id`),
  KEY `employee_idx` (`employee_id`),
  KEY `status_idx` (`status`),
  KEY `created_by_idx` (`created_by`),
  KEY `idx_complaints_court` (`court_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `judicial_post`
--

DROP TABLE IF EXISTS `judicial_post`;
CREATE TABLE IF NOT EXISTS `judicial_post` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `judicial_posts`
--

DROP TABLE IF EXISTS `judicial_posts`;
CREATE TABLE IF NOT EXISTS `judicial_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
