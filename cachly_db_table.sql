--
-- Table structure for table `cachly_cache`
-- Cause Cachly default hashing algorithm is sh1 then ID is CHAR(40)
--
CREATE TABLE `cachly_cache` (
	`ID` char(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
	`data` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
	`insertDate` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
	`expires` datetime DEFAULT NULL COMMENT 'timestamp when will be expired'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `cachly_cache` ADD PRIMARY KEY (`ID`),ADD KEY `expires` (`expires`);
