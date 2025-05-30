<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс SSW_StockDisplay отвечает за отображение информации о наличии товаров на складах.
 * Содержит методы для генерации HTML-кода и вывода информации на странице товара.
 *
 * @author  Alex Kovalev <alex.kovalevv@gmail.com> <Telegram:@alex_kovalevv>
 */
class SSW_StockDisplay {

	private $current_time;

	public function __construct() {
		$this->current_time = new DateTime( 'now', new DateTimeZone( 'Asia/Yekaterinburg' ) );
	}

	/**
	 * Генерирует HTML-блок с информацией о наличии товара.
	 *
	 * @param string $title Заголовок блока.
	 * @param string $message Сообщение о наличии.
	 * @param int|string $quantity Количество товара (опционально).
	 *
	 * @return string HTML-код блока.
	 */
	public function generate_stock_html( string $title, string $message, string $quantity = '' ): string {
		$quantity_html = '';

		if ( is_numeric( $quantity ) ) {
			$quantity_display = $this->get_quantity_display( (int) $quantity );
			$quantity_class   = (int) $quantity > 0 ? 'ssw-stock-quantity' : 'ssw-stock-quantity ssw-out-of-stock';
			$quantity_html    = sprintf( '<br><span class="%s">В наличии: %s</span>', $quantity_class, $quantity_display );
		} elseif ( ! empty( $quantity ) ) {
			$quantity_html = sprintf( '<br>%s', $quantity );
		}

		return sprintf(
			'<div class="ssw-custom-stock-info">
            <p><strong>%s</strong>, %s%s</p>
        </div>',
			esc_html( $title ),
			$message,
			$quantity_html
		);
	}

	/**
	 * Отображает информацию о наличии товара на складах.
	 *
	 * @param object $stock_data Данные о наличии товара.
	 *
	 * @return string HTML-код с информацией о наличии.
	 */
	public function display_stock_info( object $stock_data ): string {
		// Проверка на предзаказ, когда нет остатков
		if ( intval( $stock_data->available_in_gorkogo ) <= 0 &&
		     intval( $stock_data->available_in_main_stock ) <= 0 &&
		     intval( $stock_data->pre_order ) === 1 ) {

			// Формируем дату доступности (сегодня + 10 дней)
			$availability_date = clone $this->current_time;
			$availability_date->modify( '+10 days' );
			$formatted_date = $availability_date->format( 'd.m.Y' );

			// Возвращаем только колонку предзаказа
			return sprintf(
				'<div class="ssw-stock-info-container ssw-single-column">
					<div class="ssw-stock-column ssw-preorder-column">
						<h3>Предзаказ:</h3>
						<div class="ssw-custom-stock-info">
							<p><strong>Доступно с %s</strong></p>
						</div>
					</div>
				</div>',
				esc_html( $formatted_date )
			);
		}

		// Стандартное отображение для обычных товаров
		$pickup_html = $this->generate_gorkogo_stock_info( $stock_data );
		$pickup_html .= $this->generate_main_stock_info( $stock_data );

		$delivery_html = $this->generate_delivery_info( $stock_data );

		$columns = [];
		if ( ! empty( $pickup_html ) ) {
			$columns[] = [
				'title'   => 'Самовывоз:',
				'content' => $pickup_html,
				'class'   => 'ssw-pickup-column',
			];
		}
		if ( ! empty( $delivery_html ) ) {
			$columns[] = [
				'title'   => 'Доставка:',
				'content' => $delivery_html,
				'class'   => 'ssw-delivery-column',
			];
		}

		if ( empty( $columns ) ) {
			return '';
		}

		$columns_html = '';
		foreach ( $columns as $column ) {
			$columns_html .= sprintf(
				'<div class="ssw-stock-column %s">
                <h3>%s</h3>
                %s
            </div>',
				esc_attr( $column['class'] ),
				esc_html( $column['title'] ),
				$column['content']
			);
		}

		$container_class = count( $columns ) > 1 ? 'ssw-stock-info-container' : 'ssw-stock-info-container ssw-single-column';

		return sprintf(
			'<div class="%s">
            %s
        </div>',
			esc_attr( $container_class ),
			$columns_html
		);
	}

	/**
	 * Генерирует HTML-код для информации о наличии в магазине "Горького 35".
	 *
	 * @param object $stock_data Данные о наличии товара.
	 *
	 * @return string HTML-код.
	 */
	private function generate_gorkogo_stock_info( object $stock_data ): string {
		$title = "Магазин Горького 35";

		if ( intval( $stock_data->available_in_gorkogo ) > 0 ) {
			return $this->generate_stock_html(
				$title,
				'(сегодня до 21:00)',
				$stock_data->available_in_gorkogo
			);
		} elseif ( intval( $stock_data->available_in_main_stock ) > 0 ) { // Если в магазине на Горького нет в наличии
			$current_day = intval( $this->current_time->format( 'w' ) ); // Номер дня недели (0 - воскресенье, 6 - суббота)
			$message     = ( $current_day == 6 ) ? '(в пн после 15:00)' : '(завтра после 15:00)';

			return $this->generate_stock_html(
				$title,
				$message,
				$stock_data->available_in_gorkogo
			);
		} else {
			return $this->generate_stock_html(
				$title,
				'<strong>Нет в наличии</strong>',
				0
			);
		}
	}

	/**
	 * Генерирует HTML-код для информации о наличии на основном складе.
	 *
	 * @param object $stock_data Данные о наличии товара.
	 *
	 * @return string HTML-код.
	 */
	private function generate_main_stock_info( object $stock_data ): string {
		if ( intval( $stock_data->available_in_main_stock ) > 0 ) {
			return $this->generate_stock_html(
				'Склад Екатеринбург',
				'(завтра после 16:00)',
				$stock_data->available_in_main_stock
			);
		}

		return '';
	}

	/**
	 * Генерирует HTML-код для информации о доставке.
	 *
	 * @param object $stock_data Данные о наличии товара.
	 *
	 * @return string HTML-код.
	 */
	private function generate_delivery_info( object $stock_data ): string {
		$html = '';

		if ( intval( $stock_data->available_in_main_stock ) > 0 ) {
			$html .= $this->generate_stock_html( 'Оплата онлайн', $this->get_online_payment_message( $stock_data ) );
			$html .= $this->generate_stock_html( 'Оплата при получении', $this->get_pay_on_delivery_message() );
		}

		return $html;
	}

	/**
	 * Возвращает отображение количества товара.
	 *
	 * @param int $quantity Количество товара.
	 *
	 * @return string Отображение количества.
	 */
	private function get_quantity_display( int $quantity ): string {
		return $quantity > 5 ? 'много' : $quantity;
	}

	/**
	 * Возвращает сообщение о доставке для онлайн-оплаты.
	 *
	 * @return string Сообщение о доставке.
	 */
	private function get_online_payment_message( object $stock_data ): string {
		$current_hour = intval( $this->current_time->format( 'H' ) );
		$current_day  = intval( $this->current_time->format( 'w' ) ); // Номер дня недели (0 - воскресенье, 6 - суббота)

		if ( intval( $stock_data->available_in_gorkogo ) > 0 ) {
			if ( $current_hour <= 20 ) {
				return '(сегодня до 22:00)';
			} else {
				return '(завтра до 22:00)';
			}
		} else {
			if ( $current_hour <= 18 ) {
				return '(сегодня до 22:00)';
			} elseif ( $current_day != 0 ) { // Не воскресенье
				return '(завтра до 22:00)';
			} else { // Воскресенье
				return '(в пн до 22:00)';
			}
		}
	}

	/**
	 * Возвращает сообщение о доставке для оплаты при получении.
	 *
	 * @return string Сообщение о доставке.
	 */
	private function get_pay_on_delivery_message(): string {
		$current_hour = intval( $this->current_time->format( 'H' ) );
		$current_day  = intval( $this->current_time->format( 'w' ) ); // Номер дня недели (0 - воскресенье, 6 - суббота)

		if ( $current_day === 6 && $current_hour < 14 ) {
			// Сегодня суббота и сейчас раньше 14:00
			return '(в пн с 18:00 до 22:00)';
		} elseif ( ( $current_day === 6 && $current_hour >= 14 ) || ( $current_day === 0 ) || ( $current_day === 1 && $current_hour < 12 ) ) {
			// Временной диапазон: с субботы после 14:00 до понедельника 12:00
			return '(в пн с 18:00 до 22:00)';
		} elseif ( $current_day >= 1 && $current_day <= 5 ) {
			// Будние дни после понедельника 12:00
			return '(завтра с 18:00 до 22:00)';
		}

		return '';
	}
}