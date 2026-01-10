-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 12, 2025 at 05:58 AM
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
(6, 'additional District & Sessions Court, Sehwan', 'Jamshoro', NULL),
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
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','reader','employee') DEFAULT 'employee',
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `username`, `password_hash`, `role`, `court_id`, `taluka`, `bps`, `post`, `contact`, `cnic`, `joining_date`, `created_at`) VALUES
(1, 'Super Admin', 'admin', '$2y$10$Whsy/Oy2JVwGQP07L0vREuwpRw6o7NASkeEaYhKWQK0bxE1TDkwvO', 'admin', NULL, NULL, 'BPS-21', 'Administrator', '0123456789', '12345-6789012-3', '2020-01-01', '2025-08-11 05:15:11'),
(2, 'Reader User', 'reader', '$2y$10$Whsy/Oy2JVwGQP07L0vREuwpRw6o7NASkeEaYhKWQK0bxE1TDkwvO', 'reader', NULL, NULL, 'BPS-17', 'Reader', '0123456789', '12345-6789012-4', '2021-05-01', '2025-08-11 05:15:11'),
(3, 'Sample Employee', 'emp1', '$2y$10$Whsy/Oy2JVwGQP07L0vREuwpRw6o7NASkeEaYhKWQK0bxE1TDkwvO', 'employee', NULL, NULL, 'BPS-05', 'Clerk', '0123456789', '12345-6789012-5', '2022-03-01', '2025-08-11 05:15:11'),
(4, 'Zeeshan Ali', 'xee', '$2y$10$vSmRYEmm2RSV5zRb.hiS5uvBRtzN55TKXrtBcvBYHLw4yzyjaV6wu', 'employee', 3, NULL, '14', 'Data Coder', NULL, NULL, NULL, '2025-08-11 06:07:59'),
(5, 'Asad', 'asad', '$2y$10$nzKz.h2kXWUez4eq6bE18upA7eRRMZu1B0WTdvaYg0V5UlziFQvCy', 'employee', 3, NULL, '11', 'Clerk', NULL, NULL, NULL, '2025-08-11 06:49:24');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `court_id` int(11) DEFAULT NULL,
  `file_path` text NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`),
  KEY `court_id` (`court_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
CREATE TABLE IF NOT EXISTS `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `status`, `remarks`, `created_at`) VALUES
(1, 3, 'Casual', '2025-08-12', '2025-08-12', 'approved', 'Casual Leave', '2025-08-11 05:53:08'),
(2, 5, 'Casual', '2025-08-11', '2025-08-11', 'approved', '', '2025-08-11 06:49:57');

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
-- Stand-in structure for view `users`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
`id` int(11)
,`name` varchar(255)
,`username` varchar(100)
,`password_hash` varchar(255)
,`role` enum('admin','reader','employee')
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
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
