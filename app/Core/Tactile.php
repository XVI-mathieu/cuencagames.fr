<?php
namespace App\Core;

/**
 * Rend les jeux en version navigateur jouables sur téléphone et tablette, sans modifier leurs fichiers :
 * au moment de servir la page, on ajoute (si besoin) la balise « viewport » et le script de commandes tactiles
 * (assets/js/tactile.js) réglé par le profil du jeu (app/Config/tactile.php).
 */
class Tactile
{
    // region Injection dans la page d'un jeu

    /**
     * Retourne la page du jeu prête pour le tactile.
     *
     * @param string $html Page du jeu telle qu'enregistrée.
     * @param string $nom  Nom du jeu (sert à choisir son profil de commandes).
     */
    public static function inject(string $html, string $nom): string
    {
        $html = self::addViewport($html);
        // Les jeux du noyau « Cuenca Games » gèrent eux-mêmes le tactile : le script universel serait redondant.
        if (str_contains($html, 'const CG = (() =>')) {
            return $html;
        }

        $profil = self::profile($nom);
        $version = (int) @filemtime(ROOT . '/assets/js/tactile.js');
        $snippet = '<script>window.CG_TACTILE=' . json_encode($profil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>'
            . '<script src="' . url('/assets/js/tactile.js') . '?v=' . $version . '"></script>';

        $pos = strripos($html, '</body>');
        return $pos === false ? $html . $snippet : substr($html, 0, $pos) . $snippet . substr($html, $pos);
    }

    // endregion

    // region Profil et balise viewport

    /** Profil de commandes d'un jeu : celui de son nom, sinon le profil par défaut. */
    public static function profile(string $nom): array
    {
        $profils = require ROOT . '/app/Config/tactile.php';
        return $profils[mb_strtolower(trim($nom))] ?? $profils['_defaut'];
    }

    /** Ajoute la balise viewport si le jeu n'en a pas (sans elle, le téléphone affiche la page en « bureau » minuscule). */
    private static function addViewport(string $html): string
    {
        if (preg_match('/<meta[^>]+name\s*=\s*["\']viewport["\']/i', $html)) {
            return $html;
        }
        $meta = '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
        if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $fin = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $fin) . $meta . substr($html, $fin);
        }
        return $meta . $html;
    }

    // endregion
}
