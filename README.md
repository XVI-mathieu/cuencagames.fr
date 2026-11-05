# cuencagames.fr — site de jeux à abonnements (PHP MVC + MySQL)

Site en PHP avec une architecture **MVC** (Modèle – Vue – Contrôleur), une base **MySQL** accédée par **PDO** et aucun framework.
Des **abonnements** (Essentiel, Premium, Ultime) donnent accès à plus ou moins de jeux : chaque jeu demande un niveau
(0 = gratuit pour tous). Boutique de démonstration : prix fictifs, aucun paiement réel.

**Ce document est le seul document de la racine.** Il décrit chaque dossier : ce qu'il contient et comment c'est classé.

---

## 1. Vue d'ensemble de la racine

```
public_html/
├── index.php        point d'entrée UNIQUE du site (le seul fichier de code à la racine)
├── .htaccess        configuration du serveur Apache / LiteSpeed (voir « Pourquoi un .htaccess ? »)
├── README.md        ce document
├── app/             le code de l'application (MVC)
├── assets/          ressources servies telles quelles au navigateur (styles, scripts, images, polices)
├── database/        schéma SQL, installateur et schéma relationnel
├── storage/         données et fichiers du site (jamais accessibles depuis le web)
└── scripts/         outils pour le développeur (lancement local)
```

**Pourquoi un `.htaccess` ?** Le serveur ne lit cette configuration qu'à la racine : elle ne peut pas se ranger dans un sous-dossier.
Elle interdit l'accès direct à `app/`, `storage/`, `database/` et `scripts/` et envoie toute autre adresse vers `index.php`.
Chaque dossier interne contient en plus son propre `.htaccess` (« tout refuser ») : double protection.

---

## 2. Le trajet d'une requête (MVC)

```
navigateur ──► index.php ──► app/Core/bootstrap.php ──► Router ──► Contrôleur ──► Modèle ──► MySQL
                                                                        │
                                                                        └──► Vue (layout.php + page) ──► navigateur
```

1. `index.php` reçoit **toutes** les requêtes (deux formes d'adresse : URL propre `/projet/ab12` ou index réduit
   `index.php?controleur=projet&action=show&id=ab12`).
2. `app/Core/bootstrap.php` charge les classes, ouvre la session, installe la gestion des erreurs.
3. Le **routeur** (`app/Core/Router.php`) lit la table `app/Config/routes.php` et appelle la méthode du bon **contrôleur**.
4. Le contrôleur vérifie les droits, appelle les **modèles** (accès à la base) et choisit la **vue**.
5. La vue (HTML + un peu de PHP d'affichage) est insérée dans `layout.php`. **Une vue n'appelle jamais un modèle** :
   elle ne reçoit que des tableaux préparés par le contrôleur.

---

## 3. Dossier `app/` — le code

```
app/
├── Config/        réglages
├── Controllers/   C : reçoivent la requête, décident, choisissent la vue
├── Core/          le « moteur » du site (routeur, authentification, outils)
├── Models/        M : accès aux données (tous héritent de ModelePDO)
└── Views/         V : le HTML affiché
```

### `app/Config/` — réglages
| Fichier | Rôle |
|---|---|
| `config.php` | Nom du site, informations légales, limites d'envoi de fichiers, catégories, **section `db`** (modèle sans secrets). |
| `config.local.php` | **Identifiants réels de la base** (à créer, jamais versionné ni publié). Modèle : `config.local.example.php`. |
| `routes.php` | Table des adresses : à chaque URL correspond une méthode d'un contrôleur ; déclare aussi l'index réduit. |

### `app/Controllers/` — contrôleurs (« fins » : ils ne contiennent ni SQL ni HTML)
| Fichier | Rôle |
|---|---|
| `HomeController.php` | Accueil, mentions légales, confidentialité (RGPD), page Sécurité. |
| `ProjectController.php` | Fiche d'un jeu, jeu dans le navigateur (chrono d'essai sans compte), téléchargement ; **vérifie le niveau d'abonnement**. |
| `AbonnementController.php` | Page des formules, souscription, résiliation. |
| `AccountController.php` | Connexion (pseudo **ou** e-mail), inscription complète, vérification AJAX du pseudo / e-mail, « Mon compte » (export et suppression RGPD). |
| `AdminController.php` | Tableau de bord web : ajout, modification, suppression de jeux, installateur Windows. |
| `ApiController.php` | API JSON de l'ancienne application Windows « Gestionnaire Jeux ». |

### `app/Core/` — le moteur
| Fichier | Rôle |
|---|---|
| `bootstrap.php` | Démarrage : chargement automatique des classes, fuseau horaire, erreurs, session. |
| `Router.php` | Routeur : URL propres **et** index réduit (`?controleur=…&action=…`, seules les actions déclarées sont joignables). |
| `Controller.php` | Classe mère des contrôleurs : affichage des vues, redirections, contrôles (connexion, droits, jeton CSRF), envoi de fichiers. |
| `ModelePDO.php` | Classe mère des modèles : connexion PDO unique, `getLesTuples`, `appelerProcedure`, `appelerFonction`, transactions. |
| `Auth.php` | Qui est connecté, ses droits, **son niveau d'abonnement** (`Auth::level()`). |
| `Permissions.php` | Catalogue des droits (jeux, comptes, abonnements) et profils prédéfinis. |
| `Config.php` | Lecture de la configuration (`config.php` + `config.local.php` + variables d'environnement). |
| `Csrf.php` | Jeton secret des formulaires. |
| `Upload.php` | Réception et contrôle des fichiers `.exe` / `.zip` / `.html` (dossier `storage/`). |
| `helpers.php` | Petites fonctions d'affichage : `url()`, `e()` (échappement), `format_price()`, `flash()`… |

### `app/Models/` — modèles (chacun = une table ou un groupe de tables)
| Fichier | Table / objets de la base |
|---|---|
| `Project.php` | Table `projet` (jeux). Validation et gestion des fichiers d'un jeu. |
| `User.php` | Table `utilisateur` : nom, prénom, e-mail, téléphone, pseudo, mot de passe haché, rôle, droits. |
| `Abonnement.php` | Tables `abonnement` et `souscription`, via les procédures `sp_souscrire`, `sp_resilier`, `sp_abonnement_actuel`, `sp_souscriptions_utilisateur`, `sp_jeux_abonnement` et la fonction `fn_niveau_utilisateur`. |

### `app/Views/` — vues (chaque fichier commence par un commentaire qui liste ses variables)
| Élément | Contenu |
|---|---|
| `layout.php` | Mise en page commune : en-tête, menu, messages, pied de page, bandeau cookies. |
| `home/` | Accueil (cartes, filtres), mentions légales, confidentialité, page Sécurité. |
| `projects/` | Fiche d'un jeu, écran « temps d'essai écoulé ». |
| `abonnements/` | Page des formules, abonnement en cours, historique. |
| `account/` | Connexion + inscription (deux onglets), « Mon compte ». |
| `admin/` | Tableau de bord, formulaire de jeu (`form.php` + élément réutilisable `_source-fichier.php`), installation. |
| `partials/` | Éléments réutilisables (liste d'erreurs). |
| `errors/` | Page 404. |

---

## 4. Dossier `assets/` — ressources publiques

```
assets/
├── css/       style.css (thème), securite.css (page Sécurité)
├── js/        accueil.js (filtres), connexion.js (onglets + vérifications AJAX), cookies.js (bandeau), securite.js (simulation)
├── images/    logo.png, favicon.ico
├── fonts/     polices hébergées sur le site (aucun appel à Google : RGPD)
└── cinema/    scène animée de l'accueil (cinema.js, cinema.css, calques SVG) et sa documentation (README.md, MANIFEST.md)
```
Aucun code PHP ici : ce sont les seuls fichiers servis directement par le serveur.

---

## 5. Dossier `database/` — la base de données

| Fichier | Rôle |
|---|---|
| `schema.sql` | **Tables** (`categorie`, `utilisateur`, `abonnement`, `projet`, `souscription`), **fonctions** (`fn_niveau_utilisateur`, `fn_peut_acceder`), **procédures** de consultation et d'action, **triggers** (`trg_souscription_bi`, `trg_projet_bi/bu`). Rejouable sans perte de données. |
| `installer.php` | Installe le schéma ; avec `--migrer`, importe les anciens fichiers JSON (`storage/*.json`). |
| `schema-relationnel.svg` | Schéma relationnel graphique (tables, clés, cardinalités). |

Règle d'accès : un jeu a un **niveau** (0 gratuit, 1 Essentiel, 2 Premium, 3 Ultime) ; un joueur a le niveau de son abonnement en cours
(0 sans abonnement). Il accède aux jeux dont le niveau est **inférieur ou égal** au sien. Une souscription dure un mois ; le prix, le nom
de la formule et la date de fin sont fixés par un trigger, jamais par le code PHP ; la résiliation garde l'accès jusqu'à la date de fin.

---

## 6. Dossier `storage/` — données du site (protégé)

```
storage/
├── depot/     fichiers déposés par FTP, en attente d'être importés depuis le tableau de bord
├── uploads/   fichiers des jeux en ligne (.exe / .zip / .html) sous des noms aléatoires
├── outils/    installateur de l'application Windows « Gestionnaire Jeux »
└── *.json     anciennes données (comptes, catalogue) : servent uniquement à la migration vers MySQL
```
Ne jamais publier ce dossier sur GitHub (`.gitignore`) : il contient des comptes et des fichiers volumineux.

## 7. Dossier `scripts/` — développement
`demarrer.bat` : lance le site en local sur http://localhost:8000 (PHP 8.1+ avec l'extension `pdo_mysql`).

---

## 8. Conventions de code

- **Une classe = un fichier**, namespace `App\…` qui reflète le dossier (chargement automatique dans `bootstrap.php`).
- Chaque classe, méthode, constante et propriété a un commentaire (PHPDoc) ; le code est découpé en **régions**
  (`// region …` / `// endregion` en PHP, `#region` en C#, `// #region` en JavaScript) pour être replié dans l'éditeur.
- **Pas de répétition** : ce qui sert deux fois est factorisé (`ModelePDO`, `Controller::startFile`, `Project::validationErrors`,
  `partials/erreurs.php`, `admin/_source-fichier.php`…).
- **Sécurité** : requêtes préparées (PDO), jeton CSRF sur chaque formulaire POST, mots de passe hachés (`password_hash`),
  échappement de tout ce qui est affiché (`e()`), droits revérifiés côté serveur, accès direct aux dossiers internes interdit.

## 9. Installation

**En local (Windows)** : WAMP (PHP + MariaDB) → créer une base → copier `app/Config/config.local.example.php` en `config.local.php`
et le remplir → `php database/installer.php --migrer` → `scripts/demarrer.bat`.

**Chez l'hébergeur (Hostinger)** :
1. hPanel → *Bases de données* : créer la base et son utilisateur.
2. Créer `app/Config/config.local.php` sur le serveur avec ces identifiants.
3. phpMyAdmin → *Importer* → `database/schema.sql` (puis reprendre les données avec `--migrer` en local, ou export / import).
4. Envoyer le contenu de `public_html/` par FTP (sans écraser `storage/` ni `config.local.php`).

**Ajouter un jeu** : tableau de bord → « Ajouter un projet ». Gros fichiers : les déposer par FTP dans `storage/depot/`, puis les choisir dans
le formulaire (évite les blocages 403 du pare-feu de l'hébergeur). Le champ **Formule requise** décide de qui peut y jouer.

**Mot de passe administrateur oublié** : dans phpMyAdmin, supprimer la ligne du compte dans la table `utilisateur`, puis ouvrir `/admin`.
