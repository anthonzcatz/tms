-- =========================================================
-- COMPANIES TABLE MIGRATION
-- Used by employees.b_company_id
-- =========================================================

CREATE TABLE IF NOT EXISTS `companies` (
  `comp_id` int(11) NOT NULL AUTO_INCREMENT,
  `comp_name` varchar(150) NOT NULL,
  `comp_abbre` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_contact` varchar(50) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `comp_status` varchar(20) DEFAULT 'active',
  `comp_addedby` int(11) NOT NULL,
  `comp_dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`comp_id`),
  KEY `comp_addedby` (`comp_addedby`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default company
INSERT INTO `companies` (`comp_id`, `comp_name`, `comp_abbre`, `company_address`, `company_contact`, `company_email`, `comp_status`, `comp_addedby`, `comp_dateadded`) 
SELECT 1, 'Main Company', 'MAIN', NULL, NULL, NULL, 'active', 1, current_timestamp()
WHERE NOT EXISTS (SELECT 1 FROM `companies` WHERE `comp_id` = 1);
