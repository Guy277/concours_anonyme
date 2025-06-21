<?php
/**
 * Page de modification d'un utilisateur
 * Permet aux administrateurs de modifier les informations d'un utilisateur
 */

session_start();
require_once '../includes/config.php';

// Vérification des droits d'accès
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;

// Vérification de l'ID de l'utilisateur
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int)$_GET['id'];

// Récupération des informations de l'utilisateur
$sql = "SELECT * FROM utilisateurs WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: gerer.php');
    exit();
}

// Vérification des droits : seuls les admins peuvent modifier d'autres utilisateurs
// Les utilisateurs normaux ne peuvent modifier que leur propre profil
$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isEditingSelf = ($user_id == $currentUserId);

if (!$isAdmin && !$isEditingSelf) {
    header('Location: ../dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Vérifications de sécurité
    $isSuperAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
    $currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $isEditingSelf = ($user_id == $currentUserId);

    // Validation des données
    if (empty($nom) || empty($prenom) || empty($email) || empty($role)) {
        $error = "Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif (!in_array($role, ['admin', 'candidat', 'correcteur'])) {
        $error = "Le rôle sélectionné n'est pas valide.";
    } elseif (!$isAdmin && $role !== $user['role']) {
        $error = "Vous ne pouvez pas changer votre rôle.";
    } elseif ($isEditingSelf && $user['role'] === 'admin' && $role !== 'admin') {
        $error = "Vous ne pouvez pas changer votre propre rôle d'administrateur.";
    } elseif (!$isSuperAdmin && $user['role'] === 'admin' && $user_id != $currentUserId) {
        $error = "Seul le super administrateur peut modifier les autres administrateurs.";
    } else {
        // Vérification si l'email est déjà utilisé par un autre utilisateur
        $sql = "SELECT 1 FROM utilisateurs WHERE email = :email AND id != :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['email' => $email, 'user_id' => $user_id]);
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
                    $sql = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email, mot_de_passe = :mot_de_passe, role = :role WHERE id = :user_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'mot_de_passe' => $password_hash,
                        'role' => $role,
                        'user_id' => $user_id
                    ]);
                }
            } else {
                // Mise à jour sans changer le mot de passe
                $sql = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email, role = :role WHERE id = :user_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'role' => $role,
                    'user_id' => $user_id
                ]);
            }
            $success = "L'utilisateur a été modifié avec succès.";
            // Mise à jour des informations affichées
            $user['nom'] = $nom;
            $user['prenom'] = $prenom;
            $user['email'] = $email;
            $user['role'] = $role;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un utilisateur - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Modifier un utilisateur</h1>
                    
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
                                <label for="role">Rôle *</label>
                                <?php
                                $isSuperAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
                                $currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                                $isEditingSelf = ($user_id == $currentUserId);
                                $canChangeRole = $isAdmin && ($isSuperAdmin || !($user['role'] === 'admin' && $isEditingSelf));
                                ?>
                                <select id="role" name="role" required <?php echo !$canChangeRole ? 'disabled' : ''; ?>>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="candidat" <?php echo $user['role'] === 'candidat' ? 'selected' : ''; ?>>Candidat</option>
                                    <option value="correcteur" <?php echo $user['role'] === 'correcteur' ? 'selected' : ''; ?>>Correcteur</option>
                                </select>
                                <?php if (!$canChangeRole): ?>
                                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                                    <small style="color: #666;">Vous ne pouvez pas modifier votre propre rôle.</small>
                                <?php endif; ?>
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
                                <a href="gerer.php" class="btn btn-secondary" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-left: 10px; display: inline-block;">Annuler</a>
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