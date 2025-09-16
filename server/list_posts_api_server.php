<?php
// server/list_posts_api_server.php
header('Content-Type: application/json; charset=utf-8');

try {
    $host = "localhost";
    $db   = "meddiolog";
    $user = "root";
    $pass = "";

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Ensure posts has author_name for legacy schemas
    try { $pdo->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS author_name VARCHAR(100) NULL"); } catch (Throwable $e) {}
    // Ensure post_likes table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_uniq_id VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_like (post_id, user_uniq_id),
        INDEX (post_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Получаем посты с количеством лайков и комментариев + аватар автора
    $stmt = $pdo->query("SELECT 
            p.id, p.title, p.body, p.link_url, p.poll_question, p.author_name, p.user_uniq_id, p.created_at,
            u.avatar_icon AS author_avatar_icon,
            u.avatar_url AS author_avatar_url,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count
        FROM posts p
        LEFT JOIN users u ON u.uniq_id = p.user_uniq_id
        ORDER BY p.id DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Подтягиваем файлы для каждого поста (простым способом)
    $fileStmt = $pdo->prepare("SELECT file_path, file_type FROM post_files WHERE post_id = :pid ORDER BY id ASC");
    foreach ($posts as &$p) {
        $fileStmt->execute([':pid' => $p['id']]);
        $p['files'] = $fileStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
