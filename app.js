const postsGrid = document.getElementById('posts');
const weekLabel = document.getElementById('week-label');
const weekPrevBtn = document.getElementById('week-prev');
const weekNextBtn = document.getElementById('week-next');
const readonlyBadge = document.getElementById('readonly-badge');
const progressLabel = document.getElementById('progress-label');
const toast = document.getElementById('toast');
const notifyBtn = document.getElementById('notify-slack');

const PLATFORM_LABELS = { facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok' };
const STATUS_LABELS = { pending: 'À valider', approved: 'Validé', rejected: 'Refusé' };

let state = { weekOf: null, posts: [], readOnly: false };
let weeksList = []; // du plus récent au plus ancien
let weekIndex = 0;

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 2200);
}

function formatWeek(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return `Semaine du ${d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })}`;
}

function updateProgress() {
  const total = state.posts.length;
  const done = state.posts.filter((p) => p.status !== 'pending').length;
  progressLabel.textContent = total ? `${done}/${total} traités` : '';
}

async function fetchWeeksList() {
  try {
    const res = await fetch('api.php?action=weeks');
    const data = await res.json();
    weeksList = data.weeks || [];
  } catch {
    weeksList = [];
  }
}

async function loadWeek(index) {
  weekIndex = Math.max(0, Math.min(index, weeksList.length - 1));
  const week = weeksList[weekIndex];
  const url = weekIndex === 0 ? 'api.php?action=posts' : `api.php?action=posts&week=${encodeURIComponent(week)}`;

  const res = await fetch(url);
  if (!res.ok) {
    showToast('Semaine introuvable');
    return;
  }
  state = await res.json();
  render();
  renderVideo();
  updateWeekNav();
}

function updateWeekNav() {
  weekLabel.textContent = formatWeek(state.weekOf);
  weekPrevBtn.disabled = weekIndex >= weeksList.length - 1;
  weekNextBtn.disabled = weekIndex <= 0;
  readonlyBadge.hidden = !state.readOnly;
  notifyBtn.hidden = !!state.readOnly;
}

async function updatePost(id, patch) {
  const res = await fetch('api.php?action=update-post', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...patch }),
  });
  if (!res.ok) {
    showToast('Erreur de sauvegarde');
    return null;
  }
  const updated = await res.json();
  const idx = state.posts.findIndex((p) => p.id === id);
  if (idx !== -1) state.posts[idx] = updated;
  updateProgress();
  return updated;
}

function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function renderCard(post, readOnly) {
  const card = document.createElement('article');
  card.className = 'card' + (readOnly ? ' read-only' : '');
  card.dataset.id = post.id;

  const mediaBlock = post.thumbnailUrl
    ? `<div class="card-media"><img src="${escapeHtml(post.thumbnailUrl)}" alt="Visuel : ${escapeHtml(post.title)}" loading="lazy" /></div>`
    : '';

  const ro = readOnly ? 'readonly' : '';
  const roDisabled = readOnly ? 'disabled' : '';

  card.innerHTML = `
    ${mediaBlock}
    <div class="card-body">
      <div class="title-row">
        <input class="category-input" data-field="category" value="${escapeHtml(post.category)}" placeholder="Catégorie" ${ro} />
      </div>
      <input class="title-input" data-field="title" value="${escapeHtml(post.title)}" placeholder="Titre du post" ${ro} />

      <textarea class="caption-box" data-field="caption" ${ro}>${escapeHtml(post.caption)}</textarea>

      <div class="platforms">
        ${Object.entries(PLATFORM_LABELS)
          .map(
            ([key, label]) => `
          <label>
            <input type="checkbox" data-platform="${key}" ${post.platforms[key] ? 'checked' : ''} ${roDisabled} />
            ${label}
          </label>`
          )
          .join('')}
      </div>

      <input class="note-box" type="text" data-field="note" placeholder="Note interne (optionnel)" value="${escapeHtml(post.note || '')}" ${ro} />

      <div class="actions-row">
        <a class="btn" href="${escapeHtml(post.canvaViewUrl)}" target="_blank" rel="noopener">⬇️ Télécharger le visuel (Canva)</a>
        <button class="btn copy-btn">📋 Copier le texte</button>
      </div>

      <div class="status-row">
        <span class="status-pill status-${post.status}">${STATUS_LABELS[post.status]}</span>
        ${
          readOnly
            ? ''
            : `<div class="validate-actions">
          <button class="btn btn-approve ${post.status === 'approved' ? 'active' : ''}" data-status="approved">✓ Valider</button>
          <button class="btn btn-reject ${post.status === 'rejected' ? 'active' : ''}" data-status="rejected">✕ Refuser</button>
        </div>`
        }
      </div>
    </div>
  `;

  const media = card.querySelector('.card-media');
  const img = media ? media.querySelector('img') : null;
  // Lien Canva expiré : on retire la carte média plutôt que d'afficher une icône cassée.
  if (img) img.addEventListener('error', () => media.remove());

  card.querySelector('.copy-btn').addEventListener('click', async () => {
    const textarea = card.querySelector('.caption-box');
    await navigator.clipboard.writeText(textarea.value);
    showToast('Texte copié !');
  });

  if (readOnly) {
    return card;
  }

  const saveTitle = debounce((value) => updatePost(post.id, { title: value }), 600);
  card.querySelector('.title-input').addEventListener('input', (e) => saveTitle(e.target.value));

  const saveCategory = debounce((value) => updatePost(post.id, { category: value }), 600);
  card.querySelector('.category-input').addEventListener('input', (e) => saveCategory(e.target.value));

  const saveCaption = debounce((value) => updatePost(post.id, { caption: value }), 600);
  card.querySelector('.caption-box').addEventListener('input', (e) => saveCaption(e.target.value));

  const saveNote = debounce((value) => updatePost(post.id, { note: value }), 600);
  card.querySelector('.note-box').addEventListener('input', (e) => saveNote(e.target.value));

  card.querySelectorAll('input[data-platform]').forEach((input) => {
    input.addEventListener('change', () => {
      updatePost(post.id, { platforms: { [input.dataset.platform]: input.checked } });
    });
  });

  card.querySelectorAll('[data-status]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      // Cliquer sur un statut déjà actif le remet "à valider"
      const finalStatus = btn.classList.contains('active') ? 'pending' : btn.dataset.status;
      const updated = await updatePost(post.id, { status: finalStatus });
      if (updated) refreshCardStatus(card, updated);
    });
  });

  return card;
}

function refreshCardStatus(card, post) {
  const pill = card.querySelector('.status-pill');
  pill.className = `status-pill status-${post.status}`;
  pill.textContent = STATUS_LABELS[post.status];
  card.querySelector('[data-status="approved"]')?.classList.toggle('active', post.status === 'approved');
  card.querySelector('[data-status="rejected"]')?.classList.toggle('active', post.status === 'rejected');
}

function render() {
  postsGrid.innerHTML = '';
  state.posts.forEach((post) => postsGrid.appendChild(renderCard(post, !!state.readOnly)));
  updateProgress();
}

function renderVideo() {
  const video = state.video || { script: '', prompt: '' };
  const scriptBox = document.getElementById('video-script');
  const promptBox = document.getElementById('video-prompt');
  scriptBox.value = video.script || '';
  promptBox.value = video.prompt || '';
  scriptBox.readOnly = !!state.readOnly;
  promptBox.readOnly = !!state.readOnly;

  // On enlève d'anciens listeners éventuels en clonant les nœuds, pour éviter
  // d'empiler des handlers à chaque changement de semaine.
  const freshScriptBox = scriptBox.cloneNode(true);
  scriptBox.replaceWith(freshScriptBox);
  const freshPromptBox = promptBox.cloneNode(true);
  promptBox.replaceWith(freshPromptBox);

  if (!state.readOnly) {
    const saveScript = debounce((value) => updateVideo({ script: value }), 600);
    freshScriptBox.addEventListener('input', (e) => saveScript(e.target.value));

    const savePrompt = debounce((value) => updateVideo({ prompt: value }), 600);
    freshPromptBox.addEventListener('input', (e) => savePrompt(e.target.value));
  }

  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.replaceWith(btn.cloneNode(true));
  });
  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const target = document.getElementById(btn.dataset.copyTarget);
      await navigator.clipboard.writeText(target.value);
      showToast('Copié !');
    });
  });
}

async function updateVideo(patch) {
  const res = await fetch('api.php?action=update-video', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(patch),
  });
  if (!res.ok) {
    showToast('Erreur de sauvegarde');
    return null;
  }
  const updated = await res.json();
  state.video = updated;
  return updated;
}

weekPrevBtn.addEventListener('click', () => loadWeek(weekIndex + 1));
weekNextBtn.addEventListener('click', () => loadWeek(weekIndex - 1));

notifyBtn.addEventListener('click', async () => {
  const lines = state.posts.map((p) => {
    const platforms = Object.entries(p.platforms)
      .filter(([, on]) => on)
      .map(([key]) => PLATFORM_LABELS[key])
      .join(', ') || 'aucune plateforme sélectionnée';
    return `• *${p.title}* — ${STATUS_LABELS[p.status]} — ${platforms}`;
  });
  const text = `🦆 *Coin Coin Réseaux — ${formatWeek(state.weekOf)}*\n${lines.join('\n')}`;

  notifyBtn.disabled = true;
  try {
    const res = await fetch('api.php?action=notify-slack', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Erreur inconnue');
    showToast('Résumé envoyé sur Slack !');
  } catch (err) {
    showToast(`Slack non configuré : ${err.message}`);
  } finally {
    notifyBtn.disabled = false;
  }
});

async function init() {
  await fetchWeeksList();
  if (weeksList.length === 0) {
    // Filet de sécurité : si l'API "weeks" échoue, on affiche quand même la semaine en cours.
    weeksList = [null];
  }
  await loadWeek(0);
}

init();
