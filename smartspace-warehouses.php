<?php
/**
 * Plugin Name: SmartSpace warehouses
 * Description: Реализует информацию о наличии товара по складам, в карточке товара.
 * Version: 1.0.0
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

define( 'SSW_PLUGIN_VERSION', '1.0.0' );
define( 'SSW_REST_API_SECRET', 'secret' ); // Заменить на свой
define( 'SSW_PLUGIN_FILE', __FILE__ );
define( 'SSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SSW_PLUGIN_SLUG', dirname( plugin_basename( __FILE__ ) ) );
define( 'SSW_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'SSW_PLUGIN_DIR', dirname( __FILE__ ) );

require_once SSW_PLUGIN_DIR . '/functions.php';

register_activation_hook( __FILE__, 'ssw_create_table' );

add_action( 'init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( - 1 );
	}

	require_once SSW_PLUGIN_DIR . '/test-import.php';

	if ( isset( $_GET['import-warehouse-test-data'] ) ) {
		ssw_create_table();
		$data = ssw_parse_xls_file( SSW_PLUGIN_DIR . '/test-data.xls' );
		ssw_insert_data_into_database( $data );
		exit;
	}
} );

add_action( 'woocommerce_single_product_summary', function () {
	if ( is_product() ) {
		global $wpdb, $product;

		$article = $product->get_sku(); // SKU используется как артикул

		if ( $article ) {
			$table_name = $wpdb->prefix . 'ssw_warehouses_stock';
			$result     = $wpdb->get_row( $wpdb->prepare(
				"SELECT available_in_gorkogo, available_in_main_stock_next_day FROM {$table_name} WHERE article = %s",
				$article
			) );

			if ( $result ) {
				$html = '';

				$generate_stock_html = function ( $location, $message, $quantity ) {
					return '<div class="custom-stock-info" style="margin-top: 20px; font-size: 16px; color: #333; border-radius: 10px; border:1px solid #444;">
								<p style="padding:20px;margin:0;">' . esc_html( $location ) . ', ' . $message . '<br>В наличии: <strong>' . intval( $quantity ) . '</strong></p>
							</div>';
				};

				// Проверяем наличие товара в магазине Горького
				if ( intval( $result->available_in_gorkogo ) > 0 ) {
					$html .= $generate_stock_html(
						'Магазин Горького 35',
						'<strong style="color:green">можно забрать сейчас</strong>',
						$result->available_in_gorkogo
					);
				}

				// Проверяем наличие товара на основном складе
				if ( intval( $result->available_in_main_stock_next_day ) > 0 ) {
					$html .= $generate_stock_html(
						'Основной склад',
						'заказать самовывоз на следующий день после 16:00 (кроме воскресенья)',
						$result->available_in_main_stock_next_day
					);
				}


				if ( $html ) {
					echo $html;
				}
			}
		}
	}
}, 15 );


add_action( 'rest_api_init', function () {
	// Регистрируем маршрут
	register_rest_route( 'ssw/v1', '/update-stock', [
		'methods'             => 'POST',
		'callback'            => 'ssw_update_stock',
		'permission_callback' => function ( $request ) {
			// Проверка секретного ключа
			$provided_key = $request->get_param( 'secret_key' );

			return $provided_key === SSW_REST_API_SECRET;
		},
	] );
} );

function ssw_update_stock( WP_REST_Request $request ) {
	global $wpdb;

	$items = $request->get_param( 'items' ); // Ожидаем массив товаров
	if ( ! is_array( $items ) || empty( $items ) ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => 'Не переданы данные о товарах или данные имеют неверный формат.',
		], 400 );
	}

	// Название таблицы
	$table_name = $wpdb->prefix . 'ssw_warehouses_stock';
	$errors     = [];
	$successes  = [];

	// Перебираем переданные товары и обновляем/добавляем данные
	foreach ( $items as $item ) {
		$sku                              = isset( $item['article'] ) ? $item['article'] : null;
		$available_in_gorkogo             = isset( $item['available_in_gorkogo'] ) ? $item['available_in_gorkogo'] : null;
		$available_in_main_stock_next_day = isset( $item['available_in_main_stock_next_day'] ) ? $item['available_in_main_stock_next_day'] : null;

		// Проверяем обязательные параметры
		if ( empty( $sku ) || ! isset( $available_in_gorkogo ) || ! isset( $available_in_main_stock_next_day ) ) {
			$errors[] = [
				'article' => $sku,
				'message' => 'Отсутствуют обязательные параметры: артикул, available_in_gorkogo или available_in_main_stock_next_day.'
			];
			continue;
		}

		// Проверяем, существует ли запись с таким артикулом
		$existing_item = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE article = %s",
			$sku
		) );

		if ( $existing_item ) {
			// Обновляем запись, если она существует
			$updated = $wpdb->update(
				$table_name,
				[
					'available_in_gorkogo'             => intval( $available_in_gorkogo ),
					'available_in_main_stock_next_day' => intval( $available_in_main_stock_next_day ),
					'updated_at'                       => current_time( 'mysql' ),
				],
				[ 'article' => $sku ],
				[ '%d', '%d', '%s' ],
				[ '%s' ]
			);

			if ( $updated !== false ) {
				$successes[] = [
					'article' => $sku,
					'message' => 'Данные о товаре успешно обновлены.'
				];
			} else {
				$errors[] = [
					'article' => $sku,
					'message' => 'Не удалось обновить данные о товаре.'
				];
			}
		} else {
			// Добавляем новую запись, если её нет
			$inserted = $wpdb->insert(
				$table_name,
				[
					'article'                          => $sku,
					'available_in_gorkogo'             => intval( $available_in_gorkogo ),
					'available_in_main_stock_next_day' => intval( $available_in_main_stock_next_day ),
					'created_at'                       => current_time( 'mysql' ),
					'updated_at'                       => current_time( 'mysql' ),
				],
				[ '%s', '%d', '%d', '%s', '%s' ]
			);

			if ( $inserted ) {
				$successes[] = [
					'article' => $sku,
					'message' => 'Новый товар успешно добавлен.'
				];
			} else {
				$errors[] = [
					'article' => $sku,
					'message' => 'Не удалось добавить новый товар.'
				];
			}
		}
	}

	return new WP_REST_Response( [
		'success'  => empty( $errors ),
		'messages' => [
			'successes' => $successes,
			'errors'    => $errors,
		],
	], empty( $errors ) ? 200 : 207 ); // 207 - Multi-Status для частичного успеха
}


