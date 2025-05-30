<?php
/**
 * Plugin Name: SmartSpace warehouses
 * Description: Реализует информацию о наличии товара по складам, в карточке товара.
 * Version: 1.0.7
 * Author: Alex Kovalev
 * Author URI: https://alexkovalev.pro
 * Text Domain: smartspace-warehouses
 * WC requires at least: 6.0
 * WC tested up to: 9.4.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 **/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSW_PLUGIN_VERSION', '1.0.7' );
define( 'SSW_REST_API_SECRET', '' ); // Заменить на свой
define( 'SSW_PLUGIN_FILE', __FILE__ );
define( 'SSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SSW_PLUGIN_SLUG', dirname( plugin_basename( __FILE__ ) ) );
define( 'SSW_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'SSW_PLUGIN_DIR', dirname( __FILE__ ) );

require_once SSW_PLUGIN_DIR . '/includes/class-database-handler.php';
require_once SSW_PLUGIN_DIR . '/includes/class-stock-display.php';
require_once SSW_PLUGIN_DIR . '/includes/class-rest-api-handler.php';
require_once SSW_PLUGIN_DIR . '/includes/class-file-importer.php';
require_once SSW_PLUGIN_DIR . '/includes/class-warehouse-manager.php';

/**
 * Функция активации плагина
 */
function ssw_activate_plugin() {
	$stored_version = get_option( 'ssw_plugin_version', '0' );

	if ( version_compare( $stored_version, '1.0.5', '<' ) ) {
		// Здесь выполняем миграцию данных
		$db_handler = new SSW_DatabaseHandler();
		$db_handler->migrate_table_to_v105();

		// Отключаем функцию предзаказа для всех товаров WooCommerce
		$args = [
			'post_type'      => 'product',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		];

		$product_ids = get_posts( $args );

		foreach ( $product_ids as $product_id ) {
			update_post_meta( $product_id, '_backorders', 'no' );
		}

		if ( class_exists( 'WC_Logger' ) ) {
			$logger = wc_get_logger();
			$logger->info( sprintf( 'SmartSpace Warehouses: выполнена миграция с версии %s до %s', $stored_version, SSW_PLUGIN_VERSION ), [ 'source' => 'smartspace-warehouses' ] );
		} else {
			error_log( sprintf( 'SmartSpace Warehouses: выполнена миграция с версии %s до %s', $stored_version, SSW_PLUGIN_VERSION ) );
		}
	}

	update_option( 'ssw_plugin_version', SSW_PLUGIN_VERSION );
}


register_activation_hook( __FILE__, 'ssw_activate_plugin' );

new SSW_WarehouseManager();