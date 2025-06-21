-- =================================================================
-- Schéma de la base de données pour l'application Concours Anonyme
-- Version : 2.0
-- Date de dernière mise à jour : 2024-07-28
-- Ce fichier reflète la structure complète de la base de données.
-- Pour l'initialisation avec des données de base, voir `init.php`.
-- =================================================================

CREATE DATABASE IF NOT EXISTS concours_anonyme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE concours_anonyme;

-- --------------------------------------------------------
-- Table `utilisateurs`
-- Stocke les informations sur tous les utilisateurs de l'application.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'candidat', 'correcteur') NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `concours`
-- Contient les informations sur les concours organisés.
-- La colonne `titre` est unique pour éviter les doublons.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS concours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    grading_grid_json JSON NULL COMMENT 'Grille d''évaluation au format JSON',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_titre (titre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `copies`
-- Gère les soumissions de copies par les candidats.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS copies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    concours_id INT,
    candidat_id INT,
    identifiant_anonyme VARCHAR(50) UNIQUE,
    fichier_path VARCHAR(255),
    date_depot DATETIME,
    statut ENUM('en_attente', 'en_correction', 'correction_soumise', 'corrigee', 'rejetee') NOT NULL DEFAULT 'en_attente',
    note_finale DECIMAL(5,2) NULL COMMENT 'Note finale de la copie',
    correcteur_id INT NULL COMMENT 'ID du correcteur assigné',
    FOREIGN KEY (concours_id) REFERENCES concours(id) ON DELETE CASCADE,
    FOREIGN KEY (candidat_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `corrections`
-- Stocke les évaluations des copies faites par les correcteurs.
-- Inclut les champs pour le processus de validation par l'admin.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS corrections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    copie_id INT,
    correcteur_id INT,
    evaluation_data_json JSON NULL COMMENT 'Données d''évaluation au format JSON',
    date_correction DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut_validation VARCHAR(50) NOT NULL DEFAULT 'en_attente' COMMENT 'en_attente, validee, rejetee, modification_demandee',
    commentaire_admin TEXT NULL,
    date_validation DATETIME NULL,
    validee_par INT NULL,
    FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
    FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (validee_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `rejets_corrections`
-- Historique des corrections rejetées par un administrateur.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS rejets_corrections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    copie_id INT NOT NULL,
    correcteur_id INT NOT NULL,
    admin_id INT NOT NULL,
    commentaire_rejet TEXT NOT NULL,
    correction_data_json JSON,
    note_rejetee DECIMAL(5,2),
    date_rejet DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
    FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `attributions_copies`
-- Fait le lien entre une copie et le correcteur qui lui est attribué.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS attributions_copies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    copie_id INT NOT NULL,
    correcteur_id INT NOT NULL,
    date_attribution DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
    FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_copie_attribution (copie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `assignations_correcteurs`
-- Gère l'assignation globale d'un correcteur à un concours.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS assignations_correcteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concours_id INT NOT NULL,
    correcteur_id INT NOT NULL,
    date_assignation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (concours_id) REFERENCES concours(id) ON DELETE CASCADE,
    FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignation (concours_id, correcteur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `grille_evaluation`
-- Stocke les critères et barèmes pour la notation.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS grille_evaluation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concours_id INT,
    critere VARCHAR(255),
    bareme DECIMAL(5,2),
    FOREIGN KEY (concours_id) REFERENCES concours(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `audit_logs`
-- Journal d'audit pour tracer les actions importantes.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `historique_modifications`
-- Trace les modifications sur les fichiers de copies.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS historique_modifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    copie_id INT NOT NULL,
    user_id INT NOT NULL,
    ancien_fichier TEXT COMMENT 'Chemin de l''ancien fichier',
    nouveau_fichier TEXT COMMENT 'Chemin du nouveau fichier',
    raison VARCHAR(255) DEFAULT 'Modification candidat',
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_copie_date (copie_id, date_modification),
    INDEX idx_user_date (user_id, date_modification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `contacts`
-- Enregistre les messages envoyés via le formulaire de contact.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    date_creation DATETIME NOT NULL,
    traite BOOLEAN DEFAULT FALSE,
    date_traitement DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
-- Création des index pour optimiser les performances
-- =================================================================
CREATE INDEX IF NOT EXISTS idx_concours_dates ON concours(date_debut, date_fin);
CREATE INDEX IF NOT EXISTS idx_corrections_date ON corrections(date_correction);
CREATE INDEX IF NOT EXISTS idx_utilisateurs_role ON utilisateurs(role);
CREATE INDEX IF NOT EXISTS idx_attributions_correcteur ON attributions_copies(correcteur_id);
CREATE INDEX IF NOT EXISTS idx_attributions_date ON attributions_copies(date_attribution);

-- =================================================================
-- Fin du schéma
-- =================================================================
