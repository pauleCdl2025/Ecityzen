-- Script pour corriger les contraintes de clé étrangère dans Supabase
-- À exécuter dans l'éditeur SQL de Supabase Dashboard
-- Ce script modifie les contraintes pour gérer automatiquement la suppression des utilisateurs

-- 1. Supprimer l'ancienne contrainte sur signalements.agent_assigné_id
ALTER TABLE signalements 
DROP CONSTRAINT IF EXISTS signalements_agent_assigné_id_fkey;

-- 2. Recréer la contrainte avec ON DELETE SET NULL
-- Cela permettra de supprimer un utilisateur même s'il est assigné à des signalements
-- Les signalements auront simplement agent_assigné_id = NULL
ALTER TABLE signalements
ADD CONSTRAINT signalements_agent_assigné_id_fkey 
FOREIGN KEY (agent_assigné_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 3. Faire de même pour demandes.agent_assigné_id
ALTER TABLE demandes 
DROP CONSTRAINT IF EXISTS demandes_agent_assigné_id_fkey;

ALTER TABLE demandes
ADD CONSTRAINT demandes_agent_assigné_id_fkey 
FOREIGN KEY (agent_assigné_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 4. Pour utilisateur_id dans signalements, on peut garder CASCADE ou SET NULL selon le besoin
-- CASCADE = supprimer les signalements si l'utilisateur est supprimé
-- SET NULL = garder les signalements mais avec utilisateur_id = NULL
-- Ici on met SET NULL pour garder l'historique
ALTER TABLE signalements 
DROP CONSTRAINT IF EXISTS signalements_utilisateur_id_fkey;

ALTER TABLE signalements
ADD CONSTRAINT signalements_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 5. Pour demandes.utilisateur_id
ALTER TABLE demandes 
DROP CONSTRAINT IF EXISTS demandes_utilisateur_id_fkey;

ALTER TABLE demandes
ADD CONSTRAINT demandes_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 6. Pour missions.agent_id
ALTER TABLE missions 
DROP CONSTRAINT IF EXISTS missions_agent_id_fkey;

ALTER TABLE missions
ADD CONSTRAINT missions_agent_id_fkey 
FOREIGN KEY (agent_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 7. Pour paiements.utilisateur_id (on garde SET NULL pour l'historique)
ALTER TABLE paiements 
DROP CONSTRAINT IF EXISTS paiements_utilisateur_id_fkey;

ALTER TABLE paiements
ADD CONSTRAINT paiements_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 8. Pour licences_commerciales.utilisateur_id
ALTER TABLE licences_commerciales 
DROP CONSTRAINT IF EXISTS licences_commerciales_utilisateur_id_fkey;

ALTER TABLE licences_commerciales
ADD CONSTRAINT licences_commerciales_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- 9. Pour emplacements_marche.utilisateur_id
ALTER TABLE emplacements_marche 
DROP CONSTRAINT IF EXISTS emplacements_marche_utilisateur_id_fkey;

ALTER TABLE emplacements_marche
ADD CONSTRAINT emplacements_marche_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) 
REFERENCES utilisateurs(id) 
ON DELETE SET NULL;

-- Vérification : Afficher toutes les contraintes de clé étrangère
SELECT
    tc.table_name, 
    kcu.column_name, 
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name,
    rc.delete_rule
FROM 
    information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
      AND ccu.table_schema = tc.table_schema
    JOIN information_schema.referential_constraints AS rc
      ON rc.constraint_name = tc.constraint_name
      AND rc.constraint_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY' 
    AND tc.table_schema = 'public'
    AND ccu.table_name = 'utilisateurs'
ORDER BY tc.table_name, kcu.column_name;



