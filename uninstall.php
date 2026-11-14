<?php
// アンインストール時はテーブルを削除する（設定が不要になった場合のみ到達する）。
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lime_columns"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option('lime_columns_db_version');
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lime_columns\\_foreign\\_%'"); // phpcs:ignore
