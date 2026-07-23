# Coin Coin Réseaux

Page interne de centralisation et de validation des publications hebdomadaires
(Facebook, Instagram, TikTok) pour **Coin Coin Publicité**.

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

## Démarrer

```bash
npm install
npm start
```

Puis ouvrir http://localhost:3000

## Configurer la notification Slack (optionnel)

1. Dans Slack, créer un **Incoming Webhook** pour le canal `#all-coin-coin`
   (Réglages de l'espace de travail > Apps > Incoming Webhooks > Ajouter une
   configuration de webhook, choisir le canal).
2. Copier `.env.example` en `.env` et coller l'URL du webhook :
   ```
   SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
   ```
3. Redémarrer le serveur. Le bouton **« Envoyer le résumé sur Slack »** en
   haut de la page postera alors l'état de validation des 4 posts dans le
   canal.

## Mettre à jour les visuels chaque semaine

Les posts affichés viennent du fichier `data/posts.json`. Pour l'instant, la
mise à jour hebdomadaire se fait manuellement à partir du dossier Canva
« Visuel (Hebdo) » : titre, catégorie et texte de légende sont à reporter dans
`data/posts.json` (un post = un objet dans le tableau `posts`).

Comme il n'y a pas encore de connexion permanente à l'API Canva, l'aperçu
visuel de chaque post (`thumbnailUrl`) peut expirer après un certain temps —
c'est normal. Le bouton **« Télécharger le visuel (Canva) »** reste toujours
valide et ouvre directement le design correspondant dans Canva.

## Où sont stockées les validations ?

Tout est persisté dans `data/posts.json` (texte modifié, plateformes cochées,
statut validé/refusé, notes internes). Pas de base de données externe pour
l'instant — ce fichier est la source de vérité.

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
