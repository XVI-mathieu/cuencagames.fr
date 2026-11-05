<?php
/**
 * Page « Mon compte » : informations, droits, abonnement, export des données et suppression (RGPD).
 *
 * Variables : $user (compte connecté), $droits (liste de [groupe, libellé, description]),
 * $actuel (abonnement en cours ou null), $errors (messages).
 */
?>
<div class="auth">
    <div class="panel">
        <h1>Mon compte</h1>

        <?php include __DIR__ . '/../partials/erreurs.php'; ?>

        <?php // region Informations et droits du compte ?>
        <dl class="profile">
            <dt>Nom d'utilisateur</dt><dd><?= e($user['username']) ?></dd>
            <?php if ($user['first_name'] !== '' || $user['last_name'] !== ''): ?>
                <dt>Nom</dt><dd><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?></dd>
            <?php endif; ?>
            <?php if ($user['email'] !== ''): ?>
                <dt>E-mail</dt><dd><?= e($user['email']) ?></dd>
            <?php endif; ?>
            <?php if ($user['phone'] !== ''): ?>
                <dt>Téléphone</dt><dd><?= e($user['phone']) ?></dd>
            <?php endif; ?>
            <dt>Rôle</dt><dd><?= $user['role'] === 'admin' ? 'Administrateur (tous les droits)' : 'Joueur' ?></dd>
            <dt>Mes droits</dt>
            <dd>
                <?php if (!$droits): ?>
                    Aucun droit de gestion
                <?php else: ?>
                    <ul class="rights">
                        <?php foreach ($droits as [$groupe, $libelle]): ?>
                            <li><?= e($groupe) ?> — <?= e($libelle) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </dd>
            <dt>Compte créé le</dt><dd><?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?></dd>
            <dt>Dernière connexion</dt><dd><?= $user['last_login'] ? e(date('d/m/Y H:i', strtotime($user['last_login']))) : '—' ?></dd>
        </dl>
        <?php // endregion ?>

        <?php // region Mon abonnement ?>
        <h2>Mon abonnement</h2>
        <p>
            <?php if ($actuel): ?>
                ⭐ <strong><?= e($actuel['name']) ?></strong>, valable jusqu'au <?= e(date('d/m/Y', strtotime($actuel['end']))) ?>
                <?= $actuel['status'] === 'resiliee' ? '(résilié, non renouvelé)' : '' ?>
            <?php else: ?>
                Formule <strong>Gratuit</strong>.
            <?php endif; ?>
        </p>
        <p><a class="btn" href="<?= e(url_reduit('abonnement', 'index')) ?>">⭐ Gérer mon abonnement</a></p>
        <?php // endregion ?>

        <?php // region Mes données (RGPD : accès et portabilité) ?>
        <h2>Mes données (RGPD)</h2>
        <p class="muted">
            Vous pouvez récupérer toutes les données que ce site conserve sur vous, ou supprimer votre compte.
            Voir la <a href="<?= url('/confidentialite') ?>">politique de confidentialité</a>.
        </p>
        <p><a class="btn" href="<?= url('/mon-compte/donnees') ?>">⬇ Télécharger mes données (JSON)</a></p>
        <?php // endregion ?>

        <?php // region Suppression du compte (RGPD : effacement) ?>
        <h2>Supprimer mon compte</h2>
        <p class="muted">Action définitive : votre compte, vos abonnements et vos données sont effacés immédiatement.</p>
        <form method="post" action="<?= url('/mon-compte/supprimer') ?>" class="form"
              onsubmit="return confirm('Supprimer définitivement votre compte ?');">
            <?= csrf_field() ?>
            <label class="field">
                <span>Confirmez avec votre mot de passe</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
        </form>
        <?php // endregion ?>
    </div>
</div>
