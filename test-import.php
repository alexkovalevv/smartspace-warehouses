<?php
/**
 * Import test data
 * @author Alexander Kovalev <alex.kovalevv@gmail.com> <Tg:@alex_kovalevv>
 * @copyright (c) 21.01.2025, CreativeMotion
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function ssw_parse_xls_file( $file_path ) {
	try {
		try {
			$spreadsheet = IOFactory::load( $file_path );

			$worksheet = $spreadsheet->getActiveSheet();

			// Получаем все строки таблицы
			$rows = $worksheet->toArray( null, true, true, true );

			// Поля, которые мы ищем
			$required_columns = [
				'Артикул',
				'Наименование',
				'Магазин Горького 35, можно забрать сейчас',
				'Основной склад, заказать самовывоз на следующий день после 16:00 (кроме воскресенья)'
			];

			// Поля для новой структуры
			$english_keys = [
				'Артикул'                                                                              => 'article',
				'Магазин Горького 35, можно забрать сейчас'                                            => 'available_in_gorkogo',
				'Основной склад, заказать самовывоз на следующий день после 16:00 (кроме воскресенья)' => 'available_in_main_stock_next_day',
			];

			$header_row     = null;
			$column_indexes = [];

			// Поиск строки с заголовками
			foreach ( $rows as $row_index => $row ) {
				$matches = 0;
				foreach ( $row as $column_name ) {
					if ( in_array( $column_name, $required_columns ) ) {
						$matches ++;
					}
				}

				// Если нашли строку с заголовками
				if ( $matches >= count( $required_columns ) / 2 ) {
					$header_row = $row_index;
					// Сохранение индексов колонок
					foreach ( $row as $column_index => $column_name ) {
						if ( in_array( $column_name, $required_columns ) ) {
							$column_indexes[ $column_name ] = $column_index;
						}
					}
					break;
				}
			}

			if ( ! $header_row || count( $column_indexes ) < count( $required_columns ) ) {
				throw new Exception( "Не удалось найти соответствующую строку с заголовками или не все колонки найдены." );
			}

			// Начинаем обработку строк с данных, следующих после заголовков
			$result = [];
			foreach ( $rows as $row_index => $row ) {
				if ( $row_index <= $header_row ) {
					continue; // Пропускаем строки до заголовков
				}

				if ( empty( $row[ $column_indexes['Артикул'] ] ) ) {
					continue;
				}

				$entry = [];
				foreach ( $english_keys as $original_key => $translated_key ) {
					$entry[ $translated_key ] = $row[ $column_indexes[ $original_key ] ] ?? null;
				}

				$result[] = $entry;
			}

			return $result;

		} catch ( Exception $e ) {
			echo "Ошибка: " . $e->getMessage();

			return [];
		}

	} catch ( Exception $e ) {
		echo "Ошибка: " . $e->getMessage();

		return [];
	}
}


function ssw_insert_data_into_database( $data ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'ssw_warehouses_stock';

	if ( empty( $data ) ) {
		echo "Нет данных для вставки в базу данных.\n";

		return;
	}

	foreach ( $data as $row ) {
		$wpdb->insert(
			$table_name,
			[
				'article'                          => $row['article'],
				'available_in_gorkogo'             => $row['available_in_gorkogo'],
				'available_in_main_stock_next_day' => $row['available_in_main_stock_next_day'],
				'created_at'                       => current_time( 'mysql' ),
				'updated_at'                       => current_time( 'mysql' )
			],
			[
				'%s',
				'%d',
				'%d',
				'%s'
			]
		);

		if ( $wpdb->last_error ) {
			echo "Ошибка при вставке данных: " . $wpdb->last_error . "<br>";
		} else {
			echo "Данные успешно вставлены!, Артикул: {$row['article']}<br>";
		}
	}
}