<?php
/**
 * Создаёт таблицу базы данных для хранения данных о запасах на складах.
 *
 * Таблица включает колонки для информации о запасах, такие как коды, артикулы,
 * наименования и доступность в различных местах. Использует схему базы данных WordPress
 * и обеспечивает совместимость с текущей кодировкой базы данных.
 *
 * @return void
 * @global wpdb $wpdb Класс базы данных WordPress.
 * @author  Alex Kovalev <alex.kovalevv@gmail.com> <Telegram:@alex_kovalevv>
 */

function ssw_create_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . "ssw_warehouses_stock";

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        code VARCHAR(50) NOT NULL, -- Колонка 'Код'
        article VARCHAR(100) NOT NULL, -- Колонка 'Артикул'
        name TEXT NOT NULL, -- Колонка 'Наименование'
        total_stock INT(11) DEFAULT 0 NOT NULL, -- Колонка 'Общий остаток'
        available_in_gorkogo INT(11) DEFAULT 0 NOT NULL, -- Колонка 'Магазин Горького 35'
        available_in_main_stock_next_day INT(11) DEFAULT 0 NOT NULL, -- Колонка 'Основной склад'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, -- Дата и время создания
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Дата и время обновления
        PRIMARY KEY (id)
    ) $charset_collate;";

	dbDelta( $sql );
}
