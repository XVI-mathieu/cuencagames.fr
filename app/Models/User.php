<?php
namespace App\Models;

use App\Core\ModelePDO;
use App\Core\Permissions;

/**
 * Modèle « Utilisateur » : comptes du site (table `utilisateur`).
 * Rôles : « admin » (gère le site) ou « user » (joueur).
 *
 * Les méthodes renvoient des tableaux aux clés suivantes : id, username (pseudo), last_name (nom), first_name (prénom),
 * email, phone (téléphone, facultatif), password_hash, role, permissions, created_at, last_login.
 */
class User extends ModelePDO
{
    // region Constantes

    /** Rôles possibles d'un compte. */
    public const ROLES = ['user', 'admin'];

    /** Nom ou prénom : lettres (accents compris), espaces, apostrophes et tirets ; 2 à 60 caractères. */
    private const MOTIF_NOM = "/^\\p{L}[\\p{L} '\\-]{0,58}\\p{L}$/u";

    /** Pseudo : lettres, chiffres, _ . - ; 3 à 30 caractères (pas de « @ » : un pseudo ne ressemble jamais à un e-mail). */
    private const MOTIF_PSEUDO = '/^[A-Za-z0-9_.-]{3,30}$/';

    // endregion

    // region Lecture

    /** Tous les comptes, du plus ancien au plus récent. */
    public static function all(): array
    {
        return array_map(self::hydrate(...), self::getLesTuples('SELECT * FROM utilisateur ORDER BY cree_le, pseudo'));
    }

    /** Vrai s'il existe au moins un compte (sinon le site propose l'installation de l'administrateur). */
    public static function hasAny(): bool
    {
        return (int) self::getUneValeur('SELECT COUNT(*) FROM utilisateur') > 0;
    }

    /** Nombre d'administrateurs (sert à protéger le dernier). */
    public static function adminCount(): int
    {
        return (int) self::getUneValeur("SELECT COUNT(*) FROM utilisateur WHERE role = 'admin'");
    }

    /** Un compte par son identifiant (null s'il n'existe pas). */
    public static function find(string $id): ?array
    {
        return self::trouver('id', $id);
    }

    /** Un compte par son nom d'utilisateur, sans tenir compte des majuscules (null s'il n'existe pas). */
    public static function findByName(string $username): ?array
    {
        // la collation utf8mb4_unicode_ci compare sans distinguer majuscules et minuscules
        return self::trouver('pseudo', $username);
    }

    /** Un compte par son adresse e-mail, sans tenir compte des majuscules (null s'il n'existe pas). */
    public static function findByEmail(string $email): ?array
    {
        return self::trouver('email', $email);
    }

    /** Un compte par son identifiant de connexion : l'adresse e-mail si elle contient « @ », sinon le pseudo. */
    public static function findByLogin(string $login): ?array
    {
        return str_contains($login, '@') ? self::findByEmail($login) : self::findByName($login);
    }

    // endregion

    // region Validation (chaque méthode retourne un message d'erreur, ou null si la valeur est valide)

    /** Pseudo : format correct et libre. */
    public static function usernameError(string $username): ?string
    {
        if (!preg_match(self::MOTIF_PSEUDO, $username)) {
            return 'Le nom d\'utilisateur doit faire 3 à 30 caractères (lettres, chiffres, _ . -).';
        }
        if (self::findByName($username)) {
            return 'Ce nom d\'utilisateur est déjà pris.';
        }
        return null;
    }

    /** Adresse e-mail : format correct et pas déjà utilisée. */
    public static function emailError(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || str_length($email) > 190) {
            return 'L\'adresse e-mail n\'est pas valide.';
        }
        if (self::findByEmail($email)) {
            return 'Cette adresse e-mail est déjà utilisée.';
        }
        return null;
    }

    /** Nom ou prénom ($libelle : « Le nom », « Le prénom »). */
    public static function nameError(string $libelle, string $valeur): ?string
    {
        return preg_match(self::MOTIF_NOM, $valeur)
            ? null
            : $libelle . ' doit faire 2 à 60 caractères (lettres, espaces, apostrophes, tirets).';
    }

    /** Téléphone facultatif : vide, ou 6 à 15 chiffres avec « + » initial, espaces, points et tirets acceptés. */
    public static function phoneError(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }
        return preg_match('/^\+?[0-9]{6,15}$/', self::normalizePhone($phone))
            ? null
            : 'Le numéro de téléphone n\'est pas valide (6 à 15 chiffres).';
    }

    /** Mot de passe : 8 caractères minimum. */
    public static function passwordError(string $password): ?string
    {
        return str_length($password) < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : null;
    }

    /** Numéro sans espaces, points ni tirets : « 06 12.34-56 78 » => « 0612345678 ». */
    public static function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/[ .\-]/', '', trim($phone));
    }

    // endregion

    // region Authentification

    /** Vérifie les identifiants (pseudo ou e-mail, et mot de passe) ; retourne l'utilisateur, ou null si l'un est faux. */
    public static function verify(string $login, string $password): ?array
    {
        $user = self::findByLogin($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /** Enregistre la date de dernière connexion. */
    public static function touchLogin(string $id): void
    {
        self::modifier($id, 'derniere_connexion', self::maintenant());
    }

    // endregion

    // region Écriture

    /**
     * Crée un compte (mot de passe haché) et le retourne.
     *
     * @param array $profile last_name, first_name, email, phone : tous facultatifs (comptes de gestion créés à la main).
     */
    public static function create(string $username, string $password, string $role = 'user', array $permissions = [], array $profile = []): array
    {
        $id = bin2hex(random_bytes(6));
        self::executerAction(
            'INSERT INTO utilisateur (id, pseudo, nom, prenom, email, telephone, mot_de_passe, role, droits, cree_le)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $username,
                ($profile['last_name'] ?? '') !== '' ? $profile['last_name'] : null,
                ($profile['first_name'] ?? '') !== '' ? $profile['first_name'] : null,
                ($profile['email'] ?? '') !== '' ? $profile['email'] : null,
                ($profile['phone'] ?? '') !== '' ? self::normalizePhone($profile['phone']) : null,
                password_hash($password, PASSWORD_DEFAULT),
                in_array($role, self::ROLES, true) ? $role : 'user',
                json_encode(Permissions::normalize($permissions)),
                self::maintenant(),
            ]
        );
        return self::find($id);
    }

    /** Change le rôle d'un compte ('user' ou 'admin'), valeurs inconnues ignorées. */
    public static function setRole(string $id, string $role): void
    {
        if (in_array($role, self::ROLES, true)) {
            self::modifier($id, 'role', $role);
        }
    }

    /** Définit les droits d'un compte (les droits de lecture impliqués sont ajoutés). */
    public static function setPermissions(string $id, array $permissions): void
    {
        self::modifier($id, 'droits', json_encode(Permissions::normalize($permissions)));
    }

    /** Change le mot de passe d'un compte (stocké haché). */
    public static function setPassword(string $id, string $password): void
    {
        self::modifier($id, 'mot_de_passe', password_hash($password, PASSWORD_DEFAULT));
    }

    /** Supprime un compte (ses abonnements sont supprimés avec lui : droit à l'effacement). */
    public static function delete(string $id): void
    {
        self::executerAction('DELETE FROM utilisateur WHERE id = ?', [$id]);
    }

    // endregion

    // region Conversions

    /** Version d'un compte sans le mot de passe haché, pour l'API et l'export RGPD. */
    public static function publicView(array $user): array
    {
        unset($user['password_hash']);
        $user['permissions'] = Permissions::of($user);
        return $user;
    }

    /** Un compte dont la colonne $colonne vaut $valeur (la colonne est choisie dans le code, jamais par l'utilisateur). */
    private static function trouver(string $colonne, string $valeur): ?array
    {
        $ligne = self::getUnTuple("SELECT * FROM utilisateur WHERE $colonne = ?", [$valeur]);
        return $ligne ? self::hydrate($ligne) : null;
    }

    /** Met à jour une seule colonne d'un compte. */
    private static function modifier(string $id, string $colonne, mixed $valeur): void
    {
        self::executerAction("UPDATE utilisateur SET $colonne = ? WHERE id = ?", [$valeur, $id]);
    }

    /** Transforme une ligne de la table en tableau aux clés lisibles par les vues. */
    private static function hydrate(array $ligne): array
    {
        return [
            'id'            => $ligne['id'],
            'username'      => $ligne['pseudo'],
            'last_name'     => (string) $ligne['nom'],
            'first_name'    => (string) $ligne['prenom'],
            'email'         => (string) $ligne['email'],
            'phone'         => (string) $ligne['telephone'],
            'password_hash' => $ligne['mot_de_passe'],
            'role'          => $ligne['role'],
            'permissions'   => json_decode($ligne['droits'] ?: '[]', true) ?: [],
            'created_at'    => self::versIso($ligne['cree_le']),
            'last_login'    => self::versIso($ligne['derniere_connexion']),
        ];
    }

    // endregion
}
