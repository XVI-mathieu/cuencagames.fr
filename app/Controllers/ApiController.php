<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Permissions;
use App\Core\Upload;
use App\Models\Project;
use App\Models\User;
use RuntimeException;

/**
 * API JSON utilisée par l'application Windows « Gestionnaire Jeux ».
 * Chaque requête (POST) contient `username` + `password` ; chaque action vérifie ensuite
 * le droit correspondant (voir Core/Permissions.php). Le rôle « admin » a tous les droits.
 *
 * Réponses : {"ok": true, ...} en cas de succès, {"ok": false, "error": "message"} sinon.
 */
class ApiController extends Controller
{
    // region Données

    /** Compte authentifié pour la requête en cours. */
    private array $me = [];

    // endregion

    // region Connexion

    /** Vérifie les identifiants et renvoie les droits du compte, avec le catalogue et les profils de droits. */
    public function login(): void
    {
        $this->authorize();
        $droits = Permissions::of($this->me);
        if (!$droits) {
            $this->fail("Ce compte n'a aucun droit de gestion.", 403);
        }
        $this->json([
            'ok'          => true,
            'username'    => $this->me['username'],
            'role'        => $this->me['role'],
            'permissions' => $droits,
            'catalog'     => Permissions::catalogForApi(),
            'profiles'    => Permissions::profilesForApi(),
        ]);
    }

    // endregion

    // region Jeux

    /** Liste des jeux et des catégories. */
    public function projects(): void
    {
        $this->authorize(Permissions::JEUX_LIRE);
        $this->json(['ok' => true, 'projects' => Project::all(), 'categories' => Config::get('categories', [])]);
    }

    /** Ajoute un jeu (sans `id`) ou modifie un jeu (avec `id`) ; fichiers facultatifs en modification. */
    public function saveProject(): void
    {
        $this->authorize($this->postedId() !== '' ? Permissions::JEUX_MODIFIER : Permissions::JEUX_AJOUTER);

        $id = $this->postedId();
        $project = $id !== '' ? (Project::find($id) ?? $this->fail('Jeu introuvable.', 404)) : null;

        $data = Project::fromInput($_POST);
        $erreurs = Project::validationErrors($data);
        if ($erreurs) {
            $this->fail($erreurs[0]);
        }

        try {
            $file = $this->incoming('file');
            $html = $this->incoming('html_file', Upload::HTML_EXTENSIONS);
        } catch (RuntimeException $ex) {
            $this->fail($ex->getMessage());
        }
        if (!$file && !$project) {
            $this->fail('Choisissez un fichier .exe ou .zip pour ce nouveau jeu.');
        }
        $removeHtml = !empty($_POST['remove_html']);
        $data = Project::withFiles($data, $file, $html, $removeHtml);

        if ($project) {
            Project::update($id, $data);
            Project::deleteReplacedFiles($project, (bool) $file, $html || $removeHtml);
            $saved = Project::find($id);
        } else {
            $saved = Project::create($data);
        }
        $this->json(['ok' => true, 'project' => $saved]);
    }

    /** Met des jeux en maintenance / les remet en ligne : `ids` (séparés par des virgules) + `maintenance` (1 ou 0). */
    public function setMaintenance(): void
    {
        $this->authorize(Permissions::JEUX_MODIFIER);

        $ids = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['ids'] ?? '')))));
        if (!$ids) {
            $this->fail('Aucun jeu sélectionné.');
        }
        $count = Project::setMaintenance($ids, !empty($_POST['maintenance']) && $_POST['maintenance'] !== '0');
        $this->json(['ok' => true, 'count' => $count]);
    }

    /** Supprime un jeu et ses fichiers. */
    public function deleteProject(): void
    {
        $this->authorize(Permissions::JEUX_SUPPRIMER);
        $project = Project::find($this->postedId()) ?? $this->fail('Jeu introuvable.', 404);

        Project::delete($project['id']);
        Project::deleteFiles($project);
        $this->json(['ok' => true]);
    }

    // endregion

    // region Comptes utilisateurs

    /** Liste des comptes (sans les mots de passe). */
    public function users(): void
    {
        $this->authorize(Permissions::COMPTES_LIRE);
        $this->json(['ok' => true, 'users' => array_map([User::class, 'publicView'], User::all())]);
    }

    /** Crée un compte (`new_username`, `new_password`, `role`, `permissions`). */
    public function createUser(): void
    {
        $this->authorize(Permissions::COMPTES_CREER);

        $username = trim((string) ($_POST['new_username'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'user');
        $permissions = $this->postedPermissions();

        $erreur = User::usernameError($username) ?? User::passwordError($password);
        if ($erreur) {
            $this->fail($erreur);
        }
        if ($role === 'admin') {
            $this->needAdmin('Seul un administrateur peut créer un administrateur.');
        }
        $this->needSubset($permissions);

        $user = User::create($username, $password, $role, $permissions);
        $this->json(['ok' => true, 'user' => User::publicView($user)]);
    }

    /** Passe un compte administrateur / non administrateur (réservé aux administrateurs). */
    public function setRole(): void
    {
        $this->authorize();
        $this->needAdmin("Seul un administrateur peut changer le rôle d'un compte.");
        $user = $this->targetUser();
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, User::ROLES, true)) {
            $this->fail('Rôle invalide.');
        }
        if ($user['role'] === 'admin' && $role !== 'admin' && User::adminCount() <= 1) {
            $this->fail('Impossible de retirer le dernier administrateur.');
        }
        User::setRole($user['id'], $role);
        $this->json(['ok' => true]);
    }

    /** Définit les droits d'un compte (liste séparée par des virgules dans `permissions`). */
    public function setPermissions(): void
    {
        $this->authorize(Permissions::COMPTES_MODIFIER);
        $user = $this->targetUser();
        $permissions = $this->postedPermissions();

        $this->guardTarget($user);
        if ($user['id'] === $this->me['id'] && $this->me['role'] !== 'admin') {
            $this->fail('Vous ne pouvez pas modifier vos propres droits.');
        }
        $this->needSubset($permissions);

        User::setPermissions($user['id'], $permissions);
        $this->json(['ok' => true]);
    }

    /** Change le mot de passe d'un compte (`new_password`). */
    public function setPassword(): void
    {
        $this->authorize(Permissions::COMPTES_MODIFIER);
        $user = $this->targetUser();
        $this->guardTarget($user);

        $password = (string) ($_POST['new_password'] ?? '');
        if ($erreur = User::passwordError($password)) {
            $this->fail($erreur);
        }
        User::setPassword($user['id'], $password);
        $this->json(['ok' => true]);
    }

    /** Supprime un compte (jamais le sien, ni le dernier administrateur). */
    public function deleteUser(): void
    {
        $this->authorize(Permissions::COMPTES_SUPPRIMER);
        $user = $this->targetUser();
        $this->guardTarget($user);

        if ($user['id'] === $this->me['id']) {
            $this->fail('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($user['role'] === 'admin' && User::adminCount() <= 1) {
            $this->fail('Impossible de supprimer le dernier administrateur.');
        }
        User::delete($user['id']);
        $this->json(['ok' => true]);
    }

    // endregion

    // region Contrôles d'accès

    /**
     * Authentifie la requête (identifiants dans le corps) puis, si un droit est demandé, exige que le compte le possède.
     *
     * @param string|null $permission Droit requis (null : un simple compte valide suffit).
     */
    private function authorize(?string $permission = null): void
    {
        $user = User::verify(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
        if (!$user) {
            sleep(1); // ralentit les tentatives de force brute
            $this->fail('Identifiants incorrects.', 401);
        }
        $this->me = $user;

        if ($permission !== null && !Permissions::has($this->me, $permission)) {
            $this->fail('Droit manquant : « ' . (Permissions::CATALOG[$permission][1] ?? $permission) . ' ».', 403);
        }
    }

    /** Refuse la requête si le compte connecté n'est pas administrateur. */
    private function needAdmin(string $message): void
    {
        if ($this->me['role'] !== 'admin') {
            $this->fail($message, 403);
        }
    }

    /** Un non-administrateur ne peut pas donner plus de droits qu'il n'en a. */
    private function needSubset(array $permissions): void
    {
        if ($this->me['role'] === 'admin') {
            return;
        }
        $mine = Permissions::of($this->me);
        foreach (Permissions::normalize($permissions) as $permission) {
            if (!in_array($permission, $mine, true)) {
                $this->fail("Vous ne pouvez pas accorder un droit que vous n'avez pas : « " . Permissions::CATALOG[$permission][1] . ' ».', 403);
            }
        }
    }

    /** Seul un administrateur peut modifier ou supprimer un administrateur. */
    private function guardTarget(array $user): void
    {
        if ($user['role'] === 'admin' && $this->me['role'] !== 'admin') {
            $this->fail('Seul un administrateur peut modifier ou supprimer un administrateur.', 403);
        }
    }

    // endregion

    // region Lecture de la requête

    /** Identifiant (`id`) envoyé dans la requête, vide s'il est absent. */
    private function postedId(): string
    {
        return trim((string) ($_POST['id'] ?? ''));
    }

    /** Liste des droits envoyés (tableau, ou texte séparé par des virgules). */
    private function postedPermissions(): array
    {
        $brut = $_POST['permissions'] ?? '';
        $liste = is_array($brut) ? $brut : explode(',', (string) $brut);
        return array_values(array_filter(array_map('trim', $liste), fn ($p) => $p !== ''));
    }

    /** Compte visé par la requête (`id`), ou réponse 404. */
    private function targetUser(): array
    {
        return User::find($this->postedId()) ?? $this->fail('Utilisateur introuvable.', 404);
    }

    /** Enregistre le fichier envoyé dans le champ $champ (null s'il n'y en a pas). */
    private function incoming(string $champ, ?array $allowed = null): ?array
    {
        $fichier = $_FILES[$champ] ?? null;
        if (!$fichier || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return Upload::store($fichier, $allowed);
    }

    // endregion

    // region Réponses JSON

    /** Envoie une réponse JSON et arrête le script. */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Envoie une réponse d'erreur {"ok": false, "error": ...} et arrête le script. */
    private function fail(string $message, int $status = 400): never
    {
        $this->json(['ok' => false, 'error' => $message], $status);
    }

    // endregion
}
