<?php
// server/list_notifications_api_server.php
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

  // Ensure tables exist
  $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    body TEXT NULL,
    link_url TEXT NULL,
    poll_question VARCHAR(500) NULL,
    user_uniq_id VARCHAR(64) NULL,
    author_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_uniq_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

  // Notifications:
  // 1) Replies to user's posts: comments on posts where posts.user_uniq_id = :uid
  $stmt1 = $pdo->prepare("SELECT c.id as comment_id, c.post_id, c.author_name, c.body, c.created_at,
                                 p.title as post_title
                          FROM comments c
                          JOIN posts p ON p.id = c.post_id
                          WHERE p.user_uniq_id = :uid
                          ORDER BY c.id DESC LIMIT 100");
  $stmt1->execute([':uid' => $uid]);
  $postReplies = $stmt1->fetchAll();

  // 2) Replies to user's comments: comments where parent_comment_id IN (user's comments)
  $stmtParent = $pdo->prepare("SELECT id FROM comments WHERE user_uniq_id = :uid");
  $stmtParent->execute([':uid' => $uid]);
  $ids = $stmtParent->fetchAll(PDO::FETCH_COLUMN);

  $commentReplies = [];
  if (!empty($ids)){
    $in = implode(',', array_map('intval', $ids));
    $q = $pdo->query("SELECT c.id as comment_id, c.post_id, c.author_name, c.body, c.created_at,
                             p.title as post_title, c.parent_comment_id
                      FROM comments c
                      JOIN posts p ON p.id = c.post_id
                      WHERE c.parent_comment_id IN ($in)
                      ORDER BY c.id DESC LIMIT 100");
    $commentReplies = $q->fetchAll();
  }

  echo json_encode([
    'ok' => true,
    'notifications' => [
      'post_replies' => $postReplies,
      'comment_replies' => $commentReplies
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
