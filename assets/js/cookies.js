/**
 * Bandeau d'information cookies (app/Views/layout.php).
 * Le site n'utilise que des cookies techniques indispensables : le bandeau informe, aucun consentement n'est requis.
 * Une fois « J'ai compris » cliqué, il ne réapparaît pas pendant un an (mémorisé dans le navigateur, localStorage).
 */
(function () {
    'use strict';

    // region Éléments et mémoire

    var CLE = 'cookies_info_ok';                       // clé de stockage : date à laquelle le bandeau a été lu
    var DUREE = 365 * 24 * 3600 * 1000;                // un an, en millisecondes
    var bandeau = document.getElementById('cookie-banner');
    if (!bandeau) { return; }

    /** Vrai si le bandeau a déjà été lu depuis moins d'un an. */
    function dejaLu() {
        try {
            var date = parseInt(localStorage.getItem(CLE), 10);
            return Boolean(date) && (Date.now() - date) < DUREE;
        } catch (erreur) {
            return false; // stockage indisponible (navigation privée…) : on réaffiche le bandeau
        }
    }

    /** Mémorise la lecture du bandeau. */
    function memoriser() {
        try {
            localStorage.setItem(CLE, String(Date.now()));
        } catch (erreur) { /* stockage indisponible : le bandeau réapparaîtra à la prochaine visite */ }
    }

    // endregion

    // region Comportement

    bandeau.hidden = dejaLu();

    document.getElementById('cookie-ok').addEventListener('click', function () {
        memoriser();
        bandeau.hidden = true;
    });

    // lien « Gérer les cookies » du pied de page : rouvre le bandeau
    var rouvrir = document.getElementById('cookie-reopen');
    if (rouvrir) {
        rouvrir.addEventListener('click', function () { bandeau.hidden = false; });
    }

    // endregion
})();
