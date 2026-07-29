<?php
// Méthode avancée (webhook temps réel) : appelée par Slack (Events API) à
// chaque nouveau message dans #visuels-hebdo. Nécessite de créer une app
// Slack avec Event Subscriptions — voir README.md.
//
// Pour la méthode recommandée (plus simple, via cron), voir sync-slack.php.

require __DIR__ . '/slack-parser.php';

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$signingSecret = $config['slack_signing_secret'] ?? '';
$sourceChannel = $config['slack_source_channel_id'] ?? '';
$dataFile = __DIR__ . '/data/posts.json';

$rawBody = file_get_contents('php://input');

if ($signingSecret) {
    $timestamp = $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] ?? '';
    $slackSignature = $_SERVER['HTTP_X_SLACK_SIGNATURE'] ?? '';
    if (!$timestamp || abs(time() - (int) $timestamp) > 300) {
        http_response_code(400);
        exit('Requête expirée');
    }
    $expected = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $signingSecret);
    if (!hash_equals($expected, $slackSignature)) {
        http_response_code(401);
        exit('Signature invalide');
    }
}

$payload = json_decode($rawBody, true) ?: [];

// Étape de vérification demandée par Slack à la configuration de l'URL.
if (($payload['type'] ?? '') === 'url_verification') {
    header('Content-Type: text/plain');
    echo $payload['challenge'] ?? '';
    exit;
}

// On répond tout de suite à Slack (il attend une réponse sous 3s), puis on
// continue le traitement (y compris les appels réseau vers Canva, plus lents)
// après avoir libéré la connexion, si le serveur le permet.
http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (($payload['type'] ?? '') !== 'event_callback') {
    exit;
}

$event = $payload['event'] ?? [];
$isPlainMessage = ($event['type'] ?? '') === 'message'
    && empty($event['subtype'])
    && empty($event['bot_id'])
    && empty($event['thread_ts']); // ignore les réponses en fil de discussion

if (!$isPlainMessage) {
    exit;
}
if ($sourceChannel && ($event['channel'] ?? '') !== $sourceChannel) {
    exit;
}

$text = $event['text'] ?? '';
$parsedPosts = parse_weekly_message($text);
$videoBonus = extract_video_bonus($text);

if (empty($parsedPosts)) {
    exit;
}

foreach ($parsedPosts as &$p) {
    $p['thumbnailUrl'] = fetch_og_image($p['link']) ?? '';
}
unset($p);

save_new_week($dataFile, $parsedPosts, $videoBonus);
