/**
 * Page du panier (app/Views/panier/index.php) :
 *  - modifier une quantité envoie tout de suite le formulaire (pas besoin de cliquer sur « Mettre à jour ») ;
 *  - « Vider le panier » demande une confirmation.
 */
(function () {
    'use strict';

    // region Quantités

    var formulaire = document.getElementById('form-panier');
    if (formulaire) {
        formulaire.querySelectorAll('input.qte').forEach(function (champ) {
            // l'événement « change » ne part qu'à la fin de la saisie (touche Entrée, clic ailleurs, flèches)
            champ.addEventListener('change', function () {
                if (champ.checkValidity()) {
                    formulaire.submit();
                }
            });
        });
    }

    // endregion

    // region Vidage du panier

    var vider = document.getElementById('form-vider');
    if (vider) {
        vider.addEventListener('submit', function (evenement) {
            if (!window.confirm('Vider entièrement votre panier ?')) {
                evenement.preventDefault();
            }
        });
    }

    // endregion
})();
