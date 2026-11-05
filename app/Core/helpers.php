<?php
/**
 * Petites fonctions utilitaires disponibles partout (contrôleurs, modèles et vues).
 * Chargé par bootstrap.php.
 */
use App\Core\Config;
use App\Core\Csrf;

// region Adresses (URL)

/**
 * Chemin de base du site : vide quand il est à la racine du domaine, « /sous-dossier » sinon.
 * Déduit une seule fois de l'emplacement de index.php.
 */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $base = PHP_SAPI === 'cli-server'
            ? '' // php -S -t <racine> : le site est toujours à la racine
            : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $base;
}

/** Adresse complète d'une page ou d'un fichier du site : url('/panier') => « /panier » (ou « /sous-dossier/panier »). */
function url(string $chemin = '/'): string
{
    return base_url() . '/' . ltrim($chemin, '/');
}

/**
 * Adresse de l'index réduit : url_reduit('panier') => « /index.php?controleur=panier&action=index ».
 * Voir app/Core/Router.php.
 *
 * @param array<string,string|int> $parametres Paramètres supplémentaires, ex. ['id' => 'ab12'].
 */
function url_reduit(string $controleur, string $action = 'index', array $parametres = []): string
{
    return url('/index.php') . '?' . http_build_query(['controleur' => $controleur, 'action' => $action] + $parametres);
}

// endregion

// region Sécurité et messages

/** Échappe une valeur avant de l'écrire dans le HTML (protection contre l'injection de code, « XSS »). */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

/** Champ caché contenant le jeton CSRF : à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Mémorise un message (« success » ou « error ») pour l'afficher à la prochaine page. */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Retourne les messages mémorisés par {@see flash()} et les efface (ils ne s'affichent qu'une fois). */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

// endregion

// region Mise en forme

/** Taille lisible : 1536 => « 1,5 Ko ». */
function format_size(int $octets): string
{
    $unites = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    $taille = (float) $octets;
    while ($taille >= 1024 && $i < count($unites) - 1) {
        $taille /= 1024;
        $i++;
    }
    return number_format($taille, $i === 0 ? 0 : 1, ',', ' ') . ' ' . $unites[$i];
}

/** Prix lisible : 9.9 => « 9,90 € » (boutique de démonstration, prix fictifs). */
function format_price(float $montant): string
{
    return number_format($montant, 2, ',', ' ') . ' ' . Config::get('devise', '€');
}

/** Date au format jj/mm/aaaa (« — » si vide). */
function format_date(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '—';
}

/** Longueur d'une chaîne en caractères (UTF-8), sans dépendre de l'extension mbstring. */
function str_length(string $texte): int
{
    return (int) preg_match_all('/./us', $texte);
}

/** Début d'un texte (sur une ligne), coupé proprement avec « … » s'il dépasse $longueur caractères. */
function excerpt(string $texte, int $longueur = 140): string
{
    $texte = trim((string) preg_replace('/\s+/u', ' ', $texte));
    if (preg_match('/^.{' . $longueur . '}/us', $texte, $debut) && strlen($debut[0]) < strlen($texte)) {
        return rtrim($debut[0]) . '…';
    }
    return $texte;
}

// endregion

// region Aspect des projets

/** Première lettre du nom, affichée dans l'icône d'un projet. */
function initial(string $nom): string
{
    return preg_match('/[\p{L}\p{N}]/u', $nom, $lettre) ? $lettre[0] : '?';
}

/** Teinte de couleur (0 à 359) stable, dérivée de l'identifiant du projet : un même jeu garde toujours la même couleur. */
function hue(string $id): int
{
    return crc32($id) % 360;
}

// endregion
