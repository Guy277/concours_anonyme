<?php
/**
 * Page de création d'un nouvel utilisateur
 * Permet aux administrateurs de créer de nouveaux utilisateurs
 */

session_start();
require_once '../includes/config.php';

// Vérification des droits d'accès
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation des données
    if (empty($nom) || empty($prenom) || empty($email) || empty($role) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif (!in_array($role, ['admin', 'candidat', 'correcteur'])) {
        $error = "Le rôle sélectionné n'est pas valide.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérification si l'email est déjà utilisé
        $sql = "SELECT 1 FROM utilisateurs WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        if ($stmt->rowCount() > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Création de l'utilisateur
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (:nom, :prenom, :email, :mot_de_passe, :role)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'mot_de_passe' => $password_hash,
                'role' => $role
            ])) {
                $success = "L'utilisateur a été créé avec succès.";
                // Réinitialisation du formulaire
                $nom = $prenom = $email = $role = '';
            } else {
                $error = "Une erreur est survenue lors de la création de l'utilisateur.";
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
    <title>Créer un utilisateur - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Créer un utilisateur</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="form-section">
                        <form method="POST" class="user-form">
                            <div class="form-group">
                                <label for="nom">Nom *</label>
                                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="prenom">Prénom *</label>
                                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($prenom ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Rôle *</label>
                                <select id="role" name="role" required>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="candidat" <?php echo ($role ?? '') === 'candidat' ? 'selected' : ''; ?>>Candidat</option>
                                    <option value="correcteur" <?php echo ($role ?? '') === 'correcteur' ? 'selected' : ''; ?>>Correcteur</option>
                                </select>
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
                            <div class="form-actions">
                                <button type="submit" class="btn">Créer l'utilisateur</button>
                                <a href="<?php echo APP_URL; ?>/utilisateurs/gerer.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>