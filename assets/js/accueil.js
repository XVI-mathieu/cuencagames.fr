/**
 * Accueil (app/Views/home/index.php) : filtrage par catégorie et recherche instantanée dans la liste des cartes,
 * sans recharger la page.
 *
 * Chaque carte porte data-category (sa catégorie) et data-search (son nom + sa description).
 */
(function () {
    'use strict';

    // region Éléments de la page

    var champRecherche = document.getElementById('search');
    var cartes = document.querySelectorAll('#projects .card');
    var boutonsFiltre = document.querySelectorAll('#chips .chip');
    var messageVide = document.getElementById('no-result');
    var compteur = document.getElementById('count');
    var categorie = 'all'; // catégorie sélectionnée ('all' = toutes)

    // endregion

    // region Filtrage

    /** Affiche seulement les cartes de la catégorie choisie ET contenant le texte tapé, puis met à jour le compteur. */
    function appliquer() {
        var texte = champRecherche.value.trim().toLowerCase();
        var visibles = 0;
        cartes.forEach(function (carte) {
            var correspond = (categorie === 'all' || carte.dataset.category === categorie)
                && carte.dataset.search.toLowerCase().includes(texte);
            carte.hidden = !correspond;
            if (correspond) { visibles++; }
        });
        messageVide.hidden = visibles > 0;
        compteur.textContent = visibles + ' résultat' + (visibles > 1 ? 's' : '');
    }

    champRecherche.addEventListener('input', appliquer);
    boutonsFiltre.forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            boutonsFiltre.forEach(function (autre) { autre.classList.remove('is-active'); });
            bouton.classList.add('is-active');
            categorie = bouton.dataset.filter;
            appliquer();
        });
    });

    // endregion
})();
