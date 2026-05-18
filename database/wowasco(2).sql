-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2026 at 03:52 PM
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
  `is_deleted` tinyint(4) DEFAULT 0,
  `is_deactivated` tinyint(1) DEFAULT 0,
  `model` varchar(255) DEFAULT NULL,
  `asset_subtype` varchar(100) DEFAULT NULL,
  `number_plate` varchar(50) DEFAULT NULL,
  `date_purchased` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_name`, `asset_type`, `subtype`, `serial_number`, `location`, `purchase_date`, `date_added`, `status`, `asset_value`, `depreciated_value`, `net_value`, `created_at`, `is_deleted`, `is_deactivated`, `model`, `asset_subtype`, `number_plate`, `date_purchased`) VALUES
(1, 'smart meter', 'Smart Meter', 'Fixed Asset', 'NM-465657HU', 'Westlands', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:50:48', 0, 1, NULL, NULL, NULL, NULL),
(2, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:54:42', 0, 1, NULL, NULL, NULL, NULL),
(3, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:56:13', 0, 1, NULL, NULL, NULL, NULL),
(4, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 14:58:00', 0, 1, NULL, NULL, NULL, NULL),
(5, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:03:51', 0, 1, NULL, NULL, NULL, NULL),
(6, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:12:50', 0, 0, NULL, NULL, NULL, NULL),
(7, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:16:50', 0, 0, NULL, NULL, NULL, NULL),
(8, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7890YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:21:17', 0, 0, NULL, NULL, NULL, NULL),
(9, 'smart meter', 'Smart Meter', 'Fixed Asset', 'HJ-7234YT', 'Shimo', '2026-04-30', '2026-04-30', 'Active', 70000.00, 0.00, 70000.00, '2026-04-30 15:23:52', 1, 0, 'HJ723Y', 'Fixed', '', '0000-00-00'),
(10, 'motor vehicle', 'Field', NULL, 'NMB786YH', 'Wote', NULL, NULL, 'Under Maintenance', 2500000.00, 0.00, 2500000.00, '2026-05-13 07:50:37', 0, 0, 'Ford F-150', 'Fixed', 'KCL789T', '2026-04-28'),
(11, 'motor vehicle', 'Field', NULL, 'GH675TY', 'Wote', NULL, NULL, 'Operational', 3600000.00, 0.00, 3600000.00, '2026-05-13 08:55:41', 0, 0, 'ford ranger 678', 'Fixed', '', '2026-05-13'),
(12, 'smart meter', 'Office', NULL, 'WM-143474', 'Kundakindu', NULL, NULL, 'Active', 80000.00, 0.00, 80000.00, '2026-05-13 09:01:53', 0, 0, 'WM143M', 'Fixed', '', '2026-05-12'),
(13, 'smart meter', 'Field', NULL, 'WM-204580', 'Town', NULL, NULL, 'Active', 80000.00, 0.00, 80000.00, '2026-05-13 09:10:00', 0, 0, 'WM204M', 'Fixed', '', '2026-05-14'),
(14, 'smart meter', 'Field', NULL, 'WM-381305', 'Kilala', NULL, NULL, 'Active', 75000.00, 0.00, 75000.00, '2026-05-13 09:10:47', 0, 0, 'WM381U', 'Fixed', '', '2026-04-28'),
(15, 'laptop', 'Office', NULL, 'GHT6775TY', 'wote office', NULL, NULL, 'Operational', 250000.00, 0.00, 250000.00, '2026-05-13 09:11:41', 0, 0, 'HP Elite Book', 'Hardware', '', '2026-04-30'),
(16, 'motor vehicle', 'Office', NULL, 'NBH788YY', 'Wote', NULL, NULL, 'Operational', 4500000.00, 0.00, 4500000.00, '2026-05-13 09:13:25', 0, 0, 'Toyota Hillux 450', 'Fixed', 'KCJ570H', '2026-04-26');

-- --------------------------------------------------------

--
-- Table structure for table `asset_audit_logs`
--

CREATE TABLE `asset_audit_logs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `action_by` varchar(100) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance`
--

CREATE TABLE `asset_maintenance` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `asset_name` varchar(255) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `asset_type` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `maintenance_type` varchar(100) DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `priority` varchar(50) DEFAULT NULL,
  `reported_by` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `date_reported` date DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `parts_cost` decimal(12,2) DEFAULT 0.00,
  `labour_cost` decimal(12,2) DEFAULT 0.00,
  `vendor_cost` decimal(12,2) DEFAULT 0.00,
  `actual_cost` decimal(12,2) DEFAULT 0.00,
  `downtime_hours` decimal(10,2) DEFAULT 0.00,
  `status` varchar(100) DEFAULT 'Pending',
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_maintenance`
--

INSERT INTO `asset_maintenance` (`id`, `asset_id`, `asset_name`, `serial_number`, `asset_type`, `model`, `location`, `maintenance_type`, `issue_description`, `priority`, `reported_by`, `assigned_to`, `vendor_name`, `date_reported`, `expected_completion_date`, `actual_completion_date`, `estimated_cost`, `parts_cost`, `labour_cost`, `vendor_cost`, `actual_cost`, `downtime_hours`, `status`, `resolution_notes`, `created_at`) VALUES
(1, 10, 'motor vehicle', 'NMB786YH', 'Field', 'Ford F-150', 'Wote', 'Inspection', 'Vehicle requires immediate inspection', 'Critical', 'James Bond', 'Peter Mwau', 'Alex Garage', '2026-05-14', '2026-05-30', '0000-00-00', 50000.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 'Approved', 'Approved proceed with maintenance', '2026-05-14 07:18:27');

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance_parts`
--

CREATE TABLE `asset_maintenance_parts` (
  `id` int(11) NOT NULL,
  `maintenance_id` int(11) DEFAULT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_maintenance_parts`
--

INSERT INTO `asset_maintenance_parts` (`id`, `maintenance_id`, `part_name`, `quantity`, `unit_cost`, `total_cost`) VALUES
(1, 1, 'CV_joint', 1, 10000.00, 10000.00),
(2, 1, 'CV_joint', 1, 10000.00, 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance_schedule`
--

CREATE TABLE `asset_maintenance_schedule` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `asset_name` varchar(255) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `assigned_to` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT 'Active',
  `maintenance_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset_maintenance_schedule`
--

INSERT INTO `asset_maintenance_schedule` (`id`, `asset_id`, `asset_name`, `serial_number`, `frequency`, `last_service_date`, `next_service_date`, `assigned_to`, `status`, `maintenance_type`, `notes`) VALUES
(1, 10, 'motor vehicle', 'NMB786YH', 'Yearly', '2026-04-26', '2027-04-26', 'Cate John', 'Active', NULL, 'Scheduled maintenance');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `bill_month` varchar(20) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `serial_number`, `customer_id`, `bill_month`, `amount`, `status`, `created_at`) VALUES
(1, 'WM-130043', 16, '2026-05', 1040.26, 'Pending', '2026-05-14 08:34:12'),
(2, 'WM-143474', 29, '2026-05', 3580.64, 'Pending', '2026-05-14 08:34:12'),
(3, 'WM-168753', 22, '2026-05', 2282.41, 'Pending', '2026-05-14 08:34:12'),
(4, 'WM-204580', 24, '2026-05', 4170.14, 'Pending', '2026-05-14 08:34:12'),
(5, 'WM-306581', 25, '2026-05', 1503.47, 'Pending', '2026-05-14 08:34:12'),
(6, 'WM-318189', 18, '2026-05', 2506.93, 'Pending', '2026-05-14 08:34:12'),
(7, 'WM-335358', 11, '2026-05', 3524.26, 'Pending', '2026-05-14 08:34:12'),
(8, 'WM-344352', 13, '2026-05', 1600.49, 'Pending', '2026-05-14 08:34:12'),
(9, 'WM-351757', 21, '2026-05', 929.68, 'Pending', '2026-05-14 08:34:12'),
(10, 'WM-381305', 15, '2026-05', 3346.92, 'Pending', '2026-05-14 08:34:12'),
(11, 'WM-518823', 30, '2026-05', 1445.57, 'Pending', '2026-05-14 08:34:12'),
(12, 'WM-584445', 27, '2026-05', 687.07, 'Pending', '2026-05-14 08:34:12'),
(13, 'WM-614459', 26, '2026-05', 2598.65, 'Pending', '2026-05-14 08:34:12'),
(14, 'WM-631195', 23, '2026-05', 2432.06, 'Pending', '2026-05-14 08:34:12'),
(15, 'WM-766394', 12, '2026-05', 3864.34, 'Pending', '2026-05-14 08:34:12'),
(16, 'WM-865070', 19, '2026-05', 3525.50, 'Pending', '2026-05-14 08:34:12'),
(17, 'WM-887215', 17, '2026-05', 1534.50, 'Pending', '2026-05-14 08:34:12'),
(18, 'WM-899869', 28, '2026-05', 595.98, 'Pending', '2026-05-14 08:34:12'),
(19, 'WM-908885', 20, '2026-05', 1876.39, 'Pending', '2026-05-14 08:34:12'),
(20, 'WM-934084', 14, '2026-05', 3094.04, 'Pending', '2026-05-14 08:34:12'),
(21, 'WM-9908YT', 42, '2026-05', 1341.03, 'Pending', '2026-05-14 08:34:12');

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
-- Table structure for table `customer_case_updates`
--

CREATE TABLE `customer_case_updates` (
  `id` int(11) NOT NULL,
  `case_type` varchar(50) DEFAULT NULL,
  `case_id` int(11) DEFAULT NULL,
  `action_taken` varchar(100) DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `staff_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_case_updates`
--

INSERT INTO `customer_case_updates` (`id`, `case_type`, `case_id`, `action_taken`, `old_status`, `new_status`, `staff_name`, `notes`, `created_at`) VALUES
(1, 'Meter Application', 1, 'Application Updated', 'Pending', 'Pending', 'Ben Paul', 'Ensure meter is installed at that date', '2026-05-14 08:55:58'),
(2, 'Enquiry', 1, 'Enquiry Responded', 'Pending', 'Submitted', 'John Cate', 'Meter to be installed on 15th May 2026', '2026-05-14 08:58:35'),
(3, 'Complaint', 1, 'Complaint Updated', 'Pending', 'Escalated', 'John Cate', 'Purchase in process', '2026-05-14 09:02:58'),
(4, 'Meter Application', 1, 'Application Updated', 'Pending', 'Pending', '', 'Ensure meter is installed at that date', '2026-05-18 07:48:33'),
(5, 'Enquiry', 1, 'Enquiry Responded', 'Submitted', 'Submitted', '', 'Meter to be installed on 15th May 2026', '2026-05-18 07:49:33'),
(6, 'Complaint', 1, 'Complaint Updated', 'Escalated', 'Escalated', '', 'Purchase in process', '2026-05-18 07:50:18'),
(7, 'Meter Application', 1, 'Application Updated', 'Pending', 'Pending', '', 'Ensure meter is installed at that date', '2026-05-18 07:57:04'),
(8, 'Meter Application', 1, 'Application Updated', 'Pending', 'Assigned', 'John Doe', 'Ensure meter is installed at that date', '2026-05-18 07:57:57'),
(9, 'Enquiry', 1, 'Enquiry Responded', 'Submitted', 'Submitted', '', 'Meter to be installed on 15th May 2026', '2026-05-18 07:58:13'),
(10, 'Complaint', 1, 'Complaint Updated', 'Escalated', 'Escalated', '', 'Purchase in process', '2026-05-18 07:58:29'),
(11, 'Enquiry', 1, 'Enquiry Responded', 'Submitted', 'Submitted', 'Jane Dave', 'Meter to be installed on 15th May 2026', '2026-05-18 08:05:01'),
(12, 'Complaint', 1, 'Complaint Updated', 'Escalated', 'Escalated', 'Jane Dave', 'Purchase in process', '2026-05-18 08:05:48'),
(13, 'Meter Application', 1, 'Application Updated', 'Assigned', 'Assigned', '', 'Ensure meter is installed at that date', '2026-05-18 08:07:01'),
(14, 'Meter Application', 1, 'Application Updated', 'Assigned', 'Assigned', 'Cate Mbui', 'Ensure meter is installed at that date\nAssigned meter serial: NMU-7686TY', '2026-05-18 13:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `customer_complaints`
--

CREATE TABLE `customer_complaints` (
  `id` int(11) NOT NULL,
  `complaint_ref` varchar(100) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `meter_serial` varchar(100) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `complaint_type` varchar(150) DEFAULT NULL,
  `priority` varchar(50) DEFAULT NULL,
  `assigned_staff` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `response` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `escalation_reason` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_complaints`
--

INSERT INTO `customer_complaints` (`id`, `complaint_ref`, `customer_name`, `contact`, `meter_serial`, `zone`, `complaint_type`, `priority`, `assigned_staff`, `description`, `status`, `response`, `remarks`, `created_at`, `due_date`, `resolution_notes`, `escalation_reason`, `updated_at`) VALUES
(1, 'CMP-20260514-5154', 'Johnathan Rungu', '0724344556', 'WM-557776Y', 'westlands', 'Other', 'Medium', 'John Ben', 'Meter connection not done', 'Escalated', 'Purchase in process', NULL, '2026-05-14 08:02:28', NULL, 'To be avilable next week', 'procurement', '2026-05-18 07:58:29');

-- --------------------------------------------------------

--
-- Table structure for table `customer_enquiries`
--

CREATE TABLE `customer_enquiries` (
  `id` int(11) NOT NULL,
  `enquiry_ref` varchar(100) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `enquiry_type` varchar(150) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `response` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_staff` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_enquiries`
--

INSERT INTO `customer_enquiries` (`id`, `enquiry_ref`, `customer_name`, `contact`, `email`, `enquiry_type`, `subject`, `message`, `status`, `response`, `remarks`, `created_at`, `assigned_staff`, `updated_at`) VALUES
(1, 'ENQ-20260514-5286', 'Johnathan Rungu', '0724344556', 'John@gmail.com', 'Meter Application', 'Meter connection', 'Following up on meter application', 'Submitted', 'Meter to be installed on 15th May 2026', NULL, '2026-05-14 08:01:55', 'Peter Ben', '2026-05-14 08:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `customer_meter_applications`
--

CREATE TABLE `customer_meter_applications` (
  `id` int(11) NOT NULL,
  `application_ref` varchar(50) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `location_address` varchar(255) DEFAULT NULL,
  `customer_type` varchar(50) DEFAULT NULL,
  `connection_type` varchar(50) DEFAULT NULL,
  `preferred_meter_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Submitted',
  `remarks` text DEFAULT NULL,
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
  `last_service_date` date DEFAULT NULL,
  `is_deactivated` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`id`, `serial_number`, `zone`, `status`, `created_at`, `national_id`, `customer_phone`, `alternative_phone`, `customer_type`, `customer_name`, `meter_type`, `model`, `installation_date`, `is_deleted`, `maintenance_status`, `last_reading_date`, `battery_level`, `signal_strength`, `is_online`, `technician_assigned`, `alert_acknowledged`, `last_service_date`, `is_deactivated`) VALUES
(11, 'WM-335358', 'Kilala', 'Active', '2026-05-11 11:33:30', '34455657', '0723344556', '0724354646', 'Domestic', 'Grace Mwikali', 'Smart Meter', 'WM33M', '2026-05-01', 0, 'Good', NULL, 43, 54, 1, NULL, 0, NULL, 0),
(12, 'WM-766394', 'Westlands', 'Active', '2026-05-11 11:33:30', '23344556', '0735465657', '0735465758', 'Residential', 'John Mutua', 'Smart Meter', 'WM766T', '2026-05-05', 0, 'Good', NULL, 53, 82, 1, NULL, 0, NULL, 0),
(13, 'WM-344352', 'Town', 'Active', '2026-05-11 11:33:30', '34455667', '0734455676', '07254657', 'Commercial', 'John Mutua', 'Smart Meter', 'WM344U', '2026-05-05', 0, 'Good', NULL, 41, 40, 1, NULL, 0, NULL, 0),
(14, 'WM-934084', 'Kasarani', 'Active', '2026-05-11 11:33:30', '23344556', '0735464577', '0735465758', 'Domestic', 'Joyca Hotel', 'Smart Meter', 'WM934M', '2026-05-05', 0, 'Needs Maintenance', NULL, 69, 76, 1, NULL, 0, NULL, 0),
(15, 'WM-381305', 'Kilala', 'Active', '2026-05-11 11:33:30', '23344556', '0734455667', '0735465768', 'Commercial', 'Mtito Traders', 'Smart Meter', 'WM381U', '2026-04-28', 0, 'Good', NULL, 74, 60, 1, NULL, 0, NULL, 0),
(16, 'WM-130043', 'Town', 'Active', '2026-05-11 11:33:30', '34455667', '0734455667', '0735465768', 'Commercial', 'Makindu Hospital', 'Smart Meter', 'WM130O', '2026-04-27', 0, 'Good', NULL, 67, 67, 1, NULL, 0, NULL, 0),
(17, 'WM-887215', 'Town', 'Active', '2026-05-11 11:33:30', '23344556', '0724354657', '0724354657', 'Commercial', 'Wote Market', 'Smart Meter', 'WM887K', '2026-04-26', 0, 'Needs Maintenance', NULL, 46, 73, 1, NULL, 0, NULL, 0),
(18, 'WM-318189', 'Kundakindu', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Green Farm', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 87, 61, 1, NULL, 0, NULL, 0),
(19, 'WM-865070', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Green Farm', '', '', '0000-00-00', 0, 'Good', NULL, 74, 88, 1, NULL, 0, NULL, 0),
(20, 'WM-908885', 'Muambani', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'John Mutua', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 97, 82, 1, NULL, 0, NULL, 0),
(21, 'WM-351757', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'Green Farm', '', '', '0000-00-00', 0, 'Good', NULL, 72, 33, 1, NULL, 0, NULL, 0),
(22, 'WM-168753', 'Malawi', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Wote Market', '', '', '0000-00-00', 0, 'Good', NULL, 75, 76, 1, NULL, 0, NULL, 0),
(23, 'WM-631195', 'Kilala', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Commercial', 'Wote Market', '', '', '0000-00-00', 0, 'Good', NULL, 58, 47, 1, NULL, 0, NULL, 0),
(24, 'WM-204580', 'Town', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Wote Market', '', '', '0000-00-00', 0, 'Needs Maintenance', NULL, 83, 46, 1, NULL, 0, NULL, 0),
(25, 'WM-306581', 'Return', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Grace Mwikali', '', '', '0000-00-00', 0, 'Good', NULL, 89, 45, 1, NULL, 0, NULL, 0),
(26, 'WM-614459', 'Kitikyumu', 'Inactive', '2026-05-11 11:33:30', '', '', NULL, 'Industrial', 'Mtito Traders', '', '', '0000-00-00', 0, 'Good', NULL, 72, 76, 1, NULL, 0, NULL, 1),
(27, 'WM-584445', 'Mukuyuni', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'Mutinda Hotel', 'Smart Meter', 'WM544W', '2026-04-28', 0, 'Needs Maintenance', NULL, 47, 32, 1, NULL, 0, NULL, 0),
(28, 'WM-899869', 'Kilala', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Institution', 'John Mutua', '', '', '0000-00-00', 0, 'Good', NULL, 49, 98, 1, NULL, 0, NULL, 1),
(29, 'WM-143474', 'Kundakindu', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Domestic', 'John Mutua', 'Smart Meter', 'WM143M', '2026-05-12', 0, 'Good', NULL, 87, 91, 1, NULL, 0, NULL, 0),
(30, 'WM-518823', 'Town', 'Active', '2026-05-11 11:33:30', '', '', NULL, 'Residential', 'Makueni Boys School', 'Smart Meter', 'WM518M', '2026-05-04', 0, 'Good', NULL, 75, 91, 1, NULL, 0, NULL, 1),
(42, 'WM-9908YT', 'Unoa', 'Active', '2026-05-12 10:45:33', '34455667', '0724352435', '0714352435', 'Domestic', 'Joyce John', 'Smart Meter', 'WM990Y', '2026-04-29', 0, 'Good', NULL, 100, 100, 1, NULL, 0, NULL, 0),
(43, 'NMU-7686TY', 'westlands', 'Assigned', '2026-05-18 13:39:34', '34455667', '0724344556', '0734455667', 'Residential', 'Johnathan Rungu', 'Smart Meter', 'NMU-768', '2026-05-15', 0, 'Good', NULL, 100, 100, 1, NULL, 0, NULL, 0);

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
-- Table structure for table `meter_applications`
--

CREATE TABLE `meter_applications` (
  `id` int(11) NOT NULL,
  `application_ref` varchar(100) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `meter_type` varchar(100) DEFAULT NULL,
  `customer_type` varchar(100) DEFAULT NULL,
  `national_id_copy` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `response` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_staff` varchar(100) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `meter_serial` varchar(100) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `serial_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meter_applications`
--

INSERT INTO `meter_applications` (`id`, `application_ref`, `customer_name`, `contact`, `id_number`, `zone`, `meter_type`, `customer_type`, `national_id_copy`, `status`, `response`, `remarks`, `created_at`, `assigned_staff`, `rejection_reason`, `meter_serial`, `installation_date`, `reviewed_at`, `updated_at`, `serial_number`) VALUES
(1, 'MTRAPP-20260514-3039', 'Johnathan Rungu', '0724344556', '34455667', 'westlands', 'Smart Meter', 'Residential', 'uploads/customer_ids/ID_1778745679_4758.png', 'Assigned', 'Ensure meter is installed at that date', NULL, '2026-05-14 08:01:19', 'Peter Mbui', NULL, 'NMU-7686TY', '2026-05-15', '2026-05-18 16:39:34', '2026-05-18 13:39:34', NULL);

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
-- Table structure for table `pumped_volume_entries`
--

CREATE TABLE `pumped_volume_entries` (
  `id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `pumped_date` date NOT NULL,
  `volume_m3` decimal(12,2) NOT NULL DEFAULT 0.00,
  `source_type` varchar(50) DEFAULT 'Manual Entry',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pumped_volume_entries`
--

INSERT INTO `pumped_volume_entries` (`id`, `meter_id`, `pumped_date`, `volume_m3`, `source_type`, `remarks`, `created_at`) VALUES
(1, 16, '2026-04-17', 1883.63, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(2, 29, '2026-05-09', 2252.74, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(3, 22, '2026-04-28', 7849.32, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(4, 24, '2026-04-27', 2862.45, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(5, 25, '2026-04-25', 1742.75, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(6, 18, '2026-04-18', 7350.19, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(7, 11, '2026-05-07', 6968.84, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(8, 13, '2026-04-16', 3256.33, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(9, 21, '2026-04-20', 8150.63, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(10, 15, '2026-04-16', 8555.00, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(11, 30, '2026-04-22', 8189.42, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(12, 27, '2026-05-11', 6603.92, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(13, 26, '2026-05-08', 6769.72, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(14, 23, '2026-04-15', 6189.37, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(15, 12, '2026-05-06', 2670.65, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(16, 19, '2026-04-30', 4038.05, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(17, 17, '2026-04-25', 7687.14, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(18, 28, '2026-05-10', 1713.24, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(19, 20, '2026-05-05', 8367.20, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(20, 14, '2026-04-28', 8557.63, 'Meter Reading', 'Auto-fed from meter readings', '2026-05-14 06:38:05'),
(32, 16, '2026-05-14', 5413.00, 'Meter Reading', '', '2026-05-14 06:39:41');

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
-- Table structure for table `water_rationing_schedule`
--

CREATE TABLE `water_rationing_schedule` (
  `id` int(11) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `rationing_day` varchar(50) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `notice` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source` varchar(150) DEFAULT NULL,
  `notice_type` varchar(100) DEFAULT 'Rationing',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_rationing_schedule`
--

INSERT INTO `water_rationing_schedule` (`id`, `zone`, `rationing_day`, `start_time`, `end_time`, `notice`, `status`, `created_at`, `source`, `notice_type`, `updated_at`) VALUES
(1, 'Westlands', 'Monday', '08:00:00', '18:00:00', 'Supply will be done at this time', 'Active', '2026-05-14 09:07:03', '', 'Rationing', '2026-05-18 07:50:59');

-- --------------------------------------------------------

--
-- Table structure for table `water_sources`
--

CREATE TABLE `water_sources` (
  `id` int(11) NOT NULL,
  `source_name` varchar(150) NOT NULL,
  `source_type` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(150) NOT NULL,
  `zone_code` varchar(50) DEFAULT NULL,
  `source_name` varchar(150) DEFAULT NULL,
  `officer_in_charge` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`id`, `zone_name`, `zone_code`, `source_name`, `officer_in_charge`, `status`, `notes`, `created_at`) VALUES
(1, 'Westlands', '001', 'Kaiti II', 'Paul John', 'Active', 'Supply in process', '2026-05-14 09:09:32');

-- --------------------------------------------------------

--
-- Table structure for table `zone_activity_log`
--

CREATE TABLE `zone_activity_log` (
  `id` int(11) NOT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `staff_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zone_activity_log`
--

INSERT INTO `zone_activity_log` (`id`, `action_type`, `description`, `staff_name`, `created_at`) VALUES
(1, 'Zone Saved', 'Zone saved or updated: Westlands', '', '2026-05-14 09:09:32'),
(2, 'Zone Status Updated', 'Zone ID 1 updated to Active', 'Zone Manager', '2026-05-14 09:09:38');

-- --------------------------------------------------------

--
-- Table structure for table `zone_maintenance`
--

CREATE TABLE `zone_maintenance` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `issue_title` varchar(150) DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `maintenance_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Open',
  `assigned_team` varchar(150) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_supply_schedule`
--

CREATE TABLE `zone_supply_schedule` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `supply_day` varchar(50) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_audit_logs`
--
ALTER TABLE `asset_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_maintenance_parts`
--
ALTER TABLE `asset_maintenance_parts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_maintenance_schedule`
--
ALTER TABLE `asset_maintenance_schedule`
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
-- Indexes for table `customer_case_updates`
--
ALTER TABLE `customer_case_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_complaints`
--
ALTER TABLE `customer_complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_enquiries`
--
ALTER TABLE `customer_enquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_meter_applications`
--
ALTER TABLE `customer_meter_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_ref` (`application_ref`);

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
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD UNIQUE KEY `unique_meter_serial` (`serial_number`);

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
-- Indexes for table `meter_applications`
--
ALTER TABLE `meter_applications`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `pumped_volume_entries`
--
ALTER TABLE `pumped_volume_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`),
  ADD KEY `pumped_date` (`pumped_date`);

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
-- Indexes for table `water_rationing_schedule`
--
ALTER TABLE `water_rationing_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `water_sources`
--
ALTER TABLE `water_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_name` (`source_name`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zone_name` (`zone_name`);

--
-- Indexes for table `zone_activity_log`
--
ALTER TABLE `zone_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zone_maintenance`
--
ALTER TABLE `zone_maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zone_supply_schedule`
--
ALTER TABLE `zone_supply_schedule`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `asset_audit_logs`
--
ALTER TABLE `asset_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `asset_maintenance_parts`
--
ALTER TABLE `asset_maintenance_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `asset_maintenance_schedule`
--
ALTER TABLE `asset_maintenance_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
-- AUTO_INCREMENT for table `customer_case_updates`
--
ALTER TABLE `customer_case_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `customer_complaints`
--
ALTER TABLE `customer_complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_enquiries`
--
ALTER TABLE `customer_enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_meter_applications`
--
ALTER TABLE `customer_meter_applications`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `meter_activity_logs`
--
ALTER TABLE `meter_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meter_alert_logs`
--
ALTER TABLE `meter_alert_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `meter_applications`
--
ALTER TABLE `meter_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
-- AUTO_INCREMENT for table `pumped_volume_entries`
--
ALTER TABLE `pumped_volume_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

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
-- AUTO_INCREMENT for table `water_rationing_schedule`
--
ALTER TABLE `water_rationing_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `water_sources`
--
ALTER TABLE `water_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `zone_activity_log`
--
ALTER TABLE `zone_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `zone_maintenance`
--
ALTER TABLE `zone_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zone_supply_schedule`
--
ALTER TABLE `zone_supply_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
