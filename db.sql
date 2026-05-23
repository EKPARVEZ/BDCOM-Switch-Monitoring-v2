-- phpMyAdmin SQL Dump
-- Database: `switch_monitor`

CREATE DATABASE IF NOT EXISTS `switch_monitor` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `switch_monitor`;

-- --------------------------------------------------------
-- Table structure for table `port_alerts`
-- --------------------------------------------------------
CREATE TABLE `port_alerts` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `last_status` int(11) DEFAULT NULL,
  `last_down_cause` varchar(200) DEFAULT NULL,
  `last_down_distance` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `port_traffic`
-- --------------------------------------------------------
CREATE TABLE `port_traffic` (
  `id` int(11) NOT NULL,
  `switch_id` int(11) DEFAULT NULL,
  `port_index` varchar(50) DEFAULT NULL,
  `in_octets` bigint(20) UNSIGNED DEFAULT NULL,
  `out_octets` bigint(20) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'tg_token', ''),
(2, 'tg_chat_id', '');

-- --------------------------------------------------------
-- Table structure for table `switches`
-- --------------------------------------------------------
CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `community` varchar(50) DEFAULT 'public',
  `model` varchar(50) DEFAULT 'BDCOM',
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', '2026-02-22 05:58:37');

-- --------------------------------------------------------
-- Indexes and AUTO_INCREMENT
-- --------------------------------------------------------
ALTER TABLE `port_alerts` ADD PRIMARY KEY (`id`);
ALTER TABLE `port_traffic` ADD PRIMARY KEY (`id`);
ALTER TABLE `settings` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `setting_key` (`setting_key`);
ALTER TABLE `switches` ADD PRIMARY KEY (`id`);
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `port_alerts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `port_traffic` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `settings` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `switches` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;