<?php
namespace App\Models;

use App\Core\Config;
use App\Core\ModelePDO;
use App\Core\Upload;

/**
 * Modèle « Projet » (jeux et applications) : table `projet` de la base MySQL.
 *
 * Les méthodes renvoient des tableaux aux clés historiques (name, category, size, updated_at…) :
 * les contrôleurs, les vues et l'API de l'application Windows n'ont donc pas à connaître les noms de colonnes.
 */
class Project extends ModelePDO
{
    // region Correspondance clés PHP / colonnes SQL

    /** Champs modifiables : clé du tableau PHP => colonne SQL (sert de liste blanche pour {@see Project::update()}). */
    private const COLONNES = [
        'name'          => 'nom',
        'category'      => 'code_categorie',
        'version'       => 'version',
        'description'   => 'description',
        'filename'      => 'fichier',
        'original_name' => 'nom_original',
        'size'          => 'taille',
        'html'          => 'fichier_html',
        'html_size'     => 'taille_html',
        'maintenance'   => 'maintenance',
        'level'         => 'niveau_acces',
    ];

    // endregion

    // region Lecture

    /** Tous les projets, du plus récent au plus ancien. */
    public static function all(): array
    {
        return array_map(self::hydrate(...), self::getLesTuples('SELECT * FROM projet ORDER BY modifie_le DESC, nom'));
    }

    /** Un projet par son identifiant (null s'il n'existe pas). */
    public static function find(string $id): ?array
    {
        $ligne = self::getUnTuple('SELECT * FROM projet WHERE id = ?', [$id]);
        return $ligne ? self::hydrate($ligne) : null;
    }

    // endregion

    // region Écriture

    /**
     * Ajoute un projet et le retourne.
     *
     * @param array $data name, category, version, description, filename, original_name, size
     *                    (et html, html_size, maintenance, level en option).
     */
    public static function create(array $data): array
    {
        $id = bin2hex(random_bytes(6));
        $maintenant = self::maintenant();

        self::executerAction(
            'INSERT INTO projet (id, nom, code_categorie, version, description, fichier, nom_original, taille,
                                 fichier_html, taille_html, maintenance, telechargements, niveau_acces, cree_le, modifie_le)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [
                $id,
                $data['name'],
                $data['category'] ?? 'projets',
                $data['version'],
                $data['description'],
                $data['filename'],
                $data['original_name'],
                $data['size'],
                $data['html'] ?? '',
                $data['html_size'] ?? 0,
                self::valeurColonne('maintenance', $data['maintenance'] ?? false),
                self::valeurColonne('level', $data['level'] ?? 0),
                $maintenant,
                $maintenant,
            ]
        );

        return self::find($id);
    }

    /** Met à jour les champs autorisés d'un projet (liste blanche COLONNES) et sa date de modification. */
    public static function update(string $id, array $data): void
    {
        $sets = [];
        $params = [];
        foreach (self::COLONNES as $cle => $colonne) {
            if (array_key_exists($cle, $data)) {
                $sets[] = "$colonne = ?";
                $params[] = self::valeurColonne($cle, $data[$cle]);
            }
        }
        $sets[] = 'modifie_le = ?';
        $params[] = self::maintenant();
        $params[] = $id;

        self::executerAction('UPDATE projet SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /** Met plusieurs jeux en maintenance (true) ou les remet en ligne (false), sans toucher à leurs fichiers. */
    public static function setMaintenance(array $ids, bool $maintenance): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if (!$ids) {
            return 0;
        }
        $marques = implode(', ', array_fill(0, count($ids), '?'));
        return self::executerAction(
            "UPDATE projet SET maintenance = ?, modifie_le = ? WHERE id IN ($marques)",
            [$maintenance ? 1 : 0, self::maintenant(), ...$ids]
        );
    }

    /** Retire un projet de la base (les fichiers sont supprimés par l'appelant). */
    public static function delete(string $id): void
    {
        self::executerAction('DELETE FROM projet WHERE id = ?', [$id]);
    }

    /** Ajoute 1 au compteur de téléchargements d'un projet. */
    public static function incrementDownloads(string $id): void
    {
        self::executerAction('UPDATE projet SET telechargements = telechargements + 1 WHERE id = ?', [$id]);
    }

    // endregion

    // region Saisie, validation et fichiers (partagés par le tableau de bord web et l'API Windows)

    /**
     * Données d'un projet lues dans un formulaire ou une requête API (champs nettoyés).
     * Le niveau d'accès n'est présent que s'il a été envoyé : sinon le niveau existant est conservé lors d'une modification.
     */
    public static function fromInput(array $post): array
    {
        $data = [
            'name'        => trim((string) ($post['name'] ?? '')),
            'category'    => trim((string) ($post['category'] ?? '')),
            'version'     => trim((string) ($post['version'] ?? '')),
            'description' => trim((string) ($post['description'] ?? '')),
        ];
        if (isset($post['level'])) {
            $data['level'] = (int) $post['level'];
        }
        return $data;
    }

    /** Vérifie les champs d'un projet ; retourne la liste des messages d'erreur (vide si tout est valide). */
    public static function validationErrors(array $data): array
    {
        $erreurs = [];
        if ($data['name'] === '') {
            $erreurs[] = 'Le nom du projet est obligatoire.';
        } elseif (str_length($data['name']) > 100) {
            $erreurs[] = 'Le nom ne doit pas dépasser 100 caractères.';
        }
        if (!array_key_exists($data['category'], Config::get('categories', []))) {
            $erreurs[] = 'Choisissez une catégorie (Jeux ou Projets).';
        }
        if (str_length($data['version']) > 30) {
            $erreurs[] = 'La version ne doit pas dépasser 30 caractères.';
        }
        if (str_length($data['description']) > 5000) {
            $erreurs[] = 'La description ne doit pas dépasser 5000 caractères.';
        }
        if (isset($data['level']) && ($data['level'] < 0 || $data['level'] > Abonnement::NIVEAU_MAX)) {
            $erreurs[] = 'Le niveau d\'abonnement doit être compris entre 0 (gratuit) et ' . Abonnement::NIVEAU_MAX . '.';
        }
        return $erreurs;
    }

    /**
     * Complète les données d'un projet avec les fichiers qui viennent d'être reçus.
     *
     * @param array|null $file       Résultat de Upload::store() pour le .exe / .zip (null : inchangé).
     * @param array|null $html       Résultat de Upload::store() pour la version navigateur (null : inchangée).
     * @param bool       $removeHtml Vrai pour retirer la version navigateur existante.
     */
    public static function withFiles(array $data, ?array $file, ?array $html, bool $removeHtml): array
    {
        if ($file) {
            $data += $file;
        }
        if ($html) {
            $data += ['html' => $html['filename'], 'html_size' => $html['size']];
        } elseif ($removeHtml) {
            $data += ['html' => '', 'html_size' => 0];
        }
        return $data;
    }

    /** Supprime du disque les fichiers (exécutable et version navigateur) d'un projet. */
    public static function deleteFiles(array $project): void
    {
        Upload::delete($project['filename']);
        if ($project['html'] !== '') {
            Upload::delete($project['html']);
        }
    }

    /**
     * Après une modification : supprime du disque les anciens fichiers qui viennent d'être remplacés ou retirés.
     *
     * @param array $ancien        Le projet avant modification.
     * @param bool  $exeRemplace   Vrai si un nouvel exécutable a été reçu.
     * @param bool  $htmlChange    Vrai si la version navigateur a été remplacée ou retirée.
     */
    public static function deleteReplacedFiles(array $ancien, bool $exeRemplace, bool $htmlChange): void
    {
        if ($exeRemplace) {
            Upload::delete($ancien['filename']);
        }
        if ($htmlChange && $ancien['html'] !== '') {
            Upload::delete($ancien['html']);
        }
    }

    // endregion

    // region Conversions

    /** Valeur à écrire dans la colonne correspondant à cette clé : booléen en 0/1, niveau en entier, le reste tel quel. */
    private static function valeurColonne(string $cle, mixed $valeur): mixed
    {
        return match ($cle) {
            'maintenance' => !empty($valeur) ? 1 : 0,
            'level'       => (int) $valeur,
            default       => $valeur,
        };
    }

    /** Transforme une ligne de la table en tableau aux clés historiques. */
    private static function hydrate(array $ligne): array
    {
        return [
            'id'            => $ligne['id'],
            'name'          => $ligne['nom'],
            'category'      => $ligne['code_categorie'],
            'version'       => $ligne['version'],
            'description'   => $ligne['description'],
            'filename'      => $ligne['fichier'],
            'original_name' => $ligne['nom_original'],
            'size'          => (int) $ligne['taille'],
            'html'          => $ligne['fichier_html'],
            'html_size'     => (int) $ligne['taille_html'],
            'maintenance'   => (bool) $ligne['maintenance'],
            'downloads'     => (int) $ligne['telechargements'],
            'level'         => (int) $ligne['niveau_acces'],
            'created_at'    => self::versIso($ligne['cree_le']),
            'updated_at'    => self::versIso($ligne['modifie_le']),
        ];
    }

    // endregion
}
