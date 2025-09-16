<?php
// server/get_post_api_server.php
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['post_id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'post_id is required']);
        exit;
    }

    $postId = (int)$_GET['post_id'];
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid post_id']);
        exit;
    }

    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Базовый пост + автор
    $stmt = $pdo->prepare("SELECT 
            p.id, p.title, p.body, p.link_url, p.poll_question, p.created_at,
            p.author_name, p.user_uniq_id,
            u.avatar_icon AS author_avatar_icon,
            u.avatar_url AS author_avatar_url
        FROM posts p
        LEFT JOIN users u ON u.uniq_id = p.user_uniq_id
        WHERE p.id = :id");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'post not found']);
        exit;
    }

    // Файлы поста
    $filesStmt = $pdo->prepare("SELECT file_path, file_type FROM post_files WHERE post_id = :pid ORDER BY id ASC");
    $filesStmt->execute([':pid' => $postId]);
    $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Таблица комментариев
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author VARCHAR(100) DEFAULT 'Guest',
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Комментарии
    $cmtStmt = $pdo->prepare("SELECT id, author, body, created_at FROM comments WHERE post_id = :pid ORDER BY id DESC");
    $cmtStmt->execute([':pid' => $postId]);
    $comments = $cmtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Опросы: опции и голоса
    $pdo->exec("CREATE TABLE IF NOT EXISTS poll_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        option_text VARCHAR(300) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

    $options = [];
    $totalVotes = 0;
    if (!empty($post['poll_question'])) {
        $optStmt = $pdo->prepare("SELECT id, option_text FROM poll_options WHERE post_id = :pid ORDER BY id ASC");
        $optStmt->execute([':pid' => $postId]);
        $options = $optStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($options) {
            $voteCountStmt = $pdo->prepare("SELECT option_id, COUNT(*) as cnt FROM poll_votes WHERE post_id = :pid GROUP BY option_id");
            $voteCountStmt->execute([':pid' => $postId]);
            $byOption = [];
            foreach ($voteCountStmt as $row) { $byOption[(int)$row['option_id']] = (int)$row['cnt']; $totalVotes += (int)$row['cnt']; }

            foreach ($options as &$o) {
                $oid = (int)$o['id'];
                $o['votes'] = $byOption[$oid] ?? 0;
            }
        }
    }

    // Узнаем проголосовал ли конкретный пользователь (если передан user_uniq_id)
    $userVotedOptionId = null;
    if (!empty($_GET['user_uniq_id'])) {
        $uid = trim($_GET['user_uniq_id']);
        if ($uid !== ''){
            $uvStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE post_id = :pid AND user_uniq_id = :uid LIMIT 1");
            $uvStmt->execute([':pid' => $postId, ':uid' => $uid]);
            $row = $uvStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $userVotedOptionId = (int)$row['option_id'];
        }
    }

    echo json_encode([
        'ok' => true,
        'post' => $post,
        'files' => $files,
        'comments' => $comments,
        'poll' => [
            'options' => $options,
            'totalVotes' => $totalVotes,
            'userVotedOptionId' => $userVotedOptionId,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
