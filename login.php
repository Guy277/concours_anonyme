<?php
    /**
     * Page de connexion
     * Cette page gère l'authentification des utilisateurs
     * et les redirige vers leur espace respectif selon leur rôle
     */

    // Démarrage de la session pour stocker les informations de l'utilisateur
    session_start();

    // Inclusion du fichier de configuration de la base de données
    require_once 'includes/config.php';

    // Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

    // Traitement du formulaire de connexion
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Récupération des données du formulaire
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        try {
            // Requête préparée pour éviter les injections SQL
            $sql = "SELECT id, nom, prenom, email, mot_de_passe, role FROM utilisateurs WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Vérification si l'utilisateur existe et si le mot de passe est correct
            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Stockage des informations de l'utilisateur en session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                
                // Débogage de la session
                echo "<!-- Debug Session after login:\n";
                var_dump($_SESSION);
                echo "\n -->";
                
                // Redirection selon le rôle de l'utilisateur
                switch($user['role']) {
                    case 'admin':
                        header('Location: /concours_anonyme/dashboard/admin.php');
                        break;
                    case 'candidat':
                        header('Location: /concours_anonyme/dashboard/candidat.php');
                        break;
                    case 'correcteur':
                        header('Location: /concours_anonyme/dashboard/correcteur.php');
                        break;
                }
                exit();
            } else {
                // Message d'erreur si l'authentification échoue
                $error = "Email ou mot de passe incorrect";
            }
        } catch (PDOException $e) {
            $error = "Une erreur est survenue lors de la connexion: " . $e->getMessage();
        }
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="auth-form-container">
                <h1>Connexion</h1>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Formulaire de connexion -->
                <form action="login.php" method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email utilisateur :</label>
                        <input type="text" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe :</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">Se connecter</button>
                        <a href="register.php" class="btn btn-secondary">Pas encore de compte ? S'inscrire</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>