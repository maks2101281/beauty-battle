#!/bin/bash

# Инициализация базы данных
php scripts/init_render_db.php

# Запуск Apache
apache2-foreground 