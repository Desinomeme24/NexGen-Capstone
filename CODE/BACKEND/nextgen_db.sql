-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 08:19 PM
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
-- Database: `nextgen_db`
--

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES
(1, 4, 'reject_request', 'registration_request', 1, 'Rejected request #1', '2026-03-14 15:48:15'),
(2, 4, 'resubmit_request', 'registration_request', 2, 'Marked request #2 for resubmission', '2026-03-14 15:56:11'),
(3, 4, 'approve_request', 'registration_request', 2, 'Approved request #2 (REQ-20260314-0002) and created user #6', '2026-03-14 16:09:21'),
(4, 4, 'change_role', 'user', 6, 'Changed role of user #6 to owner', '2026-03-14 18:59:05'),
(5, 4, 'change_role', 'user', 6, 'Changed role of user #6 to employee', '2026-03-14 19:02:29');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES
(1, 'Beverages', '2026-03-07 17:10:41'),
(2, 'Snacks', '2026-03-07 17:10:41'),
(3, 'Canned Goods', '2026-03-07 17:10:41'),
(4, 'Frozen Goods', '2026-03-07 17:10:41'),
(5, 'Personal Care', '2026-03-07 17:10:41'),
(6, 'Household Supplies', '2026-03-07 17:10:41'),
(7, 'Pastry', '2026-03-07 17:10:41'),
(8, 'Others', '2026-03-07 17:10:41');

-- --------------------------------------------------------

--
-- Table structure for table `employee_masterlist`
--

CREATE TABLE `employee_masterlist` (
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
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
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

INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `brand`, `unit`, `cost_price`, `selling_price`, `stock_quantity`, `reorder_level`, `on_order_level`, `expiry_date`, `product_image`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '01', 'Coke Mismo', 1, 'Coca-cola', 'Bottles', 18.00, 20.00, 21, 0, 0, '2026-06-01', 'uploads/products/product_1773228185_69b150990af49.png', 'Drink Responsibly', 1, '2026-03-11 11:23:05', '2026-03-12 06:20:59'),
(4, '02', 'Lemon Soda', 1, 'Lemon Juicy', 'Bottles', 8.00, 10.00, 27, 0, 0, '2026-06-01', 'uploads/products/product_1773229034_69b153eab6209.png', 'Drink Responsibly', 1, '2026-03-11 11:37:14', '2026-03-12 10:18:15'),
(5, '03', 'Redhorse Beer', 1, 'Redhorse', 'Bottles', 65.00, 70.00, 20, 0, 0, '2026-06-01', 'uploads/products/product_1773229187_69b1548384ab3.png', 'Drink Responsibly', 1, '2026-03-11 11:39:47', '2026-03-11 11:39:47'),
(6, '04', 'Alfonso Light', 1, 'Alfonso', 'Bottles', 250.00, 285.00, 11, 0, 0, '2026-09-01', 'uploads/products/product_1773229443_69b15583b8421.png', 'Drink Responsibly', 1, '2026-03-11 11:44:03', '2026-03-13 03:40:46'),
(7, '05', 'SanMig Light', 1, 'SanMig', 'Bottles', 50.00, 55.00, 20, 0, 0, '2026-09-01', 'uploads/products/product_1773229544_69b155e8f1eb2.png', 'Drink Responsibly', 1, '2026-03-11 11:45:44', '2026-03-12 14:30:05'),
(8, '06', 'Gin Bilog', 1, 'Ginebra', 'Bottles', 65.00, 75.00, 25, 0, 0, '2026-09-01', 'uploads/products/product_1773229701_69b15685306b9.png', 'Drink Responsibly', 1, '2026-03-11 11:48:21', '2026-03-12 06:32:49'),
(9, '07', 'Dutchmill Superfruits', 1, 'Dutch Mill', 'Packs', 20.00, 25.00, 45, 0, 0, '2026-12-01', 'uploads/products/product_1773229904_69b15750758d9.png', 'Drink Responsibly', 1, '2026-03-11 11:51:44', '2026-03-14 09:29:53'),
(10, '08', 'Dutchmill Blueberry', 1, 'Dutch Mill', 'Packs', 13.00, 17.00, 50, 0, 0, '2026-12-01', 'uploads/products/product_1773230071_69b157f71c18c.png', 'Drink Reponsibly', 1, '2026-03-11 11:54:31', '2026-03-11 11:54:31'),
(11, '09', 'Chuckie', 1, 'Nestle', 'Packs', 20.00, 25.00, 38, 0, 0, '2027-04-01', 'uploads/products/product_1773230261_69b158b5391bd.png', 'Drink Responsibly', 1, '2026-03-11 11:57:41', '2026-03-12 05:30:21'),
(12, '10', 'Mega Sardines', 3, 'Mega', 'Pieces', 25.00, 29.00, 23, 0, 0, '2027-01-01', 'uploads/products/product_1773230392_69b159381c9e6.png', 'Consume Responsibly', 1, '2026-03-11 11:59:52', '2026-03-11 11:59:52'),
(13, '11', 'Centure Tuna', 3, 'Century', 'Pieces', 38.00, 43.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773230465_69b159816347b.png', 'Consume Responsibly', 1, '2026-03-11 12:01:05', '2026-03-11 12:01:05'),
(14, '12', 'Giniling', 3, 'Argentina', 'Pieces', 31.00, 35.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773230576_69b159f0ca965.png', 'Consume Responsibly', 1, '2026-03-11 12:02:56', '2026-03-11 12:02:56'),
(16, '13', 'Funtastik Tocino', 4, 'CDO', 'Packs', 55.00, 62.00, 12, 5, 0, '2027-01-01', 'uploads/products/product_1773230866_69b15b120759a.png', 'Consume Responsibly', 1, '2026-03-11 12:07:46', '2026-03-12 11:36:54'),
(17, '14', 'Idol Cheesedog', 4, 'CDO', 'Packs', 55.00, 62.00, 20, 0, 0, '2027-01-01', 'uploads/products/product_1773230981_69b15b8592979.png', 'Consume Responsibly', 1, '2026-03-11 12:09:41', '2026-03-11 12:09:41'),
(18, '15', 'Chicken Nuggets', 4, 'STAR', 'Packs', 50.00, 55.00, 19, 0, 0, '2027-01-01', 'uploads/products/product_1773231091_69b15bf394249.png', 'Consume Responsibly', 1, '2026-03-11 12:11:31', '2026-03-12 11:36:54'),
(19, '16', 'Lighter', 6, 'Torch', 'Pieces', 10.00, 16.00, 24, 0, 0, '2999-01-01', 'uploads/products/product_1773231235_69b15c8396e53.png', 'Be Responsible', 1, '2026-03-11 12:13:55', '2026-03-11 13:14:09'),
(20, '17', 'Joy Calamansi', 6, 'Joy', 'Pieces', 10.00, 14.00, 25, 0, 0, '2028-01-01', 'uploads/products/product_1773231401_69b15d29f092d.png', 'Be Reponsible', 1, '2026-03-11 12:16:41', '2026-03-12 14:30:05'),
(21, '18', 'Joy Lemon', 6, 'Joy', 'Pieces', 10.00, 14.00, 25, 0, 0, '2028-01-01', 'uploads/products/product_1773231468_69b15d6c3bd64.png', 'Be Responsible', 1, '2026-03-11 12:17:48', '2026-03-11 12:18:04'),
(22, '19', 'Mantika', 8, 'Mantika', 'Bottles', 30.00, 35.00, 12, 0, 0, '2027-01-01', 'uploads/products/product_1773231782_69b15ea64691b.png', 'Be Responsible', 1, '2026-03-11 12:23:02', '2026-03-11 12:23:02'),
(23, '20', 'Crispy Fry', 8, 'Ajinomoto', 'Pieces', 13.00, 21.00, 15, 0, 0, '2028-01-01', 'uploads/products/product_1773231948_69b15f4c7e0f3.png', 'Be Responsible', 1, '2026-03-11 12:25:48', '2026-03-11 12:25:48'),
(24, '21', 'Mang Tomas Sarsa', 8, 'Mang Tomas', 'Pieces', 35.00, 42.00, 19, 0, 0, '2028-01-01', 'uploads/products/product_1773232022_69b15f969a6e7.png', 'Be Responsible', 1, '2026-03-11 12:27:02', '2026-03-12 06:09:21'),
(25, '22', 'Rexona Men', 5, 'Rexona', 'Pieces', 7.00, 10.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773232307_69b160b32527c.png', 'Be Responsible', 1, '2026-03-11 12:31:47', '2026-03-11 12:31:47'),
(26, '23', 'Cream Silk Pink', 5, 'Cream Silk', 'Pieces', 8.00, 11.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773232385_69b16101ddbdb.png', 'Be Responsible', 1, '2026-03-11 12:33:05', '2026-03-11 12:33:05'),
(27, '24', 'Sunsilk Aloe Vera', 5, 'Sunsilk', 'Pieces', 6.00, 9.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773232468_69b16154047b8.png', 'Be Responsible', 1, '2026-03-11 12:34:28', '2026-03-11 12:34:28'),
(28, '25', 'Pillows', 2, 'Oishi', 'Packs', 8.00, 11.00, 15, 0, 0, '2027-01-01', 'uploads/products/product_1773232559_69b161afec3d1.png', 'Consume Responsibly', 1, '2026-03-11 12:35:59', '2026-03-11 12:35:59'),
(29, '26', 'Cheezy', 2, 'Leslie\'s', 'Pieces', 8.00, 11.00, 14, 0, 0, '2027-01-01', 'uploads/products/product_1773232643_69b162037f52c.png', 'Consume Responsibly', 1, '2026-03-11 12:37:23', '2026-03-11 12:42:09'),
(30, '27', 'Potato Crisp Onion', 2, 'Oishi', 'Pieces', 8.00, 11.00, 13, 0, 0, '2027-01-01', 'uploads/products/product_1773232715_69b1624bc379a.png', 'Consume Responsibly', 1, '2026-03-11 12:38:35', '2026-03-11 12:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `registration_requests`
--

CREATE TABLE `registration_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(50) NOT NULL,
  `employee_no` varchar(50) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_requests`
--

INSERT INTO `registration_requests` (`id`, `request_code`, `employee_no`, `full_name`, `email`, `phone`, `address`, `username`, `password_hash`, `valid_id_path`, `requested_role`, `request_status`, `admin_remarks`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 'REQ-20260314-0001', 'EMP-029', 'Snowi D Cat', 'snowi@gmail.com', '09532526758', 'Tiga kela Sed', 'Snowi', '$2y$10$VbMSr0QV4VPwyPCWalm6.OP2fGcASOhWU7q.TDoAd6jL1BNMA1esW', 'uploads/valid_ids/valid_id_69b58208ad9148.04257378.jpg', 'employee', 'rejected', 'The picture is outdated', 4, '2026-03-14 16:48:15', '2026-03-14 15:43:04'),
(2, 'REQ-20260314-0002', 'EMP-030', 'Chu K Umani', 'chuchu@gmail.com', '09784574125', 'Tiga kela Sed', 'Chuchu', '$2y$10$jiWRmhk3ttT76vSyi8xP9OAySYUnowPON3L7gnisGPG5nvbpVyQfu', 'uploads/valid_ids/valid_id_69b58496be2010.52169437.jpg', 'employee', 'approved', 'No code', 4, '2026-03-14 17:09:21', '2026-03-14 15:53:58');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sales_no` varchar(50) NOT NULL,
  `salesperson_id` int(11) NOT NULL,
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

INSERT INTO `sales` (`id`, `sales_no`, `salesperson_id`, `total_amount`, `payment_status`, `payment_method`, `order_status`, `sale_date`, `created_at`) VALUES
(1, 'SAL-20260311-134045', 2, 33.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-11 20:42:09', '2026-03-11 12:42:09'),
(2, 'SAL-20260311-141241', 2, 586.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-11 21:14:09', '2026-03-11 13:14:09'),
(3, 'SAL-20260312-062922', 2, 50.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 13:30:21', '2026-03-12 05:30:21'),
(4, 'SAL-20260312-064713', 2, 89.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 13:47:37', '2026-03-12 05:47:37'),
(5, 'SAL-20260312-070148', 2, 110.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:02:02', '2026-03-12 06:02:02'),
(6, 'SAL-20260312-070910', 2, 42.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:09:21', '2026-03-12 06:09:21'),
(7, 'SAL-20260312-072034', 2, 60.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:20:59', '2026-03-12 06:20:59'),
(8, 'SAL-20260312-073230', 1, 375.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:32:49', '2026-03-12 06:32:49'),
(9, 'SAL-20260312-111755', 2, 30.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 18:18:15', '2026-03-12 10:18:15'),
(10, 'SAL-20260312-123626', 2, 344.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 19:36:54', '2026-03-12 11:36:54'),
(11, 'SAL-20260312-152937', 2, 331.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 22:30:05', '2026-03-12 14:30:05'),
(12, 'SAL-20260313-044006', 2, 570.00, 'Partially Paid', 'Cash', 'Fulfilled', '2026-03-13 11:40:46', '2026-03-13 03:40:46'),
(13, 'SAL-20260314-102408', 2, 50.00, 'Unpaid', 'GCash', 'Fulfilled', '2026-03-14 17:29:53', '2026-03-14 09:29:53');

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
(1, 1, 30, 2, 11.00, 22.00),
(2, 1, 29, 1, 11.00, 11.00),
(3, 2, 19, 1, 16.00, 16.00),
(4, 2, 6, 2, 285.00, 570.00),
(5, 3, 11, 2, 25.00, 0.00),
(6, 4, 9, 3, 25.00, 0.00),
(7, 4, 20, 1, 14.00, 0.00),
(8, 5, 18, 2, 55.00, 0.00),
(9, 6, 24, 1, 42.00, 0.00),
(10, 7, 1, 3, 20.00, 0.00),
(11, 8, 8, 5, 75.00, 0.00),
(12, 9, 4, 3, 10.00, 0.00),
(13, 10, 18, 4, 55.00, 0.00),
(14, 10, 16, 2, 62.00, 0.00),
(15, 11, 20, 4, 14.00, 0.00),
(16, 11, 7, 5, 55.00, 0.00),
(17, 12, 6, 2, 285.00, 0.00),
(18, 13, 9, 2, 25.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
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

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `remarks`, `created_by`, `created_at`) VALUES
(1, 30, 'stock_out', 2, 'Sale recorded: SAL-20260311-134045', 2, '2026-03-11 12:42:09'),
(2, 29, 'stock_out', 1, 'Sale recorded: SAL-20260311-134045', 2, '2026-03-11 12:42:09'),
(3, 19, 'stock_out', 1, 'Sale recorded: SAL-20260311-141241', 2, '2026-03-11 13:14:09'),
(4, 6, 'stock_out', 2, 'Sale recorded: SAL-20260311-141241', 2, '2026-03-11 13:14:09'),
(5, 24, 'stock_out', 1, 'Auto-deducted from sale: SAL-20260312-070910', 2, '2026-03-12 06:09:21'),
(6, 1, 'stock_out', 3, 'Auto-deducted from sale: SAL-20260312-072034', 2, '2026-03-12 06:20:59'),
(7, 8, 'stock_out', 5, 'Auto-deducted from sale: SAL-20260312-073230', 1, '2026-03-12 06:32:49'),
(8, 4, 'stock_out', 3, 'Auto-deducted from sale: SAL-20260312-111755', 2, '2026-03-12 10:18:15'),
(9, 18, 'stock_out', 4, 'Auto-deducted from sale: SAL-20260312-123626', 2, '2026-03-12 11:36:54'),
(10, 16, 'stock_out', 2, 'Auto-deducted from sale: SAL-20260312-123626', 2, '2026-03-12 11:36:54'),
(11, 20, 'stock_out', 4, 'Auto-deducted from sale: SAL-20260312-152937', 2, '2026-03-12 14:30:05'),
(12, 7, 'stock_out', 5, 'Auto-deducted from sale: SAL-20260312-152937', 2, '2026-03-12 14:30:05'),
(13, 6, 'stock_out', 2, 'Auto-deducted from sale: SAL-20260313-044006', 2, '2026-03-13 03:40:46'),
(14, 9, 'stock_out', 2, 'Auto-deducted from sale: SAL-20260314-102408', 2, '2026-03-14 09:29:53');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `employee_no`, `email`, `phone`, `address`, `otp_preference`, `otp_code`, `otp_expires_at`, `password`, `role`, `account_status`, `profile_image`, `valid_id_path`, `is_verified`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`) VALUES
(1, 'Sedric', 'Sedric Macasieb', NULL, 'sedricmacasieb29@gmail.com', '09925853329', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$c4olsT30OtQHDGpwecbXNe3Q/YMNcEmTA.RAx5/a5Ry162TH6rjC6', 'owner', 'active', 'uploads/profile_69ae63b4f27ea6.93611954.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-06 15:19:38'),
(2, 'Desinomeme', 'Matt Railey Valdevia', NULL, 'mvaldevia@gmail.com', '09957266803', '443 Avocado St. Napico Manggahan Pasig City mayor vico i lab u', 'email', NULL, NULL, '$2y$10$ugVIrlkRfIH4rsi/XujzceWS5VBzFM512rnrKIUIRnm/x7AGptGci', 'owner', 'active', 'uploads/profile_69ac3303264562.74696813.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-07 14:15:31'),
(4, 'NexGenAdmin', 'System Administrator', 'ADMIN-001', 'mattraileyvaldevia@gmail.com', '09957266803', 'Main Office', 'email', NULL, NULL, '$2y$10$8jKA1C1NL6RQoHFxdhsmQus.t3Tso1670mfwl3QfQw4UfOjGqOR5y', 'system_admin', 'active', 'uploads/profile_69b57647dc3837.12693462.png', NULL, 1, NULL, NULL, NULL, '2026-03-14 13:13:10'),
(5, 'Juswa', 'Joshua Malvar Isla', NULL, 'joshuaisla@gmail.com', '09034562422', NULL, 'email', NULL, NULL, '$2y$10$RSkUCsvf0SlWffXpVFJW2elcp.x0YiOy9p7qSsiLOdTllslnMBUcq', 'employee', 'pending', 'uploads/profile_69b56a8f504900.42054425.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-14 14:02:55'),
(6, 'Chuchu', 'Chu K Umani', 'EMP-030', 'chuchu@gmail.com', '09784574125', 'Tiga kela Sed', 'email', NULL, NULL, '$2y$10$jiWRmhk3ttT76vSyi8xP9OAySYUnowPON3L7gnisGPG5nvbpVyQfu', 'employee', 'active', 'uploads/profile_69b58869e83ae8.54802663.jpg', 'uploads/valid_ids/valid_id_69b58496be2010.52169437.jpg', 1, 4, '2026-03-14 17:09:21', NULL, '2026-03-14 16:09:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `employee_masterlist`
--
ALTER TABLE `employee_masterlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_no` (`sales_no`),
  ADD KEY `fk_sales_user` (`salesperson_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_sale` (`sale_id`),
  ADD KEY `fk_sale_items_product` (`product_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employee_masterlist`
--
ALTER TABLE `employee_masterlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_user` FOREIGN KEY (`salesperson_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
