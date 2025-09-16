<?php
// server/vote_poll_api_server.php
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $optionId = isset($_POST['option_id']) ? (int)$_POST['option_id'] : 0;
    $userId = isset($_POST['user_uniq_id']) ? trim($_POST['user_uniq_id']) : '';

    if ($postId <= 0 || $optionId <= 0 || $userId === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'post_id, option_id и user_uniq_id обязательны']);
        exit;
    }

    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Проверим, что опция принадлежит посту
    $opt = $pdo->prepare("SELECT id FROM poll_options WHERE id = :oid AND post_id = :pid LIMIT 1");
    $opt->execute([':oid' => $optionId, ':pid' => $postId]);
    if (!$opt->fetch(PDO::FETCH_ASSOC)){
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Опция не найдена для данного поста']);
        exit;
    }

    // Создадим таблицу при необходимости
    $pdo->exec("CREATE TABLE IF NOT EXISTS poll_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        option_id INT NOT NULL,
        user_uniq_id VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_user_vote (post_id, user_uniq_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Пытаемся вставить голос (уникальность по (post_id, user_uniq_id))
    $stmt = $pdo->prepare("INSERT INTO poll_votes (post_id, option_id, user_uniq_id) VALUES (:pid, :oid, :uid)");
    $stmt->execute([':pid' => $postId, ':oid' => $optionId, ':uid' => $userId]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    if ((int)$e->getCode() === 23000) { // нарушение уникальности
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Вы уже голосовали']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
