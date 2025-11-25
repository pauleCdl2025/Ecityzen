-- Script d'installation complète e-cityzen Gabon
-- Ce script crée toutes les tables ET insère les données de démonstration
-- À exécuter dans phpMyAdmin sur une base de données VIDE

USE ecityzen_gabon;

-- ============================================
-- PARTIE 1 : CRÉATION DES TABLES MANQUANTES
-- ============================================

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

-- ============================================
-- PARTIE 2 : DONNÉES DE DÉMONSTRATION
-- ============================================

-- Données de démonstration pour le budget municipal
INSERT INTO budget_municipal (exercice_budgetaire, poste_budgetaire, categorie, budget_initial, budget_rectificatif, depenses_engagees) VALUES
(2025, 'Voirie et infrastructures', 'investissement', 500000000, 0, 125000000),
(2025, 'Assainissement', 'investissement', 300000000, 50000000, 80000000),
(2025, 'Éclairage public', 'investissement', 150000000, 0, 45000000),
(2025, 'Salaires agents', 'fonctionnement', 800000000, 0, 200000000),
(2025, 'Maintenance équipements', 'fonctionnement', 200000000, 0, 60000000),
(2025, 'Services publics', 'fonctionnement', 400000000, 0, 120000000)
ON DUPLICATE KEY UPDATE budget_initial = VALUES(budget_initial);

-- Dépenses budgétaires détaillées
INSERT INTO depenses_budgetaires (budget_id, fournisseur, montant, description, date_depense, reference_marche) 
SELECT b.id, 'Entreprise ABC', 50000000, 'Réfection route principale', '2025-01-15', 'MCH-2025-001'
FROM budget_municipal b WHERE b.poste_budgetaire = 'Voirie et infrastructures' AND b.exercice_budgetaire = 2025
LIMIT 1
ON DUPLICATE KEY UPDATE montant = VALUES(montant);

-- Calendrier d'exécution
INSERT INTO calendrier_execution (budget_id, trimestre, montant_prevu, montant_realise)
SELECT b.id, 'T1', 125000000, 125000000
FROM budget_municipal b WHERE b.poste_budgetaire = 'Voirie et infrastructures' AND b.exercice_budgetaire = 2025
LIMIT 1
ON DUPLICATE KEY UPDATE montant_realise = VALUES(montant_realise);

-- Chantiers de travaux publics
INSERT INTO chantiers_travaux (nom_chantier, type_travaux, description, localisation_gps, latitude, longitude, date_debut, date_fin_prevue, impact_circulation, statut_chantier, numero_chantier) VALUES
('Réfection Avenue Léon Mba', 'voirie', 'Réfection complète de la chaussée et des trottoirs', 'Avenue Léon Mba, Libreville', 0.3901, 9.4540, '2025-01-10', '2025-03-31', 'circulation_alternee', 'en_cours', 'CHT-2025-000001'),
('Installation éclairage LED', 'eclairage', 'Remplacement des lampadaires par des LED', 'Quartier Nombakélé', 0.4100, 9.4700, '2025-02-01', '2025-04-30', 'ralentissements', 'en_cours', 'CHT-2025-000002'),
('Réseau assainissement', 'assainissement', 'Extension du réseau d\'assainissement', 'Quartier Akanda', 0.3800, 9.4400, '2025-03-01', '2025-06-30', 'route_bloquee', 'a_venir', 'CHT-2025-000003')
ON DUPLICATE KEY UPDATE statut_chantier = VALUES(statut_chantier);

-- Photos de chantiers
INSERT INTO photos_chantiers (chantier_id, photo_url, description, date_photo)
SELECT c.id, 'https://via.placeholder.com/800x600', 'Avancement travaux - 50%', '2025-01-20'
FROM chantiers_travaux c WHERE c.numero_chantier = 'CHT-2025-000001'
LIMIT 1
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Stands de marché
INSERT INTO stands_marche (marche, numero_stand, type_stand, taille, tarif_journalier, tarif_hebdomadaire, tarif_mensuel, statut) VALUES
('Marche Mont-Bouet', 'A-01', 'alimentaire', '3x2m', 5000, 30000, 120000, 'disponible'),
('Marche Mont-Bouet', 'A-02', 'alimentaire', '3x2m', 5000, 30000, 120000, 'disponible'),
('Marche Mont-Bouet', 'B-01', 'textile', '2x2m', 4000, 25000, 100000, 'disponible'),
('Marche Nkembo', 'A-01', 'alimentaire', '3x2m', 5000, 30000, 120000, 'disponible'),
('Marche Nkembo', 'A-02', 'alimentaire', '3x2m', 5000, 30000, 120000, 'occupe'),
('Marche Oloumi', 'A-01', 'artisanat', '2x2m', 3500, 20000, 80000, 'disponible')
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Notifications
INSERT INTO notifications (type_notification, titre, message, categorie, canal_diffusion, date_envoi, numero_notification) VALUES
('circulation', 'Travaux en cours - Avenue Léon Mba', 'Des travaux sont en cours sur l\'Avenue Léon Mba. Circulation alternée prévue jusqu\'au 31 mars.', 'importante', 'push,email', NOW(), 'NOTIF-2025-000001'),
('evenement', 'Concert gratuit - Place de l\'Indépendance', 'Concert gratuit le samedi 15 février à 18h sur la Place de l\'Indépendance.', 'informative', 'push', DATE_ADD(NOW(), INTERVAL 1 DAY), 'NOTIF-2025-000002'),
('alerte', 'Coupure d\'eau prévue', 'Coupure d\'eau prévue le 20 février de 8h à 16h dans le quartier Nombakélé.', 'urgente', 'push,sms,email', DATE_ADD(NOW(), INTERVAL 2 DAY), 'NOTIF-2025-000003')
ON DUPLICATE KEY UPDATE date_envoi = VALUES(date_envoi);

-- FAQ
INSERT INTO faq (categorie, page_contexte, question, reponse, ordre_affichage, actif) VALUES
('general', 'accueil', 'Comment créer un compte ?', 'Utilisez votre numéro de téléphone gabonais et créez un mot de passe sécurisé.', 1, 1),
('demandes', 'demandes', 'Combien de temps pour traiter une demande ?', 'Le délai moyen de traitement est de 2 à 5 jours ouvrés selon le type de demande.', 1, 1),
('paiements', 'paiements', 'Quels moyens de paiement sont acceptés ?', 'Nous acceptons ClickPay, Airtel Money, Moov Money, carte bancaire et paiement en espèces.', 1, 1),
('signalements', 'signalements', 'Comment suivre mon signalement ?', 'Vous pouvez suivre l\'état de votre signalement dans votre tableau de bord.', 1, 1),
('budget', 'budget', 'Où consulter le budget municipal ?', 'Le budget municipal est accessible à tous les citoyens dans la section Budget Municipal.', 1, 1)
ON DUPLICATE KEY UPDATE reponse = VALUES(reponse);

-- Demandes de démonstration (si utilisateurs existent)
INSERT INTO demandes (utilisateur_id, type, service, motif, cout, statut, agent_assigné_id, numero_dossier, date_creation) 
SELECT u.id, 'Administratif', 'Acte de Naissance', 'Demande d\'acte de naissance pour enfant', 5000, 'en_traitement', 
       (SELECT id FROM utilisateurs WHERE role = 'agent' LIMIT 1),
       CONCAT('DC', YEAR(NOW()), '-', LPAD((SELECT COALESCE(MAX(CAST(SUBSTRING(numero_dossier, 4) AS UNSIGNED)), 0) + 1 FROM demandes WHERE numero_dossier LIKE CONCAT('DC', YEAR(NOW()), '-%')), 6, '0')),
       DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM utilisateurs u WHERE u.role = 'citoyen' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Signalements de démonstration
INSERT INTO signalements (utilisateur_id, type, sous_type, description, localisation, latitude, longitude, statut, agent_assigné_id, date_creation)
SELECT u.id, 'Infrastructure', 'Nid-de-poule', 'Grand nid-de-poule sur l\'Avenue Bouet, danger pour les véhicules', 'Avenue Bouet, Libreville', 0.3901, 9.4540, 'en_cours',
       (SELECT id FROM utilisateurs WHERE role = 'agent' LIMIT 1),
       DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM utilisateurs u WHERE u.role = 'citoyen' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Missions agents
INSERT INTO missions (agent_id, type_mission, description, localisation, date_mission, statut)
SELECT u.id, 'Vérification signalement', 'Vérifier le nid-de-poule signalé Avenue Bouet', 'Avenue Bouet, Libreville', DATE_ADD(NOW(), INTERVAL 1 DAY), 'planifiee'
FROM utilisateurs u WHERE u.role = 'agent' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Licences commerciales
INSERT INTO licences_commerciales (commercant_id, type_licence, montant, date_debut, date_fin, statut)
SELECT u.id, 'Patente annuelle', 125000, '2025-01-01', '2025-12-31', 'active'
FROM utilisateurs u WHERE u.role = 'commercant' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Emplacements marché
INSERT INTO emplacements_marche (commercant_id, marche, emplacement, montant_mensuel, date_debut, date_fin, statut)
SELECT u.id, 'Marche Nkembo', 'A-02', 120000, '2025-01-01', '2025-12-31', 'actif'
FROM utilisateurs u WHERE u.role = 'commercant' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Paiements
INSERT INTO paiements (utilisateur_id, demande_id, type_paiement, montant, methode, reference_paiement, statut, date_paiement, date_confirmation)
SELECT u.id, 
       (SELECT id FROM demandes WHERE utilisateur_id = u.id LIMIT 1),
       'service_administratif', 5000, 'clickpay', CONCAT('PAY-', YEAR(NOW()), '-', LPAD((SELECT COALESCE(MAX(CAST(SUBSTRING(reference_paiement, 9) AS UNSIGNED)), 0) + 1 FROM paiements WHERE reference_paiement LIKE CONCAT('PAY-', YEAR(NOW()), '-%')), 8, '0')),
       'confirme', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM utilisateurs u WHERE u.role = 'citoyen' LIMIT 1
ON DUPLICATE KEY UPDATE statut = VALUES(statut);

-- Statistiques
INSERT INTO statistiques (date_stat, type_stat, valeur, description) VALUES
(CURDATE(), 'demandes_jour', 15, 'Demandes créées aujourd\'hui'),
(CURDATE(), 'signalements_jour', 8, 'Signalements créés aujourd\'hui'),
(CURDATE(), 'paiements_jour', 12, 'Paiements effectués aujourd\'hui')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- Préférences notifications (pour les utilisateurs de démo)
INSERT INTO preferences_notifications (utilisateur_id, notifications_circulation, notifications_evenements, notifications_alertes, notifications_administratives, notifications_commerciales, canal_push, canal_sms, canal_email, geolocalisation_active, rayon_km)
SELECT id, 1, 1, 1, 1, 0, 1, 0, 1, 0, 5
FROM utilisateurs
WHERE role IN ('citoyen', 'commercant')
ON DUPLICATE KEY UPDATE notifications_circulation = VALUES(notifications_circulation);

SELECT 'Installation complète terminée avec succès !' as message;



