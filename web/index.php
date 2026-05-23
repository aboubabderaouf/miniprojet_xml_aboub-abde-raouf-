 <?php
$xml_path = '../club.xml';
$message  = '';
$msgType  = '';

function trier_scores($a, $b) {
    if ($a['score'] === $b['score']) return 0;
    return ($a['score'] < $b['score']) ? 1 : -1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $eventId    = trim($_POST['event']      ?? '');
    $playerId   = trim($_POST['player']     ?? '');
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
            if ((string)$ev['id'] === $eventId) {
                $targetEvent = $ev;
                break;
            }
        }

        $alreadyIn = false;
        if ($targetEvent) {
            foreach ($targetEvent->participants->participant as $p) {
                if ((string)$p['membreRef'] === $playerId) {
                    $alreadyIn = true;
                    break;
                }
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

$club = simplexml_load_file($xml_path);
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
                        elseif (strpos($catName, 'Web')     !== false) $badgeCls = 'badge-green';
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
                if ((string)$ev['id'] === $selectedEventId) {
                    $selectedEvent = $ev;
                    break;
                }
            }

            if ($selectedEvent):
                $coeff   = (float)$selectedEvent['coefficient'];
                $entries = [];

                foreach ($selectedEvent->participants->participant as $p) {
                    $pRef    = (string)$p['membreRef'];
                    $member  = $club->xpath("//membre[@id='$pRef']")[0];
                    $diff    = (int)$p->complexite;
                    $t       = (int)$p->tempsExecution;
                    $score   = round(($diff + $t) * $coeff, 2);

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
                        $pos      = $i + 1;
                        $isTop    = ($row['score'] == $topScore && $topScore > 0);
                        $pct      = $topScore > 0 ? round(($row['score'] / $topScore) * 100) : 0;
                        $rankCls  = $pos <= 3 ? "rank-{$pos}" : '';
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
            <div class="empty-state">
                <div class="ei">🔍</div>
                <p>Événement introuvable. Veuillez réessayer.</p>
            </div>
            <?php endif; ?>
        <?php elseif (!$selectedEventId): ?>
        <div class="empty-state">
            <div class="ei">📊</div>
            <p>Sélectionnez un événement pour afficher le classement.</p>
        </div>
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

</main>

<footer>TechArena &mdash; Mini Projet XML/XSD/XQuery/PHP &mdash; 2025</footer>

</body>
</html>
