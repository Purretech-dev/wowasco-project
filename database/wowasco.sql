-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 03:19 PM
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
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`id`, `serial_number`, `zone`, `status`, `created_at`, `national_id`, `customer_phone`, `alternative_phone`, `customer_type`, `customer_name`, `meter_type`, `model`, `installation_date`, `is_deleted`) VALUES
(1, 'NM-465657HU', 'Westlands', 'Active', '2026-04-30 14:42:16', '45675423', '0734455667', '0756342534', 'Commercial', 'James Bond', 'Smart Meter', 'NM6575', '2026-04-30', 1),
(2, 'HJ-7890YT', 'Shimo', 'Active', '2026-04-30 14:43:06', '34455667', '0734455667', '0756436789', 'Residential', 'Jane Patrick', 'Smart Meter', 'HJ564RT', '2026-04-30', 0),
(3, 'DF-GH5600', 'Kasarani', 'Active', '2026-04-30 14:43:53', '23234567', '073445342546', '0756436789', 'Residential', 'faith john', 'Smart Meter', 'GHY789', '2026-04-24', 0),
(4, 'JKH-6754RT', 'Kundakindu', 'Active', '2026-05-05 09:29:01', '12233423', '0713243524', '0713243524', 'Domestic', 'jane Jacks', 'Smart Meter', 'JKH6785', '2026-05-01', 0),
(5, 'HJN-5354TY', 'Town', 'Active', '2026-05-05 09:31:53', '12233445', '0735243534', '0724354645', 'Commercial', 'peter pats', 'Smart Meter', 'HJN5354', '2026-05-04', 0),
(6, 'HJK-46455TG', 'Muambani', 'Active', '2026-05-05 09:32:52', '2334354', '0723344556', '0724354657', 'Domestic', 'Denis John', 'Smart Meter', 'HJK4645', '2026-04-29', 1),
(7, 'HBN-909YU', 'Unoa', 'Active', '2026-05-05 09:33:48', '24354657', '072435465', '0724354657', 'Domestic', 'Jane peter', 'Smart Meter', 'HBN909', '2026-04-30', 0);

-- --------------------------------------------------------

--
-- Table structure for table `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `reading_value` decimal(10,2) NOT NULL,
  `reading_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meter_readings`
--

INSERT INTO `meter_readings` (`id`, `meter_id`, `reading_value`, `reading_date`, `created_at`) VALUES
(1, 3, 188.00, '2026-04-30 16:46:20', '2026-04-30 14:46:20'),
(2, 3, 325.00, '2026-04-29 16:46:20', '2026-04-30 14:46:20'),
(3, 3, 256.00, '2026-04-28 16:46:20', '2026-04-30 14:46:20'),
(4, 3, 326.00, '2026-04-27 16:46:20', '2026-04-30 14:46:20'),
(5, 3, 357.00, '2026-04-26 16:46:20', '2026-04-30 14:46:20'),
(6, 3, 468.00, '2026-04-25 16:46:20', '2026-04-30 14:46:20'),
(7, 3, 209.00, '2026-04-24 16:46:20', '2026-04-30 14:46:20'),
(8, 2, 240.00, '2026-04-30 16:46:20', '2026-04-30 14:46:20'),
(9, 2, 295.00, '2026-04-29 16:46:20', '2026-04-30 14:46:20'),
(10, 2, 224.00, '2026-04-28 16:46:20', '2026-04-30 14:46:20'),
(11, 2, 341.00, '2026-04-27 16:46:20', '2026-04-30 14:46:20'),
(12, 2, 158.00, '2026-04-26 16:46:20', '2026-04-30 14:46:20'),
(13, 2, 388.00, '2026-04-25 16:46:20', '2026-04-30 14:46:20'),
(14, 2, 210.00, '2026-04-24 16:46:20', '2026-04-30 14:46:20'),
(15, 1, 58.00, '2026-04-30 16:46:20', '2026-04-30 14:46:20'),
(16, 1, 272.00, '2026-04-29 16:46:20', '2026-04-30 14:46:20'),
(17, 1, 96.00, '2026-04-28 16:46:20', '2026-04-30 14:46:20'),
(18, 1, 228.00, '2026-04-27 16:46:20', '2026-04-30 14:46:20'),
(19, 1, 434.00, '2026-04-26 16:46:20', '2026-04-30 14:46:20'),
(20, 1, 95.00, '2026-04-25 16:46:20', '2026-04-30 14:46:20'),
(21, 1, 368.00, '2026-04-24 16:46:20', '2026-04-30 14:46:20'),
(22, 7, 152.00, '2026-05-05 13:29:16', '2026-05-05 11:29:16'),
(23, 7, 195.00, '2026-05-04 13:29:16', '2026-05-05 11:29:16'),
(24, 7, 380.00, '2026-05-03 13:29:16', '2026-05-05 11:29:16'),
(25, 7, 82.00, '2026-05-02 13:29:16', '2026-05-05 11:29:16'),
(26, 7, 123.00, '2026-05-01 13:29:16', '2026-05-05 11:29:16'),
(27, 7, 71.00, '2026-04-30 13:29:16', '2026-05-05 11:29:16'),
(28, 7, 443.00, '2026-04-29 13:29:16', '2026-05-05 11:29:16'),
(29, 6, 143.00, '2026-05-05 13:29:16', '2026-05-05 11:29:16'),
(30, 6, 127.00, '2026-05-04 13:29:16', '2026-05-05 11:29:16'),
(31, 6, 428.00, '2026-05-03 13:29:16', '2026-05-05 11:29:16'),
(32, 6, 232.00, '2026-05-02 13:29:16', '2026-05-05 11:29:16'),
(33, 6, 421.00, '2026-05-01 13:29:16', '2026-05-05 11:29:16'),
(34, 6, 494.00, '2026-04-30 13:29:16', '2026-05-05 11:29:16'),
(35, 6, 97.00, '2026-04-29 13:29:16', '2026-05-05 11:29:16'),
(36, 5, 331.00, '2026-05-05 13:29:16', '2026-05-05 11:29:16'),
(37, 5, 279.00, '2026-05-04 13:29:16', '2026-05-05 11:29:16'),
(38, 5, 189.00, '2026-05-03 13:29:16', '2026-05-05 11:29:16'),
(39, 5, 207.00, '2026-05-02 13:29:16', '2026-05-05 11:29:16'),
(40, 5, 101.00, '2026-05-01 13:29:16', '2026-05-05 11:29:16'),
(41, 5, 243.00, '2026-04-30 13:29:16', '2026-05-05 11:29:16'),
(42, 5, 148.00, '2026-04-29 13:29:16', '2026-05-05 11:29:16'),
(43, 4, 158.00, '2026-05-05 13:29:16', '2026-05-05 11:29:16'),
(44, 4, 274.00, '2026-05-04 13:29:16', '2026-05-05 11:29:16'),
(45, 4, 381.00, '2026-05-03 13:29:16', '2026-05-05 11:29:16'),
(46, 4, 235.00, '2026-05-02 13:29:16', '2026-05-05 11:29:16'),
(47, 4, 265.00, '2026-05-01 13:29:16', '2026-05-05 11:29:16'),
(48, 4, 411.00, '2026-04-30 13:29:16', '2026-05-05 11:29:16'),
(49, 4, 113.00, '2026-04-29 13:29:16', '2026-05-05 11:29:16');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

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
