-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 04:34 PM
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
-- Dumping data for table `accounts_receivable`
--

INSERT INTO `accounts_receivable` (`id`, `sale_id`, `customer_id`, `total_amount`, `amount_paid`, `balance_due`, `due_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 14, 2, 181.00, 181.00, 0.00, '2026-04-15', 'Paid', 'Fully paid', 6, '2026-04-01 05:14:39', '2026-04-01 05:31:04'),
(2, 16, 4, 125.00, 125.00, 0.00, '2026-04-04', 'Paid', 'late payment', 6, '2026-04-01 06:34:56', '2026-04-15 03:50:42'),
(3, 20, 7, 56.00, 56.00, 0.00, '2026-04-10', 'Paid', 'late payment', 6, '2026-04-01 07:53:01', '2026-04-15 03:49:43'),
(4, 21, 8, 660.00, 660.00, 0.00, '2026-04-30', 'Paid', 'Bayad na', 6, '2026-04-07 09:06:52', '2026-04-07 09:07:48'),
(5, 24, 9, 210.00, 210.00, 0.00, '2026-04-13', 'Paid', 'goods na', 10, '2026-04-12 15:33:54', '2026-04-12 15:38:31'),
(6, 28, 10, 33.00, 33.00, 0.00, '2026-04-18', 'Paid', 'binayaran ni tita', 10, '2026-04-12 17:10:49', '2026-04-12 17:11:40'),
(7, 30, 11, 60.00, 60.00, 0.00, '2026-04-20', 'Paid', 'oks na', 10, '2026-04-14 07:03:28', '2026-04-23 11:17:34'),
(8, 32, 8, 44.00, 44.00, 0.00, '2026-04-20', 'Paid', 'Bayad na', 10, '2026-04-14 08:07:28', '2026-04-14 08:08:04'),
(9, 33, 13, 85.00, 85.00, 0.00, '2026-04-18', 'Paid', 'bayad na kanina', 10, '2026-04-14 08:33:40', '2026-04-14 08:36:32'),
(10, 34, 14, 30.00, 30.00, 0.00, '2026-04-30', 'Paid', 'bayad na', 10, '2026-04-14 08:35:54', '2026-04-14 08:48:56'),
(11, 42, 12, 75.00, 75.00, 0.00, '2026-05-01', 'Paid', 'oks na', 2, '2026-04-23 11:25:06', '2026-04-23 11:25:41'),
(12, 44, 16, 34.00, 34.00, 0.00, '2026-04-26', 'Paid', '', 14, '2026-04-24 14:34:49', '2026-04-24 14:42:55');

--
-- Triggers `accounts_receivable`
--
DELIMITER $$
CREATE TRIGGER `trg_accounts_receivable_audit_update` AFTER UPDATE ON `accounts_receivable` FOR EACH ROW BEGIN
    IF OLD.amount_paid <> NEW.amount_paid
       OR OLD.balance_due <> NEW.balance_due
       OR OLD.status <> NEW.status
       OR IFNULL(OLD.notes, '') <> IFNULL(NEW.notes, '') THEN

        INSERT INTO audit_logs (
            table_name,
            record_id,
            action_type,
            changed_by,
            old_values,
            new_values
        ) VALUES (
            'accounts_receivable',
            NEW.id,
            'UPDATE',
            NEW.customer_id,
            CONCAT(
                'sale_id=', IFNULL(OLD.sale_id, ''),
                '; customer_id=', IFNULL(OLD.customer_id, ''),
                '; total_amount=', IFNULL(OLD.total_amount, ''),
                '; amount_paid=', IFNULL(OLD.amount_paid, ''),
                '; balance_due=', IFNULL(OLD.balance_due, ''),
                '; due_date=', IFNULL(OLD.due_date, ''),
                '; status=', IFNULL(OLD.status, ''),
                '; notes=', IFNULL(OLD.notes, '')
            ),
            CONCAT(
                'sale_id=', IFNULL(NEW.sale_id, ''),
                '; customer_id=', IFNULL(NEW.customer_id, ''),
                '; total_amount=', IFNULL(NEW.total_amount, ''),
                '; amount_paid=', IFNULL(NEW.amount_paid, ''),
                '; balance_due=', IFNULL(NEW.balance_due, ''),
                '; due_date=', IFNULL(NEW.due_date, ''),
                '; status=', IFNULL(NEW.status, ''),
                '; notes=', IFNULL(NEW.notes, '')
            )
        );
    END IF;
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
(1, 4, 'reject_request', 'registration_request', 1, 'Rejected request #1', NULL, NULL, NULL, NULL, '2026-03-14 15:48:15'),
(2, 4, 'resubmit_request', 'registration_request', 2, 'Marked request #2 for resubmission', NULL, NULL, NULL, NULL, '2026-03-14 15:56:11'),
(3, 4, 'approve_request', 'registration_request', 2, 'Approved request #2 (REQ-20260314-0002) and created user #6', NULL, NULL, NULL, NULL, '2026-03-14 16:09:21'),
(4, 4, 'change_role', 'user', 6, 'Changed role of user #6 to owner', NULL, NULL, NULL, NULL, '2026-03-14 18:59:05'),
(5, 4, 'change_role', 'user', 6, 'Changed role of user #6 to employee', NULL, NULL, NULL, NULL, '2026-03-14 19:02:29'),
(6, 4, 'approve_request', 'registration_request', 3, 'Approved request #3 (REQ-20260407-0001) and created user #7', NULL, NULL, NULL, NULL, '2026-04-07 08:47:27'),
(7, 4, 'approve_request', 'registration_request', 4, 'Approved request #4 (REQ-20260407-0002) and created user #8', NULL, NULL, NULL, NULL, '2026-04-07 09:00:42'),
(8, 4, 'approve_request', 'registration_request', 5, 'Approved request #5 (REQ-20260408-0001) and created user #9', NULL, NULL, NULL, NULL, '2026-04-08 05:12:39'),
(9, 4, 'approve_request', 'registration_request', 6, 'Approved request #6 (REQ-20260408-0002) and created user #10', NULL, NULL, NULL, NULL, '2026-04-08 07:36:55'),
(10, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Vion', NULL, NULL, NULL, NULL, '2026-04-11 18:37:26'),
(11, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: Vion', NULL, NULL, NULL, NULL, '2026-04-11 18:40:04'),
(12, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Vion', NULL, NULL, NULL, NULL, '2026-04-11 18:40:25'),
(13, 4, 'update_user_permissions', 'user', 6, 'Updated user #6 (Chuchu) - role: employee, status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-11 18:41:44'),
(14, 4, 'update_user_permissions', 'user', 6, 'Updated user #6 (Chuchu) - role: employee, status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-11 19:04:50'),
(15, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Chuchu', NULL, NULL, NULL, NULL, '2026-04-11 19:16:56'),
(16, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: Chuchu', NULL, NULL, NULL, NULL, '2026-04-12 03:44:04'),
(17, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Chuchu', NULL, NULL, NULL, NULL, '2026-04-12 03:50:52'),
(18, 4, 'update_user_permissions', 'user', 6, 'Updated user #6 (Chuchu) - role: employee, status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-12 03:51:25'),
(19, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: Chuchu', NULL, NULL, NULL, NULL, '2026-04-12 04:43:00'),
(20, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Chuchu', NULL, NULL, NULL, NULL, '2026-04-12 05:03:50'),
(21, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: RAC', NULL, NULL, NULL, NULL, '2026-04-12 15:24:06'),
(22, 4, 'update_user_permissions', 'user', 10, 'Updated user #10 (RAC) - role: owner, status: active, verified: 1, inventory: 0, sales: 1, analytics: 1, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-12 15:28:04'),
(23, 4, 'update_user_permissions', 'user', 10, 'Updated user #10 (RAC) - role: owner, status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-12 15:40:15'),
(24, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: RAC', NULL, NULL, NULL, NULL, '2026-04-12 16:07:49'),
(25, 4, 'update_user_permissions', 'user', 6, 'Updated user #6 (Chuchu) - role: employee, status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-13 02:25:54'),
(26, 4, 'resubmit_request', 'registration_request', 7, 'Marked request #7 for resubmission', NULL, NULL, NULL, NULL, '2026-04-13 10:02:57'),
(27, 4, 'approve_request', 'registration_request', 7, 'Approved request #7 (REQ-20260412-0001) and created user #11 with role-based module permissions', NULL, NULL, NULL, NULL, '2026-04-13 10:06:55'),
(28, 4, 'reject_request', 'registration_request', 8, 'Rejected request #8', NULL, NULL, NULL, NULL, '2026-04-13 10:12:27'),
(29, 4, 'update_user_permissions', 'user', 11, 'Updated user #11 (averyval) - role: employee, status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 1', NULL, NULL, NULL, NULL, '2026-04-13 11:45:04'),
(30, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: Joshua', NULL, NULL, NULL, NULL, '2026-04-13 15:22:24'),
(31, 4, 'approve_request', 'registration_request', 9, 'Approved request #9 (REQ-20260413-0002) and created user #12 with role-based module permissions', NULL, NULL, NULL, NULL, '2026-04-13 15:47:22'),
(32, 4, 'resubmit_request', 'registration_request', 10, 'Marked request #10 for resubmission', NULL, NULL, NULL, NULL, '2026-04-13 15:49:59'),
(33, 4, 'reject_request', 'registration_request', 10, 'Rejected request #10', NULL, NULL, NULL, NULL, '2026-04-13 15:51:59'),
(34, 4, 'resubmit_request', 'registration_request', 10, 'Marked request #10 for resubmission', NULL, NULL, NULL, NULL, '2026-04-13 16:04:00'),
(35, 4, 'reject_request', 'registration_request', 10, 'Rejected request #10', NULL, NULL, NULL, NULL, '2026-04-13 16:05:23'),
(36, 4, 'resubmit_request', 'registration_request', 10, 'Marked request #10 for resubmission', NULL, NULL, NULL, NULL, '2026-04-13 16:17:14'),
(37, 4, 'approve_request', 'registration_request', 11, 'Approved request #11 (REQ-20260416-0001) and created user #13 with role-based module permissions', NULL, NULL, NULL, NULL, '2026-04-16 05:46:22'),
(38, 4, 'revoke_access', 'user_batch', 0, 'Revoked module access from: Joshie', NULL, NULL, NULL, NULL, '2026-04-19 13:32:38'),
(39, 4, 'update_user_permissions', 'user', 13, 'Updated user #13 (Joshie) - role: employee, status: active, verified: 1, inventory: 0, sales: 1, analytics: 0, accounts_receivable: 0', NULL, NULL, NULL, NULL, '2026-04-19 13:34:53'),
(40, 4, 'grant_access', 'user_batch', 0, 'Granted default module access to: Joshie', NULL, NULL, NULL, NULL, '2026-04-23 11:04:38'),
(41, 4, 'approve_request', 'registration_request', 12, 'Approved request #12 (REQ-20260424-0001) and created user #14 with role-based module permissions', NULL, NULL, NULL, NULL, '2026-04-24 14:20:40'),
(42, 4, 'reject_request', 'registration_request', 13, 'Rejected request #13 (REQ-20260424-0002)', NULL, NULL, NULL, NULL, '2026-04-24 15:07:32'),
(43, 4, 'approve_request', 'registration_request', 14, 'Approved request #14 (REQ-20260427-0001) and created user #15 with role-based module permissions', NULL, NULL, NULL, NULL, '2026-04-27 09:58:48'),
(44, 4, 'unlock_user_account', 'user', 15, 'Unlocked user account #15 (sedsed)', NULL, NULL, NULL, NULL, '2026-04-27 11:14:28'),
(45, 4, 'unlock_user_account', 'user', 15, 'Unlocked user account #15 (sedsed)', NULL, NULL, NULL, NULL, '2026-04-27 11:19:59'),
(46, 4, 'unlock_user_account', 'user', 15, 'Unlocked user account #15 (sedsed) and restored full 5 login attempts.', NULL, NULL, NULL, NULL, '2026-04-27 11:24:02'),
(47, 4, 'unlock_user_account', 'user', 14, 'Unlocked user account #14 (Elwin) and restored full 3 login attempts.', '0000000000000000000000000000000000000000000000000000000000000000', 'a17a0ba61aacf80a4926527089ad86177fb9f449ab6a4d7f6abfb2adc0213515', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 14:29:54');

--
-- Triggers `admin_logs`
--
DELIMITER $$
CREATE TRIGGER `trg_admin_logs_no_delete` BEFORE DELETE ON `admin_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'admin_logs is append-only. Deletes are not allowed.';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_admin_logs_no_update` BEFORE UPDATE ON `admin_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'admin_logs is append-only. Updates are not allowed.';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `table_name`, `record_id`, `action_type`, `changed_by`, `old_values`, `new_values`, `changed_at`) VALUES
(1, 'users', 2, 'UPDATE', 2, 'username=Desinomeme; full_name=Matt Railey Valdevia; employee_no=; email=mvaldevia@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-21 22:11:20', 'username=Desinomeme; full_name=Matt Railey Valdevia; employee_no=; email=mvaldevia@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-23 18:39:45', '2026-04-23 10:39:45'),
(2, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-19 21:43:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-23 18:52:47', '2026-04-23 10:52:47'),
(3, 'users', 13, 'UPDATE', 13, 'username=Joshie; full_name=Joshua Malvar Isla; employee_no=EMP-300; email=joshuaisla04@gmail.com; role=employee; account_status=active; can_inventory=0; can_sales=1; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-16 19:42:32', 'username=Joshie; full_name=Joshua Malvar Isla; employee_no=EMP-300; email=joshuaisla04@gmail.com; role=employee; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=0; can_accounts_receivable=1; last_login_at=2026-04-16 19:42:32', '2026-04-23 11:04:38'),
(4, 'users', 2, 'UPDATE', 2, 'username=Desinomeme; full_name=Matt Railey Valdevia; employee_no=; email=mvaldevia@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-23 18:39:45', 'username=Desinomeme; full_name=Matt Railey Valdevia; employee_no=; email=mvaldevia@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-23 19:05:29', '2026-04-23 11:05:29'),
(5, 'accounts_receivable', 7, 'UPDATE', 11, 'sale_id=30; customer_id=11; total_amount=60.00; amount_paid=20.00; balance_due=40.00; due_date=2026-04-20; status=Partially Paid; notes=bukas babayaran kulang', 'sale_id=30; customer_id=11; total_amount=60.00; amount_paid=60.00; balance_due=0.00; due_date=2026-04-20; status=Paid; notes=oks na', '2026-04-23 11:17:34'),
(6, 'products', 9, 'UPDATE', NULL, 'product_code=07; product_name=Dutchmill Superfruits; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=41; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', 'product_code=07; product_name=Dutchmill Superfruits; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=38; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', '2026-04-23 11:25:06'),
(7, 'accounts_receivable', 11, 'UPDATE', 12, 'sale_id=42; customer_id=12; total_amount=75.00; amount_paid=35.00; balance_due=40.00; due_date=2026-05-01; status=Partially Paid; notes=', 'sale_id=42; customer_id=12; total_amount=75.00; amount_paid=75.00; balance_due=0.00; due_date=2026-05-01; status=Paid; notes=oks na', '2026-04-23 11:25:41'),
(8, 'products', 30, 'UPDATE', NULL, 'product_code=27; product_name=Potato Crisp Onion; category_id=2; brand=Oishi; unit=Pieces; cost_price=8.00; selling_price=11.00; stock_quantity=3; reorder_level=0; on_order_level=0; expiry_date=2027-01-01; is_active=1', 'product_code=27; product_name=Potato Crisp Onion; category_id=2; brand=Oishi; unit=Pieces; cost_price=8.00; selling_price=11.00; stock_quantity=3; reorder_level=0; on_order_level=0; expiry_date=2027-01-01; is_active=0', '2026-04-23 11:43:50'),
(9, 'products', 30, 'UPDATE', NULL, 'is_active=1', 'is_active=0', '2026-04-23 11:43:50'),
(10, 'products', 32, 'INSERT', NULL, NULL, 'product_code=28; product_name=Cup Noodles Beef; category_id=10; brand=Nissin; unit=pcs; cost_price=30.00; selling_price=35.00; stock_quantity=40; reorder_level=10; on_order_level=0; expiry_date=2027-01-01; is_active=1', '2026-04-23 11:49:03'),
(11, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-23 18:52:47', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 22:17:13', '2026-04-24 14:17:13'),
(12, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 22:17:13', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 22:20:24', '2026-04-24 14:20:24'),
(13, 'users', 14, 'INSERT', 14, NULL, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=', '2026-04-24 14:20:40'),
(14, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 22:20:49', '2026-04-24 14:20:49'),
(15, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 22:20:49', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 22:20:49', '2026-04-24 14:22:39'),
(16, 'products', 11, 'UPDATE', NULL, 'product_code=09; product_name=Chuckie; category_id=1; brand=Nestle; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=30; reorder_level=0; on_order_level=0; expiry_date=2027-04-01; is_active=1', 'product_code=09; product_name=Chuckie; category_id=1; brand=Nestle; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=28; reorder_level=0; on_order_level=0; expiry_date=2027-04-01; is_active=1', '2026-04-24 14:29:16'),
(17, 'products', 10, 'UPDATE', NULL, 'product_code=08; product_name=Dutchmill Blueberry; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=13.00; selling_price=17.00; stock_quantity=40; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', 'product_code=08; product_name=Dutchmill Blueberry; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=13.00; selling_price=17.00; stock_quantity=38; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', '2026-04-24 14:34:49'),
(18, 'products', 4, 'UPDATE', NULL, 'product_code=02; product_name=Lemon Soda; category_id=1; brand=Lemon Juicy; unit=Bottles; cost_price=8.00; selling_price=10.00; stock_quantity=20; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', 'product_code=02; product_name=Lemon Soda; category_id=1; brand=Lemon Juicy; unit=Bottles; cost_price=8.00; selling_price=10.00; stock_quantity=25; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', '2026-04-24 14:37:31'),
(19, 'products', 1, 'UPDATE', NULL, 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=18; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=25; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', '2026-04-24 14:38:15'),
(20, 'accounts_receivable', 12, 'UPDATE', 16, 'sale_id=44; customer_id=16; total_amount=34.00; amount_paid=7.00; balance_due=27.00; due_date=2026-04-26; status=Partially Paid; notes=', 'sale_id=44; customer_id=16; total_amount=34.00; amount_paid=27.00; balance_due=7.00; due_date=2026-04-26; status=Partially Paid; notes=', '2026-04-24 14:42:22'),
(21, 'accounts_receivable', 12, 'UPDATE', 16, 'sale_id=44; customer_id=16; total_amount=34.00; amount_paid=27.00; balance_due=7.00; due_date=2026-04-26; status=Partially Paid; notes=', 'sale_id=44; customer_id=16; total_amount=34.00; amount_paid=34.00; balance_due=0.00; due_date=2026-04-26; status=Paid; notes=', '2026-04-24 14:42:55'),
(24, 'products', 1, 'UPDATE', NULL, 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=25; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=26; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', '2026-04-24 14:50:32'),
(26, 'products', 1, 'UPDATE', NULL, 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=26; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', 'product_code=01; product_name=Coke Mismo; category_id=1; brand=Coca-cola; unit=Bottles; cost_price=18.00; selling_price=20.00; stock_quantity=27; reorder_level=0; on_order_level=0; expiry_date=2026-06-01; is_active=1', '2026-04-24 14:52:10'),
(27, 'products', 33, 'INSERT', NULL, NULL, 'product_code=777; product_name=Loaded Cheese; category_id=2; brand=Stateline; unit=pcs; cost_price=10.00; selling_price=9.98; stock_quantity=10; reorder_level=5; on_order_level=0; expiry_date=2026-05-29; is_active=1', '2026-04-24 15:01:53'),
(28, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 22:20:24', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 23:04:24', '2026-04-24 15:04:24'),
(29, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 23:04:24', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 23:07:00', '2026-04-24 15:07:00'),
(30, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 22:20:49', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 23:17:20', '2026-04-24 15:17:20'),
(31, 'products', 33, 'UPDATE', NULL, 'is_active=1', 'is_active=0', '2026-04-24 15:17:35'),
(32, 'products', 33, 'UPDATE', NULL, 'product_code=777; product_name=Loaded Cheese; category_id=2; brand=Stateline; unit=pcs; cost_price=10.00; selling_price=9.98; stock_quantity=10; reorder_level=5; on_order_level=0; expiry_date=2026-05-29; is_active=1', 'product_code=777; product_name=Loaded Cheese; category_id=2; brand=Stateline; unit=pcs; cost_price=10.00; selling_price=9.98; stock_quantity=10; reorder_level=5; on_order_level=0; expiry_date=2026-05-29; is_active=0', '2026-04-24 15:17:35'),
(33, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-24 23:17:20', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:38:29', '2026-04-27 09:38:29'),
(34, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-24 23:07:00', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 17:58:26', '2026-04-27 09:58:26'),
(35, 'users', 15, 'INSERT', 15, NULL, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=', '2026-04-27 09:58:48'),
(36, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 09:59:20'),
(37, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 17:58:26', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 17:58:26', '2026-04-27 10:16:10'),
(38, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 17:58:26', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:16:10', '2026-04-27 10:16:10'),
(39, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:16:10', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:16:10', '2026-04-27 10:21:44'),
(40, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:16:10', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', '2026-04-27 10:21:45'),
(41, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', '2026-04-27 10:39:59'),
(42, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', '2026-04-27 10:40:14'),
(43, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:21:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', '2026-04-27 10:40:14'),
(44, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', '2026-04-27 10:44:54'),
(45, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 10:55:13'),
(46, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 10:55:23'),
(47, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 10:55:35'),
(48, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 10:55:53'),
(49, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 10:56:10'),
(50, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', '2026-04-27 11:12:42'),
(51, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', '2026-04-27 11:12:53'),
(52, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 18:40:14', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', '2026-04-27 11:12:53'),
(53, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:13:35'),
(54, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', '2026-04-27 11:14:01'),
(55, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', '2026-04-27 11:14:10'),
(56, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:12:53', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:14:10', '2026-04-27 11:14:10'),
(57, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:14:28'),
(58, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:17:19'),
(59, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:18:44'),
(60, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:18:51'),
(61, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:19:08'),
(62, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:19:19'),
(63, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:14:10', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:14:10', '2026-04-27 11:19:46'),
(64, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:14:10', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:19:46', '2026-04-27 11:19:46'),
(65, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:19:59'),
(66, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:22:54'),
(67, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:23:02'),
(68, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:23:17'),
(69, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:23:22'),
(70, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:23:27'),
(71, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:19:46', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:19:46', '2026-04-27 11:23:45'),
(72, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:19:46', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:23:45'),
(73, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:24:02'),
(74, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-27 11:24:19'),
(75, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:24:28'),
(76, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:41:10'),
(77, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:41:13'),
(78, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:41:15'),
(79, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:41:17'),
(80, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-27 11:41:21'),
(81, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:38:29', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:38:29', '2026-04-27 11:55:08'),
(82, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:38:29', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', '2026-04-27 11:55:08'),
(83, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', '2026-04-27 14:53:40'),
(84, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', '2026-04-27 14:54:00'),
(85, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 19:55:08', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', '2026-04-27 14:54:00'),
(86, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', '2026-04-28 08:16:24'),
(87, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', '2026-04-28 08:17:51'),
(88, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', '2026-04-28 08:18:03'),
(89, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-28 08:18:34'),
(90, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', '2026-04-28 08:19:09'),
(91, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 17:59:20', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 16:19:09', '2026-04-28 08:19:09'),
(92, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', '2026-04-28 09:05:11'),
(93, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-27 22:54:00', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 09:05:11'),
(94, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 10:09:03'),
(95, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 10:09:16'),
(96, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 10:09:31'),
(97, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 16:19:09', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 16:19:09', '2026-04-28 10:09:56'),
(98, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 16:19:09', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', '2026-04-28 10:09:56'),
(99, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', '2026-04-28 10:11:56'),
(100, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', '2026-04-28 10:24:48');
INSERT INTO `audit_logs` (`id`, `table_name`, `record_id`, `action_type`, `changed_by`, `old_values`, `new_values`, `changed_at`) VALUES
(101, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:09:56', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:24:48', '2026-04-28 10:24:48'),
(102, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:24:48', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:24:48', '2026-04-28 11:25:29'),
(103, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 18:24:48', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:25:29', '2026-04-28 11:25:29'),
(104, 'products', 6, 'UPDATE', NULL, 'product_code=04; product_name=Alfonso Light; category_id=1; brand=Alfonso; unit=Bottles; cost_price=250.00; selling_price=285.00; stock_quantity=10; reorder_level=0; on_order_level=0; expiry_date=2026-09-01; is_active=1', 'product_code=04; product_name=Alfonso Light; category_id=1; brand=Alfonso; unit=Bottles; cost_price=250.00; selling_price=285.00; stock_quantity=9; reorder_level=0; on_order_level=0; expiry_date=2026-09-01; is_active=1', '2026-04-28 11:26:07'),
(105, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:25:29', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:25:29', '2026-04-28 11:47:04'),
(106, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:25:29', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', '2026-04-28 11:47:04'),
(107, 'products', 11, 'UPDATE', NULL, 'product_code=09; product_name=Chuckie; category_id=1; brand=Nestle; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=28; reorder_level=0; on_order_level=0; expiry_date=2027-04-01; is_active=1', 'product_code=09; product_name=Chuckie; category_id=1; brand=Nestle; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=27; reorder_level=0; on_order_level=0; expiry_date=2027-04-01; is_active=1', '2026-04-28 11:47:37'),
(108, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', '2026-04-28 12:06:27'),
(109, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', '2026-04-28 12:06:56'),
(110, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', '2026-04-28 12:07:35'),
(111, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', '2026-04-28 12:07:52'),
(112, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 19:47:04', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:07:52', '2026-04-28 12:07:52'),
(113, 'products', 9, 'UPDATE', NULL, 'product_code=07; product_name=Dutchmill Superfruits; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=38; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', 'product_code=07; product_name=Dutchmill Superfruits; category_id=1; brand=Dutch Mill; unit=Packs; cost_price=20.00; selling_price=25.00; stock_quantity=37; reorder_level=0; on_order_level=0; expiry_date=2026-12-01; is_active=1', '2026-04-28 12:08:21'),
(114, 'products', 14, 'UPDATE', NULL, 'product_code=12; product_name=Giniling; category_id=3; brand=Argentina; unit=Pieces; cost_price=31.00; selling_price=35.00; stock_quantity=20; reorder_level=0; on_order_level=0; expiry_date=2028-01-01; is_active=1', 'product_code=12; product_name=Giniling; category_id=3; brand=Argentina; unit=Pieces; cost_price=31.00; selling_price=35.00; stock_quantity=19; reorder_level=0; on_order_level=0; expiry_date=2028-01-01; is_active=1', '2026-04-28 12:08:58'),
(115, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:07:52', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:07:52', '2026-04-28 12:46:58'),
(116, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:07:52', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:46:58', '2026-04-28 12:46:58'),
(117, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', '2026-04-28 12:48:07'),
(118, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-27 19:23:45', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', '2026-04-28 12:48:07'),
(119, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:46:58', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:46:58', '2026-04-28 12:59:49'),
(120, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:46:58', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 12:59:49'),
(121, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 13:00:20'),
(122, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 13:01:23'),
(123, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 13:14:53'),
(124, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', '2026-04-28 13:18:49'),
(125, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', '2026-04-28 13:19:12'),
(126, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 20:48:07', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:12', '2026-04-28 13:19:12'),
(127, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:12', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:12', '2026-04-28 13:19:28'),
(128, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:12', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:28', '2026-04-28 13:19:28'),
(129, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:28', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:28', '2026-04-28 13:20:05'),
(130, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:19:28', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', '2026-04-28 13:20:05'),
(131, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', '2026-04-28 13:20:22'),
(132, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', '2026-04-28 13:20:34'),
(133, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', '2026-04-28 13:20:34'),
(134, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', '2026-04-28 13:23:05'),
(135, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', '2026-04-28 13:24:18'),
(136, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', '2026-04-28 13:28:21'),
(137, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:20:34', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:21', '2026-04-28 13:28:21'),
(138, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:21', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:21', '2026-04-28 13:28:38'),
(139, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:21', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', '2026-04-28 13:28:38'),
(140, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', '2026-04-28 13:29:00'),
(141, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', '2026-04-28 13:29:17'),
(142, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', '2026-04-28 13:29:40'),
(143, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', '2026-04-28 13:35:05'),
(144, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:28:38', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', '2026-04-28 13:35:05'),
(145, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', '2026-04-28 13:49:54'),
(146, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', '2026-04-28 13:50:19'),
(147, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:35:05', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', '2026-04-28 13:50:19'),
(148, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', '2026-04-28 13:53:40'),
(149, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', '2026-04-28 13:55:36'),
(150, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', '2026-04-28 13:55:47'),
(151, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:50:19', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', '2026-04-28 13:55:47'),
(152, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', '2026-04-28 13:56:03'),
(153, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', '2026-04-28 13:56:13'),
(154, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', '2026-04-28 13:56:25'),
(155, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:55:47', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', '2026-04-28 13:56:25'),
(156, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', '2026-04-28 13:56:42'),
(157, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', '2026-04-28 14:00:38'),
(158, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', '2026-04-28 14:05:46'),
(159, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 14:06:15'),
(160, 'users', 15, 'UPDATE', 15, 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', 'username=sedsed; full_name=sedriiicc; employee_no=2004; email=sedsed@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 20:59:49', '2026-04-28 14:10:25'),
(161, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 14:11:22'),
(162, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 14:29:15'),
(163, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', '2026-04-28 14:29:33'),
(164, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 21:56:25', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:29:33', '2026-04-28 14:29:33'),
(165, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 14:29:54'),
(166, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:29:33', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:29:33', '2026-04-28 14:30:33'),
(167, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:29:33', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:33', '2026-04-28 14:30:33'),
(168, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:33', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:33', '2026-04-28 14:30:51'),
(169, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:33', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:51', '2026-04-28 14:30:51'),
(170, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:51', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:51', '2026-04-28 14:31:07'),
(171, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:30:51', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:07', '2026-04-28 14:31:07'),
(172, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:07', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:07', '2026-04-28 14:31:39'),
(173, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:07', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:39', '2026-04-28 14:31:39'),
(174, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:39', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:39', '2026-04-28 14:31:57'),
(175, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:39', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:57', '2026-04-28 14:31:57'),
(176, 'users', 4, 'UPDATE', 4, 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:57', 'username=NexGenAdmin; full_name=System Administrator; employee_no=ADMIN-001; email=mattraileyvaldevia@gmail.com; role=system_admin; account_status=active; can_inventory=0; can_sales=0; can_sales_analytics=0; can_accounts_receivable=0; last_login_at=2026-04-28 22:31:57', '2026-04-28 14:32:18'),
(177, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', '2026-04-28 14:32:34'),
(178, 'users', 14, 'UPDATE', 14, 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 17:05:11', 'username=Elwin; full_name=Farma; employee_no=5555; email=win@gmail.com; role=owner; account_status=active; can_inventory=1; can_sales=1; can_sales_analytics=1; can_accounts_receivable=1; last_login_at=2026-04-28 22:32:34', '2026-04-28 14:32:34');

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
(8, 'Others', '2026-03-07 17:10:41'),
(9, 'Powdered Drink', '2026-04-12 16:23:34'),
(10, 'Instant Noodle', '2026-04-23 11:47:35');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `customer_name`, `phone`, `email`, `address`, `status`, `created_at`) VALUES
(1, 'CUS-20260401-001', 'Avery Isaac Valdevia', '09055194166', 'averyisaac17@gmail.com', 'Pasig City', 1, '2026-04-01 04:43:32'),
(2, 'CUS-20260401-002', 'Avery Isaac Valdevia', '09055194166', 'averyisaac17@gmail.com', 'Pasig City', 1, '2026-04-01 04:45:45'),
(3, 'CUS-20260401-003', 'Betlag', '', '', '', 1, '2026-04-01 06:24:47'),
(4, 'CUS-20260401-004', 'Lance Karl', '09278126727', 'lance@gmail.com', 'Tiga Pasig', 1, '2026-04-01 06:34:14'),
(5, 'CUS-20260401-005', 'Utol', '', '', '', 1, '2026-04-01 07:23:21'),
(6, 'CUS-20260401-006', 'luk', '', '', '', 1, '2026-04-01 07:28:49'),
(7, 'CUS-20260401-007', 'Jethro', '09526257824', 'jethro@gmail.com', 'Pasig', 1, '2026-04-01 07:52:34'),
(8, 'CUS-20260407-001', 'Joshua Isla', '09021438342', 'joshuaisla3@gmail.com', 'Gruar Cainta, Rizal', 1, '2026-04-07 09:05:57'),
(9, 'CUS-20260412-001', 'Omri', '45478514', 'omri@gmail.com', 'Tiga Gruar kela Isla', 1, '2026-04-12 15:31:27'),
(10, 'CUS-20260412-002', 'Skye', '0901313', 'skye@gmail.com', 'kela Sed na bahay', 1, '2026-04-12 17:10:02'),
(11, 'CUS-20260414-001', 'Justine', '0190290', 'justine@gmail.com', 'Taytay, Rizal', 1, '2026-04-14 07:02:46'),
(12, 'CUS-20260414-002', 'Princess Halley Valdevia', '09295232222', 'princess@gmail.com', 'Tiga Pasig', 1, '2026-04-14 07:30:23'),
(13, 'CUS-20260414-003', 'Tery Magbanua', '093238429499', 'maria@gmail.com', 'Pasig City', 1, '2026-04-14 08:32:38'),
(14, 'CUS-20260414-004', 'Vincent Valdevia', '093238429499', 'maria@gmail.com', 'Taytay, Rizal', 1, '2026-04-14 08:34:30'),
(15, 'CUS-20260424-001', 'Ariawin Suarezz', '', '', '', 1, '2026-04-24 14:28:28'),
(16, 'CUS-20260424-002', 'Ariawin Suarezz', '09303318958', 'elwinasuarez1207@gmail.com', 'dyan lang', 1, '2026-04-24 14:34:12');

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
(1, '01', 'Coke Mismo', 1, 'Coca-cola', 'Bottles', 18.00, 20.00, 27, 0, 0, '2026-06-01', 'uploads/products/product_1773228185_69b150990af49.png', 'Drink Responsibly', 1, '2026-03-11 11:23:05', '2026-04-24 14:52:10'),
(4, '02', 'Lemon Soda', 1, 'Lemon Juicy', 'Bottles', 8.00, 10.00, 25, 0, 0, '2026-06-01', 'uploads/products/product_1773229034_69b153eab6209.png', 'Drink Responsibly', 1, '2026-03-11 11:37:14', '2026-04-24 14:37:31'),
(5, '03', 'Redhorse Beer', 1, 'Redhorse', 'Bottles', 65.00, 70.00, 17, 0, 0, '2026-06-01', 'uploads/products/product_1773229187_69b1548384ab3.png', 'Drink Responsibly', 1, '2026-03-11 11:39:47', '2026-04-12 15:33:54'),
(6, '04', 'Alfonso Light', 1, 'Alfonso', 'Bottles', 250.00, 285.00, 9, 0, 0, '2026-09-01', 'uploads/products/product_1773229443_69b15583b8421.png', 'Drink Responsibly', 1, '2026-03-11 11:44:03', '2026-04-28 11:26:07'),
(7, '05', 'SanMig Light', 1, 'SanMig', 'Bottles', 50.00, 55.00, 20, 0, 0, '2026-09-01', 'uploads/products/product_1773229544_69b155e8f1eb2.png', 'Drink Responsibly', 1, '2026-03-11 11:45:44', '2026-03-12 14:30:05'),
(8, '06', 'Gin Bilog', 1, 'Ginebra', 'Bottles', 65.00, 75.00, 18, 0, 0, '2026-09-01', 'uploads/products/product_1773229701_69b15685306b9.png', 'Drink Responsibly', 1, '2026-03-11 11:48:21', '2026-04-14 11:54:21'),
(9, '07', 'Dutchmill Superfruits', 1, 'Dutch Mill', 'Packs', 20.00, 25.00, 37, 0, 0, '2026-12-01', 'uploads/products/product_1773229904_69b15750758d9.png', 'Drink Responsibly', 1, '2026-03-11 11:51:44', '2026-04-28 12:08:21'),
(10, '08', 'Dutchmill Blueberry', 1, 'Dutch Mill', 'Packs', 13.00, 17.00, 38, 0, 0, '2026-12-01', 'uploads/products/product_1773230071_69b157f71c18c.png', 'Drink Reponsibly', 1, '2026-03-11 11:54:31', '2026-04-24 14:34:49'),
(11, '09', 'Chuckie', 1, 'Nestle', 'Packs', 20.00, 25.00, 27, 0, 0, '2027-04-01', 'uploads/products/product_1773230261_69b158b5391bd.png', 'Drink Responsibly', 1, '2026-03-11 11:57:41', '2026-04-28 11:47:37'),
(12, '10', 'Mega Sardines', 3, 'Mega', 'Pieces', 25.00, 29.00, 20, 0, 0, '2027-01-01', 'uploads/products/product_1773230392_69b159381c9e6.png', 'Consume Responsibly', 1, '2026-03-11 11:59:52', '2026-04-01 06:25:11'),
(13, '11', 'Centure Tuna', 3, 'Century', 'Pieces', 38.00, 43.00, 18, 0, 0, '2028-01-01', 'uploads/products/product_1773230465_69b159816347b.png', 'Consume Responsibly', 1, '2026-03-11 12:01:05', '2026-04-01 05:14:39'),
(14, '12', 'Giniling', 3, 'Argentina', 'Pieces', 31.00, 35.00, 19, 0, 0, '2028-01-01', 'uploads/products/product_1773230576_69b159f0ca965.png', 'Consume Responsibly', 1, '2026-03-11 12:02:56', '2026-04-28 12:08:58'),
(16, '13', 'Funtastik Tocino', 4, 'CDO', 'Packs', 55.00, 62.00, 12, 0, 0, '2027-01-01', 'uploads/products/product_1773230866_69b15b120759a.png', 'Consume Responsibly', 1, '2026-03-11 12:07:46', '2026-04-01 07:21:07'),
(17, '14', 'Idol Cheesedog', 4, 'CDO', 'Packs', 55.00, 62.00, 20, 0, 0, '2027-01-01', 'uploads/products/product_1773230981_69b15b8592979.png', 'Consume Responsibly', 1, '2026-03-11 12:09:41', '2026-03-11 12:09:41'),
(18, '15', 'Chicken Nuggets', 4, 'STAR', 'Packs', 50.00, 55.00, 18, 0, 0, '2027-01-01', 'uploads/products/product_1773231091_69b15bf394249.png', 'Consume Responsibly', 1, '2026-03-11 12:11:31', '2026-04-01 05:14:39'),
(19, '16', 'Lighter', 6, 'Torch', 'Pieces', 10.00, 16.00, 22, 0, 0, '2999-01-01', 'uploads/products/product_1773231235_69b15c8396e53.png', 'Be Responsible', 1, '2026-03-11 12:13:55', '2026-04-01 07:51:15'),
(20, '17', 'Joy Calamansi', 6, 'Joy', 'Pieces', 10.00, 14.00, 18, 0, 0, '2028-01-01', 'uploads/products/product_1773231401_69b15d29f092d.png', 'Be Reponsible', 1, '2026-03-11 12:16:41', '2026-04-12 17:02:51'),
(21, '18', 'Joy Lemon', 6, 'Joy', 'Pieces', 10.00, 14.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773231468_69b15d6c3bd64.png', 'Be Responsible', 0, '2026-03-11 12:17:48', '2026-04-14 09:38:35'),
(22, '19', 'Mantika', 8, 'Mantika', 'Bottles', 30.00, 35.00, 12, 0, 0, '2027-01-01', 'uploads/products/product_1773231782_69b15ea64691b.png', 'Be Responsible', 1, '2026-03-11 12:23:02', '2026-03-11 12:23:02'),
(23, '20', 'Crispy Fry', 8, 'Ajinomoto', 'Pieces', 13.00, 21.00, 15, 0, 0, '2028-01-01', 'uploads/products/product_1773231948_69b15f4c7e0f3.png', 'Be Responsible', 1, '2026-03-11 12:25:48', '2026-03-11 12:25:48'),
(24, '21', 'Mang Tomas Sarsa', 8, 'Mang Tomas', 'Pieces', 35.00, 42.00, 17, 0, 0, '2028-01-01', 'uploads/products/product_1773232022_69b15f969a6e7.png', 'Be Responsible', 1, '2026-03-11 12:27:02', '2026-04-12 17:04:40'),
(25, '22', 'Rexona Men', 5, 'Rexona', 'Pieces', 7.00, 10.00, 0, 0, 0, '2028-01-01', 'uploads/products/product_1773232307_69b160b32527c.png', 'Be Responsible', 1, '2026-03-11 12:31:47', '2026-04-01 07:28:58'),
(26, '23', 'Cream Silk Pink', 5, 'Cream Silk', 'Pieces', 8.00, 11.00, 8, 10, 0, '2028-01-01', 'uploads/products/product_1773232385_69b16101ddbdb.png', 'Be Responsible', 1, '2026-03-11 12:33:05', '2026-04-01 07:25:25'),
(27, '24', 'Sunsilk Aloe Vera', 5, 'Sunsilk', 'Pieces', 6.00, 9.00, 20, 0, 0, '2028-01-01', 'uploads/products/product_1773232468_69b16154047b8.png', 'Be Responsible', 1, '2026-03-11 12:34:28', '2026-03-11 12:34:28'),
(28, '25', 'Pillows', 2, 'Oishi', 'Packs', 8.00, 11.00, 12, 0, 0, '2027-01-01', 'uploads/products/product_1773232559_69b161afec3d1.png', 'Consume Responsibly', 1, '2026-03-11 12:35:59', '2026-04-12 17:10:49'),
(29, '26', 'Cheezy', 2, 'Leslie\'s', 'Pieces', 8.00, 11.00, 15, 0, 0, '2027-01-01', 'uploads/products/product_1773232643_69b162037f52c.png', 'Consume Responsibly', 1, '2026-03-11 12:37:23', '2026-04-14 08:07:28'),
(30, '27', 'Potato Crisp Onion', 2, 'Oishi', 'Pieces', 8.00, 11.00, 3, 0, 0, '2027-01-01', 'uploads/products/product_1773232715_69b1624bc379a.png', 'Consume Responsibly', 0, '2026-03-11 12:38:35', '2026-04-23 11:43:50'),
(32, '28', 'Cup Noodles Beef', 10, 'Nissin', 'pcs', 30.00, 35.00, 40, 10, 0, '2027-01-01', 'uploads/products/product_1776944943_69ea072f8614f.png', 'Eat Responsibly', 1, '2026-04-23 11:49:03', '2026-04-23 11:49:03'),
(33, '777', 'Loaded Cheese', 2, 'Stateline', 'pcs', 10.00, 9.98, 10, 5, 0, '2026-05-29', 'uploads/products/default.png', '', 0, '2026-04-24 15:01:53', '2026-04-24 15:17:35');

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `trg_products_audit_archive` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
    IF OLD.is_active <> NEW.is_active THEN
        INSERT INTO audit_logs (
            table_name,
            record_id,
            action_type,
            changed_by,
            old_values,
            new_values
        ) VALUES (
            'products',
            NEW.id,
            'UPDATE',
            NULL,
            CONCAT('is_active=', OLD.is_active),
            CONCAT('is_active=', NEW.is_active)
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_products_audit_delete` AFTER DELETE ON `products` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (
        table_name,
        record_id,
        action_type,
        changed_by,
        old_values,
        new_values
    ) VALUES (
        'products',
        OLD.id,
        'DELETE',
        NULL,
        CONCAT(
            'product_code=', IFNULL(OLD.product_code, ''),
            '; product_name=', IFNULL(OLD.product_name, ''),
            '; category_id=', IFNULL(OLD.category_id, ''),
            '; brand=', IFNULL(OLD.brand, ''),
            '; unit=', IFNULL(OLD.unit, ''),
            '; cost_price=', IFNULL(OLD.cost_price, ''),
            '; selling_price=', IFNULL(OLD.selling_price, ''),
            '; stock_quantity=', IFNULL(OLD.stock_quantity, ''),
            '; reorder_level=', IFNULL(OLD.reorder_level, ''),
            '; on_order_level=', IFNULL(OLD.on_order_level, ''),
            '; expiry_date=', IFNULL(OLD.expiry_date, ''),
            '; is_active=', IFNULL(OLD.is_active, '')
        ),
        NULL
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_products_audit_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (
        table_name,
        record_id,
        action_type,
        changed_by,
        old_values,
        new_values
    ) VALUES (
        'products',
        NEW.id,
        'INSERT',
        NULL,
        NULL,
        CONCAT(
            'product_code=', IFNULL(NEW.product_code, ''),
            '; product_name=', IFNULL(NEW.product_name, ''),
            '; category_id=', IFNULL(NEW.category_id, ''),
            '; brand=', IFNULL(NEW.brand, ''),
            '; unit=', IFNULL(NEW.unit, ''),
            '; cost_price=', IFNULL(NEW.cost_price, ''),
            '; selling_price=', IFNULL(NEW.selling_price, ''),
            '; stock_quantity=', IFNULL(NEW.stock_quantity, ''),
            '; reorder_level=', IFNULL(NEW.reorder_level, ''),
            '; on_order_level=', IFNULL(NEW.on_order_level, ''),
            '; expiry_date=', IFNULL(NEW.expiry_date, ''),
            '; is_active=', IFNULL(NEW.is_active, '')
        )
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_products_audit_update` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (
        table_name,
        record_id,
        action_type,
        changed_by,
        old_values,
        new_values
    ) VALUES (
        'products',
        NEW.id,
        'UPDATE',
        NULL,
        CONCAT(
            'product_code=', IFNULL(OLD.product_code, ''),
            '; product_name=', IFNULL(OLD.product_name, ''),
            '; category_id=', IFNULL(OLD.category_id, ''),
            '; brand=', IFNULL(OLD.brand, ''),
            '; unit=', IFNULL(OLD.unit, ''),
            '; cost_price=', IFNULL(OLD.cost_price, ''),
            '; selling_price=', IFNULL(OLD.selling_price, ''),
            '; stock_quantity=', IFNULL(OLD.stock_quantity, ''),
            '; reorder_level=', IFNULL(OLD.reorder_level, ''),
            '; on_order_level=', IFNULL(OLD.on_order_level, ''),
            '; expiry_date=', IFNULL(OLD.expiry_date, ''),
            '; is_active=', IFNULL(OLD.is_active, '')
        ),
        CONCAT(
            'product_code=', IFNULL(NEW.product_code, ''),
            '; product_name=', IFNULL(NEW.product_name, ''),
            '; category_id=', IFNULL(NEW.category_id, ''),
            '; brand=', IFNULL(NEW.brand, ''),
            '; unit=', IFNULL(NEW.unit, ''),
            '; cost_price=', IFNULL(NEW.cost_price, ''),
            '; selling_price=', IFNULL(NEW.selling_price, ''),
            '; stock_quantity=', IFNULL(NEW.stock_quantity, ''),
            '; reorder_level=', IFNULL(NEW.reorder_level, ''),
            '; on_order_level=', IFNULL(NEW.on_order_level, ''),
            '; expiry_date=', IFNULL(NEW.expiry_date, ''),
            '; is_active=', IFNULL(NEW.is_active, '')
        )
    );
END
$$
DELIMITER ;

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
(2, 'REQ-20260314-0002', 'EMP-030', 'Chu K Umani', 'chuchu@gmail.com', '09784574125', 'Tiga kela Sed', 'Chuchu', '$2y$10$jiWRmhk3ttT76vSyi8xP9OAySYUnowPON3L7gnisGPG5nvbpVyQfu', 'uploads/valid_ids/valid_id_69b58496be2010.52169437.jpg', 'employee', 'approved', 'No code', 4, '2026-03-14 17:09:21', '2026-03-14 15:53:58'),
(3, 'REQ-20260407-0001', '10', 'Joshua Malvar Isla', 'joshuaisla3@gmail.com', '09565038115', 'Gruar Cainta Rizal', 'Joshua', '$2y$10$7kyu20xZez4pHDt2j2ZdkuRAg1QR5X8ZtopUDQIQZ5WmjM2AKXfmq', 'uploads/valid_ids/valid_id_69d4c44d790201.79995650.png', 'owner', 'approved', 'Approved by system administrator', 4, '2026-04-07 10:47:27', '2026-04-07 08:46:05'),
(4, 'REQ-20260407-0002', '11', 'Elviona Suarez', 'elvionasuarez1207@gmail.com', '09094399525', 'Blk 13 Arenda Brgy San Juan Cainta Rizal', 'Vion', '$2y$10$X0dUR5uVZRN3IU.ySqrOheiJZmTsx5zCIJBU5PKowX7bfonvfvoWW', 'uploads/valid_ids/valid_id_69d4c788d38326.16659538.jpg', 'employee', 'approved', 'Approved by system administrator', 4, '2026-04-07 11:00:42', '2026-04-07 08:59:52'),
(5, 'REQ-20260408-0001', '007', 'Elwina Suarez', 'mariaelwinasuarez07@gmail.com', '09303318958', 'Blk 13 Sitio Arinda Brgy San Juan Cainta Rizal', 'Wina', '$2y$10$fJ/jFhzDyBG7TayDVG8P5eU6LNlJ9ACbIfwMajIlJNfKNC8evr442', 'uploads/valid_ids/valid_id_69d5e38b39b044.69773426.png', 'owner', 'approved', 'Approved by system administrator', 4, '2026-04-08 07:12:39', '2026-04-08 05:11:39'),
(6, 'REQ-20260408-0002', 'oo', 'RAC', 'rac.microenterprise@gmail.com', '12345', 'dyan lang', 'RAC', '$2y$10$qmcoNU4rTNUDlea4/exLBu8N8.NMIx9Zw2aQsi.KvRW5p4HpUm7Uu', 'uploads/valid_ids/valid_id_69d6057267f976.18547648.png', 'owner', 'approved', 'Approved by system administrator', 4, '2026-04-08 09:36:55', '2026-04-08 07:36:18'),
(7, 'REQ-20260412-0001', 'EMP-024', 'Avery Isaac Valdevia', 'averyisaac17@gmail.com', '09325865747', '32 Amethyst St. Greenpark Village Cainta, Rizal', 'averyval', '$2y$10$PnLZaek77AI2PCnr4pPxZuJjxLlsw4S5HM4cD9tgkyVuI.52GN7yK', 'uploads/valid_ids/valid_id_69dbb6caeff840.16254119.png', 'employee', 'approved', 'pasok ka na boi', 4, '2026-04-13 12:06:55', '2026-04-12 15:14:19'),
(8, 'REQ-20260413-0001', 'EMP-111', 'Sonia Macasieb', 'SoniaMacasieb@gmail.com', '092437636527', 'bahay ni sonia', 'sonia', '$2y$10$zbm2vzfar9CKpN0KQPM6kOl7rxGhhefiHkkzwpOSIkCzsn3fj2TSC', 'uploads/valid_ids/valid_id_69dcc0fa80f519.05901125.png', 'employee', 'rejected', 'kasalanan ni kevin durant', 4, '2026-04-13 12:12:27', '2026-04-13 10:10:02'),
(9, 'REQ-20260413-0002', 'EMP-365', 'Matt Valdevia', 'common@gmail.com', '09957266803', 'Pasig City', 'common_matter', '$2y$10$tV8fEqu/HUPU9rk3oBptt.IAEYFm9xDoQ7czP5AXhJG8YeFDrmIOy', 'uploads/valid_ids/valid_id_69dd0fd716bf68.18573264.png', 'owner', 'approved', 'You\'re Desi! Welcome to our club!', 4, '2026-04-13 17:47:22', '2026-04-13 15:46:31'),
(10, 'REQ-20260413-0003', 'EMP-150', 'Avery Isaac Valdevia', 'housetons@gmail.com', '09102819302', 'Pasig City', 'mchousetons', '$2y$10$bLvz1FMM9Co8wFHKsQ1evuSDadA0h54HC7LRMeVMcyUHb5DnHytDS', 'uploads/valid_ids/valid_id_69dd107bd16759.71785823.png', 'employee', 'resubmit', 'Tarub kaba pls', 4, '2026-04-13 18:17:14', '2026-04-13 15:49:15'),
(11, 'REQ-20260416-0001', 'EMP-300', 'Joshua Malvar Isla', 'joshuaisla04@gmail.com', '09565038115', 'Gruar Cainta Rizal', 'Joshie', '$2y$10$PRLCnIxDSI2CufbLKo7te.Glu9ha4O6JHAlJA4rtnh40ncAig0eLe', 'uploads/valid_ids/valid_id_69e07767604e08.15732705.jpg', 'employee', 'approved', 'Acccepted! <3', 4, '2026-04-16 07:46:22', '2026-04-16 05:45:11'),
(12, 'REQ-20260424-0001', '5555', 'Farma', 'win@gmail.com', '09303318958', 'sa tabi', 'Elwin', '$2y$10$QwwxTj9eASQdSoxSp0jny.PLR2/4kZTnHqqU84RO5BJbuYeNoVlr2', 'uploads/valid_ids/valid_id_69eb7c20360783.95488227.png', 'owner', 'approved', 'Approved by system administrator', 4, '2026-04-24 16:20:40', '2026-04-24 14:20:16'),
(13, 'REQ-20260424-0002', 'Yoonwin', 'yoon win', 'yoonwinmin0709@gmail.com', '09303318958', 'dyan lang', 'yonwin', '$2y$10$xCnSF3sj8vJ4bV1Rx7G/Ne3xdZHZGR7sp76xIaEDyNjrQqgM7yo7K', 'uploads/valid_ids/valid_id_69eb870ce06916.09612755.jpg', 'owner', 'rejected', 'Incomplete valid ID submitted.', 4, '2026-04-24 17:07:32', '2026-04-24 15:06:53'),
(14, 'REQ-20260427-0001', '2004', 'sedriiicc', 'sedsed@gmail.com', '09543765432', 'dyan lang', 'sedsed', '$2y$10$RjOnjF8ljrzeWXqe8FU43.xuOh5Wu5TLKny68wW/Ki.b1XSqpCEqa', 'uploads/valid_ids/valid_id_69ef2f87627442.66866205.jpg', 'owner', 'approved', 'yes', 4, '2026-04-27 11:58:48', '2026-04-27 09:42:31');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
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

INSERT INTO `sales` (`id`, `sales_no`, `salesperson_id`, `customer_id`, `total_amount`, `payment_status`, `payment_method`, `order_status`, `sale_date`, `created_at`) VALUES
(1, 'SAL-20260311-134045', 2, NULL, 33.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-11 20:42:09', '2026-03-11 12:42:09'),
(2, 'SAL-20260311-141241', 2, NULL, 586.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-11 21:14:09', '2026-03-11 13:14:09'),
(3, 'SAL-20260312-062922', 2, NULL, 50.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 13:30:21', '2026-03-12 05:30:21'),
(4, 'SAL-20260312-064713', 2, NULL, 89.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 13:47:37', '2026-03-12 05:47:37'),
(5, 'SAL-20260312-070148', 2, NULL, 110.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:02:02', '2026-03-12 06:02:02'),
(6, 'SAL-20260312-070910', 2, NULL, 42.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:09:21', '2026-03-12 06:09:21'),
(7, 'SAL-20260312-072034', 2, NULL, 60.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:20:59', '2026-03-12 06:20:59'),
(8, 'SAL-20260312-073230', 1, NULL, 375.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 14:32:49', '2026-03-12 06:32:49'),
(9, 'SAL-20260312-111755', 2, NULL, 30.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 18:18:15', '2026-03-12 10:18:15'),
(10, 'SAL-20260312-123626', 2, NULL, 344.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 19:36:54', '2026-03-12 11:36:54'),
(11, 'SAL-20260312-152937', 2, NULL, 331.00, 'Paid', 'Cash', 'Fulfilled', '2026-03-12 22:30:05', '2026-03-12 14:30:05'),
(12, 'SAL-20260313-044006', 2, NULL, 570.00, 'Partially Paid', 'Cash', 'Fulfilled', '2026-03-13 11:40:46', '2026-03-13 03:40:46'),
(13, 'SAL-20260314-102408', 2, NULL, 50.00, 'Unpaid', 'GCash', 'Fulfilled', '2026-03-14 17:29:53', '2026-03-14 09:29:53'),
(14, 'SAL-20260401-071258', 6, 2, 181.00, 'Partially Paid', 'Cash', 'Fulfilled', '2026-04-01 13:14:39', '2026-04-01 05:14:39'),
(15, 'SAL-20260401-082418', 6, 3, 87.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-01 14:25:11', '2026-04-01 06:25:11'),
(16, 'SAL-20260401-082520', 6, 4, 125.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-01 14:34:56', '2026-04-01 06:34:56'),
(17, 'SAL-20260401-092251', 6, 5, 132.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-01 15:25:25', '2026-04-01 07:25:25'),
(18, 'SAL-20260401-092720', 6, 6, 200.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-01 15:28:58', '2026-04-01 07:28:58'),
(19, 'SAL-20260401-095044', 6, NULL, 32.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-01 15:51:15', '2026-04-01 07:51:15'),
(20, 'SAL-20260401-095122', 6, 7, 56.00, 'Paid', 'GCash', 'Fulfilled', '2026-04-01 15:53:01', '2026-04-01 07:53:01'),
(21, 'SAL-20260407-110415', 6, 8, 660.00, 'Partially Paid', 'Cash', 'Fulfilled', '2026-04-07 17:06:52', '2026-04-07 09:06:52'),
(22, 'SAL-20260412-162323', 2, NULL, 85.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-12 22:23:40', '2026-04-12 14:23:40'),
(23, 'SAL-20260412-172839', 10, NULL, 75.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-12 23:29:08', '2026-04-12 15:29:08'),
(24, 'SAL-20260412-173041', 10, 9, 210.00, 'Partially Paid', 'GCash', 'Fulfilled', '2026-04-12 23:33:54', '2026-04-12 15:33:54'),
(25, 'SAL-20260412-182957', 10, NULL, 60.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-13 00:31:07', '2026-04-12 16:31:07'),
(26, 'SAL-20260412-190234', 10, NULL, 42.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-13 01:02:51', '2026-04-12 17:02:51'),
(27, 'SAL-20260412-190426', 10, NULL, 84.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-13 01:04:40', '2026-04-12 17:04:40'),
(28, 'SAL-20260412-190851', 10, 10, 33.00, 'Partially Paid', 'Cash', 'Pending', '2026-04-13 01:10:49', '2026-04-12 17:10:49'),
(29, 'SAL-20260413-183633', 1, NULL, 165.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 00:37:07', '2026-04-13 16:37:07'),
(30, 'SAL-20260414-090150', 10, 11, 60.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 15:03:28', '2026-04-14 07:03:28'),
(31, 'SAL-20260414-090545', 10, 12, 100.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 16:00:49', '2026-04-14 08:00:49'),
(32, 'SAL-20260414-100100', 10, 8, 44.00, 'Partially Paid', 'Cash', 'Pending', '2026-04-14 16:07:28', '2026-04-14 08:07:28'),
(33, 'SAL-20260414-103143', 10, 13, 85.00, 'Unpaid', 'Cash', 'Pending', '2026-04-14 16:33:39', '2026-04-14 08:33:39'),
(34, 'SAL-20260414-103355', 10, 14, 30.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 16:35:54', '2026-04-14 08:35:54'),
(35, 'SAL-20260414-113131', 10, NULL, 22.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 17:33:20', '2026-04-14 09:33:20'),
(36, 'SAL-20260414-113624', 10, NULL, 70.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 17:36:38', '2026-04-14 09:36:38'),
(37, 'SAL-20260414-135314', 10, NULL, 150.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-14 19:54:21', '2026-04-14 11:54:21'),
(42, 'SAL-20260423-132434', 2, 12, 75.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-23 19:25:06', '2026-04-23 11:25:06'),
(43, 'SAL-20260424-162603', 14, 15, 50.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-24 22:29:16', '2026-04-24 14:29:16'),
(44, 'SAL-20260424-163218', 14, 16, 34.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-24 22:34:49', '2026-04-24 14:34:49'),
(45, 'SAL-20260428-132535', 15, NULL, 285.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-28 19:26:07', '2026-04-28 11:26:07'),
(46, 'SAL-20260428-134710', 15, NULL, 25.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-28 19:47:37', '2026-04-28 11:47:37'),
(47, 'SAL-20260428-140800', 15, NULL, 25.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-28 20:08:21', '2026-04-28 12:08:21'),
(48, 'SAL-20260428-140851', 15, NULL, 35.00, 'Paid', 'Cash', 'Fulfilled', '2026-04-28 20:08:58', '2026-04-28 12:08:58');

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
(18, 13, 9, 2, 25.00, 0.00),
(19, 14, 13, 2, 43.00, 86.00),
(20, 14, 4, 4, 10.00, 40.00),
(21, 14, 18, 1, 55.00, 55.00),
(22, 15, 12, 3, 29.00, 87.00),
(23, 16, 11, 5, 25.00, 125.00),
(24, 17, 26, 12, 11.00, 132.00),
(25, 18, 25, 20, 10.00, 200.00),
(26, 19, 19, 2, 16.00, 32.00),
(27, 20, 20, 4, 14.00, 56.00),
(28, 21, 6, 1, 285.00, 285.00),
(29, 21, 8, 5, 75.00, 375.00),
(30, 22, 10, 5, 17.00, 85.00),
(31, 23, 11, 3, 25.00, 75.00),
(32, 24, 5, 3, 70.00, 210.00),
(34, 26, 20, 3, 14.00, 42.00),
(35, 27, 24, 2, 42.00, 84.00),
(36, 28, 28, 3, 11.00, 33.00),
(37, 29, 30, 15, 11.00, 165.00),
(38, 30, 1, 3, 20.00, 60.00),
(39, 31, 9, 4, 25.00, 100.00),
(40, 32, 29, 4, 11.00, 44.00),
(41, 33, 10, 5, 17.00, 85.00),
(42, 34, 4, 3, 10.00, 30.00),
(43, 35, 30, 2, 11.00, 22.00),
(44, 36, 21, 5, 14.00, 70.00),
(45, 37, 8, 2, 75.00, 150.00),
(50, 42, 9, 3, 25.00, 75.00),
(51, 43, 11, 2, 25.00, 50.00),
(52, 44, 10, 2, 17.00, 34.00),
(53, 45, 6, 1, 285.00, 285.00),
(54, 46, 11, 1, 25.00, 25.00),
(55, 47, 9, 1, 25.00, 25.00),
(56, 48, 14, 1, 35.00, 35.00);

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
(14, 9, 'stock_out', 2, 'Auto-deducted from sale: SAL-20260314-102408', 2, '2026-03-14 09:29:53'),
(15, 13, 'stock_out', 2, 'Sale recorded: SAL-20260401-071258', 6, '2026-04-01 05:14:39'),
(16, 4, 'stock_out', 4, 'Sale recorded: SAL-20260401-071258', 6, '2026-04-01 05:14:39'),
(17, 18, 'stock_out', 1, 'Sale recorded: SAL-20260401-071258', 6, '2026-04-01 05:14:39'),
(18, 12, 'stock_out', 3, 'Sale recorded: SAL-20260401-082418', 6, '2026-04-01 06:25:11'),
(19, 11, 'stock_out', 5, 'Sale recorded: SAL-20260401-082520', 6, '2026-04-01 06:34:56'),
(20, 26, 'stock_out', 12, 'Sale recorded: SAL-20260401-092251', 6, '2026-04-01 07:25:25'),
(21, 25, 'stock_out', 20, 'Sale recorded: SAL-20260401-092720', 6, '2026-04-01 07:28:58'),
(22, 19, 'stock_out', 2, 'Sale recorded: SAL-20260401-095044', 6, '2026-04-01 07:51:15'),
(23, 20, 'stock_out', 4, 'Sale recorded: SAL-20260401-095122', 6, '2026-04-01 07:53:01'),
(24, 6, 'stock_out', 1, 'Sale recorded: SAL-20260407-110415', 6, '2026-04-07 09:06:52'),
(25, 8, 'stock_out', 5, 'Sale recorded: SAL-20260407-110415', 6, '2026-04-07 09:06:52'),
(26, 30, 'stock_out', 5, '', NULL, '2026-04-12 13:30:20'),
(27, 10, 'stock_out', 5, 'Sale recorded: SAL-20260412-162323', 2, '2026-04-12 14:23:40'),
(28, 11, 'stock_out', 3, 'Sale recorded: SAL-20260412-172839', 10, '2026-04-12 15:29:08'),
(29, 5, 'stock_out', 3, 'Sale recorded: SAL-20260412-173041', 10, '2026-04-12 15:33:54'),
(33, 20, 'stock_out', 3, 'Sale recorded: SAL-20260412-190234', 10, '2026-04-12 17:02:51'),
(34, 24, 'stock_out', 2, 'Sale recorded: SAL-20260412-190426', 10, '2026-04-12 17:04:40'),
(35, 28, 'stock_out', 3, 'Sale recorded: SAL-20260412-190851', 10, '2026-04-12 17:10:49'),
(36, 30, 'stock_out', 15, 'Sale recorded: SAL-20260413-183633', 1, '2026-04-13 16:37:07'),
(37, 29, 'stock_in', 5, '', NULL, '2026-04-13 16:39:13'),
(38, 30, 'stock_in', 5, '', NULL, '2026-04-13 17:09:32'),
(39, 1, 'stock_out', 3, 'Sale recorded: SAL-20260414-090150', 10, '2026-04-14 07:03:28'),
(40, 9, 'stock_out', 4, 'Sale recorded: SAL-20260414-090545', 10, '2026-04-14 08:00:49'),
(41, 29, 'stock_out', 4, 'Sale recorded: SAL-20260414-100100', 10, '2026-04-14 08:07:28'),
(42, 10, 'stock_out', 5, 'Sale recorded: SAL-20260414-103143', 10, '2026-04-14 08:33:40'),
(43, 4, 'stock_out', 3, 'Sale recorded: SAL-20260414-103355', 10, '2026-04-14 08:35:54'),
(44, 30, 'stock_out', 2, 'Sale recorded: SAL-20260414-113131', 10, '2026-04-14 09:33:20'),
(45, 21, 'stock_out', 5, 'Sale recorded: SAL-20260414-113624', 10, '2026-04-14 09:36:38'),
(46, 8, 'stock_out', 2, 'Sale recorded: SAL-20260414-135314', 10, '2026-04-14 11:54:21'),
(47, 9, 'stock_out', 3, 'Sale recorded: SAL-20260423-132434', 2, '2026-04-23 11:25:06'),
(48, 11, 'stock_out', 2, 'Sale recorded: SAL-20260424-162603', 14, '2026-04-24 14:29:16'),
(49, 10, 'stock_out', 2, 'Sale recorded: SAL-20260424-163218', 14, '2026-04-24 14:34:49'),
(50, 6, 'stock_out', 1, 'Sale recorded: SAL-20260428-132535', 15, '2026-04-28 11:26:07'),
(51, 11, 'stock_out', 1, 'Sale recorded: SAL-20260428-134710', 15, '2026-04-28 11:47:37'),
(52, 9, 'stock_out', 1, 'Sale recorded: SAL-20260428-140800', 15, '2026-04-28 12:08:21'),
(53, 14, 'stock_out', 1, 'Sale recorded: SAL-20260428-140851', 15, '2026-04-28 12:08:58');

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
  `last_failed_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `employee_no`, `email`, `phone`, `address`, `otp_preference`, `otp_code`, `otp_expires_at`, `password`, `role`, `account_status`, `profile_image`, `valid_id_path`, `is_verified`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `can_inventory`, `can_sales`, `can_sales_analytics`, `can_accounts_receivable`, `last_login_at`, `failed_login_attempts`, `locked_until`, `last_failed_login_at`) VALUES
(1, 'Sedric', 'Sedric Macasieb', NULL, 'sedricmacasieb29@gmail.com', '09925853329', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$8LTEkfEdEj2w5Wi2W3UT..VatOEd/OYZ75Uqc/4aygeroWMMQGtru', 'owner', 'active', 'uploads/profile_69ae63b4f27ea6.93611954.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-06 15:19:38', 1, 1, 1, 1, '2026-04-14 00:35:37', 0, NULL, NULL),
(2, 'Desinomeme', 'Matt Railey Valdevia', NULL, 'mvaldevia@gmail.com', '09957266803', '443 Avocado St. Napico Manggahan Pasig City mayor vico i lab u', 'email', NULL, NULL, '$2y$10$ugVIrlkRfIH4rsi/XujzceWS5VBzFM512rnrKIUIRnm/x7AGptGci', 'owner', 'active', 'uploads/profile_69ac3303264562.74696813.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-07 14:15:31', 1, 1, 1, 1, '2026-04-23 19:05:29', 0, NULL, NULL),
(4, 'NexGenAdmin', 'System Administrator', 'ADMIN-001', 'mattraileyvaldevia@gmail.com', '09957266803', 'Main Office', 'email', '848122', '2026-04-27 13:46:21', '$2y$10$xUbWxg5FjIoo3YRR64iRrOxvFMzlkSPM8v5IQJNhX2hTSndfaT57u', 'system_admin', 'active', 'uploads/profile_69b57647dc3837.12693462.png', NULL, 1, NULL, NULL, NULL, '2026-03-14 13:13:10', 0, 0, 0, 0, '2026-04-28 22:31:57', 1, NULL, '2026-04-28 22:32:18'),
(5, 'Juswa', 'Joshua Malvar Isla', NULL, 'joshuaisla@gmail.com', '09034562422', NULL, 'email', NULL, NULL, '$2y$10$RSkUCsvf0SlWffXpVFJW2elcp.x0YiOy9p7qSsiLOdTllslnMBUcq', 'employee', 'pending', 'uploads/profile_69b56a8f504900.42054425.jpg', NULL, 0, NULL, NULL, NULL, '2026-03-14 14:02:55', 1, 1, 0, 1, NULL, 0, NULL, NULL),
(6, 'Chuchu', 'Chu K Umani', 'EMP-030', 'chuchu@gmail.com', '09784574125', 'Tiga kela Sed', 'email', NULL, NULL, '$2y$10$jiWRmhk3ttT76vSyi8xP9OAySYUnowPON3L7gnisGPG5nvbpVyQfu', 'employee', 'active', 'uploads/profile_69b58869e83ae8.54802663.jpg', 'uploads/valid_ids/valid_id_69b58496be2010.52169437.jpg', 1, 4, '2026-03-14 17:09:21', NULL, '2026-03-14 16:09:21', 1, 0, 0, 0, '2026-04-13 10:22:39', 0, NULL, NULL),
(7, 'Joshua', 'Joshua Malvar Isla', '10', 'joshuaisla3@gmail.com', '09565038115', 'Gruar Cainta Rizal', 'email', NULL, NULL, '$2y$10$7kyu20xZez4pHDt2j2ZdkuRAg1QR5X8ZtopUDQIQZ5WmjM2AKXfmq', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69d4c44d790201.79995650.png', 1, 4, '2026-04-07 10:47:27', NULL, '2026-04-07 08:47:27', 0, 0, 0, 0, NULL, 0, NULL, NULL),
(8, 'Vion', 'Elviona Suarez', '11', 'elvionasuarez1207@gmail.com', '09094399525', 'Blk 13 Arenda Brgy San Juan Cainta Rizal', 'email', NULL, NULL, '$2y$10$X0dUR5uVZRN3IU.ySqrOheiJZmTsx5zCIJBU5PKowX7bfonvfvoWW', 'employee', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69d4c788d38326.16659538.jpg', 1, 4, '2026-04-07 11:00:42', NULL, '2026-04-07 09:00:42', 1, 1, 0, 1, NULL, 0, NULL, NULL),
(9, 'Wina', 'Elwina Suarez', '007', 'mariaelwinasuarez07@gmail.com', '09303318958', 'Blk 13 Sitio Arinda Brgy San Juan Cainta Rizal', 'email', NULL, NULL, '$2y$10$fJ/jFhzDyBG7TayDVG8P5eU6LNlJ9ACbIfwMajIlJNfKNC8evr442', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69d5e38b39b044.69773426.png', 1, 4, '2026-04-08 07:12:39', NULL, '2026-04-08 05:12:39', 1, 1, 1, 1, NULL, 0, NULL, NULL),
(10, 'RAC', 'RAC', 'oo', 'rac.microenterprise@gmail.com', '12345', 'dyan lang', 'email', NULL, NULL, '$2y$10$kRqZ3rKRPahNKJJJ.ZBVIOx.NKnx.WbzW0PnEDMoNpDVI9B.Omdve', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69d6057267f976.18547648.png', 1, 4, '2026-04-08 09:36:55', NULL, '2026-04-08 07:36:55', 1, 1, 1, 1, '2026-04-15 11:47:55', 0, NULL, NULL),
(11, 'averyval', 'Avery Isaac Valdevia', 'EMP-024', 'averyisaac17@gmail.com', '09325865747', '32 Amethyst St. Greenpark Village Cainta, Rizal', 'email', NULL, NULL, '$2y$10$PnLZaek77AI2PCnr4pPxZuJjxLlsw4S5HM4cD9tgkyVuI.52GN7yK', 'employee', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69dbb6caeff840.16254119.png', 1, 4, '2026-04-13 12:06:55', NULL, '2026-04-13 10:06:55', 1, 0, 0, 1, '2026-04-13 19:49:17', 0, NULL, NULL),
(12, 'common_matter', 'Matt Valdevia', 'EMP-365', 'common@gmail.com', '09957266803', 'Pasig City', 'email', NULL, NULL, '$2y$10$tV8fEqu/HUPU9rk3oBptt.IAEYFm9xDoQ7czP5AXhJG8YeFDrmIOy', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_69dd0fd716bf68.18573264.png', 1, 4, '2026-04-13 17:47:22', NULL, '2026-04-13 15:47:22', 1, 1, 1, 1, '2026-04-13 23:47:40', 0, NULL, NULL),
(13, 'Joshie', 'Joshua Malvar Isla', 'EMP-300', 'joshuaisla04@gmail.com', '09565038115', 'Gruar Cainta Rizal', 'email', NULL, NULL, '$2y$10$PRLCnIxDSI2CufbLKo7te.Glu9ha4O6JHAlJA4rtnh40ncAig0eLe', 'employee', 'active', 'uploads/profile_69e077ed3f5e87.44891711.jpg', 'uploads/valid_ids/valid_id_69e07767604e08.15732705.jpg', 1, 4, '2026-04-16 07:46:22', NULL, '2026-04-16 05:46:22', 1, 1, 0, 1, '2026-04-16 19:42:32', 0, NULL, NULL),
(14, 'Elwin', 'Farma', '5555', 'win@gmail.com', '09303318958', 'sa tabi', 'email', NULL, NULL, '$2y$10$QwwxTj9eASQdSoxSp0jny.PLR2/4kZTnHqqU84RO5BJbuYeNoVlr2', 'owner', 'active', 'uploads/profile_69eb7caf493a50.85691126.jpg', 'uploads/valid_ids/valid_id_69eb7c20360783.95488227.png', 1, 4, '2026-04-24 16:20:40', NULL, '2026-04-24 14:20:40', 1, 1, 1, 1, '2026-04-28 22:32:34', 0, NULL, NULL),
(15, 'sedsed', 'sedriiicc', '2004', 'sedsed@gmail.com', '09543765432', 'dyan lang', 'email', '035445', '2026-04-28 18:21:56', '$2y$10$RjOnjF8ljrzeWXqe8FU43.xuOh5Wu5TLKny68wW/Ki.b1XSqpCEqa', 'owner', 'active', 'uploads/profile_69f0afa3786b51.93262467.png', 'uploads/valid_ids/valid_id_69ef2f87627442.66866205.jpg', 1, 4, '2026-04-27 11:58:48', NULL, '2026-04-27 09:58:48', 1, 1, 1, 1, '2026-04-28 20:59:49', 3, '2026-04-28 22:25:25', '2026-04-28 22:10:25');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `trg_users_audit_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (
        table_name,
        record_id,
        action_type,
        changed_by,
        old_values,
        new_values
    ) VALUES (
        'users',
        NEW.id,
        'INSERT',
        NEW.id,
        NULL,
        CONCAT(
            'username=', IFNULL(NEW.username, ''),
            '; full_name=', IFNULL(NEW.full_name, ''),
            '; employee_no=', IFNULL(NEW.employee_no, ''),
            '; email=', IFNULL(NEW.email, ''),
            '; role=', IFNULL(NEW.role, ''),
            '; account_status=', IFNULL(NEW.account_status, ''),
            '; can_inventory=', IFNULL(NEW.can_inventory, ''),
            '; can_sales=', IFNULL(NEW.can_sales, ''),
            '; can_sales_analytics=', IFNULL(NEW.can_sales_analytics, ''),
            '; can_accounts_receivable=', IFNULL(NEW.can_accounts_receivable, ''),
            '; last_login_at=', IFNULL(NEW.last_login_at, '')
        )
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (
        table_name,
        record_id,
        action_type,
        changed_by,
        old_values,
        new_values
    ) VALUES (
        'users',
        NEW.id,
        'UPDATE',
        NEW.id,
        CONCAT(
            'username=', IFNULL(OLD.username, ''),
            '; full_name=', IFNULL(OLD.full_name, ''),
            '; employee_no=', IFNULL(OLD.employee_no, ''),
            '; email=', IFNULL(OLD.email, ''),
            '; role=', IFNULL(OLD.role, ''),
            '; account_status=', IFNULL(OLD.account_status, ''),
            '; can_inventory=', IFNULL(OLD.can_inventory, ''),
            '; can_sales=', IFNULL(OLD.can_sales, ''),
            '; can_sales_analytics=', IFNULL(OLD.can_sales_analytics, ''),
            '; can_accounts_receivable=', IFNULL(OLD.can_accounts_receivable, ''),
            '; last_login_at=', IFNULL(OLD.last_login_at, '')
        ),
        CONCAT(
            'username=', IFNULL(NEW.username, ''),
            '; full_name=', IFNULL(NEW.full_name, ''),
            '; employee_no=', IFNULL(NEW.employee_no, ''),
            '; email=', IFNULL(NEW.email, ''),
            '; role=', IFNULL(NEW.role, ''),
            '; account_status=', IFNULL(NEW.account_status, ''),
            '; can_inventory=', IFNULL(NEW.can_inventory, ''),
            '; can_sales=', IFNULL(NEW.can_sales, ''),
            '; can_sales_analytics=', IFNULL(NEW.can_sales_analytics, ''),
            '; can_accounts_receivable=', IFNULL(NEW.can_accounts_receivable, ''),
            '; last_login_at=', IFNULL(NEW.last_login_at, '')
        )
    );
END
$$
DELIMITER ;

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
  ADD KEY `fk_receivable_user` (`created_by`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `idx_products_category_id` (`category_id`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `idx_registration_requests_status` (`request_status`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_no` (`sales_no`),
  ADD KEY `fk_sales_user` (`salesperson_id`),
  ADD KEY `fk_sales_customer` (`customer_id`),
  ADD KEY `idx_sales_sale_date` (`sale_date`);

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
  ADD KEY `fk_stock_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  ADD CONSTRAINT `fk_receivable_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receivable_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receivable_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
