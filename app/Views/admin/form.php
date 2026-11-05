<?php
/**
 * Formulaire d'ajout / modification d'un projet.
 *
 * Variables : $project (null pour un ajout), $old (valeurs saisies), $errors, $depotFiles et $depotHtml (fichiers du
 * dossier de dépôt FTP), $selectedDepot et $selectedDepotHtml (présélection), $phpUploadMax, $niveaux (libellés des formules) ; données communes : $site.
 */
$isEdit = $project !== null;
$action = $isEdit ? '/admin/modifier/' . $project['id'] : '/admin/ajouter';
?>
<?php // region Styles propres à ce formulaire ?>
<style>
    .file-sources { display: grid; gap: 14px; }
    .file-source { padding: 16px; border: 1px dashed var(--border); background: rgba(7, 8, 15, .35); display: flex; flex-direction: column; gap: 10px; }
    .file-source-title { color: var(--text); font-weight: 700; }
    .form select {
        width: 100%; padding: 12px 14px; background: rgba(7, 8, 15, .6); color: var(--text);
        border: 1px solid var(--border); border-radius: 0; font: inherit; font-size: 1.02rem;
    }
    .form select:focus { outline: none; border-color: var(--primary); }
    .file-source code { color: var(--lime); font-size: .92em; }
    .file-source ol { margin: 0; padding-left: 20px; color: var(--muted); font-size: .95rem; }
    .check { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .95rem; }
    .check input { width: auto; }
    .or-sep { text-align: center; color: var(--muted); font-family: var(--font-display); font-size: .65rem; letter-spacing: .2em; }
</style>
<?php // endregion ?>

<a class="back" href="<?= url('/admin') ?>">← Tableau de bord</a>

<div class="panel">
    <h1><?= e($title) ?></h1>

    <?php include __DIR__ . '/../partials/erreurs.php'; ?>

    <form method="post" action="<?= url($action) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>

        <?php // region Informations du projet ?>
        <div class="row">
            <label class="field grow">
                <span>Nom du projet *</span>
                <input type="text" name="name" required maxlength="100" value="<?= e($old['name'] ?? '') ?>">
            </label>
            <label class="field">
                <span>Catégorie *</span>
                <select name="category" required>
                    <?php foreach ($site['categories'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($old['category'] ?? 'jeux') === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Version</span>
                <input type="text" name="version" maxlength="30" placeholder="1.0.0" value="<?= e($old['version'] ?? '') ?>">
            </label>
            <label class="field">
                <span>Formule requise</span>
                <select name="level">
                    <?php foreach ($niveaux as $niveau => $libelle): ?>
                        <option value="<?= (int) $niveau ?>" <?= (int) ($old['level'] ?? 0) === $niveau ? 'selected' : '' ?>>
                            <?= $niveau > 0 ? '⭐ ' : '' ?><?= e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label class="field">
            <span>Description</span>
            <textarea name="description" rows="7" maxlength="5000"
                      placeholder="À quoi sert l'application, comment l'utiliser…"><?= e($old['description'] ?? '') ?></textarea>
        </label>
        <?php // endregion ?>

        <?php // region Fichier .exe ou .zip ?>
        <div class="field">
            <span>Fichier .exe ou .zip <?= $isEdit ? '(laisser vide pour garder l\'actuel)' : '*' ?></span>
            <?php if ($isEdit): ?>
                <small>Actuel : <strong><?= e($project['original_name']) ?></strong> (<?= format_size($project['size']) ?>)</small>
            <?php endif; ?>

            <?php
            $depot = $depotFiles;
            $champDepot = 'depot_file';
            $selection = $selectedDepot;
            $messageDepot = 'Aucun fichier dans le dossier de dépôt pour l\'instant.';
            $champFichier = 'file';
            $accept = '.exe,.zip';
            $aideEnvoi = 'Maximum ' . (int) $site['uploadMaxMo'] . ' Mo (limite PHP actuelle : ' . e($phpUploadMax) . ').'
                . ' Si vous obtenez une erreur 403, utilisez l\'option 1.';
            $etapesFtp = true;
            include __DIR__ . '/_source-fichier.php';
            ?>
        </div>
        <?php // endregion ?>

        <?php // region Maintenance ?>
        <div class="field">
            <label class="check"><input type="checkbox" name="maintenance" value="1" <?= !empty($old['maintenance']) ? 'checked' : '' ?>>
                🛠 Mettre en maintenance (le jeu reste visible, mais « Jouer » et le téléchargement sont grisés)</label>
        </div>
        <?php // endregion ?>

        <?php // region Version navigateur .html (facultative) ?>
        <div class="field">
            <span>Version navigateur .html (facultatif) — ajoute le bouton « ▶ Jouer » sur le site</span>
            <?php if ($isEdit && $project['html'] !== ''): ?>
                <small>Actuelle : <a href="<?= url('/jouer/' . $project['id']) ?>" target="_blank" rel="noopener">ouvrir</a> (<?= format_size($project['html_size']) ?>)</small>
                <label class="check"><input type="checkbox" name="remove_html" value="1"> Retirer la version navigateur</label>
            <?php endif; ?>

            <?php
            $depot = $depotHtml;
            $champDepot = 'depot_html';
            $selection = $selectedDepotHtml;
            $messageDepot = 'Aucun fichier .html dans le dossier de dépôt pour l\'instant.';
            $champFichier = 'html_file';
            $accept = '.html,.htm';
            $aideEnvoi = 'Le jeu doit tenir dans un seul fichier .html (c\'est le cas des « &lt;Jeu&gt;.html » du dossier jeux).';
            $etapesFtp = false;
            include __DIR__ . '/_source-fichier.php';
            ?>
        </div>
        <?php // endregion ?>

        <div class="form-actions">
            <a class="btn" href="<?= url('/admin') ?>">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Enregistrer' : 'Mettre en ligne' ?></button>
        </div>
    </form>
</div>
