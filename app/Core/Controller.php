<?php
namespace App\Core;

/**
 * Contrôleur de base : tout contrôleur du site en hérite.
 * Il fournit l'affichage des vues, les redirections et les contrôles d'accès communs (connexion, droits, jeton CSRF).
 */
class Controller
{
    // region Affichage

    /**
     * Affiche une vue (app/Views/<vue>.php) à l'intérieur de la mise en page layout.php.
     *
     * @param string $view Nom de la vue, ex. 'home/index'.
     * @param array  $data Variables rendues disponibles dans la vue.
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data + $this->commonViewData(), EXTR_SKIP);

        ob_start();
        require ROOT . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        require ROOT . '/app/Views/layout.php';
    }

    /**
     * Données que toutes les vues reçoivent : ainsi les vues n'appellent jamais la configuration, la session
     * ni les modèles elles-mêmes (règle MVC).
     *
     * - $site    : identité du site et informations légales (config.php) et catégories ;
     * - $session : visiteur connecté ou non, son pseudo, accès au tableau de bord, minutes d'essai sans compte,
     *              nombre de lignes de son panier.
     *
     * @return array{site: array, session: array}
     */
    private function commonViewData(): array
    {
        $limite = (int) Config::get('play_limit_seconds', 600);
        $connecte = Auth::loggedIn();

        return [
            'site' => [
                'nom'          => Config::get('site_name'),
                'slogan'       => Config::get('site_tagline'),
                'auteur'       => Config::get('author'),
                'statut'       => Config::get('editeur_statut'),
                'contact'      => Config::get('contact_url'),
                'hebergeurNom' => Config::get('hebergeur_nom'),
                'hebergeurUrl' => Config::get('hebergeur_url'),
                'categories'   => Config::get('categories', []),
                'cinema'       => Config::get('cinema_mode', 'test'),
                'uploadMaxMo'  => (int) Config::get('max_upload_mb'),
            ],
            'session' => [
                'connecte'     => $connecte,
                'pseudo'       => $connecte ? Auth::user()['username'] : '',
                'tableauBord'  => Auth::can(Permissions::JEUX_LIRE),
                'panierLignes' => Panier::nbLignes(),
                // minutes de jeu gratuit pour un visiteur sans compte (0 : pas de limite ou visiteur connecté)
                'essaiMinutes' => !$connecte && $limite > 0 ? (int) round($limite / 60) : 0,
            ],
        ];
    }

    /** Redirige vers une adresse du site (ex. '/admin') et arrête le script. */
    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    /** Affiche la page 404 et arrête le script. */
    public function notFound(): never
    {
        http_response_code(404);
        $this->render('errors/404', ['title' => 'Page introuvable']);
        exit;
    }

    /**
     * Prépare l'envoi d'un fichier : libère la session (le reste du site reste utilisable pendant un long
     * téléchargement), supprime la limite de temps, vide les tampons et envoie les en-têtes communs.
     *
     * @param string $contentType Type du contenu, ex. 'application/octet-stream'.
     * @param int    $length      Taille en octets.
     * @param string $disposition Valeur de l'en-tête Content-Disposition (vide : aucune).
     */
    protected function startFile(string $contentType, int $length, string $disposition = ''): void
    {
        session_write_close();
        set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $contentType);
        if ($disposition !== '') {
            header('Content-Disposition: ' . $disposition);
        }
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    // endregion

    // region Contrôles d'accès

    /**
     * Exige un compte connecté et retourne ses données ; sinon redirige vers la connexion avec ce message.
     *
     * @param string $message Message affiché si le visiteur n'est pas connecté (vide : redirection silencieuse).
     */
    protected function requireLogin(string $message = ''): array
    {
        $user = Auth::user();
        if (!$user) {
            if ($message !== '') {
                flash('error', $message);
            }
            $this->redirect('/connexion');
        }
        return $user;
    }

    /** Exige un compte connecté possédant ce droit (sinon connexion, ou message d'accès refusé). */
    protected function requirePermission(string $permission): void
    {
        if (!Auth::loggedIn()) {
            $this->redirect(Auth::isConfigured() ? '/connexion' : '/admin/installation');
        }
        if (!Auth::can($permission)) {
            flash('error', "Vous n'avez pas le droit d'effectuer cette action.");
            $this->redirect('/');
        }
    }

    /** Refuse la requête si le jeton CSRF du formulaire est absent ou invalide. */
    protected function verifyCsrf(): void
    {
        // Si le fichier dépasse post_max_size, PHP vide $_POST : on l'explique clairement.
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('error', 'Le fichier est trop volumineux pour la configuration PHP du serveur (post_max_size = '
                . ini_get('post_max_size') . ').');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('/admin')));
            exit;
        }

        if (!Csrf::check($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            exit('Jeton de sécurité invalide. Rechargez la page et réessayez.');
        }
    }

    // endregion
}
