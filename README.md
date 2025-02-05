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
| `items[].sku`                          | string  | Артикул товара (SKU).                                                           | Обязательно    |
| `items[].available_in_gorkogo`             | integer | Количество товара в магазине на улице Горького.                                 | Обязательно    |
| `items[].available_in_main_stock` | integer | Количество товара, доступного на основном складе (самовывоз на следующий день). | Обязательно    |
| `secret_key`                               | string  | Секретный ключ API для проверки подлинности запроса.                            | Обязательно    |

---

### 3. Пример запроса

Пример отправки запроса с использованием `secret_key` с помощью `cURL`:

```bash
curl -X POST https://example.com/wp-json/ssw/v1/update-stock \
-H "Content-Type: application/json" \
-d '{
  "secret_key": "your_generated_api_key_here",
  "items": [
    {
      "sku": "SKU123",
      "available_in_gorkogo": 20,
      "available_in_main_stock": 30
    },
    {
      "sku": "SKU456",
      "available_in_gorkogo": 10,
      "available_in_main_stock": 15
    }
  ]
}'
```

> **Примечание:**  
> Замените `"your_generated_api_key_here"` на ваш сгенерированный `secret_key`.

---

### 4. Ответы API

#### Успех (200):

Если все товары успешно обновлены или добавлены:

```json
{
  "success": true,
  "messages": {
    "successes": [
      {
        "sku": "SKU123",
        "message": "Новый товар успешно добавлен."
      },
      {
        "sku": "SKU456",
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
        "sku": "SKU123",
        "message": "Новый товар успешно добавлен."
      }
    ],
    "errors": [
      {
        "sku": "SKU789",
        "message": "Отсутствуют обязательные параметры: артикул, available_in_gorkogo или available_in_main_stock."
      }
    ]
  }
}
```

#### Ошибка проверки ключа (401):

Если передан некорректный или отсутствует `secret_key`:

```json
{
  "success": false,
  "message": "Недействительный ключ API."
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

1. **Проверка `secret_key`:**
   Перед обработкой запроса сначала проверяется, передан ли `secret_key`, и соответствует ли он установленному значению.
   Если ключ отсутствует или неверен, возвращается ошибка с кодом `401 Unauthorized`.

2. **Обновление данных:**
   Если запись с артикулом (`sku`) уже существует, ее данные обновляются согласно переданным параметрам.

3. **Добавление новых записей:**
   Если запись с переданным артикулом отсутствует, она добавляется в базу с параметрами `available_in_gorkogo` и
   `available_in_main_stock`.

4. **Ведется лог ошибок:**
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
- Telegram: @alex_kovalevv