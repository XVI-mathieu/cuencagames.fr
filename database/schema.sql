-- ============================================================================
--  Base de données du site (MySQL 8 / MariaDB 10.5+), jeu de caractères utf8mb4
--
--  Principe de la boutique : des ABONNEMENTS (Essentiel, Premium, Ultime). Chaque jeu demande un niveau
--  d'abonnement (0 = gratuit pour tous) ; un joueur accède aux jeux dont le niveau est inférieur ou égal
--  au niveau de son abonnement en cours. Boutique de démonstration : aucun paiement réel.
--
--  Version 2 : PANIER. Le joueur remplit un panier (une formule, une ou plusieurs durées de 1, 3, 6 ou 12 mois,
--  chacune en plusieurs exemplaires). Le panier vit en session (PHP) ; à la validation il devient une COMMANDE
--  (tables commande et ligne_commande) puis un abonnement. Les prix sont toujours calculés par la base
--  (fonction fn_prix_offre), jamais par PHP.
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

-- Commandes de la boutique : une commande = un panier validé (une seule formule, plusieurs lignes de durées).
-- nom_abonnement, total et mois_total sont remplis par les triggers / procédures (jamais par PHP).
CREATE TABLE IF NOT EXISTS commande (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_utilisateur   CHAR(12)      NOT NULL,
    id_abonnement    INT UNSIGNED  NOT NULL,
    nom_abonnement   VARCHAR(40)   NOT NULL DEFAULT '',     -- rempli par le trigger trg_commande_bi
    statut           ENUM('en_cours','validee') NOT NULL DEFAULT 'en_cours',
    mois_total       SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- somme (durée x quantité) des lignes, fixée à la validation
    total            DECIMAL(8,2)  NOT NULL DEFAULT 0.00,   -- fixé à la validation par fn_total_commande
    cree_le          DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commande_utilisateur (id_utilisateur),
    CONSTRAINT fk_commande_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_abonnement  FOREIGN KEY (id_abonnement)  REFERENCES abonnement (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lignes d'une commande : une durée (en mois) et le nombre d'exemplaires. Le prix unitaire est recopié par le trigger
-- trg_ligne_commande_bi (un changement de tarif ne modifie pas les commandes passées).
CREATE TABLE IF NOT EXISTS ligne_commande (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_commande      INT UNSIGNED  NOT NULL,
    duree_mois       TINYINT UNSIGNED NOT NULL,             -- 1, 3, 6 ou 12 (contrôlé par fn_prix_offre)
    quantite         TINYINT UNSIGNED NOT NULL,             -- de 1 à 12
    prix_unitaire    DECIMAL(8,2)  NOT NULL DEFAULT 0.00,   -- rempli par le trigger
    PRIMARY KEY (id),
    UNIQUE KEY uq_ligne_commande_duree (id_commande, duree_mois),
    CONSTRAINT fk_ligne_commande_commande FOREIGN KEY (id_commande) REFERENCES commande (id) ON DELETE CASCADE,
    CONSTRAINT ck_ligne_commande_quantite CHECK (quantite BETWEEN 1 AND 12)
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
    date_fin         DATETIME      NOT NULL,                -- rempli par le trigger : début + durée
    statut           ENUM('active','resiliee','terminee') NOT NULL DEFAULT 'active',
    duree_mois       SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- rempli par le trigger : 1, ou la durée totale de la commande
    id_commande      INT UNSIGNED  NULL,                    -- commande à l'origine de l'abonnement (NULL : abonnement direct)
    PRIMARY KEY (id),
    KEY idx_souscription_utilisateur (id_utilisateur),
    CONSTRAINT fk_souscription_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE,
    CONSTRAINT fk_souscription_abonnement  FOREIGN KEY (id_abonnement)  REFERENCES abonnement (id),
    CONSTRAINT fk_souscription_commande    FOREIGN KEY (id_commande)    REFERENCES commande (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- endregion

-- region MIGRATION (bases créées avant la version 2 : ajoute les colonnes du panier à souscription)

DROP PROCEDURE IF EXISTS pr_migration_v2;

DELIMITER $$

-- Procédure temporaire : MySQL et MariaDB n'ont pas la même syntaxe pour « ADD COLUMN IF NOT EXISTS »,
-- on teste donc l'existence de la colonne dans information_schema. Sans effet sur une base neuve.
CREATE PROCEDURE pr_migration_v2()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'souscription' AND COLUMN_NAME = 'duree_mois') THEN
        ALTER TABLE souscription ADD COLUMN duree_mois SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER statut;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'souscription' AND COLUMN_NAME = 'id_commande') THEN
        ALTER TABLE souscription ADD COLUMN id_commande INT UNSIGNED NULL AFTER duree_mois;
        ALTER TABLE souscription ADD CONSTRAINT fk_souscription_commande
            FOREIGN KEY (id_commande) REFERENCES commande (id) ON DELETE SET NULL;
    END IF;
END$$

DELIMITER ;

CALL pr_migration_v2();
DROP PROCEDURE pr_migration_v2;

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
DROP TRIGGER   IF EXISTS trg_commande_bi;
DROP TRIGGER   IF EXISTS trg_ligne_commande_bi;
DROP TRIGGER   IF EXISTS trg_projet_bi;
DROP TRIGGER   IF EXISTS trg_projet_bu;
DROP PROCEDURE IF EXISTS sp_souscrire;
DROP PROCEDURE IF EXISTS sp_creer_commande;
DROP PROCEDURE IF EXISTS sp_ajouter_ligne_commande;
DROP PROCEDURE IF EXISTS sp_valider_commande;
DROP PROCEDURE IF EXISTS sp_commandes_utilisateur;
DROP PROCEDURE IF EXISTS sp_resilier;
DROP PROCEDURE IF EXISTS sp_abonnement_actuel;
DROP PROCEDURE IF EXISTS sp_souscriptions_utilisateur;
DROP PROCEDURE IF EXISTS sp_jeux_abonnement;
DROP FUNCTION  IF EXISTS fn_niveau_utilisateur;
DROP FUNCTION  IF EXISTS fn_peut_acceder;
DROP FUNCTION  IF EXISTS fn_prix_offre;
DROP FUNCTION  IF EXISTS fn_total_commande;

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

-- FONCTION stockée paramétrée : prix d'une offre = formule x durée, remise de fidélité comprise
-- (1 mois : 0 %, 3 mois : 5 %, 6 mois : 10 %, 12 mois : 15 %). NULL si la formule n'existe pas ou si la durée n'est pas proposée.
CREATE FUNCTION fn_prix_offre(p_id_abonnement INT UNSIGNED, p_duree_mois TINYINT UNSIGNED)
RETURNS DECIMAL(8,2)
READS SQL DATA
BEGIN
    DECLARE v_remise TINYINT UNSIGNED;
    SET v_remise = CASE p_duree_mois WHEN 1 THEN 0 WHEN 3 THEN 5 WHEN 6 THEN 10 WHEN 12 THEN 15 ELSE NULL END;
    IF v_remise IS NULL THEN
        RETURN NULL;
    END IF;
    RETURN (SELECT ROUND(prix_mensuel * p_duree_mois * (100 - v_remise) / 100, 2) FROM abonnement WHERE id = p_id_abonnement);
END$$

-- FONCTION stockée paramétrée : total d'une commande = somme (quantité x prix unitaire) de ses lignes.
CREATE FUNCTION fn_total_commande(p_id_commande INT UNSIGNED)
RETURNS DECIMAL(8,2)
READS SQL DATA
BEGIN
    RETURN COALESCE((SELECT SUM(quantite * prix_unitaire) FROM ligne_commande WHERE id_commande = p_id_commande), 0);
END$$

-- FONCTION stockée paramétrée : 1 si le joueur peut jouer au jeu (niveau suffisant), 0 sinon.
CREATE FUNCTION fn_peut_acceder(p_id_utilisateur CHAR(12), p_id_projet CHAR(12))
RETURNS TINYINT
READS SQL DATA
BEGIN
    RETURN fn_niveau_utilisateur(p_id_utilisateur) >= COALESCE((SELECT niveau_acces FROM projet WHERE id = p_id_projet), 0);
END$$

-- PROCÉDURE d'ACTION paramétrée : souscrit un abonnement. L'abonnement en cours est remplacé immédiatement.
-- Le prix, le nom, la durée et la date de fin sont fixés par le trigger trg_souscription_bi (jamais par l'appelant).
-- p_id_commande : commande validée à l'origine de l'abonnement (NULL : abonnement direct d'un mois au tarif mensuel).
CREATE PROCEDURE sp_souscrire(IN p_id_utilisateur CHAR(12), IN p_id_abonnement INT UNSIGNED, IN p_id_commande INT UNSIGNED,
                              OUT p_id_souscription INT UNSIGNED)
BEGIN
    UPDATE souscription
       SET statut = 'terminee', date_fin = NOW()
     WHERE id_utilisateur = p_id_utilisateur
       AND statut IN ('active', 'resiliee')
       AND date_fin > NOW();

    INSERT INTO souscription (id_utilisateur, id_abonnement, id_commande, date_debut)
    VALUES (p_id_utilisateur, p_id_abonnement, p_id_commande, NOW());

    SET p_id_souscription = LAST_INSERT_ID();
END$$

-- PROCÉDURE d'ACTION paramétrée : ouvre une commande (vide) pour une formule ; retourne son numéro.
-- Le trigger trg_commande_bi refuse une formule inconnue ou retirée de la vente.
CREATE PROCEDURE sp_creer_commande(IN p_id_utilisateur CHAR(12), IN p_id_abonnement INT UNSIGNED, OUT p_id_commande INT UNSIGNED)
BEGIN
    INSERT INTO commande (id_utilisateur, id_abonnement, cree_le)
    VALUES (p_id_utilisateur, p_id_abonnement, NOW());

    SET p_id_commande = LAST_INSERT_ID();
END$$

-- PROCÉDURE d'ACTION paramétrée : ajoute une ligne (durée x quantité) à une commande en cours.
-- Le trigger trg_ligne_commande_bi recopie le prix unitaire et refuse une durée ou une quantité invalide.
CREATE PROCEDURE sp_ajouter_ligne_commande(IN p_id_commande INT UNSIGNED, IN p_duree_mois TINYINT UNSIGNED, IN p_quantite TINYINT UNSIGNED)
BEGIN
    INSERT INTO ligne_commande (id_commande, duree_mois, quantite)
    VALUES (p_id_commande, p_duree_mois, p_quantite);
END$$

-- PROCÉDURE d'ACTION paramétrée : valide une commande (total et durée calculés par la base), puis souscrit l'abonnement
-- correspondant avec sp_souscrire : les jeux de la formule sont débloqués pour toute la durée commandée.
CREATE PROCEDURE sp_valider_commande(IN p_id_commande INT UNSIGNED, OUT p_id_souscription INT UNSIGNED)
BEGIN
    DECLARE v_utilisateur CHAR(12);
    DECLARE v_abonnement  INT UNSIGNED;
    DECLARE v_statut      VARCHAR(10);

    SELECT id_utilisateur, id_abonnement, statut INTO v_utilisateur, v_abonnement, v_statut
    FROM commande WHERE id = p_id_commande;

    IF v_statut IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Commande introuvable.';
    END IF;
    IF v_statut <> 'en_cours' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cette commande est déjà validée.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM ligne_commande WHERE id_commande = p_id_commande) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La commande ne contient aucune ligne.';
    END IF;

    UPDATE commande
       SET statut     = 'validee',
           total      = fn_total_commande(p_id_commande),
           mois_total = (SELECT SUM(duree_mois * quantite) FROM ligne_commande WHERE id_commande = p_id_commande)
     WHERE id = p_id_commande;

    CALL sp_souscrire(v_utilisateur, v_abonnement, p_id_commande, p_id_souscription);
END$$

-- PROCÉDURE de CONSULTATION paramétrée : commandes validées d'un joueur, de la plus récente à la plus ancienne.
CREATE PROCEDURE sp_commandes_utilisateur(IN p_id_utilisateur CHAR(12))
BEGIN
    SELECT id, nom_abonnement, mois_total, total, cree_le
    FROM commande
    WHERE id_utilisateur = p_id_utilisateur AND statut = 'validee'
    ORDER BY cree_le DESC, id DESC;
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
    SELECT id, id_abonnement, nom_abonnement, niveau, prix, duree_mois, date_debut, date_fin, statut
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
    SELECT id, nom_abonnement, niveau, prix, duree_mois, date_debut, date_fin,
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
-- formule choisie, fixe la durée et la date de fin, et refuse une formule inconnue ou retirée de la vente.
--  - abonnement direct (sans commande) : un mois, au tarif mensuel ;
--  - abonnement issu d'une commande : durée totale et montant total de la commande validée.
CREATE TRIGGER trg_souscription_bi BEFORE INSERT ON souscription
FOR EACH ROW
BEGIN
    DECLARE v_nom    VARCHAR(40);
    DECLARE v_niveau TINYINT UNSIGNED;
    DECLARE v_prix   DECIMAL(8,2);
    DECLARE v_actif  TINYINT(1);
    DECLARE v_mois   SMALLINT UNSIGNED DEFAULT 1;
    DECLARE v_statut VARCHAR(10);

    SELECT nom, niveau, prix_mensuel, actif INTO v_nom, v_niveau, v_prix, v_actif
    FROM abonnement WHERE id = NEW.id_abonnement;

    IF v_nom IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Abonnement introuvable.';
    END IF;
    IF v_actif = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cet abonnement n''est plus proposé.';
    END IF;

    IF NEW.id_commande IS NOT NULL THEN
        SELECT statut, mois_total, total INTO v_statut, v_mois, v_prix
        FROM commande WHERE id = NEW.id_commande;
        IF v_statut IS NULL OR v_statut <> 'validee' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La commande doit être validée avant de souscrire.';
        END IF;
    END IF;

    SET NEW.nom_abonnement = v_nom;
    SET NEW.niveau     = v_niveau;
    SET NEW.prix       = v_prix;
    SET NEW.duree_mois = v_mois;
    SET NEW.statut     = 'active';
    SET NEW.date_fin   = DATE_ADD(NEW.date_debut, INTERVAL v_mois MONTH);
END$$

-- TRIGGER de CONTRÔLE : une commande ne s'ouvre que pour une formule qui existe et qui est encore proposée.
CREATE TRIGGER trg_commande_bi BEFORE INSERT ON commande
FOR EACH ROW
BEGIN
    DECLARE v_nom   VARCHAR(40);
    DECLARE v_actif TINYINT(1);

    SELECT nom, actif INTO v_nom, v_actif FROM abonnement WHERE id = NEW.id_abonnement;

    IF v_nom IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Abonnement introuvable.';
    END IF;
    IF v_actif = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cet abonnement n''est plus proposé.';
    END IF;

    SET NEW.nom_abonnement = v_nom;
    SET NEW.statut     = 'en_cours';
    SET NEW.mois_total = 0;
    SET NEW.total      = 0;
END$$

-- TRIGGER de CALCUL et de CONTRÔLE : à chaque ligne de commande, le serveur recopie le prix de l'offre (fn_prix_offre),
-- et refuse une durée non proposée, une commande déjà validée ou une commande de plus de 36 mois au total.
CREATE TRIGGER trg_ligne_commande_bi BEFORE INSERT ON ligne_commande
FOR EACH ROW
BEGIN
    DECLARE v_abonnement INT UNSIGNED;
    DECLARE v_statut     VARCHAR(10);
    DECLARE v_prix       DECIMAL(8,2);
    DECLARE v_mois       INT;

    SELECT id_abonnement, statut INTO v_abonnement, v_statut FROM commande WHERE id = NEW.id_commande;

    IF v_statut IS NULL OR v_statut <> 'en_cours' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cette commande n''accepte plus de lignes.';
    END IF;

    SET v_prix = fn_prix_offre(v_abonnement, NEW.duree_mois);
    IF v_prix IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cette durée n''est pas proposée (1, 3, 6 ou 12 mois).';
    END IF;
    IF NEW.quantite < 1 OR NEW.quantite > 12 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La quantité doit être comprise entre 1 et 12.';
    END IF;

    SELECT COALESCE(SUM(duree_mois * quantite), 0) INTO v_mois FROM ligne_commande WHERE id_commande = NEW.id_commande;
    IF v_mois + NEW.duree_mois * NEW.quantite > 36 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une commande ne peut pas dépasser 36 mois d''abonnement.';
    END IF;

    SET NEW.prix_unitaire = v_prix;
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
