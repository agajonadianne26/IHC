-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 09, 2026 at 02:13 PM
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
-- Database: `ihc`
--

-- --------------------------------------------------------

--
-- Table structure for table `additional_charges`
--

CREATE TABLE `additional_charges` (
  `id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(11) NOT NULL,
  `charge_type` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `charge_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `or_number` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `additional_equity_payments`
--

CREATE TABLE `additional_equity_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `installment_no` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `or_number` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'UNPAID',
  `remarks` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(80) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `holding_fee_id` int(11) DEFAULT NULL,
  `reservation_fee_id` int(11) DEFAULT NULL,
  `property_unit_id` int(11) DEFAULT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `actor_id` varchar(100) DEFAULT NULL,
  `actor_name` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `action`, `contract_id`, `holding_fee_id`, `reservation_fee_id`, `property_unit_id`, `from_status`, `to_status`, `actor_id`, `actor_name`, `details`, `created_at`) VALUES
(13, 'payment.created', 24, NULL, NULL, 22, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-24\",\"amount\":1500000,\"method\":\"Over-the-Counter Cashier\",\"orNumber\":\"OR-DP-2026-00001\",\"paymentKind\":\"downpayment\",\"checkNumber\":null,\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"current\",\"allocations\":[{\"kind\":\"downpayment\",\"no\":null,\"amount\":1500000}],\"allocationSummary\":{\"principalApplied\":1500000,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"current\",\"target\":\"downpayment\"},\"dateCollected\":\"2026-09-27\",\"paymentId\":34}', '2026-09-25 08:02:13'),
(23, 'payment.created', 24, NULL, NULL, 22, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-24\",\"amount\":31250,\"method\":\"Bank Wire \\/ Online Deposit\",\"orNumber\":\"OR-INS-2026-00001\",\"paymentKind\":\"installment\",\"checkNumber\":null,\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"auto\",\"allocations\":[{\"kind\":\"installment\",\"no\":1,\"amount\":31250}],\"allocationSummary\":{\"principalApplied\":31250,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"auto\",\"interestAmount\":0,\"penaltyAmount\":0,\"target\":\"installment\"},\"interestAmount\":0,\"penaltyAmount\":0,\"dateCollected\":\"2026-10-02\",\"paymentId\":45}', '2026-10-02 05:11:50'),
(24, 'payment.created', 45, NULL, NULL, 193, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-45\",\"amount\":300000,\"method\":\"Over-the-Counter Cashier\",\"orNumber\":\"OR-DP-2026-00002\",\"paymentKind\":\"downpayment\",\"checkNumber\":null,\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"auto\",\"allocations\":[{\"kind\":\"downpayment\",\"no\":null,\"amount\":300000}],\"allocationSummary\":{\"principalApplied\":300000,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"auto\",\"interestAmount\":0,\"penaltyAmount\":0,\"target\":\"downpayment\"},\"interestAmount\":0,\"penaltyAmount\":0,\"dateCollected\":\"2026-10-05\",\"paymentId\":46}', '2026-10-02 05:12:54'),
(25, 'payment.created', 45, NULL, NULL, 193, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-45\",\"amount\":144444.44,\"method\":\"GCash \\/ Maya\",\"orNumber\":\"OR-INS-2026-00002\",\"paymentKind\":\"installment\",\"checkNumber\":null,\"externalReference\":\"12314343241344\",\"receiptRequested\":true,\"applicationMode\":\"auto\",\"allocations\":[{\"kind\":\"installment\",\"no\":1,\"amount\":144444.44}],\"allocationSummary\":{\"principalApplied\":144444.44,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"auto\",\"interestAmount\":0,\"penaltyAmount\":0,\"target\":\"installment\"},\"interestAmount\":0,\"penaltyAmount\":0,\"dateCollected\":\"2026-11-04\",\"paymentId\":47}', '2026-10-02 05:17:31'),
(26, 'payment.created', 24, NULL, NULL, 22, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-24\",\"amount\":31250,\"method\":\"Over-the-Counter Cashier\",\"orNumber\":\"OR-INS-2026-00003\",\"paymentKind\":\"installment\",\"checkNumber\":null,\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"auto\",\"allocations\":[{\"kind\":\"installment\",\"no\":2,\"amount\":31250}],\"allocationSummary\":{\"principalApplied\":31250,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"auto\",\"interestAmount\":0,\"penaltyAmount\":0,\"target\":\"installment\"},\"interestAmount\":0,\"penaltyAmount\":0,\"dateCollected\":\"2026-10-23\",\"paymentId\":48}', '2026-10-09 00:26:19'),
(27, 'payment.created', 46, NULL, NULL, 347, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-46\",\"amount\":2000000,\"method\":\"Bank Wire \\/ Online Deposit\",\"orNumber\":\"OR-DP-2026-00003\",\"paymentKind\":\"downpayment\",\"checkNumber\":\"42342343223423424342424\",\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"auto\",\"allocations\":[{\"kind\":\"downpayment\",\"no\":null,\"amount\":2000000}],\"allocationSummary\":{\"principalApplied\":2000000,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"auto\",\"interestAmount\":0,\"penaltyAmount\":0,\"target\":\"downpayment\"},\"interestAmount\":0,\"penaltyAmount\":0,\"dateCollected\":\"2026-10-16\",\"paymentId\":49}', '2026-10-09 00:32:19'),
(28, 'holding_fee.created', NULL, 3, NULL, 394, NULL, 'PENDING', '2', 'Ana Reyes', '{\"amount\":5000,\"unit\":\"Test Village - Block 7 Lot 3\",\"prospectClientId\":1}', '2026-10-09 09:01:32'),
(29, 'reservation_fee.created', NULL, NULL, 1, 394, NULL, 'PAID', '2', 'Ana Reyes', '{\"amount\":25000,\"unit\":\"Test Village - Block 7 Lot 3\",\"prospectClientId\":1}', '2026-10-09 09:01:40'),
(30, 'unit.status_changed', NULL, NULL, 1, 394, 'AVAILABLE', 'RESERVED', '2', 'Ana Reyes', '{\"reason\":\"reservation paid\"}', '2026-10-09 09:01:40'),
(31, 'holding_fee.converted', NULL, 3, 1, 394, 'PAID', 'CONVERTED', '2', 'Ana Reyes', '{\"reservation_id\":1}', '2026-10-09 09:01:40'),
(32, 'holding_fee.created', NULL, 5, NULL, 406, NULL, 'PAID', '2', 'Ana Reyes', '{\"amount\":7500,\"unit\":\"Rose St Phase 2 - Block 9 Lot 11\",\"prospectClientId\":2}', '2026-10-09 09:23:46'),
(33, 'unit.status_changed', NULL, 5, NULL, 406, 'AVAILABLE', 'ON HOLD', '2', 'Ana Reyes', '{\"reason\":\"holding paid\"}', '2026-10-09 09:23:46'),
(34, 'reservation_fee.created', NULL, NULL, 3, 406, NULL, 'PAID', '2', 'Ana Reyes', '{\"amount\":30000,\"unit\":\"Rose St Phase 2 - Block 9 Lot 11\",\"prospectClientId\":2}', '2026-10-09 09:23:59'),
(35, 'unit.status_changed', NULL, NULL, 3, 406, 'ON HOLD', 'RESERVED', '2', 'Ana Reyes', '{\"reason\":\"reservation paid\"}', '2026-10-09 09:23:59'),
(36, 'holding_fee.converted', NULL, 5, 3, 406, 'PAID', 'CONVERTED', '2', 'Ana Reyes', '{\"reservation_id\":3}', '2026-10-09 09:23:59'),
(37, 'holding_fee.created', 49, 7, NULL, 419, NULL, 'PAID', '2', 'Ana Reyes', '{\"amount\":5000,\"unit\":\"Greenfield - Block 1 Lot 5\",\"prospectClientId\":null}', '2026-10-09 09:38:36'),
(38, 'unit.status_changed', 49, 7, NULL, 419, 'AVAILABLE', 'ON HOLD', '2', 'Ana Reyes', '{\"reason\":\"holding paid\"}', '2026-10-09 09:38:36'),
(39, 'holding_fee.created', NULL, 8, NULL, 422, NULL, 'PAID', NULL, NULL, '{\"amount\":4000,\"unit\":\"Sunrise Village - Block 2 Lot 9\",\"prospectClientId\":3}', '2026-10-09 09:38:37'),
(40, 'unit.status_changed', NULL, 8, NULL, 422, 'AVAILABLE', 'ON HOLD', NULL, NULL, '{\"reason\":\"holding paid\"}', '2026-10-09 09:38:37'),
(41, 'reservation_fee.created', 49, NULL, 5, 419, NULL, 'PAID', '2', 'Ana Reyes', '{\"amount\":25000,\"unit\":\"Greenfield - Block 1 Lot 5\",\"prospectClientId\":null}', '2026-10-09 09:38:48'),
(42, 'unit.status_changed', 49, NULL, 5, 419, 'ON HOLD', 'RESERVED', '2', 'Ana Reyes', '{\"reason\":\"reservation paid\"}', '2026-10-09 09:38:48'),
(43, 'holding_fee.converted', 49, 7, 5, 419, 'PAID', 'CONVERTED', '2', 'Ana Reyes', '{\"reservation_id\":5}', '2026-10-09 09:38:48'),
(44, 'unit.status_changed', 24, NULL, NULL, 22, 'AVAILABLE', 'AVAILABLE', '1', 'Jeremy Cantalejo', '{\"reason\":null}', '2026-10-29 01:56:46'),
(45, 'unit.status_changed', 24, NULL, NULL, 22, 'AVAILABLE', 'AVAILABLE', '5', 'Rea Villanueva', '{\"reason\":null}', '2026-10-29 01:56:46'),
(46, 'create_unit', NULL, NULL, NULL, 604, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 28, Lot 10\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"28\",\"lot\":\"10\"}', '2026-11-06 07:25:04'),
(47, 'staff.deleted', NULL, NULL, NULL, NULL, NULL, NULL, '1', 'Jeremy Cantalejo', '{\"name\":\"Mark Cruz\",\"email\":\"mark@ihc.com\"}', '2026-11-06 07:27:32'),
(48, 'staff.deleted', NULL, NULL, NULL, NULL, NULL, NULL, '1', 'Jeremy Cantalejo', '{\"name\":\"Jessica Lim\",\"email\":\"jessica@ihc.com\"}', '2026-11-06 07:27:36'),
(49, 'create_unit', NULL, NULL, NULL, 609, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 17, Lot 9\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"17\",\"lot\":\"9\"}', '2026-11-06 07:40:28'),
(50, 'create_unit', NULL, NULL, NULL, 613, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 27, Lot 6\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"27\",\"lot\":\"6\"}', '2026-11-06 07:41:05'),
(51, 'create_unit', NULL, NULL, NULL, 616, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 29, Lot15\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"29\",\"lot\":\"15\"}', '2026-11-06 07:41:56'),
(52, 'create_unit', NULL, NULL, NULL, 619, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Sto.Tomas, Phase 1, Blk 11, Lot 47\",\"project\":\"Via Verde-Sto.Tomas\",\"phase\":\"1\",\"block\":\"11\",\"lot\":\"47\"}', '2026-11-06 07:43:19'),
(53, 'create_unit', NULL, NULL, NULL, 622, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE TRECE, Phase 2B, Blk 17, lot 11\",\"project\":\"VIA VERDE TRECE\",\"phase\":\"2B\",\"block\":\"17\",\"lot\":\"11\"}', '2026-11-06 07:43:54'),
(54, 'create_unit', NULL, NULL, NULL, 625, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 28, Lot 8\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"28\",\"lot\":\"8\"}', '2026-11-06 07:44:39'),
(55, 'create_unit', NULL, NULL, NULL, 628, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE TRECE, Phase 1, Blk 3, Lot 1\",\"project\":\"VIA VERDE TRECE\",\"phase\":\"1\",\"block\":\"3\",\"lot\":\"1\"}', '2026-11-06 07:45:14'),
(56, 'create_unit', NULL, NULL, NULL, 633, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VHS-Iriga, Blk 8, Lot 36\",\"project\":\"VHS-Iriga\",\"phase\":null,\"block\":\"8\",\"lot\":\"36\"}', '2026-11-06 07:52:10'),
(57, 'unit.status_changed', NULL, NULL, NULL, 633, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 07:52:22'),
(58, 'create_unit', NULL, NULL, NULL, 638, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 8, Lot 26\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"8\",\"lot\":\"26\"}', '2026-11-06 07:53:11'),
(59, 'create_unit', NULL, NULL, NULL, 641, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 8, Lot 27\",\"project\":\"l\",\"phase\":null,\"block\":\"8\",\"lot\":\"27\"}', '2026-11-06 07:53:52'),
(60, 'create_unit', NULL, NULL, NULL, 644, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 1, Blk 5, Lot 16\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"1\",\"block\":\"5\",\"lot\":\"16\"}', '2026-11-06 07:54:35'),
(61, 'create_unit', NULL, NULL, NULL, 647, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRHS-Estanza, Blk 18, Lot 1\",\"project\":\"IRHS-Estanza\",\"phase\":null,\"block\":\"18\",\"lot\":\"1\"}', '2026-11-06 07:55:07'),
(62, 'create_unit', NULL, NULL, NULL, 650, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 28\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"10\",\"lot\":\"28\"}', '2026-11-06 07:55:38'),
(63, 'create_unit', NULL, NULL, NULL, 653, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRS-Sorsogon, Blk 27, Lot 22\",\"project\":\"IRS-Sorsogon\",\"phase\":null,\"block\":\"27\",\"lot\":\"22\"}', '2026-11-06 07:56:13'),
(64, 'create_unit', NULL, NULL, NULL, 656, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRS-Sorsogon, Blk 6, Lot 15\",\"project\":\"IRS-Sorsogon\",\"phase\":null,\"block\":\"6\",\"lot\":\"15\"}', '2026-11-06 07:56:39'),
(65, 'create_unit', NULL, NULL, NULL, 659, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 3, Lot 13\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"3\",\"lot\":\"13\"}', '2026-11-06 07:57:12'),
(66, 'create_unit', NULL, NULL, NULL, 662, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE HEIGHTS, Blk 1, Lot 17\",\"project\":\"VIA VERDE HEIGHTS\",\"phase\":null,\"block\":\"1\",\"lot\":\"17\"}', '2026-11-06 07:58:06'),
(67, 'create_unit', NULL, NULL, NULL, 665, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VHS-Iriga, Blk 1, lot 8\",\"project\":\"VHS-Iriga\",\"phase\":null,\"block\":\"1\",\"lot\":\"8\"}', '2026-11-06 07:58:47'),
(68, 'create_unit', NULL, NULL, NULL, 668, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 33\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"33\"}', '2026-11-06 07:59:20'),
(69, 'create_unit', NULL, NULL, NULL, 671, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Priya Meridien, Phase 1, Blk 7, Lot 51&53\",\"project\":\"Priya Meridien\",\"phase\":\"1\",\"block\":\"7\",\"lot\":\"51 & 53\"}', '2026-11-06 08:00:24'),
(70, 'create_unit', NULL, NULL, NULL, 674, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRS-Sorsogon, Blk 30, Lot 1\",\"project\":\"IRS-Sorsogon\",\"phase\":null,\"block\":\"30\",\"lot\":\"1\"}', '2026-11-06 08:01:00'),
(71, 'create_unit', NULL, NULL, NULL, 677, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 6\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"6\"}', '2026-11-06 08:01:40'),
(72, 'create_unit', NULL, NULL, NULL, 680, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 7\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"7\"}', '2026-11-06 08:02:08'),
(73, 'create_unit', NULL, NULL, NULL, 683, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial South Meadows, Phase 1, Blk 37, Lot 10\",\"project\":\"Imperial South Meadows\",\"phase\":\"1\",\"block\":\"37\",\"lot\":\"10\"}', '2026-11-06 08:03:04'),
(74, 'create_unit', NULL, NULL, NULL, 686, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRHS-Estanza, Blk 27, Lot 33,34\",\"project\":\"IRHS-Estanza\",\"phase\":null,\"block\":\"27\",\"lot\":\"33, 34\"}', '2026-11-06 08:03:46'),
(75, 'create_unit', NULL, NULL, NULL, 689, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 26\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"26\"}', '2026-11-06 08:04:20'),
(76, 'create_unit', NULL, NULL, NULL, 692, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 17\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"17\"}', '2026-11-06 08:06:24'),
(77, 'create_unit', NULL, NULL, NULL, 695, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 1\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"1\"}', '2026-11-06 08:07:26'),
(78, 'create_unit', NULL, NULL, NULL, 698, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE HEIGHTS, Blk 10, Lot 16\",\"project\":\"VIA VERDE HEIGHTS\",\"phase\":null,\"block\":\"10\",\"lot\":\"16\"}', '2026-11-06 08:07:56'),
(79, 'create_unit', NULL, NULL, NULL, 701, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VHS-Iriga, Blk 1, Lot 35\",\"project\":\"VHS-Iriga\",\"phase\":null,\"block\":\"1\",\"lot\":\"35\"}', '2026-11-06 08:09:22'),
(80, 'create_unit', NULL, NULL, NULL, 704, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Rancho Imperial De Silang, Phase 1, Blk 24, Lot 35\",\"project\":\"Rancho Imperial De Silang\",\"phase\":\"1\",\"block\":\"24\",\"lot\":\"35\"}', '2026-11-06 08:10:19'),
(81, 'create_unit', NULL, NULL, NULL, 707, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Tagaytay Meridian, Phase 1, Blk 2, Lot 4\",\"project\":\"Tagaytay Meridian\",\"phase\":\"1\",\"block\":\"2\",\"lot\":\"4\"}', '2026-11-06 08:10:56'),
(82, 'create_unit', NULL, NULL, NULL, 710, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"The Venetto Heights, Phase 1, Blk 7, Lot 7A\",\"project\":\"The Venetto Heights\",\"phase\":\"1\",\"block\":\"7\",\"lot\":\"7A\"}', '2026-11-06 08:11:32'),
(83, 'create_unit', NULL, NULL, NULL, 713, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 34\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"20\",\"lot\":\"34\"}', '2026-11-06 08:12:42'),
(84, 'unit.status_changed', NULL, NULL, NULL, 683, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:14'),
(85, 'unit.status_changed', NULL, NULL, NULL, 647, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:20'),
(86, 'unit.status_changed', NULL, NULL, NULL, 686, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:23'),
(87, 'unit.status_changed', NULL, NULL, NULL, 653, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:29'),
(88, 'unit.status_changed', NULL, NULL, NULL, 653, 'SOLD', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:30'),
(89, 'unit.status_changed', NULL, NULL, NULL, 674, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:33'),
(90, 'unit.status_changed', NULL, NULL, NULL, 656, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:41'),
(91, 'unit.status_changed', NULL, NULL, NULL, 671, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:51'),
(92, 'unit.status_changed', NULL, NULL, NULL, 704, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:56'),
(93, 'unit.status_changed', NULL, NULL, NULL, 707, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:13:59'),
(94, 'unit.status_changed', NULL, NULL, NULL, 710, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:02'),
(95, 'unit.status_changed', NULL, NULL, NULL, 701, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:05'),
(96, 'unit.status_changed', NULL, NULL, NULL, 665, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:09'),
(97, 'unit.status_changed', NULL, NULL, NULL, 662, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:12'),
(98, 'unit.status_changed', NULL, NULL, NULL, 698, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:15'),
(99, 'unit.status_changed', NULL, NULL, NULL, 650, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:20'),
(100, 'unit.status_changed', NULL, NULL, NULL, 659, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:31'),
(101, 'unit.status_changed', NULL, NULL, NULL, 695, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:34'),
(102, 'unit.status_changed', NULL, NULL, NULL, 692, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:37'),
(103, 'unit.status_changed', NULL, NULL, NULL, 689, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:39'),
(104, 'unit.status_changed', NULL, NULL, NULL, 668, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:42'),
(105, 'unit.status_changed', NULL, NULL, NULL, 677, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:45'),
(106, 'unit.status_changed', NULL, NULL, NULL, 680, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:49'),
(107, 'unit.status_changed', NULL, NULL, NULL, 638, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:52'),
(108, 'unit.status_changed', NULL, NULL, NULL, 641, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:14:56'),
(109, 'unit.status_changed', NULL, NULL, NULL, 644, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:15:06'),
(110, 'unit.status_changed', NULL, NULL, NULL, 713, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:15:11'),
(111, 'create_unit', NULL, NULL, NULL, 772, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Rancho Imperial De Silang, Phase 1. Blk 36, Lot 11\",\"project\":\"Rancho Imperial De Silang\",\"phase\":\"1\",\"block\":\"36\",\"lot\":\"11\"}', '2026-11-06 08:15:59'),
(112, 'create_unit', NULL, NULL, NULL, 775, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"The Venetto Heights, Phase 1, Blk 3, Lot 9A\",\"project\":\"The Venetto Heights\",\"phase\":\"1\",\"block\":\"3\",\"lot\":\"9A\"}', '2026-11-06 08:16:42'),
(113, 'create_unit', NULL, NULL, NULL, 782, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 5, Lot 26\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"5\",\"lot\":\"26\"}', '2026-11-06 08:17:50'),
(114, 'create_unit', NULL, NULL, NULL, 785, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Rancho Imperial De Silang, Phase 1, Blk 14, Lot 36\",\"project\":\"Rancho Imperial De Silang\",\"phase\":\"1\",\"block\":\"14\",\"lot\":\"36\"}', '2026-11-06 08:18:25'),
(115, 'create_unit', NULL, NULL, NULL, 788, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 32\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"32\"}', '2026-11-06 08:19:03'),
(116, 'create_unit', NULL, NULL, NULL, 791, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 33\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"33\"}', '2026-11-06 08:19:41'),
(117, 'create_unit', NULL, NULL, NULL, 794, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 25\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"25\"}', '2026-11-06 08:20:12'),
(118, 'create_unit', NULL, NULL, NULL, 797, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31. Lot 24\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"24\"}', '2026-11-06 08:21:10'),
(119, 'create_unit', NULL, NULL, NULL, 800, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 20\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"20\"}', '2026-11-06 08:22:14'),
(120, 'create_unit', NULL, NULL, NULL, 803, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Blk 31, Lot 54\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":null,\"block\":\"31\",\"lot\":\"54\"}', '2026-11-06 08:23:02'),
(121, 'create_unit', NULL, NULL, NULL, 806, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"ISS-Ligao, Blk 2, Lot 4\",\"project\":\"ISS-Ligao\",\"phase\":null,\"block\":\"2\",\"lot\":\"4\"}', '2026-11-06 08:24:31'),
(122, 'create_unit', NULL, NULL, NULL, 809, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"RIS-Pol, Blk 1, Lot 6&7\",\"project\":\"RIS-Pol\",\"phase\":null,\"block\":\"1\",\"lot\":\"6&7\"}', '2026-11-06 08:25:18'),
(123, 'create_unit', NULL, NULL, NULL, 812, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 6, Lot 2\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"6\",\"lot\":\"2\"}', '2026-11-06 08:25:54'),
(124, 'create_unit', NULL, NULL, NULL, 817, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 6, Lot 3\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"6\",\"lot\":\"3\"}', '2026-11-06 08:26:52'),
(125, 'create_unit', NULL, NULL, NULL, 820, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 6, Lot 4\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"6\",\"lot\":\"4\"}', '2026-11-06 08:27:12'),
(126, 'create_unit', NULL, NULL, NULL, 823, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRHS-Estanza, Blk 21, Lot 35\",\"project\":\"IRHS-Estanza\",\"phase\":null,\"block\":\"21\",\"lot\":\"35\"}', '2026-11-06 08:27:52'),
(127, 'create_unit', NULL, NULL, NULL, 826, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRS-Sorsogon, Blk 7, Lot 7\",\"project\":\"IRS-Sorsogon\",\"phase\":null,\"block\":\"7\",\"lot\":\"7\"}', '2026-11-06 08:28:14'),
(128, 'unit.status_changed', NULL, NULL, NULL, 785, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:32'),
(129, 'unit.status_changed', NULL, NULL, NULL, 772, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:36'),
(130, 'unit.status_changed', NULL, NULL, NULL, 775, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:38'),
(131, 'unit.status_changed', NULL, NULL, NULL, 803, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:41'),
(132, 'unit.status_changed', NULL, NULL, NULL, 800, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:51'),
(133, 'unit.status_changed', NULL, NULL, NULL, 794, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:55'),
(134, 'unit.status_changed', NULL, NULL, NULL, 788, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:28:58'),
(135, 'unit.status_changed', NULL, NULL, NULL, 791, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:29:06'),
(136, 'unit.status_changed', NULL, NULL, NULL, 797, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:29:09'),
(137, 'unit.status_changed', NULL, NULL, NULL, 782, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:29:14'),
(138, 'unit.status_changed', NULL, NULL, NULL, 806, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:29:31'),
(139, 'unit.status_changed', NULL, NULL, NULL, 809, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:29:41'),
(140, 'unit.status_changed', NULL, NULL, NULL, 812, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:30:06'),
(141, 'unit.status_changed', NULL, NULL, NULL, 817, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:30:12'),
(142, 'unit.status_changed', NULL, NULL, NULL, 820, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:30:17'),
(143, 'unit.status_changed', NULL, NULL, NULL, 823, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:30:38'),
(144, 'unit.status_changed', NULL, NULL, NULL, 826, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:30:52'),
(145, 'create_unit', NULL, NULL, NULL, 863, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VHS-Iriga, Blk 7, Lot 12\",\"project\":\"VHS-Iriga\",\"phase\":null,\"block\":\"7\",\"lot\":\"12\"}', '2026-11-06 08:33:35'),
(146, 'create_unit', NULL, NULL, NULL, 866, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial Central Plains, Phase 1, Blk 1, Lot 15\",\"project\":\"Imperial Central Plains\",\"phase\":\"1\",\"block\":\"1\",\"lot\":\"15\"}', '2026-11-06 08:34:04'),
(147, 'create_unit', NULL, NULL, NULL, 869, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 36\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"8\",\"lot\":\"36\"}', '2026-11-06 08:34:35'),
(148, 'create_unit', NULL, NULL, NULL, 872, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 45\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"45\"}', '2026-11-06 08:35:03'),
(149, 'create_unit', NULL, NULL, NULL, 875, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2. Blk 5, Lot 113\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"113\"}', '2026-11-06 08:35:30'),
(150, 'create_unit', NULL, NULL, NULL, 878, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 30\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"30\"}', '2026-11-06 08:36:06'),
(151, 'create_unit', NULL, NULL, NULL, 881, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 117\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"117\"}', '2026-11-06 08:36:32'),
(152, 'create_unit', NULL, NULL, NULL, 884, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 19\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"10\",\"lot\":\"19\"}', '2026-11-06 08:36:57'),
(153, 'create_unit', NULL, NULL, NULL, 887, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 125\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"125\"}', '2026-11-06 08:37:23'),
(154, 'create_unit', NULL, NULL, NULL, 890, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 130\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"130\"}', '2026-11-06 08:37:49'),
(155, 'create_unit', NULL, NULL, NULL, 893, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 3, Lot 1\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"3\",\"lot\":\"1\"}', '2026-11-06 08:38:23'),
(156, 'create_unit', NULL, NULL, NULL, 896, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 15&16\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"10\",\"lot\":\"15&16\"}', '2026-11-06 08:38:55'),
(157, 'create_unit', NULL, NULL, NULL, 899, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 124\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"124\"}', '2026-11-06 08:39:20'),
(158, 'create_unit', NULL, NULL, NULL, 902, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 44\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"44\"}', '2026-11-06 08:39:47'),
(159, 'create_unit', NULL, NULL, NULL, 905, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 46\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"8\",\"lot\":\"46\"}', '2026-11-06 08:40:13'),
(160, 'create_unit', NULL, NULL, NULL, 908, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial Central Plains, Phase 1, Blk 8, Lot 23\",\"project\":\"Imperial Central Plains\",\"phase\":\"1\",\"block\":\"8\",\"lot\":\"23\"}', '2026-11-06 08:40:38'),
(161, 'unit.status_changed', NULL, NULL, NULL, 866, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:41:13'),
(162, 'unit.status_changed', NULL, NULL, NULL, 908, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:41:21'),
(163, 'unit.status_changed', NULL, NULL, NULL, 863, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:41:25'),
(164, 'unit.status_changed', NULL, NULL, NULL, 896, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:42:37'),
(165, 'unit.status_changed', NULL, NULL, NULL, 884, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:42:42'),
(166, 'unit.status_changed', NULL, NULL, NULL, 869, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:15'),
(167, 'unit.status_changed', NULL, NULL, NULL, 872, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:28'),
(168, 'unit.status_changed', NULL, NULL, NULL, 875, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:32'),
(169, 'unit.status_changed', NULL, NULL, NULL, 878, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:44'),
(170, 'unit.status_changed', NULL, NULL, NULL, 881, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:50'),
(171, 'unit.status_changed', NULL, NULL, NULL, 887, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:44:57'),
(172, 'unit.status_changed', NULL, NULL, NULL, 890, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:45:00'),
(173, 'unit.status_changed', NULL, NULL, NULL, 899, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:45:09'),
(174, 'unit.status_changed', NULL, NULL, NULL, 893, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:45:11'),
(175, 'unit.status_changed', NULL, NULL, NULL, 902, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:45:26'),
(176, 'unit.status_changed', NULL, NULL, NULL, 905, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:45:37'),
(177, 'create_unit', NULL, NULL, NULL, 952, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 45\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"8\",\"lot\":\"45\"}', '2026-11-06 08:47:08'),
(178, 'create_unit', NULL, NULL, NULL, 955, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 116\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"116\"}', '2026-11-06 08:47:29'),
(179, 'create_unit', NULL, NULL, NULL, 958, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 107\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"107\"}', '2026-11-06 08:47:54'),
(180, 'create_unit', NULL, NULL, NULL, 961, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 99\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"99\"}', '2026-11-06 08:48:17'),
(181, 'create_unit', NULL, NULL, NULL, 964, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Blk 5, Lot 1\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":null,\"block\":\"5\",\"lot\":\"1\"}', '2026-11-06 08:48:40'),
(182, 'create_unit', NULL, NULL, NULL, 967, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 83\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"10\",\"lot\":\"83\"}', '2026-11-06 08:49:08'),
(183, 'create_unit', NULL, NULL, NULL, 970, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 4, Lot 1\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"4\",\"lot\":\"1\"}', '2026-11-06 08:49:29'),
(184, 'create_unit', NULL, NULL, NULL, 973, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 14\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"14\"}', '2026-11-06 08:49:48'),
(185, 'create_unit', NULL, NULL, NULL, 976, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 15\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"15\"}', '2026-11-06 08:51:57'),
(186, 'create_unit', NULL, NULL, NULL, 979, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 22\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"22\"}', '2026-11-06 08:52:23'),
(187, 'create_unit', NULL, NULL, NULL, 982, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 88\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"10\",\"lot\":\"88\"}', '2026-11-06 08:52:47'),
(188, 'create_unit', NULL, NULL, NULL, 985, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 24\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"8\",\"lot\":\"24\"}', '2026-11-06 08:53:15'),
(189, 'unit.status_changed', NULL, NULL, NULL, 964, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:53:41'),
(190, 'unit.status_changed', NULL, NULL, NULL, 967, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:53:48'),
(191, 'unit.status_changed', NULL, NULL, NULL, 982, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:53:53'),
(192, 'unit.status_changed', NULL, NULL, NULL, 970, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:53:58'),
(193, 'unit.status_changed', NULL, NULL, NULL, 958, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:03'),
(194, 'unit.status_changed', NULL, NULL, NULL, 955, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:06'),
(195, 'unit.status_changed', NULL, NULL, NULL, 952, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:16'),
(196, 'unit.status_changed', NULL, NULL, NULL, 973, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:20'),
(197, 'unit.status_changed', NULL, NULL, NULL, 976, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:24'),
(198, 'unit.status_changed', NULL, NULL, NULL, 961, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:27'),
(199, 'unit.status_changed', NULL, NULL, NULL, 979, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:33'),
(200, 'unit.status_changed', NULL, NULL, NULL, 985, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 08:54:40'),
(201, 'create_unit', NULL, NULL, NULL, 1012, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Marita Villas, Blk 5, Lot 15\",\"project\":\"Marita Villas\",\"phase\":null,\"block\":\"5\",\"lot\":\"15\"}', '2026-11-06 08:56:07'),
(202, 'create_unit', NULL, NULL, NULL, 1015, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Marita Villas, Blk 5, Lot 17\",\"project\":\"Marita Villas\",\"phase\":null,\"block\":\"5\",\"lot\":\"17\"}', '2026-11-06 08:56:29'),
(203, 'create_unit', NULL, NULL, NULL, 1018, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"The Venetto Heights, Phase 1, Blk 3, Lot 8A\",\"project\":\"The Venetto Heights\",\"phase\":\"1\",\"block\":\"3\",\"lot\":\"8A\"}', '2026-11-06 08:56:59'),
(204, 'create_unit', NULL, NULL, NULL, 1021, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 35\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"35\"}', '2026-11-06 08:57:29'),
(205, 'create_unit', NULL, NULL, NULL, 1024, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 48\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"20\",\"lot\":\"48\"}', '2026-11-06 09:00:08'),
(206, 'create_unit', NULL, NULL, NULL, 1027, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 17, Lot 26\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"17\",\"lot\":\"26\"}', '2026-11-06 09:02:21'),
(207, 'create_unit', NULL, NULL, NULL, 1030, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 18\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"18\"}', '2026-11-06 09:02:53'),
(208, 'create_unit', NULL, NULL, NULL, 1033, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Sto.Tomas, Phase 3, Blk 9, Lot 22\",\"project\":\"Via Verde-Sto.Tomas\",\"phase\":\"3\",\"block\":\"9\",\"lot\":\"22\"}', '2026-11-06 09:03:19'),
(209, 'unit.status_changed', NULL, NULL, NULL, 1012, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:03:37'),
(210, 'unit.status_changed', NULL, NULL, NULL, 1015, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:03:39'),
(211, 'unit.status_changed', NULL, NULL, NULL, 1018, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:03:55'),
(212, 'unit.status_changed', NULL, NULL, NULL, 1021, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:04:17'),
(213, 'unit.status_changed', NULL, NULL, NULL, 1024, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:04:45'),
(214, 'unit.status_changed', NULL, NULL, NULL, 1027, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:04:47'),
(215, 'unit.status_changed', NULL, NULL, NULL, 1030, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:05:02'),
(216, 'unit.status_changed', NULL, NULL, NULL, 1033, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-06 09:05:13'),
(217, 'unit.status_changed', 0, 8, NULL, 422, 'ON HOLD', 'AVAILABLE', NULL, NULL, '{\"reason\":\"holding expired\",\"holding_id\":8}', '2026-11-09 05:08:23'),
(218, 'holding_fee.expired', 0, 8, NULL, 422, 'PAID', 'EXPIRED', NULL, NULL, '{\"expiration_date\":\"2026-11-08\"}', '2026-11-09 05:08:23'),
(219, 'create_unit', NULL, NULL, NULL, 1072, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"ZZ-TEST-DELETE-ME Phase 9 Blk 99 Lot 99\",\"project\":\"ZZTest\",\"phase\":\"9\",\"block\":\"99\",\"lot\":\"99\"}', '2026-11-09 05:59:27'),
(220, 'create_unit', NULL, NULL, NULL, 1078, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"ZZ-VERIFY-ROUNDTRIP, Blk 98, Lot 98\",\"project\":\"ZZVerify\",\"phase\":\"1\",\"block\":\"98\",\"lot\":\"98\"}', '2026-11-09 06:04:09'),
(221, 'delete_unit', NULL, NULL, NULL, 1078, 'AVAILABLE', NULL, '5', 'Rea', '{\"displayLabel\":\"ZZ-VERIFY-ROUNDTRIP, Blk 98, Lot 98\"}', '2026-11-09 06:04:32'),
(222, 'create_unit', NULL, NULL, NULL, 1084, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"ZZ-FINAL-CHECK Blk 97 Lot 97\",\"project\":\"ZZ\",\"phase\":\"1\",\"block\":\"97\",\"lot\":\"97\"}', '2026-11-09 06:06:14'),
(223, 'delete_unit', NULL, NULL, NULL, 1084, 'AVAILABLE', NULL, '5', 'Rea', '{\"displayLabel\":\"ZZ-FINAL-CHECK Blk 97 Lot 97\"}', '2026-11-09 06:06:14'),
(224, 'create_unit', NULL, NULL, NULL, 1088, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 33\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"20\",\"lot\":\"33\"}', '2026-11-09 06:35:47'),
(225, 'create_unit', NULL, NULL, NULL, 1092, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 40\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"40\"}', '2026-11-09 06:36:24'),
(226, 'create_unit', NULL, NULL, NULL, 1095, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 41\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"41\"}', '2026-11-09 06:36:47'),
(227, 'create_unit', NULL, NULL, NULL, 1098, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 22\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"22\"}', '2026-11-09 06:37:09'),
(228, 'create_unit', NULL, NULL, NULL, 1101, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 21\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"21\"}', '2026-11-09 06:37:29'),
(229, 'create_unit', NULL, NULL, NULL, 1104, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 23\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"23\"}', '2026-11-09 07:07:31'),
(230, 'create_unit', NULL, NULL, NULL, 1107, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Blk 31, Lot 27\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":null,\"block\":\"31\",\"lot\":\"27\"}', '2026-11-09 07:07:59'),
(231, 'unit.status_changed', NULL, NULL, NULL, 1107, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:08:31'),
(232, 'unit.status_changed', NULL, NULL, NULL, 1101, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:08:35'),
(233, 'unit.status_changed', NULL, NULL, NULL, 1104, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:08:40'),
(234, 'unit.status_changed', NULL, NULL, NULL, 1092, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:08:53'),
(235, 'unit.status_changed', NULL, NULL, NULL, 1095, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:08:56'),
(236, 'unit.status_changed', NULL, NULL, NULL, 1098, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:09:01'),
(237, 'unit.status_changed', NULL, NULL, NULL, 1088, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:09:17'),
(238, 'create_unit', NULL, NULL, NULL, 1128, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE HEIGHTS, Blk 6, Lot 15\",\"project\":\"VIA VERDE HEIGHTS\",\"phase\":null,\"block\":\"6\",\"lot\":\"15\"}', '2026-11-09 07:10:36'),
(239, 'create_unit', NULL, NULL, NULL, 1131, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE HEIGHTS, Blk 6, Lot 16\",\"project\":\"VIA VERDE HEIGHTS\",\"phase\":null,\"block\":\"6\",\"lot\":\"16\"}', '2026-11-09 07:10:57'),
(240, 'create_unit', NULL, NULL, NULL, 1135, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 21, Lot 18\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"21\",\"lot\":\"18\"}', '2026-11-09 07:11:46'),
(241, 'create_unit', NULL, NULL, NULL, 1138, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRHS-Estanza, Blk 29, Lot 36\",\"project\":\"IRHS-Estanza\",\"phase\":null,\"block\":\"29\",\"lot\":\"36\"}', '2026-11-09 07:12:24'),
(242, 'create_unit', NULL, NULL, NULL, 1141, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"IRHS-Estanza, Blk 29, Lot 37\",\"project\":\"IRHS-Estanza\",\"phase\":null,\"block\":\"29\",\"lot\":\"37\"}', '2026-11-09 07:12:44'),
(243, 'create_unit', NULL, NULL, NULL, 1144, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VHS-Iriga, Blk 8, Lot 14\",\"project\":\"VHS-Iriga\",\"phase\":null,\"block\":\"8\",\"lot\":\"14\"}', '2026-11-09 07:13:00'),
(244, 'create_unit', NULL, NULL, NULL, 1147, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 47\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"8\",\"lot\":\"47\"}', '2026-11-09 07:13:23'),
(245, 'create_unit', NULL, NULL, NULL, 1150, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 28\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"28\"}', '2026-11-09 07:13:51'),
(246, 'create_unit', NULL, NULL, NULL, 1153, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 23\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"7\",\"lot\":\"23\"}', '2026-11-09 07:14:13'),
(247, 'create_unit', NULL, NULL, NULL, 1156, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 87\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"87\"}', '2026-11-09 07:14:38'),
(248, 'create_unit', NULL, NULL, NULL, 1159, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 25\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"25\"}', '2026-11-09 07:15:01'),
(249, 'create_unit', NULL, NULL, NULL, 1162, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 1. Blk 31, Lot 26\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"1\",\"block\":\"31\",\"lot\":\"26\"}', '2026-11-09 07:15:29');
INSERT INTO `audit_logs` (`id`, `action`, `contract_id`, `holding_fee_id`, `reservation_fee_id`, `property_unit_id`, `from_status`, `to_status`, `actor_id`, `actor_name`, `details`, `created_at`) VALUES
(250, 'create_unit', NULL, NULL, NULL, 1165, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Priya Meridien, Phase 1, Blk 11, Lot 30&31\",\"project\":\"Priya Meridien\",\"phase\":\"1\",\"block\":\"11\",\"lot\":\"30 & 32\"}', '2026-11-09 07:15:57'),
(251, 'create_unit', NULL, NULL, NULL, 1168, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Priya Meridien, phase 1, Blk 14A, Lot 5\",\"project\":\"Priya Meridien\",\"phase\":\"1\",\"block\":\"14A\",\"lot\":\"5\"}', '2026-11-09 07:16:26'),
(252, 'create_unit', NULL, NULL, NULL, 1171, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 37\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"37\"}', '2026-11-09 07:16:48'),
(253, 'create_unit', NULL, NULL, NULL, 1174, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Tagaytay Meridian, Phase 1, Blk 4, Lot 10\",\"project\":\"Tagaytay Meridian\",\"phase\":\"1\",\"block\":\"4\",\"lot\":\"10\"}', '2026-11-09 07:17:11'),
(254, 'create_unit', NULL, NULL, NULL, 1177, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial South Meadows, Phase 1, Blk 5, Lot 8\",\"project\":\"Imperial South Meadows\",\"phase\":\"1\",\"block\":\"5\",\"lot\":\"8\"}', '2026-11-09 07:17:35'),
(255, 'create_unit', NULL, NULL, NULL, 1180, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial Central Plains, Phase 1, Blk 3, Lot 9\",\"project\":\"Imperial Central Plains\",\"phase\":\"1\",\"block\":\"3\",\"lot\":\"9\"}', '2026-11-09 07:17:58'),
(256, 'create_unit', NULL, NULL, NULL, 1183, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 80\",\"project\":\"Via Verde-Cabanatuan\",\"phase\":\"2\",\"block\":\"5\",\"lot\":\"80\"}', '2026-11-09 07:18:20'),
(257, 'unit.status_changed', NULL, NULL, NULL, 1128, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:18:39'),
(258, 'unit.status_changed', NULL, NULL, NULL, 1131, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:18:40'),
(259, 'unit.status_changed', NULL, NULL, NULL, 1135, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:18:51'),
(260, 'unit.status_changed', NULL, NULL, NULL, 1138, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:18:59'),
(261, 'unit.status_changed', NULL, NULL, NULL, 1141, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:00'),
(262, 'unit.status_changed', NULL, NULL, NULL, 1144, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:08'),
(263, 'unit.status_changed', NULL, NULL, NULL, 1147, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:40'),
(264, 'unit.status_changed', NULL, NULL, NULL, 1150, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:42'),
(265, 'unit.status_changed', NULL, NULL, NULL, 1153, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:44'),
(266, 'unit.status_changed', NULL, NULL, NULL, 1156, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:52'),
(267, 'unit.status_changed', NULL, NULL, NULL, 1159, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:19:54'),
(268, 'unit.status_changed', NULL, NULL, NULL, 1183, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:02'),
(269, 'unit.status_changed', NULL, NULL, NULL, 1162, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:19'),
(270, 'unit.status_changed', NULL, NULL, NULL, 1165, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:29'),
(271, 'unit.status_changed', NULL, NULL, NULL, 1168, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:31'),
(272, 'unit.status_changed', NULL, NULL, NULL, 1171, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:42'),
(273, 'unit.status_changed', NULL, NULL, NULL, 1174, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:51'),
(274, 'unit.status_changed', NULL, NULL, NULL, 1177, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:20:57'),
(275, 'unit.status_changed', NULL, NULL, NULL, 1180, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 07:21:03'),
(276, 'create_unit', NULL, NULL, NULL, 1237, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Imperial South Meadows, Phase 2C, Blk 2, Lot 6\",\"project\":\"Imperial South Meadows\",\"phase\":\"1C\",\"block\":\"2\",\"lot\":\"6\"}', '2026-11-09 07:22:09'),
(277, 'create_unit', NULL, NULL, NULL, 1240, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Trece Martires, Phase 2B, Blk 11, Lot 3\",\"project\":\"Via Verde-Trece Martires\",\"phase\":\"2B\",\"block\":\"11\",\"lot\":\"3\"}', '2026-11-09 07:22:35'),
(278, 'create_unit', NULL, NULL, NULL, 1243, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 56\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"56\"}', '2026-11-09 07:22:56'),
(279, 'create_unit', NULL, NULL, NULL, 1246, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 34\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"34\"}', '2026-11-09 07:23:16'),
(280, 'create_unit', NULL, NULL, NULL, 1249, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 52\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"52\"}', '2026-11-09 07:23:40'),
(281, 'create_unit', NULL, NULL, NULL, 1252, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 49\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"49\"}', '2026-11-09 07:24:11'),
(282, 'create_unit', NULL, NULL, NULL, 1255, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 44\",\"project\":\"Via Verde-Padre Garcia\",\"phase\":\"2\",\"block\":\"31\",\"lot\":\"44\"}', '2026-11-09 07:24:32'),
(283, 'create_unit', NULL, NULL, NULL, 1258, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 24, Lot 10\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"24\",\"lot\":\"10\"}', '2026-11-09 09:00:33'),
(284, 'create_unit', NULL, NULL, NULL, 1264, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 10, Lot 17\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"10\",\"lot\":\"17\"}', '2026-11-09 13:02:19'),
(285, 'create_unit', NULL, NULL, NULL, 1267, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 6, Lot 9\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"6\",\"lot\":\"9\"}', '2026-11-09 13:02:51'),
(286, 'create_unit', NULL, NULL, NULL, 1270, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 21, Lot 26\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"21\",\"lot\":\"26\"}', '2026-11-09 13:03:13'),
(287, 'create_unit', NULL, NULL, NULL, 1273, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Blk 29, Lot 51\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"29\",\"lot\":\"51\"}', '2026-11-09 13:03:35'),
(288, 'create_unit', NULL, NULL, NULL, 1276, NULL, 'AVAILABLE', '5', 'Rea', '{\"displayLabel\":\"VIA VERDE-HOMAPON, Block 12, Lot 27\",\"project\":\"VIA VERDE-HOMAPON\",\"phase\":null,\"block\":\"Block 12\",\"lot\":\"Lot 27\"}', '2026-11-09 13:04:04'),
(289, 'unit.status_changed', NULL, NULL, NULL, 1237, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:04:42'),
(290, 'unit.status_changed', NULL, NULL, NULL, 1240, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:04:52'),
(291, 'unit.status_changed', NULL, NULL, NULL, 1243, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:09'),
(292, 'unit.status_changed', NULL, NULL, NULL, 1246, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:16'),
(293, 'unit.status_changed', NULL, NULL, NULL, 1249, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:20'),
(294, 'unit.status_changed', NULL, NULL, NULL, 1252, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:32'),
(295, 'unit.status_changed', NULL, NULL, NULL, 1255, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:34'),
(296, 'unit.status_changed', NULL, NULL, NULL, 1264, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:55'),
(297, 'unit.status_changed', NULL, NULL, NULL, 1270, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:57'),
(298, 'unit.status_changed', NULL, NULL, NULL, 1258, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:05:58'),
(299, 'unit.status_changed', NULL, NULL, NULL, 1273, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:06:00'),
(300, 'unit.status_changed', NULL, NULL, NULL, 1267, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:06:02'),
(301, 'unit.status_changed', NULL, NULL, NULL, 1276, 'AVAILABLE', 'SOLD', '5', 'Rea', '{\"reason\":\"Marked SOLD from Property Inventory\"}', '2026-11-09 13:06:03');

-- --------------------------------------------------------

--
-- Table structure for table `bank_rates`
--

CREATE TABLE `bank_rates` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(120) NOT NULL,
  `annual_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `display_label` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_rates`
--

INSERT INTO `bank_rates` (`id`, `bank_name`, `annual_rate`, `display_label`, `sort_order`, `updated_at`) VALUES
(10, 'In-House', 0.00, 'In-House', 0, '2026-09-25 06:20:31'),
(11, 'Pag-IBIG', 5.50, 'Pag-IBIG Fund', 1, '2026-09-25 06:20:31'),
(12, 'BDO', 6.50, 'BDO', 2, '2026-09-25 06:20:31'),
(13, 'BPI', 6.50, 'BPI', 3, '2026-09-25 06:20:31'),
(14, 'Metrobank', 6.50, 'Metrobank', 4, '2026-09-25 06:20:31'),
(15, 'Security Bank', 6.75, 'Security Bank', 5, '2026-09-25 06:20:31'),
(16, 'RCBC', 6.75, 'RCBC', 6, '2026-09-25 06:20:31'),
(17, 'UnionBank', 7.00, 'UnionBank', 7, '2026-09-25 06:20:31'),
(18, 'PNB', 7.00, 'PNB', 8, '2026-09-25 06:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `business_rules`
--

CREATE TABLE `business_rules` (
  `rule_key` varchar(80) NOT NULL,
  `rule_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_rules`
--

INSERT INTO `business_rules` (`rule_key`, `rule_value`, `description`, `updated_at`) VALUES
('business_rules.version', '6', 'Migration marker', '2026-09-25 06:20:31'),
('holding_fee.allow_direct_reservation', '0', '1=allow reservation without prior active hold', '2026-09-25 06:20:31'),
('holding_fee.convert_on_reservation', '1', '1=reservation PAID marks holding CONVERTED', '2026-09-25 06:20:31'),
('holding_fee.default_days', '30', 'Default hold window', '2026-09-25 06:20:31'),
('holding_fee.expire_makes_available', '1', '1=EXPIRED->AVAILABLE', '2026-09-25 06:20:31'),
('holding_fee.refundable', '0', '', '2026-09-25 06:20:31'),
('property_units.descriptive_backfill', '1', 'One-time fill of project/phase/block/lot from contracts', '2026-10-01 23:58:38'),
('reservation_fee.refundable', '1', '', '2026-09-25 06:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `client_accounts`
--

CREATE TABLE `client_accounts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `cellphone_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_accounts`
--

INSERT INTO `client_accounts` (`id`, `email`, `password_hash`, `full_name`, `cellphone_number`, `created_at`, `updated_at`) VALUES
(10, 'agajonadianne@gmail.com', '$2y$10$JeYNW4R5CzcDJD5QXURCOurqC32qZY2dE609yER1ezvEzIEuitSFi', 'Daniela Fuentes', '09096890140', '2026-09-25 06:33:19', '2026-09-25 06:33:19'),
(12, 'yanniedianne26@gmail.com', '$2y$10$GuawTjwrvWu/C2ibvpsVKO/KjdVqZUGoTwBKgKXb/RTmUqunnOMK2', 'Juliette Salvador', '09937963257', '2026-10-02 05:04:50', '2026-10-02 05:04:50'),
(13, 'jmcantalejo@ccc.edu.ph', '$2y$10$PX/r3eH7WZKt3SR.OrzZv.XlcDUVq38zpT.dYieqKlEy727ir/NHG', 'Linda Walker', '09096890140', '2026-10-09 00:31:36', '2026-10-09 00:31:36'),
(14, 'maria.santos@example.com', '$2y$10$SYyJ/UJtHJ/PP3kiFdpEz.TpJTS10IhfBFS55DZSTBk2U4EbZ3QOm', 'Maria Santos', '09171112222', '2026-10-09 09:10:23', '2026-10-09 09:10:23'),
(15, 'juan.cruz@example.com', '$2y$10$IipGFVOZKeUMNMsWBo0Wl.ZulWJxXeJGYMIcKGwOQ7zaBj76gbFki', 'Juan Dela Cruz', '09175558888', '2026-10-09 09:24:32', '2026-10-09 09:24:32'),
(16, 'test.remove@example.com', '$2y$10$10491G9tGXykIXeWIq8gtuN3VjvRr6kQqXlpkP3cDHmkmW5Vs.Fh6', 'Test Remove Fields', '09170001111', '2026-10-09 09:37:57', '2026-10-09 09:37:57');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `cellphone_number` varchar(50) NOT NULL,
  `total_contract_price` decimal(15,2) NOT NULL,
  `downpayment` decimal(15,2) NOT NULL,
  `installment_terms` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `property_address` varchar(500) DEFAULT NULL,
  `officer_id` varchar(100) DEFAULT NULL,
  `dp_mode` varchar(10) DEFAULT NULL,
  `dp_terms` int(11) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `annual_interest_rate` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `loanable_amount` decimal(15,2) DEFAULT NULL,
  `approved_loan_amount` decimal(15,2) DEFAULT NULL,
  `early_move_in_amount` decimal(15,2) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `project_phase` varchar(120) DEFAULT NULL,
  `block_no` varchar(50) DEFAULT NULL,
  `lot_no` varchar(50) DEFAULT NULL,
  `model_type` varchar(120) DEFAULT NULL,
  `lot_area` varchar(100) DEFAULT NULL,
  `floor_area` varchar(100) DEFAULT NULL,
  `client_address` varchar(500) DEFAULT NULL,
  `equity_monthly_rate` decimal(7,4) DEFAULT NULL,
  `equity_penalty_rate` decimal(7,4) DEFAULT NULL,
  `loan_term_years` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `client_name`, `email`, `cellphone_number`, `total_contract_price`, `downpayment`, `installment_terms`, `start_date`, `created_at`, `property_address`, `officer_id`, `dp_mode`, `dp_terms`, `bank_name`, `annual_interest_rate`, `discount_amount`, `loanable_amount`, `approved_loan_amount`, `early_move_in_amount`, `project_name`, `project_phase`, `block_no`, `lot_no`, `model_type`, `lot_area`, `floor_area`, `client_address`, `equity_monthly_rate`, `equity_penalty_rate`, `loan_term_years`) VALUES
(24, 'Daniela Fuentes', 'agajonadianne@gmail.com', '09096890140', 3000000.00, 1500000.00, 48, '2026-09-29', '2026-09-25 06:33:19', 'block 1 lot 5 Sampaguita, Madrigal Alabang Muntinlupa City', '2', 'lump', NULL, 'Pag-IBIG', 5.50, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 'Juliette Salvador', 'yanniedianne26@gmail.com', '09937963257', 3000000.00, 300000.00, 18, '2026-10-06', '2026-10-02 05:04:50', 'Phase 3, Block 12, Lot 5, Mabuhay Street, Soldier\'s Hills Subdivision, Barangay Molino VI, Bacoor City, Cavite', '2', 'lump', NULL, 'Metrobank', 6.50, 100000.00, 2600000.00, 2600000.00, 50000.00, 'Soldier\'s Hills Subdivision', 'Phase 3', 'Block 12', 'Lot 5', NULL, '150', '120', '1202 balite st. ilaya las pinas city', 1.0000, 2.0000, 15.00),
(46, 'Linda Walker', 'jmcantalejo@ccc.edu.ph', '09096890140', 5000000.00, 2000000.00, 48, '2026-10-11', '2026-10-09 00:31:36', 'Block 5 Lot 6, Phase 2, Soldier\'s Hills Subdivision', '2', 'lump', NULL, 'Pag-IBIG', 5.50, 200000.00, 1500000.00, 1500000.00, 0.00, 'Soldier\'s Hills Subdivision', 'Phase 2', 'Block 5', 'Lot 6', '3 storey', '150', '120', '1202 balite st. ilaya las pinas city', 2.0000, 2.0000, 20.00),
(47, 'Maria Santos', 'maria.santos@example.com', '09171112222', 1500000.00, 150000.00, 12, '2026-11-01', '2026-10-09 09:10:23', 'Test Village - Block 7 Lot 3', '2', 'lump', NULL, 'BDO', 8.50, 0.00, NULL, NULL, 0.00, 'Test Village', NULL, NULL, NULL, NULL, NULL, NULL, 'Cebu City', 0.0000, NULL, NULL),
(48, 'Juan Dela Cruz', 'juan.cruz@example.com', '09175558888', 2200000.00, 220000.00, 24, '2026-11-15', '2026-10-09 09:24:32', 'Rose St Phase 2 - Block 9 Lot 11', '2', 'monthly', 12, 'Pag-IBIG', 5.50, 0.00, NULL, NULL, 0.00, 'Rose St Phase 2', NULL, NULL, NULL, NULL, NULL, NULL, 'Paranaque City', 0.0000, NULL, NULL),
(49, 'Test Remove Fields', 'test.remove@example.com', '09170001111', 1800000.00, 180000.00, 12, '2026-12-01', '2026-10-09 09:37:57', 'Greenfield - Block 1 Lot 5', '2', 'monthly', 6, 'BDO', 6.50, 50000.00, 1620000.00, NULL, 10000.00, 'Greenfield', NULL, NULL, NULL, NULL, NULL, NULL, 'Manila', 12.0000, 2.0000, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `holding_fees`
--

CREATE TABLE `holding_fees` (
  `id` int(11) NOT NULL,
  `officer_id` varchar(100) DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `property_unit_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) NOT NULL,
  `property_address` varchar(500) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `or_number` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `status` enum('PENDING','PAID','EXPIRED','REFUNDED','FORFEITED','CONVERTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `payment_mode` varchar(20) DEFAULT NULL,
  `proof_name` varchar(255) DEFAULT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `converted_to_reservation_id` int(11) DEFAULT NULL,
  `proof_data_url` mediumtext DEFAULT NULL,
  `proof_is_image` tinyint(1) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `proof_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prospect_client_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holding_fees`
--

INSERT INTO `holding_fees` (`id`, `officer_id`, `contract_id`, `client_id`, `property_unit_id`, `client_name`, `property_address`, `amount`, `payment_method`, `payment_date`, `reference_number`, `or_number`, `start_date`, `expiration_date`, `status`, `payment_mode`, `proof_name`, `processed_by`, `converted_to_reservation_id`, `proof_data_url`, `proof_is_image`, `remarks`, `proof_path`, `created_at`, `updated_at`, `prospect_client_id`) VALUES
(3, '2', 47, NULL, 394, 'Maria Santos', 'Test Village - Block 7 Lot 3', 5000.00, 'Cash', '2026-10-09', NULL, NULL, '2026-10-09', '2026-11-08', 'CONVERTED', NULL, NULL, '2', 1, NULL, NULL, NULL, NULL, '2026-10-09 09:01:32', '2026-10-09 09:10:23', 1),
(4, '2', 47, NULL, 394, 'Maria Santos', 'Test Village - Block 7 Lot 3', 5000.00, 'To be collected', '2026-11-01', NULL, NULL, NULL, NULL, 'PENDING', NULL, NULL, '2', NULL, NULL, NULL, 'Set at contract creation.', NULL, '2026-10-09 09:10:23', '2026-10-09 09:10:23', NULL),
(5, '2', 48, NULL, 406, 'Juan Dela Cruz', 'Rose St Phase 2 - Block 9 Lot 11', 7500.00, 'GCash / Maya', '2026-10-09', NULL, 'OR-2026-1001', '2026-10-09', '2026-11-08', 'CONVERTED', 'partial', NULL, '2', 3, NULL, NULL, NULL, NULL, '2026-10-09 09:23:46', '2026-10-09 09:24:32', 2),
(6, '2', 48, NULL, 406, 'Juan Dela Cruz', 'Rose St Phase 2 - Block 9 Lot 11', 7500.00, 'To be collected', '2026-11-15', NULL, NULL, NULL, NULL, 'PENDING', NULL, NULL, '2', NULL, NULL, NULL, 'Set at contract creation.', NULL, '2026-10-09 09:24:32', '2026-10-09 09:24:32', NULL),
(7, '2', 49, 16, 419, 'Test Remove Fields', 'Greenfield - Block 1 Lot 5', 5000.00, 'Cash', '2026-10-09', NULL, 'OR-REG-001', '2026-10-09', '2026-11-08', 'CONVERTED', NULL, NULL, '2', 5, NULL, NULL, NULL, NULL, '2026-10-09 09:38:36', '2026-10-09 09:38:48', NULL),
(8, NULL, NULL, NULL, 422, 'Regression Tester', 'Sunrise Village - Block 2 Lot 9', 4000.00, 'Cash', '2026-10-09', NULL, 'OR-REG-002', '2026-10-09', '2026-11-08', 'EXPIRED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 09:38:37', '2026-11-09 05:08:23', 3);

-- --------------------------------------------------------

--
-- Table structure for table `notifications_logs`
--

CREATE TABLE `notifications_logs` (
  `id` int(11) NOT NULL,
  `contract_id` varchar(50) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `channel` varchar(50) NOT NULL DEFAULT 'email',
  `reminder_type` varchar(30) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed','pending','') NOT NULL DEFAULT 'pending',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications_logs`
--

INSERT INTO `notifications_logs` (`id`, `contract_id`, `client_email`, `channel`, `reminder_type`, `due_date`, `subject`, `status`, `sent_at`, `error_message`) VALUES
(6, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 16:05:46', NULL),
(7, '24', 'agajonadianne@gmail.com', 'ack_email', NULL, NULL, 'Client acknowledged payment reminder for IHC-24', 'sent', '2026-09-25 16:06:54', NULL),
(18, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 16:50:16', NULL),
(19, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 16:52:42', NULL),
(20, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 16:54:13', NULL),
(21, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 18:00:23', NULL),
(22, 'CON-45', 'yanniedianne26@gmail.com', 'email', 'payment_receipt', '2026-11-04', 'Payment Receipt OR-INS-2026-00002: Installment #1 — ₱144,444.44', 'sent', '2026-10-02 13:17:36', NULL),
(23, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-11-29', 'Payment Reminder / SOA Summary: Installment Payment #2 — ₱31,250.00 for 24 due in 58 day(s)', 'sent', '2026-10-02 13:19:11', NULL),
(24, '46', 'jmcantalejo@ccc.edu.ph', 'email', 'manual', '2026-11-11', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱58,333.33 for 46 due in 33 day(s)', 'sent', '2026-10-09 08:52:50', NULL),
(25, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-12-29', 'Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 due in 81 day(s)', 'sent', '2026-10-09 10:19:39', NULL),
(26, '24', 'agajonadianne@gmail.com', 'email', 'manual', '0000-00-00', 'FINAL NOTICE — OVERDUE: Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 is past due', 'sent', '2026-10-28 16:47:52', NULL),
(27, '24', 'agajonadianne@gmail.com', 'email', 'manual', '0000-00-00', 'FINAL NOTICE — OVERDUE: Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 is past due', 'sent', '2026-10-28 17:08:43', NULL),
(28, '24', 'agajonadianne@gmail.com', 'email', 'manual', '0000-00-00', 'FINAL NOTICE — OVERDUE: Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 is past due', 'sent', '2026-10-28 17:13:44', NULL),
(29, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-12-29', 'Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 due in 54 day(s)', 'sent', '2026-11-05 09:08:22', NULL),
(30, 'IHC-47', 'maria.santos@example.com', 'email', 'overdue', '2026-11-01', 'OVERDUE: Payment Reminder / SOA Summary: Downpayment — ₱151,062.50 for IHC-47 is past due', 'sent', '2026-11-06 08:05:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'clerk',
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `full_name`, `role`, `email`, `password_hash`, `must_change_password`) VALUES
(1, 'Jeremy Cantalejo', 'admin', 'admin@ihc.com', '$2y$10$BL9dLew5Rpc5hX/HKk3l7O4/6iqVbAlm3J7ubMOYR4JwgjwIOfSBi', 0),
(2, 'Ana Reyes', 'clerk', 'ana@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe', 0),
(5, 'Rea', 'inventory_admin', 'rea@ihc.com', '$2y$10$3UOBUc6oDGGOw9/3/5RzxOwOvLf6b.k/ticpokdx0gbjX94cQcwx2', 0);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `account_type` varchar(10) NOT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `contract_id` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `date_collected` date NOT NULL,
  `or_number` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `posted_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_number` varchar(100) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `installment_kind` varchar(20) DEFAULT NULL,
  `installment_no` int(11) DEFAULT NULL,
  `external_reference` varchar(100) DEFAULT NULL,
  `receipt_requested` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `contract_id`, `amount`, `payment_method`, `date_collected`, `or_number`, `remarks`, `posted_by`, `created_at`, `check_number`, `invoice_number`, `installment_kind`, `installment_no`, `external_reference`, `receipt_requested`) VALUES
(34, 'CON-24', 1500000.00, 'Over-the-Counter Cashier', '2026-09-27', 'OR-DP-2026-00001', 'PAID DP', '2', '2026-09-25 08:02:13', NULL, 'OR-DP-2026-00001', 'downpayment', NULL, NULL, 1),
(45, 'CON-24', 31250.00, 'Bank Wire / Online Deposit', '2026-10-02', 'OR-INS-2026-00001', NULL, '2', '2026-10-02 05:11:50', NULL, 'OR-INS-2026-00001', 'installment', 1, NULL, 1),
(46, 'CON-45', 300000.00, 'Over-the-Counter Cashier', '2026-10-05', 'OR-DP-2026-00002', NULL, '2', '2026-10-02 05:12:54', NULL, 'OR-DP-2026-00002', 'downpayment', NULL, NULL, 1),
(47, 'CON-45', 144444.44, 'GCash / Maya', '2026-11-04', 'OR-INS-2026-00002', 'PAID', '2', '2026-10-02 05:17:31', NULL, 'OR-INS-2026-00002', 'installment', 1, '12314343241344', 1),
(48, 'CON-24', 31250.00, 'Over-the-Counter Cashier', '2026-10-23', 'OR-INS-2026-00003', 'paid', '2', '2026-10-09 00:26:19', NULL, 'OR-INS-2026-00003', 'installment', 2, NULL, 1),
(49, 'CON-46', 2000000.00, 'Bank Wire / Online Deposit', '2026-10-16', 'OR-DP-2026-00003', NULL, '2', '2026-10-09 00:32:19', '42342343223423424342424', 'OR-DP-2026-00003', 'downpayment', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `installment_kind` varchar(20) NOT NULL,
  `installment_no` int(11) DEFAULT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_allocations`
--

INSERT INTO `payment_allocations` (`id`, `payment_id`, `contract_id`, `installment_kind`, `installment_no`, `allocated_amount`, `principal_amount`, `interest_amount`, `penalty_amount`, `created_at`) VALUES
(20, 34, 24, 'downpayment', NULL, 1500000.00, 1500000.00, 0.00, 0.00, '2026-09-25 08:02:13'),
(30, 45, 24, 'installment', 1, 31250.00, 31250.00, 0.00, 0.00, '2026-10-02 05:11:50'),
(31, 46, 45, 'downpayment', NULL, 300000.00, 300000.00, 0.00, 0.00, '2026-10-02 05:12:54'),
(32, 47, 45, 'installment', 1, 144444.44, 144444.44, 0.00, 0.00, '2026-10-02 05:17:31'),
(33, 48, 24, 'installment', 2, 31250.00, 31250.00, 0.00, 0.00, '2026-10-09 00:26:19'),
(34, 49, 46, 'downpayment', NULL, 2000000.00, 2000000.00, 0.00, 0.00, '2026-10-09 00:32:19');

-- --------------------------------------------------------

--
-- Table structure for table `payment_or_counters`
--

CREATE TABLE `payment_or_counters` (
  `series_code` varchar(3) NOT NULL,
  `series_year` smallint(5) UNSIGNED NOT NULL,
  `last_number` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_or_counters`
--

INSERT INTO `payment_or_counters` (`series_code`, `series_year`, `last_number`, `updated_at`) VALUES
('DP', 2026, 3, '2026-10-09 00:32:19'),
('INS', 2026, 3, '2026-10-09 00:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `property_units`
--

CREATE TABLE `property_units` (
  `id` int(11) NOT NULL,
  `project` varchar(255) DEFAULT NULL,
  `phase` varchar(120) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `unit_number` varchar(100) DEFAULT NULL,
  `model_type` varchar(120) DEFAULT NULL,
  `lot_area` varchar(100) DEFAULT NULL,
  `floor_area` varchar(100) DEFAULT NULL,
  `display_label` varchar(255) NOT NULL,
  `status` enum('AVAILABLE','ON HOLD','RESERVED','SOLD') NOT NULL DEFAULT 'AVAILABLE',
  `current_client_id` int(11) DEFAULT NULL,
  `current_contract_id` int(11) DEFAULT NULL,
  `current_holding_fee_id` int(11) DEFAULT NULL,
  `current_reservation_fee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_units`
--

INSERT INTO `property_units` (`id`, `project`, `phase`, `building`, `unit_number`, `model_type`, `lot_area`, `floor_area`, `display_label`, `status`, `current_client_id`, `current_contract_id`, `current_holding_fee_id`, `current_reservation_fee_id`, `created_at`, `updated_at`) VALUES
(22, NULL, NULL, '1', '5', NULL, NULL, NULL, 'block 1 lot 5 Sampaguita, Madrigal Alabang Muntinlupa City', 'AVAILABLE', NULL, 24, NULL, NULL, '2026-09-25 06:33:20', '2026-10-29 01:56:46'),
(193, 'Soldier\'s Hills Subdivision', 'Phase 3', 'Block 12', 'Lot 5', NULL, '150', '120', 'Phase 3, Block 12, Lot 5, Mabuhay Street, Soldier\'s Hills Subdivision, Barangay Molino VI, Bacoor City, Cavite', 'AVAILABLE', NULL, 45, NULL, NULL, '2026-10-02 05:04:50', '2026-10-02 05:04:50'),
(347, 'Soldier\'s Hills Subdivision', 'Phase 2', 'Block 5', 'Lot 6', '3 storey', '150', '120', 'Block 5 Lot 6, Phase 2, Soldier\'s Hills Subdivision', 'AVAILABLE', NULL, 46, NULL, NULL, '2026-10-09 00:31:36', '2026-10-09 00:31:36'),
(394, 'Test Village', NULL, NULL, NULL, NULL, NULL, NULL, 'Test Village - Block 7 Lot 3', 'RESERVED', NULL, 47, NULL, 1, '2026-10-09 09:01:32', '2026-10-09 09:10:23'),
(406, 'Rose St Phase 2', NULL, NULL, NULL, NULL, NULL, NULL, 'Rose St Phase 2 - Block 9 Lot 11', 'RESERVED', NULL, 48, NULL, 3, '2026-10-09 09:23:46', '2026-10-09 09:24:32'),
(419, 'Greenfield', NULL, NULL, NULL, NULL, NULL, NULL, 'Greenfield - Block 1 Lot 5', 'RESERVED', NULL, 49, NULL, 5, '2026-10-09 09:37:57', '2026-10-09 09:38:48'),
(422, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sunrise Village - Block 2 Lot 9', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-10-09 09:38:37', '2026-11-09 05:08:23'),
(604, 'Via Verde-Padre Garcia', '2', '28', '10', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 28, Lot 10', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:25:04', '2026-11-06 07:25:04'),
(609, 'Via Verde-Padre Garcia', '2', '17', '9', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 17, Lot 9', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:40:28', '2026-11-06 07:40:28'),
(613, 'Via Verde-Padre Garcia', '2', '27', '6', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 27, Lot 6', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:41:05', '2026-11-06 07:41:05'),
(616, 'Via Verde-Padre Garcia', '2', '29', '15', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 29, Lot15', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:41:56', '2026-11-06 07:41:56'),
(619, 'Via Verde-Sto.Tomas', '1', '11', '47', NULL, NULL, NULL, 'Via Verde-Sto.Tomas, Phase 1, Blk 11, Lot 47', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:43:19', '2026-11-06 07:43:19'),
(622, 'VIA VERDE TRECE', '2B', '17', '11', NULL, NULL, NULL, 'VIA VERDE TRECE, Phase 2B, Blk 17, lot 11', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:43:54', '2026-11-06 07:43:54'),
(625, 'Via Verde-Padre Garcia', '2', '28', '8', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 28, Lot 8', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:44:39', '2026-11-06 07:44:39'),
(628, 'VIA VERDE TRECE', '1', '3', '1', NULL, NULL, NULL, 'VIA VERDE TRECE, Phase 1, Blk 3, Lot 1', 'AVAILABLE', NULL, NULL, NULL, NULL, '2026-11-06 07:45:14', '2026-11-06 07:45:14'),
(633, 'VHS-Iriga', NULL, '8', '36', 'Savannah', NULL, NULL, 'VHS-Iriga, Blk 8, Lot 36', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:52:10', '2026-11-06 07:52:22'),
(638, 'VIA VERDE-HOMAPON', NULL, '8', '26', 'Tiarra DX', NULL, NULL, 'VIA VERDE-HOMAPON, Blk 8, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:53:11', '2026-11-06 08:14:52'),
(641, 'l', NULL, '8', '27', 'Tiarra DX', NULL, NULL, 'VIA VERDE-HOMAPON, Blk 8, Lot 27', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:53:52', '2026-11-06 08:14:56'),
(644, 'Via Verde-Trece Martires', '1', '5', '16', 'Tiarra Duplex', NULL, NULL, 'Via Verde-Trece Martires, Phase 1, Blk 5, Lot 16', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:54:35', '2026-11-06 08:15:06'),
(647, 'IRHS-Estanza', NULL, '18', '1', 'Lot Only', NULL, NULL, 'IRHS-Estanza, Blk 18, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:55:07', '2026-11-06 08:13:20'),
(650, 'Via Verde-Cabanatuan', '2', '10', '28', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 28', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:55:38', '2026-11-06 08:14:20'),
(653, 'IRS-Sorsogon', NULL, '27', '22', 'Lot Only', NULL, NULL, 'IRS-Sorsogon, Blk 27, Lot 22', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:56:13', '2026-11-06 08:13:30'),
(656, 'IRS-Sorsogon', NULL, '6', '15', 'Lot Only', NULL, NULL, 'IRS-Sorsogon, Blk 6, Lot 15', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:56:39', '2026-11-06 08:13:41'),
(659, 'Via Verde-Cabanatuan', '2', '3', '13', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 3, Lot 13', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:57:12', '2026-11-06 08:14:31'),
(662, 'VIA VERDE HEIGHTS', NULL, '1', '17', 'Tiarra Connovate Duplex', NULL, NULL, 'VIA VERDE HEIGHTS, Blk 1, Lot 17', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:58:06', '2026-11-06 08:14:12'),
(665, 'VHS-Iriga', NULL, '1', '8', 'Lot Only', NULL, NULL, 'VHS-Iriga, Blk 1, lot 8', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:58:47', '2026-11-06 08:14:09'),
(668, 'Via Verde-Cabanatuan', '2', '5', '33', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 33', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 07:59:20', '2026-11-06 08:14:42'),
(671, 'Priya Meridien', '1', '7', '51 & 53', 'Lot Only', NULL, NULL, 'Priya Meridien, Phase 1, Blk 7, Lot 51&53', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:00:24', '2026-11-06 08:13:51'),
(674, 'IRS-Sorsogon', NULL, '30', '1', 'Lot Only', NULL, NULL, 'IRS-Sorsogon, Blk 30, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:01:00', '2026-11-06 08:13:33'),
(677, 'Via Verde-Cabanatuan', '2', '5', '6', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 6', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:01:40', '2026-11-06 08:14:45'),
(680, 'Via Verde-Cabanatuan', '2', '5', '7', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 7', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:02:08', '2026-11-06 08:14:49'),
(683, 'Imperial South Meadows', '1', '37', '10', 'Bently', NULL, NULL, 'Imperial South Meadows, Phase 1, Blk 37, Lot 10', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:03:04', '2026-11-06 08:13:14'),
(686, 'IRHS-Estanza', NULL, '27', '33, 34', 'Inner Lot', NULL, NULL, 'IRHS-Estanza, Blk 27, Lot 33,34', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:03:46', '2026-11-06 08:13:23'),
(689, 'Via Verde-Cabanatuan', '2', '5', '26', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:04:20', '2026-11-06 08:14:39'),
(692, 'Via Verde-Cabanatuan', '2', '5', '17', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 17', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:06:24', '2026-11-06 08:14:37'),
(695, 'Via Verde-Cabanatuan', '2', '5', '1', 'Lira', NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:07:26', '2026-11-06 08:14:34'),
(698, 'VIA VERDE HEIGHTS', NULL, '10', '16', 'Deanna', NULL, NULL, 'VIA VERDE HEIGHTS, Blk 10, Lot 16', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:07:56', '2026-11-06 08:14:15'),
(701, 'VHS-Iriga', NULL, '1', '35', 'TIARRA TH', NULL, NULL, 'VHS-Iriga, Blk 1, Lot 35', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:09:22', '2026-11-06 08:14:05'),
(704, 'Rancho Imperial De Silang', '1', '24', '35', 'Diana Enhance', NULL, NULL, 'Rancho Imperial De Silang, Phase 1, Blk 24, Lot 35', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:10:19', '2026-11-06 08:13:56'),
(707, 'Tagaytay Meridian', '1', '2', '4', 'Baku', NULL, NULL, 'Tagaytay Meridian, Phase 1, Blk 2, Lot 4', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:10:56', '2026-11-06 08:13:59'),
(710, 'The Venetto Heights', '1', '7', '7A', 'Lot Only', NULL, NULL, 'The Venetto Heights, Phase 1, Blk 7, Lot 7A', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:11:32', '2026-11-06 08:14:02'),
(713, 'Via Verde-Trece Martires', '2B', '20', '34', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 34', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:12:42', '2026-11-06 08:15:11'),
(772, 'Rancho Imperial De Silang', '1', '36', '11', 'Lot Only', NULL, NULL, 'Rancho Imperial De Silang, Phase 1. Blk 36, Lot 11', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:15:59', '2026-11-06 08:28:36'),
(775, 'The Venetto Heights', '1', '3', '9A', 'Lot Only', NULL, NULL, 'The Venetto Heights, Phase 1, Blk 3, Lot 9A', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:16:42', '2026-11-06 08:28:38'),
(782, 'Via Verde-Trece Martires', '2B', '5', '26', 'Tiarra Duplex', NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 5, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:17:50', '2026-11-06 08:29:14'),
(785, 'Rancho Imperial De Silang', '1', '14', '36', 'Lot Only', NULL, NULL, 'Rancho Imperial De Silang, Phase 1, Blk 14, Lot 36', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:18:25', '2026-11-06 08:28:32'),
(788, 'Via Verde-Padre Garcia', '2', '31', '32', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 32', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:19:03', '2026-11-06 08:28:58'),
(791, 'Via Verde-Padre Garcia', '2', '31', '33', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 33', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:19:41', '2026-11-06 08:29:06'),
(794, 'Via Verde-Padre Garcia', '2', '31', '25', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 25', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:20:12', '2026-11-06 08:28:55'),
(797, 'Via Verde-Padre Garcia', '2', '31', '24', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31. Lot 24', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:21:10', '2026-11-06 08:29:09'),
(800, 'Via Verde-Padre Garcia', '2', '31', '20', 'Cielo Townhouse', NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 20', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:22:14', '2026-11-06 08:28:51'),
(803, 'Via Verde-Padre Garcia', NULL, '31', '54', 'Cielo Connovate TH', NULL, NULL, 'Via Verde-Padre Garcia, Blk 31, Lot 54', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:23:02', '2026-11-06 08:28:41'),
(806, 'ISS-Ligao', NULL, '2', '4', NULL, NULL, NULL, 'ISS-Ligao, Blk 2, Lot 4', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:24:31', '2026-11-06 08:29:31'),
(809, 'RIS-Pol', NULL, '1', '6&7', NULL, NULL, NULL, 'RIS-Pol, Blk 1, Lot 6&7', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:25:18', '2026-11-06 08:29:41'),
(812, 'VIA VERDE-HOMAPON', NULL, '6', '2', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 6, Lot 2', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:25:54', '2026-11-06 08:30:06'),
(817, 'VIA VERDE-HOMAPON', NULL, '6', '3', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 6, Lot 3', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:26:52', '2026-11-06 08:30:12'),
(820, 'VIA VERDE-HOMAPON', NULL, '6', '4', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 6, Lot 4', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:27:12', '2026-11-06 08:30:17'),
(823, 'IRHS-Estanza', NULL, '21', '35', NULL, NULL, NULL, 'IRHS-Estanza, Blk 21, Lot 35', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:27:52', '2026-11-06 08:30:38'),
(826, 'IRS-Sorsogon', NULL, '7', '7', NULL, NULL, NULL, 'IRS-Sorsogon, Blk 7, Lot 7', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:28:14', '2026-11-06 08:30:52'),
(863, 'VHS-Iriga', NULL, '7', '12', NULL, NULL, NULL, 'VHS-Iriga, Blk 7, Lot 12', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:33:35', '2026-11-06 08:41:25'),
(866, 'Imperial Central Plains', '1', '1', '15', NULL, NULL, NULL, 'Imperial Central Plains, Phase 1, Blk 1, Lot 15', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:34:04', '2026-11-06 08:41:13'),
(869, 'Via Verde-Cabanatuan', '2', '8', '36', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 36', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:34:35', '2026-11-06 08:44:15'),
(872, 'Via Verde-Cabanatuan', '2', '7', '45', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 45', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:35:03', '2026-11-06 08:44:28'),
(875, 'Via Verde-Cabanatuan', '2', '5', '113', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2. Blk 5, Lot 113', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:35:30', '2026-11-06 08:44:32'),
(878, 'Via Verde-Cabanatuan', '2', '7', '30', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 30', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:36:06', '2026-11-06 08:44:44'),
(881, 'Via Verde-Cabanatuan', '2', '5', '117', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 117', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:36:32', '2026-11-06 08:44:50'),
(884, 'Via Verde-Cabanatuan', '2', '10', '19', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 19', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:36:57', '2026-11-06 08:42:42'),
(887, 'Via Verde-Cabanatuan', '2', '5', '125', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 125', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:37:23', '2026-11-06 08:44:57'),
(890, 'Via Verde-Cabanatuan', '2', '5', '130', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 130', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:37:49', '2026-11-06 08:45:00'),
(893, 'Via Verde-Cabanatuan', '2', '3', '1', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 3, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:38:23', '2026-11-06 08:45:11'),
(896, 'Via Verde-Cabanatuan', '2', '10', '15&16', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 15&16', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:38:55', '2026-11-06 08:42:37'),
(899, 'Via Verde-Cabanatuan', '2', '5', '124', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 124', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:39:20', '2026-11-06 08:45:09'),
(902, 'Via Verde-Cabanatuan', '2', '7', '44', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 44', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:39:47', '2026-11-06 08:45:26'),
(905, 'Via Verde-Cabanatuan', '2', '8', '46', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 46', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:40:13', '2026-11-06 08:45:37'),
(908, 'Imperial Central Plains', '1', '8', '23', NULL, NULL, NULL, 'Imperial Central Plains, Phase 1, Blk 8, Lot 23', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:40:38', '2026-11-06 08:41:21'),
(952, 'Via Verde-Cabanatuan', '2', '8', '45', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 45', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:47:08', '2026-11-06 08:54:16'),
(955, 'Via Verde-Cabanatuan', '2', '5', '116', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 116', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:47:29', '2026-11-06 08:54:06'),
(958, 'Via Verde-Cabanatuan', '2', '5', '107', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 107', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:47:54', '2026-11-06 08:54:03'),
(961, 'Via Verde-Cabanatuan', '2', '5', '99', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 99', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:48:17', '2026-11-06 08:54:27'),
(964, 'Via Verde-Cabanatuan', NULL, '5', '1', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Blk 5, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:48:40', '2026-11-06 08:53:41'),
(967, 'Via Verde-Cabanatuan', '2', '10', '83', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 83', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:49:08', '2026-11-06 08:53:48'),
(970, 'Via Verde-Cabanatuan', '2', '4', '1', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 4, Lot 1', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:49:29', '2026-11-06 08:53:58'),
(973, 'Via Verde-Cabanatuan', '2', '5', '14', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 14', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:49:48', '2026-11-06 08:54:20'),
(976, 'Via Verde-Cabanatuan', '2', '5', '15', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 15', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:51:57', '2026-11-06 08:54:24'),
(979, 'Via Verde-Cabanatuan', '2', '7', '22', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 22', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:52:23', '2026-11-06 08:54:33'),
(982, 'Via Verde-Cabanatuan', '2', '10', '88', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 10, Lot 88', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:52:47', '2026-11-06 08:53:53'),
(985, 'Via Verde-Cabanatuan', '2', '8', '24', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 24', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:53:15', '2026-11-06 08:54:40'),
(1012, 'Marita Villas', NULL, '5', '15', NULL, NULL, NULL, 'Marita Villas, Blk 5, Lot 15', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:56:07', '2026-11-06 09:03:37'),
(1015, 'Marita Villas', NULL, '5', '17', NULL, NULL, NULL, 'Marita Villas, Blk 5, Lot 17', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:56:29', '2026-11-06 09:03:39'),
(1018, 'The Venetto Heights', '1', '3', '8A', NULL, NULL, NULL, 'The Venetto Heights, Phase 1, Blk 3, Lot 8A', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:56:59', '2026-11-06 09:03:55'),
(1021, 'Via Verde-Padre Garcia', '2', '31', '35', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 35', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 08:57:29', '2026-11-06 09:04:17'),
(1024, 'Via Verde-Trece Martires', '2B', '20', '48', NULL, NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 48', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 09:00:08', '2026-11-06 09:04:45'),
(1027, 'Via Verde-Trece Martires', '2B', '17', '26', NULL, NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 17, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 09:02:21', '2026-11-06 09:04:47'),
(1030, 'Via Verde-Padre Garcia', '2', '31', '18', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 18', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 09:02:53', '2026-11-06 09:05:02'),
(1033, 'Via Verde-Sto.Tomas', '3', '9', '22', NULL, NULL, NULL, 'Via Verde-Sto.Tomas, Phase 3, Blk 9, Lot 22', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-06 09:03:19', '2026-11-06 09:05:13'),
(1088, 'Via Verde-Trece Martires', '2B', '20', '33', NULL, NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 20, Lot 33', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 06:35:47', '2026-11-09 07:09:17'),
(1092, 'Via Verde-Padre Garcia', '2', '31', '40', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 40', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 06:36:24', '2026-11-09 07:08:53'),
(1095, 'Via Verde-Padre Garcia', '2', '31', '41', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 41', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 06:36:47', '2026-11-09 07:08:56'),
(1098, 'Via Verde-Padre Garcia', '2', '31', '22', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 22', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 06:37:09', '2026-11-09 07:09:01'),
(1101, 'Via Verde-Padre Garcia', '2', '31', '21', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 21', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 06:37:29', '2026-11-09 07:08:35'),
(1104, 'Via Verde-Padre Garcia', '2', '31', '23', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 23', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:07:31', '2026-11-09 07:08:40'),
(1107, 'Via Verde-Padre Garcia', NULL, '31', '27', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Blk 31, Lot 27', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:07:59', '2026-11-09 07:08:31'),
(1128, 'VIA VERDE HEIGHTS', NULL, '6', '15', NULL, NULL, NULL, 'VIA VERDE HEIGHTS, Blk 6, Lot 15', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:10:36', '2026-11-09 07:18:39'),
(1131, 'VIA VERDE HEIGHTS', NULL, '6', '16', NULL, NULL, NULL, 'VIA VERDE HEIGHTS, Blk 6, Lot 16', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:10:57', '2026-11-09 07:18:40'),
(1135, 'VIA VERDE-HOMAPON', NULL, '21', '18', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 21, Lot 18', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:11:46', '2026-11-09 07:18:51'),
(1138, 'IRHS-Estanza', NULL, '29', '36', NULL, NULL, NULL, 'IRHS-Estanza, Blk 29, Lot 36', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:12:24', '2026-11-09 07:18:59'),
(1141, 'IRHS-Estanza', NULL, '29', '37', NULL, NULL, NULL, 'IRHS-Estanza, Blk 29, Lot 37', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:12:44', '2026-11-09 07:19:00'),
(1144, 'VHS-Iriga', NULL, '8', '14', NULL, NULL, NULL, 'VHS-Iriga, Blk 8, Lot 14', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:13:00', '2026-11-09 07:19:08'),
(1147, 'Via Verde-Cabanatuan', '2', '8', '47', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 8, Lot 47', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:13:23', '2026-11-09 07:19:40'),
(1150, 'Via Verde-Cabanatuan', '2', '7', '28', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 28', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:13:51', '2026-11-09 07:19:42'),
(1153, 'Via Verde-Cabanatuan', '2', '7', '23', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 7, Lot 23', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:14:13', '2026-11-09 07:19:44'),
(1156, 'Via Verde-Cabanatuan', '2', '5', '87', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 87', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:14:38', '2026-11-09 07:19:52'),
(1159, 'Via Verde-Cabanatuan', '2', '5', '25', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 25', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:15:01', '2026-11-09 07:19:54'),
(1162, 'Via Verde-Padre Garcia', '1', '31', '26', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 1. Blk 31, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:15:29', '2026-11-09 07:20:19'),
(1165, 'Priya Meridien', '1', '11', '30 & 32', NULL, NULL, NULL, 'Priya Meridien, Phase 1, Blk 11, Lot 30&31', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:15:57', '2026-11-09 07:20:29'),
(1168, 'Priya Meridien', '1', '14A', '5', NULL, NULL, NULL, 'Priya Meridien, phase 1, Blk 14A, Lot 5', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:16:26', '2026-11-09 07:20:31'),
(1171, 'Via Verde-Padre Garcia', '2', '31', '37', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 37', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:16:48', '2026-11-09 07:20:42'),
(1174, 'Tagaytay Meridian', '1', '4', '10', NULL, NULL, NULL, 'Tagaytay Meridian, Phase 1, Blk 4, Lot 10', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:17:11', '2026-11-09 07:20:51'),
(1177, 'Imperial South Meadows', '1', '5', '8', NULL, NULL, NULL, 'Imperial South Meadows, Phase 1, Blk 5, Lot 8', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:17:35', '2026-11-09 07:20:57'),
(1180, 'Imperial Central Plains', '1', '3', '9', NULL, NULL, NULL, 'Imperial Central Plains, Phase 1, Blk 3, Lot 9', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:17:58', '2026-11-09 07:21:03'),
(1183, 'Via Verde-Cabanatuan', '2', '5', '80', NULL, NULL, NULL, 'Via Verde-Cabanatuan, Phase 2, Blk 5, Lot 80', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:18:20', '2026-11-09 07:20:02'),
(1237, 'Imperial South Meadows', '1C', '2', '6', NULL, NULL, NULL, 'Imperial South Meadows, Phase 2C, Blk 2, Lot 6', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:22:09', '2026-11-09 13:04:42'),
(1240, 'Via Verde-Trece Martires', '2B', '11', '3', NULL, NULL, NULL, 'Via Verde-Trece Martires, Phase 2B, Blk 11, Lot 3', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:22:35', '2026-11-09 13:04:52'),
(1243, 'Via Verde-Padre Garcia', '2', '31', '56', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 56', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:22:56', '2026-11-09 13:05:09'),
(1246, 'Via Verde-Padre Garcia', '2', '31', '34', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 34', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:23:16', '2026-11-09 13:05:16'),
(1249, 'Via Verde-Padre Garcia', '2', '31', '52', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 52', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:23:40', '2026-11-09 13:05:20'),
(1252, 'Via Verde-Padre Garcia', '2', '31', '49', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 49', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:24:11', '2026-11-09 13:05:32'),
(1255, 'Via Verde-Padre Garcia', '2', '31', '44', NULL, NULL, NULL, 'Via Verde-Padre Garcia, Phase 2, Blk 31, Lot 44', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 07:24:32', '2026-11-09 13:05:34'),
(1258, 'VIA VERDE-HOMAPON', NULL, '24', '10', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 24, Lot 10', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 09:00:33', '2026-11-09 13:05:58'),
(1264, 'VIA VERDE-HOMAPON', NULL, '10', '17', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 10, Lot 17', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 13:02:19', '2026-11-09 13:05:55'),
(1267, 'VIA VERDE-HOMAPON', NULL, '6', '9', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 6, Lot 9', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 13:02:51', '2026-11-09 13:06:02'),
(1270, 'VIA VERDE-HOMAPON', NULL, '21', '26', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 21, Lot 26', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 13:03:13', '2026-11-09 13:05:57'),
(1273, 'VIA VERDE-HOMAPON', NULL, '29', '51', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Blk 29, Lot 51', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 13:03:35', '2026-11-09 13:06:00'),
(1276, 'VIA VERDE-HOMAPON', NULL, 'Block 12', 'Lot 27', NULL, NULL, NULL, 'VIA VERDE-HOMAPON, Block 12, Lot 27', 'SOLD', NULL, NULL, NULL, NULL, '2026-11-09 13:04:04', '2026-11-09 13:06:03');

-- --------------------------------------------------------

--
-- Table structure for table `prospect_clients`
--

CREATE TABLE `prospect_clients` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `cellphone_number` varchar(50) DEFAULT NULL,
  `client_address` varchar(500) DEFAULT NULL,
  `property_address` varchar(500) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `project_phase` varchar(120) DEFAULT NULL,
  `block_no` varchar(50) DEFAULT NULL,
  `lot_no` varchar(50) DEFAULT NULL,
  `model_type` varchar(120) DEFAULT NULL,
  `lot_area` varchar(100) DEFAULT NULL,
  `floor_area` varchar(100) DEFAULT NULL,
  `source` enum('HOLDING','RESERVATION') NOT NULL DEFAULT 'HOLDING',
  `holding_fee_id` int(11) DEFAULT NULL,
  `reservation_fee_id` int(11) DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prospect_clients`
--

INSERT INTO `prospect_clients` (`id`, `full_name`, `email`, `cellphone_number`, `client_address`, `property_address`, `project_name`, `project_phase`, `block_no`, `lot_no`, `model_type`, `lot_area`, `floor_area`, `source`, `holding_fee_id`, `reservation_fee_id`, `contract_id`, `created_at`, `updated_at`) VALUES
(1, 'Maria Santos', 'maria.santos@example.com', '09171112222', 'Cebu City', 'Test Village - Block 7 Lot 3', 'Test Village', NULL, NULL, NULL, NULL, NULL, NULL, 'HOLDING', 3, 1, 47, '2026-10-09 09:01:32', '2026-10-09 09:10:23'),
(2, 'Juan Dela Cruz', 'juan.cruz@example.com', '09175558888', 'Paranaque City', 'Rose St Phase 2 - Block 9 Lot 11', 'Rose St Phase 2', NULL, NULL, NULL, NULL, NULL, NULL, 'RESERVATION', 5, 3, 48, '2026-10-09 09:23:46', '2026-10-09 09:25:26'),
(3, 'Regression Tester', 'regression@example.com', '09172223333', NULL, 'Sunrise Village - Block 2 Lot 9', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'HOLDING', 8, NULL, NULL, '2026-10-09 09:38:37', '2026-10-09 09:38:37');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_fees`
--

CREATE TABLE `reservation_fees` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `property_unit_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `holding_fee_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `or_number` varchar(100) DEFAULT NULL,
  `status` enum('PENDING','PAID','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `remarks` text DEFAULT NULL,
  `proof_path` varchar(500) DEFAULT NULL,
  `proof_name` varchar(255) DEFAULT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prospect_client_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_fees`
--

INSERT INTO `reservation_fees` (`id`, `contract_id`, `property_unit_id`, `client_id`, `holding_fee_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `or_number`, `status`, `remarks`, `proof_path`, `proof_name`, `processed_by`, `created_at`, `updated_at`, `prospect_client_id`) VALUES
(1, 47, 394, NULL, 3, 25000.00, 'GCash', '2026-10-09', NULL, 'OR-TEST-001', 'PAID', NULL, NULL, NULL, '2', '2026-10-09 09:01:40', '2026-10-09 09:10:23', 1),
(2, 47, 394, NULL, 4, 25000.00, 'To be collected', '2026-11-01', NULL, NULL, 'PENDING', 'Set at contract creation.', NULL, NULL, '2', '2026-10-09 09:10:23', '2026-10-09 09:10:23', NULL),
(3, 48, 406, NULL, 5, 30000.00, 'Over-the-Counter Cashier', '2026-10-09', NULL, 'OR-2026-1002', 'PAID', NULL, NULL, NULL, '2', '2026-10-09 09:23:59', '2026-10-09 09:24:32', 2),
(4, 48, 406, NULL, 6, 30000.00, 'To be collected', '2026-11-15', NULL, NULL, 'PENDING', 'Set at contract creation.', NULL, NULL, '2', '2026-10-09 09:24:32', '2026-10-09 09:24:32', NULL),
(5, 49, 419, 16, 7, 25000.00, 'GCash / Maya', '2026-10-09', NULL, 'OR-REG-003', 'PAID', NULL, NULL, NULL, '2', '2026-10-09 09:38:48', '2026-10-09 09:38:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `soa_settings`
--

CREATE TABLE `soa_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL DEFAULT 'Imperial Homes',
  `company_address` varchar(500) DEFAULT NULL,
  `company_contact` varchar(255) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `penalty_rate_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `important_notes` mediumtext DEFAULT NULL,
  `noted_by_name` varchar(255) DEFAULT NULL,
  `noted_by_position` varchar(255) DEFAULT NULL,
  `noted_by_contact` varchar(255) DEFAULT NULL,
  `validity_days` smallint(5) UNSIGNED NOT NULL DEFAULT 7,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soa_settings`
--

INSERT INTO `soa_settings` (`id`, `company_name`, `company_address`, `company_contact`, `logo_path`, `penalty_rate_percent`, `important_notes`, `noted_by_name`, `noted_by_position`, `noted_by_contact`, `validity_days`, `updated_by`, `updated_at`) VALUES
(1, 'Imperial Homes', 'Imperial Homes Corporation', 'Contact the IHC Billing Office for assistance.', 'img/ihc logo.png', 0.0000, 'Please settle this Statement of Account on or before the due date. All amounts are computed from the current IHC ledger. Penalties and interest, when applicable, follow the configured company rate and the payment status shown in this document.', 'Authorized IHC Representative', 'Billing Manager', 'IHC Billing Office', 7, NULL, '2026-09-25 07:19:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `additional_charges`
--
ALTER TABLE `additional_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_additional_charge_contract` (`contract_id`,`status`,`due_date`),
  ADD KEY `idx_additional_charge_type` (`charge_type`);

--
-- Indexes for table `additional_equity_payments`
--
ALTER TABLE `additional_equity_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_additional_equity_contract` (`contract_id`,`status`,`due_date`),
  ADD KEY `idx_additional_equity_payment` (`payment_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_contract` (`contract_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `bank_rates`
--
ALTER TABLE `bank_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bank_rates_name` (`bank_name`);

--
-- Indexes for table `business_rules`
--
ALTER TABLE `business_rules`
  ADD PRIMARY KEY (`rule_key`);

--
-- Indexes for table `client_accounts`
--
ALTER TABLE `client_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_client_accounts_email` (`email`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holding_fees`
--
ALTER TABLE `holding_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_holding_fees_officer` (`officer_id`),
  ADD KEY `idx_holding_fees_contract` (`contract_id`),
  ADD KEY `idx_hf_contract` (`contract_id`),
  ADD KEY `idx_hf_unit` (`property_unit_id`),
  ADD KEY `idx_hf_status_exp` (`status`,`expiration_date`),
  ADD KEY `idx_hf_created` (`created_at`),
  ADD KEY `idx_hf_prospect` (`prospect_client_id`);

--
-- Indexes for table `notifications_logs`
--
ALTER TABLE `notifications_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_reminder_lookup` (`contract_id`,`channel`,`reminder_type`,`status`,`sent_at`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_officers_email` (`email`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_allocation` (`payment_id`,`installment_kind`,`installment_no`),
  ADD KEY `idx_payment_alloc_contract` (`contract_id`),
  ADD KEY `idx_payment_alloc_schedule` (`contract_id`,`installment_kind`,`installment_no`);

--
-- Indexes for table `payment_or_counters`
--
ALTER TABLE `payment_or_counters`
  ADD PRIMARY KEY (`series_code`,`series_year`);

--
-- Indexes for table `property_units`
--
ALTER TABLE `property_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_property_units_label` (`display_label`),
  ADD KEY `idx_property_units_status` (`status`);

--
-- Indexes for table `prospect_clients`
--
ALTER TABLE `prospect_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prospect_email` (`email`),
  ADD KEY `idx_prospect_contract` (`contract_id`);

--
-- Indexes for table `reservation_fees`
--
ALTER TABLE `reservation_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rf_contract` (`contract_id`),
  ADD KEY `idx_rf_prospect` (`prospect_client_id`);

--
-- Indexes for table `soa_settings`
--
ALTER TABLE `soa_settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `additional_charges`
--
ALTER TABLE `additional_charges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `additional_equity_payments`
--
ALTER TABLE `additional_equity_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=302;

--
-- AUTO_INCREMENT for table `bank_rates`
--
ALTER TABLE `bank_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=883;

--
-- AUTO_INCREMENT for table `client_accounts`
--
ALTER TABLE `client_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `holding_fees`
--
ALTER TABLE `holding_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications_logs`
--
ALTER TABLE `notifications_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `property_units`
--
ALTER TABLE `property_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1308;

--
-- AUTO_INCREMENT for table `prospect_clients`
--
ALTER TABLE `prospect_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservation_fees`
--
ALTER TABLE `reservation_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
