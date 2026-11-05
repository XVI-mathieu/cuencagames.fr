<?php
namespace App\Core;

use RuntimeException;

/**
 * Gestion des fichiers .exe / .zip / .html des jeux, stockés dans storage/ (jamais servis directement par le web).
 *
 * Deux façons d'ajouter un fichier :
 *  - l'envoyer depuis le formulaire : {@see Upload::store()}
 *  - le déposer par FTP dans storage/depot/ puis le choisir dans le formulaire : {@see Upload::importFromDepot()}
 *    (utile quand le pare-feu de l'hébergeur bloque l'envoi d'exécutables : erreur 403).
 *
 * Les deux méthodes renvoient la même structure : ['filename' => nom stocké, 'original_name' => nom affiché, 'size' => octets].
 */
class Upload
{
    // region Constantes

    /** Extensions acceptées pour la version navigateur d'un jeu. */
    public const HTML_EXTENSIONS = ['html', 'htm'];

    // endregion

    // region Dossiers et chemins

    /** Dossier des fichiers publiés (storage/uploads), créé au besoin. */
    public static function dir(): string
    {
        return self::ensureDir(ROOT . '/storage/uploads');
    }

    /** Dossier de dépôt FTP (storage/depot), où l'on pose un gros fichier avant de l'importer. */
    public static function depotDir(): string
    {
        return self::ensureDir(ROOT . '/storage/depot');
    }

    /** Chemin complet d'un fichier publié (le nom est nettoyé avec basename() : pas de « ../ »). */
    public static function path(string $filename): string
    {
        return self::dir() . '/' . basename($filename);
    }

    // endregion

    // region Ajout d'un fichier

    /**
     * Enregistre le fichier reçu par le formulaire (après vérification de l'envoi, de l'extension, de la taille et de la signature).
     *
     * @param array              $file    Entrée de $_FILES.
     * @param array<string>|null $allowed Extensions acceptées (celles de config.php par défaut).
     * @return array{filename: string, original_name: string, size: int}
     * @throws RuntimeException Si le fichier est refusé (message affichable à l'utilisateur).
     */
    public static function store(array $file, ?array $allowed = null): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'Fichier trop volumineux (limite du serveur : ' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été envoyé que partiellement, réessayez.',
                UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été envoyé.',
                default               => 'Erreur serveur lors de l\'envoi du fichier (code ' . $error . ').',
            });
        }

        $original = (string) $file['name'];
        $extension = self::checkExtension($original, $allowed);

        $maxBytes = (int) Config::get('max_upload_mb', 500) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException('Fichier trop volumineux (maximum ' . Config::get('max_upload_mb') . ' Mo).');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Fichier invalide.');
        }
        self::checkSignature($file['tmp_name'], $extension);

        $filename = self::newName($extension);
        if (!move_uploaded_file($file['tmp_name'], self::path($filename))) {
            throw new RuntimeException('Impossible d\'enregistrer le fichier (vérifiez les droits du dossier storage/uploads).');
        }
        return self::result($filename, $original, (int) $file['size']);
    }

    /**
     * Liste les fichiers acceptés présents dans storage/depot/ (déposés par FTP).
     *
     * @param array<string>|null $allowed Extensions acceptées (celles de config.php par défaut).
     * @return array<string, int> nom => taille en octets
     */
    public static function depotFiles(?array $allowed = null): array
    {
        $allowed = self::allowedExtensions($allowed);
        $files = [];
        foreach (scandir(self::depotDir()) ?: [] as $name) {
            $path = self::depotDir() . '/' . $name;
            if (is_file($path) && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed, true)) {
                $files[$name] = (int) filesize($path);
            }
        }
        return $files;
    }

    /**
     * Déplace un fichier de storage/depot/ vers storage/uploads/ (même vérifications que {@see Upload::store()}).
     *
     * @param array<string>|null $allowed Extensions acceptées (celles de config.php par défaut).
     * @return array{filename: string, original_name: string, size: int}
     * @throws RuntimeException Si le fichier est introuvable ou refusé.
     */
    public static function importFromDepot(string $name, ?array $allowed = null): array
    {
        $name = basename($name);
        $source = self::depotDir() . '/' . $name;
        if ($name === '' || !is_file($source)) {
            throw new RuntimeException('Le fichier « ' . $name . ' » est introuvable dans le dossier storage/depot.');
        }

        $extension = self::checkExtension($name, $allowed);
        self::checkSignature($source, $extension);

        $size = (int) filesize($source);
        $filename = self::newName($extension);
        if (!rename($source, self::path($filename))) {
            throw new RuntimeException('Impossible de déplacer le fichier (vérifiez les droits des dossiers storage/depot et storage/uploads).');
        }
        return self::result($filename, $name, $size);
    }

    // endregion

    // region Suppression et noms

    /** Supprime un fichier publié, s'il existe. */
    public static function delete(string $filename): void
    {
        $path = self::path($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Nom de fichier d'origine nettoyé (caractères sûrs seulement) pour l'affichage et le téléchargement. */
    public static function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', basename($name)) ?? 'fichier.exe';
        return trim($name) !== '' ? trim($name) : 'fichier.exe';
    }

    // endregion

    // region Vérifications et outils internes

    /** Extensions acceptées : celles demandées, sinon celles de la configuration. */
    private static function allowedExtensions(?array $allowed): array
    {
        return $allowed ?? Config::get('allowed_extensions', ['exe']);
    }

    /** Retourne l'extension (en minuscules) du fichier, ou lève une exception si elle n'est pas acceptée. */
    private static function checkExtension(string $name, ?array $allowed = null): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = self::allowedExtensions($allowed);
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Seuls les fichiers ' . implode(', ', array_map(fn ($e) => ".$e", $allowed)) . ' sont acceptés.');
        }
        return $extension;
    }

    /** Vérifie la signature du fichier : « MZ » pour un .exe, « PK » pour un .zip, une balise HTML pour un .html. */
    private static function checkSignature(string $path, string $extension): void
    {
        $handle = fopen($path, 'rb');
        $head = $handle ? (string) fread($handle, 1024) : '';
        if ($handle) {
            fclose($handle);
        }
        $magic = substr($head, 0, 2);
        if (in_array($extension, self::HTML_EXTENSIONS, true) && !preg_match('/<(!doctype html|html)/i', $head)) {
            throw new RuntimeException('Ce fichier n\'est pas une page HTML valide.');
        }
        if ($extension === 'exe' && $magic !== 'MZ') {
            throw new RuntimeException('Ce fichier n\'est pas un exécutable Windows valide.');
        }
        if ($extension === 'zip' && $magic !== 'PK') {
            throw new RuntimeException('Ce fichier n\'est pas une archive .zip valide.');
        }
    }

    /** Nom aléatoire pour le stockage : impossible à deviner, jamais en conflit avec un autre fichier. */
    private static function newName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    /** Structure renvoyée par store() et importFromDepot(). */
    private static function result(string $filename, string $originalName, int $size): array
    {
        return [
            'filename'      => $filename,
            'original_name' => self::sanitizeName($originalName),
            'size'          => $size,
        ];
    }

    /** Crée le dossier s'il n'existe pas et le retourne. */
    private static function ensureDir(string $dir): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    // endregion
}
