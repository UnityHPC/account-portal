SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `operator` varchar(128) NOT NULL,
  `operator_ip` varchar(15) NOT NULL,
  `action_type` varchar(768) NOT NULL,
  `recipient` varchar(768) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE user_last_logins (
   operator VARCHAR(768) PRIMARY KEY,
   last_login TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `request_for` varchar(131) NOT NULL,
  `uid` varchar(128) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1031;
