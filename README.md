# Beauty Battle

Платформа для проведения голосований с авторизацией через Telegram.

## Требования

- PHP 7.4 или выше
- PostgreSQL
- Composer
- Telegram Bot Token

## Установка

1. Клонируйте репозиторий:
```bash
git clone <repository-url>
cd beautybattle
```

2. Установите зависимости:
```bash
composer install
```

3. Создайте файл `.env` и настройте переменные окружения:
```env
DB_HOST=your_host
DB_NAME=your_database
DB_USER=your_user
DB_PASSWORD=your_password

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=your_bot_username

APP_URL=your_app_url
APP_ENV=production
APP_DEBUG=false
```

4. Создайте необходимые директории:
```bash
mkdir -p uploads/photos uploads/thumbnails cache logs
chmod -R 755 uploads cache logs
```

5. Импортируйте структуру базы данных:
```bash
psql -U your_user -d your_database -f api/database/schema_pg.sql
```

6. Настройте вебхук для Telegram бота:
```bash
php api/bot/set-webhook-render.php
```

## Развертывание на Render

1. Подключите репозиторий в панели Render
2. Создайте новый Web Service
3. Настройте переменные окружения
4. Запустите деплой

## Структура проекта

- `api/` - API endpoints и классы
- `config/` - Конфигурационные файлы
- `public/` - Публичные файлы (CSS, JS, изображения)
- `uploads/` - Загруженные файлы
- `cache/` - Кэш файлы
- `logs/` - Логи (в режиме отладки)

## Лицензия

MIT 