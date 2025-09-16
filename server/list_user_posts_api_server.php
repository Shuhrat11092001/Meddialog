<?php
// server/list_user_posts_api_server.php
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

  // Ensure posts table exists (matches create_posts_api_server.php)
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


  $stmt = $pdo->prepare("SELECT id, title, body, link_url, poll_question, author_name, created_at FROM posts WHERE user_uniq_id = :uid ORDER BY id DESC");
  $stmt->execute([':uid' => $uid]);
  $posts = $stmt->fetchAll();

  // Attach files
  $pdo->exec("CREATE TABLE IF NOT EXISTS post_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $fileStmt = $pdo->prepare("SELECT file_path, file_type FROM post_files WHERE post_id = :pid ORDER BY id ASC");
  foreach ($posts as &$p){
    $fileStmt->execute([':pid' => $p['id']]);
    $p['files'] = $fileStmt->fetchAll() ?: [];
  }

  echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
