<?php
/**
 * POINT D'ENTRÉE UNIQUE du site (« index réduit » / front controller).
 *
 * C'est le seul fichier de code à la racine : toute requête arrive ici, puis est envoyée au bon contrôleur
 * (voir app/Core/Router.php). Les deux formes d'adresse sont acceptées :
 *   - URL propre  : /projet/ab12cd34ef56
 *   - index réduit : index.php?controleur=projet&action=show&id=ab12cd34ef56
 *
 * Tout le reste du code est rangé dans les dossiers décrits dans README.md.
 */
declare(strict_types=1);

// region Constantes

/** Dossier racine du site : tous les autres chemins (app, storage, assets…) en sont déduits. */
define('ROOT', __DIR__);

// endregion

// region Serveur de développement (php -S)

// Le serveur intégré de PHP ne lit pas le .htaccess : on reproduit ici ses deux règles.
if (PHP_SAPI === 'cli-server') {
    $chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

    // 1. Dossiers internes jamais servis (code, données, scripts, base de données)
    if (preg_match('#^/(app|storage|database|scripts)(/|$)#', $chemin)) {
        http_response_code(403);
        exit('Accès interdit.');
    }
    // 2. Fichiers statiques (CSS, images…) servis tels quels ; index.php reste traité par ce script
    if (is_file(ROOT . $chemin) && basename($chemin) !== 'index.php') {
        return false;
    }
}

// endregion

// region Démarrage de l'application, puis aiguillage de la requête

require ROOT . '/app/Core/bootstrap.php';

$router = new App\Core\Router();
require ROOT . '/app/Config/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

// endregion
