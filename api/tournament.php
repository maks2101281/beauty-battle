<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AuthService.php';

header('Content-Type: application/json');

// Проверка авторизации
function checkAuth() {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    if (!$token) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT telegram_username 
        FROM auth_tokens 
        WHERE token = ? AND expires_at > NOW() AND is_active = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получение текущего активного турнира
function getCurrentTournament() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT * FROM tournaments 
        WHERE status = 'active' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получение текущего раунда
function getCurrentRound($tournamentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM tournament_rounds 
        WHERE tournament_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tournamentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получение текущего матча для пользователя
function getCurrentMatch($roundId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
            c1.name as contestant1_name, c1.photo as contestant1_photo, c1.rating as contestant1_rating,
            c2.name as contestant2_name, c2.photo as contestant2_photo, c2.rating as contestant2_rating,
            (SELECT contestant_id FROM match_votes WHERE match_id = m.id AND user_id = ?) as voted_for
        FROM matches m
        JOIN contestants c1 ON m.contestant1_id = c1.id
        JOIN contestants c2 ON m.contestant2_id = c2.id
        WHERE m.round_id = ? AND m.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM match_votes 
            WHERE match_id = m.id AND user_id = ?
        )
        LIMIT 1
    ");
    $stmt->execute([$userId, $roundId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Создание нового турнира
function createTournament() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Проверяем, нет ли активного турнира
        $stmt = $pdo->query("SELECT id FROM tournaments WHERE status IN ('pending', 'active')");
        if ($stmt->fetch()) {
            throw new Exception('Уже есть активный турнир');
        }
        
        // Получаем список участниц
        $stmt = $pdo->query("SELECT id FROM contestants ORDER BY RANDOM() LIMIT 16");
        $contestants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($contestants) < 16) {
            throw new Exception('Недостаточно участниц для начала турнира');
        }
        
        // Создаем турнир
        $stmt = $pdo->prepare("
            INSERT INTO tournaments (name, status, start_date) 
            VALUES ('Beauty Battle 2024', 'active', CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute();
        $tournamentId = $stmt->fetchColumn();
        
        // Создаем первый раунд
        $stmt = $pdo->prepare("
            INSERT INTO tournament_rounds (tournament_id, round_number, status, start_date) 
            VALUES (?, 1, 'active', CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([$tournamentId]);
        $roundId = $stmt->fetchColumn();
        
        // Создаем пары для первого раунда
        for ($i = 0; $i < count($contestants); $i += 2) {
            $stmt = $pdo->prepare("
                INSERT INTO matches (
                    tournament_id, round_id, contestant1_id, contestant2_id, status
                ) VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $tournamentId,
                $roundId,
                $contestants[$i],
                $contestants[$i + 1]
            ]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Голосование в матче
function vote($matchId, $contestantId, $userId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Проверяем, не голосовал ли уже пользователь
        $stmt = $pdo->prepare("
            SELECT 1 FROM match_votes 
            WHERE match_id = ? AND user_id = ?
        ");
        $stmt->execute([$matchId, $userId]);
        if ($stmt->fetch()) {
            throw new Exception('Вы уже голосовали в этом матче');
        }
        
        // Проверяем статус матча
        $stmt = $pdo->prepare("
            SELECT status FROM matches 
            WHERE id = ? AND (contestant1_id = ? OR contestant2_id = ?)
        ");
        $stmt->execute([$matchId, $contestantId, $contestantId]);
        $match = $stmt->fetch();
        
        if (!$match) {
            throw new Exception('Матч не найден');
        }
        
        if ($match['status'] !== 'active') {
            throw new Exception('Матч уже завершен');
        }
        
        // Добавляем голос
        $stmt = $pdo->prepare("
            INSERT INTO match_votes (match_id, user_id, contestant_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$matchId, $userId, $contestantId]);
        
        // Проверяем, достаточно ли голосов для завершения матча
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as votes
            FROM match_votes
            WHERE match_id = ?
        ");
        $stmt->execute([$matchId]);
        $votes = $stmt->fetchColumn();
        
        // Если набрано 100 голосов, завершаем матч
        if ($votes >= 100) {
            $stmt = $pdo->prepare("
                UPDATE matches 
                SET status = 'completed'
                WHERE id = ?
            ");
            $stmt->execute([$matchId]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Обработка запросов
try {
    $auth = checkAuth();
    if (!$auth) {
        throw new Exception('Unauthorized', 401);
    }
    
    // Получаем ID пользователя
    $stmt = $pdo->prepare("SELECT id FROM telegram_users WHERE username = ?");
    $stmt->execute([$auth['telegram_username']]);
    $userId = $stmt->fetchColumn();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Получение текущего состояния турнира
            $tournament = getCurrentTournament();
            if (!$tournament) {
                echo json_encode([
                    'success' => true,
                    'status' => 'no_tournament',
                    'message' => 'Нет активного турнира'
                ]);
                break;
            }
            
            $round = getCurrentRound($tournament['id']);
            if (!$round) {
                echo json_encode([
                    'success' => true,
                    'status' => 'no_round',
                    'message' => 'Нет активного раунда'
                ]);
                break;
            }
            
            $match = getCurrentMatch($round['id'], $userId);
            
            echo json_encode([
                'success' => true,
                'tournament' => $tournament,
                'round' => $round,
                'match' => $match
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'create_tournament':
                        createTournament();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Турнир успешно создан'
                        ]);
                        break;
                        
                    case 'vote':
                        if (!isset($data['match_id']) || !isset($data['contestant_id'])) {
                            throw new Exception('Не указан ID матча или участницы');
                        }
                        
                        vote($data['match_id'], $data['contestant_id'], $userId);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Голос учтен'
                        ]);
                        break;
                        
                    default:
                        throw new Exception('Неизвестное действие');
                }
            } else {
                throw new Exception('Не указано действие');
            }
            break;
            
        default:
            throw new Exception('Метод не поддерживается');
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 