<?php
/**
 * Configuration du site : modifiez ces valeurs à votre goût.
 * Lue par App\Core\Config (les réglages de config.local.php et les variables d'environnement les complètent).
 */
return [
    // region Identité du site

    'site_name'          => 'Mes Projets',
    'site_tagline'       => 'Toutes mes applications Windows, prêtes à télécharger.',
    'author'             => 'Mathieu Cuenca',
    'timezone'           => 'Europe/Paris',

    // endregion

    // region Informations légales (pages « Mentions légales » et « Confidentialité »)

    'editeur_statut'     => 'Étudiant en BTS SIO (option SLAM) — site personnel à but pédagogique, non commercial',
    'contact_url'        => 'https://cuencamathieu.com',
    'hebergeur_nom'      => 'Hostinger International Ltd.',
    'hebergeur_url'      => 'https://www.hostinger.fr',

    // endregion

    // region Fichiers envoyés

    // Taille maximale d'un fichier envoyé (en Mo).
    // Doit rester inférieure à upload_max_filesize ET post_max_size de PHP
    // (sur Hostinger : hPanel → Configuration PHP → Options PHP).
    'max_upload_mb'      => 2000,
    'allowed_extensions' => ['exe', 'zip'],

    // endregion

    // region Version navigateur et accueil

    // Version navigateur (.html) : durée de jeu gratuite pour les visiteurs SANS compte (en secondes).
    // Un visiteur connecté joue sans limite. 0 = pas de limite pour personne.
    'play_limit_seconds' => 600,

    // Scène cinématique de l'accueil (assets/cinema, voir assets/cinema/README.md) :
    // 'off' = désactivée, 'test' = seulement avec ?cinema=1 dans l'adresse, 'on' = pour tous les visiteurs.
    'cinema_mode'        => 'on',

    // Catégories affichées sur l'accueil (clé interne => libellé affiché)
    'categories'         => [
        'jeux'    => 'Jeux',
        'projets' => 'Projets',
    ],

    // endregion

    // region Base de données MySQL

    // NE PAS écrire ici les vrais identifiants : copiez-les dans app/Config/config.local.php
    // (modèle : app/Config/config.local.example.php), fichier qui n'est jamais versionné ni publié sur GitHub.
    'db' => [
        'hote'         => '127.0.0.1',
        'port'         => 3306,
        'nom'          => '',
        'utilisateur'  => '',
        'mot_de_passe' => '',
    ],

    // endregion

    // region Boutique de démonstration

    // Projet BTS SIO : aucun paiement réel, les prix sont fictifs.
    'devise'             => '€',

    // endregion
];
