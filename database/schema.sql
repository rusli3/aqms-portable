CREATE TABLE IF NOT EXISTS `maintb` (
  `no` int NOT NULL AUTO_INCREMENT,
  `waktu` datetime DEFAULT NULL,
  `pm1` float DEFAULT NULL,
  `pm25` float DEFAULT NULL,
  `pm10` float DEFAULT NULL,
  `temp` float DEFAULT NULL,
  `humd` float DEFAULT NULL,
  `ampere` float DEFAULT NULL,
  `baterai` float DEFAULT NULL,
  `pompa` float DEFAULT NULL,
  `volt` float DEFAULT NULL,
  `press` float DEFAULT NULL,
  PRIMARY KEY (`no`),
  KEY `idx_maintb_waktu` (`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coretb` (
  `no` int NOT NULL AUTO_INCREMENT,
  `waktu` datetime DEFAULT NULL,
  `pm1` float DEFAULT NULL,
  `pm25` float DEFAULT NULL,
  `pm10` float DEFAULT NULL,
  `temp` float DEFAULT NULL,
  `humd` float DEFAULT NULL,
  `ampere` float DEFAULT NULL,
  `baterai` float DEFAULT NULL,
  `pompa` float DEFAULT NULL,
  `volt` float DEFAULT NULL,
  `press` float DEFAULT NULL,
  PRIMARY KEY (`no`),
  UNIQUE KEY `uq_coretb_waktu` (`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
