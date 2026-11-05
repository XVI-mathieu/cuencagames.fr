<?php
namespace App\Core;

/**
 * Catalogue des droits (permissions) du site et profils prédéfinis.
 *
 * Le rôle « admin » possède TOUS les droits. Un autre compte (rôle « user ») n'a que les droits cochés pour lui.
 * Une seule source de vérité : le site envoie ce catalogue à l'application Windows « Gestionnaire Jeux »
 * et l'application « Gestionnaire Boutique » en reprend les mêmes clés.
 */
class Permissions
{
    // region Clés des droits

    /** Voir la liste des jeux. */
    public const JEUX_LIRE = 'jeux.lire';
    /** Publier un nouveau jeu. */
    public const JEUX_AJOUTER = 'jeux.ajouter';
    /** Modifier un jeu et remplacer ses fichiers. */
    public const JEUX_MODIFIER = 'jeux.modifier';
    /** Supprimer un jeu et ses fichiers. */
    public const JEUX_SUPPRIMER = 'jeux.supprimer';
    /** Voir la liste des comptes. */
    public const COMPTES_LIRE = 'comptes.lire';
    /** Créer un compte pour quelqu'un. */
    public const COMPTES_CREER = 'comptes.creer';
    /** Changer les droits et le mot de passe d'un joueur. */
    public const COMPTES_MODIFIER = 'comptes.modifier';
    /** Supprimer le compte d'un joueur. */
    public const COMPTES_SUPPRIMER = 'comptes.supprimer';
    /** Télécharger l'application de gestion depuis le tableau de bord. */
    public const APPLICATION = 'application.telecharger';
    /** Voir les abonnements de tous les joueurs (application Gestionnaire Boutique). */
    public const ABONNEMENTS_LIRE = 'abonnements.lire';
    /** Souscrire ou résilier un abonnement pour un joueur (application Gestionnaire Boutique). */
    public const ABONNEMENTS_CREER = 'abonnements.creer';

    // endregion

    // region Catalogue, profils et implications

    /** Catalogue complet : clé => [groupe, libellé, description]. */
    public const CATALOG = [
        self::JEUX_LIRE => ['Jeux', 'Consulter les jeux',
            'Voir la liste des jeux dans le tableau de bord et l\'application (lecture seule).'],
        self::JEUX_AJOUTER => ['Jeux', 'Ajouter des jeux',
            'Publier un nouveau jeu : fichier .exe / .zip et version .html. (Inclut « Consulter les jeux ».)'],
        self::JEUX_MODIFIER => ['Jeux', 'Modifier des jeux',
            'Changer le nom, la catégorie, la version, la description et remplacer les fichiers .exe / .html. (Inclut « Consulter les jeux ».)'],
        self::JEUX_SUPPRIMER => ['Jeux', 'Supprimer des jeux',
            'Supprimer définitivement un jeu et ses fichiers. (Inclut « Consulter les jeux ».)'],
        self::COMPTES_LIRE => ['Comptes', 'Consulter les comptes',
            'Voir la liste des utilisateurs, leurs rôles, droits et dates de connexion (lecture seule).'],
        self::COMPTES_CREER => ['Comptes', 'Créer des comptes',
            'Créer un compte pour quelqu\'un. (Inclut « Consulter les comptes ».)'],
        self::COMPTES_MODIFIER => ['Comptes', 'Modifier des comptes',
            'Changer les droits et le mot de passe d\'un utilisateur (jamais ceux d\'un administrateur, et jamais plus de droits que soi-même). (Inclut « Consulter les comptes ».)'],
        self::COMPTES_SUPPRIMER => ['Comptes', 'Supprimer des comptes',
            'Supprimer le compte d\'un utilisateur (jamais celui d\'un administrateur). (Inclut « Consulter les comptes ».)'],
        self::APPLICATION => ['Application', 'Télécharger l\'application de gestion',
            'Télécharger l\'application Windows depuis le tableau de bord.'],
        self::ABONNEMENTS_LIRE => ['Abonnements', 'Consulter les abonnements',
            'Voir les abonnements de tous les joueurs dans l\'application « Gestionnaire Boutique » (lecture seule).'],
        self::ABONNEMENTS_CREER => ['Abonnements', 'Gérer les abonnements',
            'Souscrire ou résilier un abonnement pour un joueur dans l\'application « Gestionnaire Boutique ». (Inclut « Consulter les abonnements ».)'],
    ];

    /** Profils prédéfinis : nom => droits. « Administrateur » = rôle admin (tous les droits, noté '*'). */
    public const PROFILES = [
        'Joueur'         => [],
        'Lecteur'        => [self::JEUX_LIRE, self::COMPTES_LIRE, self::ABONNEMENTS_LIRE],
        'Éditeur'        => [self::JEUX_LIRE, self::JEUX_AJOUTER, self::JEUX_MODIFIER, self::APPLICATION],
        'Gestionnaire'   => [self::JEUX_LIRE, self::JEUX_AJOUTER, self::JEUX_MODIFIER, self::JEUX_SUPPRIMER,
                             self::COMPTES_LIRE, self::ABONNEMENTS_LIRE, self::ABONNEMENTS_CREER, self::APPLICATION],
        // --- Rôles spécialisés, à cumuler selon les besoins ---
        'Développeur'    => [self::JEUX_LIRE, self::JEUX_AJOUTER, self::JEUX_MODIFIER, self::JEUX_SUPPRIMER, self::APPLICATION],
        'Mainteneur'     => [self::JEUX_LIRE, self::JEUX_MODIFIER],
        'Modérateur'     => [self::JEUX_LIRE, self::COMPTES_LIRE, self::COMPTES_MODIFIER],
        'Support client' => [self::COMPTES_LIRE, self::COMPTES_CREER, self::COMPTES_MODIFIER, self::ABONNEMENTS_LIRE],
        'Responsable abonnements' => [self::COMPTES_LIRE, self::ABONNEMENTS_LIRE, self::ABONNEMENTS_CREER],
        'Responsable comptes' => [self::COMPTES_LIRE, self::COMPTES_CREER, self::COMPTES_MODIFIER, self::COMPTES_SUPPRIMER],
        'Nettoyeur'      => [self::JEUX_LIRE, self::JEUX_SUPPRIMER],
        'Administrateur' => ['*'],
    ];

    /** Phrase d'explication de chaque profil (affichée dans l'application Windows, à côté de la case du rôle). */
    public const PROFILE_DESCRIPTIONS = [
        'Joueur'         => 'Compte ordinaire : joue, s\'abonne, aucun droit de gestion.',
        'Lecteur'        => 'Consulte les jeux, les comptes et les abonnements (lecture seule).',
        'Éditeur'        => 'Ajoute et modifie des jeux, télécharge l\'application de gestion.',
        'Gestionnaire'   => 'Gère les jeux (ajout, modification, suppression) et les abonnements ; voit les comptes.',
        'Développeur'    => 'Publie, met à jour et retire ses jeux (.exe et .html), télécharge l\'application ; ne touche pas aux comptes.',
        'Mainteneur'     => 'Corrige les jeux existants (description, version, fichiers, maintenance) sans en ajouter ni en supprimer.',
        'Modérateur'     => 'Voit les comptes, change mots de passe et droits des joueurs ; consulte les jeux.',
        'Support client' => 'Crée les comptes, aide les joueurs (mots de passe) et consulte leurs abonnements.',
        'Responsable abonnements' => 'Souscrit et résilie les abonnements des joueurs ; voit les comptes.',
        'Responsable comptes'     => 'Crée, modifie et supprime les comptes des joueurs (jamais ceux d\'un administrateur).',
        'Nettoyeur'      => 'Retire des jeux du catalogue (suppression), sans pouvoir les modifier.',
        'Administrateur' => 'Tous les droits, y compris les comptes et les rôles.',
    ];

    /** Droit « lecture » requis par chaque droit d'écriture du même groupe. */
    private const IMPLIES = [
        self::JEUX_AJOUTER => self::JEUX_LIRE,
        self::JEUX_MODIFIER => self::JEUX_LIRE,
        self::JEUX_SUPPRIMER => self::JEUX_LIRE,
        self::COMPTES_CREER => self::COMPTES_LIRE,
        self::COMPTES_MODIFIER => self::COMPTES_LIRE,
        self::COMPTES_SUPPRIMER => self::COMPTES_LIRE,
        self::ABONNEMENTS_CREER => self::ABONNEMENTS_LIRE,
    ];

    // endregion

    // region Calcul des droits

    /** Toutes les clés de droits connues. */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    /** Garde les droits connus, ajoute les droits de lecture impliqués, retire les doublons. */
    public static function normalize(array $permissions): array
    {
        $permissions = array_values(array_intersect(self::all(), array_map('strval', $permissions)));
        foreach ($permissions as $permission) {
            if (isset(self::IMPLIES[$permission])) {
                $permissions[] = self::IMPLIES[$permission];
            }
        }
        return array_values(array_intersect(self::all(), array_unique($permissions)));
    }

    /** Droits effectifs d'un compte (administrateur : tous). */
    public static function of(array $user): array
    {
        if (($user['role'] ?? '') === 'admin') {
            return self::all();
        }
        return self::normalize($user['permissions'] ?? []);
    }

    /** Vrai si ce compte possède ce droit. */
    public static function has(array $user, string $permission): bool
    {
        return in_array($permission, self::of($user), true);
    }

    // endregion

    // region Formats pour l'application Windows

    /** Catalogue au format JSON attendu par l'application Windows : liste de {key, group, label, description}. */
    public static function catalogForApi(): array
    {
        $liste = [];
        foreach (self::CATALOG as $cle => [$groupe, $libelle, $description]) {
            $liste[] = ['key' => $cle, 'group' => $groupe, 'label' => $libelle, 'description' => $description];
        }
        return $liste;
    }

    /** Profils au format JSON attendu par l'application Windows : liste de {name, admin, permissions}. */
    public static function profilesForApi(): array
    {
        $profils = [];
        foreach (self::PROFILES as $nom => $permissions) {
            $estAdmin = $permissions === ['*'];
            $profils[] = [
                'name'        => $nom,
                'description' => self::PROFILE_DESCRIPTIONS[$nom] ?? '',
                'admin'       => $estAdmin,
                'permissions' => $estAdmin ? self::all() : self::normalize($permissions),
            ];
        }
        return $profils;
    }

    // endregion
}
