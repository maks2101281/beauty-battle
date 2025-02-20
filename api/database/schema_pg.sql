-- Таблица для хранения данных Telegram пользователей
CREATE TABLE telegram_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    chat_id BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_username ON telegram_users(username);
CREATE INDEX idx_chat_id ON telegram_users(chat_id);

-- Таблица для хранения кодов подтверждения
CREATE TABLE verification_codes (
    id SERIAL PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    code VARCHAR(4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_username_created ON verification_codes(telegram_username, created_at);

-- Таблица для хранения токенов авторизации
CREATE TABLE auth_tokens (
    id SERIAL PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL '30 days'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE INDEX idx_token ON auth_tokens(token);
CREATE INDEX idx_username_auth ON auth_tokens(telegram_username);

-- Таблица для отслеживания попыток авторизации
CREATE TABLE auth_attempts (
    id SERIAL PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_type VARCHAR(20) NOT NULL CHECK (attempt_type IN ('code_request', 'code_verify')),
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_username_ip_type ON auth_attempts(telegram_username, ip_address, attempt_type, created_at);

-- Таблица участников
CREATE TABLE contestants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    photo VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255) NOT NULL,
    rating INTEGER DEFAULT 1500,
    votes INTEGER DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для комментариев
CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    contestant_id INTEGER NOT NULL REFERENCES contestants(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES telegram_users(id) ON DELETE CASCADE,
    comment TEXT NOT NULL,
    likes INTEGER DEFAULT 0,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_contestant_id ON comments(contestant_id);
CREATE INDEX idx_user_id ON comments(user_id);

-- Таблица для лайков комментариев
CREATE TABLE comment_likes (
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES telegram_users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id)
);

-- Таблица для достижений
CREATE TABLE achievements (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для полученных достижений
CREATE TABLE user_achievements (
    user_id INTEGER NOT NULL REFERENCES telegram_users(id) ON DELETE CASCADE,
    achievement_id INTEGER NOT NULL REFERENCES achievements(id) ON DELETE CASCADE,
    unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id)
);

-- Таблица для настроек голосования
CREATE TABLE voting_settings (
    id SERIAL PRIMARY KEY,
    required_votes INTEGER NOT NULL DEFAULT 50,
    final_voting_time INTEGER NOT NULL DEFAULT 24,
    final_start_time TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Вставляем начальные настройки
INSERT INTO voting_settings (required_votes, final_voting_time) VALUES (50, 24); 