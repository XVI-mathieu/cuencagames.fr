<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Permissions;
use App\Models\Abonnement;
use App\Models\User;

/**
 * Comptes : connexion (pseudo ou e-mail), création de compte avec formulaire complet, déconnexion, vérification AJAX
 * du pseudo et de l'e-mail, et page « Mon compte » (abonnement, export et suppression des données : droits RGPD).
 */
class AccountController extends Controller
{
    // region Connexion

    /** Affiche le formulaire de connexion (ou redirige : installation initiale, ou déjà connecté). */
    public function loginForm(): void
    {
        if (!Auth::isConfigured()) {
            $this->redirect('/admin/installation');
        }
        if (Auth::loggedIn()) {
            $this->redirect(Auth::can(Permissions::JEUX_LIRE) ? '/admin' : '/');
        }
        $this->renderForm('login');
    }

    /** Vérifie les identifiants du formulaire de connexion : pseudo OU e-mail, puis mot de passe. */
    public function login(): void
    {
        $this->verifyCsrf();
        $login = trim((string) ($_POST['login'] ?? ''));

        $user = Auth::attempt($login, (string) ($_POST['password'] ?? ''));
        if ($user) {
            if (Permissions::has($user, Permissions::JEUX_LIRE)) {
                $this->redirect('/admin');
            }
            flash('success', 'Bon retour, ' . $user['username'] . ' !');
            $this->redirect('/');
        }

        sleep(1); // ralentit les tentatives de force brute
        $this->renderForm('login', ['Identifiant ou mot de passe incorrect.'], ['login' => $login]);
    }

    /** Ferme la session et retourne à l'accueil. */
    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        flash('success', 'Vous êtes déconnecté.');
        $this->redirect('/');
    }

    // endregion

    // region Création de compte

    /** Crée un compte joueur à partir du formulaire d'inscription complet, puis le connecte. */
    public function register(): void
    {
        $this->verifyCsrf();
        $profile = [
            'last_name'  => trim((string) ($_POST['last_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'email'      => trim((string) ($_POST['email'] ?? '')),
            'phone'      => trim((string) ($_POST['phone'] ?? '')),
            'username'   => trim((string) ($_POST['username'] ?? '')),
        ];
        $password = (string) ($_POST['password'] ?? '');

        $errors = array_values(array_filter([
            User::nameError('Le nom', $profile['last_name']),
            User::nameError('Le prénom', $profile['first_name']),
            User::emailError($profile['email']),
            User::phoneError($profile['phone']),
            User::usernameError($profile['username']),
            User::passwordError($password),
            $password !== ($_POST['password_confirm'] ?? '') ? 'Les deux mots de passe ne correspondent pas.' : null,
            empty($_POST['accept_rgpd']) ? 'Vous devez accepter la politique de confidentialité pour créer un compte.' : null,
        ]));
        if ($errors) {
            $this->renderForm('register', $errors, $profile);
            return;
        }

        Auth::login(User::create($profile['username'], $password, 'user', [], $profile));
        flash('success', 'Compte créé, bienvenue ' . $profile['first_name'] . ' !');
        $this->redirect('/');
    }

    /**
     * Appelée en AJAX par le formulaire d'inscription pendant la frappe : répond en JSON si le pseudo ou l'e-mail
     * est valide et libre (GET ?champ=pseudo|email&valeur=…).
     */
    public function fieldAvailable(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        // Anti-abus : au plus 40 vérifications par minute et par visiteur (évite de lister les comptes existants)
        $debutFenetre = (int) ($_SESSION['dispo_window'] ?? 0);
        if (time() - $debutFenetre > 60) {
            $_SESSION['dispo_window'] = time();
            $_SESSION['dispo_count'] = 0;
        }
        $_SESSION['dispo_count'] = (int) ($_SESSION['dispo_count'] ?? 0) + 1;
        if ($_SESSION['dispo_count'] > 40) {
            http_response_code(429);
            echo json_encode(['disponible' => false, 'message' => 'Trop de vérifications, patientez une minute.']);
            exit;
        }

        $valeur = trim((string) ($_GET['valeur'] ?? ''));
        $champ = (string) ($_GET['champ'] ?? '');
        $erreur = match ($champ) {
            'pseudo' => User::usernameError($valeur),
            'email'  => User::emailError($valeur),
            default  => 'Champ inconnu.',
        };
        echo json_encode([
            'disponible' => $erreur === null,
            'message'    => $erreur ?? ($champ === 'email' ? 'Cette adresse e-mail est disponible.' : 'Ce nom d\'utilisateur est disponible.'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // endregion

    // region Mon compte (droits RGPD : accès, portabilité, effacement)

    /** Page « Mon compte » : informations, droits, abonnement, export et suppression. */
    public function profile(): void
    {
        $this->requireLogin();
        $this->renderProfile();
    }

    /** Droit d'accès / portabilité : toutes les données du compte (abonnements compris), sans le mot de passe haché. */
    public function exportData(): void
    {
        $user = $this->requireLogin();

        $export = [
            'exporte_le'  => date('c'),
            'compte'      => User::publicView($user),
            'abonnements' => Abonnement::historique($user['id']),
            'remarque'    => 'Le mot de passe n\'est conservé que sous forme chiffrée (hachage) et n\'est pas exporté.',
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->startFile('application/json; charset=utf-8', strlen($json), 'attachment; filename="mes-donnees-' . $user['username'] . '.json"');
        echo $json;
        exit;
    }

    /** Droit à l'effacement : suppression immédiate du compte après confirmation du mot de passe. */
    public function deleteAccount(): void
    {
        $user = $this->requireLogin();
        $this->verifyCsrf();

        if (!password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            sleep(1);
            $this->renderProfile(['Mot de passe incorrect : le compte n\'a pas été supprimé.']);
            return;
        }
        if ($user['role'] === 'admin' && User::adminCount() <= 1) {
            $this->renderProfile(['Vous êtes le dernier administrateur : nommez-en un autre avant de supprimer ce compte.']);
            return;
        }

        User::delete($user['id']);
        Auth::logout();
        flash('success', 'Votre compte et vos données ont été supprimés.');
        $this->redirect('/');
    }

    // endregion

    // region Affichage

    /** Affiche la page « Mon compte » avec d'éventuelles erreurs. */
    private function renderProfile(array $errors = []): void
    {
        $user = Auth::user();
        $this->render('account/profil', [
            'title'  => 'Mon compte',
            'user'   => $user,
            // droits effectifs avec leur groupe et leur libellé, prêts à afficher
            'droits' => array_map(fn (string $droit): array => Permissions::CATALOG[$droit], Permissions::of($user)),
            'actuel' => Abonnement::actuel($user['id']),
            'errors' => $errors,
        ]);
    }

    /**
     * Affiche la page de connexion / création de compte.
     *
     * @param string $tab    Onglet ouvert : 'login' ou 'register'.
     * @param array  $errors Messages d'erreur à afficher.
     * @param array  $old    Valeurs saisies à réafficher (jamais les mots de passe).
     */
    private function renderForm(string $tab, array $errors = [], array $old = []): void
    {
        $this->render('account/connexion', [
            'title'  => 'Connexion',
            'tab'    => $tab,
            'errors' => $errors,
            'old'    => $old,
        ]);
    }

    // endregion
}
