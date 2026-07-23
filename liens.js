// Pour modifier un lien : change la valeur "url" ci-dessous.
// Laisser "" affiche la carte comme "lien à ajouter".
const LINKS = [
  {
    icon: '📁',
    title: 'Drive',
    description: "Fichiers, visuels et documents partagés de l'agence.",
    url: '',
  },
  {
    icon: '⬆️',
    title: 'Upload',
    description: 'Déposer rapidement un fichier ou un visuel.',
    url: '',
  },
  {
    icon: '🦆',
    title: 'Publi (réseaux)',
    description: 'Validation hebdo des posts Facebook / Instagram / TikTok.',
    url: 'index.html',
  },
  {
    icon: '🎮',
    title: 'Jeu',
    description: 'Page du jeu concours.',
    url: '',
  },
  {
    icon: '📄',
    title: 'Dépliant',
    description: 'Dépliant / plaquette de présentation.',
    url: '',
  },
  {
    icon: '💪',
    title: 'Muscu',
    description: 'Page muscu.',
    url: '',
  },
  {
    icon: '📅',
    title: 'RDV',
    description: 'Prise de rendez-vous.',
    url: '',
  },
  {
    icon: '🐙',
    title: 'GitHub',
    description: 'Dépôt du code (coincoinpub/reseaux).',
    url: 'https://github.com/coincoinpub/reseaux',
  },
];

const linksGrid = document.getElementById('links');

function renderLink(link) {
  const card = document.createElement(link.url ? 'a' : 'div');
  const missing = !link.url;
  card.className = `link-card${missing ? ' is-missing' : ''}`;
  if (!missing) {
    card.href = link.url;
    if (/^https?:\/\//.test(link.url)) {
      card.target = '_blank';
      card.rel = 'noopener';
    }
  }

  card.innerHTML = `
    <span class="link-icon">${link.icon}</span>
    <h2 class="link-title">${link.title}</h2>
    <p class="link-desc">${link.description}</p>
    ${missing ? '<span class="link-missing-note">Lien à ajouter</span>' : ''}
  `;

  return card;
}

LINKS.forEach((link) => linksGrid.appendChild(renderLink(link)));
