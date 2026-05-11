-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2026 at 02:42 PM
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
-- Database: `wowasco`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset_name` varchar(150) NOT NULL,
  `asset_type` varchar(100) NOT NULL,
  `subtype` varchar(100) DEFAULT NULL,
  `serial_number` varchar(150) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `asset_value` decimal(12,2) DEFAULT 0.00,
  `depreciated_value` decimal(12,2) DEFAULT 0.00,
  `net_value` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_name`, `asset_type`, `subtype`, `serial_number`, `location`, `purchase_date`, `date_added`, `status`, `asset_value`, `depreciated_value`, `net_value`, `created_at`, `is_deleted`) VALUES
(1, 'smart meter', 'Smart Meter', 'Fixed Asset', 'NM-465657HU', 'Westlands', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:50:48', 0),
(2, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:54:42', 0),
(3, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:56:13', 0),
(4, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:58:00', 0),
(5, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:03:51', 0),
(6, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:12:50', 0),
(7, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:16:50', 0),
(8, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:21:17', 0),
(9, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:23:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `bill_month` varchar(20) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumption`
--

CREATE TABLE `consumption` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `month` varchar(20) DEFAULT NULL,
  `units_used` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `meter_type` varchar(50) DEFAULT NULL,
  `customer_type` varchar(50) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `created_at`, `phone`, `alt_phone`, `id_number`, `meter_type`, `customer_type`, `zone`) VALUES
(1, 'janet dow', '', '2026-05-01 15:37:35', '0713243546', '0713254364', '1324354657', 'Smart Meter', 'Domestic', 'westlands');

-- --------------------------------------------------------

--
-- Table structure for table `customer_complaints`
--

CREATE TABLE `customer_complaints` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `complaint` text NOT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `assigned_staff` varchar(100) DEFAULT NULL,
  `escalation_reason` text DEFAULT NULL,
  `pending_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `infrastructure`
--

CREATE TABLE `infrastructure` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(100) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `asset_category` varchar(100) DEFAULT NULL,
  `activity` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meters`
--

CREATE TABLE `meters` (
  `id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `national_id` varchar(50) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `alternative_phone` varchar(20) DEFAULT NULL,
  `customer_type` varchar(100) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `meter_type` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `installation_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `maintenance_status` varchar(50) DEFAULT 'Good',
  `last_reading_date` date DEFAULT NULL,
  `battery_level` int(11) DEFAULT 100,
  `signal_strength` int(11) DEFAULT 100,
  `is_online` tinyint(1) DEFAULT 1,
  `technician_assigned` varchar(100) DEFAULT NULL,
  `alert_acknowledged` tinyint(1) DEFAULT 0,
  `last_service_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`id`, `serial_number`, `zone`, `status`, `created_at`, `national_id`, `customer_phone`, `alternative_phone`, `customer_type`, `customer_name`, `meter_type`, `model`, `installation_date`, `is_deleted`, `maintenance_status`, `last_reading_date`, `battery_level`, `signal_strength`, `is_online`, `technician_assigned`, `alert_acknowledged`, `last_service_date`) VALUES
(11, 'WM-335358', 'Kilala', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Grace Mwikali', '', '', '0000-00-00', 0, 'Good', NULL, 43, 54, 1, NULL, 0, NULL),
(12, 'WM-766394', 'Westlands', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'John Mutua', '', '', '0000-00-00', 0, 'Good', NULL, 53, 82, 1, NULL, 0, NULL),
(13, 'WM-344352', 'Mwaani', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Commercial', 'John Mutua', '', '', '0000-00-00', 0, 'Good', NULL, 41, 40, 1, NULL, 0, NULL),
(14, 'WM-934084', 'Kasarani', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Mutinda Hotel', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 69, 76, 1, NULL, 0, NULL),
(15, 'WM-381305', 'Kilala', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Mtito Traders', '', '', '0000-00-00', 0, 'Good', NULL, 74, 60, 1, NULL, 0, NULL),
(16, 'WM-130043', 'Kilala', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'Makindu Hospital', '', '', '0000-00-00', 0, 'Good', NULL, 67, 67, 1, NULL, 0, NULL),
(17, 'WM-887215', 'Kitikyumu', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Wote Market', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 46, 73, 1, NULL, 0, NULL),
(18, 'WM-318189', 'Kundakindu', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Green Farm', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 87, 61, 1, NULL, 0, NULL),
(19, 'WM-865070', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Green Farm', '', '', '0000-00-00', 0, 'Good', NULL, 74, 88, 1, NULL, 0, NULL),
(20, 'WM-908885', 'Muambani', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'John Mutua', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 97, 82, 1, NULL, 0, NULL),
(21, 'WM-351757', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'Green Farm', '', '', '0000-00-00', 0, 'Good', NULL, 72, 33, 1, NULL, 0, NULL),
(22, 'WM-168753', 'Malawi', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Wote Market', '', '', '0000-00-00', 0, 'Good', NULL, 75, 76, 1, NULL, 0, NULL),
(23, 'WM-631195', 'Kilala', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Commercial', 'Wote Market', '', '', '0000-00-00', 0, 'Good', NULL, 58, 47, 1, NULL, 0, NULL),
(24, 'WM-204580', 'Town', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Wote Market', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 83, 46, 1, NULL, 0, NULL),
(25, 'WM-306581', 'Return', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Grace Mwikali', '', '', '0000-00-00', 0, 'Good', NULL, 89, 45, 1, NULL, 0, NULL),
(26, 'WM-614459', 'Kitikyumu', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Mtito Traders', '', '', '0000-00-00', 0, 'Good', NULL, 72, 76, 1, NULL, 0, NULL),
(27, 'WM-584445', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Mutinda Hotel', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 47, 32, 1, NULL, 0, NULL),
(28, 'WM-899869', 'Kilala', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'John Mutua', '', '', '0000-00-00', 0, 'Good', NULL, 49, 98, 1, NULL, 0, NULL),
(29, 'WM-143474', 'Malawi', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'John Mutua', '', '', '0000-00-00', 0, 'Good', NULL, 87, 91, 1, NULL, 0, NULL),
(30, 'WM-518823', 'Town', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Makueni Boys School', '', '', '0000-00-00', 0, 'Good', NULL, 75, 91, 1, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `meter_activity_logs`
--

CREATE TABLE `meter_activity_logs` (
  `id` int(11) NOT NULL,
  `activity` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meter_alert_logs`
--

CREATE TABLE `meter_alert_logs` (
  `id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `alert_type` varchar(100) DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `alert_message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meter_alert_logs`
--

INSERT INTO `meter_alert_logs` (`id`, `meter_id`, `alert_type`, `severity`, `alert_message`, `status`, `created_at`, `message`) VALUES
(1, 16, 'Inactive Meter', 'Low', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(2, 22, 'Maintenance Required', 'High', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(3, 24, 'Weak Signal', 'High', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(4, 25, 'Weak Signal', 'Low', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(5, 11, 'Weak Signal', 'Medium', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(6, 23, 'Weak Signal', 'High', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(7, 19, 'Weak Signal', 'Medium', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(8, 17, 'Maintenance Required', 'Low', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert'),
(9, 28, 'Maintenance Required', 'High', NULL, 'Open', '2026-05-11 11:37:26', 'Auto-generated system alert');

-- --------------------------------------------------------

--
-- Table structure for table `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `consumption` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meter_readings`
--

INSERT INTO `meter_readings` (`id`, `meter_id`, `reading_date`, `consumption`, `created_at`) VALUES
(1, 16, '2026-04-17', 1883.63, '2026-05-11 11:34:51'),
(2, 29, '2026-05-09', 2252.74, '2026-05-11 11:34:51'),
(3, 22, '2026-04-28', 7849.32, '2026-05-11 11:34:51'),
(4, 24, '2026-04-27', 2862.45, '2026-05-11 11:34:51'),
(5, 25, '2026-04-25', 1742.75, '2026-05-11 11:34:51'),
(6, 18, '2026-04-18', 7350.19, '2026-05-11 11:34:51'),
(7, 11, '2026-05-07', 6968.84, '2026-05-11 11:34:51'),
(8, 13, '2026-04-16', 3256.33, '2026-05-11 11:34:51'),
(9, 21, '2026-04-20', 8150.63, '2026-05-11 11:34:51'),
(10, 15, '2026-04-16', 8555.00, '2026-05-11 11:34:51'),
(11, 30, '2026-04-22', 8189.42, '2026-05-11 11:34:51'),
(12, 27, '2026-05-11', 6603.92, '2026-05-11 11:34:51'),
(13, 26, '2026-05-08', 6769.72, '2026-05-11 11:34:51'),
(14, 23, '2026-04-15', 6189.37, '2026-05-11 11:34:51'),
(15, 12, '2026-05-06', 2670.65, '2026-05-11 11:34:51'),
(16, 19, '2026-04-30', 4038.05, '2026-05-11 11:34:51'),
(17, 17, '2026-04-25', 7687.14, '2026-05-11 11:34:51'),
(18, 28, '2026-05-10', 1713.24, '2026-05-11 11:34:51'),
(19, 20, '2026-05-05', 8367.20, '2026-05-11 11:34:51'),
(20, 14, '2026-04-28', 8557.63, '2026-05-11 11:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `method` varchar(50) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_records`
--

CREATE TABLE `production_records` (
  `id` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `source_name` varchar(100) DEFAULT NULL,
  `pumped_volume` decimal(12,2) DEFAULT NULL,
  `operator_name` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_records`
--

INSERT INTO `production_records` (`id`, `production_date`, `source_name`, `pumped_volume`, `operator_name`, `remarks`, `created_at`) VALUES
(1, '2026-05-11', 'Borehole A', 12000.00, 'Operator 1', 'Normal', '2026-05-11 11:35:10'),
(2, '2026-05-11', 'Borehole B', 14500.00, 'Operator 2', 'Stable', '2026-05-11 11:35:10'),
(3, '2026-05-11', 'Treatment Plant', 17000.00, 'Operator 3', 'Peak Supply', '2026-05-11 11:35:10'),
(4, '2026-05-11', 'Reservoir Station', 13000.00, 'Operator 1', 'Normal', '2026-05-11 11:35:10'),
(5, '2026-05-10', 'Borehole A', 11800.00, 'Operator 2', 'Stable', '2026-05-11 11:35:10'),
(6, '2026-05-09', 'Treatment Plant', 16500.00, 'Operator 3', 'High Demand', '2026-05-11 11:35:10');

-- --------------------------------------------------------

--
-- Table structure for table `rationing_schedule`
--

CREATE TABLE `rationing_schedule` (
  `id` int(11) NOT NULL,
  `schedule_date` date DEFAULT NULL,
  `area` varchar(150) DEFAULT NULL,
  `time_slot` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smart_meter_applications`
--

CREATE TABLE `smart_meter_applications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `application_reason` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smart_meter_applications`
--

INSERT INTO `smart_meter_applications` (`id`, `customer_id`, `application_reason`, `status`, `rejection_reason`, `applied_at`, `updated_at`) VALUES
(1, 1, 'fvcrcr', 'Pending', '', '2026-05-01 16:29:54', '2026-05-01 16:30:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer') DEFAULT 'customer',
  `customer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `water_loss_logs`
--

CREATE TABLE `water_loss_logs` (
  `id` int(11) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `loss_percentage` decimal(5,2) DEFAULT NULL,
  `detected_issue` varchar(255) DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`id`, `zone_name`) VALUES
(13, 'Kaiti'),
(10, 'Kasarani'),
(12, 'Kilala'),
(8, 'Kitikyumu'),
(4, 'Kundakindu'),
(5, 'Malawi'),
(7, 'Muambani'),
(9, 'Mukuyuni'),
(11, 'Mwaani'),
(6, 'Return'),
(2, 'Shimo'),
(3, 'Town'),
(1, 'Westlands');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consumption`
--
ALTER TABLE `consumption`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_complaints`
--
ALTER TABLE `customer_complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `infrastructure`
--
ALTER TABLE `infrastructure`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meters`
--
ALTER TABLE `meters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`);

--
-- Indexes for table `meter_activity_logs`
--
ALTER TABLE `meter_activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meter_alert_logs`
--
ALTER TABLE `meter_alert_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`);

--
-- Indexes for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_records`
--
ALTER TABLE `production_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rationing_schedule`
--
ALTER TABLE `rationing_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `smart_meter_applications`
--
ALTER TABLE `smart_meter_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `water_loss_logs`
--
ALTER TABLE `water_loss_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zone_name` (`zone_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consumption`
--
ALTER TABLE `consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_complaints`
--
ALTER TABLE `customer_complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `infrastructure`
--
ALTER TABLE `infrastructure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meters`
--
ALTER TABLE `meters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `meter_activity_logs`
--
ALTER TABLE `meter_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meter_alert_logs`
--
ALTER TABLE `meter_alert_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_records`
--
ALTER TABLE `production_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rationing_schedule`
--
ALTER TABLE `rationing_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smart_meter_applications`
--
ALTER TABLE `smart_meter_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `water_loss_logs`
--
ALTER TABLE `water_loss_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `meter_alert_logs`
--
ALTER TABLE `meter_alert_logs`
  ADD CONSTRAINT `meter_alert_logs_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
