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
		} elseif ( intval( $stock_data->available_in_main_stock ) > 0 ) {
			$message = $this->get_gorkogo_pickup_message();

			return $this->generate_stock_html(
				$title,
				$message,
				$stock_data->available_in_main_stock
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
			$html .= $this->generate_stock_html( 'Оплата онлайн', $this->get_delivery_message() );
			$html .= $this->generate_stock_html( 'Опалат при получении', $this->get_delivery_message( true ) );
		}

		return $html;
	}

	/**
	 * Возвращает сообщение о возможности забрать товар в магазине "Горького 35".
	 *
	 * @return string Сообщение.
	 */
	private function get_gorkogo_pickup_message(): string {
		$day_of_week = date( 'w' );

		return ( $day_of_week == 6 ) ? '(в пн после 15:00)' : '(завтра после 15:00)';
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
	 * Возвращает сообщение о доставке в зависимости от текущего времени.
	 *
	 * @return string Сообщение о доставке.
	 */
	private function get_delivery_message( $pay_on_delivery = false ): string {
		$current_time = new DateTime( 'now', new DateTimeZone( 'Asia/Yekaterinburg' ) );
		$current_hour = intval( $current_time->format( 'H' ) );
		$current_day  = intval( $current_time->format( 'w' ) ); // Номер дня недели (0 - воскресенье, 6 - суббота)

		if ( $current_day === 6 && $current_hour < 14 ) {
			// Сегодня суббота и сейчас раньше 14:00
			return $pay_on_delivery ? '(в пн с 18:00 до 22:00)' : '(сегодня, до 22:00)';
		} elseif ( ( $current_day === 6 && $current_hour >= 14 ) || ( $current_day === 0 ) || ( $current_day === 1 && $current_hour < 12 ) ) {
			// Временной диапазон: с субботы после 14:00 до понедельника 12:00
			return '(в пн с 18:00 до 22:00)';
		} elseif ( $current_day >= 1 && $current_day <= 5 ) {
			// Будние дни после понедельника 12:00
			return $pay_on_delivery ? '(завтра с 18:00 до 22:00)' : '(с 18:00 до 22:00)';
		}

		return '';
	}
}