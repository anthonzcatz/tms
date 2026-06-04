-- Add wallet insufficient balance setting to system_settings
ALTER TABLE `system_settings`
  ADD COLUMN IF NOT EXISTS `pos_allow_insufficient_wallet` TINYINT(1) DEFAULT 0
  COMMENT 'When enabled, POS can process ticket sales even if provider wallet balance is insufficient. Wallet will go negative and must be topped up.';

UPDATE `system_settings` SET `pos_allow_insufficient_wallet` = 0 WHERE setting_id = 1;
