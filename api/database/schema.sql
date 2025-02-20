-- Таблица для хранения данных Telegram пользователей
CREATE TABLE telegram_users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    chat_id BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX idx_username (username),
    INDEX idx_chat_id (chat_id)
);

-- Таблица для хранения кодов подтверждения
CREATE TABLE verification_codes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    code VARCHAR(4) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX idx_username_created (telegram_username, created_at)
);

-- Таблица для хранения токенов авторизации
CREATE TABLE auth_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    INDEX idx_token (token),
    INDEX idx_username (telegram_username)
);

-- Таблица для отслеживания попыток авторизации
CREATE TABLE auth_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    telegram_username VARCHAR(32) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_type ENUM('code_request', 'code_verify') NOT NULL,
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX idx_username_ip_type (telegram_username, ip_address, attempt_type, created_at)
);

-- Таблица для комментариев
CREATE TABLE comments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contestant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    likes INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES telegram_users(id) ON DELETE CASCADE,
    INDEX idx_contestant_id (contestant_id),
    INDEX idx_user_id (user_id)
);

-- Таблица для лайков комментариев
CREATE TABLE comment_likes (
    comment_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES telegram_users(id) ON DELETE CASCADE
);

-- Таблица для достижений
CREATE TABLE achievements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для полученных достижений
CREATE TABLE user_achievements (
    user_id BIGINT NOT NULL,
    achievement_id BIGINT NOT NULL,
    unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES telegram_users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

-- Таблица для логов
CREATE TABLE activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    action_type VARCHAR(50) NOT NULL,
    action_details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (user_id, action_type),
    INDEX idx_created_at (created_at)
); 