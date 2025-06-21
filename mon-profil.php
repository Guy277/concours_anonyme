<?php
/**
 * Page de profil utilisateur
 * Permet à tous les utilisateurs de modifier leur propre profil
 */

session_start();
require_once 'includes/config.php';

// Vérification de la connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = null;
$success = null;

// Récupération des informations de l'utilisateur connecté
$sql = "SELECT * FROM utilisateurs WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation des données
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = "Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } else {
        // Vérification si l'email est déjà utilisé par un autre utilisateur
        $sql = "SELECT 1 FROM utilisateurs WHERE email = :email AND id != :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['email' => $email, 'user_id' => $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Si un nouveau mot de passe est fourni
            if (!empty($password)) {
                if ($password !== $password_confirm) {
                    $error = "Les mots de passe ne correspondent pas.";
                } elseif (strlen($password) < 8) {
                    $error = "Le mot de passe doit contenir au moins 8 caractères.";
                } else {
                    // Mise à jour avec le nouveau mot de passe
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email, mot_de_passe = :mot_de_passe WHERE id = :user_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'mot_de_passe' => $password_hash,
                        'user_id' => $_SESSION['user_id']
                    ]);
                }
            } else {
                // Mise à jour sans changer le mot de passe
                $sql = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email WHERE id = :user_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'user_id' => $_SESSION['user_id']
                ]);
            }
            $success = "Votre profil a été mis à jour avec succès.";
            // Mise à jour des informations affichées
            $user['nom'] = $nom;
            $user['prenom'] = $prenom;
            $user['email'] = $email;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include 'includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1 class="profile-title-with-status">
                        <?php
                        $isSuperAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
                        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
                        ?>
                        <?php if ($isAdmin): ?>
                            <?php if ($isSuperAdmin): ?>
                                <span class="admin-badge super-admin">👑</span>
                            <?php else: ?>
                                <span class="admin-badge admin">⭐</span>
                            <?php endif; ?>
                        <?php elseif ($_SESSION['role'] === 'candidat'): ?>
                            <span class="admin-badge">📚</span>
                        <?php elseif ($_SESSION['role'] === 'correcteur'): ?>
                            <span class="admin-badge">📝</span>
                        <?php endif; ?>
                        Mon Profil
                    </h1>
                    
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
                                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="prenom">Prénom *</label>
                                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <input type="text" id="role" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" disabled>
                                <small style="color: #666;">Votre rôle ne peut pas être modifié.</small>
                            </div>
                            <h3>Changer le mot de passe</h3>
                            <div class="form-group">
                                <label for="password">Nouveau mot de passe</label>
                                <input type="password" id="password" name="password">
                                <small>Laissez vide pour conserver le mot de passe actuel</small>
                            </div>
                            <div class="form-group">
                                <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                                <input type="password" id="password_confirm" name="password_confirm">
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" style="background-color: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Enregistrer les modifications</button>
                                <a href="dashboard/<?php echo $_SESSION['role']; ?>.php" class="btn btn-secondary" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-left: 10px; display: inline-block;">Retour au tableau de bord</a>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
