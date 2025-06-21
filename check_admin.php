<?php
require_once 'includes/config.php';

try {
    // Récupérer les données de l'admin
    $sql = "SELECT * FROM utilisateurs WHERE email = 'admin@concours-anonyme.com'";
    $stmt = $conn->query($sql);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<h2>✅ Administrateur trouvé</h2>";
        echo "<p>Pour vous connecter :</p>";
        echo "<ul>";
        echo "<li>Email : admin@concours-anonyme.com</li>";
        echo "<li>Mot de passe : admin123</li>";
        echo "</ul>";
        
        // Vérifier le mot de passe
        if (password_verify('admin123', $admin['mot_de_passe'])) {
            echo "<p style='color: green;'>✅ Le mot de passe 'admin123' est correct !</p>";
        } else {
            echo "<p style='color: red;'>❌ Le mot de passe 'admin123' n'est pas correct !</p>";
        }
    } else {
        echo "<h2>❌ Administrateur non trouvé</h2>";
        echo "<p>Veuillez exécuter le fichier schema.sql pour créer la base de données et l'administrateur.</p>";
    }
} catch (PDOException $e) {
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Empêcher la suppression du super admin (id=1)
if ($user && $user['role'] === 'admin' && $user['id'] == 1) {
    $error = "Impossible de supprimer le super administrateur.";
}
?>