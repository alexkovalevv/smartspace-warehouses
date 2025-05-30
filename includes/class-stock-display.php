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

	public function render_popup() {
		?>
		<div class="ssw-popup" id="ssw-popup">
			<div class="ssw-popup__overlay"></div>
			<div class="ssw-popup__content">
				<button class="ssw-popup__close">&times;</button>
				<h2 class="ssw-popup__title"></h2>
				<div class="ssw-popup__text"></div>
			</div>
		</div>
		<?php
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

			// Возвращаем только колонку предзаказа для страницы продукта
			if ( is_product() ) {
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
			} elseif ( is_product_category() ) {
				// Для страницы категории отображаем информацию о предзаказе
				$output   = sprintf( 'Предзаказ (доступно с %s)', esc_html( $formatted_date ) );
				$popup_id = 'store-popup-' . get_the_ID();

				// Содержимое всплывающего окна
				$popup_content = sprintf(
					'<div class="ssw-stock-column ssw-preorder-column">
					<h3>Предзаказ:</h3>
					<div class="ssw-custom-stock-info">
						<p><strong>Доступно с %s</strong></p>
					</div>
				</div>',
					esc_html( $formatted_date )
				);

				$popup_content = sprintf(
					'<div class="ssw-stock-info-container ssw-single-column">%s</div>',
					$popup_content
				);

				return sprintf(
					'<div class="ssw-category-stock-info">%s</div>
				<div class="ssw-hidden-content" id="%s" style="display: none;">
					<h2 class="ssw-hidden-content__title">%s</h2>
					<div class="ssw-hidden-content__content">%s</div>
				</div>',
					$output,
					esc_attr( $popup_id ),
					esc_html( get_the_title() ),
					$popup_content
				);
			}

			return '';
		}

		// Стандартное отображение для обычных товаров
		$pickup_html   = $this->generate_gorkogo_stock_info( $stock_data );
		$pickup_html   .= $this->generate_main_stock_info( $stock_data );
		$delivery_html = $this->generate_delivery_info( $stock_data );

		if ( is_product() ) {
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
				'<div class="%s">%s</div>',
				esc_attr( $container_class ),
				$columns_html
			);
		} elseif ( is_product_category() ) {
			$output       = '';
			$total_stores = 0;
			$popup_id     = 'store-popup-' . get_the_ID();

			if ( intval( $stock_data->available_in_gorkogo ) > 0 ) {
				$total_stores ++;
			}
			if ( intval( $stock_data->available_in_main_stock ) > 0 ) {
				$total_stores ++;
			}

			if ( $total_stores > 0 ) {
				$store_word = $this->get_store_word( $total_stores );
				$output     .= sprintf(
					'В наличии в <a href="#" class="ssw-popup__trigger" data-target="%s">%d %s</a>',
					esc_attr( $popup_id ),
					$total_stores,
					$store_word
				);
			} else {
				$output .= 'Нет в наличии';
			}

			if ( intval( $stock_data->available_in_main_stock ) > 0 ) {
				$output .= ', Доставка: ';

				$online_msg = $this->get_online_payment_message( $stock_data );
				$output     .= 'при оплате онлайн, ' . $online_msg;

				$delivery_msg = $this->get_pay_on_delivery_message();
				if ( ! empty( $delivery_msg ) ) {
					$output .= ', оплате при получении, ' . $delivery_msg;
				}
			}

			$popup_content = '';

			if ( ! empty( $pickup_html ) ) {
				$popup_content .= sprintf(
					'<div class="ssw-stock-column %s">
                   <h3>%s</h3>
                   %s
               </div>',
					'ssw-pickup-column',
					'Самовывоз:',
					$pickup_html
				);
			}

			if ( ! empty( $delivery_html ) ) {
				$popup_content .= sprintf(
					'<div class="ssw-stock-column %s">
                   <h3>%s</h3>
                   %s
               </div>',
					'ssw-delivery-column',
					'Доставка:',
					$delivery_html
				);
			}

			$popup_content = sprintf(
				'<div class="ssw-stock-info-container">%s</div>',
				$popup_content
			);

			return sprintf(
				'<div class="ssw-category-stock-info">%s</div>
           <div class="ssw-hidden-content" id="%s" style="display: none;">
               <h2 class="ssw-hidden-content__title">%s</h2>
               <div class="ssw-hidden-content__content">%s</div>
           </div>',
				$output,
				esc_attr( $popup_id ),
				esc_html( get_the_title() ),
				$popup_content
			);
		}

		return '';
	}

	/**
	 * Определяет правильное слово для использования с числом магазинов.
	 *
	 * @param int $count Количество магазинов.
	 *
	 * @return string Подходящее слово для описания магазинов.
	 */
	private function get_store_word( int $count ): string {
		if ( $count % 10 == 1 && $count % 100 != 11 ) {
			return 'магазине';
		} else {
			return 'магазинах';
		}
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