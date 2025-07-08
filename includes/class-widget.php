<?php
/**
 * Пользовательский виджет для фильтрации по статусу товара
 *
 * @author Alexander Kovalev <alex.kovalevv@gmail.com> <Tg:@alex_kovalevv>
 * @copyright (c) 08.07.2025, CreativeMotion
 * @version 1.0
 */

class WC_Widget_Stock_Status_Filter extends WP_Widget
{

    /**
     * Конструктор
     */
    public function __construct()
    {
        parent::__construct(
            'wc_stock_status_filter',
            'Фильтр по наличию товара',
            [
                'description' => 'Фильтрует товары по статусу наличия (в наличии, предзаказ).',
            ]
        );

        add_action('wp_head', [$this, 'add_inline_styles']);
        add_filter('woocommerce_product_query', [$this, 'filter_products_by_stock_status'], 10, 2);
    }

    /**
     * Фильтрация товаров по статусу наличия
     */
    public function filter_products_by_stock_status($q, $instance)
    {
        if (isset($_GET['stock_status'])) {
            $stock_status = sanitize_text_field($_GET['stock_status']);

            // Если выбрано "Все товары", не применяем никаких фильтров
            if ($stock_status === '') {
                return $q;
            }

            // Фильтруем товары по статусу наличия
            if ($stock_status === 'instock' || $stock_status === 'backorder') {
                $q->set('meta_query', [
                    [
                        'key' => '_stock_status',
                        'value' => $stock_status,
                        'compare' => '=',
                    ]
                ]);
            }
        }
        return $q;
    }

    /**
     * Добавление inline CSS стилей
     */
    public function add_inline_styles()
    {
        ?>
        <style>
            .wcsf-stock-status-filter {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                list-style: none;
                padding: 0;
                margin: 0;
                line-height: 1.2;
            }

            .wcsf-stock-status-filter li {
                margin: 3px 0;
                width: 100%;
                text-align: left;
            }

            .wcsf-stock-status-filter li a {
                display: flex;
                align-items: center;
                text-decoration: none;
                padding: 2px 8px;
                border-radius: 3px;
                transition: all 0.2s ease;
                color: #333;
            }

            .wcsf-stock-status-filter li a:hover {
                background-color: #f7f7f7;
            }

            .wcsf-checkbox {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 1px solid #ccc;
                border-radius: 3px;
                margin-right: 8px;
                position: relative;
                background: #fff;
            }

            .wcsf-checkbox-checked:after {
                content: '';
                position: absolute;
                top: 2px;
                left: 2px;
                width: 10px;
                height: 10px;
                background: #333;
                border-radius: 1px;
            }
        </style>
        <?php
    }

    /**
     * Отображение виджета в сайдбаре
     */
    public function widget($args, $instance)
    {

        if (is_product() || is_front_page()) {
            return;
        }

        echo $args['before_widget'];

        $title = !empty($instance['title']) ? $instance['title'] : 'Наличие товара';
        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];

        $current_status = isset($_GET['stock_status']) ? sanitize_text_field($_GET['stock_status']) : '';

        $base_url = remove_query_arg('stock_status', add_query_arg(null, null));

        $statuses = [
            '' => 'Все товары',
            'instock' => 'В наличии',
            'backorder' => 'Предзаказ'
        ];

        echo '<ul class="wcsf-stock-status-filter">';
        foreach ($statuses as $status => $label) {
            $url = $status ? add_query_arg('stock_status', $status, $base_url) : remove_query_arg('stock_status', $base_url);
            $is_chosen = ($status === '' && $current_status === '') || ($status !== '' && $status === $current_status);

            $checkbox_class = 'wcsf-checkbox';
            if ($is_chosen) {
                $checkbox_class .= ' wcsf-checkbox-checked';
            }

            printf(
                '<li class="wcsf-item wcsf-%s %s"><a href="%s"><span class="%s"></span>%s</a></li>',
                $status ? esc_attr($status) : 'empty',
                $is_chosen ? 'wcsf-chosen' : '',
                esc_url($url),
                esc_attr($checkbox_class),
                esc_html($label)
            );
        }
        echo '</ul>';

        echo $args['after_widget'];
    }

    /**
     * Настройки виджета в админке
     */
    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : 'Наличие товара';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Заголовок:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    /**
     * Сохранение настроек виджета
     */
    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}