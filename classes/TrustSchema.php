<?php

/** Additive v0.3 migration; legacy columns remain available for recovery/inspection. */
final class MarclandTrustSchema
{
    public static function migrate($db): bool
    {
        $lock = substr('marclandtrust_' . hash('sha256', _DB_NAME_ . ':' . _DB_PREFIX_), 0, 64);
        if ((string) $db->getValue("SELECT GET_LOCK('" . pSQL($lock) . "', 10)", false) !== '1') {
            return false;
        }
        try {
            // DDL implicitly commits. Each step is repeatable and never replaces existing rows.
            $column = $db->getRow("SELECT COLUMN_TYPE AS `Type` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . pSQL(_DB_PREFIX_ . 'marclandtrust_zone') . "' AND COLUMN_NAME = 'position'", false);
            if (!$column) {
                return false;
            }
            if (stripos($column['Type'], 'tinyint') !== false || stripos($column['Type'], 'smallint') !== false) {
                if (!$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'marclandtrust_zone` MODIFY `position` INT UNSIGNED NOT NULL DEFAULT 0')) {
                    return false;
                }
            }
            if (!$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'marclandtrust_zone_placement` (
                `id_zone` INT UNSIGNED NOT NULL,
                `placement` VARCHAR(32) NOT NULL,
                `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `position` INT UNSIGNED NOT NULL DEFAULT 1,
                `link_type` VARCHAR(16) NOT NULL DEFAULT \'none\',
                `link_target` VARCHAR(2048) DEFAULT NULL,
                PRIMARY KEY (`id_zone`, `placement`),
                KEY `placement_enabled_position` (`placement`, `enabled`, `position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')) {
                return false;
            }
            // INSERT IGNORE only initializes absent product rows: merchant edits are never reset.
            return $db->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'marclandtrust_zone_placement`
                (`id_zone`, `placement`, `enabled`, `position`, `link_type`, `link_target`)
                SELECT `id_zone`, \'product\', 1, `position`, `link_type`, `link_target`
                FROM `' . _DB_PREFIX_ . 'marclandtrust_zone`');
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('" . pSQL($lock) . "')", false);
        }
    }
}
