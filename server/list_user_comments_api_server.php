<?php
// server/list_user_comments_api_server.php
header('Content-Type: application/json; charset=utf-8');

$uid = isset($_GET['user_uniq_id']) ? trim($_GET['user_uniq_id']) : '';
if ($uid === ''){
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'user_uniq_id is required']);
  exit;
}

try{
  $host = "localhost";
  $db   = "meddiolog";
  $user = "root";
  $pass = "";
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Ensure comments table exists
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


  $stmt = $pdo->prepare("SELECT id, post_id, author, author_name, body, parent_comment_id, created_at
                         FROM comments WHERE user_uniq_id = :uid ORDER BY id DESC");
  $stmt->execute([':uid' => $uid]);
  $comments = $stmt->fetchAll();

  echo json_encode(['ok' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
