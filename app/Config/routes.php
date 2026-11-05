<?php
/**
 * Table des routes : à chaque adresse correspond une méthode d'un contrôleur.
 * Chargé par index.php ; la variable $router (App\Core\Router) vient de lui.
 *
 * Syntaxe : $router->get('/adresse/{id}', [Contrôleur::class, 'méthode']) ; « {id} » est transmis à la méthode.
 */
use App\Controllers\AbonnementController;
use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\HomeController;
use App\Controllers\ProjectController;

// region Index réduit

// Les mêmes pages sont aussi joignables par  index.php?controleur=xxx&action=yyy[&id=zzz].
// Seules les actions listées ici sont autorisées, avec la méthode HTTP indiquée.
$router->reduit('accueil', HomeController::class, ['index' => 'GET']);
$router->reduit('projet', ProjectController::class, ['show' => 'GET', 'play' => 'GET', 'download' => 'GET']);
$router->reduit('abonnement', AbonnementController::class, ['index' => 'GET', 'souscrire' => 'POST', 'resilier' => 'POST']);

// endregion

// region Pages publiques

$router->get('/', [HomeController::class, 'index']);
$router->get('/securite', [HomeController::class, 'security']);
$router->get('/mentions-legales', [HomeController::class, 'legal']);
$router->get('/confidentialite', [HomeController::class, 'privacy']);
$router->get('/projet/{id}', [ProjectController::class, 'show']);
$router->get('/telecharger/{id}', [ProjectController::class, 'download']);
$router->get('/jouer/{id}', [ProjectController::class, 'play']);
$router->post('/jeu/chrono', [ProjectController::class, 'playPulse']); // « battement » du chrono d'essai (JavaScript)

// endregion

// region Boutique : abonnements

$router->get('/abonnements', [AbonnementController::class, 'index']);
$router->post('/abonnements/souscrire/{id}', [AbonnementController::class, 'souscrire']);
$router->post('/abonnements/resilier', [AbonnementController::class, 'resilier']);

// endregion

// region Comptes (joueurs et administrateurs)

$router->get('/compte/disponibilite', [AccountController::class, 'fieldAvailable']); // appelé en AJAX (pseudo ou e-mail)
$router->get('/connexion', [AccountController::class, 'loginForm']);
$router->post('/connexion', [AccountController::class, 'login']);
$router->post('/inscription', [AccountController::class, 'register']);
$router->post('/deconnexion', [AccountController::class, 'logout']);
$router->get('/mon-compte', [AccountController::class, 'profile']);
$router->get('/mon-compte/donnees', [AccountController::class, 'exportData']);
$router->post('/mon-compte/supprimer', [AccountController::class, 'deleteAccount']);

// endregion

// region API JSON de l'application Windows « Gestionnaire Jeux » (identifiants dans chaque requête)

$router->post('/api/connexion', [ApiController::class, 'login']);
$router->post('/api/projets/liste', [ApiController::class, 'projects']);
$router->post('/api/projets/enregistrer', [ApiController::class, 'saveProject']);
$router->post('/api/projets/maintenance', [ApiController::class, 'setMaintenance']);
$router->post('/api/projets/supprimer', [ApiController::class, 'deleteProject']);
$router->post('/api/utilisateurs/liste', [ApiController::class, 'users']);
$router->post('/api/utilisateurs/creer', [ApiController::class, 'createUser']);
$router->post('/api/utilisateurs/role', [ApiController::class, 'setRole']);
$router->post('/api/utilisateurs/droits', [ApiController::class, 'setPermissions']);
$router->post('/api/utilisateurs/motdepasse', [ApiController::class, 'setPassword']);
$router->post('/api/utilisateurs/supprimer', [ApiController::class, 'deleteUser']);

// endregion

// region Administration (tableau de bord web)

$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/installation', [AdminController::class, 'setupForm']);
$router->post('/admin/installation', [AdminController::class, 'setup']);
$router->get('/admin/connexion', [AccountController::class, 'loginForm']);
$router->post('/admin/deconnexion', [AdminController::class, 'logout']);
$router->get('/admin/application', [AdminController::class, 'downloadApp']);
$router->get('/admin/ajouter', [AdminController::class, 'create']);
$router->post('/admin/ajouter', [AdminController::class, 'store']);
$router->get('/admin/modifier/{id}', [AdminController::class, 'edit']);
$router->post('/admin/modifier/{id}', [AdminController::class, 'update']);
$router->post('/admin/supprimer/{id}', [AdminController::class, 'destroy']);

// endregion
