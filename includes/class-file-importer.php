<?php
// Exit if accessed directly
use PhpOffice\PhpSpreadsheet\IOFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс SSW_FileImporter отвечает за импорт данных из файлов (например, XLS) и их обработку.
 * Использует библиотеку PhpSpreadsheet для работы с Excel-файлами.
 */
class SSW_FileImporter {

	/**
	 * Парсит XLS-файл и возвращает данные в виде массива.
	 *
	 * @param string $file_path Путь к файлу для парсинга.
	 *
	 * @return array Массив данных, извлеченных из файла.
	 * @throws Exception Если произошла ошибка при загрузке или обработке файла.
	 */
	public function parse_xls_file( $file_path ): array {
		try {
			$rows           = $this->load_and_get_rows( $file_path );
			$header_row     = $this->find_header_row( $rows );
			$column_indexes = $this->get_column_indexes( $rows, $header_row );

			return $this->process_rows( $rows, $header_row, $column_indexes );
		} catch ( Exception $e ) {
			throw new Exception( "Ошибка при парсинге файла: " . $e->getMessage() );
		}
	}

	/**
	 * Загружает файл и возвращает все строки.
	 *
	 * @param string $file_path Путь к файлу.
	 *
	 * @return array Все строки из файла.
	 * @throws Exception Если файл не удалось загрузить.
	 */
	private function load_and_get_rows( $file_path ): array {
		require SSW_PLUGIN_DIR . '/vendor/autoload.php';

		$spreadsheet = IOFactory::load( $file_path );
		$worksheet   = $spreadsheet->getActiveSheet();

		return $worksheet->toArray( null, true, true, true );
	}

	/**
	 * Находит строку с заголовками.
	 *
	 * @param array $rows Все строки из файла.
	 *
	 * @return int Индекс строки с заголовками.
	 * @throws Exception Если строка с заголовками не найдена.
	 */
	private function find_header_row( $rows ): int {
		$required_columns = $this->get_required_columns();
		foreach ( $rows as $row_index => $row ) {
			if ( $this->is_header_row( $row, $required_columns ) ) {
				return $row_index;
			}
		}
		throw new Exception( "Не удалось найти строку с заголовками." );
	}

	/**
	 * Проверяет, является ли строка заголовком.
	 *
	 * @param array $row Строка из файла.
	 * @param array $required_columns Необходимые колонки.
	 *
	 * @return bool True, если строка является заголовком.
	 */
	private function is_header_row( $row, $required_columns ): bool {
		$matches = 0;
		foreach ( $row as $column_name ) {
			if ( in_array( $column_name, $required_columns ) ) {
				$matches ++;
			}
		}

		return $matches >= count( $required_columns ) / 2;
	}

	/**
	 * Возвращает индексы колонок на основе заголовков.
	 *
	 * @param array $rows Все строки из файла.
	 * @param int $header_row Индекс строки с заголовками.
	 *
	 * @return array Массив индексов колонок.
	 */
	private function get_column_indexes( $rows, $header_row ): array {
		$column_indexes   = [];
		$required_columns = $this->get_required_columns();
		foreach ( $rows[ $header_row ] as $column_index => $column_name ) {
			if ( in_array( $column_name, $required_columns ) ) {
				$column_indexes[ $column_name ] = $column_index;
			}
		}

		return $column_indexes;
	}

	/**
	 * Обрабатывает строки данных и возвращает результат.
	 *
	 * @param array $rows Все строки из файла.
	 * @param int $header_row Индекс строки с заголовками.
	 * @param array $column_indexes Индексы колонок.
	 *
	 * @return array Обработанные данные.
	 */
	private function process_rows( $rows, $header_row, $column_indexes ): array {
		$result       = [];
		$english_keys = $this->get_english_keys();
		foreach ( $rows as $row_index => $row ) {
			if ( $row_index <= $header_row || empty( $row[ $column_indexes['Артикул'] ] ) ) {
				continue;
			}
			$result[] = $this->map_row_to_english_keys( $row, $column_indexes, $english_keys );
		}

		return $result;
	}

	/**
	 * Преобразует строку данных в массив с английскими ключами.
	 *
	 * @param array $row Строка данных.
	 * @param array $column_indexes Индексы колонок.
	 * @param array $english_keys Соответствие русских и английских ключей.
	 *
	 * @return array Преобразованная строка.
	 */
	private function map_row_to_english_keys( array $row, array $column_indexes, array $english_keys ): array {
		$entry = [];
		foreach ( $english_keys as $original_key => $translated_key ) {
			$entry[ $translated_key ] = $row[ $column_indexes[ $original_key ] ] ?? null;
		}

		return $entry;
	}

	/**
	 * Возвращает список необходимых колонок.
	 *
	 * @return array Массив необходимых колонок.
	 */
	private function get_required_columns(): array {
		return [
			'Артикул',
			'Наименование',
			'Магазин Горького 35, можно забрать сейчас',
			'Основной склад, заказать самовывоз на следующий день после 16:00 (кроме воскресенья)'
		];
	}

	/**
	 * Возвращает соответствие русских и английских ключей.
	 *
	 * @return array Массив соответствий.
	 */
	private function get_english_keys(): array {
		return [
			'Артикул'                                                                              => 'sku',
			'Магазин Горького 35, можно забрать сейчас'                                            => 'available_in_gorkogo',
			'Основной склад, заказать самовывоз на следующий день после 16:00 (кроме воскресенья)' => 'available_in_main_stock',
		];
	}
}