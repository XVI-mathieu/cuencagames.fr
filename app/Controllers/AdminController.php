<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Permissions;
use App\Core\Upload;
use App\Models\Abonnement;
use App\Models\Project;
use App\Models\User;
use RuntimeException;

/**
 * Tableau de bord et gestion des projets (ajout, modification, suppression, installation de l'application).
 * Chaque action exige le droit correspondant (voir Core/Permissions.php).
 */
class AdminController extends Controller
{
    // region Tableau de bord

    /** Liste des projets avec les actions permises à ce compte. */
    public function index(): void
    {
        $this->requirePermission(Permissions::JEUX_LIRE);

        $installateur = self::appPath();
        $present = is_file($installateur);

        $this->render('admin/index', [
            'title'    => 'Tableau de bord',
            'projects' => Project::all(),
            // droits qui décident des boutons affichés (le serveur les revérifie à chaque action)
            'peut'     => [
                'ajouter'     => Auth::can(Permissions::JEUX_AJOUTER),
                'modifier'    => Auth::can(Permissions::JEUX_MODIFIER),
                'supprimer'   => Auth::can(Permissions::JEUX_SUPPRIMER),
                'application' => Auth::can(Permissions::APPLICATION),
            ],
            // encart « application Windows »
            'installateur' => [
                'present' => $present,
                'taille'  => $present ? (int) filesize($installateur) : 0,
                'date'    => $present ? (int) filemtime($installateur) : 0,
            ],
            'adresseSite'  => $this->siteAddress(),
            'niveaux'      => Abonnement::libellesNiveaux(), // 0 => Gratuit, 1 => Essentiel…
        ]);
    }

    /** Adresse du site sans « https:// », à saisir dans l'application Windows (ex. « cuencagames.fr »). */
    private function siteAddress(): string
    {
        $schema = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        return (string) preg_replace('#^https?://#', '', rtrim($schema . ($_SERVER['HTTP_HOST'] ?? '') . base_url(), '/'));
    }

    // endregion

    // region Première installation : création du compte administrateur

    /** Formulaire de création du mot de passe administrateur (seulement tant qu'aucun compte n'existe). */
    public function setupForm(): void
    {
        if (Auth::isConfigured()) {
            $this->redirect('/connexion');
        }
        $this->render('admin/setup', ['title' => 'Installation', 'errors' => []]);
    }

    /** Crée le compte administrateur « admin » avec le mot de passe choisi, puis le connecte. */
    public function setup(): void
    {
        if (Auth::isConfigured()) {
            $this->redirect('/connexion');
        }
        $this->verifyCsrf();

        $password = (string) ($_POST['password'] ?? '');
        $errors = array_values(array_filter([
            User::passwordError($password),
            $password !== ($_POST['password_confirm'] ?? '') ? 'Les deux mots de passe ne correspondent pas.' : null,
        ]));
        if ($errors) {
            $this->render('admin/setup', ['title' => 'Installation', 'errors' => $errors]);
            return;
        }

        Auth::login(User::create('admin', $password, 'admin'));
        flash('success', 'Mot de passe créé. Bienvenue dans votre espace d\'administration !');
        $this->redirect('/admin');
    }

    // endregion

    // region Déconnexion

    /** Ferme la session et retourne à l'accueil. */
    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        flash('success', 'Vous êtes déconnecté.');
        $this->redirect('/');
    }

    // endregion

    // region Application Windows de gestion

    /** Chemin de l'installateur de l'application Windows de gestion. */
    public static function appPath(): string
    {
        return ROOT . '/storage/outils/Setup_GestionnaireJeux.exe';
    }

    /** Télécharge l'installateur de l'application de gestion (droit « Télécharger l'application »). */
    public function downloadApp(): void
    {
        $this->requirePermission(Permissions::APPLICATION);
        $chemin = self::appPath();
        if (!is_file($chemin)) {
            flash('error', "L'application n'est pas sur le serveur : déposez « Setup_GestionnaireJeux.exe » dans storage/outils/.");
            $this->redirect('/admin');
        }

        $this->startFile('application/octet-stream', (int) filesize($chemin), 'attachment; filename="Setup_GestionnaireJeux.exe"');
        readfile($chemin);
        exit;
    }

    // endregion

    // region Ajout d'un projet

    /** Formulaire d'ajout. */
    public function create(): void
    {
        $this->requirePermission(Permissions::JEUX_AJOUTER);
        $this->renderForm(null, [], []);
    }

    /** Enregistre un nouveau projet : valide les champs, reçoit le .exe/.zip (et le .html facultatif). */
    public function store(): void
    {
        $this->requirePermission(Permissions::JEUX_AJOUTER);
        $this->verifyCsrf();

        $data = $this->input();
        $errors = Project::validationErrors($data);

        if (!$errors) {
            try {
                $file = $this->incomingFile();
                if ($file === null) {
                    $errors[] = 'Choisissez un fichier .exe ou .zip (depuis votre ordinateur ou dans le dossier de dépôt FTP).';
                }
                $data = Project::withFiles($data, $file, $this->incomingHtml(), false);
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        if ($errors) {
            $this->renderForm(null, $data, $errors);
            return;
        }

        $project = Project::create($data);
        flash('success', '« ' . $project['name'] . ' » a été ajouté.');
        $this->redirect('/admin');
    }

    // endregion

    // region Modification d'un projet

    /** Formulaire de modification, prérempli. */
    public function edit(string $id): void
    {
        $this->requirePermission(Permissions::JEUX_MODIFIER);
        $project = Project::find($id) ?? $this->notFound();
        $this->renderForm($project, $project, []);
    }

    /** Enregistre les modifications ; un nouveau fichier remplace l'ancien, qui est alors supprimé du disque. */
    public function update(string $id): void
    {
        $this->requirePermission(Permissions::JEUX_MODIFIER);
        $this->verifyCsrf();
        $project = Project::find($id) ?? $this->notFound();

        $data = $this->input();
        $errors = Project::validationErrors($data);
        $newFile = null;
        $newHtml = null;
        $removeHtml = !empty($_POST['remove_html']);

        if (!$errors) {
            try {
                $newFile = $this->incomingFile();
                $newHtml = $this->incomingHtml();
                $data = Project::withFiles($data, $newFile, $newHtml, $removeHtml);
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        if ($errors) {
            $this->renderForm($project, $data, $errors);
            return;
        }

        Project::update($id, $data);
        Project::deleteReplacedFiles($project, (bool) $newFile, $newHtml || $removeHtml);

        flash('success', '« ' . $data['name'] . ' » a été mis à jour.');
        $this->redirect('/admin');
    }

    // endregion

    // region Suppression d'un projet

    /** Supprime un projet et ses fichiers. */
    public function destroy(string $id): void
    {
        $this->requirePermission(Permissions::JEUX_SUPPRIMER);
        $this->verifyCsrf();
        $project = Project::find($id) ?? $this->notFound();

        Project::delete($id);
        Project::deleteFiles($project);

        flash('success', '« ' . $project['name'] . ' » a été supprimé.');
        $this->redirect('/admin');
    }

    // endregion

    // region Outils internes

    /** Champs du formulaire (nettoyés) avec l'état de la case « maintenance ». */
    private function input(): array
    {
        return Project::fromInput($_POST) + ['maintenance' => !empty($_POST['maintenance'])];
    }

    /**
     * Fichier .exe / .zip envoyé depuis l'ordinateur, sinon choisi dans storage/depot (FTP).
     * Retourne null si aucun des deux n'est fourni.
     */
    private function incomingFile(): ?array
    {
        return $this->incoming('file', 'depot_file');
    }

    /** Version navigateur facultative (.html), même principe : formulaire ou dossier de dépôt. */
    private function incomingHtml(): ?array
    {
        return $this->incoming('html_file', 'depot_html', Upload::HTML_EXTENSIONS);
    }

    /**
     * Fichier reçu par le formulaire (champ $champFichier) ou choisi dans le dossier de dépôt (champ $champDepot).
     *
     * @return array{filename: string, original_name: string, size: int}|null
     */
    private function incoming(string $champFichier, string $champDepot, ?array $allowed = null): ?array
    {
        $fichier = $_FILES[$champFichier] ?? null;
        if ($fichier && $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
            return Upload::store($fichier, $allowed);
        }
        $depot = trim((string) ($_POST[$champDepot] ?? ''));
        return $depot !== '' ? Upload::importFromDepot($depot, $allowed) : null;
    }

    /** Affiche le formulaire d'ajout / modification avec les erreurs éventuelles et les fichiers du dossier de dépôt. */
    private function renderForm(?array $project, array $old, array $errors): void
    {
        $this->render('admin/form', [
            'title'      => $project ? 'Modifier « ' . $project['name'] . ' »' : 'Ajouter un projet',
            'project'    => $project,
            'old'        => $old,
            'errors'     => $errors,
            'depotFiles' => Upload::depotFiles(),
            'depotHtml'  => Upload::depotFiles(Upload::HTML_EXTENSIONS),
            // fichiers choisis dans la liste avant un refus : on les resélectionne
            'selectedDepot'     => (string) ($_POST['depot_file'] ?? ''),
            'selectedDepotHtml' => (string) ($_POST['depot_html'] ?? ''),
            'phpUploadMax'      => ini_get('upload_max_filesize'),
            'niveaux'           => Abonnement::libellesNiveaux(), // choix du niveau d'abonnement requis
        ]);
    }

    // endregion
}
