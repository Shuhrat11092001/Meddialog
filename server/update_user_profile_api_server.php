<?php
header('Content-Type: application/json; charset=utf-8');

function json_response($ok, $payload = []){
    $resp = ['ok' => $ok] + $payload;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
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
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $avatar_icon = isset($_POST['avatar_icon']) ? trim($_POST['avatar_icon']) : null; // emoji or null
    $avatar_url  = isset($_POST['avatar_url']) ? trim($_POST['avatar_url']) : null;   // url or empty to clear

    if ($user_uniq_id === ''){
        json_response(false, ['error' => 'user_uniq_id обязателен']);
    }
    if ($name === ''){
        json_response(false, ['error' => 'Имя не может быть пустым']);
    }

    // Validate avatar_icon length (emoji length may vary, but keep short)
    if ($avatar_icon !== null && $avatar_icon !== '' && mb_strlen($avatar_icon) > 8){
        json_response(false, ['error' => 'Слишком длинная иконка']);
    }

    // Build update
    $stmt = $pdo->prepare("UPDATE users SET name = :name, avatar_icon = :avatar_icon, avatar_url = :avatar_url WHERE uniq_id = :uid");
    $stmt->execute([
        ':name' => $name,
        ':avatar_icon' => ($avatar_icon === '' ? null : $avatar_icon),
        ':avatar_url' => ($avatar_url === '' ? null : $avatar_url),
        ':uid' => $user_uniq_id,
    ]);

    // Return updated user basic data
    $q = $pdo->prepare('SELECT id, name, email, uniq_id, avatar_icon, avatar_url FROM users WHERE uniq_id = :uid LIMIT 1');
    $q->execute([':uid' => $user_uniq_id]);
    $row = $q->fetch();
    if (!$row){
        json_response(false, ['error' => 'Пользователь не найден после обновления']);
    }

    json_response(true, ['user' => [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'uniq_id' => $row['uniq_id'],
        'avatar_icon' => $row['avatar_icon'] ?? null,
        'avatar_url' => $row['avatar_url'] ?? null,
    ]]);

} catch (Throwable $e){
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
