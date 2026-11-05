<?php
namespace App\Core;

/**
 * Accès à la configuration du site, chargée une seule fois (au premier appel de {@see Config::get()}).
 *
 * Trois sources, la suivante complétant ou remplaçant la précédente :
 *  1. app/Config/config.php        réglages communs, versionnés
 *  2. app/Config/config.local.php  réglages propres à une machine ou à l'hébergeur (identifiants de la base) ;
 *                                  facultatif et jamais versionné (voir .gitignore)
 *  3. variables d'environnement DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (tests et déploiements automatisés)
 */
class Config
{
    // region Données

    /** Réglages déjà chargés (null tant que le premier appel n'a pas eu lieu). */
    private static ?array $reglages = null;

    // endregion

    // region Lecture

    /**
     * Valeur d'un réglage.
     *
     * @param string $cle     Nom du réglage (ex. 'site_name').
     * @param mixed  $defaut  Valeur renvoyée si le réglage n'existe pas.
     */
    public static function get(string $cle, mixed $defaut = null): mixed
    {
        self::$reglages ??= self::charger();
        return self::$reglages[$cle] ?? $defaut;
    }

    // endregion

    // region Chargement

    /** Lit config.php, y superpose config.local.php, puis les variables d'environnement de la base. */
    private static function charger(): array
    {
        $reglages = require ROOT . '/app/Config/config.php';

        $local = ROOT . '/app/Config/config.local.php';
        if (is_file($local)) {
            $reglages = array_replace_recursive($reglages, require $local);
        }

        $environnement = [
            'hote'         => getenv('DB_HOST'),
            'port'         => getenv('DB_PORT'),
            'nom'          => getenv('DB_NAME'),
            'utilisateur'  => getenv('DB_USER'),
            'mot_de_passe' => getenv('DB_PASS'),
        ];
        // getenv() renvoie false si la variable n'existe pas : on ne garde que celles qui sont définies
        $definies = array_filter($environnement, fn ($valeur) => $valeur !== false && $valeur !== '');
        $reglages['db'] = array_replace($reglages['db'] ?? [], $definies);

        return $reglages;
    }

    // endregion
}
