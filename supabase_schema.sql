-- Schema Supabase pour e-cityzen Gabon
-- À exécuter dans l'éditeur SQL de Supabase

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id BIGSERIAL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255),
    telephone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255),
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'citoyen',
    statut VARCHAR(20) DEFAULT 'actif',
    localisation TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    secteur VARCHAR(100),
    entreprise VARCHAR(255),
    date_creation TIMESTAMP DEFAULT NOW(),
    derniere_connexion TIMESTAMP
);

-- Table signalements
CREATE TABLE IF NOT EXISTS signalements (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    type VARCHAR(100) NOT NULL,
    sous_type VARCHAR(100),
    description TEXT,
    localisation TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    photo_url TEXT,
    statut VARCHAR(50) DEFAULT 'en_attente',
    agent_assigné_id BIGINT REFERENCES utilisateurs(id),
    date_signalement TIMESTAMP DEFAULT NOW(),
    date_creation TIMESTAMP DEFAULT NOW(),
    date_modification TIMESTAMP,
    date_resolution TIMESTAMP
);

-- Table demandes
CREATE TABLE IF NOT EXISTS demandes (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    type VARCHAR(100) NOT NULL,
    service VARCHAR(255) NOT NULL,
    motif TEXT,
    montant DECIMAL(10, 2),
    statut VARCHAR(50) DEFAULT 'en_attente',
    agent_assigné_id BIGINT REFERENCES utilisateurs(id),
    documents JSONB,
    date_creation TIMESTAMP DEFAULT NOW(),
    date_modification TIMESTAMP,
    date_validation TIMESTAMP
);

-- Table paiements
CREATE TABLE IF NOT EXISTS paiements (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    demande_id BIGINT REFERENCES demandes(id),
    montant DECIMAL(10, 2) NOT NULL,
    mode_paiement VARCHAR(50) NOT NULL,
    statut VARCHAR(50) DEFAULT 'en_attente',
    reference_transaction VARCHAR(255),
    date_paiement TIMESTAMP DEFAULT NOW()
);

-- Table licences_commerciales
CREATE TABLE IF NOT EXISTS licences_commerciales (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    numero_licence VARCHAR(100) UNIQUE,
    type_activite VARCHAR(255),
    adresse TEXT,
    date_emission DATE,
    date_expiration DATE,
    statut VARCHAR(50) DEFAULT 'active'
);

-- Table emplacements_marche
CREATE TABLE IF NOT EXISTS emplacements_marche (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    marche VARCHAR(255) NOT NULL,
    numero_stand VARCHAR(50),
    type_stand VARCHAR(100),
    statut VARCHAR(50) DEFAULT 'actif',
    date_attribution DATE
);

-- Table missions
CREATE TABLE IF NOT EXISTS missions (
    id BIGSERIAL PRIMARY KEY,
    agent_id BIGINT REFERENCES utilisateurs(id),
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    localisation TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    statut VARCHAR(50) DEFAULT 'assignee',
    date_mission DATE,
    date_creation TIMESTAMP DEFAULT NOW()
);

-- Table budget_municipal
CREATE TABLE IF NOT EXISTS budget_municipal (
    id BIGSERIAL PRIMARY KEY,
    exercice_budgetaire INTEGER NOT NULL,
    poste_budgetaire VARCHAR(255) NOT NULL,
    categorie VARCHAR(50) NOT NULL,
    budget_initial DECIMAL(15, 2) NOT NULL,
    budget_rectificatif DECIMAL(15, 2) DEFAULT 0,
    depenses_engagees DECIMAL(15, 2) DEFAULT 0,
    UNIQUE(exercice_budgetaire, poste_budgetaire)
);

-- Table chantiers_travaux
CREATE TABLE IF NOT EXISTS chantiers_travaux (
    id BIGSERIAL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(100),
    localisation TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    statut VARCHAR(50) DEFAULT 'planifie',
    date_debut DATE,
    date_fin_prevue DATE,
    date_fin_reelle DATE,
    budget_alloue DECIMAL(15, 2),
    entreprise VARCHAR(255)
);

-- Table signalements_chefs_quartier
CREATE TABLE IF NOT EXISTS signalements_chefs_quartier (
    id BIGSERIAL PRIMARY KEY,
    chef_quartier_id BIGINT REFERENCES utilisateurs(id),
    type VARCHAR(100) NOT NULL,
    description TEXT,
    localisation TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    statut VARCHAR(50) DEFAULT 'en_attente',
    date_signalement TIMESTAMP DEFAULT NOW()
);

-- Table stands_marche
CREATE TABLE IF NOT EXISTS stands_marche (
    id BIGSERIAL PRIMARY KEY,
    marche VARCHAR(255) NOT NULL,
    numero_stand VARCHAR(50) NOT NULL,
    type_stand VARCHAR(100),
    tarif_journalier DECIMAL(10, 2),
    disponibilite VARCHAR(50) DEFAULT 'disponible',
    UNIQUE(marche, numero_stand)
);

-- Table notifications
CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    categorie VARCHAR(50) DEFAULT 'informative',
    statut_lecture VARCHAR(20) DEFAULT 'non_lu',
    date_envoi TIMESTAMP DEFAULT NOW()
);

-- Table preferences_notifications
CREATE TABLE IF NOT EXISTS preferences_notifications (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id) UNIQUE,
    notifications_circulation BOOLEAN DEFAULT true,
    notifications_evenements BOOLEAN DEFAULT true,
    notifications_alertes BOOLEAN DEFAULT true,
    notifications_administratives BOOLEAN DEFAULT true,
    notifications_commerciales BOOLEAN DEFAULT false
);

-- Table feedbacks
CREATE TABLE IF NOT EXISTS feedbacks (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    note INTEGER CHECK (note >= 1 AND note <= 5),
    commentaire TEXT,
    date_creation TIMESTAMP DEFAULT NOW()
);

-- Table messages_assistance
CREATE TABLE IF NOT EXISTS messages_assistance (
    id BIGSERIAL PRIMARY KEY,
    utilisateur_id BIGINT REFERENCES utilisateurs(id),
    sujet VARCHAR(255),
    message TEXT NOT NULL,
    statut VARCHAR(50) DEFAULT 'ouvert',
    date_creation TIMESTAMP DEFAULT NOW()
);

-- Table faq
CREATE TABLE IF NOT EXISTS faq (
    id BIGSERIAL PRIMARY KEY,
    question TEXT NOT NULL,
    reponse TEXT NOT NULL,
    categorie VARCHAR(100),
    ordre INTEGER DEFAULT 0
);

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_utilisateurs_telephone ON utilisateurs(telephone);
CREATE INDEX IF NOT EXISTS idx_utilisateurs_role ON utilisateurs(role);
CREATE INDEX IF NOT EXISTS idx_signalements_utilisateur ON signalements(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_signalements_statut ON signalements(statut);
CREATE INDEX IF NOT EXISTS idx_demandes_utilisateur ON demandes(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_demandes_statut ON demandes(statut);
CREATE INDEX IF NOT EXISTS idx_notifications_utilisateur ON notifications(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_notifications_statut ON notifications(statut_lecture);

-- Activer Row Level Security (RLS) - Optionnel
-- ALTER TABLE utilisateurs ENABLE ROW LEVEL SECURITY;
-- ALTER TABLE signalements ENABLE ROW LEVEL SECURITY;
-- ALTER TABLE demandes ENABLE ROW LEVEL SECURITY;

-- Politiques RLS de base (à adapter selon vos besoins)
-- CREATE POLICY "Users can view own data" ON utilisateurs FOR SELECT USING (auth.uid() = id);
-- CREATE POLICY "Users can update own data" ON utilisateurs FOR UPDATE USING (auth.uid() = id);

