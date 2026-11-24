-- ============================================================
--  CTA Lambaréné — base de données (MySQL / MariaDB, XAMPP)
--  Import : phpMyAdmin > onglet « Importer » > choisir ce fichier > Exécuter
--  (inutile de créer la base à la main : ce fichier la crée)
-- ============================================================
CREATE DATABASE IF NOT EXISTS `cta` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cta`;

-- Documents de l'application : un par module + base patients/utilisateurs + journal d'audit
-- (shell, caisse, pharma, rh, ptme, audit). `version` empêche tout écrasement silencieux.
CREATE TABLE IF NOT EXISTS `cta_kv` (
  `cle`     VARCHAR(80)  NOT NULL,
  `valeur`  LONGTEXT     NOT NULL,
  `version` INT          NOT NULL DEFAULT 1,
  `maj`     DATETIME     NOT NULL,
  `poste`   VARCHAR(120) NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique : une copie par jour et par document (conservation réglée dans config.php)
CREATE TABLE IF NOT EXISTS `cta_histo` (
  `id`      INT          NOT NULL AUTO_INCREMENT,
  `cle`     VARCHAR(80)  NOT NULL,
  `jour`    DATE         NOT NULL,
  `valeur`  LONGTEXT     NOT NULL,
  `version` INT          NOT NULL,
  `maj`     DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_histo` (`cle`, `jour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Version du schéma (l'application sait se mettre à jour toute seule : elle ajoute ce qui manque, sans toucher aux données)
CREATE TABLE IF NOT EXISTS `cta_meta` (
  `cle`    VARCHAR(60)  NOT NULL,
  `valeur` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes : mots de passe hachés (password_hash), jamais envoyés au navigateur
CREATE TABLE IF NOT EXISTS `cta_user` (
  `id`          INT          NOT NULL,
  `nom`         VARCHAR(120) NOT NULL,
  `role`        VARCHAR(40)  NOT NULL,
  `actif`       TINYINT      NOT NULL DEFAULT 1,
  `hash`        VARCHAR(255) NULL,
  `legacy_salt` VARCHAR(64)  NULL,
  `legacy_hash` VARCHAR(100) NULL,
  `must`        TINYINT      NOT NULL DEFAULT 1,
  `fails`       INT          NOT NULL DEFAULT 0,
  `lock_until`  BIGINT       NOT NULL DEFAULT 0,
  `maj`         DATETIME     NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'audit : ajouts seulement, écrit par le serveur, chaîné par empreintes SHA-256
CREATE TABLE IF NOT EXISTS `cta_audit` (
  `n`  INT          NOT NULL AUTO_INCREMENT,
  `t`  VARCHAR(30)  NOT NULL,
  `u`  VARCHAR(120) NOT NULL,
  `r`  VARCHAR(40)  NOT NULL,
  `m`  VARCHAR(60)  NOT NULL,
  `a`  VARCHAR(200) NOT NULL,
  `d`  TEXT         NOT NULL,
  `ip` VARCHAR(60)  NULL,
  `h`  CHAR(64)     NOT NULL,
  PRIMARY KEY (`n`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compteurs (numéros de reçu attribués par le serveur, sans doublon)
CREATE TABLE IF NOT EXISTS `cta_seq` (
  `nom`    VARCHAR(40) NOT NULL,
  `valeur` INT         NOT NULL,
  PRIMARY KEY (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utilisateur dédié : créé par install_database.bat (ou durcir_mysql.bat), pas besoin de le faire ici.
