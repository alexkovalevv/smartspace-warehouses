<?php
/**
 * Plugin Name: SmartSpace warehouses
 * Description: Реализует информацию о наличии товара по складам, в карточке товара.
 * Version: 1.0.1
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

define( 'SSW_PLUGIN_VERSION', '1.0.1' );
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

new SSW_WarehouseManager();