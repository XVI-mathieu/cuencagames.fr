<?php
namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Classe mère de tous les modèles : une seule connexion PDO partagée et les méthodes d'accès à la base.
 * Les modèles (Project, User, Commande…) en héritent et n'écrivent plus que leurs requêtes.
 *
 * Toutes les requêtes sont préparées (paramètres liés) : aucune valeur n'est collée dans le SQL.
 */
abstract class ModelePDO
{
    // region Connexion

    /** Connexion unique, créée à la première requête. */
    private static ?PDO $pdo = null;

    /** Connexion PDO (paramètres lus dans la section « db » de la configuration, voir Config). */
    protected static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $db = Config::get('db', []);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['hote'] ?? '127.0.0.1',
                (int) ($db['port'] ?? 3306),
                $db['nom'] ?? ''
            );
            self::$pdo = new PDO($dsn, (string) ($db['utilisateur'] ?? ''), (string) ($db['mot_de_passe'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // toute erreur SQL lève une exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // lignes sous forme de tableaux associatifs
                PDO::ATTR_EMULATE_PREPARES   => false,                    // vraies requêtes préparées côté serveur
            ]);
            // Dates de la base alignées sur le fuseau du site (le serveur MySQL peut être en UTC)
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    // endregion

    // region Requêtes de lecture

    /** Prépare et exécute une requête, puis retourne le résultat brut. */
    protected static function executerRequete(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute(array_values($params));
        return $statement;
    }

    /** SELECT : toutes les lignes du résultat (tableau de tableaux associatifs). */
    protected static function getLesTuples(string $sql, array $params = []): array
    {
        return self::executerRequete($sql, $params)->fetchAll();
    }

    /** SELECT : la première ligne du résultat, ou null s'il n'y en a aucune. */
    protected static function getUnTuple(string $sql, array $params = []): ?array
    {
        $ligne = self::executerRequete($sql, $params)->fetch();
        return $ligne === false ? null : $ligne;
    }

    /** SELECT : la première colonne de la première ligne (un COUNT, une fonction…), ou null. */
    protected static function getUneValeur(string $sql, array $params = []): mixed
    {
        $valeur = self::executerRequete($sql, $params)->fetchColumn();
        return $valeur === false ? null : $valeur;
    }

    // endregion

    // region Requêtes d'écriture

    /** INSERT / UPDATE / DELETE : retourne le nombre de lignes touchées. */
    protected static function executerAction(string $sql, array $params = []): int
    {
        return self::executerRequete($sql, $params)->rowCount();
    }

    /** Exécute $operation dans une transaction : validée si tout réussit, annulée à la première erreur. */
    protected static function transaction(callable $operation): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultat = $operation();
            $pdo->commit();
            return $resultat;
        } catch (Throwable $erreur) {
            $pdo->rollBack();
            throw $erreur;
        }
    }

    // endregion

    // region Procédures et fonctions stockées

    /**
     * Appelle une PROCÉDURE stockée de consultation et retourne les lignes de son résultat.
     * Ex. appelerProcedure('sp_commandes_utilisateur', [$idUtilisateur]).
     */
    protected static function appelerProcedure(string $nom, array $params = []): array
    {
        $statement = self::executerRequete(self::appel('CALL', $nom, count($params)), $params);
        $lignes = $statement->fetchAll();
        $statement->closeCursor(); // une procédure renvoie un second résultat « vide » qu'il faut libérer
        return $lignes;
    }

    /** Appelle une PROCÉDURE stockée d'action (INSERT / UPDATE…) qui ne renvoie pas de lignes. */
    protected static function appelerProcedureAction(string $nom, array $params = []): void
    {
        self::executerRequete(self::appel('CALL', $nom, count($params)), $params)->closeCursor();
    }

    /** Appelle une FONCTION stockée et retourne sa valeur. Ex. appelerFonction('fn_total_commande', [$id]). */
    protected static function appelerFonction(string $nom, array $params = []): mixed
    {
        return self::getUneValeur(self::appel('SELECT', $nom, count($params)), $params);
    }

    /** Construit « CALL nom(?,?) » ou « SELECT nom(?,?) » (le nom ne vient jamais de l'utilisateur). */
    private static function appel(string $verbe, string $nom, int $nbParametres): string
    {
        $marques = implode(', ', array_fill(0, $nbParametres, '?'));
        return $verbe === 'CALL' ? "CALL $nom($marques)" : "SELECT $nom($marques)";
    }

    // endregion

    // region Conversions de dates

    /** Date de la base (« 2026-10-19 08:30:00 ») au format ISO 8601 utilisé par les vues et l'API ; null reste null. */
    protected static function versIso(?string $dateBase): ?string
    {
        return $dateBase ? date('c', strtotime($dateBase)) : null;
    }

    /** Date et heure actuelles au format de la base (fuseau du site). */
    protected static function maintenant(): string
    {
        return date('Y-m-d H:i:s');
    }

    // endregion
}
