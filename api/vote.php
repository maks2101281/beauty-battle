<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
$contestantId = $data['contestant_id'] ?? null;
$isFinal = $data['is_final'] ?? false;

if (!$contestantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан ID участника']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Проверяем, голосовал ли уже пользователь
    $userId = getUserId(); // Получаем ID пользователя из токена
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as vote_count 
        FROM votes 
        WHERE user_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$userId]);
    $voteCheck = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($voteCheck['vote_count'] > 0 && !$isFinal) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Вы уже голосовали сегодня'
        ]);
        exit;
    }

    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Добавляем голос
    $stmt = $pdo->prepare("
        INSERT INTO votes (user_id, contestant_id, is_final, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $contestantId, $isFinal]);

    // Обновляем количество голосов участника
    $stmt = $pdo->prepare("
        UPDATE contestants 
        SET votes = votes + 1 
        WHERE id = ?
    ");
    $stmt->execute([$contestantId]);

    // Получаем обновленные данные участника
    $stmt = $pdo->prepare("
        SELECT votes, required_votes 
        FROM contestants c
        JOIN voting_settings vs ON 1=1
        WHERE c.id = ?
    ");
    $stmt->execute([$contestantId]);
    $contestantData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Проверяем, закончилось ли финальное голосование
    $votingEnded = false;
    if ($isFinal) {
        $stmt = $pdo->prepare("
            SELECT 
                (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(final_start_time)) > (final_voting_time * 3600) as ended
            FROM voting_settings
            WHERE id = 1
        ");
        $stmt->execute();
        $finalCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        $votingEnded = $finalCheck['ended'];
    }

    $pdo->commit();

    // Получаем общее количество голосов
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'contestant_votes' => $contestantData['votes'],
        'required_votes' => $contestantData['required_votes'],
        'total_votes' => $totalVotes,
        'voting_ended' => $votingEnded
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных'
    ]);
} 