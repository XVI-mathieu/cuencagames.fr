<?php
/**
 * Écran affiché quand un visiteur sans compte a épuisé son temps de jeu gratuit.
 *
 * Variables : $project, $minutes (durée de l'essai), $formule (formule nécessaire si le jeu est réservé à un abonnement, sinon vide).
 */
?>
<div class="auth">
    <div class="panel">
        <h1>⏱ Temps de jeu gratuit écoulé</h1>
        <?php if ($formule !== ''): ?>
            <?php // region Jeu réservé à un abonnement : l'essai est fini, il faut s'abonner ?>
            <p class="muted">
                Sans compte, vous pouviez jouer <strong><?= (int) $minutes ?> minutes</strong> à « <?= e($project['name']) ?> ».
                Ce jeu est réservé à la formule <strong><?= e($formule) ?></strong> ou supérieure.
            </p>
            <p><a class="btn btn-primary btn-block" href="<?= e(url_reduit('abonnement')) ?>">⭐ Voir les abonnements</a></p>
            <?php // endregion ?>
        <?php else: ?>
            <?php // region Jeu gratuit : un compte gratuit suffit pour jouer sans limite ?>
            <p class="muted">
                Sans compte, la version navigateur de « <?= e($project['name']) ?> » est jouable
                <strong><?= (int) $minutes ?> minutes</strong>. Créez un compte gratuit pour jouer sans limite,
                ou téléchargez le jeu sur votre ordinateur.
            </p>
            <p><a class="btn btn-primary btn-block" href="<?= url('/connexion#register') ?>">Créer un compte gratuit</a></p>
            <?php // endregion ?>
        <?php endif; ?>
        <p><a class="btn btn-block" href="<?= url('/connexion') ?>">J'ai déjà un compte : me connecter</a></p>
        <p><a class="btn btn-block" href="<?= url('/projet/' . $project['id']) ?>">Retour à la fiche du jeu</a></p>
    </div>
</div>
