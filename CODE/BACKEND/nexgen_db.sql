-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nexgen_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `nexgen_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `nexgen_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `nexgen_db`;

--
-- Table structure for table `accounts_masterlist`
--

DROP TABLE IF EXISTS `accounts_masterlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts_masterlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_no` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_no` (`employee_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts_masterlist`
--

LOCK TABLES `accounts_masterlist` WRITE;
/*!40000 ALTER TABLE `accounts_masterlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounts_masterlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounts_receivable`
--

DROP TABLE IF EXISTS `accounts_receivable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts_receivable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_receivable_sale` (`sale_id`),
  KEY `fk_receivable_customer` (`customer_id`),
  KEY `fk_receivable_user` (`created_by`),
  KEY `idx_ar_business` (`business_id`),
  KEY `idx_ar_business_status` (`business_id`,`status`),
  KEY `idx_ar_business_due_date` (`business_id`,`due_date`),
  CONSTRAINT `fk_ar_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts_receivable`
--

LOCK TABLES `accounts_receivable` WRITE;
/*!40000 ALTER TABLE `accounts_receivable` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounts_receivable` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_accounts_receivable_after_update` AFTER UPDATE ON `accounts_receivable` FOR EACH ROW BEGIN
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

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `previous_hash` char(64) DEFAULT NULL,
  `log_hash` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_logs`
--

LOCK TABLES `admin_logs` WRITE;
/*!40000 ALTER TABLE `admin_logs` DISABLE KEYS */;
INSERT INTO `admin_logs` VALUES (52,1,'approve_request','registration_request',18,'Approved request #18 (REQ-20260806-0001) and created user #2','0000000000000000000000000000000000000000000000000000000000000000','ac6afa636771762170982fe1ba7325b8a9fe008e15149310ef98800b522b8684','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0','2026-08-06 07:10:22'),(53,1,'approve_request','registration_request',19,'Approved request #19 (REQ-20260806-0002) and created user #3','ac6afa636771762170982fe1ba7325b8a9fe008e15149310ef98800b522b8684','6e9839b7eab47c677107b95c42acfe9cd59d527f70320dacbcd93c1999acdf4c','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0','2026-08-06 07:19:13'),(54,1,'approve_request','registration_request',20,'Approved request #20 (REQ-20260807-0001) and created user #4','6e9839b7eab47c677107b95c42acfe9cd59d527f70320dacbcd93c1999acdf4c','03f68c930ffbe5e839e1f1f79da4d84681ca7f7704f843b8994dde296f51aa0b','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0','2026-08-06 16:37:06'),(55,1,'approve_request','registration_request',21,'Approved request #21 (REQ-20260813-0001) and created user #5','03f68c930ffbe5e839e1f1f79da4d84681ca7f7704f843b8994dde296f51aa0b','b525f290dcd1ce2983da1f20b990f1f4c22ae1dc42ea5dfe198af65615665fd6','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-13 09:17:55'),(56,1,'update_user_permissions','user',5,'Updated user #5 (sed) - role: owner, status: active, verified: 1, inventory: 1, sales: 1, analytics: 1, accounts_receivable: 0','b525f290dcd1ce2983da1f20b990f1f4c22ae1dc42ea5dfe198af65615665fd6','71d867fd69e1e3f6bbac456e441db7ed110c33ab276fa96c3bd997ea0c066a3d','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-18 09:58:01');
/*!40000 ALTER TABLE `admin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL COMMENT 'Affected table',
  `action` varchar(30) NOT NULL COMMENT 'INSERT | UPDATE | ARCHIVE | PAYMENT',
  `record_id` int(11) NOT NULL COMMENT 'Primary key of the affected row',
  `changed_by` int(11) DEFAULT NULL COMMENT 'User ID from @audit_user_id',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP from @audit_ip',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Row snapshot before the change' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Row snapshot after the change' CHECK (json_valid(`new_values`)),
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit footprint for all critical table operations in NexGen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `businesses`
--

DROP TABLE IF EXISTS `businesses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `businesses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `business_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `business_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_code` (`business_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `businesses`
--

LOCK TABLES `businesses` WRITE;
/*!40000 ALTER TABLE `businesses` DISABLE KEYS */;
INSERT INTO `businesses` VALUES (1,'Concentrix','Other SME','Pasig City','2026-08-06 07:10:22','SME-C842D3'),(2,'Omri Bakery Shop','Retail Store','Cainta, Rizal','2026-08-06 07:19:13','SME-A96981'),(3,'sed hardware','Hardware / Construction Supplies','038B st. martin san andres cainta rizal','2026-08-13 09:17:55','SME-FCEA8E'),(4,'Nena\'s Sari-Sari Store','Mini Grocery / Sari-Sari Store','Barangay San Andres, Cainta, Rizal','2026-08-13 10:22:10','SME-1F68A2'),(5,'MediCare Pharmacy','Pharmacy / Drugstore','Barangay San Andres, Cainta, Rizal','2026-08-13 10:22:10','SME-4492FA'),(6,'Scholar\'s Corner Supplies','School / Office Supplies','Barangay San Andres, Cainta, Rizal','2026-08-13 10:22:10','SME-8CBDA8');
/*!40000 ALTER TABLE `businesses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_business_name` (`business_id`,`category_name`),
  KEY `idx_categories_business` (`business_id`),
  CONSTRAINT `fk_categories_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (11,1,'Instant Noodle','2026-08-06 07:12:54'),(12,2,'Canned Goods','2026-08-06 15:50:35'),(13,3,'Cement & Aggregates','2026-08-13 10:24:07'),(14,3,'Steel & Rebars','2026-08-13 10:24:07'),(15,3,'Plumbing Supplies','2026-08-13 10:24:07'),(16,3,'Electrical Supplies','2026-08-13 10:24:07'),(17,3,'Hand Tools','2026-08-13 10:24:07'),(18,3,'Power Tools','2026-08-13 10:24:07'),(19,3,'Paint & Finishes','2026-08-13 10:24:07'),(20,3,'Fasteners & Hardware','2026-08-13 10:24:07'),(21,4,'Canned Goods','2026-08-13 10:24:07'),(22,4,'Instant Noodles','2026-08-13 10:24:07'),(23,4,'Snacks & Chips','2026-08-13 10:24:07'),(24,4,'Beverages','2026-08-13 10:24:07'),(25,4,'Rice & Grains','2026-08-13 10:24:07'),(26,4,'Condiments & Sauces','2026-08-13 10:24:07'),(27,4,'Toiletries','2026-08-13 10:24:07'),(28,4,'Household Supplies','2026-08-13 10:24:07'),(29,5,'Pain Relievers','2026-08-13 10:24:07'),(30,5,'Antibiotics','2026-08-13 10:24:07'),(31,5,'Vitamins & Supplements','2026-08-13 10:24:07'),(32,5,'Cough & Cold Medicine','2026-08-13 10:24:07'),(33,5,'First Aid Supplies','2026-08-13 10:24:07'),(34,5,'Personal Care','2026-08-13 10:24:07'),(35,5,'Baby Care','2026-08-13 10:24:07'),(36,5,'Medical Devices','2026-08-13 10:24:07'),(37,6,'Writing Instruments','2026-08-13 10:24:07'),(38,6,'Paper Products','2026-08-13 10:24:07'),(39,6,'Notebooks & Journals','2026-08-13 10:24:07'),(40,6,'Art Supplies','2026-08-13 10:24:07'),(41,6,'Office Equipment','2026-08-13 10:24:07'),(42,6,'Filing & Organization','2026-08-13 10:24:07'),(43,6,'Printing Supplies','2026-08-13 10:24:07'),(44,6,'Bags & Backpacks','2026-08-13 10:24:07');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_business_code` (`business_id`,`customer_code`),
  KEY `idx_customers_business` (`business_id`),
  KEY `idx_customers_business_code` (`business_id`,`customer_code`),
  CONSTRAINT `fk_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_batches`
--

DROP TABLE IF EXISTS `product_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `status` enum('active','dropped') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_batches_business` (`business_id`),
  KEY `idx_product_batches_product` (`product_id`,`expiry_date`),
  CONSTRAINT `fk_product_batches_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_product_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (6,4,86,'B-CB-01',NULL,'2027-03-15',80.000,'active','2026-08-18 09:10:28'),(7,4,87,'B-TU-01',NULL,'2027-05-20',100.000,'active','2026-08-18 09:10:28'),(8,4,88,'B-CM-01',NULL,'2026-08-28',60.000,'active','2026-08-18 09:10:28'),(9,4,89,'B-PC-01',NULL,'2027-01-10',200.000,'active','2026-08-18 09:10:28'),(10,4,90,'B-CN-01',NULL,'2026-12-05',90.000,'active','2026-08-18 09:10:28'),(11,4,91,'B-IM-01',NULL,'2026-11-15',150.000,'active','2026-08-18 09:10:28'),(12,4,92,'B-PI-01',NULL,'2026-10-30',70.000,'active','2026-08-18 09:10:28'),(13,4,93,'B-BB-01',NULL,'2026-09-20',85.000,'active','2026-08-18 09:10:28'),(14,4,94,'B-CH-01',NULL,'2026-08-26',75.000,'active','2026-08-18 09:10:28'),(15,4,95,'B-CC-01',NULL,'2027-02-01',48.000,'active','2026-08-18 09:10:28'),(16,4,96,'B-NC-01',NULL,'2027-06-10',40.000,'active','2026-08-18 09:10:28'),(17,4,97,'B-C2-01',NULL,'2026-12-25',60.000,'active','2026-08-18 09:10:28'),(18,4,98,'B-RI-01',NULL,'2027-08-01',150.500,'active','2026-08-18 09:10:28'),(19,4,99,'B-JR-01',NULL,'2027-07-15',25.000,'active','2026-08-18 09:10:28'),(20,4,100,'B-MB-01',NULL,'2027-04-20',40.750,'active','2026-08-18 09:10:28'),(21,4,101,'B-SS-01',NULL,'2027-09-01',55.000,'active','2026-08-18 09:10:28'),(22,4,102,'B-BK-01',NULL,'2027-03-10',65.000,'active','2026-08-18 09:10:28'),(23,4,103,'B-VN-01',NULL,'2027-05-05',50.000,'active','2026-08-18 09:10:28'),(24,4,104,'B-SG-01',NULL,'2027-10-01',100.000,'active','2026-08-18 09:10:28'),(25,4,105,'B-CT-01',NULL,'2027-08-15',70.000,'active','2026-08-18 09:10:28'),(26,4,106,'B-PS-01',NULL,'2027-06-20',45.000,'active','2026-08-18 09:10:28'),(27,4,107,'B-TD-01',NULL,'2027-11-01',55.000,'active','2026-08-18 09:10:28'),(28,4,108,'B-JD-01',NULL,'2026-08-05',60.000,'active','2026-08-18 09:10:28'),(29,4,109,'B-ZX-01',NULL,'2027-01-20',50.000,'active','2026-08-18 09:10:28'),(30,5,110,'B-BG-01',NULL,'2027-04-15',100.000,'active','2026-08-18 09:11:37'),(31,5,111,'B-AD-01',NULL,'2027-02-20',60.000,'active','2026-08-18 09:11:37'),(32,5,112,'B-AL-01',NULL,'2026-08-27',70.000,'active','2026-08-18 09:11:37'),(34,5,114,'B-CA-01',NULL,'2026-12-05',35.000,'active','2026-08-18 09:11:37'),(35,5,115,'B-CX-01',NULL,'2026-11-20',40.000,'active','2026-08-18 09:11:37'),(36,5,116,'B-EN-01',NULL,'2027-06-15',80.000,'active','2026-08-18 09:11:37'),(37,5,117,'B-CT-02',NULL,'2027-08-01',30.000,'active','2026-08-18 09:11:37'),(38,5,118,'B-PC-02',NULL,'2027-05-10',65.000,'active','2026-08-18 09:11:37'),(39,5,119,'B-NZ-01',NULL,'2026-10-15',75.000,'active','2026-08-18 09:11:37'),(40,5,120,'B-RB-01',NULL,'2026-09-25',45.000,'active','2026-08-18 09:11:37'),(41,5,121,'B-BF-01',NULL,'2026-08-24',70.000,'active','2026-08-18 09:11:37'),(42,5,122,'B-BT-01',NULL,'2027-07-01',40.000,'active','2026-08-18 09:11:37'),(43,5,123,'B-BA-01',NULL,'2028-01-01',90.000,'active','2026-08-18 09:11:37'),(44,5,124,'B-GZ-01',NULL,'2027-09-15',60.000,'active','2026-08-18 09:11:37'),(45,5,125,'B-GC-01',NULL,'2027-03-01',100.000,'active','2026-08-18 09:11:37'),(46,5,126,'B-CF-01',NULL,'2027-06-01',20.000,'active','2026-08-18 09:11:37'),(47,5,127,'B-PU-01',NULL,'2026-08-05',55.000,'active','2026-08-18 09:11:37'),(48,5,128,'B-PM-01',NULL,'2027-10-01',30.000,'active','2026-08-18 09:11:37'),(49,5,129,'B-JB-01',NULL,'2027-12-01',45.000,'active','2026-08-18 09:11:37'),(50,5,130,'B-BW-01',NULL,'2027-04-01',10.000,'active','2026-08-18 09:11:37'),(51,5,131,'B-DT-01',NULL,'2029-01-01',25.000,'active','2026-08-18 09:11:37'),(52,5,132,'B-BP-01',NULL,'2029-01-01',15.000,'active','2026-08-18 09:11:37'),(53,5,133,'B-PO-01',NULL,'2029-01-01',20.000,'active','2026-08-18 09:11:37'),(55,5,113,'INITIAL',NULL,NULL,0.000,'active','2026-08-25 01:32:35');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_product_batches_after_insert AFTER INSERT ON product_batches
FOR EACH ROW
BEGIN
    UPDATE products
    SET stock_quantity = (
        SELECT COALESCE(SUM(quantity),0) FROM product_batches
        WHERE product_id = NEW.product_id AND status = 'active'
          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    )
    WHERE id = NEW.product_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_product_batches_after_update AFTER UPDATE ON product_batches
FOR EACH ROW
BEGIN
    UPDATE products
    SET stock_quantity = (
        SELECT COALESCE(SUM(quantity),0) FROM product_batches
        WHERE product_id = NEW.product_id AND status = 'active'
          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    )
    WHERE id = NEW.product_id;
    IF OLD.product_id <> NEW.product_id THEN
        UPDATE products
        SET stock_quantity = (
            SELECT COALESCE(SUM(quantity),0) FROM product_batches
            WHERE product_id = OLD.product_id AND status = 'active'
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        )
        WHERE id = OLD.product_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_product_batches_after_delete AFTER DELETE ON product_batches
FOR EACH ROW
BEGIN
    UPDATE products
    SET stock_quantity = (
        SELECT COALESCE(SUM(quantity),0) FROM product_batches
        WHERE product_id = OLD.product_id AND status = 'active'
          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    )
    WHERE id = OLD.product_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` decimal(12,3) NOT NULL DEFAULT 5.000,
  `on_order_level` decimal(12,3) NOT NULL DEFAULT 0.000,
  `expiry_date` date DEFAULT NULL,
  `product_image` varchar(255) DEFAULT 'uploads/products/default.png',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_business_code` (`business_id`,`product_code`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_business` (`business_id`),
  KEY `idx_products_business_category` (`business_id`,`category_id`),
  KEY `idx_products_business_created` (`business_id`,`created_at`),
  CONSTRAINT `fk_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (34,1,'01','Cup Noodles Sotanghon',11,'Nissin','pcs',30.00,35.00,0.00,10.000,5.000,20.000,'2027-02-24','uploads/products/product_1786000489_6a743469ba0f0.png','Eat Responsibly.',1,'2026-08-06 07:14:49','2026-08-07 05:50:56'),(35,2,'01','Ground Pork Mix',12,'Argentina','pcs',40.00,47.00,0.00,20.000,5.000,0.000,'2028-01-01','uploads/products/product_cc14c3a5008edd92.png','Eat Responsibly.',1,'2026-08-06 15:52:00','2026-08-06 15:52:00'),(38,3,'HW-001','Portland Cement 40kg Bag',13,'Republic Cement','bag',210.00,245.00,0.00,60.000,10.000,0.000,NULL,'uploads/products/default.png','General purpose Portland cement for construction',1,'2026-08-18 09:07:37','2026-08-24 16:34:49'),(39,3,'HW-002','Washed Sand',13,'Local Supplier','cu.m',850.00,980.00,0.00,20.500,5.000,0.000,NULL,'uploads/products/default.png','Fine washed sand for masonry work',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(40,3,'HW-003','Crushed Gravel 3/4in',13,'Local Supplier','cu.m',900.00,1050.00,0.00,15.750,5.000,0.000,NULL,'uploads/products/default.png','Coarse aggregate for concrete mixing',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(41,3,'HW-004','Deformed Bar 10mm x 6m',14,'PhilSteel','piece',185.00,215.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Reinforcing steel bar, grade 40',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(42,3,'HW-005','Deformed Bar 12mm x 6m',14,'PhilSteel','piece',265.00,305.00,0.00,80.000,15.000,0.000,NULL,'uploads/products/default.png','Reinforcing steel bar, grade 40',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(43,3,'HW-006','Steel Wire Mesh 4x8',14,'PhilSteel','roll',1450.00,1680.00,0.00,25.000,5.000,0.000,NULL,'uploads/products/default.png','Welded wire mesh for slab reinforcement',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(44,3,'HW-007','PVC Pipe 1/2in x 3m',15,'Neltex','piece',95.00,115.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','Standard PVC pipe for water supply lines',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(45,3,'HW-008','PVC Elbow Fitting 1/2in',15,'Neltex','piece',8.00,12.00,0.00,200.000,50.000,0.000,NULL,'uploads/products/default.png','90-degree PVC elbow fitting',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(46,3,'HW-009','Standard Faucet',15,'Homeworth','piece',320.00,380.00,0.00,28.000,8.000,0.000,NULL,'uploads/products/default.png','Chrome-plated single-lever faucet',1,'2026-08-18 09:07:37','2026-08-18 09:16:40'),(47,3,'HW-010','THHN Wire #12 AWG',16,'Phelps Dodge','meter',18.50,24.00,0.00,500.500,100.000,0.000,NULL,'uploads/products/default.png','Copper THHN wire for household wiring',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(48,3,'HW-011','Circuit Breaker 20A',16,'Meiden','piece',145.00,175.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Molded case circuit breaker',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(49,3,'HW-012','Electrical Tape',16,'3M','roll',25.00,35.00,0.00,150.000,30.000,0.000,NULL,'uploads/products/default.png','PVC insulating tape',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(50,3,'HW-013','Claw Hammer 16oz',17,'Stanley','piece',180.00,225.00,0.00,25.000,5.000,0.000,NULL,'uploads/products/default.png','Steel claw hammer with fiberglass handle',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(51,3,'HW-014','Adjustable Wrench 10in',17,'Stanley','piece',220.00,270.00,0.00,20.000,5.000,0.000,NULL,'uploads/products/default.png','Chrome vanadium adjustable wrench',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(52,3,'HW-015','Screwdriver Set 6pc',17,'Stanley','set',250.00,310.00,0.00,35.000,8.000,0.000,NULL,'uploads/products/default.png','Assorted flathead and Phillips screwdrivers',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(53,3,'HW-016','Cordless Drill 18V',18,'Bosch','piece',2200.00,2650.00,0.00,12.000,3.000,0.000,NULL,'uploads/products/default.png','Cordless drill driver with battery and charger',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(54,3,'HW-017','Angle Grinder 4in',18,'Makita','piece',1650.00,1980.00,0.00,8.000,2.000,0.000,NULL,'uploads/products/default.png','Corded angle grinder for cutting and grinding',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(55,3,'HW-018','Circular Saw 7.25in',18,'Makita','piece',2800.00,3350.00,0.00,6.000,2.000,0.000,NULL,'uploads/products/default.png','Corded circular saw for wood cutting',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(56,3,'HW-019','Latex Paint White 1 Gallon',19,'Boysen','gallon',450.00,540.00,0.00,45.500,10.000,0.000,NULL,'uploads/products/default.png','Water-based latex paint, interior/exterior',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(57,3,'HW-020','Enamel Paint Red 1L',19,'Boysen','liter',210.00,260.00,0.00,30.000,8.000,0.000,NULL,'uploads/products/default.png','Oil-based enamel paint, gloss finish',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(58,3,'HW-021','Paint Thinner',19,'Boysen','liter',65.00,85.00,0.00,25.500,8.000,0.000,NULL,'uploads/products/default.png','Mineral spirits paint thinner',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(59,3,'HW-022','Common Nails 3in',20,'Local Supplier','kg',68.00,85.00,0.00,75.250,15.000,0.000,NULL,'uploads/products/default.png','Common wire nails, sold by weight',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(60,3,'HW-023','Wood Screws Assorted',20,'Local Supplier','box',95.00,125.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Assorted sizes wood screws, box of 100',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(61,3,'HW-024','Standard Door Hinges',20,'Local Supplier','piece',35.00,48.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','3-inch steel door hinge',1,'2026-08-18 09:07:37','2026-08-18 09:07:37'),(62,6,'SC-001','Pilot G-2 Gel Pen Black',37,'Pilot','piece',35.00,50.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Retractable gel ink pen, 0.7mm tip',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(63,6,'SC-002','Mongol Pencil No.2 Box of 12',37,'Mongol','box',60.00,85.00,0.00,50.000,10.000,0.000,NULL,'uploads/products/default.png','Wood-cased graphite pencils',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(64,6,'SC-003','Stabilo Highlighter Yellow',37,'Stabilo','piece',40.00,58.00,0.00,80.000,15.000,0.000,NULL,'uploads/products/default.png','Chisel-tip fluorescent highlighter',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(65,6,'SC-004','Bond Paper A4 Ream',38,'Hallmark','ream',180.00,230.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','70gsm substance 20 bond paper, 500 sheets',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(66,6,'SC-005','Index Card 3x5 Pack',38,'Orions','pack',45.00,65.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Ruled index cards, pack of 100',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(67,6,'SC-006','Sticky Notes 3x3 Pad',38,'3M Post-it','pad',25.00,38.00,0.00,90.000,20.000,0.000,NULL,'uploads/products/default.png','Self-adhesive note pad, 100 sheets',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(68,6,'SC-007','Spiral Notebook 80 Leaves',39,'Veco','piece',28.00,42.00,0.00,70.000,15.000,0.000,NULL,'uploads/products/default.png','Wire-bound ruled notebook',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(69,6,'SC-008','Composition Notebook',39,'Veco','piece',22.00,35.00,0.00,65.000,15.000,0.000,NULL,'uploads/products/default.png','Sewn-bound notebook, 80 leaves',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(70,6,'SC-009','Hardbound Journal Diary',39,'National Book Store','piece',95.00,135.00,0.00,30.000,8.000,0.000,NULL,'uploads/products/default.png','Hardbound journal with ribbon marker',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(71,6,'SC-010','Crayola Crayons 24-Pack',40,'Crayola','box',85.00,120.00,0.00,35.000,8.000,0.000,NULL,'uploads/products/default.png','Assorted color wax crayons',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(72,6,'SC-011','Watercolor Set 12 Colors',40,'Faber-Castell','set',110.00,155.00,0.00,25.000,6.000,0.000,NULL,'uploads/products/default.png','Pan watercolor set with brush',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(73,6,'SC-012','Sketch Pad A4',40,'Canson','piece',65.00,95.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Medium-grain drawing paper pad',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(74,6,'SC-013','Standard Stapler',41,'Max','piece',75.00,110.00,0.00,20.000,5.000,0.000,NULL,'uploads/products/default.png','No.10 desktop stapler',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(75,6,'SC-014','Electric Pencil Sharpener',41,'Bostitch','piece',350.00,450.00,0.00,10.000,3.000,0.000,NULL,'uploads/products/default.png','Auto-stop electric pencil sharpener',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(76,6,'SC-015','Desk Organizer',41,'Deli','piece',180.00,250.00,0.00,15.000,4.000,0.000,NULL,'uploads/products/default.png','Multi-compartment desktop organizer',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(77,6,'SC-016','Expanding Folder',42,'Orions','piece',25.00,38.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Kraft expanding document folder',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(78,6,'SC-017','Ring Binder 2-inch',42,'Orions','piece',95.00,135.00,0.00,30.000,8.000,0.000,NULL,'uploads/products/default.png','D-ring view binder',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(79,6,'SC-018','Plastic Envelope',42,'Orions','piece',15.00,25.00,0.00,120.000,25.000,0.000,NULL,'uploads/products/default.png','Clear plastic document envelope with button',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(80,6,'SC-019','Inkjet Ink Cartridge Black',43,'Epson','piece',450.00,580.00,0.00,25.000,6.000,0.000,NULL,'uploads/products/default.png','Original ink cartridge for inkjet printers',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(81,6,'SC-020','Laser Toner Cartridge',43,'Brother','piece',2800.00,3400.00,0.00,8.000,2.000,0.000,NULL,'uploads/products/default.png','Original toner cartridge for laser printers',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(82,6,'SC-021','Printer Paper A4 Ream',43,'Hallmark','ream',180.00,230.00,0.00,50.000,12.000,0.000,NULL,'uploads/products/default.png','70gsm substance 20 bond paper, 500 sheets',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(83,6,'SC-022','Standard School Backpack',44,'Deuter','piece',380.00,520.00,0.00,25.000,6.000,0.000,NULL,'uploads/products/default.png','Water-resistant school backpack',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(84,6,'SC-023','Pencil Case',44,'Deli','piece',45.00,68.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','Zippered fabric pencil case',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(85,6,'SC-024','Insulated Lunch Bag',44,'Deli','piece',150.00,210.00,0.00,20.000,5.000,0.000,NULL,'uploads/products/default.png','Insulated lunch bag with zipper closure',1,'2026-08-18 09:08:11','2026-08-18 09:08:11'),(86,4,'GR-001','Argentina Corned Beef 150g',21,'Argentina','can',45.00,58.00,0.00,80.000,15.000,0.000,NULL,'uploads/products/default.png','Canned corned beef',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(87,4,'GR-002','Century Tuna Flakes in Oil 155g',21,'Century Tuna','can',38.00,50.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Tuna flakes in vegetable oil',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(88,4,'GR-003','Angel Condensed Milk 300ml',21,'Angel','can',42.00,55.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','Sweetened condensed milk',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(89,4,'GR-004','Lucky Me Pancit Canton Original',22,'Lucky Me','pack',12.00,17.00,0.00,200.000,40.000,0.000,NULL,'uploads/products/default.png','Instant stir-fry noodles',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(90,4,'GR-005','Nissin Cup Noodles Seafood',22,'Nissin','cup',25.00,33.00,0.00,90.000,20.000,0.000,NULL,'uploads/products/default.png','Instant cup noodles, seafood flavor',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(91,4,'GR-006','Payless Instant Mami',22,'Payless','pack',8.00,12.00,0.00,150.000,30.000,0.000,NULL,'uploads/products/default.png','Instant noodle soup',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(92,4,'GR-007','Piattos Cheese 85g',23,'Piattos','pack',28.00,38.00,0.00,70.000,15.000,0.000,NULL,'uploads/products/default.png','Potato crisps, cheese flavor',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(93,4,'GR-008','Boy Bawang Cornick 100g',23,'Boy Bawang','pack',18.00,25.00,0.00,85.000,15.000,0.000,NULL,'uploads/products/default.png','Fried corn snack',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(94,4,'GR-009','Chippy BBQ 110g',23,'Chippy','pack',20.00,28.00,0.00,75.000,15.000,0.000,NULL,'uploads/products/default.png','Corn chips, BBQ flavor',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(95,4,'GR-010','Coca-Cola 1.5L',24,'Coca-Cola','bottle',55.00,72.00,0.00,48.000,10.000,0.000,NULL,'uploads/products/default.png','Carbonated soft drink',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(96,4,'GR-011','Nescafe 3-in-1 Original Box',24,'Nescafe','box',95.00,125.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Instant coffee mix, box of 30 sachets',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(97,4,'GR-012','C2 Green Tea 500ml',24,'C2','bottle',22.00,30.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','Bottled iced tea',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(98,4,'GR-013','Sinandomeng Rice',25,'Local Supplier','kg',48.00,58.00,0.00,150.500,30.000,0.000,NULL,'uploads/products/default.png','Well-milled rice, sold by weight',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(99,4,'GR-014','Jasmine Rice 5kg Pack',25,'Local Supplier','pack',280.00,340.00,0.00,25.000,6.000,0.000,NULL,'uploads/products/default.png','Premium jasmine rice',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(100,4,'GR-015','Monggo Beans',25,'Local Supplier','kg',85.00,105.00,0.00,40.750,10.000,0.000,NULL,'uploads/products/default.png','Dried mung beans, sold by weight',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(101,4,'GR-016','Datu Puti Soy Sauce 1L',26,'Datu Puti','bottle',45.00,58.00,0.00,55.000,10.000,0.000,NULL,'uploads/products/default.png','Soy sauce',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(102,4,'GR-017','UFC Banana Ketchup 320g',26,'UFC','bottle',32.00,42.00,0.00,65.000,12.000,0.000,NULL,'uploads/products/default.png','Banana ketchup',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(103,4,'GR-018','Silver Swan Vinegar 1L',26,'Silver Swan','bottle',28.00,38.00,0.00,50.000,10.000,0.000,NULL,'uploads/products/default.png','Cane vinegar',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(104,4,'GR-019','Safeguard Bar Soap 90g',27,'Safeguard','piece',18.00,25.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Antibacterial bar soap',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(105,4,'GR-020','Colgate Toothpaste 150g',27,'Colgate','tube',55.00,72.00,0.00,70.000,15.000,0.000,NULL,'uploads/products/default.png','Fluoride toothpaste',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(106,4,'GR-021','Palmolive Shampoo Sachet Box',27,'Palmolive','box',65.00,85.00,0.00,45.000,10.000,0.000,NULL,'uploads/products/default.png','Shampoo sachets, box of 12',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(107,4,'GR-022','Tide Detergent Powder 1kg',28,'Tide','pack',85.00,110.00,0.00,55.000,10.000,0.000,NULL,'uploads/products/default.png','Powder laundry detergent',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(108,4,'GR-023','Joy Dishwashing Liquid 250ml',28,'Joy','bottle',32.00,42.00,0.00,0.000,15.000,0.000,NULL,'uploads/products/default.png','Dishwashing liquid, lemon scent',1,'2026-08-18 09:09:23','2026-08-25 01:18:07'),(109,4,'GR-024','Zonrox Bleach 1L',28,'Zonrox','bottle',38.00,48.00,0.00,50.000,10.000,0.000,NULL,'uploads/products/default.png','Chlorine bleach',1,'2026-08-18 09:09:23','2026-08-18 09:10:28'),(110,5,'PH-001','Biogesic Paracetamol 500mg',29,'Biogesic','box',65.00,85.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Pain reliever and fever reducer, box of 100 tablets',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(111,5,'PH-002','Advil Ibuprofen 200mg',29,'Advil','box',95.00,125.00,0.00,60.000,15.000,0.000,NULL,'uploads/products/default.png','Anti-inflammatory pain reliever, box of 20',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(112,5,'PH-003','Alaxan FR',29,'Alaxan','box',78.00,100.00,0.00,70.000,15.000,0.000,NULL,'uploads/products/default.png','Pain reliever with muscle relaxant, box of 20',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(113,5,'PH-004','Amoxicillin 500mg',30,'Amoxil','box',120.00,155.00,0.00,0.000,10.000,0.000,NULL,'uploads/products/default.png','Antibiotic capsules, box of 21',1,'2026-08-18 09:10:51','2026-08-18 11:07:11'),(114,5,'PH-005','Co-Amoxiclav 625mg',30,'Augmentin','box',185.00,235.00,0.00,35.000,8.000,0.000,NULL,'uploads/products/default.png','Antibiotic tablets, box of 10',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(115,5,'PH-006','Cephalexin 500mg',30,'Keflex','box',145.00,185.00,0.00,40.000,8.000,0.000,NULL,'uploads/products/default.png','Antibiotic capsules, box of 20',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(116,5,'PH-007','Enervon C',31,'Enervon','box',95.00,125.00,0.00,80.000,15.000,0.000,NULL,'uploads/products/default.png','Multivitamin with vitamin C, box of 30',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(117,5,'PH-008','Centrum Advance',31,'Centrum','box',350.00,430.00,0.00,30.000,6.000,0.000,NULL,'uploads/products/default.png','Multivitamin and mineral supplement, box of 30',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(118,5,'PH-009','Poten-Cee 500mg',31,'Poten-Cee','box',85.00,110.00,0.00,65.000,12.000,0.000,NULL,'uploads/products/default.png','Vitamin C supplement, box of 30',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(119,5,'PH-010','Neozep Forte',32,'Neozep','box',55.00,72.00,0.00,75.000,15.000,0.000,NULL,'uploads/products/default.png','Cold and flu relief tablets, box of 20',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(120,5,'PH-011','Robitussin Cough Syrup 60ml',32,'Robitussin','bottle',68.00,88.00,0.00,45.000,10.000,0.000,NULL,'uploads/products/default.png','Cough suppressant syrup',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(121,5,'PH-012','Bioflu',32,'Bioflu','box',62.00,82.00,0.00,0.000,15.000,0.000,NULL,'uploads/products/default.png','Flu relief tablets, box of 20',1,'2026-08-18 09:10:51','2026-08-25 01:18:07'),(122,5,'PH-013','Betadine Antiseptic Solution 60ml',33,'Betadine','bottle',75.00,95.00,0.00,40.000,10.000,0.000,NULL,'uploads/products/default.png','Povidone-iodine antiseptic solution',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(123,5,'PH-014','Band-Aid Assorted Box',33,'Band-Aid','box',45.00,60.00,0.00,90.000,15.000,0.000,NULL,'uploads/products/default.png','Assorted adhesive bandages, box of 100',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(124,5,'PH-015','Sterile Gauze Pad Box',33,'Medicare','box',55.00,72.00,0.00,60.000,12.000,0.000,NULL,'uploads/products/default.png','Sterile gauze pads, box of 50',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(125,5,'PH-016','Green Cross Alcohol 70% 500ml',34,'Green Cross','bottle',48.00,62.00,0.00,100.000,20.000,0.000,NULL,'uploads/products/default.png','Isopropyl alcohol disinfectant',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(126,5,'PH-017','Cetaphil Gentle Cleanser 125ml',34,'Cetaphil','bottle',350.00,420.00,0.00,20.000,5.000,0.000,NULL,'uploads/products/default.png','Gentle skin cleanser',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(127,5,'PH-018','Purell Hand Sanitizer 100ml',34,'Purell','bottle',85.00,110.00,0.00,0.000,10.000,0.000,NULL,'uploads/products/default.png','Alcohol-based hand sanitizer',1,'2026-08-18 09:10:51','2026-08-25 01:18:07'),(128,5,'PH-019','Pampers Diapers Small Pack',35,'Pampers','pack',250.00,310.00,0.00,30.000,6.000,0.000,NULL,'uploads/products/default.png','Baby diapers, pack of 36',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(129,5,'PH-020','Johnson\'s Baby Powder 200g',35,'Johnson\'s','bottle',95.00,120.00,0.00,45.000,10.000,0.000,NULL,'uploads/products/default.png','Talc-based baby powder',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(130,5,'PH-021','Baby Wipes Pack',35,'Bactidol','pack',65.00,85.00,0.00,10.000,12.000,0.000,NULL,'uploads/products/default.png','Alcohol-free baby wipes, pack of 80',1,'2026-08-18 09:10:51','2026-08-18 11:08:35'),(131,5,'PH-022','Digital Thermometer',36,'Omron','piece',180.00,250.00,0.00,25.000,5.000,0.000,NULL,'uploads/products/default.png','Digital body thermometer',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(132,5,'PH-023','Blood Pressure Monitor',36,'Omron','piece',1200.00,1550.00,0.00,15.000,3.000,0.000,NULL,'uploads/products/default.png','Automatic digital blood pressure monitor',1,'2026-08-18 09:10:51','2026-08-18 09:11:37'),(133,5,'PH-024','Pulse Oximeter',36,'Beurer','piece',650.00,850.00,0.00,20.000,4.000,0.000,NULL,'uploads/products/default.png','Fingertip pulse oximeter',1,'2026-08-18 09:10:51','2026-08-18 09:11:37');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_products_after_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `receivable_payments`
--

DROP TABLE IF EXISTS `receivable_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receivable_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `receivable_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Manual Update',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rp_business` (`business_id`),
  KEY `idx_rp_receivable` (`receivable_id`),
  KEY `idx_rp_sale` (`sale_id`),
  KEY `idx_rp_paid_at` (`paid_at`),
  KEY `idx_rp_business_paid_at` (`business_id`,`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivable_payments`
--

LOCK TABLES `receivable_payments` WRITE;
/*!40000 ALTER TABLE `receivable_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `receivable_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registration_requests`
--

DROP TABLE IF EXISTS `registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_code` (`request_code`),
  KEY `idx_registration_requests_status` (`request_status`),
  KEY `idx_registration_username` (`username`),
  KEY `idx_registration_email` (`email`),
  KEY `idx_registration_employee_no` (`employee_no`),
  KEY `idx_registration_business_code` (`business_code`),
  KEY `idx_registration_requests_business` (`business_id`),
  CONSTRAINT `fk_registration_requests_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_requests`
--

LOCK TABLES `registration_requests` WRITE;
/*!40000 ALTER TABLE `registration_requests` DISABLE KEYS */;
INSERT INTO `registration_requests` VALUES (18,'REQ-20260806-0001',NULL,'Matt Valdevia','mattraileyvaldevia@gmail.com','+639957266803','Pasig City','Desinomeme','$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6','uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg','owner','approved','Perfectly fine!',1,'2026-08-06 15:10:22','2026-08-06 07:00:10','Concentrix','Other SME','Pasig City','SME-C842D3',NULL),(19,'REQ-20260806-0002',NULL,'Joshua Malvar Isla','joshuaisla3@gmail.com','09789847841','Cainta, Rizal','JoshuaIsla','$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2','uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg','owner','approved','Pasok kana tol!',1,'2026-08-06 15:19:13','2026-08-06 07:18:10','Omri Bakery Shop','Retail Store','Cainta, Rizal','SME-A96981',NULL),(20,'REQ-20260807-0001','EMP-001','Avery Isaac Valdevia','averyisaac17@gmail.com','09055194166','Pasig City','mchousetons','$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW','uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg','employee','approved','Okay na!',1,'2026-08-07 00:37:06','2026-08-06 16:36:11','Concentrix','Other SME','Pasig City','SME-C842D3',NULL),(21,'REQ-20260813-0001',NULL,'Sedric Macasieb','sedricmacasieb29@gmail.com','09925853329','038 st. martin san andres cainta rizal','sed','$2y$10$1FIFbX0v.fYHb9TWFaW3vOKBRTXXvAGfXOB2v/VkcAOjB4KgRbKdi','uploads/valid_ids/valid_id_5c7d86b4e9f6fd4e77179487.png','owner','approved','approved',1,'2026-08-13 17:17:55','2026-08-13 08:08:17','sed hardware','Hardware / Construction Supplies','038B st. martin san andres cainta rizal','SME-FCEA8E',NULL);
/*!40000 ALTER TABLE `registration_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sale_items_sale` (`sale_id`),
  KEY `fk_sale_items_product` (`product_id`),
  KEY `idx_sale_items_sale_id` (`sale_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (68,60,34,10.000,35.00,0.00,350.00),(69,61,46,2.000,380.00,0.00,760.00),(70,62,113,50.000,155.00,0.00,7750.00),(71,63,130,50.000,85.00,0.00,4250.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `sales_no` varchar(50) NOT NULL,
  `salesperson_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Unpaid','Partially Paid') NOT NULL DEFAULT 'Paid',
  `payment_method` enum('Cash','GCash','Maya','Bank Transfer') NOT NULL DEFAULT 'Cash',
  `order_status` enum('Fulfilled','Pending','Cancelled') NOT NULL DEFAULT 'Fulfilled',
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_business_number` (`business_id`,`sales_no`),
  KEY `fk_sales_user` (`salesperson_id`),
  KEY `fk_sales_customer` (`customer_id`),
  KEY `idx_sales_sale_date` (`sale_date`),
  KEY `idx_sales_business` (`business_id`),
  KEY `idx_sales_business_date` (`business_id`,`sale_date`),
  KEY `idx_sales_business_number` (`business_id`,`sales_no`),
  CONSTRAINT `fk_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (60,1,'SAL-20260807-002355',2,NULL,350.00,'Paid','Cash','Fulfilled','2026-08-07 00:25:46','2026-08-06 16:25:46'),(61,3,'SAL-20260818-171430',5,NULL,760.00,'Paid','Cash','Fulfilled','2026-08-18 17:16:40','2026-08-18 09:16:40'),(62,5,'SAL-20260818-190644',7,NULL,7750.00,'Paid','Cash','Fulfilled','2026-08-18 19:07:11','2026-08-18 11:07:11'),(63,5,'SAL-20260818-190815',7,NULL,4250.00,'Paid','Cash','Fulfilled','2026-08-18 19:08:35','2026-08-18 11:08:35');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('stock_in','stock_out','order_placed') NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_stock_product` (`product_id`),
  KEY `idx_stock_movements_business` (`business_id`),
  KEY `idx_stock_business_created` (`business_id`,`created_at`),
  CONSTRAINT `fk_stock_movements_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,1,34,'stock_out',10.000,'Sale recorded: SAL-20260807-002355',2,'2026-08-06 16:25:46'),(2,3,46,'stock_out',2.000,'Sale recorded: SAL-20260818-171430',5,'2026-08-18 09:16:40'),(3,5,113,'stock_out',50.000,'Sale recorded: SAL-20260818-190644',7,'2026-08-18 11:07:11'),(4,5,130,'stock_out',50.000,'Sale recorded: SAL-20260818-190815',7,'2026-08-18 11:08:35'),(5,3,38,'order_placed',10.000,'republic cement',5,'2026-08-24 16:34:27'),(6,3,38,'stock_in',10.000,'received',5,'2026-08-24 16:34:49');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `business_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_business` (`business_id`),
  CONSTRAINT `fk_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','System Administrator','ADM-0001','admin@nexgen.local','09123456789','System','email',NULL,NULL,'$2y$10$wrzvU/O97SvEhdmCg3ji6OHsOAqI4pfKe0XJsnr4IUzmoriTRuYpi','system_admin','active','uploads/profile_6a74333eadb7d2.47780202.png',NULL,1,NULL,'2026-08-06 14:14:54',NULL,'2026-08-06 06:14:54',1,1,1,0,'2026-08-24 23:50:52',0,NULL,NULL,NULL),(2,'Desinomeme','Matt Valdevia',NULL,'mattraileyvaldevia@gmail.com','+639957266803','Pasig City','email',NULL,NULL,'$2y$10$I/hfZUKwrFyTh6dFkbBfXevFW6o5LRP3wKPnOGjnll.ZIr1iDVTw6','owner','active','uploads/profile_6a7433b04ab063.00525040.jpg','uploads/valid_ids/valid_id_238147a7fe5c91ef23020416.jpg',1,1,'2026-08-06 15:10:22',NULL,'2026-08-06 07:10:22',1,1,1,0,'2026-08-07 13:41:27',0,NULL,NULL,1),(3,'JoshuaIsla','Joshua Malvar Isla',NULL,'joshuaisla3@gmail.com','09789847841','Cainta, Rizal','email',NULL,NULL,'$2y$10$nVNhTr91SnV4513nmWGa.u/8yx677ZgjRYkTL/QDbe5z7yJ0rbfT2','owner','active','uploads/profile_6a7435c86f0a24.69298183.jpg','uploads/valid_ids/valid_id_f23d98cb23efe2fe803aacea.jpg',1,1,'2026-08-06 15:19:13',NULL,'2026-08-06 07:19:13',1,1,1,0,'2026-08-06 23:52:47',0,NULL,NULL,2),(4,'mchousetons','Avery Isaac Valdevia','EMP-001','averyisaac17@gmail.com','09055194166','Pasig City','email',NULL,NULL,'$2y$10$JFYqEfUXMMGRmwUTy7PwHu4o5Daa.g3FYzpaZWX12vSqvLE8EfuuW','employee','active','uploads/profile_6a74ba901c4954.00059389.jpg','uploads/valid_ids/valid_id_a0c974ed5c296f247aedbcc0.jpg',1,1,'2026-08-07 00:37:06',NULL,'2026-08-06 16:37:06',1,1,0,0,'2026-08-07 00:38:24',0,NULL,NULL,1),(5,'sed','Sedric Macasieb',NULL,'sedricmacasieb29@gmail.com','09925853329','038 st. martin san andres cainta rizal','email',NULL,NULL,'$2y$10$1FIFbX0v.fYHb9TWFaW3vOKBRTXXvAGfXOB2v/VkcAOjB4KgRbKdi','owner','active','uploads/profile_6a7d8c1f5e7572.54463733.png','uploads/valid_ids/valid_id_5c7d86b4e9f6fd4e77179487.png',1,1,'2026-08-13 17:17:55',NULL,'2026-08-13 09:17:55',1,1,1,0,'2026-08-25 00:25:45',0,NULL,NULL,3),(6,'grocery_test','Grocery Test Owner',NULL,'grocery_test@nexgen.local','09000000004','Barangay San Andres, Cainta, Rizal','email',NULL,NULL,'$2y$10$gpjT/Gcv1H2TXtqghFCAPuHVfSXuHvKgR4Izh11epccEak3Jyja12','owner','active','uploads/default.png',NULL,1,NULL,NULL,NULL,'2026-08-13 10:23:16',1,1,1,0,'2026-08-25 09:41:06',0,NULL,NULL,4),(7,'pharmacy_test','Pharmacy Test Owner',NULL,'pharmacy_test@nexgen.local','09000000005','Barangay San Andres, Cainta, Rizal','email',NULL,NULL,'$2y$10$gpjT/Gcv1H2TXtqghFCAPuHVfSXuHvKgR4Izh11epccEak3Jyja12','owner','active','uploads/default.png',NULL,1,NULL,NULL,NULL,'2026-08-13 10:23:16',1,1,1,0,'2026-08-18 18:47:36',0,NULL,NULL,5),(8,'school_test','School Supplies Test Owner',NULL,'school_test@nexgen.local','09000000006','Barangay San Andres, Cainta, Rizal','email',NULL,NULL,'$2y$10$gpjT/Gcv1H2TXtqghFCAPuHVfSXuHvKgR4Izh11epccEak3Jyja12','owner','active','uploads/default.png',NULL,1,NULL,NULL,NULL,'2026-08-13 10:23:16',1,1,1,0,NULL,0,NULL,NULL,6);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_receivable_summary`
--

DROP TABLE IF EXISTS `v_receivable_summary`;
/*!50001 DROP VIEW IF EXISTS `v_receivable_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_receivable_summary` AS SELECT
 1 AS `receivable_id`,
  1 AS `sale_id`,
  1 AS `customer_id`,
  1 AS `sales_no`,
  1 AS `customer_code`,
  1 AS `customer_name`,
  1 AS `total_amount`,
  1 AS `amount_paid`,
  1 AS `balance_due`,
  1 AS `due_date`,
  1 AS `stored_status`,
  1 AS `live_status`,
  1 AS `notes`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sales_summary`
--

DROP TABLE IF EXISTS `v_sales_summary`;
/*!50001 DROP VIEW IF EXISTS `v_sales_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_sales_summary` AS SELECT
 1 AS `sale_id`,
  1 AS `sales_no`,
  1 AS `sale_date`,
  1 AS `payment_status`,
  1 AS `order_status`,
  1 AS `customer_name`,
  1 AS `salesperson_name`,
  1 AS `product_name`,
  1 AS `quantity`,
  1 AS `unit_price`,
  1 AS `subtotal`,
  1 AS `total_amount` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'nexgen_db'
--

--
-- Current Database: `nexgen_db`
--

USE `nexgen_db`;

--
-- Final view structure for view `v_receivable_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_receivable_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_receivable_summary` AS select `ar`.`id` AS `receivable_id`,`ar`.`sale_id` AS `sale_id`,`ar`.`customer_id` AS `customer_id`,`s`.`sales_no` AS `sales_no`,`c`.`customer_code` AS `customer_code`,`c`.`customer_name` AS `customer_name`,`ar`.`total_amount` AS `total_amount`,`ar`.`amount_paid` AS `amount_paid`,`ar`.`balance_due` AS `balance_due`,`ar`.`due_date` AS `due_date`,`ar`.`status` AS `stored_status`,case when `ar`.`balance_due` <= 0 then 'Paid' when `ar`.`due_date` is not null and `ar`.`due_date` <> '' and `ar`.`due_date` < curdate() and `ar`.`balance_due` > 0 then 'Overdue' when `ar`.`amount_paid` > 0 and `ar`.`balance_due` > 0 then 'Partially Paid' else 'Unpaid' end AS `live_status`,`ar`.`notes` AS `notes`,`ar`.`created_at` AS `created_at`,`ar`.`updated_at` AS `updated_at` from ((`accounts_receivable` `ar` join `customers` `c` on(`ar`.`customer_id` = `c`.`id`)) join `sales` `s` on(`ar`.`sale_id` = `s`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sales_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_sales_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sales_summary` AS select `s`.`id` AS `sale_id`,`s`.`sales_no` AS `sales_no`,`s`.`sale_date` AS `sale_date`,`s`.`payment_status` AS `payment_status`,`s`.`order_status` AS `order_status`,`c`.`customer_name` AS `customer_name`,`u`.`full_name` AS `salesperson_name`,`p`.`product_name` AS `product_name`,`si`.`quantity` AS `quantity`,`si`.`unit_price` AS `unit_price`,`si`.`subtotal` AS `subtotal`,`s`.`total_amount` AS `total_amount` from ((((`sales` `s` left join `customers` `c` on(`s`.`customer_id` = `c`.`id`)) left join `users` `u` on(`s`.`salesperson_id` = `u`.`id`)) join `sale_items` `si` on(`s`.`id` = `si`.`sale_id`)) join `products` `p` on(`si`.`product_id` = `p`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-25  9:46:50
