const postsGrid = document.getElementById('posts');
const weekLabel = document.getElementById('week-label');
const progressLabel = document.getElementById('progress-label');
const toast = document.getElementById('toast');
const notifyBtn = document.getElementById('notify-slack');

const PLATFORM_LABELS = { facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok' };
const STATUS_LABELS = { pending: 'À valider', approved: 'Validé', rejected: 'Refusé' };

let state = { weekOf: null, posts: [] };

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
  progressLabel.textContent = `${done}/${total} traités`;
}

async function fetchPosts() {
  const res = await fetch('/api/posts');
  state = await res.json();
  weekLabel.textContent = formatWeek(state.weekOf);
  render();
}

async function updatePost(id, patch) {
  const res = await fetch(`/api/posts/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(patch),
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

function renderCard(post) {
  const card = document.createElement('article');
  card.className = 'card';
  card.dataset.id = post.id;

  card.innerHTML = `
    <div class="card-media">
      <img src="${post.thumbnailUrl}" alt="Visuel : ${post.title}" loading="lazy" />
      <div class="media-fallback">
        <span>🖼️ Aperçu indisponible</span>
        <span>(le lien Canva reste valable)</span>
      </div>
    </div>
    <div class="card-body">
      <span class="category-badge">${post.category}</span>
      <h2 class="card-title">${post.title}</h2>

      <textarea class="caption-box" data-field="caption">${post.caption}</textarea>

      <div class="platforms">
        ${Object.entries(PLATFORM_LABELS)
          .map(
            ([key, label]) => `
          <label>
            <input type="checkbox" data-platform="${key}" ${post.platforms[key] ? 'checked' : ''} />
            ${label}
          </label>`
          )
          .join('')}
      </div>

      <input class="note-box" type="text" data-field="note" placeholder="Note interne (optionnel)" value="${post.note || ''}" />

      <div class="actions-row">
        <a class="btn" href="${post.canvaViewUrl}" target="_blank" rel="noopener">⬇️ Télécharger le visuel (Canva)</a>
        <button class="btn copy-btn">📋 Copier le texte</button>
      </div>

      <div class="status-row">
        <span class="status-pill status-${post.status}">${STATUS_LABELS[post.status]}</span>
        <div class="validate-actions">
          <button class="btn btn-approve ${post.status === 'approved' ? 'active' : ''}" data-status="approved">✓ Valider</button>
          <button class="btn btn-reject ${post.status === 'rejected' ? 'active' : ''}" data-status="rejected">✕ Refuser</button>
        </div>
      </div>
    </div>
  `;

  const media = card.querySelector('.card-media');
  const img = media.querySelector('img');
  img.addEventListener('error', () => media.classList.add('broken'));

  const saveCaption = debounce((value) => updatePost(post.id, { caption: value }), 600);
  card.querySelector('.caption-box').addEventListener('input', (e) => saveCaption(e.target.value));

  const saveNote = debounce((value) => updatePost(post.id, { note: value }), 600);
  card.querySelector('.note-box').addEventListener('input', (e) => saveNote(e.target.value));

  card.querySelectorAll('input[data-platform]').forEach((input) => {
    input.addEventListener('change', () => {
      updatePost(post.id, { platforms: { [input.dataset.platform]: input.checked } });
    });
  });

  card.querySelector('.copy-btn').addEventListener('click', async (e) => {
    const textarea = card.querySelector('.caption-box');
    await navigator.clipboard.writeText(textarea.value);
    showToast('Texte copié !');
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
  card.querySelector('[data-status="approved"]').classList.toggle('active', post.status === 'approved');
  card.querySelector('[data-status="rejected"]').classList.toggle('active', post.status === 'rejected');
}

function render() {
  postsGrid.innerHTML = '';
  state.posts.forEach((post) => postsGrid.appendChild(renderCard(post)));
  updateProgress();
}

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
    const res = await fetch('/api/notify-slack', {
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

fetchPosts();
