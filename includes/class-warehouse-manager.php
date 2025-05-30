<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс SSW_WarehouseManager является основным классом плагина.
 * Управляет всеми основными процессами, включая инициализацию хуков, отображение информации о складе и обработку REST API.
 *
 * @author  Alex Kovalev <alex.kovalevv@gmail.com> <Telegram:@alex_kovalevv>
 */
class SSW_WarehouseManager {
	/**
	 * Обработчик базы данных, отвечает за взаимодействие с таблицей складских остатков.
	 *
	 * @var SSW_DatabaseHandler
	 */
	private SSW_DatabaseHandler $database_handler;

	/**
	 * Класс для отображения информации о складских остатках на странице продукта.
	 *
	 * @var SSW_StockDisplay
	 */
	private SSW_StockDisplay $stock_display;

	/**
	 * Обработчик REST API, отвечает за обновление складских остатков через API.
	 *
	 * @var SSW_RestAPIHandler
	 */
	private SSW_RestAPIHandler $rest_api_handler;

	/**
	 * Импортёр данных из файлов (например, XLS), используется для тестового импорта.
	 *
	 * @var SSW_FileImporter
	 */
	private SSW_FileImporter $file_importer;

	/**
	 * Конструктор класса.
	 * Инициализирует компоненты класса и устанавливает хуки.
	 */
	public function __construct() {
		$this->database_handler = new SSW_DatabaseHandler();
		$this->stock_display    = new SSW_StockDisplay();
		$this->rest_api_handler = new SSW_RestAPIHandler( $this->database_handler );
		$this->file_importer    = new SSW_FileImporter();

		$this->init_hooks();
	}

	/**
	 * Регистрация всех необходимых хуков плагина.
	 * Выполняется:
	 * - Создание таблицы в базе данных при активации плагина.
	 * - Импорт тестовых данных.
	 * - Отображение информации о складе на странице товара.
	 * - Регистрация REST API маршрутов.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Хук создания таблицы при активации плагина
		register_activation_hook( SSW_PLUGIN_FILE, [ $this->database_handler, 'create_table' ] );

		// Хук для тестового импорта данных
		add_action( 'init', [ $this, 'handle_test_import' ] );

		// Хук отображения складских остатков на странице продукта WooCommerce
		add_action( 'woocommerce_single_product_summary', [ $this, 'display_product_stock_info' ], 15 );
		add_action( 'woocommerce_after_list_view_large_item', [ $this, 'display_product_stock_info' ], 41 );

		// Хук регистрации маршрутов REST API
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		add_action( 'wp_enqueue_scripts', function () {
			if ( is_product() || is_product_category() ) {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_style( 'stock-display-styles', SSW_PLUGIN_URL . '/assets/css/stock-display.css' );
				wp_enqueue_script( 'category-popup-script', SSW_PLUGIN_URL . '/assets/js/category-popup.js', [ 'jquery' ], SSW_PLUGIN_VERSION, true );
			}
		} );

		add_action( 'wp_footer', function () {
			if ( is_product_category() ) {
				$this->stock_display->render_popup();
			}
		} );

	}

	/**
	 * Метод для тестового импорта данных со склада.
	 * Обрабатывает GET-запрос `import-warehouse-test-data`, загружает тестовые данные из XLS-файла и
	 * добавляет их в базу данных.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function handle_test_import() {
		if ( isset( $_GET['import-warehouse-test-data'] ) && current_user_can( 'manage_options' ) ) {
			if ( ! $this->database_handler->check_table_exists() ) {
				$this->database_handler->delete_table();
			}

			$this->database_handler->create_table();

			$data = $this->file_importer->parse_xls_file( SSW_PLUGIN_DIR . '/test-data.xls' );

			$result = $this->database_handler->update_stock( $data );

			$success_count = count( $result['successes'] );
			$error_count   = count( $result['errors'] );

			if ( $success_count > 0 ) {
				echo sprintf( 'Успешно обработано %d записей.', $success_count );
			} else {
				echo 'Не удалось обработать ни одной записи.';
			}

			if ( $error_count > 0 ) {
				echo sprintf( 'Произошло ошибок: %d.', $error_count );
			}

			exit;
		}
	}

	/**
	 * Отображение информации о складских остатках на странице продукта.
	 * Проверяет, является ли текущая страница продуктом WooCommerce и выводит информацию о доступных складах.
	 *
	 * @return void
	 */
	public function display_product_stock_info() {
		if ( is_product() || is_product_category() ) {
			global $product;
			$sku = $product->get_sku();

			if ( $sku ) {
				$stock_data = $this->database_handler->get_stock_by_sku( $sku );
				if ( $stock_data ) {
					echo $this->stock_display->display_stock_info( $stock_data );
				}
			}
		}
	}

	/**
	 * Регистрация REST API маршрутов.
	 * Регистрирует маршрут для обновления складских остатков. Проверяет доступ на основе секретного ключа.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'ssw/v1', '/update-stock', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this->rest_api_handler, 'update_stock' ],
			'permission_callback' => function ( $request ) {
				$provided_key = $request->get_param( 'secret_key' );

				return $provided_key === SSW_REST_API_SECRET;
			},
		] );
	}
}