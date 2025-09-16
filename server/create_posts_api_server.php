<?php
// server/create_posts_api_server.php

header('Content-Type: application/json; charset=utf-8');

try {
    // 1) Подключение к БД (как в register_login_api_server.php)
    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 2) Создание таблиц (на всякий случай — если нет)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(300) NOT NULL,
            body TEXT NULL,
            link_url TEXT NULL,
            poll_question VARCHAR(500) NULL,
            user_uniq_id VARCHAR(64) NULL,
            author_name VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_uniq_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS post_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) NOT NULL, -- image|video|other
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Таблицы для опросов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poll_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            option_text VARCHAR(300) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poll_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            option_id INT NOT NULL,
            user_uniq_id VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_user_vote (post_id, user_uniq_id),
            INDEX (post_id), INDEX (option_id), INDEX (user_uniq_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");


    // 3) Прием данных
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $body  = isset($_POST['body']) ? trim($_POST['body']) : '';
    $link  = isset($_POST['linkUrl']) ? trim($_POST['linkUrl']) : '';
    $pollQ = isset($_POST['pollQuestion']) ? trim($_POST['pollQuestion']) : '';
    $userUid = isset($_POST['user_uniq_id']) ? trim($_POST['user_uniq_id']) : '';
    $authorName = !empty($_POST['author_name']) ? trim($_POST['author_name']) : '';

    if (empty($authorName)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Имя автора обязательно']);
        exit;
    }

    if ($title === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Заголовок обязателен']);
        exit;
    }

    if ($userUid === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'user_uniq_id обязателен']);
        exit;
    }




    // 4) Вставка поста
    $stmt = $pdo->prepare("
        INSERT INTO posts (title, body, link_url, poll_question, user_uniq_id, author_name)
        VALUES (:title, :body, :link_url, :poll_question, :user_uniq_id, :author_name)
    ");
    $stmt->execute([
        ':title' => $title,
        ':body' => $body !== '' ? $body : null,
        ':link_url' => $link !== '' ? $link : null,
        ':poll_question' => $pollQ !== '' ? $pollQ : null,
        ':user_uniq_id' => $userUid,
        ':author_name' => $authorName,
    ]);

    $postId = (int)$pdo->lastInsertId();

    // 5) Загрузка медиа (если пришли)
    $savedFiles = [];
    if (!empty($_FILES['media']) && is_array($_FILES['media']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/posts/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('Не удалось создать папку загрузок');
            }
        }

        $fileCount = count($_FILES['media']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $error = $_FILES['media']['error'][$i];
            if ($error !== UPLOAD_ERR_OK) continue;

            $tmpName = $_FILES['media']['tmp_name'][$i];
            $orig    = $_FILES['media']['name'][$i];
            $type    = $_FILES['media']['type'][$i];

            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $safeName = uniqid('post_', true) . ($ext ? "." . preg_replace('/[^a-zA-Z0-9]+/', '', $ext) : '');
            $destAbs  = $uploadDir . $safeName;
            $destRel  = 'uploads/posts/' . $safeName; // относительный путь для фронта

            if (!move_uploaded_file($tmpName, $destAbs)) {
                continue; // пропускаем, если не удалось
            }

            $kind = 'other';
            if (strpos($type, 'image/') === 0) $kind = 'image';
            if (strpos($type, 'video/') === 0) $kind = 'video';

            $stmtFile = $pdo->prepare("
                INSERT INTO post_files (post_id, file_path, file_type)
                VALUES (:post_id, :file_path, :file_type)
            ");
            $stmtFile->execute([
                ':post_id' => $postId,
                ':file_path' => $destRel,
                ':file_type' => $kind
            ]);

            $savedFiles[] = ['path' => $destRel, 'type' => $kind];
        }
    }

    // 6) Опрос: сохраняем варианты, если пришли
    $savedOptions = [];
    if ($pollQ !== '' && isset($_POST['poll_options']) ) {
        $options = $_POST['poll_options'];
        if (is_array($options)){
            $optStmt = $pdo->prepare("INSERT INTO poll_options (post_id, option_text) VALUES (:post_id, :text)");
            foreach ($options as $opt){
                $opt = trim($opt);
                if ($opt === '') continue;
                $optStmt->execute([':post_id' => $postId, ':text' => $opt]);
                $savedOptions[] = ['id' => (int)$pdo->lastInsertId(), 'text' => $opt];
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'post_id' => $postId,
        'files' => $savedFiles,
        'poll_options' => $savedOptions
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}