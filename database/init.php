<?php
/**
 * Script d'initialisation de la base de données - Concours Anonyme
 * Synchronisé avec le schéma actuel et toutes les nouvelles fonctionnalités
 */

// Paramètres de connexion à MySQL XAMPP
$host = 'localhost';
$port = '3307';  // Port MySQL de XAMPP
$username = 'root';
$password = '';  // Mot de passe vide par défaut pour XAMPP

try {
    // Connexion à MySQL sans spécifier de base de données
    $pdo = new PDO("mysql:host=$host;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🚀 Initialisation de la Base de Données - Concours Anonyme</h1>";
    echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<p><strong>📋 Création de la base de données...</strong></p>";
    
    // Création de la base de données si elle n'existe pas
    $sql = "CREATE DATABASE IF NOT EXISTS concours_anonyme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Base de données <strong>concours_anonyme</strong> créée ou déjà existante<br>";
    
    // Sélection de la base de données
    $pdo->exec("USE concours_anonyme");
    echo "✅ Base de données sélectionnée<br>";
    echo "</div>";
    
    echo "<h2>📊 Création des Tables</h2>";
    
    // 1. Table des utilisateurs (admin, candidat, correcteur)
    $sql = "CREATE TABLE IF NOT EXISTS utilisateurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        mot_de_passe VARCHAR(255) NOT NULL,
        role ENUM('admin', 'candidat', 'correcteur') NOT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>utilisateurs</strong> créée ou déjà existante<br>";
    
    // 2. Table des concours
    $sql = "CREATE TABLE IF NOT EXISTS concours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_admin INT NOT NULL,
        titre VARCHAR(255) NOT NULL,
        description TEXT,
        date_debut DATETIME NOT NULL,
        date_fin DATETIME NOT NULL,
        grading_grid_json JSON NULL COMMENT 'Grille d''évaluation au format JSON',
        statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_admin) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_titre (titre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>concours</strong> créée ou déjà existante (avec contrainte UNIQUE sur titre)<br>";
    
    // 3. Table des copies déposées anonymement
    $sql = "CREATE TABLE IF NOT EXISTS copies (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>copies</strong> créée ou déjà existante<br>";
    
    // 4. Table d'attribution de copies à des correcteurs
    $sql = "CREATE TABLE IF NOT EXISTS attributions_copies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        copie_id INT NOT NULL,
        correcteur_id INT NOT NULL,
        date_attribution DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
        FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_copie_attribution (copie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>attributions_copies</strong> créée ou déjà existante<br>";
    
    // 5. Table des corrections faites par les correcteurs
    $sql = "CREATE TABLE IF NOT EXISTS corrections (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>corrections</strong> créée ou déjà existante (avec colonnes de validation)<br>";
    
    // 6. Table de l'historique des rejets
    $sql = "CREATE TABLE IF NOT EXISTS rejets_corrections (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>rejets_corrections</strong> créée ou déjà existante<br>";
    
    // 7. Table des assignations globales de correcteurs à des concours
    $sql = "CREATE TABLE IF NOT EXISTS assignations_correcteurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        concours_id INT NOT NULL,
        correcteur_id INT NOT NULL,
        date_assignation DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (concours_id) REFERENCES concours(id) ON DELETE CASCADE,
        FOREIGN KEY (correcteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignation (concours_id, correcteur_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>assignations_correcteurs</strong> créée ou déjà existante<br>";
    
    // 8. Table des grilles d'évaluation par concours
    $sql = "CREATE TABLE IF NOT EXISTS grille_evaluation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        concours_id INT,
        critere VARCHAR(255),
        bareme DECIMAL(5,2),
        FOREIGN KEY (concours_id) REFERENCES concours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>grille_evaluation</strong> créée ou déjà existante<br>";
    
    // 9. Table des journaux d'audit
    $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        details TEXT,
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>audit_logs</strong> créée ou déjà existante<br>";
    
    // 10. Table d'historique des modifications de copies
    $sql = "CREATE TABLE IF NOT EXISTS historique_modifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        copie_id INT NOT NULL,
        user_id INT NOT NULL,
        ancien_fichier TEXT COMMENT 'Chemin de l\'ancien fichier (chiffré)',
        nouveau_fichier TEXT COMMENT 'Chemin du nouveau fichier (chiffré)',
        raison VARCHAR(255) DEFAULT 'Modification candidat',
        date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        INDEX idx_copie_date (copie_id, date_modification),
        INDEX idx_user_date (user_id, date_modification)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>historique_modifications</strong> créée ou déjà existante<br>";
    
    // 11. Table des contacts (formulaire de contact)
    $sql = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        date_creation DATETIME NOT NULL,
        traite BOOLEAN DEFAULT FALSE,
        date_traitement DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✅ Table <strong>contacts</strong> créée ou déjà existante<br>";
    
    echo "<h2>🔧 Mise à jour des Colonnes</h2>";
    echo "<p>Toutes les colonnes sont désormais créées directement dans les tables. Aucune mise à jour de colonne n'est nécessaire.</p>";
    
    echo "<h2>📈 Création des Index</h2>";
    
    // Index pour optimiser les performances
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_concours_dates ON concours(date_debut, date_fin)",
        "CREATE INDEX IF NOT EXISTS idx_corrections_note ON corrections(date_correction)",
        "CREATE INDEX IF NOT EXISTS idx_utilisateurs_role ON utilisateurs(role)",
        "CREATE INDEX IF NOT EXISTS idx_attributions_correcteur ON attributions_copies(correcteur_id)",
        "CREATE INDEX IF NOT EXISTS idx_attributions_date ON attributions_copies(date_attribution)"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $pdo->exec($index_sql);
            echo "✅ Index créé avec succès<br>";
        } catch (PDOException $e) {
            echo "ℹ️ Index déjà existant ou erreur ignorée<br>";
        }
    }
    
    echo "<h2>👤 Création de l'Utilisateur Admin</h2>";
    
    // Création d'un utilisateur admin par défaut si aucun n'existe
    $sql = "SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) 
                VALUES ('Admin', 'System', 'admin@concours-anonyme.com', ?, 'admin')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$password_hash]);
        echo "✅ Utilisateur <strong>admin</strong> créé avec succès<br>";
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
        echo "<p><strong>🔑 Identifiants de connexion :</strong></p>";
        echo "<ul>";
        echo "<li><strong>Email :</strong> admin@concours-anonyme.com</li>";
        echo "<li><strong>Mot de passe :</strong> admin123</li>";
        echo "</ul>";
        echo "<p><em>⚠️ N'oubliez pas de changer ce mot de passe après la première connexion !</em></p>";
        echo "</div>";
    } else {
        echo "ℹ️ Utilisateur admin déjà existant<br>";
    }
    
    echo "<h2>📊 Statistiques Finales</h2>";
    
    // Statistiques de la base de données
    $tables = ['utilisateurs', 'concours', 'copies', 'attributions_copies', 'corrections', 'rejets_corrections', 'assignations_correcteurs', 'grille_evaluation', 'audit_logs', 'historique_modifications', 'contacts'];
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<p><strong>✅ Initialisation terminée avec succès !</strong></p>";
    echo "<p>Base de données <strong>concours_anonyme</strong> prête à utiliser.</p>";
    echo "</div>";
    
    echo "<h3>📋 Tables créées :</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<li><strong>$table</strong> : $count enregistrement(s)</li>";
        } catch (PDOException $e) {
            echo "<li><strong>$table</strong> : Erreur lors du comptage</li>";
        }
    }
    echo "</ul>";
    
    echo "<h2>🚀 Prochaines Étapes</h2>";
    echo "<ol>";
    echo "<li>Configurer les paramètres dans <strong>includes/config.php</strong></li>";
    echo "<li>Tester la connexion à l'application</li>";
    echo "<li>Créer votre premier concours</li>";
    echo "<li>Ajouter des utilisateurs (candidats et correcteurs)</li>";
    echo "</ol>";
    
    echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<p><strong>🔗 Liens utiles :</strong></p>";
    echo "<ul>";
    echo "<li><a href='../index.php' target='_blank'>Accéder à l'application</a></li>";
    echo "<li><a href='../dashboard/admin.php' target='_blank'>Dashboard administrateur</a></li>";
    echo "<li><a href='../admin/attribuer_copies.php' target='_blank'>Gestion des copies</a></li>";
    echo "</ul>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h2>❌ Erreur lors de l'initialisation :</h2>";
    echo "<p><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code :</strong> " . $e->getCode() . "</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialisation Base de Données - Concours Anonyme</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 20px; 
            background: #f8f9fa; 
            line-height: 1.6;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #007bff; 
            border-bottom: 3px solid #007bff; 
            padding-bottom: 10px; 
        }
        h2 { 
            color: #28a745; 
            border-bottom: 2px solid #28a745; 
            padding-bottom: 5px; 
            margin-top: 30px;
        }
        h3 { 
            color: #6c757d; 
            margin-top: 25px;
        }
        ul { 
            line-height: 1.8; 
        }
        li { 
            margin: 8px 0; 
        }
        a { 
            color: #007bff; 
            text-decoration: none; 
            font-weight: 500;
        }
        a:hover { 
            text-decoration: underline; 
        }
        .success { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0; 
        }
        .info { 
            background: #e7f3ff; 
            border: 1px solid #b3d9ff; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0; 
        }
        .warning { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0; 
        }
        .error { 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0; 
        }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="../index.php">← Retour à l'accueil</a></p>
    </div>
</body>
</html> 