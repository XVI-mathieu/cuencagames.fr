<?php
/**
 * Modèle de configuration locale.
 *
 * 1. Copiez ce fichier sous le nom  config.local.php  (dans le même dossier : app/Config).
 * 2. Remplissez les identifiants de VOTRE base MySQL (Hostinger : hPanel → Bases de données → Gestion).
 * 3. Ne publiez jamais config.local.php sur GitHub (il est déjà exclu par .gitignore).
 */
return [
    'db' => [
        'hote'         => 'localhost',
        'port'         => 3306,
        'nom'          => 'u123456789_cuencagames',
        'utilisateur'  => 'u123456789_cuenca',
        'mot_de_passe' => 'à remplir',
    ],
];
