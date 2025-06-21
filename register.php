<?php
/**
 * Page d'inscription
 * Permet aux utilisateurs de créer un compte (candidat ou correcteur)
 */

session_start();

// Inclusion du fichier de configuration de la base de données
require_once 'includes/config.php';

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

$error = null;
$success = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validation des données
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($role)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!in_array($role, ['candidat', 'correcteur'])) {
        $error = "Rôle invalide.";
    } else {
        // Vérification si l'email existe déjà
        $sql = "SELECT 1 FROM utilisateurs WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Cette adresse email est déjà utilisée.";
        } else {
            // Hachage du mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertion de l'utilisateur
            $sql = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$nom, $prenom, $email, $password_hash, $role])) {
                $success = "Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.";
                // Redirection vers la page de connexion après 2 secondes
                header("refresh:2;url=login.php");
            } else {
                $error = "Une erreur est survenue lors de la création du compte.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="auth-form-container">
                <h1>Inscription</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($nom ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($prenom ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe *</label>
                        <input type="password" id="password" name="password" required>
                        <small>Minimum 8 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirmer le mot de passe *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" required>
                            <option value="">Choisir un rôle...</option>
                            <option value="candidat" <?php echo (isset($role) && $role === 'candidat') ? 'selected' : ''; ?>>Candidat</option>
                            <option value="correcteur" <?php echo (isset($role) && $role === 'correcteur') ? 'selected' : ''; ?>>Correcteur</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">S'inscrire</button>
                        <a href="login.php" class="btn btn-secondary">Déjà inscrit ? Se connecter</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>