<?php
/**
 * DEPRECATED: This script used to merge add-ons into base_balance.
 *
 * Add-ons are now tracked separately. Please run:
 *   php database/migrate_customer_charge_add_on.php
 *   php database/backfill_customer_charge_add_on.php
 */
echo "This backfill is deprecated. Run database/migrate_customer_charge_add_on.php then database/backfill_customer_charge_add_on.php\n";
exit(0);
