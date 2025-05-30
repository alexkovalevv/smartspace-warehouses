<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс SSW_DatabaseHandler отвечает за взаимодействие с базой данных.
 * Содержит методы для создания таблиц, вставки, обновления и выборки данных.
 *
 * @author  Alex Kovalev <alex.kovalevv@gmail.com> <Telegram:@alex_kovalevv>
 */
class SSW_DatabaseHandler {
	/**
	 * Экземпляр глобального объекта $wpdb.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Имя таблицы в базе данных.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Конструктор класса DatabaseHandler.
	 * Получает объект $wpdb для взаимодействия с базой данных.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $this->wpdb->prefix . 'ssw_warehouses_stock';
	}

	/**
	 * Проверяет, существует ли таблица в базе данных.
	 *
	 * @return bool Возвращает true, если таблица существует, иначе false.
	 */
	public function check_table_exists(): bool {
		$query = $this->wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->table_name
		);

		// Выполняем запрос и проверяем результат
		$result = $this->wpdb->get_var( $query );

		return ! empty( $result );
	}

	/**
	 * Удаляет таблицу из базы данных.
	 *
	 * @return bool Вернет true, если таблица успешно удалена, иначе false.
	 */
	public function delete_table(): bool {
		$query = "DROP TABLE IF EXISTS {$this->table_name}";

		$result = $this->wpdb->query( $query );

		return $result !== false;
	}

	/**
	 * Создает таблицу в базе данных, если она не существует.
	 *
	 * @return void
	 */
	public function create_table() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        sku VARCHAR(100) NOT NULL, -- Колонка 'Артикул'
        available_in_gorkogo INT(11) DEFAULT 0 NOT NULL, -- Колонка 'Магазин Горького 35'
        available_in_main_stock INT(11) DEFAULT 0 NOT NULL, -- Колонка 'Основной склад'
        pre_order TINYINT(1) DEFAULT 0 NOT NULL, -- Колонка 'Предзаказ'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, -- Дата и время создания
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Дата и время обновления
        PRIMARY KEY (id)
    ) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Выполняет миграцию таблицы, добавляя колонку pre_order, если она не существует
	 *
	 * @return bool Результат выполнения миграции
	 */
	public function migrate_table_to_v105(): bool {
		if ( ! $this->check_table_exists() ) {
			return false;
		}

		// Проверяем, существует ли колонка pre_order
		$column_exists = $this->wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name} LIKE 'pre_order'" );

		if ( empty( $column_exists ) ) {
			// Добавляем колонку pre_order
			$result = $this->wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN pre_order TINYINT(1) DEFAULT 0 NOT NULL AFTER available_in_main_stock" );

			if ( $result === false ) {
				wc_get_logger()->error( 'SmartSpace Warehouses: Ошибка при добавлении колонки pre_order в таблицу ' . $this->table_name, [ 'source' => 'smartspace-warehouses' ] );

				return false;
			}

			wc_get_logger()->info( 'SmartSpace Warehouses: Колонка pre_order успешно добавлена в таблицу ' . $this->table_name, [ 'source' => 'smartspace-warehouses' ] );

			return true;
		}

		return true;
	}

	/**
	 * Возвращает складские остатки по артикулу.
	 *
	 * @param string $sku Артикул для поиска.
	 *
	 * @return object|null Результат SQL-запроса в виде объекта, либо null, если запись не найдена.
	 */
	public function get_stock_by_sku( string $sku ): ?object {
		return $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT available_in_gorkogo, available_in_main_stock, pre_order FROM {$this->table_name} WHERE sku = %s",
			$sku
		) );
	}

	/**
	 * Выполняет обновление существующей записи в базе данных.
	 *
	 * @param string $sku Артикул (ключ записи).
	 * @param int $gorkogo_shop Количество на складе Горького.
	 * @param int $main_stock Количество на основном складе.
	 * @param int $pre_order Предзаказ (1 - да, 0 - нет).
	 *
	 * @return array Результат операции с сообщением об успехе или ошибке.
	 */
	private function update_existing_stock( string $sku, int $gorkogo_shop, int $main_stock, int $pre_order = 0 ): array {
		$result = $this->wpdb->update(
			$this->table_name,
			[
				'available_in_gorkogo'    => intval( $gorkogo_shop ),
				'available_in_main_stock' => intval( $main_stock ),
				'pre_order'               => intval( $pre_order ),
				'updated_at'              => current_time( 'mysql' ),
			],
			[ 'sku' => $sku ],
			[ '%d', '%d', '%d', '%s' ],
			[ '%s' ]
		);

		return $result !== false ?
			[ 'sku' => $sku, 'message' => 'Успешно обновлено' ] :
			[ 'sku' => $sku, 'message' => 'Ошибка обновления' ];
	}

	/**
	 * Выполняет вставку новой записи в базу данных.
	 *
	 * @param string $sku Артикул (ключ записи).
	 * @param int $gorkogo Количество на складе Горького.
	 * @param int $main_stock Количество на основном складе.
	 * @param int $pre_order Предзаказ (1 - да, 0 - нет).
	 *
	 * @return bool Результат операции с сообщением об успехе или ошибке.
	 */
	private function insert_new_stock( string $sku, int $gorkogo, int $main_stock, int $pre_order = 0 ): bool {
		return $this->wpdb->insert(
			$this->table_name,
			[
				'sku'                     => $sku,
				'available_in_gorkogo'    => $gorkogo,
				'available_in_main_stock' => $main_stock,
				'pre_order'               => $pre_order,
				'created_at'              => current_time( 'mysql' ),
				'updated_at'              => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%d', '%d', '%s', '%s' ]
		);
	}

	/**
	 * Обновляет или добавляет складские остатки в базе данных.
	 * Если запись существует, она обновляется, иначе добавляется новая.
	 *
	 * @param array $items Массив данных складских остатков. Каждый элемент должен содержать:
	 *                     - 'sku' - артикул;
	 *                     - 'available_in_gorkogo' - количество в магазине Горького;
	 *                     - 'available_in_main_stock' - количество на основном складе;
	 *                     - 'pre_order' - предзаказ (опционально).
	 *
	 * @return array Возвращает массив с успешными операциями ('successes') и ошибками ('errors').
	 */
	public function update_stock( array $items ): array {
		$errors    = [];
		$successes = [];

		foreach ( $items as $item ) {
			$sku          = $item['sku'] ?? null;
			$gorkogo_shop = $item['available_in_gorkogo'] ?? null;
			$main_stock   = $item['available_in_main_stock'] ?? null;
			$pre_order    = $item['pre_order'] ?? 0;

			if ( empty( $sku ) || ! isset( $gorkogo_shop ) || ! isset( $main_stock ) ) {
				$errors[] = [ 'sku' => $sku, 'message' => 'Отсутствуют обязательные поля' ];
				continue;
			}

			// Если pre_order равен 1, находим соответствующий WooCommerce продукт и включаем предзаказ
			if ( $pre_order == 1 ) {
				$product_id = wc_get_product_id_by_sku( $sku );
				if ( $product_id ) {
					// Устанавливаем мета-данные продукта для включения предзаказа
					update_post_meta( $product_id, '_backorders', 'notify' );
					update_post_meta( $product_id, '_manage_stock', 'yes' );
				}
			}

			$existing_item = $this->wpdb->get_row( $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE sku = %s",
				$sku
			) );

			if ( $existing_item ) {
				$result          = $this->update_existing_stock( $sku, $gorkogo_shop, $main_stock, $pre_order );
				$success_message = "Данные о товаре успешно обновлены!";
				$error_message   = "Не удалось обновить товар!";
			} else {
				$result          = $this->insert_new_stock( $sku, $gorkogo_shop, $main_stock, $pre_order );
				$success_message = "Новый товар успешно добавлен!";
				$error_message   = "Не удалось добавить товар!";
			}

			// Сортировка успешных и ошибочных результатов
			if ( $result ) {
				$successes[] = [ 'sku' => $sku, 'message' => $success_message ];
			} else {
				$errors[] = [ 'sku' => $sku, 'message' => $error_message ];
			}
		}

		return [ 'successes' => $successes, 'errors' => $errors ];
	}
}