# Pluschat API — текущая серверная реализация

Все API-маршруты находятся под `/api/v1`. Запросы к защищённым маршрутам используют заголовок `Authorization: Bearer <token>`. Тело JSON-запроса должно быть корректным JSON. Ошибки API возвращаются в JSON без SQL, stack trace, паролей или ключей.

## Регистрация и вход

- `POST /api/v1/auth/register`: `email`, `password`, `first_name`; `last_name` и `birth_date` необязательны. Пароль — 10–200 символов. Телефон и SMS не требуются.
- Сервер отправляет шестизначный email-код, который действует 15 минут; в базе хранится только SHA-256-хеш кода, связанный с нормализованным email.
- `POST /api/v1/auth/verify`: `email` и `code`.
- `POST /api/v1/auth/resend-verification`: повторная отправка кода с ограничением частоты запросов.
- `POST /api/v1/auth/login`: `login` (email или ник), `password`, `device_key`; `device_name` и `platform` необязательны. Email должен быть подтверждён.
- После подтверждения email пользователь задаёт уникальный ник через `POST /api/v1/auth/profile`. Пока профиль не завершён, остальные защищённые функции недоступны.
- `GET /api/v1/auth/me` возвращает сведения о текущем аккаунте; `POST /api/v1/auth/logout` отзывает текущий токен.
- `POST /api/v1/auth/profile`, `GET/POST /api/v1/settings` — профиль и настройки приватности.
- 2FA через TOTP необязательна: `POST /api/v1/auth/2fa/setup`, `/confirm`, `/verify` и `/disable`. При включённой 2FA обычный вход выдаёт challenge вместо access token.

Почта отправляется через PHP `mail()`; хостинг должен разрешать исходящую почту. Для домена отправителя рекомендуется настроить SPF, DKIM и DMARC. Успешная статическая проверка не подтверждает реальную доставку писем.

## Профиль, контакты и безопасность

- `POST /api/v1/me/avatar` — загрузка аватара; `GET /api/v1/me/avatar` — получение через авторизованный API.
- `GET /api/v1/users/search` и `GET /api/v1/search` — поиск пользователей, групп и каналов.
- `POST /api/v1/users/{id}/block` и `/unblock` — блокировка пользователей.
- `GET /api/v1/devices`, `POST /api/v1/devices/{id}/revoke` и `POST /api/v1/auth/sessions/revoke-others` — управление устройствами и сессиями.
- `POST /api/v1/security/keys` регистрирует открытый ключ устройства и подписанный prekey. `GET /api/v1/security/keys/{userId}` отдаёт ключи собеседника только при допустимом личном диалоге и отсутствии блокировки.
- `POST /api/v1/reports` — отправка жалобы. Просмотр жалоб через `GET /api/v1/admin/reports` требует записи в таблице `admin_users` с разрешённой ролью.

## Сообщения, группы и каналы

- `POST /api/v1/conversations` создаёт диалог; `GET /api/v1/conversations` возвращает список доступных диалогов.
- `GET/POST /api/v1/conversations/{id}/messages` — история и отправка сообщений.
- `POST /api/v1/messages/{id}/edit`, `/delete`, `/reactions`, `/reactions/remove` — редактирование, удаление и реакции.
- `POST /api/v1/conversations/{id}/read` — отметка сообщений прочитанными.
- `POST /api/v1/messages/{id}/attachments`, `GET /api/v1/attachments/{id}`, `POST /api/v1/attachments/{id}/delete` — вложения. Сервер принимает только клиентски зашифрованные байты с маркером `PCF1` и версией `pc-e2ee-v1`.
- Группы: `GET/POST /api/v1/groups`, `GET /api/v1/groups/discover`, `POST /api/v1/groups/{id}/join`, `/leave`, `/invite` (body: `user_id`; owner/admin/moderator only), `GET/POST /api/v1/groups/{id}/messages`.
- Каналы: `GET/POST /api/v1/channels`, `GET /api/v1/channels/discover`, `POST /api/v1/channels/{id}/subscribe`, `GET/POST /api/v1/channels/{id}/posts`.
- Темы: `GET/POST /api/v1/groups/{id}/topics`, `GET/POST /api/v1/channels/{id}/topics`, `GET/POST /api/v1/topics/{id}/messages`; доступ зависит от членства и роли.
- Друзья, сохранённые сообщения, опросы, папки чатов, закрепление/архив/отключение уведомлений, истории и уведомления реализованы отдельными PHP-модулями и используют MySQL.
- Отправка сообщений и вложений ограничивается по частоте. Личные сообщения блокируются, если один из участников заблокировал другого.

### Формат шифрованного сообщения

Клиент должен отправлять поле `body` как JSON-строку с конвертом `pc-e2ee-v1`, алгоритмом `AES-256-GCM`, base64url `nonce` длиной 12 байт, `ciphertext` с тегом аутентификации и строковым `key_id`. Сервер проверяет формат и сохраняет opaque ciphertext, не расшифровывая его. Само наличие этого серверного контракта не доказывает, что Android-клиент правильно создаёт, хранит и обменивает ключи: клиентское E2EE и восстановление ключей требуют отдельной проверки.

## GIF-поиск (GIPHY)

- `GET /api/v1/gifs/search?q=кот&limit=20&offset=0` — поиск GIF для подтверждённого пользователя.
- Ключ читается из приватной конфигурации: `giphy_api_key` или поддерживаемый alias в приватном `confirm.php`. Он не отправляется клиенту.
- `limit` ограничен 1–25, `offset` — 0–499, частота — 30 запросов в минуту на IP.
- Ответ `results` содержит `id`, `title`, `url`, `preview_url`, `width`, `height`. Без ключа возвращается `gif_search_not_configured`; ошибки провайдера не раскрывают секреты.

## Настройки уведомлений

- `GET /api/v1/notification-settings` — возвращает значения категорий и режим предпросмотра.
- Новые личные сообщения, запросы в друзья, сообщения групп/каналов и реакции создают уведомления без текста сообщений; выключенная категория подавляет новые события.
- `POST /api/v1/notification-settings` принимает объект `categories` с булевыми значениями для `direct_messages`, `mentions`, `replies`, `birthdays`, `contact_requests`, `reactions`, `group_invites`, `group_messages`, и необязательный `preview_mode`: `full`, `sender` или `hidden`.
- Для существующей базы примените миграцию `database/migrations/010_notification_settings.sql` через PHP CLI.

## Восстановление пароля

- `POST /api/v1/auth/password/forgot` принимает email; ответ не раскрывает, существует ли аккаунт.
- Письмо содержит одноразовую ссылку на `/password-reset.php`, срок действия — один час.
- `POST /api/v1/auth/password/reset` также принимает token и новый пароль.
- Успешный сброс удаляет активные access-токены аккаунта.

## Аудио- и видеозвонки

- `POST /api/v1/calls` с `conversation_id` и `type: "audio"|"video"` создаёт вызов для участников диалога (2–8 участников).
- `GET /api/v1/calls/active?conversation_id=...`, `GET /api/v1/calls/{id}`, `POST /api/v1/calls/{id}/join`, `/decline`, `/end`.
- `POST /api/v1/calls/{id}/signal` и `GET /api/v1/calls/{id}/signals?after=0` обмениваются краткоживущими SDP/ICE-сигналами.
- PHP хранит только сигнализацию; WebRTC-медиа должно идти напрямую между устройствами или через настроенный TURN. Полный Android WebRTC UI, TURN и реальные звонки должны проверяться отдельно, поэтому наличие серверных маршрутов само по себе не означает готовность звонков к production.

## База данных и обслуживание

- Основная база: MySQL `pluschat`, хост `localhost`. Секреты остаются на хостинге; не загружайте `confirm.php`, `config.php`, пароли или API-ключи в Git.
- Существующие базы обновляются через PHP CLI: `php database/migrate.php` из корня проекта. Скрипт отказывается работать через HTTP и записывает применённые миграции в `schema_migrations`. Сначала сделайте резервную копию.
- Для новой пустой базы используйте `database/schema.sql`, затем выполните миграции через CLI. Не импортируйте схему поверх существующей базы.
- `php database/cleanup.php` через CLI удаляет истёкшие токены/вызовы и старые лимиты. При наличии планировщика хостинга настройте ежедневный запуск.
- `GET /api/v1/health` проверяет подключение к БД и наличие обязательных таблиц, не выводя имена отсутствующих таблиц или секреты.
- Статические проверки: `php tests/check-server.php` и `php tests/check-features.php`. Они не заменяют интеграционные тесты на конкретном хостинге.
