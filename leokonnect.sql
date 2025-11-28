-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql212.byetcluster.com
-- Generation Time: Oct 30, 2025 at 09:56 AM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40158735_leokonnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `login_sessions`
--

INSERT INTO `login_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `status`, `login_time`, `logout_time`) VALUES
(1, 7, 'c7312a8832af843ec52dfcfeb2059156', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-18 19:00:51', '2025-10-18 19:08:40'),
(2, 7, 'c7312a8832af843ec52dfcfeb2059156', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-18 19:04:04', '2025-10-18 19:08:40'),
(3, 1, 'c7312a8832af843ec52dfcfeb2059156', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-18 19:06:18', '2025-10-18 19:08:40'),
(4, 1, 'be8b1aab17860d8bff0065b953a22aac', '105.163.1.33', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'logged_out', '2025-10-18 19:09:24', '2025-10-18 19:10:21'),
(5, 11, 'c16353bca29935816d642a351ebc64e5', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-19 12:38:36', '2025-10-20 10:39:58'),
(6, 1, 'c16353bca29935816d642a351ebc64e5', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-19 12:48:00', '2025-10-20 10:39:58'),
(7, 1, 'c16353bca29935816d642a351ebc64e5', '105.163.1.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-19 13:44:04', '2025-10-20 10:39:58'),
(8, 1, '66c0e3a5c186ab662bd7cec906d89b33', '105.163.1.33', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-19 21:10:53', NULL),
(9, 10, '11b39b1b2375a50266cf943c8f9a50aa', '105.163.1.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-21 14:50:20', NULL),
(10, 10, 'b7715df372ec31f8f3d17ef50f58cda9', '105.163.1.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-21 18:42:15', '2025-10-22 09:42:19'),
(11, 10, '370c4a7214a77ff57582cc8776ddb147', '105.163.1.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-21 18:45:28', '2025-10-21 18:45:47'),
(12, 1, 'b7715df372ec31f8f3d17ef50f58cda9', '105.163.1.112', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-21 18:46:37', '2025-10-22 09:42:19'),
(13, 10, '370c4a7214a77ff57582cc8776ddb147', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-22 10:27:19', NULL),
(14, 10, 'b7715df372ec31f8f3d17ef50f58cda9', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-22 11:07:37', NULL),
(15, 10, '8ccb0fd294bafc07590e9bf1cab40df7', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-22 18:53:42', '2025-10-23 14:25:52'),
(16, 10, '8f8066c8341d1df5c4e7d958420b22a4', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-23 10:16:36', NULL),
(17, 10, '8ccb0fd294bafc07590e9bf1cab40df7', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-23 14:27:08', NULL),
(18, 10, '7b690bfe528c163cc8489419c74c4ec6', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-24 16:09:21', '2025-10-24 19:28:03'),
(19, 10, '7b690bfe528c163cc8489419c74c4ec6', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-24 16:14:21', '2025-10-24 19:28:03'),
(20, 7, '7b690bfe528c163cc8489419c74c4ec6', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-24 16:36:23', '2025-10-24 19:28:03'),
(21, 1, 'b1d089aa3da30901dd6fee423868a5e3', '105.163.157.0', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-24 19:12:19', '2025-10-24 19:13:16'),
(22, 1, 'b1d089aa3da30901dd6fee423868a5e3', '105.163.157.0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'logged_out', '2025-10-24 19:12:46', '2025-10-24 19:13:16'),
(23, 7, '857432cad3d2cd1bb249866659135efa', '105.163.157.0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'logged_out', '2025-10-24 19:26:27', '2025-10-24 19:27:09'),
(24, 1, 'b1d089aa3da30901dd6fee423868a5e3', '105.163.157.0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'active', '2025-10-24 19:26:38', NULL),
(25, 1, '221ec40f94e7ad17f90149d972b3263b', '105.163.157.0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'logged_out', '2025-10-24 19:27:31', '2025-10-24 19:27:44'),
(26, 10, '7c5452cec4bf9738444bcc994937998e', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-25 08:44:23', '2025-10-25 09:03:54'),
(27, 1, '7c5452cec4bf9738444bcc994937998e', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-25 09:01:48', '2025-10-25 09:03:54'),
(28, 7, '7c5452cec4bf9738444bcc994937998e', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'logged_out', '2025-10-25 09:02:26', '2025-10-25 09:03:54'),
(29, 10, '7c5452cec4bf9738444bcc994937998e', '105.163.157.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-25 09:04:30', NULL),
(30, 10, 'b9a8d1690a7597b702b28ce4e1dc37ec', '105.163.157.176', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-27 11:45:22', NULL),
(31, 1, '7a84ff52d9ba902933c21671a7ab0c6f', '105.163.157.176', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 'logged_out', '2025-10-28 11:41:54', '2025-10-28 11:44:18'),
(32, 10, '7a84ff52d9ba902933c21671a7ab0c6f', '105.163.157.176', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 'active', '2025-10-28 11:44:42', NULL),
(33, 10, 'be90f5a7f5d8abfe3a1eeb0171c6a62f', '105.163.157.176', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'active', '2025-10-30 13:15:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `session_id`, `action`, `description`, `ip_address`, `type`, `message`, `created_at`) VALUES
(14, 10, NULL, 'Deactivate User', 'Deactivate User - Ramsey Rama (officialramseybookings@gmail.com)', '105.163.1.33', 'action', 'Deactivate User by user ID 10', '2025-10-18 16:11:17'),
(13, 10, NULL, 'Activate User', 'Activate User - Angel Pahe (angel.pahe003@gmail.com)', '105.163.1.33', 'action', 'Activate User by user ID 10', '2025-10-18 16:11:12'),
(12, 10, NULL, 'Delete Plan', 'Deleted plan \'3 Hours\' (ID #11)', '105.163.1.33', 'action', 'Delete Plan by user ID 10', '2025-10-18 16:11:05'),
(15, 7, 'c7312a8832af843ec52dfcfeb2059156', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:00:51'),
(16, 7, 'c7312a8832af843ec52dfcfeb2059156', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:02:20'),
(17, 7, 'c7312a8832af843ec52dfcfeb2059156', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:04:04'),
(18, 7, 'c7312a8832af843ec52dfcfeb2059156', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:04:06'),
(19, 1, 'c7312a8832af843ec52dfcfeb2059156', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:06:18'),
(20, 1, 'c7312a8832af843ec52dfcfeb2059156', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:08:03'),
(21, 10, 'c7312a8832af843ec52dfcfeb2059156', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 19:08:40'),
(22, 1, 'be8b1aab17860d8bff0065b953a22aac', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-18 19:09:18'),
(23, 1, 'be8b1aab17860d8bff0065b953a22aac', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-18 19:09:24'),
(24, 1, 'be8b1aab17860d8bff0065b953a22aac', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-18 19:10:21'),
(25, 11, 'c16353bca29935816d642a351ebc64e5', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 12:38:36'),
(26, 11, 'c16353bca29935816d642a351ebc64e5', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 12:40:16'),
(27, 1, 'c16353bca29935816d642a351ebc64e5', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 12:48:00'),
(28, 1, 'c16353bca29935816d642a351ebc64e5', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 12:51:52'),
(29, 10, 'c16353bca29935816d642a351ebc64e5', 'Create Plan', 'Added plan \'3 Hours\' costing Ksh 20', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 13:21:02'),
(30, 10, 'c16353bca29935816d642a351ebc64e5', 'Delete Plan', 'Deleted plan \'3 Hours\' (ID #12)', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 13:23:13'),
(31, 10, 'c16353bca29935816d642a351ebc64e5', 'Activate User', 'Activate User - Ramsey Rama (officialramseybookings@gmail.com)', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 13:26:15'),
(32, 1, 'c16353bca29935816d642a351ebc64e5', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 13:44:04'),
(33, 10, '66c0e3a5c186ab662bd7cec906d89b33', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 21:10:30'),
(34, 1, '66c0e3a5c186ab662bd7cec906d89b33', 'Login', 'User logged in successfully', '105.163.1.33', 'success', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-19 21:10:53'),
(35, 10, 'c16353bca29935816d642a351ebc64e5', 'Logout', 'User logged out', '105.163.1.33', 'info', 'IP: 105.163.1.33 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 10:39:58'),
(36, 10, '11b39b1b2375a50266cf943c8f9a50aa', 'Login', 'User logged in successfully', '105.163.1.112', 'success', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 14:50:20'),
(37, 10, 'b7715df372ec31f8f3d17ef50f58cda9', 'Login', 'User logged in successfully', '105.163.1.112', 'success', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 18:42:15'),
(38, 10, '370c4a7214a77ff57582cc8776ddb147', 'Login', 'User logged in successfully', '105.163.1.112', 'success', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 18:45:28'),
(39, 10, '370c4a7214a77ff57582cc8776ddb147', 'Logout', 'User logged out', '105.163.1.112', 'info', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 18:45:47'),
(40, 10, 'b7715df372ec31f8f3d17ef50f58cda9', 'Logout', 'User logged out', '105.163.1.112', 'info', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 18:45:58'),
(41, 1, 'b7715df372ec31f8f3d17ef50f58cda9', 'Login', 'User logged in successfully', '105.163.1.112', 'success', 'IP: 105.163.1.112 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 18:46:37'),
(42, 1, 'b7715df372ec31f8f3d17ef50f58cda9', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 09:42:19'),
(43, 10, '370c4a7214a77ff57582cc8776ddb147', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 10:27:19'),
(44, 10, 'b7715df372ec31f8f3d17ef50f58cda9', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 11:07:37'),
(45, 10, '8ccb0fd294bafc07590e9bf1cab40df7', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 18:53:42'),
(46, 10, '8f8066c8341d1df5c4e7d958420b22a4', 'Deactivate User', 'Deactivate User - Ramsey Rama (officialramseybookings@gmail.com)', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 21:46:14'),
(47, 10, '8f8066c8341d1df5c4e7d958420b22a4', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 21:49:19'),
(48, 10, '8f8066c8341d1df5c4e7d958420b22a4', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-23 10:16:36'),
(49, 10, '8ccb0fd294bafc07590e9bf1cab40df7', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-23 14:25:52'),
(50, 10, '8ccb0fd294bafc07590e9bf1cab40df7', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-23 14:27:08'),
(51, 10, '7b690bfe528c163cc8489419c74c4ec6', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:09:10'),
(52, 10, '7b690bfe528c163cc8489419c74c4ec6', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:09:21'),
(53, 10, '7b690bfe528c163cc8489419c74c4ec6', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:14:08'),
(54, 10, '7b690bfe528c163cc8489419c74c4ec6', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:14:21'),
(55, 10, '7b690bfe528c163cc8489419c74c4ec6', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:36:14'),
(56, 7, '7b690bfe528c163cc8489419c74c4ec6', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 16:36:23'),
(57, 1, 'b1d089aa3da30901dd6fee423868a5e3', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 19:12:19'),
(58, 1, 'b1d089aa3da30901dd6fee423868a5e3', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:12:46'),
(59, 1, 'b1d089aa3da30901dd6fee423868a5e3', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:13:16'),
(60, 7, '857432cad3d2cd1bb249866659135efa', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:26:27'),
(61, 1, 'b1d089aa3da30901dd6fee423868a5e3', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:26:38'),
(62, 7, '857432cad3d2cd1bb249866659135efa', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:27:09'),
(63, 1, '221ec40f94e7ad17f90149d972b3263b', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:27:31'),
(64, 1, '221ec40f94e7ad17f90149d972b3263b', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-24 19:27:44'),
(65, 7, '7b690bfe528c163cc8489419c74c4ec6', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 19:28:03'),
(66, 10, '7c5452cec4bf9738444bcc994937998e', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 08:44:23'),
(67, 10, '7c5452cec4bf9738444bcc994937998e', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 08:55:07'),
(68, 1, '7c5452cec4bf9738444bcc994937998e', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:01:48'),
(69, 1, '7c5452cec4bf9738444bcc994937998e', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:02:20'),
(70, 7, '7c5452cec4bf9738444bcc994937998e', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:02:26'),
(71, 7, '7c5452cec4bf9738444bcc994937998e', 'Logout', 'User logged out', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:03:54'),
(72, 10, '7c5452cec4bf9738444bcc994937998e', 'Login', 'User logged in successfully', '105.163.157.0', 'success', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:04:30'),
(73, 10, '7c5452cec4bf9738444bcc994937998e', 'Create Plan', 'Added plan \'3hr\' costing Ksh 20', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:05:14'),
(74, 10, '7c5452cec4bf9738444bcc994937998e', 'Deactivate User', 'Deactivate User - HENDRICK MWADIME (dvdjesse8998@gmail.com)', '105.163.157.0', 'info', 'IP: 105.163.157.0 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 09:06:35'),
(75, 10, 'b9a8d1690a7597b702b28ce4e1dc37ec', 'Login', 'User logged in successfully', '105.163.157.176', 'success', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:45:22'),
(76, 10, 'b9a8d1690a7597b702b28ce4e1dc37ec', 'Activate User', 'Activate User - Ramsey Rama (officialramseybookings@gmail.com)', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:48:48'),
(77, 10, 'b9a8d1690a7597b702b28ce4e1dc37ec', 'Activate User', 'Activate User - HENDRICK MWADIME (dvdjesse8998@gmail.com)', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:48:52'),
(78, 1, '7a84ff52d9ba902933c21671a7ab0c6f', 'Login', 'User logged in successfully', '105.163.157.176', 'success', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 11:41:54'),
(79, 1, '7a84ff52d9ba902933c21671a7ab0c6f', 'Logout', 'User logged out', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 11:44:18'),
(80, 10, '7a84ff52d9ba902933c21671a7ab0c6f', 'Login', 'User logged in successfully', '105.163.157.176', 'success', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 11:44:42'),
(81, 10, '7a84ff52d9ba902933c21671a7ab0c6f', 'Deactivate User', 'Deactivate User - HENDRICK MWADIME (dvdjesse8998@gmail.com)', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 11:46:43'),
(82, 10, '7a84ff52d9ba902933c21671a7ab0c6f', 'Create Plan', 'Added plan \'12 Hour\' costing Ksh 40.00', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-28 11:47:58'),
(83, 10, 'be90f5a7f5d8abfe3a1eeb0171c6a62f', 'Login', 'User logged in successfully', '105.163.157.176', 'success', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 13:15:29'),
(84, 10, 'be90f5a7f5d8abfe3a1eeb0171c6a62f', 'Delete User', 'Deleted user HENDRICK MWADIME (dvdjesse8998@gmail.com)', '105.163.157.176', 'info', 'IP: 105.163.157.176 | Device: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 13:15:45');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `mpesa_receipt` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `transaction_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `plan_id`, `mpesa_receipt`, `amount`, `phone`, `status`, `transaction_time`, `created_at`) VALUES
(1, 7, 3, NULL, '300.00', '0791060859', 'success', '2025-10-15 09:24:53', '2025-10-14 14:20:19'),
(2, 7, 4, NULL, '1000.00', '0791060859', 'success', '2025-10-15 09:24:53', '2025-10-14 14:45:01'),
(3, 7, 1, NULL, '10.00', '0797169511', 'success', '2025-10-15 09:24:53', '2025-10-14 14:54:57'),
(4, 1, 3, NULL, '300.00', '0791060859', 'pending', NULL, '2025-10-14 15:14:47'),
(5, 7, 2, NULL, '50.00', '0797169511', 'pending', NULL, '2025-10-14 15:22:28'),
(6, 8, 2, NULL, '50.00', '0791060859', 'pending', NULL, '2025-10-14 18:49:42'),
(7, 8, 3, NULL, '300.00', '0791060859', 'pending', NULL, '2025-10-14 19:16:57'),
(8, 1, 3, NULL, '300.00', '0791060859', 'pending', NULL, '2025-10-15 10:32:12'),
(15, 1, 11, NULL, '19.00', '+254791060859', 'pending', NULL, '2025-10-18 15:41:18'),
(10, 10, 4, NULL, '1000.00', '0791060859', 'pending', NULL, '2025-10-17 16:06:02'),
(11, 10, 4, NULL, '1000.00', '0791060859', 'pending', NULL, '2025-10-17 16:06:50'),
(12, 7, 4, NULL, '1000.00', '0797169511', 'pending', NULL, '2025-10-17 20:37:53'),
(13, 7, 4, NULL, '1000.00', '0797169511', 'pending', NULL, '2025-10-17 20:45:08'),
(14, 7, 4, NULL, '1000.00', '0797169511', 'pending', NULL, '2025-10-17 20:47:12'),
(16, 7, 3, NULL, '300.00', '0797169511', 'pending', NULL, '2025-10-18 15:44:47'),
(17, 7, 3, NULL, '300.00', '+254704649933', 'pending', NULL, '2025-10-24 18:36:59'),
(18, 1, 3, NULL, '300.00', '0704649933', 'pending', NULL, '2025-10-25 09:02:01'),
(19, 1, 2, NULL, '50.00', '079122323', 'pending', NULL, '2025-10-28 11:43:01');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `title`, `price`, `duration_minutes`, `description`, `active`, `created_at`) VALUES
(1, '1 Hour', '10.00', 60, 'Unlimited access for 1 hour', 1, '2025-10-13 15:53:20'),
(2, '1 Day', '50.00', 1440, 'Unlimited access for 1 day', 1, '2025-10-13 15:53:20'),
(3, '1 Week', '300.00', 10080, 'Unlimited access for 1 week', 1, '2025-10-13 15:53:20'),
(4, '1 Month', '1000.00', 43200, 'Unlimited access for 1 month', 1, '2025-10-13 16:04:55'),
(13, '3hr', '20.00', 180, '', 1, '2025-10-25 09:05:14'),
(14, '12 Hour', '40.00', 720, 'Unlimited access for 12 Hours', 1, '2025-10-28 11:47:58');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hotspot_username` varchar(100) DEFAULT NULL,
  `hotspot_password` varchar(100) DEFAULT NULL,
  `plan_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `mac` varchar(50) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `created_at`, `status`) VALUES
(1, 'Jesse Chome', 'dvdjesse54@gmail.com', '0791060859', '$2y$10$Z5E/lKXjGYn6sQiIdeffyu4r1PS93ILCIX5KD5MtA4rWX6UQuxxWe', 'user', '2025-10-14 09:55:16', 'active'),
(9, 'Ramsey Rama', 'officialramseybookings@gmail.com', '0704649933', '$2y$10$MCpchdPK4gEo4JviqHKoK.N9.qd2o0oeDA8YorvdtAqb3l/oxSahi', 'admin', '2025-10-15 10:55:09', 'active'),
(7, 'Angel Pahe', 'angel.pahe003@gmail.com', '0797169511', '$2y$10$ptI84bLGTeVH3aw3OorkruFup.395RA1iRK/aANkg02LW1MRGWKUK', 'user', '2025-10-14 13:40:49', 'active'),
(10, 'Jesse Chome', 'jesse.ngala254@gmail.com', '07911002233', '$2y$10$qETPyG0oNIXqK9u2cqzzZOAqsGM4kRe7IbL./2h3d4kNe91uDopAy', 'admin', '2025-10-15 11:13:03', 'active'),
(12, 'Ladan Franckline Mzawai', 'ledanfrankline@gmail.com', '+254704164305', '$2y$10$STwrAe90srE8XjJBjeTphOB2C1o4SQsuuk5ybSfy/xNGxViW3iWVO', 'user', '2025-10-26 07:34:14', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login_sessions`
--
ALTER TABLE `login_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    mac_address VARCHAR(20) DEFAULT NULL,
    expiry DATETIME NOT NULL,
    plan_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','expired') DEFAULT 'active'
);

