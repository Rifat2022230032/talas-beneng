-- database.sql
-- Pembuatan database untuk IoT Rumah Pengering

CREATE DATABASE IF NOT EXISTS `db_rumah_pengering` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_rumah_pengering`;

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 19, 2026 at 07:26 PM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_rumah_pengering`
--

-- --------------------------------------------------------

--
-- Table structure for table `camera_log`
--

CREATE TABLE `camera_log` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_log`
--

CREATE TABLE `notification_log` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unread',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sensor_log` (
  `id` int(11) NOT NULL,
  `temperature` float NOT NULL,
  `humidity` float NOT NULL,
  `sht_temperature` float NOT NULL,
  `exhaust` tinyint(1) NOT NULL DEFAULT 0,
  `exhaust_1` tinyint(1) NOT NULL DEFAULT 0,
  `exhaust_2` tinyint(1) NOT NULL DEFAULT 0,
  `exhaust_3` tinyint(1) NOT NULL DEFAULT 0,
  `exhaust_4` tinyint(1) NOT NULL DEFAULT 0,
  `wifi` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `settings` (
  `key_name` varchar(50) NOT NULL,
  `value_val` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key_name`, `value_val`) VALUES
('camera_capture', 'ON'),
('camera_status', 'ON'),
('control_mode', 'AUTO'),
('exhaust_1_control', 'ON'),
('exhaust_2_control', 'ON'),
('exhaust_3_control', 'ON'),
('exhaust_4_control', 'ON'),
('exhaust_control', 'ON'),
('hum_maks', '60.0'),
('hum_min', '50.0'),
('suhu_maks', '50.0'),
('wifi_password', '12345678'),
('wifi_ssid', 'beneng');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `camera_log`
--
ALTER TABLE `camera_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_log`
--
ALTER TABLE `notification_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sensor_log`
--
ALTER TABLE `sensor_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `camera_log`
--
ALTER TABLE `camera_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `notification_log`
--
ALTER TABLE `notification_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1766;

--
-- AUTO_INCREMENT for table `sensor_log`
--
ALTER TABLE `sensor_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1346;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

