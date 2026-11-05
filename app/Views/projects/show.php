<?php
/**
 * Fiche d'un projet : description, boutons Jouer / Télécharger (grisés en maintenance, verrouillés si l'abonnement
 * est insuffisant) et caractéristiques.
 *
 * Variables : $project, $niveau (niveau d'abonnement du visiteur), $niveaux (libellés des niveaux) ;
 * données communes : $site, $session.
 */
$categorie = $site['categories'][$project['category']] ?? $project['category'];
$extension = strtolower(pathinfo($project['original_name'], PATHINFO_EXTENSION));
$formule = $niveaux[$project['level']] ?? ('Niveau ' . $project['level']); // formule nécessaire pour ce jeu
$verrouille = $project['level'] > $niveau;                                    // abonnement insuffisant
?>
<a class="back" href="<?= url('/') ?>">← Tous les projets</a>

<div class="detail">
    <?php // region Description ?>
    <section class="detail-main">
        <div class="detail-head">
            <span class="app-icon app-icon-lg" style="--hue: <?= hue($project['id']) ?>"><?= e(initial($project['name'])) ?></span>
            <div>
                <h1><?= e($project['name']) ?></h1>
                <span class="tag tag-cat"><?= e($categorie) ?></span>
                <?php if ($project['version'] !== ''): ?>
                    <span class="tag">Version <?= e($project['version']) ?></span>
                <?php endif; ?>
                <span class="tag tag-niveau niveau-<?= (int) $project['level'] ?>"><?= $project['level'] > 0 ? '⭐ ' : '' ?><?= e($formule) ?></span>
            </div>
        </div>

        <div class="description">
            <?= $project['description'] !== '' ? nl2br(e($project['description'])) : '<p class="muted">Pas de description.</p>' ?>
        </div>
    </section>
    <?php // endregion ?>

    <aside class="detail-side">
        <?php if ($project['maintenance']): ?>
            <?php // region Jeu en maintenance : tout est grisé ?>
            <p class="alert alert-error">🛠 Mise à jour en cours : ce jeu est temporairement indisponible. Revenez bientôt !</p>
            <?php if ($project['html'] !== ''): ?>
                <span class="btn btn-play btn-block btn-lg is-disabled" aria-disabled="true">▶ Jouer maintenant</span>
            <?php endif; ?>
            <span class="btn btn-primary btn-block btn-lg is-disabled" aria-disabled="true">⬇ Télécharger le .<?= e($extension) ?></span>
            <?php // endregion ?>
        <?php elseif ($verrouille): ?>
            <?php // region Abonnement insuffisant : invitation à s'abonner ?>
            <p class="alert alert-error">🔒 Ce jeu est réservé à la formule <strong><?= e($formule) ?></strong> ou supérieure.</p>
            <?php if ($project['html'] !== '' && $session['essaiMinutes'] > 0): ?>
                <?php /* Sans compte, la version navigateur de n'importe quel jeu reste jouable pendant l'essai */ ?>
                <a class="btn btn-play btn-block btn-lg" href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener">
                    ▶ Essayer <?= (int) $session['essaiMinutes'] ?> minutes
                </a>
                <p class="side-hint">Essai gratuit sans compte, directement dans le navigateur.</p>
            <?php endif; ?>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(url_reduit('abonnement', 'index')) ?>">⭐ Voir les abonnements</a>
            <?php // endregion ?>
        <?php else: ?>
            <?php // region Jouer et télécharger ?>
            <?php if ($project['html'] !== ''): ?>
                <a class="btn btn-play btn-block btn-lg" href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener">
                    ▶ Jouer maintenant
                </a>
                <p class="side-hint">Directement dans le navigateur, rien à installer : le jeu s'ouvre dans un nouvel onglet.
                    <?php if ($session['essaiMinutes'] > 0): ?>
                        <br><strong>Sans compte : <?= (int) $session['essaiMinutes'] ?> minutes de jeu.</strong>
                        <a href="<?= url('/connexion#register') ?>">Créez un compte gratuit</a> pour jouer sans limite.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <a class="btn btn-primary btn-block btn-lg" href="<?= url('/telecharger/' . $project['id']) ?>">
                ⬇ Télécharger le .<?= e($extension) ?>
            </a>
            <?php // endregion ?>
        <?php endif; ?>

        <?php // region Caractéristiques ?>
        <dl class="specs">
            <div><dt>Catégorie</dt><dd><?= e($categorie) ?></dd></div>
            <div><dt>Formule requise</dt><dd><?= e($formule) ?></dd></div>
            <div><dt>Fichier</dt><dd><?= e($project['original_name']) ?></dd></div>
            <div><dt>Taille</dt><dd><?= format_size($project['size']) ?></dd></div>
            <?php if ($project['version'] !== ''): ?>
                <div><dt>Version</dt><dd><?= e($project['version']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Mis à jour</dt><dd><?= format_date($project['updated_at']) ?></dd></div>
            <div><dt>Téléchargements</dt><dd><?= (int) $project['downloads'] ?></dd></div>
            <div><dt>Système</dt><dd>Windows<?= $project['html'] !== '' ? ' · navigateur' : '' ?></dd></div>
        </dl>
        <p class="note">
            Windows SmartScreen peut afficher un avertissement pour les applications non signées :
            cliquez sur « Informations complémentaires » puis « Exécuter quand même ».
        </p>
        <?php // endregion ?>
    </aside>
</div>
