<?php
/**
 * Installe le schéma de la base (database/schema.sql), puis importe au besoin les anciens fichiers JSON.
 *
 *   php database/installer.php            crée / met à jour les tables, procédures, fonctions et triggers
 *   php database/installer.php --migrer   en plus : copie storage/projects.json et storage/users.json dans la base
 *
 * Les identifiants viennent de app/Config/config.local.php (ou des variables DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 * Sans accès en ligne de commande (hébergement mutualisé), importez plutôt schema.sql avec phpMyAdmin.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

// region Connexion

define('ROOT', dirname(__DIR__));
require ROOT . '/app/Core/Config.php';

use App\Core\Config;

$db = Config::get('db', []);
if (($db['nom'] ?? '') === '') {
    exit("Base non configurée : renseignez app/Config/config.local.php (modèle : app/Config/config.local.example.php).\n");
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['hote'], (int) $db['port'], $db['nom']),
    (string) $db['utilisateur'],
    (string) $db['mot_de_passe'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// endregion

// region Fonctions

/**
 * Exécute un fichier SQL qui peut contenir des « DELIMITER » (procédures, fonctions, triggers) :
 * le fichier est découpé comme le fait phpMyAdmin. Retourne le nombre d'instructions exécutées.
 */
function executerFichierSql(PDO $pdo, string $fichier): int
{
    $delimiteur = ';';
    $instruction = '';
    $nombre = 0;
    foreach (preg_split('/\R/', (string) file_get_contents($fichier)) as $ligne) {
        if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $ligne, $m)) {
            $delimiteur = $m[1];
            continue;
        }
        $instruction .= $ligne . "\n";
        if (!str_ends_with(rtrim($ligne), $delimiteur)) {
            continue; // l'instruction continue sur la ligne suivante
        }
        $sql = trim(substr(rtrim($instruction), 0, -strlen($delimiteur)));
        $instruction = '';
        // on ignore les blocs qui ne contiennent que des commentaires
        if (trim((string) preg_replace('/^\s*--.*$/m', '', $sql)) !== '') {
            $pdo->exec($sql);
            $nombre++;
        }
    }
    return $nombre;
}

/** Lit un fichier JSON de storage/ (tableau vide s'il n'existe pas). */
function lireJson(string $nom): array
{
    $chemin = ROOT . '/storage/' . $nom;
    return is_file($chemin) ? (json_decode((string) file_get_contents($chemin), true) ?: []) : [];
}

/** Date ISO 8601 de l'ancien format JSON -> date de la base (null si vide). */
function versDateBase(?string $iso): ?string
{
    return $iso ? date('Y-m-d H:i:s', strtotime($iso)) : null;
}

// endregion

// region 1. Schéma

echo 'Schéma installé (' . executerFichierSql($pdo, __DIR__ . '/schema.sql') . " instructions).\n";

// endregion

// region 2. Migration des anciennes données JSON (facultative, rejouable)

if (!in_array('--migrer', $argv, true)) {
    exit(0);
}

// --- Comptes -------------------------------------------------------------------------------------------------------
$utilisateurs = lireJson('users.json');
if (!$utilisateurs) { // très ancien format : un seul compte administrateur
    $ancien = lireJson('admin.json');
    if (!empty($ancien['password_hash'])) {
        $utilisateurs[] = ['id' => bin2hex(random_bytes(6)), 'username' => 'admin', 'password_hash' => $ancien['password_hash'],
                           'role' => 'admin', 'created_at' => date('c')];
    }
}
$insererUtilisateur = $pdo->prepare(
    'INSERT INTO utilisateur (id, pseudo, mot_de_passe, role, droits, cree_le, derniere_connexion)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE pseudo = VALUES(pseudo), mot_de_passe = VALUES(mot_de_passe), role = VALUES(role), droits = VALUES(droits)'
);
foreach ($utilisateurs as $u) {
    $insererUtilisateur->execute([
        $u['id'], $u['username'], $u['password_hash'], $u['role'] ?? 'user', json_encode($u['permissions'] ?? []),
        versDateBase($u['created_at'] ?? null) ?? date('Y-m-d H:i:s'), versDateBase($u['last_login'] ?? null),
    ]);
}
echo count($utilisateurs) . " compte(s) importé(s) (nom, prénom et e-mail restent à compléter).\n";

// --- Projets -------------------------------------------------------------------------------------------------------
// Niveau d'abonnement de départ : réparti de façon stable selon l'identifiant du jeu (0 = gratuit, 1 à 3 = formules).
// Il se modifie ensuite dans le tableau de bord.
$niveauxDeDepart = [0, 0, 1, 1, 2, 3];
$projets = lireJson('projects.json');
$categorieExiste = $pdo->prepare('INSERT IGNORE INTO categorie (code, libelle) VALUES (?, ?)');
$insererProjet = $pdo->prepare(
    'INSERT INTO projet (id, nom, code_categorie, version, description, fichier, nom_original, taille,
                         fichier_html, taille_html, maintenance, telechargements, niveau_acces, cree_le, modifie_le)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE nom = VALUES(nom), code_categorie = VALUES(code_categorie), version = VALUES(version),
         description = VALUES(description), fichier = VALUES(fichier), nom_original = VALUES(nom_original),
         taille = VALUES(taille), fichier_html = VALUES(fichier_html), taille_html = VALUES(taille_html),
         maintenance = VALUES(maintenance), telechargements = VALUES(telechargements), modifie_le = VALUES(modifie_le)'
    // le niveau d'accès n'est volontairement PAS écrasé si le projet existe déjà (il a pu être modifié depuis)
);
foreach ($projets as $p) {
    $categorie = $p['category'] ?? 'projets';
    $categorieExiste->execute([$categorie, ucfirst($categorie)]);
    $insererProjet->execute([
        $p['id'], $p['name'], $categorie, $p['version'] ?? '', $p['description'] ?? '',
        $p['filename'], $p['original_name'], (int) ($p['size'] ?? 0),
        $p['html'] ?? '', (int) ($p['html_size'] ?? 0), !empty($p['maintenance']) ? 1 : 0, (int) ($p['downloads'] ?? 0),
        $niveauxDeDepart[crc32($p['id']) % count($niveauxDeDepart)],
        versDateBase($p['created_at'] ?? null) ?? date('Y-m-d H:i:s'), versDateBase($p['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
    ]);
}
echo count($projets) . " projet(s) importé(s) (niveaux d'abonnement de départ attribués aux nouveaux projets).\n";

// endregion
