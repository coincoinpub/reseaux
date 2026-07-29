<?php
// Méthode RECOMMANDÉE pour actualiser la page automatiquement : ce script va
// chercher lui-même le dernier message de #visuels-hebdo (au lieu d'attendre
// que Slack le pousse), à appeler via un Cron Job Hostinger (hPanel > Avancé
// > Cron Jobs). Voir README.md pour la configuration pas à pas.

require __DIR__ . '/slack-parser.php';

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$botToken = $config['slack_bot_token'] ?? '';
$channelId = $config['slack_source_channel_id'] ?? '';
$cronSecret = $config['cron_secret'] ?? '';
$dataFile = __DIR__ . '/data/posts.json';

if (!$botToken || !$channelId) {
    http_response_code(400);
    echo json_encode(['error' => 'slack_bot_token / slack_source_channel_id manquant dans config.php']);
    exit;
}

if ($cronSecret) {
    $providedKey = $_GET['key'] ?? '';
    if (!hash_equals($cronSecret, $providedKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Clé invalide']);
        exit;
    }
}

$ch = curl_init('https://slack.com/api/conversations.history?' . http_build_query([
    'channel' => $channelId,
    'limit' => 1,
]));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $botToken],
]);
$raw = curl_exec($ch);
curl_close($ch);

$response = json_decode($raw, true);
if (!($response['ok'] ?? false)) {
    http_response_code(502);
    echo json_encode(['error' => 'Slack API : ' . ($response['error'] ?? 'réponse invalide')]);
    exit;
}

$message = $response['messages'][0] ?? null;
if (!$message) {
    echo json_encode(['status' => 'no_message']);
    exit;
}

$existing = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : null;
$lastTs = $existing['sourceMessageTs'] ?? null;

if ($lastTs && $lastTs === $message['ts']) {
    echo json_encode(['status' => 'no_change', 'lastMessageTs' => $lastTs]);
    exit;
}

$text = $message['text'] ?? '';
$parsedPosts = parse_weekly_message($text);
$videoBonus = extract_video_bonus($text);

if (empty($parsedPosts)) {
    echo json_encode(['status' => 'no_posts_found', 'messageTs' => $message['ts']]);
    exit;
}

foreach ($parsedPosts as &$p) {
    $p['thumbnailUrl'] = fetch_og_image($p['link']) ?? '';
}
unset($p);

save_new_week($dataFile, $parsedPosts, $videoBonus, $message['ts']);

echo json_encode(['status' => 'updated', 'postsCount' => count($parsedPosts), 'messageTs' => $message['ts']]);
