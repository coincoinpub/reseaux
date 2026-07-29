<?php
// Copie ce fichier en "config.php" (même dossier) et complète les valeurs.
// config.php n'est jamais envoyé sur GitHub (voir .gitignore) : tu peux y mettre
// les vraies valeurs sans risque.

return [
    // Pour ENVOYER un résumé sur Slack (bouton "Envoyer le résumé sur Slack") :
    // Slack > Réglages de l'espace de travail > Apps > Incoming Webhooks
    // > Ajouter une configuration de webhook > choisir #all-coin-coin
    'slack_webhook_url' => '',

    // --- Actualisation automatique depuis #visuels-hebdo (voir README) ---

    // Méthode recommandée (sync-slack.php + Cron Job Hostinger) :
    // Slack > api.slack.com/apps > ton app > OAuth & Permissions
    // > Bot User OAuth Token (commence par "xoxb-")
    'slack_bot_token' => '',

    // Mot de passe au choix (invente une suite de caractères), à remettre
    // dans l'URL du Cron Job Hostinger : sync-slack.php?key=CE_MOT_DE_PASSE
    'cron_secret' => '',

    // ID du canal à écouter (#visuels-hebdo). Déjà rempli, à changer seulement
    // si tu recrées le canal.
    'slack_source_channel_id' => 'C0BDUKC15E3',

    // Méthode avancée uniquement (slack-events.php, webhook temps réel) :
    // Slack > api.slack.com/apps > ton app > Basic Information > Signing Secret
    'slack_signing_secret' => '',
];
