# SmartSpace Warehouses API

Плагин **SmartSpace Warehouses** предоставляет REST API для управления данными о запасах товаров по складам.

## Описание

Этот плагин реализует REST API для обновления или добавления данных о наличии товаров на складах.  
Основной маршрут API: `ssw/v1/update-stock`.

## Установка

1. Скачайте или клонируйте этот проект в папку плагинов вашего WordPress-сайта:  
   Поместите файлы плагина в папку `/wp-content/plugins/smartspace-warehouses`.
2. Активируйте плагин через меню "Плагины" в панели администратора WordPress.
3. Убедитесь, что база данных подготовлена для работы с плагином. Таблица создается автоматически при активации.
4. Установите константу SSW_REST_API_SECRET в smartspace-warehouses.php для верификации запросов

## Использование API

### 1. Маршрут API

**URL:**  
`POST /wp-json/ssw/v1/update-stock`

### 2. Параметры запроса

Отправляйте данные в формате `application/json` (в теле запроса). Параметры:

| Параметр                                   | Тип     | Описание                                                                        | Обязательность |
|--------------------------------------------|---------|---------------------------------------------------------------------------------|----------------|
| `items`                                    | array   | Список товаров с соответствующими параметрами.                                  | Обязательно    |
| `items[].article`                          | string  | Артикул товара (SKU).                                                           | Обязательно    |
| `items[].available_in_gorkogo`             | integer | Количество товара в магазине на улице Горького.                                 | Обязательно    |
| `items[].available_in_main_stock_next_day` | integer | Количество товара, доступного на основном складе (самовывоз на следующий день). | Обязательно    |

> **Примечание:**  
> Если товар с указанным артикулом уже существует в базе данных, данные будут обновлены. Если запись не существует, она
> будет добавлена автоматически.

### 3. Пример запроса

Пример отправки запроса с помощью `cURL`:

```bash
curl -X POST https://example.com/wp-json/ssw/v1/update-stock \
-H "Content-Type: application/json" \
-d '{
  "items": [
    {
      "article": "SKU123",
      "available_in_gorkogo": 20,
      "available_in_main_stock_next_day": 30
    },
    {
      "article": "SKU456",
      "available_in_gorkogo": 10,
      "available_in_main_stock_next_day": 15
    }
  ]
}'
```

### 4. Ответы API

Форма ответа — JSON. Возможные сценарии:

---

#### Успех (200):

Если все товары успешно обновлены или добавлены:

```json
{
  "success": true,
  "messages": {
    "successes": [
      {
        "article": "SKU123",
        "message": "Новый товар успешно добавлен."
      },
      {
        "article": "SKU456",
        "message": "Данные о товаре успешно обновлены."
      }
    ],
    "errors": []
  }
}
```

---

#### Частичный успех/ошибки (207):

Если хотя бы один товар вызывает ошибку, возвращается код `207 Multi-Status` с деталями:

```json
{
  "success": false,
  "messages": {
    "successes": [
      {
        "article": "SKU123",
        "message": "Новый товар успешно добавлен."
      }
    ],
    "errors": [
      {
        "article": "SKU789",
        "message": "Отсутствуют обязательные параметры: артикул, available_in_gorkogo или available_in_main_stock_next_day."
      }
    ]
  }
}
```

---

#### Ошибка формата запроса (400):

Если запрос не содержит параметров или данные переданы неправильно:

```json
{
  "success": false,
  "message": "Не переданы данные о товарах или данные имеют неверный формат."
}
```

---

### 5. Описание логики

1. **Обновление данных:**
   Если запись с артикулом (`article`) уже существует, ее данные обновляются согласно переданным параметрам.

2. **Добавление новых записей:**
   Если запись с переданным артикулом отсутствует, она добавляется в базу с параметрами `available_in_gorkogo` и
   `available_in_main_stock_next_day`.

3. **Ведется лог ошибок:**
   Если хоть один из товаров содержит ошибки (например, отсутствует обязательный параметр), он не обрабатывается, а
   ошибка возвращается в массиве `errors`.

---

## Проверка API

Рекомендуется использовать инструмент **Postman** либо `cURL` для тестирования API.

### Postman:

1. Создайте новый запрос.
2. Укажите метод запроса **POST** и вставьте URL: `https://example.com/wp-json/ssw/v1/update-stock`.
3. В поле **Body** выберите тип **raw** и вставьте JSON с параметрами, как в примере выше.
4. Убедитесь, что заголовок **Content-Type** установлен как `application/json`.
5. Отправьте запрос, чтобы протестировать обработку данных.

---

## Возможные ошибки

- **401 Unauthorized**: Убедитесь, что вы передаете правильный `secret_key` (если такая защита предусмотрена в вашей
  реализации).
- **400 Bad Request**: Убедитесь, что все параметры переданы корректно.
- **207 Multi-Status**: Частичный успех запроса означает, что некоторые элементы обработать не удалось. Сообщения об
  ошибках содержатся в поле `errors`.

---

## Поддержка и помощь

Если у вас возникли вопросы или проблемы, вы можете обратиться за поддержкой:

- Автор: Alex Kovalev
- Email: alex.kovalevv@gmail.com
- Telegram: @alex_kovalevv
- Сайт: [https://alexkovalev.pro](https://alexkovalev.pro)

---
[https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).