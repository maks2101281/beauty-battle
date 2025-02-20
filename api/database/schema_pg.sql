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

-- Таблица для матчей (пар участниц)
CREATE TABLE matches (
    id SERIAL PRIMARY KEY,
    tournament_id INTEGER NOT NULL REFERENCES tournaments(id) ON DELETE CASCADE,
    round_id INTEGER NOT NULL REFERENCES tournament_rounds(id) ON DELETE CASCADE,
    contestant1_id INTEGER NOT NULL REFERENCES contestants(id) ON DELETE CASCADE,
    contestant2_id INTEGER NOT NULL REFERENCES contestants(id) ON DELETE CASCADE,
    winner_id INTEGER REFERENCES contestants(id) ON DELETE SET NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'completed')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(round_id, contestant1_id),
    UNIQUE(round_id, contestant2_id)
);

-- Таблица для голосов в матчах
CREATE TABLE match_votes (
    id SERIAL PRIMARY KEY,
    match_id INTEGER NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES telegram_users(id) ON DELETE CASCADE,
    contestant_id INTEGER NOT NULL REFERENCES contestants(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(match_id, user_id)
);

-- Индексы для оптимизации
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournament_rounds_status ON tournament_rounds(status);
CREATE INDEX idx_matches_status ON matches(status);
CREATE INDEX idx_match_votes_match_id ON match_votes(match_id);
CREATE INDEX idx_match_votes_user_id ON match_votes(user_id);

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

-- Функция для определения победителя матча
CREATE OR REPLACE FUNCTION determine_match_winner()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'completed' THEN
        -- Подсчитываем голоса и определяем победителя
        WITH vote_counts AS (
            SELECT 
                contestant_id,
                COUNT(*) as votes
            FROM match_votes
            WHERE match_id = NEW.id
            GROUP BY contestant_id
            ORDER BY votes DESC
            LIMIT 1
        )
        UPDATE matches
        SET winner_id = (SELECT contestant_id FROM vote_counts)
        WHERE id = NEW.id;
    END IF;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Триггер для автоматического определения победителя
CREATE TRIGGER determine_match_winner_trigger
    AFTER UPDATE OF status ON matches
    FOR EACH ROW
    WHEN (NEW.status = 'completed')
    EXECUTE FUNCTION determine_match_winner();

-- Функция для создания следующего раунда
CREATE OR REPLACE FUNCTION create_next_round()
RETURNS TRIGGER AS $$
DECLARE
    next_round_number INTEGER;
    tournament_record RECORD;
BEGIN
    -- Получаем информацию о турнире
    SELECT * INTO tournament_record
    FROM tournaments
    WHERE id = NEW.tournament_id;
    
    -- Если все матчи текущего раунда завершены
    IF NOT EXISTS (
        SELECT 1 FROM matches
        WHERE round_id = NEW.id AND status != 'completed'
    ) THEN
        next_round_number := tournament_record.current_round + 1;
        
        -- Если есть следующий раунд
        IF next_round_number <= tournament_record.total_rounds THEN
            -- Создаем новый раунд
            INSERT INTO tournament_rounds (
                tournament_id,
                round_number,
                status
            ) VALUES (
                tournament_record.id,
                next_round_number,
                'pending'
            );
            
            -- Обновляем текущий раунд турнира
            UPDATE tournaments
            SET current_round = next_round_number
            WHERE id = tournament_record.id;
        ELSE
            -- Завершаем турнир
            UPDATE tournaments
            SET status = 'completed',
                end_date = CURRENT_TIMESTAMP
            WHERE id = tournament_record.id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Триггер для автоматического создания следующего раунда
CREATE TRIGGER create_next_round_trigger
    AFTER UPDATE OF status ON tournament_rounds
    FOR EACH ROW
    WHEN (NEW.status = 'completed')
    EXECUTE FUNCTION create_next_round(); 