-- database.sql
-- Pembuatan database untuk IoT Rumah Pengering

CREATE DATABASE IF NOT EXISTS `db_rumah_pengering` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_rumah_pengering`;

-- 1. Tabel Log Sensor
CREATE TABLE IF NOT EXISTS `sensor_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `temperature` FLOAT NOT NULL,
  `humidity` FLOAT NOT NULL,
  `sht_temperature` FLOAT NOT NULL,
  `exhaust` TINYINT(1) NOT NULL DEFAULT 0, -- 0: OFF, 1: ON (Fallback/Logical OR)
  `exhaust_1` TINYINT(1) NOT NULL DEFAULT 0, -- 0: OFF, 1: ON
  `exhaust_2` TINYINT(1) NOT NULL DEFAULT 0, -- 0: OFF, 1: ON
  `exhaust_3` TINYINT(1) NOT NULL DEFAULT 0, -- 0: OFF, 1: ON
  `exhaust_4` TINYINT(1) NOT NULL DEFAULT 0, -- 0: OFF, 1: ON
  `wifi` INT NOT NULL, -- RSSI (dBm)
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Log Kamera ESP32-CAM
CREATE TABLE IF NOT EXISTS `camera_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Log Notifikasi Sistem
CREATE TABLE IF NOT EXISTS `notification_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'unread', -- unread, read
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Pengaturan / Settings Parameter
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name` VARCHAR(50) PRIMARY KEY,
  `value_val` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seeding awal untuk data pengaturan
INSERT INTO `settings` (`key_name`, `value_val`) VALUES
('suhu_maks', '50.0'),
('hum_maks', '60.0'),
('hum_min', '50.0'),
('control_mode', 'AUTO'),
('exhaust_control', 'OFF'),
('exhaust_1_control', 'OFF'),
('exhaust_2_control', 'OFF'),
('exhaust_3_control', 'OFF'),
('exhaust_4_control', 'OFF')
ON DUPLICATE KEY UPDATE `value_val` = VALUES(`value_val`);
