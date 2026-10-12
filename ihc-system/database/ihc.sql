-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2026 at 02:02 AM
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
(43, 'holding_fee.converted', 49, 7, 5, 419, 'PAID', 'CONVERTED', '2', 'Ana Reyes', '{\"reservation_id\":5}', '2026-10-09 09:38:48');

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
(8, NULL, NULL, NULL, 422, 'Regression Tester', 'Sunrise Village - Block 2 Lot 9', 4000.00, 'Cash', '2026-10-09', NULL, 'OR-REG-002', '2026-10-09', '2026-11-08', 'PAID', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 09:38:37', '2026-10-09 09:38:37', 3);

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
(25, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-12-29', 'Payment Reminder / SOA Summary: Installment Payment #3 — ₱31,250.00 for 24 due in 81 day(s)', 'sent', '2026-10-09 10:19:39', NULL);

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
(3, 'Mark Cruz', 'clerk', 'mark@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe', 0),
(4, 'Jessica Lim', 'clerk', 'jessica@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe', 0);

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
(22, NULL, NULL, '1', '5', NULL, NULL, NULL, 'block 1 lot 5 Sampaguita, Madrigal Alabang Muntinlupa City', 'AVAILABLE', NULL, 24, NULL, NULL, '2026-09-25 06:33:20', '2026-10-01 23:58:38'),
(193, 'Soldier\'s Hills Subdivision', 'Phase 3', 'Block 12', 'Lot 5', NULL, '150', '120', 'Phase 3, Block 12, Lot 5, Mabuhay Street, Soldier\'s Hills Subdivision, Barangay Molino VI, Bacoor City, Cavite', 'AVAILABLE', NULL, 45, NULL, NULL, '2026-10-02 05:04:50', '2026-10-02 05:04:50'),
(347, 'Soldier\'s Hills Subdivision', 'Phase 2', 'Block 5', 'Lot 6', '3 storey', '150', '120', 'Block 5 Lot 6, Phase 2, Soldier\'s Hills Subdivision', 'AVAILABLE', NULL, 46, NULL, NULL, '2026-10-09 00:31:36', '2026-10-09 00:31:36'),
(394, 'Test Village', NULL, NULL, NULL, NULL, NULL, NULL, 'Test Village - Block 7 Lot 3', 'RESERVED', NULL, 47, NULL, 1, '2026-10-09 09:01:32', '2026-10-09 09:10:23'),
(406, 'Rose St Phase 2', NULL, NULL, NULL, NULL, NULL, NULL, 'Rose St Phase 2 - Block 9 Lot 11', 'RESERVED', NULL, 48, NULL, 3, '2026-10-09 09:23:46', '2026-10-09 09:24:32'),
(419, 'Greenfield', NULL, NULL, NULL, NULL, NULL, NULL, 'Greenfield - Block 1 Lot 5', 'RESERVED', NULL, 49, NULL, 5, '2026-10-09 09:37:57', '2026-10-09 09:38:48'),
(422, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Sunrise Village - Block 2 Lot 9', 'ON HOLD', NULL, NULL, 8, NULL, '2026-10-09 09:38:37', '2026-10-09 09:38:37');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `bank_rates`
--
ALTER TABLE `bank_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=730;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=436;

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
