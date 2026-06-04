-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2026 at 06:20 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `switch_monitor`
--

-- --------------------------------------------------------

--
-- Table structure for table `alert_history`
--

CREATE TABLE `alert_history` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_index` varchar(50) NOT NULL,
  `port_name` varchar(100) DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `message` text DEFAULT NULL,
  `sent_via` varchar(20) DEFAULT 'TELEGRAM',
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fiber_calibration`
--

CREATE TABLE `fiber_calibration` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_index` varchar(50) NOT NULL,
  `tx_reference` float DEFAULT 3 COMMENT 'TX power in dBm (reference)',
  `connector_loss` float DEFAULT 1 COMMENT 'Connector loss in dB (both ends)',
  `attenuation_per_km` float DEFAULT 0.35 COMMENT 'Fiber attenuation dB/km (1310nm=0.35, 1550nm=0.25)',
  `calibrated_distance_km` float DEFAULT NULL COMMENT 'Manually calibrated distance (km)',
  `calibrated_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fiber_calibration`
--

INSERT INTO `fiber_calibration` (`id`, `switch_id`, `port_index`, `tx_reference`, `connector_loss`, `attenuation_per_km`, `calibrated_distance_km`, `calibrated_at`, `notes`) VALUES
(1, 5, '', 3, 1, 0.35, 20, '2026-05-24 10:40:51', 'Calibrated via OTDR dashboard');

-- --------------------------------------------------------

--
-- Table structure for table `fiber_distances`
--

CREATE TABLE `fiber_distances` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `distance_m` decimal(10,2) DEFAULT NULL,
  `distance_km` decimal(10,3) DEFAULT NULL,
  `measured_at` datetime DEFAULT NULL,
  `method` enum('SNMP_OID','OTDR','MANUAL','CALCULATED') DEFAULT 'SNMP_OID',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `port_name` varchar(255) DEFAULT NULL,
  `issue_type` enum('FIBER_CUT','PORT_ISSUE','REMOTE_FAULT','DEGRADATION','POWER_FLUCTUATION','OTHER') DEFAULT 'OTHER',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `rx_before` varchar(50) DEFAULT NULL,
  `rx_after` varchar(50) DEFAULT NULL,
  `tx_before` varchar(50) DEFAULT NULL,
  `tx_after` varchar(50) DEFAULT NULL,
  `distance_km` decimal(10,3) DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `telegram_msg_id` varchar(100) DEFAULT NULL,
  `status` enum('ACTIVE','RESOLVED','INVESTIGATING') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `switch_id`, `port_index`, `port_name`, `issue_type`, `start_time`, `end_time`, `duration_minutes`, `rx_before`, `rx_after`, `tx_before`, `tx_after`, `distance_km`, `root_cause`, `resolution_notes`, `telegram_msg_id`, `status`, `created_at`) VALUES
(1, 5, '197', 'GigaEthernet0/1', 'FIBER_CUT', NULL, NULL, 0, NULL, NULL, NULL, NULL, '6.554', NULL, NULL, NULL, 'ACTIVE', '2026-05-23 04:18:36'),


-- --------------------------------------------------------

--
-- Table structure for table `port_alerts`
--

CREATE TABLE `port_alerts` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `last_status` int(11) DEFAULT NULL,
  `last_down_cause` varchar(100) DEFAULT NULL,
  `last_down_distance` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `down_since` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `port_alerts`
--

INSERT INTO `port_alerts` (`id`, `switch_id`, `port_index`, `last_status`, `last_down_cause`, `last_down_distance`, `updated_at`, `down_since`) VALUES
(1, 5, '69', 1, NULL, NULL, '2026-05-24 03:56:34', NULL),


-- --------------------------------------------------------

--
-- Table structure for table `port_descriptions`
--

CREATE TABLE `port_descriptions` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_index` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `port_descriptions`
--

INSERT INTO `port_descriptions` (`id`, `switch_id`, `port_index`, `description`, `customer_name`, `customer_phone`, `location`, `updated_at`) VALUES
(1, 4, '37', '', 'LACP', '', '', '2026-05-23 04:37:56'),
(2, 4, '173', '', 'FTS CORE', '', '', '2026-05-23 04:38:06'),
(3, 4, '174', '', 'JN CORE', '', '', '2026-05-23 04:38:16'),
(4, 5, '199', '', 'FTS CORE', '', '', '2026-05-23 04:39:07'),
(5, 5, '200', '', 'JN CORE', '', '', '2026-05-23 04:39:17');

-- --------------------------------------------------------

--
-- Table structure for table `port_events`
--

CREATE TABLE `port_events` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_index` varchar(50) NOT NULL,
  `port_name` varchar(100) DEFAULT NULL,
  `event_type` enum('DOWN','UP','FIBER_CUT','RECOVERED','WARNING') NOT NULL,
  `details` text DEFAULT NULL,
  `rx_power` varchar(20) DEFAULT NULL,
  `tx_power` varchar(20) DEFAULT NULL,
  `fiber_distance` varchar(50) DEFAULT NULL,
  `event_time` datetime NOT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `port_events`
--

INSERT INTO `port_events` (`id`, `switch_id`, `port_index`, `port_name`, `event_type`, `details`, `rx_power`, `tx_power`, `fiber_distance`, `event_time`, `is_read`) VALUES
(1, 5, '197', 'GigaEthernet0/1', 'FIBER_CUT', 'Fiber cut detected - Loss of Optical Signal (LOS) | Estimated distance: 77.14 km', 'N/A', 'N/A', 'Fiber Cut / No Light (LOS)', '2026-05-23 11:05:23', 0),


-- --------------------------------------------------------

--
-- Table structure for table `port_history`
--

CREATE TABLE `port_history` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `event_type` enum('UP','DOWN','DEGRADED','RECOVERED','MAINTENANCE') DEFAULT 'UP',
  `rx_power` varchar(50) DEFAULT NULL,
  `tx_power` varchar(50) DEFAULT NULL,
  `traffic_mbps` decimal(10,2) DEFAULT NULL,
  `vlan_id` varchar(50) DEFAULT NULL,
  `event_time` datetime DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `port_history`
--

INSERT INTO `port_history` (`id`, `switch_id`, `port_index`, `event_type`, `rx_power`, `tx_power`, `traffic_mbps`, `vlan_id`, `event_time`, `details`) VALUES
(1, 4, '37', 'UP', 'N/A', 'N/A', '4.02', 'N/A', '2026-05-21 18:17:16', 'Auto-logged by monitoring system'),
(2, 4, '37', 'UP', 'N/A', 'N/A', '158.51', 'N/A', '2026-05-21 18:18:12', 'Auto-logged by monitoring system'),

-- --------------------------------------------------------

--
-- Table structure for table `port_optical`
--

CREATE TABLE `port_optical` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_index` varchar(10) NOT NULL,
  `rx_dbm` varchar(20) DEFAULT NULL,
  `tx_dbm` varchar(20) DEFAULT NULL,
  `rx_raw_value` decimal(10,2) DEFAULT NULL,
  `tx_raw_value` decimal(10,2) DEFAULT NULL,
  `status_code` tinyint(4) DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `port_traffic`
--

CREATE TABLE `port_traffic` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `in_octets` bigint(20) UNSIGNED DEFAULT NULL,
  `out_octets` bigint(20) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `port_traffic`
--

INSERT INTO `port_traffic` (`id`, `switch_id`, `port_index`, `in_octets`, `out_octets`, `recorded_at`) VALUES
(38064, 4, '677', 3944617846, NULL, '2026-02-26 06:33:31'),


-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'tg_token', '8765180306:AAHIMDwC84NaNjOHhvTK4dJRK65Y2N_cP8M'),
(2, 'tg_chat_id', '1913663623');

-- --------------------------------------------------------

--
-- Table structure for table `switches`
--

CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `community` varchar(50) DEFAULT 'public',
  `model` varchar(50) DEFAULT 'BDCOM',
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `switches`
--

INSERT INTO `switches` (`id`, `name`, `ip_address`, `community`, `model`, `status`) VALUES


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', '2026-02-22 05:58:37'),

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alert_history`
--
ALTER TABLE `alert_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fiber_calibration`
--
ALTER TABLE `fiber_calibration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_port` (`switch_id`,`port_index`);

--
-- Indexes for table `fiber_distances`
--
ALTER TABLE `fiber_distances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `switch_port` (`switch_id`,`port_index`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `switch_port` (`switch_id`,`port_index`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `port_alerts`
--
ALTER TABLE `port_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_alerts_unique` (`switch_id`,`port_index`);

--
-- Indexes for table `port_descriptions`
--
ALTER TABLE `port_descriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_port` (`switch_id`,`port_index`);

--
-- Indexes for table `port_events`
--
ALTER TABLE `port_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_switch_port` (`switch_id`,`port_index`),
  ADD KEY `idx_event_time` (`event_time`),
  ADD KEY `idx_events_lookup` (`switch_id`,`port_index`,`event_time`),
  ADD KEY `idx_events_type` (`event_type`,`event_time`);

--
-- Indexes for table `port_history`
--
ALTER TABLE `port_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `switch_port_time` (`switch_id`,`port_index`,`event_time`);

--
-- Indexes for table `port_optical`
--
ALTER TABLE `port_optical`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_switch_port` (`switch_id`,`port_index`),
  ADD KEY `idx_recorded` (`recorded_at`);

--
-- Indexes for table `port_traffic`
--
ALTER TABLE `port_traffic`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `switches`
--
ALTER TABLE `switches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alert_history`
--
ALTER TABLE `alert_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fiber_calibration`
--
ALTER TABLE `fiber_calibration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fiber_distances`
--
ALTER TABLE `fiber_distances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `port_alerts`
--
ALTER TABLE `port_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3272;

--
-- AUTO_INCREMENT for table `port_descriptions`
--
ALTER TABLE `port_descriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `port_events`
--
ALTER TABLE `port_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `port_history`
--
ALTER TABLE `port_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=608;

--
-- AUTO_INCREMENT for table `port_optical`
--
ALTER TABLE `port_optical`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `port_traffic`
--
ALTER TABLE `port_traffic`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60015;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
