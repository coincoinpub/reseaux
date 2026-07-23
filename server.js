require('dotenv').config();
const express = require('express');
const path = require('path');
const fs = require('fs/promises');

const app = express();
const PORT = process.env.PORT || 3000;
const DATA_FILE = path.join(__dirname, 'data', 'posts.json');
const SLACK_WEBHOOK_URL = process.env.SLACK_WEBHOOK_URL || '';

app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

async function readData() {
  const raw = await fs.readFile(DATA_FILE, 'utf-8');
  return JSON.parse(raw);
}

async function writeData(data) {
  await fs.writeFile(DATA_FILE, JSON.stringify(data, null, 2));
}

app.get('/api/posts', async (req, res) => {
  const data = await readData();
  res.json(data);
});

app.put('/api/posts/:id', async (req, res) => {
  const data = await readData();
  const post = data.posts.find((p) => p.id === req.params.id);
  if (!post) return res.status(404).json({ error: 'Post introuvable' });

  const { caption, platforms, status, note } = req.body;
  if (typeof caption === 'string') post.caption = caption;
  if (platforms && typeof platforms === 'object') {
    post.platforms = { ...post.platforms, ...platforms };
  }
  if (status && ['pending', 'approved', 'rejected'].includes(status)) {
    post.status = status;
  }
  if (typeof note === 'string') post.note = note;
  post.updatedAt = new Date().toISOString();

  await writeData(data);
  res.json(post);
});

app.post('/api/notify-slack', async (req, res) => {
  if (!SLACK_WEBHOOK_URL) {
    return res.status(400).json({ error: 'SLACK_WEBHOOK_URL non configuré (voir README).' });
  }

  const { text } = req.body;
  if (!text) return res.status(400).json({ error: 'Champ "text" requis.' });

  try {
    const slackRes = await fetch(SLACK_WEBHOOK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text }),
    });
    if (!slackRes.ok) throw new Error(`Slack a répondu ${slackRes.status}`);
    res.json({ ok: true });
  } catch (err) {
    res.status(502).json({ error: err.message });
  }
});

app.listen(PORT, () => {
  console.log(`Coin Coin Réseaux dispo sur http://localhost:${PORT}`);
});
