<?php
namespace App\Core;

/**
 * Panier d'abonnement, conservé en SESSION (il disparaît à la fin de la session, comme un panier de magasin).
 *
 * Une ligne = une offre (formule + durée en mois) et le nombre d'exemplaires voulus (ex. « 2 x 3 mois »).
 * Le panier ne contient que des couples « idFormule-dureeMois => quantité » : les prix ne sont JAMAIS stockés ici,
 * ils sont relus en base à chaque affichage (fonction stockée fn_prix_offre).
 *
 * Un abonnement porte sur UNE seule formule : ajouter une autre formule remplace le contenu du panier.
 * La classe est statique (pas d'objet à créer) ; l'affichage et les actions sont dans PanierController.
 */
class Panier
{
    // region Constantes

    /** Durées proposées, en mois (doivent rester identiques à celles de la fonction stockée fn_prix_offre). */
    public const DUREES = [1, 3, 6, 12];

    /** Nombre maximal d'exemplaires d'une même durée. */
    public const QUANTITE_MAX = 12;

    /** Durée totale maximale d'une commande, en mois (contrôlée aussi par le trigger trg_ligne_commande_bi). */
    public const MOIS_MAX = 36;

    // endregion

    // region Lecture

    /**
     * Contenu du panier.
     *
     * @return array<string,int> « idFormule-dureeMois » => quantité
     */
    public static function getContenu(): array
    {
        if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }
        return $_SESSION['panier'];
    }

    /** Nombre de lignes du panier (affiché à côté de l'icône du panier). */
    public static function nbLignes(): int
    {
        return count(self::getContenu());
    }

    /** Le panier est-il vide ? */
    public static function estVide(): bool
    {
        return self::nbLignes() === 0;
    }

    /** Clé d'une ligne : cle(2, 3) => « 2-3 » (formule 2, durée 3 mois). */
    public static function cle(int $idFormule, int $dureeMois): string
    {
        return $idFormule . '-' . $dureeMois;
    }

    /**
     * Décompose une clé de ligne.
     *
     * @return array{0:int,1:int}|null [idFormule, dureeMois], ou null si la clé est invalide
     */
    public static function decouperCle(string $cle): ?array
    {
        return preg_match('/^(\d+)-(\d+)$/', $cle, $m) ? [(int) $m[1], (int) $m[2]] : null;
    }

    /** Durée totale du panier, en mois (somme de durée x quantité). */
    public static function totalMois(): int
    {
        $total = 0;
        foreach (self::getContenu() as $cle => $quantite) {
            $partie = self::decouperCle((string) $cle);
            $total += $partie ? $partie[1] * $quantite : 0;
        }
        return $total;
    }

    /** Identifiant de la formule du panier (null si le panier est vide). */
    public static function idFormule(): ?int
    {
        foreach (array_keys(self::getContenu()) as $cle) {
            $partie = self::decouperCle((string) $cle);
            if ($partie) {
                return $partie[0];
            }
        }
        return null;
    }

    /** Libellé d'une durée : 1 => « 1 mois », 12 => « 12 mois (1 an) ». */
    public static function libelleDuree(int $dureeMois): string
    {
        return $dureeMois . ' mois' . ($dureeMois === 12 ? ' (1 an)' : '');
    }

    // endregion

    // region Modification

    /**
     * Ajoute une offre au panier.
     *
     * @return string 'ok', 'remplace' (le panier contenait une autre formule : il a été vidé)
     *                ou 'limite' (la durée totale maximale serait dépassée : rien n'est ajouté)
     */
    public static function ajouter(int $idFormule, int $dureeMois, int $quantite = 1): string
    {
        $resultat = 'ok';
        $autreFormule = self::idFormule();
        if ($autreFormule !== null && $autreFormule !== $idFormule) {
            $_SESSION['panier'] = [];
            $resultat = 'remplace';
        }

        $cle = self::cle($idFormule, $dureeMois);
        $avant = self::getContenu()[$cle] ?? null;
        $_SESSION['panier'][$cle] = min(self::QUANTITE_MAX, ($avant ?? 0) + max(1, $quantite));

        if (self::totalMois() > self::MOIS_MAX) {
            // on annule cet ajout : la ligne retrouve sa quantité précédente (ou disparaît)
            if ($avant === null) {
                unset($_SESSION['panier'][$cle]);
            } else {
                $_SESSION['panier'][$cle] = $avant;
            }
            return 'limite';
        }
        return $resultat;
    }

    /**
     * Fixe la quantité d'une ligne (0 ou moins : la ligne est retirée).
     *
     * @return bool false si la durée totale maximale serait dépassée (la ligne reste alors inchangée)
     */
    public static function modifier(string $cle, int $quantite): bool
    {
        if ($quantite <= 0) {
            self::supprimer($cle);
            return true;
        }
        if (!isset(self::getContenu()[$cle])) {
            return true;
        }
        $avant = $_SESSION['panier'][$cle];
        $_SESSION['panier'][$cle] = min(self::QUANTITE_MAX, $quantite);
        if (self::totalMois() > self::MOIS_MAX) {
            $_SESSION['panier'][$cle] = $avant;
            return false;
        }
        return true;
    }

    /** Retire une ligne du panier. */
    public static function supprimer(string $cle): void
    {
        self::getContenu();
        unset($_SESSION['panier'][$cle]);
    }

    /** Vide entièrement le panier. */
    public static function vider(): void
    {
        $_SESSION['panier'] = [];
    }

    // endregion
}
