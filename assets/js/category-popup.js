/**
 * Скрипты для popup окна на странице категорий
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @version 1.0
 */

(function ($) {
    'use strict';

    jQuery(document).ready(function ($) {
        const $popup = $('#ssw-popup');
        const $popupTitle = $popup.find('.ssw-popup__title');
        const $popupText = $popup.find('.ssw-popup__text');
        const $triggers = $('.ssw-popup__trigger');

        // Обработчик кликов по ссылкам
        $triggers.on('click', function (event) {
            event.preventDefault();

            const targetId = $(this).data('target');
            const $hiddenContent = $('#' + targetId);

            if ($hiddenContent.length) {
                // Обновляем содержимое popup окна
                const title = $hiddenContent.find('.ssw-hidden-content__title').html() || '';
                const html = $hiddenContent.find('.ssw-hidden-content__content').html() || '';

                $popupTitle.html(title);
                $popupText.html(html);

                // Показать окно
                $popup.addClass('is-visible');
            }
        });

        // Закрытие окна
        const closePopup = function () {
            $popup.removeClass('is-visible');
        };

        $popup.find('.ssw-popup__overlay').on('click', closePopup);
        $popup.find('.ssw-popup__close').on('click', closePopup);

        // Закрытие по клавише Escape
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closePopup();
            }
        });
    });


})(jQuery);
