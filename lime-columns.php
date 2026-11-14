<?php
/**
 * Plugin Name: LimeColumns
 * Description: A standalone plugin to freely configure the columns of admin list tables.
 * Plugin URI: https://github.com/tksdub/lime-columns/
 * Author: Takuma Shigematsu
 * Author URI: https://lime.co.jp
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: lime-columns
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('LIME_COLUMNS_VERSION', '0.1.0');
define('LIME_COLUMNS_FILE', __FILE__);
define('LIME_COLUMNS_DIR', plugin_dir_path(__FILE__));
define('LIME_COLUMNS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/src/Autoloader.php';
\LimeColumns\Autoloader::register(__DIR__ . '/src');

register_activation_hook(__FILE__, [\LimeColumns\Storage\Schema::class, 'install']);

add_action('plugins_loaded', static function (): void {
    \LimeColumns\Plugin::instance()->boot();
}, 5);
