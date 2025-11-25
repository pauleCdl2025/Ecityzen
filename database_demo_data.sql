-- Données de démonstration pour e-cityzen Gabon
-- ⚠️ IMPORTANT : Exécutez d'abord add_missing_tables.sql pour créer les tables manquantes
-- À exécuter après avoir créé la structure de base de données

USE ecityzen_gabon;

-- Vérifier que les tables existent avant d'insérer des données
-- Si vous obtenez une erreur "table doesn't exist", exécutez d'abord add_missing_tables.sql

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
LIMIT 1;

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
LIMIT 1;

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

-- Demandes de démonstration
INSERT INTO demandes (utilisateur_id, type, service, motif, cout, statut, agent_assigné_id, numero_dossier, date_creation) 
SELECT u.id, 'Administratif', 'Acte de Naissance', 'Demande d\'acte de naissance pour enfant', 5000, 'en_traitement', 
       (SELECT id FROM utilisateurs WHERE role = 'agent' LIMIT 1),
       CONCAT('DC', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) FROM demandes) + 1, 6, '0')),
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
       'service_administratif', 5000, 'clickpay', CONCAT('PAY-', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) FROM paiements) + 1, 8, '0')),
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

