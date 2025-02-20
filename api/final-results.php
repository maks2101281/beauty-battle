<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем победителя финального голосования
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(DISTINCT v.user_id) as final_votes,
            TIMESTAMPDIFF(HOUR, vs.final_start_time, NOW()) as duration,
            vs.final_voting_time
        FROM contestants c
        JOIN votes v ON c.id = v.contestant_id AND v.is_final = 1
        JOIN voting_settings vs ON 1=1
        GROUP BY c.id
        ORDER BY final_votes DESC
        LIMIT 1
    ");
    $winner = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем общее количество проголосовавших в финале
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as total_voters
        FROM votes
        WHERE is_final = 1
    ");
    $votersData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Форматируем длительность
    $duration = $winner['duration'];
    if ($duration > 24) {
        $days = floor($duration / 24);
        $hours = $duration % 24;
        $durationFormatted = "{$days} д. {$hours} ч.";
    } else {
        $durationFormatted = "{$duration} ч.";
    }

    echo json_encode([
        'success' => true,
        'winner' => [
            'id' => $winner['id'],
            'name' => $winner['name'],
            'photo' => $winner['photo'],
            'rating' => $winner['rating'],
            'votes' => $winner['final_votes']
        ],
        'duration' => $durationFormatted,
        'total_voters' => $votersData['total_voters']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных'
    ]);
} 