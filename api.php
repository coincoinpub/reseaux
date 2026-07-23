<?php
header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/data/posts.json';
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : ['slack_webhook_url' => ''];

function read_data($dataFile) {
    return json_decode(file_get_contents($dataFile), true);
}

function write_data($dataFile, $data) {
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$action = $_GET['action'] ?? '';

if ($action === 'posts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(read_data($dataFile));
    exit;
}

if ($action === 'update-post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body();
    $id = $body['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Champ "id" requis.']);
        exit;
    }

    $data = read_data($dataFile);
    $found = false;
    foreach ($data['posts'] as &$post) {
        if ($post['id'] === $id) {
            $found = true;
            if (isset($body['caption']) && is_string($body['caption'])) {
                $post['caption'] = $body['caption'];
            }
            if (isset($body['platforms']) && is_array($body['platforms'])) {
                $post['platforms'] = array_merge($post['platforms'], $body['platforms']);
            }
            if (isset($body['status']) && in_array($body['status'], ['pending', 'approved', 'rejected'], true)) {
                $post['status'] = $body['status'];
            }
            if (isset($body['note']) && is_string($body['note'])) {
                $post['note'] = $body['note'];
            }
            $post['updatedAt'] = gmdate('c');
            $updatedPost = $post;
            break;
        }
    }
    unset($post);

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Post introuvable']);
        exit;
    }

    write_data($dataFile, $data);
    echo json_encode($updatedPost);
    exit;
}

if ($action === 'notify-slack' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($config['slack_webhook_url'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Webhook Slack non configuré (voir config.php).']);
        exit;
    }

    $body = read_json_body();
    $text = $body['text'] ?? '';
    if ($text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Champ "text" requis.']);
        exit;
    }

    $ch = curl_init($config['slack_webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['text' => $text]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $status >= 300) {
        http_response_code(502);
        echo json_encode(['error' => 'Slack a répondu avec une erreur.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Action inconnue']);
