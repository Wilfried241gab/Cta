<?php
// ============================================================
//  CTA Lambaréné — configuration de la connexion à la base
//  Modifiez uniquement les valeurs à droite des flèches.
// ============================================================
return [
    'pilote'          => 'mysql',       // mysql (XAMPP / phpMyAdmin)
    'hote'            => 'localhost',   // serveur MySQL / MariaDB
    'port'            => 3306,
    'base'            => 'cta',         // nom de la base (créée par base_cta.sql)
    'utilisateur'     => 'root',        // XAMPP : root (sans mot de passe) par défaut
    'mot_de_passe'    => '',
    // Adresses autorisées à utiliser l'application ([] = tout le réseau local).
    // Exemple : ['127.0.0.1', '::1', '192.168.1.']  (préfixes acceptés)
    'reseaux_autorises' => [],
    // Mot de passe provisoire donné aux comptes à leur création (chacun doit le changer à la première connexion)
    'mot_de_passe_initial' => 'cta2026',
    // Durée d'inactivité (en heures) au-delà de laquelle le serveur ferme la session
    'session_heures'  => 24,
    // true = redirige vers https:// (à activer quand HTTPS est installé, voir DURCISSEMENT.txt)
    'forcer_https'    => false,
    // Sauvegardes : dossier de copie hors de l'ordinateur (clé USB, disque réseau, dossier synchronisé). '' = aucune copie
    'copie_externe'   => '',
    // Durée de conservation des sauvegardes chiffrées, en jours
    'sauvegarde_jours' => 60,
    // Nombre de jours d'historique journalier conservés dans la base
    'conservation_jours' => 60,
    // Utilisé seulement pour les tests automatiques (ne pas modifier)
    'sqlite_fichier'  => __DIR__ . '/data/cta.sqlite',
];
