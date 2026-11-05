<?php
namespace App\Core;

use App\Models\Abonnement;
use App\Models\User;

/**
 * Authentification : comptes joueurs et administrateurs (rôle « admin »), identifiés par leur session PHP.
 * Les droits détaillés sont décrits dans {@see Permissions}.
 */
class Auth
{
    // region Cache de la requête

    /** Compte connecté, lu une seule fois par requête (menu, droits… l'appellent plusieurs fois). */
    private static ?array $cache = null;

    /** Vrai quand $cache est à jour ; remis à faux à chaque connexion ou déconnexion. */
    private static bool $cacheReady = false;

    /** Niveau d'abonnement du compte connecté, lu une seule fois par requête (null : pas encore lu). */
    private static ?int $niveau = null;

    // endregion

    // region État de l'installation

    /** Vrai s'il existe au moins un compte (sinon le site propose de créer l'administrateur). */
    public static function isConfigured(): bool
    {
        return User::hasAny();
    }

    // endregion

    // region Connexion et déconnexion

    /** Tente une connexion avec un nom et un mot de passe ; retourne l'utilisateur, ou null si les identifiants sont faux. */
    public static function attempt(string $username, string $password): ?array
    {
        $user = User::verify($username, $password);
        if (!$user) {
            return null;
        }
        self::login($user);
        return $user;
    }

    /** Ouvre la session de cet utilisateur (nouvel identifiant de session : protège contre le vol de session). */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        User::touchLogin($user['id']);
        self::$cacheReady = false;
        self::$niveau = null;
    }

    /** Ferme la session : vide ses données et en change l'identifiant. */
    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$cacheReady = false;
        self::$niveau = null;
    }

    // endregion

    // region Utilisateur courant et droits

    /** Utilisateur connecté, ou null (relu en base à chaque requête : un compte supprimé est aussitôt déconnecté). */
    public static function user(): ?array
    {
        if (!self::$cacheReady) {
            $id = $_SESSION['user_id'] ?? null;
            self::$cache = $id ? User::find($id) : null;
            self::$cacheReady = true;
        }
        return self::$cache;
    }

    /** Vrai si un utilisateur est connecté. */
    public static function loggedIn(): bool
    {
        return self::user() !== null;
    }

    /** Vrai pour un administrateur connecté. */
    public static function check(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Vrai si l'utilisateur connecté possède ce droit (l'administrateur les a tous). */
    public static function can(string $permission): bool
    {
        $user = self::user();
        return $user !== null && Permissions::has($user, $permission);
    }

    /**
     * Niveau d'ACCÈS du visiteur : un jeu est accessible si son niveau est inférieur ou égal à celui-ci.
     * Un compte de gestion (administrateur, ou tout compte qui a au moins un droit) joue à tout : niveau maximal.
     * Un simple joueur a le niveau de son abonnement ; un visiteur sans compte, 0 (jeux gratuits seulement).
     */
    public static function level(): int
    {
        return self::hasFullAccess() ? Abonnement::NIVEAU_MAX : self::subscriptionLevel();
    }

    /** Vrai pour un compte de gestion : accès à tous les jeux, sans abonnement ni limite de temps. */
    public static function hasFullAccess(): bool
    {
        return self::canManage();
    }

    /** Niveau de l'abonnement en cours du compte connecté (0 sans compte ou sans abonnement), indépendamment de ses droits. */
    public static function subscriptionLevel(): int
    {
        $user = self::user();
        if ($user === null) {
            return 0;
        }
        return self::$niveau ??= Abonnement::niveauUtilisateur($user['id']);
    }

    /** Vrai si l'utilisateur connecté a au moins un droit de gestion (accès au tableau de bord). */
    public static function canManage(): bool
    {
        $user = self::user();
        return $user !== null && Permissions::of($user) !== [];
    }

    // endregion
}
