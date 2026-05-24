 <?php
$xml_path = '../club.xml';
$message  = '';
$msgType  = '';

function xq_membres($xml): string {
    $out = "<membres>\n";
    foreach ($xml->membres->membre as $m) {
        $catId = (string)$m['categorieRef'];
        $cat   = $xml->xpath("//categorie[@id='$catId']")[0];
        $out  .= "  <membre id=\"{$m['id']}\">\n";
        $out  .= "    <nomComplet>{$m->prenom} {$m->nom}</nomComplet>\n";
        $out  .= "    <email>{$m->email}</email>\n";
        $out  .= "    <categorie>{$cat['libelle']}</categorie>\n";
        $out  .= "  </membre>\n";
    }
    return $out . "</membres>";
}

function xq_concours($xml): string {
    $list = [];
    foreach ($xml->concours->concours as $c) $list[] = $c;
    usort($list, fn($a,$b) => strcmp((string)$a['date'], (string)$b['date']));
    $out = "<listeConcours>\n";
    foreach ($list as $c) {
        $catId = (string)$c['categorieRef'];
        $cat   = $xml->xpath("//categorie[@id='$catId']")[0];
        $out  .= "  <concours id=\"{$c['id']}\">\n";
        $out  .= "    <titre>{$c->titre}</titre>\n";
        $out  .= "    <date>{$c['date']}</date>\n";
        $out  .= "    <coefficient>{$c['coefficient']}</coefficient>\n";
        $out  .= "    <categorie>{$cat['libelle']}</categorie>\n";
        $out  .= "  </concours>\n";
    }
    return $out . "</listeConcours>";
}

function xq_scores($xml): string {
    $out = "<resultats>\n";
    foreach ($xml->concours->concours as $c) {
        $coeff = (float)$c['coefficient'];
        $out  .= "  <concours titre=\"{$c->titre}\">\n";
        foreach ($c->participants->participant as $p) {
            $diff  = (int)$p->complexite;
            $temps = (int)$p->tempsExecution;
            $score = round(($diff + $temps) * $coeff, 2);
            $ref   = (string)$p['membreRef'];
            $m     = $xml->xpath("//membre[@id='$ref']")[0];
            $out  .= "    <participant>\n";
            $out  .= "      <nom>{$m->nom} {$m->prenom}</nom>\n";
            $out  .= "      <complexite>$diff</complexite>\n";
            $out  .= "      <tempsExecution>$temps</tempsExecution>\n";
            $out  .= "      <score>$score</score>\n";
            $out  .= "    </participant>\n";
        }
        $out .= "  </concours>\n";
    }
    return $out . "</resultats>";
}

function xq_vainqueurs($xml): string {
    $out = "<vainqueurs>\n";
    foreach ($xml->concours->concours as $c) {
        $coeff  = (float)$c['coefficient'];
        $scores = [];
        foreach ($c->participants->participant as $p)
            $scores[] = ((int)$p->complexite + (int)$p->tempsExecution) * $coeff;
        $max = !empty($scores) ? max($scores) : 0;
        $out .= "  <concours titre=\"{$c->titre}\" scoreMax=\"$max\">\n";
        foreach ($c->participants->participant as $p) {
            $score = ((int)$p->complexite + (int)$p->tempsExecution) * $coeff;
            if ($score == $max) {
                $ref  = (string)$p['membreRef'];
                $m    = $xml->xpath("//membre[@id='$ref']")[0];
                $out .= "    <gagnant>\n";
                $out .= "      <nom>{$m->nom}</nom>\n";
                $out .= "      <prenom>{$m->prenom}</prenom>\n";
                $out .= "      <score>$score</score>\n";
                $out .= "    </gagnant>\n";
            }
        }
        $out .= "  </concours>\n";
    }
    return $out . "</vainqueurs>";
}

function xq_membres_categorie($xml, string $categorie): string {
    $catNode = $xml->xpath("//categorie[@libelle='$categorie']");
    if (empty($catNode)) return "<error>Catégorie '$categorie' introuvable.</error>";
    $catId   = (string)$catNode[0]['id'];
    $membres = [];
    foreach ($xml->membres->membre as $m)
        if ((string)$m['categorieRef'] === $catId) $membres[] = $m;
    usort($membres, fn($a,$b) => strcmp((string)$a->nom.(string)$a->prenom, (string)$b->nom.(string)$b->prenom));
    $out = "<membres categorie=\"$categorie\">\n";
    foreach ($membres as $m) {
        $out .= "  <membre id=\"{$m['id']}\">\n";
        $out .= "    <nom>{$m->nom}</nom>\n";
        $out .= "    <prenom>{$m->prenom}</prenom>\n";
        $out .= "    <email>{$m->email}</email>\n";
        $out .= "  </membre>\n";
    }
    return $out . "</membres>";
}

function formatXml(string $xml): string {
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput       = true;
    if (@$dom->loadXML($xml)) return $dom->saveXML($dom->documentElement);
    return $xml;
}

function executeXQuery(string $query, string $xmlPath): string {
    $xml = simplexml_load_file($xmlPath);
    if (!$xml) return '<error>Impossible de charger club.xml</error>';
    $q = strtolower(preg_replace('/\s+/', ' ', $query));

    if (strpos($q, 'listeconcours') !== false)                               return xq_concours($xml);
    if (strpos($q, 'resultats') !== false && strpos($q, 'score') !== false)  return xq_scores($xml);
    if (strpos($q, 'vainqueur') !== false || strpos($q, 'gagnant') !== false) return xq_vainqueurs($xml);
    if (strpos($q, 'nomcomplet') !== false)                                  return xq_membres($xml);
    if (strpos($q, '$categorie') !== false) {
        preg_match('/:=\s*["\']([^"\']+)["\']/', $query, $match);
        return xq_membres_categorie($xml, $match[1] ?? 'Intelligence Artificielle');
    }
    return '<error>Requête non reconnue. Utilisez les boutons Q1–Q5 pour charger une requête.</error>';
}

$xq_result = '';
$xq_error  = '';
$xq_query  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'xquery') {
    $xq_query = trim($_POST['xquery'] ?? '');
    if (empty($xq_query)) {
        $xq_error = "Veuillez saisir une requête XQuery.";
    } elseif (!file_exists($xml_path)) {
        $xq_error = "Fichier club.xml introuvable.";
    } else {
        $xq_result = executeXQuery($xq_query, $xml_path);
    }
}

function trier_scores($a, $b) {
    if ($a['score'] === $b['score']) return 0;
    return ($a['score'] < $b['score']) ? 1 : -1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $eventId    = trim($_POST['event']        ?? '');
    $playerId   = trim($_POST['player']       ?? '');
    $difficulty = intval($_POST['difficulty'] ?? 0);
    $execTime   = intval($_POST['exec_time']  ?? 0);

    if (!$eventId || !$playerId) {
        $message = "Veuillez sélectionner un événement et un participant.";
        $msgType = 'error';
    } elseif ($difficulty < 0 || $difficulty > 100) {
        $message = "La difficulté doit être comprise entre 0 et 100.";
        $msgType = 'error';
    } elseif ($execTime <= 0) {
        $message = "Le temps d'exécution doit être supérieur à 0.";
        $msgType = 'error';
    } elseif (!file_exists($xml_path)) {
        $message = "Fichier de données introuvable.";
        $msgType = 'error';
    } else {
        $xml = simplexml_load_file($xml_path);

        $targetEvent = null;
        foreach ($xml->concours->concours as $ev) {
            if ((string)$ev['id'] === $eventId) { $targetEvent = $ev; break; }
        }

        $alreadyIn = false;
        if ($targetEvent) {
            foreach ($targetEvent->participants->participant as $p) {
                if ((string)$p['membreRef'] === $playerId) { $alreadyIn = true; break; }
            }
        }

        if (!$targetEvent) {
            $message = "Événement introuvable.";
            $msgType = 'error';
        } elseif ($alreadyIn) {
            $message = "Ce participant est déjà inscrit à cet événement.";
            $msgType = 'error';
        } else {
            $entry = $targetEvent->participants->addChild('participant');
            $entry->addAttribute('membreRef', $playerId);
            $entry->addChild('complexite',     (string)$difficulty);
            $entry->addChild('tempsExecution', (string)$execTime);

            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = true;
            $dom->save($xml_path);

            header("Location: index.php?view_event={$eventId}&registered=1#leaderboard");
            exit;
        }
    }
}

$club            = simplexml_load_file($xml_path);
$selectedEventId = $_GET['view_event'] ?? '';

if (isset($_GET['registered']) && $_GET['registered'] === '1' && !$message) {
    $message = "Inscription confirmée et enregistrée avec succès !";
    $msgType = 'success';
}

$totalEvents  = count($club->concours->concours);
$totalPlayers = count($club->membres->membre);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechArena — Tableau de Bord</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="header-left">
        <div class="logo-mark">⚡</div>
        <h1>Tech<em>Arena</em></h1>
    </div>
    <span class="header-badge">Saison 2025</span>
</header>

<main>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>">
        <?= $msgType === 'success' ? '✓' : '✕' ?>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-head">
            <div class="card-icon icon-purple">📅</div>
            <h2>Événements Disponibles</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Coefficient</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($club->concours->concours as $ev):
                        $catId   = (string)$ev['categorieRef'];
                        $cat     = $club->xpath("//categorie[@id='$catId']")[0];
                        $catName = (string)$cat['libelle'];
                        $badgeCls = 'badge-blue';
                        if (strpos($catName, 'Sécurité') !== false) $badgeCls = 'badge-red';
                        elseif (strpos($catName, 'Web')   !== false) $badgeCls = 'badge-green';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$ev->titre) ?></td>
                        <td><?= htmlspecialchars((string)$ev['date']) ?></td>
                        <td><span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($catName) ?></span></td>
                        <td>× <?= htmlspecialchars((string)$ev['coefficient']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="leaderboard">
        <div class="card-head">
            <div class="card-icon icon-gold">🏆</div>
            <h2>Classement des Participants</h2>
        </div>

        <div class="select-row">
            <form method="GET" action="index.php">
                <select name="view_event" id="selectEvent">
                    <option value="">Choisir un événement…</option>
                    <?php foreach ($club->concours->concours as $ev): ?>
                    <option value="<?= htmlspecialchars((string)$ev['id']) ?>"
                        <?= ($selectedEventId === (string)$ev['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$ev->titre) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Afficher</button>
            </form>
        </div>

        <?php if ($selectedEventId):
            $selectedEvent = null;
            foreach ($club->concours->concours as $ev) {
                if ((string)$ev['id'] === $selectedEventId) { $selectedEvent = $ev; break; }
            }
            if ($selectedEvent):
                $coeff   = (float)$selectedEvent['coefficient'];
                $entries = [];
                foreach ($selectedEvent->participants->participant as $p) {
                    $pRef   = (string)$p['membreRef'];
                    $member = $club->xpath("//membre[@id='$pRef']")[0];
                    $diff   = (int)$p->complexite;
                    $t      = (int)$p->tempsExecution;
                    $score  = round(($diff + $t) * $coeff, 2);
                    $entries[] = [
                        'name'  => (string)$member->prenom . ' ' . (string)$member->nom,
                        'diff'  => $diff,
                        'time'  => $t,
                        'score' => $score,
                    ];
                }
                usort($entries, 'trier_scores');
                $topScore = !empty($entries) ? $entries[0]['score'] : 0;
        ?>
        <div class="table-wrap">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Participant</th>
                        <th>Difficulté</th>
                        <th>Temps (ms)</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $i => $row):
                        $pos     = $i + 1;
                        $isTop   = ($row['score'] == $topScore && $topScore > 0);
                        $pct     = $topScore > 0 ? round(($row['score'] / $topScore) * 100) : 0;
                        $rankCls = $pos <= 3 ? "rank-{$pos}" : '';
                    ?>
                    <tr class="<?= $isTop ? 'winner' : '' ?>">
                        <td><span class="rank <?= $rankCls ?>"><?= $pos ?></span></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['diff'] ?></td>
                        <td><?= $row['time'] ?></td>
                        <td>
                            <div class="score-wrap">
                                <div class="score-track"><div class="score-fill" style="width:<?= $pct ?>%"></div></div>
                                <span class="score-num"><?= number_format($row['score'], 2) ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            <?php else: ?>
            <div class="empty-state"><div class="ei">🔍</div><p>Événement introuvable. Veuillez réessayer.</p></div>
            <?php endif; ?>
        <?php elseif (!$selectedEventId): ?>
        <div class="empty-state"><div class="ei">📊</div><p>Sélectionnez un événement pour afficher le classement.</p></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <div class="card-icon icon-green">✏️</div>
            <h2>Nouvelle Inscription</h2>
        </div>

        <form method="POST" action="index.php" class="inscription-form">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label for="event">Événement</label>
                <select name="event" id="event" required>
                    <option value="">Sélectionnez un événement…</option>
                    <?php foreach ($club->concours->concours as $ev): ?>
                    <option value="<?= htmlspecialchars((string)$ev['id']) ?>">
                        <?= htmlspecialchars((string)$ev->titre) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="player">Participant</label>
                <select name="player" id="player" required>
                    <option value="">Sélectionnez un participant…</option>
                    <?php foreach ($club->membres->membre as $m): ?>
                    <option value="<?= htmlspecialchars((string)$m['id']) ?>">
                        <?= htmlspecialchars((string)$m->prenom . ' ' . (string)$m->nom) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="difficulty">Difficulté algorithmique (0–100)</label>
                    <input type="number" name="difficulty" id="difficulty"
                           min="0" max="100" placeholder="ex : 75" required>
                </div>
                <div class="form-group">
                    <label for="exec_time">Temps d'exécution (ms)</label>
                    <input type="number" name="exec_time" id="exec_time"
                           min="1" placeholder="ex : 120" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Confirmer l'inscription →</button>
        </form>
    </section>

    <section class="card" id="xquery-console">
        <div class="card-head">
            <div class="card-icon icon-purple">🔍</div>
            <h2>XQuery Console</h2>
        </div>

        <div class="xq-presets">
            <span class="xq-label">Requêtes rapides :</span>
            <button type="button" class="xq-preset-btn" onclick="loadQuery('q1')">Q1 — Membres</button>
            <button type="button" class="xq-preset-btn" onclick="loadQuery('q2')">Q2 — Concours</button>
            <button type="button" class="xq-preset-btn" onclick="loadQuery('q3')">Q3 — Scores</button>
            <button type="button" class="xq-preset-btn" onclick="loadQuery('q4')">Q4 — Vainqueurs</button>
            <button type="button" class="xq-preset-btn" onclick="loadQuery('q5')">Q5 — Catégorie</button>
        </div>

        <form method="POST" action="index.php#xquery-console" class="xq-form" id="xqForm">
            <input type="hidden" name="action" value="xquery">
            <input type="hidden" name="xquery" id="xqueryHidden" value="<?= htmlspecialchars($xq_query) ?>">
            <div class="xq-editor-wrap">
                <div class="xq-editor-bar">
                    <span class="xq-lang-tag">XQuery 3.1</span>
                    <span class="xq-file-tag">club.xml</span>
                </div>
                <div id="xqueryInput" class="xq-editor" contenteditable="true" spellcheck="false"
                     data-placeholder="Collez ici une requête de requetes.xq ou cliquez sur Q1–Q5…"><?= htmlspecialchars($xq_query) ?></div>
            </div>
            <div class="xq-actions">
                <button type="submit" class="btn-run">▶ Exécuter</button>
                <button type="button" class="btn-clear" onclick="clearEditor()">✕ Effacer</button>
            </div>
        </form>

        <?php if ($xq_error): ?>
        <div class="alert alert-error" style="margin-top:16px">✕ <?= htmlspecialchars($xq_error) ?></div>
        <?php endif; ?>

        <?php if ($xq_result): ?>
        <div class="xq-result-wrap">
            <div class="xq-result-head">
                <span class="xq-result-label">📄 Résultat XML</span>
                <button type="button" class="btn-copy" onclick="copyResult()">⎘ Copier</button>
            </div>
            <div id="xqResult" class="xq-result" data-raw="<?= htmlspecialchars(formatXml($xq_result)) ?>"></div>
        </div>
        <?php endif; ?>
    </section>

</main>

<footer>TechArena &mdash; Mini Projet XML/XSD/XQuery/PHP &mdash; 2025</footer>

<script>
const queries = {
    q1: `<membres>
  {
    for $m in doc("club.xml")//membre
      let $cat := doc("club.xml")//categorie[@id = $m/@categorieRef]
    return
      <membre id="{string($m/@id)}">
        <nomComplet>{ string($m/prenom) || " " || string($m/nom) }</nomComplet>
        <email>{ string($m/email) }</email>
        <categorie>{ string($cat/@libelle) }</categorie>
      </membre>
  }
</membres>`,
    q2: `<listeConcours>
  {
    for $c in doc("club.xml")//concours[@id]
      let $cat := doc("club.xml")//categorie[@id = $c/@categorieRef]
    order by xs:date($c/@date) ascending
    return
      <concours id="{string($c/@id)}">
        <titre>{ string($c/titre) }</titre>
        <date>{ string($c/@date) }</date>
        <coefficient>{ string($c/@coefficient) }</coefficient>
        <categorie>{ string($cat/@libelle) }</categorie>
      </concours>
  }
</listeConcours>`,
    q3: `<resultats>
  {
    for $c in doc("club.xml")//concours[@id]
      let $coeff := xs:decimal($c/@coefficient)
    return
      <concours titre="{string($c/titre)}">
        {
          for $p in $c/participants/participant
            let $score := round((xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff * 100) div 100
            let $m := doc("club.xml")//membre[@id = $p/@membreRef]
          return
            <participant>
              <nom>{ string($m/nom) || " " || string($m/prenom) }</nom>
              <complexite>{ xs:integer($p/complexite) }</complexite>
              <tempsExecution>{ xs:integer($p/tempsExecution) }</tempsExecution>
              <score>{ $score }</score>
            </participant>
        }
      </concours>
  }
</resultats>`,
    q4: `<vainqueurs>
  {
    for $c in doc("club.xml")//concours[@id]
      let $coeff := xs:decimal($c/@coefficient)
      let $scores := for $p in $c/participants/participant
                     return (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff
      let $maxScore := max($scores)
    return
      <concours titre="{string($c/titre)}" scoreMax="{$maxScore}">
        {
          for $p in $c/participants/participant
            let $score := (xs:integer($p/complexite) + xs:integer($p/tempsExecution)) * $coeff
            where $score = $maxScore
            let $m := doc("club.xml")//membre[@id = $p/@membreRef]
          return
            <gagnant>
              <nom>{ string($m/nom) }</nom>
              <prenom>{ string($m/prenom) }</prenom>
              <score>{ $score }</score>
            </gagnant>
        }
      </concours>
  }
</vainqueurs>`,
    q5: `let $categorie := "Intelligence Artificielle"
let $catId := doc("club.xml")//categorie[@libelle = $categorie]/@id
return
  <membres categorie="{$categorie}">
    {
      for $m in doc("club.xml")//membre[@categorieRef = $catId]
      order by string($m/nom) ascending, string($m/prenom) ascending
      return
        <membre id="{string($m/@id)}">
          <nom>{ string($m/nom) }</nom>
          <prenom>{ string($m/prenom) }</prenom>
          <email>{ string($m/email) }</email>
        </membre>
    }
  </membres>`
};

function highlightXml(raw) {
    return raw
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/(&lt;\/?)([\w:.-]+)/g,'<span class="xh-tag">$1<span class="xh-name">$2</span></span>')
        .replace(/([\w:.-]+)=(&quot;[^&]*&quot;)/g,'<span class="xh-attr">$1</span>=<span class="xh-val">$2</span>');
}

function loadQuery(key) {
    const el = document.getElementById('xqueryInput');
    el.innerText = queries[key];
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function clearEditor() {
    document.getElementById('xqueryInput').innerText = '';
    document.getElementById('xqueryHidden').value = '';
}

document.getElementById('xqForm').addEventListener('submit', function() {
    const val = document.getElementById('xqueryInput').innerText;
    document.getElementById('xqueryHidden').value = val;
});

function copyResult() {
    const el = document.getElementById('xqResult');
    if (!el) return;
    navigator.clipboard.writeText(el.dataset.raw || el.innerText).then(() => {
        const btn = document.querySelector('.btn-copy');
        btn.textContent = '✓ Copié !';
        setTimeout(() => btn.textContent = '⎘ Copier', 2000);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const res = document.getElementById('xqResult');
    if (res && res.dataset.raw) res.innerHTML = highlightXml(res.dataset.raw);
});
</script>

</body>
</html>
