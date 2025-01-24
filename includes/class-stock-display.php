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
		$quantity_html = is_numeric( $quantity )
			? sprintf( '<br>В наличии: <strong>%s</strong>', $this->get_quantity_display( (int) $quantity ) )
			: ( ! empty( $quantity ) ? sprintf( '<br>%s', $quantity ) : '' );

		return sprintf(
			'<div class="custom-stock-info" style="margin-bottom: 20px; font-size: 16px; color: #333; border-radius: 10px; border:1px solid #444;">
            <p style="padding:20px;margin:0;">%s, %s%s</p>
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
		$html = $this->generate_gorkogo_stock_info( $stock_data );
		$html .= $this->generate_main_stock_info( $stock_data );
		$html .= $this->generate_delivery_info( $stock_data );

		return $html;
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
				'<strong style="color:green">можно забрать сейчас</strong>',
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
				'<strong style="color:red">Нет в наличии</strong>',
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
				'можно заказать самовывоз на следующий день после 16:00 (кроме воскресенья)',
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
			$html .= $this->generate_stock_html( 'Доставка при оплате онлайн', $this->get_delivery_message() );
			$html .= $this->generate_stock_html( 'Доставка с оплатой при получении', $this->get_delivery_message( true ) );
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

		return ( $day_of_week == 6 ) ? '<strong style="color:orange">можно забрать в пн после 15:00</strong>' : '<strong style="color:orange">можно забрать завтра после 15:00</strong>';
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
			return $pay_on_delivery ? 'доставим в пн с 18:00 до 22:00' : 'доставим сегодня, до 22:00';
		} elseif ( ( $current_day === 6 && $current_hour >= 14 ) || ( $current_day === 0 ) || ( $current_day === 1 && $current_hour < 12 ) ) {
			// Временной диапазон: с субботы после 14:00 до понедельника 12:00
			return 'доставим в пн с 18:00 до 22:00';
		} elseif ( $current_day >= 1 && $current_day <= 5 ) {
			// Будние дни после понедельника 12:00
			return $pay_on_delivery ? 'доставим завтра с 18:00 до 22:00' : 'доставим с 18:00 до 22:00';
		}

		return '';
	}
}