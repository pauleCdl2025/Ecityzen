-- Base de données e-cityzen Gabon
-- Créer d'abord la base de données dans phpMyAdmin ou via MySQL

CREATE DATABASE IF NOT EXISTS ecityzen_gabon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecityzen_gabon;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telephone VARCHAR(20) UNIQUE NOT NULL,
    role ENUM('citoyen', 'commercant', 'agent', 'manager', 'hopital', 'superadmin', 'chef_quartier', 'societe_travaux') NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    localisation VARCHAR(100),
    secteur VARCHAR(100),
    entreprise VARCHAR(100),
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME,
    INDEX idx_role (role),
    INDEX idx_telephone (telephone),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des signalements
CREATE TABLE IF NOT EXISTS signalements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    sous_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    localisation VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    photo VARCHAR(255),
    statut ENUM('en_attente', 'en_cours', 'resolu', 'rejete') DEFAULT 'en_attente',
    agent_assigné_id INT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_assigné_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_statut (statut),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_date (date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des demandes administratives
CREATE TABLE IF NOT EXISTS demandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    service VARCHAR(100) NOT NULL,
    motif TEXT,
    cout DECIMAL(10, 2) NOT NULL,
    statut ENUM('en_attente', 'en_traitement', 'valide', 'rejete', 'annule') DEFAULT 'en_attente',
    agent_assigné_id INT,
    numero_dossier VARCHAR(50) UNIQUE NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    date_validation DATETIME,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_assigné_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_statut (statut),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_numero (numero_dossier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des paiements
CREATE TABLE IF NOT EXISTS paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    demande_id INT,
    type_paiement VARCHAR(50) NOT NULL,
    montant DECIMAL(10, 2) NOT NULL,
    methode ENUM('clickpay', 'airtel', 'moov', 'carte', 'especes') NOT NULL,
    reference_paiement VARCHAR(100) UNIQUE,
    statut ENUM('en_attente', 'confirme', 'echec', 'rembourse') DEFAULT 'en_attente',
    date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_confirmation DATETIME,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE SET NULL,
    INDEX idx_statut (statut),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_reference (reference_paiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des licences commerciales
CREATE TABLE IF NOT EXISTS licences_commerciales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commercant_id INT NOT NULL,
    type_licence VARCHAR(100) NOT NULL,
    montant DECIMAL(10, 2) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('active', 'expiree', 'renouvelee') DEFAULT 'active',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commercant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_statut (statut),
    INDEX idx_commercant (commercant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des emplacements de marché
CREATE TABLE IF NOT EXISTS emplacements_marche (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commercant_id INT NOT NULL,
    marche VARCHAR(100) NOT NULL,
    emplacement VARCHAR(50) NOT NULL,
    montant_mensuel DECIMAL(10, 2) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE,
    statut ENUM('actif', 'expire', 'resilie') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commercant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_statut (statut),
    INDEX idx_commercant (commercant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des missions agents
CREATE TABLE IF NOT EXISTS missions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT NOT NULL,
    type_mission VARCHAR(100) NOT NULL,
    description TEXT,
    localisation VARCHAR(255),
    date_mission DATETIME NOT NULL,
    statut ENUM('planifiee', 'en_cours', 'terminee', 'annulee') DEFAULT 'planifiee',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_statut (statut),
    INDEX idx_agent (agent_id),
    INDEX idx_date (date_mission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des statistiques (pour les managers)
CREATE TABLE IF NOT EXISTS statistiques (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date_stat DATE NOT NULL,
    type_stat VARCHAR(50) NOT NULL,
    valeur DECIMAL(15, 2) NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stat (date_stat, type_stat),
    INDEX idx_date (date_stat),
    INDEX idx_type (type_stat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table du budget municipal
CREATE TABLE IF NOT EXISTS budget_municipal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exercice_budgetaire YEAR NOT NULL,
    poste_budgetaire VARCHAR(100) NOT NULL,
    categorie ENUM('fonctionnement', 'investissement') NOT NULL,
    budget_initial DECIMAL(15, 2) NOT NULL,
    budget_rectificatif DECIMAL(15, 2) DEFAULT 0,
    depenses_engagees DECIMAL(15, 2) DEFAULT 0,
    solde_disponible DECIMAL(15, 2) GENERATED ALWAYS AS (budget_initial + budget_rectificatif - depenses_engagees) STORED,
    taux_execution DECIMAL(5, 2) GENERATED ALWAYS AS ((depenses_engagees / (budget_initial + budget_rectificatif)) * 100) STORED,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exercice (exercice_budgetaire),
    INDEX idx_poste (poste_budgetaire),
    INDEX idx_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des dépenses budgétaires détaillées
CREATE TABLE IF NOT EXISTS depenses_budgetaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    fournisseur VARCHAR(255),
    montant DECIMAL(15, 2) NOT NULL,
    description TEXT,
    date_depense DATE NOT NULL,
    reference_marche VARCHAR(100),
    FOREIGN KEY (budget_id) REFERENCES budget_municipal(id) ON DELETE CASCADE,
    INDEX idx_budget (budget_id),
    INDEX idx_date (date_depense)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des calendriers d'exécution budgétaire
CREATE TABLE IF NOT EXISTS calendrier_execution (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    trimestre ENUM('T1', 'T2', 'T3', 'T4') NOT NULL,
    montant_prevu DECIMAL(15, 2) NOT NULL,
    montant_realise DECIMAL(15, 2) DEFAULT 0,
    FOREIGN KEY (budget_id) REFERENCES budget_municipal(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trimestre (budget_id, trimestre),
    INDEX idx_budget (budget_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des commentaires sur le budget
CREATE TABLE IF NOT EXISTS commentaires_budget (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    commentaire TEXT NOT NULL,
    statut ENUM('en_attente', 'approuve', 'rejete') DEFAULT 'en_attente',
    reponse_mairie TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budget_municipal(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_budget (budget_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des chantiers de travaux publics
CREATE TABLE IF NOT EXISTS chantiers_travaux (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_chantier VARCHAR(255) NOT NULL,
    societe_travaux_id INT,
    type_travaux ENUM('voirie', 'assainissement', 'eclairage', 'batiment', 'autre') NOT NULL,
    description TEXT NOT NULL,
    localisation_gps TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    zone_polygone TEXT,
    date_debut DATE NOT NULL,
    date_fin_prevue DATE NOT NULL,
    date_fin_reelle DATE,
    horaires_travaux VARCHAR(100),
    impact_circulation ENUM('route_bloquee', 'circulation_alternee', 'deviation', 'ralentissements', 'aucun_impact') NOT NULL,
    itineraire_alternatif TEXT,
    statut_chantier ENUM('a_venir', 'en_cours', 'suspendu', 'termine') DEFAULT 'a_venir',
    numero_chantier VARCHAR(50) UNIQUE NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (societe_travaux_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_statut (statut_chantier),
    INDEX idx_date_debut (date_debut),
    INDEX idx_type (type_travaux)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des photos de progression des chantiers
CREATE TABLE IF NOT EXISTS photos_chantiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chantier_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    description TEXT,
    date_photo DATE NOT NULL,
    FOREIGN KEY (chantier_id) REFERENCES chantiers_travaux(id) ON DELETE CASCADE,
    INDEX idx_chantier (chantier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des signalements chefs de quartier
CREATE TABLE IF NOT EXISTS signalements_chefs_quartier (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chef_quartier_id INT NOT NULL,
    nom_quartier VARCHAR(100) NOT NULL,
    type_signalement ENUM('besoin_infrastructure', 'probleme_securite', 'evenement_social', 'demande_collective', 'autre') NOT NULL,
    description_detaillee TEXT NOT NULL,
    population_concernee INT,
    urgence ENUM('basse', 'moyenne', 'haute', 'critique') NOT NULL,
    photos_videos TEXT,
    localisation_gps TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    zone_polygone TEXT,
    numero_signalement VARCHAR(50) UNIQUE NOT NULL,
    statut_traitement ENUM('nouveau', 'en_etude', 'en_cours', 'resolu', 'archive') DEFAULT 'nouveau',
    retour_mairie TEXT,
    date_signalement DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chef_quartier_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_statut (statut_traitement),
    INDEX idx_urgence (urgence),
    INDEX idx_quartier (nom_quartier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des réservations de stands marchés
CREATE TABLE IF NOT EXISTS reservations_marches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    marche VARCHAR(100) NOT NULL,
    numero_stand VARCHAR(50) NOT NULL,
    type_stand ENUM('alimentaire', 'textile', 'artisanat', 'electronique', 'autre') NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    horaire VARCHAR(50),
    tarif DECIMAL(10, 2) NOT NULL,
    mode_paiement ENUM('mobile_money', 'carte', 'especes') NOT NULL,
    numero_reservation VARCHAR(50) UNIQUE NOT NULL,
    qr_code_acces VARCHAR(255),
    statut_reservation ENUM('confirmee', 'en_attente', 'annulee', 'expiree') DEFAULT 'en_attente',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_statut (statut_reservation),
    INDEX idx_marche (marche),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des stands de marché (configuration)
CREATE TABLE IF NOT EXISTS stands_marche (
    id INT PRIMARY KEY AUTO_INCREMENT,
    marche VARCHAR(100) NOT NULL,
    numero_stand VARCHAR(50) NOT NULL,
    type_stand ENUM('alimentaire', 'textile', 'artisanat', 'electronique', 'autre') NOT NULL,
    taille VARCHAR(50),
    equipements TEXT,
    tarif_journalier DECIMAL(10, 2),
    tarif_hebdomadaire DECIMAL(10, 2),
    tarif_mensuel DECIMAL(10, 2),
    statut ENUM('disponible', 'occupe', 'maintenance') DEFAULT 'disponible',
    coordonnees_x INT,
    coordonnees_y INT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stand (marche, numero_stand),
    INDEX idx_marche (marche),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_notification ENUM('circulation', 'evenement', 'alerte', 'administrative', 'commerciale') NOT NULL,
    titre VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    categorie ENUM('urgente', 'importante', 'informative') NOT NULL,
    canal_diffusion SET('push', 'sms', 'email') NOT NULL,
    cible_destinataires TEXT,
    geolocalisation_pertinente TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    rayon_km INT,
    lien_action VARCHAR(255),
    numero_notification VARCHAR(50) UNIQUE NOT NULL,
    date_envoi DATETIME,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type_notification),
    INDEX idx_categorie (categorie),
    INDEX idx_date_envoi (date_envoi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des notifications utilisateurs (statut de lecture)
CREATE TABLE IF NOT EXISTS notifications_utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    statut_lecture ENUM('non_lu', 'lu') DEFAULT 'non_lu',
    date_lecture DATETIME,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notif_user (notification_id, utilisateur_id),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_statut (statut_lecture)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des préférences de notifications utilisateurs
CREATE TABLE IF NOT EXISTS preferences_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL UNIQUE,
    notifications_circulation BOOLEAN DEFAULT TRUE,
    notifications_evenements BOOLEAN DEFAULT TRUE,
    notifications_alertes BOOLEAN DEFAULT TRUE,
    notifications_administratives BOOLEAN DEFAULT TRUE,
    notifications_commerciales BOOLEAN DEFAULT TRUE,
    canal_push BOOLEAN DEFAULT TRUE,
    canal_sms BOOLEAN DEFAULT FALSE,
    canal_email BOOLEAN DEFAULT TRUE,
    geolocalisation_active BOOLEAN DEFAULT FALSE,
    rayon_km INT DEFAULT 5,
    horaire_ne_pas_deranger_debut TIME,
    horaire_ne_pas_deranger_fin TIME,
    quartiers_interet TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des feedbacks et notations
CREATE TABLE IF NOT EXISTS feedbacks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type_entite ENUM('incident', 'demande', 'chantier', 'service_general') NOT NULL,
    entite_id INT NOT NULL,
    note INT CHECK (note >= 1 AND note <= 5),
    commentaire TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_type (type_entite, entite_id),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des messages d'assistance
CREATE TABLE IF NOT EXISTS messages_assistance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type_message ENUM('chat', 'email', 'rappel') NOT NULL,
    sujet VARCHAR(255),
    message TEXT NOT NULL,
    page_contexte VARCHAR(255),
    statut ENUM('nouveau', 'en_traitement', 'resolu', 'ferme') DEFAULT 'nouveau',
    agent_id INT,
    reponse TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_reponse DATETIME,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_statut (statut),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des FAQ
CREATE TABLE IF NOT EXISTS faq (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categorie VARCHAR(100) NOT NULL,
    page_contexte VARCHAR(255),
    question TEXT NOT NULL,
    reponse TEXT NOT NULL,
    ordre_affichage INT DEFAULT 0,
    actif BOOLEAN DEFAULT TRUE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categorie (categorie),
    INDEX idx_page (page_contexte),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Supprimer les utilisateurs de démonstration existants (si présents)
-- Cela évite les erreurs de duplication lors de la réinstallation
DELETE FROM utilisateurs WHERE telephone IN ('+241 06 12 34 56', '+241 07 23 45 67', '+241 05 34 56 78', '+241 04 45 67 89', '+241 03 56 78 90', '+241 02 67 89 01')
   OR (email IS NOT NULL AND email IN ('pierre.mbang@example.com', 'marie.obame@example.com', 'marie.ngoua@example.com', 'paul.mengue@example.com', 'claude.mintsa@example.com'));

-- Insertion des utilisateurs de démonstration
INSERT INTO utilisateurs (nom, email, telephone, role, mot_de_passe, localisation, secteur, entreprise) VALUES
('MBANG Pierre', NULL, '+241 06 12 34 56', 'citoyen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', NULL, NULL),
('OBAME Marie', NULL, '+241 07 23 45 67', 'commercant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', NULL, 'Commerce Alimentaire'),
('NGOUA Marie', NULL, '+241 05 34 56 78', 'agent', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', 'Libreville-Sud', NULL),
('MENGUE Paul', NULL, '+241 04 45 67 89', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', NULL, NULL),
('Hôpital Central de Libreville', NULL, '+241 02 67 89 01', 'hopital', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', NULL, 'Hôpital Central'),
('MINTSA NGUEMA Claude', NULL, '+241 03 56 78 90', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Libreville', NULL, NULL)
ON DUPLICATE KEY UPDATE 
    nom = VALUES(nom),
    role = VALUES(role),
    mot_de_passe = VALUES(mot_de_passe),
    localisation = VALUES(localisation),
    secteur = VALUES(secteur),
    entreprise = VALUES(entreprise);

-- Note: Le mot de passe par défaut pour tous les utilisateurs de démo est "password"
-- En production, utilisez des mots de passe sécurisés différents pour chaque utilisateur
-- 
-- Comptes de démonstration :
-- Citoyen: +241 06 12 34 56 / password
-- Commerçant: +241 07 23 45 67 / password
-- Agent: +241 05 34 56 78 / password
-- Manager: +241 04 45 67 89 / password
-- Hôpital: +241 02 67 89 01 / password
-- Super Admin: +241 03 56 78 90 / password

