/**
 * Page « Se connecter / Créer un compte » (app/Views/account/connexion.php).
 *  - bascule entre les deux onglets sans recharger la page ;
 *  - vérifie en AJAX, pendant la frappe, que le nom d'utilisateur et l'adresse e-mail choisis à l'inscription
 *    sont valides et libres.
 *
 * L'adresse du service de vérification est lue dans l'attribut data-url-disponibilite de l'élément .auth.
 */
(function () {
    'use strict';

    // region Onglets

    /** Affiche l'onglet « login » ou « register » et cache l'autre. */
    function afficherOnglet(nom) {
        document.querySelectorAll('.tab').forEach(function (onglet) {
            onglet.classList.toggle('active', onglet.dataset.tab === nom);
        });
        document.querySelectorAll('.tab-pane').forEach(function (volet) {
            volet.hidden = volet.id !== 'pane-' + nom;
        });
    }

    document.querySelectorAll('.tab').forEach(function (onglet) {
        onglet.addEventListener('click', function (evenement) {
            evenement.preventDefault();
            afficherOnglet(onglet.dataset.tab);
        });
    });
    if (location.hash === '#register') {
        afficherOnglet('register'); // lien « Créer un compte » venant d'une autre page
    }

    // endregion

    // region Vérification AJAX de la disponibilité d'un champ

    var adresse = document.querySelector('.auth').dataset.urlDisponibilite;

    /**
     * Surveille un champ du formulaire d'inscription : après une courte pause de frappe, demande au serveur si la valeur
     * est valide et libre, et affiche la réponse sous le champ (vert si ok, rouge sinon).
     *
     * @param {string} idChamp    Identifiant du champ <input>.
     * @param {string} idEtat     Identifiant du texte affiché sous le champ.
     * @param {string} champServeur 'pseudo' ou 'email' (ce que le serveur doit vérifier).
     * @param {number} minimum    Longueur minimale avant d'interroger le serveur.
     */
    function surveiller(idChamp, idEtat, champServeur, minimum) {
        var champ = document.getElementById(idChamp);
        var etat = document.getElementById(idEtat);
        var aide = etat.textContent;  // texte d'aide d'origine, réaffiché quand il n'y a rien à vérifier
        var minuteur = null;          // attend une pause de frappe avant d'interroger le serveur
        var derniereRequete = 0;      // une réponse tardive d'une ancienne frappe est ignorée

        /** Affiche le message et bloque l'envoi du formulaire tant que la valeur est refusée. */
        function afficherEtat(texte, ok) {
            etat.textContent = texte;
            etat.style.color = ok === null ? '' : (ok ? 'var(--success-text)' : 'var(--error-text)');
            champ.setCustomValidity(ok === false ? texte : '');
        }

        champ.addEventListener('input', function () {
            clearTimeout(minuteur);
            var valeur = champ.value.trim();
            if (valeur.length < minimum) {
                afficherEtat(aide, null);
                return;
            }
            minuteur = setTimeout(function () {
                var numero = ++derniereRequete;
                fetch(adresse + '?champ=' + champServeur + '&valeur=' + encodeURIComponent(valeur), { credentials: 'same-origin' })
                    .then(function (reponse) { return reponse.json(); })
                    .then(function (donnees) {
                        if (numero === derniereRequete) { afficherEtat(donnees.message, donnees.disponible); }
                    })
                    .catch(function () {
                        // réseau coupé : on n'affiche rien, le serveur revérifiera à l'envoi du formulaire
                        if (numero === derniereRequete) { afficherEtat(aide, null); }
                    });
            }, 400);
        });
    }

    surveiller('reg-username', 'pseudo-etat', 'pseudo', 3);
    surveiller('reg-email', 'email-etat', 'email', 5);

    // endregion
})();
