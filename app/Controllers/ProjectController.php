<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Tactile;
use App\Core\Upload;
use App\Models\Abonnement;
use App\Models\Project;

/** Fiche d'un projet, version navigateur (avec limite de temps sans compte) et téléchargement. */
class ProjectController extends Controller
{
    // region Fiche d'un projet

    /** Fiche détaillée d'un projet. */
    public function show(string $id): void
    {
        $project = Project::find($id) ?? $this->notFound();

        $this->render('projects/show', [
            'title'   => $project['name'],
            'project' => $project,
            'niveau'  => Auth::level(),                  // niveau d'abonnement du visiteur
            'niveaux' => Abonnement::libellesNiveaux(),  // 0 => Gratuit, 1 => Essentiel…
        ]);
    }

    // endregion

    // region Version navigateur et essai sans compte

    /** Ouvre la version navigateur du jeu (fichier .html autonome stocké avec les .exe). */
    public function play(string $id): void
    {
        $project = Project::find($id) ?? $this->notFound();
        $this->refuseIfMaintenance($project);
        $verrouille = $project['level'] > Auth::level(); // abonnement insuffisant : seul l'essai sans compte reste possible
        $this->refuseIfNoAccess($project, $this->trialApplies());
        $path = $project['html'] !== '' ? Upload::path($project['html']) : '';
        if ($path === '' || !is_file($path)) {
            $this->notFound();
        }

        // Page du jeu : lue en mémoire pour y ajouter, selon le cas, le compte à rebours d'essai et les commandes tactiles
        $html = (string) file_get_contents($path);
        // Visiteur sans compte : le jeu est servi avec un compte à rebours ; à zéro, l'écran « temps écoulé » s'affiche
        if ($this->trialApplies()) {
            $remaining = $this->trialRemaining();
            if ($remaining <= 0) {
                http_response_code(403);
                $this->render('projects/limite', [
                    'title'   => 'Temps de jeu gratuit écoulé',
                    'project' => $project,
                    'minutes' => (int) round(Config::get('play_limit_seconds', 600) / 60),
                    'formule' => $verrouille ? (Abonnement::libellesNiveaux()[$project['level']] ?? '') : '', // formule nécessaire (jeu verrouillé)
                ]);
                exit;
            }
            // Le chrono démarre maintenant : le premier « battement » ne compte que le temps écoulé depuis ici
            $_SESSION['play_last'] = microtime(true);
            $html = $this->injectTrialTimer($html, $remaining, $project['id'], $verrouille);
        }
        $html = Tactile::inject($html, $project['name']);   // viewport + commandes tactiles (téléphone, tablette)

        $this->startFile('text/html; charset=UTF-8', strlen($html));
        echo $html;
        exit;
    }

    /**
     * « Battement » envoyé toutes les ~10 s par la page de jeu d'un visiteur sans compte :
     * le serveur décompte le temps de jeu utilisé (dans la session) et renvoie le temps restant.
     */
    public function playPulse(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!$this->trialApplies()) {
            echo json_encode(['unlimited' => true, 'remaining' => null]);
            exit;
        }

        $now = microtime(true);
        $last = (float) ($_SESSION['play_last'] ?? 0);
        // temps écoulé depuis le dernier battement (plafonné : on ne compte pas une page restée ouverte en veille)
        $elapsed = ($last > 0 && $now - $last < 40) ? $now - $last : 10.0;
        $_SESSION['play_used'] = (float) ($_SESSION['play_used'] ?? 0) + min($elapsed, 30.0);
        $_SESSION['play_last'] = $now;

        $remaining = $this->trialRemaining();
        session_write_close();
        echo json_encode(['unlimited' => false, 'remaining' => $remaining]);
        exit;
    }

    /** La limite ne concerne que les visiteurs sans compte, et seulement si elle est activée. */
    private function trialApplies(): bool
    {
        return !Auth::loggedIn() && (int) Config::get('play_limit_seconds', 600) > 0;
    }

    /** Secondes de jeu gratuit restantes pour cette session de visiteur. */
    private function trialRemaining(): int
    {
        $limit = (int) Config::get('play_limit_seconds', 600);
        return max(0, (int) floor($limit - (float) ($_SESSION['play_used'] ?? 0)));
    }

    /** Ajoute au jeu un compte à rebours discret ; à zéro, la page du jeu est remplacée par l'écran « temps écoulé ». */
    private function injectTrialTimer(string $html, int $remaining, string $id, bool $verrouille = false): string
    {
        // jeu réservé à un abonnement : à la fin de l'essai, l'invitation mène aux abonnements plutôt qu'à l'inscription
        $libelle = $verrouille ? 'Voir les abonnements' : 'Créer un compte';
        $suite = $verrouille ? 'pour continuer à jouer' : 'pour jouer sans limite';

        $config = json_encode([
            'remaining' => $remaining,
            'pulse'     => url('/jeu/chrono'),
            'limit'     => url('/jouer/' . $id),
            'register'  => $verrouille ? url_reduit('abonnement') : url('/connexion#register'),
        ], JSON_UNESCAPED_SLASHES);

        $snippet = <<<HTML
<div id="trial-bar" style="position:fixed;right:10px;bottom:10px;z-index:2147483647;max-width:92vw;padding:8px 12px;font:600 13px/1.3 system-ui,sans-serif;color:#e8ecff;background:rgba(7,8,15,.88);border:1px solid #00e5ff;box-shadow:0 0 14px rgba(0,229,255,.35)">
  ⏱ Essai sans compte : <b id="trial-time">--:--</b> restantes ·
  <a href="" id="trial-link" target="_top" style="color:#00e5ff">{$libelle}</a> {$suite}
</div>
<script>
(function () {
    var cfg = {$config};
    var rem = cfg.remaining, time = document.getElementById('trial-time');
    document.getElementById('trial-link').href = cfg.register;
    function fmt(s) { s = Math.max(0, s); var m = Math.floor(s / 60), r = s % 60; return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r; }
    function stop() { location.replace(cfg.limit); }
    function draw() { time.textContent = fmt(rem); }
    draw();
    setInterval(function () { rem--; draw(); if (rem <= 0) { stop(); } }, 1000);
    function pulse() {
        fetch(cfg.pulse, { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.unlimited) { document.getElementById('trial-bar').remove(); return; }
                rem = Math.min(rem, d.remaining);
                if (rem <= 0) { stop(); }
            })
            .catch(function () { /* réseau coupé : le compte à rebours local continue */ });
    }
    setInterval(pulse, 10000);
})();
</script>
HTML;

        $pos = strripos($html, '</body>');
        return $pos === false ? $html . $snippet : substr($html, 0, $pos) . $snippet . substr($html, $pos);
    }

    // endregion

    // region Téléchargement

    /** Télécharge le fichier .exe / .zip d'un projet et compte le téléchargement (refusé pendant une maintenance). */
    public function download(string $id): void
    {
        $project = Project::find($id) ?? $this->notFound();
        $this->refuseIfMaintenance($project);
        $this->refuseIfNoAccess($project); // le téléchargement n'est jamais couvert par l'essai sans compte
        $path = Upload::path($project['filename']);
        if (!is_file($path)) {
            $this->notFound();
        }

        Project::incrementDownloads($id);

        // nom proposé au navigateur : version ASCII sûre + version UTF-8 (RFC 5987) pour les accents
        $name = Upload::sanitizeName($project['original_name']);
        $asciiName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $name);

        $this->startFile(
            'application/octet-stream',
            (int) filesize($path),
            'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($name)
        );
        readfile($path);
        exit;
    }

    // endregion

    // region Maintenance

    /** Jeu en maintenance : ni jeu en ligne ni téléchargement, retour à sa fiche avec un message. */
    private function refuseIfMaintenance(array $project): void
    {
        if (!empty($project['maintenance'])) {
            flash('error', '« ' . $project['name'] . ' » est en maintenance (mise à jour en cours) : réessayez bientôt.');
            $this->redirect('/projet/' . $project['id']);
        }
    }

    // endregion

    // region Accès selon l'abonnement

    /**
     * Jeu réservé à un abonnement : si le niveau du visiteur est insuffisant, retour à la page des abonnements
     * avec un message qui nomme la formule nécessaire (jamais de fichier envoyé).
     * Exception : un visiteur sans compte peut ouvrir la version navigateur de n'importe quel jeu pendant son essai.
     *
     * @param bool $essaiPossible Vrai si le visiteur profite de l'essai gratuit sans compte (version navigateur seulement).
     */
    private function refuseIfNoAccess(array $project, bool $essaiPossible = false): void
    {
        if ($project['level'] > Auth::level() && !$essaiPossible) {
            $formule = Abonnement::libellesNiveaux()[$project['level']] ?? 'supérieure';
            flash('error', '« ' . $project['name'] . ' » est réservé à la formule ' . $formule . ' ou supérieure.');
            $this->redirect('/abonnements');
        }
    }

    // endregion
}
