<?php
// server/add_comment_api_server.php
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $author = isset($_POST['author']) ? trim($_POST['author']) : 'Guest';
    $body   = isset($_POST['body']) ? trim($_POST['body']) : '';
    $userUid = isset($_POST['user_uniq_id']) ? trim($_POST['user_uniq_id']) : null;
    $parentId = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;
    $authorName = isset($_POST['author_name']) ? trim($_POST['author_name']) : ($author ?: 'Guest');

    if ($postId <= 0 || $body === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'post_id and body are required']);
        exit;
    }

    // Требуем авторизацию: должен быть user_uniq_id и author_name
    if ($userUid === null || $userUid === '' || $authorName === null || $authorName === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Authorization required: user_uniq_id and author_name']);
        exit;
    }

    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Гарантируем наличие таблицы comments
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author VARCHAR(100) DEFAULT 'Guest',
        author_name VARCHAR(100) NULL,
        user_uniq_id VARCHAR(64) NULL,
        parent_comment_id INT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        INDEX (user_uniq_id), INDEX (post_id), INDEX (parent_comment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // На случай старой схемы — добавим недостающие колонки (MySQL 8+)
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS author_name VARCHAR(100) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS user_uniq_id VARCHAR(64) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_comment_id INT NULL"); } catch (Throwable $e) {}

    // Вставка
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, author, author_name, user_uniq_id, parent_comment_id, body)
                           VALUES (:post_id, :author, :author_name, :user_uniq_id, :parent_comment_id, :body)");
    $stmt->execute([
        ':post_id' => $postId,
        // Сохраняем консистентное имя автора
        ':author' => ($authorName !== '' ? $authorName : ($author !== '' ? $author : 'Guest')),
        ':author_name' => ($authorName !== '' ? $authorName : 'Guest'),
        ':user_uniq_id' => $userUid,
        ':parent_comment_id' => $parentId ?: null,
        ':body'   => $body,
    ]);

    echo json_encode(['ok' => true, 'comment_id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
