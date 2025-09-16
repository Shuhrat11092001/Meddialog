<?php
// server/upload_avatar_api_server.php
header('Content-Type: application/json; charset=utf-8');

function json_response($ok, $payload = []){
    echo json_encode(['ok'=>$ok] + $payload, JSON_UNESCAPED_UNICODE);
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

    // Ensure columns exist
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_icon VARCHAR(16) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) NULL"); } catch (Throwable $e) {}

    $user_uniq_id = isset($_POST['user_uniq_id']) ? trim($_POST['user_uniq_id']) : '';
    if ($user_uniq_id === ''){
        json_response(false, ['error' => 'user_uniq_id обязателен']);
    }

    // Validate user exists
    $st = $pdo->prepare('SELECT id FROM users WHERE uniq_id = :uid LIMIT 1');
    $st->execute([':uid' => $user_uniq_id]);
    $u = $st->fetch();
    if (!$u){ json_response(false, ['error' => 'Пользователь не найден']); }

    if (!isset($_FILES['avatar'])){
        json_response(false, ['error' => 'Файл avatar не найден']);
    }

    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK){
        json_response(false, ['error' => 'Ошибка загрузки файла']);
    }

    // Basic validation
    $mime = mime_content_type($file['tmp_name']);
    if (strpos($mime, 'image/') !== 0){
        json_response(false, ['error' => 'Допустимы только изображения']);
    }

    // Save file
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($uploadDir)){
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)){
            json_response(false, ['error' => 'Не удалось создать папку загрузок']);
        }
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    if ($ext === ''){ $ext = 'jpg'; }
    $safe = uniqid('ava_', true) . '.' . $ext;
    $destAbs = $uploadDir . $safe;
    $destRel = 'uploads/avatars/' . $safe; // for frontend

    if (!move_uploaded_file($file['tmp_name'], $destAbs)){
        json_response(false, ['error' => 'Не удалось сохранить файл']);
    }

    // Update user record
    $upd = $pdo->prepare('UPDATE users SET avatar_url = :url WHERE id = :id');
    $upd->execute([':url' => $destRel, ':id' => (int)$u['id']]);

    json_response(true, ['avatar_url' => $destRel]);

} catch (Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error' => $e->getMessage()]);
}
