-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 10, 2026 at 07:23 AM
-- Server version: 8.0.35
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `content`
--

-- --------------------------------------------------------

--
-- Table structure for table `available_models`
--

CREATE TABLE `available_models` (
  `id` int NOT NULL,
  `api_type` enum('gemini','deepseek') COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_tokens` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `available_models`
--

INSERT INTO `available_models` (`id`, `api_type`, `model_id`, `model_name`, `description`, `max_tokens`, `is_active`, `last_updated`) VALUES
(37, 'deepseek', 'deepseek-chat', 'DeepSeek Chat', NULL, NULL, 1, '2026-04-21 04:23:39'),
(38, 'deepseek', 'deepseek-reasoner', 'DeepSeek Reasoner', NULL, NULL, 1, '2026-04-21 04:23:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `available_models`
--
ALTER TABLE `available_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_type_model_id` (`api_type`,`model_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `available_models`
--
ALTER TABLE `available_models`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
