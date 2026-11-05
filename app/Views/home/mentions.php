<?php
/**
 * Mentions légales (LCEN).
 *
 * Variables : données communes $site (éditeur, contact, hébergeur : voir app/Config/config.php).
 */
?>
<article class="legal">
    <h1>Mentions légales</h1>
    <p class="muted">Conformément à l'article 6 de la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique (LCEN).</p>

    <?php // region Éditeur et hébergeur ?>
    <h2>Éditeur du site</h2>
    <p>
        <strong><?= e($site['auteur']) ?></strong>, directeur de la publication.<br>
        <?= e($site['statut']) ?>.<br>
        Contact : <a href="<?= e($site['contact']) ?>" rel="noopener"><?= e(preg_replace('#^https?://#', '', $site['contact'])) ?></a>
    </p>

    <h2>Hébergeur</h2>
    <p>
        <?= e($site['hebergeurNom']) ?> —
        <a href="<?= e($site['hebergeurUrl']) ?>" rel="noopener"><?= e(preg_replace('#^https?://#', '', $site['hebergeurUrl'])) ?></a>
    </p>
    <?php // endregion ?>

    <?php // region Nature du site, propriété intellectuelle et responsabilité ?>
    <h2>Nature du site</h2>
    <p>
        <?= e($site['nom']) ?> présente des jeux et applications réalisés par l'éditeur, à télécharger
        (.exe / .zip) ou à jouer dans le navigateur. Le site est un projet personnel et pédagogique : il ne poursuit
        aucun but commercial et ne contient aucune publicité. Sa boutique est une démonstration : les prix sont fictifs
        et aucun paiement n'est demandé.
    </p>

    <h2>Propriété intellectuelle</h2>
    <p>
        Les jeux, textes, graphismes et le code du site sont la propriété de l'éditeur, sauf mention contraire.
        Toute reproduction ou réutilisation sans autorisation est interdite. Les marques et univers cités
        (par exemple dans des hommages ou remakes de jeux) appartiennent à leurs propriétaires respectifs et
        sont évoqués sans but lucratif.
    </p>

    <h2>Responsabilité</h2>
    <p>
        Les programmes sont fournis « en l'état », sans garantie. Il est conseillé de les télécharger uniquement
        depuis ce site et de les analyser avec son antivirus comme tout fichier exécutable. L'éditeur ne peut être tenu
        responsable des dommages résultant de leur utilisation. Le site peut contenir des liens vers d'autres sites
        dont l'éditeur n'est pas responsable du contenu.
    </p>
    <?php // endregion ?>

    <h2>Données personnelles et cookies</h2>
    <p>
        Le traitement des données personnelles et l'usage des cookies sont décrits dans la
        <a href="<?= url('/confidentialite') ?>">politique de confidentialité (RGPD)</a>.
    </p>

    <p class="muted">Dernière mise à jour : <?= date('d/m/Y') ?></p>
</article>
