# Coin Coin Réseaux

Page interne de centralisation et de validation des publications hebdomadaires
(Facebook, Instagram, TikTok) pour **Coin Coin Publicité**, prévue pour
`coin-coin.fr/publi`.

Chaque semaine, 4 visuels sont créés dans le dossier Canva **« Visuel (Hebdo) »**.
Cette page permet de :

- voir les 4 visuels de la semaine en un seul endroit,
- relire/modifier le texte de chaque publication,
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

## Mise en ligne sur Hostinger (aucune connaissance technique requise)

Ce site est en PHP + fichiers statiques : pas de Node.js, pas de terminal,
pas d'installation. Ça fonctionne directement sur un hébergement web
Hostinger classique, comme un site normal.

1. Dans **hPanel → Gestionnaire de fichiers**, va dans `public_html`.
2. Crée un dossier `publi`.
3. Mets-y tous les fichiers de ce dépôt **sauf** `README.md`, `.gitignore` et
   `config.example.php`, c'est-à-dire :
   - `index.html`, `style.css`, `app.js`, `api.php`
   - le dossier `data/` (avec `posts.json` et `.htaccess`)
4. Duplique `config.example.php`, renomme la copie en `config.php`, et
   colle-la aussi dans le dossier `publi` (voir étape Slack ci-dessous pour
   ce qu'il faut mettre dedans).
5. Ouvre `coin-coin.fr/publi` : la page doit s'afficher directement.

Si la sauvegarde des validations ne fonctionne pas, il faut probablement
donner les droits d'écriture au fichier `data/posts.json` : dans le
Gestionnaire de fichiers, clic droit sur le fichier → Permissions → cocher
écriture (souvent `644` ou `664`).

## Configurer la notification Slack (optionnel)

1. Dans Slack, créer un **Incoming Webhook** pour le canal `#all-coin-coin`
   (Réglages de l'espace de travail > Apps > Incoming Webhooks > Ajouter une
   configuration de webhook, choisir le canal).
2. Ouvrir `config.php` (créé à partir de `config.example.php`, voir plus haut)
   avec l'éditeur de fichiers de hPanel et coller l'URL :
   ```php
   'slack_webhook_url' => 'https://hooks.slack.com/services/...',
   ```
3. Le bouton **« Envoyer le résumé sur Slack »** en haut de la page postera
   alors l'état de validation des 4 posts dans le canal.

`config.php` n'est jamais mis sur GitHub (il est ignoré par `.gitignore`),
donc l'URL du webhook reste privée.

## Mettre à jour les visuels chaque semaine

Les posts affichés viennent du fichier `data/posts.json`. Pour l'instant, la
mise à jour hebdomadaire se fait manuellement à partir du dossier Canva
« Visuel (Hebdo) » : titre, catégorie et texte de légende sont à reporter dans
`data/posts.json` (un post = un objet dans le tableau `posts`), directement
depuis l'éditeur de fichiers de hPanel, ou en demandant à Claude de le faire.

Comme il n'y a pas encore de connexion permanente à l'API Canva, l'aperçu
visuel de chaque post (`thumbnailUrl`) peut expirer après un certain temps —
c'est normal. Le bouton **« Télécharger le visuel (Canva) »** reste toujours
valide et ouvre directement le design correspondant dans Canva.

## Où sont stockées les validations ?

Tout est persisté dans `data/posts.json` (texte modifié, plateformes cochées,
statut validé/refusé, notes internes). Pas de base de données externe pour
l'instant — ce fichier est la source de vérité. Le dossier `data/` est
protégé par un `.htaccess` pour empêcher son accès direct depuis un
navigateur.

## Page « Liens »

`liens.html` est une page annuaire qui centralise tous les raccourcis Coin Coin
(Drive, Upload, page Publi, page Jeu, page Dépliant, page Muscu, page RDV,
GitHub). Elle reprend le même style visuel que la page d'accueil et un lien
croisé existe entre les deux pages (bouton « 🔗 Liens » / « 🦆 Validation
hebdo » en haut à droite).

Les liens sont définis dans `liens.js`, dans le tableau `LINKS` en haut du
fichier. Pour ajouter ou corriger une URL, il suffit de renseigner le champ
`url` de l'entrée correspondante (une chaîne vide affiche la carte comme
« Lien à ajouter »). Aucune base de données ni fichier `data/` n'est requis
pour cette page.

## Roadmap (publication automatique)

Une fois les accès créés côté Meta et TikTok, l'automatisation complète
(publication directe depuis cette page) pourra être ajoutée :

- **Facebook + Instagram** : créer une app sur [developers.facebook.com](https://developers.facebook.com),
  obtenir un accès à l'API Graph (Pages + Instagram Graph API) pour le compte
  Coin Coin Publicité.
- **TikTok** : créer une app sur [developers.tiktok.com](https://developers.tiktok.com)
  (TikTok for Business / Content Posting API).
- **Canva** : pour rafraîchir automatiquement les visuels hebdo (au lieu
  d'une mise à jour manuelle de `data/posts.json`), connecter l'API Canva
  Connect (OAuth) côté serveur.

Ces trois intégrations sont indépendantes et peuvent être ajoutées une par
une, sans changer le fonctionnement de la page de validation.
