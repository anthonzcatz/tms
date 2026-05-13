-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2026 at 10:47 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 8.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_types`
--

CREATE TABLE `accommodation_types` (
  `accommodation_id` bigint(20) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `accommodation_types`
--

INSERT INTO `accommodation_types` (`accommodation_id`, `code`, `name`, `created_at`) VALUES
(1, 'ECONOMY', 'Economy', '2026-05-09 01:29:12'),
(2, 'TOURIST', 'Tourist', '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `device_id` bigint(20) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `reference_code` varchar(100) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `activity_logs`
--

-- --------------------------------------------------------

--
-- Table structure for table `business_branches`
--

CREATE TABLE `business_branches` (
  `branch_id` bigint(20) NOT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(150) NOT NULL,
  `region_code` varchar(12) DEFAULT NULL,
  `province_code` varchar(12) DEFAULT NULL,
  `city_municipality_code` varchar(12) DEFAULT NULL,
  `barangay_code` varchar(12) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `business_branches`
--

INSERT INTO `business_branches` (`branch_id`, `branch_code`, `branch_name`, `region_code`, `province_code`, `city_municipality_code`, `barangay_code`, `street_address`, `landmark`, `zip_code`, `contact_number`, `email`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'MAIN_BRANCH', 'Main Branch', NULL, NULL, NULL, NULL, '123 Main Street', 'Near City Hall', '8000', '09171234567', 'mainbranch@example.com', 'active', '2026-05-09 01:29:12', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `dept_id` int(11) NOT NULL,
  `department_name` varchar(100) DEFAULT NULL,
  `department_code` varchar(100) DEFAULT NULL,
  `dept_logo` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `dept_addedby` int(11) NOT NULL,
  `dept_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`dept_id`, `department_name`, `department_code`, `dept_logo`, `status`, `dept_addedby`, `dept_dateadded`) VALUES
(1, 'Hardware', 'AE', 'dept_logo_1764032567_692500377627f.png', '', 3, '2025-11-25 01:02:47'),
(2, 'Advertising', 'AA', 'dept_logo_1764032872_69250168eff38.png', '', 3, '2026-04-21 00:37:16'),
(3, 'Star Audio', '', 'dept_logo_1764033072_692502309926a.png', '', 2, '2025-11-25 01:11:12'),
(4, 'Logistic', '', '', '', 9, '2025-03-25 06:44:28'),
(5, 'Litzbuild', '', 'dept_logo_1764033190_692502a65bce7.png', '', 9, '2025-11-25 01:13:10'),
(6, 'Motorpool', '', '', '', 9, '2025-03-25 06:44:58'),
(7, 'Ticketing', 'TCKT', 'dept_logo_1764032884_6925017452791.png', '', 2, '2025-11-25 01:08:04'),
(8, 'Construction', '', '', '', 9, '2025-09-02 03:00:53'),
(9, 'Quarry', '', '', '', 9, '2025-09-02 07:23:46');

-- --------------------------------------------------------

--
-- Table structure for table `discount_types`
--

CREATE TABLE `discount_types` (
  `discount_id` bigint(20) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `discount_types`
--

INSERT INTO `discount_types` (`discount_id`, `code`, `name`, `description`, `created_at`) VALUES
(1, 'REGULAR', 'Regular', 'Regular passenger', '2026-05-09 01:29:12'),
(2, 'STUDENT', 'Student', 'Student discounted fare', '2026-05-09 01:29:12'),
(3, 'SENIOR', 'Senior Citizen', 'Senior citizen discounted fare', '2026-05-09 01:29:12'),
(4, 'PWD', 'PWD', 'Person with disability discounted fare', '2026-05-09 01:29:12'),
(5, 'MINOR', 'Minor', 'Minor passenger discounted fare', '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(11) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `b_date` date NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(10) DEFAULT NULL,
  `b_permanent_address` text NOT NULL,
  `emp_street_address` varchar(255) DEFAULT NULL,
  `emp_province_code` varchar(12) DEFAULT NULL,
  `emp_city_code` varchar(12) DEFAULT NULL,
  `emp_barangay_code` varchar(12) DEFAULT NULL,
  `b_cont_no` varchar(100) NOT NULL,
  `b_citizenship` varchar(50) NOT NULL,
  `b_placebirth` text NOT NULL,
  `b_religion` varchar(50) NOT NULL,
  `b_sex` varchar(50) NOT NULL,
  `b_civil_status` varchar(50) NOT NULL,
  `b_height` varchar(50) NOT NULL,
  `b_weight` varchar(50) NOT NULL,
  `job_title` int(11) NOT NULL,
  `b_address` text NOT NULL,
  `b_email` varchar(250) NOT NULL,
  `date_hired` date NOT NULL,
  `daily_rate` int(11) NOT NULL,
  `cola` int(11) NOT NULL,
  `b_department_id` int(11) NOT NULL,
  `b_sub_department_id` int(11) NOT NULL,
  `b_company_id` int(11) NOT NULL,
  `b_employment_status_id` int(11) NOT NULL,
  `b_philhealth` varchar(100) NOT NULL,
  `b_sss` varchar(100) NOT NULL,
  `b_pagibig` varchar(100) NOT NULL,
  `b_tinnumber` varchar(100) NOT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_relationship` varchar(255) DEFAULT NULL,
  `emergency_contact_number` varchar(15) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `notifications` int(11) NOT NULL,
  `user_img` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `b_addedby` int(11) NOT NULL,
  `b_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employment_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `employees`
--

-- --------------------------------------------------------

--
-- Table structure for table `employment_status`
--

CREATE TABLE `employment_status` (
  `emp_stat_id` int(11) NOT NULL,
  `emp_stat_name` varchar(100) NOT NULL,
  `emp_stat_addedby` int(11) NOT NULL,
  `emp_stat_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employment_status`
--

INSERT INTO `employment_status` (`emp_stat_id`, `emp_stat_name`, `emp_stat_addedby`, `emp_stat_dateadded`) VALUES
(1, 'Regular', 2, '2024-12-27 03:29:00'),
(2, 'Contractual', 2, '2024-12-27 03:25:25'),
(3, 'Casual', 2, '2024-12-27 03:25:59'),
(4, 'Internship/OJT', 2, '2024-12-27 03:25:59'),
(5, 'Active', 2, '2024-12-28 06:37:01'),
(6, 'Inactive', 2, '2024-12-28 06:37:01'),
(7, 'Blacklist', 2, '2024-12-28 06:37:18');

-- --------------------------------------------------------

--
-- Table structure for table `passenger_accounts`
--

CREATE TABLE `passenger_accounts` (
  `passenger_id` bigint(20) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `region_code` varchar(12) DEFAULT NULL,
  `province_code` varchar(12) DEFAULT NULL,
  `city_municipality_code` varchar(12) DEFAULT NULL,
  `barangay_code` varchar(12) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` bigint(20) NOT NULL,
  `permission_code` varchar(100) DEFAULT NULL,
  `permission_name` varchar(150) DEFAULT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `parent_permission_id` bigint(20) DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `menu_icon` varchar(50) DEFAULT NULL,
  `menu_url` varchar(255) DEFAULT NULL,
  `menu_level` int(11) DEFAULT 1,
  `is_menu_item` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_code`, `permission_name`, `module_name`, `parent_permission_id`, `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `created_at`) VALUES
(1, 'VIEW_DASHBOARD', 'Dashboard', 'DASHBOARD', NULL, 1, 'fas fa-chart-pie', 'admin/dashboard', 1, 1, '2026-05-11 04:34:38'),
(2, 'VIEW_USERS', 'Users', 'USERS', NULL, 2, 'fas fa-users', 'admin/users', 1, 1, '2026-05-11 04:34:38'),
(3, 'CREATE_USERS', 'Create Users', 'USERS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(4, 'UPDATE_USERS', 'Update Users', 'USERS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(5, 'VIEW_BRANCHES', 'Branches', 'BRANCHES', NULL, 3, 'fas fa-building', 'admin/branches', 1, 1, '2026-05-11 04:34:38'),
(6, 'CREATE_BRANCHES', 'Create Branches', 'BRANCHES', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(7, 'UPDATE_BRANCHES', 'Update Branches', 'BRANCHES', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(8, 'VIEW_PROVIDERS', 'Providers', 'PROVIDERS', NULL, 4, 'fas fa-wallet', 'admin/providers', 1, 1, '2026-05-11 04:34:38'),
(9, 'CREATE_PROVIDERS', 'Create Providers', 'PROVIDERS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(10, 'UPDATE_PROVIDERS', 'Update Providers', 'PROVIDERS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(11, 'VIEW_WALLETS', 'Wallets', 'WALLETS', NULL, 5, 'fas fa-wallet', 'admin/wallets', 1, 1, '2026-05-11 04:34:38'),
(12, 'TOPUP_WALLETS', 'Topup Wallets', 'WALLETS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(13, 'VIEW_WALLET_TRANSACTIONS', 'View Wallet Transactions', 'WALLETS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(14, 'VIEW_TICKETS', 'Tickets', 'TICKETS', NULL, 6, 'fas fa-ticket-alt', 'admin/tickets', 1, 1, '2026-05-11 04:34:38'),
(15, 'CREATE_TICKETS', 'Create Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(16, 'CANCEL_TICKETS', 'Cancel Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(17, 'REFUND_TICKETS', 'Refund Tickets', 'TICKETS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(18, 'VIEW_REPORTS', 'Reports', 'REPORTS', NULL, 7, 'fas fa-chart-bar', 'admin/reports', 1, 1, '2026-05-11 04:34:38'),
(19, 'VIEW_SETTINGS', 'Settings', 'SETTINGS', NULL, 8, 'fas fa-cog', 'admin/settings', 1, 1, '2026-05-11 04:34:38'),
(20, 'UPDATE_SETTINGS', 'Update Settings', 'SETTINGS', NULL, 0, NULL, NULL, 2, 0, '2026-05-11 04:34:38'),
(21, 'ENABLE_MAINTENANCE', 'Maintenance', 'MAINTENANCE', NULL, 9, 'fas fa-tools', 'admin/maintenance', 1, 1, '2026-05-11 04:34:38'),
(22, 'MANAGE_PERMISSIONS', 'Permissions', 'PERMISSIONS', NULL, 10, 'fas fa-shield-alt', 'admin/settings/permissions', 1, 1, '2026-05-11 04:34:38'),
(23, 'CRM', 'crm', 'CRM', 1, 0, NULL, 'admin/dashboard/crm', 2, 1, '2026-05-11 06:14:16'),
(24, 'E_COMMERCE', 'E-commerce', 'E-COMMERCE', 23, 0, NULL, 'admin/dashboard/e-commerce', 3, 1, '2026-05-11 06:43:32'),
(25, 'MANAGE_USERS', 'USER MANAGEMENT', 'SETTINGS', 19, 2, 'fas fa-user-cog', 'admin/settings/users', 2, 1, '2026-05-11 07:42:32'),
(26, 'CREATE_USER', 'CREATE USER', 'SETTINGS', 25, 0, NULL, NULL, 3, 0, '2026-05-11 07:42:32'),
(27, 'UPDATE_USER', 'UPDATE USER', 'SETTINGS', 25, 0, NULL, NULL, 3, 0, '2026-05-11 07:42:32'),
(28, 'DELETE_USER', 'DELETE USER', 'SETTINGS', 25, 0, NULL, NULL, 3, 0, '2026-05-11 07:42:32');

-- --------------------------------------------------------

--
-- Table structure for table `position`
--

CREATE TABLE `position` (
  `pos_id` int(11) NOT NULL,
  `position_name` varchar(30) NOT NULL,
  `pos_code` varchar(30) NOT NULL,
  `pos_addedby` int(11) NOT NULL,
  `pos_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

--
-- Dumping data for table `position`
--

INSERT INTO `position` (`pos_id`, `position_name`, `pos_code`, `pos_addedby`, `pos_dateadded`) VALUES
(1, 'Cashier', 'CSHR', 1, '2024-12-17 08:47:41'),
(2, 'Manager', 'MGR', 1, '2024-12-12 09:05:07'),
(3, 'Programmer', 'PRG', 1, '2024-12-12 09:05:07'),
(4, 'Driver', '', 2, '2024-12-17 06:25:51'),
(5, 'Ticketing Agent', '', 2, '2024-12-17 06:27:05'),
(6, 'Operator', '', 2, '2024-12-17 06:27:23'),
(7, 'Truckman', '', 2, '2024-12-17 06:27:33'),
(8, 'Production Staff', '', 2, '2024-12-17 06:27:42'),
(9, 'Accounting Staff', '', 2, '2024-12-17 06:28:27'),
(10, 'Accounting Head', '', 2, '2024-12-17 06:28:35'),
(11, 'Computer Engineer', '', 2, '2025-01-03 00:21:00'),
(12, 'Brgy. Department Head', '', 2, '2025-03-19 02:40:16'),
(13, 'Accounting Staff/ Team Leader', '', 2, '2025-03-19 02:40:47'),
(14, 'Liaison', '', 2, '2025-03-19 02:41:59'),
(15, 'Sales Department Head', '', 2, '2025-03-19 02:48:38'),
(16, 'Overall Checker', '', 2, '2025-03-19 02:49:12'),
(17, 'Human Resource', '', 2, '2025-03-19 07:06:02'),
(18, 'Purchaser', '', 2, '2025-03-19 07:07:30'),
(19, 'Office Staff / Team Leader', '', 2, '2025-03-19 07:08:27'),
(20, 'Office Staff', '', 2, '2025-03-19 07:09:37'),
(21, 'CEO', '', 2, '2025-03-24 02:48:57'),
(22, 'SALES CHECKER', '', 9, '2025-03-25 06:03:01'),
(23, 'Sales Clerk', '', 9, '2025-03-25 06:37:45'),
(24, 'Warehouse In Charge', '', 9, '2025-03-25 06:43:03'),
(25, 'Graphic Artist', '', 9, '2025-09-02 01:38:40'),
(26, 'Quality Control In Charge', '', 9, '2025-09-02 02:06:21'),
(27, 'Messenger Staff', '', 9, '2025-09-02 02:09:43'),
(28, 'CULVERT IN CHARGE', '', 9, '2025-09-02 02:24:18'),
(29, 'Sewer', '', 9, '2025-10-06 07:20:57'),
(30, 'Sales In Charge', '', 9, '2025-09-02 03:46:49'),
(31, 'Production In Charge', '', 9, '2025-09-02 03:56:07'),
(32, 'Welder', '', 9, '2025-09-02 04:05:27'),
(33, 'Machine Operator', '', 9, '2025-09-02 04:08:27'),
(34, 'CHIEF OPERATOR', '', 9, '2025-09-02 05:18:22'),
(35, 'Department Manager', '', 9, '2025-09-02 05:56:42'),
(36, 'Warehouseman', '', 9, '2025-09-02 07:11:04'),
(37, 'Backhoe Operator', '', 9, '2025-09-02 07:23:08'),
(38, 'Logistic Staff', '', 9, '2025-09-02 07:30:40'),
(39, 'Assistant Mechanic', '', 9, '2025-09-02 07:37:45'),
(40, 'Finance Staff', '', 9, '2025-09-13 02:51:26'),
(41, 'Department Head', '', 9, '2025-09-22 06:37:04'),
(42, 'SITE ENGINEER', '', 9, '2025-09-22 06:51:41'),
(43, 'Tool Keeper', '', 9, '2025-09-22 07:19:09'),
(44, 'Electrician', '', 9, '2025-09-22 07:49:06'),
(45, 'MECHANIC', '', 9, '2025-09-22 07:52:20'),
(46, 'GRADER OPERATOR', '', 9, '2025-10-13 08:32:17'),
(47, 'Head Programmer', '', 2, '2025-12-16 06:43:31'),
(48, 'xxxxx', '', 20, '2026-04-28 03:57:49');

-- --------------------------------------------------------

--
-- Table structure for table `provider_service_fees`
--

CREATE TABLE `provider_service_fees` (
  `fee_id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `fee_type` enum('FIXED','PERCENT') DEFAULT 'FIXED',
  `fee_value` decimal(12,2) DEFAULT NULL,
  `effective_start_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `provider_service_fees`
--

INSERT INTO `provider_service_fees` (`fee_id`, `provider_id`, `branch_id`, `fee_type`, `fee_value`, `effective_start_date`, `effective_end_date`, `is_active`, `created_by`, `created_at`) VALUES
(1, 3, NULL, 'FIXED', 100.00, NULL, NULL, 1, NULL, '2026-05-09 01:29:12'),
(2, 2, NULL, 'FIXED', 100.00, NULL, NULL, 1, NULL, '2026-05-09 01:29:12'),
(3, 1, NULL, 'FIXED', 100.00, NULL, NULL, 1, NULL, '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `provider_wallets`
--

CREATE TABLE `provider_wallets` (
  `wallet_id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `branch_id` bigint(20) NOT NULL,
  `current_balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `provider_wallets`
--

INSERT INTO `provider_wallets` (`wallet_id`, `provider_id`, `branch_id`, `current_balance`, `status`, `updated_at`) VALUES
(1, 3, 1, 0.00, 'active', '2026-05-09 01:29:12'),
(2, 2, 1, 0.00, 'active', '2026-05-09 01:29:12'),
(3, 1, 1, 0.00, 'active', '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `psgc_barangays`
--

CREATE TABLE `psgc_barangays` (
  `barangay_id` bigint(20) UNSIGNED NOT NULL,
  `barangay_code` varchar(12) NOT NULL,
  `city_municipality_code` varchar(12) NOT NULL,
  `barangay_name` varchar(255) NOT NULL,
  `brgy_tin` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `psgc_barangays`
--

-- --------------------------------------------------------

--
-- Table structure for table `psgc_cities_municipalities`
--

CREATE TABLE `psgc_cities_municipalities` (
  `city_municipality_id` bigint(20) UNSIGNED NOT NULL,
  `city_municipality_code` varchar(12) NOT NULL,
  `province_code` varchar(12) NOT NULL,
  `city_municipality_name` varchar(255) NOT NULL,
  `muni_zipcode` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `psgc_cities_municipalities`
--

-- --------------------------------------------------------

--
-- Table structure for table `psgc_provinces`
--

CREATE TABLE `psgc_provinces` (
  `province_id` bigint(20) UNSIGNED NOT NULL,
  `province_code` varchar(12) NOT NULL,
  `region_code` varchar(12) NOT NULL,
  `province_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `psgc_provinces`
--

-- --------------------------------------------------------

--
-- Table structure for table `psgc_regions`
--

CREATE TABLE `psgc_regions` (
  `region_id` bigint(20) UNSIGNED NOT NULL,
  `region_code` varchar(12) NOT NULL,
  `region_name` varchar(255) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `psgc_regions`
--

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_permission_id` bigint(20) NOT NULL,
  `role_id` bigint(20) NOT NULL,
  `permission_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`) VALUES
(14, 1, 1),
(19, 1, 2),
(5, 1, 3),
(12, 1, 4),
(13, 1, 5),
(2, 1, 6),
(9, 1, 7),
(15, 1, 8),
(3, 1, 9),
(10, 1, 10),
(20, 1, 11),
(8, 1, 12),
(21, 1, 13),
(18, 1, 14),
(4, 1, 15),
(1, 1, 16),
(7, 1, 17),
(16, 1, 18),
(17, 1, 19),
(11, 1, 20),
(6, 1, 21),
(75, 1, 25),
(73, 1, 26),
(76, 1, 27),
(74, 1, 28),
(33, 2, 1),
(32, 2, 5),
(34, 2, 8),
(37, 2, 11),
(38, 2, 13),
(36, 2, 14),
(35, 2, 18),
(65, 2, 22),
(43, 3, 1),
(47, 3, 2),
(42, 3, 5),
(44, 3, 8),
(48, 3, 11),
(49, 3, 13),
(46, 3, 14),
(40, 3, 15),
(39, 3, 16),
(41, 3, 17),
(45, 3, 18),
(81, 3, 25),
(55, 4, 1),
(56, 4, 8),
(58, 4, 11),
(57, 4, 14),
(54, 4, 15),
(61, 5, 1),
(66, 5, 6),
(71, 5, 7),
(64, 5, 13),
(63, 5, 14),
(62, 5, 18);

-- --------------------------------------------------------

--
-- Table structure for table `sub_department`
--

CREATE TABLE `sub_department` (
  `sub_depart_id` int(11) NOT NULL,
  `sub_department_name` varchar(150) NOT NULL,
  `main_department_id` int(11) NOT NULL,
  `sub_depart_addedby` int(11) NOT NULL,
  `sub_depart_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sub_department`
--

INSERT INTO `sub_department` (`sub_depart_id`, `sub_department_name`, `main_department_id`, `sub_depart_addedby`, `sub_depart_dateadded`) VALUES
(1, 'Sales', 1, 2, '2025-12-16 01:07:37'),
(2, 'Brgy. Affairs', 1, 2, '2025-11-15 02:31:40'),
(3, 'DEPED', 1, 2, '2025-03-08 05:26:43'),
(4, 'Logistics', 1, 2, '2025-03-08 05:26:51'),
(5, 'Motorpool', 1, 2, '2025-03-08 05:27:00'),
(6, 'Quarry', 1, 2, '2025-03-08 05:27:08'),
(7, 'Accounting', 1, 2, '2025-03-19 01:56:23'),
(9, 'IT Department', 1, 2, '2025-03-19 03:26:53'),
(10, 'Production', 2, 2, '2025-03-20 03:20:27'),
(11, 'Tailoring', 2, 2, '2025-03-20 03:20:35'),
(12, 'Advertising/Store', 2, 2, '2025-03-20 03:20:49'),
(13, 'Finance', 1, 2, '2025-08-28 05:41:47'),
(14, 'CHB', 1, 2, '2025-08-28 05:41:56'),
(15, 'AYAG', 1, 3, '2025-08-28 06:27:32'),
(16, 'CULVERT', 1, 3, '2025-08-28 06:27:50'),
(17, 'Manager Office', 1, 2, '2025-08-30 07:41:13');

-- --------------------------------------------------------

--
-- Table structure for table `system_devices`
--

CREATE TABLE `system_devices` (
  `device_id` bigint(20) NOT NULL,
  `device_code` varchar(255) DEFAULT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `device_type` enum('desktop','laptop','tablet','mobile','other') DEFAULT 'desktop',
  `branch_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `device_remark` text DEFAULT NULL,
  `status` enum('pending','approved','blocked') DEFAULT 'pending',
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `system_devices`
--

INSERT INTO `system_devices` (`device_id`, `device_code`, `device_name`, `device_type`, `branch_id`, `ip_address`, `location_name`, `device_remark`, `status`, `approved_by`, `approved_at`, `last_used_at`, `created_at`) VALUES
(1, 'MAIN-POS-001', 'Main POS Terminal', 'desktop', NULL, NULL, 'Main Branch Front Desk', 'Primary cashier terminal', 'approved', NULL, NULL, NULL, '2026-05-09 01:29:12'),
(2, 'DEV-C8A4244B', 'Chrome Browser', 'desktop', NULL, '192.168.1.46', 'Auto-detected', 'Auto-created during login', 'approved', NULL, NULL, '2026-05-12 07:36:05', '2026-05-11 01:39:46'),
(3, 'DEV-565DB068', 'Safari Browser', 'mobile', NULL, '192.168.1.46', 'Auto-detected', 'Auto-created during login', 'approved', NULL, NULL, '2026-05-12 05:32:55', '2026-05-11 05:36:40');

-- --------------------------------------------------------

--
-- Table structure for table `system_maintenance_logs`
--

CREATE TABLE `system_maintenance_logs` (
  `log_id` bigint(20) NOT NULL,
  `mode` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `started_by` bigint(20) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` bigint(20) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_abbreviation` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_contact_number` varchar(100) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_tagline` varchar(255) DEFAULT NULL,
  `receipt_footer` text DEFAULT NULL,
  `report_footer` text DEFAULT NULL,
  `system_timezone` varchar(100) DEFAULT 'Asia/Manila',
  `system_currency` varchar(20) DEFAULT 'PHP',
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `maintenance_message` text DEFAULT NULL,
  `maintenance_start` timestamp NULL DEFAULT NULL,
  `maintenance_end` timestamp NULL DEFAULT NULL,
  `allow_admin_during_maintenance` tinyint(1) DEFAULT 1,
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `company_name`, `company_abbreviation`, `company_address`, `company_contact_number`, `company_email`, `company_tagline`, `receipt_footer`, `report_footer`, `system_timezone`, `system_currency`, `maintenance_mode`, `maintenance_message`, `maintenance_start`, `maintenance_end`, `allow_admin_during_maintenance`, `updated_by`, `updated_at`) VALUES
(1, 'Sample Ticketing Services Inc.', 'STSI', '123 Main Street, Philippines', '09171234567', 'support@example.com', 'Fast, Reliable & Secure Ticketing', 'Thank you for choosing our services.', 'System Generated Report', 'Asia/Manila', 'PHP', 0, 'System is under maintenance.', NULL, NULL, 1, NULL, '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_adjustments`
--

CREATE TABLE `ticket_adjustments` (
  `adjustment_id` bigint(20) NOT NULL,
  `transaction_id` bigint(20) NOT NULL,
  `type` enum('CANCEL','REFUND','CORRECTION') DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `charged_to` enum('customer','cashier','branch','company') DEFAULT 'customer',
  `approval_status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `approved_by` bigint(20) DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_providers`
--

CREATE TABLE `ticket_providers` (
  `provider_id` bigint(20) NOT NULL,
  `provider_code` varchar(50) DEFAULT NULL,
  `provider_name` varchar(150) DEFAULT NULL,
  `provider_type` enum('airline','shipping','bus','other') DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `ticket_providers`
--

INSERT INTO `ticket_providers` (`provider_id`, `provider_code`, `provider_name`, `provider_type`, `status`, `created_at`) VALUES
(1, 'PAL', 'Philippine Airlines', 'airline', 'active', '2026-05-09 01:29:12'),
(2, 'CEBPAC', 'Cebu Pacific', 'airline', 'active', '2026-05-09 01:29:12'),
(3, '2GO', '2GO Travel', 'shipping', 'active', '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_transactions`
--

CREATE TABLE `ticket_transactions` (
  `transaction_id` bigint(20) NOT NULL,
  `transaction_code` varchar(50) DEFAULT NULL,
  `wallet_id` bigint(20) NOT NULL,
  `passenger_id` bigint(20) NOT NULL,
  `accommodation_id` bigint(20) DEFAULT NULL,
  `discount_id` bigint(20) DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `travel_date` date DEFAULT NULL,
  `base_amount` decimal(12,2) DEFAULT NULL,
  `service_fee` decimal(12,2) DEFAULT NULL,
  `discount_amount` decimal(12,2) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('booked','cancelled','refunded') DEFAULT 'booked',
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `user_id` bigint(20) NOT NULL,
  `user_code` varchar(50) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `role_id` bigint(20) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `is_time_restricted` tinyint(1) DEFAULT 0,
  `allowed_login_start` time DEFAULT NULL,
  `allowed_login_end` time DEFAULT NULL,
  `allowed_days` varchar(100) DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `require_password_change` tinyint(1) DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_accounts`
--

INSERT INTO `user_accounts` (`user_id`, `user_code`, `branch_id`, `role_id`, `username`, `email`, `password_hash`, `profile_image`, `emp_id`, `status`, `failed_login_attempts`, `locked_until`, `is_time_restricted`, `allowed_login_start`, `allowed_login_end`, `allowed_days`, `password_changed_at`, `require_password_change`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'USR-0001', NULL, 1, 'admin', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$TGN4Lkh6aU1iSWF1QkpMYw$M/fX4dmIYbb2Y9254gcEQDUwUjKV6qJLqZ1z4E5L1b8', '/api/images/users/USR-0001_1778564557.png', 20, 'active', 0, NULL, 0, NULL, NULL, NULL, NULL, 1, '2026-05-12 07:36:05', '2026-05-09 01:29:12', '2026-05-12 05:42:37', NULL),
(3, 'USR-0002', 1, 5, 'admin1', 'catzanthonz@gmail.com', '$2y$12$NNwSEkAZ/2hD4iafBnRdR.zKpp5E8oWuMqnBeWwWGiwjT1JAi0yaa', NULL, 2, 'active', 0, NULL, 1, '08:30:00', '17:30:00', 'Monday,Wednesday,Friday', NULL, 0, NULL, '2026-05-11 09:17:56', '2026-05-11 09:17:56', NULL),
(4, 'USR-0003', 1, 3, 'admin2', 'catzanthonzx@gmail.com', '$2y$12$i5xNthRnXafMz0ajCBYrUeq7duKrkXIDEXTcMUBkii8yEzTFdvcrW', '/api/images/users/USR-0003_1778562170.png', 180, 'active', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, '2026-05-11 09:29:32', '2026-05-12 05:02:50', NULL),
(5, 'USR-0004', NULL, 5, 'azzy123', 'ozzy@gmail.com', '$2y$12$Im/04tRAwF9Rz6n/6CvHuuegc/yW.EAnf3TNlN2ElYtmqUX1k2ZEe', '/api/images/users/USR-0004_1778562277.png', 25, 'active', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, '2026-05-12 05:04:37', '2026-05-12 05:04:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` bigint(20) NOT NULL,
  `role_code` varchar(50) DEFAULT NULL,
  `role_name` varchar(100) DEFAULT NULL,
  `role_description` text DEFAULT NULL,
  `default_dashboard` varchar(255) DEFAULT '/admin/dashboard/analytics',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_code`, `role_name`, `role_description`, `default_dashboard`, `created_at`) VALUES
(1, 'SUPER_ADMIN', 'Super Administrator', 'Full system access', '/admin/dashboard/crm', '2026-05-09 01:29:12'),
(2, 'CEO', 'Chief Executive Officer', 'Executive management access', '/admin/dashboard/analytics', '2026-05-09 01:29:12'),
(3, 'MANAGER', 'Branch Manager', 'Branch management access', '/admin/dashboard/analytics', '2026-05-09 01:29:12'),
(4, 'CASHIER', 'Cashier', 'Ticketing and wallet transaction access', '/admin/dashboard/crm', '2026-05-09 01:29:12'),
(5, 'AUDITOR', 'Auditor', 'Audit and reporting access', '/admin/dashboard/analytics', '2026-05-09 01:29:12');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `device_id` bigint(20) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_sessions`
--

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `wallet_txn_id` bigint(20) NOT NULL,
  `wallet_id` bigint(20) NOT NULL,
  `txn_code` varchar(100) DEFAULT NULL,
  `txn_type` enum('TOPUP','SALE','REFUND','ADJUSTMENT') DEFAULT NULL,
  `direction` enum('IN','OUT') DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `balance_before` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accommodation_types`
--
ALTER TABLE `accommodation_types`
  ADD PRIMARY KEY (`accommodation_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `business_branches`
--
ALTER TABLE `business_branches`
  ADD PRIMARY KEY (`branch_id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`),
  ADD UNIQUE KEY `contact_number` (`contact_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_branch_address` (`region_code`,`province_code`,`city_municipality_code`,`barangay_code`,`street_address`),
  ADD KEY `idx_branch_name` (`branch_name`),
  ADD KEY `idx_region_code` (`region_code`),
  ADD KEY `idx_province_code` (`province_code`),
  ADD KEY `idx_city_municipality_code` (`city_municipality_code`),
  ADD KEY `idx_barangay_code` (`barangay_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`dept_id`),
  ADD KEY `dept_addedby` (`dept_addedby`);

--
-- Indexes for table `discount_types`
--
ALTER TABLE `discount_types`
  ADD PRIMARY KEY (`discount_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD KEY `job_title` (`job_title`,`b_department_id`,`b_company_id`,`b_employment_status_id`),
  ADD KEY `b_employment_status_id` (`b_employment_status_id`),
  ADD KEY `b_company_id` (`b_company_id`),
  ADD KEY `b_department_id` (`b_department_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_emp_province_code` (`emp_province_code`),
  ADD KEY `idx_emp_city_code` (`emp_city_code`),
  ADD KEY `idx_emp_barangay_code` (`emp_barangay_code`);

--
-- Indexes for table `employment_status`
--
ALTER TABLE `employment_status`
  ADD PRIMARY KEY (`emp_stat_id`),
  ADD KEY `employment_status_ibfk_1` (`emp_stat_addedby`);

--
-- Indexes for table `passenger_accounts`
--
ALTER TABLE `passenger_accounts`
  ADD PRIMARY KEY (`passenger_id`),
  ADD UNIQUE KEY `mobile_number` (`mobile_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `region_code` (`region_code`),
  ADD KEY `province_code` (`province_code`),
  ADD KEY `city_municipality_code` (`city_municipality_code`),
  ADD KEY `barangay_code` (`barangay_code`),
  ADD KEY `idx_fullname` (`fullname`),
  ADD KEY `idx_mobile_number` (`mobile_number`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_code` (`permission_code`),
  ADD KEY `idx_parent_permission_id` (`parent_permission_id`),
  ADD KEY `idx_menu_order` (`menu_order`),
  ADD KEY `idx_module_name` (`module_name`);

--
-- Indexes for table `position`
--
ALTER TABLE `position`
  ADD PRIMARY KEY (`pos_id`),
  ADD KEY `pos_addedby` (`pos_addedby`);

--
-- Indexes for table `provider_service_fees`
--
ALTER TABLE `provider_service_fees`
  ADD PRIMARY KEY (`fee_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `provider_wallets`
--
ALTER TABLE `provider_wallets`
  ADD PRIMARY KEY (`wallet_id`),
  ADD UNIQUE KEY `uq_wallet` (`provider_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `psgc_barangays`
--
ALTER TABLE `psgc_barangays`
  ADD PRIMARY KEY (`barangay_code`),
  ADD UNIQUE KEY `uq_psgc_barangays_barangay_id` (`barangay_id`),
  ADD KEY `idx_barangay_city` (`city_municipality_code`),
  ADD KEY `idx_barangay_name` (`barangay_name`);

--
-- Indexes for table `psgc_cities_municipalities`
--
ALTER TABLE `psgc_cities_municipalities`
  ADD PRIMARY KEY (`city_municipality_code`),
  ADD UNIQUE KEY `uq_psgc_cities_city_municipality_id` (`city_municipality_id`),
  ADD KEY `idx_city_province` (`province_code`),
  ADD KEY `idx_city_name` (`city_municipality_name`);

--
-- Indexes for table `psgc_provinces`
--
ALTER TABLE `psgc_provinces`
  ADD PRIMARY KEY (`province_code`),
  ADD UNIQUE KEY `uq_psgc_provinces_province_id` (`province_id`),
  ADD KEY `idx_province_region` (`region_code`),
  ADD KEY `idx_province_name` (`province_name`);

--
-- Indexes for table `psgc_regions`
--
ALTER TABLE `psgc_regions`
  ADD PRIMARY KEY (`region_code`),
  ADD UNIQUE KEY `uq_psgc_regions_region_id` (`region_id`),
  ADD KEY `idx_region_name` (`region_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_permission_id`),
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `sub_department`
--
ALTER TABLE `sub_department`
  ADD PRIMARY KEY (`sub_depart_id`),
  ADD KEY `opexp_depart_addedby` (`sub_depart_addedby`);

--
-- Indexes for table `system_devices`
--
ALTER TABLE `system_devices`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `device_code` (`device_code`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `system_maintenance_logs`
--
ALTER TABLE `system_maintenance_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `started_by` (`started_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `ticket_adjustments`
--
ALTER TABLE `ticket_adjustments`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ticket_providers`
--
ALTER TABLE `ticket_providers`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `provider_code` (`provider_code`);

--
-- Indexes for table `ticket_transactions`
--
ALTER TABLE `ticket_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `wallet_id` (`wallet_id`),
  ADD KEY `passenger_id` (`passenger_id`),
  ADD KEY `accommodation_id` (`accommodation_id`),
  ADD KEY `discount_id` (`discount_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_emp_id` (`emp_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_code` (`role_code`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_user_sessions_user_id` (`user_id`),
  ADD KEY `idx_user_sessions_token` (`session_token`),
  ADD KEY `idx_user_sessions_active` (`is_active`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`wallet_txn_id`),
  ADD UNIQUE KEY `txn_code` (`txn_code`),
  ADD KEY `wallet_id` (`wallet_id`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accommodation_types`
--
ALTER TABLE `accommodation_types`
  MODIFY `accommodation_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `business_branches`
--
ALTER TABLE `business_branches`
  MODIFY `branch_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `discount_types`
--
ALTER TABLE `discount_types`
  MODIFY `discount_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT for table `employment_status`
--
ALTER TABLE `employment_status`
  MODIFY `emp_stat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `passenger_accounts`
--
ALTER TABLE `passenger_accounts`
  MODIFY `passenger_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `position`
--
ALTER TABLE `position`
  MODIFY `pos_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `provider_service_fees`
--
ALTER TABLE `provider_service_fees`
  MODIFY `fee_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `provider_wallets`
--
ALTER TABLE `provider_wallets`
  MODIFY `wallet_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `psgc_barangays`
--
ALTER TABLE `psgc_barangays`
  MODIFY `barangay_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53962;

--
-- AUTO_INCREMENT for table `psgc_cities_municipalities`
--
ALTER TABLE `psgc_cities_municipalities`
  MODIFY `city_municipality_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2096;

--
-- AUTO_INCREMENT for table `psgc_provinces`
--
ALTER TABLE `psgc_provinces`
  MODIFY `province_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `psgc_regions`
--
ALTER TABLE `psgc_regions`
  MODIFY `region_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=375;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `role_permission_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `sub_department`
--
ALTER TABLE `sub_department`
  MODIFY `sub_depart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `system_devices`
--
ALTER TABLE `system_devices`
  MODIFY `device_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_maintenance_logs`
--
ALTER TABLE `system_maintenance_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ticket_adjustments`
--
ALTER TABLE `ticket_adjustments`
  MODIFY `adjustment_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_providers`
--
ALTER TABLE `ticket_providers`
  MODIFY `provider_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ticket_transactions`
--
ALTER TABLE `ticket_transactions`
  MODIFY `transaction_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `session_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `wallet_txn_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_accounts` (`user_id`),
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `system_devices` (`device_id`);

--
-- Constraints for table `business_branches`
--
ALTER TABLE `business_branches`
  ADD CONSTRAINT `business_branches_ibfk_1` FOREIGN KEY (`region_code`) REFERENCES `psgc_regions` (`region_code`),
  ADD CONSTRAINT `business_branches_ibfk_2` FOREIGN KEY (`province_code`) REFERENCES `psgc_provinces` (`province_code`),
  ADD CONSTRAINT `business_branches_ibfk_3` FOREIGN KEY (`city_municipality_code`) REFERENCES `psgc_cities_municipalities` (`city_municipality_code`),
  ADD CONSTRAINT `business_branches_ibfk_4` FOREIGN KEY (`barangay_code`) REFERENCES `psgc_barangays` (`barangay_code`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `passenger_accounts`
--
ALTER TABLE `passenger_accounts`
  ADD CONSTRAINT `passenger_accounts_ibfk_1` FOREIGN KEY (`region_code`) REFERENCES `psgc_regions` (`region_code`),
  ADD CONSTRAINT `passenger_accounts_ibfk_2` FOREIGN KEY (`province_code`) REFERENCES `psgc_provinces` (`province_code`),
  ADD CONSTRAINT `passenger_accounts_ibfk_3` FOREIGN KEY (`city_municipality_code`) REFERENCES `psgc_cities_municipalities` (`city_municipality_code`),
  ADD CONSTRAINT `passenger_accounts_ibfk_4` FOREIGN KEY (`barangay_code`) REFERENCES `psgc_barangays` (`barangay_code`);

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`parent_permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE SET NULL;

--
-- Constraints for table `provider_service_fees`
--
ALTER TABLE `provider_service_fees`
  ADD CONSTRAINT `provider_service_fees_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`),
  ADD CONSTRAINT `provider_service_fees_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`),
  ADD CONSTRAINT `provider_service_fees_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `provider_wallets`
--
ALTER TABLE `provider_wallets`
  ADD CONSTRAINT `provider_wallets_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`),
  ADD CONSTRAINT `provider_wallets_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`);

--
-- Constraints for table `psgc_barangays`
--
ALTER TABLE `psgc_barangays`
  ADD CONSTRAINT `fk_psgc_barangay_city` FOREIGN KEY (`city_municipality_code`) REFERENCES `psgc_cities_municipalities` (`city_municipality_code`) ON UPDATE CASCADE;

--
-- Constraints for table `psgc_cities_municipalities`
--
ALTER TABLE `psgc_cities_municipalities`
  ADD CONSTRAINT `fk_psgc_city_province` FOREIGN KEY (`province_code`) REFERENCES `psgc_provinces` (`province_code`) ON UPDATE CASCADE;

--
-- Constraints for table `psgc_provinces`
--
ALTER TABLE `psgc_provinces`
  ADD CONSTRAINT `fk_psgc_province_region` FOREIGN KEY (`region_code`) REFERENCES `psgc_regions` (`region_code`) ON UPDATE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`);

--
-- Constraints for table `system_devices`
--
ALTER TABLE `system_devices`
  ADD CONSTRAINT `system_devices_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`),
  ADD CONSTRAINT `system_devices_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `system_maintenance_logs`
--
ALTER TABLE `system_maintenance_logs`
  ADD CONSTRAINT `system_maintenance_logs_ibfk_1` FOREIGN KEY (`started_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `ticket_adjustments`
--
ALTER TABLE `ticket_adjustments`
  ADD CONSTRAINT `ticket_adjustments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ticket_transactions` (`transaction_id`),
  ADD CONSTRAINT `ticket_adjustments_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `user_accounts` (`user_id`),
  ADD CONSTRAINT `ticket_adjustments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `ticket_transactions`
--
ALTER TABLE `ticket_transactions`
  ADD CONSTRAINT `ticket_transactions_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `provider_wallets` (`wallet_id`),
  ADD CONSTRAINT `ticket_transactions_ibfk_2` FOREIGN KEY (`passenger_id`) REFERENCES `passenger_accounts` (`passenger_id`),
  ADD CONSTRAINT `ticket_transactions_ibfk_3` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodation_types` (`accommodation_id`),
  ADD CONSTRAINT `ticket_transactions_ibfk_4` FOREIGN KEY (`discount_id`) REFERENCES `discount_types` (`discount_id`),
  ADD CONSTRAINT `ticket_transactions_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`);

--
-- Constraints for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD CONSTRAINT `fk_user_accounts_employee` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `user_accounts_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`),
  ADD CONSTRAINT `user_accounts_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_accounts` (`user_id`),
  ADD CONSTRAINT `user_sessions_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `system_devices` (`device_id`);

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `provider_wallets` (`wallet_id`),
  ADD CONSTRAINT `wallet_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
