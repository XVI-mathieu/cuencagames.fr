<?php
/**
 * Première installation : création du mot de passe administrateur.
 *
 * Variables : $errors (messages d'erreur).
 */
?>
<div class="auth">
    <div class="panel">
        <h1>Bienvenue 👋</h1>
        <p class="muted">Première visite : choisissez le mot de passe administrateur qui vous permettra d'ajouter vos projets.</p>

        <?php include __DIR__ . '/../partials/erreurs.php'; ?>

        <form method="post" action="<?= url('/admin/installation') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span>Mot de passe (8 caractères minimum)</span>
                <input type="password" name="password" required minlength="8" autofocus autocomplete="new-password">
            </label>
            <label class="field">
                <span>Confirmer le mot de passe</span>
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Créer le compte</button>
        </form>
    </div>
</div>
