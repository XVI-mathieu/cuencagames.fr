<?php
/**
 * Mise en page commune à toutes les pages : en-tête, menu, messages flash, pied de page et bandeau cookies.
 *
 * Variables : $content (HTML de la vue), $title (facultatif), $cinema (vrai sur l'accueil), et les données communes
 * de Controller::commonViewData() : $site, $session.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php // region Métadonnées et styles ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($title) ? $title . ' — ' . $site['nom'] : $site['nom']) ?></title>
    <meta name="description" content="<?= e($site['slogan']) ?>">
    <link rel="icon" href="<?= url('/assets/images/favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= url('/assets/images/logo.png') ?>">
    <?php /* Polices hébergées sur le site : aucune requête vers Google (RGPD) */ ?>
    <link rel="preload" href="<?= url('/assets/fonts/orbitron-700.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= url('/assets/fonts/fonts.css') ?>">
    <?php /* ?v=… : l'adresse change quand le fichier change, le navigateur recharge alors la nouvelle version */ ?>
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>?v=<?= (int) @filemtime(ROOT . '/assets/css/style.css') ?>">
    <?php /* Grille des jeux (4 colonnes) : écrite directement dans la page, donc jamais servie depuis un ancien cache de feuille de style */ ?>
    <style>
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        @media (max-width: 880px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 440px) { .grid { grid-template-columns: 1fr; } }
        @media (max-width: 1250px) { .grid { gap: 14px; } .card-head { gap: 12px; } .card-head h2 { font-size: .92rem; } .card-desc { font-size: .92rem; } }
    </style>
    <?php // endregion ?>

    <?php // region Scène cinématique de l'accueil ?>
    <?php
    /* Voir assets/cinema/README.md. Mode dans config.php : 'off' | 'test' (?cinema=1) | 'on'.
       Avec prefers-reduced-motion ou un écran trop court : version fixe (décor + titre, puis le catalogue classique). */
    if (!empty($cinema) && $site['cinema'] !== 'off'): ?>
    <script>
    (function(){var d=document.documentElement;try{if(<?= $site['cinema'] === 'on' ? 'true' : 'false' ?>||/[?&]cinema=1/.test(location.search)){var st=matchMedia('(prefers-reduced-motion: reduce)').matches||innerHeight<560;d.classList.add('cinema-on');if(st){d.classList.add('cinema-static');}setTimeout(function(){if(!window.__cinemaReady){d.classList.remove('cinema-on','cinema-static');}},4000);}}catch(e){}})();
    </script>
    <link rel="stylesheet" href="<?= url('/assets/cinema/cinema.css') ?>?v=<?= (int) @filemtime(ROOT . '/assets/cinema/cinema.css') ?>">
    <script src="<?= url('/assets/cinema/cinema.js') ?>?v=<?= (int) @filemtime(ROOT . '/assets/cinema/cinema.js') ?>" defer></script>
    <?php endif; ?>
    <?php // endregion ?>
</head>
<body>
<?php // region En-tête et menu ?>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= url('/') ?>">
            <img class="brand-logo" src="<?= url('/assets/images/logo.png') ?>" alt="" width="40" height="40">
            <span class="brand-name"><?= e($site['nom']) ?></span>
        </a>
        <nav class="nav-links">
            <a href="<?= url('/') ?>">Projets</a>
            <a href="<?= url('/securite') ?>">🛡️ Sécurité</a>
            <?php /* Le lien des abonnements passe par l'index réduit : index.php?controleur=abonnement&action=index */ ?>
            <a href="<?= e(url_reduit('abonnement', 'index')) ?>" class="nav-abo">⭐ Abonnements</a>
            <?php if ($session['connecte']): ?>
                <a href="<?= url('/mon-compte') ?>" class="nav-user">👤 <?= e($session['pseudo']) ?></a>
                <?php if ($session['tableauBord']): ?>
                    <a href="<?= url('/admin') ?>">Tableau de bord</a>
                <?php endif; ?>
                <form method="post" action="<?= url('/deconnexion') ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="<?= url('/connexion') ?>">Se connecter</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php // endregion ?>

<?php // region Contenu de la page, précédé des messages flash ?>
<main class="container main">
    <?php foreach (take_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>
<?php // endregion ?>

<?php // region Pied de page ?>
<footer class="site-footer">
    <div class="container footer-row">
        <span>© <?= date('Y') ?> <?= e($site['auteur']) ?> — <?= e($site['nom']) ?></span>
        <nav class="footer-links" aria-label="Informations légales">
            <a href="<?= url('/mentions-legales') ?>">Mentions légales</a>
            <a href="<?= url('/confidentialite') ?>">Confidentialité (RGPD)</a>
            <a href="<?= url('/confidentialite#cookies') ?>">Cookies</a>
            <button type="button" class="link-button" id="cookie-reopen">Gérer les cookies</button>
        </nav>
    </div>
</footer>
<?php // endregion ?>

<?php // region Bandeau d'information cookies ?>
<?php /* Seuls des cookies techniques indispensables sont utilisés : le bandeau informe, il ne demande pas d'accord */ ?>
<div class="cookie-banner" id="cookie-banner" role="dialog" aria-live="polite" aria-label="Information sur les cookies" hidden>
    <p>
        🍪 Ce site n'utilise que des <strong>cookies techniques indispensables</strong> (maintien de votre connexion et
        sécurité des formulaires). Ils ne servent ni à vous suivre ni à la publicité et ne nécessitent pas votre accord.
        <a href="<?= url('/confidentialite#cookies') ?>">En savoir plus</a>
    </p>
    <button type="button" class="btn btn-primary btn-sm" id="cookie-ok">J'ai compris</button>
</div>
<script src="<?= url('/assets/js/cookies.js') ?>" defer></script>
<?php // endregion ?>
</body>
</html>
