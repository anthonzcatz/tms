-- Add denormalized location name columns to business_branches
-- to avoid PSGC joins on every read

ALTER TABLE `business_branches`
  ADD COLUMN IF NOT EXISTS `region_name` VARCHAR(150) NULL AFTER `region_code`,
  ADD COLUMN IF NOT EXISTS `province_name` VARCHAR(150) NULL AFTER `province_code`,
  ADD COLUMN IF NOT EXISTS `city_municipality_name` VARCHAR(150) NULL AFTER `city_municipality_code`,
  ADD COLUMN IF NOT EXISTS `barangay_name` VARCHAR(150) NULL AFTER `barangay_code`;

-- Backfill existing branches with names from PSGC reference tables
UPDATE `business_branches` bb
LEFT JOIN `psgc_regions` r ON bb.region_code = r.region_code
LEFT JOIN `psgc_provinces` p ON bb.province_code = p.province_code
LEFT JOIN `psgc_cities_municipalities` c ON bb.city_municipality_code = c.city_municipality_code
LEFT JOIN `psgc_barangays` b ON bb.barangay_code = b.barangay_code
SET 
  bb.region_name = r.region_name,
  bb.province_name = p.province_name,
  bb.city_municipality_name = c.city_municipality_name,
  bb.barangay_name = b.barangay_name;
