<?php
declare(strict_types=1);

namespace LimeColumns\Storage;

/** テーブル作成とマイグレーション。DBバージョンで差分適用する。 */
final class Schema
{
    public const DB_VERSION = '1';
    public const OPTION = 'lime_columns_db_version';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lime_columns';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_key varchar(100) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            columns longtext NULL,
            settings longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            date_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY list_key (list_key)
        ) {$charset};");
        update_option(self::OPTION, self::DB_VERSION, false);
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }
}
