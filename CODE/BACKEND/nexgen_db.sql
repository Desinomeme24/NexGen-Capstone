-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2026 at 07:58 AM
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
-- Database: `nexgen_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts_masterlist`
--

CREATE TABLE `accounts_masterlist` (
  `id` int(11) NOT NULL,
  `employee_no` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_receivable`
--

CREATE TABLE `accounts_receivable` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('Unpaid','Partially Paid','Paid','Overdue') NOT NULL DEFAULT 'Unpaid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `accounts_receivable`
--
DELIMITER $$
CREATE TRIGGER `trg_accounts_receivable_after_update` AFTER UPDATE ON `accounts_receivable` FOR EACH ROW BEGIN
    DECLARE v_remarks VARCHAR(255);

    CASE NEW.status
        WHEN 'Paid' THEN
            SET v_remarks = CONCAT(
                'Payment completed. Full balance cleared. Notes: ',
                IFNULL(NEW.notes, 'N/A')
            );
        WHEN 'Partially Paid' THEN
            SET v_remarks = CONCAT(
                'Partial payment applied. Remaining balance: ₱',
                FORMAT(NEW.balance_due, 2)
            );
        WHEN 'Overdue' THEN
            SET v_remarks = CONCAT(
                'Payment recorded but account is overdue. Balance: ₱',
                FORMAT(NEW.balance_due, 2)
            );
        ELSE
            SET v_remarks = 'Receivable record updated';
    END CASE;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `previous_hash` char(64) DEFAULT NULL,
  `log_hash` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `description`, `previous_hash`, `log_hash`, `ip_address`, `user_agent`, `created_at`) VALUES
(52, 1, 'approve_request', 'registration_request', 18, 'Approved request #18 (REQ-20260806-0001) and created user #2', '0000000000000000000000000000000000000000000000000000000000000000', 'ac6afa636771762170982fe1ba7325b8a9fe008e15149310ef98800b522b8684', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-06 07:10:22'),
(53, 1, 'approve_request', 'registration_request', 19, 'Approved request #19 (REQ-20260806-0002) and created user #3', 'ac6afa636771762170982fe1ba7325b8a9fe008e15149310ef98800b522b8684', '6e9839b7eab47c677107b95c42acfe9cd59d527f70320dacbcd93c1999acdf4c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-06 07:19:13'),
(54, 1, 'approve_request', 'registration_request', 20, 'Approved request #20 (REQ-20260807-0001) and created user #4', '6e9839b7eab47c677107b95c42acfe9cd59d527f70320dacbcd93c1999acdf4c', '03f68c930ffbe5e839e1f1f79da4d84681ca7f7704f843b8994dde296f51aa0b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-06 16:37:06');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_name` varchar(100) NOT NULL COMMENT 'Affected table',
  `action` varchar(30) NOT NULL COMMENT 'INSERT | UPDATE | ARCHIVE | PAYMENT',
  `record_id` int(11) NOT NULL COMMENT 'Primary key of the affected row',
  `changed_by` int(11) DEFAULT NULL COMMENT 'User ID from @audit_user_id',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP from @audit_ip',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Row snapshot before the change' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Row snapshot after the change' CHECK (json_valid(`new_values`)),
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit footprint for all critical table operations in NexGen';

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `business_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `business_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `business_name`, `business_type`, `business_address`, `created_at`, `business_code`) VALUES
(1, 'Concentrix', 'Other SME', 'Pasig City', '2026-08-06 07:10:22', 'SME-C842D3'),
(2, 'Omri Bakery Shop', 'Retail Store', 'Cainta, Rizal', '2026-08-06 07:19:13', 'SME-A96981');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `business_id`, `category_name`, `created_at`) VALUES
(11, 1, 'Instant Noodle', '2026-08-06 07:12:54'),
(12, 2, 'Canned Goods', '2026-08-06 15:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `on_order_level` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `product_image` varchar(255) DEFAULT 'uploads/products/default.png',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `business_id`, `product_code`, `product_name`, `category_id`, `brand`, `unit`, `cost_price`, `selling_price`, `stock_quantity`, `reorder_level`, `on_order_level`, `expiry_date`, `product_image`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(34, 1, '01', 'Cup Noodles Sotanghon', 11, 'Nissin', 'pcs', 30.00, 35.00, 10, 5, 20, '2027-02-24', 'uploads/products/product_1786000489_6a743469ba0f0.png', 'Eat Responsibly.', 1, '2026-08-06 07:14:49', '2026-08-07 05:50:56'),
(35, 2, '01', 'Ground Pork Mix', 12, 'Argentina', 'pcs', 40.00, 47.00, 20, 5, 0, '2028-01-01', 'uploads/products/product_cc14c3a5008edd92.png', 'Eat Responsibly.', 1, '2026-08-06 15:52:00', '2026-08-06 15:52:00');

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `trg_products_after_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `receivable_payments`
--

CREATE TABLE `receivable_payments` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `receivable_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Manual Update',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_requests`
--

CREATE TABLE `registration_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(50) NOT NULL,
  `employee_no` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `valid_id_path` varchar(255) NOT NULL,
  `requested_role` enum('owner','employee') NOT NULL DEFAULT 'employee',
  `request_status` enum('pending','approved','rejected','resubmit') NOT NULL DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `business_address` text NOT NULL,
  `business_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_requests`
--

INSERT INTO `registration_requests` (`id`, `request_code`, `employee_no`, `full_name`, `email`, `phone`, `address`, `username`, `password_hash`, `valid_id_path`, `requested_role`, `request_status`, `admin_remarks`, `reviewed_by`, `reviewed_at`, `created_at`, `business_name`, `business_type`, `business_address`, `business_code`) VALUES
(18, 'REQ-20260806-0001', NULL, 'Matt Valdevia', 'mattraileyvaldevia@gmail.com', '+639957266803', 'Pasig City', 'Desinomeme', '$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6', 'uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg', 'owner', 'approved', 'Perfectly fine!', 1, '2026-08-06 15:10:22', '2026-08-06 07:00:10', 'Concentrix', 'Other SME', 'Pasig City', 'SME-C842D3'),
(19, 'REQ-20260806-0002', NULL, 'Joshua Malvar Isla', 'joshuaisla3@gmail.com', '09789847841', 'Cainta, Rizal', 'JoshuaIsla', '$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2', 'uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg', 'owner', 'approved', 'Pasok kana tol!', 1, '2026-08-06 15:19:13', '2026-08-06 07:18:10', 'Omri Bakery Shop', 'Retail Store', 'Cainta, Rizal', 'SME-A96981'),
(20, 'REQ-20260807-0001', 'EMP-001', 'Avery Isaac Valdevia', 'averyisaac17@gmail.com', '09055194166', 'Pasig City', 'mchousetons', '$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW', 'uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg', 'employee', 'approved', 'Okay na!', 1, '2026-08-07 00:37:06', '2026-08-06 16:36:11', 'Concentrix', 'Other SME', 'Pasig City', 'SME-C842D3');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `sales_no` varchar(50) NOT NULL,
  `salesperson_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Unpaid','Partially Paid') NOT NULL DEFAULT 'Paid',
  `payment_method` enum('Cash','GCash','Maya','Bank Transfer') NOT NULL DEFAULT 'Cash',
  `order_status` enum('Fulfilled','Pending','Cancelled') NOT NULL DEFAULT 'Fulfilled',
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `business_id`, `sales_no`, `salesperson_id`, `customer_id`, `total_amount`, `payment_status`, `payment_method`, `order_status`, `sale_date`, `created_at`) VALUES
(60, 1, 'SAL-20260807-002355', 2, NULL, 350.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-07 00:25:46', '2026-08-06 16:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(68, 60, 34, 10, 35.00, 350.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('stock_in','stock_out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `business_id`, `product_id`, `movement_type`, `quantity`, `remarks`, `created_by`, `created_at`) VALUES
(1, 1, 34, 'stock_out', 10, 'Sale recorded: SAL-20260807-002355', 2, '2026-08-06 16:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `employee_no` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `otp_preference` enum('email') NOT NULL DEFAULT 'email',
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('system_admin','owner','employee') NOT NULL DEFAULT 'employee',
  `account_status` enum('pending','active','rejected','disabled') NOT NULL DEFAULT 'pending',
  `profile_image` varchar(255) DEFAULT 'uploads/default.png',
  `valid_id_path` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `can_inventory` tinyint(1) NOT NULL DEFAULT 0,
  `can_sales` tinyint(1) NOT NULL DEFAULT 0,
  `can_sales_analytics` tinyint(1) NOT NULL DEFAULT 0,
  `can_accounts_receivable` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_failed_login_at` datetime DEFAULT NULL,
  `business_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `employee_no`, `email`, `phone`, `address`, `otp_preference`, `otp_code`, `otp_expires_at`, `password`, `role`, `account_status`, `profile_image`, `valid_id_path`, `is_verified`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `can_inventory`, `can_sales`, `can_sales_analytics`, `can_accounts_receivable`, `last_login_at`, `failed_login_attempts`, `locked_until`, `last_failed_login_at`, `business_id`) VALUES
(1, 'admin', 'System Administrator', 'ADM-0001', 'admin@nexgen.local', '09123456789', 'System', 'email', NULL, NULL, '$2y$10$y98td6QnWlGE9x0ya6h8oehlKsxDWO5puD10bxNIKISb6z.9TcAPu', 'system_admin', 'active', 'uploads/profile_6a74333eadb7d2.47780202.png', NULL, 1, NULL, '2026-08-06 14:14:54', NULL, '2026-08-06 06:14:54', 1, 1, 1, 1, '2026-08-07 13:39:15', 0, NULL, NULL, NULL),
(2, 'Desinomeme', 'Matt Valdevia', NULL, 'mattraileyvaldevia@gmail.com', '+639957266803', 'Pasig City', 'email', NULL, NULL, '$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6', 'owner', 'active', 'uploads/profile_6a7433b04ab063.00525040.jpg', 'uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg', 1, 1, '2026-08-06 15:10:22', NULL, '2026-08-06 07:10:22', 1, 1, 1, 1, '2026-08-07 13:41:27', 0, NULL, NULL, 1),
(3, 'JoshuaIsla', 'Joshua Malvar Isla', NULL, 'joshuaisla3@gmail.com', '09789847841', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2', 'owner', 'active', 'uploads/profile_6a7435c86f0a24.69298183.jpg', 'uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg', 1, 1, '2026-08-06 15:19:13', NULL, '2026-08-06 07:19:13', 1, 1, 1, 1, '2026-08-06 23:52:47', 0, NULL, NULL, 2),
(4, 'mchousetons', 'Avery Isaac Valdevia', 'EMP-001', 'averyisaac17@gmail.com', '09055194166', 'Pasig City', 'email', NULL, NULL, '$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW', 'employee', 'active', 'uploads/profile_6a74ba901c4954.00059389.jpg', 'uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg', 1, 1, '2026-08-07 00:37:06', NULL, '2026-08-06 16:37:06', 1, 1, 0, 1, '2026-08-07 00:38:24', 0, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_receivable_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_receivable_summary` (
`receivable_id` int(11)
,`sale_id` int(11)
,`customer_id` int(11)
,`sales_no` varchar(50)
,`customer_code` varchar(50)
,`customer_name` varchar(150)
,`total_amount` decimal(10,2)
,`amount_paid` decimal(10,2)
,`balance_due` decimal(10,2)
,`due_date` date
,`stored_status` enum('Unpaid','Partially Paid','Paid','Overdue')
,`live_status` varchar(14)
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_sales_summary` (
`sale_id` int(11)
,`sales_no` varchar(50)
,`sale_date` datetime
,`payment_status` enum('Paid','Unpaid','Partially Paid')
,`order_status` enum('Fulfilled','Pending','Cancelled')
,`customer_name` varchar(150)
,`salesperson_name` varchar(100)
,`product_name` varchar(150)
,`quantity` int(11)
,`unit_price` decimal(10,2)
,`subtotal` decimal(10,2)
,`total_amount` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_receivable_summary`
--
DROP TABLE IF EXISTS `v_receivable_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_receivable_summary`  AS SELECT `ar`.`id` AS `receivable_id`, `ar`.`sale_id` AS `sale_id`, `ar`.`customer_id` AS `customer_id`, `s`.`sales_no` AS `sales_no`, `c`.`customer_code` AS `customer_code`, `c`.`customer_name` AS `customer_name`, `ar`.`total_amount` AS `total_amount`, `ar`.`amount_paid` AS `amount_paid`, `ar`.`balance_due` AS `balance_due`, `ar`.`due_date` AS `due_date`, `ar`.`status` AS `stored_status`, CASE WHEN `ar`.`balance_due` <= 0 THEN 'Paid' WHEN `ar`.`due_date` is not null AND `ar`.`due_date` <> '' AND `ar`.`due_date` < curdate() AND `ar`.`balance_due` > 0 THEN 'Overdue' WHEN `ar`.`amount_paid` > 0 AND `ar`.`balance_due` > 0 THEN 'Partially Paid' ELSE 'Unpaid' END AS `live_status`, `ar`.`notes` AS `notes`, `ar`.`created_at` AS `created_at`, `ar`.`updated_at` AS `updated_at` FROM ((`accounts_receivable` `ar` join `customers` `c` on(`ar`.`customer_id` = `c`.`id`)) join `sales` `s` on(`ar`.`sale_id` = `s`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_sales_summary`
--
DROP TABLE IF EXISTS `v_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_sales_summary`  AS SELECT `s`.`id` AS `sale_id`, `s`.`sales_no` AS `sales_no`, `s`.`sale_date` AS `sale_date`, `s`.`payment_status` AS `payment_status`, `s`.`order_status` AS `order_status`, `c`.`customer_name` AS `customer_name`, `u`.`full_name` AS `salesperson_name`, `p`.`product_name` AS `product_name`, `si`.`quantity` AS `quantity`, `si`.`unit_price` AS `unit_price`, `si`.`subtotal` AS `subtotal`, `s`.`total_amount` AS `total_amount` FROM ((((`sales` `s` left join `customers` `c` on(`s`.`customer_id` = `c`.`id`)) left join `users` `u` on(`s`.`salesperson_id` = `u`.`id`)) join `sale_items` `si` on(`s`.`id` = `si`.`sale_id`)) join `products` `p` on(`si`.`product_id` = `p`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts_masterlist`
--
ALTER TABLE `accounts_masterlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`);

--
-- Indexes for table `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_receivable_sale` (`sale_id`),
  ADD KEY `fk_receivable_customer` (`customer_id`),
  ADD KEY `fk_receivable_user` (`created_by`),
  ADD KEY `idx_ar_business` (`business_id`),
  ADD KEY `idx_ar_business_status` (`business_id`,`status`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `business_code` (`business_code`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_business_name` (`business_id`,`category_name`),
  ADD KEY `idx_categories_business` (`business_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customers_business_code` (`business_id`,`customer_code`),
  ADD KEY `idx_customers_business` (`business_id`),
  ADD KEY `idx_customers_business_code` (`business_id`,`customer_code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_business_code` (`business_id`,`product_code`),
  ADD KEY `idx_products_category_id` (`category_id`),
  ADD KEY `idx_products_business` (`business_id`);

--
-- Indexes for table `receivable_payments`
--
ALTER TABLE `receivable_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rp_business` (`business_id`),
  ADD KEY `idx_rp_receivable` (`receivable_id`),
  ADD KEY `idx_rp_sale` (`sale_id`),
  ADD KEY `idx_rp_paid_at` (`paid_at`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `idx_registration_requests_status` (`request_status`),
  ADD KEY `idx_registration_username` (`username`),
  ADD KEY `idx_registration_email` (`email`),
  ADD KEY `idx_registration_employee_no` (`employee_no`),
  ADD KEY `idx_registration_business_code` (`business_code`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_business_number` (`business_id`,`sales_no`),
  ADD KEY `fk_sales_user` (`salesperson_id`),
  ADD KEY `fk_sales_customer` (`customer_id`),
  ADD KEY `idx_sales_sale_date` (`sale_date`),
  ADD KEY `idx_sales_business` (`business_id`),
  ADD KEY `idx_sales_business_date` (`business_id`,`sale_date`),
  ADD KEY `idx_sales_business_number` (`business_id`,`sales_no`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_sale` (`sale_id`),
  ADD KEY `fk_sale_items_product` (`product_id`),
  ADD KEY `idx_sale_items_sale_id` (`sale_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_product` (`product_id`),
  ADD KEY `idx_stock_movements_business` (`business_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_business` (`business_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts_masterlist`
--
ALTER TABLE `accounts_masterlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `receivable_payments`
--
ALTER TABLE `receivable_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  ADD CONSTRAINT `fk_ar_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

-- --------------------------------------------------------
-- Performance indexes added for NexGen
-- Focus: Sales Analytics, notifications, and receivables dashboards
-- --------------------------------------------------------

-- Helps category-based analytics queries that filter products by business
-- and then join/group by category.
ALTER TABLE `products`
  ADD KEY `idx_products_business_category` (`business_id`, `category_id`);

-- Helps "recently added products" queries such as:
-- WHERE business_id = ? ORDER BY created_at DESC LIMIT ...
ALTER TABLE `products`
  ADD KEY `idx_products_business_created` (`business_id`, `created_at`);

-- Helps stock notification/history queries such as:
-- WHERE business_id = ? ORDER BY created_at DESC LIMIT ...
ALTER TABLE `stock_movements`
  ADD KEY `idx_stock_business_created` (`business_id`, `created_at`);

-- Helps Accounts Receivable aging / due-date filtering per business.
ALTER TABLE `accounts_receivable`
  ADD KEY `idx_ar_business_due_date` (`business_id`, `due_date`);

-- Helps receivable payment dashboards/history filtered by business and date.
ALTER TABLE `receivable_payments`
  ADD KEY `idx_rp_business_paid_at` (`business_id`, `paid_at`);


COMMIT;