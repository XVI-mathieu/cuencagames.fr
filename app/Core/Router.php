<?php
namespace App\Core;

use ReflectionMethod;

/**
 * Routeur : associe une requête à une méthode de contrôleur, de deux façons qui cohabitent.
 *
 *  1. URL propres déclarées dans app/Config/routes.php :  GET /projet/ab12cd34ef56
 *  2. Index réduit (une seule page d'entrée, index.php) :  index.php?controleur=projet&action=show&id=ab12cd34ef56
 *     Seuls les contrôleurs et actions déclarés dans routes.php (méthode reduit) sont joignables.
 */
class Router
{
    // region Données

    /** Routes enregistrées : méthode HTTP, expression régulière et gestionnaire [classe, méthode]. */
    private array $routes = [];

    /** Contrôleurs joignables par l'index réduit : nom => [classe, [action => méthode HTTP]]. */
    private array $reduits = [];

    // endregion

    // region Déclaration des routes (appelée par routes.php)

    /** Déclare une route GET. */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** Déclare une route POST. */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Déclare un contrôleur pour l'index réduit.
     *
     * @param string               $nom     Valeur de ?controleur=, ex. 'panier'.
     * @param string               $classe  Classe du contrôleur.
     * @param array<string,string> $actions Actions autorisées => méthode HTTP exigée, ex. ['index' => 'GET', 'vider' => 'POST'].
     */
    public function reduit(string $nom, string $classe, array $actions): void
    {
        $this->reduits[$nom] = [$classe, $actions];
    }

    /** Enregistre une route ; « {id} » dans l'URL devient un paramètre transmis à la méthode du contrôleur. */
    private function add(string $method, string $path, array $handler): void
    {
        $path = rtrim($path, '/') ?: '/';
        $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>' . self::MOTIF_PARAMETRE . ')', $path) . '$#';
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    // endregion

    // region Aiguillage d'une requête

    /** Caractères autorisés dans un paramètre d'adresse (identifiant) : lettres, chiffres, _ et -. */
    private const MOTIF_PARAMETRE = '[A-Za-z0-9_-]+';

    /**
     * Trouve la route correspondant à la requête et appelle son contrôleur (404 si aucune).
     * Gère les sites installés dans un sous-dossier (ex. /gaming) et l'URL « /index.php ».
     */
    public function dispatch(string $method, string $uri): void
    {
        if (isset($_GET['controleur'])) {
            $this->dispatchReduit($method, (string) $_GET['controleur'], (string) ($_GET['action'] ?? 'index'));
            return;
        }

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $base = base_url();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            // seuls les groupes nommés (« {id} ») deviennent des arguments de la méthode
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        $this->notFound();
    }

    /**
     * Index réduit : index.php?controleur=xxx&action=yyy[&id=zzz].
     * Les arguments de la méthode du contrôleur sont lus (par leur nom) dans l'adresse ou le formulaire.
     */
    private function dispatchReduit(string $method, string $nom, string $action): void
    {
        [$classe, $actions] = $this->reduits[$nom] ?? [null, []];
        if ($classe === null || ($actions[$action] ?? null) !== $method) {
            $this->notFound();
        }

        $arguments = [];
        foreach ((new ReflectionMethod($classe, $action))->getParameters() as $parametre) {
            $valeur = $_GET[$parametre->getName()] ?? $_POST[$parametre->getName()] ?? null;
            if ($valeur === null && !$parametre->isOptional()) {
                $this->notFound();
            }
            if ($valeur !== null) {
                // mêmes caractères autorisés que les paramètres « {id} » des URL propres
                if (!is_string($valeur) || !preg_match('/^' . self::MOTIF_PARAMETRE . '$/', $valeur)) {
                    $this->notFound();
                }
                $arguments[] = $valeur;
            }
        }
        (new $classe())->$action(...$arguments);
    }

    /** Affiche la page 404 (le contrôleur de base sait la rendre). */
    private function notFound(): never
    {
        (new Controller())->notFound();
    }

    // endregion
}
