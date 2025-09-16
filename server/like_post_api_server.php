<?php
// server/like_post_api_server.php
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $userUid = isset($_POST['user_uniq_id']) ? trim($_POST['user_uniq_id']) : '';

    if ($postId <= 0 || $userUid === ''){
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'post_id and user_uniq_id are required']);
        exit;
    }

    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_uniq_id VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_like (post_id, user_uniq_id),
        INDEX (post_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Toggle like: if exists -> delete, else -> insert
    $sel = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = :pid AND user_uniq_id = :uid');
    $sel->execute([':pid' => $postId, ':uid' => $userUid]);
    $liked = $sel->fetchColumn() ? true : false;

    if ($liked){
        $del = $pdo->prepare('DELETE FROM post_likes WHERE post_id = :pid AND user_uniq_id = :uid');
        $del->execute([':pid' => $postId, ':uid' => $userUid]);
        $liked = false;
    } else {
        $ins = $pdo->prepare('INSERT IGNORE INTO post_likes (post_id, user_uniq_id) VALUES (:pid, :uid)');
        $ins->execute([':pid' => $postId, ':uid' => $userUid]);
        $liked = true;
    }

    // Count likes
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = :pid');
    $cnt->execute([':pid' => $postId]);
    $likesCount = (int)$cnt->fetchColumn();

    echo json_encode(['ok' => true, 'liked' => $liked, 'likes_count' => $likesCount]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
