<?php
namespace App\Models;

use App\Core\ModelePDO;
use App\Core\Panier;

/**
 * Modèle « Abonnement » : formules payantes (Essentiel, Premium, Ultime) et abonnements souscrits par les joueurs.
 * Tout passe par les procédures et fonctions stockées de la base (voir database/schema.sql) :
 * le prix, le nom et la date de fin d'une souscription sont fixés par un trigger, jamais par PHP.
 *
 * Principe d'accès : un jeu demande un niveau (0 = gratuit pour tous) ; un joueur accède aux jeux dont le niveau
 * est inférieur ou égal au niveau de son abonnement en cours.
 */
class Abonnement extends ModelePDO
{
    // region Constantes

    /** Niveau de la formule la plus haute (le trigger de la base utilise le maximum réel de la table abonnement). */
    public const NIVEAU_MAX = 3;

    // endregion

    // region Formules proposées

    /**
     * Formules d'abonnement, de la moins chère à la plus chère.
     *
     * @param bool $seulementActives Vrai : uniquement celles encore proposées aux nouveaux abonnés.
     */
    public static function plans(bool $seulementActives = true): array
    {
        $sql = 'SELECT * FROM abonnement' . ($seulementActives ? ' WHERE actif = 1' : '') . ' ORDER BY niveau';
        return array_map(self::hydraterPlan(...), self::getLesTuples($sql));
    }

    /**
     * Prix d'une offre (formule + durée en mois), remise de fidélité comprise : fonction stockée fn_prix_offre.
     * Retourne null si la formule n'existe pas ou si la durée n'est pas proposée.
     */
    public static function prixOffre(int $idAbonnement, int $dureeMois): ?float
    {
        $prix = self::appelerFonction('fn_prix_offre', [$idAbonnement, $dureeMois]);
        return $prix === null ? null : (float) $prix;
    }

    /**
     * Les offres d'une formule, une par durée proposée (1, 3, 6, 12 mois), avec leur prix et leur remise.
     *
     * @return array<int,array{months:int,label:string,price:float,discount:int}>
     */
    public static function offres(array $plan): array
    {
        $offres = [];
        foreach (Panier::DUREES as $mois) {
            $prix = self::prixOffre($plan['id'], $mois);
            $sansRemise = $plan['price'] * $mois;
            $offres[] = [
                'months'   => $mois,
                'label'    => Panier::libelleDuree($mois),
                'price'    => (float) $prix,
                'discount' => $sansRemise > 0 ? (int) round((1 - $prix / $sansRemise) * 100) : 0,
            ];
        }
        return $offres;
    }

    /** Une formule par son identifiant (null si elle n'existe pas). */
    public static function find(int $id): ?array
    {
        $ligne = self::getUnTuple('SELECT * FROM abonnement WHERE id = ?', [$id]);
        return $ligne ? self::hydraterPlan($ligne) : null;
    }

    /**
     * Libellés des niveaux : [0 => 'Gratuit', 1 => 'Essentiel', 2 => 'Premium', 3 => 'Ultime'].
     * Sert à afficher le niveau demandé par un jeu.
     */
    public static function libellesNiveaux(): array
    {
        $libelles = [0 => 'Gratuit'];
        foreach (self::plans(false) as $plan) {
            $libelles[$plan['level']] = $plan['name'];
        }
        return $libelles;
    }

    /** Jeux accessibles avec une formule (procédure de consultation sp_jeux_abonnement). */
    public static function jeuxInclus(int $idAbonnement): array
    {
        return array_map(
            static fn (array $ligne): array => [
                'id' => $ligne['id'], 'name' => $ligne['nom'], 'category' => $ligne['code_categorie'], 'level' => (int) $ligne['niveau_acces'],
            ],
            self::appelerProcedure('sp_jeux_abonnement', [$idAbonnement])
        );
    }

    // endregion

    // region Abonnement d'un joueur

    /** Niveau d'accès d'un joueur : 0 sans abonnement en cours (fonction stockée fn_niveau_utilisateur). */
    public static function niveauUtilisateur(string $idUtilisateur): int
    {
        return (int) self::appelerFonction('fn_niveau_utilisateur', [$idUtilisateur]);
    }

    /** Abonnement en cours d'un joueur (procédure sp_abonnement_actuel), ou null s'il n'en a pas. */
    public static function actuel(string $idUtilisateur): ?array
    {
        $lignes = self::appelerProcedure('sp_abonnement_actuel', [$idUtilisateur]);
        return $lignes ? self::hydraterSouscription($lignes[0], $lignes[0]['statut']) : null;
    }

    /** Historique des abonnements d'un joueur, du plus récent au plus ancien (procédure sp_souscriptions_utilisateur). */
    public static function historique(string $idUtilisateur): array
    {
        return array_map(
            static fn (array $ligne): array => self::hydraterSouscription($ligne, $ligne['etat']),
            self::appelerProcedure('sp_souscriptions_utilisateur', [$idUtilisateur])
        );
    }

    // endregion

    // region Souscription et résiliation

    /**
     * Valide un panier : crée la commande (sp_creer_commande), y ajoute une ligne par offre (sp_ajouter_ligne_commande),
     * puis la valide (sp_valider_commande), ce qui souscrit l'abonnement et remplace celui en cours.
     * Le tout est une transaction : si la base refuse une règle (trigger, SQLSTATE 45000), rien n'est enregistré.
     *
     * @param array<string,int> $lignes « dureeMois » => quantité
     * @return int Numéro de la commande.
     */
    public static function commander(string $idUtilisateur, int $idAbonnement, array $lignes): int
    {
        return (int) self::transaction(function () use ($idUtilisateur, $idAbonnement, $lignes): int {
            // procédures d'action avec paramètre de sortie (@id_commande), relu juste après
            self::executerRequete('CALL sp_creer_commande(?, ?, @id_commande)', [$idUtilisateur, $idAbonnement])->closeCursor();
            $idCommande = (int) self::getUneValeur('SELECT @id_commande');

            foreach ($lignes as $dureeMois => $quantite) {
                self::appelerProcedureAction('sp_ajouter_ligne_commande', [$idCommande, (int) $dureeMois, (int) $quantite]);
            }

            self::executerRequete('CALL sp_valider_commande(?, @id_souscription)', [$idCommande])->closeCursor();
            return $idCommande;
        });
    }

    /** Commandes validées d'un joueur, de la plus récente à la plus ancienne (procédure sp_commandes_utilisateur). */
    public static function commandes(string $idUtilisateur): array
    {
        return array_map(
            static fn (array $ligne): array => [
                'id' => (int) $ligne['id'], 'name' => $ligne['nom_abonnement'], 'months' => (int) $ligne['mois_total'],
                'total' => (float) $ligne['total'], 'date' => self::versIso($ligne['cree_le']),
            ],
            self::appelerProcedure('sp_commandes_utilisateur', [$idUtilisateur])
        );
    }

    /** Résilie l'abonnement en cours (procédure sp_resilier) : pas de renouvellement, accès jusqu'à la date de fin. */
    public static function resilier(string $idUtilisateur): void
    {
        self::appelerProcedureAction('sp_resilier', [$idUtilisateur]);
    }

    // endregion

    // region Conversions

    /** Ligne de la table abonnement -> tableau aux clés lisibles par les vues. */
    private static function hydraterPlan(array $ligne): array
    {
        return [
            'id'          => (int) $ligne['id'],
            'code'        => $ligne['code'],
            'name'        => $ligne['nom'],
            'level'       => (int) $ligne['niveau'],
            'price'       => (float) $ligne['prix_mensuel'],
            'description' => $ligne['description'],
            'active'      => (bool) $ligne['actif'],
        ];
    }

    /**
     * Ligne de souscription -> tableau aux clés lisibles par les vues.
     *
     * @param string $etat 'active', 'resiliee' ou 'terminee' (la procédure de l'historique tient compte de la date de fin).
     */
    private static function hydraterSouscription(array $ligne, string $etat): array
    {
        return [
            'id'     => (int) $ligne['id'],
            'name'   => $ligne['nom_abonnement'],
            'level'  => (int) $ligne['niveau'],
            'price'  => (float) $ligne['prix'],
            'months' => (int) $ligne['duree_mois'],
            'start'  => self::versIso($ligne['date_debut']),
            'end'    => self::versIso($ligne['date_fin']),
            'status' => $etat,
        ];
    }

    // endregion
}
