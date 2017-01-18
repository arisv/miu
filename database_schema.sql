-- phpMyAdmin SQL Dump
-- version 4.4.12
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jan 19, 2017 at 01:24 AM
-- Server version: 5.5.44
-- PHP Version: 5.5.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `miubase`
--
CREATE DATABASE IF NOT EXISTS `miubase` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `miubase`;

-- --------------------------------------------------------

--
-- Table structure for table `filestorage`
--

CREATE TABLE IF NOT EXISTS `filestorage` (
  `id` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `internal_name` varchar(255) NOT NULL,
  `custom_url` varchar(255) DEFAULT NULL,
  `service_url` varchar(255) DEFAULT NULL,
  `original_extension` varchar(255) DEFAULT NULL,
  `internal_mimetype` varchar(255) DEFAULT NULL,
  `internal_size` int(10) unsigned NOT NULL,
  `date` int(10) unsigned NOT NULL,
  `visibility_status` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `uploadlog`
--

CREATE TABLE IF NOT EXISTS `uploadlog` (
  `image_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL,
  `login` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `remote_token` varchar(255) DEFAULT NULL,
  `role` int(11) NOT NULL DEFAULT '1',
  `active` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `filestorage`
--
ALTER TABLE `filestorage`
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
-- AUTO_INCREMENT for table `filestorage`
--
ALTER TABLE `filestorage`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
