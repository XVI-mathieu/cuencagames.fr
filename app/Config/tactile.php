<?php
/**
 * Commandes tactiles des jeux en version navigateur, par nom de jeu (en minuscules).
 *
 * Chaque profil :
 *  - mode      : 'stick' (joystick + boutons) ou 'none' (le jeu se joue déjà au toucher : seul le réglage d'affichage est ajouté) ;
 *  - stick     : '4' (4 directions, par défaut) ou 'h' (gauche / droite seulement, pour les jeux de course) ;
 *  - buttons   : liste de [libellé, [touches du clavier], grand bouton ?] — chaque bouton maintient ces touches tant qu'on l'appuie ;
 *  - mouse     : vrai si le jeu se vise à la souris (le toucher est alors converti en évènements souris) ;
 *  - landscape : vrai pour conseiller le mode paysage ;
 *  - pause     : faux pour masquer le bouton de pause (Échap).
 * Les jeux « Cuenca Games » (noyau commun) ont leurs propres commandes tactiles : ils n'ont pas besoin d'entrée ici.
 */
return [
    // Profil utilisé pour un jeu sans entrée dédiée : joystick + trois boutons classiques
    '_defaut' => ['mode' => 'stick', 'buttons' => [['A', ['Space'], true], ['B', ['KeyE']], ['⇧', ['ShiftLeft']]], 'landscape' => true],

    // --- Jeux de course : volant à gauche, accélérateur et frein à droite ---
    'f1 2026' => ['mode' => 'stick', 'stick' => 'h', 'landscape' => true,
        'buttons' => [['⬆', ['ArrowUp'], true], ['⬇', ['ArrowDown']], ['Rapp+', ['ShiftLeft']], ['Rapp−', ['ControlLeft']], ['Cam', ['KeyC']], ['R', ['KeyR']]]],
    'destruction derby' => ['mode' => 'stick', 'stick' => 'h', 'landscape' => true,
        'buttons' => [['⬆', ['ArrowUp'], true], ['⬇', ['ArrowDown']], ['Esp', ['Space']], ['⇧', ['ShiftLeft']], ['OK', ['Enter']]]],

    // --- Jeux d'action ---
    'rocket league' => ['mode' => 'stick', 'landscape' => true, 'buttons' => [['Saut', ['Space'], true], ['Boost', ['ShiftLeft']], ['Dérap', ['KeyX']], ['R', ['KeyR']]]],
    'smash arena' => ['mode' => 'stick', 'landscape' => true, 'mouse' => true, 'buttons' => [['Saut', ['Space'], true], ['Bouclier', ['ShiftLeft']]]],
    'squadarcade' => ['mode' => 'stick', 'landscape' => true, 'mouse' => true, 'buttons' => [['Saut', ['Space'], true], ['E', ['KeyE']], ['OK', ['Enter']]]],
    'trio' => ['mode' => 'stick', 'landscape' => true, 'buttons' => [['Saut', ['Space'], true], ['E', ['KeyE']], ['OK', ['Enter']], ['↺', ['KeyR']]]],

    // --- Jeux qui se jouent déjà au toucher (cartes, stratégie, clics) ---
    'star wars commander' => ['mode' => 'none', 'landscape' => true],
    'star wars clone wars' => ['mode' => 'none'],
    'tour défenses star wars' => ['mode' => 'none'],
    'belote' => ['mode' => 'none'], 'président' => ['mode' => 'none'], 'uno' => ['mode' => 'none'],
    'undercover' => ['mode' => 'none'], 'poker' => ['mode' => 'none'], 'blackjack' => ['mode' => 'none'],
];
