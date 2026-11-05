<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Abonnement;
use App\Models\Project;

/** Pages publiques : accueil, mentions légales, confidentialité (RGPD) et section Sécurité. */
class HomeController extends Controller
{
    // region Accueil

    /** Accueil : liste des jeux et projets (le filtrage et la recherche se font en JavaScript dans la vue). */
    public function index(): void
    {
        $this->render('home/index', [
            'projects' => Project::all(),
            'niveau'   => Auth::level(),                 // niveau d'abonnement du visiteur : décide des jeux débloqués
            'niveaux'  => Abonnement::libellesNiveaux(), // 0 => Gratuit, 1 => Essentiel…
        ]);
    }

    // endregion

    // region Pages d'information

    /** Mentions légales (obligation LCEN). */
    public function legal(): void
    {
        $this->render('home/mentions', ['title' => 'Mentions légales']);
    }

    /** Politique de confidentialité et cookies (RGPD). */
    public function privacy(): void
    {
        $this->render('home/confidentialite', ['title' => 'Politique de confidentialité et cookies']);
    }

    /** Section pédagogique « Sécurité » (simulation de sensibilisation). */
    public function security(): void
    {
        $this->render('home/securite', ['title' => 'Teste tes réflexes — Sécurité']);
    }

    // endregion
}
