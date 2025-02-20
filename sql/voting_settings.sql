CREATE TABLE voting_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    required_votes INT NOT NULL DEFAULT 50,
    final_voting_time INT NOT NULL DEFAULT 24,
    final_start_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Вставляем начальные настройки
INSERT INTO voting_settings (required_votes, final_voting_time) VALUES (50, 24);

-- Добавляем поле is_final в таблицу votes
ALTER TABLE votes ADD COLUMN is_final BOOLEAN DEFAULT FALSE;

-- Добавляем индекс для оптимизации запросов
CREATE INDEX idx_votes_is_final ON votes (is_final);

-- Добавляем поле votes в таблицу contestants
ALTER TABLE contestants ADD COLUMN votes INT DEFAULT 0; 