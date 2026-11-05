<?php
namespace App\Core;

/**
 * Protection contre les requêtes forgées (CSRF) : chaque formulaire POST porte un jeton secret, propre à la session,
 * qu'un site pirate ne peut pas deviner.
 */
class Csrf
{
    // region Jeton

    /** Jeton CSRF de la session (créé au premier appel), à placer dans chaque formulaire POST (voir csrf_field()). */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Vérifie, en temps constant, que le jeton reçu avec le formulaire est celui de la session. */
    public static function check(string $token): bool
    {
        return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    // endregion
}
