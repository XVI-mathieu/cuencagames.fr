<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Abonnement;
use App\Models\Project;
use PDOException;

/**
 * Abonnements : page des formules (Essentiel, Premium, Ultime), souscription, résiliation et historique du joueur.
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
        }
        unset($plan);

        $this->render('abonnements/index', [
            'title'      => 'Abonnements',
            'plans'      => $plans,
            'niveau'     => Auth::subscriptionLevel(),   // formule réellement souscrite (un compte de gestion joue à tout sans elle)
            'complet'    => Auth::hasFullAccess(),       // compte de gestion : accès à tous les jeux
            'actuel'     => $user ? Abonnement::actuel($user['id']) : null,
            'historique' => $user ? Abonnement::historique($user['id']) : [],
            // jeux gratuits pour tous (niveau 0), pas besoin de formule
            'gratuits'   => array_values(array_filter(Project::all(), fn (array $p): bool => $p['level'] === 0)),
        ]);
    }

    // endregion

    // region Souscription et résiliation

    /** Souscrit une formule (connexion obligatoire) : l'abonnement en cours est remplacé immédiatement. */
    public function souscrire(string $id): void
    {
        $this->verifyCsrf();
        $user = $this->requireLogin('Connectez-vous (ou créez un compte) pour vous abonner.');

        $plan = Abonnement::find((int) $id);
        if (!$plan || !$plan['active']) {
            flash('error', 'Cette formule n\'existe pas ou n\'est plus proposée.');
            $this->redirect('/abonnements');
        }

        try {
            Abonnement::souscrire($user['id'], $plan['id']);
        } catch (PDOException $erreur) {
            // SQLSTATE 45000 = règle métier refusée par le trigger (message prévu pour l'utilisateur)
            $refus = ($erreur->errorInfo[0] ?? '') === '45000' ? (string) ($erreur->errorInfo[2] ?? '') : '';
            flash('error', $refus !== '' ? 'Abonnement refusé : ' . $refus : 'L\'abonnement n\'a pas pu être enregistré. Réessayez plus tard.');
            $this->redirect('/abonnements');
        }

        flash('success', 'Bienvenue dans la formule ' . $plan['name'] . ' ! Vos jeux sont débloqués pendant un mois.');
        $this->redirect('/abonnements');
    }

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
