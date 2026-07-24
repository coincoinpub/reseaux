<?php
// Copie ce fichier en "config.php" (même dossier) et complète les valeurs.
// config.php n'est jamais envoyé sur GitHub (voir .gitignore) : tu peux y mettre
// les vraies valeurs sans risque.

return [
    // Pour ENVOYER un résumé sur Slack (bouton "Envoyer le résumé sur Slack") :
    // Slack > Réglages de l'espace de travail > Apps > Incoming Webhooks
    // > Ajouter une configuration de webhook > choisir #all-coin-coin
    'slack_webhook_url' => '',

    // Pour RECEVOIR automatiquement les nouveaux posts hebdo depuis #visuels-hebdo :
    // Slack > api.slack.com/apps > ton app > Basic Information > Signing Secret
    'slack_signing_secret' => '',

    // ID du canal à écouter (#visuels-hebdo). Déjà rempli, à changer seulement
    // si tu recrées le canal.
    'slack_source_channel_id' => 'C0BDUKC15E3',
];
