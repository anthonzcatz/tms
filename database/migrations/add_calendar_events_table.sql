-- Migration: Create calendar_events table
-- Run this once against tms_db

CREATE TABLE IF NOT EXISTS `calendar_events` (
  `event_id`    bigint(20)   NOT NULL AUTO_INCREMENT,
  `user_id`     bigint(20)   DEFAULT NULL COMMENT 'Owner of the event',
  `title`       varchar(255) NOT NULL,
  `description` text         DEFAULT NULL,
  `start_date`  datetime     NOT NULL,
  `end_date`    datetime     DEFAULT NULL,
  `all_day`     tinyint(1)   NOT NULL DEFAULT 0,
  `label`       varchar(50)  DEFAULT NULL COMMENT 'primary|danger|success|warning',
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`  timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Foreign key: link to user_accounts
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `fk_calendar_events_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_accounts` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
