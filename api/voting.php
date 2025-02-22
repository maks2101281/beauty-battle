<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database_render.php';

try {
    // Проверяем существование необходимых таблиц
    $required_tables = ['rounds', 'matches', 'contestants', 'votes'];
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        if (!$stmt) {
            throw new Exception("Таблица {$table} не существует");
        }
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Проверяем, есть ли активный раунд
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM rounds 
                WHERE status = 'active'
            ");
            if ($stmt->fetchColumn() == 0) {
                // Создаем первый раунд, если нет активного
                $pdo->exec("
                    INSERT INTO rounds (number, status) 
                    VALUES (1, 'active')
                    ON CONFLICT (number) DO NOTHING
                ");
            }

            // Получаем текущий активный раунд
            $stmt = $pdo->query("
                SELECT id, number 
                FROM rounds 
                WHERE status = 'active' 
                ORDER BY number DESC 
                LIMIT 1
            ");
            $round = $stmt->fetch();
            
            if (!$round) {
                throw new Exception('Нет активного раунда');
            }
            
            // Получаем активные матчи текущего раунда
            $stmt = $pdo->prepare("
                SELECT 
                    m.id as match_id,
                    m.status,
                    c1.id as contestant1_id,
                    c1.name as contestant1_name,
                    c1.photo as contestant1_photo,
                    c2.id as contestant2_id,
                    c2.name as contestant2_name,
                    c2.photo as contestant2_photo,
                    (SELECT COUNT(*) FROM votes v WHERE v.match_id = m.id AND v.contestant_id = c1.id AND NOT v.is_cancelled) as votes1,
                    (SELECT COUNT(*) FROM votes v WHERE v.match_id = m.id AND v.contestant_id = c2.id AND NOT v.is_cancelled) as votes2,
                    (SELECT contestant_id FROM votes v WHERE v.match_id = m.id AND v.ip_address = ? AND NOT v.is_cancelled) as user_vote
                FROM matches m
                JOIN contestants c1 ON m.contestant1_id = c1.id
                JOIN contestants c2 ON m.contestant2_id = c2.id
                WHERE m.round_id = ? AND m.status = 'active'
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'], $round['id']]);
            $matches = $stmt->fetchAll();

            // Если нет активных матчей, создаем новые
            if (empty($matches)) {
                $stmt = $pdo->query("
                    SELECT id FROM contestants 
                    WHERE id NOT IN (
                        SELECT contestant1_id FROM matches 
                        WHERE round_id = {$round['id']}
                        UNION
                        SELECT contestant2_id FROM matches 
                        WHERE round_id = {$round['id']}
                    )
                    ORDER BY RANDOM()
                    LIMIT 2
                ");
                $contestants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($contestants) >= 2) {
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (round_id, contestant1_id, contestant2_id, status)
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmt->execute([$round['id'], $contestants[0], $contestants[1]]);
                    
                    // Получаем обновленный список матчей
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.id as match_id,
                            m.status,
                            c1.id as contestant1_id,
                            c1.name as contestant1_name,
                            c1.photo as contestant1_photo,
                            c2.id as contestant2_id,
                            c2.name as contestant2_name,
                            c2.photo as contestant2_photo,
                            0 as votes1,
                            0 as votes2,
                            NULL as user_vote
                        FROM matches m
                        JOIN contestants c1 ON m.contestant1_id = c1.id
                        JOIN contestants c2 ON m.contestant2_id = c2.id
                        WHERE m.round_id = ? AND m.status = 'active'
                        ORDER BY m.created_at DESC
                    ");
                    $stmt->execute([$round['id']]);
                    $matches = $stmt->fetchAll();
                }
            }
            
            echo json_encode([
                'success' => true,
                'round' => $round,
                'matches' => $matches
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['match_id']) || !isset($data['contestant_id'])) {
                throw new Exception('Не указан ID матча или участницы');
            }
            
            $ip = $_SERVER['REMOTE_ADDR'];
            
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            try {
                // Проверяем статус матча
                $stmt = $pdo->prepare("
                    SELECT status, contestant1_id, contestant2_id 
                    FROM matches 
                    WHERE id = ?
                ");
                $stmt->execute([$data['match_id']]);
                $match = $stmt->fetch();
                
                if (!$match) {
                    throw new Exception('Матч не найден');
                }
                
                if ($match['status'] !== 'active') {
                    throw new Exception('Матч уже завершен');
                }
                
                if (!in_array($data['contestant_id'], [$match['contestant1_id'], $match['contestant2_id']])) {
                    throw new Exception('Некорректный ID участницы');
                }
                
                // Проверяем, голосовал ли уже этот IP
                $stmt = $pdo->prepare("
                    SELECT id, is_cancelled, contestant_id 
                    FROM votes 
                    WHERE match_id = ? AND ip_address = ?
                ");
                $stmt->execute([$data['match_id'], $ip]);
                $existing_vote = $stmt->fetch();
                
                if ($existing_vote) {
                    if (!$existing_vote['is_cancelled']) {
                        if ($existing_vote['contestant_id'] == $data['contestant_id']) {
                            // Отменяем голос
                            $stmt = $pdo->prepare("
                                UPDATE votes 
                                SET is_cancelled = true 
                                WHERE id = ?
                            ");
                            $stmt->execute([$existing_vote['id']]);
                            
                            $pdo->commit();
                            echo json_encode([
                                'success' => true,
                                'message' => 'Голос отменен'
                            ]);
                            break;
                        } else {
                            throw new Exception('Вы уже проголосовали за другую участницу');
                        }
                    } else {
                        // Восстанавливаем отмененный голос
                        $stmt = $pdo->prepare("
                            UPDATE votes 
                            SET is_cancelled = false 
                            WHERE id = ?
                        ");
                        $stmt->execute([$existing_vote['id']]);
                    }
                } else {
                    // Добавляем новый голос
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (match_id, contestant_id, ip_address)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['match_id'],
                        $data['contestant_id'],
                        $ip
                    ]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Голос учтен'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Метод не поддерживается');
    }
} catch (Exception $e) {
    error_log("Voting API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 