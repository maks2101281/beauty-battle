<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database_render.php';
require_once __DIR__ . '/../classes/AuthService.php';

try {
    // Получаем настройки голосования
    $stmt = $pdo->query("SELECT * FROM voting_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Проверяем, является ли текущий этап финальным
    $stmt = $pdo->query("
        SELECT COUNT(*) as qualified_count 
        FROM contestants 
        WHERE votes >= " . $settings['required_votes']
    );
    $qualified = $stmt->fetch(PDO::FETCH_ASSOC);

    // Если достаточно участников набрали нужное количество голосов,
    // переходим к финальному этапу
    $isFinalRound = $qualified['qualified_count'] >= 2;

    echo json_encode([
        'success' => true,
        'requiredVotes' => (int)$settings['required_votes'],
        'isFinalRound' => $isFinalRound,
        'finalVotingTime' => (int)$settings['final_voting_time']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных'
    ]);
} 