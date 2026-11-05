<?php
/**
 * Démarrage de l'application : chargement automatique des classes, fonctions utilitaires, fuseau horaire,
 * gestion des erreurs imprévues et session. Appelé une seule fois par index.php.
 */

// region Chargement automatique des classes

/**
 * Charge une classe à la demande : App\Controllers\HomeController -> app/Controllers/HomeController.php.
 * Une classe = un fichier, et le namespace reflète le dossier.
 */
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';
    if (strncmp($classe, $prefixe, strlen($prefixe)) !== 0) {
        return; // classe d'une bibliothèque externe : pas la nôtre
    }
    $chemin = ROOT . '/app/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});

require ROOT . '/app/Core/helpers.php';

// endregion

// region Réglages généraux

date_default_timezone_set(App\Core\Config::get('timezone', 'Europe/Paris'));

// endregion

// region Erreurs imprévues

/**
 * Erreur imprévue (base injoignable, bug…) : message neutre pour le visiteur, détail dans le journal du serveur.
 * Jamais d'identifiants ni de requête SQL affichés à l'écran.
 */
set_exception_handler(function (Throwable $erreur): void {
    error_log(get_class($erreur) . ' : ' . $erreur->getMessage() . ' (' . $erreur->getFile() . ':' . $erreur->getLine() . ')');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="fr"><meta charset="UTF-8"><title>Erreur</title>'
        . '<body style="font-family:system-ui;background:#07080f;color:#e8ecff;text-align:center;padding:15vh 20px">'
        . '<h1>Service momentanément indisponible</h1><p>Une erreur est survenue. Réessayez dans quelques instants.</p></body></html>';
});

// endregion

// region Session

session_start([
    'cookie_httponly' => true,                                                          // cookie invisible pour JavaScript
    'cookie_samesite' => 'Lax',                                                         // non envoyé par les sites tiers
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',      // HTTPS seulement si le site l'est
    'use_strict_mode' => true,                                                          // refuse un identifiant de session inventé
]);

// endregion
