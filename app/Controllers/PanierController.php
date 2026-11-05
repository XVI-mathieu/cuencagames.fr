<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Panier;
use App\Models\Abonnement;
use PDOException;

/**
 * Panier d'abonnement : affichage, ajout d'une offre, modification des quantités, suppression d'une ligne,
 * vidage et validation de la commande. Le panier lui-même (stockage en session) est géré par la classe Panier ;
 * les prix sont relus en base (Abonnement::prixOffre) à chaque affichage.
 *
 * Boutique de démonstration : aucun paiement réel, les prix sont fictifs.
 */
class PanierController extends Controller
{
    // region Affichage

    /** Affiche le panier : une ligne par offre, sous-totaux et total (tarifs relus en base). */
    public function index(): void
    {
        $lignes = $this->construireLignes();
        $total = 0.0;
        foreach ($lignes as $ligne) {
            $total += $ligne['subtotal'];
        }

        $plan = ($id = Panier::idFormule()) !== null ? Abonnement::find($id) : null;

        $this->render('panier/index', [
            'title'       => 'Mon panier',
            'lignes'      => $lignes,
            'plan'        => $plan,
            'total'       => $total,
            'mois'        => Panier::totalMois(),
            'quantiteMax' => Panier::QUANTITE_MAX,
            'moisMax'     => Panier::MOIS_MAX,
        ]);
    }

    // endregion

    // region Modification du panier

    /** Ajoute une offre au panier (POST : formule, duree, quantite). */
    public function ajouter(): void
    {
        $this->verifyCsrf();

        $plan = Abonnement::find((int) ($_POST['formule'] ?? 0));
        $duree = (int) ($_POST['duree'] ?? 0);
        $quantite = max(1, (int) ($_POST['quantite'] ?? 1));

        if (!$plan || !$plan['active'] || !in_array($duree, Panier::DUREES, true)) {
            flash('error', 'Cette offre n\'existe pas ou n\'est plus proposée.');
            $this->redirect('/abonnements');
        }

        $resultat = Panier::ajouter($plan['id'], $duree, $quantite);
        if ($resultat === 'limite') {
            flash('error', 'Une commande ne peut pas dépasser ' . Panier::MOIS_MAX . ' mois d\'abonnement.');
            $this->redirect('/panier');
        }

        $message = $plan['name'] . ' (' . Panier::libelleDuree($duree) . ') ajouté au panier.';
        if ($resultat === 'remplace') {
            $message .= ' Votre panier contenait une autre formule : il a été remplacé (un abonnement porte sur une seule formule).';
        }
        flash('success', $message);
        $this->redirect('/panier');
    }

    /** Met à jour les quantités (POST : quantites[cle] = nombre). Une quantité de 0 retire la ligne. */
    public function modifier(): void
    {
        $this->verifyCsrf();

        $refuse = false;
        $quantites = $_POST['quantites'] ?? [];
        if (is_array($quantites)) {
            foreach ($quantites as $cle => $quantite) {
                if (Panier::decouperCle((string) $cle) !== null && !Panier::modifier((string) $cle, (int) $quantite)) {
                    $refuse = true;
                }
            }
        }

        if ($refuse) {
            flash('error', 'Une commande ne peut pas dépasser ' . Panier::MOIS_MAX . ' mois d\'abonnement.');
        } else {
            flash('success', 'Votre panier a été mis à jour.');
        }
        $this->redirect('/panier');
    }

    /** Retire une ligne du panier (POST : cle). */
    public function supprimer(): void
    {
        $this->verifyCsrf();
        Panier::supprimer((string) ($_POST['cle'] ?? ''));
        flash('success', 'L\'offre a été retirée du panier.');
        $this->redirect('/panier');
    }

    /** Vide entièrement le panier. */
    public function vider(): void
    {
        $this->verifyCsrf();
        Panier::vider();
        flash('success', 'Votre panier a été vidé.');
        $this->redirect('/panier');
    }

    // endregion

    // region Validation de la commande

    /**
     * Valide le panier (connexion obligatoire) : la commande est enregistrée et l'abonnement souscrit.
     * Si la base refuse une règle métier (trigger, SQLSTATE 45000), son message est affiché et le panier est conservé.
     */
    public function valider(): void
    {
        $this->verifyCsrf();
        $user = $this->requireLogin('Connectez-vous (ou créez un compte) pour valider votre commande.');

        $lignes = $this->construireLignes();
        $plan = ($id = Panier::idFormule()) !== null ? Abonnement::find($id) : null;
        if (!$lignes || !$plan || !$plan['active']) {
            flash('error', 'Votre panier est vide.');
            $this->redirect('/panier');
        }

        // « dureeMois » => quantité, dans l'ordre du panier
        $aCommander = [];
        foreach ($lignes as $ligne) {
            $aCommander[$ligne['months']] = $ligne['quantity'];
        }

        try {
            $numero = Abonnement::commander($user['id'], $plan['id'], $aCommander);
        } catch (PDOException $erreur) {
            // SQLSTATE 45000 = règle métier refusée par un trigger (message prévu pour l'utilisateur)
            $refus = ($erreur->errorInfo[0] ?? '') === '45000' ? (string) ($erreur->errorInfo[2] ?? '') : '';
            flash('error', $refus !== '' ? 'Commande refusée : ' . $refus : 'La commande n\'a pas pu être enregistrée. Réessayez plus tard.');
            $this->redirect('/panier');
        }

        Panier::vider();
        flash('success', 'Commande n° ' . $numero . ' validée : bienvenue dans la formule ' . $plan['name'] . ' ! Vos jeux sont débloqués.');
        $this->redirect('/abonnements');
    }

    // endregion

    // region Préparation des lignes

    /**
     * Prépare les lignes du panier pour la vue : formule, durée, quantité, prix unitaire relu en base et sous-total.
     * Les offres qui n'existent plus (formule retirée, durée supprimée) sont retirées du panier.
     *
     * @return array<int,array{key:string,name:string,months:int,label:string,quantity:int,price:float,subtotal:float}>
     */
    private function construireLignes(): array
    {
        $lignes = [];
        foreach (Panier::getContenu() as $cle => $quantite) {
            $partie = Panier::decouperCle((string) $cle);
            $plan = $partie ? Abonnement::find($partie[0]) : null;
            $prix = $plan && $plan['active'] ? Abonnement::prixOffre($partie[0], $partie[1]) : null;
            if ($prix === null) {
                Panier::supprimer((string) $cle);
                continue;
            }
            $lignes[] = [
                'key'      => (string) $cle,
                'name'     => $plan['name'],
                'months'   => $partie[1],
                'label'    => Panier::libelleDuree($partie[1]),
                'quantity' => $quantite,
                'price'    => $prix,
                'subtotal' => $prix * $quantite,
            ];
        }
        return $lignes;
    }

    // endregion
}
