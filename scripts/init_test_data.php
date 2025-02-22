<?php
require_once __DIR__ . '/../config/database_render.php';

try {
    // Создаем тестовых участниц
    $contestants = [
        [
            'name' => 'Участница 1',
            'photo' => '/uploads/photos/test1.jpg'
        ],
        [
            'name' => 'Участница 2',
            'photo' => '/uploads/photos/test2.jpg'
        ],
        [
            'name' => 'Участница 3',
            'photo' => '/uploads/photos/test3.jpg'
        ],
        [
            'name' => 'Участница 4',
            'photo' => '/uploads/photos/test4.jpg'
        ]
    ];

    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Очищаем существующие данные
    $pdo->exec("TRUNCATE contestants CASCADE");
    $pdo->exec("TRUNCATE rounds CASCADE");
    $pdo->exec("TRUNCATE matches CASCADE");
    $pdo->exec("TRUNCATE votes CASCADE");

    // Добавляем участниц
    $stmt = $pdo->prepare("
        INSERT INTO contestants (name, photo, created_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
    ");

    foreach ($contestants as $contestant) {
        $stmt->execute([
            $contestant['name'],
            $contestant['photo']
        ]);
    }

    // Создаем первый раунд
    $pdo->exec("
        INSERT INTO rounds (number, status, created_at)
        VALUES (1, 'active', CURRENT_TIMESTAMP)
    ");

    // Получаем ID раунда
    $roundId = $pdo->lastInsertId('rounds_id_seq');

    // Создаем первый матч
    $stmt = $pdo->prepare("
        INSERT INTO matches (
            round_id, contestant1_id, contestant2_id,
            status, created_at
        )
        SELECT 
            ?, 
            c1.id, 
            c2.id,
            'active',
            CURRENT_TIMESTAMP
        FROM 
            (SELECT id FROM contestants ORDER BY id LIMIT 1) c1,
            (SELECT id FROM contestants WHERE id > (SELECT id FROM contestants ORDER BY id LIMIT 1) LIMIT 1) c2
    ");
    $stmt->execute([$roundId]);

    // Копируем тестовые изображения
    $sourceDir = __DIR__ . '/../public/images/test';
    $targetDir = __DIR__ . '/../public/uploads/photos';
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    for ($i = 1; $i <= 4; $i++) {
        $source = $sourceDir . "/test{$i}.jpg";
        $target = $targetDir . "/test{$i}.jpg";
        
        if (file_exists($source)) {
            copy($source, $target);
        }
    }

    $pdo->commit();
    echo "Тестовые данные успешно добавлены\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
} 