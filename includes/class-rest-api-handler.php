<?php
// Запрещаем прямой доступ к файлу
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс SSW_RestAPIHandler отвечает за обработку REST API запросов.
 * Предназначен для обновления данных о наличии товаров через REST API запросы.
 *
 * @author  Alex Kovalev <alex.kovalevv@gmail.com> <Telegram:@alex_kovalevv>
 */
class SSW_RestAPIHandler {

	/**
	 * Обработчик базы данных, используемый для обновления данных о наличии товаров.
	 *
	 * @var object $database_handler Экземпляр объекта, взаимодействующего с базой данных.
	 */
	private $database_handler;

	/**
	 * Конструктор класса.
	 *
	 * @param object $database_handler Экземпляр обработчика базы данных.
	 */
	public function __construct( $database_handler ) {
		$this->database_handler = $database_handler;
	}

	/**
	 * Обрабатывает запрос на обновление данных о наличии товаров.
	 * Принимает массив товаров из API-запроса и передает его в обработчик базы данных.
	 *
	 * @param WP_REST_Request $request Объект REST API запроса.
	 *
	 * @return WP_REST_Response Объект REST API ответа, содержащий статус операции.
	 */
	public function update_stock( WP_REST_Request $request ): WP_REST_Response {
		$items = $request->get_param( 'items' );

		if ( ! is_array( $items ) || empty( $items ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Некорректные или отсутствующие данные о товарах.',
			], 400 ); // HTTP-статус 400: Неверный запрос
		}

		// Обновляем полученные данные в базе
		$result = $this->database_handler->update_stock( $items );

		// Определяем HTTP-статус ответа — 200 (успех) или 207 (частичные ошибки)
		$status = empty( $result['errors'] ) ? 200 : 207;

		return new WP_REST_Response( [
			'success'  => empty( $result['errors'] ),
			'messages' => $result,
		], $status );
	}
}