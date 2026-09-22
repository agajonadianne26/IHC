-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 06:38 AM
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
-- Database: `ihc_cms`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `total_contract_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `downpayment_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `installment_terms` int(11) NOT NULL DEFAULT 1,
  `start_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installment_schedules`
--

CREATE TABLE `installment_schedules` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'Pending Payment',
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `or_number` varchar(100) DEFAULT NULL,
  `cashier_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `contract_id` varchar(50) DEFAULT NULL,
  `installment_id` int(11) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `channel` varchar(10) DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `status` varchar(10) DEFAULT 'pending',
  `sent_at` datetime DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `contract_id`, `installment_id`, `client_email`, `channel`, `subject`, `status`, `sent_at`, `error_message`) VALUES
(1, 'CON-7', NULL, 'agajonadianne@gmail.com', 'email', 'Reminder: Payment of ₱1,000,000.00 for CON-7 due in 1 day(s)', 'sent', '2026-09-16 08:18:10', NULL),
(2, '7', NULL, 'agajonadianne@gmail.com', 'email', 'Reminder: Payment of ₱1,000,000.00 for 7 due in 1 day(s)', 'sent', '2026-09-16 09:24:50', NULL),
(3, '7', NULL, 'agajonadianne@gmail.com', 'email', 'Reminder: Payment of ₱1,000,000.00 for 7 due in 1 day(s)', 'sent', '2026-09-16 09:29:50', NULL),
(4, '7', NULL, 'agajonadianne@gmail.com', 'email', 'Reminder: Payment of ₱1,000,000.00 for 7 due in 1 day(s)', 'sent', '2026-09-16 09:34:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'clerk',
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `name`, `role`, `email`) VALUES
(1, 'Jeremy Cantalejo', 'admin', 'admin@ihc.com'),
(2, 'Ana Reyes', 'clerk', 'ana@ihc.com'),
(3, 'Mark Cruz', 'clerk', 'mark@ihc.com'),
(4, 'Jessica Lim', 'clerk', 'jessica@ihc.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `installment_schedules`
--
ALTER TABLE `installment_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_logs_installment_day` (`installment_id`,`channel`,`status`,`sent_at`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `installment_schedules`
--
ALTER TABLE `installment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
