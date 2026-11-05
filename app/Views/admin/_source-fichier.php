<?php
/**
 * Élément réutilisable du formulaire d'ajout / modification : les deux façons de fournir un fichier
 * (Option 1 : déposé par FTP dans storage/depot, Option 2 : envoyé depuis l'ordinateur).
 * Inclus deux fois par admin/form.php : une fois pour le .exe / .zip, une fois pour la version navigateur .html.
 *
 * Variables attendues :
 *  $depot       fichiers du dossier de dépôt (nom => taille)        $champDepot   nom du champ <select>
 *  $selection   fichier de dépôt à présélectionner                  $messageDepot texte « aucun fichier dans le dépôt »
 *  $champFichier nom du champ <input type="file">                   $accept       extensions acceptées (attribut accept)
 *  $aideEnvoi   texte d'aide sous le champ d'envoi (HTML autorisé)  $etapesFtp    afficher les 2 étapes FTP (vrai / faux)
 */
?>
<div class="file-sources">
    <div class="file-source">
        <span class="file-source-title">Option 1 — Fichier déposé par FTP dans <code>storage/depot/</code><?= $etapesFtp ? ' (recommandé)' : '' ?></span>
        <?php if ($depot): ?>
            <select name="<?= e($champDepot) ?>">
                <option value="">— Choisir un fichier du dossier de dépôt —</option>
                <?php foreach ($depot as $nom => $taille): ?>
                    <option value="<?= e($nom) ?>" <?= $selection === $nom ? 'selected' : '' ?>><?= e($nom) ?> (<?= format_size($taille) ?>)</option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <small><?= e($messageDepot) ?></small>
        <?php endif; ?>
        <?php if ($etapesFtp): ?>
            <ol>
                <li>Avec FileZilla, envoyez votre .exe ou .zip dans le dossier <code>storage/depot/</code> du site.</li>
                <li>Rechargez cette page (touche F5) : le fichier apparaît dans la liste.</li>
            </ol>
        <?php endif; ?>
    </div>

    <div class="or-sep">— ou —</div>

    <div class="file-source">
        <span class="file-source-title">Option 2 — Envoyer depuis l'ordinateur</span>
        <input type="file" name="<?= e($champFichier) ?>" accept="<?= e($accept) ?>">
        <small><?= $aideEnvoi ?></small>
    </div>
</div>
