<?php
// Méthode RECOMMANDÉE pour actualiser la page automatiquement : ce script va
// chercher lui-même le dernier message de #visuels-hebdo (au lieu d'attendre
// que Slack le pousse), à appeler via un Cron Job Hostinger (hPanel > Avancé
// > Cron Jobs). Voir README.md pour la configuration pas à pas.
//
// Protégé par une clé (?key=...) car accessible publiquement par URL. Le
// bouton "Actualiser" de la page elle-même utilise api.php?action=refresh à
// la place, qui fait exactement la même chose sans avoir besoin de la clé.

require __DIR__ . '/slack-parser.php';

@set_time_limit(60);
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$cronSecret = $config['cron_secret'] ?? '';
$dataFile = __DIR__ . '/data/posts.json';

if ($cronSecret) {
    $providedKey = $_GET['key'] ?? '';
    if (!hash_equals($cronSecret, $providedKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Clé invalide']);
        exit;
    }
}

$result = sync_from_slack($config['slack_bot_token'] ?? '', $config['slack_source_channel_id'] ?? '', $dataFile);

if (isset($result['error'])) {
    $isConfigError = str_contains($result['error'], 'config.php');
    http_response_code($isConfigError ? 400 : 502);
}
echo json_encode($result);
