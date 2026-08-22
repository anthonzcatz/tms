-- Configure whether POS ticket sales require an operator-supplied ticket number.
-- Default is enabled to preserve the current POS behavior.
ALTER TABLE `system_settings`
  ADD COLUMN IF NOT EXISTS `pos_ticket_number_required` TINYINT(1) NOT NULL DEFAULT 1
  COMMENT 'When enabled, POS ticket sales require a ticket number.';

UPDATE `system_settings`
SET `pos_ticket_number_required` = 1
WHERE `setting_id` = 1;
