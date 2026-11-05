<?php
/**
 * Accueil : présentation, statistiques, filtres, recherche et cartes des projets.
 * Le filtrage se fait en JavaScript, sans rechargement de la page (assets/js/accueil.js).
 *
 * Variables : $projects (liste des projets), $niveau (niveau d'abonnement du visiteur), $niveaux (libellés des niveaux) ;
 * données communes : $site, $session.
 */
$cinema = true; // la mise en page peut charger la scène cinématique (voir layout.php)

$categories = $site['categories'];

// Nombre de projets par catégorie, pour les statistiques et les boutons de filtre
$counts = [];
foreach ($projects as $project) {
    $counts[$project['category']] = ($counts[$project['category']] ?? 0) + 1;
}
?>

<?php // region Présentation, statistiques, filtres et recherche ?>
<section class="hero">
    <span class="eyebrow">Arsenal // Téléchargements</span>
    <h1><?= e($site['nom']) ?></h1>
    <p><?= e($site['slogan']) ?></p>
    <?php if ($projects): ?>
        <div class="stats">
            <?php foreach ($categories as $key => $label): ?>
                <div class="stat"><strong><?= (int) ($counts[$key] ?? 0) ?></strong><span><?= e($label) ?></span></div>
            <?php endforeach; ?>
            <div class="stat"><strong><?= array_sum(array_column($projects, 'downloads')) ?></strong><span>Téléchargements</span></div>
        </div>

        <div class="chips" id="chips">
            <button type="button" class="chip is-active" data-filter="all">Tout<span><?= count($projects) ?></span></button>
            <?php foreach ($categories as $key => $label): ?>
                <button type="button" class="chip" data-filter="<?= e($key) ?>">
                    <?= e($label) ?><span><?= (int) ($counts[$key] ?? 0) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="search">
            <input type="search" id="search" placeholder="Rechercher…" aria-label="Rechercher un projet" list="noms-projets" autocomplete="off">
            <?php /* Autocomplétion : le navigateur propose les noms des jeux pendant la frappe */ ?>
            <datalist id="noms-projets">
                <?php foreach ($projects as $project): ?>
                    <option value="<?= e($project['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <span class="count" id="count"><?= count($projects) ?> résultat<?= count($projects) > 1 ? 's' : '' ?></span>
        </div>
    <?php endif; ?>
</section>
<?php // endregion ?>

<?php if (!$projects): ?>
    <div class="empty">
        <p class="empty-title">Aucun projet pour le moment</p>
        <p>Connectez-vous à l'<a href="<?= url('/connexion') ?>">administration</a> pour ajouter votre premier .exe.</p>
    </div>
<?php else: ?>
    <?php // region Cartes des projets ?>
    <div class="grid" id="projects">
        <?php foreach ($projects as $project): ?>
            <?php
            $extension = strtolower(pathinfo($project['original_name'], PATHINFO_EXTENSION));
            $verrouille = $project['level'] > $niveau; // niveau d'abonnement insuffisant : jeu verrouillé
            ?>
            <article class="card"
                     data-category="<?= e($project['category']) ?>"
                     data-search="<?= e($project['name'] . ' ' . $project['description']) ?>">
                <a class="card-link" href="<?= url('/projet/' . $project['id']) ?>">
                    <div class="card-head">
                        <span class="app-icon" style="--hue: <?= hue($project['id']) ?>"><?= e(initial($project['name'])) ?></span>
                        <div>
                            <h2><?= e($project['name']) ?></h2>
                            <span class="tag tag-cat"><?= e($categories[$project['category']] ?? $project['category']) ?></span>
                            <?php if ($project['version'] !== ''): ?>
                                <span class="tag">v<?= e($project['version']) ?></span>
                            <?php endif; ?>
                            <span class="tag tag-niveau niveau-<?= (int) $project['level'] ?>">
                                <?= $project['level'] > 0 ? '⭐ ' : '' ?><?= e($niveaux[$project['level']] ?? 'Niveau ' . $project['level']) ?>
                            </span>
                            <?php if ($project['maintenance']): ?>
                                <span class="tag tag-maint">🛠 Maintenance</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="card-desc"><?= e(excerpt($project['description']) ?: 'Pas de description.') ?></p>
                </a>
                <div class="card-foot">
                    <span class="meta"><?= format_size($project['size']) ?> · <?= (int) $project['downloads'] ?> téléch.</span>
                    <div class="card-actions">
                        <?php if ($project['maintenance']): ?>
                            <?php /* En maintenance : boutons grisés, rien n'est cliquable */ ?>
                            <?php if ($project['html'] !== ''): ?>
                                <span class="btn btn-play btn-sm is-disabled" aria-disabled="true" title="Indisponible : mise à jour en cours">▶ Jouer</span>
                            <?php endif; ?>
                            <span class="btn btn-primary btn-sm is-disabled" aria-disabled="true" title="Indisponible : mise à jour en cours">⬇ .<?= e($extension) ?></span>
                        <?php elseif ($verrouille): ?>
                            <?php /* Abonnement insuffisant : essai de la version navigateur sans compte, et un bouton vers les formules */ ?>
                            <?php if ($project['html'] !== '' && $session['essaiMinutes'] > 0): ?>
                                <a class="btn btn-play btn-sm" href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener"
                                   title="Essai gratuit sans compte : <?= (int) $session['essaiMinutes'] ?> min">▶ Essai <?= (int) $session['essaiMinutes'] ?> min</a>
                            <?php endif; ?>
                            <a class="btn btn-sm" href="<?= e(url_reduit('abonnement', 'index')) ?>"
                               title="Ce jeu demande la formule <?= e($niveaux[$project['level']] ?? '') ?>">🔒 Formule <?= e($niveaux[$project['level']] ?? '') ?></a>
                        <?php else: ?>
                            <?php if ($project['html'] !== ''): ?>
                                <a class="btn btn-play btn-sm" href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener"
                                   title="Ouvrir le jeu dans le navigateur<?= $session['essaiMinutes'] > 0 ? ' (' . (int) $session['essaiMinutes'] . ' min sans compte)' : '' ?>">▶ Jouer</a>
                            <?php endif; ?>
                            <a class="btn btn-primary btn-sm" href="<?= url('/telecharger/' . $project['id']) ?>"
                               title="Télécharger <?= e($project['original_name']) ?>">⬇ .<?= e($extension) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="empty" id="no-result" hidden>Rien ne correspond à votre recherche.</p>
    <?php // endregion ?>

    <?php /* Filtrage et recherche instantanés : voir assets/js/accueil.js */ ?>
    <script src="<?= url('/assets/js/accueil.js') ?>?v=<?= (int) @filemtime(ROOT . '/assets/js/accueil.js') ?>"></script>
<?php endif; ?>
