-- ============================================================================
--  Base de données du site (MySQL 8 / MariaDB 10.5+), jeu de caractères utf8mb4
--
--  Principe de la boutique : des ABONNEMENTS (Essentiel, Premium, Ultime). Chaque jeu demande un niveau
--  d'abonnement (0 = gratuit pour tous) ; un joueur accède aux jeux dont le niveau est inférieur ou égal
--  au niveau de son abonnement en cours. Boutique de démonstration : aucun paiement réel.
--
--  À importer dans la base DÉJÀ créée (phpMyAdmin → onglet « Importer », ou
--  php database/installer.php en ligne de commande).
--  Le script peut être rejoué sans danger : les tables et les données existantes sont conservées,
--  seules les procédures, les fonctions et les triggers sont recréés.
-- ============================================================================

SET NAMES utf8mb4;

-- region TABLES

-- Catégories de projets affichées sur l'accueil
CREATE TABLE IF NOT EXISTS categorie (
    code     VARCHAR(30) NOT NULL,
    libelle  VARCHAR(60) NOT NULL,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes du site : joueurs et gestionnaires. nom, prénom et e-mail sont obligatoires à l'inscription mais restent
-- facultatifs en base (comptes créés avant l'ajout du formulaire complet, ex. le compte « admin »).
CREATE TABLE IF NOT EXISTS utilisateur (
    id                  CHAR(12)     NOT NULL,
    pseudo              VARCHAR(30)  NOT NULL,
    nom                 VARCHAR(60)  NULL,
    prenom              VARCHAR(60)  NULL,
    email               VARCHAR(190) NULL,
    telephone           VARCHAR(20)  NULL,                  -- facultatif
    mot_de_passe        VARCHAR(255) NOT NULL,              -- hachage password_hash(), jamais le mot de passe
    role                ENUM('user','admin') NOT NULL DEFAULT 'user',
    droits              TEXT         NOT NULL,              -- liste JSON des droits, ex. ["jeux.lire"]
    cree_le             DATETIME     NOT NULL,
    derniere_connexion  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utilisateur_pseudo (pseudo),              -- unicité sans tenir compte des majuscules
    UNIQUE KEY uq_utilisateur_email (email)                 -- plusieurs NULL autorisés, jamais deux e-mails identiques
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formules d'abonnement payantes (le niveau 0, « Gratuit », est implicite : il ne se souscrit pas)
CREATE TABLE IF NOT EXISTS abonnement (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code          VARCHAR(20)   NOT NULL,
    nom           VARCHAR(40)   NOT NULL,
    niveau        TINYINT UNSIGNED NOT NULL,                -- 1 = Essentiel, 2 = Premium, 3 = Ultime
    prix_mensuel  DECIMAL(6,2)  NOT NULL,                   -- prix fictif, par mois
    description   VARCHAR(255)  NOT NULL DEFAULT '',
    actif         TINYINT(1)    NOT NULL DEFAULT 1,         -- 0 = n'est plus proposé aux nouveaux abonnés
    PRIMARY KEY (id),
    UNIQUE KEY uq_abonnement_code (code),
    UNIQUE KEY uq_abonnement_niveau (niveau)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeux et applications ; niveau_acces = niveau d'abonnement minimal pour y jouer / le télécharger (0 = gratuit)
CREATE TABLE IF NOT EXISTS projet (
    id               CHAR(12)      NOT NULL,
    nom              VARCHAR(100)  NOT NULL,
    code_categorie   VARCHAR(30)   NOT NULL DEFAULT 'projets',
    version          VARCHAR(30)   NOT NULL DEFAULT '',
    description      TEXT          NOT NULL,
    fichier          VARCHAR(255)  NOT NULL,                -- nom du fichier stocké dans storage/uploads
    nom_original     VARCHAR(255)  NOT NULL,
    taille           BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fichier_html     VARCHAR(255)  NOT NULL DEFAULT '',     -- version jouable dans le navigateur (facultative)
    taille_html      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    maintenance      TINYINT(1)    NOT NULL DEFAULT 0,
    telechargements  INT UNSIGNED  NOT NULL DEFAULT 0,
    niveau_acces     TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- contrôlé par les triggers trg_projet_*
    cree_le          DATETIME      NOT NULL,
    modifie_le       DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_projet_categorie (code_categorie),
    CONSTRAINT fk_projet_categorie FOREIGN KEY (code_categorie) REFERENCES categorie (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abonnements souscrits par les joueurs. Le nom, le niveau et le prix sont recopiés au moment de la souscription
-- (par le trigger trg_souscription_bi) : un changement de tarif ne modifie pas l'historique.
CREATE TABLE IF NOT EXISTS souscription (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_utilisateur   CHAR(12)      NOT NULL,
    id_abonnement    INT UNSIGNED  NOT NULL,
    nom_abonnement   VARCHAR(40)   NOT NULL DEFAULT '',     -- rempli par le trigger
    niveau           TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- rempli par le trigger
    prix             DECIMAL(6,2)  NOT NULL DEFAULT 0.00,   -- rempli par le trigger
    date_debut       DATETIME      NOT NULL,
    date_fin         DATETIME      NOT NULL,                -- rempli par le trigger : début + 1 mois
    statut           ENUM('active','resiliee','terminee') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_souscription_utilisateur (id_utilisateur),
    CONSTRAINT fk_souscription_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE,
    CONSTRAINT fk_souscription_abonnement  FOREIGN KEY (id_abonnement)  REFERENCES abonnement (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- endregion

-- region DONNÉES DE DÉPART

INSERT IGNORE INTO categorie (code, libelle) VALUES ('jeux', 'Jeux'), ('projets', 'Projets');

INSERT IGNORE INTO abonnement (code, nom, niveau, prix_mensuel, description) VALUES
    ('essentiel', 'Essentiel', 1, 4.99,  'Les jeux classiques en plus des jeux gratuits.'),
    ('premium',   'Premium',   2, 9.99,  'Tous les jeux Essentiel et les jeux Premium.'),
    ('ultime',    'Ultime',    3, 14.99, 'Tout le catalogue, y compris les nouveautés.');

-- endregion

-- region FONCTIONS, PROCÉDURES STOCKÉES ET TRIGGERS (recréés à chaque import)

DROP TRIGGER   IF EXISTS trg_souscription_bi;
DROP TRIGGER   IF EXISTS trg_projet_bi;
DROP TRIGGER   IF EXISTS trg_projet_bu;
DROP PROCEDURE IF EXISTS sp_souscrire;
DROP PROCEDURE IF EXISTS sp_resilier;
DROP PROCEDURE IF EXISTS sp_abonnement_actuel;
DROP PROCEDURE IF EXISTS sp_souscriptions_utilisateur;
DROP PROCEDURE IF EXISTS sp_jeux_abonnement;
DROP FUNCTION  IF EXISTS fn_niveau_utilisateur;
DROP FUNCTION  IF EXISTS fn_peut_acceder;

DELIMITER $$

-- FONCTION stockée paramétrée : niveau d'abonnement d'un joueur = niveau de son abonnement en cours le plus élevé,
-- 0 (gratuit) s'il n'en a pas. Un abonnement résilié reste valable jusqu'à sa date de fin.
CREATE FUNCTION fn_niveau_utilisateur(p_id_utilisateur CHAR(12))
RETURNS TINYINT UNSIGNED
READS SQL DATA
BEGIN
    RETURN COALESCE(
        (SELECT MAX(niveau) FROM souscription
         WHERE id_utilisateur = p_id_utilisateur
           AND statut IN ('active', 'resiliee')
           AND date_fin > NOW()),
        0
    );
END$$

-- FONCTION stockée paramétrée : 1 si le joueur peut jouer au jeu (niveau suffisant), 0 sinon.
CREATE FUNCTION fn_peut_acceder(p_id_utilisateur CHAR(12), p_id_projet CHAR(12))
RETURNS TINYINT
READS SQL DATA
BEGIN
    RETURN fn_niveau_utilisateur(p_id_utilisateur) >= COALESCE((SELECT niveau_acces FROM projet WHERE id = p_id_projet), 0);
END$$

-- PROCÉDURE d'ACTION paramétrée : souscrit un abonnement. L'abonnement en cours est remplacé immédiatement.
-- Le prix, le nom et la date de fin sont fixés par le trigger trg_souscription_bi (jamais par l'appelant).
CREATE PROCEDURE sp_souscrire(IN p_id_utilisateur CHAR(12), IN p_id_abonnement INT UNSIGNED, OUT p_id_souscription INT UNSIGNED)
BEGIN
    UPDATE souscription
       SET statut = 'terminee', date_fin = NOW()
     WHERE id_utilisateur = p_id_utilisateur
       AND statut IN ('active', 'resiliee')
       AND date_fin > NOW();

    INSERT INTO souscription (id_utilisateur, id_abonnement, date_debut)
    VALUES (p_id_utilisateur, p_id_abonnement, NOW());

    SET p_id_souscription = LAST_INSERT_ID();
END$$

-- PROCÉDURE d'ACTION paramétrée : résilie l'abonnement en cours (pas de renouvellement ; l'accès reste ouvert
-- jusqu'à la date de fin déjà payée).
CREATE PROCEDURE sp_resilier(IN p_id_utilisateur CHAR(12))
BEGIN
    UPDATE souscription
       SET statut = 'resiliee'
     WHERE id_utilisateur = p_id_utilisateur
       AND statut = 'active'
       AND date_fin > NOW();
END$$

-- PROCÉDURE de CONSULTATION paramétrée : l'abonnement en cours d'un joueur (zéro ligne s'il est au niveau gratuit).
CREATE PROCEDURE sp_abonnement_actuel(IN p_id_utilisateur CHAR(12))
BEGIN
    SELECT id, id_abonnement, nom_abonnement, niveau, prix, date_debut, date_fin, statut
    FROM souscription
    WHERE id_utilisateur = p_id_utilisateur
      AND statut IN ('active', 'resiliee')
      AND date_fin > NOW()
    ORDER BY niveau DESC, date_fin DESC
    LIMIT 1;
END$$

-- PROCÉDURE de CONSULTATION paramétrée : historique des abonnements d'un joueur, du plus récent au plus ancien.
-- « etat » tient compte de la date : un abonnement dont la date de fin est passée est « terminee ».
CREATE PROCEDURE sp_souscriptions_utilisateur(IN p_id_utilisateur CHAR(12))
BEGIN
    SELECT id, nom_abonnement, niveau, prix, date_debut, date_fin,
           CASE WHEN date_fin <= NOW() THEN 'terminee' ELSE statut END AS etat
    FROM souscription
    WHERE id_utilisateur = p_id_utilisateur
    ORDER BY date_debut DESC, id DESC;
END$$

-- PROCÉDURE de CONSULTATION paramétrée : jeux accessibles avec une formule (niveau du jeu <= niveau de la formule).
CREATE PROCEDURE sp_jeux_abonnement(IN p_id_abonnement INT UNSIGNED)
BEGIN
    SELECT p.id, p.nom, p.code_categorie, p.niveau_acces
    FROM projet p
    INNER JOIN abonnement a ON a.id = p_id_abonnement
    WHERE p.niveau_acces <= a.niveau
    ORDER BY p.niveau_acces, p.nom;
END$$

-- TRIGGER de CALCUL et de CONTRÔLE : à chaque souscription, le serveur recopie le nom, le niveau et le prix de la
-- formule choisie, fixe la durée à un mois et refuse une formule inconnue ou retirée de la vente.
CREATE TRIGGER trg_souscription_bi BEFORE INSERT ON souscription
FOR EACH ROW
BEGIN
    DECLARE v_nom    VARCHAR(40);
    DECLARE v_niveau TINYINT UNSIGNED;
    DECLARE v_prix   DECIMAL(6,2);
    DECLARE v_actif  TINYINT(1);

    SELECT nom, niveau, prix_mensuel, actif INTO v_nom, v_niveau, v_prix, v_actif
    FROM abonnement WHERE id = NEW.id_abonnement;

    IF v_nom IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Abonnement introuvable.';
    END IF;
    IF v_actif = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cet abonnement n''est plus proposé.';
    END IF;

    SET NEW.nom_abonnement = v_nom;
    SET NEW.niveau   = v_niveau;
    SET NEW.prix     = v_prix;
    SET NEW.statut   = 'active';
    SET NEW.date_fin = DATE_ADD(NEW.date_debut, INTERVAL 1 MONTH);
END$$

-- TRIGGERS de CONTRÔLE : le niveau d'accès d'un jeu doit exister (de 0 au niveau de la formule la plus haute).
CREATE TRIGGER trg_projet_bi BEFORE INSERT ON projet
FOR EACH ROW
BEGIN
    IF NEW.niveau_acces > COALESCE((SELECT MAX(niveau) FROM abonnement), 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Niveau d''accès inconnu : il dépasse la formule la plus haute.';
    END IF;
END$$

CREATE TRIGGER trg_projet_bu BEFORE UPDATE ON projet
FOR EACH ROW
BEGIN
    IF NEW.niveau_acces > COALESCE((SELECT MAX(niveau) FROM abonnement), 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Niveau d''accès inconnu : il dépasse la formule la plus haute.';
    END IF;
END$$

DELIMITER ;

-- endregion
