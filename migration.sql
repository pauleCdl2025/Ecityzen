-- Script de migration pour les bases de données existantes
-- À exécuter si vous avez déjà une base de données avec l'ancienne structure
-- 
-- Ce script met à jour la structure de la table utilisateurs pour :
-- - Rendre l'email optionnel (NULL)
-- - Rendre le téléphone obligatoire et unique
--
-- ATTENTION : Sauvegardez votre base de données avant d'exécuter ce script !

USE ecityzen_gabon;

-- Supprimer l'index unique sur email s'il existe
ALTER TABLE utilisateurs DROP INDEX idx_email;

-- Modifier la structure de la table
ALTER TABLE utilisateurs 
    MODIFY email VARCHAR(100) NULL,
    MODIFY telephone VARCHAR(20) NOT NULL;

-- Ajouter l'index unique sur téléphone
ALTER TABLE utilisateurs ADD UNIQUE INDEX idx_telephone (telephone);

-- Mettre à jour les emails existants à NULL (optionnel, car maintenant on utilise le téléphone)
-- UPDATE utilisateurs SET email = NULL WHERE email IS NOT NULL;









