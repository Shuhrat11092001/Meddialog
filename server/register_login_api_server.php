<?php
header('Content-Type: application/json; charset=utf-8');

// Simple helper to output and exit
function json_response($ok, $payload = []){
    $resp = ['ok' => $ok] + $payload;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}

$type = $_POST['type'] ?? '';

// Basic input
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$type){
    json_response(false, ['error' => 'Не указан тип операции']);
}

// DB connection
$host = "localhost";
$db   = "meddiolog";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // ensure avatar columns exist
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_icon VARCHAR(16) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) NULL"); } catch (Throwable $e) {}
} catch (Throwable $e){
    json_response(false, ['error' => 'Ошибка подключения к БД']);
}

if ($type === 'register'){
    if (!$fullname || !$email || !$password){
        json_response(false, ['error' => 'Не все поля заполнены']);
    }
    // Check existing email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()){
        json_response(false, ['error' => 'Пользователь с таким email уже существует']);
    }

    $user_uniq_id = uniqid();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (name, email, password, uniq_id) VALUES (:name, :email, :password, :uniq_id)');
    try{
        $ins->execute([
            ':name' => $fullname,
            ':email' => $email,
            ':password' => $hash,
            ':uniq_id' => $user_uniq_id,
        ]);
        $uid = (int)$pdo->lastInsertId();
        json_response(true, ['user' => [
            'id' => $uid,
            'name' => $fullname,
            'email' => $email,
            'uniq_id' => $user_uniq_id,
            'avatar_icon' => null,
            'avatar_url' => null,
        ]]);
    } catch (Throwable $e){
        json_response(false, ['error' => 'Ошибка при регистрации']);
    }
}

if ($type === 'login'){
    if (!$email || !$password){
        json_response(false, ['error' => 'Не все поля заполнены']);
    }
    $stmt = $pdo->prepare('SELECT id, name, email, password, uniq_id, avatar_icon, avatar_url FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    if (!$row){
        json_response(false, ['error' => 'Пользователь не найден']);
    }
    if (!password_verify($password, $row['password'])){
        json_response(false, ['error' => 'Неверный пароль']);
    }
    json_response(true, ['user' => [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'uniq_id' => $row['uniq_id'],
        'avatar_icon' => $row['avatar_icon'] ?? null,
        'avatar_url' => $row['avatar_url'] ?? null,
    ]]);
}

// Change password operation
if ($type === 'change_password'){
    $user_uniq_id = $_POST['user_uniq_id'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!$user_uniq_id || !$current_password || !$new_password){
        json_response(false, ['error' => 'Не все поля заполнены']);
    }
    if (strlen($new_password) < 6){
        json_response(false, ['error' => 'Новый пароль должен быть не менее 6 символов']);
    }

    // Find user by uniq_id
    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE uniq_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $user_uniq_id]);
    $row = $stmt->fetch();
    if (!$row){
        json_response(false, ['error' => 'Пользователь не найден']);
    }

    // Verify current password
    if (!password_verify($current_password, $row['password'])){
        json_response(false, ['error' => 'Текущий пароль неверный']);
    }

    // Update to new hashed password
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    try{
        $upd = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $upd->execute([':password' => $hash, ':id' => (int)$row['id']]);
        json_response(true, ['message' => 'Пароль обновлён']);
    } catch (Throwable $e){
        json_response(false, ['error' => 'Ошибка при обновлении пароля']);
    }
}

json_response(false, ['error' => 'Неизвестная операция']);

