-- Таблица для хранения данных Telegram пользователей
CREATE TABLE telegram_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    chat_id BIGINT NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    votes_to_win INTEGER NOT NULL DEFAULT 10,
    max_active_matches INTEGER NOT NULL DEFAULT 5,
    votes_per_ip_per_day INTEGER NOT NULL DEFAULT 20,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для турниров
CREATE TABLE tournaments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'completed')),
    current_round INTEGER NOT NULL DEFAULT 1,
    total_rounds INTEGER NOT NULL DEFAULT 4,
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для раундов турнира
CREATE TABLE tournament_rounds (
    id SERIAL PRIMARY KEY,
    tournament_id INTEGER NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
    round_number INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'completed')),
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tournament_id, round_number)
);

-- Таблица раундов
CREATE TABLE rounds (
    id SERIAL PRIMARY KEY,
    number INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'completed')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- Таблица матчей (пар участниц)
CREATE TABLE matches (
    id SERIAL PRIMARY KEY,
    round_id INTEGER NOT NULL REFERENCES rounds(id),
    contestant1_id INTEGER NOT NULL REFERENCES contestants(id),
    contestant2_id INTEGER NOT NULL REFERENCES contestants(id),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed')),
    winner_id INTEGER REFERENCES contestants(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    CONSTRAINT different_contestants CHECK (contestant1_id != contestant2_id)
);

-- Таблица голосов
CREATE TABLE votes (
    id SERIAL PRIMARY KEY,
    match_id INTEGER NOT NULL REFERENCES matches(id),
    contestant_id INTEGER NOT NULL REFERENCES contestants(id),
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_cancelled BOOLEAN NOT NULL DEFAULT false,
    UNIQUE(match_id, ip_address)
);

-- Таблица для отслеживания IP адресов
CREATE TABLE ip_votes (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    votes_count INTEGER DEFAULT 1,
    last_vote_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для оптимизации
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournament_rounds_status ON tournament_rounds(status);
CREATE INDEX idx_matches_status ON matches(status);
CREATE INDEX idx_votes_match ON votes(match_id);
CREATE INDEX idx_votes_ip ON votes(ip_address);
CREATE INDEX idx_contestants_rating ON contestants(rating DESC);
CREATE INDEX idx_matches_contestants ON matches(contestant1_id, contestant2_id);

-- Функция для автоматического обновления updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Триггеры для автоматического обновления updated_at
CREATE TRIGGER update_contestants_updated_at
    BEFORE UPDATE ON contestants
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tournaments_updated_at
    BEFORE UPDATE ON tournaments
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tournament_rounds_updated_at
    BEFORE UPDATE ON tournament_rounds
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_matches_updated_at
    BEFORE UPDATE ON matches
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Функция для подсчета голосов
CREATE OR REPLACE FUNCTION count_valid_votes(match_id INTEGER, contestant_id INTEGER)
RETURNS INTEGER AS $$
BEGIN
    RETURN (
        SELECT COUNT(*)
        FROM votes
        WHERE votes.match_id = $1
        AND votes.contestant_id = $2
        AND NOT is_cancelled
    );
END;
$$ LANGUAGE plpgsql;

-- Функция для определения победителя
CREATE OR REPLACE FUNCTION determine_match_winner(match_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    votes1 INTEGER;
    votes2 INTEGER;
    contestant1 INTEGER;
    contestant2 INTEGER;
BEGIN
    -- Получаем ID участниц
    SELECT contestant1_id, contestant2_id INTO contestant1, contestant2
    FROM matches WHERE id = match_id;
    
    -- Подсчитываем голоса
    votes1 := count_valid_votes(match_id, contestant1);
    votes2 := count_valid_votes(match_id, contestant2);
    
    -- Определяем победителя
    IF votes1 > votes2 THEN
        RETURN contestant1;
    ELSIF votes2 > votes1 THEN
        RETURN contestant2;
    ELSE
        RETURN NULL; -- Ничья
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Функция для завершения раунда
CREATE OR REPLACE FUNCTION complete_round(round_id INTEGER)
RETURNS BOOLEAN AS $$
DECLARE
    next_round INTEGER;
    current_number INTEGER;
BEGIN
    -- Получаем номер текущего раунда
    SELECT number INTO current_number FROM rounds WHERE id = round_id;
    
    -- Завершаем текущий раунд
    UPDATE rounds 
    SET status = 'completed',
        completed_at = CURRENT_TIMESTAMP
    WHERE id = round_id;
    
    -- Создаем следующий раунд, если это не финал
    IF current_number < 4 THEN -- максимум 4 раунда (16->8->4->2)
        INSERT INTO rounds (number, status)
        VALUES (current_number + 1, 'active')
        RETURNING id INTO next_round;
        
        -- Создаем матчи для следующего раунда
        INSERT INTO matches (round_id, contestant1_id, contestant2_id)
        SELECT 
            next_round,
            m1.winner_id,
            m2.winner_id
        FROM (
            SELECT ROW_NUMBER() OVER (ORDER BY id) as rn,
                   winner_id
            FROM matches 
            WHERE round_id = round_id
            AND status = 'completed'
        ) m1
        JOIN (
            SELECT ROW_NUMBER() OVER (ORDER BY id) as rn,
                   winner_id
            FROM matches 
            WHERE round_id = round_id
            AND status = 'completed'
        ) m2 ON m1.rn % 2 = 1 AND m2.rn = m1.rn + 1;
    END IF;
    
    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Вставляем начальный раунд
INSERT INTO rounds (number, status) VALUES (1, 'active');

-- Вставляем начальные настройки
INSERT INTO voting_settings (votes_to_win, max_active_matches, votes_per_ip_per_day)
VALUES (10, 5, 20)
ON CONFLICT DO NOTHING; 