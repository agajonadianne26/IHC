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
-- Database: `ihc`
--

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
(6, 'agajonadianne@gmail.com', '$2y$10$biMS0rfQJ91FT3P.CWdg3.iyOlMBBgyiS6cHnZ8tFVqgdrtIO.Rny', 'Marzia Paloma', '09096890140', '2026-09-22 03:11:29', '2026-09-22 03:11:29');

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

INSERT INTO `contracts` (`id`, `client_name`, `email`, `cellphone_number`, `total_contract_price`, `downpayment`, `installment_terms`, `start_date`, `created_at`, `property_address`, `officer_id`) VALUES
(8, 'jimmy fuentes', 'jeremypaulcantalejo28@gmail.com', '0909337463746', 3500000.00, 2000000.00, 60, '2026-09-24', '2026-09-10 06:52:29', 'laguna', '2'),
(9, 'lara pascual', 'yanniedianne26@gmail.com', '09865432761', 4500000.00, 2000000.00, 65, '2026-10-01', '2026-09-10 07:38:19', 'muntinlupa', '2'),
(10, 'cynthia santos', 'cynthias@gmail.com', '09231178921', 2400000.00, 1000000.00, 50, '2026-09-30', '2026-09-10 08:21:04', 'alabang', '2'),
(13, 'Trina Madrigal', 'yanniedianne26@gmail.com', '09096890140', 3500000.00, 2000000.00, 48, '2026-09-19', '2026-09-16 09:05:49', 'block 5 lot 20 Madrigal Alabang Muntinlupa City', '2'),
(14, 'paul soriano', 'jeremypaulcantalejo28@gmail.com', '0933675436', 4000000.00, 3500000.00, 48, '2026-09-20', '2026-09-17 01:24:13', 'block 6 lot 15 Alabang muntinlupa city', '2'),
(19, 'Marzia Paloma', 'agajonadianne@gmail.com', '09096890140', 3000000.00, 1500000.00, 60, '2026-09-26', '2026-09-22 03:11:29', 'block 5 lot 20 Madrigal Ayala Alabang Muntinlupa City', '2');

-- --------------------------------------------------------

--
-- Table structure for table `holding_fees`
--
-- Payments > Holding Fees (previously localStorage only). php/api_holding_fees.php
-- creates this table on demand if it is missing.
--

CREATE TABLE `holding_fees` (
  `id` int(11) NOT NULL,
  `officer_id` varchar(100) DEFAULT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) NOT NULL,
  `property_address` varchar(500) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) NOT NULL,
  `payment_date` date NOT NULL,
  `or_number` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `proof_name` varchar(255) DEFAULT NULL,
  `proof_data_url` mediumtext DEFAULT NULL,
  `proof_is_image` tinyint(1) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
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
(1, '7', 'agajonadianne@gmail.com', 'ack_email', NULL, NULL, 'Client acknowledged payment reminder for IHC-7', 'sent', '2026-09-16 09:38:28', NULL),
(2, 'CON-13', 'yanniedianne26@gmail.com', 'email', 'payment_receipt', '2026-09-17', 'Payment Receipt: ₱2,000,000.00 received for CON-13', 'sent', '2026-09-16 18:43:05', NULL),
(3, 'IHC-14', 'jeremypaulcantalejo28@gmail.com', 'email', 'due_soon', '2026-09-20', 'Reminder: Payment of ₱3,500,000.00 for IHC-14 due in 3 day(s)', 'sent', '2026-09-17 09:24:21', NULL),
(4, 'CON-14', 'jeremypaulcantalejo28@gmail.com', 'email', 'payment_receipt', '2026-09-19', 'Payment Receipt: ₱3,500,000.00 received for CON-14', 'sent', '2026-09-17 09:24:49', NULL);

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
-- Table structure for table `payment_or_counters`
--

CREATE TABLE `payment_or_counters` (
  `series_code` varchar(3) NOT NULL,
  `series_year` smallint(5) unsigned NOT NULL,
  `last_number` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `soa_settings`
--

CREATE TABLE `soa_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `company_name` varchar(255) NOT NULL DEFAULT 'Imperial Homes',
  `company_address` varchar(500) DEFAULT NULL,
  `company_contact` varchar(255) DEFAULT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `penalty_rate_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `important_notes` mediumtext DEFAULT NULL,
  `noted_by_name` varchar(255) DEFAULT NULL,
  `noted_by_position` varchar(255) DEFAULT NULL,
  `noted_by_contact` varchar(255) DEFAULT NULL,
  `validity_days` smallint(5) unsigned NOT NULL DEFAULT 7,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `soa_settings` (`id`,`company_name`,`company_address`,`company_contact`,`logo_path`,`penalty_rate_percent`,`important_notes`,`noted_by_name`,`noted_by_position`,`noted_by_contact`,`validity_days`) VALUES (1,'Imperial Homes','Imperial Homes Corporation','Contact the IHC Billing Office for assistance.','img/ihc logo.png',0.0000,'Please settle this Statement of Account on or before the due date. All amounts are computed from the current IHC ledger. Penalties and interest, when applicable, follow the configured company rate and the payment status shown in this document.','Authorized IHC Representative','Billing Manager','IHC Billing Office',7);

-- --------------------------------------------------------

--
-- Table structure for table `additional_charges`
--

CREATE TABLE `additional_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_additional_charge_contract` (`contract_id`,`status`,`due_date`),
  KEY `idx_additional_charge_type` (`charge_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `additional_equity_payments`
--

CREATE TABLE `additional_equity_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_additional_equity_contract` (`contract_id`,`status`,`due_date`),
  KEY `idx_additional_equity_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `installment_kind` varchar(20) NOT NULL,
  `installment_no` int(11) DEFAULT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_allocation` (`payment_id`,`installment_kind`,`installment_no`),
  KEY `idx_payment_alloc_contract` (`contract_id`),
  KEY `idx_payment_alloc_schedule` (`contract_id`,`installment_kind`,`installment_no`)
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
  `installment_no` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `contract_id`, `amount`, `payment_method`, `date_collected`, `or_number`, `remarks`, `posted_by`, `created_at`) VALUES
(3, 'CON-9', 2000000.00, 'GCash / Maya', '2026-09-29', 'OR-123456-789-0', 'PAID', '2', '2026-09-10 08:06:10'),
(4, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:11:03'),
(5, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:11:07'),
(6, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:11:10'),
(7, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:11:42'),
(8, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:13:54'),
(9, 'CON-7', 1000000.00, 'Over-the-Counter Cashier', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:14:20'),
(10, 'CON-7', 1000000.00, 'Over-the-Counter Cashier', '2026-09-16', 'OR-123456-789-1', 'PAID', '2', '2026-09-10 08:14:33'),
(11, 'CON-10', 1000000.00, 'Over-the-Counter Cashier', '2026-09-15', 'OR-123456-789-3', 'paid', '2', '2026-09-10 08:36:35'),
(12, 'CON-8', 2000000.00, 'GCash / Maya', '2026-09-16', 'OR-1123456-9786', 'semi paid', '2', '2026-09-11 02:28:31'),
(13, 'CON-10', 1000000.00, 'Over-the-Counter Cashier', '2026-09-24', 'OR-123456-0937', 'PAID', '2', '2026-09-11 02:32:07'),
(14, 'CON-8', 2000000.00, 'GCash / Maya', '2026-09-16', 'OR-12345-6879-5', 'PAID', '2', '2026-09-11 02:32:53'),
(15, 'CON-7', 1000000.00, 'Bank Wire / Online Deposit', '2026-09-24', 'OR12345-785', 'test', '2', '2026-09-11 03:10:10'),
(16, 'CON-8', 2000000.00, 'Over-the-Counter Cashier', '2026-09-15', 'OR12345667890', 'PAID', '2', '2026-09-14 06:39:53'),
(17, 'CON-9', 2000000.00, 'Post-Dated Check (PDC)', '2026-09-23', '34243242342423424', 'DSFSD', '2', '2026-09-14 06:40:29'),
(18, 'CON-7', 1000000.00, 'Over-the-Counter Cashier', '2026-09-17', '29473792749729479724729', 'paid', '2', '2026-09-16 07:31:17'),
(19, 'CON-13', 2000000.00, 'Bank Wire / Online Deposit', '2026-09-17', '32434353453453', 'paid', '2', '2026-09-16 10:42:46'),
(20, 'CON-14', 3500000.00, 'Post-Dated Check (PDC)', '2026-09-19', '43232432343454', 'paid', '2', '2026-09-17 01:24:41');

--
-- Indexes for dumped tables
--

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
  ADD KEY `idx_holding_fees_contract` (`contract_id`);

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
-- Indexes for table `payment_or_counters`
--
ALTER TABLE `payment_or_counters`
  ADD PRIMARY KEY (`series_code`,`series_year`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client_accounts`
--
ALTER TABLE `client_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `holding_fees`
--
ALTER TABLE `holding_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications_logs`
--
ALTER TABLE `notifications_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
