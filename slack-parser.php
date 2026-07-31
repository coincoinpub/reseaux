<?php
// Fonctions partagées pour transformer le message hebdo Slack (#visuels-hebdo)
// en données exploitables par la page. Utilisé par sync-slack.php (méthode
// recommandée, via cron) et slack-events.php (méthode avancée, webhook temps réel).

function slack_unescape($text) {
    return str_replace(['&amp;', '&lt;', '&gt;'], ['&', '<', '>'], $text);
}

function clean_caption_text($text) {
    $text = preg_replace_callback('/<([^|>]+)\|([^>]+)>/', fn($m) => $m[2], $text);
    $text = preg_replace_callback('/<([^>]+)>/', fn($m) => $m[1], $text);
    $text = preg_replace('/^>\s?/m', '', $text);
    $text = preg_replace('/[*_`]/', '', $text);
    $text = preg_replace('/^\s*(Lien|Titre|Texte)\s*:?\s*/im', '', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

function extract_canva_links($text) {
    preg_match_all('/<(https:\/\/(?:www\.)?canva\.com\/d\/[A-Za-z0-9_-]+)(?:\|[^>]*)?>/', $text, $m);
    return $m[1];
}

// Découpe le message hebdo en posts individuels. Tolérant aux variations de
// mise en forme (le message est rédigé à la main chaque semaine) : ignore
// tout ce qui suit la partie "bonus / vidéo / sources", ne garde que les
// blocs numérotés contenant un lien Canva.
function parse_weekly_message($rawText) {
    $text = slack_unescape($rawText);

    $stopMarkers = [
        '/\n\s*[_*]*Bonus/i',
        '/\n\s*[_*]*Texte\s*\+\s*prompt/i',
        '/\n\s*[_*]*Script\s*\(/i',
        '/\n\s*[_*]*Prompt\s*g[ée]n[ée]ration/iu',
        '/\n\s*Dossier complet/i',
        '/\n\s*Sources\s+actu/i',
        '/\n\s*_?Sent using_?/i',
        '/\n\s*Point de vigilance/i',
    ];
    foreach ($stopMarkers as $re) {
        if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $m[0][1]);
        }
    }

    $parts = preg_split('/\n(?=[_*\s]{0,3}\d+\.\s)/', $text);
    $posts = [];

    foreach ($parts as $part) {
        if (!preg_match('/^[_*\s]{0,3}(\d+)\.\s*(.*)$/s', trim($part), $m)) {
            continue;
        }
        $num = (int) $m[1];
        $rest = $m[2];

        $links = extract_canva_links($rest);
        if (empty($links)) {
            continue;
        }
        $link = $links[0];

        $markerPos = mb_strpos($rest, '<' . $link);
        $newlinePos = mb_strpos($rest, "\n");
        $headingEnd = $markerPos !== false ? $markerPos : $newlinePos;

        $heading = $headingEnd !== false ? mb_substr($rest, 0, $headingEnd) : $rest;
        $heading = trim(clean_caption_text($heading), " \t\n\r\0\x0B-—:*_");

        $body = $headingEnd !== false ? mb_substr($rest, $headingEnd) : '';
        // le lien Canva est déjà affiché séparément (bouton de téléchargement) :
        // on l'enlève du texte plutôt que de le convertir en libellé résiduel.
        $body = preg_replace('/<https:\/\/(?:www\.)?canva\.com\/d\/[A-Za-z0-9_-]+(?:\|[^>]*)?>/', '', $body);
        $body = clean_caption_text($body);

        $category = '';
        $title = $heading;
        if (preg_match('/^(.*?)\s*[-—:]\s*(.+)$/u', $heading, $hm)) {
            $category = trim($hm[1]);
            $title = trim($hm[2]);
        }

        $posts[$num] = [
            'title' => $title !== '' ? $title : "Post $num",
            'category' => $category,
            'caption' => $body !== '' ? $body : $title,
            'link' => $link,
        ];
    }

    ksort($posts);
    return array_values($posts);
}

// Extrait le "bonus vidéo" du message (script + prompt de génération), quand
// présent. Tolérant : ce bloc n'a pas toujours le même intitulé d'une
// semaine à l'autre ("Bonus - idée vidéo" / "Texte + prompt pour la future
// vidéo promo"...).
function extract_video_bonus($rawText) {
    $text = slack_unescape($rawText);

    if (!preg_match('/\n\s*[_*]*(?:Bonus|Texte\s*\+\s*prompt)[^\n]*\n(.*)$/is', $text, $m)) {
        return null;
    }
    $section = $m[1];

    $script = '';
    $prompt = '';

    if (preg_match('/(?:Script[^\n]*|Texte)\s*:?\s*\n?(.*?)(?=\n\s*[_*]*Prompt\s*(?:de\s*|g[ée]n[ée]ration)|$)/isu', $section, $sm)) {
        $script = clean_caption_text($sm[1]);
    }
    if (preg_match('/Prompt\s*(?:de\s*)?g[ée]n[ée]ration[^\n:]*:?\s*\n?(.*)$/isu', $section, $pm)) {
        $prompt = clean_caption_text($pm[1]);
        $prompt = preg_split('/\n\s*(Dossier complet|Sources\s+actu|Sent using)/i', $prompt)[0];
        $prompt = trim($prompt);
    }

    if ($script === '' && $prompt === '') {
        return null;
    }
    return ['script' => $script, 'prompt' => $prompt];
}

function fetch_og_image($url) {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CoinCoinReseauxBot/1.0)',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    if (!$html) {
        return null;
    }
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
        return $m[1];
    }
    return null;
}

function save_new_week($dataFile, $parsedPosts, $videoBonus, $sourceMessageTs = null) {
    $posts = [];
    $i = 1;
    foreach ($parsedPosts as $p) {
        $posts[] = [
            'id' => 'W' . date('Ymd') . '-' . $i,
            'title' => $p['title'],
            'category' => $p['category'] ?: 'À catégoriser',
            'captionDraft' => $p['caption'],
            'caption' => $p['caption'],
            'platforms' => ['facebook' => true, 'instagram' => true, 'tiktok' => false],
            'status' => 'pending',
            'note' => '',
            'canvaViewUrl' => $p['link'],
            'canvaEditUrl' => $p['link'],
            'thumbnailUrl' => $p['thumbnailUrl'] ?? '',
            'updatedAt' => null,
        ];
        $i++;
    }

    // Si aucun bonus vidéo n'est trouvé cette semaine, on garde celui déjà
    // enregistré plutôt que de l'effacer.
    $existing = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : null;
    $video = $videoBonus ?: ($existing['video'] ?? ['script' => '', 'prompt' => '']);
    $video['updatedAt'] = gmdate('c');

    $newWeekOf = date('Y-m-d');

    // On archive l'ancienne semaine avant de la remplacer, pour garder un
    // historique consultable (voir archive_week).
    if ($existing && !empty($existing['posts']) && ($existing['weekOf'] ?? null) !== $newWeekOf) {
        archive_week($dataFile, $existing);
    }

    $data = [
        'weekOf' => $newWeekOf,
        'posts' => $posts,
        'video' => $video,
        'sourceMessageTs' => $sourceMessageTs ?? ($existing['sourceMessageTs'] ?? null),
    ];
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Conserve un instantané en lecture seule de la semaine remplacée, et ne
// garde que les 4 plus récentes archives (les plus anciennes sont supprimées).
function archive_week($dataFile, $weekData) {
    $archiveDir = dirname($dataFile) . '/archive';
    if (!is_dir($archiveDir)) {
        mkdir($archiveDir, 0755, true);
    }

    $weekOf = $weekData['weekOf'] ?? date('Y-m-d');
    $archiveFile = $archiveDir . '/' . $weekOf . '.json';
    file_put_contents($archiveFile, json_encode($weekData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $files = glob($archiveDir . '/*.json');
    if ($files) {
        rsort($files); // noms de fichiers = dates AAAA-MM-JJ -> tri décroissant = plus récent d'abord
        foreach (array_slice($files, 4) as $old) {
            unlink($old);
        }
    }
}
