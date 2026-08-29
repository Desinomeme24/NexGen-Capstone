-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 30, 2026 at 12:32 AM
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
-- Dumping data for table `accounts_receivable`
--

INSERT INTO `accounts_receivable` (`id`, `business_id`, `sale_id`, `customer_id`, `total_amount`, `amount_paid`, `balance_due`, `due_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(24, 11, 76, 18, 77.40, 50.00, 27.40, '2026-10-10', 'Partially Paid', NULL, 16, '2026-08-29 14:27:48', '2026-08-29 14:27:48'),
(25, 11, 77, 18, 38.70, 20.00, 18.70, '2026-09-02', 'Partially Paid', NULL, 16, '2026-08-29 21:02:05', '2026-08-29 21:02:05'),
(26, 11, 78, 18, 51.70, 20.00, 31.70, '2026-09-04', 'Partially Paid', NULL, 16, '2026-08-29 21:39:25', '2026-08-29 21:39:25'),
(27, 11, 79, 18, 51.70, 10.00, 41.70, '2026-09-04', 'Partially Paid', NULL, 16, '2026-08-29 22:29:06', '2026-08-29 22:29:06');

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
(54, 1, 'approve_request', 'registration_request', 20, 'Approved request #20 (REQ-20260807-0001) and created user #4', '6e9839b7eab47c677107b95c42acfe9cd59d527f70320dacbcd93c1999acdf4c', '03f68c930ffbe5e839e1f1f79da4d84681ca7f7704f843b8994dde296f51aa0b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-06 16:37:06'),
(55, 1, 'approve_request', 'registration_request', 21, 'Approved request #21 (REQ-20260820-0001) and created user #5', '03f68c930ffbe5e839e1f1f79da4d84681ca7f7704f843b8994dde296f51aa0b', '010c2ed2e262f6a4c7bf20f93a4f9434db15a1921661af01ed156607c33f23fb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 08:18:44'),
(56, 1, 'resubmit_request', 'registration_request', 22, 'Marked request #22 for resubmission', '010c2ed2e262f6a4c7bf20f93a4f9434db15a1921661af01ed156607c33f23fb', '5509c62e1bb381c7c1308a3b60ee0c6c277a4f76ac5efeaed592232b2c2098cf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 11:50:43'),
(57, 1, 'approve_request', 'registration_request', 22, 'Approved request #22 (REQ-20260820-0002) and created user #6', '5509c62e1bb381c7c1308a3b60ee0c6c277a4f76ac5efeaed592232b2c2098cf', '37663a413f7d0cea4bba710b0d79e995c2d6104c1c50a10e6449fd542431f7e9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 11:51:39'),
(58, 1, 'reject_request', 'registration_request', 23, 'Rejected request #23', '37663a413f7d0cea4bba710b0d79e995c2d6104c1c50a10e6449fd542431f7e9', '0addb752093d3b5423f1a882fa1bcb32da28c77400f28972dcd78f09914037bb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 12:00:31'),
(59, 1, 'approve_request', 'registration_request', 25, 'Approved request #25 (REQ-20260820-0005) and created user #7', '0addb752093d3b5423f1a882fa1bcb32da28c77400f28972dcd78f09914037bb', 'a16749d20d3fbc60c1c8401b97809f23641b5d9412a6bd843db0f60a8503ab8a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 12:18:21'),
(60, 1, 'approve_request', 'registration_request', 26, 'Approved request #26 (REQ-20260820-0006) and created user #8', 'a16749d20d3fbc60c1c8401b97809f23641b5d9412a6bd843db0f60a8503ab8a', '709d352d99a40ce67898c6b5f1150ae16407ba61e25480010ee9f8e5b367eebd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 12:30:35'),
(64, 1, 'approve_request', 'registration_request', 27, 'Approved request #27 (REQ-20260821-0001) and created user #9', '709d352d99a40ce67898c6b5f1150ae16407ba61e25480010ee9f8e5b367eebd', 'efbd88ce5fa0d910f6e60fa384d536502c1b89c01412d5fe5533a4979865ec65', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 11:35:22'),
(65, 1, 'approve_request', 'registration_request', 29, 'Approved request #29 (REQ-20260825-0002) and created user #10', 'efbd88ce5fa0d910f6e60fa384d536502c1b89c01412d5fe5533a4979865ec65', '3f998c06da905f657876edf7f88157a246b1ce56a33098d3db7608adfeccc0e4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 15:53:26'),
(66, 1, 'approve_request', 'registration_request', 30, 'Approved request #30 (REQ-20260826-0001) and created user #11', '3f998c06da905f657876edf7f88157a246b1ce56a33098d3db7608adfeccc0e4', '0d392716984afae2a9642de18ae87c4589a6a2ab11d6230c9e7919e5ee1a37a7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 16:20:40'),
(67, 1, 'approve_request', 'registration_request', 31, 'Approved request #31 (REQ-20260826-0002) and created user #12', '0d392716984afae2a9642de18ae87c4589a6a2ab11d6230c9e7919e5ee1a37a7', 'd1fa2533db163f7a6b967bdbc9a71ff57bda66ad415d4a569ba43caea9eff143', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 16:26:09'),
(68, 1, 'approve_request', 'registration_request', 32, 'Approved request #32 (REQ-20260826-0003) and created user #13', 'd1fa2533db163f7a6b967bdbc9a71ff57bda66ad415d4a569ba43caea9eff143', '706ff0adc51cca7be50c208b4ba9f17789d6b82c7bfdf5cb9b6f8156d3b2ae73', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 16:36:48'),
(69, 1, 'approve_request', 'registration_request', 34, 'Approved request #34 (REQ-20260826-0002) and created user #14 for SME-F31938 / BR-B54F1B52 (East Branch)', '706ff0adc51cca7be50c208b4ba9f17789d6b82c7bfdf5cb9b6f8156d3b2ae73', 'b6364f06c1a588d62c49da856aad2dba0ea63cea2b53f83bdceef0bb595d42d6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 17:03:25'),
(70, 1, 'update_user_permissions', 'user', 7, 'Updated user #7 (Arianna) - role: employee, workspace: Valdevia Entreprises / Main Branch (SME-7CA227 / BR-L00000003), status: disabled, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 1', 'b6364f06c1a588d62c49da856aad2dba0ea63cea2b53f83bdceef0bb595d42d6', '2fe8a865f09d3d6b241a22b4b081d6c8e3614e0490e8ee6387c6801d53e35783', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:35:51'),
(71, 1, 'update_user_permissions', 'user', 2, 'Updated user #2 (Desinomeme) - role: owner, workspace: Concentrix / Main Branch (SME-C842D3 / BR-L00000001), status: disabled, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', '2fe8a865f09d3d6b241a22b4b081d6c8e3614e0490e8ee6387c6801d53e35783', '99b4e24c05a091a4af948b91d8bacdd12fdb4df30fdd73cb7ccebce3ffc011d6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:39:39'),
(72, 1, 'update_user_permissions', 'user', 2, 'Updated user #2 (Desinomeme) - role: owner, workspace: Concentrix / Main Branch (SME-C842D3 / BR-L00000001), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', '99b4e24c05a091a4af948b91d8bacdd12fdb4df30fdd73cb7ccebce3ffc011d6', '120e383bbc3f80ff6bb2f43acbbdccddb49bd4d9b2f47b70f5a28b13b77ab195', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:42:54'),
(73, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: Vion Test Mart / East Branch (SME-F31938 / BR-B54F1B52), status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', '120e383bbc3f80ff6bb2f43acbbdccddb49bd4d9b2f47b70f5a28b13b77ab195', '53a6d35ab1a9e0676bb6113fbba302e8fb3685c09f7a8ba583be4fd9a5530720', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:47:42'),
(74, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: Vion Test Mart / East Branch (SME-F31938 / BR-B54F1B52), status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 1', '53a6d35ab1a9e0676bb6113fbba302e8fb3685c09f7a8ba583be4fd9a5530720', '9659cdd3ee6385d5d079bc18da0ed0ff86c852bf58d709c3a4af2cf7c1f7d50b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:53:53'),
(75, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: Vion Test Mart / East Branch (SME-F31938 / BR-B54F1B52), status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 0', '9659cdd3ee6385d5d079bc18da0ed0ff86c852bf58d709c3a4af2cf7c1f7d50b', 'a42304c18a89b9d5a6f33604a3c2d98bf2f3e498eff8dffbadb86d210b5f5b35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 20:54:11'),
(76, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: preserved business/branch assignment (business_id: 6), status: active, verified: 1, inventory: 1, sales: 0, analytics: 0, accounts_receivable: 0', 'a42304c18a89b9d5a6f33604a3c2d98bf2f3e498eff8dffbadb86d210b5f5b35', 'adb65bf3e8c41be9bde1c08e900e72a4ed8719cb0501096a80378ceb7a8e698d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 21:38:28'),
(77, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: preserved business/branch assignment (business_id: 6), status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 1', 'adb65bf3e8c41be9bde1c08e900e72a4ed8719cb0501096a80378ceb7a8e698d', 'dac4fb26c66c3534b085c2ba5b4468fb3dcc772940862b34b4607f965fa646ac', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 21:38:42'),
(78, 1, 'update_user_permissions', 'user', 14, 'Updated user #14 (east_branch_employee) - role: employee, workspace: preserved business/branch assignment (business_id: 6), status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 0', 'dac4fb26c66c3534b085c2ba5b4468fb3dcc772940862b34b4607f965fa646ac', 'cd74308a5cb6c9d48b2fff6da0ba7fad9be80c676437abaf6a20384fc3c596df', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-25 21:38:56'),
(79, 1, 'update_user_permissions', 'user', 10, 'Updated user #10 (branch_test_owner) - role: owner, workspace: preserved business/branch assignment (business_id: 6), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', 'cd74308a5cb6c9d48b2fff6da0ba7fad9be80c676437abaf6a20384fc3c596df', 'eb2a3a1f7ba9706daf62e43e970dfd2e78ca2f5d9e3295e38b0b3b232ca7a498', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-26 18:51:00'),
(80, 1, 'update_user_permissions', 'user', 10, 'Updated user #10 (branch_test_owner) - role: owner, workspace: preserved business/branch assignment (business_id: 6), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 0', 'eb2a3a1f7ba9706daf62e43e970dfd2e78ca2f5d9e3295e38b0b3b232ca7a498', '1142705ad21c7c12730046658c73a1316048d05c5299c8949727d6afe52a5d89', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-26 18:54:12'),
(81, 1, 'approve_request', 'registration_request', 35, 'Approved request #35 (REQ-20260828-0001) and created user #15 for SME-0F765D / BR-9A813F28 (Main Branch)', '1142705ad21c7c12730046658c73a1316048d05c5299c8949727d6afe52a5d89', 'a0b380d948181b8a00b176bab51c1f1bab1b6dcd5a3e5ab7adccb5b201538417', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-27 20:40:56'),
(82, 1, 'resubmit_request', 'registration_request', 24, 'Marked request #24 for resubmission', 'a0b380d948181b8a00b176bab51c1f1bab1b6dcd5a3e5ab7adccb5b201538417', '5a98388a601cf4f5128f0453219cc037db66b7f5b1d752a7279321454a1df51f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-27 21:26:18'),
(83, 1, 'update_user_permissions', 'user', 7, 'Updated user #7 (Arianna) - role: employee, workspace: preserved business/branch assignment (business_id: 3), status: active, verified: 1, inventory: 1, sales: 1, analytics: 0, accounts_receivable: 1', '5a98388a601cf4f5128f0453219cc037db66b7f5b1d752a7279321454a1df51f', 'bd87b450168995baf1a1cd61eda4936db8ea4e58d3955f69e41e686996f4fedb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 18:34:32'),
(84, 1, 'approve_request', 'registration_request', 36, 'Approved request #36 (REQ-20260829-5C22E2B8) and created user #16 for SME-663A3B / BR-0F0D3CA4 (Main Branch)', 'bd87b450168995baf1a1cd61eda4936db8ea4e58d3955f69e41e686996f4fedb', 'ca4769df719f663b00e1253f3c3deb50cfd08c67db932ca978522c22da60c12d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 18:41:14'),
(85, 1, 'update_user_permissions', 'user', 16, 'Updated user #16 (Jackie) - role: owner, workspace: preserved business/branch assignment (business_id: 10), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', 'ca4769df719f663b00e1253f3c3deb50cfd08c67db932ca978522c22da60c12d', 'a9504256be89bf4c691f09b4d9facfc872185d27dad2c4f710a1dc745fe63898', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 19:13:26'),
(86, 1, 'update_user_permissions', 'user', 16, 'Updated user #16 (Jackie) - role: owner, workspace: preserved business/branch assignment (business_id: 10), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 0', 'a9504256be89bf4c691f09b4d9facfc872185d27dad2c4f710a1dc745fe63898', '26d2f2f13b7b646b3a25ac82c53ac0791c7bb331ae212d25cd1ba4f72635c90d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 19:17:31'),
(87, 1, 'approve_request', 'registration_request', 38, 'Approved request #38 (REQ-20260829-8291FF00) and created user #17 for SME-663A3B / BR-0F0D3CA4 (Main Branch)', '26d2f2f13b7b646b3a25ac82c53ac0791c7bb331ae212d25cd1ba4f72635c90d', '287e8081f139bda46dcad9ace31df9583aa316a5d3b1d9bf6ce0da53fd6650a2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 19:34:31'),
(88, 1, 'reject_request', 'registration_request', 37, 'Rejected request #37', '287e8081f139bda46dcad9ace31df9583aa316a5d3b1d9bf6ce0da53fd6650a2', '596a492665f23a479bb665dc70d96f394504cf4b1d1d25beda5530b45b488e0e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 19:35:31'),
(89, 1, 'approve_workspace_request', 'workspace_request', 3, 'Approved WRQ-20260829-05D8BF and created SME-47C8D918 / BR-1F28CD7F', '596a492665f23a479bb665dc70d96f394504cf4b1d1d25beda5530b45b488e0e', '543ff2e9ad0b5f48f5417cc3158b51daf7190c340dce085c8f9a04346141acd2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 21:59:03'),
(90, 1, 'update_user_permissions', 'user', 16, 'Updated user #16 (Jackie) - role: owner, workspace: preserved business/branch assignment (business_id: 11), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', '543ff2e9ad0b5f48f5417cc3158b51daf7190c340dce085c8f9a04346141acd2', '17f6303eaace95050ae4ec6dff7b1cb6c992bd72e2ba75b07c4c673501ee23ba', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 22:03:50'),
(91, 1, 'update_user_permissions', 'user', 16, 'Updated user #16 (Jackie) - role: owner, workspace: preserved business/branch assignment (business_id: 11), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 0', '17f6303eaace95050ae4ec6dff7b1cb6c992bd72e2ba75b07c4c673501ee23ba', '804d93c5854b6d00fb2ac3368c29173bdbe421706de072d0708638f225b64d95', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 22:22:00'),
(92, 1, 'resubmit_request', 'registration_request', 24, 'Updated request #24 for resubmission; changed: email, username', '804d93c5854b6d00fb2ac3368c29173bdbe421706de072d0708638f225b64d95', '142b5a186e0b6c49ae20229542860f1eb409e90599d4b1c7d89a53320b9e46b5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:15:28'),
(93, 1, 'reject_workspace_request', 'workspace_request', 2, 'Rejected workspace request WRQ-20260828-EF7740', '142b5a186e0b6c49ae20229542860f1eb409e90599d4b1c7d89a53320b9e46b5', '557570579ef040afe0374ee1e5701cb852193912c0612434cce36b66e5c4efe7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:17:09'),
(94, 1, 'approve_workspace_request', 'workspace_request', 1, 'Approved WRQ-20260828-6B6383 and created SME-2923830B / BR-2C64B352', '557570579ef040afe0374ee1e5701cb852193912c0612434cce36b66e5c4efe7', 'fd3ba8eff8027c7a4b2325607515d07b3bdbb196798d90fac886ab6bac983cc9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:18:14'),
(95, 1, 'approve_workspace_request', 'workspace_request', 4, 'Approved WRQ-20260829-384621 and created SME-47C8D918 / BR-ED480070', 'fd3ba8eff8027c7a4b2325607515d07b3bdbb196798d90fac886ab6bac983cc9', '02bc94115ec6633d4d2099fff9e7e30482dde0d349665e992694e9952895a69f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:52:45'),
(96, 1, 'resubmit_request', 'registration_request', 28, 'Updated request #28 for resubmission; changed: email', '02bc94115ec6633d4d2099fff9e7e30482dde0d349665e992694e9952895a69f', '9dbc5d24009e4bdef6ae86e44dee28e2ac4bcf812872564b0aae7690ed134286', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:54:16'),
(97, 1, 'approve_request', 'registration_request', 28, 'Approved request #28 (REQ-20260825-0001) and created user #18 for SME-3D764B / BR-AED32A85 (Main Branch)', '9dbc5d24009e4bdef6ae86e44dee28e2ac4bcf812872564b0aae7690ed134286', '3faba245761c6eda53d6fb8f8193938f248af8b95525bff701159812eb9b7eae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:54:53'),
(98, 1, 'reject_request', 'registration_request', 24, 'Rejected request #24 (REQ-20260820-0004)', '3faba245761c6eda53d6fb8f8193938f248af8b95525bff701159812eb9b7eae', '908ec465bb1da3b85c123c773b709d3b1029dbdc1f74a5678494e23f1e8f1c89', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 09:55:38'),
(99, 1, 'update_user_permissions', 'user', 18, 'Updated user #18 (Tester1) - role: owner, workspace: preserved business/branch assignment (business_id: 14), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', '908ec465bb1da3b85c123c773b709d3b1029dbdc1f74a5678494e23f1e8f1c89', '59bde9c1a00a2961dd22137533399811d39705735a892eafe9bd8354ef3bfb33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 14:12:28'),
(100, 1, 'update_user_permissions', 'user', 18, 'Updated user #18 (Tester1) - role: owner, workspace: preserved business/branch assignment (business_id: 14), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 0', '59bde9c1a00a2961dd22137533399811d39705735a892eafe9bd8354ef3bfb33', '95f5f87008f11329c8c4c1f9e638ce38d3fa6cf66dd1b0cd180a6e9cc81f17d6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 14:21:52'),
(101, 1, 'update_user_permissions', 'user', 16, 'Updated user #16 (Jackie) - role: owner, workspace: preserved business/branch assignment (business_id: 10), status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 1', '95f5f87008f11329c8c4c1f9e638ce38d3fa6cf66dd1b0cd180a6e9cc81f17d6', '8a0e5c1667bf9c00bdf2ffbda0d07452db7ebd1f899aa9c3616f160371213ce6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 14:22:04');

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
  `business_code` varchar(20) NOT NULL,
  `business_entity_id` int(11) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(150) NOT NULL,
  `is_main_branch` tinyint(1) NOT NULL DEFAULT 0,
  `branch_status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `business_name`, `business_type`, `business_address`, `created_at`, `business_code`, `business_entity_id`, `branch_code`, `branch_name`, `is_main_branch`, `branch_status`) VALUES
(1, 'Concentrix', 'Other SME', 'Pasig City', '2026-08-06 07:10:22', 'SME-C842D3', 4, 'BR-L00000001', 'Main Branch', 1, 'active'),
(2, 'Omri Bakery Shop', 'Retail Store', 'Cainta, Rizal', '2026-08-06 07:19:13', 'SME-A96981', 3, 'BR-L00000002', 'Main Branch', 1, 'active'),
(3, 'Valdevia Entreprises', 'Mini Grocery', 'Pasig City', '2026-08-20 11:51:39', 'SME-7CA227', 2, 'BR-L00000003', 'Main Branch', 1, 'active'),
(4, 'Halley Enterprises', 'Mini Grocery', '443 Avocado St. Napico Manggahan, Pasig Ctiy', '2026-08-21 11:35:22', 'SME-5F6738', 1, 'BR-L00000004', 'Main Branch', 1, 'active'),
(5, 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', '10 Main Street, Cainta, Rizal', '2026-08-25 15:53:26', 'SME-F31938', 8, 'BR-1BBE89CA', 'Main Branch', 1, 'active'),
(6, 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', '25 East Road, Pasig City', '2026-08-25 16:00:10', 'SME-F31938', 8, 'BR-B54F1B52', 'East Branch', 0, 'active'),
(7, 'Vion Test Pharmacy', 'Pharmacy / Drugstore', '50 Pharmacy Street, Cainta, Rizal', '2026-08-25 17:13:28', 'SME-572B7802', 9, 'BR-33C64368', 'Main Branch', 1, 'active'),
(8, 'Vion Test Pharmacy', 'Pharmacy / Drugstore', '75 New Street, Cainta, Rizal', '2026-08-25 17:15:58', 'SME-150C7711', 10, 'BR-B92734A7', 'Pharmacy Branch', 1, 'active'),
(9, 'Theresa Enterprises', 'School / Office Supplies', '32 Pearl St. Napico Manggahan, Pasig City', '2026-08-27 20:40:56', 'SME-0F765D', 11, 'BR-9A813F28', 'Main Branch', 1, 'active'),
(10, 'Valdevia Entreprises', 'Mini Grocery / Sari-Sari Store', 'Pasig City', '2026-08-28 18:41:14', 'SME-663A3B', 12, 'BR-0F0D3CA4', 'Main Branch', 1, 'active'),
(11, 'Magbanua Enterprises', 'Mini Grocery / Sari-Sari Store', '67 Chinatown St. New York, New York', '2026-08-28 21:59:03', 'SME-47C8D918', 13, 'BR-1F28CD7F', 'Main Branch', 1, 'active'),
(12, 'Vion Hardware & Services', 'Hardware / Construction Supplies', '10 Oak St. Baranggay Sta. Ana Taytay, Rizal', '2026-08-29 09:18:14', 'SME-2923830B', 14, 'BR-2C64B352', 'Main Branch', 1, 'active'),
(13, 'Magbanua Enterprises', 'Mini Grocery / Sari-Sari Store', '41 Golden State CA, United States', '2026-08-29 09:52:45', 'SME-47C8D918', 13, 'BR-ED480070', 'Second Branch', 0, 'active'),
(14, 'Valdevia Entreprises', 'Hardware / Construction Supplies', '443 Avocado St. Napico Manggahan, Pasig Ctiy', '2026-08-29 09:54:53', 'SME-3D764B', 15, 'BR-AED32A85', 'Main Branch', 1, 'active');

--
-- Triggers `businesses`
--
DELIMITER $$
CREATE TRIGGER `trg_businesses_before_insert_workspace` BEFORE INSERT ON `businesses` FOR EACH ROW BEGIN
    DECLARE v_entity_id INT DEFAULT NULL;
    DECLARE v_entity_created TINYINT DEFAULT 0;

    IF NEW.`business_code` IS NULL OR TRIM(NEW.`business_code`) = '' THEN
        SET NEW.`business_code` = CONCAT('SME-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 8)));
    END IF;

    IF NEW.`business_entity_id` IS NULL OR NEW.`business_entity_id` = 0 THEN
        SELECT `id` INTO v_entity_id
        FROM `business_entities`
        WHERE `business_code` = NEW.`business_code`
        LIMIT 1;

        IF v_entity_id IS NULL THEN
            INSERT INTO `business_entities`
                (`business_code`, `business_name`, `business_type`, `created_by`, `status`)
            VALUES
                (NEW.`business_code`, NEW.`business_name`, NEW.`business_type`, NULL, 'active');
            SET v_entity_id = LAST_INSERT_ID();
            SET v_entity_created = 1;
        END IF;

        SET NEW.`business_entity_id` = v_entity_id;
    END IF;

    IF NEW.`branch_code` IS NULL OR TRIM(NEW.`branch_code`) = '' THEN
        SET NEW.`branch_code` = CONCAT('BR-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 8)));
    END IF;

    IF NEW.`branch_name` IS NULL OR TRIM(NEW.`branch_name`) = '' THEN
        SET NEW.`branch_name` = 'Main Branch';
    END IF;

    IF v_entity_created = 1 THEN
        SET NEW.`is_main_branch` = 1;
    END IF;

    IF NEW.`branch_status` IS NULL OR NEW.`branch_status` = '' THEN
        SET NEW.`branch_status` = 'active';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `business_entities`
--

CREATE TABLE `business_entities` (
  `id` int(11) NOT NULL,
  `business_code` varchar(20) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_entities`
--

INSERT INTO `business_entities` (`id`, `business_code`, `business_name`, `business_type`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SME-5F6738', 'Halley Enterprises', 'Mini Grocery', NULL, 'active', '2026-08-21 11:35:22', '2026-08-25 15:34:17'),
(2, 'SME-7CA227', 'Valdevia Entreprises', 'Mini Grocery', NULL, 'active', '2026-08-20 11:51:39', '2026-08-25 15:34:17'),
(3, 'SME-A96981', 'Omri Bakery Shop', 'Retail Store', NULL, 'active', '2026-08-06 07:19:13', '2026-08-25 15:34:17'),
(4, 'SME-C842D3', 'Concentrix', 'Other SME', NULL, 'active', '2026-08-06 07:10:22', '2026-08-25 15:34:17'),
(8, 'SME-F31938', 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', NULL, 'active', '2026-08-25 15:53:26', '2026-08-25 15:53:26'),
(9, 'SME-572B7802', 'Vion Test Pharmacy', 'Pharmacy / Drugstore', 10, 'active', '2026-08-25 17:13:28', '2026-08-25 17:13:28'),
(10, 'SME-150C7711', 'Vion Test Pharmacy', 'Pharmacy / Drugstore', 10, 'active', '2026-08-25 17:15:58', '2026-08-25 17:15:58'),
(11, 'SME-0F765D', 'Theresa Enterprises', 'School / Office Supplies', NULL, 'active', '2026-08-27 20:40:56', '2026-08-27 20:40:56'),
(12, 'SME-663A3B', 'Valdevia Entreprises', 'Mini Grocery / Sari-Sari Store', NULL, 'active', '2026-08-28 18:41:14', '2026-08-28 18:41:14'),
(13, 'SME-47C8D918', 'Magbanua Enterprises', 'Mini Grocery / Sari-Sari Store', 16, 'active', '2026-08-28 21:59:03', '2026-08-28 21:59:03'),
(14, 'SME-2923830B', 'Vion Hardware & Services', 'Hardware / Construction Supplies', 10, 'active', '2026-08-29 09:18:14', '2026-08-29 09:18:14'),
(15, 'SME-3D764B', 'Valdevia Entreprises', 'Hardware / Construction Supplies', NULL, 'active', '2026-08-29 09:54:53', '2026-08-29 09:54:53');

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
(12, 2, 'Canned Goods', '2026-08-06 15:50:35'),
(14, 1, 'Canned Goods', '2026-08-19 07:47:58'),
(17, 1, 'Beverages', '2026-08-20 08:22:48'),
(18, 1, 'Frozen Goods', '2026-08-20 08:23:01'),
(19, 2, 'Powdered Drink', '2026-08-20 12:32:58'),
(22, 5, 'Canned Goods', '2026-08-25 16:08:39'),
(23, 6, 'Canned Goods', '2026-08-25 16:11:35'),
(25, 3, 'Frozen Goods', '2026-08-28 18:37:09'),
(26, 10, 'Frozen Goods', '2026-08-28 20:57:45'),
(27, 10, 'Beverages', '2026-08-28 20:58:05'),
(28, 10, 'Canned Goods', '2026-08-28 20:58:15'),
(29, 10, 'Instant Noodle', '2026-08-28 20:58:24'),
(31, 10, 'Food Spread', '2026-08-28 20:59:00'),
(32, 10, 'Powdered Drink', '2026-08-28 20:59:10'),
(33, 10, 'Personal Hygiene', '2026-08-28 21:13:30'),
(34, 11, 'Canned Goods', '2026-08-28 22:11:31'),
(35, 10, 'Desserts', '2026-08-29 10:26:39'),
(36, 11, 'Personal Hygiene', '2026-08-29 14:53:59');

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

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `business_id`, `customer_code`, `customer_name`, `phone`, `email`, `address`, `status`, `created_at`) VALUES
(17, 1, 'CUS-20260826-001', 'Matt', '', '', '', 1, '2026-08-26 14:44:08'),
(18, 11, 'CUS-20260829-91BFDB', 'Utang1', '09087854785', 'utang1@gmail.com', '443 Pearl St. Pasig City', 1, '2026-08-29 14:27:48');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `user_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`user_id`, `module`, `last_seen_at`) VALUES
(16, 'sales_analytics', '2026-08-29 05:53:20');

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
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 CHECK (`discount_percent` between 0.00 and 100.00),
  `stock_quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` decimal(12,3) NOT NULL DEFAULT 5.000,
  `on_order_level` decimal(12,3) NOT NULL DEFAULT 0.000,
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

INSERT INTO `products` (`id`, `business_id`, `product_code`, `product_name`, `category_id`, `brand`, `unit`, `cost_price`, `selling_price`, `discount_percent`, `stock_quantity`, `reorder_level`, `on_order_level`, `expiry_date`, `product_image`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(34, 1, '01', 'Cup Noodles Sotanghon', 11, 'Nissin', 'pcs', 30.00, 35.00, 0.00, 40.000, 5.000, 0.000, '2027-02-24', 'uploads/products/product_1786000489_6a743469ba0f0.png', 'Eat Responsibly.', 1, '2026-08-06 07:14:49', '2026-08-20 08:30:54'),
(35, 2, '01', 'Ground Pork Mix', 12, 'Argentina', 'pcs', 40.00, 47.00, 0.00, 20.000, 5.000, 0.000, '2028-01-01', 'uploads/products/product_cc14c3a5008edd92.png', 'Eat Responsibly.', 1, '2026-08-06 15:52:00', '2026-08-06 15:52:00'),
(36, 1, '02', 'Alfonso Light', 17, 'Alfonso', 'Bottles', 350.00, 390.00, 0.00, 34.000, 5.000, 0.000, '2028-06-01', 'uploads/products/product_fd7428434756936f.png', 'Drink Responsibly.', 1, '2026-08-20 08:24:18', '2026-08-25 22:00:30'),
(37, 2, '02', 'Bear Brand Fortified', 19, 'Nestle', 'PCS', 10.00, 15.00, 0.00, 10.000, 5.000, 0.000, '2027-07-01', 'uploads/products/product_e4de19b5490021d4.png', 'Consume Responsibly.', 1, '2026-08-20 12:35:25', '2026-08-20 12:35:25'),
(38, 5, 'MAIN-TEST-001', 'Main Branch Test Product', 22, 'Valdevia', 'pcs', 50.00, 60.00, 0.00, 20.000, 5.000, 0.000, NULL, 'uploads/products/product_51304544029a0036.jpg', 'Use Responsibly.', 1, '2026-08-25 16:10:07', '2026-08-26 18:18:52'),
(39, 6, 'EAST-TEST-001', 'East Branch Test Product', 23, 'Suarez', 'pcs', 50.00, 60.00, 0.00, 5.000, 5.000, 0.000, NULL, 'uploads/products/product_7e1153185041d048.jpg', 'Use Responsibly.', 1, '2026-08-25 16:12:45', '2026-08-25 16:12:45'),
(40, 1, '03', 'Idol Cheesedog', 18, 'CDO', 'Packs', 70.00, 80.00, 10.00, 15.000, 5.000, 0.000, '2028-01-01', 'uploads/products/product_68b5e33ad0e50165.png', 'Consume Responsibly.', 1, '2026-08-28 11:07:17', '2026-08-28 11:07:17'),
(41, 3, '01', 'Idol Cheesedog', 25, 'CDO', 'Packs', 70.00, 80.00, 10.00, 12.000, 5.000, 0.000, '2028-01-01', 'uploads/products/product_ceac4e7b3f5dbcc4.png', 'Consume Responsibly.', 1, '2026-08-28 18:38:37', '2026-08-28 18:39:30'),
(42, 10, '01', 'Alfonso Light', 27, 'Alfonso', 'Bottles', 70.00, 85.00, 10.00, 16.000, 5.000, 0.000, NULL, 'uploads/products/product_b0c59444244694ce.png', 'Drink Responsibly.', 1, '2026-08-28 21:00:54', '2026-08-28 21:43:20'),
(43, 10, '02', 'Ground Pork Mix', 28, 'Argentina', 'Pcs', 30.00, 37.00, 10.00, 13.000, 5.000, 0.000, NULL, 'uploads/products/product_c579a61717d77e8d.png', 'Eat Responsibly.', 1, '2026-08-28 21:02:17', '2026-08-28 21:42:51'),
(44, 10, '03', 'Cheez Whiz Original', 31, 'Cheez Whiz', 'Pcs', 30.00, 37.00, 10.00, 4.000, 5.000, 0.000, NULL, 'uploads/products/product_7904a0886e89641b.png', 'Consume Responsibly.', 1, '2026-08-28 21:03:57', '2026-08-28 21:03:57'),
(45, 10, '04', 'Idol Cheesedog', 26, 'CDO', 'Pcs', 50.00, 65.00, 10.00, 0.000, 5.000, 0.000, NULL, 'uploads/products/product_e4ba2e5858fb7db4.png', 'Consume Responsibly.', 1, '2026-08-28 21:06:08', '2026-08-28 21:06:08'),
(46, 10, '05', 'Cheesy Seafood', 29, 'Nissin', 'Pcs', 30.00, 41.00, 10.00, 10.000, 5.000, 0.000, NULL, 'uploads/products/product_f3e5e52b50ce915b.png', 'Eat Responsibly.', 0, '2026-08-28 21:08:07', '2026-08-28 21:08:16'),
(47, 10, '06', 'Bear Brand Fortified', 32, 'Nestle', 'Pcs', 30.00, 41.00, 10.00, 10.000, 5.000, 0.000, NULL, 'uploads/products/product_163a6b709f45adba.png', 'Eat Responsibly.', 0, '2026-08-28 21:09:39', '2026-08-28 21:09:39'),
(48, 10, '07', 'Coke Mismo', 27, 'Coca-cola', 'Bottles', 19.00, 25.00, 10.00, 10.000, 5.000, 0.000, NULL, 'uploads/products/product_c85fb210552a2174.png', 'Drink Responsibly.', 1, '2026-08-28 21:11:44', '2026-08-28 21:41:20'),
(49, 10, '08', 'Cream Silk Pink', 33, 'Cream Silk', 'Pcs', 7.00, 12.00, 0.00, 9.000, 5.000, 0.000, NULL, 'uploads/products/product_fa3c5296055f547c.png', 'Consume Responsibly.', 1, '2026-08-28 21:14:57', '2026-08-28 21:41:53'),
(50, 10, '09', 'Dove Keratin', 33, 'Dove', 'Pcs', 7.00, 12.00, 0.00, 8.000, 5.000, 0.000, NULL, 'uploads/products/product_3176a81397e3ac57.png', 'Consume Responsibly.', 1, '2026-08-28 21:16:08', '2026-08-28 21:44:02'),
(51, 10, '10', 'Close Up Red', 33, 'CloseUp', 'Pcs', 8.00, 13.00, 0.00, 11.000, 5.000, 0.000, NULL, 'uploads/products/product_6aee199ffbe14ff3.png', 'Consume Responsibly.', 1, '2026-08-28 21:17:47', '2026-08-28 21:45:07'),
(52, 10, '11', 'Gin Bilog', 27, 'San Miguel', 'Bottles', 70.00, 85.00, 10.00, 23.000, 5.000, 0.000, NULL, 'uploads/products/product_6223f81c626ee3f6.png', 'Consume Responsibly.', 1, '2026-08-28 21:19:42', '2026-08-28 21:44:31'),
(53, 11, '01', 'Century Tuna', 34, 'Century', 'Pcs', 35.00, 43.00, 10.00, 3.000, 5.000, 0.000, NULL, 'uploads/products/product_d1274087f376f1ff.png', 'Eat Responsibly.', 1, '2026-08-28 22:12:51', '2026-08-29 22:29:06'),
(54, 11, '02', 'Cream Silk Pink', 36, 'Cream Silk', 'Pcs', 8.00, 13.00, 0.00, 10.000, 5.000, 0.000, NULL, 'uploads/products/product_e4f186b835f93ffe.png', 'Consume Responsibly.', 1, '2026-08-29 14:55:16', '2026-08-29 22:30:33');

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
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `status` enum('active','dropped') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`id`, `business_id`, `product_id`, `batch_number`, `lot_number`, `expiry_date`, `quantity`, `status`, `created_at`) VALUES
(2, 1, 34, 'MIGRATION-INITIAL-34', 'Legacy stock before batch migration', '2027-02-24', 40.000, 'active', '2026-08-25 07:55:06'),
(3, 2, 35, 'MIGRATION-INITIAL-35', 'Legacy stock before batch migration', '2028-01-01', 20.000, 'active', '2026-08-25 07:55:06'),
(4, 1, 36, 'MIGRATION-INITIAL-36', 'Legacy stock before batch migration', '2028-06-01', 22.000, 'active', '2026-08-25 07:55:06'),
(5, 2, 37, 'MIGRATION-INITIAL-37', 'Legacy stock before batch migration', '2027-07-01', 10.000, 'active', '2026-08-25 07:55:06'),
(10, 6, 39, 'B-20260826-001245-8e31', NULL, '2026-12-07', 5.000, 'active', '2026-08-25 16:12:45'),
(11, 5, 38, 'B-20260827-000152-3009', NULL, '2027-04-01', 10.000, 'active', '2026-08-26 16:01:52'),
(12, 5, 38, 'B-20260827-021852-a3bc', NULL, '2028-01-01', 10.000, 'active', '2026-08-26 18:18:52'),
(13, 10, 42, 'B-20260829-050054-1013', NULL, '2030-01-01', 16.000, 'active', '2026-08-28 21:00:54'),
(14, 10, 43, 'B-20260829-050217-5d07', NULL, '2028-01-01', 13.000, 'active', '2026-08-28 21:02:17'),
(15, 10, 44, 'B-20260829-050357-159e', NULL, '2027-01-01', 4.000, 'active', '2026-08-28 21:03:57'),
(16, 10, 45, 'B-20260829-050608-5b0c', NULL, '2026-08-01', 1.000, 'active', '2026-08-28 21:06:08'),
(17, 10, 46, 'B-20260829-050807-9f66', NULL, '2028-01-01', 10.000, 'active', '2026-08-28 21:08:07'),
(18, 10, 47, 'B-20260829-050939-1a4c', NULL, '2028-03-01', 10.000, 'active', '2026-08-28 21:09:39'),
(19, 10, 48, 'B-20260829-051144-bde5', NULL, '2026-09-05', 10.000, 'active', '2026-08-28 21:11:44'),
(20, 10, 49, 'B-20260829-051457-c37e', NULL, '2026-09-12', 9.000, 'active', '2026-08-28 21:14:57'),
(21, 10, 50, 'B-20260829-051608-e8b7', NULL, '2026-09-14', 8.000, 'active', '2026-08-28 21:16:08'),
(22, 10, 51, 'B-20260829-051747-68f4', NULL, '2026-09-29', 11.000, 'active', '2026-08-28 21:17:47'),
(23, 10, 52, 'B-20260829-051942-30c8', NULL, '2026-09-24', 14.000, 'active', '2026-08-28 21:19:42'),
(24, 10, 52, 'B-20260829-053202-d430', NULL, '2026-09-18', 9.000, 'active', '2026-08-28 21:32:02'),
(25, 11, 53, 'B-20260829-061251-fe9e', NULL, '2027-03-18', 3.000, 'active', '2026-08-28 22:12:51'),
(26, 11, 54, 'B-20260829-225516-eaa7', NULL, '2026-08-31', 10.000, 'active', '2026-08-29 14:55:16');

--
-- Triggers `product_batches`
--
DELIMITER $$
CREATE TRIGGER `trg_product_batches_after_delete` AFTER DELETE ON `product_batches` FOR EACH ROW BEGIN
    UPDATE `products` p
       SET p.`stock_quantity` = (
           SELECT COALESCE(SUM(pb.`quantity`), 0)
             FROM `product_batches` pb
            WHERE pb.`product_id` = OLD.`product_id`
              AND pb.`business_id` = p.`business_id`
              AND pb.`status` = 'active'
              AND (pb.`expiry_date` IS NULL OR pb.`expiry_date` >= CURRENT_DATE)
       )
     WHERE p.`id` = OLD.`product_id`
       AND p.`business_id` = OLD.`business_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_product_batches_after_insert` AFTER INSERT ON `product_batches` FOR EACH ROW BEGIN
    UPDATE `products` p
       SET p.`stock_quantity` = (
           SELECT COALESCE(SUM(pb.`quantity`), 0)
             FROM `product_batches` pb
            WHERE pb.`product_id` = NEW.`product_id`
              AND pb.`business_id` = p.`business_id`
              AND pb.`status` = 'active'
              AND (pb.`expiry_date` IS NULL OR pb.`expiry_date` >= CURRENT_DATE)
       )
     WHERE p.`id` = NEW.`product_id`
       AND p.`business_id` = NEW.`business_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_product_batches_after_update` AFTER UPDATE ON `product_batches` FOR EACH ROW BEGIN
    UPDATE `products` p
       SET p.`stock_quantity` = (
           SELECT COALESCE(SUM(pb.`quantity`), 0)
             FROM `product_batches` pb
            WHERE pb.`product_id` = NEW.`product_id`
              AND pb.`business_id` = p.`business_id`
              AND pb.`status` = 'active'
              AND (pb.`expiry_date` IS NULL OR pb.`expiry_date` >= CURRENT_DATE)
       )
     WHERE p.`id` = NEW.`product_id`
       AND p.`business_id` = NEW.`business_id`;

    IF OLD.`product_id` <> NEW.`product_id`
       OR OLD.`business_id` <> NEW.`business_id` THEN
        UPDATE `products` p
           SET p.`stock_quantity` = (
               SELECT COALESCE(SUM(pb.`quantity`), 0)
                 FROM `product_batches` pb
                WHERE pb.`product_id` = OLD.`product_id`
                  AND pb.`business_id` = p.`business_id`
                  AND pb.`status` = 'active'
                  AND (pb.`expiry_date` IS NULL OR pb.`expiry_date` >= CURRENT_DATE)
           )
         WHERE p.`id` = OLD.`product_id`
           AND p.`business_id` = OLD.`business_id`;
    END IF;
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
  `business_code` varchar(20) DEFAULT NULL,
  `business_id` int(11) DEFAULT NULL,
  `business_entity_id` int(11) DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `possible_duplicate` tinyint(1) NOT NULL DEFAULT 0,
  `duplicate_business_id` int(11) DEFAULT NULL,
  `duplicate_reason` varchar(255) DEFAULT NULL,
  `pending_username_key` varchar(50) GENERATED ALWAYS AS (case when `request_status` in ('pending','resubmit') then lcase(trim(`username`)) else NULL end) STORED,
  `pending_email_key` varchar(100) GENERATED ALWAYS AS (case when `request_status` in ('pending','resubmit') then lcase(trim(`email`)) else NULL end) STORED,
  `pending_employee_key` varchar(80) GENERATED ALWAYS AS (case when `request_status` in ('pending','resubmit') and `requested_role` = 'employee' and `business_id` is not null and `employee_no` is not null then concat(cast(`business_id` as char charset utf8mb4),':',lcase(trim(`employee_no`))) else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_requests`
--

INSERT INTO `registration_requests` (`id`, `request_code`, `employee_no`, `full_name`, `email`, `phone`, `address`, `username`, `password_hash`, `valid_id_path`, `requested_role`, `request_status`, `admin_remarks`, `reviewed_by`, `reviewed_at`, `created_at`, `business_name`, `business_type`, `business_address`, `business_code`, `business_id`, `business_entity_id`, `branch_code`, `possible_duplicate`, `duplicate_business_id`, `duplicate_reason`) VALUES
(18, 'REQ-20260806-0001', NULL, 'Matt Valdevia', 'mattraileyvaldevia@gmail.com', '+639957266803', 'Pasig City', 'Desinomeme', '$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6', 'uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg', 'owner', 'approved', 'Perfectly fine!', 1, '2026-08-06 15:10:22', '2026-08-06 07:00:10', 'Concentrix', 'Other SME', 'Pasig City', 'SME-C842D3', 1, 4, 'BR-L00000001', 0, NULL, NULL),
(19, 'REQ-20260806-0002', NULL, 'Joshua Malvar Isla', 'joshuaisla3@gmail.com', '09789847841', 'Cainta, Rizal', 'JoshuaIsla', '$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2', 'uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg', 'owner', 'approved', 'Pasok kana tol!', 1, '2026-08-06 15:19:13', '2026-08-06 07:18:10', 'Omri Bakery Shop', 'Retail Store', 'Cainta, Rizal', 'SME-A96981', 2, 3, 'BR-L00000002', 0, NULL, NULL),
(20, 'REQ-20260807-0001', 'EMP-001', 'Avery Isaac Valdevia', 'averyisaac17@gmail.com', '09055194166', 'Pasig City', 'mchousetons', '$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW', 'uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg', 'employee', 'approved', 'Okay na!', 1, '2026-08-07 00:37:06', '2026-08-06 16:36:11', 'Concentrix', 'Other SME', 'Pasig City', 'SME-C842D3', 1, 4, 'BR-L00000001', 0, NULL, NULL),
(21, 'REQ-20260820-0001', 'EMP-002', 'Matthew Baldebya', 'mraileyvaldevia@gmail.com', '09097854785', 'Pasig City', 'matteocoole8', '$2y$10$OQOhs2h0JkrhzJXXjwt0Reh4Cr570TMUSsEQWJCc8m6vDdinAqAMK', 'uploads/valid_ids/valid_id_e176db51cdec36988614fab3.jpg', 'employee', 'approved', 'Good!', 1, '2026-08-20 16:18:44', '2026-08-20 08:17:04', 'Concentrix', 'Other SME', 'Pasig City', 'SME-C842D3', 1, 4, 'BR-L00000001', 0, NULL, NULL),
(22, 'REQ-20260820-0002', NULL, 'Matt Valdevia', 'matt@gmail.com', '09097854785', 'Pasig City', 'common_matter', '$2y$10$ViWd9KxpteuGzGWL3sokxegusPwT5oAsUHdS5ScEQLGLgxubW9Hgq', 'uploads/valid_ids/valid_id_920f5ef563fe81cfeecb0c9e.jpg', 'owner', 'approved', 'Done!', 1, '2026-08-20 19:51:39', '2026-08-20 11:45:06', 'Valdevia Entreprises', 'Mini Grocery', 'Pasig City', 'SME-7CA227', 3, 2, 'BR-L00000003', 0, NULL, NULL),
(23, 'REQ-20260820-0003', NULL, 'Princess Halley Valdevia', 'valdeviaprincess@gmail.com', '09097854152', 'Pasig City', 'Halleaux', '$2y$10$9UFPM/k2i9JZeCEkQ338COQenrtvvp8viKBeHHY6cdXCsEliTWQiS', 'uploads/valid_ids/valid_id_559e0dda17f1cae7d018d87f.jpg', 'owner', 'rejected', 'Minor!', 1, '2026-08-20 20:00:31', '2026-08-20 11:59:39', 'Bebe Enterprises', 'Mini Market', 'Pasig City', NULL, NULL, NULL, NULL, 0, NULL, NULL),
(24, 'REQ-20260820-0004', NULL, 'Princess Halley Valdevia', 'halley@gmail.com', '09097854152', 'Pasig City', 'Cally123v', '$2y$10$8EbW2/qAHNLpA6Lb94s9iejHDPzX5Fa73ApJl/H4HYTPZ6t31oC9G', 'uploads/valid_ids/valid_id_c40a61d4e9257e4e5f24367a.jpg', 'owner', 'rejected', 'Deadline due.', 1, '2026-08-29 17:55:38', '2026-08-20 12:09:51', 'Bebe Enterprises', 'Mini Market', 'Pasig City', NULL, NULL, NULL, NULL, 0, NULL, NULL),
(25, 'REQ-20260820-0005', 'EMP-001', 'Shannen Valdevia', 'faithvaldevia@gmail.com', '09274224082', 'Pasig City', 'Arianna', '$2y$10$W1Ij/qI/mEmCsLDZOdf6TOIHCp5OSgT1brW/k2RjaIoC8dne8zcqK', 'uploads/valid_ids/valid_id_d7e4fca63efa23db5b6b7305.jpg', 'employee', 'approved', 'Done!', 1, '2026-08-20 20:18:21', '2026-08-20 12:16:58', 'Valdevia Entreprises', 'Mini Grocery', 'Pasig City', 'SME-7CA227', 3, 2, 'BR-L00000003', 0, NULL, NULL),
(26, 'REQ-20260820-0006', 'EMP-001', 'Omri Isla', 'Omri@gmail.com', '09878457485', 'Cainta, Rizal', 'Omri', '$2y$10$p22AQpVGusQ7T1q6.Qtnv.G5g14Q1j9Ws9kUPRVKwHrZ8D3wCONEG', 'uploads/valid_ids/valid_id_7b8a25f469d390f569bb746b.jpg', 'employee', 'approved', 'Done!', 1, '2026-08-20 20:30:35', '2026-08-20 12:29:39', 'Omri Bakery Shop', 'Retail Store', 'Cainta, Rizal', 'SME-A96981', 2, 3, 'BR-L00000002', 0, NULL, NULL),
(27, 'REQ-20260821-0001', NULL, 'Tester1', 'valdeviaprincesshalley@gmail.com', '09784587485', 'Pasig City', 'tester', '$2y$10$/3j/XIKUJYaJ5VrxItsjzeA0TtaMdBbnNWU7drEdMTDYbdz5jrik.', 'uploads/valid_ids/valid_id_876c206940c3b98d61b6822b.jpg', 'owner', 'approved', 'All set!', 1, '2026-08-21 19:35:22', '2026-08-21 10:09:36', 'Halley Enterprises', 'Mini Grocery', '443 Avocado St. Napico Manggahan, Pasig Ctiy', 'SME-5F6738', 4, 1, 'BR-L00000004', 0, NULL, NULL),
(28, 'REQ-20260825-0001', NULL, 'Tester1', 'mattvaldevia@gmail.com', '09784587485', 'Pasig City', 'Tester1', '$2y$10$IJefMuy33s37Y6Ed7IL3S.O4J6FGWZdDxbQHceSeMCh3ebYa.El2C', 'uploads/valid_ids/valid_id_3268ea785f30d503a3206ed4.pdf', 'owner', 'approved', 'Done!', 1, '2026-08-29 17:54:53', '2026-08-25 08:13:43', 'Valdevia Entreprises', 'Hardware / Construction Supplies', '443 Avocado St. Napico Manggahan, Pasig Ctiy', 'SME-3D764B', 14, 15, 'BR-AED32A85', 0, NULL, NULL),
(29, 'REQ-20260825-0002', NULL, 'Branch Test Owner', 'branchowner@test.com', '09957457485', 'Cainta, Rizal', 'branch_test_owner', '$2y$10$4lCgmdQAL/Ml7NmHdprS/.Q1y5F5rNKvaiTQK/A3sjMsiglrs4WJO', 'uploads/valid_ids/valid_id_f31476206f73e7eaa87f2e69.pdf', 'owner', 'approved', 'Approved!', 1, '2026-08-25 23:53:26', '2026-08-25 15:51:18', 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', '10 Main Street, Cainta, Rizal', 'SME-F31938', 5, 8, 'BR-1BBE89CA', 0, NULL, NULL),
(30, 'REQ-20260826-0001', 'TEST-MAIN-001', 'Main Branch Employee', 'mainemployee@test.com', '09957857845', 'Cainta, Rizal', 'main_branch_employee', '$2y$10$jZp0Ehe44VO9.qx1ssoyWOj7Hq8DIYhEDNYGZf9jAmszdtKsVqVM.', 'uploads/valid_ids/valid_id_9d66e5825d45d5929573b3ad.pdf', 'employee', 'approved', 'Done!', 1, '2026-08-26 00:20:40', '2026-08-25 16:18:32', 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', '10 Main Street, Cainta, Rizal', 'SME-F31938', 5, 8, 'BR-1BBE89CA', 0, NULL, NULL),
(34, 'REQ-20260826-0002', 'TEST-EAST-002', 'East Branch Employee', 'eastemployee@test.com', '09222222222', 'Pasig City', 'east_branch_employee', '$2y$10$4YdsTgcZ.pAG3DzRhQydieid6LrlU0j3gt719yTelmKTdp.8akXay', 'uploads/valid_ids/valid_id_d6d1aeb1362abf177988c7a9.jpg', 'employee', 'approved', 'Done!', 1, '2026-08-26 01:03:25', '2026-08-25 17:02:32', 'Vion Test Mart', 'Mini Grocery / Sari-Sari Store', '25 East Road, Pasig City', 'SME-F31938', 6, 8, 'BR-B54F1B52', 0, NULL, NULL),
(35, 'REQ-20260828-0001', NULL, 'Ma. Theresa Valdevia', 'maria@gmail.com', '09957854745', 'Pasig City', 'Tester2', '$2y$10$EvBWyTo2kwcxIudYakImQ..CYNqLtFWL1Ie5Ndxn5jTR86t6Dg58G', 'uploads/valid_ids/valid_id_8ab109392fc15eac8c8a4ce2.png', 'owner', 'approved', 'Done!', 1, '2026-08-28 04:40:56', '2026-08-27 20:38:04', 'Theresa Enterprises', 'School / Office Supplies', '32 Pearl St. Napico Manggahan, Pasig City', 'SME-0F765D', 9, 11, 'BR-9A813F28', 0, NULL, NULL),
(36, 'REQ-20260829-5C22E2B8', NULL, 'Jackie Chan', 'jackie@gmail.com', '09957268457', 'Pasig City', 'Jackie', '$argon2id$v=19$m=19456,t=2,p=1$U0l1bXBtVEFaZ05peXlGTQ$j+fgueOR5mafIlgpwftuQ6y2QkpcOpUJ2p4TN5aXNME', 'private-valid-id:valid_id_e568473b3340e2c1368eacee.jpg', 'owner', 'approved', 'Done!', 1, '2026-08-29 02:41:14', '2026-08-28 18:31:42', 'Valdevia Entreprises', 'Mini Grocery / Sari-Sari Store', 'Pasig City', 'SME-663A3B', 10, 12, 'BR-0F0D3CA4', 0, NULL, NULL),
(37, 'REQ-20260829-40F980ED', NULL, 'Ma. Theresa Valdevia', 'theresa@gmail.com', '0995745845124', 'Pasig City', 'Tester3', '$argon2id$v=19$m=19456,t=2,p=1$Mnp2ZEpieTduQ1NSZnkwZA$NWlJyDoJgz0Lk0yI6wnGqjjh3rXSA+KAmpumgHPYc0s', 'private-valid-id:valid_id_f64a13e1c8c2af3fe68b4a00.jpg', 'owner', 'rejected', 'Invalid documents!', 1, '2026-08-29 03:35:31', '2026-08-28 19:27:55', 'Maria Enterprises', 'Pharmacy / Drugstore', '15 Brook Street, Cainta, Rizal', NULL, NULL, NULL, NULL, 0, NULL, NULL),
(38, 'REQ-20260829-8291FF00', 'EMP-001', 'Employee3', 'employee3@gmail.com', '09957547845', 'Pasig City', 'Employee3', '$argon2id$v=19$m=19456,t=2,p=1$eDBtejBnMmxaVmF3Wmx0cg$AEUrUo5rgP94yw54qrAvIYfes81MZDVHVNS8K4l2cik', 'private-valid-id:valid_id_193a931cfc7ca330e369f006.jpg', 'employee', 'approved', 'Done!', 1, '2026-08-29 03:34:31', '2026-08-28 19:33:21', 'Valdevia Entreprises', 'Mini Grocery / Sari-Sari Store', 'Pasig City', 'SME-663A3B', 10, 12, 'BR-0F0D3CA4', 0, NULL, NULL);

--
-- Triggers `registration_requests`
--
DELIMITER $$
CREATE TRIGGER `trg_registration_requests_before_update_workspace` BEFORE UPDATE ON `registration_requests` FOR EACH ROW BEGIN
    DECLARE v_business_id INT DEFAULT NULL;
    DECLARE v_entity_id INT DEFAULT NULL;
    DECLARE v_business_code VARCHAR(20) DEFAULT NULL;
    DECLARE v_branch_code VARCHAR(20) DEFAULT NULL;

    IF NEW.`request_status` = 'approved' THEN
        IF NEW.`business_id` IS NOT NULL THEN
            SELECT b.`id`, b.`business_entity_id`, b.`business_code`, b.`branch_code`
            INTO v_business_id, v_entity_id, v_business_code, v_branch_code
            FROM `businesses` b
            WHERE b.`id` = NEW.`business_id`
            LIMIT 1;
        ELSEIF NEW.`business_code` IS NOT NULL THEN
            SELECT b.`id`, b.`business_entity_id`, b.`business_code`, b.`branch_code`
            INTO v_business_id, v_entity_id, v_business_code, v_branch_code
            FROM `businesses` b
            WHERE b.`business_code` = NEW.`business_code`
            ORDER BY b.`is_main_branch` DESC, b.`id` ASC
            LIMIT 1;
        END IF;

        IF v_business_id IS NOT NULL THEN
            SET NEW.`business_id` = v_business_id;
            SET NEW.`business_entity_id` = v_entity_id;
            SET NEW.`business_code` = v_business_code;
            SET NEW.`branch_code` = v_branch_code;
        END IF;
    END IF;
END
$$
DELIMITER ;

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
(60, 1, 'SAL-20260807-002355', 2, NULL, 350.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-07 00:25:46', '2026-08-06 16:25:46'),
(61, 1, 'SAL-20260820-211226', 2, NULL, 1170.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-20 21:36:02', '2026-08-20 13:36:02'),
(62, 5, 'SAL-20260826-001345', 10, NULL, 120.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-26 00:15:09', '2026-08-25 16:15:09'),
(63, 5, 'SAL-20260826-235144', 10, NULL, 180.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-26 23:53:19', '2026-08-26 15:53:19'),
(64, 5, 'SAL-20260827-000218', 10, NULL, 300.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-27 00:03:08', '2026-08-26 16:03:08'),
(65, 3, 'SAL-20260829-023847', 7, NULL, 194.40, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 02:39:30', '2026-08-28 18:39:30'),
(66, 10, 'SAL-20260829-053551', 16, NULL, 206.55, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:40:00', '2026-08-28 21:40:00'),
(67, 10, 'SAL-20260829-054012', 16, NULL, 39.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:40:49', '2026-08-28 21:40:49'),
(68, 10, 'SAL-20260829-054056', 16, NULL, 101.25, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:41:20', '2026-08-28 21:41:20'),
(69, 10, 'SAL-20260829-054127', 16, NULL, 24.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:41:53', '2026-08-28 21:41:53'),
(70, 10, 'SAL-20260829-054218', 16, NULL, 59.94, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:42:51', '2026-08-28 21:42:51'),
(71, 10, 'SAL-20260829-054259', 16, NULL, 68.85, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:43:20', '2026-08-28 21:43:20'),
(72, 10, 'SAL-20260829-054328', 16, NULL, 24.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:44:02', '2026-08-28 21:44:02'),
(73, 10, 'SAL-20260829-054410', 16, NULL, 68.85, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:44:31', '2026-08-28 21:44:31'),
(74, 10, 'SAL-20260829-054438', 16, NULL, 13.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 05:45:07', '2026-08-28 21:45:07'),
(75, 11, 'SAL-20260829-061650', 16, NULL, 69.66, 'Paid', 'Cash', 'Fulfilled', '2026-08-29 06:17:15', '2026-08-28 22:17:15'),
(76, 11, 'SAL-20260829-222315', 16, 18, 77.40, 'Partially Paid', 'Cash', 'Pending', '2026-08-29 22:27:48', '2026-08-29 14:27:48'),
(77, 11, 'SAL-20260830-050025', 16, 18, 38.70, 'Partially Paid', 'Cash', 'Pending', '2026-08-30 05:02:05', '2026-08-29 21:02:05'),
(78, 11, 'SAL-20260830-053815', 16, 18, 51.70, 'Partially Paid', 'Cash', 'Pending', '2026-08-30 05:39:25', '2026-08-29 21:39:25'),
(79, 11, 'SAL-20260830-062331', 16, 18, 51.70, 'Partially Paid', 'Cash', 'Pending', '2026-08-30 06:29:06', '2026-08-29 22:29:06'),
(80, 11, 'SAL-20260830-062918', 16, NULL, 13.00, 'Paid', 'Cash', 'Fulfilled', '2026-08-30 06:30:33', '2026-08-29 22:30:33');

-- --------------------------------------------------------

--
-- Table structure for table `sales_milestones`
--

CREATE TABLE `sales_milestones` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `period_type` enum('today','week','month','quarter','semi_annual','annual') NOT NULL,
  `period_bucket` varchar(20) NOT NULL,
  `threshold` int(11) NOT NULL,
  `actual_amount` decimal(12,2) DEFAULT NULL,
  `reached_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_milestones`
--

INSERT INTO `sales_milestones` (`id`, `business_id`, `period_type`, `period_bucket`, `threshold`, `actual_amount`, `reached_at`) VALUES
(1, 10, 'today', '2026-08-29', 500, NULL, '2026-08-29 05:50:36');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 CHECK (`discount_percent` between 0.00 and 100.00),
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `discount_percent`, `subtotal`) VALUES
(68, 60, 34, 10.000, 35.00, 0.00, 350.00),
(69, 61, 36, 3.000, 390.00, 0.00, 1170.00),
(70, 62, 38, 2.000, 60.00, 0.00, 120.00),
(71, 63, 38, 3.000, 60.00, 0.00, 180.00),
(72, 64, 38, 5.000, 60.00, 0.00, 300.00),
(73, 65, 41, 3.000, 72.00, 10.00, 194.40),
(74, 66, 42, 3.000, 76.50, 10.00, 206.55),
(75, 67, 51, 3.000, 13.00, 0.00, 39.00),
(76, 68, 48, 5.000, 22.50, 10.00, 101.25),
(77, 69, 49, 2.000, 12.00, 0.00, 24.00),
(78, 70, 43, 2.000, 33.30, 10.00, 59.94),
(79, 71, 42, 1.000, 76.50, 10.00, 68.85),
(80, 72, 50, 2.000, 12.00, 0.00, 24.00),
(81, 73, 52, 1.000, 76.50, 10.00, 68.85),
(82, 74, 51, 1.000, 13.00, 0.00, 13.00),
(83, 75, 53, 2.000, 38.70, 10.00, 69.66),
(84, 76, 53, 2.000, 43.00, 10.00, 77.40),
(85, 77, 53, 1.000, 43.00, 10.00, 38.70),
(86, 78, 53, 1.000, 43.00, 10.00, 38.70),
(87, 78, 54, 1.000, 13.00, 0.00, 13.00),
(88, 79, 54, 1.000, 13.00, 0.00, 13.00),
(89, 79, 53, 1.000, 43.00, 10.00, 38.70),
(90, 80, 54, 1.000, 13.00, 0.00, 13.00);

-- --------------------------------------------------------

--
-- Table structure for table `security_rate_limits`
--

CREATE TABLE `security_rate_limits` (
  `bucket_key` char(64) NOT NULL,
  `action_name` varchar(64) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_rate_limits`
--

INSERT INTO `security_rate_limits` (`bucket_key`, `action_name`, `attempts`, `window_started_at`, `blocked_until`, `updated_at`) VALUES
('0342cbedf76026dded5b587e8142c99dfb843c2a3c49ecf452f5ac81143aada6', 'signup_email', 2, '2026-08-29 02:27:34', NULL, '2026-08-28 18:31:42'),
('219843a968a3e6655174e4a4a6743e3073cb7da51ee2e5e3d18ade6a783a9398', 'signup_ip', 2, '2026-08-29 03:27:55', NULL, '2026-08-28 19:33:21'),
('9287101e8d7145e3439faa7c0c7d9fecc2690a4887e9cf5c3db5d068e85142ef', 'signup_email', 1, '2026-08-29 03:33:21', NULL, '2026-08-28 19:33:21'),
('cda68d40b04139c890a20ec93cc2a56988472fbb634f65d06a6f0c8833870597', 'signup_email', 1, '2026-08-29 03:27:55', NULL, '2026-08-28 19:27:55'),
('f9e6212578f4d0693bdbab226cfe671d4a81494abbf1cdb96a90e6adb018c7bb', 'signup_email', 1, '2026-08-28 17:39:44', NULL, '2026-08-28 09:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('stock_in','stock_out','order_placed') NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `business_id`, `product_id`, `movement_type`, `quantity`, `remarks`, `created_by`, `created_at`) VALUES
(1, 1, 34, 'stock_out', 10.000, 'Sale recorded: SAL-20260807-002355', 2, '2026-08-06 16:25:46'),
(2, 1, 34, 'stock_in', 20.000, 'Supplier: Puregold\r\nPO#: 001\r\nETA: 08/26/2026', 2, '2026-08-19 07:46:55'),
(3, 1, 36, 'stock_out', 3.000, 'Nabasag', 5, '2026-08-20 08:25:49'),
(4, 1, 34, 'order_placed', 10.000, 'Supplier: Osave\r\nPO#: 001\r\nETA: 08/26/2026', 2, '2026-08-20 08:30:16'),
(5, 1, 34, 'stock_in', 10.000, 'Received!', 2, '2026-08-20 08:30:54'),
(6, 1, 36, 'stock_in', 5.000, 'Done', 2, '2026-08-20 13:06:22'),
(7, 1, 36, 'stock_out', 2.000, 'Done', 2, '2026-08-20 13:07:04'),
(8, 1, 36, 'order_placed', 10.000, 'Supplier: Osave\r\nPO#:001\r\nETA: 08/26/2026', 2, '2026-08-20 13:08:54'),
(9, 1, 36, 'stock_in', 10.000, 'Order Received!', 2, '2026-08-20 13:09:39'),
(10, 1, 36, 'stock_out', 3.000, 'Sale recorded: SAL-20260820-211226', 2, '2026-08-20 13:36:02'),
(11, 5, 38, 'stock_out', 2.000, 'Sale recorded: SAL-20260826-001345', 10, '2026-08-25 16:15:09'),
(12, 1, 36, 'order_placed', 12.000, 'Supplier: Osave\r\nPO#:003\r\nETA: 08/30/2026', 2, '2026-08-25 21:58:00'),
(13, 1, 36, 'stock_in', 12.000, 'Received!', 2, '2026-08-25 21:58:26'),
(14, 5, 38, 'stock_out', 3.000, 'Sale recorded: SAL-20260826-235144', 10, '2026-08-26 15:53:19'),
(15, 5, 38, 'order_placed', 10.000, 'Supplier: Osave\r\nPO#: 001\r\nETA: 08/31/2026', 10, '2026-08-26 16:01:18'),
(16, 5, 38, 'stock_in', 10.000, 'Done!', 10, '2026-08-26 16:01:52'),
(17, 5, 38, 'stock_out', 5.000, 'Sale recorded: SAL-20260827-000218', 10, '2026-08-26 16:03:08'),
(18, 5, 38, 'order_placed', 10.000, 'Supplier: Osave\r\nPO#: 002\r\nETA: 08/31/2026', 10, '2026-08-26 18:18:33'),
(19, 5, 38, 'stock_in', 10.000, 'Done!', 10, '2026-08-26 18:18:52'),
(20, 3, 41, 'stock_out', 3.000, 'Sale recorded: SAL-20260829-023847', 7, '2026-08-28 18:39:30'),
(21, 10, 52, 'order_placed', 10.000, 'Supplier: Osave\r\nPO#: 001\r\nETA: 09/30/2026', 16, '2026-08-28 21:31:24'),
(22, 10, 52, 'stock_in', 10.000, 'Done!', 16, '2026-08-28 21:32:02'),
(23, 10, 42, 'stock_out', 3.000, 'Sale recorded: SAL-20260829-053551', 16, '2026-08-28 21:40:00'),
(24, 10, 51, 'stock_out', 3.000, 'Sale recorded: SAL-20260829-054012', 16, '2026-08-28 21:40:49'),
(25, 10, 48, 'stock_out', 5.000, 'Sale recorded: SAL-20260829-054056', 16, '2026-08-28 21:41:20'),
(26, 10, 49, 'stock_out', 2.000, 'Sale recorded: SAL-20260829-054127', 16, '2026-08-28 21:41:53'),
(27, 10, 43, 'stock_out', 2.000, 'Sale recorded: SAL-20260829-054218', 16, '2026-08-28 21:42:51'),
(28, 10, 42, 'stock_out', 1.000, 'Sale recorded: SAL-20260829-054259', 16, '2026-08-28 21:43:20'),
(29, 10, 50, 'stock_out', 2.000, 'Sale recorded: SAL-20260829-054328', 16, '2026-08-28 21:44:02'),
(30, 10, 52, 'stock_out', 1.000, 'Sale recorded: SAL-20260829-054410', 16, '2026-08-28 21:44:31'),
(31, 10, 51, 'stock_out', 1.000, 'Sale recorded: SAL-20260829-054438', 16, '2026-08-28 21:45:07'),
(32, 11, 53, 'stock_out', 2.000, 'Sale recorded: SAL-20260829-061650', 16, '2026-08-28 22:17:15'),
(33, 11, 53, 'stock_out', 2.000, 'Sale recorded: SAL-20260829-222315', 16, '2026-08-29 14:27:48'),
(34, 11, 53, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-050025', 16, '2026-08-29 21:02:05'),
(35, 11, 53, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-053815', 16, '2026-08-29 21:39:25'),
(36, 11, 54, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-053815', 16, '2026-08-29 21:39:25'),
(37, 11, 54, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-062331', 16, '2026-08-29 22:29:06'),
(38, 11, 53, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-062331', 16, '2026-08-29 22:29:06'),
(39, 11, 54, 'stock_out', 1.000, 'Sale recorded: SAL-20260830-062918', 16, '2026-08-29 22:30:33');

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
(1, 'NexGenAdmin', 'System Administrator', 'ADM-0001', 'admin@nexgen.local', '09123456789', 'System', 'email', '676060', '2026-08-26 04:39:40', '$2y$10$y98td6QnWlGE9x0ya6h8oehlKsxDWO5puD10bxNIKISb6z.9TcAPu', 'system_admin', 'active', 'uploads/profile_6a86b700316541.35218612.png', NULL, 1, NULL, '2026-08-06 14:14:54', NULL, '2026-08-06 06:14:54', 1, 1, 1, 1, '2026-08-29 22:20:43', 0, NULL, NULL, NULL),
(2, 'Desinomeme', 'Matt Valdevia', NULL, 'mattraileyvaldevia@gmail.com', '+639957266803', 'Pasig City', 'email', '976582', '2026-08-27 01:40:04', '$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6', 'owner', 'active', 'uploads/profile_6a7433b04ab063.00525040.jpg', 'uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg', 1, 1, '2026-08-06 15:10:22', NULL, '2026-08-06 07:10:22', 1, 1, 1, 1, '2026-08-29 02:47:49', 0, NULL, NULL, 1),
(3, 'JoshuaIsla', 'Joshua Malvar Isla', NULL, 'joshuaisla3@gmail.com', '09789847841', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2', 'owner', 'active', 'uploads/profile_6a7435c86f0a24.69298183.jpg', 'uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg', 1, 1, '2026-08-06 15:19:13', NULL, '2026-08-06 07:19:13', 1, 1, 1, 1, '2026-08-20 20:36:44', 0, NULL, NULL, 2),
(4, 'mchousetons', 'Avery Isaac Valdevia', 'EMP-001', 'averyisaac17@gmail.com', '09055194166', 'Pasig City', 'email', NULL, NULL, '$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW', 'employee', 'active', 'uploads/profile_6a74ba901c4954.00059389.jpg', 'uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg', 1, 1, '2026-08-07 00:37:06', NULL, '2026-08-06 16:37:06', 1, 1, 0, 1, '2026-08-07 00:38:24', 0, NULL, NULL, 1),
(5, 'matteocoole8', 'Matthew Baldebya', 'EMP-002', 'mraileyvaldevia@gmail.com', '09097854785', 'Pasig City', 'email', NULL, NULL, '$2y$10$OQOhs2h0JkrhzJXXjwt0Reh4Cr570TMUSsEQWJCc8m6vDdinAqAMK', 'employee', 'active', 'uploads/profile_6a86ba78e0bcd4.24835092.jpg', 'uploads/valid_ids/valid_id_e176db51cdec36988614fab3.jpg', 1, 1, '2026-08-20 16:18:44', NULL, '2026-08-20 08:18:44', 1, 1, 0, 1, '2026-08-20 16:20:36', 0, NULL, NULL, 1),
(6, 'common_matter', 'Matt Valdevia', NULL, 'matt@gmail.com', '09097854785', 'Pasig City', 'email', NULL, NULL, '$2y$10$ViWd9KxpteuGzGWL3sokxegusPwT5oAsUHdS5ScEQLGLgxubW9Hgq', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_920f5ef563fe81cfeecb0c9e.jpg', 1, 1, '2026-08-20 19:51:39', NULL, '2026-08-20 11:51:39', 1, 1, 1, 1, '2026-08-20 19:54:06', 3, '2026-08-20 20:22:24', '2026-08-20 20:21:24', 3),
(7, 'Arianna', 'Shannen Valdevia', 'EMP-001', 'faithvaldevia@gmail.com', '09274224082', 'Pasig City', 'email', NULL, NULL, '$2y$10$W1Ij/qI/mEmCsLDZOdf6TOIHCp5OSgT1brW/k2RjaIoC8dne8zcqK', 'employee', 'active', 'uploads/profile_6a91d51ae9ac28.36463877.jpg', 'uploads/valid_ids/valid_id_d7e4fca63efa23db5b6b7305.jpg', 1, 1, '2026-08-20 20:18:21', NULL, '2026-08-20 12:18:21', 1, 1, 0, 1, '2026-08-29 02:35:44', 0, NULL, NULL, 3),
(8, 'Omri', 'Omri Isla', 'EMP-001', 'Omri@gmail.com', '09878457485', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$p22AQpVGusQ7T1q6.Qtnv.G5g14Q1j9Ws9kUPRVKwHrZ8D3wCONEG', 'employee', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_7b8a25f469d390f569bb746b.jpg', 1, 1, '2026-08-20 20:30:35', NULL, '2026-08-20 12:30:35', 1, 1, 0, 1, '2026-08-20 20:32:03', 0, NULL, NULL, 2),
(9, 'tester', 'Tester1', NULL, 'valdeviaprincesshalley@gmail.com', '09784587485', 'Pasig City', 'email', NULL, NULL, '$2y$10$/3j/XIKUJYaJ5VrxItsjzeA0TtaMdBbnNWU7drEdMTDYbdz5jrik.', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_876c206940c3b98d61b6822b.jpg', 1, 1, '2026-08-21 19:35:22', NULL, '2026-08-21 11:35:22', 1, 1, 1, 1, NULL, 0, NULL, NULL, 4),
(10, 'BranchOwner', 'Branch Test Owner', NULL, 'branchowner@test.com', '09957457485', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$4lCgmdQAL/Ml7NmHdprS/.Q1y5F5rNKvaiTQK/A3sjMsiglrs4WJO', 'owner', 'active', 'uploads/profile_6a8dbb5e9af548.07087700.jpg', 'uploads/valid_ids/valid_id_f31476206f73e7eaa87f2e69.pdf', 1, 1, '2026-08-25 23:53:26', NULL, '2026-08-25 15:53:26', 1, 1, 1, 0, '2026-08-29 17:32:40', 0, NULL, NULL, 8),
(11, 'main_branch_employee', 'Main Branch Employee', 'TEST-MAIN-001', 'mainemployee@test.com', '09957857845', 'Cainta, Rizal', 'email', NULL, NULL, '$2y$10$jZp0Ehe44VO9.qx1ssoyWOj7Hq8DIYhEDNYGZf9jAmszdtKsVqVM.', 'employee', 'active', 'uploads/profile_6a8dc1294f8b55.99102283.jpg', 'uploads/valid_ids/valid_id_9d66e5825d45d5929573b3ad.pdf', 1, 1, '2026-08-26 00:20:40', NULL, '2026-08-25 16:20:40', 1, 1, 0, 0, '2026-08-26 00:21:40', 0, NULL, NULL, 5),
(14, 'east_branch_employee', 'East Branch Employee', 'TEST-EAST-002', 'eastemployee@test.com', '09222222222', 'Pasig City', 'email', NULL, NULL, '$2y$10$4YdsTgcZ.pAG3DzRhQydieid6LrlU0j3gt719yTelmKTdp.8akXay', 'employee', 'active', 'uploads/profile_6a8dcb4cd19556.43400471.jpg', 'uploads/valid_ids/valid_id_d6d1aeb1362abf177988c7a9.jpg', 1, 1, '2026-08-26 01:03:25', NULL, '2026-08-25 17:03:25', 1, 1, 0, 0, '2026-08-26 01:04:32', 0, NULL, NULL, 6),
(15, 'Tester2', 'Ma. Theresa Valdevia', NULL, 'maria@gmail.com', '09957854745', 'Pasig City', 'email', NULL, NULL, '$2y$10$EvBWyTo2kwcxIudYakImQ..CYNqLtFWL1Ie5Ndxn5jTR86t6Dg58G', 'owner', 'active', 'uploads/profile_6a90aa68d7af30.73173051.jpg', 'uploads/valid_ids/valid_id_8ab109392fc15eac8c8a4ce2.png', 1, 1, '2026-08-28 04:40:56', NULL, '2026-08-27 20:40:56', 1, 1, 1, 0, '2026-08-29 17:20:00', 0, NULL, NULL, 9),
(16, 'Jackie', 'Jackie Chan', NULL, 'jackie@gmail.com', '09957268457', 'Pasig City', 'email', NULL, NULL, '$argon2id$v=19$m=19456,t=2,p=1$U0l1bXBtVEFaZ05peXlGTQ$j+fgueOR5mafIlgpwftuQ6y2QkpcOpUJ2p4TN5aXNME', 'owner', 'active', 'uploads/profile_6a91f6050dff26.05731770.jpg', 'private-valid-id:valid_id_e568473b3340e2c1368eacee.jpg', 1, 1, '2026-08-29 02:41:14', NULL, '2026-08-28 18:41:14', 1, 1, 1, 1, '2026-08-30 04:55:40', 0, NULL, NULL, 11),
(17, 'Employee3', 'Employee3', 'EMP-001', 'employee3@gmail.com', '09957547845', 'Pasig City', 'email', NULL, NULL, '$argon2id$v=19$m=19456,t=2,p=1$eDBtejBnMmxaVmF3Wmx0cg$AEUrUo5rgP94yw54qrAvIYfes81MZDVHVNS8K4l2cik', 'employee', 'active', 'uploads/default.png', 'private-valid-id:valid_id_193a931cfc7ca330e369f006.jpg', 1, 1, '2026-08-29 03:34:31', NULL, '2026-08-28 19:34:31', 1, 1, 0, 0, '2026-08-29 06:27:40', 0, NULL, NULL, 10),
(18, 'Tester1', 'Tester1', NULL, 'mattvaldevia@gmail.com', '09784587485', 'Pasig City', 'email', NULL, NULL, '$2y$10$IJefMuy33s37Y6Ed7IL3S.O4J6FGWZdDxbQHceSeMCh3ebYa.El2C', 'owner', 'active', 'uploads/default.png', 'uploads/valid_ids/valid_id_3268ea785f30d503a3206ed4.pdf', 1, 1, '2026-08-29 17:54:53', NULL, '2026-08-29 09:54:53', 1, 1, 1, 0, NULL, 0, NULL, NULL, 14);

-- --------------------------------------------------------

--
-- Table structure for table `user_branch_assignments`
--

CREATE TABLE `user_branch_assignments` (
  `user_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_branch_assignments`
--

INSERT INTO `user_branch_assignments` (`user_id`, `business_id`, `is_primary`, `status`, `assigned_at`) VALUES
(2, 1, 1, 'active', '2026-08-25 15:34:17'),
(3, 2, 1, 'active', '2026-08-25 15:34:17'),
(4, 1, 1, 'active', '2026-08-25 15:34:17'),
(5, 1, 1, 'active', '2026-08-25 15:34:17'),
(6, 3, 1, 'active', '2026-08-25 15:34:17'),
(7, 3, 1, 'active', '2026-08-25 20:35:51'),
(8, 2, 1, 'active', '2026-08-25 15:34:17'),
(9, 4, 1, 'active', '2026-08-25 15:34:17'),
(10, 5, 0, 'active', '2026-08-25 15:54:39'),
(10, 6, 0, 'active', '2026-08-25 16:00:10'),
(10, 7, 0, 'active', '2026-08-25 17:13:28'),
(10, 8, 1, 'active', '2026-08-25 17:15:58'),
(10, 12, 0, 'active', '2026-08-29 09:18:14'),
(11, 5, 1, 'active', '2026-08-25 16:21:40'),
(14, 6, 1, 'active', '2026-08-25 20:54:11'),
(15, 9, 1, 'active', '2026-08-27 20:40:56'),
(16, 10, 0, 'active', '2026-08-28 18:41:14'),
(16, 11, 1, 'active', '2026-08-28 21:59:03'),
(16, 13, 0, 'active', '2026-08-29 09:52:45'),
(17, 10, 1, 'active', '2026-08-28 19:34:31'),
(18, 14, 1, 'active', '2026-08-29 09:54:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_business_assignments`
--

CREATE TABLE `user_business_assignments` (
  `user_id` int(11) NOT NULL,
  `business_entity_id` int(11) NOT NULL,
  `assignment_role` enum('owner','employee') NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_business_assignments`
--

INSERT INTO `user_business_assignments` (`user_id`, `business_entity_id`, `assignment_role`, `is_default`, `status`, `assigned_at`) VALUES
(2, 4, 'owner', 1, 'active', '2026-08-25 15:34:17'),
(3, 3, 'owner', 1, 'active', '2026-08-25 15:34:17'),
(4, 4, 'employee', 1, 'active', '2026-08-25 15:34:17'),
(5, 4, 'employee', 1, 'active', '2026-08-25 15:34:17'),
(6, 2, 'owner', 1, 'active', '2026-08-25 15:34:17'),
(7, 2, 'employee', 1, 'active', '2026-08-25 20:35:51'),
(8, 3, 'employee', 1, 'active', '2026-08-25 15:34:17'),
(9, 1, 'owner', 1, 'active', '2026-08-25 15:34:17'),
(10, 8, 'owner', 0, 'active', '2026-08-25 15:54:39'),
(10, 9, 'owner', 0, 'active', '2026-08-25 17:13:28'),
(10, 10, 'owner', 1, 'active', '2026-08-25 17:15:58'),
(10, 14, 'owner', 0, 'active', '2026-08-29 09:18:14'),
(11, 8, 'employee', 1, 'active', '2026-08-25 16:21:40'),
(14, 8, 'employee', 1, 'active', '2026-08-25 20:54:11'),
(15, 11, 'owner', 1, 'active', '2026-08-27 20:40:56'),
(16, 12, 'owner', 0, 'active', '2026-08-28 18:41:14'),
(16, 13, 'owner', 1, 'active', '2026-08-28 21:59:03'),
(17, 12, 'employee', 1, 'active', '2026-08-28 19:34:31'),
(18, 15, 'owner', 1, 'active', '2026-08-29 09:54:53');

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
,`quantity` decimal(12,3)
,`unit_price` decimal(10,2)
,`subtotal` decimal(10,2)
,`total_amount` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `workspace_requests`
--

CREATE TABLE `workspace_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(50) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `request_type` enum('business','branch') NOT NULL,
  `business_entity_id` int(11) DEFAULT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `business_address` text NOT NULL,
  `branch_name` varchar(150) NOT NULL,
  `separate_operations` tinyint(1) NOT NULL DEFAULT 0,
  `request_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_business_entity_id` int(11) DEFAULT NULL,
  `approved_business_id` int(11) DEFAULT NULL,
  `business_code` varchar(20) DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `pending_guard` tinyint(1) GENERATED ALWAYS AS (case when `request_status` = 'pending' then 1 else NULL end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_requests`
--

INSERT INTO `workspace_requests` (`id`, `request_code`, `requested_by`, `request_type`, `business_entity_id`, `business_name`, `business_type`, `business_address`, `branch_name`, `separate_operations`, `request_status`, `admin_remarks`, `reviewed_by`, `reviewed_at`, `approved_business_entity_id`, `approved_business_id`, `business_code`, `branch_code`, `created_at`, `updated_at`) VALUES
(1, 'WRQ-20260828-6B6383', 10, 'business', NULL, 'Vion Hardware & Services', 'Hardware / Construction Supplies', '10 Oak St. Baranggay Sta. Ana Taytay, Rizal', 'Main Branch', 1, 'approved', 'Done!', 1, '2026-08-29 17:18:14', 14, 12, 'SME-2923830B', 'BR-2C64B352', '2026-08-27 19:07:45', '2026-08-29 09:18:14'),
(2, 'WRQ-20260828-EF7740', 15, 'branch', 11, 'Theresa Enterprises', 'School / Office Supplies', '17 Gold St. Greenpark Village Cainta, Rizal', 'Cainta Branch', 0, 'rejected', 'Invalid documents!', 1, '2026-08-29 17:17:09', NULL, NULL, NULL, NULL, '2026-08-27 21:24:25', '2026-08-29 09:17:09'),
(3, 'WRQ-20260829-05D8BF', 16, 'business', NULL, 'Magbanua Enterprises', 'Mini Grocery / Sari-Sari Store', '67 Chinatown St. New York, New York', 'Main Branch', 1, 'approved', 'Valid!', 1, '2026-08-29 05:59:03', 13, 11, 'SME-47C8D918', 'BR-1F28CD7F', '2026-08-28 21:57:29', '2026-08-28 21:59:03'),
(4, 'WRQ-20260829-384621', 16, 'branch', 13, 'Magbanua Enterprises', 'Mini Grocery / Sari-Sari Store', '41 Golden State CA, United States', 'Second Branch', 0, 'approved', 'Done talking with the owner!', 1, '2026-08-29 17:52:45', 13, 13, 'SME-47C8D918', 'BR-ED480070', '2026-08-29 09:51:13', '2026-08-29 09:52:45');

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
  ADD KEY `idx_ar_business_status` (`business_id`,`status`),
  ADD KEY `idx_ar_business_due_date` (`business_id`,`due_date`);

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
  ADD UNIQUE KEY `uq_businesses_branch_code` (`branch_code`),
  ADD KEY `idx_businesses_entity` (`business_entity_id`),
  ADD KEY `idx_businesses_code` (`business_code`);

--
-- Indexes for table `business_entities`
--
ALTER TABLE `business_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_entities_code` (`business_code`),
  ADD KEY `idx_business_entities_name_type` (`business_name`,`business_type`),
  ADD KEY `idx_business_entities_created_by` (`created_by`);

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
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`user_id`,`module`),
  ADD KEY `idx_notification_reads_module_seen` (`module`,`last_seen_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_business_code` (`business_id`,`product_code`),
  ADD KEY `idx_products_category_id` (`category_id`),
  ADD KEY `idx_products_business` (`business_id`),
  ADD KEY `idx_products_business_category` (`business_id`,`category_id`),
  ADD KEY `idx_products_business_created` (`business_id`,`created_at`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_batches_business` (`business_id`),
  ADD KEY `idx_product_batches_product` (`product_id`,`expiry_date`);

--
-- Indexes for table `receivable_payments`
--
ALTER TABLE `receivable_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rp_business` (`business_id`),
  ADD KEY `idx_rp_receivable` (`receivable_id`),
  ADD KEY `idx_rp_sale` (`sale_id`),
  ADD KEY `idx_rp_paid_at` (`paid_at`),
  ADD KEY `idx_rp_business_paid_at` (`business_id`,`paid_at`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD UNIQUE KEY `uq_registration_pending_username` (`pending_username_key`),
  ADD UNIQUE KEY `uq_registration_pending_email` (`pending_email_key`),
  ADD UNIQUE KEY `uq_registration_pending_employee` (`pending_employee_key`),
  ADD KEY `idx_registration_requests_status` (`request_status`),
  ADD KEY `idx_registration_username` (`username`),
  ADD KEY `idx_registration_email` (`email`),
  ADD KEY `idx_registration_employee_no` (`employee_no`),
  ADD KEY `idx_registration_business_code` (`business_code`),
  ADD KEY `idx_registration_requests_business` (`business_id`),
  ADD KEY `idx_registration_branch_code` (`branch_code`),
  ADD KEY `idx_registration_entity` (`business_entity_id`),
  ADD KEY `fk_registration_duplicate_business` (`duplicate_business_id`);

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
-- Indexes for table `sales_milestones`
--
ALTER TABLE `sales_milestones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_milestone` (`business_id`,`period_type`,`period_bucket`,`threshold`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_sale` (`sale_id`),
  ADD KEY `fk_sale_items_product` (`product_id`),
  ADD KEY `idx_sale_items_sale_id` (`sale_id`);

--
-- Indexes for table `security_rate_limits`
--
ALTER TABLE `security_rate_limits`
  ADD PRIMARY KEY (`bucket_key`),
  ADD KEY `idx_security_rate_limits_updated` (`updated_at`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_product` (`product_id`),
  ADD KEY `idx_stock_movements_business` (`business_id`),
  ADD KEY `idx_stock_business_created` (`business_id`,`created_at`);

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
-- Indexes for table `user_branch_assignments`
--
ALTER TABLE `user_branch_assignments`
  ADD PRIMARY KEY (`user_id`,`business_id`),
  ADD KEY `idx_ubra_business` (`business_id`),
  ADD KEY `idx_ubra_user_primary` (`user_id`,`is_primary`,`status`);

--
-- Indexes for table `user_business_assignments`
--
ALTER TABLE `user_business_assignments`
  ADD PRIMARY KEY (`user_id`,`business_entity_id`),
  ADD KEY `idx_uba_entity` (`business_entity_id`),
  ADD KEY `idx_uba_user_default` (`user_id`,`is_default`,`status`);

--
-- Indexes for table `workspace_requests`
--
ALTER TABLE `workspace_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_workspace_request_code` (`request_code`),
  ADD UNIQUE KEY `uq_workspace_one_pending_per_account` (`requested_by`,`pending_guard`),
  ADD KEY `idx_workspace_request_status` (`request_status`,`created_at`),
  ADD KEY `idx_workspace_request_entity` (`business_entity_id`),
  ADD KEY `idx_workspace_request_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_workspace_request_approved_entity` (`approved_business_entity_id`),
  ADD KEY `idx_workspace_request_approved_branch` (`approved_business_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `business_entities`
--
ALTER TABLE `business_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `receivable_payments`
--
ALTER TABLE `receivable_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `sales_milestones`
--
ALTER TABLE `sales_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `workspace_requests`
--
ALTER TABLE `workspace_requests`
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
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `fk_businesses_entity` FOREIGN KEY (`business_entity_id`) REFERENCES `business_entities` (`id`);

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
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_product_batches_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  ADD CONSTRAINT `fk_product_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD CONSTRAINT `fk_registration_duplicate_business` FOREIGN KEY (`duplicate_business_id`) REFERENCES `businesses` (`id`),
  ADD CONSTRAINT `fk_registration_entity` FOREIGN KEY (`business_entity_id`) REFERENCES `business_entities` (`id`),
  ADD CONSTRAINT `fk_registration_requests_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

--
-- Constraints for table `sales_milestones`
--
ALTER TABLE `sales_milestones`
  ADD CONSTRAINT `fk_sales_milestones_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`);

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

--
-- Constraints for table `user_branch_assignments`
--
ALTER TABLE `user_branch_assignments`
  ADD CONSTRAINT `fk_ubra_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ubra_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_business_assignments`
--
ALTER TABLE `user_business_assignments`
  ADD CONSTRAINT `fk_uba_entity` FOREIGN KEY (`business_entity_id`) REFERENCES `business_entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uba_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workspace_requests`
--
ALTER TABLE `workspace_requests`
  ADD CONSTRAINT `fk_workspace_request_approved_branch` FOREIGN KEY (`approved_business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_workspace_request_approved_entity` FOREIGN KEY (`approved_business_entity_id`) REFERENCES `business_entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_workspace_request_owner` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_workspace_request_parent_entity` FOREIGN KEY (`business_entity_id`) REFERENCES `business_entities` (`id`),
  ADD CONSTRAINT `fk_workspace_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
