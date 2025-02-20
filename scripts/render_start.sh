#!/bin/bash

# Инициализация базы данных
php scripts/init_render_db.php

# Настройка Apache для работы с переменной PORT от Render
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/sites-available/000-default.conf

# Установка вебхука для Telegram бота
php api/bot/set-webhook-render.php

# Запуск Apache
apache2-foreground 