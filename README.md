# Coin Coin Réseaux

Page interne de centralisation et de validation des publications hebdomadaires
(Facebook, Instagram, TikTok) pour **Coin Coin Publicité**, en ligne sur
`coin-coin.fr/publi`.

Chaque semaine, un message est posté dans Slack **#visuels-hebdo** avec les
visuels créés dans Canva (dossier « Visuel (Hebdo) ») : les liens et les
textes de chaque post. Cette page permet de :

- voir les posts de la semaine en un seul endroit (mis à jour automatiquement
  dès que le message hebdo arrive dans Slack, voir plus bas),
- relire/modifier le titre, la catégorie et le texte de chaque publication,
- choisir les plateformes de diffusion (Facebook / Instagram / TikTok),
- valider ou refuser chaque post,
- ouvrir/télécharger le visuel directement depuis Canva,
- copier le texte prêt à coller,
- envoyer un résumé de l'état de validation sur Slack (`#all-coin-coin`).

**Important : ce n'est pas un outil de publication automatique.** Coin Coin
Publicité n'a pas encore de comptes développeur Meta/TikTok, donc la
publication finale sur Facebook, Instagram et TikTok reste manuelle : une fois
un post validé, on télécharge le visuel et on copie le texte pour le publier
soi-même sur chaque plateforme. Voir la section [Roadmap](#roadmap-publication-automatique).

Le style de la page (couleurs, police, logo) reprend celui de
[coin-coin.fr](https://coin-coin.fr) (dépôt `coincoinpub/site`), pour rester
cohérent avec le reste du site.

## Mise en ligne sur Hostinger (aucune connaissance technique requise)

Ce site est en PHP + fichiers statiques : pas de Node.js, pas de terminal,
pas d'installation. Ça fonctionne directement sur un hébergement web
Hostinger classique, comme un site normal.

1. Dans **hPanel → Gestionnaire de fichiers**, va dans `public_html`.
2. Crée un dossier `publi`.
3. Mets-y tous les fichiers de ce dépôt **sauf** `README.md`, `.gitignore` et
   `config.example.php`, c'est-à-dire :
   - `index.html`, `style.css`, `app.js`, `logo.svg`
   - `api.php`, `slack-parser.php`, `sync-slack.php`, `slack-events.php`
   - le dossier `data/` (avec `posts.json` et `.htaccess`)
4. Duplique `config.example.php`, renomme la copie en `config.php`, et
   colle-la aussi dans le dossier `publi` (voir les étapes Slack ci-dessous
   pour ce qu'il faut mettre dedans).
5. Ouvre `coin-coin.fr/publi` : la page doit s'afficher directement.

Si la sauvegarde des validations ne fonctionne pas, il faut probablement
donner les droits d'écriture au fichier `data/posts.json` : dans le
Gestionnaire de fichiers, clic droit sur le fichier → Permissions → cocher
écriture (souvent `644` ou `664`).

## Actualisation automatique depuis Slack (#visuels-hebdo)

La page peut aller chercher elle-même le dernier message de
**#visuels-hebdo** à intervalle régulier (par exemple une fois par jour), et
se mettre à jour automatiquement avec les nouveaux posts (titre, catégorie,
texte, lien Canva) — sans que tu aies rien à faire manuellement une fois que
c'est en place. Ça marche même si le texte n'est pas parfaitement structuré :
il est analysé automatiquement, et tout reste modifiable ensuite sur la page.

C'est totalement sans risque de le faire tourner souvent (tous les jours, par
exemple, pour ne pas dépendre du jour exact où le message hebdo est posté) :
s'il n'y a pas de nouveau message depuis la dernière fois, rien ne bouge sur
la page — les validations déjà faites sont préservées.

Deux étapes : créer un accès Slack (une seule fois), puis programmer un Cron
Job Hostinger.

### 1. Créer l'accès Slack (lecture seule)

1. Va sur [api.slack.com/apps](https://api.slack.com/apps) → **Create New App**
   → **From scratch**. Nomme-la par exemple « Coin Coin Réseaux Sync » et
   choisis l'espace de travail Coin Coin.
2. Dans le menu de gauche, **OAuth & Permissions** → descends jusqu'à **Scopes**
   → **Bot Token Scopes** → **Add an OAuth Scope**, ajoute :
   - `channels:history`
   - `channels:read`
3. Toujours sur cette page, clique **Install to Workspace** en haut, puis
   **Autoriser**.
4. Une fois installée, copie le **Bot User OAuth Token** (commence par `xoxb-`)
   et colle-le dans `config.php` :
   ```php
   'slack_bot_token' => 'xoxb-xxxxxxxxxxxxx',
   ```
5. Invente un mot de passe (n'importe quelle suite de caractères) et mets-le
   dans `config.php` aussi :
   ```php
   'cron_secret' => 'un-mot-de-passe-au-choix',
   ```
6. Dans Slack, va dans le canal **#visuels-hebdo** et tape `/invite
   @Coin Coin Réseaux Sync` (ou le nom donné à l'app) pour l'ajouter au canal
   — sinon elle ne peut pas lire les messages.

`slack_source_channel_id` dans `config.php` est déjà pré-rempli avec l'ID du
canal #visuels-hebdo.

### 2. Programmer le Cron Job sur Hostinger

1. Dans **hPanel → Avancé → Cron Jobs**, clique **Créer un nouveau Cron Job**.
2. Fréquence : par exemple **une fois par jour**.
3. Commande / URL à appeler :
   ```
   https://coin-coin.fr/publi/sync-slack.php?key=un-mot-de-passe-au-choix
   ```
   (le même mot de passe que `cron_secret` dans `config.php`)
4. Enregistre.

C'est tout — à partir de maintenant, la page se met à jour toute seule.
Pour vérifier que ça fonctionne sans attendre le cron, tu peux copier-coller
cette URL directement dans un navigateur : elle affiche `"status":"updated"`
si un nouveau message a été trouvé et pris en compte, ou `"status":"no_change"`
si rien de nouveau.

### Méthode avancée (alternative, non recommandée)

`slack-events.php` existe aussi dans ce dépôt pour une actualisation en temps
réel (dès la seconde où le message est posté, via l'Events API de Slack), mais
demande de configurer une URL vérifiée par Slack en plus — plus de manipulation
pour un gain minime ici. À réserver à un usage avancé si besoin.

## Configurer la notification Slack sortante (optionnel)

1. Dans Slack, créer un **Incoming Webhook** pour le canal `#all-coin-coin`
   (Réglages de l'espace de travail > Apps > Incoming Webhooks > Ajouter une
   configuration de webhook, choisir le canal).
2. Ouvrir `config.php` avec l'éditeur de fichiers de hPanel et coller l'URL :
   ```php
   'slack_webhook_url' => 'https://hooks.slack.com/services/...',
   ```
3. Le bouton **« Envoyer le résumé sur Slack »** en haut de la page postera
   alors l'état de validation des posts dans le canal.

`config.php` n'est jamais mis sur GitHub (il est ignoré par `.gitignore`),
donc les URL/secrets restent privés.

## Mettre à jour les visuels manuellement (si besoin)

En dehors de l'actualisation automatique, `data/posts.json` peut aussi être
modifié directement (éditeur de fichiers de hPanel, ou en demandant à
Claude) : un post = un objet dans le tableau `posts`.

L'aperçu visuel de chaque post (`thumbnailUrl`) est récupéré automatiquement
depuis Canva lors de l'ingestion Slack (best-effort) ; s'il n'est pas
disponible, le bouton **« Télécharger le visuel (Canva) »** reste toujours
valide et ouvre directement le design correspondant dans Canva.

## Où sont stockées les validations ?

Tout est persisté dans `data/posts.json` (texte modifié, plateformes cochées,
statut validé/refusé, notes internes). Pas de base de données externe pour
l'instant — ce fichier est la source de vérité. Le dossier `data/` est
protégé par un `.htaccess` pour empêcher son accès direct depuis un
navigateur.

## Roadmap (publication automatique)

Une fois les accès créés côté Meta et TikTok, l'automatisation complète
(publication directe depuis cette page) pourra être ajoutée :

- **Facebook + Instagram** : créer une app sur [developers.facebook.com](https://developers.facebook.com),
  obtenir un accès à l'API Graph (Pages + Instagram Graph API) pour le compte
  Coin Coin Publicité.
- **TikTok** : créer une app sur [developers.tiktok.com](https://developers.tiktok.com)
  (TikTok for Business / Content Posting API).

Ces intégrations sont indépendantes et peuvent être ajoutées une par une,
sans changer le fonctionnement de la page de validation.
