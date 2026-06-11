SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `operator` varchar(128) NOT NULL,
  `operator_ip` varchar(15) NOT NULL,
  `action_type` varchar(768) NOT NULL,
  `recipient` varchar(768) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_last_logins` (
   `operator` VARCHAR(768) PRIMARY KEY,
   `last_login` TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE `requests` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `request_for` varchar(131) NOT NULL,
  `uid` varchar(128) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pi_group_expiration_dates` (
  `gid` varchar(131) NOT NULL,
  `expiration_date` timestamp NOT NULL,
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;