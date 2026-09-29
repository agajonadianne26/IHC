-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 03:36 AM
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
(13, 'payment.created', 24, NULL, NULL, 22, NULL, NULL, '2', 'Ana Reyes', '{\"contractId\":\"CON-24\",\"amount\":1500000,\"method\":\"Over-the-Counter Cashier\",\"orNumber\":\"OR-DP-2026-00001\",\"paymentKind\":\"downpayment\",\"checkNumber\":null,\"externalReference\":null,\"receiptRequested\":true,\"applicationMode\":\"current\",\"allocations\":[{\"kind\":\"downpayment\",\"no\":null,\"amount\":1500000}],\"allocationSummary\":{\"principalApplied\":1500000,\"additionalEquityApplied\":0,\"installmentsCovered\":1,\"applicationMode\":\"current\",\"target\":\"downpayment\"},\"dateCollected\":\"2026-09-27\",\"paymentId\":34}', '2026-09-25 08:02:13');

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
(10, 'agajonadianne@gmail.com', '$2y$10$JeYNW4R5CzcDJD5QXURCOurqC32qZY2dE609yER1ezvEzIEuitSFi', 'Daniela Fuentes', '09096890140', '2026-09-25 06:33:19', '2026-09-25 06:33:19');

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
(24, 'Daniela Fuentes', 'agajonadianne@gmail.com', '09096890140', 3000000.00, 1500000.00, 48, '2026-09-29', '2026-09-25 06:33:19', 'block 1 lot 5 Sampaguita, Madrigal Alabang Muntinlupa City', '2', 'lump', NULL, 'Pag-IBIG', 5.50, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(21, '24', 'agajonadianne@gmail.com', 'email', 'manual', '2026-10-29', 'Payment Reminder / SOA Summary: Installment Payment #1 — ₱31,250.00 for 24 due in 34 day(s)', 'sent', '2026-09-25 18:00:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'clerk',
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `full_name`, `role`, `email`, `password_hash`) VALUES
(1, 'Jeremy Cantalejo', 'admin', 'admin@ihc.com', '$2y$10$BL9dLew5Rpc5hX/HKk3l7O4/6iqVbAlm3J7ubMOYR4JwgjwIOfSBi'),
(2, 'Ana Reyes', 'clerk', 'ana@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'),
(3, 'Mark Cruz', 'clerk', 'mark@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe'),
(4, 'Jessica Lim', 'clerk', 'jessica@ihc.com', '$2y$10$6UYqdMxkJc8llZc34FO7A.WTxPliEGwnIHVv/kL9whScON4McXXbe');

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
(34, 'CON-24', 1500000.00, 'Over-the-Counter Cashier', '2026-09-27', 'OR-DP-2026-00001', 'PAID DP', '2', '2026-09-25 08:02:13', NULL, 'OR-DP-2026-00001', 'downpayment', NULL, NULL, 1);

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
(20, 34, 24, 'downpayment', NULL, 1500000.00, 1500000.00, 0.00, 0.00, '2026-09-25 08:02:13');

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
('DP', 2026, 1, '2026-09-25 09:10:46'),
('INS', 2026, 0, '2026-09-25 09:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `property_units`
--

CREATE TABLE `property_units` (
  `id` int(11) NOT NULL,
  `project` varchar(255) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `unit_number` varchar(100) DEFAULT NULL,
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

INSERT INTO `property_units` (`id`, `project`, `building`, `unit_number`, `display_label`, `status`, `current_client_id`, `current_contract_id`, `current_holding_fee_id`, `current_reservation_fee_id`, `created_at`, `updated_at`) VALUES
(22, NULL, NULL, NULL, 'block 1 lot 5 Sampaguita, Madrigal Alabang Muntinlupa City', 'AVAILABLE', NULL, 24, NULL, NULL, '2026-09-25 06:33:20', '2026-09-25 06:33:20');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_fees`
--

CREATE TABLE `reservation_fees` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD KEY `idx_hf_created` (`created_at`);

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
-- Indexes for table `reservation_fees`
--
ALTER TABLE `reservation_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rf_contract` (`contract_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `bank_rates`
--
ALTER TABLE `bank_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- AUTO_INCREMENT for table `client_accounts`
--
ALTER TABLE `client_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `holding_fees`
--
ALTER TABLE `holding_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications_logs`
--
ALTER TABLE `notifications_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `property_units`
--
ALTER TABLE `property_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `reservation_fees`
--
ALTER TABLE `reservation_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
