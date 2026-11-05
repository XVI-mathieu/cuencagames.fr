<?php
/**
 * Tableau de bord : encart de l'application Windows et liste des projets.
 *
 * Variables : $projects, $peut (droits du compte : ajouter, modifier, supprimer, application),
 * $installateur (present, taille, date), $adresseSite, $niveaux (libellés des formules) ; données communes : $site.
 */
$categories = $site['categories'];
?>
<?php // region En-tête et bouton d'ajout ?>
<div class="page-head">
    <h1>Tableau de bord</h1>
    <div class="actions">
        <?php if ($peut['ajouter']): ?>
            <a class="btn btn-primary" href="<?= url('/admin/ajouter') ?>">+ Ajouter un projet</a>
        <?php endif; ?>
    </div>
</div>
<?php // endregion ?>

<?php // region Application Windows de gestion ?>
<?php if ($peut['application']): ?>
    <section class="app-card">
        <img src="<?= url('/assets/images/logo.png') ?>" alt="" width="72" height="72">
        <div class="app-card-body">
            <h2>Application de gestion Windows</h2>
            <p class="muted">
                Gérez les jeux (.exe / .html) et les comptes utilisateurs depuis votre ordinateur, avec les droits de votre compte.
                <?php if ($installateur['present']): ?>
                    Installateur : <?= format_size($installateur['taille']) ?>, mis à jour le <?= e(date('d/m/Y', $installateur['date'])) ?>.
                <?php endif; ?>
            </p>
            <ol class="app-steps">
                <li>Cliquez sur <strong>Installer l'application</strong> : le fichier <code>Setup_GestionnaireJeux.exe</code> se télécharge.</li>
                <li>Ouvrez-le. Si Windows affiche « Windows a protégé votre ordinateur », cliquez sur
                    <strong>Informations complémentaires</strong> puis <strong>Exécuter quand même</strong>
                    (l'installateur n'est pas encore signé numériquement).</li>
                <li>Choisissez <em>Application seule</em> (le site est celui-ci), puis connectez-vous à
                    <code><?= e($adresseSite) ?></code> avec votre compte.</li>
            </ol>
            <?php if ($installateur['present']): ?>
                <a class="btn btn-primary" href="<?= url('/admin/application') ?>">⬇ Installer l'application</a>
            <?php else: ?>
                <p class="alert alert-error">L'installateur n'est pas encore sur le serveur (dossier <code>storage/outils</code>).</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php // endregion ?>

<?php if (!$projects): ?>
    <div class="empty">
        <p class="empty-title">Aucun projet</p>
        <p>Cliquez sur « Ajouter un projet » pour mettre en ligne votre premier .exe.</p>
    </div>
<?php else: ?>
    <?php // region Tableau des projets ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Projet</th>
                <th>Catégorie</th>
                <th>Version</th>
                <th>Formule</th>
                <th>Taille</th>
                <th>Navigateur</th>
                <th class="num">Téléch.</th>
                <th>Mis à jour</th>
                <th class="actions">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <div class="cell-project">
                            <span class="app-icon app-icon-sm" style="--hue: <?= hue($project['id']) ?>"><?= e(initial($project['name'])) ?></span>
                            <a href="<?= url('/projet/' . $project['id']) ?>"><?= e($project['name']) ?></a>
                            <?php if ($project['maintenance']): ?><span class="tag tag-maint">🛠 Maintenance</span><?php endif; ?>
                        </div>
                    </td>
                    <td><span class="tag tag-cat"><?= e($categories[$project['category']] ?? $project['category']) ?></span></td>
                    <td><?= e($project['version'] ?: '—') ?></td>
                    <td><span class="tag tag-niveau niveau-<?= (int) $project['level'] ?>"><?= e($niveaux[$project['level']] ?? $project['level']) ?></span></td>
                    <td><?= format_size($project['size']) ?></td>
                    <td><?php if ($project['html'] !== ''): ?><a href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener">▶ oui</a><?php else: ?>—<?php endif; ?></td>
                    <td class="num"><?= (int) $project['downloads'] ?></td>
                    <td><?= format_date($project['updated_at']) ?></td>
                    <td class="actions">
                        <?php if ($peut['modifier']): ?>
                            <a class="btn btn-sm" href="<?= url('/admin/modifier/' . $project['id']) ?>">Modifier</a>
                        <?php endif; ?>
                        <?php if ($peut['supprimer']): ?>
                        <form method="post" action="<?= url('/admin/supprimer/' . $project['id']) ?>" class="inline"
                              onsubmit="return confirm('Supprimer définitivement « <?= e(addslashes($project['name'])) ?> » ?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                        </form>
                        <?php endif; ?>
                        <?php if (!$peut['modifier'] && !$peut['supprimer']): ?><span class="muted">lecture seule</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php // endregion ?>
<?php endif; ?>
