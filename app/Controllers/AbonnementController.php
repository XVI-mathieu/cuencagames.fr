<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Panier;
use App\Models\Abonnement;
use App\Models\Project;

/**
 * Abonnements : page des formules (Essentiel, Premium, Ultime) avec leurs offres (durées), résiliation et historique du joueur.
 * L'achat passe par le panier (PanierController).
 * Boutique de démonstration : aucun paiement réel, les prix sont fictifs.
 */
class AbonnementController extends Controller
{
    // region Affichage

    /** Page des formules : jeux inclus dans chacune, abonnement en cours et historique du joueur connecté. */
    public function index(): void
    {
        $user = Auth::user();
        $plans = Abonnement::plans();
        // pour chaque formule, la liste des jeux auxquels elle donne accès (procédure sp_jeux_abonnement)
        foreach ($plans as &$plan) {
            $plan['games'] = Abonnement::jeuxInclus($plan['id']);
            $plan['offers'] = Abonnement::offres($plan);   // une offre par durée (1, 3, 6, 12 mois), prix calculés par la base
        }
        unset($plan);

        $this->render('abonnements/index', [
            'title'      => 'Abonnements',
            'plans'      => $plans,
            'niveau'     => Auth::subscriptionLevel(),   // formule réellement souscrite (un compte de gestion joue à tout sans elle)
            'complet'    => Auth::hasFullAccess(),       // compte de gestion : accès à tous les jeux
            'actuel'     => $user ? Abonnement::actuel($user['id']) : null,
            'historique' => $user ? Abonnement::historique($user['id']) : [],
            'commandes'  => $user ? Abonnement::commandes($user['id']) : [],
            'quantiteMax' => Panier::QUANTITE_MAX,
            // jeux gratuits pour tous (niveau 0), pas besoin de formule
            'gratuits'   => array_values(array_filter(Project::all(), fn (array $p): bool => $p['level'] === 0)),
        ]);
    }

    // endregion

    // region Résiliation

    /** Résilie l'abonnement en cours : pas de renouvellement, l'accès reste ouvert jusqu'à la date de fin. */
    public function resilier(): void
    {
        $this->verifyCsrf();
        $user = $this->requireLogin();
        Abonnement::resilier($user['id']);
        flash('success', 'Votre abonnement est résilié : il reste actif jusqu\'à sa date de fin.');
        $this->redirect('/abonnements');
    }

    // endregion
}
