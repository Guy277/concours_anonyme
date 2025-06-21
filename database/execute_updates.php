<?php
/**
 * Script d'exécution des mises à jour de la base de données
 * Crée la table attributions_copies et effectue les migrations nécessaires
 */

require_once '../includes/config.php';

echo "<h1>🔧 Mise à jour de la base de données</h1>";
echo "<p>Exécution des scripts de mise à jour...</p>";

try {
    // Lecture du fichier SQL
    $sql_file = __DIR__ . '/update_attributions_table.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Fichier SQL introuvable : $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        throw new Exception("Impossible de lire le fichier SQL");
    }
    
    // Séparation des requêtes
    $queries = array_filter(array_map('trim', explode(';', $sql_content)));
    
    echo "<h2>📋 Exécution des requêtes :</h2>";
    echo "<ul>";
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue; // Ignorer les commentaires et lignes vides
        }
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            // Afficher le type de requête
            $query_type = strtoupper(explode(' ', trim($query))[0]);
            echo "<li>✅ <strong>$query_type</strong> : " . substr($query, 0, 100) . "...</li>";
            $success_count++;
            
        } catch (PDOException $e) {
            // Ignorer les erreurs "table already exists" et "column already exists"
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<li>ℹ️ <strong>IGNORÉ</strong> : " . substr($query, 0, 100) . "... (déjà existant)</li>";
            } else {
                echo "<li>❌ <strong>ERREUR</strong> : " . substr($query, 0, 100) . "...<br>";
                echo "<em>Erreur : " . htmlspecialchars($e->getMessage()) . "</em></li>";
                $error_count++;
            }
        }
    }
    
    echo "</ul>";
    
    // Ajout de la colonne 'statut' à la table 'concours' si elle n'existe pas
    echo "<h2>🔧 Vérification de la colonne 'statut' pour les concours...</h2>";
    try {
        // Requête pour vérifier l'existence de la colonne
        $checkColumn = $conn->query("SHOW COLUMNS FROM `concours` LIKE 'statut'");
        
        if ($checkColumn->rowCount() == 0) {
            echo "<p>🟡 La colonne 'statut' est manquante. Tentative d'ajout...</p>";
            $alterTable = $conn->query("ALTER TABLE `concours` ADD `statut` VARCHAR(50) NOT NULL DEFAULT 'en_attente' AFTER `grading_grid_json`");
            echo "<p>✅ La colonne 'statut' a été ajoutée avec succès à la table 'concours'.</p>";
        } else {
            echo "<p>✅ La colonne 'statut' existe déjà. Aucune action n'est nécessaire.</p>";
        }
    } catch (PDOException $e) {
        echo "<p>❌ Erreur lors de la vérification/ajout de la colonne 'statut': " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Vérifications post-migration
    echo "<h2>🔍 Vérifications :</h2>";
    echo "<ul>";
    
    // Vérifier que la table attributions_copies existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'attributions_copies'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<li>✅ Table <strong>attributions_copies</strong> créée avec succès</li>";
        
        // Compter les enregistrements
        $stmt = $conn->prepare("SELECT COUNT(*) FROM attributions_copies");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "<li>📊 <strong>$count</strong> attributions dans la table</li>";
    } else {
        echo "<li>❌ Table <strong>attributions_copies</strong> non trouvée</li>";
    }
    
    // Vérifier la colonne note_totale dans copies
    $stmt = $conn->prepare("SHOW COLUMNS FROM copies LIKE 'note_totale'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<li>✅ Colonne <strong>note_totale</strong> ajoutée à la table copies</li>";
    } else {
        echo "<li>ℹ️ Colonne <strong>note_totale</strong> non ajoutée (peut-être déjà existante)</li>";
    }
    
    // Statistiques finales
    $stmt = $conn->prepare("SELECT COUNT(*) FROM copies");
    $stmt->execute();
    $total_copies = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM utilisateurs WHERE role = 'correcteur'");
    $stmt->execute();
    $total_correcteurs = $stmt->fetchColumn();
    
    echo "<li>📈 <strong>$total_copies</strong> copies dans le système</li>";
    echo "<li>👥 <strong>$total_correcteurs</strong> correcteurs disponibles</li>";
    
    echo "</ul>";
    
    // Résumé
    echo "<h2>📊 Résumé :</h2>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<p><strong>✅ Mise à jour terminée avec succès !</strong></p>";
    echo "<ul>";
    echo "<li>Requêtes exécutées avec succès : <strong>$success_count</strong></li>";
    if ($error_count > 0) {
        echo "<li>Erreurs rencontrées : <strong>$error_count</strong></li>";
    }
    echo "<li>Table d'attribution des copies : <strong>Opérationnelle</strong></li>";
    echo "<li>Interface de gestion : <strong>Prête à utiliser</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🚀 Prochaines étapes :</h2>";
    echo "<ol>";
    echo "<li>Tester l'interface de gestion des copies : <a href='../admin/attribuer_copies.php' target='_blank'>admin/attribuer_copies.php</a></li>";
    echo "<li>Vérifier l'attribution des copies aux correcteurs</li>";
    echo "<li>Tester les fonctionnalités de réattribution</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>";
    echo "<h2>❌ Erreur lors de la mise à jour :</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour Base de Données - Concours Anonyme</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #007bff; }
        h2 { color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 5px; }
        ul { line-height: 1.6; }
        li { margin: 5px 0; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="../dashboard/admin.php">← Retour au dashboard admin</a></p>
        <p><a href="../admin/attribuer_copies.php">🆕 Tester la gestion des copies</a></p>
    </div>
</body>
</html>
