<?php
/**
 * Page « Se connecter / Créer un compte » (deux onglets, gérés par assets/js/connexion.js).
 * Connexion : pseudo OU e-mail, puis mot de passe. Inscription : nom, prénom, e-mail, téléphone (facultatif), pseudo, mot de passe.
 *
 * Variables : $tab ('login' ou 'register'), $errors (messages), $old (valeurs à réafficher, jamais les mots de passe).
 */
$ancien = fn (string $champ, string $onglet): string => $tab === $onglet ? (string) ($old[$champ] ?? '') : ''; // valeur à réafficher
?>
<div class="auth" data-url-disponibilite="<?= e(url('/compte/disponibilite')) ?>">
    <div class="panel">
        <?php // region Onglets ?>
        <div class="tabs" role="tablist">
            <a href="#login" class="tab <?= $tab === 'login' ? 'active' : '' ?>" data-tab="login">Se connecter</a>
            <a href="#register" class="tab <?= $tab === 'register' ? 'active' : '' ?>" data-tab="register">Créer un compte</a>
        </div>
        <?php // endregion ?>

        <?php include __DIR__ . '/../partials/erreurs.php'; ?>

        <?php // region Formulaire de connexion : pseudo ou e-mail ?>
        <form method="post" action="<?= url('/connexion') ?>" class="form tab-pane" id="pane-login" <?= $tab === 'login' ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <label class="field">
                <span>Nom d'utilisateur ou e-mail</span>
                <input type="text" name="login" required autocomplete="username" value="<?= e($ancien('login', 'login')) ?>">
            </label>
            <label class="field">
                <span>Mot de passe</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>
        <?php // endregion ?>

        <?php // region Formulaire de création de compte ?>
        <form method="post" action="<?= url('/inscription') ?>" class="form tab-pane" id="pane-register" <?= $tab === 'register' ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <div class="row">
                <label class="field grow">
                    <span>Nom *</span>
                    <input type="text" name="last_name" required minlength="2" maxlength="60" autocomplete="family-name" value="<?= e($ancien('last_name', 'register')) ?>">
                </label>
                <label class="field grow">
                    <span>Prénom *</span>
                    <input type="text" name="first_name" required minlength="2" maxlength="60" autocomplete="given-name" value="<?= e($ancien('first_name', 'register')) ?>">
                </label>
            </div>
            <label class="field">
                <span>Adresse e-mail *</span>
                <input type="email" name="email" id="reg-email" required maxlength="190" autocomplete="email" value="<?= e($ancien('email', 'register')) ?>">
                <small id="email-etat" aria-live="polite">Elle servira aussi à vous connecter.</small>
            </label>
            <label class="field">
                <span>Téléphone (facultatif)</span>
                <input type="tel" name="phone" maxlength="20" autocomplete="tel" placeholder="06 12 34 56 78" pattern="[+]?[0-9 .\-]{6,20}" value="<?= e($ancien('phone', 'register')) ?>">
            </label>
            <label class="field">
                <span>Nom d'utilisateur *</span>
                <input type="text" name="username" id="reg-username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]+" autocomplete="nickname" value="<?= e($ancien('username', 'register')) ?>">
                <small id="pseudo-etat" aria-live="polite">3 à 30 caractères : lettres, chiffres, _ . -</small>
            </label>
            <label class="field">
                <span>Mot de passe * (8 caractères minimum)</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label class="field">
                <span>Confirmer le mot de passe *</span>
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
            <label class="consent">
                <input type="checkbox" name="accept_rgpd" value="1" required>
                <span>J'ai lu la <a href="<?= url('/confidentialite') ?>" target="_blank" rel="noopener">politique de confidentialité</a>
                    et j'accepte que mes informations (nom, prénom, e-mail, téléphone, pseudo) et mon mot de passe (chiffré)
                    soient conservés pour gérer mon compte et mes abonnements. Je peux supprimer mon compte à tout moment.</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
        </form>
        <?php // endregion ?>
    </div>
</div>
<script src="<?= url('/assets/js/connexion.js') ?>?v=<?= (int) @filemtime(ROOT . '/assets/js/connexion.js') ?>"></script>
